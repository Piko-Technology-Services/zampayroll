@extends('platform.layout')
@section('title', 'Companies · ZamPayroll admin')
@section('content')
<div class="pr-head"><div><h1>Companies</h1><p>{{ $companies->total() }} client {{ \Illuminate\Support\Str::plural('company', $companies->total()) }}</p></div></div>
<form class="pl-filter" method="GET">
    <div class="pr-search" style="max-width:320px"><i class="bi bi-search"></i><input type="search" name="q" value="{{ $q }}" placeholder="Name, code or email"></div>
    @foreach(['' => 'All', 'trialing' => 'Trial', 'active' => 'Active', 'past_due' => 'Overdue', 'suspended' => 'Limited', 'cancelled' => 'Cancelled'] as $v => $l)
        <a href="{{ route('platform.companies', array_filter(['q' => $q, 'status' => $v])) }}" class="{{ (string) $status === $v ? 'on' : '' }}">{{ $l }}</a>
    @endforeach
</form>
<section class="pr-card">
    <div class="pr-tablewrap">
        <table class="pr-table pr-hist">
            <thead><tr><th>Company</th><th>Plan</th><th>Status</th><th style="text-align:right">Employees</th><th style="text-align:right">Users</th><th>Trial / paid until</th><th>Workspace</th><th>Joined</th></tr></thead>
            <tbody>
            @foreach($companies as $c)
                <tr onclick="location.href='{{ route('platform.companies.show', $c->id) }}'">
                    <td><b>{{ $c->name }}</b><br><small class="pp-muted">{{ $c->company_code }} · {{ $c->email }}</small></td>
                    <td>{{ $c->plan ? ucfirst($c->plan) : '—' }}<br><small class="pp-muted">{{ $c->billing_type }}</small></td>
                    <td><span class="pl-tag {{ $c->subscription_status }}">{{ str_replace('_', ' ', $c->subscription_status) }}</span></td>
                    <td class="num">{{ $c->isDedicated() ? '·' : ($people[$c->id] ?? 0) }}</td>
                    <td class="num">{{ $c->isDedicated() ? '·' : ($staff[$c->id] ?? 0) }}</td>
                    <td>{{ $c->subscription_status === 'trialing' ? 'Trial → ' . optional($c->trial_ends_at)->format('j M') : ($c->paid_until ? optional($c->paid_until)->format('j M Y') : '—') }}</td>
                    <td>{{ $c->isDedicated() ? $c->subdomain : 'Shared' }}@if($c->requested_subdomain)<br><span class="pl-tag past_due">wants {{ $c->requested_subdomain }}</span>@endif</td>
                    <td>{{ $c->created_at->format('j M Y') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $companies->links('pagination::bootstrap-5') }}
</section>
@endsection
