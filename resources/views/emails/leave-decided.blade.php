<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, Helvetica, sans-serif; color:#1A2530; background:#F1EFE9; padding:24px;">
    <div style="max-width:520px; margin:0 auto; background:#fff; border:1px solid #E1DACB; border-radius:6px; padding:28px;">
        <div style="font-size:11px; letter-spacing:.1em; text-transform:uppercase; color:#9C7A32; font-weight:700; margin-bottom:6px;">
            Leave Application Update
        </div>
        <h2 style="margin:0 0 16px; font-size:18px; color:{{ $leaveRequest->status === 'approved' ? '#2F6F4E' : '#9B3A2A' }};">
            Your {{ strtolower($leaveRequest->typeName()) }} request has been {{ $leaveRequest->status }}
        </h2>

        <table style="width:100%; font-size:13px; color:#17263A; border-collapse:collapse;">
            <tr>
                <td style="padding:6px 0; color:#7A8595;">Dates</td>
                <td style="padding:6px 0; text-align:right;">
                    {{ $leaveRequest->start_date->format('d M Y') }} &rarr; {{ $leaveRequest->end_date->format('d M Y') }}
                </td>
            </tr>
            <tr>
                <td style="padding:6px 0; color:#7A8595;">Days</td>
                <td style="padding:6px 0; text-align:right; font-weight:700;">{{ $leaveRequest->days }}</td>
            </tr>
            @if($leaveRequest->status === 'cancelled' && (float) $leaveRequest->deducted_days > 0)
            <tr>
                <td style="padding:6px 0; color:#7A8595;">Balance</td>
                <td style="padding:6px 0; text-align:right;">{{ rtrim(rtrim(number_format((float) $leaveRequest->deducted_days, 1), '0'), '.') }} days given back</td>
            </tr>
            @endif
            @if($leaveRequest->cancel_reason)
            <tr>
                <td style="padding:6px 0; color:#7A8595; vertical-align:top;">Reason</td>
                <td style="padding:6px 0; text-align:right;">{{ $leaveRequest->cancel_reason }}</td>
            </tr>
            @endif
            @if($leaveRequest->hr_comment)
            <tr>
                <td style="padding:6px 0; color:#7A8595; vertical-align:top;">HR Comment</td>
                <td style="padding:6px 0; text-align:right;">{{ $leaveRequest->hr_comment }}</td>
            </tr>
            @endif
        </table>
    </div>
</body>
</html>
