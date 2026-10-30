<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MufiseAccount extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['access_token', 'pairing'];

    protected $casts = [
        'access_token' => 'encrypted',
        'pairing' => 'encrypted:array',
        'expires_at' => 'datetime',
        'next_poll_at' => 'datetime',
    ];
}
