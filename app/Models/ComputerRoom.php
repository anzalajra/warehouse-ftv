<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ComputerRoom extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'description',
        'image_path',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (ComputerRoom $room) {
            if (empty($room->code)) {
                $base = Str::slug($room->name) ?: 'room';
                $code = $base;
                $i = 1;
                while (static::withTrashed()->where('code', $code)->exists()) {
                    $code = $base.'-'.(++$i);
                }
                $room->code = $code;
            }
        });
    }

    public function computers(): HasMany
    {
        return $this->hasMany(Computer::class, 'room_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
