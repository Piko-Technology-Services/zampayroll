@extends('layouts.onboarding')
@section('title', 'Workspace not ready yet')
@section('aside')
    <h2>Almost ready.</h2>
    <p class="lead-copy">Dedicated workspaces open once the administrator confirms their email and the database is created.</p>
@endsection
@section('content')
<div class="ob-status" style="margin-top:3rem">
    <div class="ob-status-icon copper" aria-hidden="true">⏳</div>
    <h1 class="ob-title">{{ $company->name }}'s workspace isn't open yet</h1>
    <p class="ob-sub">
        @if($company->status === \App\Models\Company::STATUS_PENDING_VERIFICATION)
            The administrator still needs to confirm their email address. Look for the email from ZamPayroll and click the link inside.
        @elseif($company->status === \App\Models\Company::STATUS_SUSPENDED)
            This workspace has been paused. Please contact your administrator or support@zampayroll.com.
        @else
            We're still preparing the dedicated database. You'll get an email as soon as it's ready.
        @endif
    </p>
    <a class="zp-btn zp-btn-ghost" href="{{ \App\Support\Tenancy::centralUrl('/') }}">Go to {{ \App\Support\Tenancy::centralDomain() }}</a>
</div>
@endsection
