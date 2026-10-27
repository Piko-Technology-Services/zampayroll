@extends('layouts.app')

@section('title', 'Start a pay run · ZamPayroll')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/zp-people.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/zp-payroll.css') }}">
@endpush

@php
    $k = fn ($n) => 'K ' . number_format((float) $n, 2);
    $mName = $month->format('F');
    $oldCoverage = old('coverage', $continue?->coverageMode() ?? 'all');
    $oldDeps = old('departments', $continue?->scope['departments'] ?? []);
    $oldPeople = array_map('intval', old('employees', $continue?->scope['employees'] ?? []));
    $oldType = old('run_type', $continue?->run_type ?? ($inMonth->isEmpty() ? 'regular' : 'supplementary'));
@endphp

@section('content')
<div class="pr pp">
    <a class="pr-crumb" href="{{ route('payroll.runs.index') }}"><i class="bi bi-arrow-left"></i> Payroll</a>
    <div class="pr-head">
        <div>
            <h1>{{ $continue ? 'Recalculate ' . $continue->displayName() : 'Start a pay run' }}</h1>
            <p>Pick the month and who's being paid. We'll work out everyone's pay, PAYE, NAPSA and NHIMA, and pull in loan repayments and approved overtime.</p>
        </div>
        <div class="pr-head-actions">
            <button type="button" class="pp-btn pp-btn-ghost pp-btn-sm" data-walkthrough><i class="bi bi-play-circle"></i> How payroll works</button>
        </div>
    </div>

    @if(session('error'))<div class="pp-callout red" style="margin-bottom:1rem"><i class="bi bi-exclamation-octagon"></i><div>{{ session('error') }}</div></div>@endif
    @if($errors->any())<div class="pp-callout red" style="margin-bottom:1rem"><i class="bi bi-exclamation-octagon"></i><div>{{ $errors->first() }}</div></div>@endif

    <form method="POST" action="{{ route('payroll.runs.store') }}" id="prStart">
        @csrf
        <input type="hidden" name="period" value="{{ $month->format('Y-m') }}">
        <div class="pr-layout">
            <div>
                {{-- 1 · Month --}}
                <section class="pr-card">
                    <div class="pr-card-head"><h2>1 · Which month?</h2></div>
                    <div class="pr-months">
                        @foreach($months as $m)
                            <label class="pr-month-opt">
                                <input type="radio" name="_month" value="{{ $m['value'] }}" @checked($m['value'] === $month->format('Y-m'))
                                       onchange="location.href='{{ route('payroll.runs.create') }}?month={{ $m['value'] }}'">
                                <span>{{ $m['label'] }}<small>{{ $m['note'] }}</small></span>
                            </label>
                        @endforeach
                    </div>

                    @if($inMonth->isNotEmpty())
                        <div class="pr-inmonth">
                            <div class="pr-inmonth-head">Pay runs in {{ $month->format('F Y') }} <span>{{ $inMonth->count() }}</span></div>
                            @foreach($inMonth as $r)
                                <div class="pr-inmonth-row {{ $continue && $continue->id === $r->id ? 'on' : '' }}">
                                    <span class="pr-pill {{ $r->statusTone() }}">{{ $r->statusLabel() }}</span>
                                    <div class="t"><b>{{ $r->displayName() }}</b><small>{{ $r->typeLabel() }} · {{ $r->coverageSummary() }} · {{ $r->payrolls_count }} people · {{ $k($r->net_pay) }}</small></div>
                                    @if($r->isEditable())
                                        @if($continue && $continue->id === $r->id)
                                            <span class="pr-tag">Recalculating this one</span>
                                        @else
                                            <a class="pp-btn pp-btn-ghost pp-btn-sm" href="{{ route('payroll.runs.create', ['month' => $month->format('Y-m'), 'run' => $r->id]) }}">Recalculate</a>
                                        @endif
                                    @endif
                                    <a class="pp-btn pp-btn-ghost pp-btn-sm" href="{{ route('payroll.runs.show', $r) }}">Open</a>
                                </div>
                            @endforeach
                            <div class="pr-inmonth-new">
                                @if($continue)
                                    <a class="pp-link" href="{{ route('payroll.runs.create', ['month' => $month->format('Y-m'), 'new' => 1]) }}"><i class="bi bi-plus-circle"></i> Start a separate new pay run for {{ $mName }} instead</a>
                                @else
                                    <i class="bi bi-plus-circle-fill" style="color:var(--zp-green)"></i> You're starting a <b>new, separate</b> pay run for {{ $mName }}. The runs above stay as they are.
                                @endif
                            </div>
                        </div>
                    @endif
                    @if($continue)<input type="hidden" name="run_id" value="{{ $continue->id }}">@else<input type="hidden" name="new_run" value="1">@endif
                </section>

                {{-- 2 · Who --}}
                <section class="pr-card">
                    <div class="pr-card-head"><h2>2 · What kind of run, and who's in it?</h2></div>

                    <div class="pr-label">Type of run</div>
                    <div class="pr-chipsel" role="radiogroup">
                        @foreach($types as $v => $label)
                            <label><input type="radio" name="run_type" value="{{ $v }}" @checked($oldType === $v)><span>{{ $label }}</span></label>
                        @endforeach
                    </div>

                    <div class="pr-label" style="margin-top:1.1rem">Who's being paid</div>
                    <div class="pr-seg" role="radiogroup">
                        <label><input type="radio" name="coverage" value="all" @checked($oldCoverage === 'all')><span><i class="bi bi-people"></i> Everyone<small>{{ $employees->count() }} active</small></span></label>
                        <label><input type="radio" name="coverage" value="departments" @checked($oldCoverage === 'departments')><span><i class="bi bi-diagram-3"></i> Departments<small>{{ $departments->count() }} teams</small></span></label>
                        <label><input type="radio" name="coverage" value="people" @checked($oldCoverage === 'people')><span><i class="bi bi-person-check"></i> Pick people<small>e.g. a bonus run</small></span></label>
                    </div>

                    <div class="pr-cov" data-cov="departments" hidden>
                        <div class="pr-deps">
                            @foreach($departments as $dep => $n)
                                <label><input type="checkbox" name="departments[]" value="{{ $dep === 'No department' ? '__none__' : $dep }}" @checked(in_array($dep === 'No department' ? '__none__' : $dep, $oldDeps, true))><span>{{ $dep }} <small>{{ $n }}</small></span></label>
                            @endforeach
                        </div>
                    </div>

                    <div class="pr-cov" data-cov="people" hidden>
                        <div class="pr-picker">
                            <div class="pr-picker-bar">
                                <i class="bi bi-search"></i><input type="search" id="prPickQ" placeholder="Search name, ID or department">
                                <span class="pp-muted" id="prPickN">0 picked</span>
                                <button type="button" class="pp-link" id="prPickAll">Select shown</button>
                                <button type="button" class="pp-link" id="prPickNone">Clear</button>
                            </div>
                            <div class="pr-picker-list">
                                @foreach($employees as $e)
                                    <label data-q="{{ strtolower($e->first_name . ' ' . $e->last_name . ' ' . $e->employee_id . ' ' . $e->department) }}">
                                        <input type="checkbox" name="employees[]" value="{{ $e->id }}" @checked(in_array($e->id, $oldPeople, true))>
                                        <span class="av">{{ strtoupper(substr($e->first_name, 0, 1) . substr($e->last_name, 0, 1)) }}</span>
                                        <span class="t"><b>{{ $e->first_name }} {{ $e->last_name }}</b><small>{{ $e->employee_id }}{{ $e->department ? ' · ' . $e->department : '' }}</small></span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    @if($alreadyPaid)
                        <div class="pp-callout" style="margin-top:1rem"><i class="bi bi-layers"></i>
                            <div>{{ $alreadyPaid }} {{ $alreadyPaid === 1 ? 'person is' : 'people are' }} already in an approved {{ $mName }} run. A second run is taxed on its own figures —
                                the NAPSA, NHIMA and ZRA returns can add all of {{ $mName }}'s runs together so you can check the totals before you file.</div></div>
                    @endif
                </section>

                {{-- 3 · Preview --}}
                <section class="pr-card">
                    <div class="pr-card-head"><h2>3 · What we found for {{ $mName }}</h2><span class="sub">everyone active, before calculating</span></div>
                    <div class="pr-pre">
                        <div><span>Active people</span><b>{{ $preview['people'] }}</b></div>
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

                {{-- 4 · Options --}}
                <section class="pr-card">
                    <div class="pr-card-head"><h2>4 · Options</h2></div>
                    <label class="pr-switch" style="margin-bottom:1rem">
                        <input type="hidden" name="include_overtime" value="0">
                        <input type="checkbox" name="include_overtime" value="1" @checked($continue ? $continue->include_overtime : true)>
                        Add approved overtime
                        <span class="pp-muted" style="font-weight:500">— {{ $preview['overtime'] }} people · {{ $k($preview['overtime_amt']) }} (each claim is only ever paid once)</span>
                    </label>
                    <div class="pp-field" style="max-width:420px">
                        <label for="alias">Name <small>optional</small></label>
                        <input class="pp-input" id="alias" name="alias" placeholder="{{ $continue?->displayName() ?? ($month->format('F Y') . ($inMonth->isEmpty() ? ' payroll' : ' · Supplementary #' . ($inMonth->count() + 1))) }}" value="{{ old('alias') }}">
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
                    <p class="pp-muted" style="font-size:.8rem;margin:.8rem 0 0">Nothing is final until the last approver signs. You can recalculate as often as you like before you submit — and an approver can unlock it later if something was missed.</p>
                </div>
                <div class="pr-card pr-summary" id="prSummary">
                    <span class="pp-muted" style="font-size:.75rem;font-weight:600">THIS PAY RUN</span>
                    <b id="prSumWho">Everyone active</b>
                    <small id="prSumType"></small>
                </div>
                <button type="submit" class="pp-btn pp-btn-primary" id="prGo" style="width:100%;padding:1rem">
                    <i class="bi bi-calculator"></i> <span>{{ $continue ? 'Recalculate' : 'Calculate' }} {{ $mName }} payroll</span>
                </button>
            </aside>
        </div>
    </form>
