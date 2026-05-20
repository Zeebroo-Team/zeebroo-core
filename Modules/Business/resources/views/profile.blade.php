@extends('theme::layouts.app', ['title' => $title, 'heading' => $heading])

@section('content')
@php
    $displayLogoUrl = $business->displayLogoUrl();
    $hasCustomLogo = $business->hasCustomLogo();
    $branchCount = $business->branches->count();
    $showLocationsTab = $business->multiWarehouseBranchEnabled() || $branchCount > 0;
@endphp
<style>
/* Page-width profile (no outer card chrome) */
.business-pro{max-width:1040px;margin:0 auto;}
.business-pro-banner{
    position:relative;
    padding:clamp(12px,2vw,8px) 0 clamp(24px,3vw,36px);
    border-bottom:1px solid var(--border);
}
.business-pro-banner::before{
    content:"";position:absolute;left:-28px;right:-28px;top:-12px;height:clamp(140px,22vw,200px);z-index:-1;pointer-events:none;
    opacity:.55;
    background:
        radial-gradient(ellipse 72% 100% at 18% 0%, color-mix(in srgb,var(--primary) 18%,transparent) 0%, transparent 58%),
        radial-gradient(ellipse 56% 80% at 92% 8%, color-mix(in srgb,var(--primary) 10%,transparent) 0%, transparent 50%);
}
@media (max-width:900px){
    .business-pro-banner::before{left:-16px;right:-16px;}
}
.business-pro-banner-inner{position:relative;z-index:1;display:flex;align-items:flex-end;gap:clamp(18px,3vw,28px);flex-wrap:wrap;}
.business-pro-avatar-btn{
    cursor:pointer;border:0;padding:0;margin:0;background:transparent;line-height:0;border-radius:20px;
    flex-shrink:0;outline:none;-webkit-tap-highlight-color:transparent;
}
.business-pro-avatar-btn .business-pro-avatar{
    display:block;width:clamp(92px,18vw,120px);height:clamp(92px,18vw,120px);border-radius:20px;
    object-fit:contain;background:color-mix(in srgb,var(--card) 40%,transparent);
    border:1px solid var(--border);transition:box-shadow .22s ease,transform .22s ease,border-color .22s ease;
}
.business-pro-avatar-btn:focus-visible .business-pro-avatar,
.business-pro-avatar-btn:hover .business-pro-avatar{
    box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 55%,transparent),0 14px 32px rgba(0,0,0,.14);
    transform:scale(1.04);border-color:color-mix(in srgb,var(--primary) 45%,var(--border));
}
/* Avatar lightbox modal */
.business-pro-avatar-modal{
    position:fixed;inset:0;z-index:240;display:none;align-items:flex-start;justify-content:center;
    padding:clamp(20px,4vh,48px) max(14px,2.5vmin) clamp(28px,5vh,56px);box-sizing:border-box;
}
.business-pro-avatar-modal.is-open{display:flex;}
.business-pro-avatar-modal-backdrop{
    position:absolute;inset:0;background:rgba(2,6,23,.62);backdrop-filter:blur(4px);cursor:pointer;
}
:is(html[data-theme="light"],html[data-theme="light_blue"]) .business-pro-avatar-modal-backdrop{background:rgba(17,24,39,.42);}
.business-pro-avatar-modal-shell{
    --biz-pro-shell-pad:clamp(18px,2.2vmin,26px);
    position:relative;z-index:1;box-sizing:border-box;width:min(92vw,1320px);max-width:calc(100vw - 28px);
    height:auto;min-height:0;max-height:calc(100vh - clamp(72px,12vh,120px));
    padding:var(--biz-pro-shell-pad) var(--biz-pro-shell-pad) 0;padding-bottom:0;border-radius:14px;border:1px solid var(--border);
    background:var(--card);box-shadow:0 24px 56px rgba(0,0,0,.28);
    display:flex;flex-direction:column;align-items:stretch;justify-content:flex-start;gap:0;
    overflow:hidden;
}
.business-pro-avatar-modal-close{
    width:40px;height:40px;border-radius:12px;flex-shrink:0;
    border:1px solid var(--border);background:color-mix(in srgb,var(--card) 88%,var(--border));color:var(--text);
    cursor:pointer;display:grid;place-items:center;font-size:20px;line-height:1;transition:background .15s ease,border-color .15s ease;
}
.business-pro-avatar-modal-close:hover{background:color-mix(in srgb,var(--primary) 12%,var(--card));border-color:color-mix(in srgb,var(--primary) 35%,var(--border));}
.business-pro-logo-modal-head{
    display:flex;align-items:flex-start;justify-content:space-between;gap:12px;
    padding:0 0 10px;margin:0 0 10px;border-bottom:1px solid var(--border);
    flex-shrink:0;background:linear-gradient(180deg,color-mix(in srgb,var(--card)100%,transparent),transparent);
}
.business-pro-logo-modal-title-wrap{min-width:0;}
.business-pro-logo-modal-title{margin:0 0 4px;font-size:clamp(17px,2.4vw,20px);font-weight:800;letter-spacing:-.02em;}
.business-pro-logo-modal-sub{margin:0;font-size:13px;color:var(--muted);}
.business-pro-logo-modal-tabs{display:flex;gap:5px;flex-shrink:0;flex-wrap:wrap;margin:0 0 8px;padding:0 0 8px;border-bottom:1px solid color-mix(in srgb,var(--border)92%,transparent);}
.business-pro-logo-modal-tab{
    padding:8px 14px;font-size:13px;font-weight:700;border-radius:10px;border:1px solid var(--border);
    background:transparent;color:var(--muted);cursor:pointer;font-family:inherit;transition:.15s ease;
}
.business-pro-logo-modal-tab:hover{color:var(--text);border-color:color-mix(in srgb,var(--primary) 28%,var(--border));}
.business-pro-logo-modal-tab.is-active{color:var(--text);border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 11%,transparent);}
.business-pro-logo-modal-content{
    flex:1;min-height:0;overflow-x:hidden;overflow-y:auto;-webkit-overflow-scrolling:touch;scrollbar-gutter:stable;width:100%;
    padding-right:2px;padding-bottom:clamp(6px,1.4vmin,12px);/* footer bar handles outer shell padding */
}
.business-pro-logo-subpane{display:none;}
.business-pro-logo-subpane.is-active{display:block;}
.business-pro-logo-upload-layout{
    display:grid;
    grid-template-columns:clamp(200px,min(42%,320px),320px) 1fr;
    gap:clamp(18px,3vw,32px);align-items:start;
}
@media (max-width:600px){.business-pro-logo-upload-layout{grid-template-columns:1fr;}}
.business-pro-logo-upload-sidebar{
    width:100%;box-sizing:border-box;
    display:flex;flex-direction:column;align-items:center;text-align:center;gap:12px;padding:clamp(18px,3vw,24px);border-radius:18px;
    border:1px solid color-mix(in srgb,var(--border)92%,transparent);
    background:linear-gradient(160deg,color-mix(in srgb,var(--primary)6%,transparent),transparent);
}
.business-pro-logo-upload-sidebar-label{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.12em;color:var(--muted);margin:0;}
.business-pro-logo-upload-current-thumb{
    width:100%;max-width:180px;aspect-ratio:1;height:auto;border-radius:16px;border:1px solid var(--border);object-fit:contain;
    background:color-mix(in srgb,var(--card)62%,var(--border));display:block;
}
.business-pro-logo-upload-sidebar-hint{margin:0;font-size:12px;line-height:1.45;color:var(--muted);max-width:16em;}
.business-pro-logo-upload-remove-form{margin:0;}
.business-pro-logo-upload-remove-btn{
    padding:8px 10px;font-size:11px;font-weight:650;border-radius:10px;border:1px solid color-mix(in srgb,#ef4444 35%,var(--border));
    background:color-mix(in srgb,#ef4444 7%,transparent);color:var(--text);cursor:pointer;font-family:inherit;width:100%;
}
.business-pro-logo-upload-remove-btn:hover{background:color-mix(in srgb,#ef4444 12%,transparent);}
.business-pro-logo-upload-main{display:flex;flex-direction:column;gap:14px;min-width:0;}
.business-pro-dropzone{
    position:relative;border-radius:18px;border:2px dashed color-mix(in srgb,var(--border)88%,var(--primary));
    background:linear-gradient(145deg,color-mix(in srgb,var(--primary)5%,var(--card)),color-mix(in srgb,var(--card)97%,transparent));
    transition:border-color .22s ease,background .22s ease,box-shadow .22s ease,transform .18s ease;
    outline:none;
}
.business-pro-dropzone:hover{border-color:color-mix(in srgb,var(--primary)38%,var(--border));box-shadow:0 10px 28px rgba(0,0,0,.06);}
.business-pro-dropzone:focus-visible{box-shadow:0 0 0 3px color-mix(in srgb,var(--primary)45%,transparent);}
.business-pro-dropzone.is-dragover{
    border-style:solid;border-color:color-mix(in srgb,var(--primary)55%,var(--border));
    background:color-mix(in srgb,var(--primary)12%,var(--card));
    box-shadow:0 0 0 1px color-mix(in srgb,var(--primary)25%,transparent),0 16px 40px rgba(0,0,0,.1);
    transform:scale(1.01);
}
@media (prefers-reduced-motion:reduce){
    .business-pro-dropzone,.business-pro-dropzone:hover,.business-pro-dropzone.is-dragover{transition:none!important;transform:none!important;}
}
.business-pro-dropzone-inner{padding:clamp(22px,4vw,36px) clamp(16px,3vw,24px);text-align:center;}
.business-pro-dropzone-icon{
    width:52px;height:52px;margin:0 auto 14px;border-radius:14px;display:grid;place-items:center;
    font-size:22px;color:var(--primary);
    background:color-mix(in srgb,var(--primary)14%,transparent);border:1px solid color-mix(in srgb,var(--primary)28%,var(--border));
}
.business-pro-dropzone-title{margin:0 0 6px;font-size:16px;font-weight:800;letter-spacing:-.02em;color:var(--text);}
.business-pro-dropzone-sub{margin:0 0 16px;font-size:13px;line-height:1.5;color:var(--muted);}
.business-pro-dropzone-actions{display:flex;flex-wrap:wrap;gap:10px;justify-content:center;align-items:center;}
.business-pro-dropzone-browse{
    padding:10px 18px;font-size:13px;font-weight:700;border-radius:12px;border:1px solid color-mix(in srgb,var(--btn-bg)55%,var(--border));
    background:var(--btn-bg);color:#fff;cursor:pointer;font-family:inherit;transition:background .15s ease,transform .12s ease;
}
.business-pro-dropzone-browse:hover{background:var(--btn-hover);color:#111827;}
.business-pro-dropzone-hint-inline{font-size:12px;color:var(--muted);}
.business-pro-dropzone-filled{display:none;padding:clamp(16px,3vw,22px);text-align:center;}
.business-pro-dropzone.has-file .business-pro-dropzone-inner{display:none;}
.business-pro-dropzone.has-file .business-pro-dropzone-filled{display:block;}
.business-pro-dropzone-filled-preview{
    max-width:min(200px,55vw);max-height:160px;width:auto;height:auto;margin:0 auto 12px;border-radius:12px;
    object-fit:contain;border:1px solid var(--border);background:color-mix(in srgb,var(--card)70%,var(--border));
}
.business-pro-dropzone-filled-name{margin:0 0 4px;font-size:13px;font-weight:700;color:var(--text);word-break:break-all;}
.business-pro-dropzone-filled-meta{margin:0 0 14px;font-size:12px;color:var(--muted);}
.business-pro-dropzone-change{
    padding:8px 14px;font-size:12px;font-weight:650;border-radius:10px;border:1px solid var(--border);
    background:color-mix(in srgb,var(--card)90%,transparent);color:var(--text);cursor:pointer;font-family:inherit;margin-right:8px;
}
.business-pro-dropzone-change:hover{border-color:color-mix(in srgb,var(--primary)35%,var(--border));}
.business-pro-logo-file-err{margin:0 0 6px;font-size:12px;font-weight:600;color:#ef4444;text-align:center;}
/* Footer primary actions: one style for Save logo + Save generated logo (no negative-margin bleed — avoids clipping under overflow:hidden) */
.business-pro-logo-modal-footer-btn{
    display:inline-flex;align-items:center;justify-content:center;gap:6px;flex-shrink:0;
    min-height:40px;padding:0 18px;font-size:13px;font-weight:750;border-radius:10px;
    border:1px solid color-mix(in srgb,var(--btn-bg)52%,var(--border));
    background:var(--btn-bg);color:#fff;cursor:pointer;font-family:inherit;-webkit-appearance:none;appearance:none;
    transition:background .15s ease,opacity .15s ease,color .15s ease;line-height:1.2;box-sizing:border-box;
    text-align:center;
}
.business-pro-logo-modal-footer-btn i{display:inline-flex;align-items:center;justify-content:center;width:1.1em;font-size:14px;line-height:1;flex-shrink:0;}
.business-pro-logo-modal-footer-btn:hover:not(:disabled){background:var(--btn-hover);color:#111827;}
.business-pro-logo-modal-footer-btn:disabled{opacity:.5;cursor:not-allowed;}
/* Tailwind preflight restores `display` on [hidden]; force-hide toggled footer actions */
.business-pro-logo-modal-footer-btn[hidden]{display:none!important;}
.business-pro-logo-modal-footer{
    flex-shrink:0;display:flex;flex-direction:row;align-self:stretch;justify-content:flex-end;align-items:center;margin-top:auto;
    padding:clamp(10px,1.8vmin,14px) 0 max(clamp(10px,1.8vmin,14px),env(safe-area-inset-bottom,0px));box-sizing:border-box;
    border-top:1px solid var(--border);
    background:linear-gradient(180deg,color-mix(in srgb,var(--bg)85%,transparent),color-mix(in srgb,var(--card)94%,transparent));
}
.business-pro-logo-modal-footer-actions{
    display:flex;justify-content:flex-end;align-items:center;gap:8px;width:100%;min-width:0;box-sizing:border-box;
}
@media (max-width:520px){
    .business-pro-logo-modal-footer-actions{flex-direction:column;align-items:stretch;}
    .business-pro-logo-modal-footer-btn{width:100%;min-height:44px;}
}
.business-pro-logo-upload-footnote{margin:0;font-size:12px;color:var(--muted);line-height:1.45;}
.business-pro-logo-upload-footnote a{color:var(--primary);font-weight:650;text-decoration:none;}
.business-pro-logo-upload-footnote a:hover{text-decoration:underline;}
.business-pro-visually-hidden{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip-path:inset(50%);white-space:nowrap;border:0;}
.business-pro-logo-ai-shell{max-width:100%;}
.business-pro-logo-ai-alert{
    margin:0 0 10px;padding:8px 11px;border-radius:10px;display:none;
    border:1px solid color-mix(in srgb,#ef4444 42%,var(--border));
    background:color-mix(in srgb,#ef4444 9%,var(--card));
    color:#b91c1c;font-size:12px;font-weight:650;line-height:1.45;
}
.business-pro-logo-ai-grid{
    display:grid;grid-template-columns:1fr;gap:clamp(10px,1.6vw,14px);align-items:start;
}
@media (min-width:720px){
    .business-pro-logo-ai-grid{
        grid-template-columns:minmax(200px,40%) minmax(0,1fr);gap:clamp(12px,1.8vw,18px);
    }
    .business-pro-logo-ai-panel--preview{position:sticky;top:0;}
}
.business-pro-logo-ai-panel{
    border:1px solid color-mix(in srgb,var(--border)92%,transparent);
    border-radius:14px;padding:clamp(11px,1.6vw,15px);box-sizing:border-box;
    background:linear-gradient(165deg,color-mix(in srgb,var(--primary)5%,transparent),color-mix(in srgb,var(--card)94%,transparent));
}
.business-pro-logo-ai-panel-title{
    margin:0 0 8px;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.12em;color:var(--muted);
}
.business-pro-logo-ai-panel-title--sub{margin-top:12px;margin-bottom:6px;padding-top:10px;border-top:1px solid color-mix(in srgb,var(--border)92%,transparent);}
.business-pro-logo-ai-field label{display:block;font-size:11.5px;font-weight:650;color:var(--muted);margin-bottom:4px;line-height:1.25;}
.business-pro-logo-ai-field{margin-bottom:0;}
.business-pro-logo-ai-fields-row{display:grid;grid-template-columns:1fr;gap:10px;}
@media (min-width:600px){
    .business-pro-logo-ai-fields-row{grid-template-columns:repeat(3,minmax(0,1fr));gap:8px 10px;}
}
.business-pro-logo-ai-panel select,.business-pro-logo-ai-panel textarea{
    width:100%;min-width:0;box-sizing:border-box;font-family:inherit;
    padding:8px 10px;border-radius:10px;border:1px solid var(--border);font-size:13px;color:var(--text);
    background:color-mix(in srgb,var(--card)96%,transparent);
    min-height:40px;line-height:1.25;
}
.business-pro-logo-ai-panel textarea{min-height:76px;resize:vertical;line-height:1.45;padding-top:8px;}
.business-pro-logo-ai-panel select{cursor:pointer;}
.business-pro-logo-ai-preview-box{
    width:100%;aspect-ratio:1;max-width:min(236px,100%);margin:0 auto;
    border-radius:12px;border:1px solid color-mix(in srgb,var(--border)90%,transparent);
    background:
        linear-gradient(45deg,color-mix(in srgb,var(--border)35%,transparent) 25%,transparent 25%),
        linear-gradient(-45deg,color-mix(in srgb,var(--border)35%,transparent) 25%,transparent 25%),
        linear-gradient(45deg,transparent 75%,color-mix(in srgb,var(--border)35%,transparent) 75%),
        linear-gradient(-45deg,transparent 75%,color-mix(in srgb,var(--border)35%,transparent) 75%);
    background-size:10px 10px;background-position:0 0,0 5px,5px -5px,-5px 0;
    background-color:color-mix(in srgb,var(--card)55%,var(--border));
    display:grid;place-items:center;text-align:center;padding:clamp(8px,1.8vw,12px);box-sizing:border-box;
}
.business-pro-logo-ai-preview-box img{width:100%;height:100%;object-fit:contain;border-radius:8px;display:none;}
.business-pro-logo-ai-preview-box.is-ready img{display:block;}
.business-pro-logo-ai-preview-placeholder{margin:0;font-size:12px;line-height:1.45;color:var(--muted);max-width:14em;}
.business-pro-logo-ai-preview-box.is-busy .business-pro-logo-ai-preview-placeholder{
    display:flex;flex-direction:column;align-items:center;gap:8px;
}
.business-pro-logo-ai-preview-box.is-busy .business-pro-logo-ai-preview-placeholder::after{
    content:"";width:22px;height:22px;border:2.5px solid color-mix(in srgb,var(--primary)30%,var(--border));
    border-top-color:var(--primary);border-radius:50%;animation:business-pro-spin .75s linear infinite;
    margin:0;
}
@keyframes business-pro-spin{to{transform:rotate(360deg);}}
.business-pro-logo-ai-actions{margin-top:12px;padding-top:10px;border-top:1px solid color-mix(in srgb,var(--border)92%,transparent);}
.business-pro-logo-ai-generate-btn{
    display:inline-flex;align-items:center;justify-content:center;gap:6px;width:100%;max-width:300px;
    min-height:40px;padding:0 18px;font-size:13px;font-weight:750;border-radius:10px;
    border:1px solid color-mix(in srgb,var(--btn-bg)52%,var(--border));
    background:var(--btn-bg);color:#fff;cursor:pointer;font-family:inherit;line-height:1.2;transition:background .15s ease;
}
@media (min-width:600px){
    .business-pro-logo-ai-generate-btn{width:auto;min-width:176px;padding:0 18px;}
}
.business-pro-logo-ai-generate-btn:hover:not(:disabled){background:var(--btn-hover);color:#111827;}
.business-pro-logo-ai-generate-btn:disabled{opacity:.55;cursor:not-allowed;}
body.business-pro-avatar-modal-open{overflow:hidden;}
.business-pro-identity{flex:1;min-width:200px;}
.business-pro-title{margin:0 0 10px;font-size:clamp(22px,3.8vw,30px);font-weight:800;letter-spacing:-.035em;line-height:1.15;}
.business-pro-tags{display:flex;flex-wrap:wrap;gap:8px;}
.business-pro-chip{
    display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:650;padding:5px 10px;border-radius:999px;
    border:1px solid var(--border);background:transparent;color:var(--text);
}
.business-pro-chip i{font-size:11px;opacity:.75;}
.business-pro-pane-subhead{margin:16px 0 10px;font-size:12px;font-weight:780;text-transform:uppercase;letter-spacing:.09em;color:var(--muted);}
.business-pro-brand-split{border:0;height:1px;background:linear-gradient(90deg,var(--border),transparent);margin:16px 0 18px;}
.business-pro-brand-form{margin:0;padding:0;border:0;}
.business-pro-brand-fields{display:grid;gap:clamp(14px,2vmin,18px);max-width:min(620px,100%);}
.business-pro-brand-field label{display:block;font-size:12px;font-weight:650;color:var(--muted);margin-bottom:6px;line-height:1.3;}
.business-pro-brand-field-head{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:8px;margin-bottom:6px;}
.business-pro-brand-field-head label{margin-bottom:0!important;}
.business-pro-ai-gen-btn{font-size:12px;font-weight:650;padding:6px 12px;line-height:1.2;border-radius:9px;font-family:inherit;cursor:pointer;
    border:1px solid color-mix(in srgb,var(--border)92%,transparent);background:color-mix(in srgb,var(--primary)14%,transparent);color:var(--text);}
.business-pro-ai-gen-btn:hover:not(:disabled){border-color:color-mix(in srgb,var(--primary)45%,var(--border));background:color-mix(in srgb,var(--primary)22%,transparent);}
.business-pro-ai-gen-btn:disabled{opacity:.52;cursor:not-allowed;}
.business-pro-brand-field input[type=text],.business-pro-brand-field select,.business-pro-brand-field textarea{
    width:100%;box-sizing:border-box;font-family:inherit;font-size:14px;color:var(--text);
    border:1px solid var(--border);border-radius:12px;background:color-mix(in srgb,var(--card)94%,transparent);
}
.business-pro-brand-field select{min-height:44px;padding:9px 12px;cursor:pointer;}
.business-pro-brand-field textarea{padding:11px 12px;line-height:1.5;resize:vertical;min-height:100px;}
.business-pro-brand-field textarea.business-pro-brand-short{min-height:72px;}
.business-pro-brand-field input[type=text]{min-height:44px;padding:0 12px;}
.business-pro-brand-feature{
    padding:clamp(14px,2vmin,18px);border-radius:14px;border:1px dashed color-mix(in srgb,var(--border)86%,transparent);
    background:color-mix(in srgb,var(--card)93%,transparent);
}
.business-pro-brand-feature .business-pro-brand-field textarea{min-height:88px;}
.business-pro-brand-feature-title{margin:0 0 12px;font-size:12px;font-weight:720;color:var(--text);}
.business-pro-brand-features-list{display:grid;gap:clamp(14px,2vmin,18px);}
.business-pro-brand-feature-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin:0 0 12px;}
.business-pro-brand-feature-head .business-pro-brand-feature-title{margin:0;}
.business-pro-brand-feature-remove{font-size:12px;font-weight:650;color:var(--muted);background:transparent;border:0;padding:4px 8px;cursor:pointer;border-radius:8px;font-family:inherit;flex-shrink:0;}
.business-pro-brand-feature-remove:hover{color:#b91c1c;background:color-mix(in srgb,#ef4444 10%,transparent);}
.business-pro-brand-add-feature{display:inline-flex;align-items:center;gap:8px;margin:4px 0 0;padding:9px 14px;font-size:13px;font-weight:650;border-radius:11px;border:1px dashed var(--border);background:color-mix(in srgb,var(--card)93%,transparent);color:var(--text);cursor:pointer;font-family:inherit;}
.business-pro-brand-add-feature:hover{border-color:color-mix(in srgb,var(--primary)40%,var(--border));background:color-mix(in srgb,var(--primary)8%,transparent);}
.business-pro-brand-add-feature:disabled{opacity:.48;cursor:not-allowed;}
.business-pro-brand-submit{
    justify-self:flex-start;display:inline-flex;align-items:center;gap:8px;margin-top:4px;padding:11px 20px;font-size:14px;font-weight:750;border-radius:12px;border:1px solid color-mix(in srgb,var(--btn-bg)52%,var(--border));
    background:var(--btn-bg);color:#fff;cursor:pointer;font-family:inherit;transition:background .15s ease;
}
.business-pro-brand-submit:hover{background:var(--btn-hover);color:#111827;}
.business-pro-about-tagline{font-size:14px;line-height:1.5;font-weight:650;color:var(--text);margin:0 0 10px;}
.business-pro-about-features{display:grid;gap:14px;margin:16px 0 0;padding:0;border:0;border-top:1px solid color-mix(in srgb,var(--border)94%,transparent);padding-top:16px;}
.business-pro-about-feature{margin:0;}
.business-pro-about-feature h5{margin:0 0 4px;font-size:13px;font-weight:750;color:var(--text);}
.business-pro-about-feature p{margin:0;font-size:14px;line-height:1.55;color:var(--muted);}
.business-pro-body{display:grid;grid-template-columns:1fr auto;gap:0;min-height:360px;align-items:stretch;}
@media (max-width:820px){
    .business-pro-body{display:flex;flex-direction:column;}
    .business-pro-tabs{order:-1;border-top:none;border-bottom:1px solid var(--border);}
}
.business-pro-main{padding:clamp(22px,3vw,32px);}
.business-pro-pane{display:none;}
.business-pro-pane.is-active{display:block;animation:business-pro-fade .28s ease;}
@keyframes business-pro-fade{from{opacity:0;transform:translateY(6px);}to{opacity:1;transform:none;}}
.business-pro-pane-head{margin:0 0 18px;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);}
.business-pro-metrics{
    display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px clamp(16px,4vw,40px);margin-bottom:28px;
    padding-bottom:24px;border-bottom:1px solid var(--border);
}
@media (max-width:520px){.business-pro-metrics{grid-template-columns:1fr;padding-bottom:20px;}}
.business-pro-metric{padding:4px 0;border:0;border-radius:0;background:transparent;}
.business-pro-metric-val{margin:0 0 4px;font-size:22px;font-weight:800;letter-spacing:-.02em;font-variant-numeric:tabular-nums;}
.business-pro-metric-lab{margin:0;font-size:12px;color:var(--muted);font-weight:600;}
.business-pro-about{
    margin:0;padding:0 0 0 16px;border:0;border-left:3px solid color-mix(in srgb,var(--primary) 55%,var(--border));
    font-size:15px;line-height:1.7;color:var(--text);background:transparent;border-radius:0;
}
.business-pro-about.muted{color:var(--muted);font-style:italic;}
.business-pro-cards{display:grid;gap:12px;}
.business-pro-branch{
    padding:16px 0;font-size:14px;line-height:1.55;border:0;border-radius:0;
    border-bottom:1px solid var(--border);background:transparent;
}
.business-pro-branch:last-child{border-bottom:none;}
.business-pro-branch h3{margin:0 0 6px;font-size:14px;font-weight:700;}
.business-pro-muted{font-size:12px;color:var(--muted);margin-top:4px;display:block;}
.business-pro-contact{margin-top:10px;display:flex;flex-wrap:wrap;gap:12px;font-size:12px;color:var(--muted);}
.business-pro-formcard{
    padding:20px 0 0;margin-top:4px;border:0;border-top:1px dashed color-mix(in srgb,var(--border) 90%,var(--primary));
    background:transparent;border-radius:0;
}
.business-pro-formcard p{margin:0 0 14px;font-size:14px;color:var(--muted);line-height:1.6;}
.business-pro-uploadrow{display:flex;flex-wrap:wrap;gap:10px;align-items:center;}
.business-pro-uploadrow input[type=file]{font-size:12px;max-width:100%;color:var(--muted);}
.business-pro-dangerbtn{border:1px solid color-mix(in srgb,#ef4444 40%,var(--border));background:color-mix(in srgb,#ef4444 8%,transparent);
    color:var(--text);padding:9px 14px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;}
.business-pro-dangerbtn:hover{background:color-mix(in srgb,#ef4444 14%,transparent);}
.business-pro-settingtile{
    display:flex;align-items:center;justify-content:space-between;gap:14px;padding:14px 0;border:0;border-bottom:1px solid var(--border);
    border-radius:0;text-decoration:none;color:inherit;transition:opacity .15s ease;
}
.business-pro-settingtile:hover{opacity:.88;}
.business-pro-settingtile span{font-weight:700;font-size:14px;display:block;margin-bottom:2px;}
.business-pro-settingtile small{color:var(--muted);font-size:12px;}
/* Right tabs rail — sticky on desktop (align-self:start so the track isn’t stretched to main height) */
.business-pro-tabs{
    width:clamp(184px,22vw,220px);border-left:1px solid var(--border);
    padding:clamp(14px,2vw,22px) 0 clamp(14px,2vw,22px) clamp(14px,2vw,22px);background:transparent;
}
@media (min-width:821px){
    .business-pro-tabs{
        align-self:start;
        position:sticky;
        top:calc(env(safe-area-inset-top, 0px) + 68px);
        z-index:11;
        max-height:calc(100vh - env(safe-area-inset-top, 0px) - 80px);
        overflow-y:auto;
        overflow-x:hidden;
        overscroll-behavior:contain;
        background:var(--card);
        padding-right:clamp(8px,1.2vw,12px);
        box-sizing:border-box;
    }
}
@media (max-width:820px){
    .business-pro-tabs{width:100%;border-left:none;border-top:1px solid var(--border);
        display:flex;flex-direction:row;overflow-x:auto;gap:8px;-webkit-overflow-scrolling:touch;padding:12px 16px;
        background:transparent;
    }
}
.business-pro-tab{
    width:100%;display:flex;align-items:center;gap:10px;padding:11px 12px;border-radius:11px;margin-bottom:6px;border:1px solid transparent;background:transparent;
    color:var(--muted);font-size:13px;font-weight:600;cursor:pointer;text-align:left;font-family:inherit;transition:.18s ease;
}
@media (max-width:820px){
    .business-pro-tab{width:auto;flex-shrink:0;margin-bottom:0;white-space:nowrap;}
}
.business-pro-tab i{width:14px;text-align:center;font-size:13px;}
.business-pro-tab:hover{color:var(--text);border-color:var(--border);background:color-mix(in srgb,var(--primary) 6%,transparent);}
.business-pro-tab.is-active{
    color:var(--text);border-color:color-mix(in srgb,var(--primary) 45%,var(--border));
    background:color-mix(in srgb,var(--primary) 10%,transparent);box-shadow:none;
}
.business-pro-msg{
    padding:14px;border-radius:12px;display:flex;align-items:center;gap:10px;font-size:13px;margin-bottom:20px;line-height:1.4;font-weight:600;
}
.business-pro-msg--ok{border:1px solid color-mix(in srgb,#16a34a 45%,var(--border));background:color-mix(in srgb,#16a34a 10%,var(--card));}
.business-pro-msg--err{color:#ef4444;font-weight:600;margin-bottom:16px;}
.business-pro-gbp{
    margin:0 0 20px;padding:clamp(14px,2.2vmin,18px);border-radius:14px;border:1px solid color-mix(in srgb,var(--border)92%,transparent);
    background:linear-gradient(145deg,color-mix(in srgb,var(--primary)5%,transparent),color-mix(in srgb,var(--card)96%,transparent));
}
.business-pro-gbp-hint{margin:0 0 14px;font-size:13px;line-height:1.5;}
.business-pro-gbp-row{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:10px;}
.business-pro-gbp-select{
    flex:1 1 200px;min-width:min(280px,100%);min-height:44px;padding:9px 12px;font-family:inherit;font-size:14px;
    border:1px solid var(--border);border-radius:12px;background:color-mix(in srgb,var(--card)94%,transparent);color:var(--text);cursor:pointer;
}
.business-pro-gbp-linked{margin-top:14px;padding-top:14px;border-top:1px solid color-mix(in srgb,var(--border)85%,transparent);}
.business-pro-gbp-linked-title{margin:0 0 12px;font-size:14px;font-weight:680;line-height:1.4;color:var(--text);word-break:break-word;}
.business-pro-gbp-linked-title code{font-size:12px;font-weight:500;}
.business-pro-gbp-actions{display:flex;flex-wrap:wrap;gap:10px;align-items:center;}
.business-pro-gbp-overwrite{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text);cursor:pointer;margin:0;}
.business-pro-gbp-msg{margin:12px 0 0;font-size:13px;font-weight:600;line-height:1.45;}
.business-pro-gbp-msg.is-err{color:#b91c1c;}
.business-pro-gbp-msg.is-ok{color:#15803d;}
</style>

<div class="business-pro">
    @if(session('status'))
        <div class="business-pro-msg business-pro-msg--ok" style="margin:0 0 20px;">
            <span style="width:22px;height:22px;display:inline-flex;align-items:center;justify-content:center;border-radius:999px;background:color-mix(in srgb,#22c55e 22%,transparent);flex-shrink:0;">
                <i class="fa fa-check" style="color:#22c55e;font-size:11px;"></i>
            </span>
            {{ session('status') }}
        </div>
    @endif

    @if($errors->any())
        <div class="business-pro-msg--err" style="margin:0 0 16px;">{{ $errors->first() }}</div>
    @endif

    <header class="business-pro-banner">
        <div class="business-pro-banner-inner">
            <button
                type="button"
                class="business-pro-avatar-btn"
                id="businessProAvatarOpen"
                aria-haspopup="dialog"
                aria-expanded="false"
                aria-controls="businessProAvatarModal"
                aria-label="Manage business logo — upload or create"
                title="Manage business logo"
            >
                <img src="{{ $displayLogoUrl }}" alt="{{ $business->name }}" class="business-pro-avatar" width="120" height="120" decoding="async" fetchpriority="high">
            </button>
            <div class="business-pro-identity">
                <h1 class="business-pro-title">{{ $business->name }}</h1>
                <div class="business-pro-tags">
                    <span class="business-pro-chip"><i class="fa fa-layer-group"></i>{{ filled($business->company_category_slug) ? (\Modules\Business\Support\BrandCompanyCategoryCatalog::labelsByValue()[$business->company_category_slug] ?? $business->category) : $business->category }}</span>
                    <span class="business-pro-chip"><i class="fa fa-fingerprint"></i>ID #{{ $business->id }}</span>
                    @if($branchCount > 0)
                        <span class="business-pro-chip"><i class="fa fa-location-dot"></i>{{ $branchCount }} {{ \Illuminate\Support\Str::plural('location', $branchCount) }}</span>
                    @endif
                </div>
            </div>
        </div>
    </header>

    <div class="business-pro-body">
        <div class="business-pro-main">
            <div id="business-pro-panel-overview" class="business-pro-pane is-active" role="tabpanel" aria-labelledby="business-pro-tab-overview" aria-hidden="false">
                <p class="business-pro-pane-head">Snapshot</p>
                <div class="business-pro-metrics">
                    <div class="business-pro-metric">
                        <p class="business-pro-metric-val">{{ $business->created_at?->format('d M Y') ?? '—' }}</p>
                        <p class="business-pro-metric-lab">Created</p>
                    </div>
                    <div class="business-pro-metric">
                        <p class="business-pro-metric-val">{{ $business->updated_at?->format('d M Y') ?? '—' }}</p>
                        <p class="business-pro-metric-lab">Last updated</p>
                    </div>
                    <div class="business-pro-metric">
                        <p class="business-pro-metric-val">{{ $branchCount }}</p>
                        <p class="business-pro-metric-lab">Locations</p>
                    </div>
                </div>
                <p class="business-pro-pane-head">About</p>
                <div class="business-pro-about @if(!$business->short_description && !$business->description) muted @endif">
                    @if(filled($business->short_description))
                        <p class="business-pro-about-tagline">{{ $business->short_description }}</p>
                    @endif
                    @if(filled($business->description))
                        <div>{{ $business->description }}</div>
                    @elseif(!filled($business->short_description))
                        <span>No description yet — add your company summary on the Brand tab.</span>
                    @endif
                </div>
                @php
                    $aboutFeatures = is_array($business->brand_features) ? $business->brand_features : [];
                @endphp
                @if(!empty($aboutFeatures))
                    <div class="business-pro-about-features" role="list">
                        @foreach($aboutFeatures as $feat)
                            @continue(! is_array($feat))
                            @php
                                $fTitle = isset($feat['title']) ? (string) $feat['title'] : '';
                                $fBody = isset($feat['content']) ? (string) $feat['content'] : '';
                            @endphp
                            @continue($fTitle === '' && $fBody === '')
                            <article class="business-pro-about-feature" role="listitem">
                                @if($fTitle !== '')
                                    <h5>{{ $fTitle }}</h5>
                                @endif
                                @if($fBody !== '')
                                    <p>{{ $fBody }}</p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>

            <div id="business-pro-panel-locations" class="business-pro-pane" role="tabpanel" aria-labelledby="business-pro-tab-locations" aria-hidden="true">
                <p class="business-pro-pane-head">Locations &amp; branches</p>
                @if($showLocationsTab && $business->branches->isNotEmpty())
                    <div class="business-pro-cards">
                        @foreach($business->branches as $branch)
                            <article class="business-pro-branch">
                                <h3>{{ $branch->name }}</h3>
                                @if($branch->address)
                                    <span class="business-pro-muted">{{ $branch->address }}</span>
                                @endif
                                <div class="business-pro-contact">
                                    @if($branch->phone)<span><i class="fa fa-phone"></i> {{ $branch->phone }}</span>@endif
                                    @if($branch->email)<span><i class="fa fa-envelope"></i> {{ $branch->email }}</span>@endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="business-pro-about muted">No branch records to show. Enable multi-location mode or add branches from the dashboard when available.</div>
                @endif
            </div>

            <div id="business-pro-panel-brand" class="business-pro-pane" role="tabpanel" aria-labelledby="business-pro-tab-brand" aria-hidden="true">
                <p class="business-pro-pane-head">Brand &amp; logo</p>
                <div style="display:flex;flex-wrap:wrap;gap:10px;">
                    <button type="button" class="linkbtn" style="padding:9px 16px;font-size:13px;" id="businessProBrandOpenUpload">
                        <i class="fa fa-upload"></i> Upload logo…
                    </button>
                    <button type="button" class="linkbtn" style="padding:9px 16px;font-size:13px;background:color-mix(in srgb,var(--card)94%,transparent);color:var(--text);border:1px solid var(--border);"
                        id="businessProBrandOpenCreator">
                        <i class="fa fa-wand-magic-sparkles"></i> AI logo creator…
                    </button>
                </div>
                <hr class="business-pro-brand-split" aria-hidden="true">
                <p class="business-pro-pane-subhead">Company profile</p>
                <form class="business-pro-brand-form" method="post" action="{{ route('business.profile.brand.update') }}"
                    data-brand-copy-url="{{ route('business.profile.brand.copy.generate') }}">
                    @csrf
                    <div class="business-pro-brand-fields">
                        <div class="business-pro-brand-field">
                            <label for="businessBrandCategory">Company category</label>
                            <select id="businessBrandCategory" name="company_category_slug" required>
                                <option value="" disabled {{ $business->company_category_slug ? '' : 'selected' }}>Select category…</option>
                                @foreach ($brandCategories as $opt)
                                    <option value="{{ $opt['value'] }}" @selected((string) $business->company_category_slug === (string) $opt['value'])>{{ $opt['label'] }}</option>
                                @endforeach
                            </select>
                            @error('company_category_slug')
                                <span style="display:block;color:#ef4444;font-size:12px;margin-top:6px;font-weight:600;">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="business-pro-brand-field">
                            <label for="businessBrandAiHint">Optional: guide for AI (services, tone, locale)</label>
                            <input type="text" id="businessBrandAiHint" name="" maxlength="400" autocomplete="off" placeholder="e.g. Boutique hotel in Kandy emphasising eco-friendly stays"
                                style="width:100%;box-sizing:border-box;min-height:44px;padding:0 12px;font-family:inherit;font-size:14px;color:var(--text);border:1px solid var(--border);border-radius:12px;background:color-mix(in srgb,var(--card)94%,transparent);" value="">
                            <small class="muted" style="display:block;margin-top:6px;font-size:11px;">Used only when you click Generate below. Not saved until you submit the brand form.</small>
                        </div>
                        <div class="business-pro-brand-field">
                            <div class="business-pro-brand-field-head">
                                <label for="businessBrandShort">Short description</label>
                                <button type="button" class="linkbtn business-pro-ai-gen-btn" id="businessBrandGenShortBtn" aria-label="Generate short description with AI">
                                    <i class="fa fa-wand-magic-sparkles"></i> Generate
                                </button>
                            </div>
                            <textarea id="businessBrandShort" class="business-pro-brand-short" name="short_description" maxlength="360" rows="3" placeholder="One or two sentences that sum up what you do.">{{ old('short_description', $business->short_description) }}</textarea>
                            @error('short_description')
                                <span style="display:block;color:#ef4444;font-size:12px;margin-top:6px;font-weight:600;">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="business-pro-brand-field">
                            <div class="business-pro-brand-field-head">
                                <label for="businessBrandDescription">Full description</label>
                                <button type="button" class="linkbtn business-pro-ai-gen-btn" id="businessBrandGenDescBtn" aria-label="Generate full description with AI">
                                    <i class="fa fa-wand-magic-sparkles"></i> Generate
                                </button>
                            </div>
                            <textarea id="businessBrandDescription" name="description" maxlength="6000" rows="6" placeholder="Tell customers about your company, vision, audience, etc.">{{ old('description', $business->description) }}</textarea>
                            @error('description')
                                <span style="display:block;color:#ef4444;font-size:12px;margin-top:6px;font-weight:600;">{{ $message }}</span>
                            @enderror
                        </div>
                        <p style="margin:8px 0 4px;font-size:11px;font-weight:750;text-transform:uppercase;letter-spacing:.09em;color:var(--muted);">Highlights</p>
                        <p class="muted" style="margin:0 0 10px;font-size:12px;line-height:1.45;">Optional feature callouts shown on Overview (title + detail).</p>
                        @php
                            $__featOld = old('feature_items');
                            $brandFeatureRows = is_array($__featOld) ? $__featOld : ($brandFeatures ?? []);
                            if (! is_array($brandFeatureRows)) {
                                $brandFeatureRows = [];
                            }
                            $brandFeatureRows = array_filter($brandFeatureRows, static fn ($row) => is_array($row));
                            $brandFeatureKeys = array_keys($brandFeatureRows);
                            $brandFeatureNextIndex = $brandFeatureKeys === [] ? 0 : max(array_map('intval', $brandFeatureKeys)) + 1;
                        @endphp
                        <div id="businessBrandFeaturesList" class="business-pro-brand-features-list" data-next-index="{{ $brandFeatureNextIndex }}" data-max="12">
                            @foreach ($brandFeatureRows as $fi => $slot)
                                <div class="business-pro-brand-feature" data-brand-feature-row>
                                    <div class="business-pro-brand-feature-head">
                                        <p class="business-pro-brand-feature-title">Highlight</p>
                                        <button type="button" class="business-pro-brand-feature-remove" aria-label="Remove highlight">Remove</button>
                                    </div>
                                    <div class="business-pro-brand-field" style="margin-bottom:12px;">
                                        <label for="businessBrandFeatTitle{{ $fi }}">Title</label>
                                        <input id="businessBrandFeatTitle{{ $fi }}" type="text" name="feature_items[{{ $fi }}][title]" value="{{ old('feature_items.'.$fi.'.title', $slot['title'] ?? '') }}" maxlength="140" placeholder="e.g. Secure by design">
                                        @error('feature_items.'.$fi.'.title')
                                            <span style="display:block;color:#ef4444;font-size:12px;margin-top:6px;font-weight:600;">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div class="business-pro-brand-field" style="margin-bottom:0;">
                                        <label for="businessBrandFeatBody{{ $fi }}">Content</label>
                                        <textarea id="businessBrandFeatBody{{ $fi }}" name="feature_items[{{ $fi }}][content]" maxlength="2000" placeholder="Supporting detail">{{ old('feature_items.'.$fi.'.content', $slot['content'] ?? '') }}</textarea>
                                        @error('feature_items.'.$fi.'.content')
                                            <span style="display:block;color:#ef4444;font-size:12px;margin-top:6px;font-weight:600;">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <template id="businessBrandFeatureTemplate">
                            <div class="business-pro-brand-feature" data-brand-feature-row>
                                <div class="business-pro-brand-feature-head">
                                    <p class="business-pro-brand-feature-title">Highlight</p>
                                    <button type="button" class="business-pro-brand-feature-remove" aria-label="Remove highlight">Remove</button>
                                </div>
                                <div class="business-pro-brand-field" style="margin-bottom:12px;">
                                    <label for="businessBrandFeatTitle__IX__">Title</label>
                                    <input id="businessBrandFeatTitle__IX__" type="text" name="feature_items[__IX__][title]" value="" maxlength="140" placeholder="e.g. Secure by design">
                                </div>
                                <div class="business-pro-brand-field" style="margin-bottom:0;">
                                    <label for="businessBrandFeatBody__IX__">Content</label>
                                    <textarea id="businessBrandFeatBody__IX__" name="feature_items[__IX__][content]" maxlength="2000" placeholder="Supporting detail"></textarea>
                                </div>
                            </div>
                        </template>
                        <button type="button" class="business-pro-brand-add-feature" id="businessBrandFeatureAddBtn">
                            <i class="fa fa-plus" aria-hidden="true"></i> Add highlight
                        </button>
                        @if ($errors->has('feature_items') || $errors->has('feature_items.*'))
                            <span style="color:#ef4444;font-size:12px;font-weight:600;">Check feature fields for errors.</span>
                        @endif
                        <button type="submit" class="business-pro-brand-submit">
                            <i class="fa fa-floppy-disk" aria-hidden="true"></i><span>Save brand profile</span>
                        </button>
                    </div>
                </form>
            </div>

            <div id="business-pro-panel-settings" class="business-pro-pane" role="tabpanel" aria-labelledby="business-pro-tab-settings" aria-hidden="true">
                <p class="business-pro-pane-head">Configuration</p>
                @php
                    $gp = $googleBp ?? [];
                    $gpScopeOn = (bool) ($gp['manageScopeConfigured'] ?? false);
                    $gpOAuth = (bool) ($gp['oauthConnected'] ?? false);
                    $gpLinkedRes = trim((string) ($business->google_location_resource ?? ''));
                    $gpLinked = $gpLinkedRes !== '';
                    $gpDispTitle = trim((string) ($business->google_location_title_cache ?? ''));
                @endphp
                <p class="business-pro-pane-subhead" style="margin-top:0;">Google Business Profile</p>
                @if (! $gpScopeOn)
                    <p class="muted" style="font-size:13px;line-height:1.5;margin:-4px 0 16px;">
                        Listing link and import are disabled. Set <code>GOOGLE_BUSINESS_PROFILE_SCOPE=true</code> in <code>.env</code>,
                        enable the Google Account Management and Business Information APIs for your OAuth project, optionally set
                        <code>GOOGLE_OAUTH_PROMPT=consent</code> once, then reconnect Google.
                    </p>
                @elseif (! $gpOAuth)
                    <p class="muted" style="font-size:13px;line-height:1.5;margin:-4px 0 16px;">
                        {{ __('Connect Google in') }}
                        <a href="{{ route('app-connection.index') }}">{{ __('App connections') }}</a>{{ __('. After enabling Business Profile scope, use Reconnect to grant access.') }}
                    </p>
                @else
                    <div
                        id="googleBpRoot"
                        class="business-pro-gbp"
                        data-csrf="{{ csrf_token() }}"
                        data-url-locations="{{ route('business.profile.google.locations') }}"
                        data-url-link="{{ route('business.profile.google.link') }}"
                        data-url-unlink="{{ route('business.profile.google.unlink') }}"
                        data-url-import="{{ route('business.profile.google.import') }}"
                    >
                        <p class="business-pro-gbp-hint muted">
                            Load your verified Google listings and link one to this SociBiz business. <strong>Import description</strong> fills the short and full description fields on the <strong>Brand</strong> tab — switch to Brand to review and click <strong>Save brand profile</strong>.
                        </p>
                        <div class="business-pro-gbp-row">
                            <button type="button" class="linkbtn" id="googleBpLoad" style="padding:9px 16px;font-size:13px;">
                                <i class="fa fa-download"></i> Load listings
                            </button>
                            <select id="googleBpSelect" class="business-pro-gbp-select" disabled autocomplete="off" aria-label="Google Business Profile listing">
                                <option value="">{{ __('Choose a listing…') }}</option>
                            </select>
                            <button type="button" class="linkbtn" id="googleBpLink" style="padding:9px 16px;font-size:13px;" disabled>
                                <i class="fa fa-link"></i> Link
                            </button>
                        </div>
                        @if ($gpLinked)
                            <div class="business-pro-gbp-linked" id="googleBpLinkedBanner">
                                <p class="business-pro-gbp-linked-title">
                                    <i class="fa fa-circle-check" style="color:#16a34a;"></i>
                                    Linked:
                                    @if ($gpDispTitle !== '')
                                        <strong>{{ $gpDispTitle }}</strong>
                                    @endif
                                    <code style="display:block;margin-top:6px;word-break:break-all;">{{ $gpLinkedRes }}</code>
                                </p>
                                <div class="business-pro-gbp-actions">
                                    <label class="business-pro-gbp-overwrite">
                                        <input type="checkbox" id="googleBpOverwriteName" value="1">
                                        <span>Overwrite SociBiz business name</span>
                                    </label>
                                    <button type="button" class="linkbtn" id="googleBpImport" style="padding:9px 16px;font-size:13px;">
                                        <i class="fa fa-file-import"></i> Import description
                                    </button>
                                    <button type="button" class="linkbtn" id="googleBpUnlink"
                                        style="padding:9px 16px;font-size:13px;background:color-mix(in srgb,var(--card)94%,transparent);color:var(--text);border:1px solid color-mix(in srgb,#ef4444 32%,var(--border));">
                                        <i class="fa fa-link-slash"></i> Unlink
                                    </button>
                                </div>
                            </div>
                        @endif
                        <div class="business-pro-gbp-msg" id="googleBpMsg" aria-live="polite"></div>
                    </div>
                @endif
                <hr class="business-pro-brand-split" style="margin-top:8px;" aria-hidden="true">
                <p class="business-pro-pane-subhead">More settings</p>
                <a href="{{ route('settings.business') }}" class="business-pro-settingtile">
                    <div>
                        <span>Business settings</span>
                        <small>Preferences, integrations, and advanced options</small>
                    </div>
                    <i class="fa fa-chevron-right" style="color:var(--muted);"></i>
                </a>
            </div>
        </div>

        <nav class="business-pro-tabs" role="tablist" aria-label="Profile sections">
            <button type="button" class="business-pro-tab is-active" id="business-pro-tab-overview" role="tab" aria-selected="true" aria-controls="business-pro-panel-overview" data-business-pro-tab="overview">
                <i class="fa fa-chart-pie"></i>Overview
            </button>
            <button type="button" class="business-pro-tab" id="business-pro-tab-locations" role="tab" aria-selected="false" aria-controls="business-pro-panel-locations" data-business-pro-tab="locations">
                <i class="fa fa-location-dot"></i>Locations
            </button>
            <button type="button" class="business-pro-tab" id="business-pro-tab-brand" role="tab" aria-selected="false" aria-controls="business-pro-panel-brand" data-business-pro-tab="brand">
                <i class="fa fa-palette"></i>Brand
            </button>
            <button type="button" class="business-pro-tab" id="business-pro-tab-settings" role="tab" aria-selected="false" aria-controls="business-pro-panel-settings" data-business-pro-tab="settings">
                <i class="fa fa-sliders"></i>Settings
            </button>
        </nav>
    </div>
</div>

<div id="businessProAvatarModal" class="business-pro-avatar-modal" role="dialog" aria-modal="true" aria-labelledby="businessProAvatarModalTitle" aria-hidden="true">
    <div class="business-pro-avatar-modal-backdrop" tabindex="-1" data-business-pro-avatar-dismiss role="presentation"></div>
    <div class="business-pro-avatar-modal-shell">
        <div class="business-pro-logo-modal-head">
            <div class="business-pro-logo-modal-title-wrap">
                <p class="business-pro-logo-modal-title" id="businessProAvatarModalTitle">{{ $business->name }}</p>
                <p class="business-pro-logo-modal-sub">Logo editor — upload or build a badge</p>
            </div>
            <button type="button" class="business-pro-avatar-modal-close" data-business-pro-avatar-dismiss aria-label="Close">&times;</button>
        </div>
        <div class="business-pro-logo-modal-tabs" role="tablist" aria-label="Logo tools">
            <button type="button" class="business-pro-logo-modal-tab is-active" id="businessProLogoTabUpload" role="tab" aria-selected="true" data-logo-modal-tab="upload">
                <i class="fa fa-upload"></i> Upload
            </button>
            <button type="button" class="business-pro-logo-modal-tab" id="businessProLogoTabCreator" role="tab" aria-selected="false" data-logo-modal-tab="creator">
                <i class="fa fa-wand-magic-sparkles"></i> Creator
            </button>
        </div>
        <div class="business-pro-logo-modal-content">
            <div id="businessProLogoPaneUpload" class="business-pro-logo-subpane is-active" role="tabpanel" aria-labelledby="businessProLogoTabUpload" aria-hidden="false">
                @if ($errors->has('logo'))
                    <div style="color:#ef4444;font-size:13px;margin-bottom:12px;font-weight:600;">{{ $errors->first('logo') }}</div>
                @endif
                <div class="business-pro-logo-upload-layout">
                    <aside class="business-pro-logo-upload-sidebar" aria-label="Current logo">
                        <p class="business-pro-logo-upload-sidebar-label">Now showing</p>
                        <img src="{{ $displayLogoUrl }}" alt="" id="businessProLogoCurrentThumb" class="business-pro-logo-upload-current-thumb" decoding="async" width="180" height="180">
                        <p class="business-pro-logo-upload-sidebar-hint">Live across your workspace</p>
                        @if($hasCustomLogo)
                            <form method="post" action="{{ route('business.profile.logo.destroy') }}" class="business-pro-logo-upload-remove-form" onsubmit="return confirm('Remove the current logo and fall back to the default placeholder until you upload again?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="business-pro-logo-upload-remove-btn">Remove upload</button>
                            </form>
                        @endif
                    </aside>
                    <div class="business-pro-logo-upload-main">
                        <form method="post" action="{{ route('business.profile.logo.store') }}" enctype="multipart/form-data" id="businessProLogoUploadForm">
                            @csrf
                            <label for="businessProLogoFileInput" class="business-pro-visually-hidden">Logo image file</label>
                            <input id="businessProLogoFileInput" class="business-pro-visually-hidden" type="file" name="logo" accept="image/jpeg,image/png,image/gif,image/webp" tabindex="-1">
                            <div
                                class="business-pro-dropzone"
                                id="businessProLogoDropzone"
                                tabindex="0"
                                role="region"
                                aria-label="Drop zone for logo image"
                            >
                                <div class="business-pro-dropzone-inner">
                                    <div class="business-pro-dropzone-icon" aria-hidden="true"><i class="fa fa-cloud-arrow-up"></i></div>
                                    <p class="business-pro-dropzone-title">Drop your logo here</p>
                                    <p class="business-pro-dropzone-sub">High-resolution square or wide logos work best. We accept PNG, JPG, GIF, and WebP up to 2&nbsp;MB.</p>
                                    <div class="business-pro-dropzone-actions">
                                        <button type="button" class="business-pro-dropzone-browse" id="businessProLogoBrowseBtn">
                                            <i class="fa fa-folder-open"></i> Browse files
                                        </button>
                                        <span class="business-pro-dropzone-hint-inline">or drag an image onto this area</span>
                                    </div>
                                </div>
                                <div class="business-pro-dropzone-filled" id="businessProLogoDropzoneFilled" aria-live="polite">
                                    <img src="" alt="" class="business-pro-dropzone-filled-preview" id="businessProLogoPendingPreview" width="200" height="160">
                                    <p class="business-pro-dropzone-filled-name" id="businessProLogoPendingName"></p>
                                    <p class="business-pro-dropzone-filled-meta" id="businessProLogoPendingMeta"></p>
                                    <button type="button" class="business-pro-dropzone-change" id="businessProLogoChangeFile">Choose different file</button>
                                </div>
                            </div>
                            <p class="business-pro-logo-file-err" id="businessProLogoFileErr" hidden></p>
                        </form>
                        <p class="business-pro-logo-upload-footnote">Need more options? <a href="{{ route('settings.business') }}">Open business settings</a></p>
                    </div>
                </div>
            </div>
            <div id="businessProLogoPaneCreator" class="business-pro-logo-subpane" role="tabpanel" aria-labelledby="businessProLogoTabCreator" aria-hidden="true">
                <div class="business-pro-logo-ai-shell">
                    <p id="businessProAiLogoErr" class="business-pro-logo-ai-alert" role="alert"></p>
                    <div class="business-pro-logo-ai-grid">
                        <section class="business-pro-logo-ai-panel business-pro-logo-ai-panel--preview" aria-labelledby="businessProAiPreviewHeading">
                            <h4 class="business-pro-logo-ai-panel-title" id="businessProAiPreviewHeading">Preview</h4>
                            <div class="business-pro-logo-ai-preview-box" id="businessProAiLogoPreviewBox" aria-live="polite">
                                <p class="business-pro-logo-ai-preview-placeholder" id="businessProAiLogoPreviewPlaceholder">Your logo will show here when ready.</p>
                                <img src="" alt="" id="businessProAiLogoPreviewImg" decoding="async" width="236" height="236">
                            </div>
                        </section>
                        <section class="business-pro-logo-ai-panel business-pro-logo-ai-panel--form" aria-labelledby="businessProAiFormHeading">
                            <h4 class="business-pro-logo-ai-panel-title" id="businessProAiFormHeading">Brand &amp; style</h4>
                            <div class="business-pro-logo-ai-fields-row">
                                <div class="business-pro-logo-ai-field">
                                    <label for="businessProAiCategory">Company category</label>
                                    <select id="businessProAiCategory" required>
                                        <option value="" disabled selected>Choose…</option>
                                        @foreach ($logoAiCategories as $row)
                                            <option value="{{ $row['value'] }}">{{ $row['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="business-pro-logo-ai-field">
                                    <label for="businessProAiStyle">Logo style</label>
                                    <select id="businessProAiStyle" required>
                                        <option value="" disabled selected>Choose…</option>
                                        @foreach ($logoAiStyles as $row)
                                            <option value="{{ $row['value'] }}">{{ $row['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="business-pro-logo-ai-field">
                                    <label for="businessProAiBackground">Background</label>
                                    <select id="businessProAiBackground" required>
                                        <option value="" disabled selected>Choose…</option>
                                        @foreach ($logoAiBackgrounds as $row)
                                            <option value="{{ $row['value'] }}">{{ $row['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <h4 class="business-pro-logo-ai-panel-title business-pro-logo-ai-panel-title--sub">Custom prompt</h4>
                            <div class="business-pro-logo-ai-field">
                                <label for="businessProAiCustomPrompt">Extra direction <span style="font-weight:500;color:var(--muted);">(optional)</span></label>
                                <textarea id="businessProAiCustomPrompt" maxlength="2000" rows="3" placeholder="e.g. Mountain motif, green palette, no text in the mark…"></textarea>
                            </div>
                            <div class="business-pro-logo-ai-actions">
                                <button type="button" class="business-pro-logo-ai-generate-btn" id="businessProAiLogoGenerateBtn">
                                    <i class="fa fa-wand-magic-sparkles" aria-hidden="true"></i><span>Generate logo</span>
                                </button>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </div>
        <footer class="business-pro-logo-modal-footer" role="contentinfo">
            <div class="business-pro-logo-modal-footer-actions">
                <button type="submit" form="businessProLogoUploadForm" class="business-pro-logo-modal-footer-btn" id="businessProLogoSubmitBtn" disabled>
                    <i class="fa fa-circle-check" aria-hidden="true"></i><span>Save logo</span>
                </button>
                <button type="button" class="business-pro-logo-modal-footer-btn" id="businessProCreatorSaveBtn" hidden disabled>
                    <i class="fa fa-circle-check" aria-hidden="true"></i><span>Save generated logo</span>
                </button>
            </div>
        </footer>
        <form id="businessProAiLogoApplyForm" method="post" action="{{ route('business.profile.logo.generation.apply') }}" class="business-pro-visually-hidden" aria-hidden="true">
            @csrf
            <input type="hidden" name="generation_uuid" id="businessProAiLogoGenerationUuidField" value="">
        </form>
    </div>
</div>

<script>
(function () {
    var tabs = document.querySelectorAll('[data-business-pro-tab]');
    var panes = {
        overview: document.getElementById('business-pro-panel-overview'),
        locations: document.getElementById('business-pro-panel-locations'),
        brand: document.getElementById('business-pro-panel-brand'),
        settings: document.getElementById('business-pro-panel-settings')
    };
    function show(key) {
        Object.keys(panes).forEach(function (k) {
            var p = panes[k];
            if (!p) return;
            var on = k === key;
            p.classList.toggle('is-active', on);
            p.setAttribute('aria-hidden', on ? 'false' : 'true');
        });
        tabs.forEach(function (btn) {
            var on = btn.getAttribute('data-business-pro-tab') === key;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        try { localStorage.setItem('business_pro_tab', key); } catch (e) {}
    }
    tabs.forEach(function (btn) {
        btn.addEventListener('click', function () {
            show(btn.getAttribute('data-business-pro-tab'));
        });
    });
    var saved = null;
    try { saved = localStorage.getItem('business_pro_tab'); } catch (e) {}
    if (saved && panes[saved]) show(saved);
})();

(function () {
    var modal = document.getElementById('businessProAvatarModal');
    var openBtn = document.getElementById('businessProAvatarOpen');
    var btnBrandUpload = document.getElementById('businessProBrandOpenUpload');
    var btnBrandCreator = document.getElementById('businessProBrandOpenCreator');
    var closeEls = modal ? modal.querySelectorAll('[data-business-pro-avatar-dismiss]') : [];
    var tabBtns = modal ? modal.querySelectorAll('[data-logo-modal-tab]') : [];
    var panes = {
        upload: document.getElementById('businessProLogoPaneUpload'),
        creator: document.getElementById('businessProLogoPaneCreator'),
    };

    if (!modal || !openBtn) return;

    var lastFocus = null;
    var pendingObjectUrl = null;

    var fileInputLogo = document.getElementById('businessProLogoFileInput');
    var dropzoneLogo = document.getElementById('businessProLogoDropzone');
    var browseLogoBtn = document.getElementById('businessProLogoBrowseBtn');
    var changeLogoBtn = document.getElementById('businessProLogoChangeFile');
    var submitLogoBtn = document.getElementById('businessProLogoSubmitBtn');
    var logoFileErrEl = document.getElementById('businessProLogoFileErr');
    var logoUploadForm = document.getElementById('businessProLogoUploadForm');
    var pendingLogoPreviewImg = document.getElementById('businessProLogoPendingPreview');
    var pendingLogoNameEl = document.getElementById('businessProLogoPendingName');
    var pendingLogoMetaEl = document.getElementById('businessProLogoPendingMeta');

    function formatFileSize(bytes) {
        if (typeof bytes !== 'number' || bytes < 0) return '';
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(bytes < 10240 ? 1 : 0) + ' KB';
        return (bytes / 1048576).toFixed(2) + ' MB';
    }

    function showLogoDropzoneErr(msg) {
        if (!logoFileErrEl) return;
        logoFileErrEl.textContent = msg;
        logoFileErrEl.hidden = false;
    }

    function clearLogoDropzoneErr() {
        if (!logoFileErrEl) return;
        logoFileErrEl.textContent = '';
        logoFileErrEl.hidden = true;
    }

    function resetLogoDropzonePending() {
        if (pendingObjectUrl) {
            try { URL.revokeObjectURL(pendingObjectUrl); } catch (eR) {}
            pendingObjectUrl = null;
        }
        if (fileInputLogo) {
            fileInputLogo.value = '';
        }
        if (dropzoneLogo) {
            dropzoneLogo.classList.remove('has-file', 'is-dragover');
        }
        if (pendingLogoPreviewImg) {
            pendingLogoPreviewImg.removeAttribute('src');
        }
        if (submitLogoBtn) {
            submitLogoBtn.disabled = true;
        }
        clearLogoDropzoneErr();
    }

    function logoFileLooksLikeImage(file) {
        if (!file) return false;
        var mime = (file.type || '').toLowerCase();
        var name = (file.name || '').toLowerCase();
        if (mime === 'image/jpeg' || mime === 'image/png' || mime === 'image/gif' || mime === 'image/webp') {
            return true;
        }
        return /\.(jpe?g|png|gif|webp)$/.test(name);
    }

    function assignDroppedLogo(file) {
        if (!fileInputLogo || !dropzoneLogo || !file) return false;
        clearLogoDropzoneErr();

        if (!logoFileLooksLikeImage(file)) {
            showLogoDropzoneErr('Use a PNG, JPG, GIF, or WebP image.');
            return false;
        }

        var maxBytes = 2097152;
        if (file.size > maxBytes) {
            showLogoDropzoneErr('This file is larger than 2 MB. Compress it or choose a smaller image.');
            return false;
        }

        try {
            var dt = new DataTransfer();
            dt.items.add(file);
            fileInputLogo.files = dt.files;
        } catch (err) {
            showLogoDropzoneErr('Drag and drop is not supported in this browser. Use “Browse files” instead.');
            return false;
        }

        if (pendingObjectUrl) {
            try { URL.revokeObjectURL(pendingObjectUrl); } catch (e2) {}
        }
        pendingObjectUrl = URL.createObjectURL(file);
        if (pendingLogoPreviewImg) {
            pendingLogoPreviewImg.src = pendingObjectUrl;
            pendingLogoPreviewImg.alt = file.name ? 'Pending upload: ' + file.name : 'Pending logo preview';
        }
        if (pendingLogoNameEl) pendingLogoNameEl.textContent = file.name || 'Untitled image';
        var mimeTail = ((file.type || '').replace(/^image\//i, '') || '').toUpperCase();
        if (pendingLogoMetaEl) pendingLogoMetaEl.textContent = formatFileSize(file.size) + ' · ' + (mimeTail || 'IMAGE');

        dropzoneLogo.classList.add('has-file');
        if (submitLogoBtn) submitLogoBtn.disabled = false;
        return true;
    }

    function bindLogoUploader() {
        if (!dropzoneLogo || !fileInputLogo) return;

        dropzoneLogo.addEventListener('click', function (ev) {
            if (dropzoneLogo.classList.contains('has-file')) return;
            if (ev.target.closest('.business-pro-dropzone-browse')) return;
            fileInputLogo.click();
        });

        dropzoneLogo.addEventListener('keydown', function (ev) {
            if (dropzoneLogo.classList.contains('has-file')) return;
            if (ev.key === 'Enter' || ev.key === ' ') {
                ev.preventDefault();
                fileInputLogo.click();
            }
        });

        if (browseLogoBtn) {
            browseLogoBtn.addEventListener('click', function (ev) {
                ev.preventDefault();
                ev.stopPropagation();
                fileInputLogo.click();
            });
        }

        if (changeLogoBtn) {
            changeLogoBtn.addEventListener('click', function (ev) {
                ev.preventDefault();
                ev.stopPropagation();
                resetLogoDropzonePending();
                fileInputLogo.click();
            });
        }

        fileInputLogo.addEventListener('change', function () {
            var f = fileInputLogo.files && fileInputLogo.files[0];
            if (f) assignDroppedLogo(f);
        });

        ['dragenter', 'dragover'].forEach(function (evName) {
            dropzoneLogo.addEventListener(evName, function (ev) {
                ev.preventDefault();
                ev.stopPropagation();
                dropzoneLogo.classList.add('is-dragover');
                try { ev.dataTransfer.dropEffect = 'copy'; } catch (eD) {}
            });
        });

        dropzoneLogo.addEventListener('dragleave', function (ev) {
            ev.preventDefault();
            if (!dropzoneLogo.contains(ev.relatedTarget)) dropzoneLogo.classList.remove('is-dragover');
        });

        dropzoneLogo.addEventListener('drop', function (ev) {
            ev.preventDefault();
            ev.stopPropagation();
            dropzoneLogo.classList.remove('is-dragover');
            var list = ev.dataTransfer && ev.dataTransfer.files;
            if (list && list.length) assignDroppedLogo(list[0]);
        });

        if (logoUploadForm) {
            logoUploadForm.addEventListener('submit', function (ev) {
                if (!fileInputLogo.files || !fileInputLogo.files.length) {
                    ev.preventDefault();
                    showLogoDropzoneErr('Select an image file before saving.');
                }
            });
        }
    }

    bindLogoUploader();

    var aiGenerateUrl = @json(route('business.profile.logo.generate'));
    var aiPollBase = @json(url('/business/profile/logo/generation'));

    var aiPollTimer = null;
    var aiActiveUuid = null;
    var aiPollAttempts = 0;
    var aiCat = document.getElementById('businessProAiCategory');
    var aiStyle = document.getElementById('businessProAiStyle');
    var aiBg = document.getElementById('businessProAiBackground');
    var aiPrompt = document.getElementById('businessProAiCustomPrompt');
    var aiErr = document.getElementById('businessProAiLogoErr');
    var aiGenBtn = document.getElementById('businessProAiLogoGenerateBtn');
    var aiPreviewBox = document.getElementById('businessProAiLogoPreviewBox');
    var aiPreviewPh = document.getElementById('businessProAiLogoPreviewPlaceholder');
    var aiPreviewImg = document.getElementById('businessProAiLogoPreviewImg');
    var saveCreatorBtnAi = document.getElementById('businessProCreatorSaveBtn');
    var aiApplyForm = document.getElementById('businessProAiLogoApplyForm');
    var aiUuidField = document.getElementById('businessProAiLogoGenerationUuidField');

    function aiCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function showAiLogoErr(msg) {
        if (!aiErr) return;
        aiErr.textContent = msg || '';
        aiErr.style.display = msg ? 'block' : 'none';
    }

    function stopAiPolling() {
        if (aiPollTimer) clearInterval(aiPollTimer);
        aiPollTimer = null;
        aiPollAttempts = 0;
    }

    function resetAiLogoUi() {
        stopAiPolling();
        aiActiveUuid = null;
        if (aiPreviewBox) aiPreviewBox.classList.remove('is-ready', 'is-busy');
        if (aiPreviewImg) {
            aiPreviewImg.removeAttribute('src');
            aiPreviewImg.alt = '';
        }
        if (aiPreviewPh) aiPreviewPh.textContent = 'Your logo will show here when ready.';
        if (aiGenBtn) aiGenBtn.disabled = false;
        if (saveCreatorBtnAi) saveCreatorBtnAi.disabled = true;
        if (aiUuidField) aiUuidField.value = '';
        showAiLogoErr('');
    }

    function setCreatorAiSaveReady(on) {
        if (saveCreatorBtnAi) saveCreatorBtnAi.disabled = !on;
    }

    async function pollAiLogoOnce() {
        if (!aiActiveUuid) return;
        aiPollAttempts += 1;
        if (aiPollAttempts > 90) {
            stopAiPolling();
            if (aiPreviewBox) aiPreviewBox.classList.remove('is-busy');
            if (aiGenBtn) aiGenBtn.disabled = false;
            if (aiPreviewPh) aiPreviewPh.textContent = 'Your logo will show here when ready.';
            showAiLogoErr('Generation timed out — try again in a moment.');
            return;
        }
        try {
            var res = await fetch(aiPollBase + '/' + encodeURIComponent(aiActiveUuid), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            var data = await res.json().catch(function () {
                return {};
            });
            if (!res.ok) return;
            if (data.status === 'completed' && data.preview_url) {
                stopAiPolling();
                if (aiPreviewBox) {
                    aiPreviewBox.classList.remove('is-busy');
                    aiPreviewBox.classList.add('is-ready');
                }
                if (aiPreviewImg) {
                    aiPreviewImg.src = data.preview_url;
                    aiPreviewImg.alt = 'Generated logo preview';
                }
                if (aiPreviewPh) aiPreviewPh.textContent = '';
                if (aiGenBtn) aiGenBtn.disabled = false;
                setCreatorAiSaveReady(true);
                return;
            }
            if (data.status === 'failed') {
                stopAiPolling();
                if (aiPreviewBox) aiPreviewBox.classList.remove('is-busy');
                if (aiGenBtn) aiGenBtn.disabled = false;
                if (aiPreviewPh) aiPreviewPh.textContent = 'Your logo will show here when ready.';
                showAiLogoErr(data.error || 'Generation failed.');
            }
        } catch (ePoll) {}
    }

    function startAiLogoPolling(uuid) {
        aiActiveUuid = uuid;
        stopAiPolling();
        aiPollTimer = setInterval(pollAiLogoOnce, 2000);
        pollAiLogoOnce();
    }

    if (aiGenBtn && aiCat && aiStyle && aiBg) {
        aiGenBtn.addEventListener('click', async function () {
            showAiLogoErr('');
            setCreatorAiSaveReady(false);
            if (!aiCat.value || !aiStyle.value || !aiBg.value) {
                showAiLogoErr('Choose company category, logo style, and background.');
                return;
            }
            aiGenBtn.disabled = true;
            if (aiPreviewBox) {
                aiPreviewBox.classList.add('is-busy');
                aiPreviewBox.classList.remove('is-ready');
            }
            if (aiPreviewImg) aiPreviewImg.removeAttribute('src');
            if (aiPreviewPh) aiPreviewPh.textContent = 'Generating…';
            stopAiPolling();
            try {
                var payload = {
                    company_category: aiCat.value,
                    logo_style: aiStyle.value,
                    background_theme: aiBg.value,
                    custom_prompt: aiPrompt ? aiPrompt.value.trim() || null : null,
                };
                var res = await fetch(aiGenerateUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': aiCsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload),
                });
                var data = await res.json().catch(function () {
                    return {};
                });
                if (!res.ok) {
                    if (aiPreviewBox) aiPreviewBox.classList.remove('is-busy');
                    if (aiPreviewPh) aiPreviewPh.textContent = 'Your logo will show here when ready.';
                    var flattened =
                        data && data.errors && typeof data.errors === 'object'
                            ? Object.values(data.errors).flat().find(Boolean)
                            : null;
                    showAiLogoErr(
                        flattened ||
                            (typeof data.message === 'string' ? data.message : null) ||
                            'Could not queue generation.',
                    );
                    aiGenBtn.disabled = false;
                    return;
                }
                if (!data.uuid) {
                    if (aiPreviewBox) aiPreviewBox.classList.remove('is-busy');
                    if (aiPreviewPh) aiPreviewPh.textContent = 'Your logo will show here when ready.';
                    showAiLogoErr('Unexpected response from server.');
                    aiGenBtn.disabled = false;
                    return;
                }
                if (aiUuidField) aiUuidField.value = data.uuid;
                startAiLogoPolling(data.uuid);
            } catch (eNet) {
                if (aiPreviewBox) aiPreviewBox.classList.remove('is-busy');
                if (aiPreviewPh) aiPreviewPh.textContent = 'Your logo will show here when ready.';
                showAiLogoErr('Network error.');
                aiGenBtn.disabled = false;
            }
        });
    }

    if (saveCreatorBtnAi && aiApplyForm && aiUuidField) {
        saveCreatorBtnAi.addEventListener('click', function () {
            if (!aiUuidField.value) {
                showAiLogoErr('Generate a logo first.');
                return;
            }
            if (saveCreatorBtnAi.disabled) return;
            aiApplyForm.submit();
        });
    }

    function switchLogoModalTab(key) {
        var k = key === 'creator' ? 'creator' : 'upload';
        Object.keys(panes).forEach(function (id) {
            var p = panes[id];
            if (!p) return;
            var on = id === k;
            p.classList.toggle('is-active', on);
            p.setAttribute('aria-hidden', on ? 'false' : 'true');
        });
        tabBtns.forEach(function (btn) {
            var on = btn.getAttribute('data-logo-modal-tab') === k;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        var uploadSaveBtnEl = document.getElementById('businessProLogoSubmitBtn');
        var creatorSaveBtnEl = document.getElementById('businessProCreatorSaveBtn');
        if (uploadSaveBtnEl) uploadSaveBtnEl.hidden = k !== 'upload';
        if (creatorSaveBtnEl) creatorSaveBtnEl.hidden = k !== 'creator';
    }

    function setOpen(on, tabKey) {
        modal.classList.toggle('is-open', on);
        modal.setAttribute('aria-hidden', on ? 'false' : 'true');
        openBtn.setAttribute('aria-expanded', on ? 'true' : 'false');
        document.body.classList.toggle('business-pro-avatar-modal-open', on);
        if (on) {
            switchLogoModalTab(tabKey || 'upload');
            lastFocus = document.activeElement;
            var closeBtn = modal.querySelector('.business-pro-avatar-modal-close');
            if (closeBtn) closeBtn.focus();
        } else {
            resetLogoDropzonePending();
            resetAiLogoUi();
            if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
            lastFocus = null;
        }
    }

    tabBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            switchLogoModalTab(btn.getAttribute('data-logo-modal-tab'));
        });
    });

    openBtn.addEventListener('click', function () {
        setOpen(true, 'upload');
    });
    if (btnBrandUpload) btnBrandUpload.addEventListener('click', function () { setOpen(true, 'upload'); });
    if (btnBrandCreator) btnBrandCreator.addEventListener('click', function () { setOpen(true, 'creator'); });

    window.zeebrooOpenBusinessLogoModal = function (tab) {
        setOpen(true, tab || 'upload');
    };

    closeEls.forEach(function (el) {
        el.addEventListener('click', function () {
            setOpen(false);
        });
    });

    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape' && modal.classList.contains('is-open')) {
            ev.preventDefault();
            setOpen(false);
        }
    });
})();
</script>
<script>
(function () {
    var list = document.getElementById('businessBrandFeaturesList');
    var tpl = document.getElementById('businessBrandFeatureTemplate');
    var addBtn = document.getElementById('businessBrandFeatureAddBtn');
    if (!list || !tpl || !addBtn) return;

    var maxItems = parseInt(list.getAttribute('data-max'), 10) || 12;

    function featCount() {
        return list.querySelectorAll('[data-brand-feature-row]').length;
    }

    function syncAddState() {
        addBtn.disabled = featCount() >= maxItems;
    }

    function readNextIndex() {
        var ix = parseInt(list.getAttribute('data-next-index'), 10);
        if (!isFinite(ix) || ix < 0) {
            ix = 0;
        }
        return ix;
    }

    function bumpNextIndex(ix) {
        list.setAttribute('data-next-index', String(ix + 1));
    }

    addBtn.addEventListener('click', function () {
        if (featCount() >= maxItems) return;
        var ix = readNextIndex();
        var html = tpl.innerHTML.replace(/__IX__/g, String(ix));
        list.insertAdjacentHTML('beforeend', html);
        bumpNextIndex(ix);
        syncAddState();
    });

    list.addEventListener('click', function (ev) {
        var rm = ev.target.closest('.business-pro-brand-feature-remove');
        if (!rm || !list.contains(rm)) return;
        ev.preventDefault();
        var card = rm.closest('[data-brand-feature-row]');
        if (card) card.remove();
        syncAddState();
    });

    syncAddState();
})();
</script>
<script>
(function () {
    var brandForm = document.querySelector('.business-pro-brand-form');
    if (!brandForm || !brandForm.getAttribute('data-brand-copy-url')) return;

    var url = brandForm.getAttribute('data-brand-copy-url');
    var csrfEl = brandForm.querySelector('input[name="_token"]');
    var token = csrfEl ? csrfEl.value : '';
    var catEl = document.getElementById('businessBrandCategory');
    var hintEl = document.getElementById('businessBrandAiHint');
    var shortTa = document.getElementById('businessBrandShort');
    var descTa = document.getElementById('businessBrandDescription');
    var btnShort = document.getElementById('businessBrandGenShortBtn');
    var btnDesc = document.getElementById('businessBrandGenDescBtn');

    var HTML_SHORT_BTN = btnShort ? btnShort.innerHTML : '';
    var HTML_DESC_BTN = btnDesc ? btnDesc.innerHTML : '';

    function setSpinner(kind) {
        var spin = '<i class="fa fa-spinner fa-spin"></i> Generate…';
        if ((kind === 'short' || kind === 'both') && btnShort) btnShort.innerHTML = spin;
        if ((kind === 'full' || kind === 'both') && btnDesc) btnDesc.innerHTML = spin;
    }

    function restoreButtons() {
        if (btnShort) {
            btnShort.disabled = false;
            btnShort.innerHTML = HTML_SHORT_BTN;
        }
        if (btnDesc) {
            btnDesc.disabled = false;
            btnDesc.innerHTML = HTML_DESC_BTN;
        }
    }

    function runGenerate(kind) {
        if (!shortTa || !descTa || !catEl || !token) return;
        var slug = catEl.value;
        if (!slug) {
            alert('Choose a company category first — the AI uses it for context.');
            catEl.focus();
            return;
        }
        var hint = hintEl && hintEl.value ? String(hintEl.value).trim() : '';

        var payload = {
            kind: kind,
            company_category_slug: slug,
            hint: hint || '',
            existing_short_description: shortTa.value || '',
            existing_description: descTa.value || ''
        };

        if (btnShort) btnShort.disabled = true;
        if (btnDesc) btnDesc.disabled = true;
        setSpinner(kind);

        fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload)
        }).then(function (res) {
            return res.json().then(function (data) {
                return { ok: res.ok, status: res.status, data: data };
            });
        }).then(function (r) {
            if (!r.ok || r.data.error) {
                var msg = (r.data && r.data.error) ? r.data.error : ('Request failed (' + r.status + ')');
                alert(msg);
                return;
            }
            if (r.data.short_description && shortTa) {
                shortTa.value = String(r.data.short_description);
                shortTa.dispatchEvent(new Event('input', { bubbles: true }));
            }
            if (r.data.description && descTa) {
                descTa.value = String(r.data.description);
                descTa.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }).catch(function () {
            alert('Could not reach the server. Check your connection and try again.');
        }).finally(function () {
            restoreButtons();
        });
    }

    if (btnShort) {
        btnShort.addEventListener('click', function () {
            runGenerate('short');
        });
    }
    if (btnDesc) {
        btnDesc.addEventListener('click', function () {
            runGenerate('full');
        });
    }
})();
</script>
<script>
(function () {
    var root = document.getElementById('googleBpRoot');
    if (!root) return;

    var token = root.getAttribute('data-csrf') || '';
    var urlLoc = root.getAttribute('data-url-locations');
    var urlLink = root.getAttribute('data-url-link');
    var urlUnlink = root.getAttribute('data-url-unlink');
    var urlImport = root.getAttribute('data-url-import');
    var btnLoad = document.getElementById('googleBpLoad');
    var sel = document.getElementById('googleBpSelect');
    var btnLink = document.getElementById('googleBpLink');
    var btnUnlink = document.getElementById('googleBpUnlink');
    var btnImport = document.getElementById('googleBpImport');
    var cbOverwrite = document.getElementById('googleBpOverwriteName');
    var msg = document.getElementById('googleBpMsg');

    function flash(text, ok) {
        if (!msg) return;
        msg.textContent = text || '';
        msg.classList.remove('is-err', 'is-ok');
        if (!text) return;
        msg.classList.add(ok ? 'is-ok' : 'is-err');
    }

    function jsonFetch(method, url, bodyObj) {
        return fetch(url, {
            method: method,
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: bodyObj !== undefined ? JSON.stringify(bodyObj) : undefined
        }).then(function (res) {
            return res.json().then(function (data) {
                return { ok: res.ok, status: res.status, data: data };
            });
        });
    }

    function resetSelect() {
        if (!sel) return;
        sel.innerHTML = '';
        var z = document.createElement('option');
        z.value = '';
        z.textContent = 'Choose a listing…';
        sel.appendChild(z);
        sel.disabled = true;
        if (btnLink) btnLink.disabled = true;
    }

    function fillSelect(rows) {
        if (!sel) return;
        resetSelect();
        if (!rows || !rows.length) return;
        rows.forEach(function (row) {
            if (!row || !row.resource) return;
            var opt = document.createElement('option');
            opt.value = String(row.resource);
            var tt = row.title ? String(row.title) : '';
            opt.textContent = tt ? tt + ' — ' + row.resource : row.resource;
            sel.appendChild(opt);
        });
        sel.disabled = false;
        if (btnLink) btnLink.disabled = false;
    }

    if (btnLoad && urlLoc && token) {
        btnLoad.addEventListener('click', function () {
            flash('', true);
            btnLoad.disabled = true;
            jsonFetch('GET', urlLoc, undefined).then(function (r) {
                if (!r.ok || (r.data && r.data.error)) {
                    flash((r.data && r.data.error) ? r.data.error : ('HTTP ' + r.status), false);
                    resetSelect();
                    return;
                }
                var rows = r.data.locations || [];
                if (!rows.length) {
                    flash('No listings returned for your Google account.', false);
                    resetSelect();
                    return;
                }
                flash(rows.length + ' listing(s) loaded. Pick one and link.', true);
                fillSelect(rows);
            }).catch(function () {
                flash('Could not reach the server.', false);
            }).finally(function () {
                btnLoad.disabled = false;
            });
        });
    }

    if (btnLink && urlLink && sel && token) {
        btnLink.addEventListener('click', function () {
            var v = sel.value;
            if (!v) {
                flash('Choose a listing first.', false);
                return;
            }
            flash('', true);
            btnLink.disabled = true;
            jsonFetch('POST', urlLink, { location_resource: v }).then(function (r) {
                if (!r.ok || (r.data && r.data.error)) {
                    flash((r.data && r.data.error) ? r.data.error : ('HTTP ' + r.status), false);
                    return;
                }
                window.location.reload();
            }).catch(function () {
                flash('Could not reach the server.', false);
            }).finally(function () {
                if (btnLink) btnLink.disabled = !sel.value;
            });
        });
        if (sel) {
            sel.addEventListener('change', function () {
                if (btnLink) btnLink.disabled = !sel.value;
            });
        }
    }

    if (btnUnlink && urlUnlink && token) {
        btnUnlink.addEventListener('click', function () {
            if (!window.confirm('Unlink this Google Business Profile listing?')) return;
            flash('', true);
            btnUnlink.disabled = true;
            jsonFetch('POST', urlUnlink, {}).then(function (r) {
                if (!r.ok || (r.data && r.data.error)) {
                    flash((r.data && r.data.error) ? r.data.error : ('HTTP ' + r.status), false);
                    return;
                }
                window.location.reload();
            }).catch(function () {
                flash('Could not reach the server.', false);
            }).finally(function () {
                btnUnlink.disabled = false;
            });
        });
    }

    if (btnImport && urlImport && token) {
        btnImport.addEventListener('click', function () {
            flash('', true);
            var overwrite = cbOverwrite && cbOverwrite.checked ? true : false;
            btnImport.disabled = true;
            jsonFetch('POST', urlImport, { overwrite_name: overwrite }).then(function (r) {
                if (!r.ok || (r.data && r.data.error)) {
                    flash((r.data && r.data.error) ? r.data.error : ('HTTP ' + r.status), false);
                    return;
                }
                var shortTa = document.getElementById('businessBrandShort');
                var descTa = document.getElementById('businessBrandDescription');
                if (r.data.short_description && shortTa) {
                    shortTa.value = String(r.data.short_description);
                    shortTa.dispatchEvent(new Event('input', { bubbles: true }));
                }
                if (r.data.description && descTa) {
                    descTa.value = String(r.data.description);
                    descTa.dispatchEvent(new Event('input', { bubbles: true }));
                }
                if (r.data.name) {
                    var h1 = document.querySelector('.business-pro-title');
                    if (h1) h1.textContent = String(r.data.name);
                }
                flash('Description imported — open the Brand tab to review and click Save brand profile.', true);
            }).catch(function () {
                flash('Could not reach the server.', false);
            }).finally(function () {
                btnImport.disabled = false;
            });
        });
    }
})();
</script>
@endsection
