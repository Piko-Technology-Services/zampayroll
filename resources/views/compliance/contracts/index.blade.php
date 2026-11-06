@extends('layouts.app')
@section('title', 'Employment contracts · ZamPayroll')
@include('compliance.partials.head')
@section('content')
@php $T = \App\Services\Compliance\TemplateLibrary::CONTRACT_TYPES; @endphp
<div class="pr pp cc">
    <div class="pr-head"><div><h1>Employment contracts</h1><p>Generate, send, sign and store contracts. Executed contracts are locked and filed in each employee's records.</p></div>
        <div class="pr-head-actions">@if(auth()->user()->hasPermission('compliance.contracts'))<a class="pp-btn pp-btn-primary" href="{{ route('compliance.contracts.create') }}"><i class="bi bi-file-earmark-plus"></i> New contract</a>@endif</div></div>
    <div class="cc-chips">
        <a href="{{ route('compliance.contracts.index') }}" class="{{ ! $status ? 'on' : '' }}">All<b>{{ $counts->sum() }}</b></a>
        @foreach(['draft' => 'Drafts', 'sent' => 'With employee', 'in_approval' => 'In approval', 'returned' => 'Returned', 'executed' => 'Executed', 'superseded' => 'Superseded', 'cancelled' => 'Cancelled'] as $k => $l)
            @php $n = $k === 'returned' ? ($counts['declined'] ?? 0) + ($counts['changes'] ?? 0) + ($counts['rejected'] ?? 0) : ($counts[$k] ?? 0); @endphp
            <a href="{{ route('compliance.contracts.index', ['status' => $k]) }}" class="{{ $status === $k ? 'on' : '' }}">{{ $l }}<b>{{ $n }}</b></a>
        @endforeach
    </div>
    <section class="pr-card">
        <form class="cc-filters" method="GET">
            <input type="hidden" name="status" value="{{ $status }}">
            <div class="pp-field" style="flex:1"><label>Search</label><input class="pp-input" name="q" value="{{ request('q') }}" placeholder="Name, employee number or reference"></div>
            <div class="pp-field"><label>Type</label><select class="pp-select" name="type"><option value="">All types</option>@foreach($T as $k => $l)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $l }}</option>@endforeach</select></div>
            <button class="pp-btn pp-btn-ghost">Filter</button>
        </form>
        <div class="cc-wrap"><table class="cc-table">
            <thead><tr><th>Reference</th><th>Employee</th><th>Type</th><th>Status</th><th>Version</th><th>Updated</th></tr></thead>
            <tbody>
            @forelse($contracts as $c)
                <tr>
                    <td><a class="row-link" href="{{ route('compliance.contracts.show', $c) }}">{{ $c->reference }}</a></td>
                    <td>{{ $c->employee?->full_name }}<br><small class="pp-muted">{{ $c->employee?->position }}</small></td>
                    <td>{{ $T[$c->contract_type] ?? $c->contract_type }}</td>
                    <td>@include('compliance.partials.status', ['status' => $c->status, 'label' => $c->statusLabel()])</td>
                    <td>v{{ $c->current_version }}</td>
                    <td><small>{{ $c->updated_at->diffForHumans() }}</small></td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="pr-empty"><i class="bi bi-file-earmark-person"></i>No contracts here yet.</div></td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $contracts->links() }}
    </section>
</div>
@endsection
