<style>
.pcat-page-card{max-width:100%;margin:0;}
.pcat-nav{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 14px;font-size:12px;}
.pcat-nav a{color:var(--primary);font-weight:600;text-decoration:none;padding:4px 8px;border-radius:6px;border:1px solid transparent;}
.pcat-nav a:hover{border-color:var(--border);background:color-mix(in srgb,var(--primary) 6%,transparent);}
.pcat-nav a.is-active{border-color:color-mix(in srgb,var(--primary) 35%,var(--border));background:color-mix(in srgb,var(--primary) 10%,transparent);color:var(--text);}
.pcat-toolbar{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px;}
.pcat-field label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:5px;}
.pcat-field input,.pcat-field textarea,.pcat-field select{width:100%;box-sizing:border-box;padding:9px 10px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);}
.pcat-field select{cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2394a3b8' d='M2.5 4.5 6 8l3.5-3.5'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;padding-right:30px;}
.pcat-field textarea{min-height:70px;line-height:1.45;resize:vertical;font-family:inherit;}
.pcat-form-grid{display:grid;gap:10px;}@media (min-width:720px){.pcat-form-grid--2{grid-template-columns:repeat(2,minmax(0,1fr));gap:12px 16px}}
.pcat-banner{margin:0 0 12px;padding:10px 12px;border-radius:10px;font-size:13px;}
.pcat-banner--ok{border:1px solid color-mix(in srgb,#22c55e 40%,var(--border));background:color-mix(in srgb,#22c55e 9%,transparent);}
.pcat-banner--err{border:1px solid color-mix(in srgb,#f87171 40%,var(--border));background:color-mix(in srgb,#f87171 8%,transparent);}
.pcat-inline{border-radius:12px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 98%,transparent);padding:14px 16px 16px;}
.pcat-inline h2{margin:0 0 8px;font-size:16px;font-weight:800;}
.pcat-muted{margin:6px 0 0;font-size:13px;line-height:1.45;color:var(--muted);max-width:62ch;}
.pcat-table-wrap{border:1px solid var(--border);border-radius:11px;overflow:auto;}
.pcat-table{width:100%;border-collapse:collapse;font-size:13px;min-width:480px;}
.pcat-table th{text-align:left;padding:9px 12px;background:color-mix(in srgb,var(--card) 92%,transparent);font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);border-bottom:1px solid var(--border);}
.pcat-table td{padding:10px 12px;border-bottom:1px solid color-mix(in srgb,var(--border) 80%,transparent);vertical-align:top;}
.pcat-table tr:last-child td{border-bottom:none;}
.pcat-table tbody.pcat-table__body--sortable tr{cursor:default;}
.pcat-drag-handle{
    display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;
    border:1px solid var(--border);border-radius:8px;background:color-mix(in srgb,var(--card) 92%,transparent);
    color:var(--muted);cursor:grab;touch-action:none;
}
.pcat-drag-handle:active{cursor:grabbing;}
.pcat-drag-handle:hover{border-color:color-mix(in srgb,var(--primary) 35%,var(--border));color:var(--text);}
.pcat-drag-handle i{font-size:12px;line-height:1;}
.pcat-sort-ghost{opacity:.55;background:color-mix(in srgb,var(--primary) 8%,var(--card));}
.pcat-sort-chosen{box-shadow:0 6px 18px -10px rgba(0,0,0,.35);}
.pcat-reorder-status{font-size:12px;color:var(--muted);margin-left:auto;}
.pcat-reorder-status.is-saving{color:var(--primary);}
.pcat-reorder-status.is-error{color:#f87171;}
.pcat-subcat-indent{color:var(--muted);margin-right:6px;font-weight:700;}
.pcat-list{display:flex;flex-direction:column;gap:10px;}
.pcat-list .pcat-block-group{display:flex;flex-direction:column;gap:5px;}
.pcat-list .pcat-parent-slot{min-height:38px;border-radius:8px;border:1px dashed transparent;transition:border-color .15s ease,background .15s ease;}
.pcat-list .pcat-parent-slot:empty,.pcat-list .pcat-parent-slot--promote{border-color:color-mix(in srgb,var(--primary) 22%,var(--border));background:color-mix(in srgb,var(--primary) 4%,transparent);}
.pcat-list .pcat-tree-item{display:flex;flex-direction:column;gap:5px;}
.pcat-list .pcat-sublist-wrap{display:flex;flex-direction:column;gap:4px;}
.pcat-list .pcat-sublist-wrap--nested .pcat-sublist-head{display:none;}
.pcat-list .pcat-sublist{min-height:30px;display:flex;flex-direction:column;gap:5px;padding:2px 0 2px calc(10px + (var(--pcat-depth, 0) * 8px));border-left:2px solid color-mix(in srgb,var(--border) 80%,transparent);}
.pcat-list .pcat-sublist:empty{border-left-style:dashed;border-left-color:color-mix(in srgb,var(--primary) 20%,var(--border));}
.pcat-list .pcat-drop-hint{margin:0;padding:8px 10px;font-size:11px;text-align:center;}
.pcat-list .pcat-sublist-head{padding:2px 2px 0 10px;}
.pcat-list .pcat-sublist-head__label{display:inline-flex;flex-wrap:wrap;align-items:center;gap:4px;font-size:11px;color:var(--text);}
.pcat-list .pcat-sublist-head__label .muted{font-weight:400;}
.pcat-list .pcat-card{
    display:grid;grid-template-columns:auto 1fr auto;gap:6px 10px;align-items:center;
    padding:7px 10px;border:1px solid var(--border);border-radius:8px;
    background:color-mix(in srgb,var(--card) 98%,transparent);
}
.pcat-list .pcat-card .pcat-drag-handle{width:22px;height:22px;border-radius:6px;flex-shrink:0;}
.pcat-list .pcat-card .pcat-drag-handle i{font-size:10px;}
@media (max-width:639px){
    .pcat-list .pcat-card{grid-template-columns:auto 1fr;align-items:start;}
    .pcat-list .pcat-card__actions{grid-column:1/-1;justify-content:flex-start;padding-top:2px;}
}
.pcat-list .pcat-card--sub{margin-left:calc(6px + (var(--pcat-depth, 1) * 6px));border-left:2px solid color-mix(in srgb,var(--primary) 28%,var(--border));}
.pcat-list .pcat-card__head{display:flex;align-items:center;gap:4px;min-width:0;}
.pcat-list .pcat-card__title{margin:0;font-size:13px;font-weight:700;color:var(--text);line-height:1.25;}
.pcat-list .pcat-card__desc{margin:2px 0 0;font-size:11px;line-height:1.35;}
.pcat-list .pcat-card__meta{display:flex;flex-wrap:wrap;align-items:center;gap:4px 6px;margin-top:3px;font-size:11px;}
.pcat-list .pcat-card__actions{display:flex;flex-wrap:wrap;align-items:center;justify-content:flex-end;gap:6px;}
.pcat-list .pcat-card .pcat-badge{font-size:10px;padding:2px 6px;}
.pcat-list .pcat-card .pcat-link{font-size:11px;}
.pcat-list .pcat-card .pcat-btn-del{padding:3px 6px;font-size:10px;border-radius:5px;}
.pcat-list .pcat-card .pcat-card__note{font-size:10px;}
.pcat-card{
    display:grid;grid-template-columns:auto 1fr auto;gap:12px 14px;align-items:start;
    padding:12px 14px;border:1px solid var(--border);border-radius:11px;
    background:color-mix(in srgb,var(--card) 98%,transparent);
}
.pcat-card--sub{margin-left:12px;border-left:3px solid color-mix(in srgb,var(--primary) 28%,var(--border));}
.pcat-card__head{display:flex;align-items:center;gap:6px;flex-wrap:wrap;}
.pcat-card__title{margin:0;font-size:15px;font-weight:700;color:var(--text);line-height:1.3;}
.pcat-card__desc{margin:6px 0 0;font-size:12px;line-height:1.45;}
.pcat-card__meta{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-top:8px;font-size:12px;}
.pcat-card__actions{display:flex;flex-wrap:wrap;align-items:center;justify-content:flex-end;gap:10px;padding-top:2px;}
.pcat-block-group.pcat-sort-ghost,.pcat-card.pcat-sort-ghost{opacity:.55;}
.pcat-block-group.pcat-sort-chosen,.pcat-card.pcat-sort-chosen{box-shadow:0 6px 18px -10px rgba(0,0,0,.35);}
.pcat-badge{font-size:11px;font-weight:700;padding:3px 8px;border-radius:999px;border:1px solid var(--border);display:inline-block;}
.pcat-badge--on{border-color:color-mix(in srgb,#22c55e 45%,var(--border));background:color-mix(in srgb,#22c55e 12%,transparent);}
.pcat-badge--off{opacity:.8;color:var(--muted);}
.pcat-link{color:var(--primary);font-weight:600;text-decoration:none;font-size:12px;}
.pcat-link:hover{text-decoration:underline;}
.pcat-btn-del{padding:6px 9px;font-size:11px;font-weight:600;border-radius:7px;border:1px solid color-mix(in srgb,#ef4444 42%,var(--border));background:transparent;color:#f97373;cursor:pointer;}
:is(html[data-theme="light"],html[data-theme="light_blue"]) .pcat-btn-del{color:#dc2626;}
.pcat-modal{position:fixed;inset:0;z-index:120;display:flex;justify-content:center;align-items:flex-start;padding:max(12px,2.5vh) max(14px,env(safe-area-inset-right)) calc(14px + env(safe-area-inset-bottom)) max(14px,env(safe-area-inset-left));overflow:auto;box-sizing:border-box;opacity:0;visibility:hidden;pointer-events:none;transition:opacity .22s ease,visibility .22s ease;}
.pcat-modal.pcat-modal--open{opacity:1;visibility:visible;pointer-events:auto;}
.pcat-modal__backdrop{position:fixed;inset:0;z-index:0;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);}
:is(html[data-theme="light"],html[data-theme="light_blue"]) .pcat-modal__backdrop{background:rgba(17,24,39,.38);}
.pcat-modal__panel{position:relative;z-index:1;width:100%;max-width:520px;margin:auto;border-radius:14px;border:1px solid var(--border);background:var(--card);box-shadow:0 20px 48px rgba(0,0,0,.32);display:flex;flex-direction:column;max-height:min(94vh,calc(100dvh - 48px));}
.pcat-modal__head{display:flex;justify-content:space-between;align-items:center;padding:11px 14px;border-bottom:1px solid var(--border);}
.pcat-modal__head h2{margin:0;font-size:15px;font-weight:800;}
.pcat-modal__close{width:32px;height:32px;display:grid;place-items:center;border:1px solid var(--border);border-radius:9px;background:transparent;color:inherit;cursor:pointer;font-size:17px;line-height:1;}
.pcat-modal__body{padding:14px 14px 16px;overflow:auto;}
html.pcat-modal-open-html,html.pcat-modal-open-html body{overflow:hidden;}
.pcat-active-row{display:flex;align-items:center;justify-content:space-between;gap:14px;width:100%;padding:11px 14px;box-sizing:border-box;border-radius:10px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 94%,transparent);}
.pcat-active-row__lbl{margin:0;font-size:13px;font-weight:600;color:var(--text);cursor:pointer;}
.pcat-switch{position:relative;display:inline-block;width:46px;height:26px;flex-shrink:0;}
.pcat-switch input{opacity:0;width:0;height:0;margin:0;position:absolute;}
.pcat-switch-slider{position:absolute;inset:0;cursor:pointer;background:#475569;border-radius:999px;transition:.2s;}
.pcat-switch-slider:before{content:"";position:absolute;height:20px;width:20px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.2s;box-shadow:0 1px 3px rgba(0,0,0,.22);}
.pcat-switch input:checked + .pcat-switch-slider{background:#22c55e;}
.pcat-switch input:checked + .pcat-switch-slider:before{transform:translateX(20px);}
.pcat-switch input:focus-visible + .pcat-switch-slider{box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 45%,transparent);}
</style>
