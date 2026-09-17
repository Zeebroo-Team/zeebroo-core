<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Zeebroo POS — Payment</title>
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
  .icon--cancel  { background: #f59e0b; }
  .icon--failed  { background: #ef4444; }
  h1 { font-size: 19px; margin: 0 0 8px; }
  p  { font-size: 14px; color: #6b7280; line-height: 1.6; margin: 0 0 22px; }
  a.btn {
    display: inline-block; padding: 11px 22px; border-radius: 10px; background: #4e8ef7;
    color: #fff; text-decoration: none; font-weight: 600; font-size: 14px;
  }
  a.btn:hover { background: #2563eb; }
</style>
</head>
<body>
@php
  $copy = [
    'success' => ['icon' => '✓', 'cls' => 'success', 'title' => 'Payment successful', 'text' => 'Your monthly subscription is now active. You can close this window and return to the Zeebroo POS app.'],
    'cancel'  => ['icon' => '!', 'cls' => 'cancel',  'title' => 'Payment canceled', 'text' => 'Your business setup is saved. Return to the app to try the payment again.'],
    'failed'  => ['icon' => '×', 'cls' => 'failed',  'title' => 'We could not confirm your payment', 'text' => 'Please return to the app and try again, or contact support if the problem continues.'],
  ][$status] ?? ['icon' => '×', 'cls' => 'failed', 'title' => 'Something went wrong', 'text' => 'Please return to the app and try again.'];
  $deepLink = 'socibiz://payment?status=' . urlencode($status) . ($paymentId ? '&payment_id=' . urlencode((string) $paymentId) : '');
@endphp
<div class="card">
  <div class="icon icon--{{ $copy['cls'] }}">{{ $copy['icon'] }}</div>
  <h1>{{ $copy['title'] }}</h1>
  <p>{{ $copy['text'] }}</p>
  <a class="btn" id="return-link" href="{{ $deepLink }}">Open Zeebroo POS</a>
</div>
<script>
  // Best-effort auto-return — some browsers require the user gesture on the
  // button above before honoring a custom-protocol navigation.
  window.location.href = document.getElementById('return-link').href;
</script>
</body>
</html>
