@extends('emails.layouts.brand')

@php
    $first = \Illuminate\Support\Str::before($partner->name, ' ');
    $rate = rtrim(rtrim(number_format(app(\App\Services\Partners\PartnerProgramme::class)->rateFor($partner), 2), '0'), '.');
@endphp

@section('title', 'ZamPayroll partner programme')
@section('eyebrow', 'Partner programme')
@section('heading', match ($kind) {
    'welcome' => 'Welcome aboard, ' . $first . '!',
    'received' => 'Thanks for applying, ' . $first,
    'approved' => 'You\'re approved, ' . $first . '!',
    'new_client' => 'You have a new client 🎉',
    'paid' => 'Commission paid',
    default => 'Partner update',
})

@section('body')
    @if(in_array($kind, ['welcome', 'approved'], true))
        <p style="margin:0 0 14px;">You can now introduce ZamPayroll to companies and earn {{ $rate }}% of every invoice they pay. Every company that signs up with your code is linked to you automatically.</p>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0A3B22;border-radius:12px;margin:0 0 14px;">
            <tr><td style="padding:16px 18px;color:#ffffff;font-size:13px;">
                Your partner code<br>
                <b style="font-size:26px;color:#F6C79A;letter-spacing:.02em;">{{ $partner->code }}</b><br>
                <span style="color:#D4ECDC;">Sign-up link: {{ $partner->joinUrl() }}</span>
            </td></tr>
        </table>
        <p style="margin:0 0 14px;">First, confirm your email address with the separate link we sent. Then sign in to your partner dashboard to copy your link, share it on WhatsApp and follow your clients.</p>
        @include('emails.partials.button', ['url' => route('partner.dashboard'), 'label' => 'Open my partner dashboard'])
    @elseif($kind === 'received')
        <p style="margin:0 0 14px;">We've received your application to become a ZamPayroll partner. We'll review it within one working day and email you your partner code as soon as you're approved.</p>
        <p style="margin:0 0 14px;">Meanwhile, please confirm your email address with the link we sent you.</p>
    @elseif($kind === 'new_client')
        <p style="margin:0 0 14px;"><b>{{ $data['company'] ?? 'A company' }}</b> just created a ZamPayroll account with your code {{ $partner->code }}. They're now on your dashboard.</p>
        <p style="margin:0 0 14px;">Tip: help them paste in their staff list and run their first payroll — clients who do that in the first week usually stay.</p>
        @include('emails.partials.button', ['url' => route('partner.dashboard'), 'label' => 'See my clients'])
    @elseif($kind === 'paid')
        <p style="margin:0 0 14px;">We've paid you <b>K {{ number_format((float) ($data['amount'] ?? 0), 2) }}</b> in commission{{ ! empty($data['reference']) ? ' (reference ' . $data['reference'] . ')' : '' }}. Thank you for growing ZamPayroll with us.</p>
        @include('emails.partials.button', ['url' => route('partner.dashboard'), 'label' => 'See my commission'])
    @endif
@endsection

@section('footer_note')
    Questions about the partner programme? Reply to this email or WhatsApp {{ config('site.contact.phone') }}.
@endsection
