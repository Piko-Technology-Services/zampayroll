@extends('layouts.app')

@section('title', 'Overtime · ZamPayroll')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/zp-people.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/zp-payroll.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/zp-requests.css') }}">
@endpush

@php
    $u = auth()->user();
    $h = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
    $k = fn ($n) => 'K ' . number_format((float) $n, 2);
    $tabs = ['pending' => 'Waiting', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled', 'all' => 'All'];
@endphp

@section('content')
<div class="pr pp rq">
    <div class="pr-head">
        <div>
            <h1>Overtime</h1>
            <p>Approved overtime goes into the next payroll automatically, once. Late approvals are picked up the month after.</p>
        </div>
        <div class="pr-head-actions">
            <a href="{{ route('overtime.report') }}" class="pp-btn pp-btn-ghost"><i class="bi bi-bar-chart"></i> Monthly report</a>
            @if($isHr)<a href="{{ route('overtime.settings') }}" class="pp-btn pp-btn-ghost"><i class="bi bi-sliders"></i> Rates &amp; rules</a>@endif
            <button type="button" class="pp-btn pp-btn-primary" data-rq-open="rqOt"><i class="bi bi-plus-lg"></i> Record overtime</button>
        </div>
    </div>

    <div class="rq-kpis">
        <div class="rq-kpi main"><span>Waiting for a decision</span><b>{{ $counts['pending'] }}</b></div>
        <div class="rq-kpi"><span>Approved hours · {{ now()->format('F') }}</span><b>{{ $h($kpi['hours']) }}</b></div>
        <div class="rq-kpi"><span>Approved amount · {{ now()->format('F') }}</span><b>{{ $k($kpi['amount']) }}</b></div>
        <div class="rq-kpi"><span>Approved, not yet in a payroll</span><b>{{ $k($kpi['unpaid']) }}</b></div>
    </div>

    <div class="rq-bar">
        <nav class="rq-tabs">
            @foreach($tabs as $key => $label)
                <a href="{{ route('overtime.dashboard', array_filter(['status' => $key, 'q' => request('q'), 'month' => request('month')])) }}" class="rq-tab {{ $status === $key ? 'on' : '' }}">
                    {{ $label }} @isset($counts[$key])<span class="n">{{ $counts[$key] }}</span>@endisset
                </a>
            @endforeach
        </nav>
        <form class="rq-filters" method="GET">
            <input type="hidden" name="status" value="{{ $status }}">
            <input class="pp-input" type="search" name="q" value="{{ request('q') }}" placeholder="Search a name or number">
            <input class="pp-input" type="month" name="month" value="{{ request('month') }}" onchange="this.form.submit()">
        </form>
    </div>

    <div class="rq-list">
        @forelse($requests as $ot)
            @php
                $emp = $ot->employee;
                $decide = $service->canDecide($u, $ot);
                $cancel = $service->canCancel($u, $ot);
            @endphp
            <article class="rq-item {{ $decide ? 'attn' : '' }}">
                <div class="rq-av">{{ $emp?->initials }}</div>
                <div class="rq-main">
                    <div class="rq-who">
                        <b>{{ $emp?->full_name ?? 'Former employee' }}</b>
                        <small>{{ $emp?->employee_id }}{{ $emp?->department ? ' · ' . $emp->department : '' }}</small>
                        <span class="rq-type">{{ $types[$ot->type]['label'] ?? $ot->typeLabel() }} · {{ $h($ot->rate_multiplier) }}×</span>
                    </div>
                    <div class="rq-what">
                        <span><i class="bi bi-calendar-event"></i> {{ $ot->date->format('D j M Y') }}
                            @if($emp && $ot->status === 'pending' && ! (new \App\Services\LeaveDayCalculator($emp))->isWorkingDay($ot->date))<span class="rq-type" style="background:var(--zp-copper-50);color:#9a5a17">Rest day / holiday</span>@endif
                        </span>
                        <span><i class="bi bi-clock"></i> {{ substr($ot->start_time, 0, 5) }}–{{ substr($ot->end_time, 0, 5) }}{{ $ot->break_minutes ? ' less ' . $ot->break_minutes . ' min' : '' }}</span>
                        <span><i class="bi bi-hourglass-split"></i> <b>{{ $h($ot->hours) }}</b> h{{ $ot->requested_hours ? ' (asked ' . $h($ot->requested_hours) . ')' : '' }}</span>
                        <span><i class="bi bi-cash"></i> <b>{{ $k($ot->amount) }}</b></span>
                    </div>
                    @if($ot->comment)<div class="rq-note">“{{ $ot->comment }}”</div>@endif
                    <div class="rq-trail">
                        <span><i class="bi bi-send"></i>Logged {{ $ot->created_at->diffForHumans() }}{{ $ot->recorded_by ? ' (entered by HR)' : '' }}</span>
                        @if($ot->reviewed_at && in_array($ot->status, ['approved', 'rejected'], true))<span><i class="bi bi-check2-circle"></i>{{ ucfirst($ot->status) }} by {{ $ot->reviewedBy?->name }} · {{ $ot->reviewed_at->format('j M') }}</span>@endif
                        @if($ot->hr_comment)<span><i class="bi bi-chat-left-text"></i>{{ $ot->hr_comment }}</span>@endif
                        @if($ot->payrollRun)<span><i class="bi bi-wallet2"></i>In {{ $ot->payrollRun->displayName() }} ({{ strtolower($ot->payrollRun->statusLabel()) }})</span>@elseif($ot->status === 'approved')<span><i class="bi bi-hourglass"></i>Goes into the next payroll</span>@endif
                        @if($ot->status === 'cancelled')<span><i class="bi bi-x-circle"></i>Cancelled by {{ $ot->cancelledBy?->name ?? '—' }}{{ $ot->cancel_reason ? ' — ' . $ot->cancel_reason : '' }}</span>@endif
                    </div>
                </div>
                <div class="rq-side">
                    <span class="rq-pill {{ $ot->status }}">{{ $ot->statusLabel() }}</span>
                    @if($decide)
                        <form method="POST" action="{{ route('overtime.approve', $ot) }}" class="rq-decide">
                            @csrf
                            <input class="pp-input" type="number" name="hours" step="0.25" min="0.25" max="{{ (float) $ot->hours }}" placeholder="{{ $h($ot->hours) }} h" title="Approve fewer hours (optional)" style="width:80px">
                            <input class="pp-input" type="text" name="comment" maxlength="500" placeholder="Comment (optional)" style="width:150px">
                            <button class="pp-btn pp-btn-sm rq-btn-no" formaction="{{ route('overtime.reject', $ot) }}"><i class="bi bi-x-lg"></i> Reject</button>
                            <button class="pp-btn pp-btn-sm pp-btn-primary"><i class="bi bi-check-lg"></i> Approve</button>
                        </form>
                    @endif
                    @if($cancel)
                        <details class="rq-more">
                            <summary><i class="bi bi-x-circle"></i> Cancel</summary>
                            <form method="POST" action="{{ route('overtime.cancel', $ot) }}">
                                @csrf
                                <input class="pp-input" type="text" name="reason" maxlength="500" placeholder="Reason" style="width:170px;padding:.45rem .6rem;font-size:.8rem">
                                <button class="pp-btn pp-btn-sm rq-btn-no">Cancel it</button>
                            </form>
                        </details>
                    @endif
                </div>
            </article>
        @empty
            <div class="rq-empty"><i class="bi bi-alarm"></i>{{ $status === 'pending' ? 'No overtime waiting for approval.' : 'Nothing here.' }}</div>
        @endforelse
    </div>
    <div style="margin-top:1rem">{{ $requests->links() }}</div>
</div>
@endsection

@push('modals')
<div class="rq-modal-bg" id="rqOt" data-check="{{ route('overtime.check') }}">
    <form class="rq-modal" method="POST" action="{{ route('overtime.record') }}">
        @csrf
        <h2 class="zp-display">Record overtime</h2>
        <div class="sub">For a supervisor or HR entering hours worked by someone in the team.</div>
        <div class="pp-field">
            <label for="otEmp">Employee</label>
            <select class="pp-select" name="employee_id" id="otEmp" required>
                <option value="">Choose…</option>
                @foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->first_name }} {{ $e->last_name }} · {{ $e->employee_id }}</option>@endforeach
            </select>
        </div>
        <div class="rq-grid2">
            <div class="pp-field"><label for="otDate">Date worked</label><input class="pp-input" type="date" name="date" id="otDate" value="{{ now()->toDateString() }}" required></div>
            <div class="pp-field"><label for="otType">Rate</label>
                <select class="pp-select" name="type" id="otType">@foreach($types as $key => $t)<option value="{{ $key }}">{{ $t['label'] }} ({{ $h($t['multiplier']) }}×)</option>@endforeach</select>
            </div>
            <div class="pp-field"><label for="otStart">From</label><input class="pp-input" type="time" name="start_time" id="otStart" value="17:00" required></div>
            <div class="pp-field"><label for="otEnd">To</label><input class="pp-input" type="time" name="end_time" id="otEnd" value="19:00" required></div>
        </div>
        <div class="pp-field"><label for="otBreak">Breaks <small>(minutes, not paid)</small></label><input class="pp-input" type="number" min="0" max="720" step="5" name="break_minutes" id="otBreak" value="0"></div>
        <div class="rq-calc" id="otCalc" hidden></div>
        <div class="pp-field"><label for="otWhy">What was it for?</label><input class="pp-input" type="text" name="comment" id="otWhy" maxlength="1000" placeholder="e.g. Month-end stock count"></div>
        <label class="rq-check"><input type="checkbox" name="approve_now" value="1" checked> Approve it now</label>
        <div class="rq-modal-actions">
            <button type="button" class="pp-btn pp-btn-ghost" data-rq-close>Close</button>
            <button class="pp-btn pp-btn-primary"><i class="bi bi-check-lg"></i> Save overtime</button>
        </div>
    </form>
