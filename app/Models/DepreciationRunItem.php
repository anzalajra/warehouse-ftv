<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepreciationRunItem extends Model
{
    protected $fillable = [
        'depreciation_run_id',
        'product_unit_id',
        'amount',
        'accumulated_after',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'accumulated_after' => 'decimal:2',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(DepreciationRun::class, 'depreciation_run_id');
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class);
    }
}
