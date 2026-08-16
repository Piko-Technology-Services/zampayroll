@extends('layouts.app')

@section('content')

<div class="empf">

    {{-- ===== HEADER ===== --}}
    <div class="empf-header">
        <div class="empf-header-copy">
            <span class="empf-icon"><i class="bi bi-pencil-square"></i></span>
            <div>
                <div class="empf-eyebrow">HR Management</div>
                <h1 class="empf-title">Edit Employee</h1>
                <div class="empf-subtitle">Update employee information</div>
            </div>
        </div>

        <a class="empf-btn empf-btn-outline" href="{{ route('employees.index') }}">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>

    <div class="empf-layout">

        {{-- ===== FORM COLUMN ===== --}}
        <div class="empf-main">

            <form method="POST"
                  action="{{ route('employees.update', $employee->id) }}"
                  enctype="multipart/form-data"
                  novalidate>

                @csrf
                @method('PUT')

                {{-- ================= PERSONAL ================= --}}
                <div class="empf-card">
                    <div class="empf-section-label">Personal Information</div>

                    <div class="empf-grid empf-grid-3">
                        <div class="empf-field">
                            <label class="empf-label">First Name</label>
                            <input class="empf-input" name="first_name"
                                   value="{{ old('first_name', $employee->first_name) }}" required>
                        </div>
                        <div class="empf-field">
                            <label class="empf-label">Middle Name</label>
                            <input class="empf-input" name="middle_name"
                                   value="{{ old('middle_name', $employee->middle_name) }}">
                        </div>
                        <div class="empf-field">
                            <label class="empf-label">Last Name</label>
                            <input class="empf-input" name="last_name"
                                   value="{{ old('last_name', $employee->last_name) }}" required>
                        </div>

                        <div class="empf-field">
                            <label class="empf-label">Date of Birth</label>
                            <input type="date" class="empf-input" name="date_of_birth"
                                   value="{{ old('date_of_birth', $employee->date_of_birth?->format('Y-m-d')) }}">
                        </div>
                        <div class="empf-field">
                            <label class="empf-label">Gender</label>
                            <select class="empf-input" name="gender">
                                <option value="">Select</option>
                                <option value="Male" {{ $employee->gender == 'Male' ? 'selected' : '' }}>Male</option>
                                <option value="Female" {{ $employee->gender == 'Female' ? 'selected' : '' }}>Female</option>
                            </select>
                        </div>
                        <div class="empf-field">
                            <label class="empf-label">Nationality</label>
                            <input class="empf-input" name="nationality"
                                   value="{{ old('nationality', $employee->nationality) }}">
                        </div>

                        <div class="empf-field empf-span-2">
                            <label class="empf-label">NRC Number</label>
                            <input class="empf-input" name="nrc_no"
                                   value="{{ old('nrc_no', $employee->nrc_no) }}">
                        </div>
                        <div class="empf-field">
                            <label class="empf-label">Passport Number</label>
                            <input class="empf-input" name="passport_number"
                                   value="{{ old('passport_number', $employee->passport_number) }}">
                        </div>
                    </div>
                </div>

                {{-- ================= CONTACT ================= --}}
                <div class="empf-card">
                    <div class="empf-section-label">Contact Information</div>

                    <div class="empf-grid empf-grid-2">
                        <div class="empf-field">
                            <label class="empf-label">Personal Email</label>
                            <input class="empf-input" name="personal_email"
                                   value="{{ old('personal_email', $employee->personal_email) }}">
                        </div>
                        <div class="empf-field">
                            <label class="empf-label">Company Email</label>
                            <input class="empf-input" name="company_email"
                                   value="{{ old('company_email', $employee->company_email) }}">
                        </div>
                        <div class="empf-field">
                            <label class="empf-label">Primary Phone</label>
                            <input class="empf-input" name="primary_phone"
                                   value="{{ old('primary_phone', $employee->primary_phone) }}">
                        </div>
                        <div class="empf-field">
                            <label class="empf-label">Secondary Phone</label>
                            <input class="empf-input" name="secondary_phone"
                                   value="{{ old('secondary_phone', $employee->secondary_phone) }}">
                        </div>
                    </div>
                </div>

                {{-- ================= JOB ================= --}}
                <div class="empf-card">
                    <div class="empf-section-label">Job Information</div>

                    <div class="empf-grid empf-grid-2">
                        <div class="empf-field">
                            <label class="empf-label">Position</label>
                            <input class="empf-input" name="position"
                                   value="{{ old('position', $employee->position) }}" required>
                        </div>
                        <div class="empf-field">
                            <label class="empf-label">Department</label>
                            <input class="empf-input" name="department"
                                   value="{{ old('department', $employee->department) }}">
                        </div>
                        <div class="empf-field">
                            <label class="empf-label">Branch</label>
                            <input class="empf-input" name="branch"
                                   value="{{ old('branch', $employee->branch) }}">
                        </div>
                        <div class="empf-field">
                            <label class="empf-label">Supervisor</label>
                            <input class="empf-input" name="supervisor"
                                   value="{{ old('supervisor', $employee->supervisor) }}">
                        </div>
                        <div class="empf-field">
                            <label class="empf-label">Employment Status</label>
                            <select class="empf-input" name="employment_status">
                                <option value="Active" {{ $employee->employment_status == 'Active' ? 'selected' : '' }}>Active</option>
                                <option value="Exited" {{ $employee->employment_status == 'Exited' ? 'selected' : '' }}>Exited</option>
                                <option value="Suspended" {{ $employee->employment_status == 'Suspended' ? 'selected' : '' }}>Suspended</option>
                            </select>
                        </div>
                    </div>
                </div>

                {{-- ================= DATES ================= --}}
                <div class="empf-card">
                    <div class="empf-section-label">Employment Dates</div>

                    <div class="empf-grid empf-grid-2">
                        <div class="empf-field">
                            <label class="empf-label">Probation Start</label>
                            <input type="date" class="empf-input" name="probation_start"
                                   value="{{ old('probation_start', optional($employee->probation_start)->format('Y-m-d')) }}">
                        </div>
                        <div class="empf-field">
                            <label class="empf-label">Probation End</label>
                            <input type="date" class="empf-input" name="probation_end"
                                   value="{{ old('probation_end', optional($employee->probation_end)->format('Y-m-d')) }}">
                        </div>
                        <div class="empf-field">
                            <label class="empf-label">Contract Start</label>
                            <input type="date" class="empf-input" name="contract_start"
                                   value="{{ old('contract_start', optional($employee->contract_start)->format('Y-m-d')) }}">
                        </div>
                        <div class="empf-field">
                            <label class="empf-label">Contract End</label>
                            <input type="date" class="empf-input" name="contract_end"
                                   value="{{ old('contract_end', optional($employee->contract_end)->format('Y-m-d')) }}">
                        </div>
                    </div>
                </div>

                {{-- ================= EMERGENCY ================= --}}
                <div class="empf-card">
                    <div class="empf-section-label">Emergency Contact</div>

                    <div class="empf-grid empf-grid-3">
                        <div class="empf-field">
                            <label class="empf-label">Name</label>
                            <input class="empf-input" name="emergency_name"
                                   value="{{ old('emergency_name', $employee->emergency_name) }}">
                        </div>
                        <div class="empf-field">
                            <label class="empf-label">Relationship</label>
                            <input class="empf-input" name="emergency_relationship"
                                   value="{{ old('emergency_relationship', $employee->emergency_relationship) }}">
                        </div>
                        <div class="empf-field">
                            <label class="empf-label">Phone</label>
                            <input class="empf-input" name="emergency_phone"
                                   value="{{ old('emergency_phone', $employee->emergency_phone) }}">
                        </div>
                    </div>
                </div>

                {{-- ================= NEXT OF KIN ================= --}}
                <div class="empf-card">
                    <div class="empf-section-label">Next of Kin</div>

                    <div class="empf-grid empf-grid-3">
                        <div class="empf-field">
                            <label class="empf-label">Name</label>
                            <input class="empf-input" name="next_of_kin_name"
                                   value="{{ old('next_of_kin_name', $employee->next_of_kin_name) }}">
                        </div>
                        <div class="empf-field">
                            <label class="empf-label">Phone</label>
                            <input class="empf-input" name="next_of_kin_phone"
                                   value="{{ old('next_of_kin_phone', $employee->next_of_kin_phone) }}">
                        </div>
                        <div class="empf-field">
                            <label class="empf-label">Address</label>
                            <input class="empf-input" name="next_of_kin_address"
                                   value="{{ old('next_of_kin_address', $employee->next_of_kin_address) }}">
                        </div>
                    </div>
                </div>

                {{-- ================= FINANCE ================= --}}
                <div class="empf-card">
                    <div class="empf-section-label">Finance</div>

                    <div class="empf-grid empf-grid-3">
                        <div class="empf-field">
                            <label class="empf-label">Bank</label>
                            <input class="empf-input" name="bank_name"
                                   value="{{ old('bank_name', $employee->bank_name) }}">
                        </div>
                        <div class="empf-field">
                            <label class="empf-label">Account Number</label>
                            <input class="empf-input" name="bank_account_no"
                                   value="{{ old('bank_account_no', $employee->bank_account_no) }}">
                        </div>
                        <div class="empf-field">
                            <label class="empf-label">Net Salary</label>
                            <input class="empf-input" name="net_salary"
                                   value="{{ old('net_salary', $employee->net_salary) }}">
                        </div>
                        <div class="empf-field">
                            <label class="empf-label">SSN NAPSA</label>
                            <input class="empf-input" name="ssn"
                                   value="{{ old('ssn', $employee->ssn) }}">
                        </div>

                        <div class="empf-field">
                            <label class="empf-label">NHIMA</label>
                            <input class="empf-input" name="nhima_no"
                                   value="{{ old('nhima_no', $employee->nhima_no) }}">
                        </div>

                        <div class="empf-field">
                            <label class="empf-label">TPIN</label>
                            <input class="empf-input" name="tpin"
                                   value="{{ old('tpin', $employee->tpin) }}">
                        </div>
                    </div>
                </div>

                {{-- ================= LEAVE ENTITLEMENT ================= --}}
                <div class="empf-card">
                    <div class="empf-section-label">Leave Entitlement</div>

                    <div class="empf-grid empf-grid-2">
                        <div class="empf-field">
                            <label class="empf-label">Leave Days Entitled</label>
                            <input type="number" step="0.5" min="0" class="empf-input" name="leave_days_entitled"
                                value="{{ old('leave_days_entitled', $employee->leave_days_entitled) }}">
                            <small class="empf-hint">Full annual entitlement — prorate for mid-year starts.</small>
                        </div>
                        <div class="empf-field">
                            <label class="empf-label">Leave Days Balance</label>
                            <input type="number" step="0.5" min="0" class="empf-input" name="leave_days_balance"
                                value="{{ old('leave_days_balance', $employee->leave_days_balance) }}">
                            <small class="empf-hint">Days currently available to the employee.</small>
                        </div>
                    </div>
                </div>

                {{-- ================= UPLOADS ================= --}}
                <div class="empf-card">
                    <div class="empf-section-label">Uploads</div>

                    <div class="empf-grid empf-grid-2">

                        {{-- PASSPORT PHOTO --}}
                        <div class="empf-field">
                            <label class="empf-label">Passport Photo</label>

                            <div class="empf-photo-row">
                                <img src="{{ $employee->passport_photo
                                        ? asset('storage/' . $employee->passport_photo)
                                        : asset('assets/images/avatar/avatar.jpg') }}"
                                     class="empf-photo-preview">
                                <div class="empf-photo-note">
                                    Current photo<br>
                                    <span>Upload new to replace</span>
                                </div>
                            </div>

                            <input type="file" class="empf-input mt-2" name="passport_photo">
                        </div>

                        {{-- DOCUMENTS --}}
                        <div class="empf-field">
                            <label class="empf-label">Documents</label>

                            <input type="file" class="empf-input mb-2" name="documents[]" multiple>

                            @if(!empty($employee->uploads) && is_array($employee->uploads))
                                <div class="empf-doc-list">
                                    <div class="empf-doc-list-label">Existing Documents</div>
                                    @foreach($employee->uploads as $doc)
                                        <a href="{{ asset('storage/' . $doc['path']) }}"
                                           target="_blank"
                                           class="empf-doc-link">
                                            <i class="bi bi-file-earmark-text"></i> {{ $doc['name'] }}
                                        </a>
                                    @endforeach
                                </div>
                            @else
                                <p class="empf-doc-empty">No documents uploaded</p>
                            @endif
                        </div>

                    </div>
                </div>

                {{-- ================= ACTIONS ================= --}}
                <div class="empf-actions">
                    <a href="{{ route('employees.index') }}" class="empf-btn empf-btn-outline">Cancel</a>
                    <button class="empf-btn empf-btn-primary">
                        <i class="bi bi-save"></i> Update Employee
                    </button>
                </div>

            </form>

        </div>

        {{-- ===== SIDE SUMMARY ===== --}}
        <div class="empf-side">

            <div class="empf-card empf-summary-card">

                <div class="empf-summary-head">
                    <img src="{{ $employee->passport_photo
                            ? asset('storage/' . $employee->passport_photo)
                            : asset('assets/images/avatar/avatar.jpg') }}"
                         class="empf-summary-avatar">
                    <div>
                        <div class="empf-summary-name">{{ $employee->first_name }} {{ $employee->last_name }}</div>
                        <div class="empf-summary-pos">{{ $employee->position }}</div>
                        <span class="empf-status-badge empf-status-{{ strtolower($employee->employment_status) }}">
                            {{ $employee->employment_status }}
                        </span>
                    </div>
                </div>

                <div class="empf-summary-section">
                    <div class="empf-summary-row"><span>Employee ID</span><strong>{{ $employee->employee_id }}</strong></div>
                    <div class="empf-summary-row"><span>Department</span><strong>{{ $employee->department ?? '—' }}</strong></div>
                    <div class="empf-summary-row"><span>Supervisor</span><strong>{{ $employee->supervisor ?? '—' }}</strong></div>
                    <div class="empf-summary-row"><span>Age</span><strong>{{ $employee->age ?? '—' }}</strong></div>
                </div>

                <div class="empf-summary-label">Contact</div>
                <div class="empf-summary-section">
                    <div class="empf-summary-row"><span>Email</span><strong class="text-truncate" style="max-width:150px;">{{ $employee->personal_email ?? '—' }}</strong></div>
                    <div class="empf-summary-row"><span>Phone</span><strong>{{ $employee->primary_phone ?? '—' }}</strong></div>
                </div>

                <div class="empf-summary-label">Contract</div>
                <div class="empf-summary-section">
                    <div class="empf-summary-row"><span>Start</span><strong>{{ $employee->contract_start ?? '—' }}</strong></div>
                    <div class="empf-summary-row"><span>End</span><strong>{{ $employee->contract_end ?? '—' }}</strong></div>
                    <div class="empf-summary-row"><span>Probation</span><strong>{{ $employee->probation_start ?? '—' }} → {{ $employee->probation_end ?? '—' }}</strong></div>
                </div>

                <div class="empf-summary-label">Finance</div>
                <div class="empf-summary-section">
                    <div class="empf-summary-row"><span>Net Salary</span><strong>{{ number_format($employee->net_salary ?? 0, 2) }}</strong></div>
                    <div class="empf-summary-row"><span>Bank</span><strong>{{ $employee->bank_name ?? '—' }}</strong></div>
                    <div class="empf-summary-row"><span>Leave Balance</span><strong>{{ $employee->leave_days_balance ?? '—' }} / {{ $employee->leave_days_entitled ?? '—' }}</strong></div>
                </div>

            </div>

        </div>

    </div>