</div>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-rq-open]').forEach(b => b.addEventListener('click', () => document.getElementById(b.dataset.rqOpen).classList.add('open')));
    document.querySelectorAll('.rq-modal-bg').forEach(bg => bg.addEventListener('click', e => { if (e.target === bg || e.target.closest('[data-rq-close]')) bg.classList.remove('open'); }));
    const m = document.getElementById('rqOt'), f = id => document.getElementById(id), out = f('otCalc');
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    async function calc() {
        if (!f('otEmp').value) { out.hidden = true; return; }
        const q = new URLSearchParams({ employee_id: f('otEmp').value, date: f('otDate').value, start_time: f('otStart').value, end_time: f('otEnd').value, break_minutes: f('otBreak').value || 0, type: f('otType').value });
        const r = await fetch(m.dataset.check + '?' + q, { headers: { Accept: 'application/json' } });
        if (!r.ok) return;
        const d = await r.json();
        out.hidden = false; out.classList.toggle('bad', !d.ok);
        out.innerHTML = d.ok
            ? '<span>Hours<b>' + d.hours + '</b></span><span>Rate<b style="font-size:1rem">K ' + d.hourly_rate.toFixed(2) + '/h</b></span><span>Amount<b>K ' + d.amount.toLocaleString('en-ZM', { minimumFractionDigits: 2 }) + '</b></span>'
              + (d.warnings.length ? '<span style="flex-basis:100%;color:#9a5a17"><i class="bi bi-info-circle"></i> ' + esc(d.warnings.join(' ')) + '</span>' : '')
            : '<span><i class="bi bi-exclamation-triangle"></i> ' + esc(d.message) + '</span>';
        out.style.flexWrap = 'wrap';
    }
    ['otEmp', 'otDate', 'otType', 'otStart', 'otEnd', 'otBreak'].forEach(id => f(id).addEventListener('change', calc));
});
</script>
@endpush
