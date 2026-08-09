<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DemoRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'full_name',
        'email',
        'phone',
        'company_name',
        'company_size',
        'industry',
        'message',
        'admin_notified_at',
        'user_notified_at',
    ];

    protected $casts = [
        'admin_notified_at' => 'datetime',
        'user_notified_at' => 'datetime',
    ];
}
