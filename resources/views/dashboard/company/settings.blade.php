@extends('layouts.app')

@section('content')

<div class="cos p-5">

    {{-- ===== HEADER ===== --}}
    <div class="cos-header">
        <div>
            <a href="{{ route('dashboard') }}" class="cos-back"><i class="bi bi-arrow-left"></i> Dashboard</a>
            <div class="cos-eyebrow">Settings</div>
            <h1 class="cos-title">Company Information</h1>
            <div class="cos-subtitle">Registration details, statutory numbers, and access controls for {{ $company->name }}</div>
        </div>
    </div>

    @if(session('success'))
        <div class="cos-alert"><i class="bi bi-check-circle"></i> {{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="cos-alert cos-alert-warn">
            <i class="bi bi-exclamation-triangle"></i> Please fix the following:
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('company.settings.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        {{-- ===== COMPANY PROFILE ===== --}}
        <div class="cos-card">
            <div class="cos-section-label"><span class="cos-section-no">01</span>Company Profile</div>

            <div class="cos-logo-row">
                <div class="cos-logo-preview">
                    @if($company->logo_path)
                        <img src="{{ asset('storage/' . $company->logo_path) }}" alt="{{ $company->name }} logo">
                    @else
                        <i class="bi bi-building"></i>
                    @endif
                </div>
                <div>
                    <label class="cos-field-label" for="logo">Company Logo</label>
                    <input type="file" id="logo" name="logo" class="cos-file-input" accept="image/*">
                    <div class="cos-field-hint">Shown on payslips and letterheads. PNG or JPG, up to 2MB.</div>
                </div>
            </div>

            <div class="cos-grid">
                <div class="cos-field">
                    <label class="cos-field-label" for="name">Company Name</label>
                    <input type="text" id="name" name="name" class="cos-input" required
                           value="{{ old('name', $company->name) }}">
                </div>
                <div class="cos-field">
                    <label class="cos-field-label" for="email">Company Email</label>
                    <input type="email" id="email" name="email" class="cos-input"
                           value="{{ old('email', $company->email) }}">
                </div>
                <div class="cos-field">
                    <label class="cos-field-label" for="phone">Phone Number</label>
                    <input type="text" id="phone" name="phone" class="cos-input"
                           value="{{ old('phone', $company->phone) }}">
                </div>
                <div class="cos-field">
                    <label class="cos-field-label" for="website">Website</label>
                    <input type="text" id="website" name="website" class="cos-input" placeholder="https://"
                           value="{{ old('website', $company->website) }}">
                </div>
            </div>

            <div class="cos-grid">
                <div class="cos-field cos-field-wide">
                    <label class="cos-field-label" for="address">Physical Address</label>
                    <textarea id="address" name="address" class="cos-input" rows="2">{{ old('address', $company->address) }}</textarea>
                </div>
                <div class="cos-field">
                    <label class="cos-field-label" for="city">City / Town</label>
                    <input type="text" id="city" name="city" class="cos-input"
                           value="{{ old('city', $company->city) }}">
                </div>
                <div class="cos-field">
                    <label class="cos-field-label" for="province">Province</label>
                    <select id="province" name="province" class="cos-input">
                        <option value="">Select province</option>
                        @foreach(['Central','Copperbelt','Eastern','Luapula','Lusaka','Muchinga','Northern','North-Western','Southern','Western'] as $p)
                            <option value="{{ $p }}" {{ old('province', $company->province) === $p ? 'selected' : '' }}>{{ $p }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="cos-note">
                <i class="bi bi-info-circle"></i>
                Working days and public holidays are managed under
                <a href="{{ route('attendance.settings') }}">Attendance → Working Days &amp; Holidays</a>.
            </div>
        </div>

        {{-- ===== STATUTORY REGISTRATION NUMBERS ===== --}}
        <div class="cos-card">
            <div class="cos-section-label"><span class="cos-section-no">02</span>Statutory Registration Numbers</div>
            <div class="cos-field-hint" style="margin: -.6rem 0 1.1rem;">Used on payslips and statutory returns. Leave blank if not applicable.</div>

            <div class="cos-grid">
                <div class="cos-field">
                    <label class="cos-field-label" for="tpin">ZRA TPIN</label>
                    <input type="text" id="tpin" name="tpin" class="cos-input"
                           value="{{ old('tpin', $company->tpin) }}">
                </div>
                <div class="cos-field">
                    <label class="cos-field-label" for="vat_number">VAT Registration No.</label>
                    <input type="text" id="vat_number" name="vat_number" class="cos-input"
                           value="{{ old('vat_number', $company->vat_number) }}">
                </div>
                <div class="cos-field">
                    <label class="cos-field-label" for="napsa_employer_number">NAPSA Employer No.</label>
                    <input type="text" id="napsa_employer_number" name="napsa_employer_number" class="cos-input"
                           value="{{ old('napsa_employer_number', $company->napsa_employer_number) }}">
                </div>
                <div class="cos-field">
                    <label class="cos-field-label" for="nhima_employer_number">NHIMA Employer No.</label>
                    <input type="text" id="nhima_employer_number" name="nhima_employer_number" class="cos-input"
                           value="{{ old('nhima_employer_number', $company->nhima_employer_number) }}">
                </div>
                <div class="cos-field">
                    <label class="cos-field-label" for="pacra_registration_number">PACRA Registration No.</label>
                    <input type="text" id="pacra_registration_number" name="pacra_registration_number" class="cos-input"
                           value="{{ old('pacra_registration_number', $company->pacra_registration_number) }}">
                </div>
                <div class="cos-field">
                    <label class="cos-field-label" for="workers_comp_number">Workers' Comp. (WCFCB) No.</label>
                    <input type="text" id="workers_comp_number" name="workers_comp_number" class="cos-input"
                           value="{{ old('workers_comp_number', $company->workers_comp_number) }}">
                </div>
            </div>
        </div>

        <div class="cos-actions">
            <button class="cos-btn cos-btn-primary"><i class="bi bi-save"></i> Save Changes</button>
        </div>
    </form>

    {{-- ===== ACCESS CODE ===== --}}
    <div class="cos-card">
        <div class="cos-section-label"><span class="cos-section-no">03</span>Employee Access Code</div>
        <div class="cos-field-hint" style="margin: -.6rem 0 1.1rem;">Share this code with employees so they can register against your company.</div>

        <div class="cos-code-row">
            <div class="cos-code-box">
                <span class="cos-code-value" id="cosAccessCode">{{ $company->access_code ?? '— not generated —' }}</span>
                <span class="cos-code-badge {{ $company->access_code_active ? 'active' : 'inactive' }}">
                    {{ $company->access_code_active ? 'Active' : 'Inactive' }}
                </span>
            </div>

            @if($company->access_code)
                <button type="button" class="cos-btn cos-btn-outline" onclick="cosCopyCode()">
                    <i class="bi bi-clipboard"></i> Copy
                </button>
            @endif

            <form method="POST" action="{{ route('company.settings.access-code.generate') }}">
                @csrf
                <button class="cos-btn cos-btn-outline" onclick="return confirm('Generate a new access code? The old one will stop working immediately.');">
                    <i class="bi bi-arrow-repeat"></i> {{ $company->access_code ? 'Regenerate' : 'Generate' }}
                </button>
            </form>

            @if($company->access_code && $company->access_code_active)
                <form method="POST" action="{{ route('company.settings.access-code.deactivate') }}">
                    @csrf
                    <button class="cos-btn cos-btn-warn" onclick="return confirm('Deactivate the access code?');">
                        <i class="bi bi-slash-circle"></i> Deactivate
                    </button>
                </form>
            @endif
        </div>
    </div>

</div>


{{-- ===== STYLES ===== --}}
<style>
@import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap');

.cos * { box-sizing: border-box; }

.cos {
    --paper: #ffffff;
    --ink: #00742E;
    --ink-soft: #58687D;
    --ink-faint: #93A0B2;
    --brass: #00742E;
    --brass-soft: #029508;
    --line: #E5E9F0;
    --active-bg: #EAF3EC;
    --warn: #D50115;
    --warn-bg: #F7E7E2;

    font-family: 'IBM Plex Sans', -apple-system, BlinkMacSystemFont, sans-serif;
    color: var(--ink);
    background: var(--paper);
    padding: 1.5rem 0 2.5rem;
    max-width: 860px;
    margin: 0 auto;
}

.cos-back { display: inline-flex; align-items: center; gap: .35rem; font-size: .78rem; font-weight: 600; color: var(--ink-soft); text-decoration: none; margin-bottom: .6rem; }
.cos-back:hover { color: var(--brass); }
.cos-eyebrow { font-size: .68rem; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; color: var(--brass); margin-bottom: .2rem; }
.cos-title { font-size: 1.3rem; font-weight: 700; margin: 0; color: var(--ink); }
.cos-subtitle { font-size: .8rem; color: var(--ink-faint); margin-top: .2rem; }

.cos-alert { border-radius: 6px; font-size: .84rem; padding: .7rem 1rem; margin: 1rem 0; background: var(--active-bg); color: #2F6F4E; border: 1px solid #C7E0CD; }
.cos-alert-warn { background: var(--warn-bg); color: var(--warn); border-color: #E3C6BC; }
.cos-alert-warn ul { margin: .4rem 0 0; padding-left: 1.1rem; }

.cos-card { background: #fff; border: 1px solid var(--line); border-radius: 6px; padding: 1.4rem; margin: 1rem 0; position: relative; }
.cos-card::before { content: ""; position: absolute; top: 0; left: 26px; width: 56px; height: 5px; background: var(--brass); border-radius: 0 0 3px 3px; }

.cos-section-label {
    display: flex; align-items: baseline; gap: .55rem;
    font-size: .8rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .04em; color: var(--ink);
    border-bottom: 1px solid var(--line); padding-bottom: .7rem; margin: .3rem 0 1.1rem;
}
.cos-section-no { font-family: 'IBM Plex Mono', monospace; font-size: .68rem; font-weight: 600; color: #fff; background: var(--brass); padding: .05rem .4rem; border-radius: 3px; }

/* ---- Logo ---- */
.cos-logo-row { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.3rem; }
.cos-logo-preview {
    width: 64px; height: 64px; border-radius: 8px; border: 1px dashed var(--line);
    display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0;
    color: var(--ink-faint); font-size: 1.4rem; background: #FAFBFC;
}
.cos-logo-preview img { width: 100%; height: 100%; object-fit: cover; }
.cos-file-input { font-size: .78rem; display: block; margin-top: .3rem; }

/* ---- Fields ---- */
.cos-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
.cos-field-wide { grid-column: 1 / -1; }
.cos-field-label { display: block; font-size: .74rem; font-weight: 600; color: var(--ink-soft); margin-bottom: .3rem; }
.cos-input {
    width: 100%; font-size: .84rem; padding: .55rem .7rem;
    border: 1px solid var(--line); border-radius: 5px; outline: none; color: var(--ink); font-family: inherit;
}
.cos-input:focus { border-color: var(--brass); }
.cos-field-hint { font-size: .72rem; color: var(--ink-faint); margin-top: .3rem; }

.cos-note { font-size: .76rem; color: var(--ink-faint); background: #FAFBFC; border: 1px solid var(--line); border-radius: 6px; padding: .6rem .8rem; margin-top: .3rem; }
.cos-note a { color: var(--brass); font-weight: 600; text-decoration: none; }
.cos-note a:hover { text-decoration: underline; }

.cos-actions { display: flex; justify-content: flex-end; margin: 1.2rem 0; }

/* ---- Buttons ---- */
.cos-btn { display: inline-flex; align-items: center; gap: .4rem; font-size: .8rem; font-weight: 600; padding: .55rem 1rem; border-radius: 6px; border: 1px solid transparent; cursor: pointer; }
.cos-btn-primary { background: var(--ink); color: #fff; border-color: var(--ink); }
.cos-btn-primary:hover { background: #23374F; }
.cos-btn-outline { background: #fff; color: var(--ink-soft); border-color: var(--line); }
.cos-btn-outline:hover { border-color: var(--brass); color: var(--brass); }
.cos-btn-warn { background: #fff; color: var(--warn); border-color: #E3C6BC; }
.cos-btn-warn:hover { background: var(--warn-bg); }

/* ---- Access code ---- */
.cos-code-row { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; }
.cos-code-box {
    display: flex; align-items: center; gap: .6rem;
    background: #FAFBFC; border: 1px solid var(--line); border-radius: 6px;
    padding: .6rem 1rem; flex: 1 1 220px;
}
.cos-code-value { font-family: 'IBM Plex Mono', monospace; font-size: .95rem; font-weight: 600; letter-spacing: .06em; color: var(--ink); }
.cos-code-badge { font-size: .64rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; padding: .2rem .55rem; border-radius: 20px; }
.cos-code-badge.active { background: var(--active-bg); color: #2F6F4E; }
.cos-code-badge.inactive { background: var(--warn-bg); color: var(--warn); }
.cos-code-row form { margin: 0; }

@media (max-width: 640px) {
    .cos-grid { grid-template-columns: 1fr; }
}
</style>

<script>
function cosCopyCode() {
    const text = document.getElementById('cosAccessCode').innerText;
    navigator.clipboard.writeText(text).then(() => {
        const btn = event.currentTarget;
        const original = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check2"></i> Copied';
        setTimeout(() => { btn.innerHTML = original; }, 1500);
    });
}
</script>

@endsection
