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
<header class="pl-top">
    <a class="pl-brand" href="{{ route('partner.dashboard') }}">
        <img src="{{ asset('assets/images/logo.png') }}" alt="" width="34" height="34">
        <span><b>ZamPayroll</b><small>Partner dashboard</small></span>
    </a>
    <nav class="pl-nav" aria-label="Partner">
        <a href="{{ route('partner.dashboard') }}" class="{{ request()->routeIs('partner.dashboard') ? 'on' : '' }}"><i class="bi bi-speedometer2"></i>Dashboard</a>
        <a href="{{ route('partner.dashboard') }}#clients"><i class="bi bi-buildings"></i>Clients</a>
        <a href="{{ route('partner.dashboard') }}#commission"><i class="bi bi-cash-coin"></i>Commission</a>
        <a href="https://wa.me/{{ config('site.contact.whatsapp') }}?text={{ urlencode('Hi ZamPayroll, partner question: ') }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i>Partner support</a>
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
