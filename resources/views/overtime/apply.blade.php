<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Apply for Overtime</title>
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
.eyebrow { font-family: 'IBM Plex Sans', sans-serif; font-size: .68rem; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; color: var(--brass); margin-bottom: .3rem; text-align: center; }
h1 { font-family: 'IBM Plex Sans', sans-serif; font-size: 1.4rem; text-align: center; margin: 0 0 .3rem; color: var(--ink); }
.sub { text-align: center; font-size: .84rem; color: var(--ink-faint); margin-bottom: 1.6rem; }
.card { background: var(--paper); border: 1px solid var(--line); border-radius: 8px; padding: 1.8rem; position: relative; }
.card::before { content: ""; position: absolute; top: 0; left: 28px; width: 60px; height: 6px; background: var(--brass); border-radius: 0 0 3px 3px; }
.field { margin-bottom: 1.1rem; }
label { display: block; font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--ink-faint); margin-bottom: .35rem; }
.field-hint { font-size: .72rem; color: var(--ink-faint); margin-top: .3rem; }
select, input, textarea { width: 100%; font-size: .88rem; padding: .65rem .8rem; border: 1px solid var(--line); border-radius: 6px; outline: none; font-family: inherit; color: var(--ink); }
select:focus, input:focus, textarea:focus { border-color: var(--brass); }
.row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }
@media (max-width: 480px) { .row-3 { grid-template-columns: 1fr; } }
textarea { resize: vertical; min-height: 70px; }
button { width: 100%; background: var(--brass); color: var(--paper); border: none; padding: .8rem; border-radius: 6px; font-size: .88rem; font-weight: 700; cursor: pointer; margin-top: .4rem; }
button:hover { background: var(--ink-soft); }
.error { background: #FDE8E8; color: var(--accent-red); border: 1px solid #F5C5D0; border-radius: 6px; padding: .8rem 1rem; font-size: .8rem; margin-bottom: 1.2rem; }
.error ul { margin: .3rem 0 0; padding-left: 1.1rem; }
.type-options { display: flex; gap: .7rem; }
.type-option { flex: 1; border: 1px solid var(--line); border-radius: 6px; padding: .7rem; cursor: pointer; position: relative; }
.type-option input { position: absolute; opacity: 0; }
.type-option-label { font-size: .82rem; font-weight: 700; color: var(--ink); }
.type-option-tip { font-size: .68rem; color: var(--ink-faint); margin-top: .15rem; }
.type-option:has(input:checked) { border-color: var(--brass); background: var(--brass-soft); }
.hours-badge { display: inline-flex; align-items: center; gap: .4rem; font-size: .78rem; font-weight: 600; background: var(--paper); color: var(--ink-faint); border: 1px solid var(--line); padding: .5rem .8rem; border-radius: 6px; margin-bottom: 1.1rem; }
.hours-badge.hours-badge-ready { background: var(--brass-soft); color: var(--brass); border-color: var(--brass); }
</style>
</head>
<body>
<div class="wrap">
    <div class="eyebrow">Personnel Registry</div>
    <h1>Apply for Overtime</h1>
    <div class="sub">Use your registered company email address to apply</div>

    @if($errors->any())
        <div class="error">
            <strong>Please fix the following:</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card">
        <form method="POST" action="{{ route('overtime.apply.store') }}">
            @csrf

            <div class="row">
                <div class="field">
                    <label>Employee Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" required>
                </div>
                <div class="field">
                    <label>Company Email Address</label>
                    <input type="email" name="email" value="{{ old('email') }}" required placeholder="you@company.com">
                </div>
            </div>

            <div class="field">
                <label>Date</label>
                <input type="date" name="date" id="otDate" value="{{ old('date') }}" required>
            </div>

            <div class="row">
                <div class="field">
                    <label>Start Time</label>
                    <input type="time" name="start_time" id="startTime" value="{{ old('start_time') }}" required>
                </div>
                <div class="field">
                    <label>End Time</label>
                    <input type="time" name="end_time" id="endTime" value="{{ old('end_time') }}" required>
                </div>
            </div>

            <div class="hours-badge" id="hoursBadge">
                <span>ℹ</span> <span id="hoursBadgeText">Select start and end time to see hours worked</span>
            </div>

            <div class="field">
                <label>Type of Overtime</label>
                <div class="type-options">
                    @foreach(\App\Models\OvertimeRequest::TYPES as $key => $t)
                        <label class="type-option" title="{{ $t['tooltip'] }}">
                            <input type="radio" name="type" value="{{ $key }}" {{ old('type') === $key ? 'checked' : '' }} required>
                            <div class="type-option-label">{{ $t['label'] }}</div>
                            <div class="type-option-tip">{{ $t['tooltip'] }} · {{ $t['multiplier'] }}x rate</div>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="field">
                <label>Comment (optional)</label>
                <textarea name="comment" placeholder="What was the overtime for?">{{ old('comment') }}</textarea>
            </div>

            <button type="submit">Submit Application</button>
        </form>
    </div>
</div>

<script>
(function () {
    const startInput = document.getElementById('startTime');
    const endInput = document.getElementById('endTime');
    const badge = document.getElementById('hoursBadge');
    const badgeText = document.getElementById('hoursBadgeText');

    function recalc() {
        if (!startInput.value || !endInput.value) return;
        const [sh, sm] = startInput.value.split(':').map(Number);
        const [eh, em] = endInput.value.split(':').map(Number);
        const startMinutes = sh * 60 + sm;
        const endMinutes = eh * 60 + em;

        if (endMinutes <= startMinutes) {
            badge.classList.remove('hours-badge-ready');
            badgeText.textContent = 'End time must be after start time';
            return;
        }

        const hours = Math.round(((endMinutes - startMinutes) / 60) * 100) / 100;
        badge.classList.add('hours-badge-ready');
        badgeText.textContent = hours + ' hour' + (hours === 1 ? '' : 's') + ' worked';
    }

    startInput.addEventListener('change', recalc);
    endInput.addEventListener('change', recalc);
})();
</script>
</body>
</html>
