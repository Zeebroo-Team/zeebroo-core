@extends('theme::layouts.app', ['title' => $employee->full_name, 'heading' => 'Employee'])

@section('content')
<style>
.emp-show-page{max-width:100%;width:100%;margin:0;box-sizing:border-box;}
.emp-show-card{border-radius:var(--radius,14px);border:1px solid var(--border);background:var(--card);padding:clamp(14px,2.2vmin,22px);}
.emp-show__head{margin-bottom:clamp(16px,2.5vmin,22px);display:flex;flex-direction:column;gap:14px;}
.emp-show__toprow{
    display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:clamp(12px,2.5vw,20px);
}
.emp-show__identity{display:flex;align-items:center;gap:clamp(12px,2vw,18px);min-width:0;flex:1 1 220px;}
/* NOTE: .emp-show__avatar--placeholder must NOT set width/height % — same element as .emp-show__avatar, and % would override fixed size (huge circle bug). */
.emp-show__avatar{
    box-sizing:border-box;
    flex:0 0 88px;
    align-self:flex-start;
    width:88px;
    min-width:88px;
    max-width:88px;
    height:88px;
    min-height:88px;
    max-height:88px;
    aspect-ratio:1;
    border-radius:50%;
    overflow:hidden;
    border:2px solid color-mix(in srgb,var(--border)80%,transparent);
    background:color-mix(in srgb,var(--primary)12%,var(--card));
    box-shadow:0 1px 0 color-mix(in srgb,var(--border)50%,transparent) inset,0 4px 12px rgba(0,0,0,.07);
    position:relative;
}
.emp-show__avatar img{
    width:100%;height:100%;max-width:none;object-fit:cover;object-position:center center;display:block;
}
.emp-show__avatar--placeholder{
    display:flex;align-items:center;justify-content:center;
    font-size:clamp(20px,2.2vw,26px);font-weight:800;letter-spacing:-.02em;
    color:color-mix(in srgb,var(--primary)50%,var(--text));
    user-select:none;
}
.emp-show__title-block{min-width:0;align-self:center;}
.emp-show__title{margin:0;font-size:clamp(18px,2.4vw,22px);font-weight:800;letter-spacing:-.02em;}
.emp-show__meta{margin:6px 0 0;font-size:13px;color:var(--muted);line-height:1.45;}
.emp-show__photo-panel{
    flex:0 1 340px;min-width:min(100%,260px);
    display:flex;flex-direction:column;gap:10px;
    padding:12px 14px;border-radius:10px;
    border:1px solid color-mix(in srgb,var(--border)88%,transparent);
    background:var(--card);
    box-sizing:border-box;
}
.emp-show__photo-panel-h{
    margin:0;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);
}
.emp-show__upload-form{margin:0;}
.emp-show__upload-row{display:flex;flex-wrap:wrap;align-items:center;gap:8px;}
.emp-show__file-input{
    flex:1 1 180px;min-width:0;max-width:100%;
    font-size:12px;color:var(--muted);
}
.emp-show__file-input::file-selector-button{
    font-family:inherit;margin-inline-end:10px;padding:8px 14px;border-radius:8px;
    border:1px solid color-mix(in srgb,var(--border)95%,transparent);
    background:color-mix(in srgb,var(--card)94%,var(--border));
    color:var(--text);font-size:12px;font-weight:600;cursor:pointer;
    transition:border-color .15s ease,background .15s ease;
}
.emp-show__file-input::file-selector-button:hover{
    border-color:color-mix(in srgb,var(--primary)38%,var(--border));
    background:color-mix(in srgb,var(--primary)7%,var(--card));
}
.emp-show__btn-upload{
    padding:8px 16px;font-size:12px;font-weight:600;border-radius:8px;cursor:pointer;font-family:inherit;
    border:1px solid color-mix(in srgb,var(--primary)42%,var(--border));
    background:color-mix(in srgb,var(--primary)12%,transparent);color:var(--text);
    white-space:nowrap;
}
.emp-show__btn-upload:hover{background:color-mix(in srgb,var(--primary)18%,transparent);}
.emp-show__btn-remove{
    align-self:flex-start;padding:7px 12px;font-size:12px;font-weight:600;border-radius:8px;cursor:pointer;font-family:inherit;
    border:1px solid var(--border);background:transparent;color:var(--muted);
}
.emp-show__btn-remove:hover{color:var(--text);border-color:color-mix(in srgb,var(--muted)45%,var(--border));}
.emp-dropzone{
    position:relative;min-height:76px;padding:12px 14px;border:2px dashed color-mix(in srgb,var(--border)88%,transparent);
    border-radius:10px;background:color-mix(in srgb,var(--card)99%,transparent);box-sizing:border-box;
    display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;text-align:center;
    transition:border-color .15s ease,background .15s ease;
}
.emp-dropzone.is-dragover{
    border-color:color-mix(in srgb,var(--primary)50%,var(--border));
    background:color-mix(in srgb,var(--primary)9%,var(--card));
}
.emp-dropzone__input{
    position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer;font-size:0;margin:0;padding:0;
}
.emp-dropzone__label{font-size:13px;font-weight:600;color:var(--text);pointer-events:none;}
.emp-dropzone__label i{margin-right:6px;opacity:.88;}
.emp-dropzone__or{font-size:11px;color:var(--muted);pointer-events:none;}
.emp-dropzone__filename{font-size:11px;color:var(--muted);word-break:break-word;max-width:100%;min-height:1.35em;padding:0 4px;}
.emp-show__nav-row{display:flex;flex-wrap:wrap;gap:8px;align-items:center;padding-top:12px;border-top:1px solid color-mix(in srgb,var(--border)65%,transparent);}
.emp-show__nav-btn{
    padding:8px 14px;font-size:13px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;border-radius:10px;
    border:1px solid var(--border);background:color-mix(in srgb,var(--card)96%,transparent);color:var(--text);
}
.emp-show__nav-btn:hover{border-color:color-mix(in srgb,var(--primary)35%,var(--border));}
.emp-show__nav-btn--primary{border-color:color-mix(in srgb,var(--primary)38%,var(--border));background:color-mix(in srgb,var(--primary)10%,transparent);}
.emp-show__flash{margin:0 0 12px;padding:10px 12px;border-radius:10px;border:1px solid color-mix(in srgb,#22c55e 40%,var(--border));background:color-mix(in srgb,#22c55e 9%,transparent);font-size:13px;font-weight:600;}
.emp-show__err{margin:0 0 10px;padding:8px 10px;border-radius:8px;border:1px solid color-mix(in srgb,#f87171 40%,var(--border));background:color-mix(in srgb,#f87171 8%,transparent);font-size:12px;}
.emp-docs-upload-card{
    margin:0 0 20px;padding:clamp(14px,2vmin,18px) clamp(14px,2.2vmin,20px);border-radius:12px;
    border:1px solid color-mix(in srgb,var(--border)88%,transparent);
    background:var(--card);
    box-shadow:0 1px 0 color-mix(in srgb,var(--border)55%,transparent) inset,0 6px 20px rgba(0,0,0,.04);
}
:is(html[data-theme="dark"],html[data-theme*="dark"]) .emp-docs-upload-card{
    box-shadow:0 1px 0 rgba(255,255,255,.04) inset,0 8px 28px rgba(0,0,0,.28);
}
.emp-docs-form{margin:0;}
.emp-docs-upload-grid{display:grid;gap:clamp(14px,2vw,18px);}
@media (min-width:880px){
    .emp-docs-upload-grid{grid-template-columns:minmax(200px,.38fr) minmax(0,1fr);align-items:start;}
}
.emp-docs-upload-field{display:flex;flex-direction:column;gap:8px;min-width:0;}
.emp-docs-upload-field--span{grid-column:1/-1;}
@media (min-width:880px){
    .emp-docs-upload-field--span{grid-column:2/-1;}
    .emp-docs-upload-field:first-child{align-self:start;}
}
.emp-docs-upload-field label,.emp-docs-form label{
    font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);
}
.emp-docs-form__select{
    width:100%;max-width:100%;padding:10px 12px;border-radius:10px;border:1px solid color-mix(in srgb,var(--border)92%,transparent);
    background:var(--card);color:var(--text);font-size:13px;font-family:inherit;line-height:1.35;
    transition:border-color .15s ease,box-shadow .15s ease;
}
.emp-docs-form__select:hover{border-color:color-mix(in srgb,var(--primary)28%,var(--border));}
.emp-docs-form__select:focus{
    outline:none;border-color:color-mix(in srgb,var(--primary)42%,var(--border));
    box-shadow:0 0 0 3px color-mix(in srgb,var(--primary)18%,transparent);
}
.emp-docs-dropzone{
    min-height:128px!important;padding:16px 18px!important;border-width:2px!important;border-style:dashed!important;
}
.emp-docs-dropzone:focus-within{
    border-color:color-mix(in srgb,var(--primary)38%,var(--border))!important;
    box-shadow:0 0 0 3px color-mix(in srgb,var(--primary)12%,transparent);
}
.emp-docs-dropzone__icon{
    display:flex;align-items:center;justify-content:center;width:44px;height:44px;margin:2px auto 10px;border-radius:12px;
    background:color-mix(in srgb,var(--primary)11%,var(--card));color:color-mix(in srgb,var(--primary)82%,var(--text));
    font-size:18px;
}
.emp-docs-dropzone__hint{
    margin:10px 0 0;font-size:11px;line-height:1.45;color:var(--muted);max-width:42ch;margin-left:auto;margin-right:auto;
}
.emp-dropzone__meta{display:block;margin-top:4px;font-size:11px;font-weight:600;color:color-mix(in srgb,var(--muted)92%,var(--text));}
.emp-dropzone__err{
    margin:10px 0 0;padding:8px 10px;border-radius:8px;font-size:12px;font-weight:600;line-height:1.35;
    border:1px solid color-mix(in srgb,#f87171 45%,var(--border));background:color-mix(in srgb,#f87171 9%,var(--card));color:var(--text);
}
.emp-docs-upload-actions{
    display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-top:12px;
}
.emp-show__btn-upload--docs{padding:10px 20px;font-size:13px;border-radius:10px;font-weight:700;}
.emp-docs-clear-btn{
    padding:9px 14px;font-size:12px;font-weight:600;border-radius:9px;cursor:pointer;font-family:inherit;
    border:1px solid var(--border);background:transparent;color:var(--muted);
}
.emp-docs-clear-btn:hover{color:var(--text);border-color:color-mix(in srgb,var(--muted)40%,var(--border));}
.emp-docs-table-wrap{margin-top:14px;overflow:auto;border:1px solid color-mix(in srgb,var(--border)92%,transparent);border-radius:10px;}
.emp-docs-table{width:100%;border-collapse:collapse;font-size:13px;min-width:520px;}
.emp-leave-table-wrap .emp-docs-table{min-width:760px;}
.emp-docs-table th{text-align:left;padding:8px 10px;background:color-mix(in srgb,var(--card)92%,transparent);font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);border-bottom:1px solid var(--border);}
.emp-docs-table td{padding:9px 10px;border-bottom:1px solid color-mix(in srgb,var(--border)82%,transparent);vertical-align:middle;color:var(--text);}
.emp-docs-table tr:last-child td{border-bottom:none;}
.emp-docs-table__fname{font-weight:600;word-break:break-word;}
.emp-docs-table__meta{font-size:11px;color:var(--muted);}
.emp-docs-table th:last-child,.emp-docs-table td:last-child{text-align:end;vertical-align:middle;width:1%;white-space:nowrap;}
.emp-docs-table__acts{
    display:flex;flex-wrap:nowrap;align-items:center;justify-content:flex-end;gap:4px;
}
.emp-docs-table__acts form{display:flex;flex-wrap:nowrap;align-items:center;gap:4px;margin:0;padding:0;}
.emp-docs-action{
    display:inline-flex;align-items:center;justify-content:center;box-sizing:border-box;
    min-height:0;padding:4px 8px;font-size:11px;font-weight:600;font-family:inherit;line-height:1.15;
    border-radius:6px;cursor:pointer;text-decoration:none;white-space:nowrap;
    border:1px solid color-mix(in srgb,var(--primary)38%,var(--border));
    background:color-mix(in srgb,var(--primary)10%,transparent);color:var(--primary);
    transition:border-color .15s ease,background .15s ease,color .15s ease;
}
.emp-docs-action:hover{
    background:color-mix(in srgb,var(--primary)16%,transparent);
    border-color:color-mix(in srgb,var(--primary)48%,var(--border));
    color:var(--text);
}
.emp-docs-action--danger{
    border-color:color-mix(in srgb,var(--border)92%,transparent);
    background:color-mix(in srgb,var(--card)96%,transparent);
    color:var(--muted);
}
.emp-docs-action--danger:hover{
    color:var(--text);
    border-color:color-mix(in srgb,#f87171 45%,var(--border));
    background:color-mix(in srgb,#f87171 7%,var(--card));
}

.emp-leave-card{
    margin:0 0 18px;padding:clamp(14px,2vmin,18px);border-radius:12px;border:1px solid color-mix(in srgb,var(--border)88%,transparent);
    background:var(--card);box-shadow:0 1px 0 color-mix(in srgb,var(--border)55%,transparent) inset,0 6px 18px rgba(0,0,0,.04);
}
:is(html[data-theme="dark"],html[data-theme*="dark"]) .emp-leave-card{
    box-shadow:0 1px 0 rgba(255,255,255,.04) inset,0 8px 24px rgba(0,0,0,.26);
}
.emp-leave-card__head{margin:0 0 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);}
.emp-leave-grid{display:grid;gap:12px;}@media (min-width:640px){.emp-leave-grid{grid-template-columns:repeat(2,minmax(0,1fr));}}
.emp-leave-grid__full{grid-column:1/-1;}
.emp-leave-field{display:flex;flex-direction:column;gap:6px;min-width:0;}
.emp-leave-field label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);}
.emp-leave-field textarea{width:100%;box-sizing:border-box;padding:10px 12px;font-size:13px;line-height:1.45;border-radius:10px;border:1px solid color-mix(in srgb,var(--border)92%,transparent);background:var(--card);color:var(--text);font-family:inherit;}
.emp-leave-actions{margin-top:4px;display:flex;flex-wrap:wrap;gap:10px;align-items:center;}
.emp-leave-submit{padding:10px 20px;font-size:13px;font-weight:700;border-radius:10px;cursor:pointer;font-family:inherit;
    border:1px solid color-mix(in srgb,var(--primary)42%,var(--border));background:color-mix(in srgb,var(--primary)12%,transparent);color:var(--text);}
.emp-leave-submit:hover{
    background:color-mix(in srgb,var(--primary)18%,transparent);
    color:var(--text);
    transform:none;
}
.emp-docs-pill{
    font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;padding:2px 8px;border-radius:999px;white-space:nowrap;display:inline-block;
}
.emp-docs-pill--pending{color:#b45309;background:color-mix(in srgb,#b45309 12%,transparent);border:1px solid color-mix(in srgb,#b45309 28%,var(--border));}
.emp-docs-pill--approved{color:#15803d;background:color-mix(in srgb,#22c55e 11%,transparent);border:1px solid color-mix(in srgb,#22c55e 30%,var(--border));}
.emp-docs-pill--rejected{color:var(--muted);background:color-mix(in srgb,var(--card)92%,transparent);border:1px solid var(--border);}
.emp-leave-act{display:inline-flex;align-items:center;padding:4px 8px;font-size:11px;font-weight:600;line-height:1.15;border-radius:6px;cursor:pointer;font-family:inherit;border:1px solid var(--border);background:color-mix(in srgb,var(--card)94%,transparent);color:var(--text);white-space:nowrap;}
.emp-leave-act--ok{border-color:color-mix(in srgb,#22c55e 35%,var(--border));color:color-mix(in srgb,#15803d 92%,var(--text));}
.emp-leave-act--no{border-color:color-mix(in srgb,var(--border)90%,transparent);color:var(--muted);}
.emp-leave-toolbar{
    display:flex;align-items:center;justify-content:flex-end;gap:10px;margin:0 0 12px;
}
.emp-leave-add-btn{
    display:inline-flex;align-items:center;gap:7px;
    padding:9px 14px;border-radius:10px;cursor:pointer;font-size:13px;font-weight:700;font-family:inherit;
    border:1px solid color-mix(in srgb,var(--primary)42%,var(--border));
    background:color-mix(in srgb,var(--primary)12%,transparent);color:var(--text);
}
.emp-leave-add-btn:hover{
    background:color-mix(in srgb,var(--primary)18%,transparent);
    color:var(--text);
    transform:none;
}
.emp-leave-modal{
    position:fixed;inset:0;z-index:1300;display:none;
}
.emp-leave-modal.is-open{display:block;}
.emp-leave-modal__backdrop{
    all:unset;
    position:absolute;inset:0;display:block;cursor:pointer;
    background:rgba(15,23,42,.46);
}
.emp-leave-modal__backdrop:hover,
.emp-leave-modal__backdrop:focus-visible{
    background:rgba(15,23,42,.46);
    transform:none;
    color:inherit;
}
.emp-leave-modal__dialog{
    position:relative;
    width:min(92vw,1120px);
    max-width:calc(100vw - 30px);
    max-height:calc(100vh - 36px);
    overflow:auto;
    margin:18px auto;
    border-radius:14px;
    border:1px solid color-mix(in srgb,var(--border)90%,transparent);
    background:var(--card);
    padding:clamp(14px,2.1vmin,20px);
    box-shadow:0 16px 42px rgba(0,0,0,.24);
}
.emp-leave-modal__split{
    display:grid;
    grid-template-columns:minmax(0,1fr) minmax(280px,1.14fr);
    gap:clamp(16px,2.8vw,26px);
    align-items:start;
}
@media (max-width:900px){
    .emp-leave-modal__split{grid-template-columns:1fr;}
    .emp-leave-preview-col{order:-1;}
}
.emp-leave-preview-col{min-width:0;}
.emp-leave-preview__tag{
    margin:0 0 8px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);
}
.emp-leave-preview__watermark{
    margin:0 0 10px;font-size:11px;font-weight:600;color:color-mix(in srgb,var(--muted)88%,var(--text));
}
.emp-leave-preview__sheet{
    background:#ffffff;
    color:#111827;
    border:1px solid #e5e7eb;
    border-radius:10px;
    padding:clamp(18px,2.8vmin,26px);
    min-height:280px;
    max-height:min(52vh,520px);
    overflow:auto;
    box-shadow:0 2px 8px rgba(15,23,42,.08),0 0 0 1px rgba(15,23,42,.04);
    font-family:ui-serif,Georgia,Cambria,"Times New Roman",Times,serif;
    font-size:13px;
    line-height:1.55;
}
.emp-leave-preview__sheet p{margin:0 0 12px;}
.emp-leave-preview__sheet p:last-child{margin-bottom:0;}
.emp-leave-preview__muted{color:#64748b;font-size:12px;}
.emp-leave-preview__subject{font-weight:700;margin-bottom:14px;}
.emp-leave-preview__note{margin-top:12px;padding-top:12px;border-top:1px dashed #e5e7eb;font-style:italic;color:#334155;}
.emp-leave-preview__sigblock{margin-top:14px;white-space:pre-line;}
.emp-leave-modal__form-col{
    min-width:0;
    border-radius:12px;
    border:1px solid color-mix(in srgb,var(--border)88%,transparent);
    background:color-mix(in srgb,var(--card)96%,var(--border));
    padding:clamp(14px,2.2vmin,20px);
    box-shadow:0 1px 0 color-mix(in srgb,var(--border)55%,transparent) inset;
}
.emp-leave-modal__form-head{
    margin:0 0 6px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);
}
.emp-leave-modal__form-lead{
    margin:0 0 16px;font-size:12px;line-height:1.5;color:var(--muted);max-width:52ch;
}
.emp-leave-grid--modal{
    display:grid;
    gap:14px;
    grid-template-columns:1fr;
}
@media (min-width:520px){
    .emp-leave-grid--modal{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
    .emp-leave-grid--modal .emp-leave-field--type,
    .emp-leave-grid--modal .emp-leave-field--note{
        grid-column:1/-1;
    }
}
.emp-leave-modal__form-col .emp-leave-field label{
    font-size:11px;letter-spacing:.05em;margin-bottom:2px;
}
.emp-leave-modal__form-col .emp-docs-form__select,
.emp-leave-modal__form-col input[type="date"]{
    min-height:44px;
    box-sizing:border-box;
    padding-top:10px;padding-bottom:10px;
}
.emp-leave-modal__form-col .emp-leave-field textarea{
    min-height:96px;
    resize:vertical;
    line-height:1.45;
}
.emp-leave-modal__form-col .emp-docs-form__select:focus,
.emp-leave-modal__form-col input[type="date"]:focus,
.emp-leave-modal__form-col .emp-leave-field textarea:focus{
    outline:none;
    border-color:color-mix(in srgb,var(--primary)42%,var(--border));
    box-shadow:0 0 0 3px color-mix(in srgb,var(--primary)14%,transparent);
}
.emp-leave-modal__form-col .emp-leave-actions{
    margin-top:6px;
    padding-top:16px;
    border-top:1px solid color-mix(in srgb,var(--border)78%,transparent);
}
.emp-leave-modal__form-col .emp-leave-submit{
    width:100%;
    min-height:46px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    box-sizing:border-box;
}
.emp-leave-modal__head{
    display:flex;align-items:center;justify-content:space-between;gap:10px;margin:0 0 10px;
}
.emp-leave-modal__title{
    margin:0;font-size:15px;font-weight:800;letter-spacing:-.01em;color:var(--text);
}
.emp-leave-modal__close{
    width:32px;height:32px;border-radius:8px;border:1px solid var(--border);
    background:transparent;color:var(--muted);cursor:pointer;font-size:14px;
}
.emp-leave-modal__close:hover{
    color:var(--text);
    border-color:color-mix(in srgb,var(--muted)45%,var(--border));
    background:color-mix(in srgb,var(--card)94%,transparent);
    transform:none;
}
@media (max-width:900px){
    .emp-leave-modal__dialog{width:min(94vw,1120px);}
}

.emp-show-layout{display:grid;grid-template-columns:1fr clamp(182px,22vw,240px);gap:0;min-height:280px;align-items:stretch;}
@media (max-width:820px){
    .emp-show-layout{display:flex;flex-direction:column;}
    .emp-show-tabs{
        order:-1;width:100%;border:none;border-bottom:1px solid var(--border);
        flex-direction:row!important;flex-wrap:nowrap;overflow-x:auto;-webkit-overflow-scrolling:touch;
        padding:10px 0 14px;margin:0 0 8px!important;
        position:relative!important;top:auto!important;max-height:none!important;
        background:transparent;
    }
    .emp-show-tab{width:auto!important;flex-shrink:0;margin-bottom:0!important;white-space:nowrap;}
}

.emp-show-main{padding:0 clamp(4px,1.2vmin,14px) 0 0;}
@media (max-width:820px){.emp-show-main{padding:0;width:100%;}}

.emp-show-pane{display:none;}
.emp-show-pane.is-active{display:block;animation:emp-show-pane-fade .26s ease;}
@keyframes emp-show-pane-fade{from{opacity:0;transform:translateY(6px);}to{opacity:1;transform:none;}}

.emp-show-pane-head{margin:0 0 14px;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);}
.emp-show__block{margin:0;padding:0;border:none;background:transparent;}
.emp-show__rows{display:grid;gap:14px clamp(18px,3vw,32px);}
@media (min-width:768px){.emp-show__rows{grid-template-columns:repeat(2,minmax(0,1fr));}}
@media (min-width:1100px){.emp-show__rows{grid-template-columns:repeat(3,minmax(0,1fr));}}
.emp-show__row{display:flex;flex-direction:column;gap:4px;min-width:0;padding:12px 14px;border-radius:12px;border:1px solid color-mix(in srgb,var(--border)92%,transparent);background:color-mix(in srgb,var(--card)96%,transparent);}
.emp-show__row--full{grid-column:1/-1;}
.emp-show__dt{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);}
.emp-show__dd{margin:0;font-size:14px;line-height:1.5;color:var(--text);word-break:break-word;}
.emp-show__dd a{color:var(--primary);font-weight:600;text-decoration:none;}
.emp-show__dd a:hover{text-decoration:underline;}
.emp-show__row-head{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;}
.emp-show__mini-edit{
    flex-shrink:0;width:30px;height:30px;padding:0;border-radius:8px;border:1px solid color-mix(in srgb,var(--border)92%,transparent);
    background:color-mix(in srgb,var(--card)94%,transparent);color:var(--muted);cursor:pointer;font-size:12px;line-height:1;
}
.emp-show__mini-edit:hover{color:var(--text);border-color:color-mix(in srgb,var(--primary)38%,var(--border));background:color-mix(in srgb,var(--primary)8%,transparent);}
.emp-show__input{
    width:100%;max-width:100%;box-sizing:border-box;padding:10px 12px;border-radius:10px;border:1px solid var(--border);
    background:var(--card);color:var(--text);font-size:14px;font-family:inherit;
}
.emp-show__input--textarea{min-height:88px;resize:vertical;line-height:1.45;}
.emp-show__input--select{min-height:42px;}
.emp-show__edit-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;align-items:center;}
.emp-show__btn-save{
    padding:8px 14px;font-size:12px;font-weight:700;border-radius:8px;cursor:pointer;font-family:inherit;
    border:1px solid color-mix(in srgb,var(--primary)42%,var(--border));background:color-mix(in srgb,var(--primary)12%,transparent);color:var(--text);
}
.emp-show__btn-save:hover{background:color-mix(in srgb,var(--primary)18%,transparent);}
.emp-show__btn-cancel{padding:8px 14px;font-size:12px;border-radius:8px;cursor:pointer;font-family:inherit;border:1px solid var(--border);background:transparent;color:var(--muted);}
.emp-show__btn-cancel:hover{color:var(--text);}
.emp-show__field-err{margin:8px 0 0;font-size:12px;color:#dc2626;}

.emp-show-tabs{
    width:100%;border-left:1px solid var(--border);
    padding:clamp(8px,1.5vmin,14px) 0 clamp(8px,1.5vmin,14px) clamp(12px,1.8vmin,18px);background:transparent;
}
@media (min-width:821px){
    .emp-show-tabs{
        align-self:start;position:sticky;top:calc(env(safe-area-inset-top,0px) + 68px);z-index:10;
        max-height:calc(100vh - env(safe-area-inset-top,0px) - 80px);
        overflow-y:auto;overflow-x:hidden;overscroll-behavior:contain;
        background:var(--card);box-sizing:border-box;
    }
}
.emp-show-tab{
    width:100%;display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:11px;margin-bottom:5px;border:1px solid transparent;background:transparent;
    color:var(--muted);font-size:13px;font-weight:600;cursor:pointer;text-align:left;font-family:inherit;transition:.16s ease;
}
.emp-show-tab i{width:14px;text-align:center;font-size:13px;flex-shrink:0;}
.emp-show-tab:hover{color:var(--text);border-color:var(--border);background:color-mix(in srgb,var(--primary)6%,transparent);}
.emp-show-tab.is-active{
    color:var(--text);border-color:color-mix(in srgb,var(--primary)45%,var(--border));
    background:color-mix(in srgb,var(--primary)10%,transparent);box-shadow:none;
}

.emp-show-prose{margin:0 0 14px;font-size:14px;line-height:1.6;color:var(--muted);max-width:76ch;}
.emp-show-empty{
    margin:0;padding:clamp(16px,2.5vmin,22px);border-radius:12px;border:1px dashed color-mix(in srgb,var(--border)82%,var(--muted));
    background:color-mix(in srgb,var(--card)98%,transparent);font-size:14px;line-height:1.55;color:var(--muted);
}
.emp-show-empty strong{color:var(--text);font-weight:700;}

.emp-ov-cards{
    display:grid;gap:10px clamp(12px,1.5vw,16px);
    grid-template-columns:repeat(2,minmax(0,1fr));
    align-items:stretch;
}
@media (min-width:1024px){
    .emp-ov-cards{grid-template-columns:repeat(4,minmax(0,1fr));gap:12px clamp(12px,1.6vw,18px);}
}
@media (max-width:520px){.emp-ov-cards{grid-template-columns:1fr;}}

.emp-ov-card{
    position:relative;display:flex;flex-direction:column;box-sizing:border-box;border-radius:12px;padding:0;min-height:0;
    border:1px solid color-mix(in srgb,var(--border)92%,transparent);
    background:var(--card);
    box-shadow:0 1px 2px rgba(0,0,0,.05),0 4px 12px rgba(0,0,0,.04);
    overflow:hidden;min-width:0;
}
:is(html[data-theme="dark"],html[data-theme*="dark"]) .emp-ov-card{
    box-shadow:0 1px 0 rgba(255,255,255,.05) inset,0 8px 24px rgba(0,0,0,.35);
}
.emp-ov-card__rail{
    position:absolute;left:0;top:0;bottom:0;width:3px;border-radius:12px 0 0 12px;
    background:color-mix(in srgb,var(--ov-accent,var(--primary))72%,transparent);
}
.emp-ov-card__body{
    flex:1;display:flex;flex-direction:column;justify-content:flex-start;min-width:0;
    padding:8px 10px 8px 15px;/* room after rail */
    gap:0;
}
.emp-ov-card__title{
    margin:0 0 3px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;
    color:color-mix(in srgb,var(--muted)85%,var(--text));
    line-height:1.25;min-width:0;
}

.emp-ov-card__metric{
    display:flex;flex-wrap:wrap;align-items:baseline;gap:0 .35em;margin:0 0 0;min-width:0;
}
.emp-ov-card__value{
    margin:0;font-size:clamp(13px,.25vw + 12.6px,14px);font-weight:600;letter-spacing:-.01em;color:var(--text);
    line-height:1.3;font-variant-numeric:tabular-nums;word-break:break-word;max-width:100%;
}
.emp-ov-card__suffix{
    display:inline;margin:0;font-size:12px;font-weight:500;color:var(--muted);line-height:1.3;white-space:nowrap;
}

.emp-ov-card__footer{
    margin-top:auto;padding-top:7px;
}
.emp-ov-card__hint{
    margin:0;font-size:11px;line-height:1.38;color:color-mix(in srgb,var(--muted)97%,var(--text));
}
.emp-ov-card__detail{
    margin:5px 0 0;padding:4px 8px;border-radius:6px;
    font-size:10.5px;font-weight:600;line-height:1.36;color:color-mix(in srgb,var(--text)90%,var(--muted));
    background:color-mix(in srgb,var(--ov-accent,var(--primary))9%,var(--card));
    border:1px solid color-mix(in srgb,var(--ov-accent,var(--primary))14%,var(--border));
}

.emp-payslip-list{display:grid;gap:10px;}
.emp-payslip-card{
    border:1px solid color-mix(in srgb,var(--border)88%,transparent);
    border-radius:12px;padding:11px 12px;
    background:color-mix(in srgb,var(--card)98%,transparent);
}
.emp-payslip-card__row{
    display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:8px 12px;
}
.emp-payslip-card__period{margin:0;font-size:14px;font-weight:700;line-height:1.3;color:var(--text);}
.emp-payslip-card__meta{margin:2px 0 0;font-size:12px;line-height:1.4;color:var(--muted);}
.emp-payslip-card__actions{display:flex;flex-wrap:wrap;gap:7px;}
.emp-payslip-card__btn{
    display:inline-flex;align-items:center;justify-content:center;gap:5px;
    padding:6px 10px;font-size:12px;font-weight:700;border-radius:8px;text-decoration:none;
    border:1px solid color-mix(in srgb,var(--primary)38%,var(--border));
    background:color-mix(in srgb,var(--primary)10%,transparent);color:var(--text);
}
.emp-payslip-card__btn:hover{
    text-decoration:none;background:color-mix(in srgb,var(--primary)16%,transparent);
}
</style>

@php
    $overviewMetrics ??= [];
    $bizCurrency = (string) (get_settings('business.currency', '', $business ?? null) ?: '');
    $employeePayslips ??= collect();
    $empDept = $employee->department;
    $deptSalMin = $empDept?->salary_range_min !== null ? (float) $empDept->salary_range_min : null;
    $deptSalMax = $empDept?->salary_range_max !== null ? (float) $empDept->salary_range_max : null;
    $hasDeptSalaryGuide = $deptSalMin !== null || $deptSalMax !== null;
@endphp

<div class="emp-show-page emp-show-card">
    @if(session('status'))
        <p class="emp-show__flash" role="status">{{ session('status') }}</p>
    @endif
    @if($errors->has('profile_photo'))
        <p class="emp-show__err" role="alert">{{ $errors->first('profile_photo') }}</p>
    @endif
    <header class="emp-show__head">
        <div class="emp-show__toprow">
            <div class="emp-show__identity">
                @if($employee->profilePhotoUrl())
                    <span class="emp-show__avatar">
                        <img src="{{ $employee->profilePhotoUrl() }}" alt="{{ __('Profile photo of :name', ['name' => $employee->full_name]) }}" width="88" height="88" loading="eager" decoding="async" fetchpriority="high">
                    </span>
                @else
                    <span class="emp-show__avatar emp-show__avatar--placeholder" aria-hidden="true">{{ $employee->avatarInitials() }}</span>
                @endif
                <div class="emp-show__title-block">
                    <h1 class="emp-show__title">{{ $employee->full_name }}</h1>
                    <p class="emp-show__meta">
                        <strong style="color:var(--text);">{{ $employee->employee_id }}</strong>
                        @if($employee->jobTitle)<span> · </span>{{ $employee->jobTitle->name }}@endif
                        @if($employee->department)<span> · </span>{{ $employee->department->name }}@endif
                    </p>
                </div>
            </div>
            <div class="emp-show__photo-panel" aria-label="{{ __('Profile photo') }}">
                <p class="emp-show__photo-panel-h">{{ __('Profile photo') }}</p>
                <form method="post" action="{{ route('hr.employees.profile-photo.store', $employee) }}" enctype="multipart/form-data" class="emp-show__upload-form">
                    @csrf
                    <div class="emp-show__upload-row">
                        <input type="file" name="profile_photo" id="emp-profile-photo-input" class="emp-show__file-input" accept="image/*" required>
                        <button type="submit" class="emp-show__btn-upload">{{ __('Upload') }}</button>
                    </div>
                </form>
                @if($employee->hasProfilePhoto())
                    <form method="post" action="{{ route('hr.employees.profile-photo.destroy', $employee) }}" onsubmit="return confirm(@json(__('Remove this profile photo?')))">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="emp-show__btn-remove">{{ __('Remove photo') }}</button>
                    </form>
                @endif
            </div>
        </div>
        <div class="emp-show__nav-row">
            <a href="{{ route('hr.employees.index') }}" class="emp-show__nav-btn"><i class="fa fa-arrow-left" aria-hidden="true"></i>{{ __('Employees') }}</a>
            <a href="{{ route('hr.index') }}" class="emp-show__nav-btn emp-show__nav-btn--primary"><i class="fa fa-layer-group" aria-hidden="true"></i>{{ __('HR hub') }}</a>
        </div>
    </header>

    <div class="emp-show-layout">
        <div class="emp-show-main">
            <div
                id="emp-show-panel-overview"
                class="emp-show-pane is-active"
                role="tabpanel"
                aria-labelledby="emp-show-tab-overview"
                aria-hidden="false"
            >
                <p class="emp-show-pane-head">{{ __('Overview') }}</p>
                <p class="emp-show-prose" style="margin-bottom:18px;">{{ __('At-a-glance People metrics · live data expands as Payroll, Leave, and Attendance connect.') }}</p>
                <div class="emp-ov-cards" role="region" aria-label="{{ __('Overview metrics') }}">
                    @foreach (['leave', 'attendance', 'salary', 'deductions'] as $ovKey)
                        @continue(! isset($overviewMetrics[$ovKey]) || ! is_array($overviewMetrics[$ovKey]))
                        @php
                            $c = $overviewMetrics[$ovKey];
                        @endphp
                        <article class="emp-ov-card" style="--ov-accent: {{ $c['accent'] ?? 'var(--primary)' }};">
                            <span class="emp-ov-card__rail" aria-hidden="true"></span>
                            <div class="emp-ov-card__body">
                                <p class="emp-ov-card__title" id="emp-ov-{{ $ovKey }}-title">{{ $c['title'] ?? '' }}</p>
                                <div class="emp-ov-card__metric" aria-describedby="emp-ov-{{ $ovKey }}-title">
                                    <span class="emp-ov-card__value">{{ $c['value'] ?? '—' }}</span>@if(! empty($c['suffix']))<span class="emp-ov-card__suffix">&nbsp;{{ $c['suffix'] }}</span>@endif
                                </div>
                                <div class="emp-ov-card__footer">
                                    @if(! empty($c['hint']))
                                        <p class="emp-ov-card__hint">{{ $c['hint'] }}</p>
                                    @endif
                                    @if(($ovKey === 'deductions') && isset($c['detail']) && (string) $c['detail'] !== '')
                                        <div class="emp-ov-card__detail">{{ $c['detail'] }}</div>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>

            <div
                id="emp-show-panel-personal"
                class="emp-show-pane"
                role="tabpanel"
                aria-labelledby="emp-show-tab-personal"
                aria-hidden="true"
            >
                <p class="emp-show-pane-head">Personal details</p>
                <section class="emp-show__block" aria-label="{{ __('Personal details') }}">
                    @php
                        $dobVal = $employee->date_of_birth?->format('Y-m-d');
                    @endphp
                    <div class="emp-show__rows">
                        @include('hrmanagement::employees.partials.editable-field-row', ['label' => __('Full name'), 'field' => 'full_name', 'panel' => 'personal', 'type' => 'text', 'value' => $employee->full_name])
                        @include('hrmanagement::employees.partials.editable-field-row', ['label' => __('Date of birth'), 'field' => 'date_of_birth', 'panel' => 'personal', 'type' => 'date', 'value' => $dobVal])
                        @include('hrmanagement::employees.partials.editable-field-row', ['label' => __('NIC / Passport'), 'field' => 'nic_passport_number', 'panel' => 'personal', 'type' => 'text', 'value' => $employee->nic_passport_number])
                        @include('hrmanagement::employees.partials.editable-field-row', ['label' => __('Permanent address'), 'field' => 'permanent_address', 'panel' => 'personal', 'type' => 'textarea', 'value' => $employee->permanent_address, 'fullWidth' => true])
                        @include('hrmanagement::employees.partials.editable-field-row', ['label' => __('Current address'), 'field' => 'current_address', 'panel' => 'personal', 'type' => 'textarea', 'value' => $employee->current_address, 'fullWidth' => true])
                        @include('hrmanagement::employees.partials.editable-field-row', ['label' => __('Phone'), 'field' => 'phone_number', 'panel' => 'personal', 'type' => 'tel', 'value' => $employee->phone_number])
                        @include('hrmanagement::employees.partials.editable-field-row', ['label' => __('Personal email'), 'field' => 'personal_email', 'panel' => 'personal', 'type' => 'email', 'value' => $employee->personal_email])
                    </div>
                </section>
            </div>

            <div id="emp-show-panel-employment" class="emp-show-pane" role="tabpanel" aria-labelledby="emp-show-tab-employment" aria-hidden="true">
                <p class="emp-show-pane-head">Employment</p>
                <section class="emp-show__block" aria-label="{{ __('Employment') }}">
                    @php
                        $jobTitleSelect = $hrEditJobTitles->mapWithKeys(fn ($j) => [$j->id => $j->name])->all();
                        $deptSelect = $hrEditDepartments->mapWithKeys(fn ($d) => [$d->id => $d->name])->all();
                        $employmentSelect = collect(\Modules\HRManagement\Models\Employee::EMPLOYMENT_TYPES)->mapWithKeys(fn ($t) => [$t => $employmentTypeLabels[$t] ?? $t])->all();
                        $dojVal = $employee->date_of_joining?->format('Y-m-d');
                    @endphp
                    <div class="emp-show__rows">
                        @include('hrmanagement::employees.partials.editable-field-row', ['label' => __('Employee ID'), 'field' => 'employee_id', 'panel' => 'employment', 'type' => 'text', 'value' => $employee->employee_id])
                        @include('hrmanagement::employees.partials.editable-field-row', ['label' => __('Job title'), 'field' => 'job_title_id', 'panel' => 'employment', 'type' => 'select', 'value' => $employee->job_title_id, 'selectOptions' => $jobTitleSelect])
                        @include('hrmanagement::employees.partials.editable-field-row', ['label' => __('Department'), 'field' => 'department_id', 'panel' => 'employment', 'type' => 'select', 'value' => $employee->department_id, 'selectOptions' => $deptSelect])
                        @include('hrmanagement::employees.partials.editable-field-row', ['label' => __('Date of joining'), 'field' => 'date_of_joining', 'panel' => 'employment', 'type' => 'date', 'value' => $dojVal])
                        @include('hrmanagement::employees.partials.editable-field-row', ['label' => __('Employment type'), 'field' => 'employment_type', 'panel' => 'employment', 'type' => 'select', 'value' => $employee->employment_type, 'selectOptions' => $employmentSelect, 'displayValue' => $employee->employmentTypeLabel()])
                    </div>
                </section>
            </div>

            <div id="emp-show-panel-emergency" class="emp-show-pane" role="tabpanel" aria-labelledby="emp-show-tab-emergency" aria-hidden="true">
                <p class="emp-show-pane-head">Emergency contact</p>
                <section class="emp-show__block" aria-label="{{ __('Emergency contact') }}">
                    <div class="emp-show__rows">
                        @include('hrmanagement::employees.partials.editable-field-row', ['label' => __('Name'), 'field' => 'emergency_contact_name', 'panel' => 'emergency', 'type' => 'text', 'value' => $employee->emergency_contact_name])
                        @include('hrmanagement::employees.partials.editable-field-row', ['label' => __('Relationship'), 'field' => 'emergency_contact_relationship', 'panel' => 'emergency', 'type' => 'text', 'value' => $employee->emergency_contact_relationship])
                        @include('hrmanagement::employees.partials.editable-field-row', ['label' => __('Phone'), 'field' => 'emergency_contact_phone', 'panel' => 'emergency', 'type' => 'tel', 'value' => $employee->emergency_contact_phone, 'fullWidth' => true])
                    </div>
                </section>
            </div>

            <div id="emp-show-panel-bank" class="emp-show-pane" role="tabpanel" aria-labelledby="emp-show-tab-bank" aria-hidden="true">
                <p class="emp-show-pane-head">Bank account</p>
                <section class="emp-show__block" aria-label="{{ __('Bank account') }}">
                    @php
                        $bankSelect = $banks->mapWithKeys(fn ($b) => [$b->id => $b->name])->all();
                    @endphp
                    <div class="emp-show__rows">
                        @include('hrmanagement::employees.partials.editable-field-row', ['label' => __('Account holder'), 'field' => 'bank_account_holder_name', 'panel' => 'bank', 'type' => 'text', 'value' => $employee->bank_account_holder_name, 'fullWidth' => true])
                        @include('hrmanagement::employees.partials.editable-field-row', ['label' => __('Bank'), 'field' => 'bank_id', 'panel' => 'bank', 'type' => 'select', 'value' => $employee->bank_id, 'selectOptions' => $bankSelect])
                        @include('hrmanagement::employees.partials.editable-field-row', ['label' => __('Branch'), 'field' => 'bank_branch', 'panel' => 'bank', 'type' => 'text', 'value' => $employee->bank_branch])
                        @include('hrmanagement::employees.partials.editable-field-row', ['label' => __('Account number'), 'field' => 'bank_account_number', 'panel' => 'bank', 'type' => 'text', 'value' => $employee->bank_account_number, 'fullWidth' => true])
                    </div>
                </section>
            </div>

            <div id="emp-show-panel-statutory" class="emp-show-pane" role="tabpanel" aria-labelledby="emp-show-tab-statutory" aria-hidden="true">
                <p class="emp-show-pane-head">Statutory references</p>
                <section class="emp-show__block" aria-label="{{ __('Statutory references') }}">
                    <div class="emp-show__rows">
                        @include('hrmanagement::employees.partials.editable-field-row', ['label' => __('EPF number'), 'field' => 'epf_number', 'panel' => 'statutory', 'type' => 'text', 'value' => $employee->epf_number, 'displayValue' => filled($employee->epf_number) ? $employee->epf_number : '—', 'required' => false])
                        @include('hrmanagement::employees.partials.editable-field-row', ['label' => __('ETF number'), 'field' => 'etf_number', 'panel' => 'statutory', 'type' => 'text', 'value' => $employee->etf_number, 'displayValue' => filled($employee->etf_number) ? $employee->etf_number : '—', 'required' => false])
                        @include('hrmanagement::employees.partials.editable-field-row', ['label' => __('Tax TIN'), 'field' => 'tax_tin', 'panel' => 'statutory', 'type' => 'text', 'value' => $employee->tax_tin, 'displayValue' => filled($employee->tax_tin) ? $employee->tax_tin : '—', 'fullWidth' => true, 'required' => false])
                    </div>
                </section>
            </div>

            <div id="emp-show-panel-documents" class="emp-show-pane" role="tabpanel" aria-labelledby="emp-show-tab-documents" aria-hidden="true">
                <p class="emp-show-pane-head">{{ __('Documents') }}</p>
                <p class="emp-show-prose">{{ __('Upload files by category (certifications, personal files, applications, contracts, etc.). Original file names are kept on record; files are stored securely for this business.') }}</p>

                @php
                    $documentCategories ??= \Modules\HRManagement\Models\EmployeeDocument::CATEGORIES;
                @endphp

                @if($errors->has('document_category') || $errors->has('document_file'))
                    <div class="emp-show__err" role="alert">
                        <ul style="margin:0;padding-left:18px;">
                            @foreach($errors->get('document_category', []) as $msg)<li>{{ $msg }}</li>@endforeach
                            @foreach($errors->get('document_file', []) as $msg)<li>{{ $msg }}</li>@endforeach
                        </ul>
                    </div>
                @endif

                <section class="emp-docs-upload-card" aria-label="{{ __('Add a document') }}">
                    <form
                        method="post"
                        action="{{ route('hr.employees.documents.store', $employee) }}"
                        enctype="multipart/form-data"
                        class="emp-docs-form"
                        id="emp-docs-upload-form"
                        aria-label="{{ __('Upload document') }}"
                    >
                        @csrf
                        <div class="emp-docs-upload-grid">
                            <div class="emp-docs-upload-field">
                                <label for="emp-document-category">{{ __('Document type') }}</label>
                                <select name="document_category" id="emp-document-category" class="emp-docs-form__select" required>
                                    <option value="">{{ __('Choose category…') }}</option>
                                    @foreach($documentCategories as $cat)
                                        <option value="{{ $cat }}" @selected(old('document_category') === $cat)>{{ \Modules\HRManagement\Models\EmployeeDocument::categoryLabel($cat) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="emp-docs-upload-field emp-docs-upload-field--span">
                                <label for="emp-document-file">{{ __('File') }}</label>
                                @php $docMaxBytes = 15360 * 1024; @endphp
                                <div
                                    class="emp-dropzone emp-docs-dropzone"
                                    data-emp-dropzone
                                    data-emp-dropzone-input="emp-document-file"
                                    data-emp-dropzone-clear-id="emp-document-clear"
                                    data-emp-dropzone-max-bytes="{{ $docMaxBytes }}"
                                    data-emp-dropzone-max-msg="{{ __('Each file must be 15 MB or smaller.') }}"
                                >
                                    <input
                                        type="file"
                                        name="document_file"
                                        id="emp-document-file"
                                        class="emp-dropzone__input"
                                        required
                                        accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif,.webp,.txt,.rtf,.xlsx,.xls,.ppt,.pptx,.csv,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                                    >
                                    <span class="emp-docs-dropzone__icon" aria-hidden="true"><i class="fa fa-file-circle-plus"></i></span>
                                    <span class="emp-dropzone__label"><i class="fa fa-cloud-arrow-up" aria-hidden="true"></i>{{ __('Drop a file here') }}</span>
                                    <span class="emp-dropzone__or">{{ __('or tap to browse from your device') }}</span>
                                    <p class="emp-docs-dropzone__hint">{{ __('PDF, Word, Excel, PowerPoint, CSV, RTF, plain text, and images — up to :size.', ['size' => '15 MB']) }}</p>
                                    <span class="emp-dropzone__filename" id="emp-document-filename" aria-live="polite"></span>
                                    <span class="emp-dropzone__meta" id="emp-document-filemeta" aria-live="polite"></span>
                                    <p class="emp-dropzone__err" id="emp-document-file-err" role="alert" hidden></p>
                                </div>
                                <div class="emp-docs-upload-actions">
                                    <button type="submit" class="emp-show__btn-upload emp-show__btn-upload--docs">{{ __('Upload document') }}</button>
                                    <button type="button" class="emp-docs-clear-btn" id="emp-document-clear" hidden>{{ __('Clear file') }}</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </section>

                @if($employee->documents->isEmpty())
                    <div class="emp-show-empty">{{ __('No documents uploaded yet.') }}</div>
                @else
                    <div class="emp-docs-table-wrap">
                        <table class="emp-docs-table">
                            <thead>
                                <tr>
                                    <th>{{ __('Category') }}</th>
                                    <th>{{ __('File name') }}</th>
                                    <th>{{ __('Uploaded') }}</th>
                                    <th>{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($employee->documents as $doc)
                                    <tr>
                                        <td>{{ \Modules\HRManagement\Models\EmployeeDocument::categoryLabel($doc->category) }}</td>
                                        <td><span class="emp-docs-table__fname">{{ $doc->original_filename }}</span>@if($doc->mime_type)<br><span class="emp-docs-table__meta">{{ $doc->mime_type }}</span>@endif</td>
                                        <td><span class="emp-docs-table__meta">{{ $doc->created_at?->format('Y-m-d H:i') ?? '—' }}</span></td>
                                        <td>
                                            <div class="emp-docs-table__acts">
                                                <a href="{{ route('hr.employees.documents.download', [$employee, $doc]) }}" class="emp-docs-action">{{ __('Download') }}</a>
                                                <form method="post" action="{{ route('hr.employees.documents.destroy', [$employee, $doc]) }}" onsubmit="return confirm(@json(__('Delete this document?')))">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="emp-docs-action emp-docs-action--danger">{{ __('Delete') }}</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div id="emp-show-panel-certifications" class="emp-show-pane" role="tabpanel" aria-labelledby="emp-show-tab-certifications" aria-hidden="true">
                <p class="emp-show-pane-head">{{ __('Career certifications') }}</p>
                <p class="emp-show-prose">{{ __('Professional licences, vocational certificates, exams, renewals—and expiry reminders when we add workflows.') }}</p>
                <div class="emp-show-empty">{{ __('No career certifications recorded yet.') }}</div>
            </div>

            <div id="emp-show-panel-salary" class="emp-show-pane" role="tabpanel" aria-labelledby="emp-show-tab-salary" aria-hidden="true">
                <p class="emp-show-pane-head">{{ __('Salary') }}</p>
                @if ($employee->salary !== null)
                    @php
                        $basicDisp = $employee->basic_salary !== null
                            ? (($bizCurrency !== '' ? $bizCurrency.' ' : '').number_format((float) $employee->basic_salary, abs((float) $employee->basic_salary - round((float) $employee->basic_salary)) < 0.0001 ? 0 : 2))
                            : '—';
                    @endphp
                    <section class="emp-show__block" aria-label="{{ __('Compensation on file') }}">
                        <div class="emp-show__rows">
                            @include('hrmanagement::employees.partials.editable-field-row', [
                                'label' => __('Basic salary'),
                                'field' => 'basic_salary',
                                'panel' => 'salary',
                                'type' => 'number',
                                'numberStep' => '0.01',
                                'value' => $employee->basic_salary,
                                'displayValue' => $basicDisp,
                            ])
                            <div class="emp-show__row emp-show__row--full">
                                <span class="emp-show__dt">{{ __('Monthly gross') }}</span>
                                <p class="emp-show__dd">
                                    <strong>{{ $bizCurrency !== '' ? $bizCurrency.' ' : '' }}{{ number_format((float) $employee->salary, abs((float) $employee->salary - round((float) $employee->salary)) < 0.0001 ? 0 : 2) }}</strong>
                                </p>
                                <p class="muted" style="margin:8px 0 0;font-size:12px;line-height:1.45;">{{ __('Updates automatically when you change basic salary or allowances.') }}</p>
                            </div>
                            @foreach($employee->employeeAllowances as $ea)
                                @include('hrmanagement::employees.partials.editable-allowance-row', ['ea' => $ea, 'employee' => $employee, 'bizCurrency' => $bizCurrency])
                            @endforeach
                        </div>
                    </section>
                    <p class="emp-show-prose" style="margin-top:14px;">{{ __('Payslips and payroll runs will use this record when Payroll is connected.') }}</p>
                @else
                    <p class="emp-show-prose">{{ __('No monthly gross recorded for this person yet. New hires capture basic salary and allowances on create; editing will arrive with Payroll.') }}</p>
                @endif
                <section class="emp-show__block" aria-label="{{ __('Payslips') }}" style="margin-top:14px;">
                    <p class="emp-show-pane-head" style="margin-bottom:10px;">{{ __('Payslips') }}</p>
                    @if($employeePayslips->isEmpty())
                        <div class="emp-show-empty">{{ __('No payslips available yet for this employee.') }}</div>
                    @else
                        <div class="emp-payslip-list">
                            @foreach($employeePayslips as $payrollItem)
                                @php
                                    $cycle = $payrollItem->cycle;
                                    $periodLabel = $cycle ? str_pad((string) $cycle->month, 2, '0', STR_PAD_LEFT).'/'.$cycle->year : __('Unknown period');
                                @endphp
                                <article class="emp-payslip-card">
                                    <div class="emp-payslip-card__row">
                                        <div>
                                            <p class="emp-payslip-card__period">{{ __('Payroll period: :period', ['period' => $periodLabel]) }}</p>
                                            <p class="emp-payslip-card__meta">
                                                {{ __('Cycle') }}: {{ $cycle->name ?? '—' }}
                                                <span> · </span>{{ __('Net pay') }}: {{ $bizCurrency !== '' ? $bizCurrency.' ' : '' }}{{ number_format((float) ($payrollItem->net_pay ?? 0), 2) }}
                                                <span> · </span>{{ __('Status') }}: {{ ucfirst((string) ($payrollItem->status ?? '—')) }}
                                            </p>
                                        </div>
                                        @if($cycle)
                                            <div class="emp-payslip-card__actions">
                                                <a class="emp-payslip-card__btn" href="{{ route('hr.payroll.cycles.items.payslip', ['cycle' => $cycle, 'item' => $payrollItem]) }}">
                                                    <i class="fa fa-eye" aria-hidden="true"></i>{{ __('View payslip') }}
                                                </a>
                                                <a class="emp-payslip-card__btn" href="{{ route('hr.payroll.cycles.items.payslip.download', ['cycle' => $cycle, 'item' => $payrollItem]) }}">
                                                    <i class="fa fa-download" aria-hidden="true"></i>{{ __('Download') }}
                                                </a>
                                            </div>
                                        @endif
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </section>
                @if ($hasDeptSalaryGuide && $empDept)
                    <section class="emp-show__block" aria-label="{{ __('Department salary guide') }}">
                        <div class="emp-show__rows">
                            <div class="emp-show__row emp-show__row--full">
                                <span class="emp-show__dt">{{ __('Department salary guide') }}</span>
                                <p class="emp-show__dd">
                                    <strong>{{ $empDept->name }}</strong>
                                    @if ($deptSalMin !== null && $deptSalMax !== null)
                                        — {{ $bizCurrency !== '' ? $bizCurrency.' ' : '' }}{{ number_format($deptSalMin, abs($deptSalMin - round($deptSalMin)) < 0.0001 ? 0 : 2) }} – {{ number_format($deptSalMax, abs($deptSalMax - round($deptSalMax)) < 0.0001 ? 0 : 2) }}
                                    @elseif ($deptSalMin !== null)
                                        — {{ __('from') }} {{ $bizCurrency !== '' ? $bizCurrency.' ' : '' }}{{ number_format($deptSalMin, abs($deptSalMin - round($deptSalMin)) < 0.0001 ? 0 : 2) }}
                                    @elseif ($deptSalMax !== null)
                                        — {{ __('up to') }} {{ $bizCurrency !== '' ? $bizCurrency.' ' : '' }}{{ number_format($deptSalMax, abs($deptSalMax - round($deptSalMax)) < 0.0001 ? 0 : 2) }}
                                    @endif
                                </p>
                                <p class="muted" style="margin:8px 0 0;font-size:12px;line-height:1.45;">{{ __('Indicative range from the Departments catalogue—not this person\'s actual compensation.') }}</p>
                            </div>
                        </div>
                    </section>
                @else
                    <div class="emp-show-empty">
                        @if ($empDept)
                            {{ __('This department does not have a salary band in the catalogue yet.') }}
                            @if (Route::has('hr.departments.show'))
                                <span style="display:block;margin-top:12px;"><a href="{{ route('hr.departments.show', $empDept) }}" class="linkbtn" style="padding:8px 14px;font-size:13px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;"><i class="fa fa-folder-tree"></i>{{ __('Department settings') }}</a></span>
                            @endif
                        @else
                            {{ __('Assign a department on the Employment tab so a departmental salary guide can appear here.') }}
                        @endif
                    </div>
                @endif
                <p style="margin:14px 0 0;">
                    @if(Route::has('hr.payroll.index'))
                        <a href="{{ route('hr.payroll.index') }}" class="linkbtn" style="padding:8px 14px;font-size:13px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;"><i class="fa fa-money-check-dollar"></i>{{ __('Payroll hub') }}</a>
                    @endif
                    @if(Route::has('hr.departments.index'))
                        <a href="{{ route('hr.departments.index') }}" class="linkbtn" style="padding:8px 14px;font-size:13px;margin-inline-start:8px;background:color-mix(in srgb,var(--card)94%,transparent);border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;"><i class="fa fa-folder-tree"></i>{{ __('Departments') }}</a>
                    @endif
                </p>
            </div>

            <div id="emp-show-panel-leave" class="emp-show-pane" role="tabpanel" aria-labelledby="emp-show-tab-leave" aria-hidden="true">
                <p class="emp-show-pane-head">{{ __('Leave requests') }}</p>
                <p class="emp-show-prose">{{ __('Log time off for this person. Pending requests can be approved or rejected here or from the HR hub inbox.') }}</p>

                @php
                    $leaveTypeLabels = [
                        'annual' => __('Annual'),
                        'casual' => __('Casual'),
                        'sick' => __('Sick'),
                        'unpaid' => __('Unpaid'),
                        'other' => __('Other'),
                    ];
                    $leaveStatusLabels = [
                        \Modules\HRManagement\Models\LeaveRequest::STATUS_PENDING => __('Pending'),
                        \Modules\HRManagement\Models\LeaveRequest::STATUS_APPROVED => __('Approved'),
                        \Modules\HRManagement\Models\LeaveRequest::STATUS_REJECTED => __('Rejected'),
                    ];
                    $leaveBalanceSummary = $leaveBalanceSummary ?? null;
                    $leaveLetterPhrases = [
                        'previewHeading' => __('Letter preview'),
                        'draftNote' => __('Draft preview — updates automatically as you fill the form.'),
                        'toLabel' => __('To'),
                        'hrLine' => __('Human Resources / Management'),
                        'dateLabel' => __('Date'),
                        'subjectLabel' => __('Subject'),
                        'subjectPrefix' => __('Leave application'),
                        'salutation' => __('Dear Sir/Madam,'),
                        'body' => __('I, :name (Employee ID :id), respectfully request :leave_type leave for the period :start through :end (:days calendar days, inclusive).'),
                        'noteLead' => __('Additional details:'),
                        'closing' => __('Thank you for your consideration.'),
                        'signOff' => __('Yours sincerely,'),
                        'placeholderIncomplete' => __('Complete the form beside this preview to generate the letter.'),
                    ];
                    $leaveLetterMeta = [
                        'employeeName' => $employee->full_name,
                        'employeeId' => (string) $employee->employee_id,
                        'businessName' => $business->name,
                    ];
                @endphp
                <script type="application/json" id="emp-leave-letter-bundle">@json(['phrases' => $leaveLetterPhrases, 'leaveTypes' => $leaveTypeLabels, 'meta' => $leaveLetterMeta])</script>

                @if($leaveBalanceSummary && isset($leaveBalanceSummary['year'], $leaveBalanceSummary['types']))
                    <section class="emp-leave-summary" aria-labelledby="emp-leave-balance-heading" style="margin-bottom:clamp(14px,2.2vmin,20px);">
                        <p id="emp-leave-balance-heading" class="emp-leave-card__head" style="margin-bottom:6px;">{{ __('Balances · calendar :year', ['year' => $leaveBalanceSummary['year']]) }}</p>
                        <p class="muted" style="margin:0 0 12px;font-size:12px;line-height:1.45;max-width:78ch;">
                            {{ __('Days overlap this calendar year. Policy caps come from Business settings → HR. Pending requests reduce available balances until rejected.') }}
                        </p>
                        <div class="emp-ov-cards" role="region" aria-label="{{ __('Leave balances') }}">
                            @foreach(\Modules\HRManagement\Models\LeaveRequest::LEAVE_TYPES as $ltBal)
                                @php
                                    $b = $leaveBalanceSummary['types'][$ltBal] ?? [];
                                    $accent = $b['accent'] ?? 'var(--primary)';
                                @endphp
                                <article class="emp-ov-card" style="--ov-accent: {{ $accent }};">
                                    <span class="emp-ov-card__rail" aria-hidden="true"></span>
                                    <div class="emp-ov-card__body">
                                        <p class="emp-ov-card__title">{{ $leaveTypeLabels[$ltBal] ?? $ltBal }}</p>
                                        <div class="emp-ov-card__metric">
                                            @if(($b['entitlement'] ?? null) !== null)
                                                <span class="emp-ov-card__value">{{ $b['remaining'] ?? '—' }}</span><span class="emp-ov-card__suffix">{{ __('days available') }}</span>
                                            @else
                                                <span class="emp-ov-card__value">{{ __('—') }}</span><span class="emp-ov-card__suffix">{{ __('no capped allocation') }}</span>
                                            @endif
                                        </div>
                                        @php
                                            $entLbl = (($b['entitlement'] ?? null) !== null) ? (string) $b['entitlement'] : '—';
                                        @endphp
                                        <div class="emp-ov-card__footer">
                                            <p class="emp-ov-card__hint">{{ __('Approved: :a · Pending: :p · Year allocation: :e', ['a' => (int) ($b['approved_days'] ?? 0), 'p' => (int) ($b['pending_days'] ?? 0), 'e' => $entLbl]) }}</p>
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endif

                <div class="emp-leave-toolbar">
                    <button type="button" class="emp-leave-add-btn" id="emp-leave-open-modal">
                        <i class="fa fa-plus" aria-hidden="true"></i>{{ __('Add leave request') }}
                    </button>
                </div>

                @php
                    $hasLeaveRequestErrors = $errors->has('leave_type')
                        || $errors->has('leave_starts_on')
                        || $errors->has('leave_ends_on')
                        || $errors->has('leave_note');
                @endphp

                <div class="emp-leave-modal @if($hasLeaveRequestErrors) is-open @endif" id="emp-leave-modal" aria-hidden="{{ $hasLeaveRequestErrors ? 'false' : 'true' }}">
                    <button type="button" class="emp-leave-modal__backdrop" id="emp-leave-close-backdrop" aria-label="{{ __('Close') }}"></button>
                    <section class="emp-leave-modal__dialog" aria-label="{{ __('New leave request') }}" role="dialog" aria-modal="true" aria-labelledby="emp-leave-modal-title">
                        <div class="emp-leave-modal__head">
                            <h3 class="emp-leave-modal__title" id="emp-leave-modal-title">{{ __('New leave request') }}</h3>
                            <button type="button" class="emp-leave-modal__close" id="emp-leave-close-modal" aria-label="{{ __('Close') }}">
                                <i class="fa fa-xmark" aria-hidden="true"></i>
                            </button>
                        </div>

                        @if($hasLeaveRequestErrors)
                            <div class="emp-show__err" role="alert" style="margin-bottom:12px;">
                                <ul style="margin:0;padding-left:18px;">
                                    @foreach($errors->get('leave_type', []) as $msg)<li>{{ $msg }}</li>@endforeach
                                    @foreach($errors->get('leave_starts_on', []) as $msg)<li>{{ $msg }}</li>@endforeach
                                    @foreach($errors->get('leave_ends_on', []) as $msg)<li>{{ $msg }}</li>@endforeach
                                    @foreach($errors->get('leave_note', []) as $msg)<li>{{ $msg }}</li>@endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="emp-leave-modal__split">
                            <div class="emp-leave-preview-col">
                                <p class="emp-leave-preview__tag" id="emp-leave-preview-heading">{{ $leaveLetterPhrases['previewHeading'] }}</p>
                                <p class="emp-leave-preview__watermark" id="emp-leave-preview-draft">{{ $leaveLetterPhrases['draftNote'] }}</p>
                                <div class="emp-leave-preview__sheet" aria-live="polite">
                                    <p class="emp-leave-preview__muted">
                                        <span id="leave-prev-to-label"></span><br>
                                        <strong id="leave-prev-company"></strong><br>
                                        <span id="leave-prev-hr"></span>
                                    </p>
                                    <p class="emp-leave-preview__muted" id="leave-prev-date-line"></p>
                                    <p class="emp-leave-preview__subject" id="leave-prev-subject"></p>
                                    <p id="leave-prev-salutation"></p>
                                    <p id="leave-prev-body"></p>
                                    <p class="emp-leave-preview__note" id="leave-prev-note-block" hidden></p>
                                    <p id="leave-prev-closing"></p>
                                    <p id="leave-prev-signoff"></p>
                                    <p class="emp-leave-preview__sigblock"><strong id="leave-prev-signatory"></strong></p>
                                </div>
                            </div>
                            <div class="emp-leave-modal__form-col">
                                <p class="emp-leave-modal__form-head">{{ __('Request details') }}</p>
                                <p class="emp-leave-modal__form-lead">{{ __('Choose leave type and dates. The letter preview on the left updates automatically.') }}</p>
                                <form method="post" action="{{ route('hr.employees.leave-requests.store', $employee) }}" class="emp-docs-form" id="emp-leave-request-form">
                                    @csrf
                                    <div class="emp-leave-grid emp-leave-grid--modal">
                                        <div class="emp-leave-field emp-leave-field--type">
                                            <label for="emp-leave-type">{{ __('Leave type') }}</label>
                                            <select name="leave_type" id="emp-leave-type" class="emp-docs-form__select" required>
                                                <option value="">{{ __('Choose…') }}</option>
                                                @foreach(\Modules\HRManagement\Models\LeaveRequest::LEAVE_TYPES as $lt)
                                                    <option value="{{ $lt }}" @selected(old('leave_type') === $lt)>{{ $leaveTypeLabels[$lt] ?? $lt }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="emp-leave-field">
                                            <label for="emp-leave-start">{{ __('From') }}</label>
                                            <input type="date" name="leave_starts_on" id="emp-leave-start" class="emp-docs-form__select" value="{{ old('leave_starts_on') }}" required>
                                        </div>
                                        <div class="emp-leave-field">
                                            <label for="emp-leave-end">{{ __('To') }}</label>
                                            <input type="date" name="leave_ends_on" id="emp-leave-end" class="emp-docs-form__select" value="{{ old('leave_ends_on') }}" required>
                                        </div>
                                        <div class="emp-leave-field emp-leave-field--note">
                                            <label for="emp-leave-note">{{ __('Note') }} <span class="muted">({{ __('optional') }})</span></label>
                                            <textarea name="leave_note" id="emp-leave-note" rows="4" maxlength="2000" placeholder="{{ __('Reason or context') }}">{{ old('leave_note') }}</textarea>
                                        </div>
                                    </div>
                                    <div class="emp-leave-actions">
                                        <button type="submit" class="emp-leave-submit">{{ __('Submit leave request') }}</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </section>
                </div>

                @if($employee->leaveRequests->isEmpty())
                    <div class="emp-show-empty">{{ __('No leave requests recorded yet.') }}</div>
                @else
                    <div class="emp-docs-table-wrap emp-leave-table-wrap">
                        <table class="emp-docs-table">
                            <thead>
                                <tr>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Type') }}</th>
                                    <th>{{ __('From') }}</th>
                                    <th>{{ __('To') }}</th>
                                    <th>{{ __('Note') }}</th>
                                    <th>{{ __('Logged') }}</th>
                                    <th>{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($employee->leaveRequests as $lr)
                                    <tr id="hr-leave-request-{{ $lr->id }}">
                                        <td>
                                            @php
                                                $pill = match ($lr->status) {
                                                    \Modules\HRManagement\Models\LeaveRequest::STATUS_PENDING => 'emp-docs-pill--pending',
                                                    \Modules\HRManagement\Models\LeaveRequest::STATUS_APPROVED => 'emp-docs-pill--approved',
                                                    default => 'emp-docs-pill--rejected',
                                                };
                                            @endphp
                                            <span class="emp-docs-pill {{ $pill }}">{{ $leaveStatusLabels[$lr->status] ?? $lr->status }}</span>
                                        </td>
                                        <td>{{ $leaveTypeLabels[$lr->leave_type] ?? $lr->leave_type }}</td>
                                        <td><span class="emp-docs-table__meta">{{ $lr->starts_on?->format('Y-m-d') ?? '—' }}</span></td>
                                        <td><span class="emp-docs-table__meta">{{ $lr->ends_on?->format('Y-m-d') ?? '—' }}</span></td>
                                        <td><span class="emp-docs-table__fname">{{ filled($lr->note) ? \Illuminate\Support\Str::limit($lr->note, 80) : '—' }}</span></td>
                                        <td><span class="emp-docs-table__meta">{{ $lr->created_at?->format('Y-m-d H:i') ?? '—' }}</span></td>
                                        <td>
                                            @if($lr->isPending())
                                                <div class="emp-docs-table__acts">
                                                    <form method="post" action="{{ route('hr.leave-requests.update', $lr) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" name="leave_status" value="{{ \Modules\HRManagement\Models\LeaveRequest::STATUS_APPROVED }}" class="emp-leave-act emp-leave-act--ok">{{ __('Approve') }}</button>
                                                        <button type="submit" name="leave_status" value="{{ \Modules\HRManagement\Models\LeaveRequest::STATUS_REJECTED }}" class="emp-leave-act emp-leave-act--no">{{ __('Reject') }}</button>
                                                    </form>
                                                </div>
                                            @else
                                                <span class="emp-docs-table__meta">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <nav class="emp-show-tabs" role="tablist" aria-label="{{ __('Employee record sections') }}">
            <button type="button" class="emp-show-tab is-active" id="emp-show-tab-overview" role="tab" aria-selected="true" aria-controls="emp-show-panel-overview" data-emp-show-tab="overview">
                <i class="fa fa-gauge-high" aria-hidden="true"></i>{{ __('Overview') }}
            </button>
            <button type="button" class="emp-show-tab" id="emp-show-tab-personal" role="tab" aria-selected="false" aria-controls="emp-show-panel-personal" data-emp-show-tab="personal">
                <i class="fa fa-user" aria-hidden="true"></i>Personal
            </button>
            <button type="button" class="emp-show-tab" id="emp-show-tab-employment" role="tab" aria-selected="false" aria-controls="emp-show-panel-employment" data-emp-show-tab="employment">
                <i class="fa fa-briefcase" aria-hidden="true"></i>Employment
            </button>
            <button type="button" class="emp-show-tab" id="emp-show-tab-emergency" role="tab" aria-selected="false" aria-controls="emp-show-panel-emergency" data-emp-show-tab="emergency">
                <i class="fa fa-phone-volume" aria-hidden="true"></i>Emergency
            </button>
            <button type="button" class="emp-show-tab" id="emp-show-tab-bank" role="tab" aria-selected="false" aria-controls="emp-show-panel-bank" data-emp-show-tab="bank">
                <i class="fa fa-building-columns" aria-hidden="true"></i>Banking
            </button>
            <button type="button" class="emp-show-tab" id="emp-show-tab-statutory" role="tab" aria-selected="false" aria-controls="emp-show-panel-statutory" data-emp-show-tab="statutory">
                <i class="fa fa-file-lines" aria-hidden="true"></i>Statutory
            </button>
            <button type="button" class="emp-show-tab" id="emp-show-tab-documents" role="tab" aria-selected="false" aria-controls="emp-show-panel-documents" data-emp-show-tab="documents">
                <i class="fa fa-folder-open" aria-hidden="true"></i>{{ __('Documents') }}
            </button>
            <button type="button" class="emp-show-tab" id="emp-show-tab-certifications" role="tab" aria-selected="false" aria-controls="emp-show-panel-certifications" data-emp-show-tab="certifications">
                <i class="fa fa-award" aria-hidden="true"></i>{{ __('Certifications') }}
            </button>
            <button type="button" class="emp-show-tab" id="emp-show-tab-salary" role="tab" aria-selected="false" aria-controls="emp-show-panel-salary" data-emp-show-tab="salary">
                <i class="fa fa-money-bill-wave" aria-hidden="true"></i>{{ __('Salary') }}
            </button>
            <button type="button" class="emp-show-tab" id="emp-show-tab-leave" role="tab" aria-selected="false" aria-controls="emp-show-panel-leave" data-emp-show-tab="leave">
                <i class="fa fa-calendar-days" aria-hidden="true"></i>{{ __('Leave') }}
            </button>
        </nav>
    </div>
</div>

<script>
(function () {
    var tabs = document.querySelectorAll('[data-emp-show-tab]');
    var panes = {
        overview: document.getElementById('emp-show-panel-overview'),
        personal: document.getElementById('emp-show-panel-personal'),
        employment: document.getElementById('emp-show-panel-employment'),
        emergency: document.getElementById('emp-show-panel-emergency'),
        bank: document.getElementById('emp-show-panel-bank'),
        statutory: document.getElementById('emp-show-panel-statutory'),
        documents: document.getElementById('emp-show-panel-documents'),
        certifications: document.getElementById('emp-show-panel-certifications'),
        salary: document.getElementById('emp-show-panel-salary'),
        leave: document.getElementById('emp-show-panel-leave'),
    };
    var VALID_TABS = Object.keys(panes);

    function syncEmpShowHash(key) {
        var path = window.location.pathname || '';
        var search = window.location.search || '';
        var url = path + search + '#' + key;
        try {
            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, '', url);
            } else {
                window.location.hash = key;
            }
        } catch (e) {}
    }

    function show(key, opts) {
        opts = opts || {};
        if (VALID_TABS.indexOf(key) === -1) {
            key = 'overview';
        }
        Object.keys(panes).forEach(function (k) {
            var p = panes[k];
            if (!p) return;
            var on = k === key;
            p.classList.toggle('is-active', on);
            p.setAttribute('aria-hidden', on ? 'false' : 'true');
        });
        tabs.forEach(function (btn) {
            var on = btn.getAttribute('data-emp-show-tab') === key;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        if (!opts.skipHash) {
            syncEmpShowHash(key);
        }
    }

    function applyEmpShowHash() {
        var h = (window.location.hash || '').replace(/^#/, '').toLowerCase();
        if (!h || VALID_TABS.indexOf(h) === -1) {
            return;
        }
        if (typeof window.__empShowGo !== 'function') {
            return;
        }
        window.__empShowGo(h, { skipHash: true });
    }

    tabs.forEach(function (btn) {
        btn.addEventListener('click', function () {
            show(btn.getAttribute('data-emp-show-tab'));
        });
    });
    window.__empShowGo = show;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyEmpShowHash);
    } else {
        applyEmpShowHash();
    }
    window.addEventListener('hashchange', function () {
        applyEmpShowHash();
    });
})();
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-emp-field-edit]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var row = btn.closest('.emp-show__row--editable');
            if (!row) return;
            row.classList.add('is-editing');
            var view = row.querySelector('.emp-show__view');
            var form = row.querySelector('.emp-show__edit-form');
            if (view) view.setAttribute('hidden', 'hidden');
            if (form) form.removeAttribute('hidden');
            btn.setAttribute('hidden', 'hidden');
        });
    });
    document.querySelectorAll('[data-emp-field-cancel]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var row = btn.closest('.emp-show__row--editable');
            if (!row) return;
            row.classList.remove('is-editing');
            var view = row.querySelector('.emp-show__view');
            var form = row.querySelector('.emp-show__edit-form');
            var editBtn = row.querySelector('[data-emp-field-edit]');
            if (view) view.removeAttribute('hidden');
            if (form) {
                form.setAttribute('hidden', 'hidden');
                if (typeof form.reset === 'function') form.reset();
            }
            if (editBtn) editBtn.removeAttribute('hidden');
        });
    });
});
</script>
@if(old('_panel'))
<script>
document.addEventListener('DOMContentLoaded', function () {
    var p = @json(old('_panel'));
    if (window.__empShowGo && p) {
        window.__empShowGo(p);
    }
});
</script>
@endif
@if($errors->has('document_category') || $errors->has('document_file'))
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.__empShowGo === 'function') {
        window.__empShowGo('documents');
    }
});
</script>
@endif
@if($errors->has('leave_type') || $errors->has('leave_starts_on') || $errors->has('leave_ends_on') || $errors->has('leave_note'))
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.__empShowGo === 'function') {
        window.__empShowGo('leave');
    }
});
</script>
@endif
<script>
document.addEventListener('DOMContentLoaded', function () {
    function formatDropzoneBytes(n) {
        if (n === undefined || n === null || !isFinite(n)) {
            return '';
        }
        var u = ['B', 'KB', 'MB', 'GB'];
        var i = 0;
        var x = n;
        while (x >= 1024 && i < u.length - 1) {
            x /= 1024;
            i++;
        }
        if (i === 0) {
            return String(Math.round(x)) + ' ' + u[i];
        }
        var decimals = x >= 10 ? 0 : 1;
        var s = x.toFixed(decimals);
        if (decimals === 1 && s.indexOf('.') !== -1) {
            s = s.replace(/\.0$/, '');
        }
        return s + ' ' + u[i];
    }

    document.querySelectorAll('[data-emp-dropzone]').forEach(function (zone) {
        var inputId = zone.getAttribute('data-emp-dropzone-input');
        var input = inputId ? document.getElementById(inputId) : zone.querySelector("input[type='file']");
        if (!input || input.type !== 'file') return;

        var filenameEl = zone.querySelector('.emp-dropzone__filename');
        var metaEl = zone.querySelector('.emp-dropzone__meta');
        var errEl = zone.querySelector('.emp-dropzone__err');
        var clearBtnId = zone.getAttribute('data-emp-dropzone-clear-id');
        var clearBtn = clearBtnId ? document.getElementById(clearBtnId) : null;
        var maxBytes = parseInt(zone.getAttribute('data-emp-dropzone-max-bytes') || '0', 10) || 0;
        var maxMsg = zone.getAttribute('data-emp-dropzone-max-msg') || '';
        var imagesOnly = zone.getAttribute('data-emp-dropzone-images-only') === '1';
        var invalidMsg = zone.getAttribute('data-emp-dropzone-invalid-msg') || '';
        var form = zone.closest('form');

        var dragDepth = 0;
        var leaveTimer = null;

        function setErr(msg) {
            if (!errEl) return;
            if (msg) {
                errEl.textContent = msg;
                errEl.hidden = false;
            } else {
                errEl.textContent = '';
                errEl.hidden = true;
            }
        }

        function syncUi() {
            var f = input.files && input.files[0];
            if (f) {
                if (filenameEl) filenameEl.textContent = f.name;
                if (metaEl) metaEl.textContent = formatDropzoneBytes(f.size);
                if (clearBtn) clearBtn.hidden = false;
                if (maxBytes > 0 && f.size > maxBytes) {
                    setErr(maxMsg);
                } else {
                    setErr('');
                }
            } else {
                if (filenameEl) filenameEl.textContent = '';
                if (metaEl) metaEl.textContent = '';
                if (clearBtn) clearBtn.hidden = true;
                setErr('');
            }
        }

        function assignFile(file) {
            if (!file) return;
            if (imagesOnly && file.type && file.type.indexOf('image/') !== 0) {
                window.alert(invalidMsg);
                return;
            }
            if (maxBytes > 0 && file.size > maxBytes) {
                try {
                    var emptyDt = new DataTransfer();
                    input.files = emptyDt.files;
                } catch (e) {
                    input.value = '';
                }
                syncUi();
                setErr(maxMsg);
                return;
            }
            try {
                var dt = new DataTransfer();
                dt.items.add(file);
                input.files = dt.files;
            } catch (err) {
                return;
            }
            syncUi();
        }

        function clearFile() {
            try {
                var emptyDt = new DataTransfer();
                input.files = emptyDt.files;
            } catch (e) {
                input.value = '';
            }
            syncUi();
        }

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                clearFile();
                input.focus();
            });
        }

        syncUi();
        input.addEventListener('change', syncUi);

        if (form && maxBytes > 0) {
            form.addEventListener('submit', function (e) {
                var f = input.files && input.files[0];
                if (f && f.size > maxBytes) {
                    e.preventDefault();
                    setErr(maxMsg);
                }
            });
        }

        zone.addEventListener('dragenter', function (e) {
            e.preventDefault();
            e.stopPropagation();
            dragDepth++;
            clearTimeout(leaveTimer);
            zone.classList.add('is-dragover');
        });
        zone.addEventListener('dragleave', function (e) {
            e.preventDefault();
            e.stopPropagation();
            dragDepth = Math.max(0, dragDepth - 1);
            if (dragDepth === 0) {
                leaveTimer = setTimeout(function () {
                    zone.classList.remove('is-dragover');
                }, 50);
            }
        });
        zone.addEventListener('dragover', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (e.dataTransfer) {
                e.dataTransfer.dropEffect = 'copy';
            }
            clearTimeout(leaveTimer);
            zone.classList.add('is-dragover');
        });
        zone.addEventListener('drop', function (e) {
            e.preventDefault();
            e.stopPropagation();
            clearTimeout(leaveTimer);
            dragDepth = 0;
            zone.classList.remove('is-dragover');
            var f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
            if (f) {
                assignFile(f);
            }
        });
    });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('emp-leave-modal');
    var openBtn = document.getElementById('emp-leave-open-modal');
    var closeBtn = document.getElementById('emp-leave-close-modal');
    var backdrop = document.getElementById('emp-leave-close-backdrop');
    var bundleEl = document.getElementById('emp-leave-letter-bundle');
    var form = document.getElementById('emp-leave-request-form');
    if (!modal || !openBtn || !closeBtn || !backdrop) {
        return;
    }

    var bundle = {};
    try {
        bundle = bundleEl && bundleEl.textContent ? JSON.parse(bundleEl.textContent) : {};
    } catch (e) {
        bundle = {};
    }
    var phrases = bundle.phrases || {};
    var leaveTypes = bundle.leaveTypes || {};
    var meta = bundle.meta || {};

    function formatLongDate(ymd) {
        if (!ymd || typeof ymd !== 'string') {
            return '';
        }
        var d = new Date(ymd + 'T12:00:00');
        if (isNaN(d.getTime())) {
            return ymd;
        }
        try {
            return d.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
        } catch (err) {
            return ymd;
        }
    }

    function inclusiveCalendarDays(startStr, endStr) {
        if (!startStr || !endStr) {
            return '';
        }
        var a = new Date(startStr + 'T12:00:00');
        var b = new Date(endStr + 'T12:00:00');
        if (isNaN(a.getTime()) || isNaN(b.getTime()) || b < a) {
            return '';
        }
        return String(Math.round((b - a) / 86400000) + 1);
    }

    function namedPlaceholders(template, map) {
        if (!template) {
            return '';
        }
        return template.replace(/:([a-zA-Z_][a-zA-Z0-9_]*)/g, function (_m, key) {
            return Object.prototype.hasOwnProperty.call(map, key) && map[key] != null ? String(map[key]) : _m;
        });
    }

    function syncLeaveLetterPreview() {
        var typeEl = document.getElementById('emp-leave-type');
        var startEl = document.getElementById('emp-leave-start');
        var endEl = document.getElementById('emp-leave-end');
        var noteEl = document.getElementById('emp-leave-note');
        var leaveType = typeEl && typeEl.value ? typeEl.value : '';
        var start = startEl && startEl.value ? startEl.value : '';
        var end = endEl && endEl.value ? endEl.value : '';
        var note = noteEl && noteEl.value ? noteEl.value.trim() : '';

        var typeLabel = leaveType && leaveTypes[leaveType] ? leaveTypes[leaveType] : '…';

        var todayStr = formatLongDate(
            new Date().getFullYear() +
                '-' +
                String(new Date().getMonth() + 1).padStart(2, '0') +
                '-' +
                String(new Date().getDate()).padStart(2, '0')
        );

        var setTxt = function (id, text) {
            var el = document.getElementById(id);
            if (el) {
                el.textContent = text || '';
            }
        };

        setTxt('leave-prev-to-label', phrases.toLabel || '');
        setTxt('leave-prev-company', meta.businessName || '');
        setTxt('leave-prev-hr', phrases.hrLine || '');
        setTxt(
            'leave-prev-date-line',
            (phrases.dateLabel || 'Date') + ': ' + todayStr
        );
        setTxt('leave-prev-salutation', phrases.salutation || '');
        setTxt('leave-prev-closing', phrases.closing || '');
        setTxt('leave-prev-signoff', phrases.signOff || '');
        setTxt(
            'leave-prev-signatory',
            (meta.employeeName || '') + (meta.employeeId ? '\n' + meta.employeeId : '')
        );

        var days = inclusiveCalendarDays(start, end);
        var startLong = formatLongDate(start);
        var endLong = formatLongDate(end);

        var subjectParts = [phrases.subjectPrefix || ''];
        if (leaveType) {
            subjectParts.push(typeLabel);
        }
        var subjectCore = subjectParts.filter(Boolean).join(' — ');
        if (startLong && endLong) {
            subjectCore += ' (' + startLong + ' – ' + endLong + ')';
        }
        setTxt(
            'leave-prev-subject',
            (phrases.subjectLabel ? phrases.subjectLabel + ': ' : '') + subjectCore
        );

        var noteBlock = document.getElementById('leave-prev-note-block');
        if (noteBlock) {
            if (note) {
                noteBlock.hidden = false;
                noteBlock.textContent = (phrases.noteLead ? phrases.noteLead + ' ' : '') + note;
            } else {
                noteBlock.hidden = true;
                noteBlock.textContent = '';
            }
        }

        if (!leaveType || !start || !end || !days) {
            setTxt('leave-prev-body', phrases.placeholderIncomplete || '');
            return;
        }

        var bodyMap = {
            name: meta.employeeName || '',
            id: meta.employeeId || '',
            leave_type: typeLabel,
            start: startLong,
            end: endLong,
            days: days,
        };
        setTxt('leave-prev-body', namedPlaceholders(phrases.body || '', bodyMap));
    }

    function openLeaveModal() {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        syncLeaveLetterPreview();
    }

    function closeLeaveModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    openBtn.addEventListener('click', openLeaveModal);
    closeBtn.addEventListener('click', closeLeaveModal);
    backdrop.addEventListener('click', closeLeaveModal);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) {
            closeLeaveModal();
        }
    });

    if (form) {
        ['change', 'input'].forEach(function (evt) {
            form.addEventListener(
                evt,
                function () {
                    if (modal.classList.contains('is-open')) {
                        syncLeaveLetterPreview();
                    }
                },
                true
            );
        });
    }

    syncLeaveLetterPreview();
    if (modal.classList.contains('is-open')) {
        document.body.style.overflow = 'hidden';
    }
});
</script>
@endsection
