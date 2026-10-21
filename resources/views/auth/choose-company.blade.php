@extends('layouts.auth')

@section('title', 'Choose a company')
@section('subtitle', 'Payroll Workspace')

@section('content')
<div class="mb-4">
    <h1 class="mb-1">Which company?</h1>
    <p class="text-muted mb-0">{{ $choices['email'] }} has access to more than one. Pick where you want to go.</p>
</div>

<div class="d-grid gap-2">
    @foreach($choices['matches'] as $m)
        <form method="POST" action="{{ route('login.choose') }}">
            @csrf
            <input type="hidden" name="company_id" value="{{ $m['company_id'] }}">
            <button type="submit" class="auth-cta-card w-100 text-start border-0" style="cursor:pointer">
                <span class="ic"><i class="bi {{ $m['dedicated'] ? 'bi-building-lock' : 'bi-building' }}"></i></span>
                <span><b>{{ $m['company'] }}</b><span>{{ $m['role'] }}{{ $m['dedicated'] ? ' · private workspace' : '' }}</span></span>
            </button>
        </form>
    @endforeach
</div>

<p class="small text-muted mt-4 mb-0"><a href="{{ route('login') }}" class="text-success text-decoration-none fw-semibold"><i class="bi bi-arrow-left"></i> Use a different account</a></p>
@endsection
