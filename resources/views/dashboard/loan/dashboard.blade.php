@extends('layouts.app')

@section('content')

<div class="lnd p-5">

    <div class="lnd-header">
        <div>
            <div class="lnd-eyebrow">Loan Management</div>
            <h1 class="lnd-title">Loan Requests</h1>
            <div class="lnd-subtitle">Applications, their proposed repayment terms, and where each one stands</div>
        </div>
        <a href="{{ route('loan.ledger.index') }}" class="lnd-btn lnd-btn-outline">
            <i class="bi bi-journal-text"></i> Loan Ledger
        </a>
    </div>

    @if(session('success'))
        <div class="lnd-alert"><i class="bi bi-check-circle"></i> {{ session('success') }}</div>
    @endif

    {{-- ===== COMPREHENSIVE SUMMARY ===== --}}
    <div class="lnd-summary">
        <div class="lnd-summary-card">
            <div class="lnd-summary-label">Pending Review</div>
            <div class="lnd-summary-value">{{ $counts['pending'] }}</div>
            <div class="lnd-summary-sub">K {{ number_format($totals['pending_amount'], 2) }} requested</div>
        </div>
        <div class="lnd-summary-card">
            <div class="lnd-summary-label">Approved</div>
            <div class="lnd-summary-value">{{ $counts['approved'] }}</div>
            <div class="lnd-summary-sub">K {{ number_format($totals['approved_amount'], 2) }} total</div>
        </div>
        <div class="lnd-summary-card lnd-summary-warn">
            <div class="lnd-summary-label">Awaiting Ledger Entry</div>
            <div class="lnd-summary-value">{{ $totals['awaiting_ledger'] }}</div>
            <div class="lnd-summary-sub">Approved but not yet tracked</div>
        </div>
        <div class="lnd-summary-card">
            <div class="lnd-summary-label">Rejected</div>
            <div class="lnd-summary-value">{{ $counts['rejected'] }}</div>
        </div>
    </div>

    <div class="lnd-tabs">
        @foreach(['all' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $key => $label)
            <a href="{{ route('loan.dashboard', ['status' => $key]) }}" class="lnd-tab {{ $status === $key ? 'lnd-tab-active' : '' }}">
                {{ $label }} @if(isset($counts[$key]))<span class="lnd-tab-count">{{ $counts[$key] }}</span>@endif
            </a>
        @endforeach
    </div>

    <div class="lnd-card">
        <table class="lnd-table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th class="num">Amount</th>
                    <th>Payment Plan</th>
                    <th class="num">Installment</th>
                    <th>Est. Duration</th>
                    <th>Documents</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($requests as $req)
                    <tr>
                        <td>
                            <div class="lnd-emp-name">{{ $req->employee->first_name }} {{ $req->employee->last_name }}</div>
                            <div class="lnd-emp-id"><i class="bi bi-hash"></i>{{ $req->employee->employee_id }}</div>
                        </td>
                        <td class="num lnd-mono">K {{ number_format($req->amount, 2) }}</td>
                        <td>
                            {{ \App\Models\LoanRequest::PAYMENT_PLANS[$req->payment_plan] ?? $req->payment_plan }}
                            @if($req->payment_plan_note)
                                <div class="lnd-td-muted">{{ $req->payment_plan_note }}</div>
                            @endif
                        </td>
                        <td class="num lnd-mono">
                            @if($req->payment_plan === 'once_off')
                                <span class="lnd-td-muted">Full amount</span>
                            @elseif($req->installment_amount)
                                K {{ number_format($req->installment_amount, 2) }}
                            @else
                                <span class="lnd-td-muted">—</span>
                            @endif
                        </td>
                        <td class="lnd-td-muted">
                            @php $installments = $req->estimatedInstallments(); @endphp
                            @if($installments)
                                {{ $installments }} installment{{ $installments === 1 ? '' : 's' }}
                                <div class="lnd-td-faint">by {{ $req->estimatedPayoffDate()?->format('M Y') }}</div>
                            @else
                                <span class="lnd-td-faint">—</span>
                            @endif
                        </td>
                        <td>
                            @if(!empty($req->documents))
                                @foreach($req->documents as $doc)
                                    <a href="{{ asset('storage/' . $doc['path']) }}" target="_blank" class="lnd-doc-link">
                                        <i class="bi bi-file-earmark-text"></i> {{ Str::limit($doc['name'], 16) }}
                                    </a>
                                @endforeach
                            @else
                                <span class="lnd-td-muted">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="lnd-status lnd-status-{{ $req->status }}">{{ ucfirst($req->status) }}</span>
                            @if($req->status === 'approved')
                                @if($req->loan)
                                    <div class="lnd-ledger-flag lnd-ledger-in"><i class="bi bi-check2"></i> In Ledger</div>
                                @else
                                    <div class="lnd-ledger-flag lnd-ledger-out"><i class="bi bi-exclamation-circle"></i> Not Ledgered</div>
                                @endif
                            @endif
                        </td>
                        <td class="lnd-actions">
                            @if($req->status === 'pending')
                                <form method="POST" action="{{ route('loan.approve', $req) }}" class="lnd-decision-form">
                                    @csrf
                                    <input type="text" name="comment" class="lnd-comment" placeholder="Comment (optional)">
                                    <button class="lnd-btn-icon lnd-btn-approve" title="Approve"><i class="bi bi-check-lg"></i></button>
                                </form>
                                <form method="POST" action="{{ route('loan.reject', $req) }}" class="lnd-decision-form">
                                    @csrf
                                    <button class="lnd-btn-icon lnd-btn-reject" title="Reject" onclick="return confirm('Reject this loan request?');"><i class="bi bi-x-lg"></i></button>
                                </form>
                            @elseif($req->status === 'approved' && !$req->loan)
                                <a href="{{ route('loan.ledger.create') }}" class="lnd-btn-icon lnd-btn-ledger" title="Add to Ledger"><i class="bi bi-journal-plus"></i></a>
                            @elseif($req->loan)
                                <a href="{{ route('loan.ledger.show', $req->loan) }}" class="lnd-btn-icon lnd-btn-ledger" title="View in Ledger"><i class="bi bi-eye"></i></a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="lnd-empty">No loan requests here.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>

<style>
@import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap');
.lnd * { box-sizing: border-box; }
.lnd {
    --paper: #ffffff;
    --ink: #00742E;
    --ink-soft: #58687D;
    --ink-faint: #93A0B2;
    --brass: #00742E;
    --brass-soft: #EAF7ED;
    --line: #D9E9DE;
    --accent-orange: #E97B00;
    --accent-red: #D50115;
    --active: #00742E;
    --active-bg: #EAF7ED;
    --warn: #D50115;
    --warn-bg: #FDEBEC;
    font-family: 'IBM Plex Sans', sans-serif; color: var(--ink); background: var(--paper); padding: 1.5rem 0 2.5rem;
}
.lnd-header { display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: .75rem; margin-bottom: 1rem; }
.lnd-eyebrow { font-family: 'IBM Plex Sans', sans-serif; font-size: .68rem; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; color: var(--brass); margin-bottom: .2rem; }
.lnd-title { font-family: 'IBM Plex Sans', sans-serif; font-size: 1.4rem; font-weight: 700; margin: 0; }
.lnd-subtitle { font-size: .8rem; color: var(--ink-faint); margin-top: .2rem; }
.lnd-btn { display: inline-flex; align-items: center; gap: .4rem; font-size: .8rem; font-weight: 600; padding: .55rem 1rem; border-radius: 6px; text-decoration: none; border: 1px solid var(--line); background: #fff; color: var(--ink-soft); }
.lnd-btn-outline:hover { border-color: var(--brass); color: var(--brass); }
.lnd-alert { border-radius: 6px; font-size: .84rem; padding: .7rem 1rem; margin-bottom: 1rem; background: var(--active-bg); color: var(--active); border: 1px solid var(--line); }

.lnd-summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1rem; }
@media (max-width: 800px) { .lnd-summary { grid-template-columns: repeat(2, 1fr); } }
.lnd-summary-card { background: #fff; border: 1px solid var(--line); border-radius: 6px; padding: .9rem 1.1rem; border-top: 3px solid var(--brass); }
.lnd-summary-card.lnd-summary-warn { border-top-color: var(--accent-orange); }
.lnd-summary-label { font-size: .64rem; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--ink-faint); margin-bottom: .4rem; }
.lnd-summary-value { font-size: 1.3rem; font-weight: 700; color: var(--ink); }
.lnd-summary-sub { font-size: .72rem; color: var(--ink-faint); margin-top: .2rem; }

.lnd-tabs { display: flex; gap: .4rem; margin-bottom: 1rem; }
.lnd-tab { display: inline-flex; align-items: center; gap: .4rem; font-size: .8rem; font-weight: 600; padding: .5rem .9rem; border-radius: 6px; text-decoration: none; color: var(--ink-soft); border: 1px solid var(--line); background: #fff; }
.lnd-tab-active { background: var(--ink); color: #fff; border-color: var(--ink); }
.lnd-tab-count { font-size: .68rem; background: rgba(0,116,46,.08); padding: .05rem .4rem; border-radius: 10px; }
.lnd-tab-active .lnd-tab-count { background: rgba(255,255,255,.2); }

.lnd-card { background: #fff; border: 1px solid var(--line); border-radius: 6px; overflow-x: auto; }
.lnd-table { width: 100%; border-collapse: collapse; font-size: .82rem; min-width: 1080px; }
.lnd-table thead th { font-size: .64rem; font-weight: 600; color: var(--ink-faint); text-transform: uppercase; letter-spacing: .06em; padding: .8rem 1rem; border-bottom: 2px solid var(--ink); text-align: left; background: #F9FCFA; }
.lnd-table thead th.num, .lnd-table td.num { text-align: right; }
.lnd-table td { padding: .6rem 1rem; border-bottom: 1px solid var(--line); vertical-align: middle; }
.lnd-mono { font-family: 'IBM Plex Sans', sans-serif; }
.lnd-emp-name { font-weight: 600; font-size: .82rem; }
.lnd-emp-id { font-size: .66rem; color: var(--ink-faint); }
.lnd-td-muted { color: var(--ink-faint); font-size: .72rem; }
.lnd-td-faint { color: var(--ink-faint); font-size: .68rem; margin-top: .1rem; }
.lnd-empty { text-align: center; color: var(--ink-faint); padding: 1.5rem 0 !important; }
.lnd-doc-link { display: block; font-size: .74rem; color: var(--ink-soft); text-decoration: none; margin-bottom: .2rem; }
.lnd-doc-link:hover { color: var(--brass); }

.lnd-status { display: inline-block; font-size: .68rem; font-weight: 700; padding: .2rem .6rem; border-radius: 4px; text-transform: uppercase; }
.lnd-status-pending { background: #FFF2E7; color: var(--accent-orange); }
.lnd-status-approved { background: var(--active-bg); color: var(--active); }
.lnd-status-rejected { background: var(--warn-bg); color: var(--warn); }

.lnd-ledger-flag { display: flex; align-items: center; gap: .3rem; font-size: .66rem; font-weight: 600; margin-top: .3rem; }
.lnd-ledger-in { color: var(--active); }
.lnd-ledger-out { color: var(--accent-orange); }

.lnd-actions { display: flex; gap: .4rem; align-items: center; }
.lnd-decision-form { display: flex; align-items: center; gap: .3rem; }
.lnd-comment { font-size: .74rem; padding: .3rem .5rem; border: 1px solid var(--line); border-radius: 4px; width: 110px; }
.lnd-btn-icon { width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center; border-radius: 5px; border: 1px solid var(--line); background: #fff; cursor: pointer; text-decoration: none; color: var(--ink-soft); }
.lnd-btn-approve { color: var(--active); }
.lnd-btn-approve:hover { background: var(--active-bg); border-color: var(--line); }
.lnd-btn-reject { color: var(--warn); }
.lnd-btn-reject:hover { background: var(--warn-bg); border-color: #F2C7CC; }
.lnd-btn-ledger:hover { background: var(--brass-soft); color: var(--ink); border-color: var(--brass); }
</style>

@endsection