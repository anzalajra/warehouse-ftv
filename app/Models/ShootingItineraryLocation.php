<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShootingItineraryLocation extends Model
{
    protected $fillable = ['position', 'location_name', 'start_at', 'end_at'];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function itinerary(): BelongsTo
    {
        return $this->belongsTo(ShootingItinerary::class, 'shooting_itinerary_id');
    }
}
