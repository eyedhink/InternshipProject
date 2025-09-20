<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Morilog\Jalali\Jalalian;

class Order extends Model
{
    protected $fillable = [
        'info',
        'date'
    ];

    protected $casts = [
        'info' => 'array',
    ];

    public function getDateShamsiAttribute(): string
    {
        return Jalalian::forge($this->date)->format('Y/m/d');
    }
}
