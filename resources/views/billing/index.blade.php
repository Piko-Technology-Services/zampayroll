@extends('layouts.app')

@section('title', 'Billing · ZamPayroll')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/zp-people.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/zp-payroll.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/zp-billing.css') }}">
@endpush

@php
    $k = fn ($n) => 'K ' . number_format((float) $n, 2);
    $limit = $prices['prepaid_limit'];
    $onPlan = in_array($state['status'], ['active', 'past_due'], true) || ($state['status'] === 'suspended' && $company->billing_type);
    $statusLabel = [
        'trialing' => 'Free trial', 'active' => 'Active', 'past_due' => 'Payment overdue',
        'suspended' => 'Limited (read-only)', 'cancelled' => 'Cancelled',
    ][$state['status']] ?? ucfirst($state['status']);
    $planLabel = $company->plan === 'business' ? 'Business · post-paid' : ($company->plan === 'starter' ? 'Starter · pre-paid ' . ($company->billing_cycle === 'annual' ? 'yearly' : 'monthly') : 'No plan yet');
    $tooBig = $state['employees'] > $limit;
@endphp

@section('content')
<div class="pr pp bl" id="blPage" data-csrf="{{ csrf_token() }}">
    <div class="pr-head">
        <div>
            <h1>Billing &amp; subscription</h1>
            <p>Your plan, what's due, invoices and receipts. Pay by mobile money or card without leaving ZamPayroll.</p>
        </div>
        <div class="pr-head-actions">
            <a href="{{ route('support.index') }}" class="pp-btn pp-btn-ghost"><i class="bi bi-life-preserver"></i> Billing help</a>
        </div>
    </div>

    {{-- ============ STATUS + COUNTDOWN ============ --}}
    <section class="bl-hero {{ $state['tone'] }}">
        <div>
            <div class="pp-eyebrow">{{ $statusLabel }} · {{ $planLabel }}</div>
            <h2>{{ $state['headline'] ?? $statusLabel }}</h2>
            <p>
                @if($state['limited'])
                    Your account is read-only — nothing has been deleted. {{ $state['open_invoices']->isNotEmpty() ? 'Pay the open invoice below to unlock everything instantly.' : 'Choose a plan below to unlock everything instantly.' }}
                @elseif($state['status'] === 'trialing')
                    Everything is unlocked during your trial. Pick a plan any time — your paid period starts when the trial ends, so you don't lose any free days.
                @elseif($company->billing_type === 'postpaid')
                    {{ $state['employees'] }} active employees · estimated next invoice <b>{{ $k($state['estimate'] ?? 0) }}</b>, due {{ $prices['invoice_due_days'] }} days after it's sent.
                @else
                    Your subscription renews {{ optional($company->paid_until)->format('j M Y') }}. We'll email the renewal invoice {{ $prices['renewal_notice_days'] }} days before.
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

    @unless($dpoReady)
        <div class="pp-callout" style="margin:0 0 1rem"><i class="bi bi-info-circle"></i><div>Online payments are being switched on. You can still choose a plan; we'll email your invoice.</div></div>
    @endunless

    {{-- ============ OPEN INVOICES ============ --}}
    @if($state['open_invoices']->isNotEmpty())
        <section class="pr-card">
            <div class="pr-card-head"><div><h2>To pay</h2><div class="sub">Pay now by mobile money or card.</div></div></div>
            <div class="bl-due">
                @foreach($state['open_invoices'] as $inv)
                    <div class="bl-due-row {{ $inv->isOverdue() ? 'late' : '' }}">
                        <span class="ic"><i class="bi bi-receipt"></i></span>
                        <div class="main">
                            <b>{{ $inv->title() }}</b>
                            <small>{{ $inv->number }} · {{ $inv->isOverdue() ? 'overdue since' : 'due' }} {{ optional($inv->due_at)->format('j M Y') }}</small>
                        </div>
                        <span class="amt">{{ $k($inv->total) }}</span>
                        <a class="pp-btn pp-btn-ghost pp-btn-sm" href="{{ route('billing.invoices.pdf', $inv) }}" target="_blank"><i class="bi bi-file-earmark-pdf"></i></a>
                        <button type="button" class="pp-btn pp-btn-primary pp-btn-sm" data-pay="{{ $inv->id }}" data-number="{{ $inv->number }}" data-amount="{{ $k($inv->total) }}"
                                data-mobile="{{ route('billing.pay.mobile', $inv) }}" data-card="{{ route('billing.pay.card', $inv) }}" @disabled(! $dpoReady)>
                            <i class="bi bi-lightning-charge"></i> Pay now
                        </button>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ============ PLANS ============ --}}
    @if(! $onPlan || $state['status'] === 'trialing' || $state['limited'])
        <section class="pr-card">
            <div class="pr-card-head"><div><h2>Choose your plan</h2><div class="sub">You have {{ $state['employees'] }} active {{ \Illuminate\Support\Str::plural('employee', $state['employees']) }}. The plan follows your team size.</div></div></div>
            <div class="bl-plans">
                <div class="bl-plan {{ $state['recommended'] === 'starter' ? 'rec' : '' }} {{ $tooBig ? 'off' : '' }}">
                    @if($state['recommended'] === 'starter')<span class="badge-rec">Recommended for you</span>@endif
                    <h3>Starter</h3>
                    <p class="pp-muted">Up to {{ $limit }} employees · pay up front</p>
                    <div class="bl-cycle" role="radiogroup">
                        <label><input type="radio" name="cycle_pick" value="monthly" checked> <span><b>{{ $k($prices['starter_monthly']) }}</b> / month</span></label>
                        <label><input type="radio" name="cycle_pick" value="annual"> <span><b>{{ $k($prices['starter_annual']) }}</b> / year
                            @if($prices['starter_monthly'] * 12 > $prices['starter_annual'])<em>save {{ $k($prices['starter_monthly'] * 12 - $prices['starter_annual']) }}</em>@endif</span></label>
                    </div>
                    <ul>
                        <li><i class="bi bi-check2"></i> Payroll with PAYE, NAPSA &amp; NHIMA</li>
                        <li><i class="bi bi-check2"></i> Employee self-service portal</li>
                        <li><i class="bi bi-check2"></i> Leave, overtime &amp; loans</li>
                        <li><i class="bi bi-check2"></i> Approval chain &amp; audit trail</li>
                    </ul>
                    <form method="POST" action="{{ route('billing.prepaid') }}" class="bl-plan-go">
                        @csrf
                        <input type="hidden" name="cycle" value="monthly" id="blCycle">
                        <button class="pp-btn pp-btn-primary" style="width:100%" @disabled($tooBig)>
                            {{ $tooBig ? 'You have more than ' . $limit . ' employees' : 'Choose Starter & pay' }}
                        </button>
                    </form>
                </div>

                <div class="bl-plan dark {{ $state['recommended'] === 'business' ? 'rec' : '' }}">
                    @if($state['recommended'] === 'business')<span class="badge-rec">Recommended for you</span>@endif
                    <h3>Business</h3>
                    <p>More than {{ $limit }} employees · pay after each month</p>
                    <div class="bl-price"><b>{{ $k($prices['business_per_employee']) }}</b> per active employee / month</div>
                    <small>Minimum {{ $k($prices['business_minimum']) }} a month · today that's about <b>{{ $k(max($prices['business_minimum'], max($state['employees'], 1) * $prices['business_per_employee'])) }}</b></small>
                    <ul>
                        <li><i class="bi bi-check2"></i> Everything in Starter</li>
                        <li><i class="bi bi-check2"></i> Only pay for people you actually paid</li>
                        <li><i class="bi bi-check2"></i> Option of your own private web address</li>
                        <li><i class="bi bi-check2"></i> Priority support</li>
                    </ul>
                    <form method="POST" action="{{ route('billing.postpaid') }}" class="bl-plan-go">
                        @csrf
                        <label class="bl-accept"><input type="checkbox" name="accept" value="1" required> I agree to pay each monthly invoice within {{ $prices['invoice_due_days'] }} days.</label>
                        <button class="pp-btn pp-btn-light" style="width:100%">Start Business plan</button>
                    </form>
                </div>
            </div>
        </section>
    @endif

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
                                    <td><span class="pr-pill {{ $inv->status === 'paid' ? 'locked' : ($inv->isOverdue() ? 'review' : 'draft') }}">{{ $inv->status === 'paid' ? 'Paid ' . optional($inv->paid_at)->format('j M') : ($inv->isOverdue() ? 'Overdue' : ucfirst($inv->status)) }}</span></td>
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

            @if($state['recommended'] === 'business' || $company->plan === 'business' || $company->isDedicated())
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
                            <li class="{{ $p->status === 'paid' ? 'approved' : ($p->status === 'failed' ? 'sent_back' : '') }}">
                                <i class="bi {{ $p->method === 'card' ? 'bi-credit-card' : 'bi-phone' }}"></i>
                                <div><b>{{ $k($p->amount) }} · {{ ucfirst($p->status) }}</b>
                                    <small>{{ $p->method === 'card' ? 'Card' : (config('dpo.networks.' . $p->network . '.label') ?? 'Mobile money') }} · {{ $p->created_at->format('j M, H:i') }}</small></div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </aside>
    </div>
