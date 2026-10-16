@php
    $site = config('site');
    $navItems = [
        ['site.features', 'Features'],
        ['site.ai', 'AI assistant'],
        ['site.pricing', 'Pricing'],
        ['home', 'Contact', '#demo'],
    ];
    $shareUrl = urlencode(url()->current());
    $shareText = urlencode(($seo['title'] ?? 'ZamPayroll') . ' — ');
    $socialIcons = ['facebook' => 'bi-facebook', 'instagram' => 'bi-instagram', 'tiktok' => 'bi-tiktok', 'linkedin' => 'bi-linkedin', 'youtube' => 'bi-youtube', 'x' => 'bi-twitter-x'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('partials.seo', ['seo' => $seo ?? []])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/vendors/bootstrap-icons/bootstrap-icons.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/zp-site.css') }}">
    @stack('head')
</head>
<body class="site">
<a class="skip" href="#main">Skip to content</a>

<header class="s-head" id="sHead">
    <div class="wrap s-head-in">
        <a class="s-brand" href="{{ route('home') }}" aria-label="ZamPayroll home" translate="no">
            <img src="{{ asset('assets/images/logo.png') }}" alt="" width="36" height="36">
            <span><b>Zam</b>Payroll</span>
        </a>
        <nav class="s-nav" id="sNav" aria-label="Main">
            @foreach($navItems as $item)
                <a href="{{ route($item[0]) . ($item[2] ?? '') }}" @if(empty($item[2]) && request()->routeIs($item[0])) aria-current="page" @endif>{{ $item[1] }}</a>
            @endforeach
            <a class="show-sm" href="{{ route('login') }}">Sign in</a>
            <div class="show-sm" style="padding:.4rem .75rem">@include('partials.translate', ['variant' => 'light'])</div>
        </nav>
        <div class="s-head-cta">
            <span class="hide-sm">@include('partials.translate', ['variant' => 'light'])</span>
            <a class="btn btn-line btn-sm" href="{{ route('login') }}">Sign in</a>
            <a class="btn btn-primary btn-sm" href="{{ route('register') }}">Start free</a>
            <button class="s-menu-btn" type="button" aria-controls="sNav" aria-expanded="false" aria-label="Menu" onclick="const n=document.getElementById('sNav');n.classList.toggle('open');this.setAttribute('aria-expanded',n.classList.contains('open'))"><i class="bi bi-list"></i></button>
        </div>
    </div>
</header>

<main id="main">
    @yield('content')
</main>

<div class="foot-flag" aria-hidden="true"></div>
<footer class="s-foot">
    <div class="wrap">
        <div class="foot-grid">
            <div class="foot-brand">
                <a class="s-brand" href="{{ route('home') }}" style="color:#fff" translate="no"><img src="{{ asset('assets/images/logo.png') }}" alt="" width="36" height="36"><span><b>Zam</b>Payroll</span></a>
                <p>HR and payroll built for Zambian businesses — PAYE, NAPSA and NHIMA handled, payslips on every phone.</p>
                @php $socials = array_filter($site['social']); @endphp
                @if($socials)
                    <h4 style="margin-top:1.2rem">Follow us</h4>
                    <div class="social">
                        @foreach($socials as $net => $link)
                            <a href="{{ $link }}" target="_blank" rel="noopener me" aria-label="ZamPayroll on {{ ucfirst($net) }}"><i class="bi {{ $socialIcons[$net] ?? 'bi-link-45deg' }}"></i></a>
                        @endforeach
                    </div>
                @endif
            </div>
            <div>
                <h4>Product</h4>
                <ul>
                    <li><a href="{{ route('site.features') }}">Features</a></li>
                    <li><a href="{{ route('site.ai') }}">AI assistant</a></li>
                    <li><a href="{{ route('site.features') }}#portal">Employee self-service</a></li>
                    <li><a href="{{ route('site.features') }}#compliance">ZRA, NAPSA &amp; NHIMA</a></li>
                    <li><a href="{{ route('site.pricing') }}">Pricing</a></li>
                </ul>
            </div>
            <div>
                <h4>Get started</h4>
                <ul>
                    <li><a href="{{ route('register') }}">Create a free workspace</a></li>
                    <li><a href="{{ route('login') }}">Sign in</a></li>
                    <li><a href="{{ route('home') }}#demo">Book a demo</a></li>
                    <li><a href="{{ route('site.pricing') }}#resellers">Become a reseller</a></li>
                </ul>
            </div>
            <div>
                <h4>Legal</h4>
                <ul>
                    <li><a href="{{ route('site.terms') }}">Terms of service</a></li>
                    <li><a href="{{ route('site.privacy') }}">Privacy policy</a></li>
                    <li><a href="{{ url('/sitemap.xml') }}">Sitemap</a></li>
                </ul>
            </div>
            <div>
                <h4>Talk to us</h4>
                <ul>
                    <li><a href="mailto:{{ $site['contact']['email'] }}"><i class="bi bi-envelope"></i> {{ $site['contact']['email'] }}</a></li>
                    <li><a href="tel:{{ preg_replace('/\s+/', '', $site['contact']['phone']) }}"><i class="bi bi-telephone"></i> {{ $site['contact']['phone'] }}</a></li>
                    <li><a href="https://wa.me/{{ $site['contact']['whatsapp'] }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> WhatsApp us</a></li>
                    <li><i class="bi bi-geo-alt"></i> {{ $site['contact']['address'] }}</li>
                    <li><i class="bi bi-clock"></i> {{ $site['contact']['hours'] }}</li>
                </ul>
            </div>
        </div>

        <div class="foot-bottom">
            <span>&copy; {{ date('Y') }} ZamPayroll. Made in Zambia.</span>
            <div class="share" style="--s-line:rgba(255,255,255,.15)">
                <span style="color:#B5C2B9">Share</span>
                <a href="https://wa.me/?text={{ $shareText . $shareUrl }}" target="_blank" rel="noopener" aria-label="Share on WhatsApp" style="background:transparent;color:#fff"><i class="bi bi-whatsapp"></i></a>
                <a href="https://www.facebook.com/sharer/sharer.php?u={{ $shareUrl }}" target="_blank" rel="noopener" aria-label="Share on Facebook" style="background:transparent;color:#fff"><i class="bi bi-facebook"></i></a>
                <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ $shareUrl }}" target="_blank" rel="noopener" aria-label="Share on LinkedIn" style="background:transparent;color:#fff"><i class="bi bi-linkedin"></i></a>
                <a href="https://twitter.com/intent/tweet?url={{ $shareUrl }}&text={{ $shareText }}" target="_blank" rel="noopener" aria-label="Share on X" style="background:transparent;color:#fff"><i class="bi bi-twitter-x"></i></a>
                <button type="button" aria-label="Copy link (for Instagram or TikTok)" title="Copy link — paste it in Instagram or TikTok" style="background:transparent;color:#fff"
                        onclick="navigator.clipboard.writeText(location.href);this.innerHTML='<i class=\'bi bi-check2\'></i>'"><i class="bi bi-link-45deg"></i></button>
            </div>
        </div>
    </div>
</footer>

<a class="wa-float" href="https://wa.me/{{ $site['contact']['whatsapp'] }}?text={{ urlencode('Hi ZamPayroll, I would like to know more about your payroll system.') }}" target="_blank" rel="noopener" aria-label="Chat with us on WhatsApp">
    <i class="bi bi-whatsapp"></i><span>Chat with us</span>
</a>

<script>
    (function () {
        const h = document.getElementById('sHead');
        const on = () => h.classList.toggle('scrolled', window.scrollY > 8);
        on(); window.addEventListener('scroll', on, { passive: true });
    })();
</script>
@stack('scripts')
</body>
</html>
