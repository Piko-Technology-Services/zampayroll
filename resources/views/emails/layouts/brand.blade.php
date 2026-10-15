{{--
    ZamPayroll branded e-mail shell (table layout + inline styles for mail clients).
    Sections: preheader, eyebrow, title, body.  Variables: $companyName (optional)
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title>@yield('title', 'ZamPayroll')</title>
</head>
<body style="margin:0;padding:0;background:#F4F1EA;font-family:'Segoe UI',Helvetica,Arial,sans-serif;color:#14161A;">
<span style="display:none!important;visibility:hidden;opacity:0;height:0;width:0;overflow:hidden;">@yield('preheader')</span>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F4F1EA;padding:32px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:580px;">

                {{-- Brand header --}}
                <tr>
                    <td style="padding:0 0 18px 4px;">
                        <table role="presentation" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="background:#00742D;border-radius:10px;width:40px;height:40px;text-align:center;color:#fff;font-weight:800;font-size:18px;font-family:Georgia,serif;">Z</td>
                                <td style="padding-left:10px;font-size:18px;font-weight:700;font-family:Georgia,'Times New Roman',serif;">
                                    <span style="color:#00742D;">Zam</span><span style="color:#14161A;">Payroll</span>
                                    @isset($companyName)
                                        <div style="font-family:'Segoe UI',Helvetica,Arial,sans-serif;font-size:12px;font-weight:500;color:#6B6F78;">{{ $companyName }}</div>
                                    @endisset
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Card --}}
                <tr>
                    <td style="background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 18px 40px -24px rgba(20,22,26,.35);">
                        {{-- Zambian flag stripe --}}
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="height:6px;background:#00742D;width:70%;"></td>
                                <td style="height:6px;background:#C42926;width:10%;"></td>
                                <td style="height:6px;background:#0E1011;width:10%;"></td>
                                <td style="height:6px;background:#DB7F2F;width:10%;"></td>
                            </tr>
                        </table>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="padding:34px 34px 30px;">
                                    @hasSection('eyebrow')
                                        <div style="font-size:12px;letter-spacing:.14em;text-transform:uppercase;font-weight:700;color:#00742D;margin-bottom:10px;">@yield('eyebrow')</div>
                                    @endif
                                    <h1 style="margin:0 0 14px;font-family:Georgia,'Times New Roman',serif;font-size:26px;line-height:1.25;color:#14161A;">@yield('heading')</h1>
                                    <div style="font-size:15px;line-height:1.65;color:#3A3E46;">
                                        @yield('body')
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td style="padding:20px 8px 0;text-align:center;font-size:12px;line-height:1.6;color:#8A8D96;">
                        @yield('footer_note')
                        <div style="margin-top:8px;">ZamPayroll · HR &amp; Payroll made simple for Zambian businesses</div>
                        <div>This is an automated message from {{ config('mail.from.address') }} — please don't reply.</div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
