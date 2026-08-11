@extends('layouts.app')

@section('content')

<div class="hrf-wrap">

    {{-- ===== TOP BAR ===== --}}
    <div class="hrf-topbar">
        <div>
            <div class="hrf-eyebrow">Personnel File</div>
            <h1 class="hrf-pagetitle">{{ $employee->first_name }} {{ $employee->last_name }}</h1>
        </div>
        <div class="hrf-topactions">
            <a class="hrf-btn hrf-btn-ghost" href="{{ route('employees.index') }}">
                <i class="bi bi-arrow-left"></i> Back
            </a>
            <a class="hrf-btn hrf-btn-solid" href="{{ route('employees.edit', $employee) }}">
                <i class="bi bi-pencil-square"></i> Edit File
            </a>
        </div>
    </div>

    {{-- ===== DOSSIER ===== --}}
    <div class="hrf-file">

        {{-- vertical index strip --}}
        <div class="hrf-tabstrip" aria-hidden="true">
            <span>ID</span>
            <span>PERSONAL</span>
            <span>EMPLOYMENT</span>
            <span>FINANCE</span>
            <span>DOCS</span>
        </div>

        {{-- ===== IDENTITY PANEL ===== --}}
        <div class="hrf-id-panel">

            <div class="hrf-badge-card">
                <div class="hrf-photo-frame">
                    @if($employee->passport_photo)
                        <img src="{{ asset('storage/' . $employee->passport_photo) }}"
                             alt="{{ $employee->first_name }} {{ $employee->last_name }}">
                    @else
                        <span class="hrf-photo-initials">
                            {{ strtoupper(substr($employee->first_name, 0, 1)) }}{{ strtoupper(substr($employee->last_name, 0, 1)) }}
                        </span>
                    @endif
                </div>
                <div class="hrf-badge-id">{{ $employee->employee_id }}</div>
                <div class="hrf-status-pill hrf-status-{{ $employee->employment_status == 'Active' ? 'active' : 'inactive' }}">
                    <span class="hrf-status-dot"></span>{{ $employee->employment_status }}
                </div>
            </div>

            <div class="hrf-panel-block">
                <div class="hrf-panel-label">Contacts</div>
                @if($employee->personal_email)
                <div class="hrf-line"><i class="bi bi-envelope"></i><span>{{ $employee->personal_email }}</span></div>
                @endif
                @if($employee->company_email)
                <div class="hrf-line"><i class="bi bi-building"></i><span>{{ $employee->company_email }}</span></div>
                @endif
                @if($employee->primary_phone)
                <div class="hrf-line"><i class="bi bi-telephone"></i><span>{{ $employee->primary_phone }}</span></div>
                @endif
                @if($employee->secondary_phone)
                <div class="hrf-line"><i class="bi bi-telephone"></i><span>{{ $employee->secondary_phone }}</span></div>
                @endif
            </div>

            @if($employee->emergency_name || $employee->emergency_phone)
            <div class="hrf-panel-block">
                <div class="hrf-panel-label">Emergency Contact</div>
                @if($employee->emergency_name)
                <div class="hrf-line"><i class="bi bi-person"></i><span>{{ $employee->emergency_name }}</span></div>
                @endif
                @if($employee->emergency_relationship)
                <div class="hrf-line"><i class="bi bi-heart"></i><span>{{ $employee->emergency_relationship }}</span></div>
                @endif
                @if($employee->emergency_phone)
                <div class="hrf-line"><i class="bi bi-telephone"></i><span>{{ $employee->emergency_phone }}</span></div>
                @endif
            </div>
            @endif

            @if($employee->next_of_kin_name || $employee->next_of_kin_phone)
            <div class="hrf-panel-block">
                <div class="hrf-panel-label">Next of Kin</div>
                @if($employee->next_of_kin_name)
                <div class="hrf-line"><i class="bi bi-person"></i><span>{{ $employee->next_of_kin_name }}</span></div>
                @endif
                @if($employee->next_of_kin_phone)
                <div class="hrf-line"><i class="bi bi-telephone"></i><span>{{ $employee->next_of_kin_phone }}</span></div>
                @endif
                @if($employee->next_of_kin_address)
                <div class="hrf-line"><i class="bi bi-geo-alt"></i><span>{{ $employee->next_of_kin_address }}</span></div>
                @endif
            </div>
            @endif

            <div class="hrf-panel-block">
                <div class="hrf-panel-label">Finance</div>
                @if($employee->bank_name)
                <div class="hrf-line"><i class="bi bi-bank"></i><span>{{ $employee->bank_name }}</span></div>
                @endif
                @if($employee->bank_account_no)
                <div class="hrf-line hrf-mono"><i class="bi bi-credit-card"></i><span>{{ $employee->bank_account_no }}</span></div>
                @endif
                @if($employee->ssn)
                <div class="hrf-line hrf-mono"><i class="bi bi-shield-check"></i><span>SSN {{ $employee->ssn }}</span></div>
                @endif
                @if($employee->nhima_no)
                <div class="hrf-line hrf-mono"><i class="bi bi-heart"></i><span>NHIMA {{ $employee->nhima_no }}</span></div>
                @endif
                @if($employee->tpin)
                <div class="hrf-line hrf-mono"><i class="bi bi-receipt"></i><span>TPIN {{ $employee->tpin }}</span></div>
                @endif
            </div>

            @if($employee->salary)
            <div class="hrf-salary-stamp">
                <div class="hrf-salary-label">Monthly Salary</div>
                <div class="hrf-salary-amount">K {{ number_format($employee->salary, 2) }}</div>
            </div>
            @endif

        </div>{{-- end identity panel --}}

        {{-- ===== RECORD SHEET ===== --}}
        <div class="hrf-record">

            <div class="hrf-record-head">
                <div class="hrf-name">
                    {{ strtoupper($employee->first_name) }}
                    @if($employee->middle_name) {{ strtoupper($employee->middle_name) }} @endif
                    {{ strtoupper($employee->last_name) }}
                </div>
                <div class="hrf-role">
                    {{ $employee->position }}
                    @if($employee->department) <span class="hrf-role-sep">/</span> {{ $employee->department }} @endif
                    @if($employee->branch) <span class="hrf-role-sep">/</span> {{ $employee->branch }} @endif
                </div>
            </div>

            <section class="hrf-section">
                <div class="hrf-section-head"><span class="hrf-section-no">01</span>Personal</div>
                <div class="hrf-grid">
                    @if($employee->date_of_birth)
                    <div class="hrf-field"><div class="hrf-field-label">Date of birth</div><div class="hrf-field-value">{{ $employee->date_of_birth->format('Y-m-d') }}</div></div>
                    @endif
                    @if($employee->age)
                    <div class="hrf-field"><div class="hrf-field-label">Age</div><div class="hrf-field-value">{{ $employee->age }}</div></div>
                    @endif
                    @if($employee->gender)
                    <div class="hrf-field"><div class="hrf-field-label">Gender</div><div class="hrf-field-value">{{ $employee->gender }}</div></div>
                    @endif
                    @if($employee->nationality)
                    <div class="hrf-field"><div class="hrf-field-label">Nationality</div><div class="hrf-field-value">{{ $employee->nationality }}</div></div>
                    @endif
                    @if($employee->nrc_no)
                    <div class="hrf-field"><div class="hrf-field-label">NRC Number</div><div class="hrf-field-value hrf-mono">{{ $employee->nrc_no }}</div></div>
                    @endif
                    @if($employee->passport_number)
                    <div class="hrf-field"><div class="hrf-field-label">Passport</div><div class="hrf-field-value hrf-mono">{{ $employee->passport_number }}</div></div>
                    @endif
                </div>
            </section>

            <section class="hrf-section">
                <div class="hrf-section-head"><span class="hrf-section-no">02</span>Employment</div>
                <div class="hrf-grid">
                    @if($employee->department)
                    <div class="hrf-field"><div class="hrf-field-label">Department</div><div class="hrf-field-value">{{ $employee->department }}</div></div>
                    @endif
                    @if($employee->branch)
                    <div class="hrf-field"><div class="hrf-field-label">Branch</div><div class="hrf-field-value">{{ $employee->branch }}</div></div>
                    @endif
                    @if($employee->position)
                    <div class="hrf-field"><div class="hrf-field-label">Position</div><div class="hrf-field-value">{{ $employee->position }}</div></div>
                    @endif
                    @if($employee->supervisor)
                    <div class="hrf-field"><div class="hrf-field-label">Supervisor</div><div class="hrf-field-value">{{ $employee->supervisor }}</div></div>
                    @endif
                </div>
            </section>

            @if($employee->probation_start || $employee->probation_end || $employee->contract_start || $employee->contract_end)
            <section class="hrf-section">
                <div class="hrf-section-head"><span class="hrf-section-no">03</span>Employment Dates</div>
                <div class="hrf-timeline">
                    @if($employee->probation_start || $employee->probation_end)
                    <div class="hrf-timeline-row">
                        <div class="hrf-timeline-label">Probation</div>
                        <div class="hrf-timeline-track">
                            <span class="hrf-timeline-date">{{ $employee->probation_start?->format('Y-m-d') ?? '—' }}</span>
                            <span class="hrf-timeline-bar"></span>
                            <span class="hrf-timeline-date">{{ $employee->probation_end?->format('Y-m-d') ?? '—' }}</span>
                        </div>
                    </div>
                    @endif
                    @if($employee->contract_start || $employee->contract_end)
                    <div class="hrf-timeline-row">
                        <div class="hrf-timeline-label">Contract</div>
                        <div class="hrf-timeline-track">
                            <span class="hrf-timeline-date">{{ $employee->contract_start?->format('Y-m-d') ?? '—' }}</span>
                            <span class="hrf-timeline-bar"></span>
                            <span class="hrf-timeline-date">{{ $employee->contract_end?->format('Y-m-d') ?? '—' }}</span>
                        </div>
                    </div>
                    @endif
                </div>
            </section>
            @endif

            <section class="hrf-section">
                <div class="hrf-section-head"><span class="hrf-section-no">04</span>Documents</div>
                <div class="hrf-docs">
                    @if(!empty($employee->uploads))
                        @foreach($employee->uploads as $doc)
                            <a href="{{ asset('storage/' . $doc['path']) }}" target="_blank" class="hrf-doc-tab">
                                <i class="bi bi-file-earmark-text"></i> {{ $doc['name'] }}
                            </a>
                        @endforeach
                    @else
                        <span class="hrf-no-docs">No documents uploaded</span>
                    @endif
                </div>
            </section>

            <div class="hrf-record-actions">
                <button onclick="window.print()" class="hrf-btn hrf-btn-ghost">
                    <i class="bi bi-printer"></i> Print File
                </button>
            </div>

        </div>{{-- end record sheet --}}

    </div>{{-- end dossier --}}

