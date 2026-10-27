@extends('layouts.app')

@section('title', $meta['short'] . ' return · ' . ($period?->format('F Y') ?? $run->period))

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/zp-people.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/zp-payroll.css') }}">
<style>
.rt-top { display: flex; gap: .6rem; align-items: center; flex-wrap: wrap; margin-bottom: 1rem; }
.rt-seg { display: inline-flex; background: var(--zp-mist); border-radius: 12px; padding: 3px; }
.rt-seg a { padding: .45rem .8rem; border-radius: 9px; font-size: .84rem; font-weight: 600; color: var(--zp-ink-3); text-decoration: none; }
.rt-seg a.on { background: #fff; color: var(--zp-ink); box-shadow: 0 2px 8px -4px rgba(0,0,0,.25); }
.rt-chip { display: inline-flex; gap: .35rem; align-items: center; padding: .35rem .7rem; border-radius: 99px; font-size: .8rem; font-weight: 700; border: 1px solid var(--zp-line); background: #fff; cursor: pointer; }
.rt-chip.fix { color: var(--zp-red); border-color: #f3c2c1; background: #FBEAEA; }
.rt-chip.check { color: #9a5a17; border-color: #f1d6b9; background: var(--zp-copper-50); }
.rt-chip.ok { color: var(--zp-green); background: var(--zp-green-50); border-color: var(--zp-green-100); }
.rt-chip.on { box-shadow: 0 0 0 2px var(--zp-ink); }
.rt-wrap { background: #fff; border: 1px solid var(--zp-line); border-radius: 18px; overflow: auto; max-height: 64vh; }
.rt { border-collapse: separate; border-spacing: 0; width: max-content; min-width: 100%; font-size: .82rem; }
.rt th { position: sticky; top: 0; z-index: 2; background: #F7F9F7; text-align: left; font-size: .68rem; text-transform: uppercase; letter-spacing: .06em; color: var(--zp-ink-3); padding: .6rem .5rem; border-bottom: 1px solid var(--zp-line); white-space: nowrap; }
.rt th.num, .rt td.num input { text-align: right; }
.rt td { border-bottom: 1px solid #EEF2EF; padding: 0; position: relative; }
.rt td.who { position: sticky; left: 0; z-index: 1; background: #fff; padding: .45rem .7rem; font-weight: 600; white-space: nowrap; border-right: 1px solid var(--zp-line); min-width: 170px; max-width: 260px; }
.rt td.who small { white-space: normal; line-height: 1.3; }
.rt td.who small { display: block; color: var(--zp-ink-3); font-weight: 500; font-size: .72rem; }
.rt th.who { left: 0; z-index: 3; }
.rt input { width: 100%; min-width: 92px; border: 0; background: transparent; padding: .55rem .5rem; font: inherit; color: var(--zp-ink); outline: none; font-variant-numeric: tabular-nums; }
.rt input:focus { background: #fff; box-shadow: inset 0 0 0 2px var(--zp-green); border-radius: 6px; }
.rt td.edited input { background: var(--zp-copper-50); }
.rt td.edited::after { content: ""; position: absolute; top: 3px; right: 3px; width: 6px; height: 6px; border-radius: 50%; background: var(--zp-copper); }
.rt td.bad input { box-shadow: inset 0 -2px 0 var(--zp-red); }
.rt td.warn input { box-shadow: inset 0 -2px 0 var(--zp-copper); }
.rt td.act { width: 38px; text-align: center; }
.rt td.act button { border: 0; background: none; color: var(--zp-ink-3); cursor: pointer; padding: .4rem; border-radius: 8px; }
.rt td.act button:hover { color: var(--zp-red); background: #FBEAEA; }
.rt tr.gone td { opacity: .35; text-decoration: line-through; }
.rt tr.gone td.act { opacity: 1; text-decoration: none; }
.rt tfoot td { position: sticky; bottom: 0; background: #F7F9F7; font-weight: 700; padding: .55rem .5rem; border-top: 1px solid var(--zp-line); text-align: right; font-variant-numeric: tabular-nums; }
.rt tfoot td.who { text-align: left; }
.rt-issues { font-size: .72rem; color: var(--zp-ink-3); }
.rt-issues b { color: var(--zp-red); font-weight: 600; }
.rt-issues i { color: #9a5a17; font-style: normal; }
.rt-bar { position: sticky; bottom: 0; z-index: 5; margin-top: .9rem; display: flex; gap: .6rem; align-items: center; flex-wrap: wrap; background: rgba(255,255,255,.96); backdrop-filter: blur(8px); border: 1px solid var(--zp-line); border-radius: 18px; padding: .75rem 1rem; }
.rt-bar .grow { flex: 1; min-width: 220px; font-size: .86rem; color: var(--zp-ink-2); }
.rt-dirty { color: #9a5a17; font-weight: 700; }
</style>
@endpush

@php
    $label = $period?->format('F Y') ?? $run->period;
    $fix = $rows->sum(fn ($r) => collect($r['checks'] ?? [])->where('level', 'fix')->count());
    $chk = $rows->sum(fn ($r) => collect($r['checks'] ?? [])->where('level', 'check')->count());
@endphp

@section('content')
<div class="pr pp" id="rtPage"
     data-save="{{ route('payroll.reports.returns.save', [$run, $kind]) }}"
     data-scope="{{ $scope }}" data-csrf="{{ csrf_token() }}"
     data-cols="{{ json_encode(array_map(fn ($c) => ['label' => $c[0], 'type' => $c[1]], $columns)) }}">
    <a class="pr-crumb" href="{{ route('payroll.reports.index', ['run_id' => $run->id]) }}"><i class="bi bi-arrow-left"></i> Reports &amp; returns</a>
    <div class="pr-head">
        <div><h1>{{ $meta['label'] }}</h1>
            <p>{{ $label }} · {{ $rows->count() }} {{ \Illuminate\Support\Str::plural('line', $rows->count()) }} · from {{ $runs->map->displayName()->implode(' + ') }}</p></div>
        <div class="pr-head-actions">
            @foreach($otherKinds as $k => $m)
                <a class="pp-btn pp-btn-ghost pp-btn-sm" href="{{ route('payroll.reports.returns.preview', [$run, $k, 'scope' => $scope]) }}">{{ $m['short'] }}</a>
            @endforeach
        </div>
    </div>

    @if($saved)
        <div class="pp-callout" style="margin:0 0 1rem;background:var(--zp-copper-50)"><i class="bi bi-pencil-square"></i>
            <div style="flex:1"><b>You're looking at your corrected version</b> — saved {{ $saved->updated_at->diffForHumans() }}{{ $saved->editor ? ' by ' . $saved->editor->name : '' }}. Downloads use it. Edited cells are shaded; hover one to see the payroll figure.
                @if($missing->isNotEmpty())<br><b>Paid since you saved:</b> {{ $missing->implode(', ') }} — reset to include them, or add them as lines.@endif</div>
            <form method="POST" action="{{ route('payroll.reports.returns.reset', [$run, $kind, 'scope' => $scope]) }}" onsubmit="return confirm('Discard your edits and go back to the payroll figures?')">@csrf<button class="pp-btn pp-btn-ghost pp-btn-sm">Reset to payroll figures</button></form>
        </div>
    @endif

    <div class="rt-top">
        @if($monthRuns->count() > 1)
            <div class="rt-seg" role="group" aria-label="What this return covers">
                <a href="{{ route('payroll.reports.returns.preview', [$run, $kind, 'scope' => 'month']) }}" class="{{ $scope === 'month' ? 'on' : '' }}">Whole month ({{ $monthRuns->count() }} pay runs)</a>
                <a href="{{ route('payroll.reports.returns.preview', [$run, $kind, 'scope' => 'run']) }}" class="{{ $scope === 'run' ? 'on' : '' }}">This pay run only</a>
            </div>
        @endif
        <span class="rt-chip ok" data-filter=""><i class="bi bi-list"></i> All {{ $rows->count() }}</span>
        @if($fix)<span class="rt-chip fix" data-filter="fix"><i class="bi bi-exclamation-octagon"></i> {{ $fix }} to fix</span>@endif
        @if($chk)<span class="rt-chip check" data-filter="check"><i class="bi bi-exclamation-triangle"></i> {{ $chk }} to check</span>@endif
        @if(! $fix && ! $chk)<span class="rt-chip ok"><i class="bi bi-check2-circle"></i> Everything looks right</span>@endif
    </div>

    <div class="rt-wrap">
        <table class="rt" id="rtGrid">
            <thead><tr>
                <th class="who">Employee</th>
                @foreach($columns as $key => [$head, $type])
                    <th class="{{ $type === 'money' ? 'num' : '' }}" title="Column in the upload file: {{ $head ?: '(blank)' }}">{{ $head !== '' ? $head : 'type' }}</th>
                @endforeach
                <th></th>
            </tr></thead>
            <tbody>
            @foreach($rows as $r)
                @php $checks = collect($r['checks'] ?? []); $lvl = $checks->contains('level', 'fix') ? 'fix' : ($checks->isNotEmpty() ? 'check' : ''); @endphp
                <tr data-key="{{ $r['key'] }}" data-label="{{ $r['label'] }}" data-employee="{{ $r['employee_id'] ?? '' }}" data-level="{{ $lvl }}">
                    <td class="who">{{ $r['label'] }}
                        @if($checks->isNotEmpty())<small class="rt-issues">@foreach($checks as $c)@if($c['level'] === 'fix')<b>{{ $c['msg'] }}</b>@else<i>{{ $c['msg'] }}</i>@endif{{ $loop->last ? '' : ' · ' }}@endforeach</small>
                        @elseif(($r['runs'] ?? 1) > 1)<small>{{ $r['runs'] }} pay runs added up</small>@endif
                    </td>
                    @foreach($columns as $key => [$head, $type])
                        @php
                            $v = $r['cells'][$key] ?? '';
                            $orig = $r['original'][$key] ?? null;
                            $edited = $orig !== null && (string) ($type === 'money' ? number_format((float) $v, 2, '.', '') : $v) !== (string) ($type === 'money' ? number_format((float) $orig, 2, '.', '') : $orig);
                            $c = $checks->firstWhere('col', $key);
                        @endphp
                        <td class="{{ $type === 'money' ? 'num' : '' }} {{ $edited ? 'edited' : '' }} {{ $c ? ($c['level'] === 'fix' ? 'bad' : 'warn') : '' }}">
                            <input data-col="{{ $key }}" value="{{ $type === 'money' ? number_format((float) $v, 2, '.', '') : $v }}"
                                   data-orig="{{ $orig === null ? '' : ($type === 'money' ? number_format((float) $orig, 2, '.', '') : $orig) }}"
                                   @if($orig !== null) title="{{ $edited ? 'Payroll figure: ' . ($type === 'money' ? number_format((float) $orig, 2) : ($orig === '' ? '(blank)' : $orig)) : '' }}{{ $c ? ($edited ? ' · ' : '') . $c['msg'] : '' }}" @endif
                                   @if($type !== 'text') inputmode="decimal" @endif aria-label="{{ $r['label'] }} — {{ $head ?: 'type' }}">
                        </td>
                    @endforeach
                    <td class="act"><button type="button" data-remove title="Remove this line from the file" aria-label="Remove line"><i class="bi bi-x-lg"></i></button></td>
                </tr>
            @endforeach
            </tbody>
            <tfoot><tr>
                <td class="who">Total</td>
                @foreach($columns as $key => [$head, $type])<td data-total="{{ $type === 'money' ? $key : '' }}">{{ $type === 'money' ? number_format($totals[$key] ?? 0, 2) : '' }}</td>@endforeach
                <td></td>
            </tr></tfoot>
        </table>
    </div>

    <div class="rt-bar">
        <div class="grow"><span id="rtState">Figures from payroll. Click any cell to correct it.</span></div>
        <button type="button" class="pp-btn pp-btn-ghost" id="rtAdd"><i class="bi bi-plus-lg"></i> Add a line</button>
        <button type="button" class="pp-btn pp-btn-ghost" id="rtSave" disabled><i class="bi bi-floppy"></i> Save changes</button>
        @if($kind === 'nhima')
            <label style="font-size:.8rem;display:flex;gap:.35rem;align-items:center"><input type="checkbox" id="rtHeader"> Include header row</label>
        @endif
        <a class="pp-btn pp-btn-primary" id="rtDownload" href="{{ route('payroll.reports.returns.download', [$run, $kind, 'scope' => $scope]) }}"><i class="bi bi-download"></i> Download {{ strtoupper($meta['format']) }}</a>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const page = document.getElementById('rtPage'), grid = document.getElementById('rtGrid'), body = grid.tBodies[0];
    const cols = JSON.parse(page.dataset.cols), keys = Object.keys(cols);
    const removed = new Set();
    let dirty = false;

    const fmt = n => (Math.round(n * 100) / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    function totals() {
        keys.filter(k => cols[k].type === 'money').forEach(k => {
            let t = 0;
            body.querySelectorAll('tr:not(.gone) input[data-col="' + k + '"]').forEach(i => t += parseFloat((i.value || '0').replace(/[, K]/g, '')) || 0);
            const cell = grid.querySelector('[data-total="' + k + '"]'); if (cell) cell.textContent = fmt(t);
        });
    }
    function mark(state) {
        dirty = state;
        document.getElementById('rtSave').disabled = !dirty;
        document.getElementById('rtSave').classList.toggle('pp-btn-primary', dirty);
        document.getElementById('rtState').innerHTML = dirty ? '<span class="rt-dirty">Unsaved changes</span> — save them so the download uses your figures.' : 'Click any cell to correct it.';
    }
    function bind(input) {
        input.addEventListener('input', () => {
            const td = input.parentElement, orig = input.dataset.orig;
            if (orig !== undefined && input.closest('tr').dataset.key.charAt(0) === 'e') td.classList.toggle('edited', input.value.trim() !== orig);
            totals(); mark(true);
        });
        input.addEventListener('keydown', e => {
            if (e.key === 'Enter' || e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                const tr = input.closest('tr'), col = input.dataset.col;
                const next = e.key === 'ArrowUp' ? tr.previousElementSibling : tr.nextElementSibling;
                next?.querySelector('input[data-col="' + col + '"]')?.focus();
            }
        });
    }
    body.querySelectorAll('input').forEach(bind);

    body.addEventListener('click', e => {
        const b = e.target.closest('[data-remove]'); if (!b) return;
        const tr = b.closest('tr');
        if (tr.dataset.key.startsWith('new')) { tr.remove(); }
        else {
            tr.classList.toggle('gone');
            tr.classList.contains('gone') ? removed.add(tr.dataset.key) : removed.delete(tr.dataset.key);
            b.innerHTML = tr.classList.contains('gone') ? '<i class="bi bi-arrow-counterclockwise"></i>' : '<i class="bi bi-x-lg"></i>';
            b.title = tr.classList.contains('gone') ? 'Put this line back' : 'Remove this line from the file';
        }
        totals(); mark(true);
    });

    document.getElementById('rtAdd').addEventListener('click', () => {
        const tr = document.createElement('tr');
        tr.dataset.key = 'new' + Date.now(); tr.dataset.label = 'Added line';
        tr.innerHTML = '<td class="who">Added line<small>not in payroll</small></td>' + keys.map(k => '<td class="' + (cols[k].type === 'money' ? 'num' : '') + '"><input data-col="' + k + '" value="' + (cols[k].type === 'money' ? '0.00' : '') + '"></td>').join('') + '<td class="act"><button type="button" data-remove title="Remove"><i class="bi bi-x-lg"></i></button></td>';
        // copy the period / employer columns from the first line
        const first = body.querySelector('tr');
        ['employer', 'year', 'month', 'flag', 'nature'].forEach(k => { const src = first?.querySelector('input[data-col="' + k + '"]'), dst = tr.querySelector('input[data-col="' + k + '"]'); if (src && dst) dst.value = src.value; });
        body.appendChild(tr); tr.querySelectorAll('input').forEach(bind); tr.querySelector('input').focus();
        mark(true);
    });

    document.querySelectorAll('[data-filter]').forEach(ch => ch.addEventListener('click', () => {
        const f = ch.dataset.filter;
        document.querySelectorAll('[data-filter]').forEach(c => c.classList.toggle('on', c === ch && f !== ''));
        body.querySelectorAll('tr').forEach(tr => tr.hidden = f && tr.dataset.level !== f && !(f === 'check' && tr.dataset.level === 'fix'));
    }));

    async function save() {
        const rows = [...body.querySelectorAll('tr:not(.gone)')].map(tr => ({
            key: tr.dataset.key, label: tr.dataset.label, employee_id: tr.dataset.employee ? +tr.dataset.employee : null,
            cells: Object.fromEntries([...tr.querySelectorAll('input[data-col]')].map(i => [i.dataset.col, i.value])),
        }));
        const btn = document.getElementById('rtSave'); btn.disabled = true; btn.textContent = 'Saving…';
        const r = await fetch(page.dataset.save, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': page.dataset.csrf },
            body: JSON.stringify({ rows, removed: [...removed], scope: page.dataset.scope }) });
        if (r.ok) { location.reload(); return true; }
        const j = await r.json().catch(() => ({}));
        alert(j.message || 'Could not save — check the highlighted cells.'); btn.disabled = false; btn.innerHTML = '<i class="bi bi-floppy"></i> Save changes';
        return false;
    }
    document.getElementById('rtSave').addEventListener('click', save);
    document.getElementById('rtDownload').addEventListener('click', e => {
        const h = document.getElementById('rtHeader');
        if (h) { const u = new URL(e.currentTarget.href); u.searchParams.set('with_header', h.checked ? 1 : 0); e.currentTarget.href = u.toString(); }
        if (dirty && !confirm('You have unsaved changes. Download the last saved figures anyway?\n\nChoose Cancel, then "Save changes", to include your edits.')) e.preventDefault();
    });
    window.addEventListener('beforeunload', e => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
})();
</script>
@endpush
