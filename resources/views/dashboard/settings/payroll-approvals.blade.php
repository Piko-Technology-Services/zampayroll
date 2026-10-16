@extends('layouts.app')

@section('title', 'Payroll approvals · ZamPayroll')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/zp-people.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/zp-payroll.css') }}">
@endpush

@php
    $roleCounts = $people->groupBy(fn ($u) => $u->roleKey())->map->count();
    $opts = [
        'roles' => $roles->map(fn ($r) => ['v' => 'role:' . $r->key, 'l' => $r->name, 'n' => $roleCounts[$r->key] ?? 0, 'sees' => in_array('*', (array) $r->permissions) || in_array('payroll.view', (array) $r->permissions)])->values(),
        'people' => $people->map(fn ($u) => ['v' => 'user:' . $u->id, 'l' => $u->name, 'r' => $u->roleName(), 'sees' => $u->hasPermission('payroll.view')])->values(),
    ];
    $initial = collect(old('steps', $steps->map(fn ($s) => ['name' => $s['name'], 'who' => $s['user_id'] ? 'user:' . $s['user_id'] : 'role:' . $s['role_key']])->all()))->values();
@endphp

@section('content')
<div class="pr pp">
    <a class="pr-crumb" href="{{ route('payroll.runs.index') }}"><i class="bi bi-arrow-left"></i> Payroll</a>
    <div class="pr-head">
        <div>
            <h1>Payroll approval chain</h1>
            <p>Who must sign off a payroll, in what order, before it is locked. Each person is emailed when it's their turn.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('payroll-approvals.update') }}" id="prChainForm">
        @csrf @method('PUT')
        <div class="pr-layout">
            <section class="pr-card">
                <div class="pr-card-head">
                    <div><h2>Steps</h2><div class="sub">{{ $isDefault ? 'You\'re using the default: one approval by an Administrator.' : 'Runs submitted from now on follow this chain.' }}</div></div>
                    <div class="pr-presets">
                        <button type="button" class="pp-btn pp-btn-ghost pp-btn-sm" data-preset="simple">One approver</button>
                        <button type="button" class="pp-btn pp-btn-ghost pp-btn-sm" data-preset="two">Review + approve</button>
                        <button type="button" class="pp-btn pp-btn-ghost pp-btn-sm" data-preset="three">HR → Finance → MD</button>
                    </div>
                </div>

                <div class="pr-flow" style="margin-bottom:1rem" id="prChainPreview"></div>

                <div class="pr-steps-edit" id="prSteps"></div>
                <button type="button" class="pp-btn pp-btn-soft" id="prAdd" style="margin-top:.8rem"><i class="bi bi-plus-lg"></i> Add a step</button>

                <div style="margin-top:1.4rem;padding-top:1.1rem;border-top:1px solid var(--zp-line)">
                    <input type="hidden" name="self_approval" value="0">
                    <label class="pr-switch"><input type="checkbox" name="self_approval" value="1" @checked($selfApproval)> The person who prepared a payroll may also approve it</label>
                    <p class="pp-muted" style="font-size:.8rem;margin:.4rem 0 0">Turn this off for proper separation of duties (recommended once you have more than one payroll person).</p>
                </div>
            </section>

            <aside class="pr-side">
                <div class="pr-card">
                    <div class="pr-card-head" style="margin-bottom:.5rem"><h2 style="font-size:1rem">How it works</h2></div>
                    <ol class="pr-chain" style="font-size:.85rem">
                        <li class="done"><span class="node"><i class="bi bi-pencil"></i></span><div><b>Draft</b><small>HR calculates and checks the highlights. Recalculate as often as needed.</small></div></li>
                        <li class="current"><span class="node"><i class="bi bi-send"></i></span><div><b>Submitted</b><small>Figures freeze. Each step approves in order, or sends it back with a comment.</small></div></li>
                        <li class="locked done"><span class="node"><i class="bi bi-lock-fill"></i></span><div><b>Locked</b><small>After the last approval: payslips, bank file and returns are released, loan balances update.</small></div></li>
                    </ol>
                </div>
                <button class="pp-btn pp-btn-primary" style="width:100%;padding:1rem"><i class="bi bi-check2-circle"></i> Save approval chain</button>
            </aside>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/zp-people.js') }}"></script>
