@php
    $cards = \App\Services\Billing\Plans::cards();
    $seo = [
        'title' => 'Pricing — payroll and HR software for Zambian businesses | ZamPayroll',
        'description' => 'Try ZamPayroll free for two months with up to 10 employees. Plans for growing businesses, medium enterprises and corporates, from K799 a month.',
        'path' => '/pricing',
        'schema' => [[
            '@context' => 'https://schema.org', '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => config('site.url') . '/'],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Pricing', 'item' => config('site.url') . '/pricing'],
            ],
        ]],
    ];
    $k = fn ($n) => 'K' . number_format((float) $n);
@endphp
@extends('layouts.site')

@section('content')
<section class="page-hero">
    <div class="wrap">
        <div class="crumbs"><a href="{{ route('home') }}">Home</a> / Pricing</div>
        <div class="pp-eyebrow">ZAMPAYROLL · PRICING</div>
        <h1>One powerful platform. Every tool your business needs.</h1>
        <p>Simplify payroll, empower your workforce and manage your entire employee lifecycle with one intelligent platform built for modern businesses in Zambia.</p>
        <p style="margin-top:14px"><span class="tag">Complete HR &amp; Payroll Suite</span> <span class="tag">AI-Powered Workforce Intelligence</span></p>
    </div>
</section>

<section class="sec" style="padding-top:32px">
    <div class="wrap">
        <h2 style="margin-bottom:6px">Choose your plan</h2>
        <p class="pp-muted" style="margin:0 0 24px">Pick the plan for your number of active employees, billed monthly or annually. Pay by mobile money, card or invoice. Prices are in Zambian Kwacha, and board members are not counted.</p>

        <div class="plans">
            {{-- Free experience --}}
            <div class="plan">
                <h3>Free Experience</h3>
                <p class="pp-muted" style="margin:0 0 8px">For startups and small businesses</p>
                <div class="price">K0</div>
                <div class="per">for 2 months</div>
                <p style="margin:0 0 14px">Up to 10 active employees. K0 for 2 months, then a paid plan from month three.</p>
                <ul>
                    <li><i class="bi bi-check2"></i> Access to core HR and payroll capabilities</li>
                    <li><i class="bi bi-check2"></i> Employee records and payroll management</li>
                    <li><i class="bi bi-check2"></i> Employee self-service</li>
                    <li><i class="bi bi-check2"></i> Limited AI assistant usage</li>
                    <li><i class="bi bi-check2"></i> Standard support</li>
                </ul>
                <p class="pp-muted" style="font-size:.86rem;margin:12px 0 16px">A paid subscription is required from month three to continue using the platform.</p>
                <a href="{{ route('register') }}" class="btn btn-line">Start free</a>
            </div>

            @foreach($cards as $key => $c)
                <div class="plan {{ $key === 'medium' ? 'feat' : '' }}">
                    @if($key === 'medium')<span class="tag">Popular Choice</span>@endif
                    <h3>{{ $c['name'] }}</h3>
                    <p class="pp-muted" style="margin:0 0 8px">{{ $c['tagline'] }} · {{ $c['range'] }}</p>
                    <div class="price">{{ $k($c['monthly']) }}</div>
                    <div class="per">/ month</div>
                    <p class="pp-muted" style="font-size:.9rem;margin:0 0 14px">or {{ $k($c['annual']) }} a year · 2 months free</p>
                    <p style="margin:0 0 14px">{{ $c['description'] }}</p>
                    <ul>
                        @if($key === 'tikula')
                            <li><i class="bi bi-check2"></i> Your complete ZamPayroll experience</li>
                            <li><i class="bi bi-check2"></i> Payroll and employee management</li>
                            <li><i class="bi bi-check2"></i> Attendance, leave, overtime and loans</li>
                            <li><i class="bi bi-check2"></i> Reporting, AI assistance and support</li>
                        @elseif($key === 'medium')
                            <li><i class="bi bi-check2"></i> The same complete platform</li>
                            <li><i class="bi bi-check2"></i> Every core feature and support service included</li>
                            <li><i class="bi bi-check2"></i> Priced to reflect your workforce size</li>
                        @else
                            <li><i class="bi bi-check2"></i> Full access to ZamPayroll's capabilities</li>
                            <li><i class="bi bi-check2"></i> Support included</li>
                            <li><i class="bi bi-check2"></i> Consistency and visibility across your workforce</li>
                        @endif
                    </ul>
                    <a href="{{ route('register') }}" class="btn {{ $key === 'medium' ? 'btn-primary' : 'btn-line' }}">Choose {{ $c['name'] }}</a>
                </div>
            @endforeach

            {{-- Enterprise --}}
            <div class="plan">
                <h3>Enterprise</h3>
                <p class="pp-muted" style="margin:0 0 8px">301+ active employees</p>
                <div class="price" style="font-size:1.9rem">Tailored Pricing</div>
                <p style="margin:12px 0 14px">For large employers, multi-site operations and organisations with specialised deployment requirements.</p>
                <ul>
                    <li><i class="bi bi-check2"></i> All ZamPayroll features and support</li>
                    <li><i class="bi bi-check2"></i> Workforce-scale deployment planning</li>
                    <li><i class="bi bi-check2"></i> Implementation and migration assessment</li>
                    <li><i class="bi bi-check2"></i> Tailored commercial arrangements</li>
                    <li><i class="bi bi-check2"></i> Optional infrastructure and integration assessment</li>
                </ul>
                <p class="pp-muted" style="font-size:.86rem;margin:12px 0 16px">Tailored pricing, agreed with our team.</p>
                <a href="{{ route('home') }}#demo" class="btn btn-line">Talk to our team</a>
            </div>
        </div>
    </div>
</section>

<section class="sec" style="padding-top:0">
    <div class="wrap">
        <div class="plan" style="max-width:820px;margin:0 auto;text-align:center">
            <div class="pp-eyebrow">The ZamPayroll promise</div>
            <h2 style="margin:6px 0 10px">Everything included. Nothing essential held back.</h2>
            <p style="margin:0">Every paid plan includes the complete ZamPayroll platform and standard support. You only pay more as your workforce grows.</p>
            <p class="pp-muted" style="margin:12px 0 0;font-size:.9rem">Pay by mobile money (Airtel, MTN, Zamtel), Visa or Mastercard, or by invoice. Card payments are processed securely by DPO Pay.</p>
            <p style="margin:18px 0 0;font-weight:700;color:var(--zp-green, #0A3B22)">Start with confidence. Scale with intelligence. Manage your people with ZamPayroll.</p>
        </div>
    </div>
</section>

<section class="sec" style="padding-top:0" id="resellers">
    <div class="wrap">
        <div class="plan" style="max-width:820px;margin:0 auto">
            <h3>Partners</h3>
            <div class="price" style="font-size:1.9rem">Earn {{ rtrim(rtrim(number_format(\App\Services\Billing\PlatformSettings::get('partner_commission_rate'), 2), '0'), '.') }}%</div>
            <div class="per">HR firms, HR practitioners, HR students &amp; freelancers</div>
            <ul>
                <li><i class="bi bi-check2"></i> Your own partner code and sign-up link</li>
                <li><i class="bi bi-check2"></i> A dashboard of your clients and commission</li>
                <li><i class="bi bi-check2"></i> Earn on every invoice your clients pay</li>
            </ul>
            <a href="{{ route('partners.show') }}" class="btn btn-line"><i class="bi bi-person-badge"></i> Become a partner</a>
        </div>
    </div>
</section>
@endsection
