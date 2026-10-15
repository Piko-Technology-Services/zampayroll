@extends('layouts.portal')
@section('title', 'Payslips')

@section('content')
<div class="pt-page-head">
    <div>
        <h1>Payslips</h1>
        <p>Every finalised payslip, ready to view or download.</p>
    </div>
    <div style="display:flex;gap:.6rem">
        <div class="pt-stat">Gross pay {{ now()->year }}<b>K{{ number_format($ytd['gross'], 2) }}</b></div>
        <div class="pt-stat">Take-home {{ now()->year }}<b>K{{ number_format($ytd['net'], 2) }}</b></div>
    </div>
</div>

@forelse($byYear as $year => $slips)
    <h2 class="pt-year">{{ $year }}</h2>
    <div class="pt-card" style="padding:.4rem 1.1rem">
        <table class="pt-table">
            <thead><tr><th>Pay period</th><th class="d-none d-md-table-cell" style="text-align:right">Gross</th><th class="d-none d-md-table-cell" style="text-align:right">Deductions</th><th style="text-align:right">Take-home</th><th></th></tr></thead>
            <tbody>
            @foreach($slips as $p)
                <tr>
                    <td><b>{{ $p->pay_period ?: $p->run?->period }}</b><div class="small text-muted">{{ $p->run?->alias }}</div></td>
                    <td class="money d-none d-md-table-cell">K{{ number_format((float) $p->total_income, 2) }}</td>
                    <td class="money d-none d-md-table-cell">K{{ number_format((float) $p->total_deductions, 2) }}</td>
                    <td class="money" style="color:var(--zp-forest)">K{{ number_format((float) $p->net_pay, 2) }}</td>
                    <td style="text-align:right;white-space:nowrap">
                        <a class="zp-btn zp-btn-ghost" style="padding:.4rem .7rem" href="{{ route('portal.payslips.show', $p) }}" target="_blank" rel="noopener" aria-label="View payslip"><i class="bi bi-eye"></i></a>
                        <a class="zp-btn zp-btn-primary" style="padding:.4rem .7rem" href="{{ route('portal.payslips.download', $p) }}" aria-label="Download PDF"><i class="bi bi-download"></i></a>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@empty
    <div class="pt-card"><div class="pt-empty"><i class="bi bi-receipt"></i>No payslips yet. They appear here once HR finalises a payroll run that includes you.</div></div>
@endforelse
@endsection
