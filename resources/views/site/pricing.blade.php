@php
    $seo = [
        'title' => 'Pricing — free payroll software for up to 10 employees | ZamPayroll',
        'description' => 'ZamPayroll is free for Zambian companies with up to 10 employees. Larger companies get a dedicated workspace with their own web address and database.',
        'path' => '/pricing',
        'schema' => [[
            '@context' => 'https://schema.org', '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => config('site.url') . '/'],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Pricing', 'item' => config('site.url') . '/pricing'],
            ],
        ]],
    ];
@endphp
@extends('layouts.site')

@section('content')
<section class="page-hero">
    <div class="wrap">
        <div class="crumbs"><a href="{{ route('home') }}">Home</a> / Pricing</div>
        <h1>Free while you're small. Your own workspace when you grow.</h1>
        <p>Every plan includes payroll with PAYE, NAPSA and NHIMA, payslips, leave, attendance, overtime, loans, the employee portal, roles, languages and AI mode.</p>
    </div>
</section>

<section class="sec" style="padding-top:32px">
    <div class="wrap">
        <div class="plans">
            <div class="plan feat">
                <span class="tag">Most popular</span>
                <h3>Free</h3>
                <div class="price">K0</div>
                <div class="per">for up to 10 employees, forever</div>
                <ul>
                    <li><i class="bi bi-check2"></i> Full payroll, payslips and statutory returns</li>
                    <li><i class="bi bi-check2"></i> Leave, attendance, overtime and loans</li>
                    <li><i class="bi bi-check2"></i> Self-service portal for every employee</li>
                    <li><i class="bi bi-check2"></i> AI mode</li>
                    <li><i class="bi bi-check2"></i> Roles and team access</li>
                    <li><i class="bi bi-check2"></i> Secure shared cloud — your data private to your company</li>
                </ul>
                <a href="{{ route('register') }}" class="btn btn-primary">Start free</a>
            </div>
            <div class="plan">
                <h3>Business</h3>
                <div class="price" style="font-size:1.9rem">Talk to us</div>
                <div class="per">for 11 or more employees</div>
                <ul>
                    <li><i class="bi bi-check2"></i> Everything in Free</li>
                    <li><i class="bi bi-check2"></i> Dedicated workspace — yourcompany.zampayroll.com</li>
                    <li><i class="bi bi-check2"></i> Your own separate database</li>
                    <li><i class="bi bi-check2"></i> Help moving data from your current system</li>
                    <li><i class="bi bi-check2"></i> Priority phone and WhatsApp support</li>
                </ul>
                <a href="{{ route('home') }}#demo" class="btn btn-line">Get a quote</a>
            </div>
            <div class="plan" id="resellers">
                <h3>Resellers &amp; accountants</h3>
                <div class="price" style="font-size:1.9rem">Partner</div>
                <div class="per">for firms running payroll for clients</div>
                <ul>
                    <li><i class="bi bi-check2"></i> Onboard and support your clients on ZamPayroll</li>
                    <li><i class="bi bi-check2"></i> Training and demo material</li>
                    <li><i class="bi bi-check2"></i> Earn on every client you bring</li>
                </ul>
                <a href="https://wa.me/{{ config('site.contact.whatsapp') }}?text={{ urlencode('Hi ZamPayroll, I am interested in becoming a reseller.') }}" target="_blank" rel="noopener" class="btn btn-line"><i class="bi bi-whatsapp"></i> Talk to us</a>
            </div>
        </div>
    </div>
</section>
@endsection
