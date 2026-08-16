@extends('layouts.app')

@section('content')

<div class="empf">

    {{-- ===== HEADER ===== --}}
    <div class="empf-header">
        <div class="empf-header-copy">
            <span class="empf-icon"><i class="bi bi-person-plus"></i></span>
            <div>
                <div class="empf-eyebrow">HR Management</div>
                <h1 class="empf-title">Add Employee</h1>
                <div class="empf-subtitle">Create a complete employee profile</div>
            </div>
        </div>

        <a class="empf-btn empf-btn-outline" href="{{ route('employees.index') }}">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>

    <form method="POST"
          action="{{ route('employees.store') }}"
          enctype="multipart/form-data">

        @csrf

        {{-- ================= PERSONAL INFO ================= --}}
        <div class="empf-card">
            <div class="empf-section-label">Personal Information</div>

            <div class="empf-grid empf-grid-3">
                <div class="empf-field">
                    <label class="empf-label">First Name</label>
                    <input class="empf-input" name="first_name" required>
                </div>
                <div class="empf-field">
                    <label class="empf-label">Middle Name</label>
                    <input class="empf-input" name="middle_name">
                </div>
                <div class="empf-field">
                    <label class="empf-label">Last Name</label>
                    <input class="empf-input" name="last_name" required>
                </div>

                <div class="empf-field">
                    <label class="empf-label">Date of Birth</label>
                    <input type="date" class="empf-input" name="date_of_birth" required>
                </div>
                <div class="empf-field">
                    <label class="empf-label">Gender</label>
                    <select class="empf-input" name="gender" required>
                        <option value="">Select</option>
                        <option>Male</option>
                        <option>Female</option>
                    </select>
                </div>
                <div class="empf-field">
                    <label class="empf-label">Nationality</label>
                    <input class="empf-input" name="nationality">
                </div>

                <div class="empf-field empf-span-2">
                    <label class="empf-label">NRC Number</label>
                    <input class="empf-input" name="nrc_no">
                </div>
                <div class="empf-field">
                    <label class="empf-label">Passport Number</label>
                    <input class="empf-input" name="passport_number">
                </div>
            </div>
        </div>

        {{-- ================= CONTACT INFO ================= --}}
        <div class="empf-card">
            <div class="empf-section-label">Contact Information</div>

            <div class="empf-grid empf-grid-2">
                <div class="empf-field">
                    <label class="empf-label">Personal Email</label>
                    <input class="empf-input" name="personal_email">
                </div>
                <div class="empf-field">
                    <label class="empf-label">Company Email</label>
                    <input class="empf-input" name="company_email">
                </div>
                <div class="empf-field">
                    <label class="empf-label">Primary Phone</label>
                    <input class="empf-input" name="primary_phone" required>
                </div>
                <div class="empf-field">
                    <label class="empf-label">Secondary Phone</label>
                    <input class="empf-input" name="secondary_phone">
                </div>
            </div>
        </div>

        {{-- ================= JOB INFO ================= --}}
        <div class="empf-card">
            <div class="empf-section-label">Job Information</div>

            <div class="empf-grid empf-grid-2">
                <div class="empf-field">
                    <label class="empf-label">Position</label>
                    <input class="empf-input" name="position" required>
                </div>
                <div class="empf-field">
                    <label class="empf-label">Department</label>
                    <input class="empf-input" name="department">
                </div>
                <div class="empf-field">
                    <label class="empf-label">Branch</label>
                    <input class="empf-input" name="branch">
                </div>
                <div class="empf-field">
                    <label class="empf-label">Supervisor</label>
                    <input class="empf-input" name="supervisor">
                </div>
                <div class="empf-field">
                    <label class="empf-label">Employment Status</label>
                    <select class="empf-input" name="employment_status">
                        <option value="Active">Active</option>
                        <option value="Exited">Exited</option>
                        <option value="Suspended">Suspended</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- ================= EMPLOYMENT DATES ================= --}}
        <div class="empf-card">
            <div class="empf-section-label">Employment Dates</div>

            <div class="empf-grid empf-grid-2">
                <div class="empf-field">
                    <label class="empf-label">Probation Start</label>
                    <input type="date" class="empf-input" name="probation_start">
                </div>
                <div class="empf-field">
                    <label class="empf-label">Probation End</label>
                    <input type="date" class="empf-input" name="probation_end">
                </div>
                <div class="empf-field">
                    <label class="empf-label">Contract Start</label>
                    <input type="date" class="empf-input" name="contract_start">
                </div>
                <div class="empf-field">
                    <label class="empf-label">Contract End</label>
                    <input type="date" class="empf-input" name="contract_end">
                </div>
            </div>
        </div>

        {{-- ================= EMERGENCY ================= --}}
        <div class="empf-card">
            <div class="empf-section-label">Emergency Contact</div>

            <div class="empf-grid empf-grid-3">
                <div class="empf-field">
                    <label class="empf-label">Name</label>
                    <input class="empf-input" name="emergency_name">
                </div>
                <div class="empf-field">
                    <label class="empf-label">Relationship</label>
                    <input class="empf-input" name="emergency_relationship">
                </div>
                <div class="empf-field">
                    <label class="empf-label">Phone</label>
                    <input class="empf-input" name="emergency_phone">
                </div>
            </div>
        </div>

        {{-- ================= NEXT OF KIN ================= --}}
        <div class="empf-card">
            <div class="empf-section-label">Next of Kin</div>

            <div class="empf-grid empf-grid-3">
                <div class="empf-field">
                    <label class="empf-label">Name</label>
                    <input class="empf-input" name="next_of_kin_name">
                </div>
                <div class="empf-field">
                    <label class="empf-label">Phone</label>
                    <input class="empf-input" name="next_of_kin_phone">
                </div>
                <div class="empf-field">
                    <label class="empf-label">Address</label>
                    <input class="empf-input" name="next_of_kin_address">
                </div>
            </div>
        </div>

        {{-- ================= FINANCE ================= --}}
        <div class="empf-card">
            <div class="empf-section-label">Finance Information</div>

            <div class="empf-grid empf-grid-3">
                <div class="empf-field">
                    <label class="empf-label">Bank Name</label>
                    <input class="empf-input" name="bank_name">
                </div>
                <div class="empf-field">
                    <label class="empf-label">Account Number</label>
                    <input class="empf-input" name="bank_account_no">
                </div>
                <div class="empf-field">
                    <label class="empf-label">NET Salary</label>
                    <input type="number" class="empf-input" name="net_salary">
                </div>
                <div class="empf-field">
                    <label class="empf-label">SSN NAPSA</label>
                    <input class="empf-input" name="ssn">
                </div>

                <div class="empf-field">
                    <label class="empf-label">NHIMA Number</label>
                    <input class="empf-input" name="nhima_no">
                </div>

                <div class="empf-field">
                    <label class="empf-label">TPIN</label>
                    <input class="empf-input" name="tpin">
                </div>
                
            </div>
        </div>

        {{-- ================= LEAVE ENTITLEMENT ================= --}}
        <div class="empf-card">
            <div class="empf-section-label">Leave Entitlement</div>

            <div class="empf-grid empf-grid-2">
                <div class="empf-field">
                    <label class="empf-label">Leave Days Entitled</label>
                    <input type="number" step="0.5" min="0" class="empf-input" name="leave_days_entitled">
                    <small class="empf-hint">Full annual entitlement — prorate this for employees starting mid-year.</small>
                </div>
                <div class="empf-field">
                    <label class="empf-label">Leave Days Balance</label>
                    <input type="number" step="0.5" min="0" class="empf-input" name="leave_days_balance">
                    <small class="empf-hint">Days currently available to the employee (starting balance).</small>
                </div>
            </div>
        </div>

        {{-- ================= UPLOADS ================= --}}
        <div class="empf-card">
            <div class="empf-section-label">Uploads</div>

            <div class="empf-grid empf-grid-2">
                <div class="empf-field">
                    <label class="empf-label">Passport Photo</label>
                    <input type="file" class="empf-input" name="passport_photo">
                </div>
                <div class="empf-field">
                    <label class="empf-label">Documents</label>
                    <input type="file" class="empf-input" name="documents[]" multiple>
                </div>
            </div>
        </div>

        {{-- ================= ACTIONS ================= --}}
        <div class="empf-actions">
            <a href="{{ route('employees.index') }}" class="empf-btn empf-btn-outline">Cancel</a>
            <button class="empf-btn empf-btn-primary">
                <i class="bi bi-save"></i> Save Employee
            </button>
        </div>

    </form>

</div>


{{-- ===== STYLES ===== --}}
<style>
.empf * { box-sizing: border-box; }

.empf {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
    color: #0F172A;
    background: #F8FAFC;
    padding: 1.5rem 0 2.5rem;
    max-width: 980px;
    margin: 0 auto;
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

.empf-hint { font-size: .7rem; color: #94A3B8; margin-top: -.15rem; }

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
    transition: border-color .15s, box-shadow .15s;
}
.empf-input:focus { border-color: #00742D; box-shadow: 0 0 0 3px rgba(0,116,45,.08); }
.empf-input[type="file"] { padding: .5rem .7rem; font-size: .78rem; }

/* ---- Actions ---- */
.empf-actions {
    display: flex;
    justify-content: flex-end;
    gap: .6rem;
    margin-top: .5rem;
}
</style>

@endsection