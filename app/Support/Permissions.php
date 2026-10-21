<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Everything a team member can be allowed to do, the default roles, and
 * which screens each permission unlocks.
 */
class Permissions
{
    public const GROUPS = [
        'People' => [
            'employees.view'   => 'See employee records',
            'employees.manage' => 'Add, edit and remove employees',
            'employees.import' => 'Import employees from Excel/CSV',
            'portal.manage'    => 'Create and manage employee portals',
        ],
        'Attendance' => [
            'attendance.view'   => 'See attendance',
            'attendance.manage' => 'Mark attendance, working days & holidays',
        ],
        'Leave' => [
            'leave.view'    => 'See leave requests and the leave sheet',
            'leave.approve' => 'Approve or reject leave, edit the leave sheet',
        ],
        'Overtime & loans' => [
            'overtime.approve' => 'Review and approve overtime',
            'loans.view'       => 'See loan requests and the loan ledger',
            'loans.manage'     => 'Approve loans and record repayments',
            'loans.disburse'   => 'Record loan payouts (money paid to the employee)',
        ],
        'Payroll' => [
            'payroll.view'     => 'See payroll runs and payslips',
            'payroll.run'      => 'Prepare payroll, adjustments and payslip emails',
            'payroll.finalize' => 'Set up the payroll approval chain',
            'payroll.rules'    => 'Change payroll rules (allowances, deductions)',
        ],
        'Reports' => [
            'reports.view'      => 'Management reports and bank files',
            'reports.statutory' => 'ZRA, NAPSA, NHIMA and WCFCB returns',
        ],
        'Administration' => [
            'settings.company' => 'Company settings and bank templates',
            'billing.manage'   => 'Subscription, invoices and payments to ZamPayroll',
            'team.manage'      => 'Invite people and manage roles',
            'ai.use'           => 'Use AI mode',
        ],
    ];

    public const DEFAULT_ROLES = [
        'company_admin' => [
            'name' => 'Administrator',
            'description' => 'Full access, including company settings and team roles.',
            'permissions' => ['*'],
        ],
        'hr' => [
            'name' => 'HR Officer',
            'description' => 'Runs people, attendance, leave and day-to-day requests.',
            'permissions' => ['employees.view', 'employees.manage', 'employees.import', 'portal.manage', 'attendance.view', 'attendance.manage',
                'leave.view', 'leave.approve', 'overtime.approve', 'loans.view', 'payroll.view', 'reports.view', 'ai.use'],
        ],
        'payroll' => [
            'name' => 'Payroll Officer',
            'description' => 'Prepares payroll, loans and statutory returns.',
            'permissions' => ['employees.view', 'attendance.view', 'leave.view', 'overtime.approve', 'loans.view', 'loans.manage', 'loans.disburse',
                'payroll.view', 'payroll.run', 'payroll.rules', 'reports.view', 'reports.statutory', 'ai.use'],
        ],
        'manager' => [
            'name' => 'Manager',
            'description' => 'Approves leave and overtime, sees team attendance.',
            // No payroll or reports: managers approve time off without seeing salaries.
            'permissions' => ['employees.view', 'attendance.view', 'attendance.manage', 'leave.view', 'leave.approve', 'overtime.approve', 'ai.use'],
        ],
        'viewer' => [
            'name' => 'Viewer / Auditor',
            'description' => 'Read-only access to records, payroll and reports.',
            'permissions' => ['employees.view', 'attendance.view', 'leave.view', 'loans.view', 'payroll.view', 'reports.view', 'reports.statutory'],
        ],
    ];

    /** Older role names map onto the new roles. */
    public const LEGACY_ROLE_MAP = ['employee' => 'viewer', 'super_admin' => 'company_admin'];

