<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Services\TaxService;

class Rental extends Model
{
    protected $fillable = [
        'rental_code',
        'user_id',
        'discount_id',
        'daily_discount_id',
        'daily_discount_amount',
        'date_promotion_id',
        'date_promotion_amount',
        'quotation_id',
        'invoice_id',
        'discount_code',
        'start_date',
        'end_date',
        'returned_date',
        'status',
        'subtotal',
        'discount',
        'discount_type',
        'total',
        'late_fee',
        'deposit',
        'deposit_type',
        'security_deposit_amount',
        'security_deposit_status',
        'down_payment_amount',
        'down_payment_status',
        'notes',
        'tax_base',
        'ppn_rate',
        'tax_name',
        'ppn_amount',
        'pph_rate',
        'pph_amount',
        'price_includes_tax',
        'is_taxable',
        'checklist_downloaded_at',
        'permit_template_clicked_at',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'returned_date' => 'datetime',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'late_fee' => 'decimal:2',
        'deposit' => 'decimal:2',
        'security_deposit_amount' => 'decimal:2',
        'down_payment_amount' => 'decimal:2',
        'tax_base' => 'decimal:2',
        'ppn_rate' => 'decimal:2',
        'ppn_amount' => 'decimal:2',
        'pph_rate' => 'decimal:2',
        'pph_amount' => 'decimal:2',
        'price_includes_tax' => 'boolean',
        'is_taxable' => 'boolean',
        'checklist_downloaded_at' => 'datetime',
        'permit_template_clicked_at' => 'datetime',
    ];

    public const STATUS_QUOTATION = 'quotation';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_LATE_PICKUP = 'late_pickup';
    public const STATUS_LATE_RETURN = 'late_return';
    public const STATUS_PARTIAL_RETURN = 'partial_return';

