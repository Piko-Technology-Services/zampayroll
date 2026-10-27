@extends('emails.layouts.brand')

@section('title', 'New access')
@section('preheader', "You now have {$user->roleName()} access in ZamPayroll.")
@section('eyebrow', 'Team access')
@section('heading', 'You have ' . $user->roleName() . ' access')

@section('body')
    <p style="margin:0 0 14px;">Hi {{ strtok($user->name, ' ') }},</p>
    <p style="margin:0 0 14px;">{{ $grantedBy }} has given you <strong>{{ $user->roleName() }}</strong> access at <strong>{{ $companyName }}</strong>.
        Sign in with your usual email and password.</p>
    <p style="margin:0 0 14px;padding:12px 16px;background:#F1F4F2;border-radius:10px;">You keep your employee portal too. Use the switch at the top of the screen to move between your
        {{ $user->roleName() }} dashboard and your own payslips@unless($user->isBoardMember()), leave@endunless and loans.</p>
    @include('emails.partials.button', ['url' => route('login', ['email' => $user->email]), 'label' => 'Sign in'])
@endsection
