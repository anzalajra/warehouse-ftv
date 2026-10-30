<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MufiseOperation extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['payload'];

    protected $casts = ['payload' => 'encrypted:array', 'completed_at' => 'datetime'];
}
