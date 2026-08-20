@extends('layouts.app')

@section('content')

<div class="lls p-5">

    <div class="lls-header">
        <a href="{{ route('loan.ledger.index') }}" class="lls-back"><i class="bi bi-arrow-left"></i> Loan Ledger</a>
        <div class="lls-eyebrow">Loan Management</div>
        <h1 class="lls-title">{{ $loan->employee->first_name }} {{ $loan->employee->last_name }}</h1>
        <span class="lls-status lls-status-{{ $loan->status }}">{{ \App\Models\Loan::STATUSES[$loan->status] ?? $loan->status }}</span>
    </div>

    @if(session('success'))
        <div class="lls-alert"><i class="bi bi-check-circle"></i> {{ session('success') }}</div>
    @endif

    <div class="lls-layout">

        {{-- ===== LEFT: TERMS + PAYMENT FORM ===== --}}
        <div class="lls-left">

            <div class="lls-card">
                <div class="lls-section-label">Loan Terms</div>

                <form method="POST" action="{{ route('loan.ledger.update', $loan) }}">
                    @csrf
                    @method('PUT')

                    <div class="lls-grid">
                        <div class="lls-field">
                            <label class="lls-label">Payment Plan</label>
                            <select name="payment_plan" class="lls-input">
                                @foreach(\App\Models\LoanRequest::PAYMENT_PLANS as $key => $label)
                                    <option value="{{ $key }}" {{ $loan->payment_plan === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="lls-field">
                            <label class="lls-label">Installment Amount</label>
                            <input type="number" step="0.01" min="0" name="installment_amount" class="lls-input"
                                   value="{{ $loan->installment_amount }}" placeholder="Required unless Once-off">
                        </div>
                        <div class="lls-field">
                            <label class="lls-label">Plan Detail</label>
                            <input type="text" name="payment_plan_note" class="lls-input" value="{{ $loan->payment_plan_note }}">
                        </div>
                        <div class="lls-field">
                            <label class="lls-label">Next Deduction Date</label>
                            <input type="date" name="next_deduction_date" class="lls-input"
                                   value="{{ $loan->next_deduction_date?->format('Y-m-d') }}">
                        </div>
                        <div class="lls-field">
                            <label class="lls-label">Status</label>
                            <select name="status" class="lls-input">
                                @foreach(\App\Models\Loan::STATUSES as $key => $label)
                                    <option value="{{ $key }}" {{ $loan->status === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="lls-actions">
                        <button class="lls-btn lls-btn-outline"><i class="bi bi-save"></i> Update Terms</button>
                    </div>
                </form>
            </div>

            <div class="lls-card">
                <div class="lls-section-label">Record a Payment / Adjustment</div>

                <form method="POST" action="{{ route('loan.ledger.payments.store', $loan) }}">
                    @csrf

                    <div class="lls-grid">
                        <div class="lls-field">
                            <label class="lls-label">Type</label>
                            <select name="type" class="lls-input" required>
                                <option value="manual_payment">Manual Payment</option>
                                <option value="adjustment">Balance Adjustment</option>
                                <option value="write_off">Write Off Remaining Balance</option>
                            </select>
                        </div>
                        <div class="lls-field">
                            <label class="lls-label">Amount</label>
                            <input type="number" step="0.01" min="0.01" name="amount" class="lls-input" placeholder="Ignored for write-off">
                        </div>
                        <div class="lls-field" style="grid-column: span 2;">
                            <label class="lls-label">Note</label>
                            <input type="text" name="note" class="lls-input" placeholder="Optional">
                        </div>
                    </div>

                    <div class="lls-actions">
                        <button class="lls-btn lls-btn-primary"><i class="bi bi-plus-lg"></i> Record</button>
                    </div>
                </form>
            </div>

        </div>

        {{-- ===== RIGHT: BALANCE + HISTORY ===== --}}
        <div class="lls-right">

            <div class="lls-balance-card">
                <div class="lls-balance-label">Outstanding Balance</div>
                <div class="lls-balance-value">K {{ number_format($loan->balance, 2) }}</div>
                <div class="lls-balance-sub">of K {{ number_format($loan->principal_amount, 2) }} principal</div>

                <div class="lls-balance-bar">
                    @php $pct = $loan->principal_amount > 0 ? min(100, round((1 - $loan->balance / $loan->principal_amount) * 100)) : 0; @endphp
                    <div class="lls-balance-fill" style="width: {{ $pct }}%"></div>
                </div>
                <div class="lls-balance-pct">{{ $pct }}% repaid</div>
            </div>

            <div class="lls-card">
                <div class="lls-section-label">Payment History</div>

                <table class="lls-table">
                    <thead>
                        <tr><th>Date</th><th>Type</th><th class="num">Amount</th><th class="num">Balance After</th><th>Note</th></tr>
                    </thead>
                    <tbody>
                        @forelse($loan->payments as $payment)
                            <tr>
                                <td class="lls-mono">{{ $payment->created_at->format('d M Y') }}</td>
                                <td>
                                    <span class="lls-type lls-type-{{ $payment->type }}">
                                        {{ \App\Models\LoanPayment::TYPES[$payment->type] ?? $payment->type }}
                                    </span>
                                    @if($payment->payroll)
                                        <div class="lls-td-muted">{{ $payment->payroll->payrollRun->period ?? '' }}</div>
                                    @endif
                                </td>
                                <td class="num lls-mono">K {{ number_format($payment->amount, 2) }}</td>
                                <td class="num lls-mono">K {{ number_format($payment->balance_after, 2) }}</td>
                                <td class="lls-td-muted">{{ $payment->note ?? ($payment->recordedBy->name ?? '—') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="lls-empty">No payments recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>

    </div>

</div>

<style>
@import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap');
.lls * { box-sizing: border-box; }
.lls {
    --paper: #ffffff; --ink: #00742E; --ink-soft: #58687D; --ink-faint: #93A0B2;
    --brass: #00742E; --brass-soft: #EAF7ED; --line: #D9E9DE;
    --accent-orange: #E97B00; --accent-red: #D50115;
    --active: #00742E; --active-bg: #EAF7ED; --warn: #D50115; --warn-bg: #FDE7EA; --sick: #E97B00; --sick-bg: #FFF1E1;
    font-family: 'IBM Plex Sans', sans-serif; color: var(--ink); background: var(--paper); padding: 1.5rem 0 2.5rem;
}
.lls-back { display: inline-flex; align-items: center; gap: .35rem; font-size: .78rem; font-weight: 600; color: var(--ink-soft); text-decoration: none; margin-bottom: .6rem; }
.lls-back:hover { color: var(--brass); }
.lls-eyebrow { font-family: 'IBM Plex Sans', sans-serif; font-size: .68rem; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; color: var(--brass); margin-bottom: .2rem; }
.lls-title { font-family: 'IBM Plex Sans', sans-serif; font-size: 1.4rem; font-weight: 700; margin: 0 0 .4rem; display: inline-block; margin-right: .6rem; }
.lls-header { margin-bottom: 1rem; }

.lls-status { display: inline-block; font-size: .68rem; font-weight: 700; padding: .22rem .65rem; border-radius: 4px; text-transform: uppercase; vertical-align: middle; }
.lls-status-active { background: var(--active-bg); color: var(--active); }
.lls-status-completed { background: #EAEEF4; color: #35507A; }
.lls-status-paused { background: var(--sick-bg); color: var(--sick); }
.lls-status-written_off { background: var(--warn-bg); color: var(--warn); }

.lls-alert { border-radius: 6px; font-size: .84rem; padding: .7rem 1rem; margin-bottom: 1rem; background: var(--active-bg); color: var(--active); border: 1px solid var(--line); }

.lls-layout { display: grid; grid-template-columns: 1fr 1.3fr; gap: 1rem; align-items: start; }
@media (max-width: 992px) { .lls-layout { grid-template-columns: 1fr; } }

.lls-card { background: #fff; border: 1px solid var(--line); border-radius: 6px; padding: 1.3rem; margin-bottom: 1rem; position: relative; }
.lls-card::before { content: ""; position: absolute; top: 0; left: 24px; width: 52px; height: 5px; background: var(--brass); border-radius: 0 0 3px 3px; }
.lls-section-label { font-family: 'IBM Plex Sans', sans-serif; font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--ink); border-bottom: 1px solid var(--line); padding-bottom: .6rem; margin: .3rem 0 1rem; }

.lls-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .9rem; }
.lls-field { display: flex; flex-direction: column; gap: .3rem; }
.lls-label { font-size: .66rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--ink-faint); }
.lls-input { font-size: .8rem; padding: .5rem .7rem; border: 1px solid var(--line); border-radius: 5px; background: #fff; color: var(--ink); outline: none; width: 100%; }
.lls-input:focus { border-color: var(--brass); }
.lls-actions { display: flex; justify-content: flex-end; margin-top: .9rem; }

.lls-btn { display: inline-flex; align-items: center; gap: .4rem; font-size: .78rem; font-weight: 600; padding: .5rem .95rem; border-radius: 6px; border: 1px solid transparent; cursor: pointer; }
.lls-btn-primary { background: var(--ink); color: #fff; border-color: var(--ink); }
.lls-btn-primary:hover { background: #005A24; }
.lls-btn-outline { background: #fff; color: var(--ink-soft); border-color: var(--line); }
.lls-btn-outline:hover { border-color: var(--brass); color: var(--brass); }

.lls-balance-card { background: #fff; border: 2px solid var(--ink); border-radius: 6px; padding: 1.3rem; margin-bottom: 1rem; text-align: center; }
.lls-balance-label { font-family: 'IBM Plex Sans', sans-serif; font-size: .68rem; font-weight: 600; text-transform: uppercase; letter-spacing: .08em; color: var(--ink-faint); }
.lls-balance-value { font-family: 'IBM Plex Mono', monospace; font-size: 1.8rem; font-weight: 700; color: var(--ink); margin: .3rem 0; }
.lls-balance-sub { font-size: .74rem; color: var(--ink-faint); margin-bottom: .8rem; }
.lls-balance-bar { height: 6px; background: var(--brass-soft); border-radius: 4px; overflow: hidden; }
.lls-balance-fill { height: 100%; background: var(--active); }
.lls-balance-pct { font-size: .7rem; color: var(--ink-faint); margin-top: .4rem; }

.lls-table { width: 100%; border-collapse: collapse; font-size: .8rem; }
.lls-table thead th { font-family: 'IBM Plex Sans', sans-serif; font-size: .62rem; font-weight: 600; color: var(--ink-faint); text-transform: uppercase; letter-spacing: .05em; padding: .6rem .5rem; border-bottom: 2px solid var(--ink); text-align: left; }
.lls-table thead th.num, .lls-table td.num { text-align: right; }
.lls-table td { padding: .55rem .5rem; border-bottom: 1px solid var(--line); vertical-align: top; }
.lls-mono { font-family: 'IBM Plex Mono', monospace; }
.lls-td-muted { color: var(--ink-faint); font-size: .72rem; margin-top: .15rem; }
.lls-empty { text-align: center; color: var(--ink-faint); padding: 1.2rem 0 !important; }

.lls-type { font-size: .66rem; font-weight: 700; padding: .12rem .5rem; border-radius: 4px; }
.lls-type-deduction { background: var(--active-bg); color: var(--active); }
.lls-type-manual_payment { background: #EAEEF4; color: #35507A; }
.lls-type-adjustment { background: var(--sick-bg); color: var(--sick); }
.lls-type-write_off { background: var(--warn-bg); color: var(--warn); }
</style>

@endsection
