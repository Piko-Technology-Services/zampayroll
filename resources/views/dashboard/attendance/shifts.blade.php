@extends('layouts.app')

@section('title', 'Shifts · ZamPayroll')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/zp-people.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/zp-rota.css') }}">
@endpush

@php
    $mins = fn ($t) => ((int) substr($t, 0, 2)) * 60 + (int) substr($t, 3, 2);
    $hrs = fn ($s) => rtrim(rtrim(number_format($s->hours(), 2), '0'), '.');
@endphp

@section('content')
<div class="rt pp">
    <div class="rt-head">
        <div>
            <h1>Company shifts</h1>
            <p>The shifts your people can be scheduled on. HR and supervisors use these on the <a class="pp-link" href="{{ route('attendance.rota') }}">shift rota</a>.</p>
        </div>
        <a class="pp-btn pp-btn-ghost" href="{{ route('attendance.rota') }}"><i class="bi bi-calendar-week"></i> Open the rota</a>
    </div>

    @if(session('success'))<div class="pp-callout green" style="margin:0 0 1rem"><i class="bi bi-check-circle"></i><div>{{ session('success') }}</div></div>@endif
    @if($errors->any())<div class="pp-callout" style="margin:0 0 1rem"><i class="bi bi-exclamation-triangle"></i><div>{{ $errors->first() }}</div></div>@endif

    <form class="sh-form" method="POST" action="{{ route('attendance.shifts.store') }}" id="shForm" data-store="{{ route('attendance.shifts.store') }}">
        @csrf
        <input type="hidden" name="_method" value="POST" id="shMethod">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.8rem">
            <b id="shFormTitle" style="font-size:1rem">Add a shift</b>
            <button type="button" class="pp-link" id="shCancel" hidden style="background:none;border:0;cursor:pointer">Cancel editing</button>
        </div>
        <div class="grid">
            <div class="pp-field" style="margin:0"><label for="shName">Name</label><input class="pp-input" id="shName" name="name" required maxlength="60" placeholder="e.g. Morning" value="{{ old('name') }}"></div>
            <div class="pp-field" style="margin:0"><label for="shCode">Short code <small>optional</small></label><input class="pp-input" id="shCode" name="code" maxlength="4" placeholder="M" value="{{ old('code') }}"></div>
            <div class="pp-field" style="margin:0"><label for="shStart">Starts</label><input class="pp-input" type="time" id="shStart" name="start_time" required value="{{ old('start_time', '08:00') }}"></div>
            <div class="pp-field" style="margin:0"><label for="shEnd">Ends</label><input class="pp-input" type="time" id="shEnd" name="end_time" required value="{{ old('end_time', '17:00') }}"></div>
            <div class="pp-field" style="margin:0"><label for="shBreak">Unpaid break <small>min</small></label><input class="pp-input" type="number" min="0" max="240" step="5" id="shBreak" name="break_minutes" value="{{ old('break_minutes', 60) }}"></div>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:1rem;flex-wrap:wrap;margin-top:.9rem">
            <div>
                <div style="font-size:.8rem;font-weight:600">Colour</div>
                <div class="sh-swatches">
                    @foreach($palette as $i => $c)
                        <label><input type="radio" name="color" value="{{ $c }}" @checked(old('color', $palette[$shifts->count() % count($palette)]) === $c) aria-label="Colour {{ $i + 1 }}"><span style="--c:{{ $c }}"></span></label>
                    @endforeach
                </div>
            </div>
            <div style="display:flex;gap:.8rem;align-items:center">
                <span class="pp-muted" id="shLen" style="font-size:.85rem"></span>
                <label class="pr-switch" id="shActiveWrap" hidden style="font-size:.85rem;display:flex;gap:.35rem;align-items:center"><input type="hidden" name="active" value="0" disabled><input type="checkbox" name="active" value="1" checked disabled> In use</label>
                <button class="pp-btn pp-btn-primary"><i class="bi bi-check-lg"></i> <span id="shSubmit">Add shift</span></button>
            </div>
        </div>
    </form>

    <div style="margin:1.4rem 0 .7rem;display:flex;justify-content:space-between;align-items:center">
        <b>{{ $shifts->count() }} {{ \Illuminate\Support\Str::plural('shift', $shifts->count()) }}</b>
        @if($shifts->isEmpty())<span class="pp-muted" style="font-size:.85rem">Tip: most companies start with Morning, Afternoon and Night.</span>@endif
    </div>

    <div class="sh-list">
        @foreach($shifts as $s)
            @php
                $a = $mins($s->start_time); $b = $mins($s->end_time);
                $segments = $b > $a ? [[$a, $b]] : [[$a, 1440], [0, $b]];
            @endphp
            <article class="sh-card {{ $s->active ? '' : 'retired' }}" style="--c:{{ $s->color }}">
                <div class="sh-card-top">
                    <span class="sh-code">{{ $s->code }}</span>
                    <div><h3>{{ $s->name }}</h3><small class="pp-muted">{{ $s->active ? ($s->upcoming ? $s->upcoming . ' upcoming ' . \Illuminate\Support\Str::plural('day', $s->upcoming) . ' scheduled' : 'Not scheduled yet') : 'Retired' }}</small></div>
                </div>
                <div class="times">{{ $s->times() }}</div>
                <div class="meta"><span><i class="bi bi-clock"></i> {{ $hrs($s) }}h paid</span>@if($s->break_minutes)<span><i class="bi bi-cup-hot"></i> {{ $s->break_minutes }} min break</span>@endif @if($s->isOvernight())<span><i class="bi bi-moon-stars"></i> Overnight</span>@endif</div>
                <div class="sh-bar" aria-hidden="true">@foreach($segments as [$x, $y])<i style="left:{{ $x / 14.4 }}%;width:{{ ($y - $x) / 14.4 }}%"></i>@endforeach</div>
                <div class="sh-ticks" aria-hidden="true"><span>00</span><span>06</span><span>12</span><span>18</span><span>24</span></div>
                <div class="sh-actions">
                    <button type="button" class="pp-btn pp-btn-ghost pp-btn-sm" data-edit='{{ json_encode(['url' => route('attendance.shifts.update', $s), 'name' => $s->name, 'code' => $s->code, 'start' => substr($s->start_time, 0, 5), 'end' => substr($s->end_time, 0, 5), 'break' => $s->break_minutes, 'color' => $s->color, 'active' => $s->active], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) }}'><i class="bi bi-pencil"></i> Edit</button>
                    <form method="POST" action="{{ route('attendance.shifts.destroy', $s) }}" onsubmit="return confirm('{{ $s->upcoming ? 'This shift is on the rota. It will be retired and taken off future days. Continue?' : 'Delete this shift?' }}')">
                        @csrf @method('DELETE')
                        <button class="pp-btn pp-btn-ghost pp-btn-sm"><i class="bi bi-trash"></i> {{ $s->assignments()->exists() ? 'Retire' : 'Delete' }}</button>
                    </form>
                </div>
            </article>
        @endforeach
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const f = document.getElementById('shForm');
    const len = document.getElementById('shLen');
    function length() {
        const [a, b] = [f.start_time.value, f.end_time.value].map(t => { const [h, m] = (t || '0:0').split(':'); return (+h) * 60 + (+m); });
        let d = b - a; if (d <= 0) d += 1440;
        d -= +f.break_minutes.value || 0;
        len.textContent = (Math.max(0, d) / 60).toFixed(2).replace(/\.?0+$/, '') + 'h paid' + (b <= a ? ' · overnight' : '');
    }
    ['input', 'change'].forEach(ev => f.addEventListener(ev, length)); length();
    const activeWrap = document.getElementById('shActiveWrap');
    function setActiveInputs(on) { activeWrap.hidden = ! on; activeWrap.querySelectorAll('input').forEach(i => i.disabled = ! on); }
    document.querySelectorAll('[data-edit]').forEach(b => b.addEventListener('click', () => {
        const d = JSON.parse(b.dataset.edit);
        f.action = d.url; document.getElementById('shMethod').value = 'PUT';
        f.elements['name'].value = d.name; f.code.value = d.code; f.start_time.value = d.start; f.end_time.value = d.end; f.break_minutes.value = d.break;
        const c = f.querySelector('[name=color][value="' + d.color + '"]'); if (c) c.checked = true;
        setActiveInputs(true); activeWrap.querySelector('[type=checkbox]').checked = !! d.active;
        document.getElementById('shFormTitle').textContent = 'Edit ' + d.name;
        document.getElementById('shSubmit').textContent = 'Save changes';
        document.getElementById('shCancel').hidden = false;
        length(); f.scrollIntoView({ behavior: 'smooth', block: 'start' }); f.elements['name'].focus();
    }));
    document.getElementById('shCancel').addEventListener('click', () => {
        f.reset(); f.action = f.dataset.store; document.getElementById('shMethod').value = 'POST';
        setActiveInputs(false);
        document.getElementById('shFormTitle').textContent = 'Add a shift'; document.getElementById('shSubmit').textContent = 'Add shift';
        document.getElementById('shCancel').hidden = true; length();
    });
});
</script>
@endpush
