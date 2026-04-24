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

        $serial = $kitData['serial_number'] ?? null;
        $name   = $kitData['name'] ?? null;

        if (empty($serial) || empty($name)) {
            return null;
        }

        // Reuse existing unit if the serial is already tracked
        $unit = ProductUnit::where('serial_number', $serial)->first();

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

        // Backfill any other UnitKit rows sharing this serial that lost their link
        UnitKit::where('serial_number', $serial)
            ->whereNull('linked_unit_id')
            ->update(['linked_unit_id' => $unit->id]);

        return $unit->id;
    }
}
