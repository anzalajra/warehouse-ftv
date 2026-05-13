<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Announcement extends Model
{
    protected $fillable = [
        'title',
        'type',
        'image_path',
        'content',
        'link_url',
        'link_label',
        'banner_bg_color',
        'banner_text_color',
        'is_active',
        'starts_at',
        'ends_at',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function scopeActiveNow($query)
    {
        $now = Carbon::now();

        return $query->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });
    }

    public static function activePopup(): ?self
    {
        return static::activeNow()
            ->where('type', 'popup')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->first();
    }

    public static function activeBanners()
    {
        return static::activeNow()
            ->where('type', 'banner')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();
    }
}
