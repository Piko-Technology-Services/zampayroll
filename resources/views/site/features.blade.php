@php
    $seo = [
        'title' => 'Features — Payroll, PAYE, NAPSA, NHIMA, leave & HR | ZamPayroll',
        'description' => 'Everything Zambian HR teams need: net-to-gross payroll with PAYE, NAPSA and NHIMA, statutory returns, bank files, leave, attendance, overtime, staff loans, employee self-service and an AI assistant.',
        'path' => '/features',
        'schema' => [[
            '@context' => 'https://schema.org', '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => config('site.url') . '/'],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Features', 'item' => config('site.url') . '/features'],
            ],
        ]],
    ];
    $sections = [
        ['payroll', 'bi-cash-stack', 'Payroll', 'Run the month in minutes and lock it when it\'s right.', [
            'Net-to-gross: agree take-home pay, the system works out gross pay and deductions',
            'Housing, transport and other allowances as fixed amounts or percentages',
            'Once-off bonuses, commissions and deductions per run — or import them from Excel',
            'Loan repayments deducted automatically from the loan ledger',
            'Review screen per employee, then finalise to lock the run',
            'PDF payslips — downloadable, printable, emailed, and in each employee\'s portal',
        ]],
        ['compliance', 'bi-shield-check', 'ZRA, NAPSA, NHIMA & WCFCB', 'Statutory work done as part of payroll, not after it.', [
            'PAYE using the ZRA tax bands',
            'NAPSA employee and employer contributions, with the contribution ceiling',
            'NHIMA contributions',
            'Monthly returns ready to upload, plus management reports by department and branch',
            'Bank bulk-payment files for Zanaco, Absa, Stanbic, FNB, Access, Ecobank and more — or your bank\'s own template',
        ]],
        ['people', 'bi-people', 'People', 'One record per employee that every other module reads from.', [
            'Contract, salary, NRC, TPIN, NAPSA, NHIMA, bank details and documents',
            'Import from Excel or CSV — column names from other systems are recognised',
            'Paste a messy list into AI mode and it sorts it out',
            'Contract end dates and probation flagged before they lapse',
        ]],
        ['leave', 'bi-calendar2-heart', 'Leave', 'Fair, visible and counted correctly.', [
            'All Zambian leave types — annual, sick, maternity, paternity, compassionate, family and more',
            'Working days counted for you, skipping weekends and public holidays',
            'Balances update automatically on approval; full-year leave sheet',
            'Employees apply from their phone; HR approves with one tap',
        ]],
        ['attendance', 'bi-clock-history', 'Attendance', 'Know who is in, without chasing anyone.', [
            'Monthly calendar with present, absent, sick, leave and holiday',
            'Import from biometric or spreadsheet exports',
            'Company working days and public holidays set once',
            'Attendance report per employee and month',
        ]],
        ['overtime', 'bi-alarm', 'Overtime & loans', 'Requests that price themselves.', [
            'Normal (1.5×) and double (2×) overtime, priced before submission',
            'Approved overtime ready for payroll; export by month',
            'Loan and salary-advance requests with repayment plans',
            'Loan ledger with every deduction and manual payment',
        ]],
        ['portal', 'bi-phone', 'Employee self-service', 'A portal for every employee, created in one click.', [
            'Login details and instructions emailed automatically',
            'Payslips, leave balance, overtime and loans on any phone',
            'Employees keep their own contact and next-of-kin details current',
            'No app to install',
        ]],
        ['teams', 'bi-shield-lock', 'Teams, roles & languages', 'Built for real companies with many hands on payroll.', [
            'Sign in with company code, email and password',
            'Administrator, HR Officer, Payroll Officer, Manager and Viewer roles — or create your own',
            'Tick exactly what each role can see and do',
            'Use the system in Chinese, French, Portuguese, Somali, Arabic, Hindi, Gujarati, Urdu, Swahili or Nyanja',
            'Small teams share our secure cloud for free; larger companies get their own workspace and database',
        ]],
    ];
@endphp
@extends('layouts.site')

@section('content')
<section class="page-hero">
    <div class="wrap">
        <div class="crumbs"><a href="{{ route('home') }}">Home</a> / Features</div>
        <h1>Everything HR does in a month, in one place.</h1>
        <p>Each part feeds the next — what employees request and what HR approves flows straight into payroll, so nothing is re-typed.</p>
    </div>
</section>

<section class="sec" style="padding-top:32px">
    <div class="wrap">
        <div class="mods">
            @foreach($sections as [$id, $icon, $title, $intro, $points])
                <article class="mod" id="{{ $id }}">
                    <i class="bi {{ $icon }} ic"></i>
                    <h3>{{ $title }}</h3>
                    <p>{{ $intro }}</p>
                    <ul>@foreach($points as $pt)<li>{{ $pt }}</li>@endforeach</ul>
                </article>
            @endforeach
            <article class="mod" style="background:var(--s-forest);color:#DCEDE2;border-color:var(--s-forest)">
                <i class="bi bi-stars ic" style="color:var(--s-copper)"></i>
                <h3 style="color:#fff">AI mode</h3>
                <p style="color:#B9D3C3">Ask questions or give instructions in plain English. It prepares the work; you confirm.</p>
                <a href="{{ route('site.ai') }}" class="btn btn-copper btn-sm">How it works</a>
            </article>
        </div>
    </div>
</section>

<section class="sec sec-white">
    <div class="wrap">
        <div class="cta-band">
            <div><h2>Try it with your own team.</h2><p>Free for up to 10 employees. Set up in about 3 minutes.</p></div>
            <div style="display:flex;gap:.6rem;flex-wrap:wrap"><a href="{{ route('register') }}" class="btn btn-copper">Start free</a><a href="{{ route('home') }}#demo" class="btn btn-ghost-light">Book a demo</a></div>
        </div>
    </div>
</section>
@endsection
