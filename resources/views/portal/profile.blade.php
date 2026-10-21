@extends('layouts.portal')
@section('title', 'My profile')

@section('content')
<div class="pt-page-head">
    <div>
        <h1>My profile</h1>
        <p>Keep your contact details current. Job and salary are managed by HR; bank changes are checked by HR first.</p>
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

    <div class="pt-card pt-span-12" id="bank">
        <h3>Change the bank account my salary goes to</h3>
        <p class="zp-hint" style="margin-top:-.4rem">For your safety, HR checks this with you before anything changes. You'll get an email when you send it and when HR decides.</p>
        @php $waiting = $changes->firstWhere('status', 'pending'); @endphp
        @if($waiting)
            <div class="pt-estimate" style="justify-content:space-between;align-items:center">
                <span>Waiting for HR: <b>{{ $waiting->changes['bank_name'] ?? '' }} ••••{{ substr((string) ($waiting->changes['bank_account_no'] ?? ''), -4) }}</b>, sent {{ $waiting->created_at->diffForHumans() }}</span>
                <form method="POST" action="{{ route('portal.profile.changes.cancel', $waiting) }}" onsubmit="return confirm('Cancel this change?')">@csrf @method('DELETE')<button class="zp-btn-link" style="border:0;background:none;color:var(--zp-red)">Cancel</button></form>
            </div>
        @else
            <form method="POST" action="{{ route('portal.profile.bank') }}" enctype="multipart/form-data" novalidate>
                @csrf
                <div class="ob-grid">
                    <div class="zp-field">
                        <label class="zp-label" for="bank_name">Bank</label>
                        <input class="zp-input @error('bank_name') is-invalid @enderror" name="bank_name" id="bank_name" list="bankList" value="{{ old('bank_name') }}" required>
                        <datalist id="bankList">@foreach($banks as $b)<option value="{{ $b }}">@endforeach</datalist>
                        @error('bank_name')<div class="zp-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="zp-field">
                        <label class="zp-label" for="bank_branch">Branch <small>(optional)</small></label>
                        <input class="zp-input" name="bank_branch" id="bank_branch" value="{{ old('bank_branch') }}">
                    </div>
                    <div class="zp-field">
                        <label class="zp-label" for="bank_account_no">New account number</label>
                        <input class="zp-input @error('bank_account_no') is-invalid @enderror" name="bank_account_no" id="bank_account_no" inputmode="numeric" autocomplete="off" required>
                        @error('bank_account_no')<div class="zp-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="zp-field">
                        <label class="zp-label" for="bank_account_no_confirmation">Type it again</label>
                        <input class="zp-input @error('bank_account_no_confirmation') is-invalid @enderror" name="bank_account_no_confirmation" id="bank_account_no_confirmation" inputmode="numeric" autocomplete="off" required>
                        @error('bank_account_no_confirmation')<div class="zp-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="zp-field">
                        <label class="zp-label" for="document">Bank letter or card <small>(optional, PDF/photo)</small></label>
                        <input class="zp-input" type="file" name="document" id="document" accept=".pdf,.jpg,.jpeg,.png">
                    </div>
                    <div class="zp-field">
                        <label class="zp-label" for="note">Note for HR <small>(optional)</small></label>
                        <input class="zp-input" name="note" id="note" maxlength="500" value="{{ old('note') }}">
                    </div>
                </div>
                <button class="zp-btn zp-btn-ghost" type="submit"><i class="bi bi-shield-check"></i> Send to HR to check</button>
            </form>
        @endif
        @if($changes->where('status', '!=', 'pending')->isNotEmpty())
            <ul class="pt-list mt-2">
                @foreach($changes->where('status', '!=', 'pending') as $c)
                    <li><span class="ic"><i class="bi bi-bank"></i></span><span class="tx"><b>{{ $c->changes['bank_name'] ?? '' }} ••••{{ substr((string) ($c->changes['bank_account_no'] ?? ''), -4) }}</b><span>{{ $c->created_at->format('j M Y') }}{{ $c->review_comment ? ' · HR: ' . $c->review_comment : '' }}</span></span><span class="pt-status {{ $c->status }}">{{ $c->statusLabel() }}</span></li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
@endsection
