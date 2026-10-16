@extends('emails.layouts.brand')

@section('title', 'Your company code')
@section('preheader', 'Here is the company code you sign in with.')
@section('eyebrow', 'Sign-in help')
@section('heading', 'Your company code')

@section('body')
    <p style="margin:0 0 14px;">You asked for the company code you use to sign in to ZamPayroll:</p>
    @foreach($companies as $c)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#EAF3EA;border-radius:12px;margin:0 0 10px;">
            <tr><td style="padding:14px 18px;">
                <div style="font-size:13px;color:#4E5A52;">{{ $c->name }}</div>
                <div style="font-family:Georgia,serif;font-size:26px;font-weight:700;letter-spacing:.06em;color:#0A3B22;">{{ $c->company_code }}</div>
            </td></tr>
        </table>
    @endforeach
    @include('emails.partials.button', ['url' => route('login', ['code' => $companies->first()->company_code]), 'label' => 'Sign in now'])
@endsection

@section('footer_note')
    Didn't ask for this? You can ignore this email — your account is unchanged.
@endsection
