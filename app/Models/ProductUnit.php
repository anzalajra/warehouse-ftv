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
        'accumulated_depreciation',
        'notes',
        'last_checked_at',
        'maintenance_status',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'purchase_price' => 'decimal:2',
        'residual_value' => 'decimal:2',
        'accumulated_depreciation' => 'decimal:2',
        'useful_life' => 'integer',
        'last_checked_at' => 'datetime',
    ];

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_RENTED = 'rented';

    public const STATUS_MAINTENANCE = 'maintenance';

    public const STATUS_RETIRED = 'retired';

    /** Conditions that force a unit into maintenance / out of availability. */
    public const DAMAGED_CONDITIONS = ['broken', 'lost'];

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
        if ($cost == 0) {
            return 0;
        }

        $residual = $this->residual_value ?? 0;

        // Prefer the depreciation actually POSTED (finance:run-depreciation). This keeps
        // book value historical — editing useful-life later no longer rewrites the past.
        $accumulated = (float) ($this->accumulated_depreciation ?? 0);
        if ($accumulated > 0) {
            return max($residual, round($cost - $accumulated, 2));
        }

        // Fallback estimate (units never processed by a depreciation run): straight-line
        // from purchase date.
        $lifeMonths = $this->useful_life ?? 60;
        $purchaseDate = $this->purchase_date;

        if (! $purchaseDate) {
            return $cost;
        }

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

    /** Single source of truth for the maintenance progress field used across the Maintenance UI. */
    public static function getMaintenanceStatusOptions(): array
    {
        return [
            'In Repair' => 'In Repair',
            'Waiting Parts' => 'Waiting Parts',
            'Waiting Customer' => 'Waiting Customer',
            'Ready for QC' => 'Ready for QC',
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
        if ($this->warehouse && (! $this->warehouse->is_active || ! $this->warehouse->is_available_for_rental)) {
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
                    Rental::STATUS_LATE_RETURN,
                    Rental::STATUS_PARTIAL_RETURN,
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
                                Rental::STATUS_LATE_RETURN,
                                Rental::STATUS_PARTIAL_RETURN,
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
                                    Rental::STATUS_LATE_RETURN,
                                    Rental::STATUS_PARTIAL_RETURN,
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
                                                Rental::STATUS_LATE_RETURN,
                                                Rental::STATUS_PARTIAL_RETURN,
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
        if (! $isRented) {
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
            // Maintenance is derived (not a sticky early-return) so a unit can never
            // silently drift back to AVAILABLE. Two independent sources:
            //   1. An OPEN unit-level maintenance ticket (kit-level tickets, identified
            //      by a non-null unit_kit_id, do NOT pull the parent unit into maintenance).
            //   2. A damaged condition (broken/lost) — these must stay out of availability
            //      regardless of whether a ticket exists.
            $hasOpenTicket = $this->maintenanceRecords()
                ->whereNull('unit_kit_id')
                ->open()
                ->exists();

            $isDamaged = in_array($this->condition, self::DAMAGED_CONDITIONS);

            if ($hasOpenTicket || $isDamaged) {
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
                if (! $isScheduled) {
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
     * Canonical entry point for putting a unit into maintenance. Ensures an OPEN
     * maintenance ticket exists (idempotent) and recomputes status. Used by the
     * Pickup/Return auto-flag, the admin "Send to Maintenance" action and the
     * Record Cost action so every path behaves identically.
     *
     * Pass a $unitKitId for a kit-level repair: the ticket is tracked but does
     * NOT force the parent unit into maintenance (refreshStatus only considers
     * unit-level tickets — see refreshStatus()).
     */
    public function sendToMaintenance(
        string $reason = 'Maintenance',
        string $type = MaintenanceRecord::TYPE_CORRECTIVE,
        ?int $unitKitId = null,
        ?int $technicianId = null,
        ?int $rentalId = null
    ): MaintenanceRecord {
        $technicianId = $technicianId ?? \Illuminate\Support\Facades\Auth::id();

        $existing = $this->maintenanceRecords()
            ->when($unitKitId !== null,
                fn ($q) => $q->where('unit_kit_id', $unitKitId),
                fn ($q) => $q->whereNull('unit_kit_id'))
            ->open()
            ->latest('id')
            ->first();

        if ($existing) {
            // Reuse the open ticket; backfill started_at / source rental for legacy rows.
            $patch = [];
            if (! $existing->started_at) {
                $patch['started_at'] = now();
            }
            if (! $existing->rental_id && $rentalId) {
                $patch['rental_id'] = $rentalId;
            }
            if ($patch) {
                $existing->update($patch);
            }
            $record = $existing;
        } else {
            $record = $this->maintenanceRecords()->create([
                'unit_kit_id' => $unitKitId,
                'rental_id' => $rentalId,
                'technician_id' => $technicianId,
                'title' => $reason,
                'description' => $reason,
                'cost' => 0,
                'date' => now(),
                'started_at' => now(),
                'status' => MaintenanceRecord::STATUS_IN_PROGRESS,
                'type' => $type,
            ]);
        }

        $this->refreshStatus();

        return $record;
    }

    protected ?MaintenanceRecord $cachedOpenMaintenanceRecord = null;

    protected bool $loadedOpenMaintenanceRecord = false;

    /** The current open, unit-level maintenance ticket (if any). Memoized per instance (used by several columns). */
    public function getOpenMaintenanceRecordAttribute(): ?MaintenanceRecord
    {
        if (! $this->loadedOpenMaintenanceRecord) {
            $this->cachedOpenMaintenanceRecord = $this->maintenanceRecords()
                ->whereNull('unit_kit_id')
                ->with('rental.customer')
                ->open()
                ->latest('started_at')
                ->first();
            $this->loadedOpenMaintenanceRecord = true;
        }

        return $this->cachedOpenMaintenanceRecord;
    }

    /** Days the unit has been down on its current open ticket, or null if not in maintenance. */
    public function getDaysInMaintenanceAttribute(): ?int
    {
        $record = $this->open_maintenance_record;

        if (! $record || ! $record->started_at) {
            return null;
        }

        return max(0, (int) abs($record->started_at->diffInDays(now())));
    }

    /** Whether this unit is overdue for a QC / stock-opname check. */
    public function getIsQcDueAttribute(): bool
    {
        $intervalDays = (int) Setting::get('maintenance_qc_interval_days', 90);

        if ($intervalDays <= 0) {
            return false;
        }

        if (! $this->last_checked_at) {
            return true;
        }

        return $this->last_checked_at->lt(now()->subDays($intervalDays));
    }

    /** Number of (non-cancelled) rentals this unit served since its last maintenance. */
    public function getRentalsSinceLastMaintenanceAttribute(): int
    {
        $lastDate = $this->maintenanceRecords()->max('completed_at')
            ?? $this->maintenanceRecords()->max('date');

        return $this->rentalItems()
            ->whereHas('rental', function ($q) use ($lastDate) {
                $q->whereNotIn('status', [Rental::STATUS_CANCELLED]);
                if ($lastDate) {
                    $q->where('end_date', '>=', $lastDate);
                }
            })
            ->count();
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
                    Rental::STATUS_PARTIAL_RETURN,
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
        if (! $isRented) {
            $unitKitIds = \App\Models\UnitKit::where('linked_unit_id', $this->id)->pluck('id');

            if ($unitKitIds->isNotEmpty()) {
                $isComponentRented = \App\Models\RentalItemKit::whereIn('unit_kit_id', $unitKitIds)
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
            if (! $isScheduled) {
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
