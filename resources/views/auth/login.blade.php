@extends('layouts.auth')

@section('title', 'Sign in')
@section('subtitle', $workspace ? $workspace->name . ' workspace' : 'Payroll Workspace')

@section('content')

<div class="mb-4">
    <h1 class="mb-1">Welcome back</h1>
    <p class="text-muted mb-0">
        @if($workspace)
            Sign in to {{ $workspace->name }}'s dedicated workspace.
        @else
            Sign in to run payroll, or open your employee self-service portal.
        @endif
    </p>
</div>

@if(session('success'))
    <div class="zp-alert zp-alert-success"><i class="bi bi-check-circle"></i><div>{{ session('success') }}</div></div>
@endif
@if(session('status'))
    <div class="zp-alert zp-alert-success"><i class="bi bi-check-circle"></i><div>{{ session('status') }}</div></div>
@endif

<form method="POST" action="{{ route('login') }}" novalidate>
    @csrf

    <div class="mb-3">
        <label class="form-label small fw-semibold text-muted" for="email">Email address</label>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope"></i></span>
            <input type="email" name="email" id="email" value="{{ old('email', $prefillEmail) }}"
                   class="form-control border-start-0 @error('email') is-invalid @enderror"
                   placeholder="name@company.com" autocomplete="username" required autofocus>
        </div>
        @error('email')
            <small class="text-danger d-block mt-1">{{ $message }}</small>
        @enderror
    </div>

    <div class="mb-3">
        <div class="d-flex justify-content-between">
            <label class="form-label small fw-semibold text-muted" for="password">Password</label>
            <a href="{{ route('password.request', array_filter(['email' => old('email', $prefillEmail)])) }}" class="small text-success text-decoration-none fw-semibold">Forgot password?</a>
        </div>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock"></i></span>
            <input type="password" name="password" id="password"
                   class="form-control border-start-0 border-end-0 @error('password') is-invalid @enderror"
                   placeholder="Enter your password" autocomplete="current-password" required>
            <button class="input-group-text bg-light" type="button" onclick="const p=document.getElementById('password');p.type=p.type==='password'?'text':'password';this.firstElementChild.className='bi bi-eye'+(p.type==='password'?'':'-slash')" aria-label="Show password" style="border-radius:0 12px 12px 0"><i class="bi bi-eye"></i></button>
        </div>
        @error('password')
            <small class="text-danger">{{ $message }}</small>
        @enderror
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="remember" id="remember" @checked(old('remember'))>
            <label class="form-check-label" for="remember">Keep me signed in</label>
        </div>
        <span class="small text-muted"><i class="bi bi-shield-check"></i> Secure sign-in</span>
    </div>

    <button class="btn btn-success w-100 py-2" type="submit">
        <i class="bi bi-box-arrow-in-right me-2"></i> Sign in
    </button>
</form>

@if(session('unverified_email'))
    <form method="POST" action="{{ route('verification.send') }}" class="mt-3">
        @csrf
        <input type="hidden" name="email" value="{{ session('unverified_email') }}">
        <button class="btn btn-link p-0 small text-success fw-semibold" type="submit"><i class="bi bi-envelope-arrow-up"></i> Resend the confirmation email</button>
    </form>
@endif

@unless($workspace)
    <div class="auth-divider">New to ZamPayroll?</div>
    <a class="auth-cta-card" href="{{ route('register') }}">
        <span class="ic"><i class="bi bi-rocket-takeoff"></i></span>
        <span><b>Create a company workspace</b><span>Set up takes about 3 minutes — no card needed.</span></span>
    </a>
@endunless

<p class="small text-muted mt-4 mb-0"><i class="bi bi-person-badge"></i> Employees: use the email and temporary password HR sent you.</p>

@endsection
