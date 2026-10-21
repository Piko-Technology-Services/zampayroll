@extends('platform.layout')
@section('title', $company->name . ' · ZamPayroll admin')
@php $k = fn ($n) => 'K ' . number_format((float) $n, 2); @endphp
@section('content')
<a class="pr-crumb" href="{{ route('platform.companies') }}"><i class="bi bi-arrow-left"></i> Companies</a>
<div class="pr-head">
    <div><h1>{{ $company->name }} <span class="pl-tag {{ $company->subscription_status }}" style="vertical-align:middle;font-family:var(--zp-font)">{{ str_replace('_', ' ', $company->subscription_status) }}</span></h1>
        <p>{{ $company->company_code }} · {{ $company->isDedicated() ? 'Private: ' . $company->workspaceHost() : 'Shared workspace' }} · joined {{ $company->created_at->format('j M Y') }} · {{ $state['employees'] }} active employees</p></div>
</div>

<div class="pl-kpis">
    <div class="pr-kpi main"><span>Plan</span><b>{{ $company->plan ? ucfirst($company->plan) : 'Trial' }}</b><span class="pr-delta">{{ $company->billing_type ?: 'not chosen' }}{{ $company->billing_type === 'prepaid' ? ' · ' . $company->billing_cycle : '' }}</span></div>
    <div class="pr-kpi"><span>{{ $company->subscription_status === 'trialing' ? 'Trial ends' : ($company->billing_type === 'postpaid' ? 'Next invoice' : 'Paid until') }}</span>
        <b>{{ $state['countdown_to'] ? $state['countdown_to']->format('j M Y') : '—' }}</b><span class="pr-delta">{{ $state['countdown_to'] ? $state['countdown_to']->diffForHumans() : '' }}</span></div>
    <div class="pr-kpi"><span>Open invoices</span><b>{{ $k($state['open_invoices']->sum('total')) }}</b></div>
    <div class="pr-kpi"><span>Lifetime paid</span><b>{{ $k($invoices->where('status', 'paid')->sum('total')) }}</b></div>
    <div class="pr-kpi"><span>Access</span><b style="color:{{ $state['limited'] ? 'var(--zp-red)' : 'var(--zp-green)' }}">{{ $state['limited'] ? 'Read-only' : 'Full' }}</b></div>
</div>

