<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminLogs extends Model
{
    protected $table = 'admin_logs';
    protected $fillable = [
        'type',
        'action',
        'data'
    ];
    protected $casts = [
        'data' => 'json'
    ];
}
