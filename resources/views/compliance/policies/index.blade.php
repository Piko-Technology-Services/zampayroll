@extends('layouts.app')
@section('title', 'Company policies · ZamPayroll')
@include('compliance.partials.head')
@section('content')
@php $can = auth()->user()->hasPermission('compliance.policies'); $LS = \App\Services\Compliance\TemplateLibrary::LEGAL_STATUS; @endphp
<div class="pr pp cc">
    <div class="pr-head"><div><h1>Company policies</h1><p>Your policies, their approval and who has acknowledged them.</p></div>
        <div class="pr-head-actions">
            <a class="pp-btn pp-btn-ghost" href="{{ route('compliance.acknowledgements') }}"><i class="bi bi-person-check"></i> Acknowledgements</a>
            @if($can)<a class="pp-btn pp-btn-primary" href="{{ route('compliance.policies.library') }}"><i class="bi bi-journal-plus"></i> Policy library</a>@endif
        </div></div>
    @unless($hasWorkflow)<div class="cc-note" style="margin-bottom:1rem"><i class="bi bi-info-circle"></i> You have no policy approval workflow, so HR publishes policies directly. <a href="{{ route('compliance.workflows.create', ['for' => 'policy']) }}">Add one</a> to require management approval first.</div>@endunless
    <div class="cc-chips">
        @foreach(['' => 'All', 'active' => 'Active', 'draft' => 'Drafts', 'in_approval' => 'In approval', 'changes' => 'Changes requested', 'review' => 'Review due', 'archived' => 'Archived'] as $k => $l)
            <a href="{{ route('compliance.policies.index', $k ? ['filter' => $k] : []) }}" class="{{ (string) $filter === $k ? 'on' : '' }}">{{ $l }}</a>
        @endforeach
    </div>
    <section class="pr-card">
        <div class="cc-wrap"><table class="cc-table">
            <thead><tr><th>Policy</th><th>Legal status</th><th>Status</th><th>Active version</th><th>Acknowledged</th><th>Review</th></tr></thead>
            <tbody>
            @forelse($policies as $p)
                <tr>
                    <td><a class="row-link" href="{{ route('compliance.policies.show', $p) }}">{{ $p->title }}</a><br><small class="pp-muted">{{ $p->reference }}</small></td>
                    <td>@if($p->legal_status)<span class="cc-pill {{ $p->legal_status === 'required' ? 'red' : ($p->legal_status === 'conditional' ? 'amber' : '') }}">{{ $LS[$p->legal_status] }}</span>@endif</td>
                    <td>@include('compliance.partials.status', ['status' => $p->status, 'label' => $p->statusLabel()]) @if($p->hasPendingRevision())<span class="cc-pill blue">Revision v{{ $p->current_version }}</span>@endif</td>
                    <td>{{ $p->active_version ? 'v' . $p->active_version : '—' }}</td>
                    <td>@if($ack[$p->id])<b>{{ $ack[$p->id]['done'] }}</b> / {{ $ack[$p->id]['of'] }}@else<span class="pp-muted">—</span>@endif</td>
                    <td><small class="{{ $p->review_date && $p->review_date->lte(now()->addDays(30)) ? 'text-danger' : '' }}">{{ optional($p->review_date)->format('j M Y') }}</small></td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="pr-empty"><i class="bi bi-journal"></i>No policies yet. Start from the <a href="{{ route('compliance.policies.library') }}">policy library</a>.</div></td></tr>
            @endforelse
            </tbody>
        </table></div>
    </section>
</div>
@endsection
