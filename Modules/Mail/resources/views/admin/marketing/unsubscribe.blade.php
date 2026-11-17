<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $done ? 'Unsubscribed' : 'Unsubscribe' }} · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    <style>
        body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:16px;box-sizing:border-box;background:#f5f5f4;font-family:Inter,system-ui,-apple-system,'Segoe UI',sans-serif;color:#0a0a0a;}
        .card{width:100%;max-width:420px;background:#fff;border:1px solid #e7e5e4;border-radius:18px;padding:32px 28px;text-align:center;box-shadow:0 10px 30px rgba(0,0,0,.05);}
        .icon{width:56px;height:56px;border-radius:16px;margin:0 auto 16px;display:grid;place-items:center;font-size:22px;background:#fef9c3;color:#ca8a04;}
        .icon.ok{background:#dcfce7;color:#16a34a;}
        h1{margin:0 0 8px;font-size:20px;}
        p{margin:0 0 20px;font-size:14px;line-height:1.55;color:#57534e;}
        button{border:0;border-radius:10px;padding:12px 20px;background:#171717;color:#fff;font-size:14px;font-weight:600;cursor:pointer;width:100%;font-family:inherit;}
        button:hover{background:#facc15;color:#0a0a0a;}
        .email{font-weight:600;color:#0a0a0a;}
    </style>
</head>
<body>
    <div class="card">
        @if($done)
            <div class="icon ok"><i class="fa fa-circle-check"></i></div>
            <h1>You're unsubscribed</h1>
            <p><span class="email">{{ $user->email }}</span> won't receive marketing emails from {{ config('app.name') }} anymore. You'll still get important account and billing emails.</p>
        @else
            <div class="icon"><i class="fa fa-envelope-circle-check"></i></div>
            <h1>Unsubscribe from marketing emails?</h1>
            <p>Stop sending news and promotional emails to <span class="email">{{ $user->email }}</span>. Account and billing emails are not affected.</p>
            <form method="POST" action="{{ $actionUrl }}">
                <button type="submit">Unsubscribe</button>
            </form>
        @endif
    </div>
</body>
</html>
