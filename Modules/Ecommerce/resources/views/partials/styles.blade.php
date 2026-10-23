{{-- Shared storefront styles. Base = "classic"; .tpl-* classes on <body> restyle per website template. --}}
<style>
:root{--accent:{{ $store->theme_color ?: '#7c3aed' }};--bg:#f7f7fb;--card:#fff;--text:#1f2937;--muted:#6b7280;--border:#e5e7eb;--danger:#dc2626;--ok:#059669;--radius:14px;--head-bg:var(--card);--head-text:var(--text);--font:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;--font-head:var(--font)}
@media (prefers-color-scheme:dark){:root{--bg:#111220;--card:#1b1c2e;--text:#e5e7f0;--muted:#9ca3c0;--border:#2d2f48}}
*{box-sizing:border-box}
body{margin:0;font-family:var(--font);background:var(--bg);color:var(--text);line-height:1.5}
h1,h2,h3{font-family:var(--font-head)}
a{color:var(--accent)}
.sf-announce{background:var(--accent);color:#fff;text-align:center;font-size:13px;padding:7px 16px}
.sf-head{position:sticky;top:0;z-index:20;background:var(--head-bg);color:var(--head-text);border-bottom:1px solid var(--border)}
.sf-head-in{max-width:1180px;margin:0 auto;padding:12px 16px;display:flex;align-items:center;gap:14px}
.sf-brand{display:flex;align-items:center;gap:10px;font-weight:800;font-size:18px;text-decoration:none;color:inherit;min-width:0;font-family:var(--font-head)}
.sf-brand img{width:38px;height:38px;border-radius:9px;object-fit:cover}
.sf-brand span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sf-search{flex:1;max-width:420px;margin-left:auto;position:relative}
.sf-search input{width:100%;padding:9px 12px 9px 34px;border:1px solid var(--border);border-radius:10px;background:var(--bg);color:var(--text);font-size:14px}
.sf-search i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:13px}
.sf-cart-btn{position:relative;border:0;background:var(--accent);color:#fff;border-radius:10px;padding:9px 14px;font-size:14px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:8px;flex-shrink:0}
.sf-cart-count{background:#fff;color:var(--accent);border-radius:999px;font-size:11px;font-weight:800;min-width:20px;height:20px;display:grid;place-items:center;padding:0 5px}
.sf-hero{max-width:1180px;margin:18px auto 0;padding:0 16px}
.sf-hero-in{border-radius:var(--radius);overflow:hidden;position:relative;min-height:200px;background:linear-gradient(135deg,var(--accent),#111827);color:#fff;display:flex;align-items:flex-end}
.sf-hero-in img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:.55}
.sf-hero-txt{position:relative;padding:28px}
.sf-hero-txt h1{margin:0 0 4px;font-size:clamp(24px,4vw,38px);letter-spacing:-.02em}
.sf-hero-txt p{margin:0;opacity:.9;max-width:640px}
/* Slider */
.sf-slider{position:relative;border-radius:var(--radius);overflow:hidden;aspect-ratio:16/6;min-height:220px;background:#111827}
.sf-slide{position:absolute;inset:0;opacity:0;transition:opacity .6s;display:flex;align-items:flex-end;color:#fff}
.sf-slide.on{opacity:1;z-index:1}
.sf-slide img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.sf-slide::after{content:'';position:absolute;inset:0;background:linear-gradient(transparent 35%,rgba(0,0,0,.6))}
.sf-slide-txt{position:relative;z-index:1;padding:30px;max-width:680px}
.sf-slide-txt h2{margin:0 0 4px;font-size:clamp(22px,3.6vw,36px);letter-spacing:-.02em}
.sf-slide-txt p{margin:0 0 12px;opacity:.92}
.sf-slide-btn{display:inline-block;background:var(--accent);color:#fff;text-decoration:none;font-weight:700;padding:10px 18px;border-radius:10px}
.sf-arrow{position:absolute;top:50%;transform:translateY(-50%);z-index:2;width:38px;height:38px;border-radius:50%;border:0;background:rgba(255,255,255,.85);color:#111;cursor:pointer}
.sf-arrow.prev{left:12px}.sf-arrow.next{right:12px}
.sf-dots{position:absolute;bottom:12px;right:16px;z-index:2;display:flex;gap:6px}
.sf-dots button{width:9px;height:9px;border-radius:50%;border:0;background:rgba(255,255,255,.5);cursor:pointer;padding:0}
.sf-dots button.on{background:#fff;width:22px;border-radius:999px}
.sf-main{max-width:1180px;margin:0 auto;padding:22px 16px 60px}
.sf-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px}
.sf-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;display:flex;flex-direction:column}
.sf-card-img{aspect-ratio:1/1;background:var(--bg);display:grid;place-items:center;color:var(--muted);font-size:34px;position:relative}
.sf-card-img img{width:100%;height:100%;object-fit:cover}
.sf-badge{position:absolute;top:10px;left:10px;background:var(--accent);color:#fff;font-size:11px;font-weight:700;padding:3px 8px;border-radius:999px}
.sf-card-body{padding:12px 14px 14px;display:flex;flex-direction:column;gap:6px;flex:1}
.sf-card-title{font-weight:650;font-size:14px}
.sf-price{font-weight:800;font-size:16px}
.sf-price s{color:var(--muted);font-weight:500;font-size:13px;margin-left:6px}
.sf-add{margin-top:auto;border:1px solid var(--accent);background:transparent;color:var(--accent);border-radius:9px;padding:8px;font-weight:650;cursor:pointer;font-size:13px}
.sf-add:hover{background:var(--accent);color:#fff}
.sf-add:disabled{opacity:.45;cursor:not-allowed;background:transparent;color:var(--muted);border-color:var(--border)}
.sf-empty{text-align:center;color:var(--muted);padding:60px 16px}
.sf-about{margin-top:40px;background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:22px}
.sf-about h2{margin:0 0 8px;font-size:18px}
.sf-foot{border-top:1px solid var(--border);color:var(--muted);font-size:13px;text-align:center;padding:22px 16px}
.sf-foot a{margin:0 6px;color:var(--muted)}
.sf-drawer-bg{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;z-index:40}
.sf-drawer{position:fixed;top:0;right:0;height:100%;width:min(440px,100%);background:var(--card);z-index:41;transform:translateX(100%);transition:transform .2s;display:flex;flex-direction:column}
.sf-open .sf-drawer-bg{display:block}.sf-open .sf-drawer{transform:none}
.sf-drawer-head{display:flex;align-items:center;justify-content:space-between;padding:16px;border-bottom:1px solid var(--border);font-weight:750}
.sf-x{border:0;background:transparent;color:var(--muted);font-size:20px;cursor:pointer}
.sf-drawer-body{flex:1;overflow-y:auto;padding:16px}
.sf-line{display:flex;gap:10px;align-items:center;padding:10px 0;border-bottom:1px solid var(--border)}
.sf-line-name{flex:1;font-size:14px;min-width:0}
.sf-qty{display:flex;align-items:center;border:1px solid var(--border);border-radius:8px}
.sf-qty button{border:0;background:transparent;color:var(--text);width:28px;height:28px;cursor:pointer}
.sf-qty span{min-width:22px;text-align:center;font-size:13px}
.sf-sum{display:flex;justify-content:space-between;font-size:14px;padding:4px 0}
.sf-sum.total{font-weight:800;font-size:16px;border-top:1px solid var(--border);margin-top:6px;padding-top:10px}
.sf-form{display:grid;gap:10px;margin-top:16px}
.sf-form label{font-size:12px;font-weight:600;color:var(--muted);display:block;margin-bottom:3px}
.sf-form input,.sf-form textarea,.sf-form select{width:100%;padding:9px 11px;border:1px solid var(--border);border-radius:9px;background:var(--bg);color:var(--text);font:inherit;font-size:14px}
.sf-pay{display:grid;gap:6px}
.sf-pay label{display:flex;align-items:center;gap:8px;border:1px solid var(--border);border-radius:9px;padding:9px 11px;margin:0;color:var(--text);font-size:14px;font-weight:500;cursor:pointer}
.sf-pay input{width:auto}
.sf-primary{width:100%;border:0;background:var(--accent);color:#fff;border-radius:10px;padding:12px;font-weight:700;font-size:15px;cursor:pointer;margin-top:6px;text-decoration:none;display:inline-block;text-align:center}
.sf-primary:disabled{opacity:.6;cursor:wait}
.sf-msg{font-size:13px;color:var(--danger);display:none}
.sf-done{text-align:center;padding:30px 10px}
.sf-done i{font-size:46px;color:var(--ok)}
.sf-bank{white-space:pre-wrap;background:var(--bg);border:1px solid var(--border);border-radius:9px;padding:10px;font-size:13px;text-align:left}

/* ── Template: Modern ── */
.tpl-modern{--radius:22px;--bg:#f4f2ff}
@media (prefers-color-scheme:dark){.tpl-modern{--bg:#14122a}}
.tpl-modern .sf-head{border-bottom:0;box-shadow:0 6px 24px rgba(17,24,39,.06)}
.tpl-modern .sf-hero-in{min-height:280px;background:linear-gradient(120deg,var(--accent),#ec4899)}
.tpl-modern .sf-card{border:0;box-shadow:0 8px 28px rgba(17,24,39,.08);transition:transform .15s}
.tpl-modern .sf-card:hover{transform:translateY(-3px)}
.tpl-modern .sf-grid{grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:22px}
.tpl-modern .sf-add{background:var(--accent);color:#fff;border-radius:999px}
.tpl-modern .sf-cart-btn{border-radius:999px}
/* ── Template: Minimal ── */
.tpl-minimal{--radius:0;--bg:#fff;--font-head:Georgia,"Times New Roman",serif}
@media (prefers-color-scheme:dark){.tpl-minimal{--bg:#121212;--card:#121212}}
.tpl-minimal .sf-head{border-bottom:1px solid var(--border)}
.tpl-minimal .sf-hero-in{background:var(--bg);color:var(--text);min-height:160px;align-items:center;justify-content:center;text-align:center}
.tpl-minimal .sf-hero-in img{opacity:.18}
.tpl-minimal .sf-hero-txt h1{font-weight:400;font-size:clamp(28px,5vw,48px)}
.tpl-minimal .sf-card{border:0;background:transparent}
.tpl-minimal .sf-card-body{padding:10px 2px}
.tpl-minimal .sf-card-title{font-weight:500}
.tpl-minimal .sf-price{font-weight:500}
.tpl-minimal .sf-add{border-color:var(--text);color:var(--text);border-radius:0;text-transform:uppercase;letter-spacing:.08em;font-size:11px}
.tpl-minimal .sf-add:hover{background:var(--text);color:var(--bg)}
.tpl-minimal .sf-cart-btn{background:var(--text);color:var(--bg);border-radius:0}
.tpl-minimal .sf-cart-count{color:var(--text)}
.tpl-minimal .sf-badge{background:var(--text);color:var(--bg);border-radius:0}
/* ── Template: Bold ── */
.tpl-bold{--head-bg:#0b0b12;--head-text:#fff;--radius:10px}
.tpl-bold .sf-head{border-bottom:3px solid var(--accent)}
.tpl-bold .sf-search input{background:#1c1c28;border-color:#2c2c3c;color:#fff}
.tpl-bold .sf-hero-in{min-height:320px;background:#0b0b12}
.tpl-bold .sf-hero-txt h1{font-size:clamp(32px,6vw,64px);text-transform:uppercase;font-weight:900;letter-spacing:-.03em}
.tpl-bold .sf-card{border:2px solid var(--text)}
.tpl-bold .sf-card-title{text-transform:uppercase;font-weight:800;letter-spacing:.02em}
.tpl-bold .sf-price{font-size:19px;color:var(--accent)}
.tpl-bold .sf-add{background:var(--text);color:var(--bg);border-color:var(--text);text-transform:uppercase;font-weight:800}
.tpl-bold .sf-add:hover{background:var(--accent);border-color:var(--accent);color:#fff}

@media (max-width:640px){.sf-cart-btn .lbl{display:none}.sf-grid,.tpl-modern .sf-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.sf-hero-txt,.sf-slide-txt{padding:20px}.sf-slider{aspect-ratio:4/3}}
</style>
