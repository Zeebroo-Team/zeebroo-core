<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="referrer" content="no-referrer">
<title>Zeebroo POS - {{ $success ? __('Signed in') : __('Sign-in failed') }}</title>
<style>
  :root { color-scheme: light dark; }
  body {
    margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
    background: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
    color: #1f2937; padding: 24px; box-sizing: border-box;
  }
  .card {
    max-width: 420px; width: 100%; background: #fff; border-radius: 16px; padding: 36px 32px;
    text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,.08);
  }
  .icon {
    width: 56px; height: 56px; border-radius: 999px; margin: 0 auto 18px; display: flex;
    align-items: center; justify-content: center; font-size: 28px; color: #fff;
  }
  .icon--success { background: #16a34a; }
  .icon--failed  { background: #ef4444; }
  h1 { font-size: 19px; margin: 0 0 8px; }
  p  { font-size: 14px; color: #6b7280; line-height: 1.6; margin: 0 0 22px; }
  a.btn {
    display: inline-block; padding: 11px 22px; border-radius: 10px; background: #4e8ef7;
    color: #fff; text-decoration: none; font-weight: 600; font-size: 14px;
  }
  a.btn:hover { background: #2563eb; }
  @media (prefers-color-scheme: dark) {
    body { background: #111827; color: #f3f4f6; }
    .card { background: #1f2937; box-shadow: none; }
    p { color: #9ca3af; }
  }
</style>
</head>
<body>
<div class="card">
  @if ($success)
    <div class="icon icon--success">&#10003;</div>
    <h1>{{ __('You are signed in') }}</h1>
    <p>{{ __('Return to the Zeebroo POS app to continue. You can close this tab.') }}</p>
  @else
    <div class="icon icon--failed">&times;</div>
    <h1>{{ __('Google sign-in did not complete') }}</h1>
    <p>{{ $error }}</p>
  @endif
  <a class="btn" id="return-link" href="{{ $deepLink }}">{{ __('Open Zeebroo POS') }}</a>
</div>
<script>
  // Best-effort auto-return. Some browsers only open a custom protocol after
  // a click, so the button above stays as the fallback.
  window.location.href = document.getElementById('return-link').href;
</script>
</body>
</html>
