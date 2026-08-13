@extends('layouts.app')

@section('content')

<div class="lyr p-5">

    <div class="lyr-header">
        <div>
            <div class="lyr-eyebrow">Leave Management</div>
            <h1 class="lyr-title">Select a Year</h1>
            <div class="lyr-subtitle">Open a year to view or edit the master leave sheet</div>
        </div>
        <a href="{{ route('leave.dashboard') }}" class="lyr-btn lyr-btn-primary">
            <i class="bi bi-inbox"></i> Leave Requests
        </a>
    </div>

    <div class="lyr-grid">
        @foreach($years as $year)
            <a href="{{ route('leave.sheet', $year) }}" class="lyr-card {{ $year === $currentYear ? 'lyr-card-current' : '' }}">
                <div class="lyr-card-year">{{ $year }}</div>
                @if($year === $currentYear)
                    <div class="lyr-card-tag">Current Year</div>
                @endif
            </a>
        @endforeach
    </div>

</div>

<style>
@import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap');
.lyr * { box-sizing: border-box; }
.lyr {
    --paper: #ffffff;
    --ink: #00742E;
    --ink-soft: #58687D;
    --ink-faint: #93A0B2;
    --brass: #00742E;
    --brass-soft: #EAF7ED;
    --line: #D9E9DE;
    --accent-orange: #E97B00;
    --accent-red: #D50115;
    font-family: 'IBM Plex Sans', sans-serif; color: var(--ink); background: var(--paper); padding: 1.5rem 0 2.5rem;
}
.lyr-header { display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: .75rem; margin-bottom: 1.4rem; }
.lyr-eyebrow { font-family: 'IBM Plex Sans', sans-serif; font-size: .68rem; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; color: var(--brass); margin-bottom: .2rem; }
.lyr-title { font-family: 'IBM Plex Sans', sans-serif; font-size: 1.4rem; font-weight: 700; margin: 0; }
.lyr-subtitle { font-size: .82rem; color: var(--ink-faint); margin-top: .2rem; }
.lyr-btn { display: inline-flex; align-items: center; gap: .4rem; font-size: .8rem; font-weight: 600; padding: .55rem 1rem; border-radius: 6px; text-decoration: none; }
.lyr-btn-primary { background: var(--ink); color: #fff; }
.lyr-btn-primary:hover { background: #005c27; color: #fff; }

.lyr-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; }
@media (max-width: 700px) { .lyr-grid { grid-template-columns: repeat(2, 1fr); } }

.lyr-card {
    background: #fff; border: 1px solid var(--line); border-radius: 8px; padding: 2rem 1rem;
    text-align: center; text-decoration: none; color: var(--ink); position: relative;
    transition: border-color .15s, box-shadow .15s, transform .15s;
}
.lyr-card:hover { border-color: var(--brass); box-shadow: 0 14px 28px -18px rgba(0,116,46,.25); transform: translateY(-2px); }
.lyr-card-current { border-color: var(--brass); background: var(--brass-soft); border-width: 2px; }
.lyr-card-year { font-family: 'IBM Plex Sans', sans-serif; font-size: 1.7rem; font-weight: 700; }
.lyr-card-tag { font-size: .64rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--brass); margin-top: .4rem; }
</style>

@endsection
