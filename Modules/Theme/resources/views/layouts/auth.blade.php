<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? __('Sign in') }} · {{ config('app.name') }}</title>
    <link rel="icon" type="image/x-icon" href="/favicons/favicon.ico">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicons/favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicons/favicon-32x32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png">
    <link rel="manifest" href="/favicons/site.webmanifest">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        /* Palette & type mirror zeebroo.com: zebra monochrome + one yellow accent.
           Radius rule: buttons & chips = pill, cards = 24px, inner elements = 14px. */
        :root{
            --paper:#F3F3F1;
            --paper-2:#E8E8E4;
            --ink:#121212;
            --ink-2:#2F2F2F;
            --zee:#FFCB2F;
            --line:rgba(18,18,18,.11);
            --r-card:24px;
            --r-in:14px;
            --display:"Bricolage Grotesque",system-ui,sans-serif;
            --body:"Geist",system-ui,-apple-system,"Segoe UI",sans-serif;
            --mono:"Geist Mono",ui-monospace,monospace;
            --ease:cubic-bezier(.16,1,.3,1);

            /* Tokens consumed by individual auth views — keep these names */
            --page:var(--paper);
            --card:#FFFFFF;
            --text:var(--ink);
            --muted:#666662;
            --border:rgba(18,18,18,.16);
            --input-bg:#FFFFFF;
            --btn:var(--ink);
            --btn-text:#FFFFFF;
            --btn-hover:#2A2A2A;
            --btn-hover-text:#FFFFFF;
            --error:#E5484D;
            --focus:var(--ink);
        }
        *{box-sizing:border-box}
        html,body{height:100%;}
        body{
            margin:0;min-height:100vh;
            font:400 16px/1.6 var(--body);
            color:var(--text);background:var(--paper);
            -webkit-font-smoothing:antialiased;
        }
        a{color:inherit;}
        :focus-visible{outline:2px solid var(--ink);outline-offset:3px;border-radius:6px;}
        @keyframes fade{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}
        @keyframes hover{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}

        /* Page centres one card; the card splits into brand panel + form */
        .auth-page{
            min-height:100vh;display:flex;align-items:center;justify-content:center;
            padding:24px 16px;
        }
        .auth-split{
            display:flex;width:100%;max-width:1180px;min-height:min(700px,calc(100vh - 48px));
            align-items:stretch;position:relative;
            background:var(--card);border:1px solid var(--line);border-radius:32px;padding:10px;
            box-shadow:0 40px 80px -40px rgba(18,18,18,.28);
            animation:fade .7s var(--ease) both;
        }

        /* ── Left: yellow brand panel (homepage mega-menu / CTA treatment) ── */
        .auth-split__visual{
            flex:0 0 50%;min-width:0;position:relative;overflow:hidden;
            border-radius:24px;
            background:linear-gradient(160deg,#FFD43F,#F2B705);
            color:var(--ink);
            padding:clamp(32px,3.6vw,48px);
            display:flex;flex-direction:column;
        }
        .auth-split__visual::before{
            content:"";position:absolute;border-radius:50%;pointer-events:none;
            width:320px;height:320px;top:-150px;left:-100px;background:rgba(255,255,255,.18);
        }
        .auth-visual__inner{
            position:relative;z-index:2;flex:1;display:flex;flex-direction:column;
            max-width:460px;width:100%;
        }
        .auth-visual__brand{
            display:inline-flex;align-items:center;gap:8px;align-self:flex-start;
            font:800 19px/1 var(--display);letter-spacing:-.04em;color:var(--ink);text-decoration:none;
        }
        .auth-visual__brand img{height:26px;width:auto;}
        .auth-visual__body{padding:clamp(28px,6vh,56px) 0 0;animation:fade .8s var(--ease) .1s both;}
        .auth-visual__label{
            display:block;font:600 11px/1 var(--mono);text-transform:uppercase;letter-spacing:.1em;
            color:rgba(18,18,18,.6);margin-bottom:14px;
        }
        .auth-visual__title{
            margin:0 0 16px;font:800 clamp(30px,3.2vw,46px)/1 var(--display);letter-spacing:-.035em;
            color:var(--ink);max-width:14ch;
        }
        .auth-visual__lead{margin:0 0 22px;font-size:16px;line-height:1.55;color:rgba(18,18,18,.72);max-width:36ch;}
        /* checklist + module chips: homepage mega-side styling */
        .auth-visual__list{list-style:none;margin:0;padding:0;display:grid;gap:10px;font-size:14.5px;font-weight:600;}
        .auth-visual__list li{display:flex;align-items:center;gap:10px;}
        .auth-visual__list i{flex:none;display:grid;place-items:center;width:20px;height:20px;border-radius:50%;background:rgba(18,18,18,.1);font-size:9px;}
        .auth-visual__modules{margin-top:auto;padding-top:24px;max-width:calc(100% - clamp(150px,15vw,200px));}
        .auth-visual__modules .auth-visual__label{margin-bottom:10px;}
        .auth-visual__chips{display:flex;flex-wrap:wrap;gap:6px;}
        .auth-visual__chips span{
            display:inline-flex;align-items:center;gap:6px;padding:6px 11px;border-radius:999px;
            background:rgba(255,255,255,.45);border:1px solid rgba(18,18,18,.08);
            font-size:12.5px;font-weight:600;line-height:1;
        }
        .auth-visual__chips i{font-size:11px;opacity:.75;}
        .auth-visual__mascot{
            position:absolute;z-index:1;right:clamp(16px,2.5vw,36px);bottom:-10px;
            height:clamp(200px,22vw,300px);width:auto;pointer-events:none;
            animation:fade 1s var(--ease) .35s both;
        }

        /* ── Right: form column ── */
        .auth-split__main{
            flex:1;min-width:0;display:flex;flex-direction:column;align-items:center;justify-content:center;
            padding:clamp(32px,4vw,56px) clamp(24px,4vw,56px) 20px;
        }
        .auth-shell{width:100%;max-width:400px;position:relative;z-index:1;flex-shrink:0;margin:auto 0;}
        .auth-panel{width:100%;background:transparent;border:none;box-shadow:none;border-radius:0;padding:0;}
        .auth-brand{display:flex;align-items:center;gap:12px;margin-bottom:18px;}
        .auth-brand__mark{
            width:40px;height:40px;border-radius:12px;display:grid;place-items:center;font-size:15px;
            color:var(--zee);background:var(--ink);flex-shrink:0;
        }
        .auth-brand__text h1{margin:0;font:800 28px/1.05 var(--display);letter-spacing:-.03em;color:var(--text);}
        .auth-brand__text p{margin:3px 0 0;font-size:13.5px;line-height:1.4;color:var(--muted);}
        .auth-body .sub{margin:0 0 18px;font-size:14px;line-height:1.5;color:var(--muted);}
        .field{margin-bottom:10px;}
        .field label{
            display:block;font:500 11px/1 var(--mono);letter-spacing:.08em;text-transform:uppercase;
            color:var(--muted);margin-bottom:7px;
        }
        .field input,.field select{
            width:100%;height:44px;padding:0 14px;border-radius:12px;border:1px solid var(--border);background:var(--input-bg);
            color:var(--text);font:400 15px var(--body);outline:none;transition:border-color .2s,box-shadow .2s;
        }
        .field input::placeholder{color:#A3A39F;}
        .field input:hover,.field select:hover{border-color:rgba(18,18,18,.3);}
        .field input:focus,.field select:focus{border-color:var(--ink);box-shadow:0 0 0 4px rgba(255,203,47,.45);}
        /* show / hide password toggle (added by script below) */
        .auth-pw{position:relative;}
        .auth-pw input{padding-right:50px;}
        .auth-pw__toggle{
            position:absolute;right:5px;top:50%;transform:translateY(-50%);
            width:36px;height:36px;border:0;border-radius:50%;background:none;cursor:pointer;
            display:grid;place-items:center;color:var(--muted);font-size:14px;transition:background .2s,color .2s;
        }
        .auth-pw__toggle:hover{background:var(--paper);color:var(--ink);}
        .field .error{color:var(--error);font-size:13px;min-height:18px;margin-top:6px;}
        .field .error:empty{min-height:4px;}
        .auth-check{display:flex;align-items:center;gap:8px;margin:4px 0 16px;font-size:13.5px;color:var(--muted);}
        .auth-check input[type=checkbox]{width:16px;height:16px;accent-color:var(--ink);cursor:pointer;margin:0;}
        .auth-check label{cursor:pointer;user-select:none;color:var(--text);}
        .auth-check a{color:var(--text);font-weight:600;text-decoration:underline;text-decoration-color:var(--zee);text-decoration-thickness:2px;text-underline-offset:4px;}
        .auth-check a:hover{text-decoration-color:var(--ink);}

        /* buttons: homepage .btn / .btn-ink */
        .auth-btn{
            display:inline-flex;align-items:center;justify-content:center;gap:8px;
            width:100%;height:44px;padding:0 20px;border:1px solid var(--btn);border-radius:999px;
            font:600 15px var(--body);cursor:pointer;
            color:var(--btn-text);background:var(--btn);
            transition:transform .3s var(--ease),background .3s,border-color .3s,color .3s;
        }
        .auth-btn i{color:var(--zee);}
        .auth-btn:hover{background:var(--btn-hover);color:var(--btn-hover-text);border-color:var(--btn-hover);}
        .auth-btn:active{transform:scale(.98);}
        .auth-btn:disabled,.auth-btn:disabled:hover{background:var(--btn);color:var(--btn-text);border-color:var(--btn);opacity:.55;cursor:not-allowed;transform:none;}
        .auth-meta{margin-top:22px;text-align:center;font-size:14px;color:var(--muted);}
        .auth-meta a{color:var(--text);font-weight:600;text-decoration:underline;text-decoration-color:var(--zee);text-decoration-thickness:2px;text-underline-offset:4px;}
        .auth-meta a:hover{text-decoration-color:var(--ink);}

        /* secondary links: homepage .btn-line */
        .auth-alt-links{display:flex;flex-wrap:nowrap;align-items:center;gap:8px;margin-top:10px;width:100%;}
        .auth-alt-pill{
            flex:1 1 0;min-width:0;display:inline-flex;align-items:center;justify-content:center;gap:8px;
            height:44px;padding:0 18px;border-radius:999px;font-size:14px;font-weight:600;line-height:1.2;
            text-decoration:none;border:1px solid rgba(18,18,18,.22);background:transparent;color:var(--text);
            transition:border-color .3s,background .3s,transform .3s var(--ease);
        }
        .auth-alt-pill i{font-size:12px;flex-shrink:0;}
        .auth-alt-pill:hover{border-color:var(--ink);background:var(--card);}
        .auth-alt-pill:active{transform:scale(.98);}
        .auth-alt-pill--hr{border-color:color-mix(in srgb,#3B6EF5 45%,transparent);color:#2448B8;}
        .auth-alt-links--switch__prompt{display:none;}
        .auth-avatar{display:none;}
        .auth-alt-pill--hr:hover{border-color:#3B6EF5;background:color-mix(in srgb,#3B6EF5 6%,var(--card));}

        .auth-divider{display:flex;align-items:center;gap:12px;margin:14px 0 10px;color:var(--muted);font:500 11px/1 var(--mono);text-transform:uppercase;letter-spacing:.1em;}
        .auth-divider::before,.auth-divider::after{content:'';flex:1;height:1px;background:var(--line);}
        .auth-oauth{
            display:flex;align-items:center;justify-content:center;gap:10px;width:100%;height:44px;
            padding:0 18px;border-radius:999px;border:1px solid var(--line);
            background:var(--card);color:var(--text);font:600 14px var(--body);text-decoration:none;
            transition:border-color .3s,box-shadow .3s,transform .3s var(--ease);
        }
        .auth-oauth:hover{border-color:rgba(18,18,18,.3);box-shadow:0 10px 24px -14px rgba(18,18,18,.35);}
        .auth-oauth:active{transform:scale(.98);}
        .auth-oauth svg{flex-shrink:0;width:18px;height:18px;}

        .auth-footer{display:flex;align-items:center;justify-content:center;flex-wrap:wrap;gap:6px 16px;padding:16px 8px 0;
            font-size:12px;color:var(--muted);flex-shrink:0;}
        .auth-footer a{color:var(--muted);text-decoration:none;font-weight:500;transition:color .2s;}
        .auth-footer a:hover{color:var(--text);}

        @media (prefers-reduced-motion:reduce){
            .auth-visual__body,.auth-visual__mascot,.auth-split{animation:none;}
        }
        @media (max-width:1100px){
            .auth-split__visual{flex-basis:46%;}
            .auth-visual__modules{display:none;}
        }
        @media (max-width:860px){
            /* Tablets: card stacks — yellow banner on top, form below */
            .auth-page{align-items:flex-start;padding:24px 16px;}
            .auth-split{flex-direction:column;max-width:560px;min-height:0;}
            .auth-split__visual{flex:none;padding:24px 26px;min-height:200px;}
            .auth-split__visual::before{width:240px;height:240px;top:-130px;left:-70px;}
            .auth-visual__body{margin:0;padding:18px 0 0;}
            .auth-visual__label,.auth-visual__list,.auth-visual__modules{display:none;}
            .auth-visual__title{font-size:clamp(24px,4.6vw,32px);margin:0 0 8px;max-width:none;padding-right:130px;}
            .auth-visual__lead{font-size:14px;margin:0;padding-right:130px;}
            .auth-visual__mascot{height:180px;right:20px;bottom:-12px;}
            .auth-split__main{flex:1;justify-content:flex-start;padding:32px 28px 18px;}
            .auth-shell{margin:0;}
        }
        @media (max-width:600px){
            /* Phones: full-bleed app-style screen, no floating card */
            body{background:var(--card);}
            .auth-page{display:block;padding:0;}
            .auth-split{
                min-height:100vh;min-height:100dvh;max-width:none;padding:0;
                border:0;border-radius:0;box-shadow:none;
            }
            /* Minimal white screen: the yellow panel shrinks to a plain logo bar */
            .auth-split__visual{
                min-height:0;border-radius:0;background:none;overflow:visible;
                padding:calc(16px + env(safe-area-inset-top)) 20px 0;
            }
            .auth-split__visual::before,
            .auth-visual__body,
            .auth-visual__modules,
            .auth-visual__mascot{display:none;}
            .auth-visual__brand{font-size:18px;}
            .auth-visual__brand img{height:24px;}

            /* Mascot avatar in a yellow circle, centred heading */
            .auth-avatar{
                display:block;width:88px;height:88px;margin:0 auto 16px;border-radius:50%;overflow:hidden;
                background:linear-gradient(160deg,#FFD43F,#F2B705);
                box-shadow:0 0 0 6px rgba(255,203,47,.18);
                animation:fade .7s var(--ease) both;
            }
            .auth-avatar img{display:block;width:100%;height:auto;margin-top:6px;transform:scale(1.35);transform-origin:50% 0;}
            .auth-split__main{padding:28px 20px calc(14px + env(safe-area-inset-bottom));}
            .auth-brand{justify-content:center;text-align:center;margin-bottom:22px;}
            .auth-brand__mark{display:none;}
            .auth-brand__text h1{font-size:28px;}
            .auth-brand__text p{font-size:14.5px;margin-top:6px;}
            .auth-body .sub{font-size:13px;margin-bottom:16px;}
            .auth-body .sub--hint{display:none;}
            .field{margin-bottom:12px;}
            .field label{margin-bottom:6px;}
            /* 48px touch targets; 16px stops iOS Safari zooming on focus */
            .field input,.field select{height:48px;font-size:16px;}
            .field .error{font-size:12px;min-height:0;margin-top:4px;}
            .field .error:empty{display:none;}
            .auth-check{flex-wrap:wrap;row-gap:8px;margin:6px 0 18px;font-size:14px;}
            .auth-check a{font-size:14px !important;}
            .auth-btn,.auth-oauth,.auth-alt-pill{height:48px;font-size:15px;}
            .auth-divider{margin:18px 0 14px;}
            .auth-alt-links{flex-wrap:wrap;}
            /* Sign in ↔ Create account switch: a text link, not a third big button */
            .auth-alt-links--switch{justify-content:center;margin-top:14px;}
            .auth-alt-links--switch .auth-alt-pill{
                flex:none;height:auto;padding:8px 4px;border:0;background:none;font-size:14px;
                text-decoration:underline;text-decoration-color:var(--zee);text-decoration-thickness:2px;text-underline-offset:5px;
            }
            .auth-alt-links--switch .auth-alt-pill:hover{background:none;text-decoration-color:var(--ink);}
            .auth-alt-links--switch .auth-alt-pill i{display:none;}
            .auth-alt-links--switch__prompt{display:inline;color:var(--muted);font-size:14px;}
            .auth-footer{gap:4px 14px;margin-top:auto;padding-top:24px;font-size:12px;text-align:center;}
        }
        @media (max-width:360px){
            .auth-avatar{width:76px;height:76px;margin-bottom:12px;}
            .auth-split__main{padding-left:16px;padding-right:16px;}
            .auth-brand__text h1{font-size:23px;}
            .auth-check{font-size:13px;}
        }
    </style>
    @stack('auth-styles')
</head>
<body>
    <div class="auth-page">
    <div class="auth-split">
        <aside class="auth-split__visual" role="complementary" aria-labelledby="auth-visual-heading">
            <div class="auth-visual__inner">
                <a class="auth-visual__brand" href="https://zeebroo.com" aria-label="{{ config('app.name') }}">
                    <img src="{{ asset('images/brand/mark.png') }}" alt="" width="25" height="32">
                    <span>{{ config('app.name') }}</span>
                </a>
                <div class="auth-visual__body">
                    <span class="auth-visual__label">{{ __('One workspace') }}</span>
                    <h2 class="auth-visual__title" id="auth-visual-heading">{{ __('Everything your team needs to run the business') }}</h2>
                    <p class="auth-visual__lead">{{ __('Accounts, cash flow, HR, and more — clear, fast, and built for real operations.') }}</p>
                    <ul class="auth-visual__list">
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i>{{ __('Turn modules on or off') }}</li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i>{{ __('Shared, live data') }}</li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i>{{ __('Works on web and desktop') }}</li>
                    </ul>
                </div>
                <div class="auth-visual__modules">
                    <span class="auth-visual__label">{{ __('Modules') }}</span>
                    <div class="auth-visual__chips">
                        <span><i class="fa-solid fa-cash-register" aria-hidden="true"></i>{{ __('POS') }}</span>
                        <span><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i>{{ __('Inventory') }}</span>
                        <span><i class="fa-solid fa-wallet" aria-hidden="true"></i>{{ __('Finance') }}</span>
                        <span><i class="fa-solid fa-file-invoice-dollar" aria-hidden="true"></i>{{ __('Bills') }}</span>
                        <span><i class="fa-solid fa-users-gear" aria-hidden="true"></i>{{ __('HR') }}</span>
                        <span><i class="fa-solid fa-bullhorn" aria-hidden="true"></i>{{ __('Campaigns') }}</span>
                    </div>
                </div>
            </div>
            <img class="auth-visual__mascot" src="{{ asset('images/brand/mascot.webp') }}" alt="" width="481" height="731" aria-hidden="true">
        </aside>
        <main class="auth-split__main" id="auth-main">
            <div class="auth-shell">
                <div class="auth-avatar" aria-hidden="true">
                    <img src="{{ asset('images/brand/mascot.webp') }}" alt="" width="481" height="731">
                </div>
                <div class="auth-panel">
                    @yield('content')
                </div>
            </div>
            <footer class="auth-footer">
                <span>&copy; {{ now()->year }} {{ config('app.name') }}</span>
                <a href="{{ route('privacy-policy') }}" target="_blank">Privacy Policy</a>
                <a href="{{ route('terms-of-service') }}" target="_blank">Terms of Service</a>
            </footer>
        </main>
    </div>
    </div>
    @stack('auth-scripts')
    <script>
    /* Show / hide toggle on every password field in the auth forms. */
    (function () {
        var showLabel = {!! json_encode(__('Show password')) !!};
        var hideLabel = {!! json_encode(__('Hide password')) !!};
        document.querySelectorAll('.field input[type=password]').forEach(function (input) {
            var wrap = document.createElement('div');
            wrap.className = 'auth-pw';
            input.parentNode.insertBefore(wrap, input);
            wrap.appendChild(input);
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'auth-pw__toggle';
            btn.setAttribute('aria-label', showLabel);
            btn.innerHTML = '<i class="fa-regular fa-eye" aria-hidden="true"></i>';
            btn.addEventListener('click', function () {
                var reveal = input.type === 'password';
                input.type = reveal ? 'text' : 'password';
                btn.setAttribute('aria-label', reveal ? hideLabel : showLabel);
                btn.firstChild.className = reveal ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
                input.focus();
            });
            wrap.appendChild(btn);
        });
    })();
    /* Forms marked data-submit-once: disable the submit button after the first submit to prevent duplicate requests. */
    (function () {
        document.querySelectorAll('form[data-submit-once]').forEach(function (form) {
            form.addEventListener('submit', function () {
                form.querySelectorAll('button[type=submit]').forEach(function (btn) {
                    btn.disabled = true;
                    if (btn.dataset.loadingText) btn.textContent = btn.dataset.loadingText;
                });
            });
        });
        /* Re-enable when the page is restored from the back/forward cache. */
        window.addEventListener('pageshow', function (e) {
            if (!e.persisted) return;
            document.querySelectorAll('form[data-submit-once] button[type=submit]').forEach(function (btn) {
                btn.disabled = false;
                if (btn.dataset.label) btn.textContent = btn.dataset.label;
            });
        });
    })();
    </script>
</body>
</html>
