@extends('layouts.app')

@section('title', 'Departments · ZamPayroll')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/zp-people.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/zp-org.css') }}">
@endpush

@php
    $k = fn ($n) => 'K ' . number_format((float) $n, 0);
    $initials = fn ($e) => strtoupper(mb_substr((string) $e->first_name, 0, 1) . mb_substr((string) $e->last_name, 0, 1));
@endphp

@section('content')
<div class="dp">
    <div class="dp-head">
        <div>
            <h1>Departments</h1>
            <p class="dp-sub"><span><b>{{ $totals->departments }}</b> {{ \Illuminate\Support\Str::plural('department', $totals->departments) }}</span><span><b>{{ $totals->people }}</b> active people</span><span><b>{{ $totals->away }}</b> away today</span></p>
        </div>
        @if($canManage)
            <button type="button" class="pp-btn pp-btn-primary" data-dp-open="new"><i class="bi bi-plus-lg"></i> New department</button>
        @endif
    </div>

    <div class="dp-kpis">
        <div class="dp-kpi main"><span>Departments</span><b>{{ $totals->departments }}</b></div>
        <div class="dp-kpi"><span>Active people</span><b>{{ $totals->people }}</b></div>
        <div class="dp-kpi"><span>Away today</span><b>{{ $totals->away }}</b></div>
        <div class="dp-kpi"><span>Without a department</span><b style="color:{{ $unassigned->count() ? 'var(--zp-copper)' : 'inherit' }}">{{ $unassigned->count() }}</b></div>
        @if($showPay)<div class="dp-kpi"><span>Monthly take-home pay</span><b>{{ $k($totals->pay) }}</b></div>@endif
    </div>

    <div class="dp-grid">
        @foreach($departments as $d)
            @php $shown = $d->people->take(6); @endphp
            <article class="dp-card" style="--c: {{ $d->color }}">
                <div class="dp-card-head">
                    <span class="dp-icon"><i class="bi bi-diagram-3"></i></span>
                    <div style="flex:1;min-width:0">
                        <h3>{{ $d->name }}</h3>
                        <p class="desc">{{ $d->record?->description ?: ($d->total ? $d->total . ' ' . \Illuminate\Support\Str::plural('person', $d->total) . ' in total' : 'No one here yet') }}</p>
                    </div>
                    @if($canManage && $d->record)
                        <div class="em-menu">
                            <button type="button" class="em-iconbtn" style="width:34px;height:34px" data-em-pop aria-label="Department options"><i class="bi bi-three-dots"></i></button>
                            <div class="em-pop">
                                <button type="button" class="it" data-dp-open="edit" data-dp="{{ json_encode(['id' => $d->record->id, 'name' => $d->record->name, 'description' => $d->record->description, 'color' => $d->color, 'head' => $d->record->head_employee_id]) }}"><i class="bi bi-pencil"></i> Edit or rename</button>
                                <button type="button" class="it" data-dp-open="delete" data-dp="{{ json_encode(['id' => $d->record->id, 'name' => $d->record->name, 'count' => $d->total]) }}" style="color:var(--zp-red)"><i class="bi bi-trash3" style="color:var(--zp-red)"></i> Remove</button>
                            </div>
                        </div>
                    @endif
                </div>

                @if($d->record?->head)
                    <div class="dp-headline"><i class="bi bi-person-badge" style="color:var(--c)"></i> Head: <b>{{ $d->record->head->first_name }} {{ $d->record->head->last_name }}</b>@if($d->record->head->position)<span class="pp-muted">· {{ $d->record->head->position }}</span>@endif</div>
                @endif

                <div class="dp-stats">
                    <div><span>Active</span><b>{{ $d->active }}</b></div>
                    <div><span>Away today</span><b>{{ $d->away }}</b></div>
                    <div><span>New (30 days)</span><b>{{ $d->new }}</b></div>
                </div>

                @if($d->total)
                    <div class="dp-people">
                        @foreach($shown as $e)
                            @if($e->passport_photo)
                                <img class="em-avatar sm" style="--c: {{ $d->color }}" src="{{ asset('storage/' . $e->passport_photo) }}" alt="{{ $e->first_name }}" title="{{ $e->first_name }} {{ $e->last_name }}">
                            @else
                                <span class="em-avatar sm" style="--c: {{ $d->color }}" title="{{ $e->first_name }} {{ $e->last_name }}">{{ $initials($e) }}</span>
                            @endif
                        @endforeach
                        @if($d->total > $shown->count())<span class="more">+{{ $d->total - $shown->count() }} more</span>@endif
                    </div>
                @endif

                <div class="dp-foot">
                    <span class="dp-pay">@if($showPay)Pay <b>{{ $k($d->pay) }}</b> / month @endif</span>
                    <a class="go" href="{{ route('employees.index', ['department' => $d->name]) }}">View people <i class="bi bi-arrow-right"></i></a>
                </div>
            </article>
        @endforeach

        @if($unassigned->count())
            <article class="dp-card" style="--c: #7B877F">
                <div class="dp-card-head">
                    <span class="dp-icon"><i class="bi bi-question-circle"></i></span>
                    <div><h3>No department</h3><p class="desc">Give these people a department from their profile.</p></div>
                </div>
                <div class="dp-people">
                    @foreach($unassigned->take(6) as $e)<span class="em-avatar sm" style="--c:#7B877F" title="{{ $e->first_name }} {{ $e->last_name }}">{{ $initials($e) }}</span>@endforeach
                    @if($unassigned->count() > 6)<span class="more">+{{ $unassigned->count() - 6 }} more</span>@endif
                </div>
                <div class="dp-foot"><span class="dp-pay">{{ $unassigned->count() }} {{ \Illuminate\Support\Str::plural('person', $unassigned->count()) }}</span><a class="go" href="{{ route('employees.index', ['department' => '__none']) }}">View people <i class="bi bi-arrow-right"></i></a></div>
            </article>
        @endif

        @if($canManage)
            <button type="button" class="dp-new" data-dp-open="new"><i class="bi bi-plus-circle"></i> Add a department</button>
        @endif
    </div>