<div class="pr-layout" style="grid-template-columns:minmax(0,1fr) 360px">
    <div>
        <section class="pr-card">
            <div class="pr-card-head"><h2>Invoices</h2></div>
            @if($invoices->isEmpty())<div class="pr-empty"><i class="bi bi-receipt"></i>No invoices yet.</div>@else
            <div class="pr-tablewrap"><table class="pr-table pr-hist">
                <thead><tr><th>Invoice</th><th>For</th><th>Status</th><th style="text-align:right">Total</th><th>Actions</th></tr></thead>
                <tbody>
                @foreach($invoices as $inv)
                    <tr style="cursor:default">
                        <td><a href="{{ route('platform.invoices.pdf', $inv) }}" target="_blank"><b>{{ $inv->number }}</b></a><br><small class="pp-muted">{{ $inv->created_at->format('j M Y') }} · due {{ optional($inv->due_at)->format('j M') }}</small></td>
                        <td style="white-space:normal;max-width:260px">{{ $inv->title() }}</td>
                        <td><span class="pl-tag {{ $inv->status === 'paid' ? 'active' : ($inv->isOverdue() ? 'suspended' : ($inv->status === 'void' ? '' : 'trialing')) }}">{{ $inv->isOverdue() ? 'overdue' : $inv->status }}</span></td>
                        <td class="num"><b>{{ $k($inv->total) }}</b></td>
                        <td>
                            @if($inv->status === 'open')
                                <details><summary class="pp-link" style="font-size:.8rem">Record payment</summary>
                                    <form method="POST" action="{{ route('platform.invoices.action', $inv) }}" style="display:grid;gap:.4rem;margin-top:.4rem">@csrf
                                        <input type="hidden" name="action" value="record_payment">
                                        <select name="method" class="pp-select" style="padding:.4rem"><option value="bank">Bank transfer</option><option value="mobile_money">Mobile money</option><option value="cash">Cash</option><option value="card">Card</option></select>
                                        <input name="reference" class="pp-input" style="padding:.4rem" placeholder="Reference / receipt no." required>
                                        <button class="pp-btn pp-btn-primary pp-btn-sm">Mark paid</button>
                                    </form></details>
                                <form method="POST" action="{{ route('platform.invoices.action', $inv) }}" style="display:inline">@csrf<input type="hidden" name="action" value="check"><button class="pp-link" style="font-size:.78rem">Check DPO</button></form> ·
                                <form method="POST" action="{{ route('platform.invoices.action', $inv) }}" style="display:inline" onsubmit="return confirm('Void {{ $inv->number }}?')">@csrf<input type="hidden" name="action" value="void"><button class="pp-link" style="font-size:.78rem;color:var(--zp-red)">Void</button></form> ·
                            @endif
                            <form method="POST" action="{{ route('platform.invoices.action', $inv) }}" style="display:inline">@csrf<input type="hidden" name="action" value="resend"><button class="pp-link" style="font-size:.78rem">Email</button></form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>@endif
        </section>

        <div class="pl-grid2" style="margin-top:1rem">
            <section class="pr-card">
                <div class="pr-card-head"><h2>Users</h2></div>
                <div class="pl-list">
                    @foreach($users as $u)
                        <div class="pl-row"><i class="bi {{ $u->employee_id ? 'bi-person-badge' : 'bi-person-gear' }}"></i><div class="grow"><b>{{ $u->name }}</b><small>{{ $u->email }} · {{ $u->role }}{{ $u->last_login_at ? ' · last seen ' . \Carbon\Carbon::parse($u->last_login_at)->diffForHumans() : '' }}</small></div></div>
                    @endforeach
                </div>
            </section>
            <section class="pr-card">
                <div class="pr-card-head"><h2>Payments</h2></div>
                <div class="pl-list">
                    @forelse($payments as $p)
                        <div class="pl-row"><i class="bi {{ $p->method === 'card' ? 'bi-credit-card' : 'bi-phone' }}"></i><div class="grow"><b>{{ $k($p->amount) }}</b><small>{{ $p->created_at->format('j M H:i') }} · {{ $p->provider }} · {{ $p->trans_ref ?: $p->reference }}{{ $p->result_explanation ? ' · ' . $p->result_explanation : '' }}</small></div><span class="pl-tag {{ $p->status === 'paid' ? 'active' : ($p->status === 'pending' ? 'trialing' : 'suspended') }}">{{ $p->status }}</span></div>
                    @empty<div class="pp-muted">No payments.</div>@endforelse
                </div>
            </section>
            <section class="pr-card">
                <div class="pr-card-head"><h2>Tickets</h2></div>
                <div class="pl-list">
                    @forelse($tickets as $t)
                        <a class="pl-row" href="{{ route('platform.tickets.show', $t) }}"><i class="bi bi-life-preserver"></i><div class="grow"><b>{{ $t->subject }}</b><small>{{ $t->number }} · {{ $t->status }}</small></div></a>
                    @empty<div class="pp-muted">No tickets.</div>@endforelse
                </div>
            </section>
            <section class="pr-card">
                <div class="pr-card-head"><h2>Activity</h2><a class="pp-link" href="{{ route('platform.audit', ['q' => $company->company_code]) }}"></a></div>
                <ul class="pr-history">
                    @forelse($audit as $a)
                        <li><i class="bi bi-dot"></i><div><b>{{ $a->summary ?: $a->action }}</b><small>{{ $a->user_name ?? 'System' }} · {{ $a->created_at->diffForHumans() }}</small></div></li>
                    @empty<li class="pp-muted">No activity yet.</li>@endforelse
                </ul>
            </section>
        </div>
    </div>

    <aside class="pr-side">
        <section class="pr-card">
            <div class="pr-card-head" style="margin-bottom:.6rem"><h2 style="font-size:1.05rem">Manage subscription</h2></div>
            <div class="pl-actions">
                <form method="POST" action="{{ route('platform.companies.action', $company->id) }}">@csrf<input type="hidden" name="action" value="extend_trial">
                    <label>Extend free trial</label><input class="pp-input" type="number" name="days" value="7" min="1" max="90"><button class="pp-btn pp-btn-ghost pp-btn-sm">+ days</button></form>
                <form method="POST" action="{{ route('platform.companies.action', $company->id) }}">@csrf<input type="hidden" name="action" value="extend_paid">
                    <label>Give paid access (e.g. paid offline)</label><input class="pp-input" type="number" name="days" value="30" min="1" max="400"><button class="pp-btn pp-btn-ghost pp-btn-sm">+ days</button></form>
                <form method="POST" action="{{ route('platform.companies.action', $company->id) }}">@csrf<input type="hidden" name="action" value="set_plan">
                    <label>Plan</label>
                    <select class="pp-select" name="plan"><option value="starter" @selected($company->plan === 'starter')>Starter (pre-paid)</option><option value="business" @selected($company->plan === 'business')>Business (post-paid)</option></select>
                    <select class="pp-select" name="billing_cycle"><option value="monthly">Monthly</option><option value="annual" @selected($company->billing_cycle === 'annual')>Yearly</option></select>
                    <button class="pp-btn pp-btn-ghost pp-btn-sm">Set</button></form>
                <form method="POST" action="{{ route('platform.companies.action', $company->id) }}">@csrf<input type="hidden" name="action" value="status">
                    <label>Status</label>
                    <select class="pp-select" name="status">@foreach(['trialing', 'active', 'past_due', 'suspended', 'cancelled'] as $s)<option value="{{ $s }}" @selected($company->subscription_status === $s)>{{ str_replace('_', ' ', $s) }}</option>@endforeach</select>
                    <button class="pp-btn pp-btn-ghost pp-btn-sm">Change</button></form>
                <form method="POST" action="{{ route('platform.companies.action', $company->id) }}">@csrf<input type="hidden" name="action" value="invoice">
                    <label>One-off invoice (e.g. onboarding)</label>
                    <input class="pp-input" name="description" placeholder="Description" required style="flex-basis:100%">
                    <input class="pp-input" name="amount" placeholder="Amount (K)" inputmode="decimal" required><button class="pp-btn pp-btn-ghost pp-btn-sm">Create &amp; email</button></form>
            </div>
        </section>

        <section class="pr-card">
            <div class="pr-card-head" style="margin-bottom:.6rem"><h2 style="font-size:1.05rem"><i class="bi bi-building-lock" style="color:var(--zp-copper)"></i> Private workspace</h2></div>
            @if($company->isDedicated())
                <p class="pp-muted" style="font-size:.85rem;margin:0">Live at <b>{{ $company->workspaceHost() }}</b> · database {{ $company->database_name }}</p>
            @else
                @if($company->requested_subdomain)<p style="font-size:.85rem">Requested <b>{{ $company->requested_subdomain }}</b> {{ optional($company->subdomain_requested_at)->diffForHumans() }}.</p>@endif
                <div class="pl-actions">
                    <form method="POST" action="{{ route('platform.companies.action', $company->id) }}" onsubmit="return confirm('Move {{ addslashes($company->name) }} to its own database now? Their users will need to sign in again.')">@csrf<input type="hidden" name="action" value="upgrade">
                        <label>Move to a private sub-domain</label><input class="pp-input" name="subdomain" value="{{ $company->requested_subdomain }}" placeholder="subdomain" required><button class="pp-btn pp-btn-primary pp-btn-sm">Approve &amp; move</button></form>
                    @if($company->requested_subdomain)
                        <form method="POST" action="{{ route('platform.companies.action', $company->id) }}">@csrf<input type="hidden" name="action" value="reject_upgrade"><button class="pp-link" style="color:var(--zp-red);font-size:.8rem">Decline request</button></form>
                    @endif
                </div>
                <p class="pp-muted" style="font-size:.76rem;margin:.6rem 0 0">Copies every record to a new database, checks the counts, then removes it from the shared one. Billing and tickets stay here.</p>
            @endif
        </section>

        <section class="pr-card">
            <div class="pr-card-head" style="margin-bottom:.6rem"><h2 style="font-size:1.05rem">Contact &amp; notes</h2></div>
            <p style="font-size:.85rem;margin:0 0 .6rem">{{ $company->email }} · {{ $company->phone }}<br>Billing: {{ $company->billing_email ?: '—' }}<br>TPIN: {{ $company->tpin ?: '—' }}</p>
            <form method="POST" action="{{ route('platform.companies.action', $company->id) }}">@csrf<input type="hidden" name="action" value="notes">
                <textarea class="pp-input" name="billing_notes" rows="3" placeholder="Internal notes (not visible to the client)">{{ $company->billing_notes }}</textarea>
                <button class="pp-btn pp-btn-ghost pp-btn-sm" style="margin-top:.4rem">Save notes</button></form>
        </section>
    </aside>
</div>
@endsection
