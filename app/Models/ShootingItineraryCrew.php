<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShootingItineraryCrew extends Model
{
    protected $table = 'shooting_itinerary_crew';

    protected $fillable = ['position', 'name', 'nim', 'role'];

    public function itinerary(): BelongsTo
    {
        return $this->belongsTo(ShootingItinerary::class, 'shooting_itinerary_id');
    }
}
