<style>
.fm-page{max-width:100%;margin:0;}
.fm-toolbar{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px;}
.fm-toolbar__actions{display:flex;flex-wrap:wrap;gap:8px;}
.fm-banner{margin:0 0 12px;padding:10px 12px;border-radius:10px;font-size:13px;}
.fm-banner--ok{border:1px solid color-mix(in srgb,#22c55e 40%,var(--border));background:color-mix(in srgb,#22c55e 9%,transparent);font-weight:600;}
.fm-banner--err{border:1px solid color-mix(in srgb,#f87171 40%,var(--border));background:color-mix(in srgb,#f87171 8%,transparent);}
.fm-crumbs{display:flex;flex-wrap:wrap;align-items:center;gap:4px;margin:0 0 14px;font-size:13px;}
.fm-crumbs a{color:var(--primary);font-weight:600;text-decoration:none;}
.fm-crumbs a:hover{text-decoration:underline;}
.fm-crumbs__sep{color:var(--muted);opacity:.7;}
.fm-crumbs__current{color:var(--text);font-weight:600;}
.fm-inline{border-radius:12px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 98%,transparent);padding:14px 16px 16px;margin-bottom:14px;}
.fm-inline h2{margin:0 0 8px;font-size:16px;font-weight:800;}
.fm-muted{margin:6px 0 0;font-size:13px;line-height:1.45;color:var(--muted);max-width:62ch;}
.fm-field label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:5px;}
.fm-field input,.fm-field textarea,.fm-field select{width:100%;box-sizing:border-box;padding:9px 10px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);}
.fm-field textarea{min-height:70px;line-height:1.45;resize:vertical;font-family:inherit;}
.fm-form-grid{display:grid;gap:10px;}
.fm-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px;margin-top:12px;}
.fm-card{
    display:flex;flex-direction:column;gap:8px;padding:12px;border-radius:11px;border:1px solid var(--border);
    background:color-mix(in srgb,var(--card) 98%,transparent);text-decoration:none;color:inherit;min-height:120px;
    transition:border-color .15s ease,box-shadow .15s ease;
}
a.fm-card--folder:hover{border-color:color-mix(in srgb,var(--primary) 40%,var(--border));box-shadow:0 4px 14px rgba(0,0,0,.08);}
.fm-card__icon{font-size:28px;line-height:1;color:color-mix(in srgb,var(--primary) 70%,var(--muted));}
.fm-card__name{font-size:13px;font-weight:700;color:var(--text);word-break:break-word;line-height:1.3;}
.fm-card__meta{font-size:11px;color:var(--muted);margin-top:auto;}
.fm-card__actions{display:flex;flex-wrap:wrap;gap:6px;margin-top:4px;}
.fm-card__thumb{width:100%;aspect-ratio:4/3;border-radius:8px;object-fit:cover;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 90%,transparent);}
.fm-link{color:var(--primary);font-weight:600;text-decoration:none;font-size:12px;}
.fm-link:hover{text-decoration:underline;}
.fm-btn-del{padding:6px 9px;font-size:11px;font-weight:600;border-radius:7px;border:1px solid color-mix(in srgb,#ef4444 42%,var(--border));background:transparent;color:#f97373;cursor:pointer;}
:is(html[data-theme="light"],html[data-theme="light_blue"]) .fm-btn-del{color:#dc2626;}
.fm-section-title{margin:16px 0 8px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);}
.fm-modal{position:fixed;inset:0;z-index:120;display:flex;justify-content:center;align-items:flex-start;padding:max(12px,2.5vh) 14px;overflow:auto;box-sizing:border-box;opacity:0;visibility:hidden;pointer-events:none;transition:opacity .22s ease,visibility .22s ease;}
.fm-modal.fm-modal--open{opacity:1;visibility:visible;pointer-events:auto;}
.fm-modal__backdrop{position:fixed;inset:0;z-index:0;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);}
.fm-modal__panel{position:relative;z-index:1;width:100%;max-width:480px;margin:auto;border-radius:14px;border:1px solid var(--border);background:var(--card);box-shadow:0 20px 48px rgba(0,0,0,.32);}
.fm-modal__head{display:flex;justify-content:space-between;align-items:center;padding:11px 14px;border-bottom:1px solid var(--border);}
.fm-modal__head h2{margin:0;font-size:15px;font-weight:800;}
.fm-modal__close{width:32px;height:32px;display:grid;place-items:center;border:1px solid var(--border);border-radius:9px;background:transparent;color:inherit;cursor:pointer;font-size:17px;line-height:1;}
.fm-modal__body{padding:14px 14px 16px;}
html.fm-modal-open-html,html.fm-modal-open-html body{overflow:hidden;}
.fm-upload-zone{
    display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;padding:20px 14px;
    border:2px dashed color-mix(in srgb,var(--primary) 35%,var(--border));border-radius:10px;background:color-mix(in srgb,var(--primary) 6%,transparent);
    text-align:center;cursor:pointer;transition:border-color .15s ease,background .15s ease;
}
.fm-upload-zone:hover,.fm-upload-zone.is-dragover{border-color:color-mix(in srgb,var(--primary) 55%,var(--border));background:color-mix(in srgb,var(--primary) 10%,transparent);}
.fm-upload-zone input[type="file"]{position:absolute;width:1px;height:1px;opacity:0;pointer-events:none;}
.fm-upload-zone__title{font-size:13px;font-weight:600;color:var(--text);}
.fm-upload-zone__hint{font-size:12px;color:var(--muted);max-width:36ch;line-height:1.4;}
.fm-file-list{margin-top:10px;font-size:12px;color:var(--muted);}
</style>
