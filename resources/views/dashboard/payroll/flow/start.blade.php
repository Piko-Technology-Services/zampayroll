@extends('layouts.app')

@section('title', 'Start a payroll · ZamPayroll')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/zp-people.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/zp-payroll.css') }}">
@endpush

@php $k = fn ($n) => 'K ' . number_format((float) $n, 2); @endphp

@section('content')
<div class="pr pp">
    <a class="pr-crumb" href="{{ route('payroll.runs.index') }}"><i class="bi bi-arrow-left"></i> Payroll</a>
    <div class="pr-head">
        <div>
            <h1>Start a payroll</h1>
            <p>Pick the month. We'll calculate everyone's pay, PAYE, NAPSA and NHIMA, and pull in loan repayments and approved overtime.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('payroll.runs.store') }}" id="prStart">
        @csrf
        <div class="pr-layout">
            <div>
                <section class="pr-card">
                    <div class="pr-card-head"><h2>1 · Which month?</h2></div>
                    <div class="pr-months">
                        @foreach($months as $m)
                            <label class="pr-month-opt">
                                <input type="radio" name="period" value="{{ $m['value'] }}" @checked($m['value'] === $month->format('Y-m'))
                                       onchange="location.href='{{ route('payroll.runs.create') }}?month={{ $m['value'] }}'">
                                <span>{{ $m['label'] }}<small>{{ $m['run'] ? $m['run']->statusLabel() : 'Not started' }}</small></span>
                            </label>
                        @endforeach
                    </div>
                    @if($existing)
                        <div class="pp-callout {{ $existing->isEditable() ? '' : 'green' }}" style="margin-top:1rem">
                            <i class="bi bi-info-circle"></i>
                            <div>
                                @if($existing->isEditable())
                                    {{ $existing->displayName() }} already exists. Continuing will <b>recalculate</b> it with today's employee details.
                                @else
                                    {{ $existing->displayName() }} is <b>{{ strtolower($existing->statusLabel()) }}</b>. <a class="pp-link" href="{{ route('payroll.runs.show', $existing) }}">Open it</a>
                                @endif
                            </div>
                        </div>
                    @endif
                </section>

                <section class="pr-card">
                    <div class="pr-card-head"><h2>2 · What we found for {{ $month->format('F') }}</h2><span class="sub">before calculating</span></div>
                    <div class="pr-pre">
                        <div><span>People to pay</span><b>{{ $preview['people'] }}</b></div>
                        <div><span>Expected take-home</span><b>{{ 'K ' . number_format($preview['expected_net'], 0) }}</b></div>
                        <div class="{{ $preview['no_pay'] ? 'warn' : '' }}"><span>No pay set</span><b>{{ $preview['no_pay'] }}</b></div>
                        <div><span>On leave</span><b>{{ $preview['on_leave'] }}</b></div>
                        <div><span>Repaying loans</span><b>{{ $preview['loans'] }}</b></div>
                        <div class="{{ $preview['pending'] ? 'warn' : '' }}"><span>Undecided requests</span><b>{{ $preview['pending'] }}</b></div>
                    </div>
                    @if($preview['pending'])
                        <div class="pp-callout" style="margin-top:1rem"><i class="bi bi-hourglass-split"></i>
                            <div>{{ $preview['pending'] }} leave, overtime or loan request(s) are still waiting for a decision. Deciding them first keeps this payroll accurate —
                                <a class="pp-link" href="{{ route('leave.dashboard') }}">leave</a> ·
                                <a class="pp-link" href="{{ route('overtime.dashboard') }}">overtime</a> ·
                                <a class="pp-link" href="{{ route('loan.dashboard') }}">loans</a>.</div></div>
                    @endif
                </section>

                <section class="pr-card">
                    <div class="pr-card-head"><h2>3 · Options</h2></div>
                    <label class="pr-switch" style="margin-bottom:1rem">
                        <input type="hidden" name="include_overtime" value="0">
                        <input type="checkbox" name="include_overtime" value="1" checked>
                        Add approved overtime
                        <span class="pp-muted" style="font-weight:500">— {{ $preview['overtime'] }} people · {{ $k($preview['overtime_amt']) }}</span>
                    </label>
                    <div class="pp-field" style="max-width:420px">
                        <label for="alias">Name <small>optional</small></label>
                        <input class="pp-input" id="alias" name="alias" placeholder="{{ $month->format('F Y') }} payroll" value="{{ old('alias') }}">
                    </div>
                </section>
            </div>

            <aside class="pr-side">
                <div class="pr-card">
                    <div class="pr-card-head" style="margin-bottom:.6rem"><h2 style="font-size:1rem">What happens next</h2></div>
                    <div class="pr-flow">
                        <span>You check</span><i class="bi bi-arrow-right"></i>
                        @foreach($chain as $s)
                            <span title="{{ $approvals->stepWho($s) }}">{{ $s['name'] }}</span><i class="bi bi-arrow-right"></i>
                        @endforeach
                        <span class="lock"><i class="bi bi-lock-fill"></i> Locked</span>
                    </div>
                    <p class="pp-muted" style="font-size:.8rem;margin:.8rem 0 0">Nothing is final until the last approver signs. You can recalculate as often as you like before you submit.</p>
                </div>
                <button type="submit" class="pp-btn pp-btn-primary" id="prGo" style="width:100%;padding:1rem" @disabled($existing && ! $existing->isEditable())>
                    <i class="bi bi-calculator"></i> <span>{{ $existing && $existing->isEditable() ? 'Recalculate' : 'Calculate' }} {{ $month->format('F') }} payroll</span>
                </button>
            </aside>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('prStart').addEventListener('submit', function () {
    const b = document.getElementById('prGo');
    b.disabled = true;
    b.querySelector('span').textContent = 'Calculating everyone…';
    b.querySelector('i').className = 'pp-busy';
});
</script>
@endpush
