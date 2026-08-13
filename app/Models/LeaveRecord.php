<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveRecord extends Model
{
    protected $fillable = ['employee_id', 'year', 'month', 'days_taken'];

    protected $casts = [
        'year'       => 'integer',
        'month'      => 'integer',
        'days_taken' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
