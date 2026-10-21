@extends('layouts.app')

@section('title', 'Leave types & rules · ZamPayroll')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/zp-people.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/zp-payroll.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/zp-requests.css') }}">
@endpush

@php $fmt = fn ($n) => $n === null ? null : rtrim(rtrim(number_format((float) $n, 1), '0'), '.'); @endphp

@section('content')
<div class="pr pp rq">
    <div class="pr-head">
        <div>
            <h1>Leave types &amp; rules</h1>
            <p>Set up the kinds of leave you offer. Day limits are yours to set: check them against your contracts and the Employment Code.</p>
        </div>
    </div>

    <div class="pr-layout" style="display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:1rem;align-items:start">
        <div>
            <div class="rq-types">
                @foreach($types as $t)
                    <details class="rq-typerow {{ $t->is_active ? '' : 'off' }}">
                        <summary>
                            <b>{{ $t->name }}</b>
                            <span class="tags">
                                <span class="rq-type {{ $t->is_paid ? '' : 'unpaid' }}">{{ $t->is_paid ? 'Paid' : 'Unpaid' }}</span>
                                @if($t->deducts_balance)<span class="rq-type">Uses annual balance</span>@endif
                                @if($t->days_per_year !== null)<span class="rq-type">{{ $fmt($t->days_per_year) }} days / year</span>@endif
                                @if($t->max_days_per_request !== null)<span class="rq-type">max {{ $fmt($t->max_days_per_request) }} per request</span>@endif
                                @if($t->requires_document)<span class="rq-type">Document{{ $t->document_after_days ? ' after ' . $t->document_after_days . ' days' : '' }}</span>@endif
                                @unless($t->employees_can_apply)<span class="rq-type">HR records only</span>@endunless
                                @unless($t->is_active)<span class="rq-type">Switched off</span>@endunless
                            </span>
                            <i class="bi bi-chevron-down chev"></i>
                        </summary>
                        <form method="POST" action="{{ route('leave.types.update', $t) }}" class="rq-typeform">
                            @csrf @method('PUT')
                            @include('dashboard.leave._type-fields', ['t' => $t])
                            <div class="full" style="display:flex;justify-content:flex-end"><button class="pp-btn pp-btn-primary pp-btn-sm"><i class="bi bi-check-lg"></i> Save</button></div>
                        </form>
                    </details>
                @endforeach
            </div>

            <details class="rq-typerow" style="margin-top:.8rem">
                <summary><b><i class="bi bi-plus-circle" style="color:var(--zp-green)"></i> Add a leave type</b><i class="bi bi-chevron-down chev"></i></summary>
                <form method="POST" action="{{ route('leave.types.store') }}" class="rq-typeform">
                    @csrf
                    @include('dashboard.leave._type-fields', ['t' => new \App\Models\LeaveType(['is_paid' => true, 'is_active' => true, 'employees_can_apply' => true])])
                    <div class="full" style="display:flex;justify-content:flex-end"><button class="pp-btn pp-btn-primary pp-btn-sm"><i class="bi bi-plus-lg"></i> Add</button></div>
                </form>
            </details>
        </div>

        <form class="pr-card" method="POST" action="{{ route('leave.types.policy') }}">
            @csrf
            <div class="pr-card-head"><h2>How approval works</h2></div>
            <div class="rq-policy">
                <label>
                    <input type="checkbox" name="leave_hr_confirm" value="1" @checked($company->leave_hr_confirm)>
                    <span><b>HR confirms manager approvals</b><small>Off: a manager or HR approves on their own, and HR still sees every request and can cancel. On: a manager approves first, then HR confirms.</small></span>
                </label>
                <label>
                    <input type="checkbox" name="leave_allow_negative" value="1" @checked($company->leave_allow_negative)>
                    <span><b>Allow leave balances below zero</b><small>Lets people take annual leave in advance. Off: requests over the balance are stopped.</small></span>
                </label>
            </div>
            <button class="pp-btn pp-btn-primary" style="margin-top:1rem;width:100%"><i class="bi bi-check-lg"></i> Save</button>
            <p style="font-size:.78rem;color:var(--zp-ink-3);margin:.8rem 0 0">Managers see requests from their own department and the people who list them as supervisor. HR and administrators see everyone.</p>
        </form>
    </div>
</div>
<style>@media (max-width: 900px) { .rq .pr-layout { grid-template-columns: 1fr !important; } }</style>
@endsection