</div>


{{-- ===== STYLES ===== --}}
<style>
@import url('https://fonts.googleapis.com/css2?family=Roboto+Slab:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap');

.hrf-wrap * { box-sizing: border-box; }

.hrf-wrap {
    --paper: #FAF8F3;
    --ink: #17263A;
    --ink-soft: #58687D;
    --ink-faint: #93A0B2;
    --brass: #9C7A32;
    --brass-soft: #E9DFC7;
    --line: #E1DACB;
    --active: #2F6F4E;
    --active-bg: #EAF3EC;
    --inactive: #8A8578;
    --inactive-bg: #F0EEE7;

    font-family: 'IBM Plex Sans', -apple-system, BlinkMacSystemFont, sans-serif;
    color: var(--ink);
    background: var(--paper);
    padding: 1.5rem 0 3rem;
}

/* ---- Top bar ---- */
.hrf-topbar {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: .75rem;
    margin-bottom: 1.1rem;
    padding: 0 .1rem;
}
.hrf-eyebrow {
    font-family: 'Roboto Slab', serif;
    font-size: .68rem;
    font-weight: 600;
    letter-spacing: .14em;
    text-transform: uppercase;
    color: var(--brass);
    margin-bottom: .25rem;
}
.hrf-pagetitle { font-size: 1.35rem; font-weight: 700; margin: 0; color: var(--ink); }
.hrf-topactions { display: flex; gap: .55rem; }