    protected static function booted()
    {
        static::created(function ($rental) {
            // Admin Notification - only send to users with admin/super_admin roles
            $admins = \App\Models\User::role(['super_admin', 'admin', 'staff'])->get();
            \Illuminate\Support\Facades\Notification::send($admins, new \App\Notifications\NewBookingNotification($rental));

            // Customer Notification
            if ($rental->customer) {
                $rental->customer->notify(new \App\Notifications\BookingConfirmedNotification($rental));
            }
        });

        static::updated(function ($rental) {
            // Notify when rental is completed
            if ($rental->isDirty('status') && $rental->status === self::STATUS_COMPLETED) {
                // Notify admins
                $admins = \App\Models\User::role(['super_admin', 'admin', 'staff'])->get();
                \Illuminate\Support\Facades\Notification::send($admins, new \App\Notifications\RentalCompletedNotification($rental));

                // Notify customer
                if ($rental->user) {
                    $rental->user->notify(new \App\Notifications\RentalCompletedNotification($rental));
                }
            }
        });

        static::saved(function ($rental) {
            $rental->refreshUnitStatuses();
        });

        static::deleting(function ($rental) {
            $units = $rental->items->map(fn($item) => $item->productUnit)->filter();
            
            static::deleted(function () use ($units) {
                foreach ($units as $unit) {
                    $unit->refreshStatus();
                }
            });
        });
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($rental) {
            if (empty($rental->rental_code)) {
                $rental->rental_code = self::generateRentalCode();
            }
        });
    }

    public static function generateRentalCode(): string
    {
        $prefix = 'RNT';
        $date = now()->format('Ymd');
        
        // Find the last rental code for today directly from the code pattern
        // This is more robust than relying on created_at
        $lastRental = self::where('rental_code', 'like', $prefix . $date . '%')
                          ->orderBy('rental_code', 'desc')
                          ->first();
                          
        $sequence = $lastRental ? intval(substr($lastRental->rental_code, -4)) + 1 : 1;
        
        // Ensure uniqueness with a loop
        do {
            $code = $prefix . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
            $exists = self::where('rental_code', $code)->exists();
            if ($exists) {
                $sequence++;
            }
        } while ($exists);

        return $code;
    }

    /**
     * Get the current administration checklist step statuses.
     * Each step returns 'completed', 'active', or 'locked'.
     * Flow: Step 1 → Step 2 → Step 3 → Step 4 (sequential).
     */
    public function getChecklistSteps(): array
    {
        $statusOrder = [
            self::STATUS_QUOTATION => 0,
            self::STATUS_CONFIRMED => 1,
            self::STATUS_ACTIVE => 2,
            self::STATUS_COMPLETED => 3,
            self::STATUS_CANCELLED => -1,
            self::STATUS_LATE_PICKUP => 1,
            self::STATUS_LATE_RETURN => 2,
            self::STATUS_PARTIAL_RETURN => 2,
        ];

        $currentOrder = $statusOrder[$this->status] ?? 0;
        $isCancelled = $this->status === self::STATUS_CANCELLED;
        $isConfirmedOrAbove = $currentOrder >= 1;

        // Step 1: Konfirmasi WA
        $step1 = $isCancelled ? 'locked' : ($isConfirmedOrAbove ? 'completed' : 'active');

        // Step 2: Download Checklist (requires step 1 completed)
        $step2 = 'locked';
        if (!$isCancelled && $isConfirmedOrAbove) {
            $step2 = $this->checklist_downloaded_at ? 'completed' : 'active';
        }

        // Step 3: Surat Perizinan (requires step 2 completed)
        $step3 = 'locked';
        if (!$isCancelled && $step2 === 'completed') {
            $step3 = $this->permit_template_clicked_at ? 'completed' : 'active';
        }

        // Step 4: Pengambilan Fisik (requires step 3 completed)
        $step4 = 'locked';
        if (!$isCancelled && $step3 === 'completed') {
            $step4 = ($currentOrder >= 2) ? 'completed' : 'active';
        }

        return [
            ['key' => 'wa_confirm', 'label' => 'Konfirmasi WA', 'status' => $step1],
            ['key' => 'download_checklist', 'label' => 'Download Checklist', 'status' => $step2],
            ['key' => 'permit_letter', 'label' => 'Surat Perizinan', 'status' => $step3],
            ['key' => 'physical_pickup', 'label' => 'Pengambilan', 'status' => $step4],
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @deprecated Use user() instead
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function discountRelation(): BelongsTo
    {
        return $this->belongsTo(Discount::class, 'discount_id');
    }

    public function dailyDiscount(): BelongsTo
    {
        return $this->belongsTo(DailyDiscount::class);
    }

    public function datePromotion(): BelongsTo
    {
        return $this->belongsTo(DatePromotion::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function journalEntry(): \Illuminate\Database\Eloquent\Relations\MorphOne
    {
        return $this->morphOne(JournalEntry::class, 'reference');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RentalItem::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_QUOTATION => 'Quotation',
            self::STATUS_CONFIRMED => 'Confirmed',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_LATE_PICKUP => 'Late Pickup',
            self::STATUS_LATE_RETURN => 'Late Return',
            self::STATUS_PARTIAL_RETURN => 'Partial Return',
        ];
    }

    public static function getStatusColor(string $status): string
    {
        return match ($status) {
            self::STATUS_QUOTATION => 'warning',
            self::STATUS_CONFIRMED => 'info',
            self::STATUS_ACTIVE => 'success',
            self::STATUS_COMPLETED => 'purple',
            self::STATUS_CANCELLED => 'gray',
            self::STATUS_PARTIAL_RETURN => 'orange',
            self::STATUS_LATE_PICKUP, self::STATUS_LATE_RETURN => 'danger',
            default => 'gray',
        };
    }

    /**
     * Check if the rental can be edited
     */
    public function canBeEdited(): bool
    {
        return in_array($this->status, [
            self::STATUS_QUOTATION,
            self::STATUS_CONFIRMED,
            self::STATUS_LATE_PICKUP,
            self::STATUS_ACTIVE,
            self::STATUS_LATE_RETURN,
            self::STATUS_PARTIAL_RETURN,
        ]);
    }

    /**
     * Get the real-time status of the rental
     */
    public function getRealTimeStatus(): string
    {
        if (in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED])) {
            return $this->status;
        }

        $now = now();

        if ($this->status === self::STATUS_QUOTATION && $this->start_date < $now) {
            return self::STATUS_LATE_PICKUP;
        }

        // Check for Partial Return condition (Dynamic)
        $hasPartialReturn = $this->deliveries->where('type', Delivery::TYPE_IN)->count() > 1;

        if ($this->end_date < $now) {
            if ($this->status === self::STATUS_ACTIVE || $this->status === self::STATUS_PARTIAL_RETURN || ($this->status === self::STATUS_ACTIVE && $hasPartialReturn)) {
                return self::STATUS_LATE_RETURN;
            }
        }

        if ($this->status === self::STATUS_ACTIVE && $hasPartialReturn) {
            return self::STATUS_PARTIAL_RETURN;
        }

        return $this->status;
    }

    /**
     * Check and update late status in database
     */
    public function checkAndUpdateLateStatus(): void
    {
        if (in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED])) {
            return;
        }

        $now = now();
        $newStatus = $this->status;

        if ($this->status === self::STATUS_QUOTATION && $this->start_date < $now) {
            $newStatus = self::STATUS_LATE_PICKUP;
        }

        if (($this->status === self::STATUS_ACTIVE || $this->status === self::STATUS_PARTIAL_RETURN) && $this->end_date < $now) {
            $newStatus = self::STATUS_LATE_RETURN;
        }

        if ($this->status !== $newStatus) {
            $this->update(['status' => $newStatus]);
            $this->refreshUnitStatuses();
        }
    }

    /**
     * Refresh all product unit statuses associated with this rental
     */
    public function refreshUnitStatuses(): void
    {
        foreach ($this->items as $item) {
            if ($item->productUnit) {
                $item->productUnit->refreshStatus();
                
                // Also refresh linked components (Children)
                foreach ($item->productUnit->kits as $kit) {
                    if ($kit->linked_unit_id) {
                         // We need to fetch the linked unit if not loaded
                         $linkedUnit = $kit->linkedUnit ?? \App\Models\ProductUnit::find($kit->linked_unit_id);
                         if ($linkedUnit) {
                             $linkedUnit->refreshStatus();
                         }
                    }
                }

                // Also refresh parent units (if this item is a component)
                // (Though usually parent status depends on component availability, not vice versa for "Rented" status,
                // but for "Scheduled" it might matter. Let's be safe.)
                $parentUnitIds = \App\Models\UnitKit::where('linked_unit_id', $item->product_unit_id)->pluck('unit_id');
                if ($parentUnitIds->isNotEmpty()) {
                    \App\Models\ProductUnit::whereIn('id', $parentUnitIds)->each(fn($u) => $u->refreshStatus());
                }
            }
        }
    }

    /**
     * Check availability of rental items (conflicts with other active rentals)
     */
    public function checkAvailability(): array
    {
        if (!$this->relationLoaded('items') || !$this->items->first()?->relationLoaded('productUnit')) {
            $this->load(['items.productUnit.kits']);
        }

        \Illuminate\Support\Facades\Log::info("Checking availability for Rental {$this->id} ({$this->rental_code})");

        $items = $this->items;
        if ($items->isEmpty()) {
            return [];
        }

        // 1. Collect all item unit IDs in this rental.
        $itemUnitIds = $items->pluck('product_unit_id')->filter()->unique()->values()->all();

        // 2. Batch-fetch parent UnitKit relationships (units that contain any of our item units as kits).
        // Map: linked_unit_id => [parent unit_id, ...]
        $parentMap = \App\Models\UnitKit::whereIn('linked_unit_id', $itemUnitIds)
            ->get(['unit_id', 'linked_unit_id'])
            ->groupBy('linked_unit_id')
            ->map(fn ($rows) => $rows->pluck('unit_id')->all())
            ->all();

        // 3. Build per-item conflict unit ID sets, plus a global union of all conflict unit IDs.
        $perItemConflictIds = [];
        $allConflictIds = [];
        foreach ($items as $item) {
            $unitId = $item->product_unit_id;
            $ids = [$unitId];

            // Parents (units that use this unit as a kit)
            if (!empty($parentMap[$unitId] ?? null)) {
                $ids = array_merge($ids, $parentMap[$unitId]);
            }

            // Children (kits of this unit) — already eager-loaded
            if ($item->productUnit && $item->productUnit->relationLoaded('kits')) {
                $childIds = $item->productUnit->kits
                    ->whereNotNull('linked_unit_id')
                    ->pluck('linked_unit_id')
                    ->all();
                $ids = array_merge($ids, $childIds);
            }

            $ids = array_values(array_unique(array_filter($ids)));
            $perItemConflictIds[$item->id] = $ids;
            $allConflictIds = array_merge($allConflictIds, $ids);
        }
        $allConflictIds = array_values(array_unique($allConflictIds));

        if (empty($allConflictIds)) {
            return [];
        }

        // 4. Single query: find every overlapping rental that has an item in $allConflictIds
        //    OR an item whose unit contains (as a kit) any unit in $allConflictIds.
        // Use strict (exclusive) date overlap: A overlaps B iff A.start < B.end AND A.end > B.start.

        // 4a. Direct-match: rental_items.product_unit_id IN (...)
        $directRentalIds = \App\Models\RentalItem::whereIn('product_unit_id', $allConflictIds)
            ->where('rental_id', '!=', $this->id)
            ->pluck('rental_id');

        // 4b. Container-match: rental_items whose productUnit has a kit linked to any conflict unit.
        $containerUnitIds = \App\Models\UnitKit::whereIn('linked_unit_id', $allConflictIds)
            ->pluck('unit_id')
            ->unique()
            ->values()
            ->all();

        $containerRentalIds = collect();
        if (!empty($containerUnitIds)) {
            $containerRentalIds = \App\Models\RentalItem::whereIn('product_unit_id', $containerUnitIds)
                ->where('rental_id', '!=', $this->id)
                ->pluck('rental_id');
        }

        $candidateRentalIds = $directRentalIds->merge($containerRentalIds)->unique()->values();

        if ($candidateRentalIds->isEmpty()) {
            return [];
        }

        $overlappingRentals = self::whereIn('id', $candidateRentalIds)
            ->whereIn('status', [self::STATUS_QUOTATION, self::STATUS_CONFIRMED, self::STATUS_ACTIVE, self::STATUS_LATE_PICKUP, self::STATUS_LATE_RETURN])
            ->where('start_date', '<', $this->end_date)
            ->where('end_date', '>', $this->start_date)
            ->with(['customer', 'items.productUnit.product', 'items.productUnit.kits'])
            ->get();

        if ($overlappingRentals->isEmpty()) {
            return [];
        }

        // 5. Build conflicts list per item by filtering the already-loaded set in-memory.
        $conflicts = [];
        foreach ($items as $item) {
            $itemConflictIds = $perItemConflictIds[$item->id] ?? [];
            if (empty($itemConflictIds)) {
                continue;
            }

            $matchedRentals = $overlappingRentals->filter(function ($otherRental) use ($itemConflictIds) {
                foreach ($otherRental->items as $otherItem) {
                    if (in_array($otherItem->product_unit_id, $itemConflictIds, true)) {
                        return true;
                    }
                    if ($otherItem->productUnit && $otherItem->productUnit->relationLoaded('kits')) {
                        $linkedIds = $otherItem->productUnit->kits
                            ->whereNotNull('linked_unit_id')
                            ->pluck('linked_unit_id')
                            ->all();
                        if (array_intersect($linkedIds, $itemConflictIds)) {
                            return true;
                        }
                    }
                }
                return false;
            })->values();

            if ($matchedRentals->isEmpty()) {
                continue;
            }

            // Annotate each conflicting rental with the specific items that matched (UI shows serials).
            $matchedRentals->each(function ($otherRental) use ($itemConflictIds) {
                $matchingItems = $otherRental->items->filter(function ($otherItem) use ($itemConflictIds) {
                    if (in_array($otherItem->product_unit_id, $itemConflictIds, true)) {
                        return true;
                    }
                    if ($otherItem->productUnit && $otherItem->productUnit->relationLoaded('kits')) {
                        $linkedIds = $otherItem->productUnit->kits
                            ->whereNotNull('linked_unit_id')
                            ->pluck('linked_unit_id')
                            ->all();
                        return (bool) array_intersect($linkedIds, $itemConflictIds);
                    }
                    return false;
                })->values();
                $otherRental->setRelation('matchingItems', $matchingItems);
            });

            \Illuminate\Support\Facades\Log::warning("Conflict detected for Rental {$this->id} item {$item->id} with rentals: " . $matchedRentals->pluck('rental_code')->implode(', '));
            $conflicts[] = [
                'product_unit' => $item->productUnit,
                'conflicting_rentals' => $matchedRentals,
            ];
        }

        return $conflicts;
    }

    /**
     * Resolve conflicts by removing conflicting items from other rentals
     */
    public function resolveConflicts(array $conflicts): void
    {
        foreach ($conflicts as $conflict) {
            $unit = $conflict['product_unit'];
            $conflictingRentals = $conflict['conflicting_rentals'];

            foreach ($conflictingRentals as $rental) {
                // Find the conflicting item
                $item = $rental->items()
                    ->where('product_unit_id', $unit->id)
                    ->first();

                if ($item) {
                    $item->delete();
                    
                    // Recalculate totals for the other rental
                    $rental->refresh();
                    $subtotal = $rental->items->sum('subtotal');
                    
                    // Update subtotal
                    $rental->subtotal = $subtotal;
                    
                    // Recalculate total (keeping existing discount amount for fixed, or logic for percent)
                    // Since applyDiscount logic is complex, we'll do a simple update here
                    // assuming the admin will review the other rental if needed.
                    $rental->total = max(0, $subtotal - $rental->discount);
                    
                    $rental->save();
                }
            }
        }
    }

    /**
     * Validate pickup and change status to active
     */
    public function validatePickup(): void
    {
        if (! in_array($this->status, [self::STATUS_CONFIRMED, self::STATUS_LATE_PICKUP])) {
            throw new \Exception('Cannot validate pickup for this rental status.');
        }

        // Check availability of rental items (conflicts with other active rentals)
        $conflicts = $this->checkAvailability();
        if (! empty($conflicts)) {
            $messages = [];
            foreach ($conflicts as $conflict) {
                $unitName = $conflict['product_unit']->product->name;
                $serial = $conflict['product_unit']->serial_number;
                
                $rentalInfo = $conflict['conflicting_rentals']->map(function ($r) {
                    $customerName = $r->customer->name ?? 'Unknown';
                    return "{$r->rental_code} ($customerName)";
                })->implode(', ');

                $messages[] = "$unitName ($serial) vs $rentalInfo";
            }
            $unitList = implode('; ', $messages);
            throw new \Exception("Cannot validate pickup. The following units have scheduling conflicts: $unitList. Please swap them.");
        }

        // Check if any unit is physically unavailable (e.g. still rented/late return from another customer)
        foreach ($this->items as $item) {
            if ($item->productUnit) {
                // Refresh status first to be sure
                $item->productUnit->refreshStatus();
                $unit = $item->productUnit;

                // 1. Check direct unit status
                if (in_array($unit->status, [ProductUnit::STATUS_RENTED, ProductUnit::STATUS_MAINTENANCE])) {
                    throw new \Exception("Unit {$unit->serial_number} ({$unit->product->name}) is currently {$unit->status}. Please swap the unit in the list before validating pickup.");
                }

                // 2. Check Components (if this is a Kit)
                // If I am picking up a Kit, all its components must be available
                $componentIds = $unit->kits()
                    ->whereNotNull('linked_unit_id')
                    ->pluck('linked_unit_id')
                    ->toArray();
                
                if (!empty($componentIds)) {
                    $unavailableComponents = ProductUnit::whereIn('id', $componentIds)
                        ->whereIn('status', [ProductUnit::STATUS_RENTED, ProductUnit::STATUS_MAINTENANCE])
                        ->get();
                    
                    if ($unavailableComponents->isNotEmpty()) {
                         $comp = $unavailableComponents->first();
                         throw new \Exception("Component Unit {$comp->serial_number} ({$comp->product->name}) inside Kit {$unit->product->name} is currently {$comp->status}. Cannot pickup this kit.");
                    }
                }

                // 3. Check Parent Kits (if this is a Component)
                // If I am picking up a Component, the Kit containing it must not be rented out
                $parentIds = \App\Models\UnitKit::where('linked_unit_id', $unit->id)
                    ->pluck('unit_id')
                    ->toArray();
                
                if (!empty($parentIds)) {
                    $unavailableParents = ProductUnit::whereIn('id', $parentIds)
                        ->whereIn('status', [ProductUnit::STATUS_RENTED, ProductUnit::STATUS_MAINTENANCE])
                        ->get();

                    if ($unavailableParents->isNotEmpty()) {
                        $parent = $unavailableParents->first();
                        throw new \Exception("This unit is part of Kit {$parent->serial_number} ({$parent->product->name}) which is currently {$parent->status}. Cannot pickup this unit.");
                    }
                }
            }
        }

        // Check if all items with kits have their kits checked
        foreach ($this->items as $item) {
            if ($item->productUnit->kits->count() > 0) {
                $checkedKits = $item->rentalItemKits->count();
                // Filter out broken/lost kits to match attachKitsFromUnit logic
                $totalKits = $item->productUnit->kits->whereNotIn('condition', ['broken', 'lost'])->count();
                
                if ($checkedKits < $totalKits) {
                    throw new \Exception('All kit items must be checked before validating pickup.');
                }
            }
        }

        $this->update(['status' => self::STATUS_ACTIVE]);

        // Update product unit statuses to Rented
        $this->refreshUnitStatuses();
    }

    /**
     * Check if the rental can be deleted
     */
    public function canBeDeleted(): bool
    {
        return $this->status === self::STATUS_QUOTATION;
    }

    public function applyDiscount(Discount $discount): void
    {
        $discountAmount = $discount->calculateDiscount($this->subtotal);
        $this->discount_id = $discount->id;
        $this->discount_code = $discount->code;
        $this->discount = $discountAmount;
        $this->discount_type = 'fixed';
        $this->total = $this->subtotal - $discountAmount;
        // Deposit logic: if percent, it auto-adjusts via accessor. If fixed, it stays.
        // We do NOT overwrite deposit here to respect manual overrides.
        $this->save();
        $discount->incrementUsage();
    }

    public function removeDiscount(): void
    {
        $this->discount_id = null;
        $this->discount_code = null;
        $this->discount = 0;
        $this->discount_type = 'fixed';
        $this->total = $this->subtotal;
        // We do NOT overwrite deposit here to respect manual overrides.
        $this->save();
    }

    public function recalculateTotal(): void
    {
        // 1. Calculate Subtotal (Items)
        $this->subtotal = $this->items()->sum('subtotal');
        
        // 2. Calculate Discount
        $discountAmount = 0;
        if ($this->discountRelation) {
            $discountAmount = $this->discountRelation->calculateDiscount($this->subtotal);
        } else {
            if ($this->discount_type === 'percent') {
                $discountAmount = $this->subtotal * ($this->discount / 100);
            } else {
                $discountAmount = $this->discount;
            }
        }
        
        // 3. Calculate Tax Base (DPP)
        // DPP = (Subtotal - Discount) + Late Fee
        $netSubtotal = max(0, $this->subtotal - $discountAmount);
        $lateFee = $this->late_fee ?? 0;
        $taxableAmount = $netSubtotal + $lateFee;
        
        // 4. Calculate Tax (Using TaxService for International Support)
        // Retrieve Customer
        $customer = $this->user ?? User::find($this->user_id);
        
        $taxResult = TaxService::calculateTax(
            $taxableAmount, 
            $this->is_taxable, 
            $this->price_includes_tax,
            $customer
        );

        $this->tax_base = $taxResult['tax_base'];
        $this->ppn_amount = $taxResult['tax_amount'];
        $this->ppn_rate = $taxResult['tax_rate'];
        $this->tax_name = $taxResult['tax_name'];
        
        // PPh Calculation (if applicable) - kept separate as it is withholding tax
        $pphAmount = 0;
        $taxEnabled = filter_var(\App\Models\Setting::get('tax_enabled', true), FILTER_VALIDATE_BOOLEAN);

        if ($taxEnabled && $this->is_taxable && $this->pph_rate > 0) {
            $pphAmount = $this->tax_base * ($this->pph_rate / 100);
        }
        $this->pph_amount = $pphAmount;
        
        // 5. Calculate Final Total
        // Total = TaxableAmount (if inclusive) OR (TaxableAmount + Tax) (if exclusive)
        // PLUS Deposit (Non-taxable)
        
        $totalBill = 0;
        if ($this->is_taxable && !$this->price_includes_tax) {
            $totalBill = $taxableAmount + $this->ppn_amount;
        } else {
            $totalBill = $taxableAmount;
        }
        
        // Add Deposit
        // Deposit calculation based on Net Subtotal (Rental Value)
        $depositValue = 0;
        if ($this->deposit_type === 'percent') {
             $depositValue = $netSubtotal * ($this->deposit / 100);
        } else {
             $depositValue = $this->deposit;
        }
        
        $this->security_deposit_amount = $depositValue;
        
        $this->total = $totalBill + $depositValue;
        
        $this->save();
    }

    public function getDiscountAmountAttribute(): float
    {
        if ($this->discount_type === 'percent') {
            return $this->subtotal * ($this->discount / 100);
        }
        return (float) $this->discount;
    }

    public function getDepositAmountAttribute(): float
    {
        // Deposit based on Subtotal - Discount (Net Rental Value)
        $discountAmount = $this->discount_amount;
        $netSubtotal = max(0, $this->subtotal - $discountAmount);

        if ($this->deposit_type === 'percent') {
            return $netSubtotal * ($this->deposit / 100);
        }
        return (float) $this->deposit;
    }

    public static function calculateDeposit(float $amount): float
    {
        // Check if deposit is enabled
        $enabled = Setting::get('deposit_enabled', true);
        if (!$enabled) {
            return 0;
        }

        $type = Setting::get('deposit_type', 'percentage');
        
        // Determine default amount based on old setting if available
        $defaultAmount = 30;
        if ($type === 'percentage') {
             $oldValue = Setting::get('deposit_percentage');
             if ($oldValue !== null) {
                 $defaultAmount = $oldValue;
             }
        }
        
        $settingAmount = Setting::get('deposit_amount', $defaultAmount);

        if ($type === 'percentage') {
            return $amount * ($settingAmount / 100);
        }

        return $settingAmount;
    }

    public static function calculateLateFee(float $dailyRate, int $daysLate): float
    {
         $type = Setting::get('late_fee_type', 'percentage');
         
         $defaultAmount = 10;
         if ($type === 'percentage') {
             $oldValue = Setting::get('late_fee_percentage');
             if ($oldValue !== null) {
                 $defaultAmount = $oldValue;
             }
         }
         
         $amount = Setting::get('late_fee_amount', $defaultAmount);
         
         if ($type === 'percentage') {
             return ($dailyRate * ($amount / 100)) * $daysLate;
         }
         
         return $amount * $daysLate;
    }


    /**
     * Calculate late fee based on overdue days
     */
    public function calculateOverdueFee(): float
    {
        if ($this->end_date->isFuture()) {
            return 0;
        }

        $overdueDays = $this->end_date->diffInDays(now(), false);
        
        if ($overdueDays <= 0) {
            return 0;
        }

        // Calculate total daily rate of all items
        $totalDailyRate = $this->items->sum(function ($item) {
            return $item->daily_rate * ($item->quantity ?? 1);
        });

        return round($totalDailyRate * $overdueDays, 2);
    }

    public function validateReturn(): void
    {
        // Check if all items (main units and kits) in the latest Delivery IN are checked
        $deliveryIn = $this->deliveries->where('type', Delivery::TYPE_IN)->sortByDesc('id')->first();
        
        if (!$deliveryIn || !$deliveryIn->allItemsChecked()) {
            throw new \Exception('All items must be checked in the Delivery Note before validating return.');
        }

        $this->returned_date = now();

        // Calculate Late Fee
        $lateFee = $this->calculateOverdueFee();
        $this->late_fee = $lateFee;
        
        // Update Total (Subtotal - Discount + Late Fee)
        // Note: Deposit is separate
        $this->total = $this->subtotal - $this->discount + $lateFee;

        $this->status = self::STATUS_COMPLETED;
        $this->save();

        // Update product unit statuses based on return condition
        // We iterate all IN deliveries to find the condition for each item
        $inDeliveries = $this->deliveries()->where('type', Delivery::TYPE_IN)->with('items')->get();
        
        foreach ($this->items as $item) {
            if ($item->productUnit) {
                // Find the delivery item for this rental item in ANY IN delivery
                $condition = null;
                
                foreach ($inDeliveries as $delivery) {
                    $dItem = $delivery->items
                        ->where('rental_item_id', $item->id)
                        ->whereNull('rental_item_kit_id')
                        ->first();

                    if ($dItem && $dItem->condition) {
                        $condition = $dItem->condition;
                    }
                }

                if ($condition && in_array($condition, ['broken', 'lost'])) {
                    $item->productUnit->update(['status' => ProductUnit::STATUS_MAINTENANCE]);
                } else {
                    $item->productUnit->refreshStatus();
                }
            }
        }
    }

    /**
     * Create delivery documents (Out and In) for this rental
     */
    public function createDeliveries(): void
    {
        // Ensure all rental items have their kits attached first
        foreach ($this->items as $item) {
            $item->attachKitsFromUnit();
        }
        $this->load('items.rentalItemKits');

        // Create or Update Delivery Out (SJK)
        $deliveryOut = $this->deliveries()->where('type', Delivery::TYPE_OUT)->first();
        if (!$deliveryOut) {
            $deliveryOut = Delivery::create([
                'rental_id' => $this->id,
                'type' => Delivery::TYPE_OUT,
                'date' => $this->start_date,
                'status' => Delivery::STATUS_DRAFT,
            ]);
        }

        if ($deliveryOut->status === Delivery::STATUS_DRAFT || $deliveryOut->items()->count() === 0) {
            foreach ($this->items as $item) {
                // Main Unit
                $deliveryOut->items()->firstOrCreate([
                    'rental_item_id' => $item->id,
                    'rental_item_kit_id' => null,
                ], [
                    'is_checked' => false,
                    'condition' => $item->productUnit->condition,
                ]);

                // Kits
                foreach ($item->rentalItemKits as $kit) {
                    $deliveryOut->items()->firstOrCreate([
                        'rental_item_id' => $item->id,
                        'rental_item_kit_id' => $kit->id,
                    ], [
                        'is_checked' => false,
                        'condition' => $kit->condition_out,
                    ]);
                }
            }
        }

        // Create or Update Delivery In (SJM)
        // Find a non-completed Delivery IN (to avoid re-populating completed partial return deliveries)
        $deliveryIn = $this->deliveries()->where('type', Delivery::TYPE_IN)
            ->where('status', '!=', Delivery::STATUS_COMPLETED)
            ->first();

        if (!$deliveryIn) {
            // Only create if no Delivery IN exists at all (first time)
            if (!$this->deliveries()->where('type', Delivery::TYPE_IN)->exists()) {
                $deliveryIn = Delivery::create([
                    'rental_id' => $this->id,
                    'type' => Delivery::TYPE_IN,
                    'date' => $this->end_date,
                    'status' => Delivery::STATUS_DRAFT,
                ]);
            } else {
                // All Delivery INs are completed (from partial returns), nothing to sync
                return;
            }
        }

        if ($deliveryIn->status === Delivery::STATUS_DRAFT) {
            foreach ($this->items as $item) {
                // Main Unit
                $deliveryIn->items()->firstOrCreate([
                    'rental_item_id' => $item->id,
                    'rental_item_kit_id' => null,
                ], [
                    'is_checked' => false,
                ]);

                // Kits
                foreach ($item->rentalItemKits as $kit) {
                    $deliveryIn->items()->firstOrCreate([
                        'rental_item_id' => $item->id,
                        'rental_item_kit_id' => $kit->id,
                    ], [
                        'is_checked' => false,
                    ]);
                }
            }
        }
    }

    /**
     * Cancel the rental with a reason
     * 
     * @param string $reason The reason for cancellation
     * @throws \Exception If rental cannot be cancelled
     */
    public function cancelRental(string $reason): void
    {
        // Validate that rental can be cancelled
        if (!in_array($this->status, [self::STATUS_QUOTATION, self::STATUS_CONFIRMED, self::STATUS_LATE_PICKUP])) {
            throw new \Exception('Cannot cancel this rental. Only quotation, confirmed or late pickup rentals can be cancelled.');
        }

        // Release all product units back to available/scheduled
        foreach ($this->items as $item) {
            if ($item->productUnit) {
                $item->productUnit->refreshStatus();
            }
        }

        // Decrement discount usage if a discount was applied
        if ($this->discountRelation) {
            $this->discountRelation->decrement('usage_count');
        }

        // Update rental status and save cancel reason
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'cancel_reason' => $reason,
        ]);

        // Cancel all associated deliveries
        $this->deliveries()->update(['status' => Delivery::STATUS_CANCELLED]);
    }

    /**
     * Check if the rental can be cancelled
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            self::STATUS_QUOTATION,
            self::STATUS_CONFIRMED,
            self::STATUS_LATE_PICKUP,
        ]);
    }
}