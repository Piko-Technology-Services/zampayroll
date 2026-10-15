@extends('layouts.onboarding')

@section('title', 'Confirm your email')

@section('aside')
    <h2>One last thing before we open the doors.</h2>
    <p class="lead-copy">Confirming your email keeps payroll data safe — it proves the administrator account really belongs to you.</p>
@endsection

@section('content')
<div class="ob-status" style="margin-top:3rem">
    <div class="ob-status-icon ob-envelope" aria-hidden="true">📬</div>
    <h1 class="ob-title">Check your inbox</h1>
    <p class="ob-sub">
        @if($email)
            We sent a confirmation link to <strong>{{ $email }}</strong>.
        @else
            We sent a confirmation link to the email you registered with.
        @endif
        Click it to activate your ZamPayroll workspace.
    </p>

    @if(session('status'))
        <div class="zp-alert zp-alert-success"><i class="bi bi-check-circle"></i><div>{{ session('status') }}</div></div>
    @endif
    @if($errors->any())
        <div class="zp-alert zp-alert-error"><i class="bi bi-exclamation-circle"></i><div>{{ $errors->first() }}</div></div>
    @endif

    <ul class="ob-checklist">
        <li><i class="bi bi-check2-circle"></i> The link works for 2 hours.</li>
        <li><i class="bi bi-check2-circle"></i> Can't see it? Look in Spam or Promotions, or search for "ZamPayroll".</li>
        <li><i class="bi bi-check2-circle"></i> Bigger teams: your dedicated workspace is created the moment you confirm.</li>
    </ul>

    <form method="POST" action="{{ route('verification.send') }}" class="zp-field" style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:flex-start">
        @csrf
        <input class="zp-input" style="flex:1;min-width:220px" type="email" name="email" value="{{ $email }}" placeholder="you@company.co.zm" required aria-label="Email address">
        <button class="zp-btn zp-btn-primary" type="submit"><i class="bi bi-arrow-repeat"></i> Resend link</button>
    </form>

    <p class="zp-hint" style="margin-top:1.5rem">Used the wrong address? <a href="{{ route('register') }}" style="color:var(--zp-green);font-weight:600">Start again</a> · Already confirmed? <a href="{{ route('login') }}" style="color:var(--zp-green);font-weight:600">Sign in</a></p>
</div>
@endsection
