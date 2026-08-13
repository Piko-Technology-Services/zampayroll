<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Holiday;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AttendanceReportController extends Controller
{
    public function index(Request $request)
    {
        [$rows, $start, $workingDaysInMonth] = $this->buildReport($request);

        return view('dashboard.attendance.report', [
            'rows'               => $rows,
            'start'              => $start,
            'prevMonth'          => $start->copy()->subMonth()->format('Y-m'),
            'nextMonth'          => $start->copy()->addMonth()->format('Y-m'),
            'workingDaysInMonth' => $workingDaysInMonth,
            'departments'        => Employee::where('employment_status', 'Active')
                ->pluck('department')->filter()->unique()->sort()->values(),
        ]);
    }

    public function export(Request $request)
    {
        [$rows, $start] = $this->buildReport($request);

        $filename = 'attendance_summary_' . $start->format('Y-m') . '.csv';

        $callback = function () use ($rows) {
            $file = fopen('php://output', 'w');
            fputcsv($file, [
                'Employee ID', 'Name', 'Department', 'Present', 'Absent', 'Sick',
                'Leave', 'Holiday', 'Other', 'Total Hours', 'Attendance Rate %',
            ]);
            foreach ($rows as $r) {
                fputcsv($file, [
                    $r['employee_id'], $r['name'], $r['department'],
                    $r['counts']['present'], $r['counts']['absent'], $r['counts']['sick'],
                    $r['counts']['leave'], $r['counts']['holiday'], $r['counts']['other'],
                    $r['hours'], $r['rate'],
                ]);
            }
            fclose($file);
        };

        return response()->streamDownload($callback, $filename);
    }

    private function buildReport(Request $request): array
    {
        $month = $request->query('month');
        $start = $month
            ? Carbon::createFromFormat('Y-m', $month)->startOfMonth()
            : now()->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $workDays = array_map('intval', $request->user()?->company?->work_days ?: [1, 2, 3, 4, 5]);
        $holidays = Holiday::all();

        $workingDaysInMonth = 0;
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $isHoliday = $holidays->contains(fn ($h) => $h->is_recurring
                ? $h->date->format('m-d') === $cursor->format('m-d')
                : $h->date->format('Y-m-d') === $cursor->format('Y-m-d'));

            if (in_array($cursor->dayOfWeekIso, $workDays, true) && ! $isHoliday) {
                $workingDaysInMonth++;
            }
            $cursor->addDay();
        }

        $employees = Employee::where('employment_status', 'Active')
            ->when($request->query('department'), fn ($q, $d) => $q->where('department', $d))
            ->orderBy('first_name')
            ->get();

        $attendance = Attendance::whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get()
            ->groupBy('employee_id');

        $rows = [];
        foreach ($employees as $employee) {
            $records = $attendance->get($employee->id, collect());

            $counts = array_fill_keys(array_keys(Attendance::STATUSES), 0);
            $hours = 0;
            foreach ($records as $r) {
                $counts[$r->status] = ($counts[$r->status] ?? 0) + 1;
                $hours += (float) ($r->hours_worked ?? 0);
            }

            $rate = $workingDaysInMonth > 0
                ? round(($counts['present'] / $workingDaysInMonth) * 100, 1)
                : 0.0;

            $rows[] = [
                'employee_id' => $employee->employee_id,
                'name'        => $employee->first_name . ' ' . $employee->last_name,
                'department'  => $employee->department ?? '—',
                'photo'       => $employee->passport_photo,
                'counts'      => $counts,
                'hours'       => round($hours, 2),
                'rate'        => $rate,
            ];
        }

        return [$rows, $start, $workingDaysInMonth];
    }
}
