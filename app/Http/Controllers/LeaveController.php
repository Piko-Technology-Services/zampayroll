<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\LeaveRecord;
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    /**
     * Year picker — the "years drill-down" landing page.
     */
    public function years()
    {
        $currentYear = (int) now()->format('Y');

        // Years that already have leave activity, plus a sensible window
        // around today so a brand-new company still sees clickable years.
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
            $entitled = (float) ($employee->leave_days_entitled ?? 24);
            $balance = $employee->leave_days_balance !== null
                ? (float) $employee->leave_days_balance
                : max(0, $entitled - $total);
            $dailyRate = $employee->leave_days_value ?? ($employee->salary ? $employee->salary / 26 : 0);
            $amountPayable = round($balance * $dailyRate, 2);

            $rows[] = [
                'employee'      => $employee,
                'monthly'       => $monthly,
                'total'         => $total,
                'entitled'      => $entitled,
                'balance'       => $balance,
                'amountPayable' => $amountPayable,
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
     * Manual HR edit of the master sheet — bulk upsert of every cell.
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
        }

        return back()->with('success', "Leave sheet for {$year} updated.");
    }
}