</div>
@endsection

@push('modals')
@if($canManage)
<div class="dp-modal" id="dpModal" role="dialog" aria-modal="true" aria-labelledby="dpTitle">
    <form class="dp-dialog pp" method="POST" id="dpForm" action="{{ route('departments.store') }}">
        @csrf
        <input type="hidden" name="_method" value="POST" id="dpMethod">
        <h2 id="dpTitle">New department</h2>
        <div class="pp-field"><label for="dpName">Name</label><input class="pp-input" id="dpName" name="name" required maxlength="100" placeholder="e.g. Finance" value="{{ old('name') }}">
            @error('name')<div class="pp-err" style="color:var(--zp-red);font-size:.8rem;margin-top:.3rem">{{ $message }}</div>@enderror</div>
        <div class="pp-field" style="margin-top:.8rem"><label for="dpDesc">What they do <small>optional</small></label><input class="pp-input" id="dpDesc" name="description" maxlength="500" placeholder="e.g. Accounts, payroll and procurement"></div>
        <div class="pp-field" style="margin-top:.8rem"><label for="dpHead">Head of department <small>optional</small></label>
            <select class="pp-select" id="dpHead" name="head_employee_id"><option value="">No one yet</option>@foreach($everyone as $e)<option value="{{ $e->id }}">{{ $e->first_name }} {{ $e->last_name }}{{ $e->position ? ' — ' . $e->position : '' }}</option>@endforeach</select></div>
        <div class="pp-field" style="margin-top:.8rem"><label>Colour</label>
            <div class="dp-swatches">@foreach($palette as $i => $c)<label><input type="radio" name="color" value="{{ $c }}" @checked($i === 0)><span style="--c: {{ $c }}"></span></label>@endforeach</div></div>
        <div class="row2"><button type="button" class="pp-btn pp-btn-ghost" data-dp-close>Cancel</button><button class="pp-btn pp-btn-primary" id="dpSave">Create department</button></div>
    </form>
