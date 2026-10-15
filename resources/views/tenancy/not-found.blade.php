@extends('layouts.onboarding')
@section('title', 'Workspace not found')
@section('aside')
    <h2>Looking for your company's workspace?</h2>
    <p class="lead-copy">Each larger company has its own address, like <em>yourcompany.{{ \App\Support\Tenancy::centralDomain() }}</em>.</p>
@endsection
@section('content')
<div class="ob-status" style="margin-top:3rem">
    <div class="ob-status-icon copper" aria-hidden="true">🧭</div>
    <h1 class="ob-title">There's no workspace at “{{ $subdomain }}”</h1>
    <p class="ob-sub">Check the address for typos, or sign in on the main site — we'll take you to the right workspace automatically.</p>
    <a class="zp-btn zp-btn-primary" href="{{ \App\Support\Tenancy::centralUrl('/login') }}">Sign in on {{ \App\Support\Tenancy::centralDomain() }}</a>
</div>
@endsection
