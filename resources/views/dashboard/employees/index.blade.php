@extends('layouts.app')

@section('title', 'People · ZamPayroll')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/zp-people.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/zp-org.css') }}">
@endpush

@php
    $u = auth()->user();
    $canManage = $u?->hasPermission('employees.manage');
    $withoutPortal = $employees->filter(fn ($e) => ! $e->user && $e->portal_email && strcasecmp((string) $e->employment_status, 'Active') === 0)->count();
    $statusOpts = ['active' => 'Active', 'away' => 'On leave today', 'inactive' => 'Inactive'];
    $flagOpts = ['missing' => 'Missing details', 'new' => 'Joined in the last 30 days', 'noportal' => 'No portal yet', 'portal' => 'Has portal'];
@endphp

@section('content')
<div class="em" id="em" data-preset="{{ json_encode($preset) }}">

    {{-- ===== header ===== --}}
    <div class="em-head">
        <div>
            <h1>People</h1>
            <p class="em-sub">
                <span><b>{{ $stats->active }}</b> active</span>
                <span><b>{{ $departments->count() }}</b> {{ \Illuminate\Support\Str::plural('department', $departments->count()) }}</span>
                @if($stats->board)<span><b>{{ $stats->board }}</b> board {{ $stats->board === 1 ? 'member' : 'members' }}</span>@endif
                @if($stats->away)<span><b>{{ $stats->away }}</b> on leave today</span>@endif
                @if($stats->new)<span><b>{{ $stats->new }}</b> new this month</span>@endif
            </p>
        </div>
        @if($people->isNotEmpty())
        <div class="em-actions">
            @if($canManage)
                <a href="{{ route('employees.create') }}" class="pp-btn pp-btn-primary"><i class="bi bi-person-plus"></i> Add employee</a>
            @endif
            <div class="em-menu">
                <button type="button" class="em-iconbtn" data-em-pop aria-label="More actions" title="More"><i class="bi bi-three-dots"></i></button>
                <div class="em-pop">
                    @if($u?->hasPermission('employees.import'))
                        <a href="{{ route('employees.import.wizard') }}"><i class="bi bi-upload"></i><div>Import a list<small>Excel, CSV or pasted text</small></div></a>
                    @endif
                    @if($canManage)
                        <button type="button" class="it" data-ai-prompt="I want to add employees. I will paste or attach the list."><i class="bi bi-stars"></i><div>Add with AI<small>Paste a list in any shape</small></div></button>
                    @endif
                    <a href="{{ route('departments.index') }}"><i class="bi bi-diagram-3"></i><div>Departments<small>Create and manage departments</small></div></a>
                    <button type="button" class="it" id="emExport"><i class="bi bi-filetype-csv"></i><div>Export this view<small>The people shown, as a CSV file</small></div></button>
                    @if($u?->hasPermission('portal.manage') && $withoutPortal)
                        <hr>
                        <form method="POST" action="{{ route('employees.portal.bulk') }}" onsubmit="return confirm('Create self-service portals for {{ $withoutPortal }} employee(s)? Each will receive an email with login details.')">
                            @csrf
                            <button class="it"><i class="bi bi-phone"></i><div>Create portals ({{ $withoutPortal }})<small>Active people with an email and no portal</small></div></button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
        @endif
    </div>

    @if($people->isEmpty())
        <div class="pp"><div class="pp-card pp-empty-team">
            <div class="pp-done-badge" style="animation:none"><i class="bi bi-people-fill"></i></div>
            <h2 style="font-family:var(--zp-font-display)">Let's bring your team in</h2>
            <p class="pp-muted">Pick whichever is easiest — you can mix and match.</p>
            <div class="opts">
                <a class="opt" href="{{ route('employees.import.wizard') }}"><i class="bi bi-file-earmark-spreadsheet"></i><b>Import a spreadsheet</b><small>Excel or CSV from your old payroll. Best for 5+ people.</small></a>
                <a class="opt" href="{{ route('employees.create') }}"><i class="bi bi-person-plus"></i><b>Add one person</b><small>A quick step-by-step journey.</small></a>
                <button type="button" class="opt" data-ai-prompt="I want to add employees. I will paste or attach the list."><i class="bi bi-stars"></i><b>Paste into AI mode</b><small>Paste a list in any shape — AI mode reads it.</small></button>
            </div>
        </div></div>
    @else

    {{-- ===== toolbar ===== --}}
    <div class="em-bar" role="search">
        <label class="em-search">
            <i class="bi bi-search"></i>
            <input type="search" id="emQ" placeholder="Search by name, job, number, phone or email" autocomplete="off" aria-label="Search people">
            <kbd>/</kbd>
        </label>

        <div class="em-menu">
            <button type="button" class="em-filter" data-em-pop data-filter-btn="status"><i class="bi bi-circle-half"></i> Status <em hidden></em></button>
            <div class="em-pop left">
                @foreach($statusOpts as $v => $l)
                    <label class="em-check"><input type="checkbox" data-f="status" value="{{ $v }}"> {{ $l }} <span class="n" data-n-status="{{ $v }}"></span></label>
                @endforeach
            </div>
        </div>

        <div class="em-menu">
            <button type="button" class="em-filter" data-em-pop data-filter-btn="nature"><i class="bi bi-briefcase"></i> Type <em hidden></em></button>
            <div class="em-pop left">
                @foreach($natures as $v => $l)
                    <label class="em-check"><input type="checkbox" data-f="nature" value="{{ $v }}"> {{ $l }} <span class="n" data-n-nature="{{ $v }}"></span></label>
                @endforeach
            </div>
        </div>

        <div class="em-menu">
            <button type="button" class="em-filter" data-em-pop data-filter-btn="flag"><i class="bi bi-funnel"></i> More <em hidden></em></button>
            <div class="em-pop left">
                @foreach($flagOpts as $v => $l)
                    <label class="em-check"><input type="checkbox" data-f="flag" value="{{ $v }}"> {{ $l }} <span class="n" data-n-flag="{{ $v }}"></span></label>
                @endforeach
            </div>
        </div>

        <div class="em-menu">
            <button type="button" class="em-filter" data-em-pop><i class="bi bi-sort-down"></i> <span id="emSortLabel">A–Z</span></button>
            <div class="em-pop">
                @foreach(['name' => 'Name A–Z', 'recent' => 'Newest first', 'dept' => 'Department', 'complete' => 'Least complete first'] as $v => $l)
                    <button type="button" class="it" data-sort="{{ $v }}"><i class="bi bi-check2" style="visibility:hidden"></i> {{ $l }}</button>
                @endforeach
            </div>
        </div>

        <div class="em-seg" role="group" aria-label="View">
            <button type="button" data-view="cards" class="on" title="Cards" aria-label="Cards"><i class="bi bi-grid-3x2-gap"></i></button>
            <button type="button" data-view="list" title="List" aria-label="List"><i class="bi bi-list-ul"></i></button>
        </div>
    </div>

    {{-- department chips --}}
    <div class="em-depts" role="group" aria-label="Departments">
        <button type="button" class="em-chip on" data-dept-chip=""><i class="bi bi-people"></i> Everyone <small>{{ $stats->total }}</small></button>
        @foreach($departments as $d)
            <button type="button" class="em-chip" data-dept-chip="{{ $d->name }}" style="--c: {{ $d->color }}"><span class="em-dot" style="--c: {{ $d->color }}"></span>{{ $d->name }} <small>{{ $d->count }}</small></button>
        @endforeach
        @if($unassigned)
            <button type="button" class="em-chip" data-dept-chip="__none" style="--c:#7B877F"><span class="em-dot" style="--c:#7B877F"></span>No department <small>{{ $unassigned }}</small></button>
        @endif
    </div>

    <div class="em-tokens" id="emTokens"></div>
    <p class="em-count" id="emCount" aria-live="polite"></p>

    {{-- ===== cards ===== --}}
    <div class="em-grid" id="emCards">
        @foreach($people as $p)
            @php $e = $p->model; @endphp
            <a class="em-card {{ $p->active ? '' : 'muted' }}" href="{{ route('employees.show', $e->id) }}" style="--c: {{ $p->color }}"
               data-id="{{ $e->id }}" data-name="{{ $p->name }}" data-dept="{{ $p->dept }}" data-nature="{{ $p->nature }}"
               data-status="{{ ! $p->active ? 'inactive' : ($p->away ? 'away active' : 'active') }}"
               data-flags="{{ trim(($p->complete < 100 ? 'missing ' : '') . ($p->new ? 'new ' : '') . ($p->portal ? 'portal' : 'noportal')) }}"
               data-since="{{ optional($p->since)->format('Y-m-d') }}" data-complete="{{ $p->complete }}"
               data-search="{{ mb_strtolower($p->name . ' ' . $e->middle_name . ' ' . $e->employee_id . ' ' . $e->position . ' ' . $p->dept . ' ' . $e->branch . ' ' . $e->primary_phone . ' ' . $e->company_email . ' ' . $e->personal_email . ' ' . $e->nrc_no) }}"
               data-csv="{{ json_encode([$e->employee_id, $p->name, $e->position, $p->dept, $e->branch, $e->employment_status, $e->employment_nature, optional($p->since)->format('Y-m-d'), $e->primary_phone, $p->email]) }}">
                <div class="em-quick">
                    @if($canManage)<object><a href="{{ route('employees.edit', $e->id) }}" title="Edit" aria-label="Edit {{ $p->name }}"><i class="bi bi-pencil"></i></a></object>@endif
                    @if($p->phone)<object><a href="tel:{{ preg_replace('/\s+/', '', $p->phone) }}" title="Call" aria-label="Call {{ $p->name }}"><i class="bi bi-telephone"></i></a></object>@endif
                    @if($p->email)<object><a href="mailto:{{ $p->email }}" title="Email" aria-label="Email {{ $p->name }}"><i class="bi bi-envelope"></i></a></object>@endif
                </div>
                <div class="em-card-top">
                    @if($e->photo_url)
                        <img class="em-avatar" src="{{ $e->photo_url }}" alt="" style="--c: {{ $p->color }}">
                    @else
                        <span class="em-avatar" style="--c: {{ $p->color }}">{{ $e->initials ?: '?' }}</span>
                    @endif
                    <div class="who">
                        <span class="em-name">{{ $p->name ?: 'Unnamed' }}</span>
                        <span class="em-role">{{ $e->position ?: 'No job title yet' }}</span>
                    </div>
                </div>
                <div class="em-tags">
                    @if($p->dept)<span class="em-tag dept"><span class="em-dot"></span>{{ $p->dept }}</span>@endif
                    @if(! $p->active)<span class="em-tag off">{{ $e->employment_status ?: 'Inactive' }}</span>
                    @elseif($p->away)<span class="em-tag leave"><i class="bi bi-airplane"></i> On leave</span>@endif
                    @if($p->nature === 'BOARD')<span class="em-tag board"><i class="bi bi-bank2"></i> Board</span>@endif
                    @if($p->new)<span class="em-tag new">New</span>@endif
                    @if($p->bday)<span class="em-tag bday">🎂 {{ $p->bday->isToday() ? 'Today' : $p->bday->format('D j M') }}</span>@endif
                </div>
                <div class="em-meta">
                    <span><i class="bi bi-hash"></i>{{ $e->employee_id ?: '—' }}{{ $e->branch ? ' · ' . $e->branch : '' }}</span>
                    <span><i class="bi bi-calendar-check"></i>{{ $p->since ? 'Joined ' . $p->since->format('M Y') : 'Start date not set' }}</span>
                </div>
                <div class="em-foot">
                    <div class="em-prog {{ $p->complete < 60 ? 'low' : '' }}" title="Profile {{ $p->complete }}% complete"><i style="width: {{ $p->complete }}%"></i></div>
                    <small>{{ $p->complete }}%</small>
                    <i class="bi bi-phone em-portal {{ $p->portal ? 'on' : '' }}" title="{{ $p->portal ? 'Has the self-service portal' : 'No portal yet' }}"></i>
                </div>
            </a>
        @endforeach
    </div>

    {{-- ===== list ===== --}}
    <div class="em-list" id="emList" hidden>
        <div class="em-row hd"><span>Name</span><span>Department</span><span>Status</span><span>Joined</span><span>Profile</span><span></span></div>
        @foreach($people as $p)
            @php $e = $p->model; @endphp
            <a class="em-row {{ $p->active ? '' : 'muted' }}" href="{{ route('employees.show', $e->id) }}" style="--c: {{ $p->color }}" data-id="{{ $e->id }}">
                <span class="who">
                    @if($e->photo_url)<img class="em-avatar sm" src="{{ $e->photo_url }}" alt="" style="--c: {{ $p->color }}">@else<span class="em-avatar sm" style="--c: {{ $p->color }}">{{ $e->initials ?: '?' }}</span>@endif
                    <span><b>{{ $p->name ?: 'Unnamed' }}</b><small>{{ $e->position ?: '—' }} · {{ $e->employee_id }}</small></span>
                </span>
                <span>@if($p->dept)<span class="em-tag dept"><span class="em-dot"></span>{{ $p->dept }}</span>@else<span class="pp-muted">—</span>@endif</span>
                <span class="em-status {{ ! $p->active ? 'off' : ($p->away ? 'away' : '') }}"><span class="em-dot"></span>{{ ! $p->active ? ($e->employment_status ?: 'Inactive') : ($p->away ? 'On leave' : 'Active') }}</span>
                <span>{{ $p->since ? $p->since->format('j M Y') : '—' }}</span>
                <span><span class="em-prog {{ $p->complete < 60 ? 'low' : '' }}" style="display:block;width:70px"><i style="width: {{ $p->complete }}%"></i></span></span>
                <span><i class="bi bi-chevron-right pp-muted"></i></span>
            </a>
        @endforeach
    </div>

    <div class="em-empty" id="emNone" hidden>
        <i class="bi bi-search big"></i>
        <h3>No one matches</h3>
        <p>Try a different name or remove a filter.</p>
        <button type="button" class="pp-btn pp-btn-ghost" data-clear-all>Clear search and filters</button>
    </div>
    <div class="em-more" id="emMoreWrap" hidden><button type="button" class="pp-btn pp-btn-ghost" id="emMore">Show more</button></div>
    @endif
