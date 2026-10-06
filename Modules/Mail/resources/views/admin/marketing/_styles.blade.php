<style>
.aem-wrap{max-width:1280px;margin:0 auto;}
.aem-header{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:20px;}
.aem-title{margin:0;font-size:22px;font-weight:800;letter-spacing:-.025em;}
.aem-sub{margin:4px 0 0;font-size:13px;color:var(--muted);max-width:640px;}
.aem-back{display:inline-flex;align-items:center;gap:6px;font-size:12.5px;color:var(--muted);text-decoration:none;margin-bottom:8px;}
.aem-back:hover{color:var(--text);}
.aem-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center;}
.aem-btn{display:inline-flex;align-items:center;gap:7px;padding:9px 15px;border-radius:10px;border:1px solid var(--border);background:transparent;color:var(--text);font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;text-decoration:none;line-height:1.2;}
.aem-btn:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 7%,transparent);color:var(--text);transform:none;}
.aem-btn--primary{background:var(--primary);border-color:var(--primary);color:#fff;}
.aem-btn--primary:hover{background:color-mix(in srgb,var(--primary) 85%,#000);border-color:color-mix(in srgb,var(--primary) 85%,#000);color:#fff;}
.aem-btn--danger:hover{color:#dc2626;border-color:#ef4444;background:color-mix(in srgb,#ef4444 8%,transparent);}
.aem-btn--sm{padding:6px 11px;font-size:12px;border-radius:8px;}
.aem-btn:disabled{opacity:.55;cursor:not-allowed;}
.aem-msg{display:flex;align-items:center;gap:9px;padding:11px 14px;border-radius:12px;margin-bottom:16px;font-size:13px;font-weight:600;border:1px solid color-mix(in srgb,#22c55e 35%,transparent);background:color-mix(in srgb,#22c55e 10%,transparent);}
.aem-msg--err{border-color:color-mix(in srgb,#ef4444 35%,transparent);background:color-mix(in srgb,#ef4444 9%,transparent);color:#dc2626;}
.aem-card{border:1px solid var(--border);border-radius:16px;background:var(--card);}
.aem-card-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:14px 18px;border-bottom:1px solid var(--border);}
.aem-card-title{margin:0;font-size:14px;font-weight:750;display:flex;align-items:center;gap:8px;}
.aem-card-title i{color:var(--primary);}
.aem-card-body{padding:18px;}
.aem-field{display:flex;flex-direction:column;gap:6px;margin-bottom:14px;}
.aem-label{font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);}
.aem-input,.aem-select{width:100%;box-sizing:border-box;padding:10px 13px;border-radius:11px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 94%,transparent);color:var(--text);font-size:13.5px;font-family:inherit;}
.aem-input:focus,.aem-select:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 18%,transparent);}
.aem-hint{font-size:11.5px;color:var(--muted);}
.aem-badge{display:inline-flex;align-items:center;gap:4px;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:700;text-transform:capitalize;white-space:nowrap;}
.aem-badge--sent,.aem-badge--completed{background:color-mix(in srgb,#22c55e 14%,transparent);color:#16a34a;}
.aem-badge--pending,.aem-badge--sending{background:color-mix(in srgb,#f59e0b 15%,transparent);color:#b45309;}
.aem-badge--failed{background:color-mix(in srgb,#ef4444 14%,transparent);color:#dc2626;}
.aem-badge--muted{background:color-mix(in srgb,#64748b 15%,transparent);color:#64748b;}
.aem-empty{padding:44px 24px;text-align:center;}
.aem-empty-icon{width:52px;height:52px;border-radius:14px;margin:0 auto 14px;display:grid;place-items:center;font-size:22px;background:color-mix(in srgb,var(--primary) 10%,transparent);color:var(--primary);}
.aem-empty-title{margin:0 0 6px;font-size:16px;font-weight:700;}
.aem-empty-sub{margin:0 0 16px;font-size:13px;color:var(--muted);}
.aem-table{width:100%;border-collapse:collapse;}
.aem-table th{padding:11px 16px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);background:color-mix(in srgb,var(--card) 88%,var(--border));text-align:left;border-bottom:1px solid var(--border);}
.aem-table td{padding:12px 16px;font-size:13.5px;border-bottom:1px solid color-mix(in srgb,var(--border) 60%,transparent);vertical-align:middle;}
.aem-table tr:last-child td{border-bottom:none;}
.aem-main{font-weight:650;color:var(--text);}
.aem-meta{font-size:12px;color:var(--muted);margin-top:1px;}
.aem-progress{height:6px;border-radius:999px;background:color-mix(in srgb,var(--border) 70%,transparent);overflow:hidden;display:flex;min-width:90px;}
.aem-progress span{display:block;height:100%;}
.aem-progress .ok{background:#22c55e;}
.aem-progress .bad{background:#ef4444;}
.aem-tags{display:flex;gap:6px;flex-wrap:wrap;}
.aem-tag{display:inline-flex;align-items:center;gap:5px;padding:4px 9px;border-radius:8px;border:1px dashed var(--border);background:transparent;color:var(--text);font-size:11.5px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;cursor:pointer;}
.aem-tag:hover{border-color:var(--primary);color:var(--primary);background:color-mix(in srgb,var(--primary) 6%,transparent);transform:none;}

/* rich editor */
.aem-editor{border:1px solid var(--border);border-radius:12px;overflow:hidden;background:var(--card);}
.aem-toolbar{display:flex;flex-wrap:wrap;gap:2px;padding:6px;border-bottom:1px solid var(--border);background:color-mix(in srgb,var(--card) 90%,var(--border));}
.aem-toolbar button,.aem-toolbar select{background:transparent;color:var(--text);border:1px solid transparent;border-radius:7px;padding:6px 8px;font-size:12.5px;min-width:30px;cursor:pointer;font-family:inherit;}
.aem-toolbar button:hover{background:color-mix(in srgb,var(--primary) 10%,transparent);color:var(--text);transform:none;}
.aem-toolbar button.is-on{background:color-mix(in srgb,var(--primary) 16%,transparent);color:var(--primary);}
.aem-toolbar select{border-color:var(--border);}
.aem-toolbar .sep{width:1px;background:var(--border);margin:3px 4px;}
.aem-toolbar input[type=color]{width:30px;height:28px;padding:2px;border:1px solid var(--border);border-radius:7px;background:transparent;cursor:pointer;}
.aem-canvas{background:#f1f5f9;padding:20px 14px;}
.aem-surface{max-width:600px;margin:0 auto;min-height:320px;background:#fff;color:#1e293b;border:1px solid #e2e8f0;border-radius:12px;padding:28px 24px;font-family:'Segoe UI',Arial,sans-serif;font-size:14px;line-height:1.6;outline:none;box-sizing:border-box;overflow-wrap:anywhere;}
.aem-surface img{max-width:100%;height:auto;}
.aem-surface a{color:#2563eb;}
.aem-source{display:none;width:100%;box-sizing:border-box;min-height:360px;border:0;padding:16px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12.5px;background:var(--card);color:var(--text);resize:vertical;}
.aem-editor.is-source .aem-canvas{display:none;}
.aem-editor.is-source .aem-source{display:block;}

/* modal */
.aem-modal{position:fixed;inset:0;z-index:1000;display:none;align-items:center;justify-content:center;padding:16px;background:rgba(15,23,42,.55);}
.aem-modal.is-open{display:flex;}
.aem-modal-box{width:min(720px,100%);max-height:92vh;display:flex;flex-direction:column;background:var(--card);border:1px solid var(--border);border-radius:16px;overflow:hidden;}
.aem-modal-box iframe{border:0;width:100%;height:70vh;background:#f1f5f9;}
@media(max-width:640px){
    .aem-table thead{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;}
    .aem-table, .aem-table tbody, .aem-table tr, .aem-table td{display:block;width:100%;box-sizing:border-box;}
    .aem-table tr{padding:12px 16px;border-bottom:1px solid color-mix(in srgb,var(--border) 60%,transparent);}
    .aem-table td{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:4px 0;border-bottom:none;text-align:right;}
    .aem-table td[data-label]::before{content:attr(data-label);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);text-align:left;flex-shrink:0;}
}
</style>
