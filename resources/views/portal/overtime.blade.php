@extends('layouts.portal')
@section('title', 'Overtime')

@php
    $fmtH = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
    $items = $requests->map(fn ($r) => [
        'title' => $r->typeLabel() . ' — K' . number_format((float) $r->amount, 2),
        'detail' => $r->date->format('D d M Y') . ', ' . substr($r->start_time, 0, 5) . '–' . substr($r->end_time, 0, 5)
            . ($r->break_minutes ? ' less ' . $r->break_minutes . ' min break' : '') . ' (' . $fmtH($r->hours) . ' hrs)'
            . ($r->payroll_run_id && $r->payrollRun ? ' · in ' . $r->payrollRun->displayName() : ''),
        'status' => $r->status,
        'label' => $r->statusLabel(),
        'note' => $r->hr_comment ?: $r->cancel_reason,
        'cancelUrl' => route('portal.overtime.cancel', $r),
    ]);
@endphp

@section('content')
<div class="pt-page-head">
    <div>
        <h1>Overtime</h1>
        <p>Log extra hours. Approved overtime is added to your next payroll.</p>
    </div>
    <div class="pt-stat">Approved this month<b>K{{ number_format($approvedThisMonth, 2) }}</b></div>
</div>

<div class="pt-grid">
    <div class="pt-card pt-span-5" id="apply">
        <h3>Log overtime</h3>
        <form method="POST" action="{{ route('portal.overtime.store') }}" novalidate>
            @csrf
            <div class="zp-field">
                <label class="zp-label" for="date">Date worked</label>
                <input class="zp-input @error('date') is-invalid @enderror" type="date" name="date" id="date" value="{{ old('date', now()->toDateString()) }}" required>
                @error('date')<div class="zp-error">{{ $message }}</div>@enderror
            </div>
            <div class="ob-grid">
                <div class="zp-field">
                    <label class="zp-label" for="start_time">From</label>
                    <input class="zp-input" type="time" name="start_time" id="start_time" value="{{ old('start_time', '17:00') }}" required>
                </div>
                <div class="zp-field">
                    <label class="zp-label" for="end_time">To</label>
                    <input class="zp-input @error('end_time') is-invalid @enderror" type="time" name="end_time" id="end_time" value="{{ old('end_time', '19:00') }}" required>
                    @error('end_time')<div class="zp-error">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="zp-field">
                <label class="zp-label" for="break_minutes">Breaks <small>(minutes, not paid)</small></label>
                <input class="zp-input @error('break_minutes') is-invalid @enderror" type="number" min="0" max="720" step="5" name="break_minutes" id="break_minutes" value="{{ old('break_minutes', 0) }}">
                @error('break_minutes')<div class="zp-error">{{ $message }}</div>@enderror
                <div class="zp-hint" id="otOvernight" hidden>Ends after midnight — counted as one shift.</div>
            </div>
            <div class="zp-field">
                <span class="zp-label">Rate</span>
                <div class="ob-tiles" style="grid-template-columns:repeat({{ min(3, count($types)) }}, 1fr)">
                    @foreach($types as $k => $t)
                        <div class="ob-tile">
                            <input type="radio" name="type" id="ot-{{ $k }}" value="{{ $k }}" data-mult="{{ $t['multiplier'] }}" @checked(old('type', array_key_first($types)) === $k)>
                            <label for="ot-{{ $k }}"><span class="t">{{ $t['label'] }} ({{ $fmtH($t['multiplier']) }}×)</span><span class="s">{{ $t['hint'] }}</span></label>
                        </div>
                    @endforeach
                </div>
                @error('type')<div class="zp-error">{{ $message }}</div>@enderror
            </div>

            <div class="pt-estimate">
                <span><span id="otHours">0</span> hrs × K{{ number_format($hourlyRate, 2) }}/hr × <span id="otMult">1.5</span></span>
                <b>K<span id="otAmount">0.00</span></b>
            </div>

            <div class="zp-field">
                <label class="zp-label" for="comment">What was it for? @unless($settings['require_reason'])<small>(optional)</small>@endunless</label>
                <textarea class="zp-textarea @error('comment') is-invalid @enderror" name="comment" id="comment" rows="3" placeholder="e.g. Month-end stock count">{{ old('comment') }}</textarea>
                @error('comment')<div class="zp-error">{{ $message }}</div>@enderror
                @error('start_time')<div class="zp-error">{{ $message }}</div>@enderror
            </div>
            <button class="zp-btn zp-btn-primary zp-btn-block" type="submit"><i class="bi bi-send"></i> Send for approval</button>
            <p class="zp-hint mt-2">The estimate uses your {{ $settings['base'] === 'gross' ? 'salary with regular allowances' : 'basic salary' }} ÷ {{ $employee->working_days_per_month }} working days ÷ {{ $fmtH($settings['hours_per_day']) }} hours. HR confirms the final amount.</p>
        </form>
    </div>

    <div class="pt-card pt-span-7">
        <h3>My overtime</h3>
        @include('portal._request-list', ['items' => $items, 'icon' => 'bi-alarm', 'empty' => 'No overtime logged yet.'])
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const rate = {{ $hourlyRate }};
    const st = document.getElementById('start_time'), et = document.getElementById('end_time');
    function upd() {
        let [a, b] = [st.value, et.value].map(v => { const [h, m] = (v || '0:0').split(':'); return +h * 60 + +m; });
        const overnight = st.value && et.value && b <= a;
        if (overnight) b += 1440;
        const brk = Math.max(0, +document.getElementById('break_minutes').value || 0);
        const hrs = Math.max(0, (b - a - brk) / 60);
        const mult = +(document.querySelector('input[name=type]:checked') || { dataset: { mult: 1 } }).dataset.mult;
        document.getElementById('otOvernight').hidden = !overnight;
        document.getElementById('otHours').textContent = hrs.toFixed(2).replace(/\.?0+$/, '');
        document.getElementById('otMult').textContent = mult;
        document.getElementById('otAmount').textContent = (rate * mult * hrs).toLocaleString('en-ZM', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    document.querySelectorAll('#start_time,#end_time,#break_minutes,input[name=type]').forEach(el => el.addEventListener('input', upd));
    upd();
})();
</script>
@endpush
