<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>We've received your demo request</title>
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
              <p style="margin:0 0 4px;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#2F7D40;font-weight:bold;">Demo request received</p>
              <h1 style="margin:0 0 16px;font-size:21px;color:#14161A;">Thanks, {{ $demoRequest->full_name }} — we've got it.</h1>
              <p style="margin:0 0 16px;font-size:14px;line-height:1.7;color:#4B4F58;">
                We've received your request for a ZamPayroll demo on behalf of <strong>{{ $demoRequest->company_name }}</strong>.
                A member of our team will reach out to you at {{ $demoRequest->email }}{{ $demoRequest->phone ? ' or ' . $demoRequest->phone : '' }} within one business day to schedule a time that works for you.
              </p>
            </td>
          </tr>
          <tr>
            <td style="padding:0 28px 8px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#EAF3EA;border-radius:12px;">
                <tr>
                  <td style="padding:18px 20px;">
                    <p style="margin:0 0 10px;font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:#1F5A2C;font-weight:bold;">What happens next</p>
                    <table role="presentation" cellpadding="0" cellspacing="0">
                      <tr>
                        <td style="padding:4px 0;font-size:13px;color:#1F5A2C;vertical-align:top;">1.</td>
                        <td style="padding:4px 0 4px 8px;font-size:13px;color:#14161A;">We review your request and match you with the right specialist.</td>
                      </tr>
                      <tr>
                        <td style="padding:4px 0;font-size:13px;color:#1F5A2C;vertical-align:top;">2.</td>
                        <td style="padding:4px 0 4px 8px;font-size:13px;color:#14161A;">We contact you to confirm a convenient date and time.</td>
                      </tr>
                      <tr>
                        <td style="padding:4px 0;font-size:13px;color:#1F5A2C;vertical-align:top;">3.</td>
                        <td style="padding:4px 0 4px 8px;font-size:13px;color:#14161A;">We walk you through ZamPayroll live, tailored to {{ $demoRequest->company_name }}.</td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td style="padding:22px 28px 6px;">
              <p style="margin:0;font-size:13px;color:#8A8D96;">In the meantime, if anything changes or you have questions, just reply to this email or reach us at</p>
              <p style="margin:6px 0 0;font-size:14px;">
                <a href="mailto:hello@zampayroll.com" style="color:#2F7D40;text-decoration:none;font-weight:600;">hello@zampayroll.com</a>
              </p>
            </td>
          </tr>
          <tr>
            <td style="padding:26px 28px;border-top:1px solid #E7E2D6;">
              <p style="margin:0;font-size:12px;color:#8A8D96;">&copy; {{ date('Y') }} ZamPayroll &middot; Lusaka, Zambia</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
