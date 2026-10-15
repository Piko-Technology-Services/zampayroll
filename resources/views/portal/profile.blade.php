@extends('layouts.portal')
@section('title', 'My profile')

@section('content')
<div class="pt-page-head">
    <div>
        <h1>My profile</h1>
        <p>Keep your contact details current. Job, salary and bank details are managed by HR.</p>
    </div>
    <a class="zp-btn zp-btn-ghost" href="{{ route('portal.password.edit') }}"><i class="bi bi-key"></i> Change password</a>
</div>

<div class="pt-grid">
    <div class="pt-card pt-span-4">
        <h3>Employment</h3>
        <ul class="pt-list">
            @foreach([
                ['bi-person', $employee->full_name, 'Name'],
                ['bi-person-vcard', $employee->employee_id, 'Employee number'],
                ['bi-briefcase', $employee->position, 'Position'],
                ['bi-diagram-3', $employee->department ?: '—', 'Department'],
                ['bi-geo-alt', $employee->branch ?: '—', 'Branch'],
                ['bi-envelope', $user->email, 'Sign-in email'],
                ['bi-bank', $employee->bank_name ? $employee->bank_name . ' ••••' . substr((string) $employee->bank_account_no, -4) : '—', 'Bank account'],
            ] as [$icon, $value, $label])
                <li><span class="ic"><i class="bi {{ $icon }}"></i></span><span class="tx"><b>{{ $value }}</b><span>{{ $label }}</span></span></li>
            @endforeach
        </ul>
        <p class="zp-hint mt-2">Something wrong here? Ask HR to update it.</p>
    </div>

    <div class="pt-card pt-span-8">
        <h3>Contact &amp; emergency details</h3>
        <form method="POST" action="{{ route('portal.profile.update') }}" novalidate>
            @csrf @method('PUT')
            <div class="ob-grid">
                <div class="zp-field">
                    <label class="zp-label" for="primary_phone">Mobile number</label>
                    <input class="zp-input @error('primary_phone') is-invalid @enderror" name="primary_phone" id="primary_phone" value="{{ old('primary_phone', $employee->primary_phone) }}" required>
                    @error('primary_phone')<div class="zp-error">{{ $message }}</div>@enderror
                </div>
                <div class="zp-field">
                    <label class="zp-label" for="secondary_phone">Other number</label>
                    <input class="zp-input" name="secondary_phone" id="secondary_phone" value="{{ old('secondary_phone', $employee->secondary_phone) }}">
                </div>
                <div class="zp-field full">
                    <label class="zp-label" for="personal_email">Personal email <small>(payslip emails go here)</small></label>
                    <input class="zp-input @error('personal_email') is-invalid @enderror" type="email" name="personal_email" id="personal_email" value="{{ old('personal_email', $employee->personal_email) }}">
                    @error('personal_email')<div class="zp-error">{{ $message }}</div>@enderror
                </div>

                <div class="full" style="font-weight:700;margin:.6rem 0 .6rem">Emergency contact</div>
                <div class="zp-field">
                    <label class="zp-label" for="emergency_name">Name</label>
                    <input class="zp-input" name="emergency_name" id="emergency_name" value="{{ old('emergency_name', $employee->emergency_name) }}">
                </div>
                <div class="zp-field">
                    <label class="zp-label" for="emergency_relationship">Relationship</label>
                    <input class="zp-input" name="emergency_relationship" id="emergency_relationship" value="{{ old('emergency_relationship', $employee->emergency_relationship) }}" placeholder="Spouse, parent, sibling…">
                </div>
                <div class="zp-field">
                    <label class="zp-label" for="emergency_phone">Phone</label>
                    <input class="zp-input" name="emergency_phone" id="emergency_phone" value="{{ old('emergency_phone', $employee->emergency_phone) }}">
                </div>

                <div class="full" style="font-weight:700;margin:.6rem 0 .6rem">Next of kin</div>
                <div class="zp-field">
                    <label class="zp-label" for="next_of_kin_name">Name</label>
                    <input class="zp-input" name="next_of_kin_name" id="next_of_kin_name" value="{{ old('next_of_kin_name', $employee->next_of_kin_name) }}">
                </div>
                <div class="zp-field">
                    <label class="zp-label" for="next_of_kin_phone">Phone</label>
                    <input class="zp-input" name="next_of_kin_phone" id="next_of_kin_phone" value="{{ old('next_of_kin_phone', $employee->next_of_kin_phone) }}">
                </div>
                <div class="zp-field full">
                    <label class="zp-label" for="next_of_kin_address">Address</label>
                    <textarea class="zp-textarea" name="next_of_kin_address" id="next_of_kin_address" rows="2">{{ old('next_of_kin_address', $employee->next_of_kin_address) }}</textarea>
                </div>
            </div>
            <button class="zp-btn zp-btn-primary" type="submit"><i class="bi bi-check2"></i> Save my details</button>
        </form>
    </div>
</div>
@endsection
