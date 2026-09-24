<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShootingItinerary extends Model
{
    protected $fillable = ['user_id', 'production_name'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(ShootingItineraryLocation::class)->orderBy('position');
    }

    public function crew(): HasMany
    {
        return $this->hasMany(ShootingItineraryCrew::class)->orderBy('position');
    }

    public function getTotalShootingDaysAttribute(): int
    {
        return $this->locations
            ->map(fn (ShootingItineraryLocation $location) => $location->start_at->toDateString())
            ->unique()
            ->count();
    }
}
