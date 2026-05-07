<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KioskCommand extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_ACKED = 'acked';
    public const STATUS_FAILED = 'failed';

    public const COMMAND_SHUTDOWN = 'shutdown';
    public const COMMAND_RESTART = 'restart';

    protected $fillable = [
        'computer_id',
        'command',
        'status',
        'issued_by',
        'sent_at',
        'acked_at',
        'error',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'acked_at' => 'datetime',
    ];

    public function computer(): BelongsTo
    {
        return $this->belongsTo(Computer::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
