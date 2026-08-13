@extends('layouts.app')

@section('content')

<div class="arp p-5">

    <div class="arp-header">
        <div>
            <a href="{{ route('attendance.calendar') }}" class="arp-back"><i class="bi bi-arrow-left"></i> Calendar</a>
            <div class="arp-eyebrow">Attendance</div>
            <h1 class="arp-title">Monthly Summary</h1>
        </div>
        <a href="{{ route('attendance.report.export', request()->query()) }}" class="arp-btn arp-btn-outline">
            <i class="bi bi-download"></i> Export CSV
        </a>
    </div>

    <div class="arp-toolbar">
        <div class="arp-nav">
            <a href="{{ route('attendance.report', array_merge(request()->query(), ['month' => $prevMonth])) }}" class="arp-nav-btn"><i class="bi bi-chevron-left"></i></a>
            <div class="arp-nav-month">{{ $start->format('F Y') }}</div>
            <a href="{{ route('attendance.report', array_merge(request()->query(), ['month' => $nextMonth])) }}" class="arp-nav-btn"><i class="bi bi-chevron-right"></i></a>
        </div>

        <form method="GET" action="{{ route('attendance.report') }}" class="arp-filter-form">
            <input type="hidden" name="month" value="{{ $start->format('Y-m') }}">
            <select name="department" class="arp-select" onchange="this.form.submit()">
                <option value="">All Departments</option>
                @foreach($departments as $dept)
                    <option value="{{ $dept }}" {{ request('department') === $dept ? 'selected' : '' }}>{{ $dept }}</option>
                @endforeach
            </select>
        </form>

        <div class="arp-working-days"><i class="bi bi-calendar-check"></i> {{ $workingDaysInMonth }} working days this month</div>
    </div>

    <div class="arp-card">
        <table class="arp-table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Department</th>
                    <th class="num">Present</th>
                    <th class="num">Absent</th>
                    <th class="num">Sick</th>
                    <th class="num">Leave</th>
                    <th class="num">Holiday</th>
                    <th class="num">Other</th>
                    <th class="num">Hours</th>
                    <th class="num">Rate</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $r)
                    <tr>
                        <td>
                            <div class="arp-emp-cell">
                                <div class="arp-avatar">
                                    <img src="{{ $r['photo'] ? asset('storage/' . $r['photo']) : asset('assets/images/avatar/avatar.jpg') }}">
                                </div>
                                <div>
                                    <div class="arp-emp-name">{{ $r['name'] }}</div>
                                    <div class="arp-emp-id"><i class="bi bi-hash"></i>{{ $r['employee_id'] }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="arp-td-muted">{{ $r['department'] }}</td>
                        <td class="num arp-num-present">{{ $r['counts']['present'] }}</td>
                        <td class="num arp-num-absent">{{ $r['counts']['absent'] }}</td>
                        <td class="num arp-num-sick">{{ $r['counts']['sick'] }}</td>
                        <td class="num arp-num-leave">{{ $r['counts']['leave'] }}</td>
                        <td class="num arp-num-holiday">{{ $r['counts']['holiday'] }}</td>
                        <td class="num">{{ $r['counts']['other'] }}</td>
                        <td class="num">{{ $r['hours'] }}</td>
                        <td class="num">
                            <span class="arp-rate {{ $r['rate'] >= 90 ? 'arp-rate-good' : ($r['rate'] >= 70 ? 'arp-rate-mid' : 'arp-rate-low') }}">
                                {{ $r['rate'] }}%
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="arp-empty">No employees found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>


<style>
@import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap');

.arp * { box-sizing: border-box; }
.arp {
    --paper: #ffffff; --ink: #00742E; --ink-soft: #58687D; --ink-faint: #93A0B2;
    --brass: #00742E; --brass-soft: #029508; --line: #e9e9e9;
    --present: #00742E; --absent: #D50115; --sick: #E97B00; --leave: #029508; --holiday: #00742E;
    font-family: 'IBM Plex Sans', -apple-system, BlinkMacSystemFont, sans-serif;
    color: var(--ink); background: var(--paper); padding: 1.5rem 0 2.5rem;
}
.arp-back { display: inline-flex; align-items: center; gap: .35rem; font-size: .78rem; font-weight: 600; color: var(--ink-soft); text-decoration: none; margin-bottom: .6rem; }
.arp-back:hover { color: var(--brass); }
.arp-header { display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: .75rem; margin-bottom: 1rem; }
.arp-eyebrow { font-family: 'IBM Plex Sans', serif; font-size: .68rem; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; color: var(--brass); margin-bottom: .2rem; }
.arp-title { font-family: 'IBM Plex Sans', serif; font-size: 1.4rem; font-weight: 700; margin: 0; color: var(--ink); }

.arp-btn { display: inline-flex; align-items: center; gap: .4rem; font-size: .8rem; font-weight: 600; padding: .55rem 1rem; border-radius: 6px; text-decoration: none; border: 1px solid var(--line); background: #fff; color: var(--ink-soft); }
.arp-btn-outline:hover { border-color: var(--brass); color: var(--brass); }

.arp-toolbar { display: flex; align-items: center; gap: 1.2rem; flex-wrap: wrap; margin-bottom: 1rem; }
.arp-nav { display: flex; align-items: center; gap: .6rem; }
.arp-nav-btn { width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; border: 1px solid var(--line); border-radius: 6px; color: var(--ink-soft); text-decoration: none; }
.arp-nav-btn:hover { border-color: var(--brass); color: var(--brass); }
.arp-nav-month { font-family: 'IBM Plex Sans', serif; font-weight: 700; font-size: .95rem; min-width: 120px; }
.arp-select { font-size: .8rem; padding: .45rem .65rem; border: 1px solid var(--line); border-radius: 5px; background: #fff; color: var(--ink-soft); }
.arp-working-days { margin-left: auto; font-size: .78rem; color: var(--ink-faint); display: flex; align-items: center; gap: .35rem; }

.arp-card { background: #fff; border: 1px solid var(--line); border-radius: 6px; overflow-x: auto; }
.arp-table { width: 100%; border-collapse: collapse; font-size: .82rem; min-width: 820px; }
.arp-table thead th { font-family: 'IBM Plex Sans', serif; font-size: .64rem; font-weight: 600; color: var(--ink-faint); text-transform: uppercase; letter-spacing: .06em; padding: .8rem .8rem; border-bottom: 2px solid var(--ink); text-align: left; background: #ececec; }
.arp-table thead th.num, .arp-table td.num { text-align: right; }
.arp-table td { padding: .6rem .8rem; border-bottom: 1px solid var(--line); font-family: 'IBM Plex Mono', monospace; }
.arp-table td:first-child, .arp-table td:nth-child(2) { font-family: 'IBM Plex Sans', sans-serif; }
.arp-td-muted { color: var(--ink-faint); }
.arp-empty { text-align: center; color: var(--ink-faint); padding: 1.5rem 0 !important; font-family: 'IBM Plex Sans', sans-serif; }

.arp-emp-cell { display: flex; align-items: center; gap: .6rem; }
.arp-avatar { width: 30px; height: 30px; border-radius: 6px; border: 1px solid var(--ink); overflow: hidden; flex-shrink: 0; background: var(--brass-soft); }
.arp-avatar img { width: 100%; height: 100%; object-fit: cover; }
.arp-emp-name { font-weight: 600; font-size: .82rem; }
.arp-emp-id { font-family: 'IBM Plex Mono', monospace; font-size: .66rem; color: var(--ink-faint); }

.arp-num-present { color: var(--present); font-weight: 600; }
.arp-num-absent { color: var(--absent); font-weight: 600; }
.arp-num-sick { color: var(--sick); }
.arp-num-leave { color: var(--leave); }
.arp-num-holiday { color: var(--holiday); }

.arp-rate { font-weight: 700; padding: .1rem .5rem; border-radius: 4px; }
.arp-rate-good { background: #EAF3EC; color: var(--present); }
.arp-rate-mid { background: #FBEEE0; color: var(--sick); }
.arp-rate-low { background: #F7E7E2; color: var(--absent); }
</style>

@endsection
