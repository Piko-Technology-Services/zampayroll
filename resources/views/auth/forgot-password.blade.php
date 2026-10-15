@extends('layouts.auth')

@section('title', 'Forgot password')
@section('subtitle', $workspace ? $workspace->name . ' workspace' : 'Account recovery')

@section('content')
<div class="mb-4">
    <h1 class="mb-1">Forgot your password?</h1>
    <p class="text-muted mb-0">It happens. Enter the email you sign in with and we'll send you a link to choose a new one.</p>
</div>

@if(session('status'))
    <div class="zp-alert zp-alert-success"><i class="bi bi-envelope-check"></i><div>{{ session('status') }}</div></div>
@endif

<form method="POST" action="{{ route('password.email') }}" novalidate>
    @csrf
    <div class="mb-4">
        <label class="form-label small fw-semibold text-muted" for="email">Email address</label>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope"></i></span>
            <input type="email" name="email" id="email" value="{{ old('email', $prefillEmail) }}"
                   class="form-control border-start-0 @error('email') is-invalid @enderror"
                   placeholder="name@company.com" autocomplete="email" required autofocus>
        </div>
        @error('email')<small class="text-danger d-block mt-1">{{ $message }}</small>@enderror
    </div>

    <button class="btn btn-success w-100 py-2" type="submit"><i class="bi bi-send me-2"></i> Email me a reset link</button>
</form>

<ul class="list-unstyled small text-muted mt-4 mb-0" style="line-height:1.9">
    <li><i class="bi bi-clock me-1 text-success"></i> The link works for 60 minutes and only once.</li>
    <li><i class="bi bi-inbox me-1 text-success"></i> Not arriving? Check your Spam folder.</li>
    <li><i class="bi bi-person-badge me-1 text-success"></i> Employees can also ask HR to re-send portal access.</li>
</ul>

<div class="text-center mt-4">
    <a href="{{ route('login') }}" class="small text-success fw-semibold text-decoration-none"><i class="bi bi-arrow-left"></i> Back to sign in</a>
</div>
@endsection
