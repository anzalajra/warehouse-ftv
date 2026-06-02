<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentalItem extends Model
{
    protected $touches = ['rental'];

    protected $fillable = [
        'rental_id',
        'parent_item_id',
        'product_unit_id',
        'product_id',
        'product_variation_id',
        'daily_rate',
        'days',
        'subtotal',
        'discount',
    ];

    protected $casts = [
        'daily_rate' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    protected static function booted()
    {
        static::saving(function ($item) {
            $gross = $item->daily_rate * $item->days;
            $discountAmount = $gross * ($item->discount / 100);
            $item->subtotal = max(0, $gross - $discountAmount);
        });

        static::created(function ($item) {
            $item->attachKitsFromUnit();
        });

        static::updated(function ($item) {
            if ($item->wasChanged('product_unit_id')) {
                $item->rentalItemKits()->delete();
                $item->unsetRelation('productUnit');
                $item->attachKitsFromUnit();
            }
        });

        static::saved(function ($item) {
            $item->productUnit?->refreshStatus();

            // Link shadow item to parent if applicable
            if ($item->product_unit_id && !$item->parent_item_id) {
                 // Check if this unit is a linked component of another unit
                 // Get all parent unit IDs that have this unit as a component
                 $parentUnitIds = \App\Models\UnitKit::where('linked_unit_id', $item->product_unit_id)
                     ->pluck('unit_id');
                 
                 if ($parentUnitIds->isNotEmpty()) {
                     // Find if any of these parent units are in the same rental
                     $parentItem = \App\Models\RentalItem::where('rental_id', $item->rental_id)
                         ->where('id', '!=', $item->id) // Avoid self-reference just in case
                         ->whereIn('product_unit_id', $parentUnitIds)
                         ->first();
                     
                     if ($parentItem) {
                         $item->parent_item_id = $parentItem->id;
                         $item->saveQuietly();
                     }
                 }
            }

            // Reverse check: Is THIS item a parent to any existing unlinked items?
            // (Handles case where Child is saved before Parent)
            // Skip for ghost slots: a null product_unit_id would make the query below
            // resolve to whereNull('unit_id') and match unrelated kit rows.
            $childUnitIds = $item->product_unit_id
                ? \App\Models\UnitKit::where('unit_id', $item->product_unit_id)
                    ->whereNotNull('linked_unit_id')
                    ->pluck('linked_unit_id')
                : collect();

            if ($childUnitIds->isNotEmpty()) {
                 // Find unlinked items in this rental that match these child units
                 $unlinkedChildren = \App\Models\RentalItem::where('rental_id', $item->rental_id)
                     ->where('id', '!=', $item->id)
                     ->whereNull('parent_item_id')
                     ->whereIn('product_unit_id', $childUnitIds)
                     ->get();
                 
                 foreach ($unlinkedChildren as $child) {
                     $child->parent_item_id = $item->id;
                     $child->saveQuietly();
                 }
            }
        });

        static::deleted(function ($item) {
            $item->productUnit?->refreshStatus();
        });
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(RentalItem::class, 'parent_item_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(RentalItem::class, 'parent_item_id');
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Product::class);
    }

    public function productVariation(): BelongsTo
    {
        return $this->belongsTo(\App\Models\ProductVariation::class);
    }

    public function rentalItemKits(): HasMany
    {
        return $this->hasMany(RentalItemKit::class);
    }

    public function deliveryItems(): HasMany
    {
        return $this->hasMany(DeliveryItem::class);
    }

    /**
     * Attach all kits from the product unit to this rental item
     */
    public function attachKitsFromUnit(): void
    {
        // Ghost slots (no assigned serial) have no unit to pull kits from.
        if (! $this->productUnit) {
            return;
        }

        $kits = $this->productUnit->kits()
            ->whereNotIn('condition', ['broken', 'lost']) // Filter out broken/lost kits
            ->get();
        
        foreach ($kits as $kit) {
            $this->rentalItemKits()->updateOrCreate(
                ['unit_kit_id' => $kit->id],
                ['condition_out' => $kit->condition]
            );
        }
    }

    /**
     * Check if all kits are returned
     */
    public function allKitsReturned(): bool
    {
        if ($this->rentalItemKits()->count() === 0) {
            return true;
        }
        return $this->rentalItemKits()->where('is_returned', false)->count() === 0;
    }

    /**
     * Get returned kits count
     */
    public function returnedKitsCount(): int
    {
        return $this->rentalItemKits()->where('is_returned', true)->count();
    }

    /**
     * Get total kits count
     */
    public function totalKitsCount(): int
    {
        return $this->rentalItemKits()->count();
    }

    /**
     * Get kits status text
     */
    public function getKitsStatusText(): string
    {
        $total = $this->totalKitsCount();
        if ($total === 0) {
            return 'No kits';
        }
        $returned = $this->returnedKitsCount();
        return "{$returned}/{$total}";
    }
}