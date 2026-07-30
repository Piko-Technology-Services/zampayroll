<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>You're invited to {{ $company->name }}</title>
</head>
<body style="font-family: Arial, sans-serif; background:#f4f5f7; padding:30px; margin:0;">

    <table width="100%" cellpadding="0" cellspacing="0" style="max-width:520px; margin:0 auto; background:#ffffff; border-radius:8px; overflow:hidden; border:1px solid #e5e7eb;">
        <tr>
            <td style="background:#0d6efd; padding:24px; text-align:center;">
                <h2 style="color:#ffffff; margin:0;">{{ $company->name }}</h2>
            </td>
        </tr>
        <tr>
            <td style="padding:28px;">
                <p style="font-size:16px; color:#212529;">Hello,</p>

                <p style="font-size:15px; color:#495057; line-height:1.6;">
                    {{ $invitation->inviter->name ?? 'A company administrator' }} has invited you to join
                    <strong>{{ $company->name }}</strong> on the payroll platform as a
                    <strong>{{ \App\Models\User::INVITABLE_ROLES[$invitation->role] ?? $invitation->role }}</strong>.
                </p>

                <p style="text-align:center; margin:28px 0;">
                    <a href="{{ $acceptUrl }}"
                       style="background:#0d6efd; color:#ffffff; text-decoration:none; padding:12px 28px; border-radius:6px; font-weight:600; display:inline-block;">
                        Accept Invitation
                    </a>
                </p>

                <p style="font-size:14px; color:#495057;">
                    Your company access code is:
                    <strong style="font-family: monospace; font-size:16px;">{{ $company->access_code ?? 'N/A' }}</strong>
                </p>

                <p style="font-size:13px; color:#6c757d;">
                    This invitation link will expire on {{ $invitation->expires_at->format('d M Y, H:i') }}.
                    If you weren't expecting this invitation, you can safely ignore this email.
                </p>

                <hr style="border:none; border-top:1px solid #e9ecef; margin:24px 0;">

                <p style="font-size:12px; color:#adb5bd;">
                    If the button above doesn't work, copy and paste this link into your browser:<br>
                    <span style="word-break:break-all;">{{ $acceptUrl }}</span>
                </p>
            </td>
        </tr>
    </table>

</body>
</html>
