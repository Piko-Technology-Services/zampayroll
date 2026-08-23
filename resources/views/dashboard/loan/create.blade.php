@extends('layouts.app')

@section('content')

<div class="llc p-5">

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

        {{-- ================= PREFILL FROM APPROVED APPLICATION ================= --}}
        <div class="llc-card llc-prefill-card">
            <div class="llc-section-label">Prefill From an Approved Application <span class="llc-optional">(optional)</span></div>

            @if($approvedApplications->isEmpty())
                <div class="llc-empty-note">
                    <i class="bi bi-inbox"></i> No approved applications are waiting to be added to the ledger.
                </div>
            @else
                <div class="llc-field">
                    <label class="llc-label">Approved Application</label>
                    <select id="prefillSelect" class="llc-input">
                        <option value="">— Start blank —</option>
                        @foreach($approvedApplications as $app)
                            <option value="{{ $app->id }}"
                                    data-employee-id="{{ $app->employee_id }}"
                                    data-amount="{{ $app->amount }}"
                                    data-plan="{{ $app->payment_plan }}"
                                    data-plan-note="{{ $app->payment_plan_note }}"
                                    data-installment="{{ $app->installment_amount }}">
                                {{ $app->employee->first_name }} {{ $app->employee->last_name }} —
                                K {{ number_format($app->amount, 2) }} —
                                approved {{ $app->reviewed_at?->format('d M Y') }}
                            </option>
                        @endforeach
                    </select>
                    <small class="llc-hint">Picking one fills in the fields below — you can still adjust anything before saving. Only shows applications not already added to the ledger.</small>
                </div>
            @endif

            <input type="hidden" name="loan_request_id" id="loanRequestIdInput">
        </div>

        <div class="llc-card">
            <div class="llc-section-label">Loan Details</div>

            <div class="llc-grid llc-grid-2">
                <div class="llc-field">
                    <label class="llc-label">Employee</label>
                    <select name="employee_id" id="employeeSelect" class="llc-input" required>
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
                    <input type="date" name="start_date" id="startDateInput" class="llc-input" value="{{ old('start_date', now()->toDateString()) }}" required>
                </div>

                <div class="llc-field">
                    <label class="llc-label">Principal Amount</label>
                    <input type="number" step="0.01" min="0.01" name="principal_amount" id="principalInput" class="llc-input" value="{{ old('principal_amount') }}" required>
                </div>
                <div class="llc-field">
                    <label class="llc-label">Current Balance</label>
                    <input type="number" step="0.01" min="0" name="balance" id="balanceInput" class="llc-input" value="{{ old('balance') }}" placeholder="Defaults to principal amount">
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
                    <input type="number" step="0.01" min="0" name="installment_amount" id="installmentInput" class="llc-input" value="{{ old('installment_amount') }}">
                    <small class="llc-hint">Required for Monthly / Every Two Months / Other. Ignored for Once-off (the full balance is deducted in one payroll run).</small>
                </div>

                <div class="llc-field" id="planNoteField">
                    <label class="llc-label">Plan Detail</label>
                    <input type="text" name="payment_plan_note" id="planNoteInput" class="llc-input" value="{{ old('payment_plan_note') }}" placeholder="e.g. K500 every 3 months">
                </div>
                <div class="llc-field">
                    <label class="llc-label">Notes</label>
                    <input type="text" name="notes" class="llc-input" value="{{ old('notes') }}" placeholder="Internal note (optional)">
                </div>
            </div>
        </div>

        {{-- ================= LIVE REPAYMENT SCHEDULE PREVIEW ================= --}}
        <div class="llc-card llc-schedule-card">
            <div class="llc-section-label">Repayment Schedule Preview</div>
            <div id="scheduleEmpty" class="llc-empty-note">
                <i class="bi bi-calendar3"></i> Fill in Principal, Payment Plan, and Installment Amount to see the projected schedule.
            </div>
            <div id="scheduleSummary" class="llc-schedule-summary" style="display:none;"></div>
            <table class="llc-schedule-table" id="scheduleTable" style="display:none;">
                <thead>
                    <tr><th>#</th><th>Date</th><th class="num">Amount</th><th class="num">Balance After</th></tr>
                </thead>
                <tbody id="scheduleBody"></tbody>
            </table>
            <div id="scheduleMore" class="llc-schedule-more" style="display:none;"></div>
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
    --paper: #ffffff; --ink: #00742E; --ink-soft: #58687D; --ink-faint: #93A0B2;
    --brass: #00742E; --brass-soft: #EAF7ED; --line: #D9E9DE; --accent-orange: #E97B00; --accent-red: #D50115;
    --warn: var(--accent-red); --warn-bg: #FFF0F1;
    font-family: 'IBM Plex Sans', sans-serif; color: var(--ink); background: var(--paper); padding: 1.5rem 0 2.5rem;
    max-width: 800px; margin: 0 auto;
}
.llc-back { display: inline-flex; align-items: center; gap: .35rem; font-size: .78rem; font-weight: 600; color: var(--ink-soft); text-decoration: none; margin-bottom: .6rem; }
.llc-back:hover { color: var(--brass); }
.llc-eyebrow { font-family: 'IBM Plex Sans', sans-serif; font-size: .68rem; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; color: var(--brass); margin-bottom: .2rem; }
.llc-title { font-family: 'IBM Plex Sans', sans-serif; font-size: 1.3rem; font-weight: 700; margin: 0; }
.llc-subtitle { font-size: .8rem; color: var(--ink-faint); margin: .2rem 0 1.2rem; }

