<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ __('HR portal') }}</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;-webkit-font-smoothing:antialiased;">
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#f1f5f9;padding:40px 16px;font-family:ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">
        <tr>
            <td align="center">
                <table role="presentation" cellpadding="0" cellspacing="0" width="560" style="max-width:560px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 10px 40px rgba(15,23,42,.08);">
                    <tr>
                        <td style="height:5px;background:linear-gradient(90deg,#4f46e5,#7c3aed,#db2777);"></td>
                    </tr>
                    @if(!empty($letterheadHtml))
                    <tr>
                        <td style="padding:24px 36px 0;">
                            {!! $letterheadHtml !!}
                        </td>
                    </tr>
                    @endif
                    <tr>
                        <td style="padding:32px 36px 8px;">
                            <p style="margin:0;font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#64748b;">{{ config('app.name') }}</p>
                            <h1 style="margin:12px 0 0;font-size:22px;line-height:1.25;font-weight:800;color:#0f172a;">{{ __('Welcome to your HR portal') }}</h1>
                            <p style="margin:16px 0 0;font-size:15px;line-height:1.55;color:#475569;">
                                {{ __('Hi :name,', ['name' => $employee->full_name]) }}
                            </p>
                            <p style="margin:12px 0 0;font-size:15px;line-height:1.55;color:#475569;">
                                @if ($payload['scenario'] === 'email_conflict')
                                    {{ __('Your organisation (:org) added you in HR, but this email is already linked to another employee profile in :app. Please contact HR so they can use a different email or resolve the account.', ['org' => $business->name, 'app' => config('app.name')]) }}
                                @else
                                    {{ __(':org has set up your employee access. Use the link below to open the HR portal and view your profile and requests.', ['org' => $business->name]) }}
                                @endif
                            </p>
                        </td>
                    </tr>
                    @if ($payload['scenario'] !== 'email_conflict' && $payload['scenario'] !== 'noop')
                    <tr>
                        <td style="padding:8px 36px 28px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td align="center" style="border-radius:12px;background:linear-gradient(135deg,#4f46e5,#6366f1);">
                                        <a href="{{ $portalLoginUrl }}" style="display:inline-block;padding:14px 28px;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:12px;">{{ __('Open HR portal') }}</a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:16px 0 0;font-size:13px;line-height:1.5;color:#64748b;">
                                {{ __('Or copy this link into your browser:') }}<br>
                                <span style="word-break:break-all;color:#4f46e5;">{{ $portalLoginUrl }}</span>
                            </p>
                        </td>
                    </tr>
                    @endif

                    @if ($payload['scenario'] === 'new_credentials')
                    <tr>
                        <td style="padding:0 36px 28px;">
                            <p style="margin:0 0 10px;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b;">{{ __('Your temporary password') }}</p>
                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#f8fafc;border-radius:12px;border:1px dashed #cbd5e1;">
                                <tr>
                                    <td style="padding:16px 18px;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:18px;font-weight:700;letter-spacing:.04em;color:#0f172a;text-align:center;">
                                        {{ $payload['temporary_password'] }}
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:14px 0 0;font-size:13px;line-height:1.5;color:#64748b;">
                                {{ __('Sign in with your email (:email) and this password, then change your password from your account settings when you can.', ['email' => $employee->personal_email]) }}
                            </p>
                        </td>
                    </tr>
                    @elseif ($payload['scenario'] === 'existing_password')
                    <tr>
                        <td style="padding:0 36px 28px;">
                            <p style="margin:0;font-size:14px;line-height:1.55;color:#475569;">
                                {{ __('You already have an account with this email. Sign in with your existing password at the link above—no new password was created.') }}
                            </p>
                        </td>
                    </tr>
                    @elseif ($payload['scenario'] === 'existing_google')
                    <tr>
                        <td style="padding:0 36px 28px;">
                            <p style="margin:0;font-size:14px;line-height:1.55;color:#475569;">
                                {{ __('You already have an account with this email. Use “Continue with Google” on the sign-in page to access your HR portal.') }}
                            </p>
                        </td>
                    </tr>
                    @endif

                    <tr>
                        <td style="padding:0 36px 36px;">
                            <p style="margin:0;font-size:12px;line-height:1.5;color:#94a3b8;">
                                {{ __('If you did not expect this message, you can ignore it or contact your HR team.') }}
                            </p>
                        </td>
                    </tr>
                </table>
                <p style="margin:24px 0 0;font-size:11px;color:#94a3b8;text-align:center;">
                    © {{ date('Y') }} {{ config('app.name') }}
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
