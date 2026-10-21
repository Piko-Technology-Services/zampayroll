<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\User;

/**
 * Who looks after whom. HR and administrators oversee everyone; a manager
 * sees people in their own department and anyone who lists them as supervisor.
 * A manager account not linked to an employee record sees the whole company.
 */
class TeamScope
{
    public static function isHr(User $user): bool
    {
        return $user->isCompanyAdmin() || $user->roleKey() === 'hr' || $user->hasPermission('employees.manage');
    }

    public static function canSee(User $user, ?Employee $employee): bool
    {
        if (! $employee) {
            return false;
        }
        if (self::isHr($user) || ! $user->employee) {
            return true;
        }
        $me = $user->employee;
        $sup = mb_strtolower(trim((string) $employee->supervisor));

        return ($me->department && $employee->department === $me->department)
            || ($sup !== '' && in_array($sup, array_filter([mb_strtolower($user->name), mb_strtolower($user->email), mb_strtolower((string) $me->full_name)]), true));
    }

    /** Limit a query on a model with an `employee` relation to the user's team. */
    public static function restrict($query, User $user)
    {
        if (self::isHr($user) || ! $user->employee) {
            return $query;
        }
        $me = $user->employee;
        $names = array_values(array_filter([$user->name, $user->email, $me->full_name]));

        return $query->whereHas('employee', fn ($e) => $e->where(function ($w) use ($me, $names) {
            if ($me->department) {
                $w->orWhere('department', $me->department);
            }
            $w->orWhereIn('supervisor', $names);
        }));
    }

    /** Email addresses of staff with a permission who look after this employee. */
    public static function approverEmails(Employee $employee, string $permission, bool $hrOnly = false): array
    {
        return User::query()->where('company_id', $employee->company_id)->get()
            ->filter(fn (User $u) => $u->isStaff() && $u->hasPermission($permission) && (int) $u->employee_id !== (int) $employee->id
                && ($hrOnly ? self::isHr($u) : self::canSee($u, $employee)))
            ->pluck('email')->filter()->unique()->values()->all();
    }
}
