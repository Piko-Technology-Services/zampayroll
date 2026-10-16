<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Get started') | ZamPayroll</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/vendors/bootstrap-icons/bootstrap-icons.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/zp-brand.css') }}">
    @stack('styles')
</head>
<body class="zp-body">
<div class="ob-shell">
    <aside class="ob-aside zp-on-dark">
        <a class="zp-brand" href="{{ route('home') }}">
            <img src="{{ asset('assets/images/logo.png') }}" alt="">
            <span class="zp-brand-word"><b>Zam</b>Payroll</span>
        </a>
        <div style="position:absolute;top:1.6rem;right:1.6rem;z-index:3">@include('partials.translate', ['variant' => 'dark'])</div>
        @yield('aside')
    </aside>

    <main class="ob-main">
        <div class="ob-main-inner">
            @yield('content')
        </div>
    </main>
</div>
<script src="{{ asset('assets/js/zp-confetti.js') }}"></script>
@stack('scripts')
</body>
</html>
