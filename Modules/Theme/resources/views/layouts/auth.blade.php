<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? __('Sign in') }} · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400&display=swap" rel="stylesheet">
    <style>
        :root{
            --page:#f5f5f4;
            --card:#ffffff;
            --text:#0a0a0a;
            --muted:#525252;
            --border:#d4d4d4;
            --input-bg:#ffffff;
            --btn:#000000;
            --btn-text:#ffffff;
            --btn-hover:#facc15;
            --btn-hover-text:#0a0a0a;
            --error:#dc2626;
            --focus:#000000;
            --visual-bg:#ffffff;
            --visual-accent:#ca8a04;
            --visual-muted:#64748b;
            --visual-text:#0f172a;
        }
        *{box-sizing:border-box}
        html,body{height:100%;}
        body{
            margin:0;min-height:100vh;
            font-family:Inter,system-ui,-apple-system,Segoe UI,sans-serif;
            color:var(--text);background:var(--page);
        }
        .auth-split{
            display:flex;min-height:100vh;width:100%;align-items:stretch;
            position:relative;z-index:1;
        }
        .auth-split__visual{
            flex:1;min-width:0;position:relative;overflow:hidden;
            background:rgba(255,255,255,.93);
            color:var(--visual-text);
            padding:clamp(28px,5vw,56px);
            display:flex;flex-direction:column;justify-content:center;
            border-right:1px solid color-mix(in srgb,var(--border) 85%,transparent);
            transition:box-shadow .35s ease;
        }
        .auth-split__visual:hover{box-shadow:inset 0 0 0 1px color-mix(in srgb,#facc15 35%,transparent);}
        .auth-ascii-corridor{
            display:flex;align-items:center;justify-content:center;
            overflow:hidden;pointer-events:none;
        }
        .auth-ascii-corridor--viewport{
            position:fixed;inset:0;z-index:0;width:100%;height:100%;
            min-height:100vh;min-height:100dvh;
        }
        .auth-ascii-corridor__pre{
            margin:0;font-family:'IBM Plex Mono',ui-monospace,monospace;font-weight:400;
            font-size:1.25px;line-height:1.25px;letter-spacing:0;
            color:#d1d5db;white-space:pre;user-select:none;opacity:.72;
            will-change:transform;transform-origin:center center;
        }
        @media (min-width:1100px){
            .auth-ascii-corridor__pre{font-size:1.5px;line-height:1.5px;letter-spacing:0;opacity:.68;}
        }
        .auth-visual__inner{
            position:relative;z-index:2;max-width:440px;margin:0 auto;width:100%;
            background:rgba(255,255,255,.86);
            backdrop-filter:blur(8px);
            -webkit-backdrop-filter:blur(8px);
            border-radius:20px;
            padding:clamp(20px,3.2vw,28px);
            box-shadow:0 1px 0 rgba(0,0,0,.04),0 12px 40px rgba(15,23,42,.06);
        }
        .auth-visual__brand{
            display:inline-flex;align-items:center;gap:10px;font-size:12px;font-weight:800;
            letter-spacing:.14em;text-transform:uppercase;color:#475569;
        }
        .auth-visual__brand-mark{
            width:36px;height:36px;border-radius:10px;display:grid;place-items:center;
            font-size:14px;font-weight:900;background:linear-gradient(135deg,#171717,#404040);color:#facc15;
        }
        .auth-visual__title{
            margin:18px 0 12px;font-size:clamp(1.65rem,3.2vw,2.15rem);font-weight:800;line-height:1.18;
            letter-spacing:-.03em;color:var(--visual-text);
        }
        .auth-visual__lead{
            margin:0 0 28px;font-size:15px;line-height:1.55;color:var(--visual-muted);font-weight:500;
        }
        .auth-visual__list{
            list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:20px;
        }
        .auth-visual__list li{
            display:flex;gap:14px;align-items:flex-start;font-size:15px;line-height:1.45;color:#334155;
        }
        .auth-visual__list .fa-fw{width:1.25em;text-align:center;color:#b45309;margin-top:3px;font-size:16px;}
        .auth-visual__footnote{
            margin-top:36px;font-size:13px;line-height:1.5;color:#64748b;max-width:36ch;
        }
        .auth-split__main{
            flex:1;min-width:0;display:flex;align-items:center;justify-content:center;
            padding:clamp(16px,4vmin,48px);
            background:rgba(245,245,244,.94);
        }
        .auth-shell{width:100%;max-width:420px;position:relative;z-index:1;}
        /* Right column: form sits flush on the page — no card chrome */
        .auth-panel{width:100%;background:transparent;border:none;box-shadow:none;border-radius:0;padding:0;}
        .auth-brand{display:flex;align-items:center;gap:14px;margin-bottom:clamp(20px,3vmin,28px);}
        .auth-brand__mark{
            width:48px;height:48px;border-radius:12px;display:grid;place-items:center;font-size:22px;
            color:var(--btn-text);background:var(--btn);flex-shrink:0;
        }
        .auth-brand__text h1{margin:0;font-size:clamp(1.35rem,2.8vw,1.55rem);font-weight:800;letter-spacing:-.03em;line-height:1.2;color:var(--text);}
        .auth-brand__text p{margin:5px 0 0;font-size:13px;line-height:1.45;color:var(--muted);font-weight:500;}
        .auth-body .sub{margin:0 0 22px;font-size:14px;line-height:1.5;color:var(--muted);}
        .field{margin-bottom:16px;}
        .field label{display:block;font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--muted);margin-bottom:7px;}
        .field input,.field select{
            width:100%;padding:12px 14px;border-radius:10px;border:1px solid var(--border);background:var(--input-bg);
            color:var(--text);font-size:15px;outline:none;transition:border-color .15s ease,box-shadow .15s ease;
        }
        .field input::placeholder{color:#a3a3a3;}
        .field input:focus,.field select:focus{
            border-color:var(--focus);
            box-shadow:0 0 0 2px #ffffff,0 0 0 4px var(--focus);
        }
        .field .error{color:var(--error);font-size:13px;min-height:20px;margin-top:6px;}
        .auth-check{display:flex;align-items:center;gap:10px;margin:12px 0 18px;font-size:14px;color:var(--muted);}
        .auth-check input[type=checkbox]{width:18px;height:18px;accent-color:var(--btn);cursor:pointer;}
        .auth-check label{cursor:pointer;user-select:none;color:var(--text);}
        .auth-btn{
            width:100%;border:2px solid var(--btn);border-radius:10px;padding:13px 16px;font-size:15px;font-weight:700;cursor:pointer;font-family:inherit;
            color:var(--btn-text);background:var(--btn);
            transition:background-color .15s ease,color .15s ease,border-color .15s ease;
        }
        .auth-btn:hover{background:var(--btn-hover);color:var(--btn-hover-text);border-color:var(--btn-hover);}
        .auth-btn:active{background:#eab308;color:var(--btn-hover-text);border-color:#eab308;}
        .auth-meta{margin-top:22px;text-align:center;font-size:14px;color:var(--muted);}
        .auth-meta a{color:var(--text);font-weight:700;text-decoration:underline;text-underline-offset:3px;}
        .auth-meta a:hover{color:var(--muted);}
        .auth-alt-links{
            display:flex;flex-direction:row;flex-wrap:nowrap;align-items:center;gap:8px;margin-top:18px;width:100%;
        }
        .auth-alt-pill{
            flex:1 1 0;min-width:0;display:inline-flex;align-items:center;justify-content:center;gap:6px;
            padding:7px 10px;border-radius:999px;font-size:12px;font-weight:700;line-height:1.2;
            text-decoration:none;border:1px solid var(--border);
            background:color-mix(in srgb,var(--card) 94%,var(--page));
            color:var(--text);
            transition:border-color .15s ease,background .15s ease,color .15s ease;
        }
        .auth-alt-pill i{font-size:11px;flex-shrink:0;opacity:.88;}
        .auth-alt-pill:hover{background:var(--card);border-color:color-mix(in srgb,var(--text) 25%,var(--border));}
        .auth-alt-pill:focus-visible{outline:2px solid var(--focus);outline-offset:2px;}
        .auth-alt-pill--hr{
            border-color:color-mix(in srgb,#6366f1 45%,var(--border));
            color:#3730a3;
            background:color-mix(in srgb,#6366f1 9%,var(--page));
        }
        .auth-alt-pill--hr:hover{
            background:color-mix(in srgb,#6366f1 15%,var(--card));
            border-color:color-mix(in srgb,#6366f1 65%,var(--border));
            color:#312e81;
        }
        .auth-divider{display:flex;align-items:center;gap:12px;margin:22px 0 16px;color:var(--muted);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;}
        .auth-divider::before,.auth-divider::after{content:'';flex:1;height:1px;background:var(--border);}
        .auth-oauth{
            display:flex;align-items:center;justify-content:center;gap:10px;width:100%;
            padding:12px 16px;border-radius:10px;border:2px solid var(--border);
            background:var(--card);color:var(--text);font-size:15px;font-weight:700;font-family:inherit;text-decoration:none;
            transition:background-color .15s ease,border-color .15s ease,color .15s ease;
        }
        .auth-oauth:hover{background:var(--btn-hover);border-color:var(--btn-hover);color:var(--btn-hover-text);}
        .auth-oauth i{font-size:18px;}
        @media (prefers-reduced-motion:reduce){
            .auth-split__visual:hover{box-shadow:none;}
        }
        @media (max-width:960px){
            .auth-split{flex-direction:column;min-height:100vh;}
            .auth-split__visual{
                flex:none;min-height:min(52vh,420px);padding:clamp(24px,6vw,40px);
                justify-content:flex-end;
                border-right:none;
                border-bottom:1px solid color-mix(in srgb,var(--border) 85%,transparent);
            }
            .auth-visual__inner{max-width:520px;}
            .auth-visual__title{font-size:clamp(1.45rem,5vw,1.85rem);}
            .auth-visual__footnote{margin-top:20px;}
            .auth-split__main{padding-top:clamp(24px,5vmin,40px);padding-bottom:clamp(32px,8vmin,48px);}
        }
    </style>
    @stack('auth-styles')
</head>
<body>
    <div class="auth-split">
        <div class="auth-ascii-corridor auth-ascii-corridor--viewport" aria-hidden="true">
            <pre class="auth-ascii-corridor__pre" id="authCorridorPre"></pre>
        </div>
        <aside class="auth-split__visual" role="complementary" aria-labelledby="auth-visual-heading">
            <div class="auth-visual__inner">
                <div class="auth-visual__brand">
                    <span class="auth-visual__brand-mark" aria-hidden="true">SB</span>
                    <span>{{ config('app.name') }}</span>
                </div>
                <h2 class="auth-visual__title" id="auth-visual-heading">{{ __('Everything your team needs to run the business') }}</h2>
                <p class="auth-visual__lead">{{ __('Accounts, cash flow, HR, and more — clear, fast, and built for real operations.') }}</p>
                <ul class="auth-visual__list">
                    <li>
                        <i class="fa fa-chart-line fa-fw" aria-hidden="true"></i>
                        <span>{{ __('See money in and out with accounts and transactions you can trust.') }}</span>
                    </li>
                    <li>
                        <i class="fa fa-users-gear fa-fw" aria-hidden="true"></i>
                        <span>{{ __('HR hub, payroll context, and employee access when you enable them.') }}</span>
                    </li>
                    <li>
                        <i class="fa fa-shield-halved fa-fw" aria-hidden="true"></i>
                        <span>{{ __('Roles and secure sign-in so the right people see the right data.') }}</span>
                    </li>
                </ul>
                <p class="auth-visual__footnote">{{ __('Join teams who keep finance and people data in one calm workspace.') }}</p>
            </div>
        </aside>
        <main class="auth-split__main" id="auth-main">
            <div class="auth-shell">
                <div class="auth-panel">
                    @yield('content')
                </div>
            </div>
        </main>
    </div>
    @stack('auth-scripts')
    <!-- ASCII corridor animation concept: https://codepen.io/obsfx/pen/jOWVOYL by Ömercan Balandı (@obsfx) — adapted for SociBiz (canvas→ASCII, light theme). -->
    <script>
    (function () {
        var asciiLayer = document.querySelector('.auth-ascii-corridor--viewport');
        var pre = document.getElementById('authCorridorPre');
        if (!asciiLayer || !pre) return;

        var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        var ascii = '.:-=+*#.@';
        var canvas = document.createElement('canvas');
        var ctx = canvas.getContext('2d');
        /* Higher resolution → more ASCII cells so full-viewport scale keeps glyphs visually small */
        canvas.width = 200;
        canvas.height = 280;

        var BOX_W = 35;
        var BOX_H = 55;
        var BOX_PAD = 30;
        var pointerTargetX = canvas.width / 2;
        var pointerTargetY = canvas.height / 2;
        var followStrength = 0.14;

        var map = function (x, xmax, xmin, tmax, tmin) {
            return ((x - xmin) / (xmax - xmin)) * (tmax - tmin) + tmin;
        };

        function clampBoxXY(tx, ty) {
            var minX = BOX_PAD;
            var minY = BOX_PAD;
            var maxX = canvas.width - BOX_W - BOX_PAD;
            var maxY = canvas.height - BOX_H - BOX_PAD;
            var x = tx - BOX_W / 2;
            var y = ty - BOX_H / 2;
            return {
                x: Math.max(minX, Math.min(maxX, x)),
                y: Math.max(minY, Math.min(maxY, y)),
            };
        }

        function setPointerFromClient(clientX, clientY) {
            var r = asciiLayer.getBoundingClientRect();
            if (r.width < 4 || r.height < 4) return;
            var nx = (clientX - r.left) / r.width;
            var ny = (clientY - r.top) / r.height;
            nx = Math.max(0, Math.min(1, nx));
            ny = Math.max(0, Math.min(1, ny));
            pointerTargetX = nx * canvas.width;
            pointerTargetY = ny * canvas.height;
        }

        var mapTable = new Array(256)
            .fill(0)
            .map(function (_, i) {
                return Math.min(
                    ascii.length - 1,
                    Math.max(0, Math.floor(map(i, 255, 0, ascii.length - 1, 0)))
                );
            });

        function line(x1, y1, x2, y2) {
            var g = ctx.createLinearGradient(x1, y1, x2, y2);
            g.addColorStop(0, 'rgba(15, 23, 42, 0.22)');
            g.addColorStop(0.5, 'rgba(15, 23, 42, 0.08)');
            g.addColorStop(1, 'rgba(15, 23, 42, 0.02)');
            ctx.strokeStyle = g;
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(x1, y1);
            ctx.lineTo(x2, y2);
            ctx.stroke();
        }

        function getAsciiOutput() {
            var imgd = ctx.getImageData(0, 0, canvas.width, canvas.height);
            var pix = imgd.data;
            var w = canvas.width;
            var h = canvas.height;
            var out = '';
            for (var y = 0; y < h; y++) {
                for (var x = 0; x < w; x++) {
                    var idx = (y * w + x) * 4;
                    var lum = pix[idx];
                    out += ascii[mapTable[lum]];
                }
                if (y < h - 1) out += '\n';
            }
            return out;
        }

        var box = {
            x: canvas.width / 2 - BOX_W / 2,
            y: canvas.height / 2 - BOX_H / 2,
            w: BOX_W,
            h: BOX_H,
        };
        var tick = 0;
        var raf = null;
        var scaleEvery = 0;

        function drawFrame() {
            if (!reduceMotion) {
                tick += 0.025;
            }

            var dest = clampBoxXY(pointerTargetX, pointerTargetY);
            var k = reduceMotion ? 1 : followStrength;
            box.x += (dest.x - box.x) * k;
            box.y += (dest.y - box.y) * k;

            ctx.fillStyle = '#f8fafc';
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            ctx.fillStyle = '#e2e8f0';
            ctx.fillRect(box.x, box.y, box.w, box.h);

            line(box.x, box.y, 0, 0);
            line(box.x + box.w, box.y, canvas.width, 0);
            line(box.x, box.y + box.h, 0, canvas.height);
            line(box.x + box.w, box.y + box.h, canvas.width, canvas.height);

            var cx = box.x + box.w / 2;
            var cy = box.y + box.h / 2;
            var pulse = reduceMotion ? 22 : 15 + 14 * (0.5 + 0.5 * Math.sin(tick * 1.6));
            var rg = ctx.createRadialGradient(cx, cy, 5, cx, cy, 70);
            rg.addColorStop(0, 'rgba(17, 24, 39, 0.2)');
            rg.addColorStop(0.55, 'rgba(17, 24, 39, 0.05)');
            rg.addColorStop(1, 'rgba(17, 24, 39, 0)');
            ctx.fillStyle = rg;
            ctx.beginPath();
            ctx.arc(cx, cy, pulse, 0, 2 * Math.PI);
            ctx.fill();

            /* Cursor highlight — tracks pointer in canvas space */
            ctx.fillStyle = 'rgba(100, 116, 139, 0.45)';
            ctx.beginPath();
            ctx.arc(pointerTargetX, pointerTargetY, 4, 0, 2 * Math.PI);
            ctx.fill();

            pre.textContent = getAsciiOutput();
            scaleEvery += 1;
            if (scaleEvery % 20 === 0 || scaleEvery < 4) {
                scaleToCover();
            }
        }

        function scaleToCover() {
            pre.style.transform = 'none';
            pre.style.display = 'inline-block';
            var aw = asciiLayer.clientWidth || window.innerWidth;
            var ah = asciiLayer.clientHeight || window.innerHeight;
            if (aw < 8 || ah < 8) return;
            var pw = pre.offsetWidth || 1;
            var ph = pre.offsetHeight || 1;
            /* Fill the visual panel edge-to-edge (no intentional shrink margin) */
            var s = Math.max((aw * 1.02) / pw, (ah * 1.02) / ph);
            pre.style.transform = 'scale(' + s + ')';
        }

        function loop() {
            drawFrame();
            raf = window.requestAnimationFrame(loop);
        }

        function stop() {
            if (raf) {
                window.cancelAnimationFrame(raf);
                raf = null;
            }
        }

        function onPointerMove(clientX, clientY) {
            setPointerFromClient(clientX, clientY);
            if (reduceMotion) {
                drawFrame();
                scaleToCover();
            }
        }

        window.addEventListener(
            'mousemove',
            function (e) {
                onPointerMove(e.clientX, e.clientY);
            },
            { passive: true }
        );
        window.addEventListener(
            'touchstart',
            function (e) {
                if (e.touches && e.touches[0]) {
                    onPointerMove(e.touches[0].clientX, e.touches[0].clientY);
                }
            },
            { passive: true }
        );
        window.addEventListener(
            'touchmove',
            function (e) {
                if (e.touches && e.touches[0]) {
                    onPointerMove(e.touches[0].clientX, e.touches[0].clientY);
                }
            },
            { passive: true }
        );

        if (reduceMotion) {
            tick = 0;
            drawFrame();
            scaleToCover();
            stop();
        } else {
            loop();
        }

        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(function () {
                scaleToCover();
            });
        }

        var resizeTimer = null;
        window.addEventListener('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(scaleToCover, 80);
        });

        window.addEventListener('beforeunload', stop);
    })();
    </script>
</body>
</html>
