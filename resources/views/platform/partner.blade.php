@extends('platform.layout')
@section('title', $partner->name . ' · Partners · ZamPayroll admin')
@php
    $k = fn ($n) => 'K ' . number_format((float) $n, 2);
    $pct = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.') . '%';
    $pendingRows = $commissions->where('status', 'pending');
@endphp
@section('content')
<a class="pr-crumb" href="{{ route('platform.partners') }}"><i class="bi bi-arrow-left"></i> Partners</a>
<div class="pr-head">
    <div><h1>{{ $partner->name }} <span class="pl-tag {{ $partner->status === 'active' ? 'active' : ($partner->status === 'pending' ? 'pending' : 'suspended') }}" style="vertical-align:middle;font-family:var(--zp-font)">{{ $partner->status === 'pending' ? 'waiting for review' : $partner->status }}</span></h1>
        <p>{{ $partner->code }} · {{ $partner->typeLabel() }}{{ $partner->firm_name ? ' · ' . $partner->firm_name : '' }}{{ $partner->city ? ' · ' . $partner->city : '' }} · applied {{ $partner->created_at->format('j M Y') }}</p></div>
    <div class="pr-head-actions">
        @if($partner->status === 'pending')
            <form method="POST" action="{{ route('platform.partners.action', $partner) }}">@csrf<input type="hidden" name="action" value="approve"><button class="pp-btn pp-btn-primary"><i class="bi bi-check2-circle"></i> Approve &amp; send code</button></form>
        @endif
        @if($partner->status === 'suspended')
            <form method="POST" action="{{ route('platform.partners.action', $partner) }}">@csrf<input type="hidden" name="action" value="reactivate"><button class="pp-btn pp-btn-ghost">Reactivate</button></form>
        @else
            <form method="POST" action="{{ route('platform.partners.action', $partner) }}" onsubmit="return confirm('Suspend {{ addslashes($partner->name) }}? Their code stops working for new sign-ups.')">@csrf<input type="hidden" name="action" value="suspend"><button class="pp-btn pp-btn-ghost" style="color:var(--zp-red)">Suspend</button></form>
        @endif
    </div>
</div>

<div class="pl-kpis">
    <div class="pr-kpi main"><span>Clients</span><b>{{ $stats['clients'] }}</b><span class="pr-delta">{{ $stats['paying'] }} paying · {{ $stats['trials'] }} on trial</span></div>
    <div class="pr-kpi"><span>Staff on payroll</span><b>{{ number_format($stats['employees']) }}</b></div>
    <div class="pr-kpi"><span>Rate</span><b>{{ $pct($stats['rate']) }}</b><span class="pr-delta">{{ $partner->commission_rate === null ? 'standard' : 'custom' }}</span></div>
    <div class="pr-kpi"><span>To pay out</span><b style="color:{{ $stats['pending'] > 0 ? 'var(--zp-copper)' : 'inherit' }}">{{ $k($stats['pending']) }}</b></div>
    <div class="pr-kpi"><span>Paid out</span><b>{{ $k($stats['paid']) }}</b></div>
</div>

