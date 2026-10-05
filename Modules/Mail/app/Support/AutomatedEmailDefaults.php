<?php

namespace Modules\Mail\Support;

use Modules\Mail\Models\AdminAutomatedEmail;

/**
 * Factory-default subject + body for each automated email. Bodies are rendered inside
 * the shared email layout (mail::emails.marketing), so they only hold the card content.
 * Inline styles only — email clients ignore <style> blocks.
 */
final class AutomatedEmailDefaults
{
    private const BRAND = '<p style="margin:0 0 28px;font-size:13px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#ca8a04;">{{app_name}}</p>';

    private const H1 = 'margin:0 0 14px;font-size:22px;line-height:1.3;font-weight:700;color:#0f172a;';

    private const P = 'margin:0 0 16px;color:#475569;';

    private const BUTTON = 'display:inline-block;padding:12px 26px;background:#0f172a;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:600;';

    private const HR = '<hr style="border:none;border-top:1px solid #e2e8f0;margin:28px 0 20px;">';

    private const SIGN = '<p style="margin:0;color:#475569;">Thanks,<br>The {{app_name}} team</p>';

    /**
     * @return array{subject: string, body: string}
     */
    public static function for(string $key): array
    {
        return match ($key) {
            AdminAutomatedEmail::WELCOME => self::welcome(),
            AdminAutomatedEmail::PASSWORD_RESET => self::passwordReset(),
            AdminAutomatedEmail::INACTIVITY => self::inactivity(),
            AdminAutomatedEmail::REPORT => self::report(),
            AdminAutomatedEmail::NEW_RELEASE => self::newRelease(),
        };
    }

    private static function button(string $href, string $label): string
    {
        return '<p style="margin:28px 0;"><a href="'.$href.'" style="'.self::BUTTON.'">'.$label.'</a></p>';
    }

    private static function welcome(): array
    {
        return [
            'subject' => 'Welcome to {{app_name}}, {{first_name}}',
            'body' => self::BRAND
                .'<h1 style="'.self::H1.'">Welcome aboard, {{first_name}}</h1>'
                .'<p style="'.self::P.'">Your {{app_name}} account is ready. You can now manage sales, inventory, customers and your team from one place.</p>'
                .'<p style="'.self::P.'">A good place to start:</p>'
                .'<ul style="margin:0 0 16px;padding-left:20px;color:#475569;">'
                .'<li style="margin-bottom:6px;">Set up your business profile</li>'
                .'<li style="margin-bottom:6px;">Add your products and customers</li>'
                .'<li>Invite your team members</li>'
                .'</ul>'
                .self::button('{{dashboard_url}}', 'Go to your dashboard')
                .self::HR
                .'<p style="margin:0 0 16px;font-size:13px;color:#64748b;">You signed up with {{email}}. If you have any questions, just reply to this email.</p>'
                .self::SIGN,
        ];
    }

    private static function passwordReset(): array
    {
        return [
            'subject' => 'Your {{app_name}} password reset code',
            'body' => self::BRAND
                .'<h1 style="'.self::H1.'">Reset your password</h1>'
                .'<p style="'.self::P.'">Hi {{first_name}}, we received a request to reset the password for your account. Use this code to continue:</p>'
                .'<p style="margin:24px 0;text-align:center;"><span style="display:inline-block;padding:14px 28px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;font-family:Consolas, Menlo, monospace;font-size:30px;font-weight:700;letter-spacing:8px;color:#0f172a;">{{otp_code}}</span></p>'
                .'<p style="'.self::P.'">This code expires in {{expiry_minutes}} minutes. For your security, never share it with anyone.</p>'
                .self::HR
                .'<p style="margin:0 0 16px;font-size:13px;color:#64748b;">Didn\'t request this? You can safely ignore this email — your password won\'t change.</p>'
                .self::SIGN,
        ];
    }

    private static function inactivity(): array
    {
        return [
            'subject' => 'We miss you at {{app_name}}, {{first_name}}',
            'body' => self::BRAND
                .'<h1 style="'.self::H1.'">It\'s been a little while</h1>'
                .'<p style="'.self::P.'">Hi {{first_name}}, we noticed you haven\'t signed in for {{days_inactive}} days. Your business data is safe and waiting for you.</p>'
                .'<p style="'.self::P.'">Pick up where you left off — check today\'s sales, review your stock and keep your records up to date.</p>'
                .self::button('{{login_url}}', 'Sign in to {{app_name}}')
                .self::HR
                .self::SIGN,
        ];
    }

    private static function report(): array
    {
        $stat = 'padding:16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;';
        $label = 'font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#64748b;';
        $value = 'font-size:24px;font-weight:700;color:#0f172a;';

        return [
            'subject' => 'Your {{app_name}} report — {{period_label}}',
            'body' => self::BRAND
                .'<h1 style="'.self::H1.'">Your business summary</h1>'
                .'<p style="'.self::P.'">Hi {{first_name}}, here is how your business performed for <strong>{{period_label}}</strong>.</p>'
                .'<table width="100%" cellpadding="0" cellspacing="0" style="margin:8px 0 24px;border-collapse:separate;">'
                .'<tr>'
                .'<td width="50%" style="'.$stat.'"><div style="'.$label.'">Sales</div><div style="'.$value.'">{{sales_count}}</div></td>'
                .'<td width="12" style="font-size:0;">&nbsp;</td>'
                .'<td width="50%" style="'.$stat.'"><div style="'.$label.'">New customers</div><div style="'.$value.'">{{new_customers}}</div></td>'
                .'</tr>'
                .'</table>'
                .'{{report_summary}}'
                .self::button('{{dashboard_url}}', 'View full reports')
                .self::HR
                .self::SIGN,
        ];
    }

    private static function newRelease(): array
    {
        return [
            'subject' => '{{release_app}} {{release_version}} is now available',
            'body' => self::BRAND
                .'<h1 style="'.self::H1.'">{{release_app}} {{release_version}} is here</h1>'
                .'<p style="'.self::P.'">Hi {{first_name}}, a new version of {{release_app}} was released on {{release_date}}. Here\'s what\'s new:</p>'
                .'{{release_notes}}'
                .self::button('{{download_url}}', 'Download the update')
                .'<p style="margin:0 0 16px;font-size:13px;color:#64748b;">If you already have the app installed, it will offer the update the next time you open it.</p>'
                .self::HR
                .self::SIGN,
        ];
    }
}
