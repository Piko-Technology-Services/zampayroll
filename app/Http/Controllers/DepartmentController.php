<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * People → Departments: an overview of every department (headcount, who's away,
 * new joiners, monthly pay) and where HR creates, renames and removes them.
 */
class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        // Board members aren't staff headcount.
        $employees = Employee::query()->workforce()->get(['id', 'first_name', 'last_name', 'department', 'position', 'employment_status', 'contract_start', 'net_salary', 'passport_photo', 'gender', 'created_at']);
        $records = Department::query()->with('head:id,first_name,last_name,position')->get()->keyBy(fn ($d) => mb_strtolower($d->name));
        $onLeave = LeaveRequest::query()->where('status', 'approved')
            ->whereDate('start_date', '<=', today())->whereDate('end_date', '>=', today())->pluck('employee_id')->flip();
        $showPay = (bool) $request->user()?->hasPermission('payroll.view');
        $active = fn ($e) => strcasecmp((string) $e->employment_status, 'Active') === 0;
        $colours = Department::colours();

        $build = function (string $name, $people, ?Department $rec) use ($onLeave, $active, $colours) {
            $live = $people->filter($active);

            return (object) [
                'name'      => $name,
                'record'    => $rec,
                'color'     => $rec?->color ?: ($colours[$name] ?? Department::PALETTE[0]),
                'total'     => $people->count(),
                'active'    => $live->count(),
                'away'      => $live->filter(fn ($e) => isset($onLeave[$e->id]))->count(),
                'new'       => $people->filter(fn ($e) => ($e->contract_start ?? $e->created_at) && \Carbon\Carbon::parse($e->contract_start ?? $e->created_at)->gt(now()->subDays(30)))->count(),
                'pay'       => (float) $live->sum('net_salary'),
                'people'    => $people->sortBy('first_name')->values(),
            ];
        };

        $grouped = $employees->groupBy(fn ($e) => trim((string) $e->department));
        $departments = Department::names()->map(fn ($n) => $build($n, $grouped->first(fn ($g, $k) => mb_strtolower($k) === mb_strtolower($n)) ?? collect(), $records[mb_strtolower($n)] ?? null));
        $unassigned = $grouped->get('', collect());

        return view('dashboard.departments.index', [
            'departments' => $departments->sortByDesc('active')->values(),
            'unassigned'  => $unassigned,
            'totals'      => (object) [
                'departments' => $departments->count(),
                'people'      => $employees->filter($active)->count(),
                'away'        => $employees->filter(fn ($e) => $active($e) && isset($onLeave[$e->id]))->count(),
                'pay'         => (float) $employees->filter($active)->sum('net_salary'),
            ],
            'showPay'     => $showPay,
            'canManage'   => (bool) $request->user()?->hasPermission('employees.manage'),
            'everyone'    => $employees->filter($active)->sortBy('first_name')->values(),
            'palette'     => Department::PALETTE,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        if (Department::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])->exists()) {
            return back()->withErrors(['name' => 'There is already a department called ' . $data['name'] . '.'])->withInput();
        }

        $dept = Department::query()->create($data + ['color' => $data['color'] ?? Department::PALETTE[Department::query()->count() % count(Department::PALETTE)]]);
        Audit::record('department.created', $dept, "Department {$dept->name} created");

        if ($request->expectsJson()) {
            return response()->json(['id' => $dept->id, 'name' => $dept->name, 'color' => $dept->color]);
        }

        return back()->with('success', "{$dept->name} created. Choose it when you add or edit an employee.");
    }

    public function update(Request $request, Department $department)
    {
        $data = $this->validated($request, $department);
        $old = $department->name;

        DB::transaction(function () use ($department, $data, $old) {
            if ($data['name'] !== $old) {
                // Merge into an existing department with the new name, or rename.
                $clash = Department::query()->whereKeyNot($department->id)->whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])->first();
                Employee::query()->where('department', $old)->update(['department' => $clash?->name ?? $data['name']]);
                if ($clash) {
                    $department->delete();

                    return;
                }
            }
            $department->update($data);
        });

        Audit::record('department.updated', $department, $data['name'] !== $old ? "Department {$old} renamed to {$data['name']}" : "Department {$old} updated");

        return back()->with('success', $data['name'] !== $old ? "Renamed to {$data['name']} — every employee in it was updated." : 'Department saved.');
    }

    public function destroy(Request $request, Department $department)
    {
        $data = $request->validate(['move_to' => ['nullable', 'string', 'max:100']]);
        $count = Employee::query()->where('department', $department->name)->count();
        $target = filled($data['move_to'] ?? null) ? Department::ensure($data['move_to'])?->name : null;

        DB::transaction(function () use ($department, $target) {
            Employee::query()->where('department', $department->name)->update(['department' => $target]);
            $department->delete();
        });

        Audit::record('department.deleted', null, "Department {$department->name} removed" . ($count ? ", {$count} employee(s) moved to " . ($target ?? 'no department') : ''));

        return back()->with('success', "{$department->name} removed" . ($count ? " — {$count} " . \Illuminate\Support\Str::plural('person', $count) . ' moved to ' . ($target ?? '"No department"') : '') . '.');
    }

    private function validated(Request $request, ?Department $department = null): array
    {
        $request->merge(['name' => trim(preg_replace('/\s+/', ' ', (string) $request->input('name')))]);

        return $request->validate([
            'name'             => ['required', 'string', 'max:100'],
            'description'      => ['nullable', 'string', 'max:500'],
            'color'            => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'head_employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->where('company_id', \App\Support\Tenancy::companyId())],
        ], ['name.required' => 'Give the department a name.']);
    }
}
