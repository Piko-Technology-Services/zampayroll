@extends('emails.layouts.brand')

@section('title', 'Proof of payment to check')
@section('eyebrow', 'Billing · manual payment')
@section('heading', 'Proof of payment to check')

@section('body')
    <p style="margin:0 0 14px;">{{ $company?->name ?? 'A company' }} has uploaded proof of payment for invoice <b>{{ $invoice->number }}</b>. The proof is attached.</p>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#EAF3EA;border-radius:12px;margin:0 0 10px;">
        <tr><td style="padding:14px 18px;font-size:14px;color:#0A3B22;">
            {{ $invoice->title() }}<br>
            <b style="font-size:20px;">K {{ number_format((float) $invoice->total, 2) }}</b>
            @if($payment->reference) · reference: {{ $payment->reference }}@endif
        </td></tr>
    </table>
    <p style="margin:0 0 14px;">Check the money has arrived, then confirm the payment or decline it with a reason in the ZamPayroll admin console.</p>
    @include('emails.partials.button', ['url' => route('platform.invoices'), 'label' => 'Review payment proofs'])
@endsection
