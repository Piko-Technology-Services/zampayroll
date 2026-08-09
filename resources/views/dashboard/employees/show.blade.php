@extends('layouts.app')

@section('content')

<div class="empv">

    {{-- ===== HEADER ===== --}}
    <div class="empv-card empv-header-card">
        <div class="empv-header-row">
            <div>
                <h1 class="empv-title">Employee Information</h1>
                <div class="empv-subtitle">
                    Edit, print, or view details of {{ $employee->first_name }} {{ $employee->last_name }}
                </div>
            </div>

            <div class="empv-header-actions">
                <a class="empv-btn empv-btn-outline" href="{{ route('employees.edit', $employee) }}">
                    <i class="bi bi-pencil-square"></i> Edit
                </a>
                <a class="empv-btn empv-btn-outline" href="{{ route('employees.index') }}">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>

    {{-- ===== PROFILE CARD ===== --}}
    <div class="empv-profile-card">

        {{-- ===== LEFT SIDEBAR ===== --}}
        <div class="empv-left">

            <div class="empv-photo-wrap">
                <div class="empv-photo-circle">
                    @if($employee->passport_photo)
                        <img src="{{ asset('storage/' . $employee->passport_photo) }}"
                             alt="{{ $employee->first_name }} {{ $employee->last_name }}">
                    @else
                        <span class="empv-photo-initials">
                            {{ strtoupper(substr($employee->first_name, 0, 1)) }}{{ strtoupper(substr($employee->last_name, 0, 1)) }}
                        </span>
                    @endif
                </div>
            </div>

            <div class="empv-badges">
                <span class="empv-badge empv-badge-id">{{ $employee->employee_id }}</span>
                <span class="empv-badge empv-badge-{{ $employee->employment_status == 'Active' ? 'active' : 'inactive' }}">
                    {{ $employee->employment_status }}
                </span>
            </div>

            <div class="empv-section-label">Contacts</div>

            @if($employee->personal_email)
            <div class="empv-info-row"><i class="bi bi-envelope"></i><span>{{ $employee->personal_email }}</span></div>
            @endif
            @if($employee->company_email)
            <div class="empv-info-row"><i class="bi bi-building"></i><span>{{ $employee->company_email }}</span></div>
            @endif
            @if($employee->primary_phone)
            <div class="empv-info-row"><i class="bi bi-telephone"></i><span>{{ $employee->primary_phone }}</span></div>
            @endif
            @if($employee->secondary_phone)
            <div class="empv-info-row"><i class="bi bi-telephone"></i><span>{{ $employee->secondary_phone }}</span></div>
            @endif

            @if($employee->emergency_name || $employee->emergency_phone)
            <div class="empv-section-label">Emergency</div>
            @if($employee->emergency_name)
            <div class="empv-info-row"><i class="bi bi-person"></i><span>{{ $employee->emergency_name }}</span></div>
            @endif
            @if($employee->emergency_relationship)
            <div class="empv-info-row"><i class="bi bi-heart"></i><span>{{ $employee->emergency_relationship }}</span></div>
            @endif
            @if($employee->emergency_phone)
            <div class="empv-info-row"><i class="bi bi-telephone"></i><span>{{ $employee->emergency_phone }}</span></div>
            @endif
            @endif

            @if($employee->next_of_kin_name || $employee->next_of_kin_phone)
            <div class="empv-section-label">Next of Kin</div>
            @if($employee->next_of_kin_name)
            <div class="empv-info-row"><i class="bi bi-person"></i><span>{{ $employee->next_of_kin_name }}</span></div>
            @endif
            @if($employee->next_of_kin_phone)
            <div class="empv-info-row"><i class="bi bi-telephone"></i><span>{{ $employee->next_of_kin_phone }}</span></div>
            @endif
            @if($employee->next_of_kin_address)
            <div class="empv-info-row"><i class="bi bi-geo-alt"></i><span>{{ $employee->next_of_kin_address }}</span></div>
            @endif
            @endif

            <div class="empv-section-label">Finance</div>
            @if($employee->bank_name)
            <div class="empv-info-row"><i class="bi bi-bank"></i><span>{{ $employee->bank_name }}</span></div>
            @endif
            @if($employee->bank_account_number)
            <div class="empv-info-row"><i class="bi bi-credit-card"></i><span>{{ $employee->bank_account_number }}</span></div>
            @endif
            @if($employee->nssf_number)
            <div class="empv-info-row"><i class="bi bi-shield-check"></i><span>NSSF: {{ $employee->nssf_number }}</span></div>
            @endif
            @if($employee->tin_number)
            <div class="empv-info-row"><i class="bi bi-receipt"></i><span>TIN: {{ $employee->tin_number }}</span></div>
            @endif

            @if($employee->salary)
            <div class="empv-salary">K {{ number_format($employee->salary, 2) }} <span>/ month</span></div>
            @endif

        </div>{{-- end left --}}

        {{-- ===== RIGHT MAIN ===== --}}
        <div class="empv-right">

            <div class="empv-name">
                {{ strtoupper($employee->first_name) }}
                @if($employee->middle_name) {{ strtoupper($employee->middle_name) }} @endif
                <span>{{ strtoupper($employee->last_name) }}</span>
            </div>

            <div class="empv-role">
                {{ $employee->position }}
                @if($employee->department) &nbsp;•&nbsp; {{ $employee->department }} @endif
                @if($employee->branch) &nbsp;•&nbsp; {{ $employee->branch }} @endif
            </div>

            <hr class="empv-divider">

            <div class="empv-section-label-right">Personal</div>
            <div class="empv-grid-2">
                @if($employee->date_of_birth)
                <div class="empv-entry"><div class="empv-entry-label">Date of birth</div><div class="empv-entry-value">{{ $employee->date_of_birth->format('Y-m-d') }}</div></div>
                @endif
                @if($employee->age)
                <div class="empv-entry"><div class="empv-entry-label">Age</div><div class="empv-entry-value">{{ $employee->age }}</div></div>
                @endif
                @if($employee->gender)
                <div class="empv-entry"><div class="empv-entry-label">Gender</div><div class="empv-entry-value">{{ $employee->gender }}</div></div>
                @endif
                @if($employee->nationality)
                <div class="empv-entry"><div class="empv-entry-label">Nationality</div><div class="empv-entry-value">{{ $employee->nationality }}</div></div>
                @endif
                @if($employee->national_id_number)
                <div class="empv-entry"><div class="empv-entry-label">National ID</div><div class="empv-entry-value">{{ $employee->national_id_number }}</div></div>
                @endif
                @if($employee->passport_number)
                <div class="empv-entry"><div class="empv-entry-label">Passport</div><div class="empv-entry-value">{{ $employee->passport_number }}</div></div>
                @endif
            </div>

            <div class="empv-section-label-right">Job Details</div>
            <div class="empv-grid-2">
                @if($employee->department)
                <div class="empv-entry"><div class="empv-entry-label">Department</div><div class="empv-entry-value">{{ $employee->department }}</div></div>
                @endif
                @if($employee->branch)
                <div class="empv-entry"><div class="empv-entry-label">Branch</div><div class="empv-entry-value">{{ $employee->branch }}</div></div>
                @endif
                @if($employee->position)
                <div class="empv-entry"><div class="empv-entry-label">Position</div><div class="empv-entry-value">{{ $employee->position }}</div></div>
                @endif
                @if($employee->supervisor)
                <div class="empv-entry"><div class="empv-entry-label">Supervisor</div><div class="empv-entry-value">{{ $employee->supervisor }}</div></div>
                @endif
            </div>

            <div class="empv-section-label-right">Employment Dates</div>
            <div class="empv-grid-2">
                @if($employee->probation_start || $employee->probation_end)
                <div class="empv-entry">
                    <div class="empv-entry-label">Probation period</div>
                    <div class="empv-entry-value">
                        {{ $employee->probation_start?->format('Y-m-d') }}
                        @if($employee->probation_end) &rarr; {{ $employee->probation_end->format('Y-m-d') }} @endif
                    </div>
                </div>
                @endif
                @if($employee->contract_start || $employee->contract_end)
                <div class="empv-entry">
                    <div class="empv-entry-label">Contract period</div>
                    <div class="empv-entry-value">
                        {{ $employee->contract_start?->format('Y-m-d') }}
                        @if($employee->contract_end) &rarr; {{ $employee->contract_end->format('Y-m-d') }} @endif
                    </div>
                </div>
                @endif
            </div>

            <hr class="empv-divider">

            <div class="empv-section-label-right">Documents</div>
            <div class="empv-docs">
                @if(!empty($employee->uploads))
                    @foreach($employee->uploads as $doc)
                        <a href="{{ asset('storage/' . $doc['path']) }}" target="_blank" class="empv-doc-btn">
                            <i class="bi bi-file-earmark-text"></i> {{ $doc['name'] }}
                        </a>
                    @endforeach
                @else
                    <span class="empv-no-docs">No documents uploaded</span>
                @endif
            </div>

            <hr class="empv-divider">

            <div class="empv-actions">
                <button onclick="window.print()" class="empv-btn empv-btn-outline">
                    <i class="bi bi-printer"></i> Print
                </button>
            </div>

        </div>{{-- end right --}}

    </div>{{-- end profile card --}}

