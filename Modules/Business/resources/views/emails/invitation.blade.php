{{-- Card content only: rendered inside mail::emails.marketing. Inline styles only. --}}
<p style="margin:0 0 28px;font-size:13px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#ca8a04;">{{ $appName }}</p>
<h1 style="margin:0 0 14px;font-size:22px;line-height:1.3;font-weight:700;color:#0f172a;">{{ $name ? "Hi {$name}, you're" : "You're" }} invited to join {{ $businessName }}</h1>
<p style="margin:0 0 16px;color:#475569;">{{ $inviterName }} has invited you to join <strong>{{ $businessName }}</strong> on {{ $appName }}.</p>

<table width="100%" cellpadding="0" cellspacing="0" style="margin:8px 0 8px;border-collapse:separate;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;">
    <tr>
        <td style="padding:12px 16px;font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#64748b;width:110px;">Business</td>
        <td style="padding:12px 16px;font-size:14px;font-weight:600;color:#0f172a;">{{ $businessName }}</td>
    </tr>
    <tr>
        <td style="padding:12px 16px;font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#64748b;border-top:1px solid #e2e8f0;">Role</td>
        <td style="padding:12px 16px;font-size:14px;font-weight:600;color:#0f172a;border-top:1px solid #e2e8f0;">{{ $roleName }}</td>
    </tr>
    @if($name)
    <tr>
        <td style="padding:12px 16px;font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#64748b;border-top:1px solid #e2e8f0;">Name</td>
        <td style="padding:12px 16px;font-size:14px;color:#0f172a;border-top:1px solid #e2e8f0;">{{ $name }}</td>
    </tr>
    @endif
    <tr>
        <td style="padding:12px 16px;font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#64748b;border-top:1px solid #e2e8f0;">Email</td>
        <td style="padding:12px 16px;font-size:14px;color:#0f172a;border-top:1px solid #e2e8f0;">{{ $email }}</td>
    </tr>
</table>

@if($hasAccount)
    <p style="margin:16px 0;color:#475569;">You already have a {{ $appName }} account. Accept the invitation and this business will appear in your business list the next time you sign in.</p>
    <p style="margin:28px 0;"><a href="{{ $url }}" style="display:inline-block;padding:12px 26px;background:#0f172a;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:600;">Accept invitation</a></p>
@else
    <p style="margin:16px 0;color:#475569;">Click below to create your password. You can then sign in to {{ $appName }} on the web, desktop and mobile apps.</p>
    <p style="margin:28px 0;"><a href="{{ $url }}" style="display:inline-block;padding:12px 26px;background:#0f172a;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:600;">Accept &amp; set up account</a></p>
@endif

<p style="margin:0 0 16px;font-size:13px;color:#64748b;">This invitation expires in {{ $expiryDays }} days. If the button doesn't work, copy this link into your browser:<br><a href="{{ $url }}" style="color:#2563eb;word-break:break-all;">{{ $url }}</a></p>
<hr style="border:none;border-top:1px solid #e2e8f0;margin:28px 0 20px;">
<p style="margin:0 0 16px;font-size:13px;color:#64748b;">Weren't expecting this? You can safely ignore this email — nothing happens unless you accept.</p>
<p style="margin:0;color:#475569;">Thanks,<br>The {{ $appName }} team</p>