</div>

<div class="dp-modal" id="dpDelete" role="dialog" aria-modal="true" aria-labelledby="dpDelTitle">
    <form class="dp-dialog pp" method="POST" id="dpDelForm">
        @csrf @method('DELETE')
        <h2 id="dpDelTitle">Remove department</h2>
        <p id="dpDelText" style="font-size:.9rem;color:var(--zp-ink-2)"></p>
        <div class="pp-field" id="dpMoveWrap"><label for="dpMove">Move its people to</label>
            <select class="pp-select" id="dpMove" name="move_to"><option value="">No department</option>@foreach($departments as $d)<option value="{{ $d->name }}">{{ $d->name }}</option>@endforeach</select></div>
        <div class="row2"><button type="button" class="pp-btn pp-btn-ghost" data-dp-close>Cancel</button><button class="pp-btn pp-btn-primary" style="background:var(--zp-red)">Remove</button></div>
    </form>
</div>
@endif
@endpush

@push('scripts')
<script>
(function () {
    document.querySelectorAll('[data-em-pop]').forEach(b => b.addEventListener('click', e => {
        e.stopPropagation();
        const pop = b.nextElementSibling; const open = pop.classList.contains('open');
        document.querySelectorAll('.em-pop.open').forEach(p => p.classList.remove('open'));
        if (!open) pop.classList.add('open');
    }));
    document.addEventListener('click', () => document.querySelectorAll('.em-pop.open').forEach(p => p.classList.remove('open')));

    const modal = document.getElementById('dpModal'), del = document.getElementById('dpDelete');
    if (!modal) return;
    const form = document.getElementById('dpForm'), method = document.getElementById('dpMethod');
    const base = @json(url('/departments'));
    const close = () => [modal, del].forEach(m => m.classList.remove('open'));
    document.querySelectorAll('[data-dp-close]').forEach(b => b.addEventListener('click', close));
    [modal, del].forEach(m => m.addEventListener('click', e => { if (e.target === m) close(); }));
    document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });

    document.querySelectorAll('[data-dp-open]').forEach(b => b.addEventListener('click', () => {
        const kind = b.dataset.dpOpen, d = b.dataset.dp ? JSON.parse(b.dataset.dp) : {};
        if (kind === 'delete') {
            document.getElementById('dpDelForm').action = base + '/' + d.id;
            document.getElementById('dpDelText').textContent = d.count
                ? `${d.name} has ${d.count} ${d.count === 1 ? 'person' : 'people'}. Choose where they go — nobody is deleted.`
                : `${d.name} has no one in it. It will be removed from the list.`;
            document.getElementById('dpMoveWrap').hidden = !d.count;
            [...document.getElementById('dpMove').options].forEach(o => o.hidden = o.value === d.name);
            del.classList.add('open'); return;
        }
        const edit = kind === 'edit';
        document.getElementById('dpTitle').textContent = edit ? 'Edit ' + d.name : 'New department';
        document.getElementById('dpSave').textContent = edit ? 'Save changes' : 'Create department';
        form.action = edit ? base + '/' + d.id : @json(route('departments.store'));
        method.value = edit ? 'PUT' : 'POST';
        document.getElementById('dpName').value = edit ? d.name : '';
        document.getElementById('dpDesc').value = edit ? (d.description || '') : '';
        document.getElementById('dpHead').value = edit && d.head ? d.head : '';
        const sw = [...form.querySelectorAll('input[name=color]')];
        (sw.find(r => edit && r.value.toLowerCase() === (d.color || '').toLowerCase()) || sw[Math.floor(Math.random() * sw.length)]).checked = true;
        modal.classList.add('open');
        setTimeout(() => document.getElementById('dpName').focus(), 50);
    }));
    @if($errors->has('name')) modal.classList.add('open'); @endif
})();
</script>
@endpush
