@extends('theme::layouts.app', ['title' => $form->name, 'heading' => $project->name, 'minimalAppShell' => true, 'hideNavbar' => true])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.lf-topbar{display:flex;flex-wrap:wrap;align-items:center;gap:12px;position:fixed;top:0;left:0;right:0;height:64px;box-sizing:border-box;padding:0 24px;background:var(--card);border-bottom:1px solid var(--border);z-index:15;}
.lf-project-nav{position:relative;flex-shrink:0;}
.lf-project-nav__trigger{display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:9px;border:1px solid var(--border);background:var(--card);color:var(--text);font-size:13px;font-weight:700;cursor:pointer;max-width:220px;}
.lf-project-nav__trigger:hover{border-color:color-mix(in srgb,var(--primary) 40%,var(--border));}
.lf-project-nav__trigger i.fa-diagram-project{color:var(--primary);}
.lf-project-nav__name{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:140px;}
.lf-project-nav__chevron{font-size:10px;color:var(--muted);margin-left:2px;}
.lf-project-nav__menu{position:absolute;top:calc(100% + 6px);left:0;min-width:220px;background:var(--card);border:1px solid var(--border);border-radius:10px;box-shadow:0 12px 32px rgba(0,0,0,.18);padding:6px;z-index:30;}
.lf-project-nav__item{display:flex;align-items:center;gap:8px;padding:8px 10px;border-radius:7px;font-size:12.5px;font-weight:600;color:var(--text);text-decoration:none;}
.lf-project-nav__item:hover{background:color-mix(in srgb,var(--primary) 8%,var(--card));}
.lf-project-nav__item.is-active{background:color-mix(in srgb,var(--primary) 12%,var(--card));color:var(--primary);}
.lf-project-nav__item i{width:14px;text-align:center;color:var(--muted);}
.lf-project-nav__item.is-active i{color:var(--primary);}
.lf-project-nav__divider{height:1px;background:var(--border);margin:5px 2px;}
.lf-name-wrap{display:flex;flex-direction:column;gap:1px;flex:1;min-width:220px;}
.lf-name-wrap small{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);padding-left:8px;}
.lf-topbar input#lf-name-input{font-size:19px;font-weight:800;border:1px solid transparent;background:transparent;color:var(--text);padding:4px 8px;border-radius:8px;width:100%;box-sizing:border-box;}
.lf-topbar input#lf-name-input:hover{border-color:var(--border);}
.lf-topbar input#lf-name-input:focus{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:var(--card);outline:none;box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 15%,transparent);}
.lf-save-group{display:flex;align-items:center;gap:10px;flex-shrink:0;}
.lf-publish-group{display:flex;align-items:center;gap:10px;flex-shrink:0;padding:0 10px;border-left:1px solid var(--border);border-right:1px solid var(--border);}
.lf-publish-group form{display:contents;}
.lf-publish-hint{font-size:12px;color:var(--muted);white-space:nowrap;}
.lf-status{font-size:12px;font-weight:700;color:var(--muted);display:inline-flex;align-items:center;gap:5px;white-space:nowrap;}
.lf-status.is-saving{color:var(--primary);}
.lf-status.is-ok{color:#16a34a;}
.lf-status.is-error{color:#ef4444;}
#lf-save-btn{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;font-size:13px;font-weight:700;border-radius:9px;}
#lf-save-btn:disabled{opacity:.6;cursor:wait;}
.lf-subbar{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-bottom:18px;font-size:12px;color:var(--muted);}
.lf-pill-btn{display:inline-flex;align-items:center;gap:6px;padding:6px 13px;font-size:12px;font-weight:700;border-radius:999px;border:1px solid var(--border);background:var(--card);color:var(--text);cursor:pointer;}
.lf-pill-btn:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));color:var(--primary);}
.lf-link-box{display:flex;align-items:center;gap:6px;padding:5px 6px 5px 12px;border:1px solid var(--border);border-radius:999px;background:var(--card);}
.lf-link-box i.fa-link{color:var(--muted);font-size:11px;}
.lf-link-box input{border:none;background:transparent;color:var(--text);font-size:12px;width:170px;}
.lf-link-box .pcat-link{padding:5px 11px;border-radius:999px;background:color-mix(in srgb,var(--primary) 10%,transparent);font-weight:700;}
.lf-builder-shell{margin-left:220px;margin-right:380px;margin-top:64px;}
.lf-builder-shell.is-preview-hidden{margin-right:0;}
.lf-layout{display:grid;grid-template-columns:1fr;gap:18px;align-items:start;}
@media(max-width:1100px){.lf-builder-shell{margin-right:0;} .lf-preview{display:none;}}
@media(max-width:760px){
    .lf-builder-shell{margin-left:0;}
    .lf-palette{position:static;top:auto;left:auto;width:auto;height:auto;box-shadow:none;border-right:none;border-bottom:1px solid var(--border);margin-bottom:14px;}
}
.lf-preview{border:1px solid var(--border);border-left:1px solid var(--border);background:var(--card);display:flex;flex-direction:column;position:fixed;top:64px;right:0;width:380px;height:calc(100vh - 64px);overflow:hidden;box-shadow:-8px 0 24px rgba(0,0,0,.08);z-index:5;}
.lf-builder-shell.is-preview-hidden .lf-preview{display:none;}
.lf-panel-tabs{display:flex;align-items:center;gap:4px;padding:8px 10px;border-bottom:1px solid var(--border);flex-shrink:0;}
.lf-panel-tab{display:inline-flex;align-items:center;gap:6px;padding:7px 12px;font-size:12px;font-weight:700;border-radius:8px;border:1px solid transparent;background:transparent;color:var(--muted);cursor:pointer;}
.lf-panel-tab:hover{color:var(--text);background:color-mix(in srgb,var(--card) 80%,transparent);}
.lf-panel-tab.is-active{color:var(--text);background:color-mix(in srgb,var(--primary) 10%,var(--card));border-color:color-mix(in srgb,var(--primary) 30%,var(--border));}
.lf-panel-tabs__link{margin-left:auto;width:28px;height:28px;display:grid;place-items:center;border-radius:7px;color:var(--muted);flex-shrink:0;}
.lf-panel-tabs__link:hover{background:color-mix(in srgb,var(--card) 80%,transparent);color:var(--primary);}
.lf-panel-pane{flex:1;display:flex;flex-direction:column;min-height:0;}
.lf-panel-pane[hidden]{display:none!important;}
.lf-preview__frame-wrap{flex:1;overflow:hidden;background:#f1f5f9;}
#lf-preview-frame{width:100%;height:100%;border:0;display:block;}
.lf-props__header{display:flex;align-items:center;gap:8px;padding:12px 14px 0;font-size:13px;font-weight:800;color:var(--text);flex-shrink:0;}
.lf-props__header[hidden],.lf-props__empty[hidden]{display:none!important;}
.lf-props__body{flex:1;overflow:auto;padding:14px;}
.lf-props__empty{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;padding:24px;text-align:center;color:var(--muted);font-size:13px;}
.lf-props__empty i{font-size:24px;color:color-mix(in srgb,var(--primary) 45%,var(--muted));}
.lf-prop-field{margin-bottom:14px;}
.lf-prop-field:last-child{margin-bottom:0;}
.lf-palette{display:flex;flex-direction:column;position:fixed;top:64px;left:0;width:220px;height:calc(100vh - 64px);overflow-y:auto;padding:16px;box-sizing:border-box;background:linear-gradient(180deg,color-mix(in srgb,var(--card) 94%,#000),var(--card));border-right:1px solid var(--border);box-shadow:8px 0 24px rgba(0,0,0,.08);z-index:5;}
.lf-tool-tabs{display:flex;gap:4px;padding:4px;border-radius:11px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 92%,var(--border) 8%);width:fit-content;margin:0 0 14px;}
.lf-tool-tab{display:grid;place-items:center;width:34px;height:34px;border-radius:8px;border:1px solid transparent;background:transparent;color:var(--muted);cursor:pointer;font-size:14px;transition:all .15s;}
.lf-tool-tab:hover{color:var(--text);background:color-mix(in srgb,var(--card) 80%,transparent);}
.lf-tool-tab.is-active{color:#fff;background:var(--primary);border-color:var(--primary);box-shadow:0 2px 8px -2px color-mix(in srgb,var(--primary) 60%,transparent);}
.lf-palette .ps-panel{display:flex;flex-direction:column;gap:14px;}
.lf-palette .ps-panel[hidden]{display:none!important;}
.lf-palette-card{border:1px solid var(--border);border-radius:12px;background:var(--card);padding:12px;}
.lf-palette-card h4{font-size:10.5px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin:0 0 10px;}
.lf-palette-hint{font-size:11px;color:var(--muted);margin:0 0 8px;line-height:1.4;}
.lf-palette-buttons{display:flex;flex-direction:column;gap:4px;}
.lf-palette-buttons button{display:flex;align-items:center;gap:10px;padding:8px 9px;font-size:12.5px;font-weight:700;border-radius:9px;border:1px solid transparent;background:transparent;color:var(--text);cursor:grab;text-align:left;transition:background .12s,border-color .12s;}
.lf-palette-buttons button:active{cursor:grabbing;}
.lf-palette-buttons button:hover{background:color-mix(in srgb,var(--primary) 7%,var(--card));border-color:color-mix(in srgb,var(--primary) 22%,var(--border));}
.lf-palette-grip{margin-left:auto;color:var(--muted);opacity:.5;font-size:11px;}
.lf-palette-buttons .pcat-sort-chosen{background:color-mix(in srgb,var(--primary) 10%,var(--card));border-color:color-mix(in srgb,var(--primary) 35%,var(--border));}
.lf-canvas.lf-canvas--drop-target{outline:2px dashed color-mix(in srgb,var(--primary) 45%,var(--border));outline-offset:4px;border-radius:12px;}
.lf-block-icon{width:26px;height:26px;border-radius:8px;display:grid;place-items:center;font-size:12px;flex-shrink:0;}
.lf-block-icon--heading{background:color-mix(in srgb,#6366f1 18%,var(--card));color:#6366f1;}
.lf-block-icon--text{background:color-mix(in srgb,#64748b 18%,var(--card));color:#64748b;}
.lf-block-icon--image{background:color-mix(in srgb,#0ea5e9 18%,var(--card));color:#0ea5e9;}
.lf-block-icon--field{background:color-mix(in srgb,#16a34a 18%,var(--card));color:#16a34a;}
.lf-block-icon--divider{background:color-mix(in srgb,#94a3b8 18%,var(--card));color:#94a3b8;}
.lf-block-icon--row{background:color-mix(in srgb,#d946ef 18%,var(--card));color:#d946ef;}
.lf-block-icon--column{background:color-mix(in srgb,#0891b2 18%,var(--card));color:#0891b2;}
.lf-settings textarea.lf-block__input{resize:vertical;}
.lf-mini-label{display:block;font-size:10.5px;font-weight:700;color:var(--muted);margin-bottom:5px;}
.lf-layout-options{display:flex;flex-direction:column;gap:8px;margin-bottom:14px;}
.lf-layout-option{position:relative;display:flex;align-items:center;gap:10px;padding:8px;border:1.5px solid var(--border);border-radius:10px;cursor:pointer;background:var(--card);transition:border-color .12s,background .12s;}
.lf-layout-option:hover{border-color:color-mix(in srgb,var(--primary) 35%,var(--border));}
.lf-layout-option.is-selected{border-color:var(--primary);background:color-mix(in srgb,var(--primary) 6%,var(--card));box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 14%,transparent);}
.lf-layout-option__radio{position:absolute;opacity:0;width:0;height:0;pointer-events:none;}
.lf-layout-option__preview{flex-shrink:0;width:60px;height:44px;border-radius:6px;background:#f1f5f9;border:1px solid #e2e8f0;display:flex;align-items:center;justify-content:center;overflow:hidden;box-sizing:border-box;}
.lf-layout-option__label{font-size:12.5px;font-weight:700;color:var(--text);}
.lf-layout-mini-card{width:40px;height:32px;background:#fff;border:1px solid #e2e8f0;border-radius:3px;display:flex;flex-direction:column;gap:2px;padding:4px;box-sizing:border-box;}
.lf-layout-mini{display:block;border-radius:1px;flex-shrink:0;}
.lf-layout-mini--heading{height:4px;width:70%;background:#0f172a;}
.lf-layout-mini--text{height:2px;width:85%;background:#cbd5e1;}
.lf-layout-mini--btn{height:5px;width:100%;background:#2563eb;border-radius:2px;margin-top:auto;}
.lf-layout-option__preview--split{gap:3px;padding:5px;}
.lf-layout-split-visual{width:16px;height:34px;background:linear-gradient(135deg,#2563eb,#1e293b);border-radius:2px;flex-shrink:0;}
.lf-layout-split-form{flex:1;display:flex;flex-direction:column;gap:3px;justify-content:center;min-width:0;}
.lf-layout-option__preview--minimal{background:#f8fafc;flex-direction:column;gap:3px;padding:8px;align-items:flex-start;}
.lf-color-input{display:flex;align-items:center;gap:8px;}
.lf-color-input input[type="color"]{width:34px;height:34px;padding:2px;border-radius:8px;border:1px solid var(--border);background:var(--card);cursor:pointer;flex-shrink:0;}
.lf-color-input input[type="text"]{flex:1;min-width:0;}
.lf-canvas{display:flex;flex-direction:column;gap:10px;min-height:160px;}
.lf-block{display:flex;gap:10px;padding:12px;border:1.5px solid var(--border);border-radius:12px;background:var(--card);align-items:flex-start;transition:box-shadow .12s,border-color .12s;}
.lf-block:hover{box-shadow:0 4px 16px -8px rgba(0,0,0,.18);}
.lf-block.is-selected{border-color:var(--primary);box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 16%,transparent);}
.lf-block__handle{cursor:grab;color:var(--muted);padding-top:6px;flex-shrink:0;font-size:13px;}
.lf-block__handle:active{cursor:grabbing;}
.lf-block__body{flex:1;min-width:0;cursor:pointer;}
.lf-block__type-label{display:flex;align-items:center;gap:8px;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:9px;}
.lf-block__input{width:100%;box-sizing:border-box;padding:8px 10px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);font-family:inherit;}
.lf-block__input:focus{outline:none;border-color:color-mix(in srgb,var(--primary) 45%,var(--border));box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 12%,transparent);}
.lf-block__remove{flex-shrink:0;width:30px;height:30px;border-radius:8px;border:1px solid transparent;background:transparent;color:var(--muted);cursor:pointer;font-size:13px;transition:background .12s,color .12s;}
.lf-block__remove:hover{background:color-mix(in srgb,#ef4444 12%,transparent);color:#ef4444;}
.lf-block__divider-preview{height:1px;background:var(--border);margin:10px 0;}
.lf-block__preview-heading{font-size:15px;font-weight:800;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.lf-block__preview-heading.lf-is-md{font-size:13px;}
.lf-block__preview-heading.lf-is-empty,.lf-block__preview-text.lf-is-empty{color:var(--muted);font-style:italic;font-weight:400;}
.lf-block__preview-text{font-size:12.5px;color:var(--muted);line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
.lf-block__preview-field-label{font-size:13px;font-weight:700;color:var(--text);}
.lf-block__preview-field-meta{font-size:11px;color:var(--muted);margin-top:3px;}
.lf-block__required-badge{display:inline-block;font-size:9px;font-weight:800;padding:1px 6px;border-radius:999px;background:color-mix(in srgb,#ef4444 14%,transparent);color:#ef4444;margin-left:6px;vertical-align:middle;}
.lf-block__preview-image{max-width:100%;max-height:64px;border-radius:6px;display:block;}
.lf-block__preview-image-empty{font-size:12px;color:var(--muted);font-style:italic;}
.lf-block-slot{display:flex;gap:10px;margin-top:2px;min-height:56px;}
.lf-row-slot{flex-direction:row;flex-wrap:wrap;align-items:flex-start;}
.lf-row-slot > .lf-block{flex:1 1 0;min-width:140px;}
.lf-column-slot{flex-direction:column;border:1.5px dashed var(--border);border-radius:10px;padding:8px;background:color-mix(in srgb,var(--card) 97%,var(--border) 3%);}
.lf-block-slot .lf-block{padding:9px;}
.lf-block-slot .lf-block__type-label{font-size:10px;margin-bottom:6px;}
.lf-block-slot__hint{flex:1;display:flex;align-items:center;justify-content:center;font-size:11.5px;color:var(--muted);text-align:center;padding:10px;border:1.5px dashed var(--border);border-radius:10px;}
.lf-row-slot > .lf-block-slot__hint{min-width:140px;}
.lf-empty{padding:36px 20px;text-align:center;border:1.5px dashed var(--border);border-radius:12px;color:var(--muted);font-size:13px;display:flex;flex-direction:column;align-items:center;gap:8px;}
.lf-empty i{font-size:22px;color:color-mix(in srgb,var(--primary) 55%,var(--muted));}
.pcat-sort-ghost{opacity:.4;}
.pcat-sort-chosen{box-shadow:0 8px 22px -10px rgba(0,0,0,.4)!important;}
</style>

<div class="pcat-page-card card lf-builder-shell" style="max-width:100%;padding:14px;">
    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif

    <div class="lf-topbar">
        <div class="lf-project-nav">
            <button type="button" class="lf-project-nav__trigger" id="lf-project-nav-trigger">
                <i class="fa fa-diagram-project"></i>
                <span class="lf-project-nav__name">{{ $project->name }}</span>
                <i class="fa fa-chevron-down lf-project-nav__chevron"></i>
            </button>
            <div class="lf-project-nav__menu" id="lf-project-nav-menu" hidden>
                <a href="{{ route('crm.projects.index') }}" class="lf-project-nav__item">
                    <i class="fa fa-arrow-left"></i> All projects
                </a>
                <div class="lf-project-nav__divider"></div>
                <a href="{{ route('crm.projects.show', $project) }}"
                   @class(['lf-project-nav__item', 'is-active' => request()->routeIs('crm.projects.show')])>
                    <i class="fa fa-house"></i> Overview
                </a>
                <a href="{{ route('crm.projects.leads.index', $project) }}"
                   @class(['lf-project-nav__item', 'is-active' => request()->routeIs('crm.projects.leads.index') || request()->routeIs('crm.leads.show') || request()->routeIs('crm.leads.edit')])>
                    <i class="fa fa-filter"></i> Leads
                </a>
                <a href="{{ route('crm.projects.leads.board', $project) }}"
                   @class(['lf-project-nav__item', 'is-active' => request()->routeIs('crm.projects.leads.board')])>
                    <i class="fa fa-table-columns"></i> Board
                </a>
                <a href="{{ route('crm.projects.stages.index', $project) }}"
                   @class(['lf-project-nav__item', 'is-active' => request()->routeIs('crm.projects.stages.index')])>
                    <i class="fa fa-sliders"></i> Stages
                </a>
                <a href="{{ route('crm.projects.forms.index', $project) }}"
                   @class(['lf-project-nav__item', 'is-active' => request()->routeIs('crm.projects.forms.*')])>
                    <i class="fa fa-window-restore"></i> Forms
                </a>
                <a href="{{ route('crm.projects.edit', $project) }}"
                   @class(['lf-project-nav__item', 'is-active' => request()->routeIs('crm.projects.edit')])>
                    <i class="fa fa-gear"></i> Settings
                </a>
            </div>
        </div>

        <div class="lf-name-wrap">
            <small>Form name</small>
            <input type="text" id="lf-name-input" value="{{ $form->name }}" maxlength="150">
        </div>

        <div class="lf-publish-group">
            @if($form->is_published)
                <span class="pcat-badge pcat-badge--on">Published</span>
                <form method="POST" action="{{ route('crm.projects.forms.unpublish', [$project, $form]) }}">
                    @csrf
                    <button type="submit" class="lf-pill-btn"><i class="fa fa-eye-slash"></i> Unpublish</button>
                </form>
            @else
                <span class="pcat-badge pcat-badge--off">Draft</span>
                <form method="POST" action="{{ route('crm.projects.forms.publish', [$project, $form]) }}">
                    @csrf
                    <button type="submit" class="lf-pill-btn"><i class="fa fa-cloud-arrow-up"></i> Publish</button>
                </form>
            @endif

            @if($form->is_published)
                <div class="lf-link-box">
                    <i class="fa fa-link"></i>
                    <input type="text" readonly value="{{ $form->publicUrl() }}" id="lf-public-url" onclick="this.select();">
                    <button type="button" class="pcat-link" style="background:none;border:none;cursor:pointer;" onclick="navigator.clipboard.writeText(document.getElementById('lf-public-url').value)">Copy</button>
                    <a href="{{ $form->publicUrl() }}" target="_blank" rel="noopener" class="pcat-link"><i class="fa fa-up-right-from-square"></i> Preview</a>
                </div>
            @else
                <span class="lf-publish-hint">Publish to get a shareable link.</span>
            @endif
        </div>

        <div class="lf-save-group">
            <button type="button" id="lf-preview-toggle" class="lf-pill-btn">
                <i class="fa fa-table-columns"></i> <span id="lf-preview-toggle-label">Hide panel</span>
            </button>
            <span id="lf-save-status" class="lf-status"></span>
            <button type="button" id="lf-save-btn" class="linkbtn">
                <i class="fa fa-floppy-disk"></i> Save
            </button>
        </div>
    </div>

    <div class="lf-subbar">
        <form method="POST" action="{{ route('crm.projects.forms.destroy', [$project, $form]) }}" onsubmit="return confirm('Delete this form? This cannot be undone.');" style="margin-left:auto;">
            @csrf
            @method('DELETE')
            <button type="submit" class="pcat-btn-del" style="padding:5px 10px;font-size:11px;">
                <i class="fa fa-trash"></i> Delete form
            </button>
        </form>
    </div>

    <div class="lf-layout">
        <div class="lf-palette">
            <nav class="lf-tool-tabs" role="tablist">
                <button type="button" class="lf-tool-tab is-active" data-lf-tab="blocks" title="Blocks" aria-label="Blocks"><i class="fa fa-cubes"></i></button>
                <button type="button" class="lf-tool-tab" data-lf-tab="design" title="Design" aria-label="Design"><i class="fa fa-brush"></i></button>
                <button type="button" class="lf-tool-tab" data-lf-tab="settings" title="Settings" aria-label="Settings"><i class="fa fa-gear"></i></button>
            </nav>

            <section id="lf-tab-blocks" class="ps-panel" data-lf-panel>
                <div class="lf-palette-card">
                    <p class="lf-palette-hint">Click or drag a block onto the form.</p>
                    <div class="lf-palette-buttons" id="lf-palette-buttons">
                        <button type="button" data-add-block="heading"><span class="lf-block-icon lf-block-icon--heading"><i class="fa fa-heading"></i></span> Heading<i class="fa fa-grip-vertical lf-palette-grip" aria-hidden="true"></i></button>
                        <button type="button" data-add-block="text"><span class="lf-block-icon lf-block-icon--text"><i class="fa fa-align-left"></i></span> Text<i class="fa fa-grip-vertical lf-palette-grip" aria-hidden="true"></i></button>
                        <button type="button" data-add-block="image"><span class="lf-block-icon lf-block-icon--image"><i class="fa fa-image"></i></span> Image<i class="fa fa-grip-vertical lf-palette-grip" aria-hidden="true"></i></button>
                        <button type="button" data-add-block="field"><span class="lf-block-icon lf-block-icon--field"><i class="fa fa-input-text"></i></span> Form field<i class="fa fa-grip-vertical lf-palette-grip" aria-hidden="true"></i></button>
                        <button type="button" data-add-block="divider"><span class="lf-block-icon lf-block-icon--divider"><i class="fa fa-minus"></i></span> Divider<i class="fa fa-grip-vertical lf-palette-grip" aria-hidden="true"></i></button>
                        <button type="button" data-add-block="row"><span class="lf-block-icon lf-block-icon--row"><i class="fa fa-window-maximize"></i></span> Row<i class="fa fa-grip-vertical lf-palette-grip" aria-hidden="true"></i></button>
                        <button type="button" data-add-block="column"><span class="lf-block-icon lf-block-icon--column"><i class="fa fa-table-columns"></i></span> Column<i class="fa fa-grip-vertical lf-palette-grip" aria-hidden="true"></i></button>
                    </div>
                </div>
            </section>

            <section id="lf-tab-design" class="ps-panel" data-lf-panel hidden>
                <div class="lf-palette-card lf-settings">
                    <h4>Layout</h4>
                    <div class="lf-layout-options" role="radiogroup" aria-label="Page layout">
                        <label class="lf-layout-option">
                            <input type="radio" name="lf-style-layout" value="card" class="lf-layout-option__radio" @checked($style['layout'] === 'card')>
                            <span class="lf-layout-option__preview lf-layout-option__preview--card">
                                <span class="lf-layout-mini-card">
                                    <span class="lf-layout-mini lf-layout-mini--heading"></span>
                                    <span class="lf-layout-mini lf-layout-mini--text"></span>
                                    <span class="lf-layout-mini lf-layout-mini--btn"></span>
                                </span>
                            </span>
                            <span class="lf-layout-option__label">Card</span>
                        </label>
                        <label class="lf-layout-option">
                            <input type="radio" name="lf-style-layout" value="split" class="lf-layout-option__radio" @checked($style['layout'] === 'split')>
                            <span class="lf-layout-option__preview lf-layout-option__preview--split">
                                <span class="lf-layout-split-visual"></span>
                                <span class="lf-layout-split-form">
                                    <span class="lf-layout-mini lf-layout-mini--text"></span>
                                    <span class="lf-layout-mini lf-layout-mini--btn"></span>
                                </span>
                            </span>
                            <span class="lf-layout-option__label">Split</span>
                        </label>
                        <label class="lf-layout-option">
                            <input type="radio" name="lf-style-layout" value="minimal" class="lf-layout-option__radio" @checked($style['layout'] === 'minimal')>
                            <span class="lf-layout-option__preview lf-layout-option__preview--minimal">
                                <span class="lf-layout-mini lf-layout-mini--heading"></span>
                                <span class="lf-layout-mini lf-layout-mini--text"></span>
                                <span class="lf-layout-mini lf-layout-mini--btn"></span>
                            </span>
                            <span class="lf-layout-option__label">Minimal</span>
                        </label>
                    </div>
                    <label class="lf-mini-label" for="lf-style-accent-text">Accent color</label>
                    <div class="lf-color-input" style="margin-bottom:10px;">
                        <input type="color" id="lf-style-accent" value="{{ $style['accent_color'] }}">
                        <input type="text" id="lf-style-accent-text" class="lf-block__input" value="{{ $style['accent_color'] }}" maxlength="7">
                    </div>
                    <label class="lf-mini-label" for="lf-style-bg-text">Background color</label>
                    <div class="lf-color-input">
                        <input type="color" id="lf-style-bg" value="{{ $style['background_color'] }}">
                        <input type="text" id="lf-style-bg-text" class="lf-block__input" value="{{ $style['background_color'] }}" maxlength="7">
                    </div>
                </div>
            </section>

            <section id="lf-tab-settings" class="ps-panel" data-lf-panel hidden>
                <div class="lf-palette-card lf-settings">
                    <h4>Submit button</h4>
                    <input type="text" id="lf-submit-text" class="lf-block__input" maxlength="60" value="{{ $form->submit_button_text }}">
                </div>
                <div class="lf-palette-card lf-settings">
                    <h4>Success message</h4>
                    <textarea id="lf-success-message" class="lf-block__input" rows="3">{{ $form->success_message }}</textarea>
                </div>
            </section>
        </div>

        <div>
            <div id="lf-canvas" class="lf-canvas"></div>
        </div>

        <div class="lf-preview" id="lf-preview-panel">
            <div class="lf-panel-tabs">
                <button type="button" class="lf-panel-tab is-active" data-panel-tab="preview">
                    <i class="fa fa-eye"></i> Preview
                </button>
                <button type="button" class="lf-panel-tab" data-panel-tab="properties" id="lf-panel-tab-properties">
                    <i class="fa fa-sliders"></i> Properties
                </button>
                @if($form->is_published)
                    <a href="{{ $form->publicUrl() }}" target="_blank" rel="noopener" title="Open public page" class="lf-panel-tabs__link"><i class="fa fa-up-right-from-square"></i></a>
                @endif
            </div>

            <div class="lf-panel-pane" data-panel-pane="preview">
                <div class="lf-preview__frame-wrap">
                    <iframe id="lf-preview-frame" title="Form preview"></iframe>
                </div>
            </div>

            <div class="lf-panel-pane" data-panel-pane="properties" hidden>
                <div class="lf-props__header" id="lf-props-header" hidden>
                    <span class="lf-block-icon" id="lf-props-icon-badge"><i class="fa" id="lf-props-icon"></i></span>
                    <span id="lf-props-title"></span>
                </div>
                <div class="lf-props__body" id="lf-props-body"></div>
                <div class="lf-props__empty" id="lf-props-empty">
                    <i class="fa fa-arrow-pointer" aria-hidden="true"></i>
                    <p>Select a block on the canvas to edit its properties.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div style="margin-top:14px;">
    <a href="{{ route('crm.projects.forms.index', $project) }}" class="linkbtn"
       style="padding:7px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fa fa-arrow-left"></i> All forms
    </a>
</div>

{{-- Hidden templates for newly-added blocks --}}
<template id="tpl-heading">@include('crm::leads.forms.partials.block-editor', ['block' => ['type' => 'heading', 'text' => 'New heading', 'size' => 'lg'], 'customFields' => $customFields])</template>
<template id="tpl-text">@include('crm::leads.forms.partials.block-editor', ['block' => ['type' => 'text', 'text' => ''], 'customFields' => $customFields])</template>
<template id="tpl-image">@include('crm::leads.forms.partials.block-editor', ['block' => ['type' => 'image'], 'customFields' => $customFields])</template>
<template id="tpl-field">@include('crm::leads.forms.partials.block-editor', ['block' => ['type' => 'field', 'field' => 'name', 'label' => 'Field label'], 'customFields' => $customFields])</template>
<template id="tpl-divider">@include('crm::leads.forms.partials.block-editor', ['block' => ['type' => 'divider'], 'customFields' => $customFields])</template>
<template id="tpl-row">@include('crm::leads.forms.partials.block-editor', ['block' => ['type' => 'row'], 'customFields' => $customFields])</template>
<template id="tpl-column">@include('crm::leads.forms.partials.block-editor', ['block' => ['type' => 'column'], 'customFields' => $customFields])</template>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
(function () {
    var canvas = document.getElementById('lf-canvas');
    var customFieldsMeta = @json($customFields->keyBy('id')->map(fn ($cf) => ['type' => $cf->type, 'options' => $cf->optionList()]));
    var knownCustomFields = @json($customFields->map(fn ($cf) => ['id' => $cf->id, 'label' => $cf->label])->values());
    var customFieldsStoreUrl = @json(route('crm.projects.custom-fields.store', $project));
    var TYPE_ICONS = { heading: 'fa-heading', text: 'fa-align-left', image: 'fa-image', field: 'fa-input-text', divider: 'fa-minus', row: 'fa-window-maximize', column: 'fa-table-columns' };
    var TYPE_LABELS = { heading: 'Heading', text: 'Text', image: 'Image', field: 'Form field', divider: 'Divider', row: 'Row', column: 'Column' };

    document.querySelectorAll('[data-lf-tab]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var tab = btn.getAttribute('data-lf-tab');
            document.querySelectorAll('[data-lf-tab]').forEach(function (b) { b.classList.remove('is-active'); });
            document.querySelectorAll('[data-lf-panel]').forEach(function (p) { p.hidden = true; });
            btn.classList.add('is-active');
            var panel = document.getElementById('lf-tab-' + tab);
            if (panel) panel.hidden = false;
        });
    });

    var PREVIEW_CSS = "*{box-sizing:border-box;}body{margin:0;font-family:'Segoe UI',Arial,sans-serif;font-size:15px;color:#1e293b;background:var(--pf-bg);min-height:100vh;}"
        + ".pf-wrap{max-width:480px;margin:0 auto;padding:28px 18px;}.pf-card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:28px 24px;box-shadow:0 4px 24px rgba(0,0,0,.06);}"
        + ".pf-heading-lg{font-size:22px;font-weight:800;color:#0f172a;letter-spacing:-.02em;margin:0 0 12px;}.pf-heading-md{font-size:17px;font-weight:700;color:#0f172a;margin:0 0 10px;}"
        + ".pf-text{font-size:14px;line-height:1.6;color:#475569;margin:0 0 14px;white-space:pre-line;}.pf-image{width:100%;border-radius:10px;margin:0 0 14px;display:block;}"
        + ".pf-divider{border:none;border-top:1px solid #e2e8f0;margin:18px 0;}.pf-field{margin:0 0 14px;}.pf-field label{display:block;font-size:12px;font-weight:700;color:#475569;margin-bottom:6px;}"
        + ".pf-field input,.pf-field textarea,.pf-field select{width:100%;box-sizing:border-box;padding:10px 12px;font-size:14px;border-radius:8px;border:1px solid #cbd5e1;background:#fff;color:#0f172a;font-family:inherit;}"
        + ".pf-field input:focus,.pf-field textarea:focus,.pf-field select:focus{outline:none;border-color:var(--pf-accent);box-shadow:0 0 0 3px color-mix(in srgb,var(--pf-accent) 20%,transparent);}"
        + ".pf-field textarea{min-height:70px;resize:vertical;}.pf-checkbox{display:flex;align-items:center;gap:8px;font-size:13px;color:#475569;}"
        + ".pf-help{font-size:11px;color:#94a3b8;margin-top:5px;line-height:1.4;}"
        + ".pf-submit{width:100%;padding:12px 16px;font-size:14px;font-weight:700;border-radius:9px;border:none;background:var(--pf-accent);color:#fff;cursor:default;margin-top:8px;}"
        + ".pf-footer{text-align:center;margin-top:16px;font-size:11px;color:#94a3b8;}"
        + ".pf-row{display:flex;gap:14px;margin:0 0 14px;flex-wrap:wrap;}.pf-col{flex:1 1 0;min-width:140px;}.pf-col .pf-field:last-child{margin-bottom:0;}"
        + "body.pf-layout-minimal .pf-card{background:transparent;border:none;box-shadow:none;padding:0;}"
        + "body.pf-layout-split{padding:0;}.pf-split{display:flex;min-height:100vh;}"
        + ".pf-split__visual{flex:1 1 38%;background:linear-gradient(135deg,var(--pf-accent),color-mix(in srgb,var(--pf-accent) 55%,#000));background-size:cover;background-position:center;display:flex;align-items:flex-end;padding:20px;}"
        + ".pf-split__visual-title{color:#fff;font-size:16px;font-weight:800;text-shadow:0 2px 14px rgba(0,0,0,.3);margin:0;}"
        + ".pf-split__form{flex:1 1 62%;display:flex;align-items:center;justify-content:center;padding:20px 16px;}"
        + ".pf-split__form .pf-wrap{padding:0;max-width:320px;width:100%;}.pf-split__form .pf-card{box-shadow:none;border:none;padding:0;}";

    function getStyleSettings() {
        return {
            layout: document.querySelector('input[name="lf-style-layout"]:checked')?.value || 'card',
            accent_color: document.getElementById('lf-style-accent-text')?.value || '#2563eb',
            background_color: document.getElementById('lf-style-bg-text')?.value || '#f1f5f9',
        };
    }

    function bindColorPair(colorId, textId) {
        var colorEl = document.getElementById(colorId);
        var textEl = document.getElementById(textId);
        if (!colorEl || !textEl) return;
        colorEl.addEventListener('input', function () { textEl.value = colorEl.value; renderPreview(); });
        textEl.addEventListener('input', function () {
            if (/^#[0-9a-fA-F]{6}$/.test(textEl.value)) colorEl.value = textEl.value;
            schedulePreview();
        });
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // Rebuilt every time a "Form field" block's properties open, so it always
    // reflects fields added since the page loaded (including via "+ Add new field…").
    function fieldMapsToOptionsHtml(selectedValue) {
        var fixed = [
            ['name', 'Name'],
            ['email', 'Email'],
            ['phone', 'Phone'],
            ['company', 'Company'],
        ];
        var html = fixed.map(function (pair) {
            return '<option value="' + pair[0] + '"' + (selectedValue === pair[0] ? ' selected' : '') + '>' + pair[1] + '</option>';
        }).join('');
        html += knownCustomFields.map(function (cf) {
            var val = 'custom:' + cf.id;
            return '<option value="' + val + '"' + (selectedValue === val ? ' selected' : '') + '>' + escapeHtml(cf.label) + '</option>';
        }).join('');
        html += '<option value="__new__">+ Add new field…</option>';
        return html;
    }

    // ---- Block state: single source of truth for all blocks ----
    var blocksState = @json($form->blocks ?? []);
    var uidCounter = 0;
    function assignUidsDeep(blocks) {
        blocks.forEach(function (b) {
            b._uid = 'b' + (uidCounter++);
            if (b.type === 'row' || b.type === 'column') {
                assignUidsDeep(b.blocks || []);
            }
        });
    }
    assignUidsDeep(blocksState);
    var selectedUid = null;

    // "row" and "column" are the only container types — every container has a uniform "blocks"
    // array of children, so these helpers just recurse into any block of either type.
    function isContainerType(type) { return type === 'row' || type === 'column'; }

    // Recursively locate a block anywhere in the tree, returning the array + index it currently
    // lives in so callers can splice it.
    function findBlockLocation(uid) {
        function search(arr) {
            for (var i = 0; i < arr.length; i++) {
                if (arr[i]._uid === uid) return { array: arr, index: i, block: arr[i] };
                if (isContainerType(arr[i].type)) {
                    var found = search(arr[i].blocks);
                    if (found) return found;
                }
            }
            return null;
        }
        return search(blocksState);
    }

    function findBlock(uid) {
        var loc = findBlockLocation(uid);
        return loc ? loc.block : null;
    }

    function buildUidMap(blocks, map) {
        map = map || {};
        blocks.forEach(function (b) {
            map[b._uid] = b;
            if (isContainerType(b.type)) buildUidMap(b.blocks, map);
        });
        return map;
    }

    function stripUidsDeep(block) {
        var copy = Object.assign({}, block);
        delete copy._uid;
        if (isContainerType(copy.type)) {
            copy.blocks = copy.blocks.map(stripUidsDeep);
        }
        return copy;
    }

    function defaultBlockForType(type) {
        if (type === 'heading') return { type: 'heading', text: 'New heading', size: 'lg' };
        if (type === 'text') return { type: 'text', text: '' };
        if (type === 'image') return { type: 'image', file_id: null, url: '', alt: '' };
        if (type === 'field') return { type: 'field', field: 'name', label: 'Field label', placeholder: '', help_text: '', required: false };
        if (type === 'divider') return { type: 'divider' };
        if (type === 'row') return { type: 'row', blocks: [] };
        if (type === 'column') return { type: 'column', blocks: [] };
        return { type: type };
    }

    function typeIconHtml(type) {
        return '<span class="lf-block-icon lf-block-icon--' + type + '"><i class="fa ' + (TYPE_ICONS[type] || 'fa-cube') + '"></i></span>';
    }

    function blockSummaryHtml(block) {
        var type = block.type;
        if (type === 'heading') {
            var text = block.text || '';
            var isMd = (block.size || 'lg') === 'md';
            return '<div class="lf-block__preview-heading' + (isMd ? ' lf-is-md' : '') + (text ? '' : ' lf-is-empty') + '">' + escapeHtml(text || 'Untitled heading') + '</div>';
        }
        if (type === 'text') {
            var t = block.text || '';
            return '<div class="lf-block__preview-text' + (t ? '' : ' lf-is-empty') + '">' + escapeHtml(t || 'Empty text block') + '</div>';
        }
        if (type === 'image') {
            return block.url
                ? '<img class="lf-block__preview-image" src="' + escapeHtml(block.url) + '" alt="">'
                : '<div class="lf-block__preview-image-empty">No image selected</div>';
        }
        if (type === 'divider') {
            return '<div class="lf-block__divider-preview"></div>';
        }
        if (type === 'field') {
            var fieldKey = block.field || 'name';
            var label = block.label || (fieldKey.charAt(0).toUpperCase() + fieldKey.slice(1));
            var meta = fieldKey.indexOf('custom:') === 0 ? 'Custom field' : fieldKey;
            return '<div class="lf-block__preview-field-label">' + escapeHtml(label) + (block.required ? '<span class="lf-block__required-badge">Required</span>' : '') + '</div>'
                + '<div class="lf-block__preview-field-meta">' + escapeHtml(meta) + '</div>';
        }
        return '';
    }

    function createBlockElement(block) {
        var card = document.createElement('div');
        card.className = 'lf-block' + (block._uid === selectedUid ? ' is-selected' : '');
        card.dataset.blockType = block.type;
        card.dataset.uid = block._uid;

        var handle = document.createElement('span');
        handle.className = 'lf-block__handle';
        handle.setAttribute('data-lf-drag', '');
        handle.title = 'Drag to reorder';
        handle.innerHTML = '<i class="fa fa-grip-vertical"></i>';
        card.appendChild(handle);

        var body = document.createElement('div');
        body.className = 'lf-block__body';
        var typeLabel = document.createElement('div');
        typeLabel.className = 'lf-block__type-label';
        typeLabel.innerHTML = typeIconHtml(block.type) + (TYPE_LABELS[block.type] || block.type);
        body.appendChild(typeLabel);

        if (isContainerType(block.type)) {
            var slot = document.createElement('div');
            slot.className = 'lf-block-slot ' + (block.type === 'row' ? 'lf-row-slot' : 'lf-column-slot');
            if (!block.blocks || !block.blocks.length) {
                var hint = document.createElement('div');
                hint.className = 'lf-block-slot__hint';
                hint.textContent = block.type === 'row' ? 'Drag a Column here' : 'Drag a block here';
                slot.appendChild(hint);
            } else {
                block.blocks.forEach(function (child) {
                    slot.appendChild(createBlockElement(child));
                });
            }
            body.appendChild(slot);
        } else {
            var summaryWrap = document.createElement('div');
            summaryWrap.innerHTML = blockSummaryHtml(block);
            while (summaryWrap.firstChild) body.appendChild(summaryWrap.firstChild);
        }

        card.appendChild(body);

        var removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'lf-block__remove';
        removeBtn.setAttribute('data-lf-remove', '');
        removeBtn.title = 'Remove block';
        removeBtn.innerHTML = '<i class="fa fa-trash-can"></i>';
        card.appendChild(removeBtn);

        return card;
    }

    function initSlotSortables() {
        if (typeof Sortable === 'undefined') return;
        canvas.querySelectorAll('.lf-block-slot').forEach(function (slotEl) {
            Sortable.create(slotEl, {
                animation: 150,
                handle: '.lf-block__handle',
                ghostClass: 'pcat-sort-ghost',
                chosenClass: 'pcat-sort-chosen',
                draggable: '.lf-block',
                group: { name: 'lf-blocks', pull: true, put: true },
                emptyInsertThreshold: 40,
                onMove: sharedOnMove,
                onEnd: reconcileTreeFromDom,
            });
        });
    }

    function renderCanvas() {
        canvas.innerHTML = '';
        if (!blocksState.length) {
            var div = document.createElement('div');
            div.className = 'lf-empty';
            div.setAttribute('data-lf-empty-hint', '');
            div.innerHTML = '<i class="fa fa-wand-magic-sparkles"></i><span>Add a block from the left to start building your form.</span>';
            canvas.appendChild(div);
            return;
        }
        blocksState.forEach(function (block) {
            canvas.appendChild(createBlockElement(block));
        });
        initSlotSortables();
    }

    function updateCanvasSummary(uid) {
        var card = canvas.querySelector('.lf-block[data-uid="' + uid + '"]');
        var block = findBlock(uid);
        if (!card || !block) return;
        var body = card.querySelector('.lf-block__body');
        body.innerHTML = '<div class="lf-block__type-label">' + typeIconHtml(block.type) + (TYPE_LABELS[block.type] || block.type) + '</div>' + blockSummaryHtml(block);
    }

    function renderPreviewBlock(block) {
        var type = block.type;
        if (type === 'heading') {
            var lg = (block.size || 'lg') === 'lg';
            var tag = lg ? 'h1' : 'h2';
            var cls = lg ? 'pf-heading-lg' : 'pf-heading-md';
            return '<' + tag + ' class="' + cls + '">' + escapeHtml(block.text) + '</' + tag + '>';
        }
        if (type === 'text') {
            return block.text ? '<p class="pf-text">' + escapeHtml(block.text) + '</p>' : '';
        }
        if (type === 'image') {
            return block.url ? '<img class="pf-image" src="' + escapeHtml(block.url) + '" alt="' + escapeHtml(block.alt) + '">' : '';
        }
        if (type === 'divider') {
            return '<hr class="pf-divider">';
        }
        if (type === 'row') {
            var cols = (block.blocks || []).map(function (col) {
                return '<div class="pf-col">' + (col.blocks || []).map(renderPreviewBlock).join('') + '</div>';
            }).join('');
            return '<div class="pf-row">' + cols + '</div>';
        }
        if (type === 'field') {
            var fieldKey = block.field || 'name';
            var label = block.label || (fieldKey.charAt(0).toUpperCase() + fieldKey.slice(1));
            var required = !!block.required;
            var placeholderAttr = block.placeholder ? ' placeholder="' + escapeHtml(block.placeholder) + '"' : '';
            var helpHtml = block.help_text ? '<div class="pf-help">' + escapeHtml(block.help_text) + '</div>' : '';
            var custom = fieldKey.indexOf('custom:') === 0 ? (customFieldsMeta[fieldKey.slice(7)] || null) : null;
            var star = required ? ' <span style="color:#ef4444;">*</span>' : '';
            if (custom && custom.type === 'checkbox') {
                return '<div class="pf-field"><label class="pf-checkbox"><input type="checkbox" disabled> ' + escapeHtml(label) + (required ? ' *' : '') + '</label>' + helpHtml + '</div>';
            }
            var input;
            if (custom && custom.type === 'select') {
                input = '<select disabled><option>— Select —</option>' + (custom.options || []).map(function (o) { return '<option>' + escapeHtml(o) + '</option>'; }).join('') + '</select>';
            } else if (custom && custom.type === 'textarea') {
                input = '<textarea disabled' + placeholderAttr + '></textarea>';
            } else {
                var inputType = fieldKey === 'email' ? 'email' : fieldKey === 'phone' ? 'tel' : (custom && custom.type === 'number') ? 'number' : (custom && custom.type === 'date') ? 'date' : 'text';
                input = '<input type="' + inputType + '" disabled' + placeholderAttr + '>';
            }
            return '<div class="pf-field"><label>' + escapeHtml(label) + star + '</label>' + input + helpHtml + '</div>';
        }
        return '';
    }

    function renderPreview() {
        var previewFrame = document.getElementById('lf-preview-frame');
        if (!previewFrame) return;
        var body = blocksState.map(renderPreviewBlock).join('\n');
        var submitTextEl = document.getElementById('lf-submit-text');
        var submitText = (submitTextEl && submitTextEl.value) || 'Submit';
        var style = getStyleSettings();
        var rootVars = ':root{--pf-accent:' + style.accent_color + ';--pf-bg:' + style.background_color + ';}';
        var formHtml = body + '<button type="button" class="pf-submit">' + escapeHtml(submitText) + '</button>';
        var bodyHtml;
        if (style.layout === 'split') {
            var firstImage = Object.values(buildUidMap(blocksState)).find(function (b) { return b.type === 'image' && b.url; });
            var visualStyle = firstImage ? ' style="background-image:url(\'' + escapeHtml(firstImage.url) + '\');"' : '';
            bodyHtml = '<div class="pf-split">'
                + '<div class="pf-split__visual"' + visualStyle + '><h2 class="pf-split__visual-title">' + escapeHtml(document.getElementById('lf-name-input').value) + '</h2></div>'
                + '<div class="pf-split__form"><div class="pf-wrap"><div class="pf-card">' + formHtml + '</div></div></div>'
                + '</div>';
        } else {
            bodyHtml = '<div class="pf-wrap"><div class="pf-card">' + formHtml + '</div></div>';
        }
        var html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>' + rootVars + PREVIEW_CSS + '</style></head>'
            + '<body class="pf-layout-' + style.layout + '">' + bodyHtml + '</body></html>';
        previewFrame.srcdoc = html;
    }

    var previewToggleBtn = document.getElementById('lf-preview-toggle');
    var previewToggleLabel = document.getElementById('lf-preview-toggle-label');
    var layoutEl = document.querySelector('.lf-builder-shell');
    previewToggleBtn?.addEventListener('click', function () {
        var hidden = layoutEl.classList.toggle('is-preview-hidden');
        previewToggleLabel.textContent = hidden ? 'Show panel' : 'Hide panel';
    });

    bindColorPair('lf-style-accent', 'lf-style-accent-text');
    bindColorPair('lf-style-bg', 'lf-style-bg-text');

    function syncLayoutOptionSelection() {
        document.querySelectorAll('.lf-layout-option').forEach(function (opt) {
            var radio = opt.querySelector('.lf-layout-option__radio');
            opt.classList.toggle('is-selected', !!radio?.checked);
        });
    }
    document.querySelectorAll('input[name="lf-style-layout"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            syncLayoutOptionSelection();
            renderPreview();
        });
    });
    syncLayoutOptionSelection();

    function csrf() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            || document.querySelector('input[name="_token"]')?.value || '';
    }

    function makeIdsUnique(fragment) {
        var suffix = '-' + Math.random().toString(36).slice(2, 9);
        var idMap = {};
        fragment.querySelectorAll('[id]').forEach(function (el) {
            var oldId = el.id;
            var newId = oldId + suffix;
            idMap[oldId] = newId;
            el.id = newId;
        });
        fragment.querySelectorAll('[for]').forEach(function (el) {
            var oldFor = el.getAttribute('for');
            if (idMap[oldFor]) el.setAttribute('for', idMap[oldFor]);
        });
    }

    // ---- Right panel tabs (Preview / Properties) ----
    function switchPanelTab(tab) {
        document.querySelectorAll('.lf-panel-tab').forEach(function (btn) {
            btn.classList.toggle('is-active', btn.dataset.panelTab === tab);
        });
        document.querySelectorAll('.lf-panel-pane').forEach(function (pane) {
            pane.hidden = pane.dataset.panelPane !== tab;
        });
    }
    document.querySelectorAll('.lf-panel-tab').forEach(function (btn) {
        btn.addEventListener('click', function () { switchPanelTab(btn.dataset.panelTab); });
    });

    // ---- Properties panel ----
    var propsBody = document.getElementById('lf-props-body');
    var propsTitle = document.getElementById('lf-props-title');
    var propsIcon = document.getElementById('lf-props-icon');
    var propsHeader = document.getElementById('lf-props-header');
    var propsEmpty = document.getElementById('lf-props-empty');

    function populateFieldsFromBlock(container, block) {
        if (block.type === 'heading') {
            var t = container.querySelector('[data-field="text"]'); if (t) t.value = block.text || '';
            var s = container.querySelector('[data-field="size"]'); if (s) s.value = block.size || 'lg';
        } else if (block.type === 'text') {
            var tx = container.querySelector('[data-field="text"]'); if (tx) tx.value = block.text || '';
        } else if (block.type === 'image') {
            var fileIdInput = container.querySelector('[data-crm-image-file-id]'); if (fileIdInput) fileIdInput.value = block.file_id || '';
            var urlInput = container.querySelector('[data-crm-image-url]'); if (urlInput) urlInput.value = block.url || '';
            var altInput = container.querySelector('[data-field="alt"]'); if (altInput) altInput.value = block.alt || '';
            var preview = container.querySelector('[data-crm-image-preview]');
            var previewImg = container.querySelector('[data-crm-image-preview-img]');
            var placeholder = container.querySelector('[data-crm-image-placeholder]');
            var clearBtn = container.querySelector('[data-crm-image-clear]');
            var previewName = container.querySelector('[data-crm-image-preview-name]');
            var hasUrl = !!block.url;
            if (previewImg && hasUrl) previewImg.src = block.url;
            if (previewName) previewName.textContent = block.alt || '';
            if (preview) preview.hidden = !hasUrl;
            if (placeholder) placeholder.hidden = hasUrl;
            if (clearBtn) clearBtn.hidden = !hasUrl;
        } else if (block.type === 'field') {
            var f = container.querySelector('[data-field="field"]');
            if (f) { f.innerHTML = fieldMapsToOptionsHtml(block.field || 'name'); f.value = block.field || 'name'; }
            var l = container.querySelector('[data-field="label"]'); if (l) l.value = block.label || '';
            var ph = container.querySelector('[data-field="placeholder"]'); if (ph) ph.value = block.placeholder || '';
            var ht = container.querySelector('[data-field="help_text"]'); if (ht) ht.value = block.help_text || '';
            var r = container.querySelector('[data-field="required"]'); if (r) r.checked = !!block.required;
            toggleNewFieldPanel(container, false);
        }
    }

    function openProperties(uid) {
        var block = findBlock(uid);
        if (!block) return;
        selectedUid = uid;
        var tpl = document.getElementById('tpl-' + block.type);
        propsBody.innerHTML = '';
        if (tpl) {
            var clone = tpl.content.cloneNode(true);
            makeIdsUnique(clone);
            propsBody.appendChild(clone);
            populateFieldsFromBlock(propsBody, block);
            if (window.initCrmImageFields) window.initCrmImageFields(propsBody);
        }
        propsTitle.textContent = TYPE_LABELS[block.type] || block.type;
        propsIcon.className = 'fa ' + (TYPE_ICONS[block.type] || 'fa-cube');
        propsHeader.hidden = false;
        propsEmpty.hidden = true;
        layoutEl.classList.remove('is-preview-hidden');
        switchPanelTab('properties');
        renderCanvas();
    }

    function closeProperties() {
        selectedUid = null;
        propsBody.innerHTML = '';
        propsHeader.hidden = true;
        propsEmpty.hidden = false;
        renderCanvas();
    }

    var projectNavTrigger = document.getElementById('lf-project-nav-trigger');
    var projectNavMenu = document.getElementById('lf-project-nav-menu');
    projectNavTrigger?.addEventListener('click', function (e) {
        e.stopPropagation();
        projectNavMenu.hidden = !projectNavMenu.hidden;
    });
    document.addEventListener('click', function (e) {
        if (projectNavMenu && !projectNavMenu.hidden && !projectNavMenu.contains(e.target) && e.target !== projectNavTrigger) {
            projectNavMenu.hidden = true;
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (selectedUid) closeProperties();
        if (projectNavMenu) projectNavMenu.hidden = true;
    });

    function onPropsFieldChange() {
        if (!selectedUid) return;
        var block = findBlock(selectedUid);
        if (!block) return;
        if (block.type === 'heading') {
            block.text = propsBody.querySelector('[data-field="text"]')?.value || '';
            block.size = propsBody.querySelector('[data-field="size"]')?.value || 'lg';
        } else if (block.type === 'text') {
            block.text = propsBody.querySelector('[data-field="text"]')?.value || '';
        } else if (block.type === 'image') {
            block.file_id = propsBody.querySelector('[data-crm-image-file-id]')?.value || null;
            block.url = propsBody.querySelector('[data-crm-image-url]')?.value || '';
            block.alt = propsBody.querySelector('[data-field="alt"]')?.value || '';
        } else if (block.type === 'field') {
            var mapsToValue = propsBody.querySelector('[data-field="field"]')?.value || 'name';
            if (mapsToValue === '__new__') {
                // Don't commit the placeholder value — the new-field panel takes over
                // until a real field is created (or the user cancels back to the prior value).
                toggleNewFieldPanel(propsBody, true);
            } else {
                toggleNewFieldPanel(propsBody, false);
                block.field = mapsToValue;
            }
            block.label = propsBody.querySelector('[data-field="label"]')?.value || '';
            block.placeholder = propsBody.querySelector('[data-field="placeholder"]')?.value || '';
            block.help_text = propsBody.querySelector('[data-field="help_text"]')?.value || '';
            block.required = !!propsBody.querySelector('[data-field="required"]')?.checked;
        }
        updateCanvasSummary(selectedUid);
        schedulePreview();
    }

    propsBody.addEventListener('input', onPropsFieldChange);
    propsBody.addEventListener('change', onPropsFieldChange);
    propsBody.addEventListener('crm-image:change', onPropsFieldChange);

    // ---- "+ Add new field…" inline creation (Form field blocks) ----
    function toggleNewFieldPanel(container, show) {
        var panel = container.querySelector('[data-lf-new-field-panel]');
        if (!panel) return;
        // Every keystroke inside the panel re-fires the delegated change/input
        // listener, which re-calls this with show=true while "Maps to" is still
        // "__new__" — only reset the panel's own fields on the hidden->shown
        // transition, or the user's in-progress input would be wiped every time.
        var wasHidden = panel.hidden;
        panel.hidden = !show;
        if (show && wasHidden) {
            var labelInput = panel.querySelector('[data-lf-new-field-label]');
            var typeSelect = panel.querySelector('[data-lf-new-field-type]');
            var optionsWrap = panel.querySelector('[data-lf-new-field-options-wrap]');
            var optionsTextarea = panel.querySelector('[data-lf-new-field-options]');
            var errorEl = panel.querySelector('[data-lf-new-field-error]');
            if (labelInput) labelInput.value = '';
            if (typeSelect) typeSelect.value = 'text';
            if (optionsTextarea) optionsTextarea.value = '';
            if (optionsWrap) optionsWrap.hidden = true;
            if (errorEl) { errorEl.hidden = true; errorEl.textContent = ''; }
            requestAnimationFrame(function () { labelInput?.focus(); });
        }
    }

    propsBody.addEventListener('change', function (e) {
        if (e.target.matches('[data-lf-new-field-type]')) {
            var optionsWrap = propsBody.querySelector('[data-lf-new-field-options-wrap]');
            if (optionsWrap) optionsWrap.hidden = e.target.value !== 'select';
        }
    });

    propsBody.addEventListener('click', function (e) {
        if (e.target.closest('[data-lf-new-field-cancel]')) {
            var block = findBlock(selectedUid);
            var fieldSelectEl = propsBody.querySelector('[data-field="field"]');
            if (fieldSelectEl && block) { fieldSelectEl.value = block.field || 'name'; }
            toggleNewFieldPanel(propsBody, false);
            return;
        }
        if (e.target.closest('[data-lf-new-field-create]')) {
            createNewCustomFieldFromPanel();
        }
    });

    function createNewCustomFieldFromPanel() {
        var panel = propsBody.querySelector('[data-lf-new-field-panel]');
        if (!panel || !selectedUid) return;
        var labelInput = panel.querySelector('[data-lf-new-field-label]');
        var typeSelect = panel.querySelector('[data-lf-new-field-type]');
        var optionsTextarea = panel.querySelector('[data-lf-new-field-options]');
        var errorEl = panel.querySelector('[data-lf-new-field-error]');
        var createBtn = panel.querySelector('[data-lf-new-field-create]');
        var label = (labelInput?.value || '').trim();
        var type = typeSelect?.value || 'text';
        var options = optionsTextarea?.value || '';

        function showError(msg) { if (errorEl) { errorEl.textContent = msg; errorEl.hidden = false; } }
        if (errorEl) { errorEl.hidden = true; errorEl.textContent = ''; }

        if (!label) { showError('Enter a label for the new field.'); return; }
        if (type === 'select' && !options.trim()) { showError('Add at least one option, one per line.'); return; }

        if (createBtn) { createBtn.disabled = true; createBtn.textContent = 'Adding…'; }

        fetch(customFieldsStoreUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf(),
            },
            body: JSON.stringify({ label: label, type: type, options: options, is_required: false }),
        })
        .then(function (res) { return res.json().then(function (d) { return { ok: res.ok, d: d }; }); })
        .then(function (r) {
            if (createBtn) { createBtn.disabled = false; createBtn.textContent = 'Add field'; }
            if (!r.ok) {
                var msg = (r.d && r.d.errors) ? Object.values(r.d.errors)[0][0] : 'Could not add field.';
                showError(msg);
                return;
            }

            knownCustomFields.push({ id: r.d.id, label: r.d.label });
            customFieldsMeta[r.d.id] = { type: r.d.type, options: r.d.options || [] };

            var block = findBlock(selectedUid);
            if (!block) return;
            block.field = 'custom:' + r.d.id;

            var fieldSelectEl = propsBody.querySelector('[data-field="field"]');
            if (fieldSelectEl) { fieldSelectEl.innerHTML = fieldMapsToOptionsHtml(block.field); fieldSelectEl.value = block.field; }
            toggleNewFieldPanel(propsBody, false);
            updateCanvasSummary(selectedUid);
            schedulePreview();
        })
        .catch(function () {
            if (createBtn) { createBtn.disabled = false; createBtn.textContent = 'Add field'; }
            showError('Could not add field. Check your connection.');
        });
    }

    // ---- Adding / removing / selecting blocks ----
    function insertBlockAt(type, index) {
        var block = defaultBlockForType(type);
        block._uid = 'b' + (uidCounter++);
        blocksState.splice(index, 0, block);
        openProperties(block._uid);
        renderPreview();
        return block;
    }

    // A "column" can only ever live inside a row, so clicking it (rather than dragging it into a
    // specific row) appends it to the last row on the canvas — auto-creating an empty row first
    // if none exists yet, so the click is never a dead end.
    function addColumnByClick() {
        var lastRow = null;
        for (var i = blocksState.length - 1; i >= 0; i--) {
            if (blocksState[i].type === 'row') { lastRow = blocksState[i]; break; }
        }
        if (!lastRow) {
            lastRow = defaultBlockForType('row');
            lastRow._uid = 'b' + (uidCounter++);
            blocksState.push(lastRow);
        }
        var col = defaultBlockForType('column');
        col._uid = 'b' + (uidCounter++);
        lastRow.blocks.push(col);
        openProperties(col._uid);
        renderPreview();
    }

    document.querySelectorAll('[data-add-block]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var type = btn.getAttribute('data-add-block');
            if (type === 'column') { addColumnByClick(); return; }
            insertBlockAt(type, blocksState.length);
        });
    });

    canvas.addEventListener('click', function (e) {
        var removeBtn = e.target.closest('[data-lf-remove]');
        if (removeBtn) {
            var card = removeBtn.closest('.lf-block');
            var uid = card && card.dataset.uid;
            if (uid) {
                var loc = findBlockLocation(uid);
                if (loc) loc.array.splice(loc.index, 1);
                if (selectedUid === uid) { closeProperties(); } else { renderCanvas(); }
                renderPreview();
            }
            return;
        }
        var body = e.target.closest('.lf-block__body');
        if (body) {
            var blockCard = body.closest('.lf-block');
            if (blockCard && blockCard.dataset.uid) openProperties(blockCard.dataset.uid);
            return;
        }
        if (e.target === canvas && selectedUid) closeProperties();
    });

    var paletteButtons = document.getElementById('lf-palette-buttons');

    // Elementor-style nesting rules: a "column" only ever lives inside a row's slot (a bare
    // column can't float at the top level or sit directly inside another column). A "row" can
    // live at the top level of the canvas OR nested inside a column, so rows/columns can nest
    // arbitrarily deep (row > column > row > column > ...). Every other (leaf) block type lives
    // at the top level or inside a column's slot — never directly inside a row.
    function sharedOnMove(evt) {
        var draggedType = (evt.dragged.dataset && evt.dragged.dataset.blockType) || evt.dragged.getAttribute('data-add-block');
        var to = evt.to;
        if (to === canvas) return draggedType !== 'column';
        if (to.classList.contains('lf-row-slot')) return draggedType === 'column';
        if (to.classList.contains('lf-column-slot')) return draggedType !== 'column';
        return false;
    }

    // Walk the actual DOM (top-level canvas + every row/column slot) and rebuild blocksState to
    // match — reusing each block's existing data object (via uid) so no properties are lost,
    // and turning any raw dragged palette button into a real block. This single reconciliation
    // step is run after every drag operation (reorder, move between slots, drop from palette),
    // which is far more robust than trying to patch multiple interacting Sortable lists by hand.
    function collectBlocksFromContainer(containerEl, uidMap) {
        var result = [];
        Array.from(containerEl.children).forEach(function (child) {
            if (child.classList && child.classList.contains('lf-block')) {
                var uid = child.dataset.uid;
                var block = uid ? uidMap[uid] : null;
                if (!block) return;
                if (block.type === 'row' || block.type === 'column') {
                    var slotEl = child.querySelector(':scope > .lf-block__body > .lf-block-slot');
                    block.blocks = slotEl ? collectBlocksFromContainer(slotEl, uidMap) : [];
                }
                result.push(block);
            } else if (child.hasAttribute && child.hasAttribute('data-add-block')) {
                var newBlock = defaultBlockForType(child.getAttribute('data-add-block'));
                newBlock._uid = 'b' + (uidCounter++);
                result.push(newBlock);
            }
        });
        return result;
    }

    function reconcileTreeFromDom() {
        var uidMap = buildUidMap(blocksState);
        var beforeUids = Object.keys(uidMap);
        var newTop = collectBlocksFromContainer(canvas, uidMap);
        blocksState.length = 0;
        Array.prototype.push.apply(blocksState, newTop);
        var afterMap = buildUidMap(blocksState);
        var newUid = Object.keys(afterMap).find(function (uid) { return beforeUids.indexOf(uid) === -1; });
        if (newUid) {
            openProperties(newUid);
        } else {
            renderCanvas();
        }
        renderPreview();
    }

    if (typeof Sortable !== 'undefined') {
        Sortable.create(canvas, {
            animation: 150,
            handle: '.lf-block__handle',
            ghostClass: 'pcat-sort-ghost',
            chosenClass: 'pcat-sort-chosen',
            draggable: '.lf-block',
            group: { name: 'lf-blocks', pull: true, put: true },
            onMove: sharedOnMove,
            onEnd: reconcileTreeFromDom,
        });

        if (paletteButtons) {
            Sortable.create(paletteButtons, {
                group: { name: 'lf-blocks', pull: 'clone', put: false },
                sort: false,
                animation: 150,
                ghostClass: 'pcat-sort-ghost',
                chosenClass: 'pcat-sort-chosen',
                onMove: sharedOnMove,
                onStart: function () { canvas.classList.add('lf-canvas--drop-target'); },
                onEnd: function () { canvas.classList.remove('lf-canvas--drop-target'); },
            });
        }
    }

    // Safety net: if a drag ever ends without SortableJS's own onEnd firing (a rare
    // clone/nested-list edge case), a raw dragged palette button can be left sitting in the
    // canvas — un-converted, so it renders as a bare unstyled element instead of a real block
    // card. Re-check shortly after every drag-ending signal and self-heal by reconciling if
    // that happened. Native HTML5 drag-and-drop (SortableJS's default, no forceFallback) does
    // not dispatch a normal "mouseup" on drop — it suppresses mouse events in favor of
    // dragstart/dragover/drop/dragend — so "dragend" must be watched too, or a real
    // mouse-driven drag never triggers this check at all.
    function scheduleSelfHealCheck() {
        setTimeout(function () {
            if (canvas.querySelector('[data-add-block]')) reconcileTreeFromDom();
        }, 60);
    }
    document.addEventListener('mouseup', scheduleSelfHealCheck);
    document.addEventListener('dragend', scheduleSelfHealCheck, true);
    document.addEventListener('drop', scheduleSelfHealCheck, true);

    var previewDebounce;
    function schedulePreview() {
        clearTimeout(previewDebounce);
        previewDebounce = setTimeout(renderPreview, 200);
    }
    document.getElementById('lf-submit-text')?.addEventListener('input', schedulePreview);

    var saveBtn = document.getElementById('lf-save-btn');
    var statusEl = document.getElementById('lf-save-status');
    var updateUrl = @json(route('crm.projects.forms.update', [$project, $form]));

    function setStatus(html, cls) {
        statusEl.innerHTML = html || '';
        statusEl.classList.remove('is-saving', 'is-ok', 'is-error');
        if (cls) statusEl.classList.add(cls);
    }

    saveBtn.addEventListener('click', function () {
        saveBtn.disabled = true;
        setStatus('<i class="fa fa-spinner fa-spin"></i> Saving…', 'is-saving');
        fetch(updateUrl, {
            method: 'PUT',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                name: document.getElementById('lf-name-input').value,
                blocks: blocksState.map(stripUidsDeep),
                style: getStyleSettings(),
                submit_button_text: document.getElementById('lf-submit-text').value,
                success_message: document.getElementById('lf-success-message').value,
            }),
        })
        .then(function (res) { return res.json().then(function (d) { return { ok: res.ok, d: d }; }); })
        .then(function (r) {
            if (!r.ok) { setStatus('<i class="fa fa-triangle-exclamation"></i> Could not save.', 'is-error'); return; }
            setStatus('<i class="fa fa-circle-check"></i> Saved', 'is-ok');
            setTimeout(function () { setStatus('', ''); }, 1800);
        })
        .catch(function () { setStatus('<i class="fa fa-triangle-exclamation"></i> Could not save.', 'is-error'); })
        .finally(function () { saveBtn.disabled = false; });
    });

    renderCanvas();
    renderPreview();
})();
</script>
@endsection
