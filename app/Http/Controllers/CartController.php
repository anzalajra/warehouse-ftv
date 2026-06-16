<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Rental;
use App\Models\Setting;
use App\Services\RentalValidationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CartController extends Controller
{
    private function rejectIfRentalDisabled(Request $request)
    {
        if (! Setting::isStorefrontRentalDisabled()) {
            return null;
        }
        $msg = Setting::storefrontRentalDisabledMessage();
        if ($request->expectsJson()) {
            return response()->json(['message' => $msg, 'rental_disabled' => true], 403);
        }

        return back()->with('error', $msg);
    }

    public function index()
    {
        $customer = Auth::guard('customer')->user();
        $cartItems = $customer->carts()->with(['productUnit.product', 'productUnit.variation'])->get();

        // Cart prices are now stored gross (no baked-in category discount), so the
        // item subtotals ARE the gross total. The category discount is applied as
        // an explicit percentage line here.
        $grossTotal = $cartItems->sum('subtotal');

        $discountPercentage = $customer->getCategoryDiscountPercentage();
        $categoryName = $customer->category ? $customer->category->name : null;

        $discountAmount = $grossTotal * ($discountPercentage / 100);
        $netTotal = $grossTotal - $discountAmount;

        $deposit = Rental::calculateDeposit($netTotal);
        $canCheckout = $customer->canRent();

        return view('frontend.cart.index', compact(
            'cartItems', 'netTotal', 'grossTotal', 'discountAmount',
            'deposit', 'canCheckout', 'discountPercentage', 'categoryName'
        ));
    }

    public function add(Request $request)
    {
        if ($r = $this->rejectIfRentalDisabled($request)) {
            return $r;
        }

        $customer = Auth::guard('customer')->user();

        // Check if customer is verified / not blocked
        if (! $customer->canRent()) {
            if ($customer->isBlocked()) {
                $msg = 'Akun Anda telah diblokir oleh admin dan tidak dapat melakukan rental.';
                if ($customer->blocked_reason) {
                    $msg .= ' Alasan: '.$customer->blocked_reason;
                }
            } else {
                $msg = 'Anda harus menyelesaikan verifikasi akun sebelum dapat melakukan rental. Silakan lengkapi dokumen di halaman Profile.';
            }
            if ($request->expectsJson()) {
                return response()->json(['message' => $msg], 403);
            }

            return back()->with('error', $msg);
        }

        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'variation_id' => 'nullable|exists:product_variations,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after:start_date',
            'quantity' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Validasi gagal.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            return back()->withErrors($validator)->withInput();
        }

        $quantity = $request->input('quantity', 1);

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        $scheduleErrors = RentalValidationService::validateRentalPeriod($startDate, $endDate);
        if (! empty($scheduleErrors)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Jadwal tidak sesuai dengan jam operasional.',
                    'errors' => $scheduleErrors,
                ], 422);
            }

            return back()->withErrors($scheduleErrors)->withInput();
        }

        $product = \App\Models\Product::findOrFail($request->product_id);

        if ($request->filled('variation_id')) {
            $variation = \App\Models\ProductVariation::findOrFail($request->variation_id);
            // Relaxed comparison to avoid string/int type mismatch
            if ($variation->product_id != $product->id) {
                $msg = 'Variasi tidak valid untuk produk ini.';
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => $msg,
                        'debug' => [
                            'var_pid' => $variation->product_id,
                            'req_pid' => $product->id,
                        ],
                    ], 422);
                }

                return back()->with('error', $msg);
            }
        }

        // Check product visibility for customer
        if (! $product->isVisibleForCustomer($customer)) {
            $msg = 'Produk ini tidak tersedia untuk kategori akun Anda.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $msg], 403);
            }

            return back()->with('error', $msg);
        }

        $days = max(1, $startDate->diffInDays($endDate));

        // Check for existing cart items and handle date synchronization
        $cartItems = $customer->carts()->with('productUnit.product')->get();
        $firstItem = $cartItems->first();

        $updates = [];
        $conflicts = [];
        $needsSync = false;

        if ($firstItem) {
            // Check if dates are different (using timestamp comparison for precision)
            if ($firstItem->start_date->ne($startDate) || $firstItem->end_date->ne($endDate)) {
                $needsSync = true;

                foreach ($cartItems as $item) {
                    $p = $item->productUnit->product;
                    // Check availability for new dates
                    // We can reuse the current unit if it's available, otherwise find another unit of same product
                    $newUnit = $p->findAvailableUnit($startDate, $endDate);

                    if ($newUnit) {
                        $updates[] = [
                            'cart_item' => $item,
                            'new_unit_id' => $newUnit->id,
                        ];
                    } else {
                        $conflicts[] = $p->name;
                    }
                }
            }
        }

        // Handle Conflicts
        if (! empty($conflicts)) {
            if (! $request->boolean('confirm_changes')) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'status' => 'conflict',
                        'conflicts' => $conflicts,
                        'message' => 'Beberapa item di keranjang tidak tersedia untuk tanggal baru.',
                    ], 409);
                }

                // Fallback for non-AJAX (though we should prioritize AJAX)
                return back()->with('error', 'Konflik ketersediaan item di keranjang. Harap gunakan fitur sinkronisasi.');
            }

            // Remove conflicting items
            foreach ($cartItems as $item) {
                if (in_array($item->productUnit->product->name, $conflicts)) {
                    $item->delete();
                }
            }
        }

        // Apply Updates (Sync Dates)
        if ($needsSync) {
            foreach ($updates as $update) {
                $update['cart_item']->update([
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'days' => $days,
                    'product_unit_id' => $update['new_unit_id'],
                    'subtotal' => $update['cart_item']->daily_rate * $days,
                ]);
            }
        }

        // Get fresh cart unit IDs after sync/cleanup
        $currentCartUnitIds = $customer->carts()->pluck('product_unit_id')->toArray();

        // Find ALL available units for this product and date range
        $allAvailableUnits = $product->findAvailableUnits($startDate, $endDate);

        // Filter by variation if selected
        if ($request->filled('variation_id')) {
            $allAvailableUnits = $allAvailableUnits->where('product_variation_id', $request->variation_id);
        }

        // Filter out units already in cart
        $availableForAdd = $allAvailableUnits->whereNotIn('id', $currentCartUnitIds);

        // Check if we have enough units
        if ($availableForAdd->count() < $quantity) {
            $msg = 'Maaf, hanya tersedia '.$availableForAdd->count().' unit tambahan untuk tanggal yang dipilih.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $msg], 422);
            }

            return back()->with('error', $msg);
        }

        // Add the requested quantity
        $unitsToAdd = $availableForAdd->take($quantity);

        // Store the GROSS (real) daily rate — the customer-category discount is no
        // longer baked into the price here; it is applied as an explicit discount
        // line at cart/checkout time (see CartController::index + CheckoutController).
        $dailyRate = $product->daily_rate;

        if ($request->filled('variation_id')) {
            $variation = \App\Models\ProductVariation::find($request->variation_id);
            if ($variation && $variation->daily_rate) {
                $dailyRate = $variation->daily_rate;
            }
        }

        foreach ($unitsToAdd as $unit) {
            Cart::create([
                'user_id' => $customer->id,
                'product_unit_id' => $unit->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'days' => $days,
                'daily_rate' => $dailyRate,
                'subtotal' => $dailyRate * $days,
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Item berhasil ditambahkan ke keranjang.']);
        }

        return redirect()->route('cart.index')->with('success', 'Item berhasil ditambahkan ke keranjang.');
    }

    public function updateAll(Request $request)
    {
        if ($r = $this->rejectIfRentalDisabled($request)) {
            return $r;
        }

        $customer = Auth::guard('customer')->user();

        $request->validate([
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after:start_date',
        ]);

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        $scheduleErrors = RentalValidationService::validateRentalPeriod($startDate, $endDate);
        if (! empty($scheduleErrors)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Jadwal tidak sesuai dengan jam operasional.',
                    'errors' => $scheduleErrors,
                ], 422);
            }

            return back()->withErrors($scheduleErrors)->withInput();
        }

        $days = max(1, $startDate->diffInDays($endDate));

        $cartItems = $customer->carts()->with('productUnit.product')->get();
        $errors = [];
        $updatedCount = 0;
        $reservedUnitIds = [];

        foreach ($cartItems as $item) {
            $product = $item->productUnit->product;

            // Try to find a unit available for the NEW dates
            // We must exclude units that have already been assigned to other items in this update loop
            $candidates = $product->findAvailableUnits($startDate, $endDate);
            $unit = $candidates->whereNotIn('id', $reservedUnitIds)->first();

            if (! $unit) {
                $errors[] = "Produk {$product->name} tidak tersedia untuk tanggal baru.";

                continue;
            }

            // Reserve this unit
            $reservedUnitIds[] = $unit->id;

            $item->update([
                'product_unit_id' => $unit->id, // Switch unit if necessary
                'start_date' => $startDate,
                'end_date' => $endDate,
                'days' => $days,
                'subtotal' => $product->daily_rate * $days,
            ]);
            $updatedCount++;
        }

        if (count($errors) > 0) {
            return back()->with('error', implode(' ', $errors).' Item lain berhasil diperbarui.');
        }

        return back()->with('success', 'Semua item di keranjang berhasil diperbarui ke tanggal baru.');
    }

    public function update(Request $request, Cart $cart)
    {
        if ($r = $this->rejectIfRentalDisabled($request)) {
            return $r;
        }

        $customer = Auth::guard('customer')->user();

        if ((int) $cart->user_id !== (int) $customer->id) {
            abort(403);
        }

        $request->validate([
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after:start_date',
        ]);

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        $scheduleErrors = RentalValidationService::validateRentalPeriod($startDate, $endDate);
        if (! empty($scheduleErrors)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Jadwal tidak sesuai dengan jam operasional.',
                    'errors' => $scheduleErrors,
                ], 422);
            }

            return back()->withErrors($scheduleErrors)->withInput();
        }

        $cart->update([
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
        $cart->recalculate();

        return back()->with('success', 'Cart updated.');
    }

    public function remove(Cart $cart)
    {
        $customer = Auth::guard('customer')->user();

        if ((int) $cart->user_id !== (int) $customer->id) {
            abort(403);
        }

        $cart->delete();

        return back()->with('success', 'Item removed from cart.');
    }

    public function updateQuantity(Request $request)
    {
        if ($r = $this->rejectIfRentalDisabled($request)) {
            return $r;
        }

        $customer = Auth::guard('customer')->user();

        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'variation_id' => 'nullable|exists:product_variations,id',
        ]);

        $productId = $request->product_id;
        $variationId = $request->variation_id;
        $newQuantity = $request->quantity;

        // Get current cart items for this product and variation
        $cartItems = $customer->carts()
            ->whereHas('productUnit', function ($query) use ($productId, $variationId) {
                $query->where('product_id', $productId);
                if ($variationId) {
                    $query->where('product_variation_id', $variationId);
                } else {
                    $query->whereNull('product_variation_id');
                }
            })
            ->get();

        $currentQuantity = $cartItems->count();

        if ($currentQuantity == $newQuantity) {
            return back();
        }

        if ($newQuantity < $currentQuantity) {
            // Remove items (LIFO)
            $toRemove = $currentQuantity - $newQuantity;
            $cartItems->sortByDesc('created_at')->take($toRemove)->each->delete();

            return back()->with('success', 'Quantity updated.');
        }

        if ($newQuantity > $currentQuantity) {
            // Add items
            $toAdd = $newQuantity - $currentQuantity;
            $firstItem = $cartItems->first();

            if (! $firstItem) {
                return back()->with('error', 'Item not found.');
            }

            $product = $firstItem->productUnit->product;
            $startDate = $firstItem->start_date;
            $endDate = $firstItem->end_date;
            $days = $firstItem->days;
            $dailyRate = $firstItem->daily_rate;

            // Find available units excluding current cart items
            $currentCartUnitIds = $customer->carts()->pluck('product_unit_id')->toArray();
            $allAvailableUnits = $product->findAvailableUnits($startDate, $endDate);

            // Filter by variation
            if ($variationId) {
                $allAvailableUnits = $allAvailableUnits->where('product_variation_id', $variationId);
            } else {
                $allAvailableUnits = $allAvailableUnits->whereNull('product_variation_id');
            }

            $availableForAdd = $allAvailableUnits->whereNotIn('id', $currentCartUnitIds);

            if ($availableForAdd->count() < $toAdd) {
                return back()->with('error', 'Hanya tersedia '.$availableForAdd->count().' unit tambahan untuk periode ini.');
            }

            $unitsToAdd = $availableForAdd->take($toAdd);

            foreach ($unitsToAdd as $unit) {
                Cart::create([
                    'user_id' => $customer->id,
                    'product_unit_id' => $unit->id,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'days' => $days,
                    'daily_rate' => $dailyRate,
                    'subtotal' => $dailyRate * $days,
                ]);
            }

            return back()->with('success', 'Quantity updated.');
        }
    }

    public function removeProduct(Request $request)
    {
        $customer = Auth::guard('customer')->user();

        $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        $customer->carts()
            ->whereHas('productUnit', function ($query) use ($request) {
                $query->where('product_id', $request->product_id);
            })
            ->delete();

        return back()->with('success', 'Product removed from cart.');
    }

    public function clear()
    {
        $customer = Auth::guard('customer')->user();
        $customer->carts()->delete();

        return back()->with('success', 'Cart cleared.');
    }
}
