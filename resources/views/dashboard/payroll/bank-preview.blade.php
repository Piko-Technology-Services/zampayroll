@extends('layouts.app')

@section('title', 'Bank file · ' . $run->displayName() . ' · ZamPayroll')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/zp-people.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/zp-payroll.css') }}">
<style>
    .bp-warn { display: flex; gap: .7rem; align-items: flex-start; background: var(--zp-copper-50); color: #7a4512; border-radius: 14px; padding: .8rem 1rem; margin-bottom: 1rem; font-size: .88rem; }
    .bp-ok { color: var(--zp-green); font-weight: 700; font-size: .78rem; }
    .bp-bad { color: #b42318; font-weight: 700; font-size: .78rem; }
    .bp-tpl { display: flex; gap: .6rem; align-items: center; flex-wrap: wrap; }
    .bp-tpl select { min-width: 280px; }
    .pr-table tbody tr { cursor: default; }
    .pr-table td.mono { font-family: ui-monospace, 'DejaVu Sans Mono', monospace; font-size: .8rem; }
</style>
@endpush

@section('content')
<div class="pr pp">
    <a class="pr-crumb" href="{{ route('payroll.runs.show', $run) }}"><i class="bi bi-arrow-left"></i> {{ $run->displayName() }}</a>
    <div class="pr-head">
        <div>
            <h1>Bank file</h1>
            <p>Check who will be paid and how much, then download the file for your bank's bulk-payment portal.</p>
        </div>
        <div class="pr-head-actions">
            <a class="pp-btn pp-btn-ghost pp-btn-sm" href="{{ route('bank-templates.index') }}"><i class="bi bi-gear"></i> Bank templates</a>
        </div>
    </div>

    <div class="pr-kpis" style="grid-template-columns:repeat(3,minmax(0,1fr))">
        <div class="pr-kpi"><span>People to pay</span><b>{{ $rows->count() }}</b></div>
        <div class="pr-kpi"><span>Ready for the bank</span><b>{{ $rows->count() - $missingAccountCount }}</b></div>
        <div class="pr-kpi main"><span>Total to pay</span><b>K {{ number_format($total, 2) }}</b></div>
    </div>

    @if($missingAccountCount)
        <div class="bp-warn"><i class="bi bi-exclamation-triangle"></i><div><b>{{ $missingAccountCount }} {{ $missingAccountCount === 1 ? 'person has' : 'people have' }} no account number.</b> They are left out of the bank file. Add their details on their profile, or pay them another way.</div></div>
    @endif
    @if($unmatchedCount)
        <div class="bp-warn"><i class="bi bi-info-circle"></i><div>We couldn't recognise the bank for {{ $unmatchedCount }} {{ $unmatchedCount === 1 ? 'person' : 'people' }}. Check the bank name on their profile so the sort code and SWIFT code are filled in.</div></div>
    @endif

    <div class="pr-card">
        <div class="pr-card-head">
            <div><h2>Download</h2><div class="sub">The file follows your bank's column layout.</div></div>
        </div>
        @if($templates->isEmpty())
            <p class="pp-muted" style="margin:0 0 .8rem">You haven't set up a bank template yet. Pick your bank once and every pay run uses it.</p>
            <a class="pp-btn pp-btn-primary" href="{{ route('bank-templates.index') }}"><i class="bi bi-bank"></i> Set up your bank template</a>
        @else
            <form method="GET" action="{{ route('payroll.reports.bank_payments.csv', $run) }}" class="bp-tpl">
                <select class="pp-select" name="template_id" aria-label="Bank template">
                    @foreach($templates as $t)
                        <option value="{{ $t->id }}" @selected($default && $default->id === $t->id)>{{ $t->name }}{{ $t->is_default ? ' (default)' : '' }}</option>
                    @endforeach
                </select>
                <button class="pp-btn pp-btn-primary"><i class="bi bi-download"></i> Download bank file (CSV)</button>
            </form>
        @endif
    </div>

    <div class="pr-card">
        <div class="pr-card-head"><div><h2>Payments</h2><div class="sub">{{ $run->displayName() }} · take-home pay per person</div></div></div>
        <div class="pr-tablewrap">
            <table class="pr-table">
                <thead><tr><th>Employee</th><th style="text-align:left">Bank</th><th style="text-align:left">Account number</th><th style="text-align:left">Sort code</th><th>Amount</th><th>Status</th></tr></thead>
                <tbody>
                @foreach($rows as $r)
                    @php($e = $r['employee'])
                    <tr>
                        <td>{{ $e?->first_name }} {{ $e?->last_name }}<div class="pp-muted" style="font-size:.75rem">{{ $e?->employee_id }}</div></td>
                        <td style="text-align:left">{{ $r['matched_bank']?->name ?? ($e?->bank_name ?: '—') }}</td>
                        <td style="text-align:left" class="mono">{{ $e?->bank_account_no ?: '—' }}</td>
                        <td style="text-align:left" class="mono">{{ $e?->bank_sort_code ?: ($r['matched_bank']?->sort_code ?: '—') }}</td>
                        <td>K {{ number_format((float) $r['payroll']->net_pay, 2) }}</td>
                        <td>
                            @if(! $r['has_account_number'])<span class="bp-bad">No account</span>
                            @elseif(! $r['matched_bank'])<span class="bp-bad">Check bank</span>
                            @else<span class="bp-ok"><i class="bi bi-check2"></i> Ready</span>@endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot><tr><td>Total · {{ $rows->count() }} people</td><td></td><td></td><td></td><td>K {{ number_format($total, 2) }}</td><td></td></tr></tfoot>
            </table>
        </div>
    </div>
</div>
@endsection
