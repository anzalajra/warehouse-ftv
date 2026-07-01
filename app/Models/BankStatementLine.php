<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankStatementLine extends Model
{
    protected $fillable = [
        'finance_account_id',
        'date',
        'description',
        'amount',
        'reference',
        'matched_transaction_id',
        'reconciled_at',
        'import_batch',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'reconciled_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinanceAccount::class, 'finance_account_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(FinanceTransaction::class, 'matched_transaction_id');
    }

    public function isMatched(): bool
    {
        return $this->matched_transaction_id !== null;
    }
}