</div>


{{-- ===== STYLES ===== --}}
<style>
.empf * { box-sizing: border-box; }

.empf {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
    color: #0F172A;
    background: #F8FAFC;
    padding: 1.5rem 0 2.5rem;
}

/* ---- Header ---- */
.empf-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: .75rem;
}
.empf-header-copy { display: flex; align-items: flex-start; gap: .9rem; }
.empf-icon {
    width: 44px; height: 44px;
    border-radius: 12px;
    background: #ECFDF5;
    color: #00742D;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.15rem;
    flex-shrink: 0;
}
.empf-eyebrow { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #94A3B8; }
.empf-title { font-size: 1.3rem; font-weight: 700; letter-spacing: -.02em; margin: .15rem 0; color: #0F172A; }
.empf-subtitle { font-size: .82rem; color: #94A3B8; }

/* ---- Buttons ---- */
.empf-btn {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    font-size: .82rem;
    font-weight: 600;
    padding: .55rem 1.1rem;
    border-radius: 10px;
    text-decoration: none;
    border: 1px solid transparent;
    cursor: pointer;
    transition: background .18s ease, transform .18s ease, border-color .18s ease;
}
.empf-btn-primary { background: #00742D; color: #fff; }
.empf-btn-primary:hover { background: #00611F; color: #fff; transform: translateY(-1px); }
.empf-btn-outline { background: #fff; color: #475569; border-color: #E5E9F0; }
.empf-btn-outline:hover { border-color: #CBD5E1; color: #0F172A; }
.empf-hint { font-size: .7rem; color: #94A3B8; margin-top: -.15rem; }

/* ---- Layout ---- */
.empf-layout { display: grid; grid-template-columns: 1fr 320px; gap: 1rem; align-items: start; }
@media (max-width: 992px) { .empf-layout { grid-template-columns: 1fr; } }

/* ---- Section cards ---- */
.empf-card {
    background: #fff;
    border: 1px solid #E9EDF2;
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    transition: border-color .2s ease, box-shadow .2s ease;
}
.empf-card:hover { border-color: #D7DEE8; box-shadow: 0 6px 18px rgba(15,23,42,.05); }

.empf-section-label {
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: #00742D;
    border-bottom: 1px solid #ECFDF5;
    padding-bottom: .6rem;
    margin-bottom: 1.1rem;
}

/* ---- Grid / fields ---- */
.empf-grid { display: grid; gap: 1rem; }
.empf-grid-2 { grid-template-columns: 1fr 1fr; }
.empf-grid-3 { grid-template-columns: 1fr 1fr 1fr; }
@media (max-width: 700px) { .empf-grid-2, .empf-grid-3 { grid-template-columns: 1fr; } }
.empf-span-2 { grid-column: span 2; }
@media (max-width: 700px) { .empf-span-2 { grid-column: span 1; } }

.empf-field { display: flex; flex-direction: column; gap: .35rem; }
.empf-label { font-size: .74rem; font-weight: 600; color: #64748B; }
.empf-input {
    font-size: .84rem;
    padding: .6rem .8rem;
    border: 1px solid #E5E9F0;
    border-radius: 10px;
    background: #fff;
    color: #0F172A;
    outline: none;
    width: 100%;
    transition: border-color .15s, box-shadow .15s;
}
.empf-input:focus { border-color: #00742D; box-shadow: 0 0 0 3px rgba(0,116,45,.08); }
.empf-input[type="file"] { padding: .5rem .7rem; font-size: .78rem; }

/* ---- Photo / docs ---- */
.empf-photo-row { display: flex; align-items: center; gap: .8rem; }
.empf-photo-preview { width: 62px; height: 62px; border-radius: 10px; object-fit: cover; border: 1px solid #E5E9F0; }
.empf-photo-note { font-size: .74rem; color: #94A3B8; }
.empf-photo-note span { color: #D97706; font-weight: 600; }

.empf-doc-list { border: 1px solid #E9EDF2; border-radius: 10px; padding: .6rem .75rem; background: #F8FAFC; }
.empf-doc-list-label { font-size: .7rem; color: #94A3B8; margin-bottom: .4rem; font-weight: 600; }
.empf-doc-link {
    display: flex;
    align-items: center;
    gap: .4rem;
    font-size: .78rem;
    color: #334155;
    text-decoration: none;
    padding: .25rem 0;
}
.empf-doc-link:hover { color: #00742D; }
.empf-doc-empty { font-size: .78rem; color: #B4BECC; }

/* ---- Actions ---- */
.empf-actions { display: flex; justify-content: flex-end; gap: .6rem; margin-top: .5rem; }

/* ---- Side summary ---- */
.empf-summary-card { position: sticky; top: 1.5rem; }
.empf-summary-head { display: flex; align-items: center; gap: .8rem; margin-bottom: 1rem; }
.empf-summary-avatar { width: 56px; height: 56px; border-radius: 50%; object-fit: cover; border: 2px solid #ECFDF5; }
.empf-summary-name { font-weight: 700; font-size: .92rem; color: #0F172A; }
.empf-summary-pos { font-size: .76rem; color: #94A3B8; margin: .1rem 0 .35rem; }

.empf-status-badge {
    display: inline-block;
    font-size: .66rem;
    font-weight: 700;
    padding: .16rem .6rem;
    border-radius: 20px;
}
.empf-status-active   { background: #ECFDF5; color: #059669; }
.empf-status-suspended,
.empf-status-exited   { background: #F1F5F9; color: #64748B; }

.empf-summary-label {
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: #94A3B8;
    margin: 1rem 0 .5rem;
}
.empf-summary-section { border-top: 1px solid #F1F5F9; padding-top: .6rem; }
.empf-summary-row {
    display: flex;
    justify-content: space-between;
    gap: .5rem;
    font-size: .8rem;
    color: #94A3B8;
    padding: .3rem 0;
}
.empf-summary-row strong { color: #0F172A; font-weight: 600; }
</style>

@endsection