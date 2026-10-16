@extends('layouts.app')

@section('content')
<div style="max-width:560px;margin:3rem auto;text-align:left">
    <div class="ob-status-icon copper" aria-hidden="true" style="width:64px;height:64px;font-size:1.8rem;margin-bottom:1rem">🔒</div>
    <h1 class="ob-title" style="font-size:1.8rem">This page isn't part of your role</h1>
    <p class="ob-sub">You're signed in as <strong>{{ $roleName }}</strong>, which doesn't include <strong>{{ strtolower($permissionLabel) }}</strong>.
        If you need it, ask your company administrator to update your role in Settings → Team &amp; roles.</p>
    <a href="{{ route('dashboard') }}" class="zp-btn zp-btn-primary"><i class="bi bi-house-door"></i> Back to Home</a>
</div>
@endsection
