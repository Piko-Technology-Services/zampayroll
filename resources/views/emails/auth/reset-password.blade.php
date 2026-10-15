@extends('emails.layouts.brand')

@section('title', 'Reset your password')
@section('preheader', 'Use this link to choose a new ZamPayroll password.')
@section('eyebrow', 'Password reset')
@section('heading', 'Let\'s get you back in, ' . $firstName . ' 🔐')

@section('body')
    <p style="margin:0 0 12px;">We received a request to reset the password for <strong>{{ $email }}</strong>
        @if($workspace) on the <strong>{{ $workspace }}</strong> workspace @endif.</p>

    @include('emails.partials.button', ['url' => $url, 'label' => 'Choose a new password'])

    <p style="margin:0 0 6px;font-size:13px;color:#6B6F78;">This link expires in {{ $expires }} minutes and can only be used once.</p>
    <p style="margin:0;font-size:12px;word-break:break-all;"><a href="{{ $url }}" style="color:#00742D;">{{ $url }}</a></p>
@endsection

@section('footer_note')
    Didn't ask for this? Ignore this email — your password stays the same.
@endsection