</div>


{{-- ===== STYLES ===== --}}
<style>
.empv * { box-sizing: border-box; }

.empv {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
    color: #0F172A;
    background: #F8FAFC;
    padding: 1.5rem 0 2.5rem;
}

.empv-card {
    background: #fff;
    border: 1px solid #E9EDF2;
    border-radius: 16px;
}

/* ---- Header ---- */
.empv-header-card { padding: 1.1rem 1.4rem; margin-bottom: 1rem; }
.empv-header-row { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .75rem; }
.empv-title { font-size: 1.1rem; font-weight: 700; margin: 0; color: #0F172A; }
.empv-subtitle { font-size: .8rem; color: #94A3B8; margin-top: .15rem; }
.empv-header-actions { display: flex; gap: .5rem; }

.empv-btn {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    font-size: .8rem;
    font-weight: 600;
    padding: .5rem 1rem;
    border-radius: 10px;
    text-decoration: none;
    border: 1px solid #E5E9F0;
    cursor: pointer;
    background: #fff;
    color: #475569;
    transition: border-color .15s, color .15s, background .15s;
}
.empv-btn-outline:hover { border-color: #A7F3D0; color: #00742D; background: #ECFDF5; }

/* ---- Profile shell ---- */
.empv-profile-card {
    display: flex;
    gap: 0;
    background: #fff;
    border: 1px solid #E9EDF2;
    border-radius: 16px;
    overflow: hidden;
    min-height: 640px;
    font-size: .85rem;
}

/* ---- Left sidebar ---- */
.empv-left {
    width: 250px;
    flex-shrink: 0;
    background: #F8FAFC;
    padding: 1.75rem 1.35rem;
    border-right: 1px solid #E9EDF2;
}

.empv-photo-wrap { display: flex; justify-content: center; margin-bottom: 1rem; }
.empv-photo-circle {
    width: 92px; height: 92px;
    border-radius: 50%;
    border: 3px solid #00742D;
    background: #ECFDF5;
    display: flex; align-items: center; justify-content: center;
    overflow: hidden;
}
.empv-photo-circle img { width: 100%; height: 100%; object-fit: cover; }
.empv-photo-initials { font-size: 1.6rem; font-weight: 700; color: #00742D; }

.empv-badges { display: flex; justify-content: center; flex-wrap: wrap; gap: .35rem; margin-bottom: 1.15rem; }
.empv-badge { display: inline-block; font-size: .7rem; font-weight: 600; padding: .2rem .7rem; border-radius: 20px; }
.empv-badge-id       { background: #EEF2FF; color: #4338CA; }
.empv-badge-active   { background: #ECFDF5; color: #059669; }
.empv-badge-inactive { background: #F1F5F9; color: #64748B; }

.empv-section-label {
    font-size: .68rem;
    font-weight: 700;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: #00742D;
    border-bottom: 1px solid #A7F3D0;
    padding-bottom: .3rem;
    margin: 1.15rem 0 .65rem;
}

.empv-info-row {
    display: flex;
    align-items: flex-start;
    gap: .5rem;
    margin-bottom: .45rem;
    color: #334155;
    line-height: 1.4;
    font-size: .82rem;
}
.empv-info-row i { font-size: .85rem; color: #94A3B8; margin-top: .1rem; flex-shrink: 0; }

.empv-salary { margin-top: .7rem; font-size: .95rem; font-weight: 800; color: #00742D; }
.empv-salary span { font-size: .72rem; font-weight: 400; color: #94A3B8; }

/* ---- Right main ---- */
.empv-right { flex: 1; padding: 1.75rem 2rem; min-width: 0; }

.empv-name { font-size: 1.4rem; font-weight: 800; color: #0F172A; letter-spacing: .01em; line-height: 1.2; margin-bottom: .2rem; }
.empv-name span { color: #00742D; }
.empv-role { font-size: .84rem; color: #64748B; margin-bottom: .3rem; }

.empv-divider { border: none; border-top: 1px solid #F1F5F9; margin: 1rem 0; }

.empv-section-label-right {
    font-size: .68rem;
    font-weight: 700;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: #00742D;
    border-bottom: 1px solid #A7F3D0;
    padding-bottom: .3rem;
    margin: 1rem 0 .75rem;
}

.empv-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: .65rem 1.25rem; margin-bottom: .4rem; }
.empv-entry-label { font-size: .7rem; color: #94A3B8; margin-bottom: .1rem; }
.empv-entry-value { font-size: .84rem; font-weight: 600; color: #0F172A; }

.empv-docs { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .5rem; }
.empv-doc-btn {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    font-size: .78rem;
    padding: .3rem .8rem;
    border: 1px solid #E5E9F0;
    border-radius: 8px;
    color: #334155;
    text-decoration: none;
    background: #fff;
    transition: background .15s, border-color .15s, color .15s;
}
.empv-doc-btn:hover { background: #ECFDF5; color: #00742D; border-color: #A7F3D0; text-decoration: none; }
.empv-no-docs { font-size: .78rem; color: #B4BECC; }

.empv-actions { display: flex; justify-content: flex-end; gap: .5rem; }

/* ---- Print ---- */
@media print {
    .empv { background: #fff; padding: 0; }
    .empv-profile-card { border: none; }
    .empv-actions, .empv-header-card { display: none !important; }
    .empv-left { background: #F8FAFC !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}

/* ---- Responsive ---- */
@media (max-width: 640px) {
    .empv-profile-card { flex-direction: column; }
    .empv-left { width: 100%; border-right: none; border-bottom: 1px solid #E9EDF2; }
    .empv-grid-2 { grid-template-columns: 1fr; }
}
</style>

@endsection