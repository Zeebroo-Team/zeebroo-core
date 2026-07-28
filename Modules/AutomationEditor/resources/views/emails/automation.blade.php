<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
  body { margin: 0; padding: 0; background: #f4f4f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
  .wrap { max-width: 600px; margin: 32px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
  .body { padding: 32px 36px; font-size: 15px; line-height: 1.65; color: #1e293b; }
  .footer { padding: 16px 36px; background: #f8fafc; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; }
</style>
</head>
<body>
  <div class="wrap">
    <div class="body">{!! $bodyHtml !!}</div>
    <div class="footer">This message was sent automatically by an automation workflow.</div>
  </div>
</body>
</html>
