<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="ZamPayroll - Payroll Management System">
    <title>@yield('title', 'Auth') | ZamPayroll - Payroll Management System</title>

    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/bootstrap-icons/bootstrap-icons.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/auth.css') }}">
    
</head>

<body class="auth-body">

    {{-- Theme Toggle --}}
    <button class="icon-button theme-toggle auth-theme-toggle" type="button" data-theme-toggle>
        <i class="bi bi-moon-stars" data-theme-icon></i>
    </button>

    <main class="auth-page">
        <section class="auth-card @yield('card_size')" id="page-content">

            {{-- =====================================================
               LEFT — FORM SIDE
            ====================================================== --}}
            <div class="auth-form-side">

                <a class="auth-brand" href="#">
                    <img src="http://misc.zampayroll.com/logo.png" width="36" height="36" alt="ZamPayroll Logo"
                        class="brand-logo">

                    <span>
                        <strong>ZamPayroll</strong>
                        <small>@yield('subtitle', 'Payroll Management System')</small>
                    </span>
                </a>

                <div class="auth-form-content">
                    @yield('content')
                </div>

                <div class="auth-footer mt-3">
                    @yield('footer')
                </div>

            </div>

            {{-- =====================================================
               RIGHT — IMAGE / SLIDER SIDE
               Photos live in public/images/payroll/1.jpg … 6.jpg
            ====================================================== --}}
            @php
                $authSlides = [
                    ['image' => 'assets/images/payroll/1.jpg', 'icon' => 'bi-graph-up-arrow', 'tag' => 'Automated', 'title' => 'Payroll that runs itself', 'text' => 'PAYE, NAPSA and pension calculated automatically on every payslip.'],
                    ['image' => 'assets/images/payroll/2.jpg', 'icon' => 'bi-shield-check', 'tag' => 'Compliant', 'title' => 'Built for compliance', 'text' => 'Stay aligned with ZRA and NAPSA statutory requirements, always.'],
                    ['image' => 'assets/images/payroll/3.jpg', 'icon' => 'bi-people', 'tag' => 'All-in-one', 'title' => 'One workspace, every employee', 'text' => 'Manage contracts, leave and payments from a single dashboard.'],
                    ['image' => 'assets/images/payroll/4.jpg', 'icon' => 'bi-lock', 'tag' => 'Secure', 'title' => 'Bank-grade security', 'text' => 'Your payroll data stays encrypted and protected at every step.'],
                    ['image' => 'assets/images/payroll/5.jpg', 'icon' => 'bi-bar-chart', 'tag' => 'Insights', 'title' => 'Clear payroll insights', 'text' => 'Track costs, trends and headcount at a glance.'],
                    ['image' => 'assets/images/payroll/6.jpg', 'icon' => 'bi-headset', 'tag' => 'Support', 'title' => 'Support when you need it', 'text' => 'A dedicated team ready to help your business grow.'],
                ];
            @endphp

            <div class="auth-image-side d-none d-lg-flex">

                <div id="authCarousel" class="carousel slide auth-carousel" data-bs-ride="carousel"
                    data-bs-interval="5000">

                    <div class="carousel-inner h-100">

                        @foreach ($authSlides as $index => $slide)
                            <div class="carousel-item h-100 @if ($index === 0) active @endif">
                                <div class="auth-slide" style="background-image: url('{{ asset($slide['image']) }}')">
                                    <div class="auth-slide-icon">
                                        <i class="bi {{ $slide['icon'] }}"></i>
                                    </div>
                                    <span class="auth-slide-tag">{{ $slide['tag'] }}</span>
                                    <h3>{{ $slide['title'] }}</h3>
                                    <p>{{ $slide['text'] }}</p>
                                </div>
                            </div>
                        @endforeach

                    </div>

                    <div class="carousel-indicators auth-carousel-indicators">
                        @foreach ($authSlides as $index => $slide)
                            <button type="button" data-bs-target="#authCarousel" data-bs-slide-to="{{ $index }}"
                                class="@if ($index === 0) active @endif"
                                @if ($index === 0) aria-current="true" @endif
                                aria-label="Slide {{ $index + 1 }}"></button>
                        @endforeach
                    </div>

                </div>

            </div>

        </section>
    </main>

    <script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/js/main.js') }}"></script>

    <script>
    // Show loader manually
    function showLoader() {
        document.getElementById('global-loader').classList.remove('d-none');
    }

    // Hide loader
    function hideLoader() {
        document.getElementById('global-loader').classList.add('d-none');
    }

    // Show loader on any form submit
    document.addEventListener('submit', function () {
        showLoader();
    });

    document.addEventListener("click", function (e) {
        const link = e.target.closest("a");

        if (link && link.href && !link.target && !link.hasAttribute("download")) {
            e.preventDefault();

            document.getElementById("page-content").style.opacity = "0";
            document.getElementById("page-content").style.transform = "translateY(6px)";

            setTimeout(() => {
                window.location = link.href;
            }, 150);
        }
    });
    </script>

    <div id="global-loader" class="global-loader d-none">
        <div class="loader-box">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2 mb-0">Loading...</p>
        </div>
    </div>

</body>

</html>