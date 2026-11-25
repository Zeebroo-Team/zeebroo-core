<style>
.pl-wrap{max-width:1080px;margin:0 auto;padding:clamp(12px,4vh,40px) 4px 40px;}
.pl-eyebrow{display:inline-flex;align-items:center;gap:7px;padding:5px 13px;border-radius:999px;margin-bottom:12px;font-size:11.5px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;
    background:color-mix(in srgb,var(--primary) 10%,transparent);color:var(--primary);border:1px solid color-mix(in srgb,var(--primary) 30%,transparent);}
.pl-eyebrow--bad{background:color-mix(in srgb,#ef4444 10%,transparent);color:#b91c1c;border-color:color-mix(in srgb,#ef4444 30%,transparent);}
.pl-title{margin:0;font-size:clamp(24px,3.2vw,32px);font-weight:800;letter-spacing:-.03em;color:var(--text);}
.pl-sub{margin:6px 0 22px;font-size:14px;color:var(--muted);max-width:680px;line-height:1.5;}
.pl-err{padding:12px 16px;border-radius:12px;margin-bottom:16px;font-size:13px;font-weight:600;color:#b91c1c;
    border:1px solid color-mix(in srgb,#ef4444 38%,var(--border));background:color-mix(in srgb,#ef4444 8%,var(--card));}
.pl-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:14px;}
.pl-card{border:1px solid var(--border);border-radius:14px;background:var(--card);padding:18px;display:flex;flex-direction:column;gap:10px;}
.pl-card--rec{border-color:color-mix(in srgb,#16a34a 55%,var(--border));box-shadow:0 0 0 3px color-mix(in srgb,#16a34a 12%,transparent);}
.pl-card-name{margin:0;font-size:17px;font-weight:800;}
.pl-price{font-size:22px;font-weight:800;letter-spacing:-.02em;}
.pl-price small{font-size:12px;font-weight:600;color:var(--muted);}
.pl-strike{font-size:12px;color:var(--muted);text-decoration:line-through;margin-left:6px;}
.pl-chips{display:flex;flex-wrap:wrap;gap:5px;}
.pl-chip{font-size:11px;font-weight:600;padding:2px 8px;border-radius:999px;border:1px solid var(--border);color:var(--muted);}
.pl-btn{margin-top:auto;display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:10px 16px;border-radius:10px;border:none;
    background:var(--btn-bg);color:#fff;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;text-decoration:none;}
.pl-btn:hover{background:var(--btn-hover);color:#111827;}
.pl-btn--ghost{background:transparent;color:var(--text);border:1px solid var(--border);}
.pl-btn--danger{background:#dc2626;}
.pl-btn[disabled]{opacity:.45;cursor:not-allowed;}
.pl-box{border:1px solid var(--border);border-radius:14px;background:var(--card);padding:18px 20px;margin-bottom:16px;}
.pl-box h3{margin:0 0 6px;font-size:15px;font-weight:800;}
.pl-box p{margin:0 0 10px;font-size:13px;color:var(--muted);line-height:1.5;}
.pl-warn{border-color:color-mix(in srgb,#f59e0b 50%,var(--border));background:color-mix(in srgb,#f59e0b 7%,var(--card));}
.pl-loss{display:flex;gap:10px;align-items:flex-start;padding:10px 0;border-top:1px solid var(--border);font-size:13px;}
.pl-loss:first-of-type{border-top:none;}
.pl-loss i{color:#d97706;margin-top:2px;}
.pl-member{display:flex;align-items:center;gap:10px;padding:8px 10px;border:1px solid var(--border);border-radius:10px;margin-bottom:6px;font-size:13px;cursor:pointer;}
.pl-member input{width:15px;height:15px;accent-color:var(--primary);}
.pl-member small{color:var(--muted);}
.pl-input{width:100%;max-width:260px;box-sizing:border-box;padding:10px 13px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);font-size:14px;font-family:inherit;}
.pl-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:6px;}
</style>
