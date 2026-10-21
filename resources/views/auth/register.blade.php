@extends('layouts.onboarding')

@section('title', 'Create your workspace')

@php
    // Which wizard step owns each field — used to reopen the right step after a server-side error.
    $fieldSteps = [
        1 => ['name', 'position', 'phone', 'email', 'password'],
        2 => ['company_name', 'industry', 'employee_count_range', 'subdomain'],
        3 => ['company_email', 'company_phone', 'website', 'company_address', 'city', 'province', 'company_tpin',
              'pacra_registration_number', 'napsa_employer_number', 'nhima_employer_number', 'workers_comp_number', 'vat_number'],
        4 => ['pay_frequency', 'pay_day', 'work_days', 'working_days_per_month', 'interested_modules', 'heard_about_us'],
        5 => ['terms'],
    ];
    $startStep = 1;
    if ($errors->any()) {
        foreach ($fieldSteps as $step => $fields) {
            foreach ($fields as $f) {
                if ($errors->has($f) || $errors->has($f . '.*')) { $startStep = $step; break 2; }
            }
        }
    }
    $oldDays = old('work_days', [1, 2, 3, 4, 5]);
    $oldModules = old('interested_modules', ['payroll', 'leave', 'portal']);
@endphp

@section('aside')
    <h2>Set up payroll your team will actually enjoy.</h2>
    <p class="lead-copy">Five short steps. Only the first two are required — you can fill in statutory numbers and preferences whenever you like.</p>

    {{-- Live workspace pass --}}
    <div class="ob-pass" aria-live="polite">
        <div class="ob-pass-top">
            <span class="zp-brand-word" style="font-size:1rem"><b>Zam</b>Payroll</span>
            <span class="ob-pass-badge" id="passBadge">Shared workspace</span>
        </div>
        <div class="ob-pass-name placeholder" id="passName">Your company</div>
        <div class="ob-pass-url" id="passUrl">{{ $centralDomain }}</div>
        <div class="ob-pass-grid">
            <div><small>Administrator</small><span id="passAdmin">—</span></div>
            <div><small>Team size</small><span id="passSize">—</span></div>
            <div><small>Industry</small><span id="passIndustry">—</span></div>
            <div><small>Pay cycle</small><span id="passCycle">Monthly</span></div>
        </div>
    </div>
@endsection

@section('content')
<div class="ob-topbar">
    <span>Step <b id="stepNow">{{ $startStep }}</b> of 5</span>
    <span>Already registered? <a href="{{ route('login') }}">Sign in</a></span>
</div>

<div class="ob-steps" role="tablist" aria-label="Registration steps">
    @foreach(['You', 'Company', 'Details', 'Payroll', 'Review'] as $i => $label)
        <button type="button" class="ob-step" data-goto="{{ $i + 1 }}" aria-label="Step {{ $i + 1 }}: {{ $label }}"></button>
    @endforeach
</div>
<div class="ob-step-meta">
    <span id="stepLabel">About you</span>
    <span id="stepNeed">Required</span>
</div>

@if ($errors->any())
    <div class="zp-alert zp-alert-error"><i class="bi bi-exclamation-circle"></i>
        <div>Please fix the highlighted field{{ $errors->count() > 1 ? 's' : '' }} and try again.</div>
    </div>
@endif

