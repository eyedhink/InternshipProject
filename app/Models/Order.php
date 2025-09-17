<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'info',
        'order_submitted_at'
    ];

    protected $casts = [
        'info' => 'array',
        'order_submitted_at' => 'datetime'
    ];
}
