<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Bill extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_name',
        'bill_number',
        'bill_date',
        'due_date',
        'amount',
        'paid_amount',
        'status',
        'description',
        'category',
        'proof_document',
        'user_id',
        'tax_amount',
        'tax_invoice_number',
    ];

    protected $casts = [
        'bill_date' => 'date',
        'due_date' => 'date',
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_PAID = 'paid';
    public const STATUS_OVERDUE = 'overdue';

    protected static function booted(): void
    {
        // Accrue the AP double entry (Dr Beban / Cr Hutang Usaha 2-1100) so the
        // GL Balance Sheet reflects the payable. No-op outside advanced mode.
        static::created(function (Bill $bill) {
            \App\Services\PayableAccountingService::postBillIssued($bill);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): MorphMany
    {
        return $this->morphMany(FinanceTransaction::class, 'reference');
    }
}
