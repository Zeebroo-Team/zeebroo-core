<style>
.aae-list{display:flex;flex-direction:column;gap:12px;}
.aae-item{display:grid;grid-template-columns:auto 1fr auto auto;align-items:center;gap:18px;padding:18px 20px;transition:border-color .15s;}
.aae-item:hover{border-color:color-mix(in srgb,var(--primary) 35%,var(--border));}
.aae-item.is-off .aae-icon{background:color-mix(in srgb,var(--muted) 12%,transparent);color:var(--muted);}
.aae-icon{width:44px;height:44px;border-radius:12px;display:grid;place-items:center;font-size:18px;background:color-mix(in srgb,var(--primary) 12%,transparent);color:var(--primary);}
.aae-info{min-width:0;}
.aae-name-row{display:flex;align-items:center;gap:9px;flex-wrap:wrap;}
.aae-name{margin:0;font-size:15px;font-weight:700;}
.aae-desc{margin:3px 0 8px;font-size:12.5px;color:var(--muted);}
.aae-meta{display:flex;flex-wrap:wrap;gap:6px 16px;font-size:12px;color:var(--text);}
.aae-meta i{color:var(--muted);font-size:10.5px;margin-right:3px;}
.aae-subject{display:inline-block;max-width:340px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;vertical-align:bottom;color:var(--muted);}
.aae-stats{display:grid;grid-template-columns:auto auto;gap:2px 16px;text-align:center;min-width:130px;}
.aae-stats strong{display:block;font-size:17px;font-weight:750;}
.aae-stats span{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;}
.aae-stats .is-bad strong{color:#dc2626;}
.aae-last{grid-column:1/-1;font-size:11px;color:var(--muted);margin-top:4px;}
.aae-actions{display:flex;align-items:center;gap:10px;}
.aae-switch{position:relative;width:42px;height:24px;border-radius:999px;border:1px solid var(--border);background:color-mix(in srgb,var(--muted) 22%,transparent);cursor:pointer;padding:0;transition:background .15s;}
.aae-switch.is-on{background:var(--primary);border-color:var(--primary);}
.aae-switch-knob{position:absolute;top:2px;left:2px;width:18px;height:18px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.25);transition:left .15s;}
.aae-switch.is-on .aae-switch-knob{left:20px;}
.aae-switch:hover{transform:none;}
.aae-layout{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:16px;align-items:start;}
.aae-side{display:flex;flex-direction:column;gap:16px;position:sticky;top:16px;}
.aae-row{display:grid;grid-template-columns:1fr 1fr;gap:0 12px;}
.aae-check{display:flex;align-items:flex-start;gap:9px;font-size:13px;cursor:pointer;margin-bottom:10px;}
.aae-check input{margin-top:3px;accent-color:var(--primary);}
.aae-log-list{max-height:360px;overflow-y:auto;overscroll-behavior:contain;}
.aae-log{display:flex;justify-content:space-between;gap:10px;padding:9px 18px;border-bottom:1px solid color-mix(in srgb,var(--border) 60%,transparent);font-size:12.5px;}
.aae-log:last-child{border-bottom:none;}
.aae-log-email{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;min-width:0;}
@media(max-width:1000px){
    .aae-layout{grid-template-columns:1fr;}
    .aae-side{position:static;}
}
@media(max-width:760px){
    .aae-item{grid-template-columns:auto 1fr;}
    .aae-stats{grid-column:1/-1;justify-self:start;text-align:left;}
    .aae-actions{grid-column:1/-1;justify-content:flex-end;}
    .aae-subject{max-width:220px;}
}
</style>
