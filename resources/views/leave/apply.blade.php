<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Apply for Leave</title>
<style>
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
@import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap');
* { box-sizing: border-box; }
body {
    margin: 0; font-family: 'IBM Plex Sans', -apple-system, sans-serif;
    background: var(--brass-soft); color: var(--ink); padding: 2.5rem 1rem;
}
.wrap { max-width: 540px; margin: 0 auto; }
.eyebrow { font-family: 'IBM Plex Sans', sans-serif; font-size: .68rem; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; color: var(--brass); margin-bottom: .3rem; text-align: center; }
h1 { font-family: 'IBM Plex Sans', sans-serif; font-size: 1.4rem; text-align: center; margin: 0 0 .3rem; color: var(--ink); }
.sub { text-align: center; font-size: .84rem; color: var(--ink-faint); margin-bottom: 1.6rem; }
.card { background: var(--paper); border: 1px solid var(--line); border-radius: 8px; padding: 1.8rem; position: relative; }
.card::before { content: ""; position: absolute; top: 0; left: 28px; width: 60px; height: 6px; background: var(--brass); border-radius: 0 0 3px 3px; }
.field { margin-bottom: 1.1rem; }
label { display: block; font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--ink-faint); margin-bottom: .35rem; }
.field-hint { font-size: .72rem; color: var(--ink-faint); margin-top: .3rem; }
select, input, textarea {
    width: 100%; font-size: .88rem; padding: .65rem .8rem; border: 1px solid var(--line);
    border-radius: 6px; outline: none; font-family: inherit; color: var(--ink);
}
select:focus, input:focus, textarea:focus { border-color: var(--brass); }
input[readonly] { background: var(--brass-soft); color: var(--ink-soft); }
.row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }
@media (max-width: 480px) { .row-3 { grid-template-columns: 1fr; } }
textarea { resize: vertical; min-height: 80px; }
input[type="file"] { padding: .5rem .6rem; font-size: .8rem; }
button {
    width: 100%; background: var(--brass); color: var(--paper); border: none; padding: .8rem;
    border-radius: 6px; font-size: .88rem; font-weight: 700; cursor: pointer; margin-top: .4rem;
}
button:hover { background: #005a23; }
.error { background: #FFE8E8; color: var(--accent-red); border: 1px solid #FFD1D1; border-radius: 6px; padding: .8rem 1rem; font-size: .8rem; margin-bottom: 1.2rem; }
.error ul { margin: .3rem 0 0; padding-left: 1.1rem; }
.section-divider { border-top: 1px solid var(--line); margin: 1.3rem 0; }
.days-badge {
    display: inline-flex; align-items: center; gap: .4rem; font-size: .78rem; font-weight: 600;
    background: var(--brass-soft); color: var(--brass); padding: .5rem .8rem; border-radius: 6px; margin-bottom: 1.1rem;
}
.days-badge.days-badge-pending { background: var(--brass-soft); color: var(--ink-faint); }
</style>
</head>
<body>
<div class="wrap">
    <div class="eyebrow">Personnel Registry</div>
    <h1>Apply for Leave</h1>
    <div class="sub">Use your registered company email address to apply</div>

    @if($errors->any())
        <div class="error">
            <strong>Please fix the following:</strong>
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <form method="POST" action="{{ route('leave.apply.store') }}" enctype="multipart/form-data" id="leaveForm">
            @csrf

            <div class="row">
                <div class="field">
                    <label>Employee Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" required>
                </div>
                <div class="field">
                    <label>Company Email Address</label>
                    <input type="email" name="email" id="emailInput" value="{{ old('email') }}" required placeholder="you@company.com">
                    <div class="field-hint" id="emailHint">Must match the email on your employee record.</div>
                </div>
            </div>

            <div class="field">
                <label>Type of Leave</label>
                <select name="leave_type" required>
                    <option value="">Select leave type…</option>
                    @foreach(\App\Models\LeaveRequest::TYPES as $key => $label)
                        <option value="{{ $key }}" {{ old('leave_type') === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="row-3">
                <div class="field">
                    <label>Start Date</label>
                    <input type="date" name="start_date" id="startDate" value="{{ old('start_date') }}" required>
                </div>
                <div class="field">
                    <label>End Date</label>
                    <input type="date" name="end_date" id="endDate" value="{{ old('end_date') }}" required>
                </div>
                <div class="field">
                    <label>Return Date</label>
                    <input type="date" name="return_date" id="returnDate" value="{{ old('return_date') }}" readonly>
                    <div class="field-hint">Auto-calculated — first working day after your leave ends.</div>
                </div>
            </div>

            <div class="days-badge days-badge-pending" id="daysBadge">
                <span>ℹ</span> <span id="daysBadgeText">Select your dates to see working days calculated</span>
            </div>

            <div class="field">
                <label>Comment (optional)</label>
                <textarea name="comment" placeholder="Any additional information for HR">{{ old('comment') }}</textarea>
            </div>

            <div class="field">
                <label>Supporting Documents (optional)</label>
                <input type="file" name="documents[]" multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                <div class="field-hint">Images, PDF, or Word documents. Max 5MB per file.</div>
            </div>

            <div class="section-divider"></div>

            <div class="field">
                <label>Supervisor Email (optional)</label>
                <input type="email" name="supervisor_email" value="{{ old('supervisor_email') }}" placeholder="supervisor@company.com">
                <div class="field-hint">Your supervisor will be copied on the application email to HR.</div>
            </div>

            <button type="submit">Submit Application</button>
        </form>
    </div>
</div>

<script>
(function () {
    const lookupUrl = "{{ route('leave.apply.workdays') }}";
    const emailInput = document.getElementById('emailInput');
    const emailHint = document.getElementById('emailHint');
    const startInput = document.getElementById('startDate');
    const endInput = document.getElementById('endDate');
    const returnInput = document.getElementById('returnDate');
    const daysBadge = document.getElementById('daysBadge');
    const daysBadgeText = document.getElementById('daysBadgeText');

    // Sensible default until the email is verified — Mon-Fri, no holidays.
    let config = { matched: false, work_days: [1, 2, 3, 4, 5], holidays: [] };

    function isWorkingDay(date) {
        const iso = date.getDay() === 0 ? 7 : date.getDay(); // JS Sun=0 -> ISO 7
        if (!config.work_days.includes(iso)) return false;

        const mm = String(date.getMonth() + 1).padStart(2, '0');
        const dd = String(date.getDate()).padStart(2, '0');
        const yyyy = date.getFullYear();
        const isoDate = `${yyyy}-${mm}-${dd}`;
        const monthDay = `${mm}-${dd}`;

        const isHoliday = config.holidays.some(h => h.is_recurring
            ? h.date.slice(5) === monthDay
            : h.date === isoDate);

        return !isHoliday;
    }

    function countBusinessDays(start, end) {
        let count = 0;
        const cursor = new Date(start);
        while (cursor <= end) {
            if (isWorkingDay(cursor)) count++;
            cursor.setDate(cursor.getDate() + 1);
        }
        return count;
    }

    function nextBusinessDay(end) {
        const cursor = new Date(end);
        cursor.setDate(cursor.getDate() + 1);
        while (!isWorkingDay(cursor)) {
            cursor.setDate(cursor.getDate() + 1);
        }
        return cursor;
    }

    function toDateInputValue(date) {
        const mm = String(date.getMonth() + 1).padStart(2, '0');
        const dd = String(date.getDate()).padStart(2, '0');
        return `${date.getFullYear()}-${mm}-${dd}`;
    }

    function recalculate() {
        if (!startInput.value || !endInput.value) return;

        const start = new Date(startInput.value + 'T00:00:00');
        const end = new Date(endInput.value + 'T00:00:00');

        if (end < start) {
            daysBadge.classList.add('days-badge-pending');
            daysBadgeText.textContent = 'End date must be on or after the start date';
            returnInput.value = '';
            return;
        }

        const days = countBusinessDays(start, end);
        const returnDate = nextBusinessDay(end);

        returnInput.value = toDateInputValue(returnDate);

        daysBadge.classList.remove('days-badge-pending');
        daysBadgeText.textContent = days + ' working day' + (days === 1 ? '' : 's') + ' requested'
            + (config.matched ? '' : ' (estimate — enter your email to confirm against your company calendar)');
    }

    async function lookupWorkDays() {
        const email = emailInput.value.trim();
        if (!email || !email.includes('@')) return;

        try {
            const res = await fetch(lookupUrl + '?email=' + encodeURIComponent(email));
            const data = await res.json();

            if (data.matched) {
                config = data;
                emailHint.textContent = 'Email verified against your company calendar.';
                emailHint.style.color = '#2F6F4E';
            } else {
                config = { matched: false, work_days: [1, 2, 3, 4, 5], holidays: [] };
                emailHint.textContent = 'No matching employee found yet — using a default Mon-Fri estimate.';
                emailHint.style.color = '#9B3A2A';
            }
        } catch (e) {
            // Silently keep the default estimate if the lookup fails.
        }

        recalculate();
    }

    emailInput.addEventListener('blur', lookupWorkDays);
    startInput.addEventListener('change', recalculate);
    endInput.addEventListener('change', recalculate);
})();
</script>
</body>
</html>