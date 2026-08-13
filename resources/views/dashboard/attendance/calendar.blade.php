@extends('layouts.app')

@section('content')

<div class="cal p-5">

    {{-- ===== HEADER ===== --}}
    <div class="cal-header">
        <div>
            <div class="cal-eyebrow">Attendance</div>
            <h1 class="cal-title">Attendance Calendar</h1>
            <div class="cal-subtitle">Click any date to mark or review attendance</div>
        </div>

        <div class="cal-header-actions">
            <a href="{{ route('attendance.settings') }}" class="cal-btn cal-btn-outline">
                <i class="bi bi-gear"></i>Set Working Days &amp; Holidays
            </a>
            <a href="{{ route('attendance.import.sample') }}" class="cal-btn cal-btn-outline">
                <i class="bi bi-download"></i> CSV Template
            </a>
            <a href="{{ route('attendance.report') }}" class="cal-btn cal-btn-outline">
                <i class="bi bi-file-earmark-text"></i> See Attendance Reports
            </a>
            <a href="{{ route('attendance.report.export') }}" class="cal-btn cal-btn-outline">
                <i class="bi bi-download"></i> Export Attendance Report
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="cal-alert cal-alert-success">
            <i class="bi bi-check-circle"></i> {{ session('success') }}
        </div>
    @endif

    {{-- ===== MONTH NAV ===== --}}
    <div class="cal-nav">
        <a href="{{ route('attendance.calendar', ['month' => $prevMonth]) }}" class="cal-nav-btn">
            <i class="bi bi-chevron-left"></i>
        </a>
        <div class="cal-nav-month">{{ $start->format('F Y') }}</div>
        <a href="{{ route('attendance.calendar', ['month' => $nextMonth]) }}" class="cal-nav-btn">
            <i class="bi bi-chevron-right"></i>
        </a>
        <a href="{{ route('attendance.calendar') }}" class="cal-nav-today">Today</a>

        <div class="cal-nav-total">
            <i class="bi bi-people"></i> {{ $totalEmployees }} active employees
        </div>
    </div>

    {{-- ===== LEGEND ===== --}}
    <div class="cal-legend">
        @foreach(\App\Models\Attendance::STATUSES as $key => $label)
            <span class="cal-legend-item"><span class="cal-dot cal-dot-{{ $key }}"></span>{{ $label }}</span>
        @endforeach
    </div>

    {{-- ===== WEEKDAY HEADER ===== --}}
    <div class="cal-grid cal-weekdays">
        <div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div><div>Sun</div>
    </div>

    {{-- ===== DAY GRID ===== --}}
    <div class="cal-grid">

        @for($i = 0; $i < $leadingBlanks; $i++)
            <div class="cal-cell cal-cell-blank"></div>
        @endfor

        @foreach($days as $day)
            <a href="{{ route('attendance.day', $day['date']) }}"
               class="cal-cell
                      @if($day['carbon']->isToday()) cal-cell-today @endif
                      @if(!$day['is_working']) cal-cell-nonworking @endif
                      @if($day['is_holiday']) cal-cell-holiday @endif">

                <div class="cal-cell-top">
                    <span class="cal-cell-num">{{ $day['day'] }}</span>
                    @if($day['is_holiday'])
                        <span class="cal-cell-flag" title="{{ $day['holiday_name'] }}"><i class="bi bi-star-fill"></i></span>
                    @elseif(!$day['is_working'])
                        <span class="cal-cell-flag cal-cell-flag-muted"><i class="bi bi-moon-stars"></i></span>
                    @endif
                </div>

                @if($day['is_holiday'])
                    <div class="cal-cell-holiday-name">{{ Str::limit($day['holiday_name'], 16) }}</div>
                @endif

                @if($day['marked'] > 0)
                    <div class="cal-cell-count">{{ $day['marked'] }}/{{ $day['total'] }} marked</div>
                    <div class="cal-cell-bar">
                        @foreach($day['counts'] as $status => $c)
                            @if($c > 0)
                                <span class="cal-bar-seg cal-dot-{{ $status }}" style="flex-grow:{{ $c }}"></span>
                            @endif
                        @endforeach
                    </div>
                @elseif($day['is_working'])
                    <div class="cal-cell-count cal-cell-unmarked">Not marked</div>
                @endif

            </a>
        @endforeach

    </div>

</div>


{{-- ===== STYLES ===== --}}
<style>
@import url('https://fonts.googleapis.com/css2?family=Roboto+Slab:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap');

.cal * { box-sizing: border-box;}

.cal {
    --paper: #ffffff;
    --ink: #00742E;
    --ink-soft: #58687D;
    --ink-faint: #93A0B2;
    --brass: #00742E;
    --brass-soft: #029508;
    --line: #f0f0f0;

    --present: #2F6F4E;
    --absent: #9B3A2A;
    --sick: #B5651D;
    --leave: #0062ff;
    --holiday: #00c753;
    --other: #8A8578;
    --nonworking: #C7C2B4;

    font-family: 'IBM Plex Sans', -apple-system, BlinkMacSystemFont, sans-serif;
    color: var(--ink);
    background: var(--paper);
    padding: 1.5rem 0 2.5rem;
}

