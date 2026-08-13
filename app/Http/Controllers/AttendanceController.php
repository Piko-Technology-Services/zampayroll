<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Holiday;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | CALENDAR (month view)
    |--------------------------------------------------------------------------
    */
    public function calendar(Request $request)
    {
        $month = $request->query('month');
        $start = $month
            ? Carbon::createFromFormat('Y-m', $month)->startOfMonth()
            : now()->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $workDays = $this->workDays($request);
        $holidays = Holiday::all();

        $totalEmployees = Employee::where('employment_status', 'Active')->count();

        $attendanceCounts = Attendance::whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('date, status, count(*) as c')
            ->groupBy('date', 'status')
            ->get()
            ->groupBy(fn ($row) => $row->date->format('Y-m-d'));

        $days = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $dateKey = $cursor->format('Y-m-d');
            $holidayMatch = $this->matchHoliday($holidays, $cursor);
            $isHoliday = (bool) $holidayMatch;
            $isWorkingDay = in_array($cursor->dayOfWeekIso, $workDays, true) && ! $isHoliday;

            $countsRaw = $attendanceCounts->get($dateKey, collect());
            $counts = [];
            foreach (Attendance::STATUSES as $key => $label) {
                $row = $countsRaw->firstWhere('status', $key);
                $counts[$key] = $row ? (int) $row->c : 0;
            }

            $days[$dateKey] = [
                'day'          => $cursor->day,
                'date'         => $dateKey,
                'carbon'       => $cursor->copy(),
                'is_working'   => $isWorkingDay,
                'is_holiday'   => $isHoliday,
                'holiday_name' => $holidayMatch->name ?? null,
                'total'        => $totalEmployees,
                'marked'       => array_sum($counts),
                'counts'       => $counts,
            ];

            $cursor->addDay();
        }

        return view('dashboard.attendance.calendar', [
            'days'            => $days,
            'start'           => $start,
            'leadingBlanks'   => $start->dayOfWeekIso - 1,
            'prevMonth'       => $start->copy()->subMonth()->format('Y-m'),
            'nextMonth'       => $start->copy()->addMonth()->format('Y-m'),
            'totalEmployees'  => $totalEmployees,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DAY DRILL-DOWN (mark attendance for one date)
    |--------------------------------------------------------------------------
    */
    public function day(Request $request, string $date)
    {
        $carbon = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();

        $workDays = $this->workDays($request);
        $holidayMatch = $this->matchHoliday(Holiday::all(), $carbon);
        $isHoliday = (bool) $holidayMatch;
        $isWorkingDay = in_array($carbon->dayOfWeekIso, $workDays, true) && ! $isHoliday;

        $employees = Employee::where('employment_status', 'Active')
            ->orderBy('first_name')
            ->get();

        $attendances = Attendance::where('date', $date)->get()->keyBy('employee_id');

        $counts = array_fill_keys(array_keys(Attendance::STATUSES), 0);
        foreach ($attendances as $a) {
            $counts[$a->status] = ($counts[$a->status] ?? 0) + 1;
        }

        return view('dashboard.attendance.day', [
            'carbon'        => $carbon,
            'date'          => $date,
            'employees'     => $employees,
            'attendances'   => $attendances,
            'isWorkingDay'  => $isWorkingDay,
            'isHoliday'     => $isHoliday,
            'holidayName'   => $holidayMatch->name ?? null,
            'counts'        => $counts,
            'departments'   => $employees->pluck('department')->filter()->unique()->sort()->values(),
            'importSkipped' => session('import_skipped', []),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SAVE ATTENDANCE FOR A DAY (bulk upsert from the table form)
    |--------------------------------------------------------------------------
    */
    public function store(Request $request, string $date)
    {
        $request->validate([
            'attendance'                => 'required|array',
            'attendance.*.status'       => 'required|in:' . implode(',', array_keys(Attendance::STATUSES)),
            'attendance.*.time_in'      => 'nullable|date_format:H:i',
            'attendance.*.time_out'     => 'nullable|date_format:H:i',
            'attendance.*.note'         => 'nullable|string|max:255',
        ]);

        foreach ($request->input('attendance') as $employeeId => $row) {
            $hours = $this->computeHours($row['time_in'] ?? null, $row['time_out'] ?? null);

            Attendance::updateOrCreate(
                ['employee_id' => $employeeId, 'date' => $date],
                [
                    'status'       => $row['status'],
                    'time_in'      => $row['time_in'] ?? null,
                    'time_out'     => $row['time_out'] ?? null,
                    'hours_worked' => $hours,
                    'note'         => $row['note'] ?? null,
                    'marked_by'    => $request->user()?->id,
                ]
            );
        }

        return redirect()->route('attendance.day', $date)
            ->with('success', 'Attendance saved for ' . Carbon::parse($date)->format('d M Y'));
    }

    /*
    |--------------------------------------------------------------------------
    | CSV IMPORT — employee_id, date, time_in, time_out, status
    |--------------------------------------------------------------------------
    */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt',
        ]);

        $file = fopen($request->file('file')->getRealPath(), 'r');
        $header = array_map('trim', fgetcsv($file));

        $employeesByEin = Employee::pluck('id', 'employee_id');

        $imported = 0;
        $skipped = [];
        $rowNum = 1;

        while ($row = fgetcsv($file)) {
            $rowNum++;

            if (count($header) !== count($row)) {
                $skipped[] = "Row {$rowNum}: column count doesn't match header";
                continue;
            }

            $data = array_combine($header, $row);

            $ein     = trim($data['employee_id'] ?? '');
            $date    = trim($data['date'] ?? '');
            $status  = trim($data['status'] ?? '') ?: 'present';
            $timeIn  = trim($data['time_in'] ?? '') ?: null;
            $timeOut = trim($data['time_out'] ?? '') ?: null;

            if ($ein === '' || ! isset($employeesByEin[$ein])) {
                $skipped[] = "Row {$rowNum}: unknown employee_id '{$ein}'";
                continue;
            }

            if ($date === '' || ! \DateTime::createFromFormat('Y-m-d', $date)) {
                $skipped[] = "Row {$rowNum}: invalid date '{$date}' (expected YYYY-MM-DD)";
                continue;
            }

            if (! array_key_exists($status, Attendance::STATUSES)) {
                $skipped[] = "Row {$rowNum}: invalid status '{$status}'";
                continue;
            }

            Attendance::updateOrCreate(
                ['employee_id' => $employeesByEin[$ein], 'date' => $date],
                [
                    'status'       => $status,
                    'time_in'      => $timeIn ? substr($timeIn, 0, 5) : null,
                    'time_out'     => $timeOut ? substr($timeOut, 0, 5) : null,
                    'hours_worked' => $this->computeHours($timeIn, $timeOut),
                    'marked_by'    => $request->user()?->id,
                ]
            );

            $imported++;
        }

        fclose($file);

        return back()
            ->with('success', "{$imported} attendance record(s) imported.")
            ->with('import_skipped', $skipped);
    }

    public function downloadSampleCsv()
    {
        $headers = ['employee_id', 'date', 'time_in', 'time_out', 'status'];
        $filename = 'attendance_import_template.csv';

        $callback = function () use ($headers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);
            fputcsv($file, ['EMP-2026-AB12C', now()->toDateString(), '08:00', '17:00', 'present']);
            fputcsv($file, ['EMP-2026-XY98Z', now()->toDateString(), '', '', 'absent']);
            fclose($file);
        };

        return response()->streamDownload($callback, $filename);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */
    private function workDays(Request $request): array
    {
        $company = $request->user()?->company;
        $days = $company?->work_days ?: [1, 2, 3, 4, 5]; // Mon-Fri default
 
        return array_map('intval', $days);
    }

    private function matchHoliday($holidays, Carbon $carbon): ?Holiday
    {
        return $holidays->first(function (Holiday $h) use ($carbon) {
            return $h->is_recurring
                ? $h->date->format('m-d') === $carbon->format('m-d')
                : $h->date->format('Y-m-d') === $carbon->format('Y-m-d');
        });
    }

    private function computeHours(?string $timeIn, ?string $timeOut): ?float
    {
        if (! $timeIn || ! $timeOut) {
            return null;
        }

        try {
            $in = Carbon::createFromFormat('H:i', substr($timeIn, 0, 5));
            $out = Carbon::createFromFormat('H:i', substr($timeOut, 0, 5));

            return round($out->diffInMinutes($in) / 60, 2);
        } catch (\Exception $e) {
            return null;
        }
    }
}