</div>
@include('dashboard.payroll.flow._walkthrough')
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('prStart');
    const covs = form.querySelectorAll('.pr-cov');
    const sumWho = document.getElementById('prSumWho'), sumType = document.getElementById('prSumType');
    const people = [...form.querySelectorAll('.pr-picker-list label')];
    const total = {{ $employees->count() }};

    function refresh() {
        const mode = form.querySelector('[name=coverage]:checked')?.value || 'all';
        covs.forEach(c => c.hidden = c.dataset.cov !== mode);
        const type = form.querySelector('[name=run_type]:checked');
        sumType.textContent = type ? type.nextElementSibling.textContent : '';
        const picked = people.filter(l => l.querySelector('input').checked).length;
        document.getElementById('prPickN').textContent = picked + ' picked';
        if (mode === 'all') sumWho.textContent = 'Everyone active · ' + total;
        if (mode === 'departments') {
            const d = [...form.querySelectorAll('[name="departments[]"]:checked')].map(i => i.nextElementSibling.firstChild.textContent.trim());
            sumWho.textContent = d.length ? d.join(', ') : 'Pick at least one department';
        }
        if (mode === 'people') sumWho.textContent = picked ? picked + (picked === 1 ? ' person' : ' people') : 'Pick at least one person';
    }
    form.addEventListener('change', refresh);
    document.getElementById('prPickQ').addEventListener('input', function () {
        const q = this.value.trim().toLowerCase();
        people.forEach(l => l.hidden = q && ! l.dataset.q.includes(q));
    });
    document.getElementById('prPickAll').onclick = () => { people.filter(l => ! l.hidden).forEach(l => l.querySelector('input').checked = true); refresh(); };
    document.getElementById('prPickNone').onclick = () => { people.forEach(l => l.querySelector('input').checked = false); refresh(); };
    refresh();

    form.addEventListener('submit', function () {
        const b = document.getElementById('prGo');
        b.disabled = true;
        b.querySelector('span').textContent = 'Calculating everyone…';
        b.querySelector('i').className = 'pp-busy';
    });
})();
</script>
@endpush
