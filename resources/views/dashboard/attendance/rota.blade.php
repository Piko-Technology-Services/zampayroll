@extends('layouts.app')

@section('title', 'Shift rota · ZamPayroll')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/zp-people.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/zp-rota.css') }}">
@endpush

@php
    $active = $shifts->where('active', true)->values();
    $byId = $shifts->keyBy('id');
    $prev = $start->copy()->subWeek()->toDateString();
    $next = $start->copy()->addWeek()->toDateString();
    $label = $start->format('j M') . ' – ' . $days->last()->format('j M Y');
    $grouped = $rows->groupBy('dept');
    $chip = function ($v) use ($byId) {
        if ($v === 'off') return ['Off', 'Day off', '#8a8f8c', true];
        $s = $byId->get($v);
        return $s ? [$s->code, $s->times(), $s->color, false] : null;
    };
@endphp

@section('content')
<div class="rt pp" id="rota" data-save="{{ route('attendance.rota.save') }}" data-edit="{{ $canEdit ? 1 : 0 }}">
    <div class="rt-head">
        <div>
            <h1>Shift rota</h1>
            <p>{{ $isHr ? 'Everyone in the company' : 'The people you look after' }} · {{ $rows->count() }} {{ \Illuminate\Support\Str::plural('person', $rows->count()) }}
                @if($canEdit)· <span class="rt-saved" id="rtSaved" aria-live="polite"><i class="bi bi-cloud-check"></i> All changes saved</span>@endif</p>
        </div>
        <div class="rt-week">
            <a class="pp-btn pp-btn-ghost pp-btn-sm" href="{{ route('attendance.rota', ['week' => $prev]) }}" aria-label="Previous week"><i class="bi bi-chevron-left"></i></a>
            <form method="GET" action="{{ route('attendance.rota') }}" class="rt-week-pick">
                <label for="rtWeek" class="sr-only">Week</label>
                <b>{{ $label }}</b>
                <input type="date" id="rtWeek" name="week" value="{{ $start->toDateString() }}" onchange="this.form.submit()" aria-label="Jump to week">
            </form>
            <a class="pp-btn pp-btn-ghost pp-btn-sm" href="{{ route('attendance.rota', ['week' => $next]) }}" aria-label="Next week"><i class="bi bi-chevron-right"></i></a>
            @unless($days->contains(fn ($d) => $d->isToday()))
                <a class="pp-btn pp-btn-ghost pp-btn-sm" href="{{ route('attendance.rota') }}">This week</a>
            @endunless
        </div>
    </div>

    @if(session('success'))<div class="pp-callout green" style="margin:0 0 1rem"><i class="bi bi-check-circle"></i><div>{{ session('success') }}</div></div>@endif
    @if(session('error'))<div class="pp-callout" style="margin:0 0 1rem"><i class="bi bi-info-circle"></i><div>{{ session('error') }}</div></div>@endif

    @if($shifts->isEmpty())
        <div class="rt-empty">
            <i class="bi bi-calendar-week"></i>
            <h2>No shifts set up yet</h2>
            <p>{{ $canDefine ? 'Start by adding the shifts your company works — e.g. Morning 06:00–14:00, Afternoon 14:00–22:00, Night 22:00–06:00.' : 'Ask your administrator to add the company\'s shifts first.' }}</p>
            @if($canDefine)<a class="pp-btn pp-btn-primary" href="{{ route('attendance.shifts') }}"><i class="bi bi-plus-lg"></i> Add shifts</a>@endif
        </div>
    @else

    {{-- Toolbar: brush palette + filters + actions --}}
    <div class="rt-tools">
        @if($canEdit)
            <div class="rt-brushes" role="radiogroup" aria-label="Shift to paint">
                <span class="rt-tools-lbl">Paint</span>
                @foreach($active as $i => $s)
                    <button type="button" class="rt-brush {{ $i === 0 ? 'on' : '' }}" data-brush="{{ $s->id }}" role="radio" aria-checked="{{ $i === 0 ? 'true' : 'false' }}" style="--c:{{ $s->color }}" title="{{ $s->name }} · {{ $s->times() }} · {{ rtrim(rtrim(number_format($s->hours(), 2), '0'), '.') }}h">
                        <i>{{ $s->code }}</i><span>{{ $s->name }}<small>{{ $s->times() }}</small></span>
                    </button>
                @endforeach
                <button type="button" class="rt-brush off" data-brush="off" role="radio" aria-checked="false" style="--c:#8a8f8c"><i>Off</i><span>Day off<small>rest day</small></span></button>
                <button type="button" class="rt-brush erase" data-brush="" role="radio" aria-checked="false" style="--c:#c9cfcb"><i><i class="bi bi-eraser"></i></i><span>Clear<small>unscheduled</small></span></button>
            </div>
        @endif
        <div class="rt-filters">
            <div class="rt-search"><i class="bi bi-search"></i><input type="search" id="rtQ" placeholder="Find a person" aria-label="Find a person"></div>
            @if($departments->count() > 1)
                <select id="rtDept" class="rt-select" aria-label="Department">
                    <option value="">All departments</option>
                    @foreach($departments as $d)<option>{{ $d }}</option>@endforeach
                </select>
            @endif
            @if($canEdit)
                <div class="rt-more">
                    <button type="button" class="pp-btn pp-btn-ghost pp-btn-sm" id="rtMoreBtn" aria-expanded="false"><i class="bi bi-magic"></i> Quick fill <i class="bi bi-chevron-down"></i></button>
                    <div class="rt-menu" id="rtMenu" hidden>
                        <form method="POST" action="{{ route('attendance.rota.copy') }}">@csrf
                            <input type="hidden" name="week" value="{{ $start->toDateString() }}"><input type="hidden" name="mode" value="last_week">
                            <button><i class="bi bi-arrow-down-left-square"></i><span>Copy last week<small>Fills empty days only</small></span></button>
                        </form>
                        <form method="POST" action="{{ route('attendance.rota.copy') }}" class="rt-menu-repeat">@csrf
                            <input type="hidden" name="week" value="{{ $start->toDateString() }}"><input type="hidden" name="mode" value="repeat">
                            <button><i class="bi bi-arrow-repeat"></i><span>Repeat this week<small>for the next <select name="weeks" onclick="event.stopPropagation()">@foreach([1, 2, 3, 4, 6, 8, 12] as $w)<option value="{{ $w }}" @selected($w === 4)>{{ $w }}</option>@endforeach</select> weeks</small></span></button>
                        </form>
                        <button type="button" data-fill="weekdays"><i class="bi bi-calendar2-range"></i><span>Paint working days<small>for everyone shown, empty days only</small></span></button>
                        <button type="button" data-fill="clear"><i class="bi bi-eraser"></i><span>Clear this week<small>for everyone shown</small></span></button>
                    </div>
                </div>
            @endif
            <button type="button" class="pp-btn pp-btn-ghost pp-btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
            @if($canDefine)<a class="pp-btn pp-btn-ghost pp-btn-sm" href="{{ route('attendance.shifts') }}"><i class="bi bi-gear"></i> Shifts</a>@endif
        </div>
    </div>
    @if($canEdit)<p class="rt-hint"><i class="bi bi-hand-index"></i> Pick a shift above, then click or drag across days to paint it. Changes save automatically.</p>@endif

    {{-- Grid --}}
    <div class="rt-print">
        <div class="rt-print-title">Shift rota · {{ $label }}</div>
        <div class="rt-scroll">
            <table class="rt-grid" id="rtGrid">
                <thead>
                    <tr>
                        <th class="who" scope="col">Person</th>
                        @foreach($days as $d)
                            @php $ds = $d->toDateString(); $hol = $holidays[$ds] ?? null; @endphp
                            <th scope="col" class="{{ $ds === $today ? 'today' : '' }} {{ in_array($d->dayOfWeekIso, $workDays, true) && ! $hol ? '' : 'rest' }}" data-date="{{ $ds }}">
                                <span>{{ $d->format('D') }}</span><b>{{ $d->format('j') }}</b>
                                @if($hol)<em title="{{ $hol }}">{{ \Illuminate\Support\Str::limit($hol, 12) }}</em>@endif
                            </th>
                        @endforeach
                        <th class="hrs" scope="col">Hours</th>
                    </tr>
                </thead>
                @foreach($grouped as $dept => $people)
                    <tbody data-dept="{{ $dept }}">
                        @if($grouped->count() > 1)<tr class="grp"><th colspan="{{ $days->count() + 2 }}">{{ $dept }} <small>{{ $people->count() }}</small></th></tr>@endif
                        @foreach($people as $p)
                            <tr data-emp="{{ $p['id'] }}" data-q="{{ strtolower($p['name'] . ' ' . $p['role']) }}">
                                <th class="who" scope="row"><span class="av">{{ $p['ini'] }}</span><span class="t"><b>{{ $p['name'] }}</b><small>{{ $p['role'] ?: $dept }}</small></span></th>
                                @foreach($days as $d)
                                    @php $ds = $d->toDateString(); $c = $p['cells'][$ds]; $ch = $c['v'] ? $chip($c['v']) : null; @endphp
                                    <td class="{{ $c['leave'] ? 'leave' : '' }} {{ in_array($d->dayOfWeekIso, $workDays, true) && ! isset($holidays[$ds]) ? '' : 'rest' }} {{ $ds === $today ? 'today' : '' }}"
                                        data-date="{{ $ds }}" data-v="{{ $c['v'] }}" @if($c['leave']) title="On {{ strtolower($c['leave']) }}" @endif>
                                        @if($canEdit)<button type="button" class="cell" aria-label="{{ $p['name'] }}, {{ $d->format('l j M') }}">@else<span class="cell">@endif
                                            @if($ch)<span class="chip {{ $ch[3] ? 'off' : '' }}" style="--c:{{ $ch[2] }}"><b>{{ $ch[0] }}</b><small>{{ $ch[1] }}</small></span>
                                            @elseif($c['leave'])<span class="lv">{{ $c['leave'] }}</span>@endif
                                        @if($canEdit)</button>@else</span>@endif
                                    </td>
                                @endforeach
                                <td class="hrs"><b>{{ rtrim(rtrim(number_format($p['hours'], 1), '0'), '.') ?: '0' }}</b>h</td>
                            </tr>
                        @endforeach
                    </tbody>
                @endforeach
                <tfoot>
                    <tr>
                        <th class="who" scope="row">On shift</th>
                        @foreach($days as $d)<td data-cover="{{ $d->toDateString() }}"></td>@endforeach
                        <td class="hrs" id="rtTotalHrs"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="rt-legend">
            @foreach($active as $s)<span><i style="background:{{ $s->color }}">{{ $s->code }}</i>{{ $s->name }} {{ $s->times() }}</span>@endforeach
            <span><i style="background:#8a8f8c">Off</i>Day off</span>
            <span><i class="lvk"></i>On approved leave</span>
        </div>
    </div>
    @if($rows->isEmpty())<div class="rt-empty" style="margin-top:1rem"><i class="bi bi-people"></i><p>No active people {{ $isHr ? 'yet' : 'report to you yet' }}.</p></div>@endif
    @endif
