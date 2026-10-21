@extends('platform.layout')
@section('title', 'Audit log · ZamPayroll admin')
@section('content')
<div class="pr-head"><div><h1>Audit log</h1><p>Approvals, payouts, payroll, billing and admin actions across all companies.</p></div></div>
<form class="pl-filter" method="GET"><div class="pr-search" style="max-width:360px"><i class="bi bi-search"></i><input type="search" name="q" value="{{ request('q') }}" placeholder="Search action or description"></div></form>
<section class="pr-card">
    <div class="pr-tablewrap"><table class="pr-table pr-hist">
        <thead><tr><th>When</th><th>Company</th><th>Who</th><th>What</th><th>Action</th></tr></thead>
        <tbody>
        @forelse($logs as $l)
            <tr style="cursor:default">
                <td>{{ $l->created_at->format('j M Y H:i') }}</td>
                <td>{{ $companies[$l->company_id] ?? '—' }}</td>
                <td>{{ $l->user_name ?? 'System' }}</td>
                <td style="white-space:normal">{{ $l->summary }}</td>
                <td><code style="font-size:.74rem">{{ $l->action }}</code></td>
            </tr>
        @empty
            <tr><td colspan="5"><div class="pr-empty">No entries.</div></td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $logs->links('pagination::bootstrap-5') }}
</section>
@endsection
