<?php

namespace App\Http\Controllers;

use App\Models\Discount;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Models\Setting;
use App\Services\PromotionService;
use App\Services\RentalValidationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    public function index()
    {
        if (Setting::isStorefrontRentalDisabled()) {
            return redirect()->route('cart.index')
                ->with('error', Setting::storefrontRentalDisabledMessage())
                ->with('rental_disabled', true);
        }

        $customer = Auth::guard('customer')->user();

        // Check if customer is verified / not blocked
        if (! $customer->canRent()) {
            if ($customer->isBlocked()) {
                $msg = 'Akun Anda telah diblokir oleh admin dan tidak dapat melakukan checkout.';
                if ($customer->blocked_reason) {
                    $msg .= ' Alasan: '.$customer->blocked_reason;
                }

                return redirect()->route('customer.dashboard')->with('error', $msg);
            }

            return redirect()->route('customer.profile')
                ->with('error', 'Anda harus menyelesaikan verifikasi akun sebelum dapat melakukan checkout. Silakan lengkapi dokumen yang diperlukan.');
        }

        $cartItems = $customer->carts()->with(['productUnit.product', 'productUnit.variation'])->get();

        if ($cartItems->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        // Cart prices are stored gross (real) — the category discount is applied as
        // an explicit layer here, FIRST, then the promo layers stack on the
        // post-category base so the final total matches what is charged.
        $grossTotal = $cartItems->sum('subtotal');
        $subtotal = $grossTotal; // the summary's "Subtotal" line = real product prices

        $totalDays = $cartItems->sum('days');
        $totalDailyRate = $cartItems->sum('daily_rate'); // gross rates

        $categoryPercentage = $customer->getCategoryDiscountPercentage();
        $categoryDiscountAmount = $grossTotal * ($categoryPercentage / 100);
        $categoryName = $customer->category ? $customer->category->name : null;
        $baseAfterCategory = $grossTotal - $categoryDiscountAmount;

        // Calculate average days and (post-category) daily rate for promotions so
        // daily/date/coupon amounts are computed on the same base the customer pays.
        $avgDays = $cartItems->count() > 0 ? (int) round($totalDays / $cartItems->count()) : 0;
        $avgGrossRate = $cartItems->count() > 0 ? $totalDailyRate / $cartItems->count() : 0;
        $avgDailyRate = $avgGrossRate * (1 - ($categoryPercentage / 100));
        $startDate = $cartItems->min('start_date');

        $deposit = Rental::calculateDeposit($baseAfterCategory);

        // Calculate promotions using PromotionService
        $discountCode = session('checkout_discount_code');
        $promotions = PromotionService::calculatePromotions(
            $baseAfterCategory,
            $avgDays,
            $avgDailyRate,
            $startDate ? Carbon::parse($startDate) : null,
            $discountCode
        );

        $dailyDiscountAmount = $promotions['daily_discount_amount'];
        $dailyDiscountName = $promotions['daily_discount']?->name;
        $datePromotionAmount = $promotions['date_promotion_amount'];
        $datePromotionName = $promotions['date_promotion']?->name;
        $discountAmount = $promotions['code_discount_amount'];
        $totalDiscount = $promotions['total_discount'];

        // Final payable (before deposit) = real price − category − promos − coupon.
        $grandTotal = max(0, $grossTotal - $categoryDiscountAmount - $totalDiscount);

        // Clear invalid discount code
        if ($discountCode && ! $promotions['code_discount']) {
            session()->forget(['checkout_discount_code', 'checkout_discount_amount']);
        }

        // Get active promotions for display
        $activePromotions = PromotionService::getActivePromotionsSummary();

        return view('frontend.checkout.index', compact(
            'customer', 'cartItems', 'subtotal', 'deposit', 'discountAmount',
            'categoryDiscountAmount', 'categoryName', 'grossTotal', 'grandTotal',
            'dailyDiscountAmount', 'dailyDiscountName', 'datePromotionAmount', 'datePromotionName',
            'totalDiscount', 'activePromotions'
        ));
    }

    public function validateDiscount(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $code = $request->code;
        $customer = Auth::guard('customer')->user();
        $cartItems = $customer->carts;

        if ($cartItems->isEmpty()) {
            return response()->json(['valid' => false, 'message' => 'Cart is empty.']);
        }

        $discount = Discount::where('code', $code)
            ->where('is_active', true)
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->first();

        if (! $discount) {
            return response()->json(['valid' => false, 'message' => 'Kode diskon tidak valid atau kadaluarsa.']);
        }

        if ($discount->usage_limit && $discount->usage_count >= $discount->usage_limit) {
            return response()->json(['valid' => false, 'message' => 'Batas penggunaan kode diskon telah tercapai.']);
        }

        // Mirror the checkout summary stacking so the live total stays correct:
        // category discount first, then daily/date, then this coupon.
        $grossTotal = $cartItems->sum('subtotal');
        $categoryPercentage = $customer->getCategoryDiscountPercentage();
        $categoryDiscountAmount = $grossTotal * ($categoryPercentage / 100);
        $baseAfterCategory = $grossTotal - $categoryDiscountAmount;

        if ($baseAfterCategory < $discount->min_rental_amount) {
            return response()->json(['valid' => false, 'message' => 'Minimal total belanja Rp '.number_format($discount->min_rental_amount, 0, ',', '.').' belum terpenuhi.']);
        }

        $totalDays = $cartItems->sum('days');
        $totalDailyRate = $cartItems->sum('daily_rate');
        $avgDays = $cartItems->count() > 0 ? (int) round($totalDays / $cartItems->count()) : 0;
        $avgGrossRate = $cartItems->count() > 0 ? $totalDailyRate / $cartItems->count() : 0;
        $avgDailyRate = $avgGrossRate * (1 - ($categoryPercentage / 100));
        $startDate = $cartItems->min('start_date');

        $promotions = PromotionService::calculatePromotions(
            $baseAfterCategory,
            $avgDays,
            $avgDailyRate,
            $startDate ? Carbon::parse($startDate) : null,
            $code
        );

        if (! $promotions['code_discount']) {
            return response()->json(['valid' => false, 'message' => 'Kode diskon tidak dapat digunakan untuk pesanan ini.']);
        }

        $codeDiscountAmount = $promotions['code_discount_amount'];

        session(['checkout_discount_code' => $code]);
        session(['checkout_discount_amount' => $codeDiscountAmount]);

        // Final payable (before deposit) after every discount layer.
        $grandTotal = max(0, $grossTotal - $categoryDiscountAmount - $promotions['total_discount']);

        return response()->json([
            'valid' => true,
            'message' => 'Kode diskon berhasil digunakan!',
            'discount_amount' => $codeDiscountAmount,
            'grand_total' => $grandTotal,
        ]);
    }

    public function process(Request $request)
    {
        if (Setting::isStorefrontRentalDisabled()) {
            $msg = Setting::storefrontRentalDisabledMessage();
            if ($request->expectsJson()) {
                return response()->json(['message' => $msg, 'rental_disabled' => true], 403);
            }

            return redirect()->route('cart.index')->with('error', $msg)->with('rental_disabled', true);
        }

        $customer = Auth::guard('customer')->user();

        // Check if customer is verified / not blocked
        if (! $customer->canRent()) {
            if ($customer->isBlocked()) {
                $msg = 'Akun Anda telah diblokir oleh admin dan tidak dapat melakukan checkout.';
                if ($customer->blocked_reason) {
                    $msg .= ' Alasan: '.$customer->blocked_reason;
                }

                return redirect()->route('customer.dashboard')->with('error', $msg);
            }

            return redirect()->route('customer.profile')
                ->with('error', 'Anda harus menyelesaikan verifikasi akun sebelum dapat melakukan checkout.');
        }

        $request->validate([
            'notes' => 'nullable|string|max:500',
            'agree_terms' => 'required|accepted',
        ]);

        $cartItems = $customer->carts()->with(['productUnit.product'])->get();

        if ($cartItems->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        // Re-validate every cart date against operational schedule. Cart could have
        // been built before admin closed a day, so re-check at checkout.
        foreach ($cartItems as $item) {
            $errs = RentalValidationService::validateRentalPeriod(
                Carbon::parse($item->start_date),
                Carbon::parse($item->end_date)
            );
            if (! empty($errs)) {
                return redirect()->route('cart.index')
                    ->withErrors($errs)
                    ->with('error', 'Beberapa item di cart Anda berada di luar jadwal operasional. Silakan perbarui tanggal.');
            }
        }

        // Calculate global totals and averages for promotions. Cart prices are
        // stored gross; the category discount is applied FIRST as its own layer,
        // then the promo layers stack on the post-category base.
        $globalGross = $cartItems->sum('subtotal');
        $categoryPercentage = $customer->getCategoryDiscountPercentage();
        $globalCategoryDiscount = $globalGross * ($categoryPercentage / 100);
        $baseAfterCategory = $globalGross - $globalCategoryDiscount;
        $globalSubtotal = $globalGross; // proportion base for per-rental splits below

        $totalDays = $cartItems->sum('days');
        $totalDailyRate = $cartItems->sum('daily_rate'); // gross rates
        $avgDays = $cartItems->count() > 0 ? (int) round($totalDays / $cartItems->count()) : 0;
        $avgGrossRate = $cartItems->count() > 0 ? $totalDailyRate / $cartItems->count() : 0;
        $avgDailyRate = $avgGrossRate * (1 - ($categoryPercentage / 100));
        $startDate = $cartItems->min('start_date');

        $discountCode = session('checkout_discount_code');

        // Calculate all promotions using PromotionService
        $promotions = PromotionService::calculatePromotions(
            $baseAfterCategory,
            $avgDays,
            $avgDailyRate,
            $startDate ? Carbon::parse($startDate) : null,
            $discountCode
        );

        $discountId = $promotions['code_discount']?->id;
        $globalDiscountAmount = $promotions['code_discount_amount'];
        $globalDailyDiscountId = $promotions['daily_discount']?->id;
        $globalDailyDiscountAmount = $promotions['daily_discount_amount'];
        $globalDatePromotionId = $promotions['date_promotion']?->id;
        $globalDatePromotionAmount = $promotions['date_promotion_amount'];
        $globalTotalDiscount = $promotions['total_discount'];

        // Increment code discount usage if applicable
        if ($promotions['code_discount']) {
            $promotions['code_discount']->increment('usage_count');
        }

        // Group cart items by date range
        $groupedItems = $cartItems->groupBy(function ($item) {
            return $item->start_date->format('Y-m-d').'_'.$item->end_date->format('Y-m-d');
        });

        // PRE-FETCH availability data in batched queries (avoids N+1 in the loop below).
        // For each (product_id, start_date, end_date) tuple we need to know which units are
        // already reserved by overlapping rentals in the relevant statuses.
        $allProductIds = $cartItems->pluck('productUnit.product_id')->filter()->unique()->values()->all();

        // Pre-load all candidate units per product (excluding maintenance/retired) in one query.
        $unitsByProduct = \App\Models\ProductUnit::whereIn('product_id', $allProductIds)
            ->whereNotIn('status', [\App\Models\ProductUnit::STATUS_MAINTENANCE, \App\Models\ProductUnit::STATUS_RETIRED])
            ->get(['id', 'product_id'])
            ->groupBy('product_id');

        // Pre-load every potentially-blocking RentalItem (unit_id, start_date, end_date) for the
        // products we care about. Single query joining rentals.
        $blockingItems = \App\Models\RentalItem::query()
            ->select('rental_items.product_unit_id', 'rentals.start_date', 'rentals.end_date')
            ->join('rentals', 'rentals.id', '=', 'rental_items.rental_id')
            ->whereIn('rentals.status', [
                Rental::STATUS_QUOTATION,
                Rental::STATUS_CONFIRMED,
                Rental::STATUS_ACTIVE,
                Rental::STATUS_LATE_PICKUP,
                Rental::STATUS_LATE_RETURN,
            ])
            ->whereIn('rental_items.product_unit_id', $unitsByProduct->flatten()->pluck('id')->all() ?: [0])
            ->get();

        // Helper: given a date range, return set of unit_ids that overlap.
        $blockedUnitsFor = function ($startDate, $endDate) use ($blockingItems) {
            $start = Carbon::parse($startDate);
            $end = Carbon::parse($endDate);
            $blocked = [];
            foreach ($blockingItems as $row) {
                if (Carbon::parse($row->start_date)->lt($end) && Carbon::parse($row->end_date)->gt($start)) {
                    $blocked[$row->product_unit_id] = true;
                }
            }

            return $blocked;
        };

        DB::beginTransaction();

        try {
            $rentals = [];
            $reservedUnitIds = []; // Track units reserved in this transaction

            foreach ($groupedItems as $dateKey => $items) {
                $firstItem = $items->first();
                $subtotal = $items->sum('subtotal');

                // Category discount for this rental (gross group × category %).
                $rentalCategoryDiscount = $subtotal * ($categoryPercentage / 100);

                // Calculate proportional discount for this rental
                $rentalDiscount = 0;
                $rentalDailyDiscountAmount = 0;
                $rentalDatePromotionAmount = 0;
                if ($globalSubtotal > 0) {
                    $proportion = $subtotal / $globalSubtotal;
                    $rentalDiscount = $globalDiscountAmount * $proportion;
                    $rentalDailyDiscountAmount = $globalDailyDiscountAmount * $proportion;
                    $rentalDatePromotionAmount = $globalDatePromotionAmount * $proportion;
                }

                $rentalTotalDiscount = $rentalDiscount + $rentalDailyDiscountAmount + $rentalDatePromotionAmount + $rentalCategoryDiscount;

                // Deposit base is the post-category amount (preserves the previous
                // deposit value, which was computed off the baked-in net price).
                $deposit = Rental::calculateDeposit($subtotal - $rentalCategoryDiscount);

                // Create Quotation first
                $quotation = \App\Models\Quotation::create([
                    'user_id' => $customer->id,
                    'date' => now(),
                    'valid_until' => now()->addDays(7),
                    'status' => \App\Models\Quotation::STATUS_ON_QUOTE,
                    'subtotal' => $subtotal,
                    'tax' => 0,
                    'total' => $subtotal - $rentalTotalDiscount,
                    'notes' => $request->notes,
                ]);

                $rental = Rental::create([
                    'user_id' => $customer->id,
                    'start_date' => $firstItem->start_date,
                    'end_date' => $firstItem->end_date,
                    'status' => Rental::STATUS_QUOTATION,
                    'quotation_id' => $quotation->id,
                    'subtotal' => $subtotal,
                    'discount' => $rentalDiscount,
                    'discount_id' => $discountId,
                    'discount_code' => $discountCode,
                    'daily_discount_id' => $globalDailyDiscountId,
                    'daily_discount_amount' => $rentalDailyDiscountAmount,
                    'date_promotion_id' => $globalDatePromotionId,
                    'date_promotion_amount' => $rentalDatePromotionAmount,
                    'category_discount_amount' => $rentalCategoryDiscount,
                    'category_name' => $customer->category?->name,
                    'total' => $subtotal - $rentalTotalDiscount,
                    'deposit' => $deposit,
                    'notes' => $request->notes,
                ]);

                // Compute blocked units once for this date range (all items in group share dates).
                $blockedForRange = $blockedUnitsFor($firstItem->start_date, $firstItem->end_date);

                // Resolve final unit IDs for every cart item BEFORE inserting, so we can bulk-insert.
                $resolvedItems = []; // [['cart' => CartItem, 'unit_id' => int], ...]
                foreach ($items as $cartItem) {
                    $product = $cartItem->productUnit->product;
                    $candidateUnits = $unitsByProduct->get($product->id, collect());
                    $finalUnitId = null;

                    if (
                        ! isset($blockedForRange[$cartItem->product_unit_id])
                        && ! in_array($cartItem->product_unit_id, $reservedUnitIds, true)
                        && $candidateUnits->contains('id', $cartItem->product_unit_id)
                    ) {
                        $finalUnitId = $cartItem->product_unit_id;
                    } else {
                        foreach ($candidateUnits as $unit) {
                            if (
                                ! isset($blockedForRange[$unit->id])
                                && ! in_array($unit->id, $reservedUnitIds, true)
                            ) {
                                $finalUnitId = $unit->id;
                                break;
                            }
                        }
                    }

                    if (! $finalUnitId) {
                        throw new \Exception("Maaf, produk {$product->name} tidak lagi tersedia untuk tanggal yang dipilih (Unit penuh).");
                    }

                    $reservedUnitIds[] = $finalUnitId;
                    $resolvedItems[] = ['cart' => $cartItem, 'unit_id' => $finalUnitId];
                }

                // BULK INSERT rental items — bypasses the saving/created/saved observer chain
                // that would otherwise fire 81+ times. We replicate the necessary side-effects
                // (kit attachment, parent_item_id linking, rental totals refresh) once, in batch.
                $now = now();
                $insertRows = [];
                foreach ($resolvedItems as $r) {
                    $cartItem = $r['cart'];
                    $gross = $cartItem->daily_rate * $cartItem->days;
                    // Discount handling mirrors RentalItem::saving (no item-level discount on cart checkout).
                    $insertRows[] = [
                        'rental_id' => $rental->id,
                        'product_unit_id' => $r['unit_id'],
                        'daily_rate' => $cartItem->daily_rate,
                        'days' => $cartItem->days,
                        'subtotal' => max(0, $gross),
                        'discount' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                RentalItem::insert($insertRows);

                // Reload items so we have IDs for the post-processing steps below.
                $rental->load(['items.productUnit.kits']);

                // ── Post-processing: replicate observer side-effects in batch ───────────────
                // (a) Bulk-attach kits for every newly created rental item in ONE insert.
                $kitInsertRows = [];
                foreach ($rental->items as $item) {
                    if (! $item->productUnit) {
                        continue;
                    }
                    foreach ($item->productUnit->kits as $kit) {
                        if (in_array($kit->condition, ['broken', 'lost'], true)) {
                            continue;
                        }
                        $kitInsertRows[] = [
                            'rental_item_id' => $item->id,
                            'unit_kit_id' => $kit->id,
                            'condition_out' => $kit->condition,
                            'is_returned' => false,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }
                if (! empty($kitInsertRows)) {
                    \App\Models\RentalItemKit::insert($kitInsertRows);
                }

                // (b) Bulk-link parent_item_id (mirrors RentalItem::saved logic, but as one pass).
                $itemUnitIds = $rental->items->pluck('product_unit_id')->all();
                $parentUnitMap = \App\Models\UnitKit::whereIn('linked_unit_id', $itemUnitIds)
                    ->get(['unit_id', 'linked_unit_id'])
                    ->groupBy('linked_unit_id')
                    ->map(fn ($rows) => $rows->pluck('unit_id')->all())
                    ->all();

                $itemsByUnit = $rental->items->keyBy('product_unit_id');
                foreach ($rental->items as $childItem) {
                    $possibleParentUnitIds = $parentUnitMap[$childItem->product_unit_id] ?? [];
                    foreach ($possibleParentUnitIds as $parentUnitId) {
                        if ($itemsByUnit->has($parentUnitId)) {
                            $parentItem = $itemsByUnit->get($parentUnitId);
                            if ($parentItem->id !== $childItem->id) {
                                \App\Models\RentalItem::where('id', $childItem->id)
                                    ->update(['parent_item_id' => $parentItem->id]);
                            }
                            break;
                        }
                    }
                }

                // Create initial deliveries (Draft SJK & SJM)
                $rental->load('items.rentalItemKits');
                $rental->createDeliveries();

                // Trigger updated observer after items added to recalculate total
                $rental->touch();

                // FINAL AVAILABILITY CHECK
                // Ensure no conflicts were missed by the query builder logic (kit/unit cross-conflicts).
                $conflicts = $rental->checkAvailability();
                if (! empty($conflicts)) {
                    throw new \Exception('Beberapa item dalam pesanan Anda tidak tersedia karena bentrok dengan penyewaan lain (Kit/Unit Conflict). Silakan pilih tanggal atau unit lain.');
                }

                $rentals[] = $rental;
            }

            // Bulk-update product_unit statuses for all freshly reserved units.
            // Replicates ProductUnit::refreshStatus() side-effect that the per-item RentalItem
            // observer would normally trigger — done once for the whole batch instead of N times.
            // For a quotation rental, the status flips from 'available' → 'scheduled'.
            // We only touch units currently 'available' to avoid clobbering 'maintenance'/'rented'/'retired'.
            if (! empty($reservedUnitIds)) {
                \App\Models\ProductUnit::whereIn('id', $reservedUnitIds)
                    ->where('status', \App\Models\ProductUnit::STATUS_AVAILABLE)
                    ->update(['status' => \App\Models\ProductUnit::STATUS_SCHEDULED]);
            }

            // Clear cart and session
            $customer->carts()->delete();
            session()->forget(['checkout_discount_code', 'checkout_discount_amount']);

            DB::commit();

            return redirect()->route('checkout.success', ['rental' => $rentals[0]->id])
                ->with('success', 'Your booking has been submitted successfully!');

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Something went wrong. Please try again. '.$e->getMessage());
        }
    }

    public function success(Rental $rental)
    {
        $customer = Auth::guard('customer')->user();

        if ((int) $rental->user_id !== (int) $customer->id) {
            abort(403);
        }

        $rental->load(['items.productUnit.product']);

        $warehousePhone = \App\Models\Setting::get('warehouse_whatsapp_number', \App\Models\Setting::get('whatsapp_number'));
        $defaultTemplate = "Halo admin warehouse, saya [customer_name] ingin konfirmasi booking [rental_code].\n\nMohon konfirmasi booking:\n[admin_url]";
        [$dateSearch, $dateReplace] = \App\Helpers\WhatsAppHelper::rentalDatePlaceholders($rental);
        $waMessage = str_replace(
            array_merge(['[customer_name]', '[rental_code]', '[admin_url]'], $dateSearch),
            array_merge([$customer->name, $rental->rental_code, route('filament.admin.resources.rentals.view', $rental)], $dateReplace),
            \App\Models\Setting::get('warehouse_wa_template', $defaultTemplate)
        );
        $waLink = \App\Helpers\WhatsAppHelper::getLink($warehousePhone, $waMessage);

        $permitLink = \App\Models\Setting::get('permit_document_link', '#');
        $checklistPdfUrl = \Illuminate\Support\Facades\URL::signedRoute('public-documents.rental.checklist', ['rental' => $rental]);

        return view('frontend.checkout.success', compact('rental', 'waLink', 'permitLink', 'checklistPdfUrl'));
    }
}
