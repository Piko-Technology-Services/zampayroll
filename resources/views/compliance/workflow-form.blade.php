@extends('layouts.app')
@section('title', 'Approval workflow · ZamPayroll')
@include('compliance.partials.head')
@section('content')
@php
    $rows = old('steps', $steps->map(fn ($s) => ['label' => $s->label, 'approver' => $s->approver_type . ':' . ($s->approver_type === 'user' ? $s->approver_user_id : $s->approver_role_key), 'action' => $s->action, 'delegate_user_id' => $s->delegate_user_id])->all());
    if (! $rows) {
        $rows = $workflow->applies_to === 'contract'
            ? [['label' => 'HR review', 'approver' => '', 'action' => 'review', 'delegate_user_id' => null], ['label' => 'Final company signature', 'approver' => '', 'action' => 'sign', 'delegate_user_id' => null]]
            : [['label' => 'Management approval', 'approver' => '', 'action' => 'approve', 'delegate_user_id' => null]];
    }
    $appliesTo = old('applies_to', $workflow->applies_to);
@endphp
<div class="pr pp cc">
    <div class="pr-head"><div><h1>{{ $workflow->exists ? 'Edit' : 'New' }} {{ $appliesTo === 'contract' ? 'contract' : 'policy' }} approval workflow</h1>
        <p>{{ $appliesTo === 'contract' ? 'Starts after the employee signs. The last step signs for the company, which makes the contract fully executed.' : 'Starts when HR submits a policy. When every step approves, the policy becomes active and employees are asked to acknowledge it.' }}</p></div>
        <div class="pr-head-actions"><a class="pp-btn pp-btn-ghost" href="{{ route('compliance.settings') }}#workflows"><i class="bi bi-arrow-left"></i> Back</a></div></div>

    <form method="POST" action="{{ $workflow->exists ? route('compliance.workflows.update', $workflow) : route('compliance.workflows.store') }}" class="pr-card">
        @csrf @if($workflow->exists) @method('PUT') @endif
        <input type="hidden" name="applies_to" value="{{ $appliesTo }}">
        <div class="pp-grid" style="grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:.8rem">
            <div class="pp-field"><label for="wf_name">Name</label><input class="pp-input" id="wf_name" name="name" required maxlength="120" value="{{ old('name', $workflow->name ?? ($appliesTo === 'contract' ? 'Standard contract approval' : 'Policy approval')) }}"></div>
            @if($appliesTo === 'contract')
            <fieldset class="pp-field" style="border:0;padding:0;margin:0"><legend style="font-size:.85rem;font-weight:700">Contract types <small class="pp-muted">leave all unticked for every type</small></legend>
                <div style="display:flex;flex-wrap:wrap;gap:.3rem .9rem">@foreach($types as $k => $l)<label class="pp-toggle"><input type="checkbox" name="contract_types[]" value="{{ $k }}" @checked(in_array($k, old('contract_types', $workflow->contract_types ?? []), true))> {{ $l }}</label>@endforeach</div>
            </fieldset>
            @endif
        </div>
        <input type="hidden" name="is_active" value="0"><label class="pp-toggle" style="margin:.6rem 0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $workflow->is_active ?? true))> Active</label>

        <h2 style="font-size:1.05rem;margin:1rem 0 .5rem">Steps, in order</h2>
        @error('steps')<div class="cc-alert danger"><i class="bi bi-exclamation-octagon lead"></i><div>{{ $message }}</div></div>@enderror
        <div class="cc-steps" id="wfSteps">
            @foreach($rows as $i => $r)
            <div class="cc-wfstep" data-step>
                <span class="n" data-n>{{ $i + 1 }}</span>
                <div class="pp-field" style="margin:0"><label class="visually-hidden">Step name</label><input class="pp-input" data-f="label" name="steps[{{ $i }}][label]" value="{{ $r['label'] }}" placeholder="e.g. Managing Director approval" required maxlength="120"></div>
                <div class="pp-field" style="margin:0"><label class="visually-hidden">Who</label>
                    <select class="pp-select" data-f="approver" name="steps[{{ $i }}][approver]" required>
                        <option value="">Who does this step?</option>
                        <optgroup label="A specific person">@foreach($users as $u)<option value="user:{{ $u->id }}" @selected($r['approver'] === 'user:' . $u->id)>{{ $u->name }} ({{ $u->roleName() }})</option>@endforeach</optgroup>
                        <optgroup label="Anyone with this role">@foreach($roles as $role)<option value="role:{{ $role->key }}" @selected($r['approver'] === 'role:' . $role->key)>{{ $role->name }}</option>@endforeach</optgroup>
                    </select>@error("steps.$i.approver")<small style="color:var(--zp-red)">{{ $message }}</small>@enderror</div>
                <div class="pp-field" style="margin:0"><label class="visually-hidden">Action</label>
                    <select class="pp-select" data-f="action" name="steps[{{ $i }}][action]">@foreach(\App\Models\Compliance\ApprovalWorkflowStep::ACTIONS as $k => $l)@if($appliesTo === 'contract' || $k !== 'sign')<option value="{{ $k }}" @selected($r['action'] === $k)>{{ $l }}</option>@endif @endforeach</select></div>
                <div class="pp-field" style="margin:0"><label class="visually-hidden">Delegate</label>
                    <select class="pp-select" data-f="delegate_user_id" name="steps[{{ $i }}][delegate_user_id]"><option value="">No delegate</option>@foreach($users as $u)<option value="{{ $u->id }}" @selected((int) $r['delegate_user_id'] === $u->id)>Delegate: {{ $u->name }}</option>@endforeach</select></div>
                <button type="button" class="pp-btn pp-btn-ghost pp-btn-sm" data-remove aria-label="Remove step"><i class="bi bi-x-lg"></i></button>
            </div>
            @endforeach
        </div>
        <button type="button" class="pp-btn pp-btn-ghost pp-btn-sm" id="wfAdd" style="margin-top:.6rem"><i class="bi bi-plus-lg"></i> Add a step</button>
        <div style="display:flex;gap:.5rem;margin-top:1.2rem;flex-wrap:wrap">
            <button class="pp-btn pp-btn-primary"><i class="bi bi-check2"></i> Save workflow</button>
        </div>
    </form>
    @if($workflow->exists)
        <form method="POST" action="{{ route('compliance.workflows.destroy', $workflow) }}" onsubmit="return confirm('Remove this workflow?')" style="margin-top:.8rem">@csrf @method('DELETE')
            <button class="pp-btn pp-btn-ghost pp-btn-sm" style="color:var(--zp-red)"><i class="bi bi-trash"></i> Remove workflow</button></form>
    @endif
</div>
@push('scripts')
<script>
(() => {
  const box = document.getElementById('wfSteps');
  const renumber = () => box.querySelectorAll('[data-step]').forEach((row, i) => {
    row.querySelector('[data-n]').textContent = i + 1;
    row.querySelectorAll('[data-f]').forEach(el => el.name = `steps[${i}][${el.dataset.f}]`);
  });
  document.getElementById('wfAdd').addEventListener('click', () => {
    const rows = box.querySelectorAll('[data-step]'); if (rows.length >= 8) return;
    const c = rows[0].cloneNode(true); c.querySelectorAll('input').forEach(i => i.value = ''); c.querySelectorAll('select').forEach(s => s.selectedIndex = 0);
    const last = rows[rows.length - 1]; box.insertBefore(c, last); renumber();
  });
  box.addEventListener('click', e => { const b = e.target.closest('[data-remove]'); if (!b) return; if (box.querySelectorAll('[data-step]').length > 1) { b.closest('[data-step]').remove(); renumber(); } });
})();
</script>
@endpush
@endsection
