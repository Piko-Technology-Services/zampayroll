@extends('emails.layouts.brand')

@php
    $k = fn ($n) => 'K ' . number_format((float) $n, 2);
    $first = \Illuminate\Support\Str::before($company->name, ' ');
    $months = $data['months'] ?? 2;
@endphp

@section('title', 'ZamPayroll billing')
@section('eyebrow', 'Billing')
@section('heading', match ($kind) {
    'invoice' => 'Your invoice is ready',
    'receipt' => 'Payment received — thank you!',
    'reminder' => 'Your invoice is overdue',
    'proof_received' => 'We\'ve received your proof of payment',
    'proof_declined' => 'We couldn\'t accept your proof of payment',
    'trial' => ($data['days_left'] ?? 0) > 0 ? 'Your free experience ends in ' . $data['days_left'] . ' days' : 'Your free experience has ended',
    'limited' => 'Your account has been limited',
    'trial_started' => 'Your free experience has started',
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
            @elseif($kind === 'proof_received')
                Thank you. We've received your proof of payment for invoice <b>{{ $invoice->number }}</b>. We'll confirm it once the money has arrived, usually within one working day. Your invoice is attached for your records.
            @elseif($kind === 'proof_declined')
                We couldn't accept the proof of payment for invoice <b>{{ $invoice->number }}</b>. <b>Reason:</b> {{ $data['reason'] ?? 'Please check the payment and upload it again.' }}
            @else
                Here is invoice <b>{{ $invoice->number }}</b> for {{ $company->name }}. The PDF is attached and shows our bank and mobile money details.
            @endif
        </p>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#EAF3EA;border-radius:12px;margin:0 0 10px;">
            <tr><td style="padding:14px 18px;font-size:14px;color:#0A3B22;">
                {{ $invoice->title() }}<br>
                <b style="font-size:20px;">{{ $k($invoice->total) }}</b>
                @if(! in_array($kind, ['receipt', 'proof_received'], true)) · due {{ optional($invoice->due_at)->format('j M Y') }}@endif
            </td></tr>
        </table>
        @if($kind === 'invoice')
            <p style="margin:0 0 14px;">Pay by bank transfer or mobile money using the details on the invoice. Then sign in, open <b>Settings → Billing</b> and upload your proof of payment under this invoice.</p>
        @endif
    @elseif($kind === 'trial')
        <p style="margin:0 0 14px;">
            @if(($data['days_left'] ?? 0) > 0)
                Your free experience of ZamPayroll for {{ $company->name }} ends in {{ $data['days_left'] }} days. Choose a plan now so nothing stops on payday.
            @else
                Your free experience for {{ $company->name }} has ended. Your data is safe — choose a plan to carry on running payroll.
            @endif
        </p>
    @elseif($kind === 'trial_started')
        <p style="margin:0 0 14px;">{{ $company->name }} now has {{ $months }} months of ZamPayroll free, for up to {{ $data['limit'] ?? 10 }} active employees. Everything is ready to use. Before the {{ $months }} months are up, choose the plan that fits your team size.</p>
    @elseif($kind === 'limited')
        <p style="margin:0 0 14px;">We couldn't confirm payment for {{ $company->name }}, so the account is now read-only. Nothing has been deleted — pay the open invoice and everything unlocks straight away.</p>
    @endif
    @if($kind === 'workspace')
        <p style="margin:0 0 14px;">{{ $company->name }} now has its own private web address and database. All your employees, payroll history and settings came with you. From now on everyone signs in at:</p>
        <p style="margin:0 0 14px;font-size:18px;font-weight:700;color:#0A3B22;">{{ $company->workspaceHost() }}</p>
        @include('emails.partials.button', ['url' => $company->workspaceUrl('/login'), 'label' => 'Sign in to your workspace'])
    @elseif($kind === 'proof_declined')
        @include('emails.partials.button', ['url' => route('billing.index'), 'label' => 'Upload a new proof of payment'])
    @elseif($kind !== 'receipt' && $kind !== 'proof_received')
        @include('emails.partials.button', ['url' => route('billing.index'), 'label' => $invoice ? 'Go to billing' : 'Choose a plan'])
    @endif
@endsection

@section('footer_note')
    Questions about billing? Reply to this email or open a ticket under Help in ZamPayroll.
@endsection
