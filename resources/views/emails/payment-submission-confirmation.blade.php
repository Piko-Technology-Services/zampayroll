<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>We've received your payment confirmation</title>
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
            <td style="padding:32px 28px 8px;">
              <p style="margin:0 0 4px;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#2F7D40;font-weight:bold;">Payment confirmation received</p>
              <h1 style="margin:0 0 16px;font-size:21px;color:#14161A;">Thanks &mdash; we've got your proof of payment.</h1>
              <p style="margin:0 0 16px;font-size:14px;line-height:1.7;color:#4B4F58;">
                We've received your Mobile Money payment confirmation for <strong>{{ $payment->service }}</strong> on behalf of <strong>{{ $payment->company_name }}</strong>.
                Our team will verify the transaction and follow up at {{ $payment->contact_email }} once it's confirmed.
              </p>
            </td>
          </tr>
          <tr>
            <td style="padding:0 28px 8px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;background:#EAF3EA;border-radius:12px;">
                <tr>
                  <td style="padding:16px 20px;font-size:13px;color:#8A8D96;width:140px;">Reference</td>
                  <td style="padding:16px 20px;font-size:14px;color:#14161A;font-weight:600;">Payment #{{ $payment->id }}</td>
                </tr>
                <tr>
                  <td style="padding:0 20px 16px;font-size:13px;color:#8A8D96;">Service</td>
                  <td style="padding:0 20px 16px;font-size:14px;color:#14161A;">{{ $payment->service }}</td>
                </tr>
                @if($payment->amount)
                <tr>
                  <td style="padding:0 20px 16px;font-size:13px;color:#8A8D96;">Amount stated</td>
                  <td style="padding:0 20px 16px;font-size:14px;color:#14161A;">K {{ number_format((float) $payment->amount, 2) }}</td>
                </tr>
                @endif
                <tr>
                  <td style="padding:0 20px 16px;font-size:13px;color:#8A8D96;">Method</td>
                  <td style="padding:0 20px 16px;font-size:14px;color:#14161A;">Mobile Money (MTN)</td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td style="padding:22px 28px 6px;">
              <p style="margin:0;font-size:13px;color:#8A8D96;">Keep this reference number handy if you need to follow up. If anything about your payment changes, just reply to this email or reach us at</p>
              <p style="margin:6px 0 0;font-size:14px;">
                <a href="mailto:info@zampayroll.com" style="color:#2F7D40;text-decoration:none;font-weight:600;">info@zampayroll.com</a>
                &nbsp;&middot;&nbsp;
                <a href="tel:+260776136965" style="color:#2F7D40;text-decoration:none;font-weight:600;">+260 776 136 965</a>
              </p>
            </td>
          </tr>
          <tr>
            <td style="padding:26px 28px;border-top:1px solid #E7E2D6;">
              <p style="margin:0;font-size:12px;color:#8A8D96;">&copy; {{ date('Y') }} ZamPayroll &middot; Lusaka, Copperbelt, Zambia</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
