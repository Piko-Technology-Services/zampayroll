@php $seo = ['title' => 'Terms of service | ZamPayroll', 'description' => 'The terms that apply when you use ZamPayroll HR and payroll software.', 'path' => '/terms']; @endphp
@extends('layouts.site')

@section('content')
<section class="page-hero"><div class="wrap"><div class="crumbs"><a href="{{ route('home') }}">Home</a> / Terms</div><h1>Terms of service</h1><p>Last updated {{ \Carbon\Carbon::parse('2026-10-15')->format('j F Y') }}</p></div></section>
<section class="sec" style="padding-top:24px"><div class="wrap">
<article class="legal">
    <h2>1. Who we are</h2>
    <p>ZamPayroll ("we", "us") provides cloud HR and payroll software to organisations in Zambia. These terms apply to every company that creates a workspace ("Customer") and to everyone who signs in to it ("Users").</p>
    <h2>2. Your account</h2>
    <p>The person who registers a company confirms they are authorised to act for it. Customers are responsible for who they invite, the roles they assign, keeping passwords private, and for activity under their company code.</p>
    <h2>3. Your data and your responsibilities</h2>
    <p>Customers own the employee and payroll data they enter. Customers are responsible for the accuracy of that data, for having a lawful basis to process their employees' personal information, and for reviewing payroll results and statutory returns before paying or filing. ZamPayroll calculates PAYE, NAPSA, NHIMA and other deductions from the information and settings you provide; it does not replace professional tax or legal advice.</p>
    <h2>4. Acceptable use</h2>
    <p>You may not use ZamPayroll unlawfully, attempt to access other customers' data, interfere with the service, or resell it without a partner agreement.</p>
    <h2>5. Plans and payment</h2>
    <p>Companies with up to 10 employees may use the Free plan. Larger companies use a paid plan agreed in writing. We may suspend a workspace for non-payment after notice.</p>
    <h2>6. Availability and support</h2>
    <p>We work to keep ZamPayroll available and backed up, but it is provided "as is" without guarantees of uninterrupted service. Planned maintenance is announced in advance where possible.</p>
    <h2>7. Liability</h2>
    <p>To the extent allowed by Zambian law, our total liability for any claim is limited to the fees you paid us in the 12 months before the claim. We are not liable for indirect losses, or for penalties arising from data you entered or results you did not review.</p>
    <h2>8. Ending your use</h2>
    <p>You may stop using ZamPayroll at any time and request an export of your data. We delete workspace data within a reasonable period after closure, unless the law requires us to keep it.</p>
    <h2>9. Changes and law</h2>
    <p>We may update these terms and will notify Customers of material changes. These terms are governed by the laws of the Republic of Zambia.</p>
    <h2>10. Contact</h2>
    <p><a href="mailto:{{ config('site.contact.email') }}">{{ config('site.contact.email') }}</a> · {{ config('site.contact.phone') }}</p>
</article>
</div></section>
@endsection