.llc-alert-error { background: var(--warn-bg); color: var(--warn); border: 1px solid #E3C6BC; border-radius: 6px; padding: .8rem 1rem; font-size: .82rem; margin-bottom: 1rem; }
.llc-alert-error ul { margin: .3rem 0 0; padding-left: 1.1rem; }

.llc-card { background: #fff; border: 1px solid var(--line); border-radius: 6px; padding: 1.4rem; margin-bottom: 1rem; position: relative; }
.llc-card::before { content: ""; position: absolute; top: 0; left: 26px; width: 56px; height: 5px; background: var(--brass); border-radius: 0 0 3px 3px; }
.llc-section-label { font-family: 'IBM Plex Sans', sans-serif; font-size: .8rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--ink); border-bottom: 1px solid var(--line); padding-bottom: .7rem; margin: .3rem 0 1.1rem; }
.llc-optional { color: var(--ink-faint); font-weight: 500; text-transform: none; letter-spacing: 0; font-size: .7rem; }

.llc-prefill-card::before { background: var(--accent-orange); }
.llc-empty-note { font-size: .8rem; color: var(--ink-faint); display: flex; align-items: center; gap: .4rem; }

.llc-grid { display: grid; gap: 1rem; }
.llc-grid-2 { grid-template-columns: 1fr 1fr; }
@media (max-width: 620px) { .llc-grid-2 { grid-template-columns: 1fr; } }
.llc-field { display: flex; flex-direction: column; gap: .35rem; }
.llc-label { font-size: .68rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--ink-faint); }
.llc-hint { font-size: .7rem; color: var(--ink-faint); line-height: 1.4; }
.llc-input { font-size: .84rem; padding: .6rem .8rem; border: 1px solid var(--line); border-radius: 6px; background: #fff; color: var(--ink); outline: none; width: 100%; }
.llc-input:focus { border-color: var(--brass); box-shadow: 0 0 0 3px var(--brass-soft); }
.llc-input.llc-prefilled { border-color: var(--accent-orange); background: #FFFAF3; }

.llc-schedule-card::before { background: var(--ink); }
.llc-schedule-summary { font-size: .84rem; font-weight: 600; color: var(--ink); background: var(--brass-soft); border-radius: 6px; padding: .7rem .9rem; margin-bottom: .9rem; }
.llc-schedule-table { width: 100%; border-collapse: collapse; font-size: .8rem; }
.llc-schedule-table thead th { font-size: .64rem; font-weight: 600; color: var(--ink-faint); text-transform: uppercase; letter-spacing: .05em; padding: .5rem; border-bottom: 2px solid var(--ink); text-align: left; }
.llc-schedule-table thead th.num, .llc-schedule-table td.num { text-align: right; }
.llc-schedule-table td { padding: .45rem .5rem; border-bottom: 1px solid var(--line); font-family: 'IBM Plex Mono', monospace; }
.llc-schedule-more { font-size: .72rem; color: var(--ink-faint); margin-top: .6rem; text-align: center; }

.llc-actions { display: flex; justify-content: flex-end; gap: .6rem; }
.llc-btn { display: inline-flex; align-items: center; gap: .4rem; font-size: .8rem; font-weight: 600; padding: .55rem 1.1rem; border-radius: 6px; border: 1px solid transparent; cursor: pointer; text-decoration: none; }
.llc-btn-primary { background: var(--ink); color: #fff; border-color: var(--ink); }
.llc-btn-primary:hover { background: #005A24; }
.llc-btn-outline { background: #fff; color: var(--ink-soft); border-color: var(--line); }
.llc-btn-outline:hover { border-color: var(--brass); color: var(--brass); }
</style>

<script>
(function () {
    const prefillSelect    = document.getElementById('prefillSelect');
    const loanRequestId    = document.getElementById('loanRequestIdInput');
    const employeeSelect   = document.getElementById('employeeSelect');
    const principalInput   = document.getElementById('principalInput');
    const balanceInput     = document.getElementById('balanceInput');
    const planSelect       = document.getElementById('planSelect');
    const planNoteInput    = document.getElementById('planNoteInput');
    const installmentInput = document.getElementById('installmentInput');
    const installmentField = document.getElementById('installmentField');
    const startDateInput   = document.getElementById('startDateInput');

    const scheduleEmpty   = document.getElementById('scheduleEmpty');
    const scheduleSummary = document.getElementById('scheduleSummary');
    const scheduleTable   = document.getElementById('scheduleTable');
    const scheduleBody    = document.getElementById('scheduleBody');
    const scheduleMore    = document.getElementById('scheduleMore');

    function toggleInstallment() {
        const isOnceOff = planSelect.value === 'once_off';
        installmentField.style.opacity = isOnceOff ? '.45' : '1';
        installmentField.querySelector('input').disabled = isOnceOff;
    }

    function markPrefilled(el, on) {
        el.classList.toggle('llc-prefilled', on);
    }

    function applyPrefill() {
        if (!prefillSelect) return;
        const opt = prefillSelect.options[prefillSelect.selectedIndex];

        if (!prefillSelect.value) {
            loanRequestId.value = '';
            [employeeSelect, principalInput, planSelect, planNoteInput, installmentInput].forEach(el => markPrefilled(el, false));
            recalcSchedule();
            return;
        }

        loanRequestId.value = prefillSelect.value;
        employeeSelect.value = opt.dataset.employeeId || '';
        principalInput.value = opt.dataset.amount || '';
        planSelect.value = opt.dataset.plan || '';
        planNoteInput.value = opt.dataset.planNote || '';
        installmentInput.value = opt.dataset.installment || '';

        [employeeSelect, principalInput, planSelect, planNoteInput, installmentInput].forEach(el => markPrefilled(el, true));

        toggleInstallment();
        recalcSchedule();
    }

    function formatMoney(v) {
        return 'K ' + v.toLocaleString('en-ZM', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function recalcSchedule() {
        const principal = parseFloat(balanceInput.value) || parseFloat(principalInput.value) || 0;
        const plan = planSelect.value;
        const start = startDateInput.value ? new Date(startDateInput.value + 'T00:00:00') : new Date();

        scheduleTable.style.display = 'none';
        scheduleSummary.style.display = 'none';
        scheduleMore.style.display = 'none';

        if (!principal || !plan) {
            scheduleEmpty.style.display = 'flex';
            return;
        }

        if (plan === 'once_off') {
            scheduleEmpty.style.display = 'none';
            scheduleSummary.style.display = 'block';
            scheduleSummary.textContent = formatMoney(principal) + ' deducted in full on ' +
                start.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) + '.';
            return;
        }

        const installment = parseFloat(installmentInput.value) || 0;
        if (installment <= 0) {
            scheduleEmpty.style.display = 'flex';
            scheduleEmpty.querySelector('span') || null;
            scheduleEmpty.innerHTML = '<i class="bi bi-calendar3"></i> Enter an installment amount to see the projected schedule.';
            return;
        }

        scheduleEmpty.style.display = 'none';

        const intervalMonths = plan === 'bi_monthly' ? 2 : 1;
        let balance = principal;
        let date = new Date(start);
        const rows = [];
        let n = 1;
        const MAX_ROWS = 36;

        while (balance > 0 && n <= MAX_ROWS) {
            const amount = Math.min(installment, balance);
            balance = Math.round((balance - amount) * 100) / 100;
            rows.push({ no: n, date: new Date(date), amount, balanceAfter: balance });
            date.setMonth(date.getMonth() + intervalMonths);
            n++;
        }

        scheduleSummary.style.display = 'block';
        const lastDate = rows[rows.length - 1].date.toLocaleDateString('en-GB', { month: 'short', year: 'numeric' });
        scheduleSummary.textContent = rows.length + ' installment' + (rows.length === 1 ? '' : 's') +
            ' of up to ' + formatMoney(installment) + ' — projected payoff around ' + lastDate + '.';

        scheduleBody.innerHTML = '';
        const visibleRows = rows.slice(0, 12);
        visibleRows.forEach(r => {
            const tr = document.createElement('tr');
            tr.innerHTML = '<td>' + r.no + '</td>' +
                '<td>' + r.date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) + '</td>' +
                '<td class="num">' + formatMoney(r.amount) + '</td>' +
                '<td class="num">' + formatMoney(r.balanceAfter) + '</td>';
            scheduleBody.appendChild(tr);
        });
        scheduleTable.style.display = 'table';

        if (rows.length > visibleRows.length) {
            scheduleMore.style.display = 'block';
            scheduleMore.textContent = '+ ' + (rows.length - visibleRows.length) + ' more installment' +
                ((rows.length - visibleRows.length) === 1 ? '' : 's') + ' not shown';
        }
    }

    if (prefillSelect) prefillSelect.addEventListener('change', applyPrefill);
    planSelect.addEventListener('change', () => { toggleInstallment(); recalcSchedule(); });
    [principalInput, balanceInput, installmentInput, startDateInput].forEach(el => el.addEventListener('input', recalcSchedule));

    toggleInstallment();
    recalcSchedule();
})();
</script>

@endsection