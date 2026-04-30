<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\UnitKit;
use Illuminate\Support\Str;

class KitUnitLinker
{
    /**
     * Resolve (or create) the ProductUnit that should be linked to a UnitKit row.
     * Returns the ProductUnit id, or null when track_by_serial is false.
     */
    public function resolveLinkedUnit(array $kitData): ?int
    {
        if (empty($kitData['track_by_serial'])) {
            return null;
        }

        $serial         = $kitData['serial_number'] ?? null;
        $name           = $kitData['name'] ?? null;
        $parentUnitId   = $kitData['parent_unit_id'] ?? null;

        if (empty($serial) || empty($name)) {
            return null;
        }

        // Resolve the parent unit's product so we can reject same-product links.
        // A kit-slot of parent X should never resolve to a ProductUnit that shares X's product —
        // a unit cannot be a component of another unit of the same product.
        $parentProductId = $parentUnitId
            ? ProductUnit::where('id', $parentUnitId)->value('product_id')
            : null;

        // Reuse existing unit if the serial is already tracked, but skip same-parent
        // and same-product matches (those are almost always bad data — e.g. a kit slot
        // accidentally carrying its parent unit's serial, or a sibling unit's serial).
        $unitQuery = ProductUnit::where('serial_number', $serial);
        if ($parentUnitId) {
            $unitQuery->where('id', '!=', $parentUnitId);
        }
        if ($parentProductId) {
            $unitQuery->where('product_id', '!=', $parentProductId);
        }
        $unit = $unitQuery->first();

        if (!$unit) {
            $category = Category::firstOrCreate(
                ['slug' => 'accessories-kits'],
                ['name' => 'Accessories & Kits', 'is_active' => true]
            );

            $brand = Brand::firstOrCreate(
                ['slug' => 'generic'],
                ['name' => 'Generic']
            );

            $productSlug = Str::slug($name);
            $product = Product::where('name', $name)->first();

            if (!$product) {
                if (Product::where('slug', $productSlug)->exists()) {
                    $productSlug .= '-' . Str::random(4);
                }

                $product = Product::create([
                    'name'        => $name,
                    'slug'        => $productSlug,
                    'category_id' => $category->id,
                    'brand_id'    => $brand->id,
                    'daily_rate'  => 0,
                    'is_active'   => true,
                ]);
            }

            $unit = ProductUnit::create([
                'product_id'    => $product->id,
                'serial_number' => $serial,
                'status'        => ProductUnit::STATUS_AVAILABLE,
                'condition'     => $kitData['condition'] ?? 'good',
            ]);
        }

        // Backfill any other UnitKit rows sharing this serial that lost their link.
        // Skip kit slots whose parent shares the resolved unit's product (same-product self-reference).
        $sameProductParentIds = ProductUnit::where('product_id', $unit->product_id)->pluck('id');
        UnitKit::where('serial_number', $serial)
            ->whereNull('linked_unit_id')
            ->whereNotIn('unit_id', $sameProductParentIds)
            ->update(['linked_unit_id' => $unit->id]);

        return $unit->id;
    }

    /**
     * Validate a serial about to be saved on a UnitKit row.
     * Returns null if OK, or a human-readable error message if the serial would create
     * a self-reference or a same-product link (both are always invalid kit relations).
     */
    public function validateKitSerial(?string $serial, ?int $parentUnitId): ?string
    {
        if (empty($serial) || empty($parentUnitId)) {
            return null;
        }

        $parent = ProductUnit::find($parentUnitId);
        if (! $parent) {
            return null;
        }

        if ($parent->serial_number === $serial) {
            return 'Kit serial cannot match its parent unit serial.';
        }

        $candidate = ProductUnit::where('serial_number', $serial)->first();
        if ($candidate && $candidate->product_id === $parent->product_id) {
            return 'Kit serial belongs to another unit of the same product — that is not a valid kit component.';
        }

        return null;
    }
}