<div class="pr-layout" style="grid-template-columns:minmax(0,1fr) 360px">
    <div>
        <section class="pr-card">
            <div class="pr-card-head"><h2>Clients</h2></div>
            @if($clients->isEmpty())
                <p class="pp-muted" style="margin:0">No companies have signed up with {{ $partner->code }} yet.</p>
            @else
            <div class="pr-tablewrap"><table class="pr-table pr-hist">
                <thead><tr><th>Company</th><th>Joined</th><th style="text-align:right">Employees</th><th>Plan</th><th style="text-align:right">Commission</th></tr></thead>
                <tbody>@foreach($clients as $c)
                    <tr onclick="location.href='{{ route('platform.companies.show', $c->id) }}'"><td><b>{{ $c->name }}</b></td><td>{{ optional($c->joined)->format('j M Y') }}</td><td class="num">{{ $c->employees }}</td><td>{{ $c->label }}</td><td class="num">{{ $c->earned > 0 ? $k($c->earned) : '—' }}</td></tr>
                @endforeach</tbody></table></div>
            @endif
        </section>

        <section class="pr-card">
            <div class="pr-card-head"><div><h2>Commission</h2><div class="sub">Created automatically when a referred company's invoice is paid. Pay the partner, then mark it paid here.</div></div></div>
            @if($commissions->isEmpty())
                <p class="pp-muted" style="margin:0">No commission yet.</p>
            @else
            <form method="POST" action="{{ route('platform.partners.action', $partner) }}" id="payForm">@csrf<input type="hidden" name="action" value="mark_paid">
                <div class="pr-tablewrap"><table class="pr-table pr-hist">
                    <thead><tr><th></th><th>Date</th><th>Client</th><th>Invoice</th><th style="text-align:right">Invoice</th><th style="text-align:right">Rate</th><th style="text-align:right">Commission</th><th>Status</th></tr></thead>
                    <tbody>@foreach($commissions as $cm)
                        <tr>
                            <td>@if($cm->status === 'pending')<input type="checkbox" name="ids[]" value="{{ $cm->id }}" checked>@endif</td>
                            <td>{{ $cm->created_at->format('j M Y') }}</td><td>{{ $cm->company?->name }}</td><td>{{ $cm->invoice_number }}</td>
                            <td class="num">{{ $k($cm->base_amount) }}</td><td class="num">{{ $pct($cm->rate) }}</td><td class="num"><b>{{ $k($cm->amount) }}</b></td>
                            <td><span class="pl-tag {{ $cm->status === 'paid' ? 'active' : ($cm->status === 'void' ? 'suspended' : 'pending') }}">{{ $cm->status }}</span>@if($cm->paid_at)<br><small class="pp-muted">{{ $cm->paid_at->format('j M') }} · {{ $cm->payout_reference }}</small>@endif</td>
                        </tr>
                    @endforeach</tbody></table></div>
                @if($pendingRows->isNotEmpty())
                    <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;margin-top:.8rem">
                        <input class="pp-input" name="reference" placeholder="Payout reference (e.g. Airtel TXN 8H2K…)" required style="max-width:320px">
                        <button class="pp-btn pp-btn-primary pp-btn-sm"><i class="bi bi-cash-coin"></i> Mark ticked as paid</button>
                        <small class="pp-muted">Pay to: {{ $partner->payout_method ? str_replace('_', ' ', $partner->payout_method) . ' — ' . $partner->payout_details : 'no payout details yet' }}</small>
                    </div>
                @endif
            </form>
            @endif
        </section>
    </div>

    <aside class="pr-side">
        <section class="pr-card">
            <div class="pr-card-head" style="margin-bottom:.6rem"><h2 style="font-size:1.05rem">Contact</h2></div>
            <p style="font-size:.85rem;margin:0">{{ $partner->email }}<br>{{ $partner->phone }}<br>Sign-up link: <a href="{{ $partner->joinUrl() }}" target="_blank">{{ $partner->joinUrl() }}</a></p>
            @if($partner->pitch)<p style="font-size:.85rem;margin:.7rem 0 0"><b>How they'll find clients:</b><br>{{ $partner->pitch }}</p>@endif
            <p style="font-size:.85rem;margin:.7rem 0 0"><b>Payout:</b> {{ $partner->payout_method ? str_replace('_', ' ', $partner->payout_method) . ' — ' . $partner->payout_details : 'not added yet' }}</p>
        </section>
        <section class="pr-card">
            <div class="pl-actions">
                <form method="POST" action="{{ route('platform.partners.action', $partner) }}">@csrf<input type="hidden" name="action" value="rate">
                    <label>Commission rate for this partner (empty = standard {{ $pct($defaultRate) }})</label>
                    <input class="pp-input" name="commission_rate" value="{{ $partner->commission_rate }}" inputmode="decimal" placeholder="{{ $defaultRate }}"><button class="pp-btn pp-btn-ghost pp-btn-sm">Save</button></form>
                <form method="POST" action="{{ route('platform.partners.action', $partner) }}">@csrf<input type="hidden" name="action" value="notes">
                    <label>Internal notes</label>
                    <textarea class="pp-input" name="admin_notes" rows="3" style="flex-basis:100%">{{ $partner->admin_notes }}</textarea><button class="pp-btn pp-btn-ghost pp-btn-sm">Save notes</button></form>
            </div>
        </section>
    </aside>
</div>
@endsection