</div>
@endsection

@push('scripts')
<script>
(function () {
    const root = document.getElementById('em');
    if (!root) return;

    /* ----- popovers ----- */
    document.querySelectorAll('[data-em-pop]').forEach(b => b.addEventListener('click', e => {
        e.stopPropagation();
        const pop = b.nextElementSibling, open = pop.classList.contains('open');
        document.querySelectorAll('.em-pop.open').forEach(p => p.classList.remove('open'));
        if (!open) pop.classList.add('open');
    }));
    document.querySelectorAll('.em-pop').forEach(p => p.addEventListener('click', e => { if (e.target.closest('label')) e.stopPropagation(); }));
    document.addEventListener('click', () => document.querySelectorAll('.em-pop.open').forEach(p => p.classList.remove('open')));

    const cardsBox = document.getElementById('emCards');
    if (!cardsBox) return;
    const cards = [...cardsBox.children];
    const rows = Object.fromEntries([...document.querySelectorAll('#emList .em-row[data-id]')].map(r => [r.dataset.id, r]));
    const listBox = document.getElementById('emList');
    const q = document.getElementById('emQ');
    const PAGE = 48;
    const store = { get: k => { try { return localStorage.getItem('zp.people.' + k); } catch (e) { return null; } }, set: (k, v) => { try { localStorage.setItem('zp.people.' + k, v); } catch (e) {} } };
    const state = { q: '', dept: '', status: new Set(), nature: new Set(), flag: new Set(), sort: store.get('sort') || 'name', view: store.get('view') || 'cards', limit: PAGE };

    const preset = JSON.parse(root.dataset.preset || '{}');
    if (preset.department) state.dept = preset.department;
    if (preset.q) { state.q = preset.q.toLowerCase(); q.value = preset.q; }
    if (preset.status) state.status.add(preset.status);

    const labels = {
        status: Object.fromEntries([...document.querySelectorAll('[data-f=status]')].map(i => [i.value, i.parentElement.textContent.trim()])),
        nature: Object.fromEntries([...document.querySelectorAll('[data-f=nature]')].map(i => [i.value, i.parentElement.textContent.trim()])),
        flag: Object.fromEntries([...document.querySelectorAll('[data-f=flag]')].map(i => [i.value, i.parentElement.textContent.trim()])),
    };

    function matches(c, skip) {
        const d = c.dataset;
        if (state.q && !state.q.split(/\s+/).every(w => d.search.includes(w))) return false;
        if (skip !== 'dept' && state.dept && (state.dept === '__none' ? d.dept !== '' : d.dept !== state.dept)) return false;
        if (skip !== 'status' && state.status.size && ![...state.status].some(s => d.status.split(' ').includes(s))) return false;
        if (skip !== 'nature' && state.nature.size && !state.nature.has(d.nature)) return false;
        if (skip !== 'flag' && state.flag.size && ![...state.flag].every(f => d.flags.split(' ').includes(f))) return false;
        return true;
    }

    const sorters = {
        name: (a, b) => a.dataset.name.localeCompare(b.dataset.name),
        recent: (a, b) => (b.dataset.since || '').localeCompare(a.dataset.since || ''),
        dept: (a, b) => (a.dataset.dept || '~').localeCompare(b.dataset.dept || '~') || a.dataset.name.localeCompare(b.dataset.name),
        complete: (a, b) => (+a.dataset.complete - +b.dataset.complete) || a.dataset.name.localeCompare(b.dataset.name),
    };
    const sortNames = { name: 'A–Z', recent: 'Newest', dept: 'Department', complete: 'Least complete' };

    function render() {
        const shown = cards.filter(c => matches(c)).sort(sorters[state.sort]);
        const visible = shown.slice(0, state.limit);
        cards.forEach(c => c.hidden = true);
        Object.values(rows).forEach(r => r.hidden = true);
        visible.forEach(c => { c.hidden = false; cardsBox.appendChild(c); const r = rows[c.dataset.id]; if (r) { r.hidden = false; listBox.appendChild(r); } });

        cardsBox.hidden = state.view !== 'cards' || !shown.length;
        listBox.hidden = state.view !== 'list' || !shown.length;
        document.getElementById('emNone').hidden = !!shown.length;
        document.getElementById('emMoreWrap').hidden = shown.length <= state.limit;
        document.getElementById('emMore').textContent = `Show ${Math.min(PAGE, shown.length - state.limit)} more`;
        const filtered = state.q || state.dept || state.status.size || state.nature.size || state.flag.size;
        document.getElementById('emCount').textContent = filtered ? `Showing ${shown.length} of ${cards.length} ${cards.length === 1 ? 'person' : 'people'}` : '';

        // counts next to each option, given the other filters
        ['status', 'nature', 'flag'].forEach(k => document.querySelectorAll(`[data-n-${k}]`).forEach(el => {
            const v = el.getAttribute(`data-n-${k}`);
            el.textContent = cards.filter(c => matches(c, k) && (k === 'status' ? c.dataset.status.split(' ').includes(v) : k === 'nature' ? c.dataset.nature === v : c.dataset.flags.split(' ').includes(v))).length;
        }));
        document.querySelectorAll('[data-dept-chip]').forEach(ch => ch.classList.toggle('on', ch.dataset.deptChip === state.dept));
        document.querySelectorAll('[data-filter-btn]').forEach(b => {
            const n = state[b.dataset.filterBtn].size, em = b.querySelector('em');
            b.classList.toggle('on', n > 0); em.hidden = !n; em.textContent = n;
        });
        document.querySelectorAll('[data-f]').forEach(i => i.checked = state[i.dataset.f].has(i.value));
        document.querySelectorAll('[data-sort]').forEach(b => b.querySelector('i').style.visibility = b.dataset.sort === state.sort ? 'visible' : 'hidden');
        document.getElementById('emSortLabel').textContent = sortNames[state.sort];
        document.querySelectorAll('[data-view]').forEach(b => b.classList.toggle('on', b.dataset.view === state.view));

        // tokens
        const tokens = [];
        if (state.dept) tokens.push(['dept', '', state.dept === '__none' ? 'No department' : state.dept]);
        ['status', 'nature', 'flag'].forEach(k => state[k].forEach(v => tokens.push([k, v, labels[k][v] || v])));
        const box = document.getElementById('emTokens');
        box.innerHTML = '';
        tokens.forEach(([k, v, l]) => {
            const t = document.createElement('span'); t.className = 'em-token'; t.textContent = l;
            const x = document.createElement('button'); x.type = 'button'; x.innerHTML = '&times;'; x.setAttribute('aria-label', 'Remove ' + l);
            x.onclick = () => { if (k === 'dept') state.dept = ''; else state[k].delete(v); state.limit = PAGE; render(); };
            t.appendChild(x); box.appendChild(t);
        });
        if (tokens.length > 1) { const c = document.createElement('button'); c.className = 'em-clear'; c.type = 'button'; c.textContent = 'Clear all'; c.onclick = clearAll; box.appendChild(c); }
    }

    function clearAll() { state.q = ''; q.value = ''; state.dept = ''; ['status', 'nature', 'flag'].forEach(k => state[k].clear()); state.limit = PAGE; render(); }

    let t; q.addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => { state.q = q.value.trim().toLowerCase(); state.limit = PAGE; render(); }, 120); });
    document.addEventListener('keydown', e => {
        if (e.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) { e.preventDefault(); q.focus(); }
        if (e.key === 'Escape' && document.activeElement === q) { q.value = ''; state.q = ''; render(); }
    });
    document.querySelectorAll('[data-f]').forEach(i => i.addEventListener('change', () => { state[i.dataset.f][i.checked ? 'add' : 'delete'](i.value); state.limit = PAGE; render(); }));
    document.querySelectorAll('[data-dept-chip]').forEach(ch => ch.addEventListener('click', () => { state.dept = state.dept === ch.dataset.deptChip ? '' : ch.dataset.deptChip; state.limit = PAGE; render(); }));
    document.querySelectorAll('[data-sort]').forEach(b => b.addEventListener('click', () => { state.sort = b.dataset.sort; store.set('sort', state.sort); render(); }));
    document.querySelectorAll('[data-view]').forEach(b => b.addEventListener('click', () => { state.view = b.dataset.view; store.set('view', state.view); render(); }));
    document.querySelectorAll('[data-clear-all]').forEach(b => b.addEventListener('click', clearAll));
    document.getElementById('emMore').addEventListener('click', () => { state.limit += PAGE; render(); });
    // Quick-action links inside a card shouldn't open the profile
    document.querySelectorAll('.em-quick a').forEach(a => a.addEventListener('click', e => e.stopPropagation()));

    document.getElementById('emExport')?.addEventListener('click', () => {
        const head = ['Employee number', 'Name', 'Job title', 'Department', 'Branch', 'Status', 'Type', 'Start date', 'Phone', 'Email'];
        const esc = v => { v = v == null ? '' : String(v); return /[",\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; };
        const lines = [head].concat(cards.filter(c => matches(c)).sort(sorters[state.sort]).map(c => JSON.parse(c.dataset.csv)));
        const blob = new Blob(['﻿' + lines.map(r => r.map(esc).join(',')).join('\r\n')], { type: 'text/csv' });
        const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'people-' + new Date().toISOString().slice(0, 10) + '.csv'; a.click();
        setTimeout(() => URL.revokeObjectURL(a.href), 1000);
    });

    render();
})();
</script>
@endpush
