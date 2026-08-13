<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, Helvetica, sans-serif; color:#1A2530; background:#F1EFE9; padding:24px;">
    <div style="max-width:520px; margin:0 auto; background:#fff; border:1px solid #E1DACB; border-radius:6px; padding:28px;">
        <div style="font-size:11px; letter-spacing:.1em; text-transform:uppercase; color:#9C7A32; font-weight:700; margin-bottom:6px;">
            Leave Application
        </div>
        <h2 style="margin:0 0 16px; font-size:18px; color:#17263A;">
            {{ $leaveRequest->employee->first_name }} {{ $leaveRequest->employee->last_name }} has applied for leave
        </h2>

        <table style="width:100%; font-size:13px; color:#17263A; border-collapse:collapse;">
            <tr>
                <td style="padding:6px 0; color:#7A8595;">Employee ID</td>
                <td style="padding:6px 0; text-align:right;">{{ $leaveRequest->employee->employee_id }}</td>
            </tr>
            <tr>
                <td style="padding:6px 0; color:#7A8595;">Leave Type</td>
                <td style="padding:6px 0; text-align:right;">{{ \App\Models\LeaveRequest::TYPES[$leaveRequest->leave_type] ?? $leaveRequest->leave_type }}</td>
            </tr>
            <tr>
                <td style="padding:6px 0; color:#7A8595;">Dates</td>
                <td style="padding:6px 0; text-align:right;">
                    {{ $leaveRequest->start_date->format('d M Y') }} &rarr; {{ $leaveRequest->end_date->format('d M Y') }}
                </td>
            </tr>
            @if($leaveRequest->return_date)
            <tr>
                <td style="padding:6px 0; color:#7A8595;">Return Date</td>
                <td style="padding:6px 0; text-align:right;">{{ $leaveRequest->return_date->format('d M Y') }}</td>
            </tr>
            @endif
            <tr>
                <td style="padding:6px 0; color:#7A8595;">Days Requested</td>
                <td style="padding:6px 0; text-align:right; font-weight:700;">{{ $leaveRequest->days }}</td>
            </tr>
            @if($leaveRequest->reason)
            <tr>
                <td style="padding:6px 0; color:#7A8595; vertical-align:top;">Comment</td>
                <td style="padding:6px 0; text-align:right;">{{ $leaveRequest->reason }}</td>
            </tr>
            @endif
            @if(!empty($leaveRequest->documents))
            <tr>
                <td style="padding:6px 0; color:#7A8595;">Documents</td>
                <td style="padding:6px 0; text-align:right;">{{ count($leaveRequest->documents) }} file(s) attached</td>
            </tr>
            @endif
        </table>

        <div style="margin-top:22px;">
            <a href="{{ route('leave.dashboard') }}"
               style="display:inline-block; background:#17263A; color:#fff; text-decoration:none; padding:10px 18px; border-radius:5px; font-size:13px; font-weight:600;">
                Review in Leave Dashboard
            </a>
        </div>
    </div>
</body>
</html>
