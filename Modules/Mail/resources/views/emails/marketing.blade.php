<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:'Segoe UI',Arial,sans-serif;">
    <div style="max-width:600px;margin:0 auto;padding:32px 16px;">
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:28px 24px;font-size:14px;line-height:1.6;color:#1e293b;">
            {!! $bodyHtml !!}
        </div>
        <div style="text-align:center;font-size:11.5px;line-height:1.6;color:#94a3b8;padding:16px 8px 0;">
            You're receiving this email because you have a {{ config('app.name') }} account.
            @if($unsubscribeUrl)
                <br><a href="{{ $unsubscribeUrl }}" style="color:#64748b;text-decoration:underline;">Unsubscribe from marketing emails</a>
            @endif
        </div>
    </div>
</body>
</html>
