@extends('platform.layout')
@section('title', 'Overview · ZamPayroll admin')
@php $k = fn ($n) => 'K ' . number_format((float) $n, 2); $max = max(1, $revenue->max('total')); @endphp
@section('content')
<div class="pr-head"><div><h1>Overview</h1><p>{{ now()->format('l j F Y') }} · DPO: {{ $dpoReady ? 'connected (' . $dpoMode . ')' : 'not configured' }}</p></div>
    <div class="pr-head-actions"><a class="pp-btn pp-btn-ghost" href="{{ route('platform.companies') }}"><i class="bi bi-buildings"></i> All companies</a></div></div>

@unless($dpoReady)
    <div class="pp-callout" style="margin-bottom:1rem"><i class="bi bi-exclamation-triangle"></i><div><b>DPO isn't configured.</b> Add DPO_COMPANY_TOKEN, DPO_SERVICE_TYPE and DPO_MODE to your .env so clients can pay online.</div></div>
@endunless

<div class="pl-kpis">
    <div class="pr-kpi main"><span>Monthly recurring revenue</span><b>{{ $k($mrr) }}</b><span class="pr-delta">{{ $counts['active'] }} paying companies</span></div>
    <div class="pr-kpi"><span>Collected this month</span><b>{{ $k($thisMonth) }}</b></div>
    <div class="pr-kpi"><span>Outstanding invoices</span><b>{{ $k($outstanding) }}</b></div>
    <div class="pr-kpi"><span>Companies</span><b>{{ $counts['companies'] }}</b><span class="pr-delta">{{ $counts['dedicated'] }} private</span></div>
    <div class="pr-kpi"><span>On free trial</span><b>{{ $counts['trialing'] }}</b></div>
    <div class="pr-kpi"><span>Overdue / limited</span><b style="color:{{ $counts['trouble'] ? 'var(--zp-red)' : 'inherit' }}">{{ $counts['trouble'] }}</b></div>
</div>

<div class="pl-grid2">
    <section class="pr-card">
        <div class="pr-card-head"><h2>Revenue, last 6 months</h2></div>
        <div class="pl-bars">
            @foreach($revenue as $m)
                <div><b>{{ $m['total'] > 0 ? 'K ' . number_format($m['total'] / 1000, 1) . 'k' : '' }}</b><i style="height:{{ max(2, round($m['total'] / $max * 100)) }}%"></i><small>{{ $m['label'] }}</small></div>
            @endforeach
        </div>
    </section>
    <section class="pr-card">
        <div class="pr-card-head"><h2>Needs attention</h2></div>
        <div class="pl-list">
            @foreach($requests as $c)
                <a class="pl-row" href="{{ route('platform.companies.show', $c->id) }}"><i class="bi bi-building-lock" style="color:var(--zp-copper)"></i><div class="grow"><b>{{ $c->name }} wants a private workspace</b><small>{{ $c->requested_subdomain }} · {{ optional($c->subdomain_requested_at)->diffForHumans() }}</small></div><i class="bi bi-chevron-right"></i></a>
            @endforeach
            @foreach($overdue as $inv)
                <a class="pl-row" href="{{ route('platform.companies.show', $inv->company_id) }}"><i class="bi bi-exclamation-circle" style="color:var(--zp-red)"></i><div class="grow"><b>{{ $inv->company?->name }} · {{ $inv->number }}</b><small>{{ $k($inv->total) }} overdue since {{ optional($inv->due_at)->format('j M') }}</small></div><i class="bi bi-chevron-right"></i></a>
            @endforeach
            @foreach($trialsEnding as $c)
                <a class="pl-row" href="{{ route('platform.companies.show', $c->id) }}"><i class="bi bi-hourglass-split" style="color:#2c5c8a"></i><div class="grow"><b>{{ $c->name }}</b><small>Trial {{ $c->trial_ends_at->isPast() ? 'ended' : 'ends' }} {{ $c->trial_ends_at->diffForHumans() }}</small></div><i class="bi bi-chevron-right"></i></a>
            @endforeach
            @foreach($openTickets as $t)
                <a class="pl-row" href="{{ route('platform.tickets.show', $t) }}"><i class="bi bi-life-preserver" style="color:var(--zp-green)"></i><div class="grow"><b>{{ $t->subject }}</b><small>{{ $t->company?->name }} · {{ optional($t->last_reply_at)->diffForHumans() }}</small></div>@if($t->priority === 'urgent')<span class="pl-tag urgent">urgent</span>@endif</a>
            @endforeach
            @if($requests->isEmpty() && $overdue->isEmpty() && $trialsEnding->isEmpty() && $openTickets->isEmpty())
                <div class="pr-allclear"><i class="bi bi-patch-check"></i>Nothing needs you right now.</div>
            @endif
        </div>
    </section>
    <section class="pr-card">
        <div class="pr-card-head"><h2>Latest payments</h2><a class="pp-link" href="{{ route('platform.invoices') }}">All billing</a></div>
        <div class="pl-list">
            @forelse($payments as $p)
                <a class="pl-row" href="{{ route('platform.companies.show', $p->company_id) }}"><i class="bi {{ $p->method === 'card' ? 'bi-credit-card' : ($p->provider === 'manual' ? 'bi-bank' : 'bi-phone') }}"></i><div class="grow"><b>{{ $p->company?->name }}</b><small>{{ $p->created_at->format('j M H:i') }} · {{ str_replace('_', ' ', $p->method) }}</small></div><b>{{ $k($p->amount) }}</b><span class="pl-tag {{ $p->status === 'paid' ? 'active' : ($p->status === 'pending' ? 'trialing' : 'suspended') }}">{{ $p->status }}</span></a>
            @empty
                <div class="pr-empty"><i class="bi bi-cash-coin"></i>No payments yet.</div>
            @endforelse
        </div>
    </section>
    <section class="pr-card">
        <div class="pr-card-head"><h2>New sign-ups</h2></div>
        <div class="pl-list">
            @foreach($signups as $c)
                <a class="pl-row" href="{{ route('platform.companies.show', $c->id) }}"><i class="bi bi-building"></i><div class="grow"><b>{{ $c->name }}</b><small>{{ $c->created_at->diffForHumans() }} · {{ $c->employee_count_range ?? '—' }} employees · {{ $c->industry }}</small></div><span class="pl-tag {{ $c->subscription_status }}">{{ str_replace('_', ' ', $c->subscription_status) }}</span></a>
            @endforeach
        </div>
    </section>
</div>
@endsection
