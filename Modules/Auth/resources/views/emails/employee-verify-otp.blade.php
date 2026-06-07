<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ __('Your verification code') }}</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;-webkit-font-smoothing:antialiased;">
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#f1f5f9;padding:40px 16px;font-family:ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">
        <tr>
            <td align="center">
                <table role="presentation" cellpadding="0" cellspacing="0" width="520" style="max-width:520px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 10px 40px rgba(15,23,42,.08);">
                    <tr>
                        <td style="height:5px;background:linear-gradient(90deg,#000000,#404040);"></td>
                    </tr>
                    <tr>
                        <td style="padding:32px 36px 8px;">
                            <p style="margin:0;font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#64748b;">{{ config('app.name') }}</p>
                            <h1 style="margin:12px 0 0;font-size:22px;line-height:1.25;font-weight:800;color:#0f172a;">{{ __('Your verification code') }}</h1>
                            <p style="margin:16px 0 0;font-size:15px;line-height:1.55;color:#475569;">
                                {{ __('Use this code to verify your email and continue creating your customer account.') }}
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 36px 8px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#f8fafc;border-radius:12px;border:2px dashed #cbd5e1;">
                                <tr>
                                    <td style="padding:22px 18px;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:34px;font-weight:800;letter-spacing:.3em;color:#0f172a;text-align:center;">
                                        {{ $otp }}
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:14px 0 0;font-size:13px;line-height:1.5;color:#64748b;text-align:center;">
                                {{ __('This code expires in 10 minutes.') }}
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 36px 32px;">
                            <p style="margin:0;font-size:12px;line-height:1.5;color:#94a3b8;">
                                {{ __('If you did not request this code, you can safely ignore this email.') }}
                            </p>
                        </td>
                    </tr>
                </table>
                <p style="margin:24px 0 0;font-size:11px;color:#94a3b8;text-align:center;">
                    &copy; {{ date('Y') }} {{ config('app.name') }}
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
