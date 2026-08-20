@extends('layouts.app')

@section('content')

<div class="llg p-5">

    <div class="llg-header">
        <div>
            <div class="llg-eyebrow">Loan Management</div>
            <h1 class="llg-title">Loan Ledger</h1>
            <div class="llg-subtitle">Every loan, its running balance, and repayment schedule</div>
        </div>
        <div class="llg-header-actions">
            <a href="{{ route('loan.dashboard') }}" class="llg-btn llg-btn-outline">
                <i class="bi bi-inbox"></i> Applications
            </a>
            <a href="{{ route('loan.ledger.create') }}" class="llg-btn llg-btn-primary">
                <i class="bi bi-plus-lg"></i> New Loan
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="llg-alert"><i class="bi bi-check-circle"></i> {{ session('success') }}</div>
    @endif

    {{-- ===== SUMMARY ===== --}}
    <div class="llg-summary">
        <div class="llg-summary-card">
            <div class="llg-summary-label">Outstanding Balance</div>
            <div class="llg-summary-value">K {{ number_format($totals['outstanding'], 2) }}</div>
        </div>
        <div class="llg-summary-card">
            <div class="llg-summary-label">Total Ever Disbursed</div>
            <div class="llg-summary-value">K {{ number_format($totals['disbursed'], 2) }}</div>
        </div>
        <div class="llg-summary-card">
            <div class="llg-summary-label">Active Loans</div>
            <div class="llg-summary-value">{{ $counts['active'] }}</div>
        </div>
        <div class="llg-summary-card">
            <div class="llg-summary-label">Completed</div>
            <div class="llg-summary-value">{{ $counts['completed'] }}</div>
        </div>
    </div>

    {{-- ===== TABS + FILTER ===== --}}
    <div class="llg-toolbar">
        <div class="llg-tabs">
            @foreach(['active' => 'Active', 'completed' => 'Completed', 'paused' => 'Paused', 'written_off' => 'Written Off', 'all' => 'All'] as $key => $label)
                <a href="{{ route('loan.ledger.index', ['status' => $key, 'department' => request('department')]) }}"
                   class="llg-tab {{ $status === $key ? 'llg-tab-active' : '' }}">
                    {{ $label }}
                    @if(isset($counts[$key]))<span class="llg-tab-count">{{ $counts[$key] }}</span>@endif
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('loan.ledger.index') }}">
            <input type="hidden" name="status" value="{{ $status }}">
            <select name="department" class="llg-select" onchange="this.form.submit()">
                <option value="">All Departments</option>
                @foreach($departments as $dept)
                    <option value="{{ $dept }}" {{ request('department') === $dept ? 'selected' : '' }}>{{ $dept }}</option>
                @endforeach
            </select>
        </form>
    </div>

    {{-- ===== TABLE ===== --}}
    <div class="llg-card">
        <table class="llg-table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th class="num">Principal</th>
                    <th class="num">Balance</th>
                    <th>Plan</th>
                    <th class="num">Installment</th>
                    <th>Next Deduction</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($loans as $loan)
                    <tr>
                        <td>
                            <div class="llg-emp-name">{{ $loan->employee->first_name }} {{ $loan->employee->last_name }}</div>
                            <div class="llg-emp-id"><i class="bi bi-hash"></i>{{ $loan->employee->employee_id }}</div>
                        </td>
                        <td class="num llg-mono">K {{ number_format($loan->principal_amount, 2) }}</td>
                        <td class="num llg-mono llg-balance">K {{ number_format($loan->balance, 2) }}</td>
                        <td>{{ \App\Models\LoanRequest::PAYMENT_PLANS[$loan->payment_plan] ?? $loan->payment_plan }}</td>
                        <td class="num llg-mono">
                            @if($loan->payment_plan === 'once_off')
                                <span class="llg-td-muted">Full balance</span>
                            @elseif($loan->installment_amount)
                                K {{ number_format($loan->installment_amount, 2) }}
                            @else
                                <span class="llg-warn-chip"><i class="bi bi-exclamation-triangle"></i> Not set</span>
                            @endif
                        </td>
                        <td class="llg-mono llg-td-muted">
                            {{ $loan->status === 'active' && $loan->next_deduction_date ? $loan->next_deduction_date->format('d M Y') : '—' }}
                        </td>
                        <td><span class="llg-status llg-status-{{ $loan->status }}">{{ \App\Models\Loan::STATUSES[$loan->status] ?? $loan->status }}</span></td>
                        <td class="text-end">
                            <a href="{{ route('loan.ledger.show', $loan) }}" class="llg-action-btn"><i class="bi bi-eye"></i></a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="llg-empty">No loans found for this view.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>

