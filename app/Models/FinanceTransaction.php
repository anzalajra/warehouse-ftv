<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FinanceTransaction extends Model
{
    protected $fillable = [
        'finance_account_id',
        'user_id',
        'type',
        'amount',
        'date',
        'category',
        'description',
        'reference_type',
        'reference_id',
        'payment_method',
        'proof_document',
        'notes',
        'tax_amount',
        'tax_invoice_number',
        'reconciled_at',
        'currency',
        'exchange_rate',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'reconciled_at' => 'datetime',
        'exchange_rate' => 'decimal:6',
    ];

    /** Cash effect signed for reconciliation: + inflow, − outflow. */
    public function signedAmount(): float
    {
        return match ($this->type) {
            self::TYPE_INCOME, self::TYPE_DEPOSIT_IN => (float) $this->amount,
            self::TYPE_EXPENSE, self::TYPE_DEPOSIT_OUT => -(float) $this->amount,
            default => 0.0,
        };
    }

    /** Amount expressed in the base currency for GL posting (amount × exchange_rate). */
    public function baseAmount(): float
    {
        $rate = (float) ($this->exchange_rate ?: 1);

        return round((float) $this->amount * ($rate > 0 ? $rate : 1), 2);
    }

    public const TYPE_INCOME = 'income';
    public const TYPE_EXPENSE = 'expense';
    public const TYPE_TRANSFER = 'transfer';
    public const TYPE_DEPOSIT_IN = 'deposit_in';
    public const TYPE_DEPOSIT_OUT = 'deposit_out';

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinanceAccount::class, 'finance_account_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function journalEntry(): \Illuminate\Database\Eloquent\Relations\MorphOne
    {
        return $this->morphOne(JournalEntry::class, 'reference');
    }
    
    protected static function boot()
    {
        parent::boot();

        static::created(function ($transaction) {
            $account = $transaction->account;
            if (in_array($transaction->type, [self::TYPE_INCOME, self::TYPE_DEPOSIT_IN])) {
                $account->increment('balance', $transaction->amount);
            } elseif (in_array($transaction->type, [self::TYPE_EXPENSE, self::TYPE_DEPOSIT_OUT])) {
                $account->decrement('balance', $transaction->amount);
            }
        });

        static::deleted(function ($transaction) {
            $account = $transaction->account;
            if (in_array($transaction->type, [self::TYPE_INCOME, self::TYPE_DEPOSIT_IN])) {
                $account->decrement('balance', $transaction->amount);
            } elseif (in_array($transaction->type, [self::TYPE_EXPENSE, self::TYPE_DEPOSIT_OUT])) {
                $account->increment('balance', $transaction->amount);
            }
        });
    }
}
