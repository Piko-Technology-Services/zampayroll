<?php

namespace App\Services\Team;

use App\Mail\TeamAccessGrantedMail;
use App\Models\CompanyRole;
use App\Models\Employee;
use App\Models\User;
use App\Services\EmployeePortalService;
use App\Support\Audit;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Giving an employee a team role (HR, Manager, Payroll, Board member…).
 * They keep their employee portal and switch between the two dashboards.
 */
class TeamAccess
{
    public function __construct(private EmployeePortalService $portal)
    {
    }

    /** People who could be given a role: active employees without team access yet. */
    public function candidates()
    {
        return Employee::query()->where('employment_status', 'Active')->with('user')->orderBy('first_name')->get()
            ->filter(fn (Employee $e) => ! $e->user || $e->user->isEmployee())->values();
    }

    public function grant(Employee $employee, string $role, User $by): User
    {
        if (! CompanyRole::where('key', $role)->exists() || $role === User::ROLE_EMPLOYEE) {
            throw new RuntimeException('Pick a role.');
        }
        if (strcasecmp((string) $employee->employment_status, 'Active') !== 0) {
            throw new RuntimeException("{$employee->full_name} is not an active employee.");
        }

        $user = $employee->user;
        if ($user) {
            if ($user->isStaff()) {
                throw new RuntimeException("{$employee->full_name} already has {$user->roleName()} access. Change their role in the list instead.");
            }
            $user->forceFill(['role' => $role])->save();
            $this->notify($user, $by);
        } else {
            if ($problem = $this->portal->eligibilityProblem($employee)) {
                throw new RuntimeException("{$employee->full_name} {$problem}.");
            }
            [$user] = $this->portal->grant($employee, $by, $role); // emails sign-in details
        }

        $this->syncBoard($user->fresh());
        Audit::record('team.access_granted', $user, "{$employee->full_name} given " . $user->fresh()->roleName() . ' access');

        return $user->fresh();
    }

    /** Role changed from the team list: keep the employee record's "board member" flag in step. */
    public function syncBoard(User $user): void
    {
        $employee = $user->employee;
        if (! $employee) {
            return;
        }
        if ($user->isBoardMember() && ! $employee->isBoardMember()) {
            $employee->forceFill(['employment_nature' => 'BOARD'])->save();
        } elseif (! $user->isBoardMember() && $employee->isBoardMember() && $user->isStaff()) {
            $employee->forceFill(['employment_nature' => 'PERMANENT'])->save();
        }
    }

    /** Take team access away. Employees keep their portal; everyone else loses the account. */
    public function revoke(User $user): string
    {
        if ($user->employee_id) {
            // A board member's employee record stays marked "Board member" (still paid, no attendance/leave).
            $user->forceFill(['role' => User::ROLE_EMPLOYEE])->save();
            Audit::record('team.access_removed', $user, "{$user->name}'s team access removed (portal kept)");

            return "{$user->name} no longer has team access. They keep their employee portal.";
        }

        $user->delete();
        Audit::record('team.access_removed', null, "{$user->name}'s access removed");

        return "{$user->name} no longer has access.";
    }

    private function notify(User $user, User $by): void
    {
        try {
            Mail::to($user->email)->send(new TeamAccessGrantedMail($user, $by->name, Tenancy::company()?->name ?? 'your company'));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
