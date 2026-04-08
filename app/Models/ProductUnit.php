<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductUnit extends Model
{
    protected $fillable = [
        'product_id',
        'product_variation_id',
        'warehouse_id',
        'serial_number',
        'condition',
        'status',
        'purchase_date',
        'purchase_price',
        'residual_value',
        'useful_life',
        'notes',
        'last_checked_at',
        'maintenance_status',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'purchase_price' => 'decimal:2',
        'residual_value' => 'decimal:2',
        'useful_life' => 'integer',
        'last_checked_at' => 'datetime',
    ];

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_RENTED = 'rented';
    public const STATUS_MAINTENANCE = 'maintenance';
    public const STATUS_RETIRED = 'retired';

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class, 'product_variation_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function kits(): HasMany
    {
        return $this->hasMany(UnitKit::class, 'unit_id');
    }

    public function rentalItems(): HasMany
    {
        return $this->hasMany(RentalItem::class);
    }

    public function linkedInKits(): HasMany
    {
        return $this->hasMany(UnitKit::class, 'linked_unit_id');
    }

    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class);
    }

    public function getCurrentValueAttribute(): float
    {
        $cost = $this->purchase_price ?? 0;
        if ($cost == 0) return 0;
        
        $residual = $this->residual_value ?? 0;
        $lifeMonths = $this->useful_life ?? 60;
        $purchaseDate = $this->purchase_date;
        
        if (!$purchaseDate) return $cost;
        
        // Calculate full months passed
        $ageMonths = $purchaseDate->diffInMonths(now());
        
        if ($ageMonths >= $lifeMonths) {
            return $residual;
        }
        
        $depreciableAmount = $cost - $residual;
        $monthlyDepreciation = $depreciableAmount / $lifeMonths;
        $depreciation = $monthlyDepreciation * $ageMonths;
        
        return max($residual, round($cost - $depreciation, 2));
    }

    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_AVAILABLE => 'Available',
            self::STATUS_SCHEDULED => 'Scheduled',
            self::STATUS_RENTED => 'Rented',
            self::STATUS_MAINTENANCE => 'Maintenance',
            self::STATUS_RETIRED => 'Retired',
        ];
    }

    public static function getConditionOptions(): array
    {
        return [
            'new' => 'New',
            'excellent' => 'Excellent',
            'good' => 'Good',
            'fair' => 'Fair',
            'poor' => 'Poor',
            'broken' => 'Broken',
            'lost' => 'Lost',
        ];
    }

    /**
     * Check if unit is available for specific dates
     * Includes checking kit components availability
     */
    public function isAvailable($startDate, $endDate, $excludeRentalId = null): bool
    {
        // 1. Basic status check
        if ($this->status === self::STATUS_RETIRED) {
            return false;
        }

        if (in_array($this->condition, ['broken', 'lost'])) {
            return false;
        }

        // Check Warehouse Availability
        if ($this->warehouse && (!$this->warehouse->is_active || !$this->warehouse->is_available_for_rental)) {
            return false;
        }

        // 2. Check direct rentals overlap
        $isRented = $this->rentalItems()
            ->where('rental_id', '!=', $excludeRentalId)
            ->whereHas('rental', function ($query) use ($startDate, $endDate) {
                $query->whereIn('status', [
                        Rental::STATUS_QUOTATION,
                        Rental::STATUS_CONFIRMED,
                        Rental::STATUS_ACTIVE,
                        Rental::STATUS_LATE_PICKUP,
                        Rental::STATUS_LATE_RETURN
                    ])
                    ->where(function ($q) use ($startDate, $endDate) {
                         $q->whereBetween('start_date', [$startDate, $endDate])
                           ->orWhereBetween('end_date', [$startDate, $endDate])
                           ->orWhere(function ($sub) use ($startDate, $endDate) {
                               $sub->where('start_date', '<', $startDate)
                                   ->where('end_date', '>', $endDate);
                           });
                    });
            })
            ->exists();

        if ($isRented) {
            return false;
        }

        // 3. Check if this unit is a COMPONENT of a Bundle that is rented (Parent is rented)
        $isComponentRented = $this->linkedInKits()
            ->whereHas('unit', function ($qParentUnit) use ($startDate, $endDate, $excludeRentalId) {
                 $qParentUnit->whereHas('rentalItems', function ($ri) use ($startDate, $endDate, $excludeRentalId) {
                      $ri->where('rental_id', '!=', $excludeRentalId)
                         ->whereHas('rental', function ($query) use ($startDate, $endDate) {
                             $query->whereIn('status', [
                                     Rental::STATUS_QUOTATION,
                                     Rental::STATUS_CONFIRMED,
                                     Rental::STATUS_ACTIVE,
                                     Rental::STATUS_LATE_PICKUP,
                                     Rental::STATUS_LATE_RETURN
                                 ])
                                 ->where(function ($q) use ($startDate, $endDate) {
                                     $q->whereBetween('start_date', [$startDate, $endDate])
                                       ->orWhereBetween('end_date', [$startDate, $endDate])
                                       ->orWhere(function ($sub) use ($startDate, $endDate) {
                                           $sub->where('start_date', '<', $startDate)
                                               ->where('end_date', '>', $endDate);
                                       });
                                });
                        });
                 });
            })
            ->exists();
        
        if ($isComponentRented) {
            return false;
        }

        // 3. Check if this unit is a BUNDLE that has a component that is rented
        $isBundleComponentRented = $this->kits()
            ->whereNotNull('linked_unit_id')
            ->whereHas('linkedUnit', function ($q) use ($startDate, $endDate, $excludeRentalId) {
                 // Check if the component is unavailable either directly or via another parent
                 $q->where(function ($query) use ($startDate, $endDate, $excludeRentalId) {
                     // 1. Component is directly rented
                     $query->whereHas('rentalItems', function ($ri) use ($startDate, $endDate, $excludeRentalId) {
                         $ri->where('rental_id', '!=', $excludeRentalId)
                            ->whereHas('rental', function ($query) use ($startDate, $endDate) {
                                $query->whereIn('status', [
                                        Rental::STATUS_QUOTATION,
                                        Rental::STATUS_CONFIRMED,
                                        Rental::STATUS_ACTIVE,
                                        Rental::STATUS_LATE_PICKUP,
                                        Rental::STATUS_LATE_RETURN
                                    ])
                                    ->where(function ($q) use ($startDate, $endDate) {
                                         $q->whereBetween('start_date', [$startDate, $endDate])
                                           ->orWhereBetween('end_date', [$startDate, $endDate])
                                           ->orWhere(function ($sub) use ($startDate, $endDate) {
                                               $sub->where('start_date', '<', $startDate)
                                                   ->where('end_date', '>', $endDate);
                                           });
                                    });
                            });
                     })
                     // 2. Component is part of ANOTHER rented bundle (Parent is rented)
                     ->orWhereHas('linkedInKits', function ($qLink) use ($startDate, $endDate, $excludeRentalId) {
                          $qLink->whereHas('unit', function ($qParentUnit) use ($startDate, $endDate, $excludeRentalId) {
                               $qParentUnit->whereHas('rentalItems', function ($ri) use ($startDate, $endDate, $excludeRentalId) {
                                    $ri->where('rental_id', '!=', $excludeRentalId)
                                       ->whereHas('rental', function ($query) use ($startDate, $endDate) {
                                           $query->whereIn('status', [
                                                   Rental::STATUS_QUOTATION,
                                                   Rental::STATUS_CONFIRMED,
                                                   Rental::STATUS_ACTIVE,
                                                   Rental::STATUS_LATE_PICKUP,
                                                   Rental::STATUS_LATE_RETURN
                                               ])
                                               ->where(function ($q) use ($startDate, $endDate) {
                                                    $q->whereBetween('start_date', [$startDate, $endDate])
                                                      ->orWhereBetween('end_date', [$startDate, $endDate])
                                                      ->orWhere(function ($sub) use ($startDate, $endDate) {
                                                          $sub->where('start_date', '<', $startDate)
                                                              ->where('end_date', '>', $endDate);
                                                      });
                                               });
                                       });
                               });
                          });
                     });
                 });
            })
            ->exists();

        if ($isBundleComponentRented) {
            return false;
        }

        return true;
    }

    /**
     * Refresh unit status based on rentals and conditions
     */
    public function refreshStatus(): void
    {
        // If status is RETIRED, don't auto-change it
        if ($this->status === self::STATUS_RETIRED) {
            return;
        }

        // Check if currently rented (direct or as component)
        $isRented = $this->rentalItems()
            ->whereHas('rental', function ($query) {
                $query->whereIn('status', [
                    Rental::STATUS_ACTIVE,
                    Rental::STATUS_LATE_RETURN,
                    Rental::STATUS_PARTIAL_RETURN,
                ]);
            })
            ->whereDoesntHave('deliveryItems', function ($q) {
                $q->whereHas('delivery', function ($d) {
                    $d->where('type', 'in')
                      ->where('status', 'completed');
                });
            })
            ->exists();

        // Check if component of a rented bundle
        if (!$isRented) {
            $unitKitIds = UnitKit::where('linked_unit_id', $this->id)->pluck('id');

            if ($unitKitIds->isNotEmpty()) {
                $isRented = RentalItemKit::whereIn('unit_kit_id', $unitKitIds)
                    ->whereHas('rentalItem', function ($ri) {
                        $ri->whereHas('rental', function ($r) {
                            $r->whereIn('status', [
                                Rental::STATUS_ACTIVE,
                                Rental::STATUS_LATE_RETURN,
                                Rental::STATUS_PARTIAL_RETURN,
                            ]);
                        });
                    })
                    ->where('is_returned', false)
                    ->exists();
            }
        }

        if ($isRented) {
            $newStatus = self::STATUS_RENTED;
        } else {
            // If status is MAINTENANCE, keep it unless rented (handled above)
            if ($this->status === self::STATUS_MAINTENANCE) {
                return;
            }

            // Also check maintenance records in case status wasn't set yet
            $inMaintenance = $this->maintenanceRecords()
                ->whereIn('status', ['pending', 'in_progress'])
                ->exists();

            if ($inMaintenance) {
                $newStatus = self::STATUS_MAINTENANCE;
            } else {
                // Check for scheduled rentals (direct)
                $isScheduled = $this->rentalItems()
                    ->whereHas('rental', function ($query) {
                        $query->whereIn('status', [
                            Rental::STATUS_QUOTATION,
                            Rental::STATUS_CONFIRMED,
                            Rental::STATUS_LATE_PICKUP,
                        ]);
                    })->exists();

                // Check if component is scheduled via bundle
                if (!$isScheduled) {
                    $unitKitIds = $unitKitIds ?? UnitKit::where('linked_unit_id', $this->id)->pluck('id');

                    if ($unitKitIds->isNotEmpty()) {
                        $isScheduled = RentalItemKit::whereIn('unit_kit_id', $unitKitIds)
                            ->whereHas('rentalItem', function ($ri) {
                                $ri->whereHas('rental', function ($r) {
                                    $r->whereIn('status', [
                                        Rental::STATUS_QUOTATION,
                                        Rental::STATUS_CONFIRMED,
                                        Rental::STATUS_LATE_PICKUP,
                                    ]);
                                });
                            })
                            ->exists();
                    }
                }

                $newStatus = $isScheduled ? self::STATUS_SCHEDULED : self::STATUS_AVAILABLE;
            }
        }

        if ($this->status !== $newStatus) {
            $this->update(['status' => $newStatus]);
        }
    }

    /**
     * Calculate total revenue generated by this unit
     */
    public function calculateTotalRevenue(): float
    {
        return $this->rentalItems()
            ->whereHas('rental', function ($query) {
                $query->whereNotIn('status', [Rental::STATUS_CANCELLED]);
            })
            ->sum('subtotal');
    }

    /**
     * Calculate total maintenance cost
     */
    public function calculateTotalMaintenanceCost(): float
    {
        return $this->maintenanceRecords()->sum('cost');
    }

    /**
     * Calculate profitability (Revenue - Maintenance - Purchase Price)
     */
    public function calculateProfitability(): float
    {
        $revenue = $this->calculateTotalRevenue();
        $maintenance = $this->calculateTotalMaintenanceCost();
        $cost = $this->purchase_price ?? 0;

        return $revenue - $maintenance - $cost;
    }

    /**
     * Update status based on rental activity
     */
    public function updateStatusBasedOnRentals()
    {
        $newStatus = $this->status;

        // Check for active rentals (Rented)
        // 1. Direct Rental
        $isRented = $this->rentalItems()
            ->whereHas('rental', function ($query) {
                $query->whereIn('status', [
                    Rental::STATUS_ACTIVE, 
                    Rental::STATUS_LATE_RETURN,
                    Rental::STATUS_PARTIAL_RETURN
                ]);
            })
            ->whereDoesntHave('deliveryItems', function ($q) {
                $q->whereHas('delivery', function ($d) {
                    $d->where('type', 'in') // Delivery::TYPE_IN
                      ->where('status', 'completed'); // Delivery::STATUS_COMPLETED
                });
            })
            ->exists();

        // 2. Component of a Rented Unit (via RentalItemKit)
        if (!$isRented) {
             $unitKitIds = \App\Models\UnitKit::where('linked_unit_id', $this->id)->pluck('id');
             
             if ($unitKitIds->isNotEmpty()) {
                 $isComponentRented = \App\Models\RentalItemKit::whereIn('unit_kit_id', $unitKitIds)
                     ->whereHas('rentalItem', function ($ri) {
                         $ri->whereHas('rental', function ($r) {
                             $r->whereIn('status', [
                                Rental::STATUS_ACTIVE, 
                                Rental::STATUS_LATE_RETURN,
                                Rental::STATUS_PARTIAL_RETURN
                            ]);
                         });
                     })
                     ->where('is_returned', false)
                     ->exists();
                 
                 if ($isComponentRented) {
                     $isRented = true;
                 }
             }
        }

        if ($isRented) {
            $newStatus = self::STATUS_RENTED;
        } else {
            // If status is MAINTENANCE, we only change it if it's rented (handled above)
            // Otherwise we keep it as MAINTENANCE until manually changed
            if ($this->status === self::STATUS_MAINTENANCE) {
                return;
            }

            // Check for scheduled rentals
            // 1. Direct Scheduled
            $isScheduled = $this->rentalItems()
                ->whereHas('rental', function ($query) {
                    $query->whereIn('status', [Rental::STATUS_QUOTATION, Rental::STATUS_CONFIRMED, Rental::STATUS_LATE_PICKUP]);
                })->exists();

            // 2. Component Scheduled
            if (!$isScheduled) {
                 $unitKitIds = \App\Models\UnitKit::where('linked_unit_id', $this->id)->pluck('id');
                 if ($unitKitIds->isNotEmpty()) {
                     $isComponentScheduled = \App\Models\RentalItemKit::whereIn('unit_kit_id', $unitKitIds)
                         ->whereHas('rentalItem', function ($ri) {
                             $ri->whereHas('rental', function ($r) {
                                 $r->whereIn('status', [Rental::STATUS_QUOTATION, Rental::STATUS_CONFIRMED, Rental::STATUS_LATE_PICKUP]);
                             });
                         })
                         ->exists();
                     
                     if ($isComponentScheduled) {
                         $isScheduled = true;
                     }
                 }
            }

            if ($isScheduled) {
                $newStatus = self::STATUS_SCHEDULED;
            } else {
                // If not rented and not scheduled, it's available
                $newStatus = self::STATUS_AVAILABLE;
            }
        }

        // Only update if status changed to avoid loops/unnecessary queries
        if ($this->status !== $newStatus) {
            $this->update(['status' => $newStatus]);
        }
    }
}