<form method="POST" action="{{ route('register') }}" id="wizard" novalidate>
    @csrf
    <input type="hidden" name="finish_later" id="finishLater" value="{{ old('finish_later', 0) }}">

    {{-- ============================== STEP 1 — YOU ============================== --}}
    <section class="ob-panel" data-step="1" data-label="About you" data-need="Required">
        <h1 class="ob-title">Hi there! Who's setting things up?</h1>
        <p class="ob-sub">You'll be the administrator of your company's workspace. We'll send a confirmation link to your email.</p>

        <div class="ob-grid">
            <div class="zp-field">
                <label class="zp-label" for="name">Full name</label>
                <input class="zp-input @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" autocomplete="name" required placeholder="Bupe Katongo">
                @error('name')<div class="zp-error">{{ $message }}</div>@enderror
            </div>
            <div class="zp-field">
                <label class="zp-label" for="position">Job title <small>(optional)</small></label>
                <input class="zp-input" id="position" name="position" value="{{ old('position') }}" placeholder="HR Manager">
            </div>
            <div class="zp-field">
                <label class="zp-label" for="email">Work email</label>
                <input class="zp-input @error('email') is-invalid @enderror" type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="email" required placeholder="you@company.co.zm">
                <div id="emailMsg">@error('email')<div class="zp-error">{{ $message }}</div>@enderror</div>
            </div>
            <div class="zp-field">
                <label class="zp-label" for="phone">Mobile number</label>
                <input class="zp-input @error('phone') is-invalid @enderror" type="tel" id="phone" name="phone" value="{{ old('phone') }}" autocomplete="tel" required placeholder="+260 97 123 4567">
                @error('phone')<div class="zp-error">{{ $message }}</div>@enderror
            </div>
            <div class="zp-field">
                <label class="zp-label" for="password">Password</label>
                <div class="zp-input-wrap">
                    <input class="zp-input @error('password') is-invalid @enderror" type="password" id="password" name="password" autocomplete="new-password" required minlength="8" placeholder="At least 8 characters">
                    <button type="button" class="zp-input-btn" data-toggle-pw="password" aria-label="Show password"><i class="bi bi-eye"></i></button>
                </div>
                <div class="ob-strength" id="pwStrength"><i></i><i></i><i></i><i></i></div>
                <div class="zp-hint" id="pwHint">Use letters and numbers.</div>
                @error('password')<div class="zp-error">{{ $message }}</div>@enderror
            </div>
            <div class="zp-field">
                <label class="zp-label" for="password_confirmation">Confirm password</label>
                <input class="zp-input" type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required placeholder="Type it again">
                <div id="pwMatch"></div>
            </div>
        </div>
    </section>

    {{-- ============================== STEP 2 — COMPANY ============================== --}}
    <section class="ob-panel" data-step="2" data-label="Your company" data-need="Required">
        <h1 class="ob-title" id="companyGreeting">Nice to meet you! Tell us about the company.</h1>
        <p class="ob-sub">Your team size helps us pick the right plan. Teams over {{ $limit }} can upgrade to their own private web address any time.</p>

        <div class="zp-field">
            <label class="zp-label" for="company_name">Company name</label>
            <input class="zp-input @error('company_name') is-invalid @enderror" id="company_name" name="company_name" value="{{ old('company_name') }}" required placeholder="Kafue Agro Ltd">
            @error('company_name')<div class="zp-error">{{ $message }}</div>@enderror
        </div>

        <div class="zp-field">
            <span class="zp-label">How many people work there?</span>
            <div class="ob-tiles" role="radiogroup">
                @foreach($ranges as $key => $range)
                    <div class="ob-tile">
                        <input type="radio" name="employee_count_range" id="size-{{ $loop->index }}" value="{{ $key }}"
                               data-dedicated="0" data-label="{{ $range['label'] }}"
                               @checked(old('employee_count_range') === $key) required>
                        <label for="size-{{ $loop->index }}">
                            <span class="emoji" aria-hidden="true">{{ $range['emoji'] }}</span>
                            <span class="t">{{ $range['label'] }}</span>
                            <span class="s">{{ $range['title'] }}</span>
                        </label>
                    </div>
                @endforeach
            </div>
            @error('employee_count_range')<div class="zp-error">{{ $message }}</div>@enderror
        </div>

        {{-- Where your data lives --}}
        <div class="ob-callout" id="sharedCallout" hidden>
            <div class="ic"><i class="bi bi-cloud-check"></i></div>
            <div>
                <h4>You'll join our shared cloud</h4>
                <p>Teams of up to {{ $limit }} people work on <strong>{{ $centralDomain }}</strong>. Your data is still completely private to your company.</p>
            </div>
        </div>
        <div class="ob-callout dedicated" id="dedicatedCallout" hidden>
            <div class="ic"><i class="bi bi-buildings"></i></div>
            <div style="flex:1">
                <h4>You get a dedicated workspace</h4>
                <p>With more than {{ $limit }} people, your company gets its own web address and its own database.</p>
                <div class="zp-field" style="margin:.8rem 0 0">
                    <label class="zp-label" for="subdomain">Choose your web address</label>
                    <div class="ob-subdomain">
                        <input id="subdomain" name="subdomain" value="{{ old('subdomain') }}" placeholder="kafue-agro" autocomplete="off" spellcheck="false" maxlength="32">
                        <span>.{{ $centralDomain }}</span>
                    </div>
                    <div id="subMsg">@error('subdomain')<div class="zp-error">{{ $message }}</div>@enderror</div>
                </div>
            </div>
        </div>

        <div class="zp-field" style="margin-top:1.3rem">
            <span class="zp-label">Industry</span>
            <div class="ob-tiles industries">
                @foreach($industries as $key => $industry)
                    <div class="ob-tile row">
                        <input type="radio" name="industry" id="ind-{{ $key }}" value="{{ $key }}" data-label="{{ $industry['label'] }}" @checked(old('industry') === $key) required>
                        <label for="ind-{{ $key }}"><i class="bi {{ $industry['icon'] }}"></i><span class="t" style="font-size:.86rem">{{ $industry['label'] }}</span></label>
                    </div>
                @endforeach
            </div>
            @error('industry')<div class="zp-error">{{ $message }}</div>@enderror
        </div>

        {{-- Mandatory info done --}}
        <div class="ob-milestone" id="milestone">
            <h4>That's everything we need 🎉</h4>
            <p>Create your account now and finish the company profile later from Settings, or take two more minutes to add statutory numbers and payroll preferences.</p>
            <label class="ob-terms" style="color:#DCEDE2;margin:0 0 1rem">
                <input type="checkbox" class="terms-box" value="1">
                <span>I agree to the ZamPayroll <a href="{{ route('site.terms') }}" target="_blank" style="color:inherit;font-weight:600">terms of service</a> and <a href="{{ route('site.privacy') }}" target="_blank" style="color:inherit;font-weight:600">privacy policy</a>.</span>
            </label>
            <div style="display:flex;gap:.6rem;flex-wrap:wrap">
                <button type="button" class="zp-btn zp-btn-copper" id="finishNow"><i class="bi bi-lightning-charge"></i> Create my account now</button>
                <button type="button" class="zp-btn zp-btn-ghost" data-next>Keep going</button>
            </div>
            <div class="zp-error" id="termsErrorEarly" hidden style="color:#FFB4B2">Please accept the terms first.</div>
        </div>
    </section>

    {{-- ============================== STEP 3 — DETAILS ============================== --}}
    <section class="ob-panel" data-step="3" data-label="Company details" data-need="Optional — you can skip">
        <h1 class="ob-title">Where can employees and ZRA find you?</h1>
        <p class="ob-sub">These appear on payslips and statutory returns. Don't have them handy? Skip — you'll find them in Company Settings.</p>

        <div class="ob-grid">
            <div class="zp-field">
                <label class="zp-label" for="company_email">Company email</label>
                <input class="zp-input @error('company_email') is-invalid @enderror" type="email" id="company_email" name="company_email" value="{{ old('company_email') }}" placeholder="info@company.co.zm">
                @error('company_email')<div class="zp-error">{{ $message }}</div>@enderror
            </div>
            <div class="zp-field">
                <label class="zp-label" for="company_phone">Office phone</label>
                <input class="zp-input" id="company_phone" name="company_phone" value="{{ old('company_phone') }}" placeholder="+260 211 000 000">
            </div>
            <div class="zp-field full">
                <label class="zp-label" for="company_address">Physical address</label>
                <input class="zp-input" id="company_address" name="company_address" value="{{ old('company_address') }}" placeholder="Plot 1234, Cairo Road">
            </div>
            <div class="zp-field">
                <label class="zp-label" for="city">City / town</label>
                <input class="zp-input" id="city" name="city" value="{{ old('city') }}" placeholder="Lusaka">
            </div>
            <div class="zp-field">
                <label class="zp-label" for="province">Province</label>
                <select class="zp-select" id="province" name="province">
                    <option value="">Select province</option>
                    @foreach($provinces as $p)<option @selected(old('province') === $p)>{{ $p }}</option>@endforeach
                </select>
            </div>
            <div class="zp-field full">
                <label class="zp-label" for="website">Website <small>(optional)</small></label>
                <input class="zp-input" id="website" name="website" value="{{ old('website') }}" placeholder="www.company.co.zm">
            </div>
            <div class="zp-field">
                <label class="zp-label" for="company_tpin">ZRA TPIN</label>
                <input class="zp-input" id="company_tpin" name="company_tpin" value="{{ old('company_tpin') }}" placeholder="1000000000">
            </div>
            <div class="zp-field">
                <label class="zp-label" for="pacra_registration_number">PACRA number</label>
                <input class="zp-input" id="pacra_registration_number" name="pacra_registration_number" value="{{ old('pacra_registration_number') }}">
            </div>
            <div class="zp-field">
                <label class="zp-label" for="napsa_employer_number">NAPSA employer number</label>
                <input class="zp-input" id="napsa_employer_number" name="napsa_employer_number" value="{{ old('napsa_employer_number') }}">
            </div>
            <div class="zp-field">
                <label class="zp-label" for="nhima_employer_number">NHIMA employer number</label>
                <input class="zp-input" id="nhima_employer_number" name="nhima_employer_number" value="{{ old('nhima_employer_number') }}">
            </div>
            <div class="zp-field">
                <label class="zp-label" for="workers_comp_number">WCFCB number</label>
                <input class="zp-input" id="workers_comp_number" name="workers_comp_number" value="{{ old('workers_comp_number') }}">
            </div>
            <div class="zp-field">
                <label class="zp-label" for="vat_number">VAT number <small>(if registered)</small></label>
                <input class="zp-input" id="vat_number" name="vat_number" value="{{ old('vat_number') }}">
            </div>
        </div>
    </section>

    {{-- ============================== STEP 4 — PAYROLL ============================== --}}
    <section class="ob-panel" data-step="4" data-label="How you pay" data-need="Optional — you can skip">
        <h1 class="ob-title">How does payday work at your company?</h1>
        <p class="ob-sub">We use this for leave day counts, overtime rates and payroll reminders.</p>

        <div class="ob-grid">
            <div class="zp-field">
                <label class="zp-label" for="pay_frequency">Pay frequency</label>
                <select class="zp-select" id="pay_frequency" name="pay_frequency">
                    @foreach($frequencies as $k => $label)<option value="{{ $k }}" @selected(old('pay_frequency', 'monthly') === $k)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="zp-field">
                <label class="zp-label" for="pay_day">Usual pay day <small>(day of month)</small></label>
                <input class="zp-input" type="number" min="1" max="31" id="pay_day" name="pay_day" value="{{ old('pay_day', 25) }}">
            </div>
        </div>

        <div class="zp-field">
            <span class="zp-label">Working days</span>
            <div class="ob-chips">
                @foreach([1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'] as $n => $d)
                    <label class="ob-chip"><input type="checkbox" name="work_days[]" value="{{ $n }}" @checked(in_array($n, array_map('intval', (array) $oldDays)))><span>{{ $d }}</span></label>
                @endforeach
            </div>
        </div>

        <div class="zp-field" style="max-width:280px">
            <label class="zp-label" for="working_days_per_month">Working days per month</label>
            <input class="zp-input" type="number" min="1" max="31" id="working_days_per_month" name="working_days_per_month" value="{{ old('working_days_per_month', 26) }}">
            <div class="zp-hint">Used to work out daily rates — 26 is the common Zambian default.</div>
        </div>

        <div class="zp-field">
            <span class="zp-label">What would you like to use first?</span>
            <div class="ob-tiles industries">
                @foreach($modules as $key => $m)
                    <div class="ob-tile row">
                        <input type="checkbox" name="interested_modules[]" id="mod-{{ $key }}" value="{{ $key }}" @checked(in_array($key, (array) $oldModules))>
                        <label for="mod-{{ $key }}"><i class="bi {{ $m['icon'] }}"></i><span class="t" style="font-size:.86rem">{{ $m['label'] }}</span></label>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="zp-field" style="max-width:360px">
            <label class="zp-label" for="heard_about_us">How did you hear about us?</label>
            <select class="zp-select" id="heard_about_us" name="heard_about_us">
                <option value="">Choose one</option>
                @foreach(['Search engine', 'Facebook / Instagram', 'LinkedIn', 'A friend or colleague', 'Our accountant', 'Event or workshop', 'Other'] as $h)
                    <option @selected(old('heard_about_us') === $h)>{{ $h }}</option>
                @endforeach
            </select>
        </div>
    </section>

    {{-- ============================== STEP 5 — REVIEW ============================== --}}
    <section class="ob-panel" data-step="5" data-label="Review" data-need="Almost done">
        <h1 class="ob-title">Ready to launch?</h1>
        <p class="ob-sub">Check everything looks right. After you create the account we'll email you a link to confirm your address.</p>

        <div class="ob-review" id="review"></div>

        <label class="ob-terms">
            <input type="checkbox" name="terms" class="terms-box" id="terms" value="1" @checked(old('terms'))>
            <span>I agree to the ZamPayroll <a href="{{ route('site.terms') }}" target="_blank" style="color:inherit;font-weight:600">terms of service</a> and <a href="{{ route('site.privacy') }}" target="_blank" style="color:inherit;font-weight:600">privacy policy</a>.</span>
        </label>
        @error('terms')<div class="zp-error">{{ $message }}</div>@enderror
        <div class="zp-error" id="termsError" hidden>Please accept the terms first.</div>
    </section>

    <div class="ob-actions">
        <button type="button" class="zp-btn zp-btn-ghost" id="backBtn"><i class="bi bi-arrow-left"></i> Back</button>
        <div class="right">
            <button type="button" class="zp-btn zp-btn-link" id="skipBtn" hidden>Skip this step</button>
            <button type="button" class="zp-btn zp-btn-primary" id="nextBtn" data-next>Continue</button>
            <button type="submit" class="zp-btn zp-btn-primary" id="submitBtn" hidden><i class="bi bi-rocket-takeoff"></i> Create my workspace</button>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('wizard');
    const panels = [...document.querySelectorAll('.ob-panel')];
    const rail = [...document.querySelectorAll('.ob-step')];
    const central = @json($centralDomain);
    const urls = { email: @json(route('register.check-email')), sub: @json(route('register.check-subdomain')) };
    let step = {{ $startStep }};
    let maxReached = {{ $startStep }};
    let emailOk = null, subOk = null, subTimer, emailTimer;

    const $ = (id) => document.getElementById(id);
    const val = (n) => (form.elements[n] && form.elements[n].value || '').trim();
    const checked = (n) => form.querySelector(`input[name="${n}"]:checked`);
    const isDedicated = () => checked('employee_count_range')?.dataset.dedicated === '1';
    const firstName = () => val('name').split(' ')[0];

    function msg(el, text, ok) {
        el.innerHTML = text ? `<div class="${ok ? 'zp-ok' : 'zp-error'}">${text}</div>` : '';
    }

    /* ---------------- step navigation ---------------- */
    function show(n) {
        step = Math.max(1, Math.min(5, n));
        maxReached = Math.max(maxReached, step);
        panels.forEach(p => p.classList.toggle('active', +p.dataset.step === step));
        rail.forEach((r, i) => { r.classList.toggle('done', i + 1 < step); r.classList.toggle('current', i + 1 === step); });
        const p = panels[step - 1];
        $('stepNow').textContent = step;
        $('stepLabel').textContent = p.dataset.label;
        $('stepNeed').textContent = p.dataset.need;
        $('backBtn').style.visibility = step === 1 ? 'hidden' : 'visible';
        $('skipBtn').hidden = !(step === 3 || step === 4);
        $('nextBtn').hidden = step === 5;
        $('submitBtn').hidden = step !== 5;
        if (step === 2 && firstName()) $('companyGreeting').textContent = `Nice to meet you, ${firstName()}! Tell us about the company.`;
        if (step === 5) buildReview();
        updateMilestone();
        window.scrollTo({ top: 0, behavior: 'smooth' });
        const focusable = p.querySelector('input:not([type=hidden]):not([type=radio]):not([type=checkbox]), select');
        if (focusable && !focusable.value) setTimeout(() => focusable.focus({ preventScroll: true }), 50);
    }

    function fieldError(name, text) {
        const el = form.elements[name];
        const input = el && (el.length && !el.tagName ? el[0] : el);
        if (!input) return;
        const wrap = input.closest('.zp-field');
        wrap?.querySelector('.zp-error.js')?.remove();
        if (input.classList) input.classList.toggle('is-invalid', !!text);
        if (text && wrap) wrap.insertAdjacentHTML('beforeend', `<div class="zp-error js">${text}</div>`);
    }

    function validate(n) {
        let ok = true;
        const need = (name, text) => { const good = !!val(name); fieldError(name, good ? '' : text); ok = ok && good; };
        if (n === 1) {
            need('name', 'What should we call you?');
            need('phone', 'We need a number in case we have to reach you.');
            const email = val('email');
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { fieldError('email', 'Enter a valid email address.'); ok = false; }
            else if (emailOk === false) { ok = false; }
            else fieldError('email', '');
            const pw = val('password');
            if (pw.length < 8 || !/[a-z]/i.test(pw) || !/\d/.test(pw)) { fieldError('password', 'Use at least 8 characters with letters and numbers.'); ok = false; }
            else fieldError('password', '');
            if (pw !== val('password_confirmation')) { msg($('pwMatch'), 'Passwords don\'t match yet.'); ok = false; }
        }
        if (n === 2) {
            need('company_name', 'What is your company called?');
            const size = checked('employee_count_range');
            if (!size) { ok = false; flashGroup('employee_count_range', 'Pick your team size.'); }
            if (!checked('industry')) { ok = false; flashGroup('industry', 'Pick the closest industry.'); }
            if (isDedicated() && subOk !== true) {
                ok = false;
                if (!val('subdomain')) msg($('subMsg'), 'Pick a web address for your workspace.');
            }
        }
        if (n === 3 && val('company_email') && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val('company_email'))) {
            fieldError('company_email', 'Enter a valid email or leave it blank.'); ok = false;
        }
        return ok;
    }

    function flashGroup(name, text) {
        const first = form.querySelector(`input[name="${name}"]`);
        const field = first.closest('.zp-field');
        field.querySelector('.zp-error.js')?.remove();
        field.insertAdjacentHTML('beforeend', `<div class="zp-error js">${text}</div>`);
    }

    document.querySelectorAll('[data-next]').forEach(b => b.addEventListener('click', () => {
        if (validate(step)) show(step + 1);
    }));
    $('backBtn').addEventListener('click', () => show(step - 1));
    $('skipBtn').addEventListener('click', () => show(step + 1));
    rail.forEach(r => r.addEventListener('click', () => {
        const target = +r.dataset.goto;
        if (target <= step) return show(target);
        for (let s = step; s < target; s++) { if (!validate(s)) return show(s); }
        show(target);
    }));

    /* Enter key moves forward instead of submitting early */
    form.addEventListener('keydown', e => {
        if (e.key === 'Enter' && e.target.tagName === 'INPUT' && step < 5) {
            e.preventDefault();
            if (validate(step)) show(step + 1);
        }
    });

    /* ---------------- milestone + finish now ---------------- */
    function mandatoryDone() {
        return val('name') && val('email') && val('phone') && val('password').length >= 8
            && val('password') === val('password_confirmation') && emailOk !== false
            && val('company_name') && checked('employee_count_range') && checked('industry')
            && (!isDedicated() || subOk === true);
    }
    function updateMilestone() {
        $('milestone').classList.toggle('show', step === 2 && !!mandatoryDone());
    }
    form.addEventListener('input', updateMilestone);
    form.addEventListener('change', updateMilestone);

    document.querySelectorAll('.terms-box').forEach(box => box.addEventListener('change', () => {
        document.querySelectorAll('.terms-box').forEach(b => b.checked = box.checked);
    }));

    $('finishNow').addEventListener('click', () => {
        if (!validate(1)) return show(1);
        if (!validate(2)) return;
        if (!$('terms').checked) { $('termsErrorEarly').hidden = false; return; }
        $('finishLater').value = 1;
        submit();
    });

    form.addEventListener('submit', e => {
        e.preventDefault();
        if (!$('terms').checked) { $('termsError').hidden = false; return; }
        for (let s = 1; s <= 2; s++) { if (!validate(s)) return show(s); }
        $('finishLater').value = 0;
        submit();
    });

    function submit() {
        document.querySelectorAll('#submitBtn, #finishNow').forEach(b => { b.disabled = true; b.innerHTML = '<span>Creating your workspace…</span>'; });
        window.ZPConfetti && ZPConfetti.burst({ count: 90 });
        setTimeout(() => HTMLFormElement.prototype.submit.call(form), 450);
    }

    /* ---------------- live checks ---------------- */
    form.elements.email.addEventListener('input', () => {
        clearTimeout(emailTimer); emailOk = null; msg($('emailMsg'), '');
        const v = val('email');
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) return;
        emailTimer = setTimeout(async () => {
            try {
                const r = await fetch(`${urls.email}?email=${encodeURIComponent(v)}`, { headers: { Accept: 'application/json' } });
                const d = await r.json();
                emailOk = d.available;
                msg($('emailMsg'), d.available ? '' : d.message, false);
                form.elements.email.classList.toggle('is-invalid', !d.available);
                updateMilestone();
            } catch (e) { emailOk = null; }
        }, 450);
    });

    const subInput = $('subdomain');
    function slugify(s) { return s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 32); }
    function checkSub() {
        clearTimeout(subTimer); subOk = null;
        const v = slugify(subInput.value);
        if (subInput.value !== v && document.activeElement !== subInput) subInput.value = v;
        renderPass();
        if (!v) { msg($('subMsg'), ''); updateMilestone(); return; }
        msg($('subMsg'), 'Checking…', true);
        subTimer = setTimeout(async () => {
            try {
                const r = await fetch(`${urls.sub}?subdomain=${encodeURIComponent(v)}&company=${encodeURIComponent(val('company_name'))}`, { headers: { Accept: 'application/json' } });
                const d = await r.json();
                subOk = d.available;
                let html = d.message;
                if (!d.available && d.suggestion) html += ` How about <button type="button" class="zp-btn-link" style="border:0;background:none;padding:0" data-suggest="${d.suggestion}">${d.suggestion}</button>?`;
                msg($('subMsg'), (d.available ? '✓ ' : '') + html, d.available);
                updateMilestone();
            } catch (e) { subOk = null; }
        }, 400);
    }
    subInput.addEventListener('input', () => { subInput.dataset.touched = 1; checkSub(); });
    subInput.addEventListener('blur', () => { subInput.value = slugify(subInput.value); checkSub(); });
    $('subMsg').addEventListener('click', e => {
        const s = e.target.dataset.suggest;
        if (s) { subInput.value = s; checkSub(); }
    });

    function syncTenancy() {
        const size = checked('employee_count_range');
        $('sharedCallout').hidden = !size || isDedicated();
        $('dedicatedCallout').hidden = !size || !isDedicated();
        if (isDedicated() && !subInput.value && val('company_name')) { subInput.value = slugify(val('company_name')); }
        if (isDedicated() && subInput.value) checkSub();
        renderPass();
    }
    form.querySelectorAll('input[name="employee_count_range"]').forEach(r => r.addEventListener('change', syncTenancy));
    form.elements.company_name.addEventListener('input', () => {
        if (isDedicated() && !subInput.dataset.touched) { subInput.value = slugify(val('company_name')); checkSub(); }
    });

    /* ---------------- password helpers ---------------- */
    const pw = form.elements.password;
    pw.addEventListener('input', () => {
        const v = pw.value; let s = 0;
        if (v.length >= 8) s++;
        if (/[a-z]/i.test(v) && /\d/.test(v)) s++;
        if (/[A-Z]/.test(v) && /[a-z]/.test(v)) s++;
        if (/[^A-Za-z0-9]/.test(v) || v.length >= 12) s++;
        $('pwStrength').className = 'ob-strength' + (v ? ' s' + Math.max(1, s) : '');
        $('pwHint').textContent = !v ? 'Use letters and numbers.' : ['Too short', 'Getting there', 'Good', 'Strong', 'Excellent 💪'][s];
    });
    form.elements.password_confirmation.addEventListener('input', () => {
        const c = val('password_confirmation');
        msg($('pwMatch'), !c ? '' : (c === val('password') ? '✓ Passwords match' : 'Passwords don\'t match yet.'), c === val('password'));
    });
    document.querySelectorAll('[data-toggle-pw]').forEach(b => b.addEventListener('click', () => {
        const i = $(b.dataset.togglePw); i.type = i.type === 'password' ? 'text' : 'password';
        b.innerHTML = `<i class="bi bi-eye${i.type === 'password' ? '' : '-slash'}"></i>`;
    }));

    /* ---------------- live workspace pass ---------------- */
    function renderPass() {
        const name = val('company_name');
        $('passName').textContent = name || 'Your company';
        $('passName').classList.toggle('placeholder', !name);
        const dedicated = isDedicated();
        const badge = $('passBadge');
        badge.textContent = dedicated ? 'Dedicated workspace' : 'Shared workspace';
        badge.classList.toggle('dedicated', dedicated);
        $('passUrl').innerHTML = dedicated
            ? `<b>${(slugify(subInput.value) || 'your-company')}</b>.${central}`
            : central;
        $('passAdmin').textContent = val('name') || '—';
        $('passSize').textContent = checked('employee_count_range')?.dataset.label ? checked('employee_count_range').dataset.label + ' people' : '—';
        $('passIndustry').textContent = checked('industry')?.dataset.label || '—';
        const freq = form.elements.pay_frequency;
        $('passCycle').textContent = freq.options[freq.selectedIndex].text;
    }
    form.addEventListener('input', renderPass);
    form.addEventListener('change', renderPass);

    /* ---------------- review ---------------- */
    function buildReview() {
        const days = [...form.querySelectorAll('input[name="work_days[]"]:checked')].map(c => c.nextElementSibling.textContent).join(', ');
        const rows = [
            ['Administrator', `${val('name')}${val('position') ? ' — ' + val('position') : ''}`, 1],
            ['Email', val('email'), 1],
            ['Phone', val('phone'), 1],
            ['Company', val('company_name'), 2],
            ['Team size', (checked('employee_count_range')?.dataset.label || '—') + ' people', 2],
            ['Workspace', isDedicated() ? `${slugify(subInput.value)}.${central} (dedicated)` : `${central} (shared)`, 2],
            ['Industry', checked('industry')?.dataset.label || '—', 2],
            ['TPIN / NAPSA', [val('company_tpin'), val('napsa_employer_number')].filter(Boolean).join(' / ') || 'Add later', 3],
            ['Location', [val('city'), val('province')].filter(Boolean).join(', ') || 'Add later', 3],
            ['Pay cycle', form.elements.pay_frequency.options[form.elements.pay_frequency.selectedIndex].text + (val('pay_day') ? `, around the ${val('pay_day')}th` : ''), 4],
            ['Working days', days || '—', 4],
        ];
        const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
        $('review').innerHTML = rows.map(([k, v, s]) =>
            `<div class="ob-review-row"><span>${k}</span><span>${esc(v)} <button type="button" data-goto-step="${s}">Edit</button></span></div>`).join('');
    }
    $('review').addEventListener('click', e => { if (e.target.dataset.gotoStep) show(+e.target.dataset.gotoStep); });

    // boot
    if (val('email')) form.elements.email.dispatchEvent(new Event('input'));
    syncTenancy();
    show(step);
})();
</script>
@endpush