.hrf-btn {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    font-size: .78rem;
    font-weight: 600;
    padding: .55rem 1.05rem;
    border-radius: 6px;
    text-decoration: none;
    cursor: pointer;
    transition: border-color .15s, color .15s, background .15s, box-shadow .15s;
}
.hrf-btn-ghost {
    border: 1px solid #D6CFBB;
    background: transparent;
    color: var(--ink-soft);
}
.hrf-btn-ghost:hover { border-color: var(--brass); color: var(--brass); }
.hrf-btn-solid {
    border: 1px solid var(--ink);
    background: var(--ink);
    color: var(--paper);
}
.hrf-btn-solid:hover { background: #23374F; border-color: #23374F; color: var(--paper); }

/* ---- Dossier shell ---- */
.hrf-file {
    display: flex;
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 4px;
    position: relative;
    box-shadow: 0 1px 0 rgba(23,38,58,.03), 0 8px 24px -18px rgba(23,38,58,.35);
    overflow: hidden;
}
.hrf-file::before {
    /* folder-tab notch */
    content: "";
    position: absolute;
    top: 0; left: 44px;
    width: 96px; height: 8px;
    background: var(--brass);
    border-radius: 0 0 3px 3px;
}

/* ---- Vertical index tab strip ---- */
.hrf-tabstrip {
    width: 26px;
    flex-shrink: 0;
    background: var(--ink);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: space-evenly;
    padding: 1.5rem 0;
}
.hrf-tabstrip span {
    writing-mode: vertical-rl;
    transform: rotate(180deg);
    font-family: 'Roboto Slab', serif;
    font-size: .58rem;
    font-weight: 600;
    letter-spacing: .12em;
    color: #7E92AC;
}

/* ---- Identity panel ---- */
.hrf-id-panel {
    width: 258px;
    flex-shrink: 0;
    background: #FCFBF8;
    border-right: 1px solid var(--line);
    padding: 1.85rem 1.4rem 1.5rem;
}

.hrf-badge-card {
    border: 1px dashed #CBC2A8;
    border-radius: 6px;
    padding: 1.1rem 1rem 1rem;
    text-align: center;
    background: #fff;
    margin-bottom: 1.4rem;
}
.hrf-photo-frame {
    width: 84px; height: 84px;
    margin: 0 auto .7rem;
    border-radius: 8px;
    border: 2px solid var(--ink);
    background: var(--brass-soft);
    display: flex; align-items: center; justify-content: center;
    overflow: hidden;
}
.hrf-photo-frame img { width: 100%; height: 100%; object-fit: cover; }
.hrf-photo-initials { font-family: 'Roboto Slab', serif; font-size: 1.4rem; font-weight: 700; color: var(--brass); }

.hrf-badge-id {
    font-family: 'IBM Plex Mono', monospace;
    font-size: .78rem;
    font-weight: 600;
    letter-spacing: .08em;
    color: var(--ink);
    margin-bottom: .55rem;
}

.hrf-status-pill {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    font-size: .68rem;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    padding: .28rem .7rem;
    border-radius: 20px;
}
.hrf-status-dot { width: 6px; height: 6px; border-radius: 50%; }
.hrf-status-active { background: var(--active-bg); color: var(--active); }
.hrf-status-active .hrf-status-dot { background: var(--active); }
.hrf-status-inactive { background: var(--inactive-bg); color: var(--inactive); }
.hrf-status-inactive .hrf-status-dot { background: var(--inactive); }

.hrf-panel-block { margin-bottom: 1.25rem; }
.hrf-panel-label {
    font-family: 'Roboto Slab', serif;
    font-size: .65rem;
    font-weight: 600;
    letter-spacing: .1em;
    text-transform: uppercase;
    color: var(--brass);
    border-bottom: 1px solid var(--brass-soft);
    padding-bottom: .3rem;
    margin-bottom: .6rem;
}
.hrf-line {
    display: flex;
    align-items: flex-start;
    gap: .5rem;
    margin-bottom: .4rem;
    color: var(--ink-soft);
    line-height: 1.4;
    font-size: .8rem;
}
.hrf-line i { font-size: .8rem; color: var(--ink-faint); margin-top: .15rem; flex-shrink: 0; }
.hrf-mono, .hrf-line.hrf-mono span { font-family: 'IBM Plex Mono', monospace; font-size: .76rem; letter-spacing: .01em; }

.hrf-salary-stamp {
    border: 2px solid var(--ink);
    border-radius: 6px;
    padding: .8rem .9rem;
    text-align: center;
    transform: rotate(-.6deg);
}
.hrf-salary-label {
    font-family: 'Roboto Slab', serif;
    font-size: .6rem;
    font-weight: 600;
    letter-spacing: .1em;
    text-transform: uppercase;
    color: var(--ink-soft);
    margin-bottom: .2rem;
}
.hrf-salary-amount { font-family: 'IBM Plex Mono', monospace; font-size: 1.05rem; font-weight: 600; color: var(--ink); }

/* ---- Record sheet ---- */
.hrf-record { flex: 1; padding: 1.85rem 2.1rem 1.6rem; min-width: 0; }

.hrf-record-head { margin-bottom: 1.4rem; padding-bottom: 1.3rem; border-bottom: 1px solid var(--line); }
.hrf-name { font-family: 'Roboto Slab', serif; font-size: 1.55rem; font-weight: 700; color: var(--ink); letter-spacing: .01em; margin-bottom: .3rem; }
.hrf-role { font-size: .85rem; color: var(--ink-soft); }
.hrf-role-sep { color: var(--ink-faint); margin: 0 .15rem; }

.hrf-section { margin-bottom: 1.6rem; }
.hrf-section:last-of-type { margin-bottom: 1.2rem; }
.hrf-section-head {
    display: flex;
    align-items: baseline;
    gap: .55rem;
    font-family: 'Roboto Slab', serif;
    font-size: .82rem;
    font-weight: 700;
    color: var(--ink);
    text-transform: uppercase;
    letter-spacing: .04em;
    margin-bottom: .85rem;
}
.hrf-section-no {
    font-family: 'IBM Plex Mono', monospace;
    font-size: .72rem;
    font-weight: 600;
    color: #fff;
    background: var(--brass);
    padding: .1rem .4rem;
    border-radius: 3px;
    letter-spacing: 0;
}

.hrf-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem 1.5rem; }
.hrf-field-label { font-size: .68rem; color: var(--ink-faint); margin-bottom: .15rem; text-transform: uppercase; letter-spacing: .04em; }
.hrf-field-value { font-size: .86rem; font-weight: 600; color: var(--ink); }

