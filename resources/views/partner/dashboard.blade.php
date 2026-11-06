@extends('partner.layout')
@section('title', 'Partner dashboard · ZamPayroll')
@php
    $k = fn ($n) => 'K ' . number_format((float) $n, 2);
    $rate = rtrim(rtrim(number_format($stats['rate'], 2), '0'), '.');
    $wa = 'https://wa.me/?text=' . rawurlencode($shareText);
@endphp
@section('content')
<div class="pr-head">
    <div><h1>Welcome, {{ \Illuminate\Support\Str::before($partner->name, ' ') }}</h1>
        <p>{{ $partner->typeLabel() }}{{ $partner->firm_name ? ' · ' . $partner->firm_name : '' }} · you earn {{ $rate }}% of every invoice your clients pay</p></div>
</div>

@if($partner->status === 'pending')
    <div class="pp-callout" style="margin-bottom:1rem"><i class="bi bi-hourglass-split"></i><div><b>Your application is being reviewed.</b> We'll email you as soon as your code is live — usually within one working day. Your code below won't work for sign-ups until then.</div></div>
@elseif($partner->status === 'suspended')
    <div class="pp-callout" style="margin-bottom:1rem;background:#FBEAEA;color:#7c1d1b"><i class="bi bi-pause-circle"></i><div><b>Your partner account is paused.</b> Your code can't be used for new sign-ups right now. Please contact ZamPayroll.</div></div>
@endif

<section class="pa-code">
    <div class="pa-code-main">
        <span class="pa-eyebrow">Your partner code</span>
        <div class="pa-code-row">
            <b id="paCode">{{ $partner->code }}</b>
            <button type="button" class="pa-btn" data-copy="{{ $partner->code }}"><i class="bi bi-clipboard"></i> Copy code</button>
        </div>
        <span class="pa-eyebrow" style="margin-top:1rem">Your sign-up link</span>
        <div class="pa-link"><span>{{ $partner->joinUrl() }}</span></div>
        <div class="pa-actions">
            <button type="button" class="pa-btn light" data-copy="{{ $partner->joinUrl() }}"><i class="bi bi-link-45deg"></i> Copy link</button>
            <a class="pa-btn wa" href="{{ $wa }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> Share on WhatsApp</a>
            <a class="pa-btn light" href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode($partner->joinUrl()) }}" target="_blank" rel="noopener"><i class="bi bi-linkedin"></i> LinkedIn</a>
        </div>
        <p class="pa-note">Companies that sign up through your link — or type your code on the sign-up form — are linked to you automatically.</p>
    </div>
    <div class="pa-code-side">
        <div><span>Commission this month</span><b>{{ $k($stats['this_month']) }}</b></div>
        <div><span>Waiting to be paid</span><b>{{ $k($stats['pending']) }}</b></div>
        <div><span>Paid to you so far</span><b>{{ $k($stats['paid']) }}</b></div>
    </div>
</section>

<div class="pl-kpis">
    <div class="pr-kpi main"><span>Clients</span><b>{{ $stats['clients'] }}</b></div>
    <div class="pr-kpi"><span>Paying</span><b>{{ $stats['paying'] }}</b></div>
    <div class="pr-kpi"><span>On free trial</span><b>{{ $stats['trials'] }}</b></div>
    <div class="pr-kpi"><span>Staff on payroll</span><b>{{ number_format($stats['employees']) }}</b></div>
    <div class="pr-kpi"><span>Your rate</span><b>{{ $rate }}%</b></div>
</div>

