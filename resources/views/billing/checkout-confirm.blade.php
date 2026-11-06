@extends('layouts.app')

@section('title', 'Confirm invoice · ZamPayroll')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/zp-people.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/zp-payroll.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/zp-billing.css') }}">
@endpush

@php $k = fn ($n) => 'K ' . number_format((float) $n, 2); @endphp

@section('content')
<div class="pr pp bl">
    <div class="pr-head">
        <div>
            <h1>Confirm your invoice</h1>
            <p>Check where the invoice should go. We'll email the PDF with our bank and mobile money details.</p>
        </div>
    </div>

    <div class="pr-layout">
        <section class="pr-card">
            <div class="pr-card-head"><div><h2>{{ $invoice->title() }}</h2><div class="sub">{{ $invoice->number }}</div></div></div>
            <div class="bl-due-row" style="margin-bottom:1rem">
                <span class="ic"><i class="bi bi-receipt"></i></span>
                <div class="main"><b>Total due</b><small>Payable within {{ config('billing.invoice_due_days', 7) }} days of sending</small></div>
                <span class="amt">{{ $k($invoice->total) }}</span>
            </div>

            <form method="POST" action="{{ route('billing.invoices.send', $invoice) }}">
                @csrf
                <div class="pp-field">
                    <label for="billing_email">Send the invoice to</label>
                    <input class="pp-input" type="email" id="billing_email" name="billing_email" required maxlength="180"
                           value="{{ old('billing_email', $billingEmail) }}" placeholder="accounts@yourcompany.co.zm">
                    @error('billing_email')<div class="err" style="color:var(--zp-red);font-size:.8rem;margin-top:.3rem">{{ $message }}</div>@enderror
                    <small class="pp-muted">You can change this address. Company administrators are copied on invoices.</small>
                </div>
                <div style="display:flex;gap:.6rem;flex-wrap:wrap;margin-top:1.2rem">
                    <button class="pp-btn pp-btn-primary"><i class="bi bi-send"></i> Confirm and send invoice</button>
                    <a class="pp-btn pp-btn-ghost" href="{{ route('billing.index', ['method' => 'manual']) }}">Back</a>
                </div>
            </form>
        </section>

        <aside class="pr-side">
            <section class="pr-card">
                <div class="pr-card-head" style="margin-bottom:.6rem"><h2 style="font-size:1.05rem">What happens next</h2></div>
                <ol class="pp-muted" style="font-size:.9rem;padding-left:1.2rem;line-height:1.6;margin:0">
                    <li>We email the invoice PDF, with our bank and mobile money details.</li>
                    <li>You pay it by bank transfer or mobile money.</li>
                    <li>Upload your proof of payment on the billing page.</li>
                    <li>We confirm the money has arrived and activate your plan.</li>
                </ol>
            </section>
            <section class="pr-card">
                <div class="pr-card-head" style="margin-bottom:.6rem"><h2 style="font-size:1.05rem">Bank details</h2></div>
                <div class="pp-muted" style="font-size:.88rem;line-height:1.6">
                    {{ $bank['name'] ?: 'Bank name to be confirmed' }}<br>
                    {{ $bank['account_name'] ?: 'ZamPayroll' }} · {{ $bank['account_number'] ?: 'account number to be confirmed' }}
                    @foreach($mobileMoney as $m)<br>{{ $m['network'] }}: {{ $m['number'] }}@endforeach
                </div>
            </section>
        </aside>
    </div>
</div>
@endsection
