<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Holiday;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Working-day maths shared by the public leave form and the self-service portal:
 * counts days between two dates excluding the company's non-working weekdays
 * and its public holidays (recurring or one-off).
 */
class LeaveDayCalculator
{
    private array $workDays;

    private Collection $holidays;

    public function __construct(private Employee $employee)
    {
        $this->workDays = array_map('intval', $employee->company?->work_days ?: [1, 2, 3, 4, 5]);
        $this->holidays = Holiday::all(); // scoped to the employee's company
    }

    public function businessDays(Carbon $start, Carbon $end): float
    {
        $days = 0;

        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            if ($this->isWorkingDay($cursor)) {
                $days++;
            }
        }

        return $days;
    }

    public function nextBusinessDay(Carbon $end): Carbon
    {
        $cursor = $end->copy()->addDay();

        for ($guard = 0; ! $this->isWorkingDay($cursor) && $guard < 60; $guard++) {
            $cursor->addDay();
        }

        return $cursor;
    }

    public function isWorkingDay(Carbon $date): bool
    {
        $isHoliday = $this->holidays->contains(fn ($h) => $h->is_recurring
            ? $h->date->format('m-d') === $date->format('m-d')
            : $h->date->format('Y-m-d') === $date->format('Y-m-d'));

        return in_array($date->dayOfWeekIso, $this->workDays, true) && ! $isHoliday;
    }

    public function holidaysBetween(Carbon $start, Carbon $end): Collection
    {
        return $this->holidays->filter(function ($h) use ($start, $end) {
            for ($c = $start->copy(); $c->lte($end); $c->addDay()) {
                if ($h->is_recurring ? $h->date->format('m-d') === $c->format('m-d') : $h->date->isSameDay($c)) {
                    return true;
                }
            }

            return false;
        })->values();
    }
}
