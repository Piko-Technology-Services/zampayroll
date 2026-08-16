<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, Helvetica, sans-serif; color:#1A2530; background:#F1EFE9; padding:24px;">
    <div style="max-width:520px; margin:0 auto; background:#fff; border:1px solid #E1DACB; border-radius:6px; padding:28px;">
        <div style="font-size:11px; letter-spacing:.1em; text-transform:uppercase; color:#9C7A32; font-weight:700; margin-bottom:6px;">
            Loan Application
        </div>
        <h2 style="margin:0 0 16px; font-size:18px; color:#17263A;">
            {{ $loanRequest->employee->first_name }} {{ $loanRequest->employee->last_name }} has applied for a loan
        </h2>

        <table style="width:100%; font-size:13px; color:#17263A; border-collapse:collapse;">
            <tr><td style="padding:6px 0; color:#7A8595;">Employee ID</td><td style="padding:6px 0; text-align:right;">{{ $loanRequest->employee->employee_id }}</td></tr>
            <tr><td style="padding:6px 0; color:#7A8595;">Amount</td><td style="padding:6px 0; text-align:right; font-weight:700;">K {{ number_format($loanRequest->amount, 2) }}</td></tr>
            <tr><td style="padding:6px 0; color:#7A8595;">Payment Plan</td><td style="padding:6px 0; text-align:right;">{{ \App\Models\LoanRequest::PAYMENT_PLANS[$loanRequest->payment_plan] ?? $loanRequest->payment_plan }}</td></tr>
            @if($loanRequest->payment_plan_note)
            <tr><td style="padding:6px 0; color:#7A8595;">Plan Detail</td><td style="padding:6px 0; text-align:right;">{{ $loanRequest->payment_plan_note }}</td></tr>
            @endif
            @if($loanRequest->reason)
            <tr><td style="padding:6px 0; color:#7A8595; vertical-align:top;">Reason</td><td style="padding:6px 0; text-align:right;">{{ $loanRequest->reason }}</td></tr>
            @endif
            @if(!empty($loanRequest->documents))
            <tr><td style="padding:6px 0; color:#7A8595;">Documents</td><td style="padding:6px 0; text-align:right;">{{ count($loanRequest->documents) }} file(s) attached</td></tr>
            @endif
        </table>

        <div style="margin-top:22px;">
            <a href="{{ route('loan.dashboard') }}"
               style="display:inline-block; background:#17263A; color:#fff; text-decoration:none; padding:10px 18px; border-radius:5px; font-size:13px; font-weight:600;">
                Review in Loan Dashboard
            </a>
        </div>
    </div>
</body>
</html>
