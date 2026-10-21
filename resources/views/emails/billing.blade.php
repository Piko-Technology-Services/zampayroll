@extends('emails.layouts.brand')

@php
    $k = fn ($n) => 'K ' . number_format((float) $n, 2);
    $first = \Illuminate\Support\Str::before($company->name, ' ');
@endphp

@section('title', 'ZamPayroll billing')
@section('eyebrow', 'Billing')
@section('heading', match ($kind) {
    'invoice' => 'Your invoice is ready',
    'receipt' => 'Payment received — thank you!',
    'reminder' => 'Your invoice is overdue',
    'trial' => ($data['days_left'] ?? 0) > 0 ? 'Your free trial ends in ' . $data['days_left'] . ' days' : 'Your free trial has ended',
    'limited' => 'Your account has been limited',
    'workspace' => 'Your private workspace is ready',
    default => 'Billing update',
})

@section('body')
    @if($invoice)
        <p style="margin:0 0 14px;">
            @if($kind === 'receipt')
                We've received your payment for <b>{{ $invoice->number }}</b>. Your receipt is attached.
            @elseif($kind === 'reminder')
                Invoice <b>{{ $invoice->number }}</b> was due on {{ optional($invoice->due_at)->format('j M Y') }}. Please pay soon to keep full access for {{ $company->name }}.
            @else
                Here is invoice <b>{{ $invoice->number }}</b> for {{ $company->name }}. The PDF is attached.
            @endif
        </p>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#EAF3EA;border-radius:12px;margin:0 0 10px;">
            <tr><td style="padding:14px 18px;font-size:14px;color:#0A3B22;">
                {{ $invoice->title() }}<br>
                <b style="font-size:20px;">{{ $k($invoice->total) }}</b>
                @if($kind !== 'receipt') · due {{ optional($invoice->due_at)->format('j M Y') }}@endif
            </td></tr>
        </table>
    @elseif($kind === 'trial')
        <p style="margin:0 0 14px;">
            @if(($data['days_left'] ?? 0) > 0)
                Your 7-day free trial of ZamPayroll for {{ $company->name }} ends in {{ $data['days_left'] }} days. Choose a plan now so nothing stops on payday.
            @else
                Your free trial for {{ $company->name }} has ended. Your data is safe — choose a plan to carry on running payroll.
            @endif
        </p>
    @elseif($kind === 'limited')
        <p style="margin:0 0 14px;">We couldn't confirm payment for {{ $company->name }}, so the account is now read-only. Nothing has been deleted — pay the open invoice and everything unlocks straight away.</p>
    @endif
    @if($kind === 'workspace')
        <p style="margin:0 0 14px;">{{ $company->name }} now has its own private web address and database. All your employees, payroll history and settings came with you. From now on everyone signs in at:</p>
        <p style="margin:0 0 14px;font-size:18px;font-weight:700;color:#0A3B22;">{{ $company->workspaceHost() }}</p>
        @include('emails.partials.button', ['url' => $company->workspaceUrl('/login'), 'label' => 'Sign in to your workspace'])
    @elseif($kind !== 'receipt')
        @include('emails.partials.button', ['url' => route('billing.index'), 'label' => $invoice ? 'Pay now' : 'Choose a plan'])
    @endif
@endsection

@section('footer_note')
    Questions about billing? Reply to this email or open a ticket under Help in ZamPayroll.
@endsection
