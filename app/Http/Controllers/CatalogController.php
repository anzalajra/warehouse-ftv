<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Models\Rental;
use App\Models\ProductUnit;
use App\Models\Setting;
use App\Services\RentalValidationService;
use Illuminate\Http\Request;
use Carbon\Carbon;

use Illuminate\Support\Facades\Auth;

class CatalogController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['category', 'units'])
            ->where('is_active', true)
            ->visibleForCustomer(Auth::guard('customer')->user())
            ->whereHas('category', fn ($q) => $q->where('slug', '!=', 'accessories-kits'));

        // Filter by date range availability
        if ($request->filled('start_date') && $request->filled('end_date')) {
            try {
                $pickupTime = $request->input('pickup_time', '09:00');
                $returnTime = $request->input('return_time', '09:00');
                
                $startDate = Carbon::parse($request->start_date . ' ' . $pickupTime);
                $endDate = Carbon::parse($request->end_date . ' ' . $returnTime);
                
                $bufferHours = (int) Setting::get('rental_buffer_time', 0);

                $query->whereHas('units', function ($unitQuery) use ($startDate, $endDate, $bufferHours) {
                    $unitQuery->whereNotIn('status', [ProductUnit::STATUS_MAINTENANCE, ProductUnit::STATUS_RETIRED])
                        ->whereDoesntHave('rentalItems', function ($rentalQuery) use ($startDate, $endDate, $bufferHours) {
                            $rentalQuery->whereHas('rental', function ($rQuery) use ($startDate, $endDate, $bufferHours) {
                                $rQuery->whereIn('status', [
                                    Rental::STATUS_QUOTATION,
                                    Rental::STATUS_CONFIRMED,
                                    Rental::STATUS_ACTIVE,
                                    Rental::STATUS_LATE_PICKUP,
                                    Rental::STATUS_LATE_RETURN
                                ])->where(function ($overlap) use ($startDate, $endDate, $bufferHours) {
                                    $overlap->where('start_date', '<', $endDate->copy()->addHours($bufferHours))
                                            ->whereRaw("DATE_ADD(end_date, INTERVAL ? HOUR) > ?", [$bufferHours, $startDate]);
                                });
                            });
                        });
                });
            } catch (\Exception $e) {
                // Invalid date format, ignore filter
            }
        }

        // Filter by category
        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }

        // Search
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        // Sort
        $sort = $request->get('sort', 'name');
        switch ($sort) {
            case 'price_low':
                $query->orderBy('daily_rate', 'asc');
                break;
            case 'price_high':
                $query->orderBy('daily_rate', 'desc');
                break;
            case 'newest':
                $query->orderBy('created_at', 'desc');
                break;
            default:
                $query->orderBy('name', 'asc');
        }

        $products = $query->paginate(12)->withQueryString();
        $categories = Category::visibleOnStorefront()->get();

        $operationalDays = array_map('strval', json_decode(Setting::get('operational_days'), true) ?? ['1', '2', '3', '4', '5', '6', '0']);
        $holidays = json_decode(Setting::get('holidays'), true) ?? [];

        return view('frontend.catalog.index', compact('products', 'categories', 'operationalDays', 'holidays'));
    }

    public function show(Product $product)
    {
        $product->load(['category', 'units.kits', 'variations']);

        // Show total units that are not retired or in maintenance
        $availableUnits = $product->units()
            ->whereNotIn('status', [ProductUnit::STATUS_MAINTENANCE, ProductUnit::STATUS_RETIRED])
            ->where(function ($q) {
                $q->whereNull('warehouse_id')
                  ->orWhereHas('warehouse', function ($wq) {
                      $wq->where('is_active', true)
                         ->where('is_available_for_rental', true);
                  });
            })
            ->get();

        $availability = $product->getAvailabilityCalendar();
        $bookedDates = $availability['booked'];
        $partialDates = $availability['partial'];

        $relatedProducts = Product::where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('is_active', true)
            ->limit(4)
            ->get();

        $operationalDays     = array_map('strval', json_decode(Setting::get('operational_days'), true) ?? ['1', '2', '3', '4', '5', '6', '0']);
        $holidays            = json_decode(Setting::get('holidays'), true) ?? [];
        $operationalSchedule = json_decode(Setting::get('operational_schedule'), true) ?? [];

        return view('frontend.catalog.show', compact('product', 'availableUnits', 'bookedDates', 'partialDates', 'relatedProducts', 'operationalDays', 'holidays', 'operationalSchedule'));
    }

    public function checkAvailability(Request $request, ProductUnit $unit)
    {
        $request->validate([
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after:start_date',
        ]);

        $startDate = \Carbon\Carbon::parse($request->start_date);
        $endDate = \Carbon\Carbon::parse($request->end_date);

        $scheduleErrors = RentalValidationService::validateRentalPeriod($startDate, $endDate);
        if (! empty($scheduleErrors)) {
            return response()->json([
                'available' => false,
                'message'   => reset($scheduleErrors),
                'errors'    => $scheduleErrors,
            ], 422);
        }

        $bufferHours = (int) Setting::get('rental_buffer_time', 0);

        // Check if unit is available for the given dates
        $isAvailable = !$unit->rentalItems()
            ->whereHas('rental', function ($query) use ($startDate, $endDate, $bufferHours) {
                $query->whereIn('status', [Rental::STATUS_QUOTATION, Rental::STATUS_CONFIRMED, Rental::STATUS_ACTIVE, Rental::STATUS_LATE_PICKUP, Rental::STATUS_LATE_RETURN])
                    ->where(function ($q) use ($startDate, $endDate, $bufferHours) {
                        $q->where('start_date', '<', $endDate->copy()->addHours($bufferHours))
                          ->whereRaw("DATE_ADD(end_date, INTERVAL ? HOUR) > ?", [$bufferHours, $startDate]);
                    });
            })
            ->exists();

        $days = max(1, $startDate->diffInDays($endDate));
        $totalPrice = $unit->product->daily_rate * $days;

        return response()->json([
            'available' => $isAvailable,
            'days' => $days,
            'daily_rate' => $unit->product->daily_rate,
            'total_price' => $totalPrice,
        ]);
    }
}