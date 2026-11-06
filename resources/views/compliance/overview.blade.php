@extends('layouts.app')
@section('title', 'Compliance Centre · ZamPayroll')
@include('compliance.partials.head')
@section('content')
@php $can = fn ($p) => auth()->user()->hasPermission($p); @endphp
<div class="pr pp cc">
    <div class="pr-head">
        <div><h1>Compliance overview</h1><p>Where your employment contracts, policies and HR documents stand today, counted live from your records.</p></div>
        <div class="pr-head-actions">
            @if($can('compliance.contracts'))<a href="{{ route('compliance.contracts.create') }}" class="pp-btn pp-btn-primary"><i class="bi bi-file-earmark-plus"></i> New contract</a>@endif
            @if($can('compliance.documents'))<a href="{{ route('compliance.documents.create') }}" class="pp-btn pp-btn-ghost"><i class="bi bi-cloud-upload"></i> Upload documents</a>@endif
        </div>
    </div>

    @if($myApprovals)
        <div class="cc-alert warn"><i class="bi bi-pen lead"></i><div><b>{{ $myApprovals }} {{ \Illuminate\Support\Str::plural('document', $myApprovals) }} waiting for your approval or signature.</b></div><a class="go" href="{{ route('compliance.approvals.index') }}">Review now <i class="bi bi-arrow-right"></i></a></div>
    @endif

    <div class="cc-kpis">
        <a class="cc-kpi" href="{{ route('employees.index') }}"><b>{{ $stats['employees'] }}</b><span>Employees</span></a>
        <a class="cc-kpi" href="{{ route('compliance.reports.index', ['report' => 'with_contract']) }}"><b>{{ $stats['with_contract'] }}</b><span>With a completed contract</span></a>
        <a class="cc-kpi {{ $stats['without_contract'] ? 'warn' : '' }}" href="{{ route('compliance.reports.index', ['report' => 'without_contract']) }}"><b>{{ $stats['without_contract'] }}</b><span>Without a completed contract</span></a>
        <a class="cc-kpi" href="{{ route('compliance.contracts.index', ['status' => 'sent']) }}"><b>{{ $stats['awaiting_employee'] }}</b><span>Awaiting employee signature</span></a>
        <a class="cc-kpi" href="{{ route('compliance.contracts.index', ['status' => 'in_approval']) }}"><b>{{ $stats['awaiting_approval'] }}</b><span>Awaiting management approval</span></a>
        <a class="cc-kpi" href="{{ route('compliance.policies.index', ['filter' => 'active']) }}"><b>{{ $stats['active_policies'] }}</b><span>Active policies</span></a>
        <a class="cc-kpi" href="{{ route('compliance.policies.index', ['filter' => 'in_approval']) }}"><b>{{ $stats['policies_awaiting'] }}</b><span>Policies awaiting approval</span></a>
        <a class="cc-kpi {{ $stats['unacknowledged'] ? 'warn' : '' }}" href="{{ route('compliance.acknowledgements') }}"><b>{{ $stats['unacknowledged'] }}</b><span>Employees yet to acknowledge</span></a>
        <a class="cc-kpi {{ $stats['policies_review'] ? 'warn' : '' }}" href="{{ route('compliance.policies.index', ['filter' => 'review']) }}"><b>{{ $stats['policies_review'] }}</b><span>Policies due for review</span></a>
        <a class="cc-kpi {{ $stats['docs_expiring'] ? 'warn' : '' }}" href="{{ route('compliance.documents.index', ['expiry' => 'soon']) }}"><b>{{ $stats['docs_expiring'] }}</b><span>Documents expiring in 30 days</span></a>
        <a class="cc-kpi {{ $stats['docs_expired'] ? 'danger' : '' }}" href="{{ route('compliance.documents.index', ['expiry' => 'expired']) }}"><b>{{ $stats['docs_expired'] }}</b><span>Expired documents</span></a>
        <a class="cc-kpi" href="{{ route('compliance.documents.index') }}"><b>{{ $stats['documents'] }}</b><span>Documents in the repository</span></a>
    </div>

    <div class="pr-layout">
        <section class="pr-card">
            <div class="pr-card-head"><div><h2>Needs attention</h2><div class="sub">Hiding an item only hides it for you until its number changes. It does not change anything.</div></div></div>
            @forelse($alerts as $a)
                <div class="cc-alert {{ $a['tone'] }}">
                    <i class="bi {{ $a['tone'] === 'danger' ? 'bi-exclamation-octagon' : ($a['tone'] === 'warn' ? 'bi-exclamation-triangle' : 'bi-info-circle') }} lead"></i>
                    <div>{{ $a['text'] }}</div>
                    <a class="go" href="{{ $a['url'] }}">View <i class="bi bi-arrow-right"></i></a>
                    <form method="POST" action="{{ route('compliance.alerts.dismiss', $a['key']) }}">@csrf<button title="Hide" aria-label="Hide this item"><i class="bi bi-x-lg"></i></button></form>
                </div>
            @empty
                <div class="pr-empty"><i class="bi bi-check2-circle"></i>Nothing needs your attention right now.</div>
            @endforelse
            <p class="cc-note" style="margin-top:.8rem"><i class="bi bi-info-circle"></i> These figures describe the documents recorded in ZamPayroll. They are not a legal assessment, and having or not having a document here does not by itself make a company compliant or non-compliant.</p>
        </section>
        <aside class="pr-side">
            <section class="pr-card">
                <div class="pr-card-head" style="margin-bottom:.6rem"><h2 style="font-size:1.05rem">Recent activity</h2></div>
                <ul class="cc-timeline">
                    @forelse($activity as $a)
                        <li><i class="bi bi-dot"></i><div>{{ $a->summary }}<small>{{ $a->user_name ?? 'ZamPayroll' }} · {{ $a->created_at->diffForHumans() }}</small></div></li>
                    @empty
                        <li class="pp-muted">No activity yet.</li>
                    @endforelse
                </ul>
            </section>
        </aside>
    </div>
</div>
@endsection
