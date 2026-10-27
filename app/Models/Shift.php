<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/** A shift the company works, e.g. Morning 06:00–14:00. Defined by the administrator. */
class Shift extends Model
{
    use \App\Models\Concerns\BelongsToCompany;

    public const PALETTE = ['#00742D', '#C8742B', '#2C6E9B', '#7A4E9C', '#B8892B', '#1F8A7A', '#A0473E', '#4F6B2F', '#3D4B5C'];

    protected $fillable = ['company_id', 'name', 'code', 'start_time', 'end_time', 'break_minutes', 'color', 'active', 'sort'];

    protected $casts = ['active' => 'boolean', 'break_minutes' => 'integer', 'sort' => 'integer'];

    public function assignments()
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    /** Ends after midnight. */
    public function isOvernight(): bool
    {
        return substr($this->end_time, 0, 5) <= substr($this->start_time, 0, 5);
    }

    /** Paid hours (length minus break). */
    public function hours(): float
    {
        $start = Carbon::createFromFormat('H:i', substr($this->start_time, 0, 5));
        $end = Carbon::createFromFormat('H:i', substr($this->end_time, 0, 5));
        if ($end->lte($start)) {
            $end->addDay();
        }

        return round(max(0, $start->diffInMinutes($end) - (int) $this->break_minutes) / 60, 2);
    }

    public function times(): string
    {
        return substr($this->start_time, 0, 5) . '–' . substr($this->end_time, 0, 5);
    }

    public function toCell(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'code' => $this->code, 'times' => $this->times(), 'hours' => $this->hours(), 'color' => $this->color, 'night' => $this->isOvernight()];
    }
}
