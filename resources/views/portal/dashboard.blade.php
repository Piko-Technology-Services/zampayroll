@extends('layouts.portal')
@section('title', 'Home')

@php
    $entitled = (float) ($employee->leave_days_entitled ?? 0);
    $balance = $employee->leave_days_balance !== null ? (float) $employee->leave_days_balance : null;
    $pct = ($entitled > 0 && $balance !== null) ? max(0, min(1, $balance / $entitled)) : 0;
    $circ = 2 * M_PI * 46;
    $loanBalance = $activeLoans->sum('balance');
    $fmt = fn ($n) => number_format((float) $n, 2);
@endphp

@section('content')
<section class="pt-hero">
    <div class="pt-hello">
        <h1>{{ $greeting }}, {{ strtok($employee->first_name ?: auth()->user()->name, ' ') }}.</h1>
        <p>Everything about your pay and time off at {{ $company?->name }}, in one place. Requests you send here go straight to HR.</p>

        <div class="pt-actions">
            <a class="pt-action leave" href="{{ route('portal.leave') }}#apply">
                <i class="bi bi-calendar2-heart"></i>
                <div><b>Request leave</b><br><span>Working days counted for you</span></div>
            </a>
            <a class="pt-action ot" href="{{ route('portal.overtime') }}#apply">
                <i class="bi bi-alarm"></i>
                <div><b>Log overtime</b><br><span>See the payout before you send</span></div>
            </a>
            <a class="pt-action loan" href="{{ route('portal.loans') }}#apply">
                <i class="bi bi-cash-coin"></i>
                <div><b>Ask for a loan</b><br><span>Choose how it's repaid</span></div>
            </a>
        </div>
    </div>

    <div>
        @if($latestPayslip)
            <div class="pt-slip">
                <div class="pt-slip-label">
                    <span>Latest payslip — {{ $latestPayslip->pay_period ?: $latestPayslip->run?->period }}</span>
                    <span class="pt-status approved">Paid out</span>
                </div>
                <div class="pt-slip-amount"><small>K</small>{{ $fmt($latestPayslip->net_pay) }}</div>
                <div class="text-muted small">Take-home pay</div>
                <div class="pt-slip-rows">
                    <div>Gross<b>K{{ $fmt($latestPayslip->total_income) }}</b></div>
                    <div>Deductions<b>K{{ $fmt($latestPayslip->total_deductions) }}</b></div>
                </div>
                <div class="pt-slip-actions">
                    <a class="zp-btn zp-btn-primary" href="{{ route('portal.payslips.show', $latestPayslip) }}" target="_blank" rel="noopener"><i class="bi bi-eye"></i> View</a>
                    <a class="zp-btn zp-btn-ghost" href="{{ route('portal.payslips.download', $latestPayslip) }}"><i class="bi bi-download"></i> PDF</a>
                </div>
            </div>
        @else
            <div class="pt-slip">
                <div class="pt-slip-label"><span>Payslips</span></div>
                <div class="pt-empty" style="padding:1.25rem 0 .5rem">
                    <i class="bi bi-receipt"></i>
                    Your first payslip will appear here as soon as HR finalises a payroll run.
                </div>
            </div>
        @endif
    </div>
</section>

