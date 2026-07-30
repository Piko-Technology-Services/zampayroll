@extends('layouts.app')

@section('content')

<div class="container-fluid">

    {{-- HEADER --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">
                <i class="bi bi-person-circle me-1"></i> My Profile
            </h4>
            <small class="text-muted">Account & company settings</small>
        </div>

        <button onclick="window.print()" class="btn btn-outline-dark btn-sm">
            <i class="bi bi-printer"></i> Print
        </button>
    </div>

    {{-- FLASH MESSAGES --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show small" role="alert">
            <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any() && !$errors->hasBag('password'))
        <div class="alert alert-danger alert-dismissible fade show small" role="alert">
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-3">

        {{-- LEFT: PROFILE CARD (preserved) --}}
        <div class="col-md-4">
            <div class="card shadow-sm border-0 p-3 text-center">

                @php
                    $names = explode(' ', $user->name);
                    $initials = strtoupper(substr($names[0] ?? '', 0, 1) . substr($names[1] ?? '', 0, 1));
                @endphp

                <div class="rounded-circle border d-flex align-items-center justify-content-center mx-auto mb-3" style="
                    width:90px; height:90px; font-size:28px; font-weight:700;
                    background:#f8f9fa; color:#495057;">
                    {{ $initials }}
                </div>

                <h5 class="mb-0">{{ $user->name }}</h5>
                <small class="text-muted">{{ $user->email }}</small>

                <hr>

                <div class="text-start small">
                    <p class="mb-2">
                        <i class="bi bi-shield-lock me-1 text-primary"></i>
                        Role: {{ \App\Models\User::INVITABLE_ROLES[$user->role] ?? ucfirst($user->role ?? 'User') }}
                    </p>
                    <p class="mb-2">
                        <i class="bi bi-calendar-check me-1 text-success"></i>
                        Joined: {{ $user->created_at?->format('d M Y') }}
                    </p>
                    <p class="mb-0">
                        <i class="bi bi-circle-fill me-1 text-success small"></i>
                        Status: Active
                    </p>
                </div>
            </div>

            @if($company)
            <div class="card shadow-sm border-0 p-3 mt-3">
                <h6 class="mb-2"><i class="bi bi-building me-1"></i> {{ $company->name }}</h6>
                <small class="text-muted d-block mb-1"><i class="bi bi-envelope me-1"></i>{{ $company->email }}</small>
                <small class="text-muted d-block"><i class="bi bi-telephone me-1"></i>{{ $company->phone ?? '—' }}</small>
            </div>
            @endif
        </div>

        {{-- RIGHT: DETAILS --}}
        <div class="col-md-8">

            {{-- 1. MY PROFILE --}}
            <div class="card shadow-sm border-0 p-3">
                <h6 class="mb-3"><i class="bi bi-person-lines-fill me-1"></i> Account Details</h6>

                <form method="POST" action="{{ route('profile.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="row g-3 small">
                        <div class="col-md-6">
                            <label class="text-muted form-label">Full Name</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
                        </div>

                        <div class="col-md-6">
                            <label class="text-muted form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>
                        </div>

                        <div class="col-md-6">
                            <label class="text-muted form-label">Position</label>
                            <input type="text" name="position" class="form-control" value="{{ old('position', $user->position) }}">
                        </div>

                        <div class="col-md-6">
                            <label class="text-muted form-label">Role</label>
                            <input type="text" class="form-control" value="{{ \App\Models\User::INVITABLE_ROLES[$user->role] ?? ucfirst($user->role ?? 'User') }}" disabled>
                            <small class="text-muted">Role is managed by your company administrator.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="text-muted form-label">Account Created</label>
                            <input type="text" class="form-control" value="{{ $user->created_at?->format('d M Y H:i') }}" disabled>
                        </div>
                    </div>

                    <div class="text-end mt-3">
                        <button class="btn btn-primary btn-sm">
                            <i class="bi bi-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>

            {{-- 2. PASSWORD & SECURITY --}}
            <div class="card shadow-sm border-0 p-3 mt-3">
                <h6 class="mb-3"><i class="bi bi-shield-lock me-1"></i> Password & Security</h6>

                <form method="POST" action="{{ route('profile.password.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="row g-3 small">
                        <div class="col-md-4">
                            <label class="text-muted form-label">Current Password</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted form-label">New Password</label>
                            <input type="password" name="new_password" class="form-control" minlength="8" required>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted form-label">Confirm New Password</label>
                            <input type="password" name="new_password_confirmation" class="form-control" minlength="8" required>
                        </div>
                    </div>

                    <div class="text-end mt-3">
                        <button class="btn btn-outline-primary btn-sm">
                            Change Password
                        </button>
                    </div>
                </form>
            </div>

            @if($company)

            {{-- 3. COMPANY INFORMATION --}}
            <div class="card shadow-sm border-0 p-3 mt-3">
                <h6 class="mb-3"><i class="bi bi-building me-1"></i> Company Information</h6>

                <form method="POST" action="{{ route('company.update') }}">
                    @csrf
                    @method('PUT')
                    @php $readonly = ! $user->isCompanyAdmin(); @endphp

                    <div class="row g-3 small">
                        <div class="col-md-6">
                            <label class="text-muted form-label">Company Name</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $company->name) }}" @disabled($readonly) required>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted form-label">Company Email</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email', $company->email) }}" @disabled($readonly) required>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted form-label">Phone Number</label>
                            <input type="text" name="phone" class="form-control" value="{{ old('phone', $company->phone) }}" @disabled($readonly)>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted form-label">TPIN</label>
                            <input type="text" name="tpin" class="form-control" value="{{ old('tpin', $company->tpin) }}" @disabled($readonly)>
                        </div>
                        <div class="col-md-12">
                            <label class="text-muted form-label">Address</label>
                            <input type="text" name="address" class="form-control" value="{{ old('address', $company->address) }}" @disabled($readonly)>
                        </div>
                    </div>

                    @if(!$readonly)
                    <div class="text-end mt-3">
                        <button class="btn btn-primary btn-sm">
                            <i class="bi bi-save"></i> Save Company Info
                        </button>
                    </div>
                    @else
                    <small class="text-muted d-block mt-2">Only a company administrator can edit this information.</small>
                    @endif
                </form>
            </div>

            @if($user->isCompanyAdmin())

                {{-- 4. COMPANY ACCESS CODE --}}
                <div class="card shadow-sm border-0 p-3 mt-3">
                    <h6 class="mb-3"><i class="bi bi-key me-1"></i> Company Access Code</h6>

                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div>
                            <div class="fw-semibold font-monospace fs-5" id="companyAccessCode">
                                {{ $company->access_code ?? 'No code generated yet' }}
                            </div>
                            <small class="text-muted">
                                Status:
                                @if($company->access_code_active)
                                    <span class="text-success">Active</span>
                                @else
                                    <span class="text-danger">Inactive</span>
                                @endif
                            </small>
                        </div>

                        <div class="d-flex gap-2 flex-wrap">
                            @if($company->access_code)
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="copyAccessCode()">
                                    <i class="bi bi-clipboard"></i> Copy
                                </button>
                            @endif

                            <form method="POST" action="{{ route('company.access-code.generate') }}">
                                @csrf
                                <button class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-arrow-repeat"></i> {{ $company->access_code ? 'Regenerate' : 'Generate' }}
                                </button>
                            </form>

                            @if($company->access_code && $company->access_code_active)
                            <form method="POST" action="{{ route('company.access-code.deactivate') }}"
                                  onsubmit="return confirm('Deactivate the company access code? Existing invitation links will still work, but the code itself will stop granting access.');">
                                @csrf
                                <button class="btn btn-outline-danger btn-sm">
                                    <i class="bi bi-slash-circle"></i> Deactivate
                                </button>
                            </form>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- 5. TEAM MEMBERS --}}
                <div class="card shadow-sm border-0 p-3 mt-3">
                    <h6 class="mb-3"><i class="bi bi-people me-1"></i> Team Members</h6>

                    <div class="table-responsive">
                        <table class="table table-sm align-middle small">
                            <thead>
                                <tr class="text-muted">
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Position</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($teamMembers as $member)
                                <tr>
                                    <td>{{ $member->name }} @if($member->id === $user->id) <span class="badge bg-light text-dark border">You</span> @endif</td>
                                    <td>{{ $member->email }}</td>
                                    <td>{{ $invitableRoles[$member->role] ?? ucfirst($member->role ?? '—') }}</td>
                                    <td>{{ $member->position ?? '—' }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="4" class="text-muted text-center py-3">No team members yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($pendingInvitations->isNotEmpty())
                    <hr>
                    <div class="small text-muted mb-2">Pending Invitations</div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle small">
                            <tbody>
                                @foreach($pendingInvitations as $invitation)
                                <tr>
                                    <td>{{ $invitation->email }}</td>
                                    <td>{{ $invitableRoles[$invitation->role] ?? $invitation->role }}</td>
                                    <td class="text-muted">Expires {{ $invitation->expires_at->format('d M Y') }}</td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('invitations.revoke', $invitation) }}"
                                              onsubmit="return confirm('Revoke this invitation?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger">Revoke</button>
                                        </form>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>

                {{-- 6. INVITE A TEAM MEMBER --}}
                <div class="card shadow-sm border-0 p-3 mt-3">
                    <h6 class="mb-3"><i class="bi bi-person-plus me-1"></i> Invite a Team Member</h6>

                    <form method="POST" action="{{ route('invitations.store') }}">
                        @csrf
                        <div class="row g-3 small">
                            <div class="col-md-5">
                                <label class="text-muted form-label">Email Address</label>
                                <input type="email" name="email" class="form-control" required placeholder="colleague@example.com">
                            </div>
                            <div class="col-md-4">
                                <label class="text-muted form-label">Role</label>
                                <select name="role" class="form-select" required>
                                    <option value="">Select role</option>
                                    @foreach($invitableRoles as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="text-muted form-label">Position</label>
                                <input type="text" name="position" class="form-control" placeholder="e.g. Payroll Officer">
                            </div>
                        </div>

                        <div class="text-end mt-3">
                            <button class="btn btn-primary btn-sm">
                                <i class="bi bi-send"></i> Send Invitation
                            </button>
                        </div>
                    </form>
                </div>

            @endif
            @endif

        </div>

    </div>

</div>

<script>
function copyAccessCode() {
    const code = document.getElementById('companyAccessCode').innerText.trim();
    navigator.clipboard.writeText(code).then(() => {
        if (typeof showToast === 'function') {
            showToast('Access code copied to clipboard', 'success');
        }
    });
}
</script>

@endsection