<script>
(function () {
    const OPTS = @json($opts);
    let steps = @json($initial);
    const box = document.getElementById('prSteps');
    const esc = zpPeople.esc;
    const find = (v) => [...OPTS.roles, ...OPTS.people].find(o => o.v === v);
    const roleV = (name) => (OPTS.roles.find(r => r.l.toLowerCase().includes(name)) || OPTS.roles[0] || {}).v;
    const PRESETS = {
        simple: () => [{ name: 'Approval', who: roleV('admin') }],
        two: () => [{ name: 'Payroll review', who: roleV('payroll') }, { name: 'Final approval', who: roleV('admin') }],
        three: () => [{ name: 'HR review', who: roleV('hr') }, { name: 'Finance check', who: roleV('payroll') }, { name: 'MD sign-off', who: roleV('admin') }],
    };

    function select(i, val) {
        let h = '<select class="pp-select" name="steps[' + i + '][who]" data-i="' + i + '"><optgroup label="Anyone with the role">';
        h += OPTS.roles.map(o => '<option value="' + o.v + '"' + (o.v === val ? ' selected' : '') + '>' + esc(o.l) + ' (' + o.n + ')</option>').join('');
        h += '</optgroup><optgroup label="A specific person">';
        h += OPTS.people.map(o => '<option value="' + o.v + '"' + (o.v === val ? ' selected' : '') + '>' + esc(o.l) + ' — ' + esc(o.r) + '</option>').join('');
        return h + '</optgroup></select>';
    }
    function hint(val) {
        const o = find(val);
        if (!o) return ['warn', 'Choose who approves this step.'];
        if (o.n === 0) return ['warn', 'Nobody has this role yet — invite someone or pick a person.'];
        if (!o.sees) return ['warn', 'This role can\'t see payroll. Give it "See payroll runs" in Team & roles.'];
        return ['', o.n !== undefined ? o.n + ' ' + (o.n === 1 ? 'person' : 'people') + ' can approve this step.' : 'Only this person can approve this step.'];
    }
    function render() {
        box.innerHTML = steps.map((s, i) => {
            const [cls, txt] = hint(s.who);
            return '<div class="pr-step-edit">' +
                '<span class="n">' + (i + 1) + '</span>' +
                '<input class="pp-input" name="steps[' + i + '][name]" value="' + esc(s.name) + '" placeholder="e.g. Finance review" data-i="' + i + '" required>' +
                select(i, s.who) +
                '<div class="tools">' +
                    '<button type="button" data-up="' + i + '" title="Move up"' + (i === 0 ? ' disabled' : '') + '><i class="bi bi-arrow-up"></i></button>' +
                    '<button type="button" data-down="' + i + '" title="Move down"' + (i === steps.length - 1 ? ' disabled' : '') + '><i class="bi bi-arrow-down"></i></button>' +
                    '<button type="button" class="del" data-del="' + i + '" title="Remove"' + (steps.length === 1 ? ' disabled' : '') + '><i class="bi bi-trash"></i></button>' +
                '</div>' +
                '<div class="hint ' + cls + '">' + esc(txt) + '</div></div>';
        }).join('');
        document.getElementById('prChainPreview').innerHTML = '<span>Prepared</span><i class="bi bi-arrow-right"></i>' +
            steps.map(s => '<span>' + esc(s.name || 'Step') + '</span><i class="bi bi-arrow-right"></i>').join('') + '<span class="lock"><i class="bi bi-lock-fill"></i> Locked</span>';
        document.getElementById('prAdd').disabled = steps.length >= 6;
    }
    function preview() {
        document.getElementById('prChainPreview').innerHTML = '<span>Prepared</span><i class="bi bi-arrow-right"></i>' +
            steps.map(s => '<span>' + esc(s.name || 'Step') + '</span><i class="bi bi-arrow-right"></i>').join('') + '<span class="lock"><i class="bi bi-lock-fill"></i> Locked</span>';
    }
    box.addEventListener('input', (e) => { if (e.target.tagName === 'INPUT' && e.target.dataset.i !== undefined) { steps[+e.target.dataset.i].name = e.target.value; preview(); } });
    box.addEventListener('change', (e) => { if (e.target.tagName === 'SELECT') { steps[+e.target.dataset.i].who = e.target.value; render(); } });
    box.addEventListener('click', (e) => {
        const b = e.target.closest('button'); if (!b) return;
        if (b.dataset.up) { const i = +b.dataset.up; [steps[i - 1], steps[i]] = [steps[i], steps[i - 1]]; }
        if (b.dataset.down) { const i = +b.dataset.down; [steps[i + 1], steps[i]] = [steps[i], steps[i + 1]]; }
        if (b.dataset.del) { steps.splice(+b.dataset.del, 1); }
        render();
    });
    document.getElementById('prAdd').addEventListener('click', () => { steps.push({ name: '', who: roleV('admin') }); render(); box.querySelector('.pr-step-edit:last-child input').focus(); });
    document.querySelectorAll('[data-preset]').forEach(b => b.addEventListener('click', () => { steps = PRESETS[b.dataset.preset](); render(); }));
    render();
})();
</script>
@endpush
