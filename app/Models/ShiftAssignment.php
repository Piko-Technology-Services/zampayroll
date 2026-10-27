<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One person's shift (or day off when shift_id is null) on one day. */
class ShiftAssignment extends Model
{
    use \App\Models\Concerns\BelongsToCompany;

    protected $fillable = ['company_id', 'employee_id', 'date', 'shift_id', 'note', 'assigned_by'];

    protected $casts = ['date' => 'date'];

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function assigner()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function isOff(): bool
    {
        return $this->shift_id === null;
    }
}