    /**
     * Route-name patterns => required permission. First match wins.
     * Routes not listed only need a signed-in staff account (dashboard, own profile).
     */
    public const ROUTE_MAP = [
        'employees.portal.*'           => 'portal.manage',
        'employees.changes.*'          => 'employees.manage',
        'employees.import'             => 'employees.import',
        'employees.import.*'           => 'employees.import',
        'employees.sample-csv'         => 'employees.import',
        'employees.create'             => 'employees.manage',
        'employees.store'              => 'employees.manage',
        'employees.edit'               => 'employees.manage',
        'employees.update'             => 'employees.manage',
        'employees.destroy'            => 'employees.manage',
        'employees.*'                  => 'employees.view',
        'departments.*'                => 'employees.view',

        'attendance.calendar'          => 'attendance.view',
        'attendance.day'               => 'attendance.view',
        'attendance.report*'           => 'attendance.view',
        'attendance.*'                 => 'attendance.manage',

        'leave.approve'                => 'leave.approve',
        'leave.cancel'                 => 'leave.approve',
        'leave.record'                 => 'leave.approve',
        'leave.check'                  => 'leave.approve',
        'leave.types.*'                => 'leave.approve',
        'leave.reject'                 => 'leave.approve',
        'leave.sheet.update'           => 'leave.approve',
        'leave.cell.update'            => 'leave.approve',
        'leave.*'                      => 'leave.view',

        'overtime.*'                   => 'overtime.approve',

        'loan.disburse'                => 'loans.disburse',
        'loan.dashboard'               => 'loans.view',
        'loan.ledger.index'            => 'loans.view',
        'loan.ledger.show'             => 'loans.view',
        'loan.*'                       => 'loans.manage',

        'payroll.runs.finalize'        => 'payroll.finalize',
        'payroll.rules.*'              => 'payroll.rules',
        'payroll.reports.napsa.*'      => 'reports.statutory',
        'payroll.reports.zra.*'        => 'reports.statutory',
        'payroll.reports.nhima.*'      => 'reports.statutory',
        'payroll.reports.wcfcb.*'      => 'reports.statutory',
        'payroll.reports.*'            => 'reports.view',
        'reports.*'                    => 'reports.view',
        'payroll.index'                => 'payroll.view',
        'payroll.show'                 => 'payroll.view',
        'payroll.print'                => 'payroll.view',
        'payroll.pdf'                  => 'payroll.view',
        'payroll.runs.index'           => 'payroll.view',
        'payroll.runs.show'            => 'payroll.view',
        'payroll.runs.payslips'        => 'payroll.view',
        'payroll.runs.hidden'          => 'payroll.view',
        'payroll.runs.trash'           => 'payroll.view',
        'payroll.runs.employee.summary' => 'payroll.view',
        'payroll.runs.editor'          => 'payroll.view',
        // Approvers are decided by the approval chain itself (checked in the controller).
        'payroll.runs.approve'         => 'payroll.view',
        'payroll.runs.sendBack'        => 'payroll.view',
        'payroll-approvals.*'          => 'payroll.finalize',
        'payroll.*'                    => 'payroll.run',

        'company.*'                    => 'settings.company',
        'billing.*'                    => 'billing.manage',
        'bank-templates.*'             => 'settings.company',
        'invitations.store'            => 'team.manage',
        'invitations.revoke'           => 'team.manage',
        'team.*'                       => 'team.manage',
        'ai.*'                         => 'ai.use',
    ];

    /** Unnamed legacy routes, matched by URI prefix. */
    public const URI_MAP = [
        'payroll/' => 'payroll.run',
    ];

    public static function all(): array
    {
        return array_merge(...array_values(array_map('array_keys', self::GROUPS)));
    }

    public static function exists(string $permission): bool
    {
        return in_array($permission, self::all(), true);
    }

    public static function label(string $permission): string
    {
        foreach (self::GROUPS as $perms) {
            if (isset($perms[$permission])) {
                return $perms[$permission];
            }
        }

        return $permission;
    }

    public static function forRequest(\Illuminate\Http\Request $request): ?string
    {
        $route = $request->route();
        $name = $route?->getName();

        if ($name) {
            foreach (self::ROUTE_MAP as $pattern => $permission) {
                if (\Illuminate\Support\Str::is($pattern, $name)) {
                    return $permission;
                }
            }

            return null;
        }

        $uri = ltrim($route?->uri() ?? $request->path(), '/');
        foreach (self::URI_MAP as $prefix => $permission) {
            if (str_starts_with($uri, $prefix)) {
                return $permission;
            }
        }

        return null;
    }

    /**
     * Create the standard roles for a company (missing ones only).
     */
    public static function seedDefaultRoles(int $companyId): void
    {
        $db = DB::connection();

        foreach (self::DEFAULT_ROLES as $key => $role) {
            $exists = $db->table('company_roles')->where('company_id', $companyId)->where('key', $key)->exists();
            if (! $exists) {
                $db->table('company_roles')->insert([
                    'company_id'  => $companyId,
                    'key'         => $key,
                    'name'        => $role['name'],
                    'description' => $role['description'],
                    'permissions' => json_encode($role['permissions']),
                    'is_system'   => true,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }
        }
    }
}