.hrf-timeline { display: flex; flex-direction: column; gap: .75rem; }
.hrf-timeline-row { display: flex; align-items: center; gap: 1rem; }
.hrf-timeline-label { width: 78px; flex-shrink: 0; font-size: .78rem; font-weight: 600; color: var(--ink-soft); }
.hrf-timeline-track { display: flex; align-items: center; gap: .6rem; flex: 1; }
.hrf-timeline-date { font-family: 'IBM Plex Mono', monospace; font-size: .74rem; color: var(--ink); white-space: nowrap; }
.hrf-timeline-bar { flex: 1; height: 2px; background: linear-gradient(90deg, var(--brass), var(--brass-soft)); border-radius: 2px; min-width: 24px; }

.hrf-docs { display: flex; flex-wrap: wrap; gap: .5rem; }
.hrf-doc-tab {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    font-size: .78rem;
    font-weight: 500;
    padding: .38rem .85rem;
    border: 1px solid var(--line);
    border-radius: 5px 5px 0 0;
    border-bottom: 2px solid var(--brass);
    color: var(--ink-soft);
    text-decoration: none;
    background: #FCFBF8;
    transition: background .15s, color .15s;
}
.hrf-doc-tab:hover { background: var(--brass-soft); color: var(--ink); text-decoration: none; }
.hrf-no-docs { font-size: .78rem; color: var(--ink-faint); font-style: italic; }

.hrf-record-actions { display: flex; justify-content: flex-end; padding-top: .6rem; border-top: 1px solid var(--line); }

/* ---- Print ---- */
@media print {
    .hrf-wrap { background: #fff; padding: 0; }
    .hrf-file { border: none; box-shadow: none; }
    .hrf-tabstrip, .hrf-topactions, .hrf-record-actions { display: none !important; }
    .hrf-id-panel { background: #FCFBF8 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}

/* ---- Responsive ---- */
@media (max-width: 860px) {
    .hrf-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 680px) {
    .hrf-file { flex-direction: column; }
    .hrf-tabstrip { display: none; }
    .hrf-id-panel { width: 100%; border-right: none; border-bottom: 1px solid var(--line); }
    .hrf-grid { grid-template-columns: 1fr; }
    .hrf-timeline-row { flex-wrap: wrap; }
}
</style>

@endsection