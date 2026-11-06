<?php

namespace App\Services\Compliance;

use App\Models\Compliance\CompanyDocument;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who may see which repository document. Enforced on every list, preview and download, in the query itself,
 * so changing an id in the URL never reveals another person's document (the company scope already stops other tenants).
 */
class DocumentAccess
{
    public static function isManager(User $user): bool
    {
        return $user->hasPermission('compliance.documents');
    }

    /** Staff-side visibility. */
    public static function scopeForStaff(Builder $q, User $user): Builder
    {
        if (! $user->isStaff() || ! $user->hasPermission('compliance.documents.view') && ! self::isManager($user)) {
            return $q->whereRaw('1 = 0');
        }
        if (self::isManager($user) || in_array($user->roleKey(), Permissions::ADMIN_ROLES, true)) {
            return $q; // HR / administrators: the whole repository
        }

        $myDept = $user->employee?->department;

        return $q->where(function (Builder $w) use ($user, $myDept) {
            $w->where('access_level', 'company');
            if ($user->hasPermission('payroll.view') || $user->hasPermission('reports.statutory')) {
                $w->orWhere('access_level', 'finance');
            }
            if ($user->hasPermission('compliance.view')) {
                $w->orWhere('access_level', 'management');
            }
            if ($myDept) {
                $w->orWhere(fn ($d) => $d->where('access_level', 'department')->where('access_department', $myDept));
            }
            if ($user->employee_id) {
                $w->orWhere(fn ($d) => $d->where('access_level', 'employee')->where('employee_id', $user->employee_id));
            }
        });
    }

    /** Portal visibility: only documents explicitly shared with employees, and only theirs or everyone's. */
    public static function scopeForEmployee(Builder $q, User $user): Builder
    {
        $employee = $user->employee;
        if (! $employee) {
            return $q->whereRaw('1 = 0');
        }

        return $q->where('share_with_employees', true)->where('status', 'active')->where(function (Builder $w) use ($employee) {
            $w->where('access_level', 'company')
                ->orWhere(fn ($d) => $d->where('access_level', 'employee')->where('employee_id', $employee->id))
                ->orWhere(fn ($d) => $d->where('access_level', 'department')->where('access_department', $employee->department)->whereNotNull('access_department'));
        });
    }

    public static function canStaffView(User $user, CompanyDocument $doc): bool
    {
        return self::scopeForStaff(CompanyDocument::query()->whereKey($doc->id), $user)->exists();
    }

    public static function canEmployeeView(User $user, CompanyDocument $doc): bool
    {
        return self::scopeForEmployee(CompanyDocument::query()->whereKey($doc->id), $user)->exists();
    }
}
