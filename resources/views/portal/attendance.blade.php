@extends('layouts.portal')
@section('title', 'My attendance')

@php
    $labels = \App\Models\Attendance::STATUSES;
    $tone = ['present' => 'approved', 'absent' => 'rejected', 'sick' => 'pending', 'leave' => 'manager_approved', 'holiday' => 'paused', 'non_working' => 'paused', 'other' => 'paused'];
    $prev = $month->copy()->subMonthNoOverflow()->format('Y-m');
    $next = $month->copy()->addMonthNoOverflow();
@endphp

@push('styles')
<style>
.pt-rota { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: .6rem; }
.pt-shift { display: flex; flex-direction: column; gap: .35rem; padding: .7rem .8rem; border-radius: 14px; border: 1px solid #e3e8e4; border-left: 5px solid var(--c); background: #fff; }
.pt-shift.today { box-shadow: 0 0 0 2px var(--c); }
.pt-shift.off { background: #f6f7f6; }
.pt-shift .d { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: #6b736e; font-weight: 700; }
.pt-shift .d b { display: block; font-size: .95rem; text-transform: none; letter-spacing: 0; color: #13261b; }
.pt-shift .s b { display: block; font-size: .9rem; }
.pt-shift .s small { color: #6b736e; font-size: .78rem; }
</style>
@endpush

@section('content')
<div class="pt-page-head">
    <div>
        <h1>My attendance</h1>
        <p>What HR has recorded for you. Spot something wrong? Let HR know.</p>
    </div>
    <div style="display:flex;gap:.5rem;align-items:center">
        <a class="zp-btn zp-btn-ghost" href="{{ route('portal.attendance', ['month' => $prev]) }}" aria-label="Previous month"><i class="bi bi-chevron-left"></i></a>
        <b style="min-width:120px;text-align:center">{{ $month->format('F Y') }}</b>
        @if($next->lte(now()))<a class="zp-btn zp-btn-ghost" href="{{ route('portal.attendance', ['month' => $next->format('Y-m')]) }}" aria-label="Next month"><i class="bi bi-chevron-right"></i></a>@endif
    </div>
</div>

<div class="pt-grid">
    @if($rota->isNotEmpty())
        <div class="pt-card pt-span-12">
            <h3>My shifts · next two weeks</h3>
            <div class="pt-rota">
                @foreach($rota as $a)
                    <div class="pt-shift {{ $a->date->isToday() ? 'today' : '' }} {{ $a->shift ? '' : 'off' }}" style="--c:{{ $a->shift?->color ?? '#9aa09c' }}">
                        <span class="d">{{ $a->date->isToday() ? 'Today' : ($a->date->isTomorrow() ? 'Tomorrow' : $a->date->format('D')) }}<b>{{ $a->date->format('j M') }}</b></span>
                        <span class="s"><b>{{ $a->shift?->name ?? 'Day off' }}</b>@if($a->shift)<small>{{ $a->shift->times() }}@if($a->shift->isOvernight()) · overnight @endif</small>@endif</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
    <div class="pt-card pt-span-4">
        <h3>This month</h3>
        <ul class="pt-list">
            @foreach(['present', 'absent', 'sick', 'leave'] as $s)
                <li><span class="ic"><i class="bi {{ ['present' => 'bi-check2-circle', 'absent' => 'bi-x-circle', 'sick' => 'bi-thermometer-half', 'leave' => 'bi-calendar2-heart'][$s] }}"></i></span>
                    <span class="tx"><b>{{ $counts[$s] ?? 0 }} day{{ ($counts[$s] ?? 0) === 1 ? '' : 's' }}</b><span>{{ $labels[$s] }}</span></span></li>
            @endforeach
            @if($hours > 0)<li><span class="ic"><i class="bi bi-clock"></i></span><span class="tx"><b>{{ rtrim(rtrim(number_format($hours, 2), '0'), '.') }} hours</b><span>Clocked</span></span></li>@endif
        </ul>
    </div>

    <div class="pt-card pt-span-8">
        <h3>Day by day</h3>
        <ul class="pt-list">
            @foreach($days as $d)
                @continue($d['future'])
                @php $r = $d['record']; @endphp
                @if($r || $d['working'])
                    <li>
                        <span class="ic" style="font-weight:700;font-size:.8rem">{{ $d['date']->format('d') }}</span>
                        <span class="tx">
                            <b>{{ $d['date']->format('l') }}</b>
                            <span>
                                @if($r && $r->time_in){{ substr($r->time_in, 0, 5) }}{{ $r->time_out ? '–' . substr($r->time_out, 0, 5) : '' }}@endif
                                @if($r && $r->note && ! str_starts_with((string) $r->note, 'leave#')) · {{ $r->note }}@elseif($r && str_starts_with((string) $r->note, 'leave#')) {{ \Illuminate\Support\Str::after($r->note, ': ') }}@endif
                                @if(! $r) Not recorded @endif
                            </span>
                        </span>
                        @if($r)<span class="pt-status {{ $tone[$r->status] ?? 'paused' }}">{{ $labels[$r->status] ?? $r->status }}</span>@endif
                    </li>
                @endif
            @endforeach
        </ul>
    </div>
</div>
@endsection
