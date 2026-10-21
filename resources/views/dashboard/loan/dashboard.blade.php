@extends('layouts.app')

@section('title', 'Loans · ZamPayroll')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/zp-people.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/zp-payroll.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/zp-requests.css') }}">
@endpush

@php
    $u = auth()->user();
    $k = fn ($n) => 'K ' . number_format((float) $n, 2);
    $tabs = ['pending' => 'Waiting', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled', 'all' => 'All'];
    $plans = \App\Models\LoanRequest::PAYMENT_PLANS;
@endphp

@section('content')
<div class="pr pp rq">
    <div class="pr-head">
        <div>
            <h1>Loans &amp; advances</h1>
            <p>Three steps, each recorded: <b>approve</b> → <b>pay out</b> → repayments come out of pay automatically.</p>
        </div>
        <div class="pr-head-actions">
            <a href="{{ route('loan.ledger.index') }}" class="pp-btn pp-btn-ghost"><i class="bi bi-journal-text"></i> Loan ledger</a>
            @if($u->hasPermission('loans.manage'))<a href="{{ route('loan.ledger.create') }}" class="pp-btn pp-btn-primary"><i class="bi bi-plus-lg"></i> Add a loan</a>@endif
        </div>
    </div>

    <div class="rq-kpis">
        <div class="rq-kpi main"><span>Requests waiting</span><b>{{ $counts['pending'] }}</b><small style="color:rgba(255,255,255,.7)">{{ $k($totals['pending_amount']) }} asked</small></div>
        <div class="rq-kpi"><span>Approved, not paid out</span><b>{{ $k($totals['awaiting_payout']) }}</b></div>
        <div class="rq-kpi"><span>Running loans</span><b>{{ $totals['active'] }}</b></div>
        <div class="rq-kpi"><span>Still owed to the company</span><b>{{ $k($totals['outstanding']) }}</b></div>
    </div>

    @if($awaitingPayout->isNotEmpty())
        <section class="pr-card" style="margin-bottom:1rem;border-color:#c9d6f5">
            <div class="pr-card-head">
                <div><h2><i class="bi bi-cash-stack" style="color:#2E4C9A"></i> Waiting to be paid out</h2><div class="sub">Approved, but the money hasn't been recorded as paid. Nothing is deducted from pay until it is.</div></div>
            </div>
            <div class="rq-list">
                @foreach($awaitingPayout as $loan)
                    <article class="rq-item">
                        <div class="rq-av">{{ $loan->employee?->initials }}</div>
                        <div class="rq-main">
                            <div class="rq-who"><b>{{ $loan->employee?->full_name }}</b><small>{{ $loan->employee?->employee_id }}</small><span class="rq-pill pending_disbursement">Not paid out</span></div>
                            <div class="rq-what">
                                <span><i class="bi bi-cash"></i> <b>{{ $k($loan->principal_amount) }}</b></span>
                                <span><i class="bi bi-arrow-repeat"></i> {{ $loan->payment_plan === 'once_off' ? 'Once-off' : $k($loan->installment_amount) . ' ' . ($loan->payment_plan === 'bi_monthly' ? 'every 2 months' : 'a month') }}</span>
                                <span><i class="bi bi-calendar"></i> from {{ $loan->next_deduction_date?->format('F Y') }}</span>
                            </div>
                            <div class="rq-trail"><span><i class="bi bi-check2-circle"></i>Approved by {{ $loan->approvedBy?->name ?? '—' }} · {{ optional($loan->approved_at)->format('j M Y') }}</span></div>
                        </div>
                        <div class="rq-side">
                            @if($service->canDisburse($u, $loan))
                                <button type="button" class="pp-btn pp-btn-sm pp-btn-primary" data-rq-open="pay{{ $loan->id }}"><i class="bi bi-cash-coin"></i> Record payout</button>
                            @elseif($u->hasPermission('loans.disburse'))
                                <small style="color:var(--zp-ink-3);max-width:180px;text-align:right">Someone other than the approver records the payout.</small>
                            @else
                                <small style="color:var(--zp-ink-3)">Waiting for payroll/finance</small>
                            @endif
                            @if($u->hasPermission('loans.manage'))
                                <details class="rq-more"><summary><i class="bi bi-x-circle"></i> Cancel</summary>
                                    <form method="POST" action="{{ route('loan.loans.cancel', $loan) }}">@csrf
                                        <input class="pp-input" type="text" name="reason" placeholder="Reason" style="width:150px;padding:.45rem .6rem;font-size:.8rem"><button class="pp-btn pp-btn-sm rq-btn-no">Cancel loan</button></form>
                                </details>
                            @endif
                        </div>
                    </article>
                    @push('modals')
                        <div class="rq-modal-bg" id="pay{{ $loan->id }}">
                            <form class="rq-modal" method="POST" action="{{ route('loan.disburse', $loan) }}">
                                @csrf
                                <h2 class="zp-display">Record payout</h2>
                                <div class="sub">{{ $loan->employee?->full_name }} · {{ $k($loan->principal_amount) }}. Only record this once the money has been paid.</div>
                                <div class="rq-grid2">
                                    <div class="pp-field"><label>Date paid</label><input class="pp-input" type="date" name="date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required></div>
                                    <div class="pp-field"><label>How</label>
                                        <select class="pp-select" name="method" required>@foreach(\App\Models\Loan::DISBURSEMENT_METHODS as $m => $label)<option value="{{ $m }}">{{ $label }}</option>@endforeach</select>
                                    </div>
                                </div>
                                <div class="pp-field"><label>Reference <small>(bank or mobile money reference)</small></label><input class="pp-input" name="reference" maxlength="120"></div>
                                <div class="pp-field"><label>First repayment from</label><input class="pp-input" type="month" name="first_deduction" value="{{ $loan->next_deduction_date?->format('Y-m') }}" min="{{ now()->format('Y-m') }}"></div>
                                <div class="rq-modal-actions">
                                    <button type="button" class="pp-btn pp-btn-ghost" data-rq-close>Close</button>
                                    <button class="pp-btn pp-btn-primary"><i class="bi bi-check-lg"></i> Money was paid</button>
                                </div>
                            </form>
                        </div>
                    @endpush
                @endforeach
            </div>
        </section>
    @endif

    <div class="rq-bar">
        <nav class="rq-tabs">
            @foreach($tabs as $key => $label)
                <a href="{{ route('loan.dashboard', array_filter(['status' => $key, 'q' => request('q')])) }}" class="rq-tab {{ $status === $key ? 'on' : '' }}">{{ $label }} @isset($counts[$key])<span class="n">{{ $counts[$key] }}</span>@endisset</a>
            @endforeach
        </nav>
        <form class="rq-filters" method="GET"><input type="hidden" name="status" value="{{ $status }}"><input class="pp-input" type="search" name="q" value="{{ request('q') }}" placeholder="Search a name or number"></form>
    </div>

    <div class="rq-list">
        @forelse($requests as $req)
            @php $emp = $req->employee; $decide = $service->canDecide($u, $req); $months = $req->estimatedInstallments(); @endphp
            <article class="rq-item {{ $decide ? 'attn' : '' }}">
                <div class="rq-av">{{ $emp?->initials }}</div>
                <div class="rq-main">
                    <div class="rq-who"><b>{{ $emp?->full_name ?? 'Former employee' }}</b><small>{{ $emp?->employee_id }}{{ $emp?->department ? ' · ' . $emp->department : '' }}</small>
                        @if($emp && (float) $emp->total_loan_balance > 0)<span class="rq-type">already owes {{ $k($emp->total_loan_balance) }}</span>@endif</div>
                    <div class="rq-what">
                        <span><i class="bi bi-cash"></i> <b>{{ $k($req->amount) }}</b></span>
                        <span><i class="bi bi-arrow-repeat"></i> {{ $plans[$req->payment_plan] ?? $req->payment_plan }}{{ $req->installment_amount ? ' · ' . $k($req->installment_amount) : '' }}</span>
                        @if($months)<span><i class="bi bi-hourglass-split"></i> about {{ $months }} deduction{{ $months === 1 ? '' : 's' }}</span>@endif
                        @if($emp && $emp->net_salary)<span><i class="bi bi-wallet2"></i> take-home {{ $k($emp->net_salary) }}</span>@endif
                    </div>
                    @if($req->reason)<div class="rq-note">“{{ $req->reason }}”</div>@endif
                    @if($req->documents)
                        <div class="rq-docs">@foreach($req->documents as $doc)<a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($doc['path']) }}" target="_blank" rel="noopener"><i class="bi bi-paperclip"></i> {{ \Illuminate\Support\Str::limit($doc['name'] ?? 'Document', 28) }}</a>@endforeach</div>
                    @endif
                    <div class="rq-trail">
                        <span><i class="bi bi-send"></i>Asked {{ $req->created_at->diffForHumans() }}</span>
                        @if($req->reviewed_at && in_array($req->status, ['approved', 'rejected'], true))<span><i class="bi bi-check2-circle"></i>{{ ucfirst($req->status) }} by {{ $req->reviewedBy?->name }} · {{ $req->reviewed_at->format('j M') }}</span>@endif
                        @if($req->loan)<span><i class="bi bi-journal-text"></i><a href="{{ route('loan.ledger.show', $req->loan) }}">On the ledger · {{ strtolower($req->loan->statusLabel()) }}</a></span>@endif
                        @if($req->hr_comment)<span><i class="bi bi-chat-left-text"></i>{{ $req->hr_comment }}</span>@endif
                        @if($req->status === 'cancelled')<span><i class="bi bi-x-circle"></i>Cancelled{{ $req->cancel_reason ? ' — ' . $req->cancel_reason : '' }}</span>@endif
                    </div>
                </div>
                <div class="rq-side">
                    <span class="rq-pill {{ $req->status }}">{{ $req->statusLabel() }}</span>
                    @if($decide)
                        <button type="button" class="pp-btn pp-btn-sm pp-btn-primary" data-rq-open="ok{{ $req->id }}"><i class="bi bi-check-lg"></i> Approve…</button>
                        <form method="POST" action="{{ route('loan.reject', $req) }}" class="rq-decide">@csrf
                            <input class="pp-input" type="text" name="comment" placeholder="Reason (optional)" style="width:150px"><button class="pp-btn pp-btn-sm rq-btn-no"><i class="bi bi-x-lg"></i> Reject</button></form>
                        @push('modals')
                            <div class="rq-modal-bg" id="ok{{ $req->id }}">
                                <form class="rq-modal" method="POST" action="{{ route('loan.approve', $req) }}">
                                    @csrf
                                    <h2 class="zp-display">Approve loan</h2>
                                    <div class="sub">{{ $emp?->full_name }} asked for {{ $k($req->amount) }}. You can change the terms. Approving puts it on the ledger as "not paid out yet".</div>
                                    <div class="rq-grid2">
                                        <div class="pp-field"><label>Amount (K)</label><input class="pp-input" type="number" step="0.01" min="1" name="amount" value="{{ (float) $req->amount }}"></div>
                                        @if($req->payment_plan !== 'once_off')<div class="pp-field"><label>Each deduction (K)</label><input class="pp-input" type="number" step="0.01" min="0.01" name="installment_amount" value="{{ (float) $req->installment_amount }}"></div>@endif
                                    </div>
                                    <div class="pp-field"><label>First deduction</label><input class="pp-input" type="month" name="first_deduction" value="{{ now()->addMonthNoOverflow()->format('Y-m') }}" min="{{ now()->format('Y-m') }}"></div>
                                    <div class="pp-field"><label>Note to the employee <small>(optional)</small></label><input class="pp-input" type="text" name="comment" maxlength="500"></div>
                                    <div class="rq-modal-actions"><button type="button" class="pp-btn pp-btn-ghost" data-rq-close>Close</button><button class="pp-btn pp-btn-primary"><i class="bi bi-check-lg"></i> Approve</button></div>
                                </form>
                            </div>
                        @endpush
                    @endif
                    @if($req->status === 'pending' && $u->hasPermission('loans.manage'))
                        <details class="rq-more"><summary><i class="bi bi-x-circle"></i> Cancel</summary>
                            <form method="POST" action="{{ route('loan.cancel', $req) }}">@csrf<input class="pp-input" type="text" name="reason" placeholder="Reason" style="width:150px;padding:.45rem .6rem;font-size:.8rem"><button class="pp-btn pp-btn-sm rq-btn-no">Cancel it</button></form>
                        </details>
                    @endif
                </div>
            </article>
        @empty
            <div class="rq-empty"><i class="bi bi-cash-coin"></i>{{ $status === 'pending' ? 'No loan requests waiting.' : 'Nothing here.' }}</div>
        @endforelse
    </div>
    <div style="margin-top:1rem">{{ $requests->links() }}</div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-rq-open]').forEach(b => b.addEventListener('click', () => document.getElementById(b.dataset.rqOpen).classList.add('open')));
    document.querySelectorAll('.rq-modal-bg').forEach(bg => bg.addEventListener('click', e => { if (e.target === bg || e.target.closest('[data-rq-close]')) bg.classList.remove('open'); }));
});
</script>
@endpush
