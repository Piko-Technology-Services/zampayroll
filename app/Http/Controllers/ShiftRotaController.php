<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Support\Audit;
use App\Support\TeamScope;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Shift rota.
 *  - Administrators define the company's shifts (settings.company).
 *  - HR schedules anyone; a supervisor/manager schedules the people they look after (attendance.manage + TeamScope).
 *  - Everyone with attendance.view can see the rota for the people they look after.
 *  - Employees see their own shifts on the portal.
 */
class ShiftRotaController extends Controller
{
    /* ------------------------------------------------------------------ */
    /*  Weekly rota                                                         */
    /* ------------------------------------------------------------------ */

    public function index(Request $request)
    {
        $user = $request->user();
        $start = $this->weekStart($request->query('week'));
        $days = collect(range(0, 6))->map(fn ($i) => $start->copy()->addDays($i));
        $end = $days->last();

        $employees = TeamScope::restrictEmployees(Employee::query()->workforce()->where('employment_status', 'Active'), $user)
            ->orderBy('department')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'department', 'position', 'employee_id', 'supervisor']);
        $ids = $employees->pluck('id');

        $assign = ShiftAssignment::whereIn('employee_id', $ids)->whereBetween('date', [$start->toDateString(), $end->toDateString()])->get()
            ->keyBy(fn ($a) => $a->employee_id . '|' . $a->date->toDateString());

        $leave = LeaveRequest::whereIn('employee_id', $ids)->where('status', 'approved')
            ->whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $start)->get(['employee_id', 'start_date', 'end_date', 'leave_type']);

        $holidays = Holiday::all()->mapWithKeys(fn ($h) => [($h->is_recurring ? $start->year . $h->date->format('-m-d') : $h->date->toDateString()) => $h->name]);
        $workDays = array_map('intval', \App\Support\Tenancy::company()?->work_days ?: [1, 2, 3, 4, 5]);

        $shifts = Shift::orderBy('sort')->orderBy('start_time')->get();
        $byId = $shifts->keyBy('id');

        $rows = $employees->map(function ($e) use ($days, $assign, $leave, $byId) {
            $cells = [];
            $hours = 0;
            foreach ($days as $d) {
                $key = $d->toDateString();
                $a = $assign->get($e->id . '|' . $key);
                $onLeave = $leave->first(fn ($l) => (int) $l->employee_id === (int) $e->id && Carbon::parse($l->start_date)->lte($d) && Carbon::parse($l->end_date)->gte($d));
                $shift = $a && $a->shift_id ? $byId->get($a->shift_id) : null;
                $hours += $shift?->hours() ?? 0;
                $cells[$key] = ['v' => $a ? ($a->shift_id ?: 'off') : null, 'leave' => $onLeave ? (LeaveRequest::TYPES[$onLeave->leave_type] ?? 'Leave') : null, 'note' => $a?->note];
            }

            return [
                'id'    => $e->id,
                'name'  => trim($e->first_name . ' ' . $e->last_name),
                'ini'   => strtoupper(mb_substr((string) $e->first_name, 0, 1) . mb_substr((string) $e->last_name, 0, 1)),
                'dept'  => $e->department ?: 'No department',
                'role'  => $e->position,
                'cells' => $cells,
                'hours' => round($hours, 2),
            ];
        })->values();

        return view('dashboard.attendance.rota', [
            'start'       => $start,
            'days'        => $days,
            'rows'        => $rows,
            'shifts'      => $shifts,
            'holidays'    => $holidays,
            'workDays'    => $workDays,
            'departments' => $rows->pluck('dept')->unique()->sort()->values(),
            'canEdit'     => $user->hasPermission('attendance.manage'),
            'canDefine'   => $user->hasPermission('settings.company'),
            'isHr'        => TeamScope::isHr($user),
            'today'       => today()->toDateString(),
        ]);
    }

    /** Save changed cells: [{employee_id, date, value: shift id | "off" | null}]. */
    public function save(Request $request)
    {
        $data = $request->validate([
            'cells'               => 'required|array|max:1000',
            'cells.*.employee_id' => 'required|integer',
            'cells.*.date'        => 'required|date_format:Y-m-d',
            'cells.*.value'       => 'nullable',
            'cells.*.note'        => 'nullable|string|max:200',
        ]);

        $n = $this->apply($request, collect($data['cells']));

        return response()->json(['ok' => true, 'saved' => $n]);
    }

    /** Copy last week into this week, or repeat this week for the next few weeks. Only empty days are filled unless "overwrite". */
    public function copy(Request $request)
    {
        $data = $request->validate([
            'week'      => 'required|date_format:Y-m-d',
            'mode'      => 'required|in:last_week,repeat',
            'weeks'     => 'nullable|integer|min:1|max:12',
            'overwrite' => 'nullable|boolean',
        ]);
        $user = $request->user();
        $week = $this->weekStart($data['week']);
        $ids = TeamScope::restrictEmployees(Employee::query()->workforce()->where('employment_status', 'Active'), $user)->pluck('id');

        [$from, $targets] = $data['mode'] === 'last_week'
            ? [$week->copy()->subWeek(), [$week]]
            : [$week, collect(range(1, (int) ($data['weeks'] ?? 4)))->map(fn ($i) => $week->copy()->addWeeks($i))->all()];

        $source = ShiftAssignment::whereIn('employee_id', $ids)->whereBetween('date', [$from->toDateString(), $from->copy()->addDays(6)->toDateString()])->get();
        if ($source->isEmpty()) {
            return back()->with('error', $data['mode'] === 'last_week' ? 'Nothing was scheduled last week to copy.' : 'Nothing is scheduled this week to repeat.');
        }

        $cells = collect();
        foreach ($targets as $t) {
            foreach ($source as $a) {
                $cells->push(['employee_id' => $a->employee_id, 'date' => $t->copy()->addDays((int) round($from->diffInDays($a->date)))->toDateString(), 'value' => $a->shift_id ?: 'off', 'note' => $a->note]);
            }
        }
        if (! $request->boolean('overwrite')) {
            $taken = ShiftAssignment::whereIn('employee_id', $ids)->whereIn('date', $cells->pluck('date')->unique()->all())->get()
                ->map(fn ($a) => $a->employee_id . '|' . $a->date->toDateString())->flip();
            $cells = $cells->reject(fn ($c) => $taken->has($c['employee_id'] . '|' . $c['date']));
        }

        $n = $this->apply($request, $cells);
        $what = $data['mode'] === 'last_week' ? 'from last week' : 'into the next ' . count($targets) . ' weeks';

        return redirect()->route('attendance.rota', ['week' => $week->toDateString()])->with('success', "Copied {$n} " . ($n === 1 ? 'day' : 'days') . " {$what}.");
    }

    /* ------------------------------------------------------------------ */
    /*  Company shifts (administrator)                                      */
    /* ------------------------------------------------------------------ */

    public function shifts()
    {
        $shifts = Shift::withCount(['assignments as upcoming' => fn ($q) => $q->whereDate('date', '>=', today())])
            ->orderBy('sort')->orderBy('start_time')->get();

        return view('dashboard.attendance.shifts', ['shifts' => $shifts, 'palette' => Shift::PALETTE]);
    }

    public function storeShift(Request $request)
    {
        $data = $this->validateShift($request);
        $data['sort'] = (int) Shift::max('sort') + 1;
        $shift = Shift::create($data);
        Audit::record('shift.created', $shift, "Shift {$shift->name} ({$shift->times()}) added");

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'shift' => $shift->toCell()])
            : redirect()->route('attendance.shifts')->with('success', "{$shift->name} added.");
    }

    public function updateShift(Request $request, Shift $shift)
    {
        $shift->update($this->validateShift($request, $shift));
        Audit::record('shift.updated', $shift, "Shift {$shift->name} updated");

        return redirect()->route('attendance.shifts')->with('success', "{$shift->name} saved.");
    }

    public function destroyShift(Shift $shift)
    {
        if ($shift->assignments()->exists()) {
            // Keep history intact: retire it instead.
            $shift->update(['active' => false]);
            ShiftAssignment::where('shift_id', $shift->id)->whereDate('date', '>=', today())->delete();

            return redirect()->route('attendance.shifts')->with('success', "{$shift->name} retired. Past rotas keep it; it's been taken off future days.");
        }
        $name = $shift->name;
        $shift->delete();
        Audit::record('shift.deleted', null, "Shift {$name} deleted");

        return redirect()->route('attendance.shifts')->with('success', "{$name} deleted.");
    }

    /* ------------------------------------------------------------------ */

    private function apply(Request $request, $cells): int
    {
        $user = $request->user();
        $employees = Employee::whereIn('id', $cells->pluck('employee_id')->unique())->get()->keyBy('id');
        $shifts = Shift::pluck('id')->flip();

        foreach ($cells as $c) {
            $e = $employees->get((int) $c['employee_id']);
            if (! $e || ! TeamScope::canSee($user, $e)) {
                throw ValidationException::withMessages(['cells' => 'You can only schedule people you look after.']);
            }
            $v = $c['value'] ?? null;
            if ($v !== null && $v !== '' && $v !== 'off' && ! $shifts->has((int) $v)) {
                throw ValidationException::withMessages(['cells' => 'That shift no longer exists. Refresh the page.']);
            }
        }

        DB::transaction(function () use ($cells, $user) {
            foreach ($cells as $c) {
                $v = $c['value'] ?? null;
                $where = ['employee_id' => (int) $c['employee_id'], 'date' => $c['date']];
                if ($v === null || $v === '') {
                    ShiftAssignment::where($where)->delete();
                } else {
                    $a = ShiftAssignment::firstOrNew($where);
                    $a->fill(['shift_id' => $v === 'off' ? null : (int) $v, 'note' => $c['note'] ?? $a->note, 'assigned_by' => $user->id])->save();
                }
            }
        });

        return $cells->count();
    }

    private function validateShift(Request $request, ?Shift $shift = null): array
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:60', Rule::unique('shifts', 'name')->where('company_id', \App\Support\Tenancy::companyId())->ignore($shift?->id)],
            'code'          => 'nullable|string|max:4',
            'start_time'    => 'required|date_format:H:i',
            'end_time'      => 'required|date_format:H:i|different:start_time',
            'break_minutes' => 'nullable|integer|min:0|max:240',
            'color'         => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'active'        => 'nullable|boolean',
        ], ['end_time.different' => 'A shift has to end at a different time than it starts.', 'name.unique' => 'There is already a shift with that name.']);

        $data['code'] = mb_strtoupper($data['code'] ?? '' ?: mb_substr($data['name'], 0, 1));
        $data['break_minutes'] = (int) ($data['break_minutes'] ?? 0);
        $data['color'] = $data['color'] ?? Shift::PALETTE[Shift::count() % count(Shift::PALETTE)];
        $data['active'] = $request->has('active') ? $request->boolean('active') : ($shift?->active ?? true);

        return $data;
    }

    private function weekStart(?string $v): Carbon
    {
        try {
            $d = $v ? Carbon::createFromFormat('Y-m-d', $v) : today();
        } catch (\Throwable $e) {
            $d = today();
        }

        return $d->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
    }
}
