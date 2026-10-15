<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use \App\Models\Concerns\BelongsToCompany;

    protected $fillable = ['name', 'date', 'is_recurring'];

    protected $casts = [
        'date'         => 'date',
        'is_recurring' => 'boolean',
    ];
}
