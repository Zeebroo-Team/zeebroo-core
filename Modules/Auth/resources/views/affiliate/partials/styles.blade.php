{{-- Shared styles for the affiliate panel and the admin Affiliates screens (aff-*). --}}
<style>
/* ── layout ── */
.aff-wrap{max-width:1200px;margin:0 auto;}
/* The hidden attribute must win over the display rules below (e.g. .aff-docs is a grid). */
.aff-wrap [hidden]{display:none !important;}
.aff-back{display:inline-flex;align-items:center;gap:7px;margin-bottom:14px;font-size:12.5px;font-weight:600;color:var(--muted);text-decoration:none;}
.aff-back:hover{color:var(--primary);}
.aff-header{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:22px;flex-wrap:wrap;}
.aff-title{margin:0;font-size:19px;font-weight:800;letter-spacing:-.025em;display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
.aff-title > i{color:var(--primary);}
.aff-sub{margin:4px 0 0;font-size:12.5px;color:var(--muted);max-width:660px;line-height:1.5;}
.aff-header-actions{display:flex;gap:8px;flex-wrap:wrap;}

/* ── flash ── */
.aff-msg{display:flex;align-items:center;gap:10px;padding:13px 16px;border-radius:12px;margin-bottom:20px;
    font-size:13px;font-weight:600;border:1px solid color-mix(in srgb,#16a34a 38%,var(--border));
    background:color-mix(in srgb,#16a34a 9%,var(--card));}
.aff-msg-err{border-color:color-mix(in srgb,#ef4444 38%,var(--border));background:color-mix(in srgb,#ef4444 9%,var(--card));color:#b91c1c;}

/* ── stats ── */
.aff-stats{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;margin-bottom:28px;}
.aff-stat{border:1px solid var(--border);border-radius:16px;background:var(--card);padding:18px 20px;display:flex;flex-direction:column;gap:6px;}
.aff-stat-icon{width:38px;height:38px;border-radius:11px;display:grid;place-items:center;font-size:16px;margin-bottom:4px;}
.aff-stat-label{font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);}
.aff-stat-value{font-size:28px;font-weight:800;letter-spacing:-.03em;line-height:1;}
.aff-stat-sub{font-size:12px;color:var(--muted);}

/* ── sections ── */
.aff-section{margin-bottom:30px;}
.aff-section-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px;flex-wrap:wrap;}
.aff-section-title{margin:0;font-size:15px;font-weight:750;display:flex;align-items:center;gap:8px;}
.aff-section-title i{color:var(--primary);}
.aff-section-meta{font-size:12px;color:var(--muted);font-weight:500;}

/* ── buttons ── */
.aff-add-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 15px;border-radius:9px;text-decoration:none;
    border:1px solid color-mix(in srgb,var(--btn-bg) 55%,var(--border));background:var(--btn-bg);
    color:#fff;font-size:12.5px;font-weight:700;cursor:pointer;font-family:inherit;transition:.15s ease;}
.aff-add-btn:hover{background:var(--btn-hover);color:#111827;}
.aff-add-btn:focus-visible,.aff-btn:focus-visible,.aff-submit-btn:focus-visible,.aff-cancel-btn:focus-visible{outline:2px solid color-mix(in srgb,var(--primary) 60%,transparent);outline-offset:2px;}
.aff-btn{padding:6px 10px;border-radius:8px;border:1px solid var(--border);background:transparent;text-decoration:none;
    color:var(--text);font-size:11.5px;font-weight:600;cursor:pointer;font-family:inherit;transition:.14s;
    display:inline-flex;align-items:center;justify-content:center;gap:5px;white-space:nowrap;}
.aff-btn:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 7%,transparent);}
.aff-btn--danger:hover{border-color:color-mix(in srgb,#ef4444 45%,var(--border));background:color-mix(in srgb,#ef4444 7%,transparent);color:#b91c1c;}
.aff-btn.is-copied{border-color:color-mix(in srgb,#16a34a 50%,var(--border));background:color-mix(in srgb,#16a34a 10%,transparent);color:#16a34a;}
.aff-submit-btn{padding:9px 22px;border-radius:10px;border:none;background:var(--btn-bg);color:#fff;
    font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:7px;}
.aff-submit-btn:hover{background:var(--btn-hover);color:#111827;}
.aff-cancel-btn{padding:9px 18px;border-radius:10px;border:1px solid var(--border);background:transparent;
    color:var(--text);font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;}
.aff-cancel-btn:hover{background:color-mix(in srgb,var(--border) 40%,transparent);}

/* ── status pills ── */
.aff-status{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:999px;font-size:10.5px;font-weight:700;white-space:nowrap;}
.aff-status--on{background:color-mix(in srgb,#22c55e 14%,transparent);color:#16a34a;}
.aff-status--off{background:color-mix(in srgb,#6b7280 16%,transparent);color:#6b7280;}
.aff-status--warn{background:color-mix(in srgb,#f59e0b 16%,transparent);color:#b45309;}

.aff-status--info{background:color-mix(in srgb,#6366f1 13%,transparent);color:#4f46e5;}

/* ── commission page ── */
.aff-stat-value--money{font-size:22px;letter-spacing:-.02em;overflow-wrap:anywhere;}
.aff-note{display:flex;gap:11px;align-items:flex-start;padding:13px 16px;margin:-10px 0 26px;border-radius:12px;font-size:13px;line-height:1.55;
    border:1px solid color-mix(in srgb,#6366f1 30%,var(--border));background:color-mix(in srgb,#6366f1 6%,var(--card));}
.aff-note > i{color:#6366f1;margin-top:3px;}
.aff-filters{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:0;}
.aff-filters select{padding:9px 34px 9px 13px;border-radius:11px;border:1px solid var(--border);color:var(--text);font-size:13px;font-family:inherit;cursor:pointer;
    appearance:none;-webkit-appearance:none;
    background:color-mix(in srgb,var(--card) 94%,transparent) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' fill='none' stroke='%2394a3b8' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") no-repeat right 13px center;}
.aff-filters select:focus{outline:none;border-color:color-mix(in srgb,var(--primary) 55%,var(--border));box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 14%,transparent);}
.aff-filters .aff-search{min-width:200px;}
.aff-btn--tall{padding:9px 13px;font-size:12.5px;border-radius:10px;}
.aff-commission{color:#16a34a;}
.aff-table tfoot td{padding:13px 16px;font-size:13.5px;font-weight:700;border-top:1px solid var(--border);
    background:color-mix(in srgb,var(--card) 88%,var(--border));}
.aff-tfoot-label{text-align:right;}
.aff-table tfoot tr:hover td{background:color-mix(in srgb,var(--card) 88%,var(--border));}

/* ── withdrawals ── */
.aff-status--danger{background:color-mix(in srgb,#ef4444 13%,transparent);color:#dc2626;}
.aff-field textarea{width:100%;box-sizing:border-box;padding:10px 13px;border-radius:11px;min-height:76px;resize:vertical;
    border:1px solid var(--border);background:color-mix(in srgb,var(--card) 94%,transparent);color:var(--text);font-size:14px;font-family:inherit;line-height:1.45;}
.aff-field textarea:focus{outline:none;border-color:color-mix(in srgb,var(--primary) 55%,var(--border));box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 14%,transparent);}
.aff-field textarea.is-invalid{border-color:color-mix(in srgb,#ef4444 60%,var(--border));}
.aff-amount{display:flex;align-items:stretch;}
.aff-amount span{display:grid;place-items:center;padding:0 12px;border:1px solid var(--border);border-right:none;border-radius:11px 0 0 11px;
    background:color-mix(in srgb,var(--card) 88%,var(--border));font-size:13px;font-weight:700;color:var(--muted);}
.aff-field .aff-amount input{border-radius:0 11px 11px 0;font-variant-numeric:tabular-nums;font-weight:650;}
.aff-amount-hint{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;}
.aff-linkbtn{border:none;background:none;padding:0;color:var(--primary);font:inherit;font-size:12px;font-weight:700;cursor:pointer;}
.aff-linkbtn:hover{text-decoration:underline;}
.aff-pay-to{border:1px solid var(--border);border-radius:12px;padding:12px 14px;background:color-mix(in srgb,var(--card) 92%,var(--border));font-size:13px;line-height:1.55;}
.aff-pay-to-head{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:4px;font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);}
.aff-pay-to-head a{text-transform:none;letter-spacing:0;font-weight:700;color:var(--primary);text-decoration:none;}
.aff-banner{display:flex;gap:12px;align-items:center;flex-wrap:wrap;padding:14px 16px;margin-bottom:22px;border-radius:12px;font-size:13px;line-height:1.5;
    border:1px solid color-mix(in srgb,#6366f1 30%,var(--border));background:color-mix(in srgb,#6366f1 6%,var(--card));}
.aff-banner > i{color:#6366f1;font-size:16px;}
.aff-banner form{margin:0 0 0 auto;}
.aff-banner--warn{border-color:color-mix(in srgb,#f59e0b 38%,var(--border));background:color-mix(in srgb,#f59e0b 8%,var(--card));}
.aff-banner--warn > i{color:#d97706;}
.aff-banner .aff-btn{margin-left:auto;}
.aff-banner form .aff-btn{margin-left:0;}
.aff-detail-lines{font-size:12px;color:var(--muted);line-height:1.5;margin-top:3px;}
.aff-detail-lines strong{color:var(--text);font-weight:650;}
.aff-statusbar{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px;}
.aff-statusbar a{display:inline-flex;align-items:center;gap:7px;padding:7px 12px;border-radius:999px;border:1px solid var(--border);text-decoration:none;
    color:var(--muted);font-size:12.5px;font-weight:650;background:var(--card);transition:.14s;}
.aff-statusbar a:hover{color:var(--text);border-color:color-mix(in srgb,var(--primary) 40%,var(--border));}
.aff-statusbar a.is-active{color:var(--text);border-color:color-mix(in srgb,var(--primary) 55%,var(--border));background:color-mix(in srgb,var(--primary) 10%,transparent);}
.aff-statusbar a span{min-width:18px;padding:0 6px;border-radius:999px;font-size:11px;line-height:18px;text-align:center;
    background:color-mix(in srgb,var(--border) 70%,transparent);color:var(--text);}
.aff-statusbar a.is-active span{background:var(--btn-bg);color:#fff;}
.aff-row-actions{display:flex;gap:6px;justify-content:flex-end;flex-wrap:nowrap;}
@media (max-width:760px){ .aff-row-actions{flex-wrap:wrap;justify-content:flex-start;} }
.aff-btn--ok:hover{border-color:color-mix(in srgb,#16a34a 50%,var(--border));background:color-mix(in srgb,#16a34a 8%,transparent);color:#15803d;}
.aff-summary{border:1px solid var(--border);border-radius:12px;padding:12px 14px;margin-bottom:16px;font-size:13px;line-height:1.6;
    background:color-mix(in srgb,var(--card) 92%,var(--border));}
.aff-summary strong{font-weight:700;}

/* ── settings page ── */
.aff-progress{display:flex;align-items:center;gap:14px;padding:13px 16px;margin-bottom:20px;border-radius:12px;font-size:13px;
    border:1px solid color-mix(in srgb,#f59e0b 38%,var(--border));background:color-mix(in srgb,#f59e0b 8%,var(--card));}
.aff-progress > i{color:#d97706;font-size:16px;}
.aff-progress-bar{flex:0 0 120px;height:6px;border-radius:999px;background:color-mix(in srgb,var(--border) 70%,transparent);overflow:hidden;margin-left:auto;}
.aff-progress-bar span{display:block;height:100%;border-radius:999px;background:#f59e0b;}
.aff-set{display:grid;grid-template-columns:240px 1fr;border:1px solid var(--border);border-radius:16px;background:var(--card);overflow:hidden;}
.aff-set-tabs{padding:14px 12px;border-right:1px solid var(--border);background:color-mix(in srgb,var(--card) 92%,var(--border));}
.aff-set-tab{width:100%;display:flex;align-items:center;gap:10px;padding:11px 12px;border-radius:11px;margin-bottom:6px;border:1px solid transparent;
    background:transparent;color:var(--muted);font-size:13px;font-weight:600;cursor:pointer;text-align:left;font-family:inherit;transition:.18s ease;}
.aff-set-tab > i:first-child{width:14px;text-align:center;font-size:13px;}
.aff-set-tab:hover{color:var(--text);border-color:var(--border);background:color-mix(in srgb,var(--primary) 6%,transparent);}
.aff-set-tab.is-active{color:var(--text);border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 10%,transparent);}
.aff-set-tab:focus-visible{outline:2px solid color-mix(in srgb,var(--primary) 60%,transparent);outline-offset:2px;}
.aff-set-tab-state{margin-left:auto;font-size:12px;}
.aff-set-tab-state--done{color:#16a34a;}
.aff-set-tab-state--todo{width:8px;height:8px;border-radius:50%;background:#f59e0b;}
.aff-set-panel{display:none;padding:24px 26px;min-width:0;}
.aff-set-panel.is-active{display:block;}
.aff-set-panel-title{margin:0 0 4px;font-size:16px;font-weight:750;}
.aff-set-panel-sub{margin:0 0 20px;font-size:13px;color:var(--muted);line-height:1.5;}
.aff-set-group{margin:24px 0 14px;padding-top:20px;border-top:1px solid color-mix(in srgb,var(--border) 70%,transparent);}
.aff-set-group-title{margin:0 0 4px;font-size:13.5px;font-weight:750;}
.aff-set-group-sub{margin:0 0 14px;font-size:12.5px;color:var(--muted);}
.aff-set-foot{margin-top:22px;display:flex;justify-content:flex-end;gap:10px;}
.aff-field-row.aff-field-row--3{grid-template-columns:1fr 1fr 1fr;}
.aff-req{color:#ef4444;}
.aff-secure{display:flex;gap:9px;align-items:flex-start;font-size:12.5px;color:var(--muted);line-height:1.5;margin:0 0 18px;padding:11px 13px;border-radius:11px;
    background:color-mix(in srgb,var(--card) 90%,var(--border));}
.aff-secure i{color:#16a34a;margin-top:2px;}

/* ID type choice */
.aff-choices{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.aff-choice{position:relative;display:flex;align-items:center;gap:11px;padding:12px 14px;border-radius:12px;border:1px solid var(--border);cursor:pointer;
    background:color-mix(in srgb,var(--card) 94%,transparent);transition:.15s ease;text-transform:none;letter-spacing:0;font-size:13.5px;font-weight:600;color:var(--text);}
.aff-field label.aff-choice{display:flex;margin:0;font-size:13.5px;font-weight:600;text-transform:none;letter-spacing:0;color:var(--text);}
.aff-choice input{position:absolute;opacity:0;pointer-events:none;}
.aff-choice i{width:30px;height:30px;border-radius:9px;display:grid;place-items:center;font-size:14px;
    background:color-mix(in srgb,var(--primary) 10%,transparent);color:var(--primary);flex-shrink:0;}
.aff-choice:hover{border-color:color-mix(in srgb,var(--primary) 35%,var(--border));}
.aff-choice:has(input:checked){border-color:color-mix(in srgb,var(--primary) 60%,var(--border));background:color-mix(in srgb,var(--primary) 8%,transparent);
    box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 12%,transparent);}
.aff-choice:has(input:focus-visible){outline:2px solid color-mix(in srgb,var(--primary) 60%,transparent);outline-offset:2px;}

/* document slots */
.aff-docs{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px;}
.aff-doc{border:1px solid var(--border);border-radius:14px;padding:14px;display:flex;flex-direction:column;gap:10px;background:color-mix(in srgb,var(--card) 96%,transparent);}
.aff-doc.is-invalid{border-color:color-mix(in srgb,#ef4444 55%,var(--border));}
.aff-doc-title{font-size:13px;font-weight:700;display:flex;align-items:center;justify-content:space-between;gap:8px;}
.aff-doc-preview{height:150px;border-radius:10px;border:1px solid color-mix(in srgb,var(--border) 70%,transparent);overflow:hidden;display:grid;place-items:center;
    background:color-mix(in srgb,var(--card) 85%,var(--border));text-decoration:none;color:var(--muted);}
.aff-doc-preview img{width:100%;height:100%;object-fit:contain;}
.aff-doc-preview .fa{font-size:34px;color:#ef4444;}
.aff-doc-meta{font-size:11.5px;color:var(--muted);overflow-wrap:anywhere;}
.aff-doc-actions{display:flex;gap:6px;flex-wrap:wrap;}
.aff-drop{position:relative;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;text-align:center;min-height:104px;padding:14px;
    border:1.5px dashed color-mix(in srgb,var(--border) 100%,var(--muted) 25%);border-radius:12px;cursor:pointer;transition:.15s ease;
    background:color-mix(in srgb,var(--card) 94%,transparent);color:var(--muted);font-size:12.5px;}
.aff-field label.aff-drop{display:flex;margin:0;text-transform:none;letter-spacing:0;font-weight:500;font-size:12.5px;color:var(--muted);}
.aff-drop:hover,.aff-drop.is-over{border-color:color-mix(in srgb,var(--primary) 60%,var(--border));background:color-mix(in srgb,var(--primary) 6%,transparent);color:var(--text);}
.aff-drop:focus-within{outline:2px solid color-mix(in srgb,var(--primary) 60%,transparent);outline-offset:2px;}
.aff-drop input{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;}
.aff-drop > i{font-size:20px;color:var(--primary);}
.aff-drop strong{color:var(--text);font-weight:650;}
.aff-drop.has-file{border-style:solid;border-color:color-mix(in srgb,#16a34a 50%,var(--border));background:color-mix(in srgb,#16a34a 6%,transparent);}
.aff-drop.has-file > i{color:#16a34a;}
.aff-drop-name{font-size:12px;color:var(--text);font-weight:600;overflow-wrap:anywhere;}

/* admin: settings details */
.aff-details{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px;}
.aff-detail-card{border:1px solid var(--border);border-radius:14px;background:var(--card);padding:16px;}
.aff-detail-card h3{margin:0 0 12px;font-size:13.5px;font-weight:750;display:flex;align-items:center;gap:8px;}
.aff-detail-card h3 i{color:var(--primary);}
.aff-detail-card h3 .aff-status{margin-left:auto;}
.aff-dl{display:grid;grid-template-columns:auto 1fr;gap:7px 14px;margin:0;font-size:13px;}
.aff-dl dt{color:var(--muted);font-size:12px;font-weight:600;}
.aff-dl dd{margin:0;overflow-wrap:anywhere;}

/* ── code cards ── */
.aff-codes{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:12px;}
.aff-code{border:1px solid var(--border);border-radius:14px;background:var(--card);padding:16px;display:flex;flex-direction:column;gap:10px;
    transition:border-color .14s,box-shadow .14s;}
.aff-code:hover{border-color:color-mix(in srgb,var(--primary) 30%,var(--border));box-shadow:0 4px 14px rgba(0,0,0,.06);}
.aff-code--off{opacity:.72;}
.aff-code-top{display:flex;align-items:center;justify-content:space-between;gap:10px;}
.aff-code-value{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:18px;font-weight:800;letter-spacing:.06em;}
.aff-code-label{font-size:12px;color:var(--muted);display:flex;align-items:center;gap:6px;}
.aff-code-link{display:flex;gap:6px;align-items:stretch;}
.aff-code-link input{flex:1;min-width:0;padding:7px 10px;border-radius:8px;border:1px solid var(--border);
    background:color-mix(in srgb,var(--card) 90%,var(--border));color:var(--muted);font-size:11.5px;font-family:inherit;}
.aff-code-link input:focus{outline:none;border-color:color-mix(in srgb,var(--primary) 55%,var(--border));color:var(--text);}
.aff-code-foot{display:flex;align-items:center;gap:6px;flex-wrap:wrap;}
.aff-code-foot form{margin:0;}
.aff-code-count{font-size:12px;font-weight:650;margin-right:auto;display:inline-flex;align-items:center;gap:6px;}
.aff-code-count i{color:var(--primary);}
.aff-code-date{font-size:11px;color:var(--muted);}

/* ── table ── */
.aff-card{border:1px solid var(--border);border-radius:16px;overflow:hidden;background:var(--card);}
.aff-table{width:100%;border-collapse:collapse;}
.aff-table th{padding:11px 16px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);
    background:color-mix(in srgb,var(--card) 88%,var(--border));text-align:left;border-bottom:1px solid var(--border);white-space:nowrap;}
.aff-table td{padding:13px 16px;font-size:13.5px;border-bottom:1px solid color-mix(in srgb,var(--border) 60%,transparent);vertical-align:middle;}
.aff-table tr:last-child td{border-bottom:none;}
.aff-table tr:hover td{background:color-mix(in srgb,var(--primary) 3%,transparent);}
.aff-user{display:flex;align-items:center;gap:11px;min-width:0;}
.aff-avatar{width:34px;height:34px;border-radius:50%;background:color-mix(in srgb,var(--primary) 14%,transparent);display:grid;place-items:center;
    font-size:13px;font-weight:700;color:var(--primary);flex-shrink:0;}
.aff-user-name{font-size:13.5px;font-weight:650;color:var(--text);}
.aff-user-email{font-size:12px;color:var(--muted);overflow-wrap:anywhere;}
.aff-muted{color:var(--muted);font-size:12.5px;}
.aff-mono{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12.5px;font-weight:700;letter-spacing:.04em;}
.aff-num{font-weight:700;font-variant-numeric:tabular-nums;}
.aff-link-row{color:inherit;text-decoration:none;}
.aff-link-row:hover .aff-user-name{color:var(--primary);}

/* ── search ── */
.aff-search{position:relative;min-width:220px;max-width:320px;flex:1;margin:0;}
.aff-search i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:12px;pointer-events:none;}
.aff-search input{width:100%;box-sizing:border-box;padding:9px 13px 9px 34px;border-radius:11px;border:1px solid var(--border);
    background:color-mix(in srgb,var(--card) 94%,transparent);color:var(--text);font-size:13px;font-family:inherit;}
.aff-search input:focus{outline:none;border-color:color-mix(in srgb,var(--primary) 55%,var(--border));box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 14%,transparent);}
.aff-pager{margin-top:16px;}

/* ── empty ── */
.aff-empty{padding:44px 24px;text-align:center;border:1px solid var(--border);border-radius:16px;background:var(--card);}
.aff-empty-icon{width:52px;height:52px;border-radius:14px;margin:0 auto 14px;display:grid;place-items:center;
    font-size:22px;background:color-mix(in srgb,var(--primary) 10%,transparent);color:var(--primary);}
.aff-empty-title{margin:0 0 6px;font-size:16px;font-weight:700;}
.aff-empty-sub{margin:0 auto;font-size:13px;color:var(--muted);max-width:460px;line-height:1.5;}

/* ── inline form card ── */
.aff-form-card{border:1px solid var(--border);border-radius:16px;background:var(--card);padding:22px;}
.aff-form-card-title{margin:0 0 4px;font-size:16px;font-weight:750;}
.aff-form-card-sub{margin:0 0 18px;font-size:13px;color:var(--muted);}
.aff-form-card-foot{margin-top:18px;display:flex;justify-content:flex-end;}

/* ── fields ── */
.aff-field{margin-bottom:16px;}
.aff-field:last-child{margin-bottom:0;}
.aff-field label{display:block;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin-bottom:6px;}
.aff-optional{font-weight:500;text-transform:none;letter-spacing:0;}
.aff-field input{width:100%;box-sizing:border-box;padding:10px 13px;border-radius:11px;
    border:1px solid var(--border);background:color-mix(in srgb,var(--card) 94%,transparent);color:var(--text);font-size:14px;font-family:inherit;}
.aff-field input:focus{outline:none;border-color:color-mix(in srgb,var(--primary) 55%,var(--border));box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 14%,transparent);}
.aff-field input.is-invalid{border-color:color-mix(in srgb,#ef4444 60%,var(--border));}
.aff-field input.aff-input-code{text-transform:uppercase;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;letter-spacing:.05em;}
.aff-field input.aff-input-code::placeholder{text-transform:none;font-family:inherit;letter-spacing:0;}
.aff-field-hint{margin:5px 0 0;font-size:11.5px;color:var(--muted);}
.aff-field-err{margin:5px 0 0;font-size:12px;font-weight:600;color:#ef4444;}
.aff-field-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.aff-field-row .aff-field{margin-bottom:16px;}

/* ── modal ── */
.aff-modal-overlay{position:fixed;inset:0;z-index:340;display:none;align-items:flex-start;justify-content:center;padding:20px;box-sizing:border-box;overflow-y:auto;}
.aff-modal-overlay.is-open{display:flex;}
.aff-modal-backdrop{position:fixed;inset:0;background:rgba(2,6,23,.55);backdrop-filter:blur(4px);cursor:pointer;}
:is(html[data-theme="light"],html[data-theme="light_blue"]) .aff-modal-backdrop{background:rgba(17,24,39,.42);}
.aff-modal-shell{position:relative;z-index:1;width:100%;max-width:560px;margin:auto 0;border-radius:18px;border:1px solid var(--border);
    background:var(--card);box-shadow:0 24px 56px rgba(0,0,0,.28);}
.aff-modal-head{padding:20px 22px 16px;border-bottom:1px solid var(--border);display:flex;align-items:flex-start;justify-content:space-between;gap:12px;}
.aff-modal-title{margin:0 0 3px;font-size:18px;font-weight:800;letter-spacing:-.02em;}
.aff-modal-sub{margin:0;font-size:13px;color:var(--muted);}
.aff-modal-close{width:34px;height:34px;border-radius:10px;border:1px solid var(--border);background:transparent;
    color:var(--text);cursor:pointer;display:grid;place-items:center;font-size:18px;line-height:1;padding:0;font-family:inherit;flex-shrink:0;}
.aff-modal-close:hover{background:color-mix(in srgb,#ef4444 8%,transparent);border-color:color-mix(in srgb,#ef4444 35%,var(--border));}
.aff-modal-body{padding:20px 22px;max-height:70vh;overflow-y:auto;}
.aff-modal-foot{padding:14px 22px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px;}

@media (max-width:900px){
  .aff-set{grid-template-columns:1fr;}
  .aff-set-tabs{display:flex;gap:8px;overflow-x:auto;-webkit-overflow-scrolling:touch;border-right:none;border-bottom:1px solid var(--border);padding:12px;}
  .aff-set-tab{width:auto;flex-shrink:0;margin-bottom:0;white-space:nowrap;}
  .aff-set-tab-state{margin-left:4px;}
}
@media (max-width:760px){
  .aff-set-panel{padding:18px 16px;}
  .aff-field-row.aff-field-row--3{grid-template-columns:1fr;}
  .aff-choices{grid-template-columns:1fr;}
  .aff-progress{flex-wrap:wrap;}
  .aff-progress-bar{flex-basis:100%;margin-left:0;}
  .aff-set-foot .aff-submit-btn{width:100%;justify-content:center;}
  .aff-stats{grid-template-columns:1fr 1fr;gap:10px;}
  .aff-stat-value.aff-stat-value--money{font-size:16px;white-space:nowrap;}
  .aff-filters{width:100%;}
  .aff-filters select{flex:1;}
  .aff-table tfoot{display:block;}
  .aff-table tfoot tr{padding:0;}
  .aff-table tfoot td.aff-tfoot-label{display:none;}
  .aff-table tfoot td[data-label]{display:flex;justify-content:space-between;}
  .aff-stat{padding:14px;}
  .aff-stat-value{font-size:24px;}
  .aff-field-row{grid-template-columns:1fr;gap:0;}
  .aff-search{max-width:none;}
  .aff-table thead{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;}
  .aff-table,.aff-table tbody,.aff-table tr,.aff-table td{display:block;width:100%;box-sizing:border-box;}
  .aff-table tr{padding:14px 16px;border-bottom:1px solid color-mix(in srgb,var(--border) 60%,transparent);}
  .aff-table tr:last-child{border-bottom:none;}
  .aff-table td{padding:5px 0;border-bottom:none;}
  .aff-table td[data-label]{display:flex;justify-content:space-between;gap:12px;align-items:center;}
  .aff-table td[data-label]::before{content:attr(data-label);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);}
  .aff-modal-overlay{padding:12px;}
  .aff-modal-body{padding:16px;}
  .aff-modal-head{padding:16px 16px 12px;}
  .aff-modal-foot{padding:12px 16px;flex-wrap:wrap;}
  .aff-modal-foot button{flex:1;justify-content:center;}
  .aff-add-btn{width:100%;justify-content:center;}
}
</style>
