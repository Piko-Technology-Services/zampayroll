@extends('layouts.app')

@section('content')

<div class="ats p-5">

    {{-- ===== HEADER ===== --}}
    <div class="ats-header">
        <div>
            <a href="{{ route('attendance.calendar') }}" class="ats-back"><i class="bi bi-arrow-left"></i> Calendar</a>
            <div class="ats-eyebrow">Attendance</div>
            <h1 class="ats-title">Working Days &amp; Holidays</h1>
            <div class="ats-subtitle">Controls which dates count as working days across the attendance module</div>
        </div>
    </div>

    @if(session('success'))
        <div class="ats-alert"><i class="bi bi-check-circle"></i> {{ session('success') }}</div>
    @endif

    {{-- ===== WORKING DAYS ===== --}}
    <div class="ats-card">
        <div class="ats-section-label"><span class="ats-section-no">01</span>Working Days</div>

        <form method="POST" action="{{ route('attendance.settings.workdays') }}">
            @csrf
            @method('PUT')

            <div class="ats-days-row">
                @php
                    $weekdays = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
                @endphp
                @foreach($weekdays as $iso => $label)
                    <label class="ats-day-toggle">
                        <input type="checkbox" name="work_days[]" value="{{ $iso }}"
                               {{ in_array($iso, $workDays) ? 'checked' : '' }}>
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>

            <div class="ats-actions">
                <button class="ats-btn ats-btn-primary"><i class="bi bi-save"></i> Save Working Days</button>
            </div>
        </form>
    </div>

    {{-- ===== HOLIDAYS ===== --}}
    <div class="ats-card">
        <div class="ats-section-label"><span class="ats-section-no">02</span>Public Holidays</div>

        <form method="POST" action="{{ route('attendance.settings.holidays.store') }}" class="ats-holiday-form">
            @csrf
            <input type="text" name="name" class="ats-input" placeholder="Holiday name" required>
            <input type="date" name="date" class="ats-input" required>
            <label class="ats-recurring-toggle">
                <input type="checkbox" name="is_recurring" value="1"> Repeats yearly
            </label>
            <button class="ats-btn ats-btn-outline"><i class="bi bi-plus-lg"></i> Add Holiday</button>
        </form>

        <table class="ats-table">
            <thead>
                <tr><th>Date</th><th>Name</th><th>Recurring</th><th></th></tr>
            </thead>
            <tbody>
                @forelse($holidays as $holiday)
                    <tr>
                        <td class="ats-mono">{{ $holiday->date->format('d M Y') }}</td>
                        <td>{{ $holiday->name }}</td>
                        <td>{{ $holiday->is_recurring ? 'Yes' : 'No' }}</td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('attendance.settings.holidays.destroy', $holiday) }}"
                                  onsubmit="return confirm('Remove this holiday?');">
                                @csrf
                                @method('DELETE')
                                <button class="ats-btn ats-btn-warn ats-btn-xs">Remove</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="ats-empty">No holidays added yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>


{{-- ===== STYLES ===== --}}
<style>
@import url('https://fonts.googleapis.com/css2?family=Roboto+Slab:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap');

.ats * { box-sizing: border-box; }

.ats {
    --paper: #ffffff;
    --ink: #00742E;
    --ink-soft: #58687D;
    --ink-faint: #93A0B2;
    --brass: #00742E;
    --brass-soft: #029508;
    --line: #E97B00;
    --active: #D50115;
    --active-bg: #EAF3EC;
    --warn: #D50115;
    --warn-bg: #F7E7E2;

    font-family: 'IBM Plex Sans', -apple-system, BlinkMacSystemFont, sans-serif;
    color: var(--ink);
    background: var(--paper);
    padding: 1.5rem 0 2.5rem;
    max-width: 780px;
    margin: 0 auto;
}

