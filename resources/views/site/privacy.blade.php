@php $seo = ['title' => 'Privacy policy | ZamPayroll', 'description' => 'How ZamPayroll collects, uses and protects personal data, in line with Zambia\'s Data Protection Act.', 'path' => '/privacy']; @endphp
@extends('layouts.site')

@section('content')
<section class="page-hero"><div class="wrap"><div class="crumbs"><a href="{{ route('home') }}">Home</a> / Privacy</div><h1>Privacy policy</h1><p>Last updated {{ \Carbon\Carbon::parse('2026-10-15')->format('j F Y') }}</p></div></section>
<section class="sec" style="padding-top:24px"><div class="wrap">
<article class="legal">
    <h2>Our role</h2>
    <p>For employee and payroll information that companies enter into ZamPayroll, the company is the data controller and ZamPayroll processes the data on its behalf. For our own website visitors, demo requests and account holders, ZamPayroll is the controller. We aim to handle personal data in line with Zambia's Data Protection Act No. 3 of 2021.</p>
    <h2>What we collect</h2>
    <ul>
        <li>Account details: name, email, phone, job title and company details.</li>
        <li>Employee records entered by customers: identity numbers (NRC, TPIN, NAPSA, NHIMA), contact details, bank details, salary, leave, attendance and payroll results.</li>
        <li>Technical data: sign-in times, IP address and browser information, used for security.</li>
        <li>Demo and contact requests submitted on this website.</li>
    </ul>
    <h2>How we use it</h2>
    <p>To provide the service (payroll, payslips, statutory returns, notifications), keep accounts secure, support customers, and improve ZamPayroll. We do not sell personal data.</p>
    <h2>Where it is stored and who sees it</h2>
    <p>Data is stored on secured servers. Each company's data is kept separate; larger companies have their own database. Our staff access customer data only to provide support or when required by law. We use email delivery providers to send messages on customers' behalf.</p>
    <h2>AI mode and translation</h2>
    <p>AI mode runs on ZamPayroll's own servers and does not send employee data to outside AI providers. If a user chooses a language other than English, the text on the page they are viewing is sent to Google Translate to be translated.</p>
    <h2>How long we keep it</h2>
    <p>For as long as the customer's workspace is active, and afterwards only as long as needed for legal, tax or audit obligations.</p>
    <h2>Your rights</h2>
    <p>You may ask for access to, correction of, or deletion of your personal data, or object to its use. Employees should first contact their employer, who controls their records. You may also complain to the Office of the Data Protection Commissioner.</p>
    <h2>Security</h2>
    <p>We use encrypted connections, hashed passwords, role-based access, and regular backups. Please tell us immediately if you suspect unauthorised access.</p>
    <h2>Contact</h2>
    <p><a href="mailto:{{ config('site.contact.email') }}">{{ config('site.contact.email') }}</a> · {{ config('site.contact.phone') }} · {{ config('site.contact.address') }}</p>
</article>
</div></section>
@endsection