</div>

{{-- ============ PAYMENT WINDOW ============ --}}
@push('modals')
<div class="pr pp">
<div class="bl-modal-bg" id="blModalBg" hidden></div>
<div class="bl-modal" id="blModal" hidden role="dialog" aria-modal="true" aria-labelledby="blModalTitle">
    <button type="button" class="pr-drawer-x bl-x" id="blClose" aria-label="Close"><i class="bi bi-x-lg"></i></button>
    <div class="bl-step" data-step="choose">
        <div class="pp-eyebrow" style="color:var(--zp-green)">Pay invoice <span id="blNum"></span></div>
        <h2 id="blModalTitle" class="bl-amount"></h2>
        <div class="bl-tabs">
            <button type="button" class="on" data-tab="mobile"><i class="bi bi-phone"></i> Mobile money</button>
            <button type="button" data-tab="card"><i class="bi bi-credit-card"></i> Card</button>
        </div>
        <form id="blMobile" class="bl-pane" data-pane="mobile">
            <div class="bl-nets">
                @foreach($networks as $key => $n)
                    <label class="bl-net {{ $key }}"><input type="radio" name="network" value="{{ $key }}" @checked($loop->first)><span>{{ $n['label'] }}</span></label>
                @endforeach
            </div>
            <div class="pp-field" style="margin-top:.9rem"><label for="blPhone">Phone number</label>
                <input class="pp-input" id="blPhone" name="phone" inputmode="tel" placeholder="097 123 4567" autocomplete="tel" required></div>
            <button class="pp-btn pp-btn-primary" style="width:100%;margin-top:1rem;padding:.95rem"><i class="bi bi-send"></i> <span>Send payment prompt</span></button>
            <p class="pp-muted" style="font-size:.78rem;margin:.6rem 0 0;text-align:center">You'll get a prompt on your phone — enter your PIN to approve.</p>
        </form>
        <div class="bl-pane" data-pane="card" hidden>
            <p class="pp-muted" style="font-size:.88rem">Your card is entered on DPO's secure page in a small window — your card details never touch ZamPayroll.</p>
            <button type="button" id="blCard" class="pp-btn pp-btn-primary" style="width:100%;padding:.95rem"><i class="bi bi-shield-lock"></i> <span>Pay securely by card</span></button>
            <p class="pp-muted" style="font-size:.74rem;margin:.6rem 0 0;text-align:center"><i class="bi bi-lock"></i> Secured by DPO Pay · Visa · Mastercard</p>
        </div>
        <div class="pp-error" id="blErr" hidden style="margin:.8rem 0 0"></div>
    </div>
    <div class="bl-step" data-step="wait" hidden>
        <div class="bl-spin"><i class="bi bi-phone-vibrate"></i></div>
        <h2>Waiting for approval…</h2>
        <p class="pp-muted" id="blWaitMsg">Approve the payment on your phone.</p>
        <p class="pp-muted" style="font-size:.78rem">This updates automatically. Don't close this window.</p>
    </div>
    <div class="bl-step" data-step="done" hidden>
        <div class="pp-done-badge" style="margin-bottom:.6rem"><i class="bi bi-check-lg"></i></div>
        <h2>Payment received 🎉</h2>
        <p class="pp-muted">Your receipt is on its way to your email.</p>
        <button type="button" class="pp-btn pp-btn-primary" onclick="location.href='{{ route('billing.index') }}'">Done</button>
    </div>
    <div class="bl-step" data-step="fail" hidden>
        <div class="bl-spin fail"><i class="bi bi-x-lg"></i></div>
        <h2>Payment didn't go through</h2>
        <p class="pp-muted" id="blFailMsg"></p>
        <button type="button" class="pp-btn pp-btn-ghost" data-retry>Try again</button>
    </div>
