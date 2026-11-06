@extends('layouts.app')

@section('title', 'Pay for ' . $plan['name'] . ' · ZamPayroll')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/zp-people.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/zp-payroll.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/zp-billing.css') }}">
@endpush

@php
    $price = $cycle === 'annual' ? $plan['annual'] : $plan['monthly'];
    $method = old('method', 'manual');
@endphp

@section('content')
<div class="pr pp bl">
    <div class="pr-head">
        <div>
            <h1>Pay for {{ $plan['name'] }}</h1>
            <p>Confirm your details and choose how to pay. It takes less than a minute.</p>
        </div>
        <div class="pr-head-actions">
            <a href="{{ route('billing.index', ['cycle' => $cycle]) }}#plans" class="pp-btn pp-btn-ghost"><i class="bi bi-arrow-left"></i> Change plan</a>
        </div>
    </div>

    <form method="POST" action="{{ route('billing.checkout') }}" class="pr-layout">
        @csrf
        <input type="hidden" name="plan" value="{{ $planKey }}">
        <input type="hidden" name="cycle" value="{{ $cycle }}">

        <div>
            <section class="pr-card">
                <div class="pr-card-head"><div><h2><span class="bl-n">2</span> Your details</h2><div class="sub">For your invoice and receipt.</div></div></div>
                <div class="bl-fields">
                    <div class="pp-field"><label for="company_name">Company name</label>
                        <input class="pp-input" type="text" id="company_name" value="{{ $company->name }}" readonly></div>
                    <div class="pp-field"><label for="contact_name">Your name</label>
                        <input class="pp-input" type="text" id="contact_name" name="contact_name" maxlength="120" value="{{ old('contact_name', $contactName) }}"></div>
                    <div class="pp-field" style="grid-column:1/-1"><label for="billing_email">Email for your receipt</label>
                        <input class="pp-input" type="email" id="billing_email" name="billing_email" maxlength="180" value="{{ old('billing_email', $billingEmail) }}" placeholder="accounts@yourcompany.co.zm">
                        @error('billing_email')<div class="err" style="color:var(--zp-red);font-size:.8rem;margin-top:.3rem">{{ $message }}</div>@enderror</div>
                </div>
            </section>

            <section class="pr-card">
                <div class="pr-card-head"><div><h2><span class="bl-n">3</span> How would you like to pay?</h2></div></div>
                <div class="bl-methods">
                    <label class="bl-method">
                        <input type="radio" name="method" value="manual" @checked($method === 'manual')>
                        <span class="ic"><i class="bi bi-receipt"></i></span>
                        <span><b>Manual invoice</b><small>We email your invoice now with our bank details. Pay by bank transfer, then upload your proof of payment.</small></span>
                    </label>
                    <label class="bl-method">
                        <input type="radio" name="method" value="online" @checked($method === 'online')>
                        <span class="ic"><i class="bi bi-phone"></i></span>
                        <span><b>Mobile money or card</b><small>Pay online with an Airtel, MTN or Zamtel prompt on your phone, or Visa or Mastercard.</small></span>
                    </label>
                </div>
                <button class="pp-btn pp-btn-primary bl-submit"><i class="bi bi-lock"></i> Continue to payment</button>
                <p class="bl-fine" style="margin-top:.8rem">By paying you agree to the
                    <a href="{{ config('billing.terms_url') }}" target="_blank" rel="noopener">Terms</a>,
                    <a href="{{ config('billing.privacy_url') }}" target="_blank" rel="noopener">Privacy</a> and
                    <a href="{{ config('billing.refunds_url') }}" target="_blank" rel="noopener">Refund</a> policies.</p>
            </section>
        </div>

        <aside class="pr-side">
            <section class="pr-card bl-summary">
                <div class="pp-eyebrow">Your plan</div>
                <h2 style="margin:.2rem 0 0">{{ $plan['name'] }}</h2>
                <p class="pp-muted" style="margin:.2rem 0 1rem">{{ $plan['tagline'] }} · {{ $plan['range'] }}</p>
                <div class="bl-tabs bl-cycle" role="group" aria-label="Billing cycle" style="margin:0 0 1rem">
                    <a href="{{ route('billing.checkout.form', ['plan' => $planKey, 'cycle' => 'monthly']) }}" class="{{ $cycle === 'monthly' ? 'on' : '' }}">Monthly</a>
                    <a href="{{ route('billing.checkout.form', ['plan' => $planKey, 'cycle' => 'annual']) }}" class="{{ $cycle === 'annual' ? 'on' : '' }}">Annually <em>2 months free</em></a>
                </div>
                <div class="bl-total"><span>Total</span><b>K{{ number_format($price, 2) }}</b></div>
                <small class="pp-muted">{{ $cycle === 'annual' ? 'For 12 months. That is 2 months free.' : 'Per month. Switch to annual and get 2 months free.' }}</small>
            </section>
        </aside>
    </form>
</div>
@endsection
