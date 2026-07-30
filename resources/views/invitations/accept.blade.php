@extends('layouts.app')

@section('content')
<div class="container-fluid" style="max-width:560px;">

    <div class="card shadow-sm border-0 p-4 mt-4">

        @if($invalid ?? false)

            <div class="text-center py-3">
                <i class="bi bi-x-circle text-danger" style="font-size:42px;"></i>
                <h5 class="mt-3">Invitation Invalid or Expired</h5>
                <p class="text-muted small">
                    This invitation link is no longer valid. Please ask your company administrator to send a new one.
                </p>
            </div>

        @elseif($wrongAccount ?? false)

            <div class="text-center py-3">
                <i class="bi bi-exclamation-triangle text-warning" style="font-size:42px;"></i>
                <h5 class="mt-3">This invitation is for a different email address</h5>
                <p class="text-muted small">
                    You're currently signed in as <strong>{{ auth()->user()->email }}</strong>, but this
                    invitation was sent to <strong>{{ $invitation->email }}</strong>. Please log out and try again.
                </p>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-outline-dark btn-sm">Log Out</button>
                </form>
            </div>

        @else

            <h5 class="mb-1"><i class="bi bi-envelope-check me-1"></i> Join {{ $invitation->company->name }}</h5>
            <p class="text-muted small mb-4">
                You've been invited as
                <strong>{{ $invitableRoles[$invitation->role] ?? $invitation->role }}</strong>.
                Confirm your details below to complete access.
            </p>

            @if ($errors->any())
                <div class="alert alert-danger small">
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('invitations.complete', $token) }}">
                @csrf
                <input type="hidden" name="is_new_user" value="{{ $isNewUser ? '1' : '0' }}">

                <div class="mb-3">
                    <label class="form-label small">Email</label>
                    <input type="email" class="form-control" value="{{ $invitation->email }}" disabled>
                </div>

                @if($isNewUser)
                    <div class="mb-3">
                        <label class="form-label small">Full Name</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small">Set a Password</label>
                        <input type="password" name="password" class="form-control" required minlength="8">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small">Confirm Password</label>
                        <input type="password" name="password_confirmation" class="form-control" required minlength="8">
                    </div>
                @else
                    <div class="alert alert-info small">
                        An account already exists for this email. Accepting will link it to
                        <strong>{{ $invitation->company->name }}</strong>.
                        @unless(auth()->check())
                            You'll need to <a href="{{ route('login') }}">log in</a> first, then revisit this link.
                        @endunless
                    </div>
                @endif

                <div class="mb-3">
                    <label class="form-label small">Position</label>
                    <input type="text" name="position" class="form-control" value="{{ old('position', $invitation->position) }}">
                </div>

                <div class="mb-4">
                    <label class="form-label small">Role</label>
                    <select name="role" class="form-select">
                        @foreach($invitableRoles as $value => $label)
                            <option value="{{ $value }}" @selected(old('role', $invitation->role) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <button class="btn btn-primary w-100" @if(!$isNewUser && !auth()->check()) disabled @endif>
                    <i class="bi bi-check2-circle"></i> Accept Invitation
                </button>
            </form>

        @endif

    </div>
</div>
@endsection
