@extends('layouts.app')

@section('content')

<div class="atd p-5">

    {{-- ===== HEADER ===== --}}
    <div class="atd-header">
        <div>
            <a href="{{ route('attendance.calendar', ['month' => $carbon->format('Y-m')]) }}" class="atd-back">
                <i class="bi bi-arrow-left"></i> Calendar
            </a>
            <div class="atd-eyebrow">Attendance</div>
            <h1 class="atd-title">{{ $carbon->format('l, d F Y') }}</h1>

            @if($isHoliday)
                <div class="atd-banner atd-banner-holiday"><i class="bi bi-star-fill"></i> Public Holiday — {{ $holidayName }}</div>
            @elseif(!$isWorkingDay)
                <div class="atd-banner atd-banner-nonworking"><i class="bi bi-moon-stars"></i> Non-Working Day</div>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="atd-alert atd-alert-success"><i class="bi bi-check-circle"></i> {{ session('success') }}</div>
    @endif

    @if(!empty($importSkipped))
        <div class="atd-alert atd-alert-warn">
            <i class="bi bi-exclamation-triangle"></i> {{ count($importSkipped) }} row(s) skipped during import:
            <ul>
                @foreach($importSkipped as $reason)
                    <li>{{ $reason }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ===== SUMMARY STRIP ===== --}}
    <div class="atd-summary">
        @foreach(\App\Models\Attendance::STATUSES as $key => $label)
            <div class="atd-summary-item">
                <span class="atd-dot atd-dot-{{ $key }}"></span>
                <span class="atd-summary-label">{{ $label }}</span>
                <span class="atd-summary-value">{{ $counts[$key] ?? 0 }}</span>
            </div>
        @endforeach
    </div>

    {{-- ===== CSV IMPORT ===== --}}
    <div class="atd-card atd-import-card">
        <div class="atd-section-label"><span class="atd-section-no">↑</span>Import Attendance Sheet</div>
        <form method="POST" action="{{ route('attendance.import') }}" enctype="multipart/form-data" class="atd-import-form">
            @csrf
            <input type="file" name="file" class="atd-file-input" required accept=".csv,.txt">
            <button class="atd-btn atd-btn-outline">
                <i class="bi bi-upload"></i> Import CSV
            </button>
            <a href="{{ route('attendance.import.sample') }}" class="atd-hint-link">
                <i class="bi bi-download"></i> Download template
            </a>
        </form>
        <div class="atd-hint">Columns: employee_id, date, time_in, time_out, status. Rows for any date can be included in one file.</div>
    </div>

    {{-- ===== BULK TOOLBAR ===== --}}
    <div class="atd-card atd-toolbar">
        <div class="atd-toolbar-group">
            <label class="atd-toolbar-label">Department</label>
            <select id="atdDeptFilter" class="atd-select" onchange="atdFilterDept()">
                <option value="">All Departments</option>
                @foreach($departments as $dept)
                    <option value="{{ $dept }}">{{ $dept }}</option>
                @endforeach
            </select>
        </div>

        <div class="atd-toolbar-group">
            <label class="atd-toolbar-label">Bulk mark visible rows as</label>
            <select id="atdBulkStatus" class="atd-select">
                @foreach(\App\Models\Attendance::STATUSES as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            <button type="button" class="atd-btn atd-btn-outline" onclick="atdApplyBulk()">
                <i class="bi bi-check2-all"></i> Apply
            </button>
        </div>
    </div>

    {{-- ===== ROSTER TABLE ===== --}}
    <form method="POST" action="{{ route('attendance.store', $date) }}" id="atdForm">
        @csrf

        <div class="atd-card atd-table-card">
            <table class="atd-table">
                <thead>
                    <tr>
                        <th style="width:22%">Employee</th>
                        <th style="width:12%">Department</th>
                        <th style="width:32%">Status</th>
                        <th style="width:10%">Time In</th>
                        <th style="width:10%">Time Out</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($employees as $employee)
                        @php
                            $existing = $attendances->get($employee->id);
                            $defaultStatus = $existing->status ?? ($isWorkingDay ? 'present' : 'non_working');
                        @endphp
                        <tr class="atd-row" data-dept="{{ $employee->department }}">
                            <td>
                                <div class="atd-emp-cell">
                                    <div class="atd-avatar">
                                        <img src="{{ $employee->passport_photo
                                            ? asset('storage/' . $employee->passport_photo)
                                            : asset('assets/images/avatar/avatar.jpg') }}">
                                    </div>
                                    <div>
                                        <div class="atd-emp-name">{{ $employee->first_name }} {{ $employee->last_name }}</div>
                                        <div class="atd-emp-id"><i class="bi bi-hash"></i>{{ $employee->employee_id }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="atd-td-muted">{{ $employee->department ?? '—' }}</td>
                            <td>
                                <div class="atd-pill-group">
                                    @foreach(\App\Models\Attendance::STATUSES as $key => $label)
                                        <input type="radio"
                                               id="status-{{ $employee->id }}-{{ $key }}"
                                               name="attendance[{{ $employee->id }}][status]"
                                               value="{{ $key }}"
                                               class="atd-pill-radio"
                                               {{ $defaultStatus === $key ? 'checked' : '' }}>
                                        <label for="status-{{ $employee->id }}-{{ $key }}"
                                               class="atd-pill atd-pill-{{ $key }}"
                                               title="{{ $label }}">{{ Str::limit($label, 4, '') }}</label>
                                    @endforeach
                                </div>
                            </td>
                            <td>
                                <input type="time" class="atd-input"
                                       name="attendance[{{ $employee->id }}][time_in]"
                                       value="{{ $existing?->time_in ? \Carbon\Carbon::parse($existing->time_in)->format('H:i') : '' }}">
                            </td>
                            <td>
                                <input type="time" class="atd-input"
                                       name="attendance[{{ $employee->id }}][time_out]"
                                       value="{{ $existing?->time_out ? \Carbon\Carbon::parse($existing->time_out)->format('H:i') : '' }}">
                            </td>
                            <td>
                                <input type="text" class="atd-input" maxlength="255"
                                       name="attendance[{{ $employee->id }}][note]"
                                       value="{{ $existing->note ?? '' }}" placeholder="—">
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="atd-save-bar">
            <button class="atd-btn atd-btn-primary">
                <i class="bi bi-save"></i> Save Attendance
            </button>
        </div>
    </form>

</div>


{{-- ===== STYLES ===== --}}
<style>
@import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap');

.atd * { box-sizing: border-box; }

.atd {
    --paper: #ffffff;
    --ink: #00742E;
    --ink-soft: #58687D;
    --ink-faint: #93A0B2;
    --brass: #00742E;
    --brass-soft: #EAF7ED;
    --line: #D9E9DE;
    --accent-orange: #E97B00;
    --accent-red: #D50115;

    --present: #00742E; --present-bg: #EAF7ED;
    --absent: #D50115; --absent-bg: #FDE7EA;
    --sick: #E97B00; --sick-bg: #FFF1E3;
    --leave: #00742E; --leave-bg: #EAF7ED;
    --holiday: #E97B00; --holiday-bg: #FFF1E3;
    --other: #58687D; --other-bg: #EEF2F5;
    --nonworking: #58687D; --nonworking-bg: #F4F6F8;

    font-family: 'IBM Plex Sans', -apple-system, BlinkMacSystemFont, sans-serif;
    color: var(--ink);
    background: var(--paper);
    padding: 1.5rem 0 2.5rem;
}

/* ---- Header ---- */
.atd-back { display: inline-flex; align-items: center; gap: .35rem; font-size: .78rem; font-weight: 600; color: var(--ink-soft); text-decoration: none; margin-bottom: .6rem; }
.atd-back:hover { color: var(--brass); }
.atd-eyebrow { font-family: 'IBM Plex Sans', sans-serif; font-size: .68rem; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; color: var(--brass); margin-bottom: .2rem; }
.atd-title { font-family: 'IBM Plex Sans', sans-serif; font-size: 1.4rem; font-weight: 700; margin: 0; color: var(--ink); }

.atd-banner { display: inline-flex; align-items: center; gap: .4rem; font-size: .78rem; font-weight: 600; padding: .4rem .8rem; border-radius: 20px; margin-top: .6rem; }
.atd-banner-holiday { background: var(--holiday-bg); color: var(--holiday); }
.atd-banner-nonworking { background: var(--nonworking-bg); color: var(--nonworking); }

.atd-alert { border-radius: 6px; font-size: .82rem; padding: .7rem 1rem; margin: 1rem 0; }
.atd-alert-success { background: var(--present-bg); color: var(--present); border: 1px solid #C7E0CD; }
.atd-alert-warn { background: var(--sick-bg); color: var(--sick); border: 1px solid #EAD2B3; }
.atd-alert-warn ul { margin: .4rem 0 0; padding-left: 1.1rem; }

/* ---- Summary strip ---- */
.atd-summary {
    display: flex; flex-wrap: wrap; gap: 1rem;
    background: #fff; border: 1px solid var(--line); border-radius: 6px;
    padding: .8rem 1.2rem; margin: 1.1rem 0;
}
.atd-summary-item { display: flex; align-items: center; gap: .4rem; font-size: .78rem; color: var(--ink-soft); }
.atd-summary-value { font-family: 'IBM Plex Mono', monospace; font-weight: 700; color: var(--ink); }
.atd-dot { width: 8px; height: 8px; border-radius: 2px; display: inline-block; }
.atd-dot-present { background: var(--present); }
.atd-dot-absent { background: var(--absent); }
.atd-dot-sick { background: var(--sick); }
.atd-dot-leave { background: var(--leave); }
.atd-dot-holiday { background: var(--holiday); }
.atd-dot-other { background: var(--other); }
.atd-dot-non_working { background: var(--nonworking); }

/* ---- Cards ---- */
.atd-card { background: #fff; border: 1px solid var(--line); border-radius: 6px; padding: 1.1rem 1.3rem; margin-bottom: 1rem; }
.atd-section-label {
    display: flex; align-items: baseline; gap: .5rem;
    font-family: 'IBM Plex Sans', serif; font-size: .76rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .04em; color: var(--ink); margin-bottom: .8rem;
}
.atd-section-no { font-family: 'IBM Plex Mono', monospace; font-size: .68rem; font-weight: 600; color: #fff; background: var(--brass); padding: .05rem .4rem; border-radius: 3px; }

.atd-import-form { display: flex; align-items: center; gap: .7rem; flex-wrap: wrap; }
.atd-file-input { font-size: .8rem; }
.atd-hint-link { font-size: .78rem; color: var(--brass); text-decoration: none; display: inline-flex; align-items: center; gap: .3rem; }
.atd-hint { font-size: .72rem; color: var(--ink-faint); margin-top: .6rem; }

/* ---- Toolbar ---- */
.atd-toolbar { display: flex; gap: 1.5rem; flex-wrap: wrap; align-items: center; }
.atd-toolbar-group { display: flex; align-items: center; gap: .5rem; }
.atd-toolbar-label { font-size: .74rem; font-weight: 600; color: var(--ink-faint); }
.atd-select {
    font-size: .8rem; padding: .45rem .65rem; border: 1px solid var(--line); border-radius: 5px;
    background: #fff; color: var(--ink-soft); outline: none; cursor: pointer;
}
.atd-select:focus { border-color: var(--brass); }

/* ---- Buttons ---- */
.atd-btn {
    display: inline-flex; align-items: center; gap: .4rem;
    font-size: .8rem; font-weight: 600; padding: .5rem .95rem;
    border-radius: 6px; border: 1px solid transparent; cursor: pointer; text-decoration: none;
    transition: background .18s, border-color .18s, color .18s;
}
.atd-btn-primary { background: var(--ink); color: #fff; border-color: var(--ink); }
.atd-btn-primary:hover { background: #23374F; }
.atd-btn-outline { background: #fff; color: var(--ink-soft); border-color: var(--line); }
.atd-btn-outline:hover { border-color: var(--brass); color: var(--brass); }

/* ---- Table ---- */
.atd-table-card { padding: 0; overflow-x: auto; }
.atd-table { width: 100%; border-collapse: collapse; font-size: .82rem; min-width: 760px; }
.atd-table thead th {
    font-family: 'IBM Plex Sans', serif; font-size: .64rem; font-weight: 600; color: var(--ink-faint);
    text-transform: uppercase; letter-spacing: .06em; padding: .8rem 1rem;
    border-bottom: 2px solid var(--ink); text-align: left; background: #FCFBF8;
}
.atd-row { border-bottom: 1px solid var(--line); }
.atd-row td { padding: .6rem 1rem; vertical-align: middle; }
.atd-td-muted { color: var(--ink-faint); font-size: .78rem; }

.atd-emp-cell { display: flex; align-items: center; gap: .6rem; }
.atd-avatar { width: 32px; height: 32px; border-radius: 6px; border: 1px solid var(--ink); overflow: hidden; flex-shrink: 0; background: var(--brass-soft); }
.atd-avatar img { width: 100%; height: 100%; object-fit: cover; }
.atd-emp-name { font-weight: 600; font-size: .82rem; color: var(--ink); }
.atd-emp-id { font-family: 'IBM Plex Mono', monospace; font-size: .68rem; color: var(--ink-faint); }

.atd-input {
    width: 100%; font-size: .78rem; padding: .4rem .55rem;
    border: 1px solid var(--line); border-radius: 5px; outline: none; color: var(--ink);
}
.atd-input:focus { border-color: var(--brass); }

/* ---- Status pill group ---- */
.atd-pill-group { display: flex; flex-wrap: wrap; gap: .3rem; }
.atd-pill-radio { position: absolute; opacity: 0; width: 0; height: 0; }
.atd-pill {
    display: inline-flex; align-items: center; justify-content: center;
    font-size: .66rem; font-weight: 700; text-transform: uppercase; letter-spacing: .02em;
    padding: .28rem .5rem; border-radius: 4px; border: 1px solid var(--line);
    color: var(--ink-faint); background: #fff; cursor: pointer; user-select: none;
    transition: background .12s, color .12s, border-color .12s;
}
.atd-pill-radio:checked + .atd-pill-present { background: var(--present-bg); color: var(--present); border-color: #C7E0CD; }
.atd-pill-radio:checked + .atd-pill-absent { background: var(--absent-bg); color: var(--absent); border-color: #E3C6BC; }
.atd-pill-radio:checked + .atd-pill-sick { background: var(--sick-bg); color: var(--sick); border-color: #EAD2B3; }
.atd-pill-radio:checked + .atd-pill-leave { background: var(--leave-bg); color: var(--leave); border-color: #C7D0E0; }
.atd-pill-radio:checked + .atd-pill-holiday { background: var(--holiday-bg); color: var(--holiday); border-color: #DCCB9C; }
.atd-pill-radio:checked + .atd-pill-other { background: var(--other-bg); color: var(--other); border-color: #DEDACD; }
.atd-pill-radio:checked + .atd-pill-non_working { background: var(--nonworking-bg); color: var(--nonworking); border-color: #DAD5C6; }
.atd-pill-radio:focus-visible + .atd-pill { outline: 2px solid var(--brass); outline-offset: 1px; }

/* ---- Save bar ---- */
.atd-save-bar { display: flex; justify-content: flex-end; position: sticky; bottom: 1rem; }

@media (max-width: 700px) {
    .atd-toolbar { flex-direction: column; align-items: flex-start; }
}
</style>

<script>
function atdFilterDept() {
    const dept = document.getElementById('atdDeptFilter').value;
    document.querySelectorAll('.atd-row').forEach(row => {
        row.style.display = (!dept || row.dataset.dept === dept) ? '' : 'none';
    });
}

function atdApplyBulk() {
    const status = document.getElementById('atdBulkStatus').value;
    document.querySelectorAll('.atd-row').forEach(row => {
        if (row.style.display === 'none') return;
        const radio = row.querySelector(`input[type="radio"][value="${status}"]`);
        if (radio) radio.checked = true;
    });
}
</script>

@endsection
