@extends('layouts.app')

@section('title', 'Payroll · ZamPayroll')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/zp-people.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/zp-payroll.css') }}">
@endpush

@php
    $u = auth()->user();
    $k = fn ($n) => 'K ' . number_format((float) $n, 2);
    $canRun = $u->hasPermission('payroll.run');
    $monthName = now()->format('F Y');
@endphp

@section('content')
<div class="pr pp" data-walkthrough-auto>
    <div class="pr-head">
        <div>
            <h1>Payroll</h1>
            <p>Calculate, check, get it approved, pay. We'll walk you through each step.</p>
        </div>
        <div class="pr-head-actions">
            <button type="button" class="pp-btn pp-btn-ghost" data-walkthrough><i class="bi bi-play-circle"></i> How payroll works</button>
            @if($u->hasPermission('payroll.finalize'))
                <a href="{{ route('payroll-approvals.index') }}" class="pp-btn pp-btn-ghost"><i class="bi bi-diagram-3"></i> Approval chain</a>
            @endif
            @if($canRun)
                <a href="{{ route('payroll.runs.create') }}" class="pp-btn pp-btn-primary"><i class="bi bi-plus-lg"></i> New pay run</a>
            @endif
        </div>
    </div>

    {{-- Waiting for me --}}
    @foreach($waiting as $w)
        <a href="{{ route('payroll.runs.show', $w) }}" class="pr-waiting" style="color:inherit">
            <span class="ic"><i class="bi bi-pen"></i></span>
            <div style="flex:1;min-width:200px">
                <b>{{ $w->displayName() }} is waiting for your approval</b>
                <small>{{ $approvals->currentStep($w)['name'] ?? 'Approval' }} · {{ $w->payrolls_count }} people · take-home {{ $k($w->net_pay) }} · submitted {{ optional($w->submitted_at)->diffForHumans() }}</small>
            </div>
            <span class="pp-btn pp-btn-copper pp-btn-sm">Review <i class="bi bi-arrow-right"></i></span>
        </a>
    @endforeach

    {{-- This month --}}
    <section class="pr-card" style="padding:1.5rem">
        <div class="pr-hero">
            <div>
                <div class="pp-eyebrow" style="color:var(--zp-green)">This month</div>
                <div class="pr-month">{{ $current ? $current->displayName() : $monthName . ' payroll' }}</div>
                @if($current)
                    <div class="pr-month-sub">
                        <span class="pr-pill {{ $current->statusTone() }}">{{ $current->statusLabel() }}</span>
                        &nbsp;{{ $current->payrolls_count }} people · take-home <b class="pr-k">{{ $k($current->net_pay ?: $current->payrolls_sum_net_pay) }}</b>
                    </div>
                @else
                    <div class="pr-month-sub">Not started yet. It takes about a minute.</div>
                @endif
                <div style="margin-top:1.3rem">
                    @include('dashboard.payroll.flow._track', ['stage' => $current ? $current->stage() : 1])
                </div>
                @if($monthRuns->count() > 1)
                    <div class="pr-counts" style="margin-top:.9rem">
                        <span style="background:var(--zp-mist)">{{ $monthRuns->count() }} pay runs this month</span>
                        @foreach($monthRuns->where('id', '!=', $current->id) as $o)
                            <a href="{{ route('payroll.runs.show', $o) }}" class="pr-pill {{ $o->statusTone() }}" title="{{ $o->statusLabel() }}">{{ $o->displayName() }}</a>
                        @endforeach
                    </div>
                @endif
            </div>
            <div>
                @if($current)
                    @php
                        $cta = match (true) {
                            $current->isEditable() => ['Continue checking', 'bi-search', 'Check the highlights and submit it for approval.'],
                            $current->isInReview() => ['See approval progress', 'bi-people', 'Waiting for ' . ($currentStep['name'] ?? 'approval') . ' — ' . $approvals->stepWho($currentStep ?? []) . '.'],
                            $current->status === \App\Models\PayrollRun::PAID => ['Open payroll', 'bi-folder2-open', 'All done for ' . now()->format('F') . '. 🎉'],
                            default => ['Pay your people', 'bi-send', 'Approved and locked. Send payslips, download the bank file, then mark it paid.'],
                        };
                    @endphp
                    <p class="pp-muted" style="font-size:.95rem;margin:0 0 1rem">{{ $cta[2] }}</p>
                    <a class="pp-btn pp-btn-primary" href="{{ route('payroll.runs.show', $current) }}"><i class="bi {{ $cta[1] }}"></i> {{ $cta[0] }}</a>
                @else
                    <div class="pr-pre">
                        <div><span>People to pay</span><b>{{ $preview['people'] }}</b></div>
                        <div><span>Expected take-home</span><b>{{ 'K ' . number_format($preview['expected_net'], 0) }}</b></div>
                        <div><span>On leave</span><b>{{ $preview['on_leave'] }}</b></div>
                        <div><span>With loans</span><b>{{ $preview['loans'] }}</b></div>
                        <div><span>Overtime</span><b>{{ $preview['overtime'] }}</b></div>
                        <div class="{{ $preview['pending'] ? 'warn' : '' }}"><span>Undecided requests</span><b>{{ $preview['pending'] }}</b></div>
                    </div>
                    @if($canRun)
                        <a class="pp-btn pp-btn-primary" style="margin-top:1rem;width:100%" href="{{ route('payroll.runs.create') }}"><i class="bi bi-play-fill"></i> Start {{ now()->format('F') }} payroll</a>
                    @endif
                @endif
            </div>
        </div>
    </section>

    {{-- History, grouped by month --}}
    <section class="pr-card">
        <div class="pr-card-head">
            <div><h2>All pay runs</h2><div class="sub">Grouped by month. A month can have several runs — a main payroll plus bonus, backpay or leaver runs.</div></div>
            <div class="pr-years">
                <a href="{{ route('payroll.runs.index', array_filter(['status' => $status])) }}" class="{{ $year ? '' : 'on' }}">All years</a>
                @foreach($years as $y)
                    <a href="{{ route('payroll.runs.index', array_filter(['year' => $y, 'status' => $status])) }}" class="{{ $year === $y ? 'on' : '' }}">{{ $y }}</a>
                @endforeach
            </div>
        </div>
        <div class="pr-years" style="margin:-.3rem 0 1rem">
            @foreach(['' => 'Any status', 'draft' => 'Being prepared', 'review' => 'In approval', 'locked' => 'Approved · locked', 'paid' => 'Paid'] as $v => $label)
                <a href="{{ route('payroll.runs.index', array_filter(['year' => $year, 'status' => $v])) }}" class="{{ $status === $v ? 'on' : '' }}">{{ $label }}@if($v) <small>{{ $counts[$v] ?? 0 }}</small>@endif</a>
            @endforeach
        </div>
        @if($months->isEmpty())
            <div class="pr-empty"><i class="bi bi-cash-stack"></i>{{ $status || $year ? 'No pay runs match.' : 'No payrolls yet. Start your first one above.' }}</div>
        @else
            @foreach($months as $period => $group)
                @php
                    $first = $group->first();
                    $c = $group->groupBy(fn ($r) => $r->statusTone())->map->count();
                    $sum = $group->sum(fn ($r) => (float) ($r->net_pay ?: $r->payrolls_sum_net_pay));
                @endphp
                <div class="pr-mgroup">
                    <div class="pr-mgroup-head">
                        <h3>{{ $first->period_date ? $first->period_date->format('F Y') : $period }}</h3>
                        <div class="pr-counts">
                            @foreach(['draft' => 'being prepared', 'review' => 'in approval', 'locked' => 'locked', 'paid' => 'paid'] as $tone => $word)
                                @if($c[$tone] ?? 0)<span class="{{ $tone }}">{{ $c[$tone] }} {{ $word }}</span>@endif
                            @endforeach
                        </div>
                        <span class="tot">{{ $group->count() }} {{ $group->count() === 1 ? 'run' : 'runs' }} · take-home <b>{{ $k($sum) }}</b></span>
                    </div>
                    @foreach($group as $r)
                        <a class="pr-mrun" href="{{ route('payroll.runs.show', $r) }}">
                            <div class="t"><b>{{ $r->displayName() }}</b><small>{{ $r->typeLabel() }} · {{ $r->coverageSummary() }}@if($r->unlock_count) · <i class="bi bi-unlock"></i> unlocked {{ $r->unlock_count }}×@endif</small></div>
                            <span class="pr-pill {{ $r->statusTone() }}">{{ $r->statusLabel() }}</span>
                            <div class="n hide-sm"><b>{{ $r->payrolls_count }}</b>people</div>
                            <div class="n"><b>{{ $k($r->net_pay ?: $r->payrolls_sum_net_pay) }}</b>{{ $r->finalized_at ? 'approved ' . $r->finalized_at->format('j M') : 'take-home' }}</div>
                        </a>
                    @endforeach
                    @if($canRun && $first->period_date)
                        <a class="pr-mgroup-add" href="{{ route('payroll.runs.create', ['month' => $first->period_date->format('Y-m'), 'new' => 1]) }}"><i class="bi bi-plus-lg"></i> Add another pay run for {{ $first->period_date->format('F') }}</a>
                    @endif
                </div>
            @endforeach
        @endif
        <div style="display:flex;gap:1rem;margin-top:.9rem;font-size:.82rem">
            <a class="pp-link" href="{{ route('payroll.runs.hidden') }}"><i class="bi bi-eye-slash"></i> Hidden ({{ $hiddenCount }})</a>
            <a class="pp-link" href="{{ route('payroll.runs.trash') }}"><i class="bi bi-trash"></i> Trash ({{ $trashedCount }})</a>
        </div>
    </section>
</div>
@include('dashboard.payroll.flow._walkthrough')
@endsection
