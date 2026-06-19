<?php

namespace App\Models;

use App\Services\TaxService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'category_discount_amount',
        'category_name',
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
        'activity_log',
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
        'daily_discount_amount' => 'decimal:2',
        'date_promotion_amount' => 'decimal:2',
        'category_discount_amount' => 'decimal:2',
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
        'activity_log' => 'array',
    ];

    public const STATUS_QUOTATION = 'quotation';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_LATE_PICKUP = 'late_pickup';

    public const STATUS_LATE_RETURN = 'late_return';

    public const STATUS_PARTIAL_RETURN = 'partial_return';

    // Quotation whose start_date passed without ever being confirmed (dead-end, like cancelled).
    public const STATUS_EXPIRED = 'expired';

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($rental) {
            if (empty($rental->rental_code)) {
                $rental->rental_code = self::generateRentalCode();
            }
        });
    }

    /**
     * Append a structured entry to the rental's activity log.
     *
     * Stored separately from `notes` so the operator's free-text notes stay clean.
     * Each entry: { at: ISO8601, type, message, user }. Written with updateQuietly()
     * so it never re-triggers RentalObserver::updated (no total recalc on a log write).
     */
    public function logActivity(string $message, string $type = 'general', ?string $user = null): void
    {
        $log = $this->activity_log ?? [];

        $log[] = [
            'at' => now()->toIso8601String(),
            'type' => $type,
            'message' => $message,
            'user' => $user ?? (auth()->user()?->email ?? 'system'),
        ];

        $this->activity_log = $log;

        if ($this->exists) {
            $this->updateQuietly(['activity_log' => $log]);
        }
    }

    public static function generateRentalCode(): string
    {
        $prefix = 'RNT';
        $date = now()->format('Ymd');

        // Find the last rental code for today directly from the code pattern
        // This is more robust than relying on created_at
        $lastRental = self::where('rental_code', 'like', $prefix.$date.'%')
            ->orderBy('rental_code', 'desc')
            ->first();

        $sequence = $lastRental ? intval(substr($lastRental->rental_code, -4)) + 1 : 1;

        // Ensure uniqueness with a loop
        do {
            $code = $prefix.$date.str_pad($sequence, 4, '0', STR_PAD_LEFT);
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
            self::STATUS_EXPIRED => -1,
        ];

        $currentOrder = $statusOrder[$this->status] ?? 0;
        // Treat expired like cancelled — a terminal dead-end that locks the checklist.
        $isCancelled = in_array($this->status, [self::STATUS_CANCELLED, self::STATUS_EXPIRED]);
        $isConfirmedOrAbove = $currentOrder >= 1;

        // Step 1: Konfirmasi WA
        $step1 = $isCancelled ? 'locked' : ($isConfirmedOrAbove ? 'completed' : 'active');

        // Step 2: Download Checklist (requires step 1 completed)
        $step2 = 'locked';
        if (! $isCancelled && $isConfirmedOrAbove) {
            $step2 = $this->checklist_downloaded_at ? 'completed' : 'active';
        }

        // Step 3: Surat Perizinan (requires step 2 completed)
        $step3 = 'locked';
        if (! $isCancelled && $step2 === 'completed') {
            $step3 = $this->permit_template_clicked_at ? 'completed' : 'active';
        }

        // Step 4: Pengambilan Fisik (requires step 3 completed)
        $step4 = 'locked';
        if (! $isCancelled && $step3 === 'completed') {
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

    /**
     * Ordered, human-readable breakdown of every discount layer applied to this
     * rental — the single source of truth reused by the View page, the rental
     * editor ringkasan, and the quotation/invoice PDFs.
     *
     * Each entry: ['key' => string, 'label' => string, 'amount' => float].
     * Only layers with a positive amount are included. Promo names are read live
     * via the FK relations (falling back to generic labels if a promo row was
     * deleted); the category discount label uses the snapshotted category_name.
     *
     * @return array<int, array{key: string, label: string, amount: float}>
     */
    public function discountBreakdown(): array
    {
        $lines = [];

        $category = (float) ($this->category_discount_amount ?? 0);
        if ($category > 0) {
            $label = 'Diskon Kategori';
            if (! empty($this->category_name)) {
                $label .= ' ('.$this->category_name.')';
            }
            $lines[] = ['key' => 'category', 'label' => $label, 'amount' => $category];
        }

        $daily = (float) ($this->daily_discount_amount ?? 0);
        if ($daily > 0) {
            $lines[] = [
                'key' => 'daily',
                'label' => $this->dailyDiscount?->name ?? 'Diskon Promo Harian',
                'amount' => $daily,
            ];
        }

        $date = (float) ($this->date_promotion_amount ?? 0);
        if ($date > 0) {
            $lines[] = [
                'key' => 'date',
                'label' => $this->datePromotion?->name ?? 'Diskon Promo Tanggal',
                'amount' => $date,
            ];
        }

        $manual = $this->discount_type === 'percent'
            ? ((float) ($this->subtotal ?? 0)) * (((float) ($this->discount ?? 0)) / 100)
            : (float) ($this->discount ?? 0);
        if ($manual > 0) {
            if (! empty($this->discount_code)) {
                $label = 'Kupon '.$this->discount_code;
            } else {
                $label = $this->discountRelation?->name ?? 'Diskon Manual';
            }
            $lines[] = ['key' => 'manual', 'label' => $label, 'amount' => $manual];
        }

        return $lines;
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

    /**
     * The outgoing delivery note (SJ Keluar) for this rental. One per rental.
     */
    public function outDelivery(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Delivery::class)->where('type', Delivery::TYPE_OUT);
    }

    /**
     * The most recent incoming delivery note (SJ Masuk). Partial returns can
     * produce several; the latest one is the currently-relevant checklist.
     */
    public function inDelivery(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Delivery::class)->where('type', Delivery::TYPE_IN)->latestOfMany();
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
            self::STATUS_EXPIRED => 'Expired',
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
            self::STATUS_EXPIRED => 'gray',
            default => 'gray',
        };
    }

    /**
     * Single source of truth for hex colors used across calendar/schedule surfaces
     * (frontend storefront + admin FullCalendar widgets). Keep in sync with
     * getStatusColor() — same logical states, just expressed as hex.
     */
    public static function getStatusHexColor(string $status): string
    {
        return match ($status) {
            self::STATUS_QUOTATION => '#f97316',
            self::STATUS_CONFIRMED => '#3b82f6',
            self::STATUS_ACTIVE => '#22c55e',
            self::STATUS_COMPLETED => '#a855f7',
            self::STATUS_CANCELLED => '#6b7280',
            self::STATUS_LATE_PICKUP,
            self::STATUS_LATE_RETURN => '#ef4444',
            self::STATUS_PARTIAL_RETURN => '#eab308',
            self::STATUS_EXPIRED => '#9ca3af',
            default => '#6b7280',
        };
    }

    /**
     * Status → human label map for calendar legends & tooltips.
     */
    public static function getStatusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_QUOTATION => 'Quotation',
            self::STATUS_CONFIRMED => 'Confirmed',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_COMPLETED => 'Done',
            self::STATUS_CANCELLED => 'Cancel',
            self::STATUS_LATE_PICKUP,
            self::STATUS_LATE_RETURN => 'Late',
            self::STATUS_PARTIAL_RETURN => 'Partial',
            self::STATUS_EXPIRED => 'Expired',
            default => ucfirst(str_replace('_', ' ', $status)),
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
            // Expiry is a soft timeout (unlike a deliberate cancellation), so an
            // expired quote stays editable and can be revived (re-date / re-confirm).
            self::STATUS_EXPIRED,
        ]);
    }

    /**
     * Get the real-time status of the rental
     */
    public function getRealTimeStatus(): string
    {
        if (in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED, self::STATUS_EXPIRED])) {
            return $this->status;
        }

        $now = now();

        // A quotation whose pickup date passed without being confirmed expires (dead-end).
        if ($this->status === self::STATUS_QUOTATION && $this->start_date < $now) {
            return self::STATUS_EXPIRED;
        }

        // A confirmed booking past its pickup date that hasn't been picked up is late.
        if ($this->status === self::STATUS_CONFIRMED && $this->start_date < $now) {
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
        if (in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED, self::STATUS_EXPIRED])) {
            return;
        }

        $now = now();
        $newStatus = $this->status;

        // Unconfirmed quotation past its pickup date → expired; confirmed → late pickup.
        if ($this->status === self::STATUS_QUOTATION && $this->start_date < $now) {
            $newStatus = self::STATUS_EXPIRED;
        }

        if ($this->status === self::STATUS_CONFIRMED && $this->start_date < $now) {
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
                    \App\Models\ProductUnit::whereIn('id', $parentUnitIds)->each(fn ($u) => $u->refreshStatus());
                }
            }
        }
    }

    /**
     * Check availability of rental items (conflicts with other active rentals)
     */
    public function checkAvailability(): array
    {
        if (! $this->relationLoaded('items') || ! $this->items->first()?->relationLoaded('productUnit')) {
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
            if (! empty($parentMap[$unitId] ?? null)) {
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
        if (! empty($containerUnitIds)) {
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

            \Illuminate\Support\Facades\Log::warning("Conflict detected for Rental {$this->id} item {$item->id} with rentals: ".$matchedRentals->pluck('rental_code')->implode(', '));
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

                if (! empty($componentIds)) {
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

                if (! empty($parentIds)) {
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

        // Reject ghost slots (product placeholder without assigned serial yet) — pickup not possible.
        $ghosts = $this->items->filter(fn ($it) => ! $it->parent_item_id && ! $it->product_unit_id);
        if ($ghosts->isNotEmpty()) {
            $sample = $ghosts->first();
            $productName = $sample->product?->name ?? 'Unknown product';
            throw new \Exception("Cannot pickup: there are {$ghosts->count()} placeholder slot(s) without an assigned unit serial (e.g. {$productName}). Assign units or remove the slots first.");
        }

        // Check if all items with kits have their kits checked
        foreach ($this->items as $item) {
            if ($item->productUnit && $item->productUnit->kits->count() > 0) {
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
        if ($this->is_taxable && ! $this->price_includes_tax) {
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
        if (! $enabled) {
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

        // Selisih jam telat (signed). end_date di masa lalu → now lebih besar → positif.
        $hoursLate = (float) $this->end_date->diffInHours(now(), false);

        if ($hoursLate <= 0) {
            return 0;
        }

        // Resolve late fee mode. Backward-compat: derive from old late_fee_type.
        $mode = Setting::get('late_fee_mode');
        if ($mode === null) {
            $oldType = Setting::get('late_fee_type');
            $mode = match ($oldType) {
                'fixed' => 'flat_per_day',
                'percentage' => 'percentage_per_day',
                default => 'full_daily_rate',
            };
        }

        $amount = (float) Setting::get('late_fee_amount', 0);

        // Item fisik yang ter-assign (abaikan ghost slot tanpa unit).
        $items = $this->items->whereNotNull('product_unit_id');

        // Tiered mode dihitung per-jam, per-item (menghormati override produk).
        if ($mode === 'tiered') {
            $tiers = json_decode(Setting::get('late_fee_tiers', '[]'), true);
            $tiers = is_array($tiers) ? $tiers : [];

            $fee = 0.0;
            foreach ($items as $item) {
                $fee += $this->tieredLateFeeForItem($item, $tiers, $hoursLate);
            }

            return round($fee, 2);
        }

        // Mode per-hari: bulatkan jam telat ke atas menjadi hari penuh.
        $overdueDays = (int) ceil($hoursLate / 24);

        $unitCount = (int) $items->sum(fn ($item) => $item->quantity ?? 1);

        // Total tarif dasar denda harian (menghormati override produk per-item).
        $totalDailyBase = (float) $items->sum(
            fn ($item) => $this->lateFeeDailyBase($item) * ($item->quantity ?? 1)
        );

        $fee = match ($mode) {
            // Override produk (jika ada) menggantikan nominal global per unit.
            'per_unit_per_day' => $items->sum(function ($item) use ($amount) {
                $override = $item->productUnit?->product?->late_fee_daily_amount;
                $perUnit = $override !== null ? (float) $override : $amount;

                return $perUnit * ($item->quantity ?? 1);
            }) * $overdueDays,
            'flat_per_day' => $amount * $overdueDays,
            'percentage_per_day' => $totalDailyBase * ($amount / 100) * $overdueDays,
            default => $totalDailyBase * $overdueDays, // full_daily_rate
        };

        return round($fee, 2);
    }

    /**
     * Tarif dasar denda harian per unit untuk sebuah item:
     * pakai override produk bila di-set, jika tidak pakai tarif sewa harian item.
     */
    protected function lateFeeDailyBase(RentalItem $item): float
    {
        $override = $item->productUnit?->product?->late_fee_daily_amount;

        return $override !== null ? (float) $override : (float) $item->daily_rate;
    }

    /**
     * Hitung denda tiered untuk satu item berdasarkan jam telat.
     * Setelah tier terakhir, tiap 24 jam berikutnya = +1× tarif dasar harian.
     */
    protected function tieredLateFeeForItem(RentalItem $item, array $tiers, float $hoursLate): float
    {
        $qty = $item->quantity ?? 1;
        $base = $this->lateFeeDailyBase($item); // tarif dasar harian per unit

        // Tanpa tier → fallback: tarif harian penuh per hari.
        if (empty($tiers)) {
            return $base * (int) ceil($hoursLate / 24) * $qty;
        }

        // Urutkan tier menaik berdasarkan up_to_hours.
        usort($tiers, fn ($a, $b) => (float) ($a['up_to_hours'] ?? 0) <=> (float) ($b['up_to_hours'] ?? 0));

        $chargePerUnit = function (array $tier) use ($base): float {
            $value = (float) ($tier['amount'] ?? 0);

            return ($tier['charge_type'] ?? 'percentage') === 'fixed'
                ? $value                       // Rp tetap per unit
                : $base * ($value / 100);      // % dari tarif harian
        };

        $lastTier = end($tiers);
        $lastHours = (float) ($lastTier['up_to_hours'] ?? 0);

        // Masih dalam jangkauan tier → ambil tier pertama yang menampung jam telat.
        if ($hoursLate <= $lastHours) {
            foreach ($tiers as $tier) {
                if ($hoursLate <= (float) ($tier['up_to_hours'] ?? 0)) {
                    return $chargePerUnit($tier) * $qty;
                }
            }
        }

        // Lewat tier terakhir → charge tier terakhir + tiap 24 jam berikutnya 1× tarif harian.
        $extraDays = (int) ceil(($hoursLate - $lastHours) / 24);
        $perUnit = $chargePerUnit($lastTier) + ($extraDays * $base);

        return $perUnit * $qty;
    }

    /**
     * Human-readable breakdown of how calculateOverdueFee() arrives at the late fee, for
     * transparency in the return settlement modal. Mirrors the exact same inputs/logic;
     * the authoritative `fee` is taken straight from calculateOverdueFee() so the rincian
     * total always matches what is actually charged.
     *
     * @return array{
     *   is_late: bool, fee: float, mode: string, mode_label: string,
     *   hours_late: float, overdue_days: int, end_date: ?string, now: string,
     *   amount_setting: float, summary: ?string,
     *   lines: array<int, array{label:string, detail:string, amount:float}>
     * }
     */
    public function lateFeeBreakdown(): array
    {
        $now = now();

        $result = [
            'is_late' => false,
            'fee' => 0.0,
            'mode' => '',
            'mode_label' => '',
            'hours_late' => 0.0,
            'overdue_days' => 0,
            'end_date' => $this->end_date?->format('d M Y H:i'),
            'now' => $now->format('d M Y H:i'),
            'amount_setting' => 0.0,
            'summary' => null,
            'lines' => [],
        ];

        if (! $this->end_date || $this->end_date->isFuture()) {
            return $result;
        }

        $hoursLate = (float) $this->end_date->diffInHours($now, false);
        if ($hoursLate <= 0) {
            return $result;
        }

        // Resolve mode exactly like calculateOverdueFee() (incl. legacy fallback).
        $mode = Setting::get('late_fee_mode');
        if ($mode === null) {
            $oldType = Setting::get('late_fee_type');
            $mode = match ($oldType) {
                'fixed' => 'flat_per_day',
                'percentage' => 'percentage_per_day',
                default => 'full_daily_rate',
            };
        }

        $amount = (float) Setting::get('late_fee_amount', 0);
        $items = $this->items->whereNotNull('product_unit_id');
        $overdueDays = (int) ceil($hoursLate / 24);

        $labels = [
            'flat_per_day' => 'Flat per hari',
            'per_unit_per_day' => 'Per unit per hari',
            'percentage_per_day' => 'Persentase tarif harian / hari',
            'full_daily_rate' => 'Tarif sewa harian penuh / hari',
            'tiered' => 'Bertingkat (tiered)',
        ];

        $result['is_late'] = true;
        $result['mode'] = $mode;
        $result['mode_label'] = $labels[$mode] ?? $mode;
        $result['hours_late'] = round($hoursLate, 1);
        $result['overdue_days'] = $overdueDays;
        $result['amount_setting'] = $amount;
        $result['fee'] = $this->calculateOverdueFee();

        $lines = [];

        if ($mode === 'tiered') {
            $tiers = json_decode(Setting::get('late_fee_tiers', '[]'), true);
            $tiers = is_array($tiers) ? $tiers : [];

            foreach ($items as $item) {
                $qty = $item->quantity ?? 1;
                $lines[] = [
                    'label' => $this->lateFeeItemLabel($item),
                    'detail' => $qty.' unit · tarif harian Rp'.number_format($this->lateFeeDailyBase($item), 0, ',', '.'),
                    'amount' => round($this->tieredLateFeeForItem($item, $tiers, $hoursLate), 2),
                ];
            }

            $result['summary'] = 'Mode bertingkat dihitung per item dari '.round($hoursLate, 1).' jam telat.';
            $result['lines'] = $lines;

            return $result;
        }

        switch ($mode) {
            case 'flat_per_day':
                $lines[] = [
                    'label' => 'Tarif flat',
                    'detail' => 'Rp'.number_format($amount, 0, ',', '.').' × '.$overdueDays.' hari',
                    'amount' => round($amount * $overdueDays, 2),
                ];
                break;

            case 'per_unit_per_day':
                foreach ($items as $item) {
                    $qty = $item->quantity ?? 1;
                    $override = $item->productUnit?->product?->late_fee_daily_amount;
                    $perUnit = $override !== null ? (float) $override : $amount;
                    $lines[] = [
                        'label' => $this->lateFeeItemLabel($item),
                        'detail' => $qty.' unit × Rp'.number_format($perUnit, 0, ',', '.')
                            .($override !== null ? ' (override produk)' : '').' × '.$overdueDays.' hari',
                        'amount' => round($perUnit * $qty * $overdueDays, 2),
                    ];
                }
                break;

            case 'percentage_per_day':
                foreach ($items as $item) {
                    $qty = $item->quantity ?? 1;
                    $dailyBase = $this->lateFeeDailyBase($item);
                    $lines[] = [
                        'label' => $this->lateFeeItemLabel($item),
                        'detail' => $qty.' × Rp'.number_format($dailyBase, 0, ',', '.')
                            .' × '.rtrim(rtrim(number_format($amount, 2, ',', ''), '0'), ',').'%'
                            .' × '.$overdueDays.' hari',
                        'amount' => round($dailyBase * $qty * ($amount / 100) * $overdueDays, 2),
                    ];
                }
                break;

            default: // full_daily_rate
                foreach ($items as $item) {
                    $qty = $item->quantity ?? 1;
                    $dailyBase = $this->lateFeeDailyBase($item);
                    $lines[] = [
                        'label' => $this->lateFeeItemLabel($item),
                        'detail' => $qty.' × Rp'.number_format($dailyBase, 0, ',', '.').' × '.$overdueDays.' hari',
                        'amount' => round($dailyBase * $qty * $overdueDays, 2),
                    ];
                }
                break;
        }

        $result['lines'] = $lines;

        return $result;
    }

    /** Product (+ variation) label for a rental item, used in the late fee breakdown. */
    protected function lateFeeItemLabel(RentalItem $item): string
    {
        $product = $item->productUnit?->product?->name ?? $item->product?->name ?? 'Item';
        $variation = $item->productUnit?->variation?->name ?? null;

        return $product.($variation ? ' ('.$variation.')' : '');
    }

    /**
     * Complete the rental on return.
     *
     * @param  float|null  $lateFee  When provided (e.g. a manual adjustment or waiver from
     *                               the settlement modal), it is used as-is. When null the
     *                               fee is auto-calculated from the overdue window.
     */
    public function validateReturn(?float $lateFee = null): void
    {
        // Check if all items (main units and kits) in the latest Delivery IN are checked
        $deliveryIn = $this->deliveries->where('type', Delivery::TYPE_IN)->sortByDesc('id')->first();

        if (! $deliveryIn || ! $deliveryIn->allItemsChecked()) {
            throw new \Exception('All items must be checked in the Delivery Note before validating return.');
        }

        $this->returned_date = now();

        // Honor an explicitly provided late fee (manual override / waiver); otherwise
        // auto-calculate. Previously this always recomputed and silently discarded any
        // manual adjustment made during the return settlement.
        $this->late_fee = $lateFee ?? $this->calculateOverdueFee();

        $this->status = self::STATUS_COMPLETED;

        // Full recalculation (subtotal, discount, tax, deposit, total) so the stored total
        // stays consistent with the rest of the app and the linked invoice. The old
        // "subtotal - discount + lateFee" shortcut dropped tax and deposit from the total.
        // recalculateTotal() persists the row.
        $this->recalculateTotal();

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
     * Reopen a COMPLETED rental back to ACTIVE so its items / total can be corrected and
     * the return redone.
     *
     * Operational revert only:
     *  - status → ACTIVE, returned_date cleared;
     *  - the latest IN delivery is reopened (DRAFT + items unchecked) so the return
     *    checklist can be redone and units stop reading as "returned";
     *  - unit statuses are recomputed (non-damaged units go back to RENTED).
     *
     * It deliberately does NOT reverse the financial entries posted by the previous
     * completion (revenue recognition / deposit settlement) — those must be reviewed
     * before completing again to avoid double counting.
     */
    public function reopenFromCompleted(): void
    {
        if ($this->status !== self::STATUS_COMPLETED) {
            throw new \RuntimeException('Only completed rentals can be reopened.');
        }

        $this->status = self::STATUS_ACTIVE;
        $this->returned_date = null;
        $this->save();

        // Reopen the final return so refreshStatus() no longer sees the items as checked-in.
        $deliveryIn = $this->deliveries()
            ->where('type', Delivery::TYPE_IN)
            ->orderByDesc('id')
            ->first();

        if ($deliveryIn) {
            $deliveryIn->update(['status' => Delivery::STATUS_DRAFT]);

            foreach ($deliveryIn->items as $item) {
                $item->update(['is_checked' => false]);
                if ($item->rental_item_kit_id && $item->rentalItemKit) {
                    $item->rentalItemKit->update(['is_returned' => false]);
                }
            }
        }

        // Non-damaged units return to RENTED; damaged/maintenance units stay out.
        foreach ($this->items as $item) {
            $item->productUnit?->refreshStatus();
        }
    }

    /**
     * Make sure an invoice reflects this rental's current total so an outstanding balance
     * (e.g. a late fee) is collectible in Accounts Receivable.
     *
     *  - When an invoice is already linked, it is recalculated.
     *  - When none exists and a balance is owed, one is issued (mirroring the confirm-time
     *    flow) and any existing income (DP, etc.) is re-linked onto it.
     *
     * @return array{invoice: ?Invoice, action: string, reopened: bool}
     *                                                                  action: 'recalc' | 'created' | 'none'
     */
    public function syncOutstandingInvoice(string $noteContext = 'update'): array
    {
        if ($this->invoice_id) {
            $invoice = Invoice::find($this->invoice_id);
            if (! $invoice) {
                return ['invoice' => null, 'action' => 'none', 'reopened' => false];
            }

            $previousStatus = $invoice->status;
            $invoice->recalculate();

            return [
                'invoice' => $invoice,
                'action' => 'recalc',
                'reopened' => $previousStatus === Invoice::STATUS_PAID && $invoice->status !== Invoice::STATUS_PAID,
            ];
        }

        // No invoice yet — only issue one when money is actually owed.
        $alreadyPaid = (float) $this->rentalIncomeTransactions()->sum('amount');
        $outstanding = (float) $this->total - $alreadyPaid;

        if ($outstanding <= 0.01) {
            return ['invoice' => null, 'action' => 'none', 'reopened' => false];
        }

        $invoice = Invoice::create([
            'user_id' => $this->user_id,
            'quotation_id' => $this->quotation_id,
            'date' => now(),
            'due_date' => now()->addDays(7),
            'status' => Invoice::STATUS_WAITING_FOR_PAYMENT,
            'subtotal' => $this->subtotal,
            'tax_base' => $this->tax_base ?? $this->subtotal,
            'ppn_rate' => $this->ppn_rate ?? 0,
            'ppn_amount' => $this->ppn_amount ?? 0,
            'tax' => $this->ppn_amount ?? 0,
            'late_fee' => $this->late_fee ?? 0,
            'total' => $this->total,
            'is_taxable' => $this->is_taxable ?? false,
            'price_includes_tax' => $this->price_includes_tax ?? false,
            'notes' => 'Generated ('.$noteContext.') for Rental '.$this->rental_code,
        ]);

        \App\Services\JournalService::recordSimpleTransaction(
            'RENTAL_INVOICE_ISSUED',
            $invoice,
            $invoice->total,
            'Invoice generated ('.$noteContext.') for Rental '.$this->rental_code
        );

        foreach ($this->rentalIncomeTransactions()->get() as $transaction) {
            $transaction->reference()->associate($invoice);
            if (! str_contains((string) $transaction->description, 'Invoice #')) {
                $transaction->description = $transaction->description.' (Inv #'.$invoice->number.')';
            }
            $transaction->save();
        }

        // Link first so the rental is included, then recalc paid_amount/status.
        $this->update(['invoice_id' => $invoice->id]);
        $invoice->recalculate();

        return ['invoice' => $invoice, 'action' => 'created', 'reopened' => false];
    }

    /**
     * Income transactions linked to this rental (and its originating quotation, if any).
     */
    protected function rentalIncomeTransactions(): \Illuminate\Database\Eloquent\Builder
    {
        return FinanceTransaction::query()
            ->where(function ($query) {
                $query->where('reference_type', self::class)
                    ->where('reference_id', $this->id);

                if ($this->quotation_id) {
                    $query->orWhere(function ($q) {
                        $q->where('reference_type', Quotation::class)
                            ->where('reference_id', $this->quotation_id);
                    });
                }
            })
            ->where('type', FinanceTransaction::TYPE_INCOME);
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
        if (! $deliveryOut) {
            $deliveryOut = Delivery::create([
                'rental_id' => $this->id,
                'type' => Delivery::TYPE_OUT,
                'date' => $this->start_date,
                'status' => Delivery::STATUS_DRAFT,
            ]);
        }

        if ($deliveryOut->status === Delivery::STATUS_DRAFT || $deliveryOut->items()->count() === 0) {
            foreach ($this->items as $item) {
                // Skip empty "ghost" slots (no unit assigned) — nothing physical to hand out.
                if (! $item->product_unit_id || ! $item->productUnit) {
                    continue;
                }

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

        if (! $deliveryIn) {
            // Only create if no Delivery IN exists at all (first time)
            if (! $this->deliveries()->where('type', Delivery::TYPE_IN)->exists()) {
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
            // Kits flagged "not taken" on the OUT delivery were never handed to the
            // customer, so there is nothing to receive back — skip them entirely so
            // they don't appear in the return checklist or block its completion gate.
            // The flag is usually set AFTER the IN row was first created at pickup time,
            // so also delete any stale IN row for those kits (not just skip new ones).
            $outNotTakenKitIds = $deliveryOut
                ? $deliveryOut->items()->where('not_taken', true)->pluck('rental_item_kit_id')->filter()->all()
                : [];

            if (! empty($outNotTakenKitIds)) {
                $deliveryIn->items()->whereIn('rental_item_kit_id', $outNotTakenKitIds)->delete();
            }

            foreach ($this->items as $item) {
                // Skip empty "ghost" slots (no unit assigned) — nothing physical to receive.
                if (! $item->product_unit_id || ! $item->productUnit) {
                    continue;
                }

                // Main Unit
                $deliveryIn->items()->firstOrCreate([
                    'rental_item_id' => $item->id,
                    'rental_item_kit_id' => null,
                ], [
                    'is_checked' => false,
                ]);

                // Kits
                foreach ($item->rentalItemKits as $kit) {
                    if (in_array($kit->id, $outNotTakenKitIds, true)) {
                        continue;
                    }

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
     * @param  string  $reason  The reason for cancellation
     *
     * @throws \Exception If rental cannot be cancelled
     */
    public function cancelRental(string $reason): void
    {
        // Validate that rental can be cancelled
        if (! in_array($this->status, [self::STATUS_QUOTATION, self::STATUS_CONFIRMED, self::STATUS_LATE_PICKUP])) {
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

        // Status transition is auto-logged by RentalObserver; record the reason too.
        if (trim($reason) !== '') {
            $this->logActivity('Dibatalkan. Alasan: ' . $reason, 'status');
        }

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