.ats-back { display: inline-flex; align-items: center; gap: .35rem; font-size: .78rem; font-weight: 600; color: var(--ink-soft); text-decoration: none; margin-bottom: .6rem; }
.ats-back:hover { color: var(--brass); }
.ats-eyebrow { font-family: 'IBM Plex Sans', serif; font-size: .68rem; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; color: var(--brass); margin-bottom: .2rem; }
.ats-title { font-family: 'IBM Plex Sans', serif; font-size: 1.3rem; font-weight: 700; margin: 0; color: var(--ink); }
.ats-subtitle { font-size: .8rem; color: var(--ink-faint); margin-top: .2rem; }

.ats-alert { border-radius: 6px; font-size: .84rem; padding: .7rem 1rem; margin: 1rem 0; background: var(--active-bg); color: var(--active); border: 1px solid #C7E0CD; }

.ats-card { background: #fff; border: 1px solid var(--line); border-radius: 6px; padding: 1.4rem; margin: 1rem 0; position: relative; }
.ats-card::before { content: ""; position: absolute; top: 0; left: 26px; width: 56px; height: 5px; background: var(--brass); border-radius: 0 0 3px 3px; }

.ats-section-label {
    display: flex; align-items: baseline; gap: .55rem;
    font-family: 'IBM Plex Sans', serif; font-size: .8rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .04em; color: var(--ink);
    border-bottom: 1px solid var(--line); padding-bottom: .7rem; margin: .3rem 0 1.1rem;
}
.ats-section-no { font-family: 'IBM Plex Mono', monospace; font-size: .68rem; font-weight: 600; color: #fff; background: var(--brass); padding: .05rem .4rem; border-radius: 3px; }

/* ---- Working days toggles ---- */
.ats-days-row { display: flex; gap: .5rem; flex-wrap: wrap; margin-bottom: 1.1rem; }
.ats-day-toggle input { position: absolute; opacity: 0; width: 0; height: 0; }
.ats-day-toggle span {
    display: inline-flex; align-items: center; justify-content: center;
    width: 56px; padding: .55rem 0; border: 1px solid var(--line); border-radius: 6px;
    font-size: .78rem; font-weight: 600; color: var(--ink-faint); cursor: pointer;
    transition: background .12s, color .12s, border-color .12s;
}
.ats-day-toggle input:checked + span { background: var(--brass-soft); color: #ffffff; border-color: var(--brass); }

.ats-actions { display: flex; justify-content: flex-end; }

/* ---- Buttons ---- */
.ats-btn { display: inline-flex; align-items: center; gap: .4rem; font-size: .8rem; font-weight: 600; padding: .5rem .95rem; border-radius: 6px; border: 1px solid transparent; cursor: pointer; }
.ats-btn-xs { padding: .3rem .65rem; font-size: .72rem; }
.ats-btn-primary { background: var(--ink); color: #fff; border-color: var(--ink); }
.ats-btn-primary:hover { background: #23374F; }
.ats-btn-outline { background: #fff; color: var(--ink-soft); border-color: var(--line); }
.ats-btn-outline:hover { border-color: var(--brass); color: var(--brass); }
.ats-btn-warn { background: #fff; color: var(--warn); border-color: #E3C6BC; }
.ats-btn-warn:hover { background: var(--warn-bg); }

/* ---- Holiday form ---- */
.ats-holiday-form { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; margin-bottom: 1.1rem; }
.ats-input { font-size: .82rem; padding: .5rem .7rem; border: 1px solid var(--line); border-radius: 5px; outline: none; }
.ats-input:focus { border-color: var(--brass); }
.ats-recurring-toggle { font-size: .78rem; color: var(--ink-soft); display: flex; align-items: center; gap: .35rem; }

/* ---- Table ---- */
.ats-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
.ats-table thead th {
    font-family: 'IBM Plex Sans', serif; font-size: .64rem; font-weight: 600; color: var(--ink-faint);
    text-transform: uppercase; letter-spacing: .06em; padding: .6rem .3rem; border-bottom: 2px solid var(--ink); text-align: left;
}
.ats-table td { padding: .6rem .3rem; border-bottom: 1px solid var(--line); }
.ats-mono { font-family: 'IBM Plex Mono', monospace; }
.ats-empty { text-align: center; color: var(--ink-faint); padding: 1.2rem 0 !important; }
</style>

@endsection
