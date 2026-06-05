<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Setting;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Models\ProductUnit;
use Carbon\Carbon;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'brand_id',
        'name',
        'slug',
        'description',
        'daily_rate',
        'buffer_time',
        'late_fee_daily_amount',
        'image',
        'is_active',
        'is_taxable',
        'price_includes_tax',
        'is_visible_on_frontend',
    ];

    protected $casts = [
        'daily_rate' => 'decimal:2',
        'buffer_time' => 'integer',
        'late_fee_daily_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'is_taxable' => 'boolean',
        'price_includes_tax' => 'boolean',
        'is_visible_on_frontend' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($product) {
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
        });
    }

    /**
     * Scope a query to only include products visible on frontend.
     */
    public function scopeVisibleOnFrontend(Builder $query): Builder
    {
        return $query->where('is_visible_on_frontend', true);
    }

    /**
     * Check if the product is fully under maintenance
     * Returns true ONLY if all units are in maintenance/broken/lost
     * Returns false if there is at least 1 unit that is NOT maintenance (even if rented)
     */
    public function isFullyUnderMaintenance(): bool
    {
        $totalUnits = $this->units()->count();
        
        if ($totalUnits === 0) {
            return false;
        }

        $maintenanceUnits = $this->units()
            ->where(function ($query) {
                $query->where('status', ProductUnit::STATUS_MAINTENANCE)
                      ->orWhereIn('condition', ['broken', 'lost']);
            })
            ->count();

        return $totalUnits === $maintenanceUnits;
    }

    // Relasi ke Category
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function excludedCustomerCategories(): BelongsToMany
    {
        return $this->belongsToMany(CustomerCategory::class, 'product_visibility_exclusions', 'product_id', 'customer_category_id');
    }

    // Relasi ke Brand
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    // Relasi ke ProductUnit
    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    // Relasi ke ProductComponent (Sebagai Child)
    public function parentComponents(): HasMany
    {
        return $this->hasMany(ProductComponent::class, 'child_product_id');
    }

    public function isBundle(): bool
    {
        return $this->components()->exists();
    }

    public function rentalItems(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(RentalItem::class, ProductUnit::class);
    }

    public function variations(): HasMany
    {
        return $this->hasMany(ProductVariation::class);
    }

    /**
     * Check if the product has any variations
     */
    public function hasVariations(): bool
    {
        return $this->variations()->exists();
    }


    public function components(): HasMany
    {
        return $this->hasMany(ProductComponent::class, 'parent_product_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ProductTag::class, 'product_product_tag')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    // Relasi ke Product (Sebagai Parent)
    public function parentProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_components', 'child_product_id', 'parent_product_id');
    }

    public function scopeVisibleForCustomer(Builder $query, $customer = null)
    {
        // Base visibility: active and visible on frontend
        $query->where('is_active', true)->where('is_visible_on_frontend', true);

        // If customer is logged in and has a category, filter exclusions
        if ($customer && isset($customer->customer_category_id)) {
            $query->whereDoesntHave('excludedCustomerCategories', function ($q) use ($customer) {
                $q->where('customer_categories.id', $customer->customer_category_id);
            });
        }

        return $query;
    }

    /**
     * Check if product is visible for customer (instance method)
     */
    public function isVisibleForCustomer($customer = null): bool
    {
        if (!$this->is_active || !$this->is_visible_on_frontend) {
            return false;
        }

        if ($customer && isset($customer->customer_category_id)) {
            if ($this->excludedCustomerCategories()->where('customer_categories.id', $customer->customer_category_id)->exists()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get availability calendar data
     * Returns ['booked' => [], 'partial' => []]
     */
    public function getAvailabilityCalendar(): array
    {
        $bookedDates = [];
        $partialDates = [];
        
        $unitsCount = $this->units()
            ->whereNotIn('status', [ProductUnit::STATUS_MAINTENANCE, ProductUnit::STATUS_RETIRED])
            ->count();
        
        if ($unitsCount === 0) {
            $start = now();
            $end = now()->addYear();
            while ($start <= $end) {
                $bookedDates[] = $start->format('Y-m-d');
                $start->addDay();
            }
            return ['booked' => $bookedDates, 'partial' => []];
        }

        $unitIds = $this->units()->pluck('id');
        $bufferHours = (int) Setting::get('rental_buffer_time', 0);
        
        $rentals = RentalItem::whereIn('product_unit_id', $unitIds)
            ->whereHas('rental', function ($query) {
                $query->whereNotIn('status', [Rental::STATUS_COMPLETED, Rental::STATUS_CANCELLED])
                    ->whereIn('status', [
                        Rental::STATUS_QUOTATION,
                        Rental::STATUS_CONFIRMED,
                        Rental::STATUS_ACTIVE,
                        Rental::STATUS_LATE_PICKUP,
                        Rental::STATUS_LATE_RETURN
                    ])
                    ->where('end_date', '>=', now()->startOfDay());
            })
            ->with(['rental' => function ($query) {
                $query->select('id', 'start_date', 'end_date');
            }, 'productUnit.kits'])
            ->get();

        // 4. Map rentals to My Units
        $unitRentals = [];
        
        // Pre-compute kit usage for my units (Optimized)
        $unitKits = \App\Models\UnitKit::whereIn('unit_id', $unitIds)
            ->whereNotNull('linked_unit_id')
            ->select('unit_id', 'linked_unit_id')
            ->get();
            
        $unitKitMap = []; // UnitID -> [KitID, KitID]
        foreach ($unitKits as $uk) {
            $unitKitMap[$uk->unit_id][] = $uk->linked_unit_id;
        }
        
        // Pre-compute reverse map: KitID -> [UnitID, UnitID] (My units using this kit)
        $kitUnitMap = [];
        foreach ($unitKitMap as $uId => $kIds) {
            foreach ($kIds as $kId) {
                $kitUnitMap[$kId][] = $uId;
            }
        }

        // Pre-compute map: ParentID -> [MyUnitID] (Parents that contain My Units)
        $parentOfMyUnitMap = [];
        $myUnitParents = \App\Models\UnitKit::whereIn('linked_unit_id', $unitIds)
            ->select('unit_id', 'linked_unit_id')
            ->get();

        $dailyStats = []; // 'Y-m-d' => ['full' => 0, 'partial' => 0]
        
        foreach ($rentals as $item) {
            $rentalStart = $item->rental->start_date;
            $rentalEnd = $item->rental->end_date->copy()->addHours($bufferHours);
            
            $periodStart = $rentalStart->copy()->startOfDay();
            $periodEnd = $rentalEnd->copy()->startOfDay();
            
            $current = $periodStart->copy();
            
            while ($current <= $periodEnd) {
                $dateStr = $current->format('Y-m-d');
                $dayStart = $current->copy()->startOfDay();
                $dayEnd = $current->copy()->endOfDay();

                // Skip if rental ends exactly at start of day (0 duration on this day)
                if ($rentalEnd->eq($dayStart)) {
                    $current->addDay();
                    continue;
                }
                
                $isFullDay = ($rentalStart->lte($dayStart) && $rentalEnd->gte($dayEnd));
                
                if (!isset($dailyStats[$dateStr])) {
                    $dailyStats[$dateStr] = ['full' => 0, 'partial' => 0];
                }
                
                if ($isFullDay) {
                    $dailyStats[$dateStr]['full']++;
                } else {
                    $dailyStats[$dateStr]['partial']++;
                }
                
                $current->addDay();
            }
        }

        foreach ($dailyStats as $date => $stats) {
            $totalOccupancy = $stats['full'] + $stats['partial'];
            
            if ($stats['full'] >= $unitsCount) {
                $bookedDates[] = $date;
            } elseif ($totalOccupancy >= $unitsCount) {
                $partialDates[] = $date;
            }
        }
        
        return ['booked' => $bookedDates, 'partial' => $partialDates];
    }

    /**
     * Find available units for a specific date range
     * Returns a collection of available ProductUnits
     */
    public function findAvailableUnits($startDate, $endDate)
    {
        $startDate = \Carbon\Carbon::parse($startDate);
        $endDate = \Carbon\Carbon::parse($endDate);

        return $this->units()
            ->whereNotIn('status', [ProductUnit::STATUS_MAINTENANCE, ProductUnit::STATUS_RETIRED])
            ->whereDoesntHave('rentalItems', function ($query) use ($startDate, $endDate) {
                $query->whereHas('rental', function ($q) use ($startDate, $endDate) {
                    $q->whereIn('status', [
                        Rental::STATUS_QUOTATION,
                        Rental::STATUS_CONFIRMED,
                        Rental::STATUS_ACTIVE,
                        Rental::STATUS_LATE_PICKUP,
                        Rental::STATUS_LATE_RETURN
                    ])->where(function ($overlap) use ($startDate, $endDate) {
                        $overlap->where('start_date', '<', $endDate)
                                ->where('end_date', '>', $startDate);
                    });
                });
            })
            ->get();
    }

    /**
     * Find an available unit for a specific date range
     */
    public function findAvailableUnit($startDate, $endDate)
    {
        return $this->findAvailableUnits($startDate, $endDate)->first();
    }
}
