<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>New Payment Submission</title>
</head>
<body style="margin:0;padding:0;background:#FAF7F0;font-family:Arial,Helvetica,sans-serif;color:#14161A;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#FAF7F0;padding:32px 0;">
    <tr>
      <td align="center">
        <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="background:#FFFFFF;border:1px solid #E7E2D6;border-radius:14px;overflow:hidden;">
          <tr>
            <td style="background:#0E1011;padding:22px 28px;">
              <span style="font-size:18px;font-weight:bold;color:#FFFFFF;">
                <span style="color:#2F7D40;">zam</span>payroll
              </span>
            </td>
          </tr>
          <tr>
            <td style="padding:28px 28px 8px;">
              <p style="margin:0 0 4px;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#DB7F2F;font-weight:bold;">New Payment Submission &middot; Needs Verification</p>
              <h1 style="margin:0 0 18px;font-size:20px;color:#14161A;">{{ $payment->company_name }} says they've paid</h1>
            </td>
          </tr>
          <tr>
            <td style="padding:0 28px 8px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                <tr>
                  <td style="padding:10px 0;border-bottom:1px solid #E7E2D6;font-size:13px;color:#8A8D96;width:150px;">Company</td>
                  <td style="padding:10px 0;border-bottom:1px solid #E7E2D6;font-size:14px;color:#14161A;font-weight:600;">{{ $payment->company_name }}</td>
                </tr>
                <tr>
                  <td style="padding:10px 0;border-bottom:1px solid #E7E2D6;font-size:13px;color:#8A8D96;">Contact email</td>
                  <td style="padding:10px 0;border-bottom:1px solid #E7E2D6;font-size:14px;color:#14161A;">
                    <a href="mailto:{{ $payment->contact_email }}" style="color:#2F7D40;text-decoration:none;">{{ $payment->contact_email }}</a>
                  </td>
                </tr>
                <tr>
                  <td style="padding:10px 0;border-bottom:1px solid #E7E2D6;font-size:13px;color:#8A8D96;">Sent from (phone)</td>
                  <td style="padding:10px 0;border-bottom:1px solid #E7E2D6;font-size:14px;color:#14161A;">{{ $payment->contact_phone }}</td>
                </tr>
                <tr>
                  <td style="padding:10px 0;border-bottom:1px solid #E7E2D6;font-size:13px;color:#8A8D96;">Service paid for</td>
                  <td style="padding:10px 0;border-bottom:1px solid #E7E2D6;font-size:14px;color:#14161A;">{{ $payment->service }}</td>
                </tr>
                <tr>
                  <td style="padding:10px 0;border-bottom:1px solid #E7E2D6;font-size:13px;color:#8A8D96;">Amount stated</td>
                  <td style="padding:10px 0;border-bottom:1px solid #E7E2D6;font-size:14px;color:#14161A;">{{ $payment->amount ? 'K ' . number_format((float) $payment->amount, 2) : 'Not provided' }}</td>
                </tr>
                <tr>
                  <td style="padding:10px 0;border-bottom:1px solid #E7E2D6;font-size:13px;color:#8A8D96;">Method</td>
                  <td style="padding:10px 0;border-bottom:1px solid #E7E2D6;font-size:14px;color:#14161A;">Mobile Money (MTN)</td>
                </tr>
              </table>
            </td>
          </tr>
          @if($payment->comment)
          <tr>
            <td style="padding:18px 28px 4px;">
              <p style="margin:0 0 6px;font-size:13px;color:#8A8D96;">Comment</p>
              <p style="margin:0;font-size:14px;line-height:1.6;color:#14161A;background:#FDF1E3;padding:14px 16px;border-radius:10px;">{{ $payment->comment }}</p>
            </td>
          </tr>
          @endif
          <tr>
            <td style="padding:20px 28px 6px;">
              <p style="margin:0;font-size:13px;color:#8A8D96;">The proof of payment is attached to this email. Please verify the transaction in the MTN Mobile Money merchant account before confirming with the customer.</p>
            </td>
          </tr>
          <tr>
            <td style="padding:20px 28px 30px;">
              <a href="mailto:{{ $payment->contact_email }}" style="display:inline-block;background:#2F7D40;color:#FFFFFF;text-decoration:none;font-weight:bold;font-size:14px;padding:12px 22px;border-radius:9px;">Reply to {{ $payment->company_name }}</a>
            </td>
          </tr>
          <tr>
            <td style="padding:16px 28px;border-top:1px solid #E7E2D6;">
              <p style="margin:0;font-size:12px;color:#8A8D96;">Submitted {{ $payment->created_at->format('d M Y, H:i') }} &middot; Payment #{{ $payment->id }}</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