</div>

@php $shiftJson = $shifts->mapWithKeys(fn ($s) => [$s->id => $s->toCell()])->all() ?: new \stdClass(); @endphp
<script type="application/json" id="rtShifts">{!! json_encode($shiftJson, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const root = document.getElementById('rota');
    const grid = document.getElementById('rtGrid');
    if (! grid) return;
    const shifts = JSON.parse(document.getElementById('rtShifts').textContent || '{}');
    const canEdit = root.dataset.edit === '1';
    const saved = document.getElementById('rtSaved');
    const csrf = document.querySelector('meta[name=csrf-token]')?.content;
    let brush = document.querySelector('.rt-brush.on')?.dataset.brush ?? '';
    let painting = false, queue = new Map(), timer = null;

    const chipHtml = v => {
        if (v === 'off') return '<span class="chip off" style="--c:#8a8f8c"><b>Off</b><small>Day off</small></span>';
        const s = shifts[v]; if (! s) return '';
        const esc = t => String(t).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
        return `<span class="chip" style="--c:${esc(s.color)}"><b>${esc(s.code)}</b><small>${esc(s.times)}</small></span>`;
    };

    function totals() {
        let all = 0;
        grid.querySelectorAll('tbody tr[data-emp]').forEach(tr => {
            let h = 0;
            tr.querySelectorAll('td[data-date]').forEach(td => { const s = shifts[td.dataset.v]; if (s) h += s.hours; });
            tr.querySelector('td.hrs b').textContent = (Math.round(h * 10) / 10).toString();
            if (! tr.hidden) all += h;
        });
        document.getElementById('rtTotalHrs').innerHTML = '<b>' + (Math.round(all * 10) / 10) + '</b>h';
        grid.querySelectorAll('[data-cover]').forEach(td => {
            const counts = {};
            grid.querySelectorAll('tbody tr[data-emp]:not([hidden]) td[data-date="' + td.dataset.cover + '"]').forEach(c => { if (shifts[c.dataset.v]) counts[c.dataset.v] = (counts[c.dataset.v] || 0) + 1; });
            td.innerHTML = Object.keys(counts).length
                ? Object.entries(counts).map(([id, n]) => `<span style="--c:${shifts[id].color}" title="${shifts[id].name}">${shifts[id].code}<b>${n}</b></span>`).join('')
                : '<span class="none">—</span>';
        });
    }

    function setCell(td, v) {
        if ((td.dataset.v || '') === (v || '')) return;
        td.dataset.v = v || '';
        const cell = td.querySelector('.cell');
        const leave = td.getAttribute('title');
        cell.innerHTML = v ? chipHtml(v) : (leave ? '<span class="lv">' + leave.replace(/^On /, '') + '</span>' : '');
        cell.classList.remove('pop'); void cell.offsetWidth; cell.classList.add('pop');
        queue.set(td.closest('tr').dataset.emp + '|' + td.dataset.date, { employee_id: +td.closest('tr').dataset.emp, date: td.dataset.date, value: v || null });
        flush();
    }

    function flush() {
        clearTimeout(timer);
        if (saved) saved.innerHTML = '<i class="bi bi-arrow-repeat"></i> Saving…';
        timer = setTimeout(async () => {
            const cells = [...queue.values()]; queue.clear();
            if (! cells.length) return;
            try {
                const r = await fetch(root.dataset.save, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ cells }) });
                if (! r.ok) throw new Error((await r.json().catch(() => ({})))?.message || 'Could not save');
                saved.innerHTML = '<i class="bi bi-cloud-check"></i> All changes saved';
                saved.classList.remove('err');
            } catch (e) {
                saved.innerHTML = '<i class="bi bi-exclamation-triangle"></i> ' + e.message + ' — <a href="">reload</a>';
                saved.classList.add('err');
            }
        }, 450);
        totals();
    }

    if (canEdit) {
        document.querySelectorAll('.rt-brush').forEach(b => b.addEventListener('click', () => {
            document.querySelectorAll('.rt-brush').forEach(x => { x.classList.toggle('on', x === b); x.setAttribute('aria-checked', x === b ? 'true' : 'false'); });
            brush = b.dataset.brush;
        }));
        grid.addEventListener('pointerdown', e => {
            const td = e.target.closest('td[data-date]'); if (! td) return;
            if (e.pointerType === 'mouse') { e.preventDefault(); painting = true; }
            setCell(td, brush);
        });
        grid.addEventListener('pointerover', e => {
            if (! painting) return;
            const td = e.target.closest('td[data-date]'); if (td) setCell(td, brush);
        });
        document.addEventListener('pointerup', () => painting = false);
        grid.addEventListener('keydown', e => {
            if ((e.key === 'Enter' || e.key === ' ') && e.target.closest('td[data-date]')) { e.preventDefault(); setCell(e.target.closest('td'), brush); }
        });

        const menuBtn = document.getElementById('rtMoreBtn'), menu = document.getElementById('rtMenu');
        menuBtn?.addEventListener('click', e => { e.stopPropagation(); menu.hidden = ! menu.hidden; menuBtn.setAttribute('aria-expanded', String(! menu.hidden)); });
        document.addEventListener('click', e => { if (menu && ! menu.contains(e.target)) { menu.hidden = true; menuBtn.setAttribute('aria-expanded', 'false'); } });
        menu?.querySelectorAll('[data-fill]').forEach(b => b.addEventListener('click', () => {
            const rows = grid.querySelectorAll('tbody tr[data-emp]:not([hidden])');
            if (b.dataset.fill === 'clear') {
                if (! confirm('Clear this week for the ' + rows.length + ' people shown?')) return;
                rows.forEach(tr => tr.querySelectorAll('td[data-date]').forEach(td => setCell(td, '')));
            } else {
                if (! brush || brush === 'off') { alert('Pick a shift to paint first.'); return; }
                rows.forEach(tr => tr.querySelectorAll('td[data-date]:not(.rest):not(.leave)').forEach(td => { if (! td.dataset.v) setCell(td, brush); }));
            }
            menu.hidden = true;
        }));
        window.addEventListener('beforeunload', e => { if (queue.size) { e.preventDefault(); e.returnValue = ''; } });
    }

    // Filters
    const q = document.getElementById('rtQ'), dept = document.getElementById('rtDept');
    function filter() {
        const term = (q.value || '').trim().toLowerCase(), d = dept ? dept.value : '';
        grid.querySelectorAll('tbody').forEach(tb => {
            let any = false;
            tb.querySelectorAll('tr[data-emp]').forEach(tr => {
                const show = (! d || tb.dataset.dept === d) && (! term || tr.dataset.q.includes(term));
                tr.hidden = ! show; any = any || show;
            });
            tb.hidden = ! any;
        });
        totals();
    }
    q.addEventListener('input', filter); dept?.addEventListener('change', filter);
    totals();
});
</script>
@endpush