<div class="pr-layout">
    <div>
        <section class="pr-card" id="clients">
            <div class="pr-card-head"><div><h2>Your clients</h2><div class="sub">Name, size and plan only — salaries and employee details stay private to each company.</div></div></div>
            @if($clients->isEmpty())
                <div class="pa-empty"><i class="bi bi-buildings"></i><b>No clients yet</b><p>Share your link with HR managers and business owners you work with. When they sign up, they appear here straight away.</p>
                    <a class="pp-btn pp-btn-primary" href="{{ $wa }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> Share your link</a></div>
            @else
                <div class="pr-tablewrap">
                    <table class="pr-table pr-hist">
                        <thead><tr><th>Company</th><th>Joined</th><th style="text-align:right">Employees</th><th>Plan</th><th>Contact</th><th style="text-align:right">You've earned</th></tr></thead>
                        <tbody>
                        @foreach($clients as $c)
                            <tr>
                                <td><b>{{ $c->name }}</b>@unless($c->verified)<br><small class="pp-muted">hasn't confirmed their email yet</small>@endunless</td>
                                <td>{{ optional($c->joined)->format('j M Y') }}</td>
                                <td class="num">{{ $c->employees }}</td>
                                <td><span class="pa-tag {{ $c->tone }}">{{ $c->label }}</span>@if($c->trial_ends)<br><small class="pp-muted">ends {{ $c->trial_ends->format('j M') }}</small>@endif</td>
                                <td><small>{{ $c->contact ?: '—' }}</small></td>
                                <td class="num">{{ $c->earned > 0 ? $k($c->earned) : '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="pr-card" id="commission">
            <div class="pr-card-head"><div><h2>Commission</h2><div class="sub">{{ $rate }}% of each paid invoice, before VAT. Earned the moment your client pays.</div></div></div>
            @if($commissions->isEmpty())
                <p class="pp-muted" style="margin:0">Nothing yet. Clients on the 2-month free experience don't pay yet, so commission starts when they pay for a plan.</p>
            @else
                <div class="pr-tablewrap">
                    <table class="pr-table pr-hist">
                        <thead><tr><th>Date</th><th>Client</th><th>Invoice</th><th style="text-align:right">Invoice amount</th><th style="text-align:right">Commission</th><th>Status</th></tr></thead>
                        <tbody>
                        @foreach($commissions as $cm)
                            <tr>
                                <td>{{ $cm->created_at->format('j M Y') }}</td>
                                <td>{{ $cm->company?->name }}</td>
                                <td>{{ $cm->invoice_number }}</td>
                                <td class="num">{{ $k($cm->base_amount) }}</td>
                                <td class="num"><b>{{ $k($cm->amount) }}</b><br><small class="pp-muted">{{ rtrim(rtrim(number_format((float) $cm->rate, 2), '0'), '.') }}%</small></td>
                                <td><span class="pa-tag {{ $cm->status === 'paid' ? 'ok' : ($cm->status === 'void' ? 'bad' : 'trial') }}">{{ $cm->status === 'pending' ? 'waiting' : $cm->status }}</span>@if($cm->paid_at)<br><small class="pp-muted">{{ $cm->paid_at->format('j M') }}{{ $cm->payout_reference ? ' · ' . $cm->payout_reference : '' }}</small>@endif</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>

    <aside>
        <section class="pr-card">
            <div class="pr-card-head" style="margin-bottom:.6rem"><h2 style="font-size:1.05rem"><i class="bi bi-wallet2" style="color:var(--zp-copper)"></i> Where we pay you</h2></div>
            <form method="POST" action="{{ route('partner.payout') }}">
                @csrf
                <div class="pp-field"><label for="payout_method">Pay me by</label>
                    <select class="pp-select" id="payout_method" name="payout_method">
                        <option value="mobile_money" @selected(old('payout_method', $partner->payout_method) === 'mobile_money')>Mobile money</option>
                        <option value="bank" @selected(old('payout_method', $partner->payout_method) === 'bank')>Bank transfer</option>
                    </select></div>
                <div class="pp-field" style="margin-top:.6rem"><label for="payout_details">Number or account details</label>
                    <textarea class="pp-input" id="payout_details" name="payout_details" rows="3" placeholder="Airtel 0977 123 456 (Chanda Mwila) — or bank, branch, account name and number">{{ old('payout_details', $partner->payout_details) }}</textarea></div>
                <div class="pp-field" style="margin-top:.6rem"><label for="firm_name">Firm name <small>optional</small></label>
                    <input class="pp-input" id="firm_name" name="firm_name" value="{{ old('firm_name', $partner->firm_name) }}"></div>
                <button class="pp-btn pp-btn-primary pp-btn-sm" style="margin-top:.7rem">Save payout details</button>
            </form>
        </section>
        <section class="pr-card">
            <div class="pr-card-head" style="margin-bottom:.4rem"><h2 style="font-size:1.05rem"><i class="bi bi-lightbulb" style="color:var(--zp-copper)"></i> Getting clients</h2></div>
            <ul class="pa-tips">
                <li>Start with companies already paying you for HR work — offer to move their payroll for them.</li>
                <li>Plans start at K799 a month, and teams of up to {{ \App\Services\Billing\PlatformSettings::get('free_limit') }} can try it free for 2 months.</li>
                <li>Help each new client paste in their staff list and run the first payroll in week one.</li>
                <li>Ask us on WhatsApp for posters, videos and a demo login for your pitch.</li>
            </ul>
        </section>
    </aside>
</div>
@endsection

@push('scripts')
<style>
.pa-code{display:grid;grid-template-columns:minmax(0,1.7fr) minmax(0,1fr);gap:0;border-radius:22px;overflow:hidden;margin-bottom:1.1rem;background:var(--zp-forest);color:#fff;position:relative}
.pa-code-main{padding:1.4rem 1.6rem;position:relative;overflow:hidden}
.pa-code-main:after{content:"";position:absolute;right:-110px;bottom:-140px;width:240px;height:240px;border-radius:50%;background:var(--zp-copper);opacity:.9;z-index:0}
.pa-code-main>*{position:relative;z-index:1}
.pa-eyebrow{display:block;font-size:.72rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#9FD8B3}
.pa-code-row{display:flex;align-items:center;gap:1rem;flex-wrap:wrap;margin-top:.2rem}
.pa-code-row b{font:700 clamp(1.8rem,4vw,2.6rem) var(--zp-font-display);color:#F6C79A;letter-spacing:.01em}
.pa-link{margin-top:.35rem;background:rgba(255,255,255,.1);border-radius:12px;padding:.6rem .8rem;font-weight:600;font-size:.92rem;word-break:break-all}
.pa-actions{display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.8rem}
.pa-btn{display:inline-flex;align-items:center;gap:.4rem;border:0;border-radius:99px;padding:.5rem 1rem;font-weight:700;font-size:.85rem;cursor:pointer;background:#fff;color:var(--zp-forest);text-decoration:none}
.pa-btn.light{background:rgba(255,255,255,.14);color:#fff}
.pa-btn.wa{background:#25D366;color:#fff}
.pa-btn.done{background:#3fb06a;color:#fff}
.pa-note{font-size:.8rem;color:#D4ECDC;margin:.8rem 0 0;max-width:520px}
.pa-code-side{display:grid;background:rgba(0,0,0,.18)}
.pa-code-side div{padding:1rem 1.4rem;border-bottom:1px solid rgba(255,255,255,.08)}
.pa-code-side span{display:block;font-size:.75rem;color:#9FD8B3;font-weight:600}
.pa-code-side b{font:700 1.35rem var(--zp-font-display)}
.pa-tag{font-size:.72rem;font-weight:700;padding:.2rem .55rem;border-radius:99px;white-space:nowrap;background:var(--zp-mist);color:var(--zp-ink-2)}
.pa-tag.ok{background:var(--zp-green-50);color:var(--zp-green)}
.pa-tag.trial{background:#EAF1F8;color:#2c5c8a}
.pa-tag.bad{background:#FBEAEA;color:var(--zp-red)}
.pa-tag.free{background:#F1F5EE;color:#3d5a45}
.pa-empty{text-align:center;padding:1.6rem 1rem;color:var(--zp-ink-2)}
.pa-empty i{font-size:2rem;color:var(--zp-green);display:block}
.pa-empty b{display:block;font-size:1.05rem;margin:.4rem 0}
.pa-empty p{max-width:420px;margin:0 auto 1rem;font-size:.9rem}
.pa-tips{margin:0;padding-left:1.1rem;font-size:.86rem;color:var(--zp-ink-2);display:grid;gap:.45rem}
@media (max-width:860px){.pa-code-main:after{display:none}.pa-code{grid-template-columns:1fr}.pa-code-side{grid-template-columns:repeat(3,1fr)}.pa-code-side div{border-bottom:0}}
@media (max-width:520px){.pa-code-side{grid-template-columns:1fr}}
</style>
<script>
document.querySelectorAll('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
        const txt = b.dataset.copy, html = b.innerHTML;
        const done = function () { b.classList.add('done'); b.innerHTML = '<i class="bi bi-check2"></i> Copied'; setTimeout(function () { b.classList.remove('done'); b.innerHTML = html; }, 1800); };
        if (navigator.clipboard) { navigator.clipboard.writeText(txt).then(done, done); }
        else { const t = document.createElement('textarea'); t.value = txt; document.body.appendChild(t); t.select(); try { document.execCommand('copy'); } catch (e) {} t.remove(); done(); }
    });
});
</script>
@endpush
