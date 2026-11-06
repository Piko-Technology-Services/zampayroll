@extends('layouts.app')
@section('title', $policy->title . ' · ZamPayroll')
@include('compliance.partials.head')
@section('content')
@php $can = auth()->user()->hasPermission('compliance.policies'); $LS = \App\Services\Compliance\TemplateLibrary::LEGAL_STATUS; $done = $acks->count(); @endphp
<div class="pr pp cc">
    <div class="pr-head"><div><h1>{{ $policy->title }}</h1>
        <p>{{ $policy->reference }} · @include('compliance.partials.status', ['status' => $policy->status, 'label' => $policy->statusLabel()]) @if($policy->legal_status)<span class="cc-pill {{ $policy->legal_status === 'required' ? 'red' : '' }}">{{ $LS[$policy->legal_status] }}</span>@endif</p></div>
        <div class="pr-head-actions">
            <a class="pp-btn pp-btn-ghost" href="{{ route('compliance.policies.preview', $policy) }}" target="_blank"><i class="bi bi-eye"></i> Preview</a>
            <a class="pp-btn pp-btn-ghost" href="{{ route('compliance.policies.pdf', $policy) }}"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
            @if($can)<a class="pp-btn pp-btn-ghost" href="{{ route('compliance.policies.docx', $policy) }}"><i class="bi bi-file-earmark-word"></i> Word</a>@endif
            @if($can && in_array($policy->status, ['draft', 'changes']))<a class="pp-btn pp-btn-primary" href="{{ route('compliance.policies.edit', $policy) }}"><i class="bi bi-pencil"></i> Edit</a>@endif
        </div></div>
    @if($policy->hasPendingRevision())<div class="cc-note" style="margin-bottom:1rem">Version {{ $policy->active_version }} is active for employees while version {{ $policy->current_version }} is being prepared.</div>@endif
    <div class="pr-layout">
        <div class="cc-paper scroll">{!! $body !!}</div>
        <aside class="pr-side">
            @if($can)
            <section class="pr-card" style="display:grid;gap:.5rem">
                @if(in_array($policy->status, ['draft', 'changes']))
                    <form method="POST" action="{{ route('compliance.policies.submit', $policy) }}">@csrf<button class="pp-btn pp-btn-primary" style="width:100%"><i class="bi bi-send-check"></i> {{ $hasWorkflow ? 'Submit for approval' : 'Publish now' }}</button></form>
                @elseif($policy->status === 'active')
                    <form method="POST" action="{{ route('compliance.policies.revise', $policy) }}" style="display:grid;gap:.4rem">@csrf
                        <input class="pp-input" name="change_reason" required maxlength="255" placeholder="Why are you updating it?">
                        <button class="pp-btn pp-btn-ghost"><i class="bi bi-pencil-square"></i> Start a revision</button></form>
                @endif
                @if($policy->status !== 'archived')<form method="POST" action="{{ route('compliance.policies.archive', $policy) }}" onsubmit="return confirm('Archive this policy? Employees will no longer see it. Versions and acknowledgements are kept.')">@csrf<button class="pp-btn pp-btn-ghost pp-btn-sm" style="color:var(--zp-red)">Archive</button></form>@endif
            </section>
            @endif
            @if($policy->legal_status && $policy->template?->legal_note)<p class="cc-note legal">{{ $policy->template->legal_note }}</p>@endif
            @if($ackVersion)
            <section class="pr-card">
                <div class="pr-card-head" style="margin-bottom:.4rem"><h2 style="font-size:1.05rem">Acknowledgements (v{{ $ackVersion }})</h2><span class="cc-pill {{ $done >= $workforce->count() ? 'green' : 'amber' }}">{{ $done }} / {{ $workforce->count() }}</span></div>
                <div style="max-height:260px;overflow:auto">
                    @foreach($workforce as $e)
                        <div style="display:flex;justify-content:space-between;font-size:.85rem;padding:.25rem 0;border-bottom:1px solid var(--zp-line)"><span>{{ $e->full_name }}</span>
                            @if(isset($acks[$e->id]))<span style="color:var(--zp-green)"><i class="bi bi-check2"></i> {{ $acks[$e->id]->acknowledged_at->format('j M') }}</span>@else<span class="pp-muted">Outstanding</span>@endif</div>
                    @endforeach
                </div>
            </section>
            @endif
            @if($approvals->isNotEmpty())
            <section class="pr-card"><h2 style="font-size:1.05rem;margin-bottom:.4rem">Approvals</h2>
                <div class="cc-steps">@foreach($approvals->where('round', $policy->round) as $a)<div class="cc-step {{ $a->status === 'approved' ? 'done' : ($a->status === 'pending' ? 'now' : (in_array($a->status, ['rejected', 'changes']) ? 'bad' : '')) }}"><span class="n">{{ $loop->iteration }}</span><div><b>{{ $a->label }}</b><small>{{ $a->approverLabel() }} · {{ \App\Models\Compliance\ApprovalRequest::STATUSES[$a->status] }}{{ $a->acted_by_name ? ' by ' . $a->acted_by_name : '' }}</small></div></div>@endforeach</div>
            </section>
            @endif
            <section class="pr-card"><h2 style="font-size:1.05rem;margin-bottom:.4rem">Versions</h2>
                <ul class="cc-timeline">@foreach($versions as $v)<li><i class="bi bi-file-earmark-text"></i><div>v{{ $v->version }} · {{ ucfirst($v->status) }}{{ (int) $policy->active_version === $v->version ? ' (active)' : '' }}<small>{{ $v->change_reason }} · {{ ($v->published_at ?? $v->created_at)->format('j M Y') }}</small>
                    <small><a href="{{ route('compliance.policies.pdf', [$policy, 'version' => $v->version]) }}">PDF</a></small></div></li>@endforeach</ul>
            </section>
        </aside>
    </div>
</div>
@endsection
