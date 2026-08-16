<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\LeaveRecord;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LeaveController extends Controller
{
    /**
     * Year picker — the "years drill-down" landing page.
     */
    public function years()
    {
        $currentYear = (int) now()->format('Y');

        $activeYears = LeaveRecord::selectRaw('DISTINCT year')->pluck('year')->toArray();
        $window = range($currentYear - 2, $currentYear + 1);

        $years = collect(array_unique(array_merge($window, $activeYears)))
            ->sortDesc()
            ->values();

        return view('dashboard.leave.years', compact('years', 'currentYear'));
    }

    /**
     * Master leave sheet for one year — employees x Jan..Dec, editable.
     */
    public function sheet(Request $request, int $year)
    {
        $employees = Employee::where('employment_status', 'Active')
            ->when($request->query('department'), fn ($q, $d) => $q->where('department', $d))
            ->orderBy('first_name')
            ->get();

        $records = LeaveRecord::where('year', $year)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get()
            ->groupBy('employee_id');

        $rows = [];
        foreach ($employees as $employee) {
            $monthly = array_fill(1, 12, 0.0);
            foreach ($records->get($employee->id, collect()) as $r) {
                $monthly[$r->month] = (float) $r->days_taken;
            }

            $total = array_sum($monthly);
            $calc  = $this->recalculateEmployeeLeave($employee, $total);

            $rows[] = [
                'employee'      => $employee,
                'monthly'       => $monthly,
                'total'         => $total,
                'entitled'      => $calc['entitled'],
                'balance'       => $calc['balance'],
                'amountPayable' => $calc['amountPayable'],
            ];
        }

        return view('dashboard.leave.sheet', [
            'year'        => $year,
            'rows'        => $rows,
            'departments' => Employee::where('employment_status', 'Active')
                ->pluck('department')->filter()->unique()->sort()->values(),
        ]);
    }

    /**
     * Single-cell realtime update — called on blur/change of one day-taken
     * input. Upserts that one LeaveRecord, recomputes the employee's yearly
     * total, persists the recalculated leave_days_balance, and returns the
     * fresh row totals as JSON so the frontend can update in place without
     * a full page reload.
     */
    public function updateCell(Request $request, int $year): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'month'       => 'required|integer|between:1,12',
            'days_taken'  => 'nullable|numeric|min:0|max:31',
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);
        $days = $validated['days_taken'] ?? 0;

        LeaveRecord::updateOrCreate(
            ['employee_id' => $employee->id, 'year' => $year, 'month' => $validated['month']],
            ['days_taken' => $days]
        );

        $total = (float) LeaveRecord::where('employee_id', $employee->id)
            ->where('year', $year)
            ->sum('days_taken');

        $calc = $this->recalculateEmployeeLeave($employee, $total, persist: true);

        return response()->json([
            'employee_id'   => $employee->id,
            'total'         => $total,
            'entitled'      => $calc['entitled'],
            'balance'       => $calc['balance'],
            'amountPayable' => $calc['amountPayable'],
        ]);
    }

    /**
     * Manual HR edit of the master sheet — bulk upsert of every cell,
     * then recalculate + persist every affected employee's balance so the
     * "Save Sheet" button stays consistent with the realtime cell updates.
     */
    public function sheetUpdate(Request $request, int $year)
    {
        $request->validate([
            'records'   => 'required|array',
            'records.*' => 'array',
            'records.*.*' => 'nullable|numeric|min:0|max:31',
        ]);

        foreach ($request->input('records', []) as $employeeId => $months) {
            foreach ($months as $month => $days) {
                LeaveRecord::updateOrCreate(
                    ['employee_id' => $employeeId, 'year' => $year, 'month' => (int) $month],
                    ['days_taken' => $days ?: 0]
                );
            }

            $employee = Employee::find($employeeId);
            if ($employee) {
                $total = (float) LeaveRecord::where('employee_id', $employeeId)
                    ->where('year', $year)
                    ->sum('days_taken');

                $this->recalculateEmployeeLeave($employee, $total, persist: true);
            }
        }

        return back()->with('success', "Leave sheet for {$year} updated.");
    }

    /**
     * Shared balance/rate/amount-payable math, used by sheet() (read-only
     * display), updateCell() (realtime single-cell save), and sheetUpdate()
     * (bulk save) — kept in one place so all three stay in sync.
     *
     * balance is always recomputed as entitled - total taken. This means
     * leave_days_balance is treated as a *derived* value driven by the
     * leave_records table, not a standing manual figure — so any HR
     * override previously placed directly on the employee record will be
     * superseded the next time a day is recorded here. If you need to
     * preserve manual overrides independent of days taken, that's a
     * behavior change worth flagging before shipping this.
     */
    private function recalculateEmployeeLeave(Employee $employee, float $totalTaken, bool $persist = false): array
    {
        $entitled = (float) ($employee->leave_days_entitled ?? 24);
        $balance  = $entitled - $totalTaken;

        $grossForLeave = $employee->natural_gross_salary ?? $employee->salary ?? 0;
        $dailyRate = $employee->leave_days_value ?? ($grossForLeave / $employee->working_days_per_month);
        $amountPayable = round($balance * $dailyRate, 2);

        if ($persist && $employee->leave_days_balance !== $balance) {
            $employee->update(['leave_days_balance' => $balance]);
        }

        return [
            'entitled'      => $entitled,
            'balance'       => $balance,
            'amountPayable' => $amountPayable,
        ];
    }
}