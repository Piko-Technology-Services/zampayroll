@extends('layouts.app')

@section('content')

<div class="llc">

    <div class="llc-header">
        <a href="{{ route('loan.ledger.index') }}" class="llc-back"><i class="bi bi-arrow-left"></i> Loan Ledger</a>
        <div class="llc-eyebrow">Loan Management</div>
        <h1 class="llc-title">Add a Loan</h1>
        <div class="llc-subtitle">For a new loan, or to record an existing one already in progress</div>
    </div>

    @if($errors->any())
        <div class="llc-alert-error">
            <strong>Please fix the following:</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('loan.ledger.store') }}">
        @csrf

        <div class="llc-card">
            <div class="llc-section-label">Loan Details</div>

            <div class="llc-grid llc-grid-2">
                <div class="llc-field">
                    <label class="llc-label">Employee</label>
                    <select name="employee_id" class="llc-input" required>
                        <option value="">Select employee…</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}" {{ old('employee_id') == $employee->id ? 'selected' : '' }}>
                                {{ $employee->first_name }} {{ $employee->last_name }} — {{ $employee->employee_id }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="llc-field">
                    <label class="llc-label">Start Date</label>
                    <input type="date" name="start_date" class="llc-input" value="{{ old('start_date', now()->toDateString()) }}" required>
                </div>

                <div class="llc-field">
                    <label class="llc-label">Principal Amount</label>
                    <input type="number" step="0.01" min="0.01" name="principal_amount" class="llc-input" value="{{ old('principal_amount') }}" required>
                </div>
                <div class="llc-field">
                    <label class="llc-label">Current Balance</label>
                    <input type="number" step="0.01" min="0" name="balance" class="llc-input" value="{{ old('balance') }}" placeholder="Defaults to principal amount">
                    <small class="llc-hint">Only set this if recording an existing loan already partially repaid.</small>
                </div>

                <div class="llc-field">
                    <label class="llc-label">Payment Plan</label>
                    <select name="payment_plan" id="planSelect" class="llc-input" required>
                        <option value="">Select repayment plan…</option>
                        @foreach(\App\Models\LoanRequest::PAYMENT_PLANS as $key => $label)
                            <option value="{{ $key }}" {{ old('payment_plan') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="llc-field" id="installmentField">
                    <label class="llc-label">Installment Amount</label>
                    <input type="number" step="0.01" min="0" name="installment_amount" class="llc-input" value="{{ old('installment_amount') }}">
                    <small class="llc-hint">Required for Monthly / Every Two Months / Other. Ignored for Once-off (the full balance is deducted in one payroll run).</small>
                </div>

                <div class="llc-field" id="planNoteField">
                    <label class="llc-label">Plan Detail</label>
                    <input type="text" name="payment_plan_note" class="llc-input" value="{{ old('payment_plan_note') }}" placeholder="e.g. K500 every 3 months">
                </div>
                <div class="llc-field">
                    <label class="llc-label">Notes</label>
                    <input type="text" name="notes" class="llc-input" value="{{ old('notes') }}" placeholder="Internal note (optional)">
                </div>
            </div>
        </div>

        <div class="llc-actions">
            <a href="{{ route('loan.ledger.index') }}" class="llc-btn llc-btn-outline">Cancel</a>
            <button class="llc-btn llc-btn-primary"><i class="bi bi-save"></i> Save Loan</button>
        </div>
    </form>

</div>

<style>
@import url('https://fonts.googleapis.com/css2?family=Roboto+Slab:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap');
.llc * { box-sizing: border-box; }
.llc {
    --paper: #FAF8F3; --ink: #17263A; --ink-soft: #58687D; --ink-faint: #93A0B2;
    --brass: #9C7A32; --brass-soft: #E9DFC7; --line: #E1DACB; --warn: #9B3A2A; --warn-bg: #F7E7E2;
    font-family: 'IBM Plex Sans', sans-serif; color: var(--ink); background: var(--paper); padding: 1.5rem 0 2.5rem;
    max-width: 760px; margin: 0 auto;
}
.llc-back { display: inline-flex; align-items: center; gap: .35rem; font-size: .78rem; font-weight: 600; color: var(--ink-soft); text-decoration: none; margin-bottom: .6rem; }
.llc-back:hover { color: var(--brass); }
.llc-eyebrow { font-family: 'Roboto Slab', serif; font-size: .68rem; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; color: var(--brass); margin-bottom: .2rem; }
.llc-title { font-family: 'Roboto Slab', serif; font-size: 1.3rem; font-weight: 700; margin: 0; }
.llc-subtitle { font-size: .8rem; color: var(--ink-faint); margin: .2rem 0 1.2rem; }

.llc-alert-error { background: var(--warn-bg); color: var(--warn); border: 1px solid #E3C6BC; border-radius: 6px; padding: .8rem 1rem; font-size: .82rem; margin-bottom: 1rem; }
.llc-alert-error ul { margin: .3rem 0 0; padding-left: 1.1rem; }

.llc-card { background: #fff; border: 1px solid var(--line); border-radius: 6px; padding: 1.4rem; margin-bottom: 1rem; position: relative; }
.llc-card::before { content: ""; position: absolute; top: 0; left: 26px; width: 56px; height: 5px; background: var(--brass); border-radius: 0 0 3px 3px; }
.llc-section-label { font-family: 'Roboto Slab', serif; font-size: .8rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--ink); border-bottom: 1px solid var(--line); padding-bottom: .7rem; margin: .3rem 0 1.1rem; }

.llc-grid { display: grid; gap: 1rem; }
.llc-grid-2 { grid-template-columns: 1fr 1fr; }
@media (max-width: 620px) { .llc-grid-2 { grid-template-columns: 1fr; } }
.llc-field { display: flex; flex-direction: column; gap: .35rem; }
.llc-label { font-size: .68rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--ink-faint); }
.llc-hint { font-size: .7rem; color: var(--ink-faint); line-height: 1.4; }
.llc-input { font-size: .84rem; padding: .6rem .8rem; border: 1px solid var(--line); border-radius: 6px; background: #fff; color: var(--ink); outline: none; width: 100%; }
.llc-input:focus { border-color: var(--brass); box-shadow: 0 0 0 3px var(--brass-soft); }

.llc-actions { display: flex; justify-content: flex-end; gap: .6rem; }
.llc-btn { display: inline-flex; align-items: center; gap: .4rem; font-size: .8rem; font-weight: 600; padding: .55rem 1.1rem; border-radius: 6px; border: 1px solid transparent; cursor: pointer; text-decoration: none; }
.llc-btn-primary { background: var(--ink); color: #fff; border-color: var(--ink); }
.llc-btn-primary:hover { background: #23374F; }
.llc-btn-outline { background: #fff; color: var(--ink-soft); border-color: var(--line); }
.llc-btn-outline:hover { border-color: var(--brass); color: var(--brass); }
</style>

<script>
(function () {
    const select = document.getElementById('planSelect');
    const installmentField = document.getElementById('installmentField');
    const noteField = document.getElementById('planNoteField');

    function toggle() {
        const isOnceOff = select.value === 'once_off';
        installmentField.style.opacity = isOnceOff ? '.45' : '1';
        installmentField.querySelector('input').disabled = isOnceOff;
        noteField.style.display = select.value === 'other' ? 'flex' : 'flex';
    }
    select.addEventListener('change', toggle);
    toggle();
})();
</script>

@endsection
