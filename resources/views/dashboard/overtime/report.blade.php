@extends('layouts.app')

@section('title', 'Overtime report · ZamPayroll')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/zp-people.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/zp-payroll.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/zp-requests.css') }}">
@endpush

@php
    $h = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
    $k = fn ($n) => 'K ' . number_format((float) $n, 2);
@endphp

@section('content')
<div class="pr pp rq">
    <div class="pr-head">
        <div>
            <h1>Overtime · {{ $month->format('F Y') }}</h1>
            <p>Approved overtime by person, with hours at each rate.</p>
        </div>
        <form class="pr-head-actions" method="GET">
            <input class="pp-input" type="month" name="month" value="{{ $month->format('Y-m') }}" onchange="this.form.submit()" style="width:auto">
            <a href="{{ route('overtime.report', ['month' => $month->format('Y-m'), 'format' => 'csv']) }}" class="pp-btn pp-btn-ghost"><i class="bi bi-download"></i> Download CSV</a>
        </form>
    </div>

    <div class="rq-kpis">
        <div class="rq-kpi main"><span>Total amount</span><b>{{ $k($rows->sum('amount')) }}</b></div>
        <div class="rq-kpi"><span>Total hours</span><b>{{ $h($rows->sum('hours')) }}</b></div>
        <div class="rq-kpi"><span>People</span><b>{{ $rows->count() }}</b></div>
        <div class="rq-kpi"><span>Claims</span><b>{{ $rows->sum('claims') }}</b></div>
    </div>

    <section class="pr-card" style="padding:0;overflow-x:auto">
        <table class="pr-table" style="width:100%;min-width:720px">
            <thead>
                <tr>
                    <th style="text-align:left">Employee</th>
                    <th>Claims</th>
                    @foreach($types as $t)<th>{{ $t['label'] }}<br><small>{{ $h($t['multiplier']) }}× hours</small></th>@endforeach
                    <th>Total hours</th>
                    <th>Amount</th>
                    <th>In a payroll</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $r)
                    <tr>
                        <td style="text-align:left"><b>{{ $r['employee']?->full_name }}</b><br><small style="color:var(--zp-ink-3)">{{ $r['employee']?->employee_id }}{{ $r['employee']?->department ? ' · ' . $r['employee']->department : '' }}</small></td>
                        <td>{{ $r['claims'] }}</td>
                        @foreach(array_keys($types) as $key)<td>{{ isset($r['by_type'][$key]) ? $h($r['by_type'][$key]) : '—' }}</td>@endforeach
                        <td><b>{{ $h($r['hours']) }}</b></td>
                        <td><b>{{ $k($r['amount']) }}</b></td>
                        <td>{{ $r['in_payroll'] }} / {{ $r['claims'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ 5 + count($types) }}" style="text-align:center;padding:2rem;color:var(--zp-ink-3)">No approved overtime in {{ $month->format('F Y') }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
</div>
<style>.rq .pr-table th, .rq .pr-table td { padding: .7rem .9rem; text-align: right; border-bottom: 1px solid var(--zp-line); font-size: .86rem; } .rq .pr-table th { font-size: .72rem; color: var(--zp-ink-3); font-weight: 700; background: var(--zp-mist); }</style>
@endsection
