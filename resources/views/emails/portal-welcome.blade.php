@extends('emails.layouts.brand')

@section('title', 'Your self-service portal')
@section('preheader', 'Payslips, leave, overtime and loans — all in one place.')
@section('eyebrow', 'Employee self-service')
@section('heading', 'Welcome aboard, ' . $firstName . '! 🎉')

@section('body')
    <p style="margin:0 0 14px;">{{ $grantedBy }} from <strong>{{ $companyName }}</strong> has created your personal
        ZamPayroll portal. From now on you can see your payslips and send requests to HR without paperwork.</p>

    {{-- Credentials card --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#EAF3EA;border:1px solid #CFE4D2;border-radius:14px;margin:6px 0 4px;">
        <tr>
            <td style="padding:18px 20px;">
                <div style="font-size:12px;letter-spacing:.12em;text-transform:uppercase;font-weight:700;color:#00742D;margin-bottom:10px;">Your sign-in details</div>
                <table role="presentation" cellpadding="0" cellspacing="0" style="font-size:14px;">
                    <tr><td style="padding:3px 14px 3px 0;color:#6B6F78;">Portal</td><td style="padding:3px 0;"><a href="{{ $loginUrl }}" style="color:#00742D;font-weight:600;">{{ parse_url($loginUrl, PHP_URL_HOST) }}</a></td></tr>
                    <tr><td style="padding:3px 14px 3px 0;color:#6B6F78;">Email</td><td style="padding:3px 0;font-weight:600;">{{ $user->email }}</td></tr>
                    <tr><td style="padding:3px 14px 3px 0;color:#6B6F78;">Temporary password</td><td style="padding:3px 0;"><code style="font-size:15px;background:#fff;border:1px dashed #00742D;border-radius:6px;padding:3px 8px;">{{ $temporaryPassword }}</code></td></tr>
                </table>
            </td>
        </tr>
    </table>

    @include('emails.partials.button', ['url' => $loginUrl, 'label' => 'Open my portal'])

    <div style="font-size:15px;font-weight:700;margin:4px 0 8px;color:#14161A;">How to get started</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;line-height:1.55;">
        @foreach([
            ['🔑', 'Sign in', 'Use the email and temporary password above. You\'ll be asked to choose your own password straight away.'],
            ['🧾', 'Payslips', 'Open <strong>Payslips</strong> to view or download every finalised payslip as a PDF.'],
            ['🌴', 'Leave', 'Go to <strong>Leave</strong>, pick your dates and we\'ll count the working days and your return date for you.'],
            ['⏱️', 'Overtime', 'Log extra hours under <strong>Overtime</strong> — the estimated payout is shown before you submit.'],
            ['💳', 'Loans', 'Request a salary advance or loan under <strong>Loans</strong> and choose how it is repaid.'],
            ['👤', 'Profile', 'Keep your phone, emergency contact and next of kin up to date under <strong>Profile</strong>.'],
        ] as [$icon, $title, $text])
            <tr>
                <td style="width:34px;vertical-align:top;padding:6px 0;font-size:18px;">{{ $icon }}</td>
                <td style="padding:6px 0;"><strong>{{ $title }}</strong> — {!! $text !!}</td>
            </tr>
        @endforeach
    </table>

    <p style="margin:16px 0 0;font-size:13px;color:#6B6F78;">You'll get an email whenever HR approves or declines one of your requests.
        Forgot your password later? Use <em>Forgot password</em> on the sign-in page.</p>
@endsection

@section('footer_note')
    Keep this email private — it contains your sign-in details.
@endsection
