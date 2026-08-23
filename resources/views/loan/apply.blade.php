<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Apply for a Loan</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Roboto+Slab:wght@600;700&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap');
* { box-sizing: border-box; }
body { margin: 0; font-family: 'IBM Plex Sans', -apple-system, sans-serif; background: #F1EFE9; color: #17263A; padding: 2.5rem 1rem; }
.wrap { max-width: 520px; margin: 0 auto; }
.eyebrow { font-family: 'Roboto Slab', serif; font-size: .68rem; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; color: #9C7A32; margin-bottom: .3rem; text-align: center; }
h1 { font-family: 'Roboto Slab', serif; font-size: 1.4rem; text-align: center; margin: 0 0 .3rem; }
.sub { text-align: center; font-size: .84rem; color: #93A0B2; margin-bottom: 1.6rem; }
.card { background: #fff; border: 1px solid #E1DACB; border-radius: 8px; padding: 1.8rem; position: relative; }
.card::before { content: ""; position: absolute; top: 0; left: 28px; width: 60px; height: 6px; background: #9C7A32; border-radius: 0 0 3px 3px; }
.field { margin-bottom: 1.1rem; }
label { display: block; font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #93A0B2; margin-bottom: .35rem; }
.field-hint { font-size: .72rem; color: #93A0B2; margin-top: .3rem; }
select, input, textarea { width: 100%; font-size: .88rem; padding: .65rem .8rem; border: 1px solid #E1DACB; border-radius: 6px; outline: none; font-family: inherit; color: #17263A; }
select:focus, input:focus, textarea:focus { border-color: #9C7A32; }
.row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
textarea { resize: vertical; min-height: 80px; }
input[type="file"] { padding: .5rem .6rem; font-size: .8rem; }
button { width: 100%; background: #17263A; color: #fff; border: none; padding: .8rem; border-radius: 6px; font-size: .88rem; font-weight: 700; cursor: pointer; margin-top: .4rem; }
button:hover { background: #23374F; }
.error { background: #F7E7E2; color: #9B3A2A; border: 1px solid #E3C6BC; border-radius: 6px; padding: .8rem 1rem; font-size: .8rem; margin-bottom: 1.2rem; }
.error ul { margin: .3rem 0 0; padding-left: 1.1rem; }
#planNoteField, #installmentField { display: none; }
.amount-prefix { position: relative; }
.amount-prefix span { position: absolute; left: .8rem; top: 50%; transform: translateY(-50%); color: #93A0B2; font-size: .88rem; }
.amount-prefix input { padding-left: 1.9rem; }
.schedule-badge {
    display: flex; align-items: center; gap: .5rem; font-size: .78rem; font-weight: 600;
    background: #FCFBF8; color: #93A0B2; padding: .6rem .8rem; border-radius: 6px; margin-bottom: 1.1rem;
}
.schedule-badge.ready { background: #EAF3EC; color: #2F6F4E; }
</style>
</head>
<body>
<div class="wrap">
    <div class="eyebrow">Personnel Registry</div>
    <h1>Apply for a Loan</h1>
    <div class="sub">Use your registered company email address to apply</div>

    @if($errors->any())
        <div class="error">
            <strong>Please fix the following:</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card">
        <form method="POST" action="{{ route('loan.apply.store') }}" enctype="multipart/form-data" id="loanForm">
            @csrf

            <div class="row">
                <div class="field">
                    <label>Employee Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" required>
                </div>
                <div class="field">
                    <label>Company Email Address</label>
                    <input type="email" name="email" value="{{ old('email') }}" required placeholder="you@company.com">
                    <div class="field-hint">Must match the email on your employee record.</div>
                </div>
            </div>

            <div class="field">
                <label>Loan Amount</label>
                <div class="amount-prefix">
                    <span>K</span>
                    <input type="number" step="0.01" min="1" id="amountInput" name="amount" value="{{ old('amount') }}" required>
                </div>
            </div>

            <div class="field">
                <label>Payment Plan</label>
                <select name="payment_plan" id="planSelect" required>
                    <option value="">Select repayment plan…</option>
                    @foreach(\App\Models\LoanRequest::PAYMENT_PLANS as $key => $label)
                        <option value="{{ $key }}" {{ old('payment_plan') === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field" id="installmentField">
                <label>Installment Amount</label>
                <div class="amount-prefix">
                    <span>K</span>
                    <input type="number" step="0.01" min="0.01" id="installmentInput" name="installment_amount" value="{{ old('installment_amount') }}">
                </div>
                <div class="field-hint">How much you'd like deducted each installment. Used to work out how long repayment will take.</div>
            </div>

            <div class="schedule-badge" id="scheduleBadge">
                <span>ℹ</span> <span id="scheduleBadgeText">Enter an amount and installment to see your repayment timeline</span>
            </div>

            <div class="field" id="planNoteField">
                <label>Describe Your Plan</label>
                <input type="text" name="payment_plan_note" value="{{ old('payment_plan_note') }}" placeholder="e.g. K500 deducted every 3 months">
            </div>

            <div class="field">
                <label>Reason (optional)</label>
                <textarea name="reason" placeholder="Briefly describe why you need the loan">{{ old('reason') }}</textarea>
            </div>

            <div class="field">
                <label>Supporting Documents (optional)</label>
                <input type="file" name="documents[]" multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                <div class="field-hint">Images, PDF, or Word documents. Max 5MB per file.</div>
            </div>

            <button type="submit">Submit Application</button>
        </form>
    </div>
</div>

<script>
(function () {
    const planSelect = document.getElementById('planSelect');
    const installmentField = document.getElementById('installmentField');
    const planNoteField = document.getElementById('planNoteField');
    const amountInput = document.getElementById('amountInput');
    const installmentInput = document.getElementById('installmentInput');
    const badge = document.getElementById('scheduleBadge');
    const badgeText = document.getElementById('scheduleBadgeText');

    function togglePlanFields() {
        const isOnceOff = planSelect.value === 'once_off';
        installmentField.style.display = isOnceOff ? 'none' : 'block';
        planNoteField.style.display = planSelect.value === 'other' ? 'block' : 'none';
        installmentInput.required = !isOnceOff && planSelect.value !== '';
        recalcSchedule();
    }

    function recalcSchedule() {
        const amount = parseFloat(amountInput.value) || 0;
        const plan = planSelect.value;

        if (!amount || !plan) {
            badge.classList.remove('ready');
            badgeText.textContent = 'Enter an amount and installment to see your repayment timeline';
            return;
        }

        if (plan === 'once_off') {
            badge.classList.add('ready');
            badgeText.textContent = 'Full amount (K' + amount.toFixed(2) + ') deducted in a single payroll run';
            return;
        }

        const installment = parseFloat(installmentInput.value) || 0;

        if (installment <= 0) {
            badge.classList.remove('ready');
            badgeText.textContent = 'Enter an installment amount to see your repayment timeline';
            return;
        }

        const months = Math.ceil(amount / installment);
        const intervalMonths = plan === 'bi_monthly' ? 2 : 1;
        const totalMonths = months * intervalMonths;

        const payoff = new Date();
        payoff.setMonth(payoff.getMonth() + totalMonths);
        const payoffLabel = payoff.toLocaleDateString('en-GB', { month: 'short', year: 'numeric' });

        badge.classList.add('ready');
        badgeText.textContent = months + ' installment' + (months === 1 ? '' : 's') +
            ' — approximately paid off by ' + payoffLabel;
    }

    planSelect.addEventListener('change', togglePlanFields);
    amountInput.addEventListener('input', recalcSchedule);
    installmentInput.addEventListener('input', recalcSchedule);
    togglePlanFields();
})();
</script>
</body>
</html>