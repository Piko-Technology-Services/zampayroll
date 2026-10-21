<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head')
    <link rel="stylesheet" href="{{ asset('assets/css/zp-people.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/zp-payroll.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/zp-billing.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/zp-platform.css') }}">
</head>
<body class="pl-body">
@php
    $openTickets = \App\Models\SupportTicket::query()->where('status', 'open')->count();
    $requests = \App\Models\Company::on(\App\Support\Tenancy::landlordConnection())->whereNotNull('requested_subdomain')->count();
    $nav = [
        ['platform.dashboard', 'bi-speedometer2', 'Overview', null, 'platform.dashboard'],
        ['platform.companies', 'bi-buildings', 'Companies', $requests ?: null, 'platform.companies*'],
        ['platform.invoices', 'bi-receipt', 'Billing', null, 'platform.invoices*'],
        ['platform.tickets', 'bi-life-preserver', 'Tickets', $openTickets ?: null, 'platform.tickets*'],
        ['platform.audit', 'bi-journal-text', 'Audit log', null, 'platform.audit'],
        ['platform.settings', 'bi-sliders', 'Settings', null, 'platform.settings*'],
    ];
@endphp
<header class="pl-top">
    <a class="pl-brand" href="{{ route('platform.dashboard') }}">
        <img src="{{ asset('assets/images/logo.png') }}" alt="" width="34" height="34">
        <span><b>ZamPayroll</b><small>Admin console</small></span>
    </a>
    <nav class="pl-nav" aria-label="Admin">
        @foreach($nav as [$r, $ic, $label, $badge, $match])
            <a href="{{ route($r) }}" class="{{ request()->routeIs($match) ? 'on' : '' }}"><i class="bi {{ $ic }}"></i>{{ $label }}@if($badge)<em>{{ $badge }}</em>@endif</a>
        @endforeach
    </nav>
    <form method="POST" action="{{ route('logout') }}" class="pl-out">@csrf<span>{{ auth()->user()->name }}</span><button title="Sign out"><i class="bi bi-box-arrow-right"></i></button></form>
</header>
<div class="zp-flag" style="border-radius:0;height:4px"><span></span><span></span><span></span><span></span></div>
<main class="pl-main pr pp">
    @if(session('success'))<div class="pl-alert ok"><i class="bi bi-check-circle"></i> {{ session('success') }}</div>@endif
    @if(session('error'))<div class="pl-alert bad"><i class="bi bi-exclamation-triangle"></i> {{ session('error') }}</div>@endif
    @if($errors->any())<div class="pl-alert bad"><i class="bi bi-exclamation-triangle"></i> {{ $errors->first() }}</div>@endif
    @yield('content')
</main>
<script src="{{ asset('assets/js/zp-people.js') }}"></script>
@stack('scripts')
</body>
</html>
