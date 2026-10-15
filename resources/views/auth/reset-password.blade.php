@extends('layouts.auth')

@section('title', 'Choose a new password')
@section('subtitle', $workspace ? $workspace->name . ' workspace' : 'Account recovery')

@section('content')
<div class="mb-4">
    <h1 class="mb-1">Choose a new password</h1>
    <p class="text-muted mb-0">Make it something you haven't used here before.</p>
</div>

<form method="POST" action="{{ route('password.store') }}" novalidate>
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">

    <div class="mb-3">
        <label class="form-label small fw-semibold text-muted" for="email">Email address</label>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope"></i></span>
            <input type="email" name="email" id="email" value="{{ old('email', $email) }}"
                   class="form-control border-start-0 @error('email') is-invalid @enderror" required autocomplete="username">
        </div>
        @error('email')<small class="text-danger d-block mt-1">{{ $message }}</small>@enderror
    </div>

    <div class="mb-3">
        <label class="form-label small fw-semibold text-muted" for="password">New password</label>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock"></i></span>
            <input type="password" name="password" id="password" class="form-control border-start-0 @error('password') is-invalid @enderror"
                   required autocomplete="new-password" autofocus>
        </div>
        <ul class="pw-rules" id="pwRules">
            <li data-rule="len">8+ characters</li>
            <li data-rule="letter">a letter</li>
            <li data-rule="num">a number</li>
            <li data-rule="match">passwords match</li>
        </ul>
        @error('password')<small class="text-danger d-block mt-1">{{ $message }}</small>@enderror
    </div>

    <div class="mb-4">
        <label class="form-label small fw-semibold text-muted" for="password_confirmation">Confirm new password</label>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock-fill"></i></span>
            <input type="password" name="password_confirmation" id="password_confirmation" class="form-control border-start-0" required autocomplete="new-password">
        </div>
    </div>

    <button class="btn btn-success w-100 py-2" type="submit"><i class="bi bi-shield-lock me-2"></i> Save new password</button>
</form>

<div class="text-center mt-4">
    <a href="{{ route('password.request') }}" class="small text-success fw-semibold text-decoration-none">Need a new link?</a>
</div>

<script>
(function () {
    const pw = document.getElementById('password'), pc = document.getElementById('password_confirmation');
    const rules = { len: v => v.length >= 8, letter: v => /[a-z]/i.test(v), num: v => /\d/.test(v), match: v => v && v === pc.value };
    function check() { document.querySelectorAll('#pwRules li').forEach(li => li.classList.toggle('ok', rules[li.dataset.rule](pw.value))); }
    pw.addEventListener('input', check); pc.addEventListener('input', check);
})();
</script>
@endsection
