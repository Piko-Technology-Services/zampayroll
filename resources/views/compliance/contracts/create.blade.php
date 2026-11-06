@extends('layouts.app')
@section('title', 'New contract · ZamPayroll')
@include('compliance.partials.head')
@section('content')
<div class="pr pp cc">
    <div class="pr-head"><div><h1>New employment contract</h1><p>Pick the employee and the kind of contract. ZamPayroll fills in everything it already knows from their record and your company profile.</p></div>
        <div class="pr-head-actions"><a class="pp-btn pp-btn-ghost" href="{{ route('compliance.contracts.index') }}"><i class="bi bi-arrow-left"></i> Contracts</a></div></div>
    @unless($workflowReady)
        <div class="cc-alert warn"><i class="bi bi-diagram-3 lead"></i><div>You can draft now, but before sending you need a contract approval workflow with a final company signatory.</div><a class="go" href="{{ route('compliance.workflows.create') }}">Set it up</a></div>
    @endunless
    <form method="POST" action="{{ route('compliance.contracts.store') }}" class="pr-card" style="display:grid;gap:.9rem;max-width:760px">
        @csrf
        <div class="pp-field"><label for="employee_id">1. Employee</label>
            <select class="pp-select" id="employee_id" name="employee_id" required><option value="">Choose an employee</option>
                @foreach($employees as $e)<option value="{{ $e->id }}" @selected((string) old('employee_id', $selected) === (string) $e->id)>{{ $e->full_name }} · {{ $e->employee_id }}{{ $e->position ? ' · ' . $e->position : '' }}</option>@endforeach
            </select></div>
        <div class="pp-field"><label for="contract_type">2. Contract type</label>
            <select class="pp-select" id="contract_type" name="contract_type" required>@foreach(\App\Services\Compliance\TemplateLibrary::CONTRACT_TYPES as $k => $l)<option value="{{ $k }}" @selected(old('contract_type') === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="pp-field"><label for="template_id">3. Template</label>
            <select class="pp-select" id="template_id" name="template_id" required>
                @foreach($templates as $t)<option value="{{ $t->id }}" data-key="{{ $t->key }}">{{ $t->name }} {{ $t->isMaster() ? '(ZamPayroll)' : '(your template, v' . $t->version . ')' }}</option>@endforeach
            </select><small class="pp-muted">Templates matching the contract type are shown first.</small></div>
        <div class="pp-field"><label for="letterhead_id">4. Letterhead</label>
            <select class="pp-select" id="letterhead_id" name="letterhead_id">
                @forelse($letterheads as $lh)<option value="{{ $lh->id }}">{{ $lh->name }}{{ $lh->is_default ? ' (default)' : '' }}</option>@empty<option value="">Generated from company profile</option>@endforelse
            </select></div>
        <div><button class="pp-btn pp-btn-primary"><i class="bi bi-magic"></i> Create draft</button></div>
    </form>
</div>
@push('scripts')
<script>
(() => {
  const type = document.getElementById('contract_type'), tpl = document.getElementById('template_id');
  const sort = () => { const opts = [...tpl.options]; opts.sort((a, b) => (b.dataset.key === type.value) - (a.dataset.key === type.value)); opts.forEach(o => tpl.appendChild(o)); tpl.selectedIndex = 0; };
  type.addEventListener('change', sort); sort();
})();
</script>
@endpush
@endsection
