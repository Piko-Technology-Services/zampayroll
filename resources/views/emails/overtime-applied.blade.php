<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, Helvetica, sans-serif; color:#1A2530; background:#F1EFE9; padding:24px;">
    <div style="max-width:520px; margin:0 auto; background:#fff; border:1px solid #E1DACB; border-radius:6px; padding:28px;">
        <div style="font-size:11px; letter-spacing:.1em; text-transform:uppercase; color:#9C7A32; font-weight:700; margin-bottom:6px;">
            Overtime Application
        </div>
        <h2 style="margin:0 0 16px; font-size:18px; color:#17263A;">
            {{ $overtimeRequest->employee->first_name }} {{ $overtimeRequest->employee->last_name }} has applied for overtime
        </h2>

        <table style="width:100%; font-size:13px; color:#17263A; border-collapse:collapse;">
            <tr><td style="padding:6px 0; color:#7A8595;">Employee ID</td><td style="padding:6px 0; text-align:right;">{{ $overtimeRequest->employee->employee_id }}</td></tr>
            <tr><td style="padding:6px 0; color:#7A8595;">Date</td><td style="padding:6px 0; text-align:right;">{{ $overtimeRequest->date->format('d M Y') }}</td></tr>
            <tr><td style="padding:6px 0; color:#7A8595;">Time</td><td style="padding:6px 0; text-align:right;">{{ \Carbon\Carbon::parse($overtimeRequest->start_time)->format('H:i') }} &rarr; {{ \Carbon\Carbon::parse($overtimeRequest->end_time)->format('H:i') }}</td></tr>
            <tr><td style="padding:6px 0; color:#7A8595;">Hours</td><td style="padding:6px 0; text-align:right; font-weight:700;">{{ $overtimeRequest->hours }}</td></tr>
            <tr><td style="padding:6px 0; color:#7A8595;">Type</td><td style="padding:6px 0; text-align:right;">{{ \App\Models\OvertimeRequest::TYPES[$overtimeRequest->type]['label'] ?? $overtimeRequest->type }} ({{ $overtimeRequest->rate_multiplier }}x)</td></tr>
            @if($overtimeRequest->amount)
            <tr><td style="padding:6px 0; color:#7A8595;">Estimated Amount</td><td style="padding:6px 0; text-align:right; font-weight:700;">K {{ number_format($overtimeRequest->amount, 2) }}</td></tr>
            @endif
            @if($overtimeRequest->comment)
            <tr><td style="padding:6px 0; color:#7A8595; vertical-align:top;">Comment</td><td style="padding:6px 0; text-align:right;">{{ $overtimeRequest->comment }}</td></tr>
            @endif
        </table>

        <div style="margin-top:22px;">
            <a href="{{ route('overtime.dashboard') }}"
               style="display:inline-block; background:#17263A; color:#fff; text-decoration:none; padding:10px 18px; border-radius:5px; font-size:13px; font-weight:600;">
                Review in Overtime Dashboard
            </a>
        </div>
    </div>
</body>
</html>
