<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    public const STATUSES = [
        'present'     => 'Present',
        'absent'      => 'Absent',
        'sick'        => 'Sick',
        'leave'       => 'Leave',
        'holiday'     => 'Holiday',
        'other'       => 'Other',
        'non_working' => 'Non-Working Day',
    ];

    protected $fillable = [
        'employee_id',
        'date',
        'status',
        'time_in',
        'time_out',
        'hours_worked',
        'note',
        'marked_by',
    ];

    protected $casts = [
        'date'          => 'date',
        'hours_worked'  => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function markedBy()
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
