<style>
.bmg-toolbar{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;margin-bottom:14px;}
.bmg-search{display:flex;gap:6px;flex-wrap:wrap;}
.bmg-search input{box-sizing:border-box;padding:8px 10px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);min-width:200px;}
.bmg-search input:focus{outline:none;border-color:var(--primary);}
.bmg-toolbar-actions{display:flex;gap:8px;flex-wrap:wrap;}
.bmg-table-wrap{border:1px solid var(--border);border-radius:11px;overflow:hidden;background:var(--card);overflow-x:auto;}
.bmg-table{width:100%;border-collapse:collapse;font-size:13px;}
.bmg-table th{text-align:left;padding:9px 12px;background:color-mix(in srgb,var(--card) 92%,transparent);font-size:10px;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);border-bottom:1px solid var(--border);white-space:nowrap;}
.bmg-table td{padding:10px 12px;border-bottom:1px solid color-mix(in srgb,var(--border) 70%,transparent);vertical-align:middle;}
.bmg-table tr:last-child td{border-bottom:none;}
.bmg-table tr:hover td{background:color-mix(in srgb,var(--card) 95%,var(--border) 5%);}
.bmg-empty{padding:48px 16px;text-align:center;color:var(--muted);}
.bmg-empty i{font-size:32px;display:block;margin-bottom:10px;opacity:.4;}
.bmg-empty p{margin:0;font-size:13px;}
.bmg-badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;border:1px solid color-mix(in srgb,var(--primary) 30%,var(--border));background:color-mix(in srgb,var(--primary) 10%,transparent);}
.bmg-badge--active{border-color:color-mix(in srgb,#22c55e 40%,var(--border));background:color-mix(in srgb,#22c55e 12%,transparent);color:#16a34a;}
.bmg-badge--inactive{border-color:color-mix(in srgb,var(--muted) 30%,var(--border));background:transparent;color:var(--muted);}
.bmg-badge--pending{border-color:color-mix(in srgb,#f59e0b 40%,var(--border));background:color-mix(in srgb,#f59e0b 12%,transparent);color:#b45309;}
.bmg-badge--in_progress{border-color:color-mix(in srgb,#3b82f6 40%,var(--border));background:color-mix(in srgb,#3b82f6 12%,transparent);color:#2563eb;}
.bmg-badge--completed{border-color:color-mix(in srgb,#22c55e 40%,var(--border));background:color-mix(in srgb,#22c55e 12%,transparent);color:#16a34a;}
.bmg-badge--cancelled{border-color:color-mix(in srgb,#ef4444 35%,var(--border));background:color-mix(in srgb,#ef4444 8%,transparent);color:#dc2626;}
.bmg-badge--draft{border-color:color-mix(in srgb,var(--muted) 30%,var(--border));background:transparent;color:var(--muted);}
.bmg-badge--approved{border-color:color-mix(in srgb,#22c55e 40%,var(--border));background:color-mix(in srgb,#22c55e 12%,transparent);color:#16a34a;}
.bmg-badge--rejected{border-color:color-mix(in srgb,#ef4444 35%,var(--border));background:color-mix(in srgb,#ef4444 8%,transparent);color:#dc2626;}
.bmg-badge--paid{border-color:color-mix(in srgb,#3b82f6 40%,var(--border));background:color-mix(in srgb,#3b82f6 12%,transparent);color:#2563eb;}
.bmg-actions{display:flex;gap:5px;justify-content:flex-end;}
.bmg-action-btn{display:inline-flex;align-items:center;gap:5px;padding:5px 10px;font-size:11.5px;font-weight:700;border-radius:8px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 90%,transparent);color:var(--text);cursor:pointer;text-decoration:none;transition:all .15s;}
.bmg-action-btn:hover{box-shadow:0 2px 8px rgba(0,0,0,.08);}
.bmg-action-btn--edit{border-color:color-mix(in srgb,var(--primary) 40%,var(--border));background:color-mix(in srgb,var(--primary) 10%,transparent);}
.bmg-action-btn--edit:hover{background:color-mix(in srgb,var(--primary) 20%,transparent);}
.bmg-action-btn--del{border-color:color-mix(in srgb,#ef4444 35%,var(--border));background:transparent;color:#f97373;}
.bmg-action-btn--del:hover{background:color-mix(in srgb,#ef4444 8%,transparent);}
:is(html[data-theme="light"],html[data-theme="light_blue"]) .bmg-action-btn--del{color:#dc2626;}
/* Modal */
.bmg-modal{position:fixed;inset:0;z-index:9100;display:flex;align-items:center;justify-content:center;padding:16px;visibility:hidden;opacity:0;pointer-events:none;transition:opacity .22s ease,visibility .22s;}
.bmg-modal.is-open{visibility:visible;opacity:1;pointer-events:auto;}
.bmg-modal__backdrop{position:absolute;inset:0;background:rgba(2,6,23,.58);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);}
.bmg-modal__panel{position:relative;z-index:1;width:min(100%,520px);border-radius:18px;border:1px solid var(--border);background:var(--card);box-shadow:0 28px 64px rgba(0,0,0,.32),0 0 0 1px rgba(255,255,255,.05);overflow:hidden;transform:translateY(10px) scale(.97);transition:transform .28s cubic-bezier(.34,1.15,.64,1);max-height:90vh;display:flex;flex-direction:column;}
.bmg-modal.is-open .bmg-modal__panel{transform:none;}
.bmg-modal__head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:14px 18px;border-bottom:1px solid var(--border);background:color-mix(in srgb,var(--card) 93%,var(--border));}
.bmg-modal__head h3{margin:0;font-size:15px;font-weight:800;color:var(--text);}
.bmg-modal__close{width:30px;height:30px;display:flex;align-items:center;justify-content:center;padding:0;line-height:1;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--muted);cursor:pointer;font-size:13px;transition:all .15s;}
.bmg-modal__close:hover{border-color:var(--text);color:var(--text);}
.bmg-modal__body{padding:18px;display:grid;gap:12px;overflow-y:auto;}
.bmg-modal__grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
@media(max-width:480px){.bmg-modal__grid{grid-template-columns:1fr;}}
.bmg-field label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin-bottom:5px;}
.bmg-field input,.bmg-field textarea,.bmg-field select{width:100%;box-sizing:border-box;padding:9px 12px;font-size:13px;border-radius:9px;border:1px solid var(--border);background:var(--card);color:var(--text);outline:none;transition:border-color .15s;}
.bmg-field input:focus,.bmg-field textarea:focus,.bmg-field select:focus{border-color:var(--primary);box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 12%,transparent);}
.bmg-field-inline{display:flex;gap:6px;align-items:flex-end;}
.bmg-field-inline .bmg-field{flex:1;}
.bmg-modal__foot{display:flex;justify-content:flex-end;gap:8px;padding:12px 18px;border-top:1px solid var(--border);background:color-mix(in srgb,var(--card) 96%,var(--border));}
.bmg-btn-ghost{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;font-size:13px;font-weight:700;border-radius:9px;border:1px solid var(--border);background:transparent;color:var(--muted);cursor:pointer;transition:all .15s;}
.bmg-btn-ghost:hover{border-color:var(--text);color:var(--text);}
.bmg-btn-primary{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;font-size:13px;font-weight:700;border-radius:9px;border:1px solid color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 14%,transparent);color:var(--text);cursor:pointer;transition:all .15s;text-decoration:none;}
.bmg-btn-primary:hover{background:color-mix(in srgb,var(--primary) 24%,transparent);}
.bmg-btn-sm{padding:5px 10px;font-size:11.5px;}
/* Import results */
.bmg-import-results{margin-bottom:14px;border:1px solid var(--border);border-radius:11px;overflow:hidden;}
.bmg-import-results summary{cursor:pointer;padding:10px 14px;font-weight:700;font-size:13px;background:color-mix(in srgb,var(--card) 92%,transparent);}
.bmg-import-results table{width:100%;border-collapse:collapse;font-size:12px;}
.bmg-import-results th,.bmg-import-results td{padding:7px 12px;border-bottom:1px solid color-mix(in srgb,var(--border) 70%,transparent);text-align:left;}
</style>
