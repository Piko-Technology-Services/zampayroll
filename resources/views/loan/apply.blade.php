<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Apply for a Loan</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap');
:root {
  --paper: #ffffff;
  --ink: #00742E;
  --ink-soft: #58687D;
  --ink-faint: #93A0B2;
  --brass: #00742E;
  --brass-soft: #EAF7ED;
  --line: #D9E9DE;
  --accent-orange: #E97B00;
  --accent-red: #D50115;
}
* { box-sizing: border-box; }
body { margin: 0; font-family: 'IBM Plex Sans', -apple-system, sans-serif; background: var(--brass-soft); color: var(--ink); padding: 2.5rem 1rem; }
.wrap { max-width: 520px; margin: 0 auto; }
.eyebrow { font-family: 'IBM Plex Sans', -apple-system, sans-serif; font-size: .68rem; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; color: var(--brass); margin-bottom: .3rem; text-align: center; }
h1 { font-family: 'IBM Plex Sans', -apple-system, sans-serif; font-size: 1.4rem; text-align: center; margin: 0 0 .3rem; color: var(--ink); }
.sub { text-align: center; font-size: .84rem; color: var(--ink-faint); margin-bottom: 1.6rem; }
.card { background: var(--paper); border: 1px solid var(--line); border-radius: 8px; padding: 1.8rem; position: relative; }
.card::before { content: ""; position: absolute; top: 0; left: 28px; width: 60px; height: 6px; background: var(--brass); border-radius: 0 0 3px 3px; }
.field { margin-bottom: 1.1rem; }
label { display: block; font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--ink-faint); margin-bottom: .35rem; }
.field-hint { font-size: .72rem; color: var(--ink-faint); margin-top: .3rem; }
select, input, textarea { width: 100%; font-size: .88rem; padding: .65rem .8rem; border: 1px solid var(--line); border-radius: 6px; outline: none; font-family: inherit; color: var(--ink); }
select:focus, input:focus, textarea:focus { border-color: var(--brass); }
.row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
textarea { resize: vertical; min-height: 80px; }
input[type="file"] { padding: .5rem .6rem; font-size: .8rem; }
button { width: 100%; background: var(--ink); color: var(--paper); border: none; padding: .8rem; border-radius: 6px; font-size: .88rem; font-weight: 700; cursor: pointer; margin-top: .4rem; }
button:hover { background: var(--brass); }
.error { background: #FFE8E8; color: var(--accent-red); border: 1px solid #FFD1D1; border-radius: 6px; padding: .8rem 1rem; font-size: .8rem; margin-bottom: 1.2rem; }
.error ul { margin: .3rem 0 0; padding-left: 1.1rem; }
#planNoteField { display: none; }
.amount-prefix { position: relative; }
.amount-prefix span { position: absolute; left: .8rem; top: 50%; transform: translateY(-50%); color: var(--ink-faint); font-size: .88rem; }
.amount-prefix input { padding-left: 1.9rem; }
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
        <form method="POST" action="{{ route('loan.apply.store') }}" enctype="multipart/form-data">
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
                    <input type="number" step="0.01" min="1" name="amount" value="{{ old('amount') }}" required>
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
    const select = document.getElementById('planSelect');
    const noteField = document.getElementById('planNoteField');
    function toggle() {
        noteField.style.display = select.value === 'other' ? 'block' : 'none';
    }
    select.addEventListener('change', toggle);
    toggle();
})();
</script>
</body>
</html>
