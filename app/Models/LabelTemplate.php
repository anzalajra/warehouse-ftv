<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * A reusable Bluetooth label design saved on the server.
 *
 * `design` holds the LuckPrinter editor's serialize() output
 * ({v, label, orientation, elements}). Exactly one row may be flagged
 * `is_default` — that design auto-fills the print queue when units are
 * sent from the product-unit "Print via Bluetooth" popup.
 */
class LabelTemplate extends Model
{
    protected $fillable = [
        'name',
        'design',
        'is_default',
    ];

    protected $casts = [
        'design' => 'array',
        'is_default' => 'boolean',
    ];

    /** Make this the sole default template (unsets the flag on all others). */
    public function setAsDefault(): void
    {
        DB::transaction(function () {
            static::where('id', '!=', $this->id)->update(['is_default' => false]);
            $this->forceFill(['is_default' => true])->save();
        });
    }
}