</div>
</div>
@endpush
@endsection

@push('scripts')
<script src="{{ asset('assets/js/zp-people.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const page = document.getElementById('blPage');
    const csrf = page.dataset.csrf;
    const $ = (s) => document.querySelector(s);
    const modal = $('#blModal'), bg = $('#blModalBg');
    let cur = null, poll = null, popup = null;

    // plan cycle toggle
    document.querySelectorAll('input[name=cycle_pick]').forEach(r => r.addEventListener('change', () => { const c = document.getElementById('blCycle'); if (c) c.value = r.value; }));

    const step = (name) => modal.querySelectorAll('.bl-step').forEach(s => s.hidden = s.dataset.step !== name);
    const open = (btn) => {
        cur = btn.dataset; $('#blNum').textContent = cur.number; $('#blModalTitle').textContent = cur.amount;
        $('#blErr').hidden = true; step('choose'); modal.hidden = false; bg.hidden = false;
        setTimeout(() => $('#blPhone').focus(), 50);
    };
    const close = () => { modal.hidden = true; bg.hidden = true; clearInterval(poll); };
    document.querySelectorAll('[data-pay]').forEach(b => b.addEventListener('click', () => open(b)));
    $('#blClose').addEventListener('click', close); bg.addEventListener('click', close);
    modal.querySelector('[data-retry]').addEventListener('click', () => step('choose'));
    modal.querySelectorAll('.bl-tabs button').forEach(t => t.addEventListener('click', () => {
        modal.querySelectorAll('.bl-tabs button').forEach(x => x.classList.toggle('on', x === t));
        modal.querySelectorAll('.bl-pane').forEach(p => p.hidden = p.dataset.pane !== t.dataset.tab);
    }));

    // guess the network from the number
    const prefixes = @json(collect($networks)->map(fn ($n) => $n['prefixes']));
    $('#blPhone').addEventListener('input', (e) => {
        const d = e.target.value.replace(/\D/g, '').replace(/^260/, '0');
        const local = d.length >= 9 && !d.startsWith('0') ? '0' + d : d;
        for (const [net, list] of Object.entries(prefixes)) if (list.some(p => local.startsWith(p))) { const r = modal.querySelector('input[value=' + net + ']'); if (r) r.checked = true; }
    });

    const post = async (url, body) => {
        const res = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(body || {}) });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.errors ? Object.values(data.errors)[0][0] : (data.message || 'Something went wrong.'));
        return data;
    };
    const err = (m) => { const e = $('#blErr'); e.textContent = m; e.hidden = false; };
    const watch = (url) => {
        clearInterval(poll);
        poll = setInterval(async () => {
            const r = await fetch(url, { headers: { Accept: 'application/json' } }).then(r => r.json()).catch(() => null);
            if (!r) return;
            if (r.status === 'paid') { clearInterval(poll); step('done'); zpPeople.confetti(); popup && !popup.closed && popup.close(); }
            else if (r.status === 'failed' || r.status === 'cancelled') { clearInterval(poll); $('#blFailMsg').textContent = r.message; step('fail'); }
        }, 4000);
    };

    $('#blMobile').addEventListener('submit', async (e) => {
        e.preventDefault(); e.stopPropagation();
        const btn = e.target.querySelector('button'); btn.disabled = true;
        try {
            const d = await post(cur.mobile, { network: new FormData(e.target).get('network'), phone: $('#blPhone').value });
            $('#blWaitMsg').textContent = d.message; step('wait'); watch(d.status_url);
        } catch (x) { err(x.message); }
        btn.disabled = false;
    });

    $('#blCard').addEventListener('click', async () => {
        // open the window first (inside the click) so pop-up blockers allow it
        popup = window.open('', 'dpo_pay', 'width=520,height=760');
        try {
            const d = await post(cur.card);
            if (popup && !popup.closed) { popup.location = d.url; } else { location.href = d.url; return; }
            $('#blWaitMsg').textContent = 'Complete the payment in the DPO window.'; step('wait'); watch(d.status_url);
        } catch (x) { popup && popup.close(); err(x.message); }
    });
    window.addEventListener('message', (e) => { if (e.data === 'zp-dpo-done' && cur) { /* polling will pick up the result */ } });

    @if($payInvoice)
        const auto = document.querySelector('[data-pay="{{ $payInvoice->id }}"]'); if (auto && !auto.disabled) open(auto);
    @endif
});
</script>
@endpush
