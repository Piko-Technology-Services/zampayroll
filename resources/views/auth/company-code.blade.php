@extends('layouts.auth')

@section('title', 'Find your company code')
@section('subtitle', 'Sign-in help')

@section('content')
<div class="mb-4">
    <h1 class="mb-1">Forgot your company code?</h1>
    <p class="text-muted mb-0">Enter the email you sign in with and we'll send you your company's code. Your administrator can also see it in Settings → Team &amp; roles.</p>
</div>

@if(session('status'))
    <div class="zp-alert zp-alert-success"><i class="bi bi-envelope-check"></i><div>{{ session('status') }}</div></div>
@endif

<form method="POST" action="{{ route('company-code.send') }}" novalidate>
    @csrf
    <div class="mb-4">
        <label class="form-label small fw-semibold text-muted" for="email">Email address</label>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope"></i></span>
            <input type="email" name="email" id="email" value="{{ old('email') }}" class="form-control border-start-0 @error('email') is-invalid @enderror" required autofocus>
        </div>
        @error('email')<small class="text-danger d-block mt-1">{{ $message }}</small>@enderror
    </div>
    <button class="btn btn-success w-100 py-2"><i class="bi bi-send me-2"></i> Email me the code</button>
</form>

<div class="text-center mt-4">
    <a href="{{ route('login') }}" class="small text-success fw-semibold text-decoration-none"><i class="bi bi-arrow-left"></i> Back to sign in</a>
</div>
@endsection
