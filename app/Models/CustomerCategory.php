<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class CustomerCategory extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'badge_color',
        'discount_percentage',
        'benefits',
        'required_custom_fields',
        'is_active',
    ];

    protected $casts = [
        'discount_percentage' => 'decimal:2',
        'benefits' => 'array',
        'required_custom_fields' => 'array',
        'is_active' => 'boolean',
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

    public function getBenefitsAttribute($value)
    {
        // Handle double-encoded JSON or JSON string inside array (common with some form components)
        if (is_array($value) && count($value) === 1 && is_string($value[0])) {
            $decoded = json_decode($value[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        if (is_string($value)) {
            // Try to decode as JSON first
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
            // Fallback to legacy pipe separator
            return explode('|', $value);
        }

        return is_array($value) ? $value : [];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function excludedProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_visibility_exclusions', 'customer_category_id', 'product_id');
    }

    public function documentTypes(): BelongsToMany
    {
        return $this->belongsToMany(DocumentType::class, 'customer_category_document_type');
    }
}