/* ---- Header ---- */
.cal-header { display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: .75rem; margin-bottom: 1rem; }
.cal-eyebrow { font-family: 'IBM Plex Sans', serif; font-size: .68rem; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; color: var(--brass); margin-bottom: .2rem; }
.cal-title { font-family: 'IBM Plex Sans', serif; font-size: 1.4rem; font-weight: 700; margin: 0; color: var(--ink); }
.cal-subtitle { font-size: .82rem; color: var(--ink-faint); margin-top: .2rem; }
.cal-header-actions { display: flex; gap: .5rem; flex-wrap: wrap; }

.cal-btn {
    display: inline-flex; align-items: center; gap: .4rem;
    font-size: .8rem; font-weight: 600; padding: .55rem 1rem;
    border-radius: 6px; text-decoration: none; border: 1px solid var(--line);
    background: #fff; color: var(--ink-soft); cursor: pointer;
    transition: border-color .15s, color .15s;
}
.cal-btn-outline:hover { border-color: var(--brass); color: var(--brass); }

.cal-alert { border-radius: 6px; font-size: .84rem; padding: .7rem 1rem; margin-bottom: 1rem; background: #EAF3EC; color: #2F6F4E; border: 1px solid #C7E0CD; }

/* ---- Month nav ---- */
.cal-nav { display: flex; align-items: center; gap: .7rem; margin-bottom: .9rem; flex-wrap: wrap; }
.cal-nav-btn {
    width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;
    border: 1px solid var(--line); border-radius: 6px; color: var(--ink-soft); text-decoration: none;
    transition: border-color .15s, color .15s;
}
.cal-nav-btn:hover { border-color: var(--brass); color: var(--brass); }
.cal-nav-month { font-family: 'IBM Plex Sans', serif; font-weight: 700; font-size: 1.05rem; color: var(--ink); min-width: 150px; }
.cal-nav-today { font-size: .78rem; font-weight: 600; color: var(--brass); text-decoration: none; border-bottom: 1px dashed var(--brass); }
.cal-nav-total { margin-left: auto; font-size: .78rem; color: var(--ink-faint); display: flex; align-items: center; gap: .35rem; }

/* ---- Legend ---- */
.cal-legend { display: flex; flex-wrap: wrap; gap: .9rem; margin-bottom: 1rem; padding: .7rem 1rem; background: #fff; border: 1px solid var(--line); border-radius: 6px; }
.cal-legend-item { display: flex; align-items: center; gap: .4rem; font-size: .74rem; color: var(--ink-soft); }
.cal-dot { width: 8px; height: 8px; border-radius: 2px; display: inline-block; }
.cal-dot-present { background: var(--present); }
.cal-dot-absent { background: var(--absent); }
.cal-dot-sick { background: var(--sick); }
.cal-dot-leave { background: var(--leave); }
.cal-dot-holiday { background: var(--holiday); }
.cal-dot-other { background: var(--other); }
.cal-dot-non_working { background: var(--nonworking); }

/* ---- Grid ---- */
.cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: .5rem; }
.cal-weekdays {
    margin-bottom: .4rem;
    font-family: 'IBM Plex Sans', serif;
    font-size: .68rem; font-weight: 600; text-transform: uppercase; letter-spacing: .06em;
    color: var(--ink-faint); text-align: center;
}

.cal-cell {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 6px;
    min-height: 92px;
    padding: .5rem .55rem;
    text-decoration: none;
    color: var(--ink);
    display: flex;
    flex-direction: column;
    transition: border-color .15s, box-shadow .15s, transform .15s;
}
.cal-cell:hover { border-color: var(--brass); box-shadow: 0 10px 20px -14px rgba(23,38,58,.3); transform: translateY(-1px); }
.cal-cell-blank { background: transparent; border: none; }
.cal-cell-today { border-color: var(--ink); border-width: 2px; }
.cal-cell-nonworking { background: repeating-linear-gradient(135deg, #fff, #fff 8px, #ecf5ea 8px, #ecf5ea 16px); }
.cal-cell-holiday { border-color: var(--brass-soft); background: #FCFAF3; }

.cal-cell-top { display: flex; justify-content: space-between; align-items: flex-start; }
.cal-cell-num { font-family: 'IBM Plex Mono', monospace; font-weight: 600; font-size: .86rem; }
.cal-cell-flag { color: var(--brass); font-size: .7rem; }
.cal-cell-flag-muted { color: var(--ink-faint); }

.cal-cell-holiday-name { font-size: .64rem; color: var(--brass); margin-top: .2rem; font-weight: 600; }

.cal-cell-count { font-size: .64rem; color: var(--ink-faint); margin-top: auto; padding-top: .3rem; }
.cal-cell-unmarked { color: #8c8c8c; font-style: italic; }

.cal-cell-bar { display: flex; height: 4px; border-radius: 3px; overflow: hidden; margin-top: .25rem; background: #F1EEE4; }
.cal-bar-seg { display: block; }

/* ---- Responsive ---- */
@media (max-width: 760px) {
    .cal-grid { grid-template-columns: repeat(7, minmax(38px, 1fr)); gap: .3rem; }
    .cal-cell { min-height: 62px; padding: .35rem; font-size: .74rem; }
    .cal-cell-holiday-name, .cal-cell-count { display: none; }
}
</style>

@endsection
