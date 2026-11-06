@extends('layouts.app')

@section('title', 'Billing · ZamPayroll')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/zp-people.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/zp-payroll.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/zp-billing.css') }}">
@endpush

@php
    $k = fn ($n) => 'K ' . number_format((float) $n, 2);
    $limit = $state['free_limit'];
    $statusLabel = [
        'trialing' => 'Free experience', 'active' => 'Active', 'past_due' => 'Payment overdue',
        'suspended' => 'Limited (read-only)', 'cancelled' => 'Cancelled',
    ][$state['status']] ?? ucfirst(str_replace('_', ' ', $state['status']));
    $isLegacy = in_array($company->plan, ['starter', 'business'], true) && $company->billing_type;
    $planName = $company->billing_type === 'prepaid' && \App\Services\Billing\Plans::isPaid($company->plan)
        ? \App\Services\Billing\Plans::name($company->plan) . ' · ' . ($company->billing_cycle === 'annual' ? 'yearly' : 'monthly')
        : ($isLegacy ? ($company->plan === 'business' ? 'Business · post-paid (legacy)' : 'Starter · pre-paid (legacy)') : 'No paid plan yet');
    $recommended = $state['recommended'];
    $openInvoices = $state['open_invoices'];
@endphp

@section('content')
<div class="pr pp bl" id="blPage">
    <div class="pr-head">
        <div>
            <h1>Billing &amp; subscription</h1>
            <p>Your plan, what's due, invoices and receipts. Prices are in Zambian Kwacha.</p>
        </div>
        <div class="pr-head-actions">
            <a href="{{ route('support.index') }}" class="pp-btn pp-btn-ghost"><i class="bi bi-life-preserver"></i> Billing help</a>
        </div>
    </div>

    {{-- ============ STATUS ============ --}}
    <section class="bl-hero {{ $state['tone'] }}">
        <div>
            <div class="pp-eyebrow">{{ $statusLabel }} · {{ $planName }}</div>
            <h2>{{ $state['headline'] ?? $statusLabel }}</h2>
            <p>
                @if($state['status'] === 'trialing' && ! $company->billing_type && ! $state['limited'])
                    You're on the free experience until {{ optional($company->trial_ends_at)->format('j M Y') }}. You can subscribe to any plan now, whatever your team size.
                @elseif($state['limited'] && $state['employees'] > $limit && $state['status'] === 'trialing')
                    The free experience covers up to {{ $limit }} active employees. You have {{ $state['employees'] }}, so choose a plan below to carry on.
                @elseif($state['limited'])
                    Your account is read-only, and nothing has been deleted. Pay an invoice below, or choose a plan, to unlock everything.
                @elseif($isLegacy)
                    Your account is on an earlier plan. You can carry on as you are, or switch to one of the plans below at any time.
                @else
                    Your plan renews on {{ optional($company->paid_until)->format('j M Y') }}. We'll email the renewal invoice {{ $prices['renewal_notice_days'] }} days before.
                @endif
            </p>
        </div>
        @if($state['countdown_to'])
            <div class="bl-clock" aria-label="Countdown">
                <div class="zp-countdown" data-to="{{ $state['countdown_to']->toIso8601String() }}">
                    <div><b data-u="d">00</b><span>days</span></div>
                    <div><b data-u="h">00</b><span>hours</span></div>
                    <div><b data-u="m">00</b><span>min</span></div>
                    <div><b data-u="s">00</b><span>sec</span></div>
                </div>
                <small>{{ $state['countdown_label'] }}</small>
            </div>
        @endif
    </section>

    {{-- ============ TO PAY / AWAITING PAYMENT ============ --}}
    @if($openInvoices->isNotEmpty())
        <section class="pr-card">
            <div class="pr-card-head" id="pay"><div><h2>Awaiting payment</h2><div class="sub">Pay the invoice, then upload your proof of payment here.</div></div></div>
            <div class="bl-due">
                @foreach($openInvoices as $inv)
                    @php
                        $method = $inv->meta['method'] ?? 'manual';
                        $proof = $awaiting->get($inv->id);
                        $rejected = $declined->get($inv->id);
                    @endphp
                    <div class="bl-due-row {{ $inv->isOverdue() ? 'late' : '' }}" style="flex-wrap:wrap">
                        <span class="ic"><i class="bi bi-receipt"></i></span>
                        <div class="main">
                            <b>{{ $inv->title() }}</b>
                            <small>{{ $inv->number }} · {{ $inv->isOverdue() ? 'overdue since' : 'due' }} {{ optional($inv->due_at)->format('j M Y') }}{{ $inv->emailed_at ? ' · sent ' . $inv->emailed_at->format('j M Y') : '' }}</small>
                        </div>
                        <span class="amt">{{ $k($inv->total) }}</span>
                        <a class="pp-btn pp-btn-ghost pp-btn-sm" href="{{ route('billing.invoices.pdf', $inv) }}" target="_blank"><i class="bi bi-file-earmark-pdf"></i> PDF</a>

                        <div style="flex-basis:100%;margin-top:.8rem">
                            @if($method === 'online')
                                <form method="POST" action="{{ route('billing.checkout') }}" style="margin:0">
                                    @csrf
                                    <input type="hidden" name="plan" value="{{ $inv->meta['plan'] ?? '' }}">
                                    <input type="hidden" name="cycle" value="{{ $inv->meta['cycle'] ?? 'monthly' }}">
                                    <input type="hidden" name="method" value="online">
                                    <button class="pp-btn pp-btn-primary pp-btn-sm"><i class="bi bi-box-arrow-up-right"></i> Continue to online payment</button>
                                    @if(isset(\App\Services\Billing\Plans::TIERS[$inv->meta['plan'] ?? '']))
                                        <a class="pp-btn pp-btn-ghost pp-btn-sm" href="{{ route('billing.checkout.form', ['plan' => $inv->meta['plan'], 'cycle' => $inv->meta['cycle'] ?? 'monthly']) }}">Pay by bank transfer instead</a>
                                    @endif
                                </form>
                            @elseif($proof)
                                <div class="pp-callout" style="margin:0"><i class="bi bi-hourglass-split"></i><div>Proof of payment received {{ $proof->created_at->format('j M Y, H:i') }}. We're checking that the money has arrived and will email you once it's confirmed.</div></div>
                            @elseif(! $inv->emailed_at)
                                <p class="pp-muted" style="margin:0 0 .6rem;font-size:.88rem">This invoice hasn't been sent yet. Confirm the email address and we'll send it with our bank and mobile money details.</p>
                                <a class="pp-btn pp-btn-primary pp-btn-sm" href="{{ route('billing.checkout.confirm', $inv) }}"><i class="bi bi-envelope-check"></i> Confirm email and send invoice</a>
                            @else
                                @if($rejected)
                                    <div class="pp-error" style="margin:0 0 .8rem">Your last proof of payment wasn't accepted: <b>{{ $rejected->result_explanation }}</b>. Please upload a new one.</div>
                                @endif
                                <div class="bl-paybox">
                                    <div class="bl-paystep">
                                        <span class="n">1</span>
                                        <div>
                                            <b>Pay {{ $k($inv->total) }} by bank transfer</b>
                                            <dl class="bl-bank">
                                                <dt>Bank</dt><dd>{{ $bank['name'] ?: 'To be confirmed' }}{{ ! empty($bank['branch']) ? ', ' . $bank['branch'] : '' }}</dd>
                                                <dt>Account name</dt><dd>{{ $bank['account_name'] ?: 'ZamPayroll' }}</dd>
                                                <dt>Account number</dt><dd>{{ $bank['account_number'] ?: 'To be confirmed' }}</dd>
                                                @foreach($mobileMoney as $m)<dt>{{ $m['network'] }}</dt><dd>{{ $m['number'] }}</dd>@endforeach
                                                <dt>Reference</dt><dd><b>{{ $inv->number }}</b></dd>
                                            </dl>
                                        </div>
                                    </div>
                                    <div class="bl-paystep">
                                        <span class="n">2</span>
                                        <div style="flex:1">
                                            <b>Upload your proof of payment</b>
                                            <form method="POST" action="{{ route('billing.invoices.proof', $inv) }}" enctype="multipart/form-data" class="bl-proof" style="display:grid;gap:.6rem;margin-top:.5rem">
                                                @csrf
                                                <input class="pp-input" type="file" id="proof-{{ $inv->id }}" name="proof" accept=".jpg,.jpeg,.png,.pdf" required aria-label="Proof of payment (JPG, PNG or PDF, up to 5MB)">
                                                <input class="pp-input" type="text" id="ref-{{ $inv->id }}" name="reference" maxlength="120" value="{{ old('reference') }}" placeholder="Transaction reference (optional)" aria-label="Transaction reference">
                                                <div><button class="pp-btn pp-btn-primary pp-btn-sm"><i class="bi bi-upload"></i> Send proof of payment</button></div>
                                                <small class="pp-muted">Screenshot or PDF, up to 5MB. We email you as soon as your plan is active.</small>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ============ PLANS ============ --}}
    <section class="pr-card" id="plans">
        <div class="pr-card-head">
            <div>
                <h2>Pay for your ZamPayroll subscription</h2>
                <div class="sub">Choose your plan, then pay by mobile money, card or invoice. Prices are in Zambian Kwacha.</div>
            </div>
        </div>

        <ol class="bl-steps">
            <li><span class="n">1</span><div><b>Choose your plan</b><small>Pick the plan for your number of active employees, billed monthly or annually.</small></div></li>
            <li><span class="n">2</span><div><b>Enter your details</b><small>Company name, your name and the email for your receipt.</small></div></li>
            <li><span class="n">3</span><div><b>Choose how to pay</b><small>Manual invoice by bank transfer, or a mobile money prompt or Visa/Mastercard online.</small></div></li>
            <li><span class="n">4</span><div><b>Get confirmed</b><small>Confirmation appears on screen and by email, and your plan is activated.</small></div></li>
        </ol>

        <div class="bl-tabs bl-cycle" role="group" aria-label="Billing cycle">
            <a href="{{ route('billing.index', ['cycle' => 'monthly']) }}#plans" class="{{ $cycle === 'monthly' ? 'on' : '' }}">Monthly</a>
            <a href="{{ route('billing.index', ['cycle' => 'annual']) }}#plans" class="{{ $cycle === 'annual' ? 'on' : '' }}">Annually <em>2 months free</em></a>
        </div>

        <div class="bl-plans3">
            @foreach($plans as $key => $p)
                @php $isRec = $recommended === $key; $isCurrent = $company->billing_type === 'prepaid' && $company->plan === $key && $company->subscription_status === 'active'; @endphp
                <div class="bl-plan {{ $key === 'medium' ? 'rec' : '' }}">
                    <div class="bl-plan-top">
                        <span class="bl-tag {{ $key === 'medium' ? 'hot' : '' }}">{{ $p['tagline'] }}</span>
                        @if($isCurrent)<span class="bl-tag mine">Your plan</span>@elseif($isRec)<span class="bl-tag mine">Fits your team</span>@endif
                    </div>
                    <h3>{{ $p['name'] }}</h3>
                    <p class="pp-muted" style="margin:.2rem 0 0">{{ $p['range'] }}</p>
                    @if($cycle === 'annual')
                        <div class="bl-price"><b>K{{ number_format($p['annual']) }}</b> / year</div>
                        <small class="bl-alt">or K{{ number_format($p['monthly']) }} a month</small>
                    @else
                        <div class="bl-price"><b>K{{ number_format($p['monthly']) }}</b> / month</div>
                        <small class="bl-alt">or K{{ number_format($p['annual']) }} a year · 2 months free</small>
                    @endif
                    <p class="bl-desc">{{ $p['description'] }}</p>
                    <a class="pp-btn {{ $key === 'medium' ? 'pp-btn-primary' : 'pp-btn-ghost' }} bl-go" href="{{ route('billing.checkout.form', ['plan' => $key, 'cycle' => $cycle]) }}">Pay for this plan</a>
                </div>
            @endforeach
        </div>

        <div class="bl-lines">
            <div><i class="bi bi-gift"></i><span><b>Free Experience</b> · Up to {{ $limit }} active employees · K0 for 2 months, then a paid plan from month three.</span></div>
            <div><i class="bi bi-buildings"></i><span><b>Enterprise</b> · 301+ active employees · Tailored pricing, agreed with our team.</span>
                <a href="mailto:{{ config('billing.support_email') }}?subject={{ rawurlencode('Enterprise plan for ' . $company->name) }}">Talk to our team</a></div>
        </div>
        <p class="bl-fine">Every paid plan includes the complete ZamPayroll platform and standard support. You only pay more as your workforce grows.
            Card payments are processed securely by DPO Pay. Mobile money supports Airtel, MTN and Zamtel.</p>
        <p class="bl-fine bl-links">
            <a href="{{ config('billing.terms_url') }}" target="_blank" rel="noopener">Terms</a>
            <a href="{{ config('billing.privacy_url') }}" target="_blank" rel="noopener">Privacy</a>
            <a href="{{ config('billing.refunds_url') }}" target="_blank" rel="noopener">Refunds</a>
        </p>
    </section>

    <div class="pr-layout">
        <div>
            {{-- ============ HISTORY ============ --}}
            <section class="pr-card">
                <div class="pr-card-head"><div><h2>Invoices &amp; receipts</h2><div class="sub">Every invoice is also emailed to you as a PDF.</div></div></div>
                @if($invoices->isEmpty())
                    <div class="pr-empty"><i class="bi bi-receipt"></i>No invoices yet.</div>
                @else
                    <div class="pr-tablewrap">
                        <table class="pr-table pr-hist">
                            <thead><tr><th>Invoice</th><th>Period</th><th>Status</th><th style="text-align:right">Amount</th><th></th></tr></thead>
                            <tbody>
                            @foreach($invoices as $inv)
                                <tr onclick="window.open('{{ route('billing.invoices.pdf', $inv) }}','_blank')">
                                    <td><b>{{ $inv->number }}</b><br><small class="pp-muted">{{ $inv->created_at->format('j M Y') }}</small></td>
                                    <td>{{ $inv->period_start ? $inv->period_start->format('j M') . ' – ' . optional($inv->period_end)->format('j M Y') : '—' }}</td>
                                    <td><span class="pr-pill {{ $inv->status === 'paid' ? 'locked' : ($inv->isOverdue() ? 'review' : 'draft') }}">{{ $inv->status === 'paid' ? 'Paid ' . optional($inv->paid_at)->format('j M') : ($inv->isOverdue() ? 'Overdue' : ($awaiting->has($inv->id) ? 'Proof sent' : ucfirst($inv->status))) }}</span></td>
                                    <td class="num"><b>{{ $k($inv->total) }}</b></td>
                                    <td style="text-align:right"><i class="bi bi-file-earmark-pdf pp-muted"></i></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>

        <aside class="pr-side">
            <section class="pr-card">
                <div class="pr-card-head" style="margin-bottom:.6rem"><h2 style="font-size:1.05rem">Billing contact</h2></div>
                <form method="POST" action="{{ route('billing.details') }}">
                    @csrf
                    <div class="pp-field"><label for="billing_email">Send invoices to <small>plus all administrators</small></label>
                        <input class="pp-input" type="email" id="billing_email" name="billing_email" value="{{ old('billing_email', $company->billing_email) }}" placeholder="accounts@yourcompany.co.zm"></div>
                    <button class="pp-btn pp-btn-ghost pp-btn-sm" style="margin-top:.6rem">Save</button>
                </form>
            </section>

            @if(app(\App\Services\Billing\Subscriptions::class)->qualifiesForPrivate($company, $state['employees']) || $company->isDedicated())
                <section class="pr-card">
                    <div class="pr-card-head" style="margin-bottom:.6rem"><h2 style="font-size:1.05rem"><i class="bi bi-building-lock" style="color:var(--zp-copper)"></i> Private workspace</h2></div>
                    @if($company->isDedicated())
                        <p class="pp-muted" style="font-size:.85rem;margin:0">You're on your own private address: <b>{{ $company->workspaceHost() }}</b>, with a separate database.</p>
                    @elseif($company->requested_subdomain)
                        <p class="pp-muted" style="font-size:.85rem;margin:0">Requested <b>{{ $company->requested_subdomain }}.{{ \App\Support\Tenancy::centralDomain() }}</b> on {{ optional($company->subdomain_requested_at)->format('j M Y') }}. We'll email you when it's live.</p>
                    @else
                        <p class="pp-muted" style="font-size:.85rem">Get your own web address and a separate database for your company's data.@if($prices['private_workspace_monthly'] > 0) {{ $k($prices['private_workspace_monthly']) }} / month extra.@endif</p>
                        <form method="POST" action="{{ route('billing.private') }}">
                            @csrf
                            <div class="ob-subdomain bl-sub"><input name="subdomain" placeholder="yourcompany" value="{{ old('subdomain') }}" maxlength="32"><span>.{{ \App\Support\Tenancy::centralDomain() }}</span></div>
                            @error('subdomain')<div class="err" style="color:var(--zp-red);font-size:.78rem;margin-top:.3rem">{{ $message }}</div>@enderror
                            <button class="pp-btn pp-btn-soft pp-btn-sm" style="margin-top:.6rem">Request private workspace</button>
                        </form>
                    @endif
                </section>
            @endif

            @if($payments->isNotEmpty())
                <section class="pr-card">
                    <div class="pr-card-head" style="margin-bottom:.6rem"><h2 style="font-size:1.05rem">Recent payments</h2></div>
                    <ul class="pr-history">
                        @foreach($payments->take(6) as $p)
                            <li class="{{ $p->status === 'paid' ? 'approved' : ($p->status === 'declined' || $p->status === 'failed' ? 'sent_back' : '') }}">
                                <i class="bi {{ $p->method === 'card' ? 'bi-credit-card' : ($p->method === 'bank_transfer' ? 'bi-bank' : 'bi-phone') }}"></i>
                                <div><b>{{ $k($p->amount) }} · {{ str_replace('_', ' ', ucfirst($p->status)) }}</b>
                                    <small>{{ $p->method === 'bank_transfer' ? 'Bank transfer' : ($p->method === 'card' ? 'Card' : 'Mobile money') }} · {{ $p->created_at->format('j M, H:i') }}{{ $p->status === 'declined' && $p->result_explanation ? ' · ' . $p->result_explanation : '' }}</small></div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </aside>
    </div>
</div>
@endsection
