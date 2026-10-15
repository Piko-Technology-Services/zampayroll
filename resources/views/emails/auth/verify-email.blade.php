@extends('emails.layouts.brand')

@section('title', 'Confirm your email')
@section('preheader', 'One click and your ZamPayroll workspace is live.')
@section('eyebrow', 'Almost there')
@section('heading', 'Confirm your email, ' . $firstName . ' ✉️')

@section('body')
    <p style="margin:0 0 12px;">Thanks for choosing ZamPayroll for <strong>{{ $companyName }}</strong>! Please confirm that
        <strong>{{ $email }}</strong> is your address so we can finish setting up your workspace.</p>

    @if($dedicated)
        <p style="margin:0 0 12px;">Because your team has more than {{ $limit }} people, we'll create your own
            <strong>dedicated workspace</strong> at <strong>{{ $host }}</strong> as soon as you confirm.</p>
    @endif

    @include('emails.partials.button', ['url' => $url, 'label' => 'Confirm my email'])

    <p style="margin:0 0 6px;font-size:13px;color:#6B6F78;">The link expires in {{ $expires }} minutes. If the button doesn't work, paste this into your browser:</p>
    <p style="margin:0;font-size:12px;word-break:break-all;"><a href="{{ $url }}" style="color:#00742D;">{{ $url }}</a></p>
@endsection

@section('footer_note')
    Didn't sign up for ZamPayroll? You can safely ignore this email.
@endsection