<style>
@import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap');
.llg * { box-sizing: border-box; }
.llg {
    --paper: #ffffff; --ink: #00742E; --ink-soft: #58687D; --ink-faint: #93A0B2;
    --brass: #00742E; --brass-soft: #EAF7ED; --line: #D9E9DE;
    --accent-orange: #E97B00; --accent-red: #D50115;
    --active: #00742E; --active-bg: #EAF7ED; --warn: #D50115; --warn-bg: #FDE8EA;
    --sick: #E97B00; --sick-bg: #FFF1E0;
    font-family: 'IBM Plex Sans', sans-serif; color: var(--ink); background: var(--paper); padding: 1.5rem 0 2.5rem;
}
.llg-header { display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: .75rem; margin-bottom: 1rem; }
.llg-eyebrow { font-family: 'IBM Plex Sans', sans-serif; font-size: .68rem; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; color: var(--brass); margin-bottom: .2rem; }
.llg-title { font-family: 'IBM Plex Sans', sans-serif; font-size: 1.4rem; font-weight: 700; margin: 0; }
.llg-subtitle { font-size: .82rem; color: var(--ink-faint); margin-top: .2rem; }
.llg-header-actions { display: flex; gap: .5rem; }

.llg-btn { display: inline-flex; align-items: center; gap: .4rem; font-size: .8rem; font-weight: 600; padding: .55rem 1rem; border-radius: 6px; text-decoration: none; border: 1px solid transparent; cursor: pointer; }
.llg-btn-primary { background: var(--ink); color: #fff; border-color: var(--ink); }
.llg-btn-primary:hover { background: #23374F; color: #fff; }
.llg-btn-outline { background: #fff; color: var(--ink-soft); border-color: var(--line); }
.llg-btn-outline:hover { border-color: var(--brass); color: var(--brass); }

.llg-alert { border-radius: 6px; font-size: .84rem; padding: .7rem 1rem; margin-bottom: 1rem; background: var(--active-bg); color: var(--active); border: 1px solid #C7E0CD; }

.llg-summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1rem; }
@media (max-width: 800px) { .llg-summary { grid-template-columns: repeat(2, 1fr); } }
.llg-summary-card { background: #fff; border: 1px solid var(--line); border-radius: 6px; padding: .9rem 1.1rem; border-top: 3px solid var(--brass); }
.llg-summary-label { font-family: 'IBM Plex Sans', sans-serif; font-size: .64rem; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--ink-faint); margin-bottom: .4rem; }
.llg-summary-value { font-family: 'IBM Plex Sans', sans-serif; font-size: 1.15rem; font-weight: 600; color: var(--ink); }

.llg-toolbar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: .75rem; margin-bottom: 1rem; }
.llg-tabs { display: flex; gap: .4rem; flex-wrap: wrap; }
.llg-tab { display: inline-flex; align-items: center; gap: .4rem; font-size: .78rem; font-weight: 600; padding: .5rem .85rem; border-radius: 6px; text-decoration: none; color: var(--ink-soft); border: 1px solid var(--line); background: #fff; }
.llg-tab-active { background: var(--ink); color: #fff; border-color: var(--ink); }
.llg-tab-count { font-family: 'IBM Plex Sans', sans-serif; font-size: .66rem; background: rgba(0,0,0,.08); padding: .05rem .4rem; border-radius: 10px; }
.llg-tab-active .llg-tab-count { background: rgba(255,255,255,.2); }
.llg-select { font-size: .8rem; padding: .5rem .7rem; border: 1px solid var(--line); border-radius: 5px; background: #fff; color: var(--ink-soft); }

.llg-card { background: #fff; border: 1px solid var(--line); border-radius: 6px; overflow-x: auto; }
.llg-table { width: 100%; border-collapse: collapse; font-size: .82rem; min-width: 960px; }
.llg-table thead th { font-family: 'IBM Plex Sans', sans-serif; font-size: .64rem; font-weight: 600; color: var(--ink-faint); text-transform: uppercase; letter-spacing: .06em; padding: .8rem 1rem; border-bottom: 2px solid var(--ink); text-align: left; background: #ffffff; }
.llg-table thead th.num, .llg-table td.num { text-align: right; }
.llg-table td { padding: .6rem 1rem; border-bottom: 1px solid var(--line); vertical-align: middle; }
.llg-mono { font-family: 'IBM Plex Sans', sans-serif; }
.llg-balance { font-weight: 700; }
.llg-emp-name { font-weight: 600; font-size: .82rem; }
.llg-emp-id { font-family: 'IBM Plex Sans', sans-serif; font-size: .66rem; color: var(--ink-faint); }
.llg-td-muted { color: var(--ink-faint); }
.llg-empty { text-align: center; color: var(--ink-faint); padding: 1.5rem 0 !important; }
.llg-warn-chip { display: inline-flex; align-items: center; gap: .3rem; font-size: .68rem; font-weight: 700; color: var(--sick); background: var(--sick-bg); padding: .15rem .5rem; border-radius: 4px; }

.llg-status { display: inline-block; font-size: .68rem; font-weight: 700; padding: .2rem .6rem; border-radius: 4px; text-transform: uppercase; }
.llg-status-active { background: var(--active-bg); color: var(--active); }
.llg-status-completed { background: var(--brass-soft); color: var(--ink); }
.llg-status-paused { background: var(--sick-bg); color: var(--sick); }
.llg-status-written_off { background: var(--warn-bg); color: var(--warn); }

.llg-action-btn { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; border: 1px solid var(--line); border-radius: 5px; color: var(--ink-soft); text-decoration: none; }
.llg-action-btn:hover { background: var(--brass-soft); color: var(--ink); border-color: var(--brass); }
</style>

@endsection