<div class="pt-grid">
    <div class="pt-card pt-span-4">
        <h3>Leave balance <a href="{{ route('portal.leave') }}">History</a></h3>
        @if($balance !== null)
            <div class="pt-ring">
                <svg viewBox="0 0 108 108" role="img" aria-label="{{ $balance }} of {{ $entitled }} days left">
                    <circle cx="54" cy="54" r="46" fill="none" stroke="#E8F3EC" stroke-width="12"/>
                    <circle cx="54" cy="54" r="46" fill="none" stroke="#00742D" stroke-width="12" stroke-linecap="round"
                            stroke-dasharray="{{ $circ }}" stroke-dashoffset="{{ $circ * (1 - $pct) }}" transform="rotate(-90 54 54)"/>
                </svg>
                <div>
                    <div class="num">{{ rtrim(rtrim(number_format($balance, 1), '0'), '.') }}</div>
                    <div class="lbl">days left of {{ rtrim(rtrim(number_format($entitled, 1), '0'), '.') }}</div>
                </div>
            </div>
        @else
            <div class="pt-empty"><i class="bi bi-calendar2"></i>HR hasn't set your leave balance yet.</div>
        @endif
    </div>

    <div class="pt-card pt-span-4">
        <h3>Waiting for HR</h3>
        <ul class="pt-list">
            <li><span class="ic"><i class="bi bi-calendar2-heart"></i></span><span class="tx"><b>Leave requests</b><span>Pending review</span></span><span class="pt-status {{ $pending['leave'] ? 'pending' : 'approved' }}">{{ $pending['leave'] ?: 'None' }}</span></li>
            <li><span class="ic"><i class="bi bi-alarm"></i></span><span class="tx"><b>Overtime claims</b><span>Pending review</span></span><span class="pt-status {{ $pending['overtime'] ? 'pending' : 'approved' }}">{{ $pending['overtime'] ?: 'None' }}</span></li>
            <li><span class="ic"><i class="bi bi-cash-coin"></i></span><span class="tx"><b>Loan requests</b><span>Pending review</span></span><span class="pt-status {{ $pending['loans'] ? 'pending' : 'approved' }}">{{ $pending['loans'] ?: 'None' }}</span></li>
        </ul>
    </div>

    <div class="pt-card pt-span-4">
        <h3>Loans <a href="{{ route('portal.loans') }}">Details</a></h3>
        @if($activeLoans->isNotEmpty())
            <div class="pt-slip-amount" style="font-size:1.9rem"><small>K</small>{{ $fmt($loanBalance) }}</div>
            <div class="text-muted small mb-2">Outstanding across {{ $activeLoans->count() }} active loan{{ $activeLoans->count() > 1 ? 's' : '' }}</div>
            @foreach($activeLoans as $loan)
                @php $paid = $loan->principal_amount > 0 ? 1 - ($loan->balance / $loan->principal_amount) : 0; @endphp
                <div class="small d-flex justify-content-between mt-2"><span>K{{ $fmt($loan->principal_amount) }} loan</span><span>{{ round($paid * 100) }}% repaid</span></div>
                <div class="zp-setup-meter" style="width:100%;background:#EEF2EF"><span style="width:{{ round($paid * 100) }}%"></span></div>
            @endforeach
        @else
            <div class="pt-empty"><i class="bi bi-emoji-smile"></i>No outstanding loans.</div>
        @endif
    </div>

    <div class="pt-card pt-span-8">
        <h3>Recent activity</h3>
        @if($recent->isEmpty())
            <div class="pt-empty"><i class="bi bi-inbox"></i>Nothing yet. When you send a request it will show up here with its status.</div>
        @else
            <ul class="pt-list">
                @foreach($recent as $item)
                    <li>
                        <span class="ic"><i class="bi {{ $item['icon'] }}"></i></span>
                        <span class="tx"><b>{{ $item['title'] }}</b><span>{{ $item['detail'] }} — sent {{ $item['at']->diffForHumans() }}</span></span>
                        <span class="pt-status {{ $item['status'] }}">{{ $item['status'] }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="pt-card pt-span-4">
        <h3>My details <a href="{{ route('portal.profile') }}">Edit</a></h3>
        <ul class="pt-list">
            <li><span class="ic"><i class="bi bi-person-vcard"></i></span><span class="tx"><b>{{ $employee->employee_id }}</b><span>Employee number</span></span></li>
            <li><span class="ic"><i class="bi bi-briefcase"></i></span><span class="tx"><b>{{ $employee->position }}</b><span>{{ $employee->department ?: 'Department not set' }}</span></span></li>
            <li><span class="ic"><i class="bi bi-receipt"></i></span><span class="tx"><b>{{ $payslipCount }} payslip{{ $payslipCount === 1 ? '' : 's' }}</b><span>Available to download</span></span></li>
        </ul>
    </div>
</div>
@endsection
