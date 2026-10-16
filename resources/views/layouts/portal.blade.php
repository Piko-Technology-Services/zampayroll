@php
    $portalUser = auth()->user();
    $portalEmployee = $portalUser->employee;
    $portalCompany = \App\Support\Tenancy::company();
    $initials = collect(explode(' ', $portalUser->name))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
    $nav = [
        ['portal.dashboard', 'bi-house-door', 'Home'],
        ['portal.payslips', 'bi-receipt', 'Payslips'],
        ['portal.leave', 'bi-calendar2-heart', 'Leave'],
        ['portal.overtime', 'bi-alarm', 'Overtime'],
        ['portal.loans', 'bi-cash-coin', 'Loans'],
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'My portal') | {{ $portalCompany?->name ?? 'ZamPayroll' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/bootstrap-icons/bootstrap-icons.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/zp-brand.css') }}">
    @stack('styles')
</head>
<body class="zp-body">

<header class="pt-top zp-on-dark">
    <div class="pt-top-inner">
        <a class="zp-brand" href="{{ route('portal.dashboard') }}">
            <img src="{{ asset('assets/images/logo.png') }}" alt="">
            <span>
                <span class="zp-brand-word"><b>Zam</b>Payroll</span>
                <span class="pt-company">{{ $portalCompany?->name }}</span>
            </span>
        </a>

        <nav class="pt-nav" aria-label="Portal">
            @foreach($nav as [$route, $icon, $label])
                <a href="{{ route($route) }}" class="{{ request()->routeIs($route . '*') ? 'active' : '' }}"><i class="bi {{ $icon }}"></i>{{ $label }}</a>
            @endforeach
        </nav>

        <div style="margin-left:auto">@include('partials.translate', ['variant' => 'dark'])</div>

        <div class="pt-user dropdown" style="margin-left:.6rem">
            <button class="pt-avatar" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu">{{ $initials }}</button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="border-radius:14px;min-width:220px">
                <li class="px-3 py-2">
                    <div class="fw-semibold">{{ $portalUser->name }}</div>
                    <div class="small text-muted">{{ $portalEmployee?->position }}</div>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="{{ route('portal.profile') }}"><i class="bi bi-person me-2"></i>My profile</a></li>
                <li><a class="dropdown-item" href="{{ route('portal.password.edit') }}"><i class="bi bi-key me-2"></i>Change password</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">@csrf
                        <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Sign out</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>

<main class="pt-main">
    @if(session('success'))
        <div class="zp-alert zp-alert-success"><i class="bi bi-check-circle"></i><div>{{ session('success') }}</div></div>
    @endif
    @if(session('error'))
        <div class="zp-alert zp-alert-error"><i class="bi bi-exclamation-circle"></i><div>{{ session('error') }}</div></div>
    @endif

    @yield('content')
</main>

<nav class="pt-tabbar" aria-label="Portal">
    @foreach($nav as [$route, $icon, $label])
        <a href="{{ route($route) }}" class="{{ request()->routeIs($route . '*') ? 'active' : '' }}"><i class="bi {{ $icon }}"></i>{{ $label }}</a>
    @endforeach
</nav>

<script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('assets/js/zp-confetti.js') }}"></script>
@if(session('celebrate'))
    <script>window.addEventListener('load', () => ZPConfetti.burst());</script>
@endif
@stack('scripts')
</body>
</html>
