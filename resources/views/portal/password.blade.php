@extends('layouts.portal')
@section('title', $forced ? 'Choose your password' : 'Change password')

@section('content')
<div style="max-width:520px;margin:1rem auto">
    <div class="pt-card" style="padding:1.75rem">
        @if($forced)
            <div class="ob-status-icon" aria-hidden="true" style="width:60px;height:60px;font-size:1.7rem;margin-bottom:1rem">🔑</div>
            <h1 class="ob-title" style="font-size:1.8rem">Welcome! First, pick your own password.</h1>
            <p class="ob-sub">Replace the temporary password from your welcome email with one only you know.</p>
        @else
            <h1 class="ob-title" style="font-size:1.8rem">Change password</h1>
            <p class="ob-sub">You'll stay signed in here; other devices will be signed out.</p>
        @endif

        <form method="POST" action="{{ route('portal.password.update') }}" novalidate>
            @csrf @method('PUT')
            <div class="zp-field">
                <label class="zp-label" for="current_password">{{ $forced ? 'Temporary password' : 'Current password' }}</label>
                <input class="zp-input @error('current_password') is-invalid @enderror" type="password" name="current_password" id="current_password" autocomplete="current-password" required autofocus>
                @error('current_password')<div class="zp-error">{{ $message }}</div>@enderror
            </div>
            <div class="zp-field">
                <label class="zp-label" for="password">New password</label>
                <input class="zp-input @error('password') is-invalid @enderror" type="password" name="password" id="password" autocomplete="new-password" required>
                <div class="zp-hint">At least 8 characters, with letters and numbers.</div>
                @error('password')<div class="zp-error">{{ $message }}</div>@enderror
            </div>
            <div class="zp-field">
                <label class="zp-label" for="password_confirmation">Confirm new password</label>
                <input class="zp-input" type="password" name="password_confirmation" id="password_confirmation" autocomplete="new-password" required>
            </div>
            <button class="zp-btn zp-btn-primary zp-btn-block" type="submit"><i class="bi bi-shield-lock"></i> {{ $forced ? 'Save and open my portal' : 'Update password' }}</button>
        </form>
    </div>
</div>
@endsection
