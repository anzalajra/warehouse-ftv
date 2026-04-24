<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Category extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'image',
        'is_active',
        'sort_order',
        'is_visible_on_storefront',
    ];

    protected $casts = [
        'is_active'                => 'boolean',
        'is_visible_on_storefront' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });

        static::updating(function ($category) {
            if ($category->isDirty('name') && empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeVisibleOnStorefront(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('is_visible_on_storefront', true)
            ->where('slug', '!=', 'accessories-kits')
            ->orderBy('sort_order')
            ->orderBy('name');
    }
}
