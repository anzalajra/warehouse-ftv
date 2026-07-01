<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class JournalEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_number',
        'date',
        'description',
        'reference_type',
        'reference_id',
        'reversal_of_id',
        'reversed_at',
        'user_id',
    ];

    protected $casts = [
        'date' => 'date',
        'reversed_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(JournalEntryItem::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /** The original entry this one reverses (if any). */
    public function reversalOf()
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_of_id');
    }

    /** The reversing entry posted against this one (if any). */
    public function reversedBy()
    {
        return $this->hasOne(JournalEntry::class, 'reversal_of_id');
    }

    public function isReversal(): bool
    {
        return $this->reversal_of_id !== null;
    }

    public function isReversed(): bool
    {
        return $this->reversed_at !== null;
    }
}
