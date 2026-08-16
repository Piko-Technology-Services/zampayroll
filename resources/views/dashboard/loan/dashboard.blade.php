@extends('layouts.app')

@section('content')

<div class="lnd p-5">

    <div class="lnd-header">
        <div>
            <div class="lnd-eyebrow">Loan Management</div>
            <h1 class="lnd-title">Loan Requests</h1>
        </div>
    </div>

    @if(session('success'))
        <div class="lnd-alert"><i class="bi bi-check-circle"></i> {{ session('success') }}</div>
    @endif

    <div class="lnd-tabs">
        @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $key => $label)
            <a href="{{ route('loan.dashboard', ['status' => $key]) }}" class="lnd-tab {{ $status === $key ? 'lnd-tab-active' : '' }}">
                {{ $label }} @if(isset($counts[$key]))<span class="lnd-tab-count">{{ $counts[$key] }}</span>@endif
            </a>
        @endforeach
    </div>

    <div class="lnd-card">
        <table class="lnd-table">
            <thead>
                <tr>
                    <th>Employee</th><th class="num">Amount</th><th>Payment Plan</th>
                    <th>Documents</th><th>Status</th><th></th>
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
                        <td><span class="lnd-status lnd-status-{{ $req->status }}">{{ ucfirst($req->status) }}</span></td>
                        <td class="lnd-actions">
                            @if($req->status === 'pending')
                                <form method="POST" action="{{ route('loan.approve', $req) }}" class="lnd-decision-form">
                                    @csrf
                                    <input type="text" name="comment" class="lnd-comment" placeholder="Comment (optional)">
                                    <button class="lnd-btn lnd-btn-approve" title="Approve"><i class="bi bi-check-lg"></i></button>
                                </form>
                                <form method="POST" action="{{ route('loan.reject', $req) }}" class="lnd-decision-form">
                                    @csrf
                                    <button class="lnd-btn lnd-btn-reject" title="Reject" onclick="return confirm('Reject this loan request?');"><i class="bi bi-x-lg"></i></button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="lnd-empty">No loan requests here.</td></tr>
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
.lnd-header { margin-bottom: 1rem; }
.lnd-eyebrow { font-family: 'IBM Plex Sans', sans-serif; font-size: .68rem; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; color: var(--brass); margin-bottom: .2rem; }
.lnd-title { font-family: 'IBM Plex Sans', sans-serif; font-size: 1.4rem; font-weight: 700; margin: 0; }
.lnd-alert { border-radius: 6px; font-size: .84rem; padding: .7rem 1rem; margin-bottom: 1rem; background: var(--active-bg); color: var(--active); border: 1px solid var(--line); }

.lnd-tabs { display: flex; gap: .4rem; margin-bottom: 1rem; }
.lnd-tab { display: inline-flex; align-items: center; gap: .4rem; font-size: .8rem; font-weight: 600; padding: .5rem .9rem; border-radius: 6px; text-decoration: none; color: var(--ink-soft); border: 1px solid var(--line); background: #fff; }
.lnd-tab-active { background: var(--ink); color: #fff; border-color: var(--ink); }
.lnd-tab-count { font-size: .68rem; background: rgba(0,116,46,.08); padding: .05rem .4rem; border-radius: 10px; }
.lnd-tab-active .lnd-tab-count { background: rgba(255,255,255,.2); }

.lnd-card { background: #fff; border: 1px solid var(--line); border-radius: 6px; overflow-x: auto; }
.lnd-table { width: 100%; border-collapse: collapse; font-size: .82rem; min-width: 900px; }
.lnd-table thead th { font-family: 'IBM Plex Sans', sans-serif; font-size: .64rem; font-weight: 600; color: var(--ink-faint); text-transform: uppercase; letter-spacing: .06em; padding: .8rem 1rem; border-bottom: 2px solid var(--ink); text-align: left; background: #F9FCFA; }
.lnd-table thead th.num, .lnd-table td.num { text-align: right; }
.lnd-table td { padding: .6rem 1rem; border-bottom: 1px solid var(--line); vertical-align: middle; }
.lnd-mono { font-family: 'IBM Plex Sans', sans-serif; }
.lnd-emp-name { font-weight: 600; font-size: .82rem; }
.lnd-emp-id { font-size: .66rem; color: var(--ink-faint); }
.lnd-td-muted { color: var(--ink-faint); font-size: .72rem; }
.lnd-empty { text-align: center; color: var(--ink-faint); padding: 1.5rem 0 !important; }
.lnd-doc-link { display: block; font-size: .74rem; color: var(--ink-soft); text-decoration: none; margin-bottom: .2rem; }
.lnd-doc-link:hover { color: var(--brass); }

.lnd-status { display: inline-block; font-size: .68rem; font-weight: 700; padding: .2rem .6rem; border-radius: 4px; text-transform: uppercase; }
.lnd-status-pending { background: #FFF2E7; color: var(--accent-orange); }
.lnd-status-approved { background: var(--active-bg); color: var(--active); }
.lnd-status-rejected { background: var(--warn-bg); color: var(--warn); }

.lnd-actions { display: flex; gap: .4rem; align-items: center; }
.lnd-decision-form { display: flex; align-items: center; gap: .3rem; }
.lnd-comment { font-size: .74rem; padding: .3rem .5rem; border: 1px solid var(--line); border-radius: 4px; width: 110px; }
.lnd-btn { width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center; border-radius: 5px; border: 1px solid var(--line); background: #fff; cursor: pointer; }
.lnd-btn-approve { color: var(--active); }
.lnd-btn-approve:hover { background: var(--active-bg); border-color: var(--line); }
.lnd-btn-reject { color: var(--warn); }
.lnd-btn-reject:hover { background: var(--warn-bg); border-color: #F2C7CC; }
</style>

@endsection
