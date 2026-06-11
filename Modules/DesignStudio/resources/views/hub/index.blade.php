@extends('theme::layouts.app', ['title' => 'Design Studio', 'heading' => 'Design Studio'])

@section('content')
<style>
.ds-hub-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:20px;}
.ds-hub-toolbar p{margin:0;font-size:13px;line-height:1.45;color:var(--muted);}
.ds-hub-grid{display:grid;gap:12px;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));margin-top:8px;}
.ds-hub-section{margin:0 0 6px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);display:flex;align-items:center;gap:8px;}
.ds-hub-section::after{content:'';flex:1;height:1px;background:var(--border);}

/* Design cards */
.ds-design-grid{display:grid;gap:14px;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));margin-top:8px;}
.ds-design-card{border:1px solid var(--border);border-radius:12px;overflow:hidden;background:var(--card);transition:border-color .2s,transform .15s;position:relative;}
.ds-design-card:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));transform:translateY(-2px);}
.ds-design-preview{height:120px;background:linear-gradient(135deg,color-mix(in srgb,var(--primary) 8%,var(--bg)) 0%,color-mix(in srgb,var(--primary) 16%,var(--bg)) 100%);display:flex;align-items:center;justify-content:center;font-size:36px;color:var(--primary);position:relative;overflow:hidden;}
.ds-design-preview__canvas{width:100%;height:100%;object-fit:cover;}
.ds-design-info{padding:10px 12px;}
.ds-design-info__title{font-size:13px;font-weight:700;color:var(--text);margin:0 0 3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.ds-design-info__meta{font-size:11px;color:var(--muted);}
.ds-design-actions{display:flex;gap:6px;padding:0 12px 10px;margin-top:2px;}
.ds-design-action{flex:1;padding:5px 8px;border-radius:6px;border:1px solid var(--border);background:transparent;color:var(--muted);font-size:11px;font-weight:700;cursor:pointer;text-align:center;transition:all .15s;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:4px;}
.ds-design-action:hover{background:var(--border);color:var(--text);}
.ds-design-action--del{color:var(--danger,#ef4444);}
.ds-design-action--del:hover{background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.4);color:#ef4444;}

/* New design button */
.ds-new-btn{display:inline-flex;align-items:center;gap:8px;padding:9px 18px;border-radius:10px;background:var(--primary);color:#fff;font-size:13px;font-weight:800;border:none;cursor:pointer;text-decoration:none;transition:background .15s;}
.ds-new-btn:hover{background:color-mix(in srgb,var(--primary) 85%,#000);color:#fff;}

/* Size picker modal */
.ds-modal-backdrop{position:fixed;inset:0;z-index:1050;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.55);backdrop-filter:blur(3px);opacity:0;visibility:hidden;transition:all .2s;}
.ds-modal-backdrop.open{opacity:1;visibility:visible;}
.ds-modal-panel{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:24px;width:min(100% - 32px,460px);box-shadow:0 24px 60px rgba(0,0,0,.3);transform:scale(.95);transition:transform .2s;}
.ds-modal-backdrop.open .ds-modal-panel{transform:scale(1);}
.ds-modal-title{font-size:17px;font-weight:800;letter-spacing:-.02em;margin:0 0 18px;}
.ds-preset-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:16px;}
.ds-preset-btn{padding:10px 8px;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--text);cursor:pointer;text-align:center;transition:all .15s;}
.ds-preset-btn:hover{border-color:var(--primary);background:color-mix(in srgb,var(--primary) 10%,transparent);color:var(--primary);}
.ds-preset-btn__name{font-size:12px;font-weight:700;display:block;margin-bottom:2px;}
.ds-preset-btn__size{font-size:10px;color:var(--muted);}
.ds-modal-divider{display:flex;align-items:center;gap:10px;margin:0 0 14px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);}
.ds-modal-divider::before,.ds-modal-divider::after{content:'';flex:1;height:1px;background:var(--border);}
.ds-custom-row{display:flex;align-items:center;gap:8px;}
.ds-custom-row input{flex:1;background:var(--bg);border:1px solid var(--border);color:var(--text);border-radius:7px;padding:8px 10px;font-size:13px;outline:none;}
.ds-custom-row input:focus{border-color:var(--primary);}
.ds-custom-row span{font-size:13px;color:var(--muted);}
.ds-modal-actions{display:flex;gap:8px;margin-top:16px;justify-content:flex-end;}
.ds-modal-btn{padding:8px 18px;border-radius:8px;font-size:13px;font-weight:700;border:1px solid var(--border);background:transparent;color:var(--text);cursor:pointer;transition:all .15s;}
.ds-modal-btn:hover{background:var(--border);}
.ds-modal-btn--primary{background:var(--primary);border-color:var(--primary);color:#fff;}
.ds-modal-btn--primary:hover{background:color-mix(in srgb,var(--primary) 85%,#000);}

/* Empty state */
.ds-empty{text-align:center;padding:40px 20px;border:2px dashed var(--border);border-radius:12px;color:var(--muted);}
.ds-empty i{font-size:36px;display:block;margin-bottom:12px;opacity:.5;}
.ds-empty h3{margin:0 0 6px;font-size:15px;font-weight:800;color:var(--text);}
.ds-empty p{margin:0 0 16px;font-size:13px;}

/* ── Checklist quick-start cards ─────────────────────────── */
.ds-ql-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px;}
@media(max-width:960px){.ds-ql-grid{grid-template-columns:repeat(2,1fr);}}
@media(max-width:500px){.ds-ql-grid{grid-template-columns:1fr 1fr;}}

.ds-ql-card{
    background:var(--card);border:1.5px solid var(--border);border-radius:14px;
    padding:16px 16px 14px;display:flex;flex-direction:column;
    transition:border-color .2s,transform .15s,box-shadow .2s;
    overflow:hidden;
}
.ds-ql-card:hover{
    border-color:color-mix(in srgb,var(--primary) 55%,var(--border));
    transform:translateY(-2px);
    box-shadow:0 6px 22px color-mix(in srgb,var(--primary) 14%,transparent);
}
.ds-ql-card.checked{
    border-color:color-mix(in srgb,var(--primary) 65%,var(--border));
    background:color-mix(in srgb,var(--primary) 4%,var(--card));
}

/* header row: icon left, checkbox right */
.ds-ql-head{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:12px;}

.ds-ql-icon{
    width:46px;height:46px;border-radius:11px;flex-shrink:0;
    background:color-mix(in srgb,var(--primary) 12%,var(--bg));
    color:var(--primary);display:flex;align-items:center;justify-content:center;
    font-size:20px;transition:background .2s;
}
.ds-ql-card.checked .ds-ql-icon{
    background:var(--primary);color:#fff;
}

.ds-ql-check{
    width:24px;height:24px;border-radius:50%;flex-shrink:0;
    border:2px solid var(--border);background:transparent;
    display:flex;align-items:center;justify-content:center;
    transition:all .2s;
}
.ds-ql-card.checked .ds-ql-check{background:var(--primary);border-color:var(--primary);}
.ds-ql-check i{font-size:11px;color:#fff;display:none;}
.ds-ql-card.checked .ds-ql-check i{display:block;}

/* body text */
.ds-ql-name{font-size:13px;font-weight:800;color:var(--text);margin:0 0 5px;line-height:1.25;}
.ds-ql-desc{font-size:11px;color:var(--muted);margin:0 0 10px;line-height:1.5;}
.ds-ql-size{
    display:inline-block;font-size:10px;font-weight:700;letter-spacing:.03em;
    font-family:monospace;color:var(--primary);
    background:color-mix(in srgb,var(--primary) 10%,transparent);
    border:1px solid color-mix(in srgb,var(--primary) 25%,transparent);
    border-radius:5px;padding:2px 8px;margin-bottom:14px;
}

/* push button to bottom of card */
.ds-ql-btn-wrap{margin-top:auto;}

/* create / edit buttons — both are <button> elements */
.ds-ql-btn{
    display:flex;align-items:center;justify-content:center;gap:6px;
    padding:8px 10px;border-radius:8px;border:1px solid var(--border);
    background:transparent;color:var(--muted);font-size:11px;font-weight:700;
    cursor:pointer;transition:all .15s;width:100%;box-sizing:border-box;
    font-family:inherit;line-height:1;white-space:nowrap;text-decoration:none;
}
.ds-ql-btn:hover{background:var(--primary);border-color:var(--primary);color:#fff;}
.ds-ql-btn--edit{
    background:color-mix(in srgb,var(--primary) 10%,transparent);
    border-color:color-mix(in srgb,var(--primary) 40%,var(--border));
    color:var(--primary);
}
.ds-ql-btn--edit:hover{background:var(--primary);border-color:var(--primary);color:#fff;}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:18px 20px;">

    <div class="ds-hub-toolbar">
        <div>
            <h2 style="margin:0 0 4px;font-size:18px;font-weight:800;letter-spacing:-.02em;">Design Studio</h2>
            <p>Creative workspace for <strong style="color:var(--text);">{{ $business->name }}</strong> — create social media graphics, posters, and marketing materials.</p>
        </div>
        <button class="ds-new-btn" onclick="openNewDesignModal()">
            <i class="fa fa-plus" aria-hidden="true"></i> New Design
        </button>
    </div>

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;margin-bottom:16px;">{{ session('status') }}</div>
    @endif

    {{-- ── Quick-start checklist cards ── --}}
    <p class="ds-hub-section" style="margin-bottom:10px;">
        <i class="fa fa-list-check" aria-hidden="true"></i> Quick Start
    </p>
    @php
        $qlItems = [
            ['type'=>'letterhead',      'label'=>'Letter Head',     'icon'=>'fa-file-lines',  'w'=>794,  'h'=>1123, 'desc'=>'Official company letterhead for correspondence and documents.'],
            ['type'=>'company-profile', 'label'=>'Company Profile', 'icon'=>'fa-building',    'w'=>1920, 'h'=>1080, 'desc'=>'Presentation-style company profile for clients and partners.'],
            ['type'=>'social-media',    'label'=>'Social Media',    'icon'=>'fa-share-nodes', 'w'=>1080, 'h'=>1080, 'desc'=>'Eye-catching posts for Instagram, Facebook and LinkedIn.'],
            ['type'=>'business-card',   'label'=>'Business Card',   'icon'=>'fa-id-card',     'w'=>1050, 'h'=>600,  'desc'=>'Professional business card for networking and branding.'],
        ];
    @endphp
    <div class="ds-ql-grid">
        @foreach($qlItems as $item)
            @php $existing = $designsByType->get($item['type']); @endphp
            <div class="ds-ql-card {{ $existing ? 'checked' : '' }}">
                <div class="ds-ql-head">
                    <div class="ds-ql-icon"><i class="fa {{ $item['icon'] }}" aria-hidden="true"></i></div>
                    <div class="ds-ql-check"><i class="fa fa-check"></i></div>
                </div>
                <p class="ds-ql-name">{{ $item['label'] }}</p>
                <p class="ds-ql-desc">{{ $item['desc'] }}</p>
                <span class="ds-ql-size">{{ $item['w'] }} × {{ $item['h'] }} px</span>
                <div class="ds-ql-btn-wrap">
                    @if($item['type'] === 'social-media')
                        <a href="{{ route('designstudio.social-media.index') }}" class="ds-ql-btn">
                            <i class="fa fa-share-nodes" aria-hidden="true"></i> Manage Post
                        </a>
                    @elseif($existing)
                        @if($item['type'] === 'letterhead')
                        <div style="display:flex;gap:6px;">
                            <button class="ds-ql-btn ds-ql-btn--edit" style="flex:1;" onclick="window.location.href='{{ route('designstudio.editor.edit', $existing) }}'">
                                <i class="fa fa-pen" aria-hidden="true"></i> Edit Design
                            </button>
                                <a href="{{ route('designstudio.letterhead.links') }}" class="ds-ql-btn" style="flex:0 0 auto;width:auto;padding:8px 12px;" title="View connections">
                                <i class="fa fa-diagram-project" aria-hidden="true"></i>
                            </a>
                        </div>
                        @else
                        <button class="ds-ql-btn ds-ql-btn--edit" onclick="window.location.href='{{ route('designstudio.editor.edit', $existing) }}'">
                            <i class="fa fa-pen" aria-hidden="true"></i> Edit Design
                        </button>
                        @endif
                    @elseif($item['type'] === 'company-profile')
                        <button class="ds-ql-btn" onclick="openCpWizard()">
                            <i class="fa fa-pen-to-square" aria-hidden="true"></i> Create Design
                        </button>
                    @elseif($item['type'] === 'letterhead')
                        <button class="ds-ql-btn" onclick="openLhChoiceModal()">
                            <i class="fa fa-pen-to-square" aria-hidden="true"></i> Create Design
                        </button>
                    @else
                        <button class="ds-ql-btn" onclick="startEditor({{ $item['w'] }},{{ $item['h'] }},'{{ $item['type'] }}')">
                            <i class="fa fa-pen-to-square" aria-hidden="true"></i> Create Design
                        </button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
    {{-- ── /Quick-start ── --}}

    <p class="ds-hub-section"><i class="fa fa-pencil-ruler" aria-hidden="true"></i> My Designs ({{ $designs->count() }})</p>

    @if($designs->isEmpty())
        <div class="ds-empty">
            <i class="fa fa-palette" aria-hidden="true"></i>
            <h3>No designs yet</h3>
            <p>Create your first design — social media post, banner, poster, or any custom size.</p>
            <button class="ds-new-btn" onclick="openNewDesignModal()">
                <i class="fa fa-plus" aria-hidden="true"></i> Create First Design
            </button>
        </div>
    @else
        <div class="ds-design-grid">
            @foreach($designs as $d)
                <div class="ds-design-card">
                    <div class="ds-design-preview">
                        <i class="fa fa-palette" aria-hidden="true"></i>
                    </div>
                    <div class="ds-design-info">
                        <p class="ds-design-info__title">{{ $d->title }}</p>
                        <p class="ds-design-info__meta">{{ $d->width }} × {{ $d->height }}px &bull; {{ $d->updated_at->diffForHumans() }}</p>
                    </div>
                    <div class="ds-design-actions">
                        <a href="{{ route('designstudio.editor.edit', $d) }}" class="ds-design-action">
                            <i class="fa fa-pen" aria-hidden="true"></i> Edit
                        </a>
                        <form action="{{ route('designstudio.designs.destroy', $d) }}" method="POST" onsubmit="return confirm('Delete this design?')" style="flex:1;display:contents;">
                            @csrf @method('DELETE')
                            <button type="submit" class="ds-design-action ds-design-action--del">
                                <i class="fa fa-trash" aria-hidden="true"></i>
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach

            {{-- "New design" card --}}
            <div class="ds-design-card" onclick="openNewDesignModal()" style="cursor:pointer;border-style:dashed;">
                <div class="ds-design-preview" style="background:transparent;">
                    <i class="fa fa-plus" aria-hidden="true" style="font-size:28px;opacity:.4;"></i>
                </div>
                <div class="ds-design-info" style="text-align:center;">
                    <p class="ds-design-info__title">New Design</p>
                    <p class="ds-design-info__meta">Start from scratch</p>
                </div>
            </div>
        </div>
    @endif

</div>

{{-- New Design Size Picker Modal --}}
<div class="ds-modal-backdrop" id="dsNewModal">
    <div class="ds-modal-panel">
        <h3 class="ds-modal-title"><i class="fa fa-palette" aria-hidden="true" style="color:var(--primary);margin-right:8px;"></i>Choose canvas size</h3>

        <div class="ds-preset-grid" id="dsPresetGrid">
            <button class="ds-preset-btn" onclick="startEditor(1080,1080)">
                <span class="ds-preset-btn__name">Instagram Post</span>
                <span class="ds-preset-btn__size">1080 × 1080</span>
            </button>
            <button class="ds-preset-btn" onclick="startEditor(1080,1920)">
                <span class="ds-preset-btn__name">Story / Reel</span>
                <span class="ds-preset-btn__size">1080 × 1920</span>
            </button>
            <button class="ds-preset-btn" onclick="startEditor(1200,630)">
                <span class="ds-preset-btn__name">Facebook Post</span>
                <span class="ds-preset-btn__size">1200 × 630</span>
            </button>
            <button class="ds-preset-btn" onclick="startEditor(1280,720)">
                <span class="ds-preset-btn__name">YouTube Thumb</span>
                <span class="ds-preset-btn__size">1280 × 720</span>
            </button>
            <button class="ds-preset-btn" onclick="startEditor(794,1123)">
                <span class="ds-preset-btn__name">A4 Portrait</span>
                <span class="ds-preset-btn__size">794 × 1123</span>
            </button>
            <button class="ds-preset-btn" onclick="startEditor(1500,500)">
                <span class="ds-preset-btn__name">Twitter Banner</span>
                <span class="ds-preset-btn__size">1500 × 500</span>
            </button>
            <button class="ds-preset-btn" onclick="startEditor(1050,600)">
                <span class="ds-preset-btn__name">Business Card</span>
                <span class="ds-preset-btn__size">1050 × 600</span>
            </button>
            <button class="ds-preset-btn" onclick="startEditor(600,400)">
                <span class="ds-preset-btn__name">Email Banner</span>
                <span class="ds-preset-btn__size">600 × 400</span>
            </button>
            <button class="ds-preset-btn" onclick="startEditor(2480,3508)">
                <span class="ds-preset-btn__name">A4 Print</span>
                <span class="ds-preset-btn__size">2480 × 3508</span>
            </button>
        </div>

        <div class="ds-modal-divider">or custom size</div>

        <div class="ds-custom-row">
            <input type="number" id="dsCustomW" placeholder="Width" value="1080" min="100" max="8000">
            <span>×</span>
            <input type="number" id="dsCustomH" placeholder="Height" value="1080" min="100" max="8000">
            <span style="font-size:11px;color:var(--muted);white-space:nowrap;">px</span>
        </div>

        <div class="ds-modal-actions">
            <button class="ds-modal-btn" onclick="closeNewDesignModal()">Cancel</button>
            <button class="ds-modal-btn ds-modal-btn--primary" onclick="startCustomEditor()">
                <i class="fa fa-wand-magic-sparkles" aria-hidden="true"></i> Open Editor
            </button>
        </div>
    </div>
</div>

<script>
function openNewDesignModal() {
    document.getElementById('dsNewModal').classList.add('open');
}
function closeNewDesignModal() {
    document.getElementById('dsNewModal').classList.remove('open');
}
document.getElementById('dsNewModal').addEventListener('click', function(e) {
    if (e.target === this) closeNewDesignModal();
});
function startEditor(w, h, type) {
    var url = '{{ route('designstudio.editor.create') }}?w=' + w + '&h=' + h;
    if (type) url += '&type=' + encodeURIComponent(type);
    window.location.href = url;
}
function startCustomEditor() {
    var w = parseInt(document.getElementById('dsCustomW').value) || 1080;
    var h = parseInt(document.getElementById('dsCustomH').value) || 1080;
    w = Math.max(100, Math.min(8000, w));
    h = Math.max(100, Math.min(8000, h));
    startEditor(w, h);
}
</script>

{{-- ══════════════════════════════════════════════════════
     COMPANY PROFILE WIZARD MODAL
     ══════════════════════════════════════════════════════ --}}
<style>
/* ── Backdrop ── */
.cpw-backdrop{
    position:fixed;inset:0;z-index:1100;display:flex;align-items:center;justify-content:center;
    background:rgba(0,0,0,.6);backdrop-filter:blur(4px);
    opacity:0;visibility:hidden;transition:opacity .25s,visibility .25s;
}
.cpw-backdrop.open{opacity:1;visibility:visible;}

/* ── Panel ── */
.cpw-panel{
    background:var(--card);border:1px solid var(--border);border-radius:20px;
    width:min(100% - 32px, 680px);max-height:calc(100vh - 48px);
    display:flex;flex-direction:column;overflow:hidden;
    box-shadow:0 32px 80px rgba(0,0,0,.35);
    transform:scale(.96) translateY(10px);transition:transform .25s;
    position:relative;
}
.cpw-backdrop.open .cpw-panel{transform:scale(1) translateY(0);}

/* ── Header ── */
.cpw-header{
    flex-shrink:0;padding:20px 24px 16px;
    border-bottom:1px solid var(--border);
    display:flex;align-items:flex-start;gap:14px;
}
.cpw-header-icon{
    width:44px;height:44px;border-radius:12px;flex-shrink:0;
    background:color-mix(in srgb,var(--primary) 14%,var(--bg));
    color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:18px;
}
.cpw-header-text{flex:1;min-width:0;}
.cpw-header-text h2{margin:0 0 2px;font-size:17px;font-weight:800;letter-spacing:-.02em;}
.cpw-header-text p{margin:0;font-size:12px;color:var(--muted);}
.cpw-close{
    width:34px;height:34px;border-radius:9px;border:1px solid var(--border);
    background:transparent;color:var(--muted);cursor:pointer;
    display:flex;align-items:center;justify-content:center;font-size:14px;
    transition:all .15s;flex-shrink:0;
}
.cpw-close:hover{background:color-mix(in srgb,var(--text) 6%,transparent);color:var(--text);}

/* ── Steps bar ── */
.cpw-steps-bar{
    flex-shrink:0;display:flex;align-items:center;justify-content:center;
    gap:0;padding:14px 24px;border-bottom:1px solid var(--border);
    background:color-mix(in srgb,var(--bg) 50%,var(--card));
}
.cpw-step-item{display:flex;flex-direction:column;align-items:center;gap:4px;flex:0 0 auto;}
.cpw-step-dot{
    width:32px;height:32px;border-radius:50%;border:2px solid var(--border);
    background:var(--bg);color:var(--muted);
    display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;
    transition:all .22s;position:relative;
}
.cpw-step-item.active .cpw-step-dot{
    background:var(--primary);border-color:var(--primary);color:#fff;
    box-shadow:0 0 0 4px color-mix(in srgb,var(--primary) 20%,transparent);
}
.cpw-step-item.done .cpw-step-dot{
    background:color-mix(in srgb,var(--primary) 14%,var(--bg));
    border-color:var(--primary);color:var(--primary);
}
.cpw-step-lbl{font-size:10px;font-weight:700;color:var(--muted);white-space:nowrap;letter-spacing:.02em;}
.cpw-step-item.active .cpw-step-lbl,.cpw-step-item.done .cpw-step-lbl{color:var(--primary);}
.cpw-step-line{flex:1;height:2px;background:var(--border);margin:0 4px;margin-bottom:16px;min-width:20px;transition:background .22s;}
.cpw-step-line.done{background:var(--primary);}

/* ── Body ── */
.cpw-body{flex:1;overflow-y:auto;padding:24px;-webkit-overflow-scrolling:touch;}

/* ── Page header ── */
.cpw-page-hdr{display:flex;align-items:center;gap:12px;margin-bottom:22px;}
.cpw-page-hdr-icon{
    width:42px;height:42px;border-radius:11px;flex-shrink:0;
    background:color-mix(in srgb,var(--primary) 12%,var(--bg));
    color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:16px;
}
.cpw-page-hdr h3{margin:0 0 2px;font-size:16px;font-weight:800;}
.cpw-page-hdr p{margin:0;font-size:12px;color:var(--muted);}

/* ── Form fields ── */
.cpw-field{margin-bottom:16px;}
.cpw-field label{display:block;font-size:12px;font-weight:700;color:var(--text);margin-bottom:6px;letter-spacing:.01em;}
.cpw-field label small{font-weight:400;color:var(--muted);margin-left:4px;}
.cpw-input,.cpw-textarea{
    width:100%;box-sizing:border-box;
    background:var(--bg);border:1.5px solid var(--border);color:var(--text);
    border-radius:9px;padding:10px 12px;font-size:13px;font-family:inherit;
    outline:none;transition:border-color .15s,box-shadow .15s;
    resize:vertical;
}
.cpw-input:focus,.cpw-textarea:focus{
    border-color:var(--primary);
    box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 12%,transparent);
}
.cpw-textarea{min-height:100px;line-height:1.5;}

/* ── 2-col grid ── */
.cpw-grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
@media(max-width:480px){.cpw-grid2{grid-template-columns:1fr;}}

/* ── Logo row (step 1) ── */
.cpw-logo-row{display:flex;gap:16px;margin-bottom:20px;align-items:flex-start;}
.cpw-logo-box{
    flex-shrink:0;width:88px;text-align:center;
}
.cpw-logo-img{
    width:80px;height:80px;border-radius:16px;object-fit:contain;
    border:1.5px solid var(--border);background:var(--bg);display:block;
}
.cpw-logo-hint{font-size:10px;color:var(--muted);display:block;margin-top:5px;line-height:1.3;}
.cpw-logo-fields{flex:1;min-width:0;}

/* ── Color swatches ── */
.cpw-swatches{display:flex;flex-wrap:wrap;gap:8px;align-items:center;}
.cpw-swatch{
    width:32px;height:32px;border-radius:50%;border:3px solid transparent;
    cursor:pointer;transition:transform .15s,box-shadow .15s,border-color .15s;
    padding:0;background:none;
}
.cpw-swatch:hover{transform:scale(1.12);}
.cpw-swatch.sel{border-color:var(--text);box-shadow:0 0 0 3px color-mix(in srgb,var(--text) 20%,transparent);}
.cpw-swatch-custom{
    width:32px;height:32px;border-radius:50%;border:1.5px dashed var(--border);
    cursor:pointer;display:flex;align-items:center;justify-content:center;
    font-size:12px;color:var(--muted);transition:all .15s;overflow:hidden;position:relative;
}
.cpw-swatch-custom:hover{border-color:var(--primary);color:var(--primary);}
.cpw-swatch-custom input[type=color]{
    position:absolute;inset:0;opacity:0;width:100%;height:100%;cursor:pointer;
}

/* ── Features list ── */
.cpw-features{display:flex;flex-direction:column;gap:8px;}
.cpw-feature-row{display:flex;gap:6px;align-items:center;}
.cpw-feature-row .cpw-input{margin:0;}
.cpw-feature-del{
    width:30px;height:36px;flex-shrink:0;border-radius:7px;border:1px solid var(--border);
    background:transparent;color:var(--muted);cursor:pointer;font-size:12px;
    display:flex;align-items:center;justify-content:center;transition:all .15s;
}
.cpw-feature-del:hover{background:rgba(239,68,68,.08);border-color:rgba(239,68,68,.4);color:#ef4444;}
.cpw-add-btn{
    display:inline-flex;align-items:center;gap:5px;padding:7px 12px;border-radius:8px;
    border:1.5px dashed var(--border);background:transparent;color:var(--muted);
    font-size:12px;font-weight:700;cursor:pointer;margin-top:4px;font-family:inherit;
    transition:all .15s;
}
.cpw-add-btn:hover{border-color:var(--primary);color:var(--primary);}

/* ── Social row ── */
.cpw-social-row{display:flex;align-items:center;gap:10px;margin-bottom:12px;}
.cpw-social-icon{
    width:36px;height:36px;border-radius:9px;flex-shrink:0;
    display:flex;align-items:center;justify-content:center;font-size:15px;
    background:color-mix(in srgb,var(--primary) 10%,var(--bg));color:var(--primary);
}
.cpw-social-row .cpw-input{margin:0;}

/* ── Review page ── */
.cpw-review{background:var(--bg);border:1px solid var(--border);border-radius:14px;padding:18px;margin-bottom:20px;}
.cpw-review-section{margin-bottom:14px;}
.cpw-review-section:last-child{margin-bottom:0;}
.cpw-review-label{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:6px;}
.cpw-review-row{display:flex;align-items:baseline;gap:8px;margin-bottom:4px;}
.cpw-review-key{font-size:11px;color:var(--muted);min-width:80px;flex-shrink:0;}
.cpw-review-val{font-size:13px;color:var(--text);font-weight:600;word-break:break-word;}
.cpw-review-logo{width:48px;height:48px;border-radius:10px;object-fit:contain;border:1px solid var(--border);background:var(--bg);}
.cpw-review-colors{display:flex;gap:6px;align-items:center;}
.cpw-review-color-dot{width:16px;height:16px;border-radius:50%;}

/* ── Generate button ── */
.cpw-gen-wrap{text-align:center;padding:8px 0 4px;}
.cpw-gen-btn{
    display:inline-flex;align-items:center;justify-content:center;gap:10px;
    padding:16px 40px;border-radius:14px;border:none;cursor:pointer;font-family:inherit;
    background:linear-gradient(135deg,var(--primary),color-mix(in srgb,var(--primary) 70%,#7c3aed));
    color:#fff;font-size:16px;font-weight:800;letter-spacing:-.01em;
    box-shadow:0 8px 32px color-mix(in srgb,var(--primary) 35%,transparent);
    transition:transform .18s,box-shadow .18s,filter .18s;position:relative;overflow:hidden;
}
.cpw-gen-btn:hover{transform:translateY(-2px);box-shadow:0 14px 40px color-mix(in srgb,var(--primary) 45%,transparent);filter:brightness(1.06);}
.cpw-gen-btn:active{transform:translateY(0);box-shadow:0 4px 16px color-mix(in srgb,var(--primary) 25%,transparent);}
.cpw-gen-btn .cpw-gen-shine{
    position:absolute;top:-50%;left:-60%;width:40%;height:200%;
    background:linear-gradient(90deg,transparent,rgba(255,255,255,.25),transparent);
    transform:skewX(-15deg);
    animation:cpwShine 2.4s ease-in-out infinite;
}
@keyframes cpwShine{0%,100%{left:-60%}50%{left:120%}}
.cpw-gen-sub{font-size:12px;color:var(--muted);margin-top:8px;}

/* ── Footer ── */
.cpw-footer{
    flex-shrink:0;display:flex;align-items:center;justify-content:space-between;
    padding:14px 24px;border-top:1px solid var(--border);gap:10px;
    background:color-mix(in srgb,var(--bg) 50%,var(--card));
}
.cpw-btn{
    display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:9px;
    border:1px solid var(--border);background:transparent;color:var(--text);
    font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;
    transition:all .15s;
}
.cpw-btn:hover{background:color-mix(in srgb,var(--text) 6%,transparent);}
.cpw-btn:disabled{opacity:.35;pointer-events:none;}
.cpw-btn-primary{background:var(--primary);border-color:var(--primary);color:#fff;}
.cpw-btn-primary:hover{background:color-mix(in srgb,var(--primary) 88%,#000);}
.cpw-pg-dots{display:flex;gap:5px;align-items:center;}
.cpw-pg-dot{width:6px;height:6px;border-radius:50%;background:var(--border);transition:all .2s;}
.cpw-pg-dot.on{background:var(--primary);width:18px;border-radius:3px;}

/* ── AI Loading Overlay ── */
.cpw-loading{
    position:absolute;inset:0;z-index:20;border-radius:20px;
    background:var(--card);
    display:none;flex-direction:column;align-items:center;justify-content:center;
    padding:40px 32px;text-align:center;gap:0;
}
.cpw-loading.on{display:flex;}
.cpw-loading-orb{
    width:72px;height:72px;border-radius:50%;
    background:linear-gradient(135deg,var(--primary),color-mix(in srgb,var(--primary) 60%,#7c3aed));
    display:flex;align-items:center;justify-content:center;
    font-size:28px;color:#fff;margin-bottom:20px;
    box-shadow:0 0 0 0 color-mix(in srgb,var(--primary) 40%,transparent);
    animation:cpwPulse 2s ease-in-out infinite;
}
@keyframes cpwPulse{
    0%,100%{box-shadow:0 0 0 0 color-mix(in srgb,var(--primary) 40%,transparent);}
    50%{box-shadow:0 0 0 20px color-mix(in srgb,var(--primary) 0%,transparent);}
}
.cpw-loading h3{margin:0 0 6px;font-size:19px;font-weight:800;letter-spacing:-.02em;}
.cpw-loading-subtitle{margin:0 0 28px;font-size:13px;color:var(--muted);}
.cpw-loading-steps{display:flex;flex-direction:column;gap:14px;width:100%;max-width:320px;margin-bottom:28px;}
.cpw-lstep{display:flex;align-items:center;gap:12px;}
.cpw-lstep-dot{
    width:22px;height:22px;border-radius:50%;flex-shrink:0;
    border:2px solid var(--border);background:var(--bg);
    display:flex;align-items:center;justify-content:center;transition:all .3s;
}
.cpw-lstep.active .cpw-lstep-dot{
    border-color:var(--primary);background:var(--primary);
    animation:cpwSpin 1s linear infinite;
}
@keyframes cpwSpin{to{box-shadow:inset 0 0 0 3px rgba(255,255,255,.4),0 0 8px color-mix(in srgb,var(--primary) 60%,transparent);}}
.cpw-lstep.done .cpw-lstep-dot{
    border-color:var(--primary);background:var(--primary);color:#fff;font-size:11px;
}
.cpw-lstep.done .cpw-lstep-dot::after{content:'✓';color:#fff;font-size:11px;font-weight:800;}
.cpw-lstep-text{font-size:13px;font-weight:600;color:var(--muted);transition:color .3s;}
.cpw-lstep.active .cpw-lstep-text{color:var(--text);}
.cpw-lstep.done .cpw-lstep-text{color:var(--primary);text-decoration:line-through;text-decoration-thickness:1px;}
.cpw-loading-hint{font-size:11px;color:var(--muted);margin:0;}
.cpw-loading-error{
    margin-top:16px;padding:10px 14px;border-radius:9px;
    background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);
    color:#ef4444;font-size:12px;font-weight:600;display:none;
}
.cpw-loading-error.on{display:block;}
.cpw-loading-retry-btn{
    margin-top:14px;padding:9px 20px;border-radius:9px;border:1px solid var(--border);
    background:transparent;color:var(--text);font-size:13px;font-weight:700;
    cursor:pointer;font-family:inherit;display:none;transition:all .15s;
}
.cpw-loading-retry-btn.on{display:inline-block;}
.cpw-loading-retry-btn:hover{background:var(--primary);border-color:var(--primary);color:#fff;}
</style>

<style>
/* ═══════════════════════════════════════════════════
   LETTER HEAD CHOICE MODAL
   ═══════════════════════════════════════════════════ */
.lhc-backdrop{
    position:fixed;inset:0;z-index:1200;display:flex;align-items:center;justify-content:center;
    background:rgba(0,0,0,.6);backdrop-filter:blur(4px);
    opacity:0;visibility:hidden;transition:opacity .25s,visibility .25s;
}
.lhc-backdrop.open{opacity:1;visibility:visible;}
.lhc-panel{
    background:var(--card);border:1px solid var(--border);border-radius:20px;
    width:min(100% - 32px,540px);max-height:calc(100vh - 48px);
    display:flex;flex-direction:column;overflow:hidden;
    box-shadow:0 32px 80px rgba(0,0,0,.35);
    transform:scale(.96) translateY(10px);transition:transform .25s;
    position:relative;
}
.lhc-backdrop.open .lhc-panel{transform:scale(1) translateY(0);}
.lhc-header{
    flex-shrink:0;padding:20px 24px 16px;border-bottom:1px solid var(--border);
    display:flex;align-items:flex-start;gap:14px;
}
.lhc-header-icon{
    width:44px;height:44px;border-radius:12px;flex-shrink:0;
    background:color-mix(in srgb,var(--primary) 14%,var(--bg));
    color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:18px;
}
.lhc-header-text{flex:1;min-width:0;}
.lhc-header-text h2{margin:0 0 2px;font-size:17px;font-weight:800;letter-spacing:-.02em;}
.lhc-header-text p{margin:0;font-size:12px;color:var(--muted);}
.lhc-close{
    width:34px;height:34px;border-radius:9px;border:1px solid var(--border);
    background:transparent;color:var(--muted);cursor:pointer;
    display:flex;align-items:center;justify-content:center;font-size:14px;
    transition:all .15s;flex-shrink:0;
}
.lhc-close:hover{background:color-mix(in srgb,var(--text) 6%,transparent);color:var(--text);}
.lhc-choices{display:grid;grid-template-columns:1fr 1fr;gap:14px;padding:22px 24px 20px;}
@media(max-width:440px){.lhc-choices{grid-template-columns:1fr;}}
.lhc-card{
    position:relative;background:var(--bg);border:2px solid var(--border);border-radius:16px;
    padding:26px 18px 20px;cursor:pointer;
    display:flex;flex-direction:column;align-items:center;text-align:center;gap:10px;
    transition:border-color .18s,transform .15s,box-shadow .18s;
}
.lhc-card:hover{
    border-color:var(--primary);transform:translateY(-2px);
    box-shadow:0 8px 24px color-mix(in srgb,var(--primary) 18%,transparent);
}
.lhc-card-icon{
    width:54px;height:54px;border-radius:14px;flex-shrink:0;
    background:color-mix(in srgb,var(--primary) 12%,var(--bg));
    color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:22px;
    transition:background .18s,color .18s;
}
.lhc-card:hover .lhc-card-icon{background:var(--primary);color:#fff;}
.lhc-card h3{margin:0;font-size:14px;font-weight:800;color:var(--text);}
.lhc-card p{margin:0;font-size:11px;color:var(--muted);line-height:1.55;}
.lhc-badge{
    position:absolute;top:-11px;right:12px;
    background:var(--primary);color:#fff;
    font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;
    padding:3px 9px;border-radius:20px;
}
.lhc-form-body{flex:1;overflow-y:auto;padding:20px 24px;-webkit-overflow-scrolling:touch;}
.lhc-field{margin-bottom:13px;}
.lhc-field label{display:block;font-size:12px;font-weight:700;color:var(--text);margin-bottom:5px;}
.lhc-input{
    width:100%;box-sizing:border-box;
    background:var(--bg);border:1.5px solid var(--border);color:var(--text);
    border-radius:9px;padding:9px 12px;font-size:13px;font-family:inherit;
    outline:none;transition:border-color .15s;
}
.lhc-input:focus{border-color:var(--primary);}
.lhc-grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
@media(max-width:440px){.lhc-grid2{grid-template-columns:1fr;}}
.lhc-footer{
    flex-shrink:0;display:flex;align-items:center;justify-content:flex-end;gap:10px;
    padding:14px 24px;border-top:1px solid var(--border);
    background:color-mix(in srgb,var(--bg) 50%,var(--card));
}
.lhc-btn{
    display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:9px;
    border:1px solid var(--border);background:transparent;color:var(--text);
    font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;transition:all .15s;
}
.lhc-btn:hover{background:color-mix(in srgb,var(--text) 6%,transparent);}
.lhc-btn-primary{
    background:linear-gradient(135deg,var(--primary),color-mix(in srgb,var(--primary) 70%,#7c3aed));
    border-color:var(--primary);color:#fff;
    box-shadow:0 4px 16px color-mix(in srgb,var(--primary) 28%,transparent);
}
.lhc-btn-primary:hover{filter:brightness(1.07);}
.lhc-loading{
    position:absolute;inset:0;z-index:20;border-radius:20px;
    background:var(--card);
    display:none;flex-direction:column;align-items:center;justify-content:center;
    padding:36px 32px;text-align:center;
}
.lhc-loading.on{display:flex;}
.lhc-loading-orb{
    width:64px;height:64px;border-radius:50%;
    background:linear-gradient(135deg,var(--primary),color-mix(in srgb,var(--primary) 60%,#7c3aed));
    display:flex;align-items:center;justify-content:center;
    font-size:24px;color:#fff;margin-bottom:18px;
    animation:lhcPulse 2s ease-in-out infinite;
}
@keyframes lhcPulse{
    0%,100%{box-shadow:0 0 0 0 color-mix(in srgb,var(--primary) 40%,transparent);}
    50%{box-shadow:0 0 0 18px color-mix(in srgb,var(--primary) 0%,transparent);}
}
.lhc-loading h3{margin:0 0 5px;font-size:18px;font-weight:800;letter-spacing:-.02em;}
.lhc-loading-sub{margin:0 0 22px;font-size:12px;color:var(--muted);}
.lhc-lsteps{display:flex;flex-direction:column;gap:12px;width:100%;max-width:280px;}
.lhc-lstep{display:flex;align-items:center;gap:10px;}
.lhc-lstep-dot{
    width:20px;height:20px;border-radius:50%;flex-shrink:0;
    border:2px solid var(--border);background:var(--bg);
    display:flex;align-items:center;justify-content:center;transition:all .3s;
}
.lhc-lstep.active .lhc-lstep-dot{border-color:var(--primary);background:var(--primary);animation:cpwSpin 1s linear infinite;}
.lhc-lstep.done .lhc-lstep-dot{border-color:var(--primary);background:var(--primary);}
.lhc-lstep.done .lhc-lstep-dot::after{content:'✓';color:#fff;font-size:10px;font-weight:800;}
.lhc-lstep-text{font-size:12px;font-weight:600;color:var(--muted);transition:color .3s;}
.lhc-lstep.active .lhc-lstep-text{color:var(--text);}
.lhc-lstep.done .lhc-lstep-text{color:var(--primary);text-decoration:line-through;text-decoration-thickness:1px;}
.lhc-err{
    margin-top:14px;padding:9px 14px;border-radius:9px;
    background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);
    color:#ef4444;font-size:12px;font-weight:600;display:none;
}
.lhc-err.on{display:block;}
</style>

{{-- ── Wizard HTML ── --}}
<div class="cpw-backdrop" id="cpwBackdrop">
<div class="cpw-panel">

    {{-- Header --}}
    <div class="cpw-header">
        <div class="cpw-header-icon"><i class="fa fa-building" aria-hidden="true"></i></div>
        <div class="cpw-header-text">
            <h2>Create Company Profile</h2>
            <p>Fill in your business details to generate a professional multi-page design</p>
        </div>
        <button class="cpw-close" onclick="closeCpWizard()" title="Close"><i class="fa fa-times" aria-hidden="true"></i></button>
    </div>

    {{-- Steps bar --}}
    <div class="cpw-steps-bar" id="cpwStepsBar">
        <div class="cpw-step-item active" data-s="1">
            <div class="cpw-step-dot"><i class="fa fa-id-card" aria-hidden="true"></i></div>
            <span class="cpw-step-lbl">Identity</span>
        </div>
        <div class="cpw-step-line" id="cpwLine1"></div>
        <div class="cpw-step-item" data-s="2">
            <div class="cpw-step-dot"><i class="fa fa-align-left" aria-hidden="true"></i></div>
            <span class="cpw-step-lbl">About</span>
        </div>
        <div class="cpw-step-line" id="cpwLine2"></div>
        <div class="cpw-step-item" data-s="3">
            <div class="cpw-step-dot"><i class="fa fa-phone" aria-hidden="true"></i></div>
            <span class="cpw-step-lbl">Contact</span>
        </div>
        <div class="cpw-step-line" id="cpwLine3"></div>
        <div class="cpw-step-item" data-s="4">
            <div class="cpw-step-dot"><i class="fa fa-share-nodes" aria-hidden="true"></i></div>
            <span class="cpw-step-lbl">Social</span>
        </div>
        <div class="cpw-step-line" id="cpwLine4"></div>
        <div class="cpw-step-item" data-s="5">
            <div class="cpw-step-dot"><i class="fa fa-wand-magic-sparkles" aria-hidden="true"></i></div>
            <span class="cpw-step-lbl">Generate</span>
        </div>
    </div>

    {{-- Body --}}
    <div class="cpw-body">

        {{-- Step 1: Identity --}}
        <div class="cpw-page" id="cpwPage1">
            <div class="cpw-page-hdr">
                <div class="cpw-page-hdr-icon"><i class="fa fa-id-card" aria-hidden="true"></i></div>
                <div><h3>Business Identity</h3><p>Your brand's core information</p></div>
            </div>
            <div class="cpw-logo-row">
                <div class="cpw-logo-box">
                    <img class="cpw-logo-img" id="cpwLogoImg" src="{{ $business->displayLogoUrl() }}" alt="Logo">
                    <span class="cpw-logo-hint">{{ $business->hasCustomLogo() ? 'Current logo' : 'No logo yet' }}</span>
                </div>
                <div class="cpw-logo-fields">
                    <div class="cpw-field" style="margin-bottom:12px;">
                        <label>Company Name</label>
                        <input class="cpw-input" id="cpwName" type="text" value="{{ $business->name }}" placeholder="Your company name">
                    </div>
                    <div class="cpw-field" style="margin-bottom:0;">
                        <label>Tagline <small>Short phrase about your business</small></label>
                        <input class="cpw-input" id="cpwTagline" type="text" value="{{ $business->short_description ?? '' }}" placeholder="Your trusted business partner">
                    </div>
                </div>
            </div>
            <div class="cpw-field">
                <label>Theme Color <small>Accent color for your design</small></label>
                <div class="cpw-swatches" id="cpwSwatches">
                    <button type="button" class="cpw-swatch sel" data-color="#3B82F6" style="background:#3B82F6" title="Ocean Blue" onclick="cpwPickColor(this)"></button>
                    <button type="button" class="cpw-swatch" data-color="#8B5CF6" style="background:#8B5CF6" title="Violet" onclick="cpwPickColor(this)"></button>
                    <button type="button" class="cpw-swatch" data-color="#F43F5E" style="background:#F43F5E" title="Rose" onclick="cpwPickColor(this)"></button>
                    <button type="button" class="cpw-swatch" data-color="#10B981" style="background:#10B981" title="Emerald" onclick="cpwPickColor(this)"></button>
                    <button type="button" class="cpw-swatch" data-color="#F59E0B" style="background:#F59E0B" title="Amber" onclick="cpwPickColor(this)"></button>
                    <button type="button" class="cpw-swatch" data-color="#0EA5E9" style="background:#0EA5E9" title="Sky" onclick="cpwPickColor(this)"></button>
                    <div class="cpw-swatch-custom" title="Custom color">
                        <i class="fa fa-eyedropper" aria-hidden="true"></i>
                        <input type="color" id="cpwColorCustom" value="#3B82F6" oninput="cpwPickCustomColor(this)">
                    </div>
                </div>
            </div>
        </div>

        {{-- Step 2: About --}}
        <div class="cpw-page" id="cpwPage2" style="display:none">
            <div class="cpw-page-hdr">
                <div class="cpw-page-hdr-icon"><i class="fa fa-align-left" aria-hidden="true"></i></div>
                <div><h3>About Your Business</h3><p>Tell clients what makes you great</p></div>
            </div>
            <div class="cpw-field">
                <label>Business Description <small>Appears on the "About Us" page</small></label>
                <textarea class="cpw-textarea" id="cpwDesc" rows="4" placeholder="We are a leading provider of…">{{ $business->description ?? '' }}</textarea>
            </div>
            <div class="cpw-field">
                <label>Key Services / What We Offer <small>Up to 4 items</small></label>
                <div class="cpw-features" id="cpwFeatures"></div>
                <button type="button" class="cpw-add-btn" id="cpwAddFeature" onclick="cpwAddFeature()">
                    <i class="fa fa-plus" aria-hidden="true"></i> Add item
                </button>
            </div>
        </div>

        {{-- Step 3: Contact --}}
        <div class="cpw-page" id="cpwPage3" style="display:none">
            <div class="cpw-page-hdr">
                <div class="cpw-page-hdr-icon"><i class="fa fa-phone" aria-hidden="true"></i></div>
                <div><h3>Contact &amp; Location</h3><p>How clients can reach you</p></div>
            </div>
            <div class="cpw-field">
                <label>Address</label>
                <textarea class="cpw-textarea" id="cpwAddress" rows="2" placeholder="123 Main Street, City, Country">{{ $mainBranch?->address ?? '' }}</textarea>
            </div>
            <div class="cpw-grid2">
                <div class="cpw-field" style="margin-bottom:0">
                    <label>Phone Number</label>
                    <input class="cpw-input" id="cpwPhone" type="tel" value="{{ $mainBranch?->phone ?? '' }}" placeholder="+1 234 567 8900">
                </div>
                <div class="cpw-field" style="margin-bottom:0">
                    <label>Email Address</label>
                    <input class="cpw-input" id="cpwEmail" type="email" value="{{ $mainBranch?->email ?? '' }}" placeholder="hello@company.com">
                </div>
            </div>
            <div class="cpw-field" style="margin-top:14px;">
                <label>Website</label>
                <input class="cpw-input" id="cpwWebsite" type="url" value="" placeholder="https://www.company.com">
            </div>
        </div>

        {{-- Step 4: Social Media --}}
        <div class="cpw-page" id="cpwPage4" style="display:none">
            <div class="cpw-page-hdr">
                <div class="cpw-page-hdr-icon"><i class="fa fa-share-nodes" aria-hidden="true"></i></div>
                <div><h3>Social Media</h3><p>Your online presence (all optional)</p></div>
            </div>
            <div class="cpw-social-row">
                <div class="cpw-social-icon" style="background:#E1306C22;color:#E1306C;"><i class="fa fa-instagram" aria-hidden="true"></i></div>
                <input class="cpw-input" id="cpwInstagram" type="text" placeholder="@yourhandle or full URL">
            </div>
            <div class="cpw-social-row">
                <div class="cpw-social-icon" style="background:#1877F222;color:#1877F2;"><i class="fa fa-facebook" aria-hidden="true"></i></div>
                <input class="cpw-input" id="cpwFacebook" type="text" placeholder="facebook.com/yourpage">
            </div>
            <div class="cpw-social-row">
                <div class="cpw-social-icon" style="background:#1DA1F222;color:#1DA1F2;"><i class="fa fa-twitter" aria-hidden="true"></i></div>
                <input class="cpw-input" id="cpwTwitter" type="text" placeholder="@yourhandle">
            </div>
            <div class="cpw-social-row">
                <div class="cpw-social-icon" style="background:#0077B522;color:#0077B5;"><i class="fa fa-linkedin" aria-hidden="true"></i></div>
                <input class="cpw-input" id="cpwLinkedin" type="text" placeholder="linkedin.com/company/yourname">
            </div>
            <div class="cpw-social-row">
                <div class="cpw-social-icon" style="background:#25D36622;color:#25D366;"><i class="fa fa-whatsapp" aria-hidden="true"></i></div>
                <input class="cpw-input" id="cpwWhatsapp" type="tel" placeholder="+1 234 567 8900">
            </div>
        </div>

        {{-- Step 5: Review & Generate --}}
        <div class="cpw-page" id="cpwPage5" style="display:none">
            <div class="cpw-page-hdr">
                <div class="cpw-page-hdr-icon"><i class="fa fa-wand-magic-sparkles" aria-hidden="true"></i></div>
                <div><h3>Ready to Generate</h3><p>Review your details then create the design</p></div>
            </div>
            <div class="cpw-review" id="cpwReviewCard"></div>
            <div class="cpw-gen-wrap">
                <button type="button" class="cpw-gen-btn" id="cpwGenerateBtn" onclick="cpwGenerate()">
                    <div class="cpw-gen-shine"></div>
                    <i class="fa fa-wand-magic-sparkles" aria-hidden="true"></i>
                    Finish &amp; Generate with AI
                </button>
                <p class="cpw-gen-sub">Gemini AI will write your copy, generate visuals &amp; build a 3-page design</p>
            </div>
        </div>

    </div>{{-- /.cpw-body --}}

    {{-- Footer --}}
    <div class="cpw-footer">
        <button type="button" class="cpw-btn" id="cpwPrevBtn" onclick="cpwPrev()" disabled>
            <i class="fa fa-arrow-left" aria-hidden="true"></i> Back
        </button>
        <div class="cpw-pg-dots" id="cpwDots">
            <span class="cpw-pg-dot on"></span>
            <span class="cpw-pg-dot"></span>
            <span class="cpw-pg-dot"></span>
            <span class="cpw-pg-dot"></span>
            <span class="cpw-pg-dot"></span>
        </div>
        <button type="button" class="cpw-btn cpw-btn-primary" id="cpwNextBtn" onclick="cpwNext()">
            Next <i class="fa fa-arrow-right" aria-hidden="true"></i>
        </button>
    </div>

    {{-- ── AI Generation Loading Overlay ── --}}
    <div class="cpw-loading" id="cpwLoading">
        <div class="cpw-loading-orb"><i class="fa fa-wand-magic-sparkles" aria-hidden="true"></i></div>
        <h3>Creating Your Design</h3>
        <p class="cpw-loading-subtitle">Gemini AI is crafting a professional 3-page company profile</p>
        <div class="cpw-loading-steps">
            <div class="cpw-lstep active" id="cpwLStep1">
                <div class="cpw-lstep-dot"></div>
                <span class="cpw-lstep-text">Crafting professional copy &amp; content</span>
            </div>
            <div class="cpw-lstep" id="cpwLStep2">
                <div class="cpw-lstep-dot"></div>
                <span class="cpw-lstep-text">Generating cover background imagery</span>
            </div>
            <div class="cpw-lstep" id="cpwLStep3">
                <div class="cpw-lstep-dot"></div>
                <span class="cpw-lstep-text">Building your 3-page canvas design</span>
            </div>
        </div>
        <p class="cpw-loading-hint">This takes about 20–35 seconds…</p>
        <div class="cpw-loading-error" id="cpwLoadingError"></div>
        <button type="button" class="cpw-loading-retry-btn" id="cpwRetryBtn" onclick="cpwRetryGenerate()">
            <i class="fa fa-rotate-right" aria-hidden="true"></i> Try Again
        </button>
    </div>

</div>{{-- /.cpw-panel --}}
</div>{{-- /.cpw-backdrop --}}

{{-- ══════════════════════════════════════════════════════
     LETTER HEAD CHOICE MODAL
     ══════════════════════════════════════════════════════ --}}
<div class="lhc-backdrop" id="lhcBackdrop">
<div class="lhc-panel">

    {{-- AI Loading Overlay --}}
    <div class="lhc-loading" id="lhcLoading">
        <div class="lhc-loading-orb"><i class="fa fa-wand-magic-sparkles" aria-hidden="true"></i></div>
        <h3>Creating your letterhead…</h3>
        <p class="lhc-loading-sub">Gemini AI is generating your professional design</p>
        <div class="lhc-lsteps">
            <div class="lhc-lstep active" id="lhcLS1">
                <div class="lhc-lstep-dot"></div>
                <span class="lhc-lstep-text">Analysing your brand identity</span>
            </div>
            <div class="lhc-lstep" id="lhcLS2">
                <div class="lhc-lstep-dot"></div>
                <span class="lhc-lstep-text">Generating professional content</span>
            </div>
            <div class="lhc-lstep" id="lhcLS3">
                <div class="lhc-lstep-dot"></div>
                <span class="lhc-lstep-text">Building canvas design</span>
            </div>
        </div>
        <div class="lhc-err" id="lhcErr"></div>
    </div>

    {{-- View 1: Choice --}}
    <div id="lhcViewChoice">
        <div class="lhc-header">
            <div class="lhc-header-icon"><i class="fa fa-file-lines" aria-hidden="true"></i></div>
            <div class="lhc-header-text">
                <h2>Create Letter Head</h2>
                <p>Choose how you want to create your company letterhead (794 × 1123 px)</p>
            </div>
            <button class="lhc-close" onclick="closeLhChoiceModal()" title="Close"><i class="fa fa-times" aria-hidden="true"></i></button>
        </div>
        <div class="lhc-choices">
            <div class="lhc-card" onclick="lhcChooseAI()">
                <div class="lhc-badge">Recommended</div>
                <div class="lhc-card-icon"><i class="fa fa-wand-magic-sparkles" aria-hidden="true"></i></div>
                <h3>Generate with AI</h3>
                <p>Let Gemini AI create a polished letterhead with your brand, professional background image, and content</p>
            </div>
            <div class="lhc-card" onclick="lhcChooseManual()">
                <div class="lhc-card-icon"><i class="fa fa-pen-ruler" aria-hidden="true"></i></div>
                <h3>Create Manually</h3>
                <p>Open the editor with a blank A4 canvas and design your letterhead from scratch</p>
            </div>
        </div>
    </div>

    {{-- View 2: AI Form --}}
    <div id="lhcViewForm" style="display:none">
        <div class="lhc-header">
            <button class="lhc-close" onclick="lhcBackToChoice()" title="Back" style="margin-right:4px;order:-1;">
                <i class="fa fa-arrow-left" aria-hidden="true"></i>
            </button>
            <div class="lhc-header-icon"><i class="fa fa-wand-magic-sparkles" aria-hidden="true"></i></div>
            <div class="lhc-header-text">
                <h2>AI Letterhead Generator</h2>
                <p>Fill in your details — AI handles the rest</p>
            </div>
            <button class="lhc-close" onclick="closeLhChoiceModal()" title="Close"><i class="fa fa-times" aria-hidden="true"></i></button>
        </div>
        <div class="lhc-form-body">
            <div class="lhc-grid2">
                <div class="lhc-field">
                    <label>Company Name</label>
                    <input class="lhc-input" id="lhcName" type="text" value="{{ $business->name }}" placeholder="Your company name">
                </div>
                <div class="lhc-field">
                    <label>Tagline <small style="font-weight:400;color:var(--muted);">Optional</small></label>
                    <input class="lhc-input" id="lhcTagline" type="text" value="{{ $business->short_description ?? '' }}" placeholder="Your trusted partner">
                </div>
            </div>
            <div class="lhc-field">
                <label>Address <small style="font-weight:400;color:var(--muted);">Optional</small></label>
                <input class="lhc-input" id="lhcAddress" type="text" value="{{ $mainBranch?->address ?? '' }}" placeholder="123 Business Street, City, Country">
            </div>
            <div class="lhc-grid2">
                <div class="lhc-field">
                    <label>Phone <small style="font-weight:400;color:var(--muted);">Optional</small></label>
                    <input class="lhc-input" id="lhcPhone" type="text" value="{{ $mainBranch?->phone ?? '' }}" placeholder="+1 234 567 890">
                </div>
                <div class="lhc-field">
                    <label>Email <small style="font-weight:400;color:var(--muted);">Optional</small></label>
                    <input class="lhc-input" id="lhcEmail" type="text" value="{{ $mainBranch?->email ?? '' }}" placeholder="hello@company.com">
                </div>
            </div>
            <div class="lhc-grid2">
                <div class="lhc-field">
                    <label>Website <small style="font-weight:400;color:var(--muted);">Optional</small></label>
                    <input class="lhc-input" id="lhcWebsite" type="text" value="" placeholder="www.company.com">
                </div>
                <div class="lhc-field">
                    <label>Theme Color</label>
                    <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;padding-top:2px;" id="lhcSwatches">
                        <button type="button" class="cpw-swatch sel" data-color="#3B82F6" style="background:#3B82F6;width:28px;height:28px;" onclick="lhcPickColor(this)"></button>
                        <button type="button" class="cpw-swatch" data-color="#8B5CF6" style="background:#8B5CF6;width:28px;height:28px;" onclick="lhcPickColor(this)"></button>
                        <button type="button" class="cpw-swatch" data-color="#F43F5E" style="background:#F43F5E;width:28px;height:28px;" onclick="lhcPickColor(this)"></button>
                        <button type="button" class="cpw-swatch" data-color="#10B981" style="background:#10B981;width:28px;height:28px;" onclick="lhcPickColor(this)"></button>
                        <button type="button" class="cpw-swatch" data-color="#F59E0B" style="background:#F59E0B;width:28px;height:28px;" onclick="lhcPickColor(this)"></button>
                        <button type="button" class="cpw-swatch" data-color="#0EA5E9" style="background:#0EA5E9;width:28px;height:28px;" onclick="lhcPickColor(this)"></button>
                        <div class="cpw-swatch-custom" title="Custom color" style="width:28px;height:28px;">
                            <i class="fa fa-eyedropper" aria-hidden="true"></i>
                            <input type="color" id="lhcColorCustom" value="#3B82F6" oninput="lhcPickCustomColor(this)">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="lhc-footer">
            <button class="lhc-btn" onclick="closeLhChoiceModal()">Cancel</button>
            <button class="lhc-btn lhc-btn-primary" id="lhcGenBtn" onclick="lhcGenerate()">
                <i class="fa fa-wand-magic-sparkles" aria-hidden="true"></i> Generate Letterhead
            </button>
        </div>
    </div>

</div>{{-- /.lhc-panel --}}
</div>{{-- /.lhc-backdrop --}}

<script>
/* ── Wizard state ── */
var cpwStep = 1;
var cpwTotalSteps = 5;
var cpwAccent = '#3B82F6';
var cpwFeatureCount = 0;

/* Pre-fill from server */
var cpwPrefill = {
    features: @json(array_values(array_filter(is_array($business->brand_features) ? $business->brand_features : [], 'strlen'))),
    logoUrl:  @json($business->logoUrl()),
};

/* ── Open / Close ── */
function openCpWizard() {
    cpwStep = 1;
    cpwAccent = '#3B82F6';
    cpwFeatureCount = 0;
    cpwRenderFeatures();
    cpwUpdateUI();
    document.getElementById('cpwBackdrop').classList.add('open');
}
function closeCpWizard() {
    document.getElementById('cpwBackdrop').classList.remove('open');
}
document.getElementById('cpwBackdrop').addEventListener('click', function(e) {
    if (e.target === this) closeCpWizard();
});

/* ── Step nav ── */
function cpwNext() {
    if (cpwStep < cpwTotalSteps) { cpwStep++; cpwUpdateUI(); }
}
function cpwPrev() {
    if (cpwStep > 1) { cpwStep--; cpwUpdateUI(); }
}

function cpwUpdateUI() {
    /* Pages */
    for (var i = 1; i <= cpwTotalSteps; i++) {
        var pg = document.getElementById('cpwPage' + i);
        if (pg) pg.style.display = (i === cpwStep) ? '' : 'none';
    }
    /* Step dots in bar */
    document.querySelectorAll('.cpw-step-item').forEach(function(el) {
        var s = parseInt(el.dataset.s);
        el.classList.remove('active', 'done');
        if (s === cpwStep) el.classList.add('active');
        else if (s < cpwStep) el.classList.add('done');
    });
    /* Connector lines */
    for (var li = 1; li <= 4; li++) {
        var ln = document.getElementById('cpwLine' + li);
        if (ln) ln.classList.toggle('done', li < cpwStep);
    }
    /* Dots */
    document.querySelectorAll('#cpwDots .cpw-pg-dot').forEach(function(d, i) {
        d.classList.toggle('on', i === cpwStep - 1);
    });
    /* Buttons */
    document.getElementById('cpwPrevBtn').disabled = (cpwStep === 1);
    var nb = document.getElementById('cpwNextBtn');
    if (cpwStep === cpwTotalSteps) {
        nb.style.display = 'none';
    } else {
        nb.style.display = '';
        nb.innerHTML = 'Next <i class="fa fa-arrow-right"></i>';
    }
    /* Build review card on last step */
    if (cpwStep === cpwTotalSteps) cpwBuildReview();
    /* Scroll body to top */
    var body = document.querySelector('.cpw-body');
    if (body) body.scrollTop = 0;
}

/* ── Color picker ── */
function cpwPickColor(btn) {
    document.querySelectorAll('#cpwSwatches .cpw-swatch').forEach(function(b){ b.classList.remove('sel'); });
    btn.classList.add('sel');
    cpwAccent = btn.dataset.color;
    document.getElementById('cpwColorCustom').value = cpwAccent;
}
function cpwPickCustomColor(inp) {
    cpwAccent = inp.value;
    document.querySelectorAll('#cpwSwatches .cpw-swatch').forEach(function(b){ b.classList.remove('sel'); });
}

/* ── Features ── */
function cpwRenderFeatures() {
    var container = document.getElementById('cpwFeatures');
    container.innerHTML = '';
    cpwFeatureCount = 0;
    var prefill = cpwPrefill.features || [];
    var count = Math.max(1, Math.min(4, prefill.length || 1));
    for (var i = 0; i < count; i++) {
        cpwAddFeature(prefill[i] || '');
    }
}
function cpwAddFeature(val) {
    var container = document.getElementById('cpwFeatures');
    var rows = container.querySelectorAll('.cpw-feature-row');
    if (rows.length >= 4) return;
    cpwFeatureCount++;
    var row = document.createElement('div');
    row.className = 'cpw-feature-row';
    row.innerHTML =
        '<input class="cpw-input" type="text" placeholder="e.g. Quality Products" value="' + (val || '').replace(/"/g,'&quot;') + '">' +
        '<button type="button" class="cpw-feature-del" onclick="this.parentNode.remove();var b=document.getElementById(\'cpwAddFeature\');if(b)b.style.display=\'\'" title="Remove"><i class="fa fa-times"></i></button>';
    container.appendChild(row);
    var addBtn = document.getElementById('cpwAddFeature');
    if (addBtn) addBtn.style.display = (container.querySelectorAll('.cpw-feature-row').length >= 4) ? 'none' : '';
}

/* ── Collect data ── */
function cpwGetData() {
    var features = [];
    document.querySelectorAll('#cpwFeatures .cpw-feature-row input').forEach(function(inp) {
        if (inp.value.trim()) features.push(inp.value.trim());
    });
    return {
        name:      (document.getElementById('cpwName').value || '').trim(),
        tagline:   (document.getElementById('cpwTagline').value || '').trim(),
        color:     cpwAccent,
        desc:      (document.getElementById('cpwDesc').value || '').trim(),
        features:  features,
        address:   (document.getElementById('cpwAddress').value || '').trim(),
        phone:     (document.getElementById('cpwPhone').value || '').trim(),
        email:     (document.getElementById('cpwEmail').value || '').trim(),
        website:   (document.getElementById('cpwWebsite').value || '').trim(),
        instagram: (document.getElementById('cpwInstagram').value || '').trim(),
        facebook:  (document.getElementById('cpwFacebook').value || '').trim(),
        twitter:   (document.getElementById('cpwTwitter').value || '').trim(),
        linkedin:  (document.getElementById('cpwLinkedin').value || '').trim(),
        whatsapp:  (document.getElementById('cpwWhatsapp').value || '').trim(),
        logoUrl:   cpwPrefill.logoUrl || '',
    };
}

/* ── Build review card ── */
function cpwBuildReview() {
    var d = cpwGetData();
    var card = document.getElementById('cpwReviewCard');
    function row(key, val) {
        if (!val) return '';
        return '<div class="cpw-review-row"><span class="cpw-review-key">' + key + '</span><span class="cpw-review-val">' + val.replace(/</g,'&lt;') + '</span></div>';
    }
    var html = '';

    html += '<div class="cpw-review-section"><div class="cpw-review-label">Identity</div>';
    if (d.logoUrl) html += '<img class="cpw-review-logo" src="' + d.logoUrl + '" style="margin-bottom:8px;">';
    html += row('Name', d.name) + row('Tagline', d.tagline);
    html += '<div class="cpw-review-row"><span class="cpw-review-key">Theme</span><div class="cpw-review-colors"><div class="cpw-review-color-dot" style="background:' + d.color + '"></div><span class="cpw-review-val">' + d.color + '</span></div></div>';
    html += '</div>';

    if (d.desc || d.features.length) {
        html += '<div class="cpw-review-section"><div class="cpw-review-label">About</div>';
        if (d.desc) html += row('Description', d.desc.length > 80 ? d.desc.slice(0, 80) + '…' : d.desc);
        if (d.features.length) html += row('Services', d.features.join(' · '));
        html += '</div>';
    }

    if (d.address || d.phone || d.email || d.website) {
        html += '<div class="cpw-review-section"><div class="cpw-review-label">Contact</div>';
        html += row('Address', d.address) + row('Phone', d.phone) + row('Email', d.email) + row('Website', d.website);
        html += '</div>';
    }

    var socials = [d.instagram && 'Instagram: ' + d.instagram, d.facebook && 'Facebook: ' + d.facebook,
                   d.twitter && 'Twitter: ' + d.twitter, d.linkedin && 'LinkedIn: ' + d.linkedin,
                   d.whatsapp && 'WhatsApp: ' + d.whatsapp].filter(Boolean);
    if (socials.length) {
        html += '<div class="cpw-review-section"><div class="cpw-review-label">Social Media</div>';
        html += row('Handles', socials.join('  ·  '));
        html += '</div>';
    }

    card.innerHTML = html;
}

/* ══════════════════════════════════════════════════════
   CANVAS TEMPLATE GENERATOR  (Fabric.js 5.3.0 JSON)
   ══════════════════════════════════════════════════════ */
/* ══════════════════════════════════════════════════════
   AI GENERATION FLOW
   ══════════════════════════════════════════════════════ */
var cpwGenerating = false;
var cpwLastData   = null;
var cpwLoadingTimers = [];

function cpwShowLoading() {
    document.getElementById('cpwLoading').classList.add('on');
    document.getElementById('cpwLoadingError').classList.remove('on');
    document.getElementById('cpwRetryBtn').classList.remove('on');
    /* Reset steps */
    ['cpwLStep1','cpwLStep2','cpwLStep3'].forEach(function(id) {
        var el = document.getElementById(id);
        el.classList.remove('active','done');
    });
    document.getElementById('cpwLStep1').classList.add('active');
    /* Advance steps on a timer (purely visual) */
    cpwLoadingTimers.push(setTimeout(function() {
        document.getElementById('cpwLStep1').classList.remove('active');
        document.getElementById('cpwLStep1').classList.add('done');
        document.getElementById('cpwLStep2').classList.add('active');
    }, 9000));
    cpwLoadingTimers.push(setTimeout(function() {
        document.getElementById('cpwLStep2').classList.remove('active');
        document.getElementById('cpwLStep2').classList.add('done');
        document.getElementById('cpwLStep3').classList.add('active');
    }, 20000));
}

function cpwHideLoading() {
    cpwLoadingTimers.forEach(clearTimeout);
    cpwLoadingTimers = [];
    document.getElementById('cpwLoading').classList.remove('on');
}

function cpwShowError(msg) {
    var el = document.getElementById('cpwLoadingError');
    el.textContent = msg || 'Generation failed. You can retry or proceed with the template.';
    el.classList.add('on');
    document.getElementById('cpwRetryBtn').classList.add('on');
    /* Remove spinner from step 3 */
    ['cpwLStep1','cpwLStep2','cpwLStep3'].forEach(function(id) {
        document.getElementById(id).classList.remove('active');
    });
}

function cpwRetryGenerate() {
    if (cpwLastData) { cpwRunGenerate(cpwLastData); }
}

function cpwGenerate() {
    if (cpwGenerating) return;
    cpwLastData = cpwGetData();
    cpwRunGenerate(cpwLastData);
}

function cpwRunGenerate(d) {
    cpwGenerating = true;
    cpwShowLoading();

    var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    fetch('{{ route('designstudio.generate.company-profile') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify(d),
    })
    .then(function(r) { return r.json(); })
    .then(function(result) {
        cpwGenerating = false;
        if (result && result.success) {
            /* Mark all steps done */
            ['cpwLStep1','cpwLStep2','cpwLStep3'].forEach(function(id) {
                var el = document.getElementById(id);
                el.classList.remove('active');
                el.classList.add('done');
            });
            cpwBuildAndLaunch(d, result);
        } else {
            /* Graceful fallback: build without AI */
            cpwFallbackAndLaunch(d, result && result.error ? result.error : null);
        }
    })
    .catch(function(err) {
        cpwGenerating = false;
        cpwFallbackAndLaunch(d, 'Network error — launching with template.');
    });
}

function cpwFallbackAndLaunch(d, errorMsg) {
    if (errorMsg) { cpwShowError(errorMsg + ' Launching template in 3s…'); }
    setTimeout(function() {
        var pages = cpwBuildFallbackPages(d);
        try { sessionStorage.setItem('dsWizardTemplate', JSON.stringify(pages)); } catch(e) {}
        cpwHideLoading();
        closeCpWizard();
        startEditor(1920, 1080, 'company-profile');
    }, errorMsg ? 3000 : 0);
}

/* Save pages to sessionStorage; strips base64 images if quota is exceeded */
function cpwSavePages(pages) {
    try {
        sessionStorage.setItem('dsWizardTemplate', JSON.stringify(pages));
    } catch(e) {
        var stripped = pages.map(function(pg) {
            try {
                var cv = JSON.parse(pg.json);
                delete cv.backgroundImage;
                cv.objects = (cv.objects||[]).filter(function(o) {
                    return o.type !== 'image' || !o.src || o.src.indexOf('data:') < 0;
                });
                return {json: JSON.stringify(cv)};
            } catch(e2) { return pg; }
        });
        try { sessionStorage.setItem('dsWizardTemplate', JSON.stringify(stripped)); } catch(e3) {}
    }
}

function cpwBuildAndLaunch(d, aiResult) {
    /* Collect the 3 themed image sources */
    var imgSrcs = {
        cover:    (aiResult && aiResult.coverImage)    || null,
        interior: (aiResult && aiResult.interiorImage) || null,
        services: (aiResult && aiResult.servicesImage) || null,
    };
    var keys    = Object.keys(imgSrcs);
    var imgData = {};
    var pending = keys.length;

    function launch() {
        var pages = cpwBuildAiPages(d, aiResult, imgData);
        cpwSavePages(pages);
        cpwHideLoading();
        closeCpWizard();
        startEditor(1920, 1080, 'company-profile');
    }
    function done() { if (--pending === 0) launch(); }

    keys.forEach(function(k) {
        var src = imgSrcs[k];
        if (!src) { imgData[k] = {w:0,h:0,src:null}; done(); return; }
        var img = new Image();
        img.onload  = function(){ imgData[k]={w:img.naturalWidth,h:img.naturalHeight,src:src}; done(); };
        img.onerror = function(){ imgData[k]={w:0,h:0,src:null}; done(); };
        img.src = src;
    });
}

/* ══════════════════════════════════════════════════════
   CANVAS BUILDERS (shared helpers)
   ══════════════════════════════════════════════════════ */
function cpwMakeBase(type, extra) {
    return Object.assign({
        type: type, version: '5.3.0',
        originX: 'left', originY: 'top',
        angle: 0, scaleX: 1, scaleY: 1,
        opacity: 1, visible: true, selectable: true, evented: true,
        flipX: false, flipY: false, skewX: 0, skewY: 0,
        strokeWidth: 0, stroke: null
    }, extra);
}
function cpwR(l,t,w,h,fill,rx,op) {
    return cpwMakeBase('rect',{left:l,top:t,width:w,height:h,fill:fill||'#0F172A',rx:rx||0,ry:rx||0,opacity:op||1});
}
function cpwT(l,t,w,text,sz,fill,weight,style,align,lh) {
    return cpwMakeBase('textbox',{left:l,top:t,width:w,text:text||'',fontSize:sz||28,
        fill:fill||'#0F172A',fontFamily:'Arial',fontWeight:weight||'normal',
        fontStyle:style||'normal',textAlign:align||'left',lineHeight:lh||1.3,
        charSpacing:0,underline:false,overline:false,linethrough:false,
        textBackgroundColor:'',styles:{}});
}
function cpwPageJson(objs, bg, bgImgSpec) {
    var canvas = {version:'5.3.0', objects: objs, background: bg || '#FFFFFF'};
    if (bgImgSpec) canvas.backgroundImage = bgImgSpec;
    return {json: JSON.stringify(canvas)};
}

/* ─── AI-enhanced canvas builder — 10 pages with AI images ─── */
function cpwBuildAiPages(d, ai, imgData) {
    var c      = (ai && ai.content) ? ai.content : {};
    var accent = d.color || '#3B82F6';
    var dark   = '#0F172A';
    var white  = '#FFFFFF';
    var light  = '#F1F5F9';
    var slate  = '#334155';
    var muted  = '#94A3B8';
    var yr     = String(new Date().getFullYear());

    /* ── Image data (w/h/src for each theme) ── */
    var iCov = (imgData && imgData.cover)    || {w:0,h:0,src:null};
    var iInt = (imgData && imgData.interior) || {w:0,h:0,src:null};
    var iSvc = (imgData && imgData.services) || {w:0,h:0,src:null};

    /* Build Fabric.js backgroundImage spec */
    function mkBg(im) {
        if (!im || !im.src || im.w === 0) return null;
        return cpwMakeBase('image', {
            left:0, top:0, width:im.w, height:im.h,
            scaleX:1920/im.w, scaleY:1080/im.h,
            src:im.src, crossOrigin:'anonymous',
            selectable:false, evented:false
        });
    }
    /* Full-canvas overlay rect */
    function ov(col, op) { return cpwR(0,0,1920,1080,col,0,op); }

    /* ── AI content (rich fallbacks for every field) ── */
    var headline  = (c.headline || d.name || 'COMPANY PROFILE').toUpperCase();
    var tagline   = c.tagline  || d.tagline || 'Your trusted partner in growth, innovation and lasting success';
    var aboutHead = c.about_heading || 'About Us';
    var desc      = c.description  || d.desc || 'We are a dedicated team of professionals committed to delivering exceptional quality in everything we do. Our client-first approach drives us to understand your unique challenges and craft solutions that create real, measurable impact. With years of proven expertise across our industry, we stand apart through our relentless commitment to innovation, integrity, and excellence — ensuring every project we undertake exceeds expectations.';
    var cta       = c.cta || 'Get in touch — let\'s build something great';
    var mission   = c.mission || 'To deliver outstanding, innovative solutions that create measurable, lasting value for every client we partner with, while maintaining the highest standards of integrity and professionalism.';
    var vision    = c.vision  || 'To become the most trusted and forward-thinking partner in our industry, empowering businesses to reach their full potential through transformative, client-centric service.';
    var services  = ((c.services && c.services.length) ? c.services :
        (d.features||[]).slice(0,4).map(function(f){return{title:f,desc:'Expert delivery with measurable results tailored to your specific business needs and objectives.'};})
        ).slice(0,4);
    var whyItems  = ((c.why_choose_us && c.why_choose_us.length) ? c.why_choose_us : [
        {title:'Expert Team', desc:'Our seasoned professionals bring deep industry knowledge and a proven track record of delivering excellence across every project and engagement.'},
        {title:'Quality Assured', desc:'Rigorous quality standards are embedded in every phase of our work, ensuring best-in-class results that stand the test of time.'},
        {title:'Client-First Focus', desc:'Your success is our ultimate measure of achievement. We listen, adapt, and remain fully committed to understanding and delivering your goals.'}
    ]).slice(0,3);
    var steps = ((c.process_steps && c.process_steps.length) ? c.process_steps : [
        {number:'01',title:'Discovery',    desc:'In-depth consultation to fully understand your vision, goals, challenges, and the outcomes that matter most to you.'},
        {number:'02',title:'Strategy',     desc:'We develop a comprehensive, tailored plan aligned with your objectives, timeline, and budget — leaving nothing to chance.'},
        {number:'03',title:'Execution',    desc:'Our expert team delivers with precision and care, maintaining clear communication and the highest standards throughout.'},
        {number:'04',title:'Growth Review',desc:'Thorough evaluation, refinement, and ongoing support to ensure lasting success and continuous improvement over time.'}
    ]).slice(0,4);
    var values = ((c.values && c.values.length) ? c.values : [
        {title:'Integrity',   desc:'Honest, transparent, and ethical in every decision and interaction we make'},
        {title:'Innovation',  desc:'Constantly evolving, pushing boundaries, and embracing what is next'},
        {title:'Excellence',  desc:'Uncompromising commitment to quality in every detail of our work'},
        {title:'Partnership', desc:'Building genuine long-term relationships founded on mutual respect'}
    ]).slice(0,4);
    var tms = ((c.testimonials && c.testimonials.length) ? c.testimonials : [
        {quote:'Working with this exceptional team completely transformed our business. Their dedication, depth of expertise, and unwavering attention to detail exceeded every single expectation we had going in.',name:'Sarah J. Mitchell',role:'Chief Executive Officer'},
        {quote:'Highly professional, results-driven, and truly collaborative throughout the entire engagement. They deeply understood our vision and delivered a solution that continues to drive meaningful growth.',name:'Michael T. Chen',role:'Director of Operations'}
    ]).slice(0,2);
    var portHead  = c.portfolio_heading || 'Our Work';
    var portItems = ((c.portfolio_items && c.portfolio_items.length) ? c.portfolio_items : [
        {title:'Brand Identity Redesign', category:'Branding & Strategy'},
        {title:'Digital Transformation',  category:'Technology'},
        {title:'Market Growth Campaign',  category:'Marketing'}
    ]).slice(0,3);

    var bgCov = mkBg(iCov);   /* cover image    — dramatic dark abstract */
    var bgInt = mkBg(iInt);   /* interior image — professional workspace */
    var bgSvc = mkBg(iSvc);   /* services image — tech/innovation abstract */

    /* ═══════════════════════════════════════════
       P1 — Cover  (cover image bg)
       ═══════════════════════════════════════════ */
    var p1 = [];
    if (!bgCov) p1.push(cpwR(0,0,1920,1080,dark));
    p1.push(ov('#000',0.48));                               /* readability */
    p1.push(cpwR(0,0,10,1080,accent));                     /* left stripe */
    p1.push(cpwR(1740,0,180,1080,accent,0,0.05));          /* right glow */
    p1.push(cpwR(0,962,1920,118,dark,0,0.70));             /* bottom bar */
    p1.push(cpwT(110,975,500,cta,22,accent,'600'));
    p1.push(cpwR(110,971,280,1,accent,0,0.5));
    var tOff = d.logoUrl ? 175 : 0;
    if (d.logoUrl) p1.push(cpwMakeBase('image',{left:120,top:260,width:150,height:150,src:d.logoUrl,crossOrigin:'anonymous',scaleX:1,scaleY:1}));
    p1.push(cpwT(120,452+tOff,1450,headline,90,white,'800','normal','left',1.0));
    p1.push(cpwT(120,570+tOff,1150,tagline,34,accent,'400','italic','left',1.35));
    p1.push(cpwR(120,646+tOff,320,5,accent));
    p1.push(cpwT(120,664+tOff,680,(d.name||'')+(d.name?' · ':'')+yr,22,'rgba(255,255,255,0.50)'));
    p1.push(cpwT(1488,50,390,'COMPANY\nPROFILE',42,white,'800','normal','right',1.05));
    var dc=[.10,.18,.28,.18,.28,.46,.28,.46,.68],dpos=[[1490,224],[1532,224],[1574,224],[1490,256],[1532,256],[1574,256],[1490,288],[1532,288],[1574,288]];
    dpos.forEach(function(p,i){p1.push(cpwR(p[0],p[1],12,12,white,6,dc[i]));});

    /* ═══════════════════════════════════════════
       P2 — About Us  (interior image: dark left + white-tinted right)
       ═══════════════════════════════════════════ */
    var aWords = aboutHead.split(' ');
    var aW1 = aWords[0]||'About', aW2 = aWords.slice(1).join(' ')||'Us';
    var p2 = [];
    if (!bgInt) p2.push(cpwR(0,0,1920,1080,white));
    p2.push(cpwR(0,0,590,1080,dark));             /* solid left panel covers image */
    p2.push(cpwR(590,0,1330,1080,white,0,0.84));  /* right: white 84% → image 16% */
    p2.push(cpwR(0,0,10,1080,accent));
    p2.push(cpwR(582,0,4,1080,accent,0,0.20));
    /* Left panel content */
    p2.push(cpwT(56,190,470,aW1,80,white,'800'));
    p2.push(cpwT(56,278,470,aW2,80,accent,'800'));
    p2.push(cpwR(56,384,240,5,accent));
    p2.push(cpwT(56,408,470,d.name||'',24,muted,'600'));
    p2.push(cpwR(310,660,240,240,accent,120,0.07));
    p2.push(cpwR(360,730,130,130,accent,65,0.10));
    /* Right content */
    p2.push(cpwT(652,70,1210,'Who We Are',46,dark,'800'));
    p2.push(cpwR(652,128,130,5,accent));
    p2.push(cpwT(652,166,1216,desc,24,slate,'normal','normal','left',1.72));
    p2.push(cpwT(652,490,700,'Our Strengths',32,dark,'800'));
    p2.push(cpwR(652,534,110,4,accent));
    whyItems.forEach(function(w,i){
        var by=558+i*152;
        p2.push(cpwR(652,by,8,122,accent,4));
        p2.push(cpwT(682,by+4,650,w.title||'',27,dark,'700'));
        if(w.desc) p2.push(cpwT(682,by+42,1096,w.desc,22,slate,'normal','normal','left',1.48));
    });

    /* ═══════════════════════════════════════════
       P3 — Our Services  (services image: dark header + white body)
       ═══════════════════════════════════════════ */
    var p3 = [];
    if (!bgSvc) p3.push(cpwR(0,0,1920,1080,white));
    p3.push(ov(dark,0.78));           /* full dark overlay on image */
    p3.push(cpwR(0,244,1920,836,white)); /* solid white body from y=244 */
    p3.push(cpwR(0,0,10,244,accent));
    p3.push(cpwT(120,46,1400,'Our Services',70,white,'800'));
    p3.push(cpwT(120,144,1000,d.name||'',26,accent,'600'));
    p3.push(cpwR(1780,0,140,244,accent,0,0.07));
    services.forEach(function(s,i){
        var col=i%2, row2=Math.floor(i/2);
        var sx=120+col*918, sy=274+row2*396;
        p3.push(cpwR(sx,sy,882,368,light,18));
        p3.push(cpwR(sx,sy,12,368,accent));
        p3.push(cpwR(sx+34,sy+32,58,58,accent,12,0.16));
        p3.push(cpwT(sx+34,sy+38,58,String(i+1).padStart(2,'0'),26,accent,'800','normal','center'));
        p3.push(cpwT(sx+114,sy+28,742,s.title||'',32,dark,'800'));
        if(s.desc) p3.push(cpwT(sx+114,sy+78,742,s.desc,23,slate,'normal','normal','left',1.58));
        p3.push(cpwR(sx+34,sy+314,110,2,accent,1,0.45));
    });
    if(!services.length) p3.push(cpwT(120,400,1680,'Add your key services in the wizard.',30,muted));

    /* ═══════════════════════════════════════════
       P4 — Why Choose Us  (services image: subtle white texture)
       ═══════════════════════════════════════════ */
    var p4 = [];
    if (!bgSvc) p4.push(cpwR(0,0,1920,1080,light));
    p4.push(ov(white,0.88));  /* white 88% → image shows subtly at 12% */
    p4.push(cpwR(0,0,1920,12,accent));
    p4.push(cpwR(0,12,12,1068,accent));
    p4.push(cpwT(120,84,1400,'Why Choose Us',68,dark,'800'));
    p4.push(cpwR(120,164,140,5,accent));
    p4.push(cpwT(120,190,1400,d.name||'',26,muted,'600'));
    whyItems.forEach(function(w,i){
        var wx=120+i*597;
        p4.push(cpwR(wx,258,566,764,white,22));
        p4.push(cpwR(wx,258,566,9,accent));
        p4.push(cpwR(wx+30,300,78,78,accent,16));
        var icons=['★','✦','◆'];
        p4.push(cpwT(wx+30,312,78,icons[i]||'●',30,white,'800','normal','center'));
        p4.push(cpwMakeBase('textbox',{left:wx+14,top:840,width:220,text:String(i+1).padStart(2,'0'),fontSize:120,fill:accent,fontFamily:'Arial',fontWeight:'800',opacity:0.06,textAlign:'left',lineHeight:1,charSpacing:0,underline:false,overline:false,linethrough:false,textBackgroundColor:'',styles:{}}));
        p4.push(cpwT(wx+30,410,500,w.title||'',34,dark,'800'));
        p4.push(cpwR(wx+30,456,90,4,accent));
        if(w.desc) p4.push(cpwT(wx+30,478,500,w.desc,24,slate,'normal','normal','left',1.68));
    });

    /* ═══════════════════════════════════════════
       P5 — How We Work  (cover image: dark dramatic)
       ═══════════════════════════════════════════ */
    var p5 = [];
    if (!bgCov) p5.push(cpwR(0,0,1920,1080,dark));
    p5.push(ov('#000',0.66));
    p5.push(cpwR(0,0,12,1080,accent));
    p5.push(cpwR(0,0,1920,12,accent));
    p5.push(cpwT(120,80,1400,'How We Work',68,white,'800'));
    p5.push(cpwR(120,160,140,5,accent));
    p5.push(cpwT(120,186,1100,d.name||'',26,muted,'600'));
    p5.push(cpwR(218,488,1440,4,accent,0,0.30));  /* horizontal step line */
    steps.forEach(function(st,i){
        var sx=120+i*448;
        p5.push(cpwR(sx,442,108,108,accent,54));
        p5.push(cpwT(sx,454,108,st.number||String(i+1).padStart(2,'0'),38,dark,'800','normal','center'));
        p5.push(cpwR(sx-14,568,434,406,white,18,0.08));
        p5.push(cpwR(sx-14,568,434,8,accent));
        p5.push(cpwT(sx+8,600,406,st.title||'',30,white,'700'));
        if(st.desc) p5.push(cpwT(sx+8,646,406,st.desc,22,muted,'normal','normal','left',1.62));
        if(i<steps.length-1) p5.push(cpwT(sx+420,462,40,'›',44,accent,'800','normal','center'));
    });
    p5.push(cpwR(1690,830,230,230,accent,115,0.05));
    p5.push(cpwR(1768,910,112,112,accent,56,0.08));

    /* ═══════════════════════════════════════════
       P6 — Mission & Vision  (interior image: accent left + dark right)
       ═══════════════════════════════════════════ */
    var p6 = [];
    if (!bgInt) { p6.push(cpwR(0,0,960,1080,accent)); p6.push(cpwR(960,0,960,1080,dark)); }
    p6.push(cpwR(0,0,960,1080,accent,0,0.88));      /* accent overlay left 88% */
    p6.push(cpwR(960,0,960,1080,dark,0,0.90));      /* dark overlay right 90% */
    p6.push(cpwR(954,0,12,1080,white,0,0.12));      /* center divider */
    /* Left: Mission */
    p6.push(cpwT(76,120,840,'Our',68,white,'300','italic'));
    p6.push(cpwT(76,196,840,'Mission',90,white,'800'));
    p6.push(cpwR(76,308,200,5,white,0,0.70));
    p6.push(cpwT(76,334,848,mission,27,white,'normal','normal','left',1.82));
    p6.push(cpwR(76,706,96,96,white,20,0.18));
    p6.push(cpwT(76,720,96,'🎯',36,white,'normal','normal','center'));
    /* Right: Vision */
    p6.push(cpwT(1040,120,840,'Our',68,white,'300','italic'));
    p6.push(cpwT(1040,196,840,'Vision',90,accent,'800'));
    p6.push(cpwR(1040,308,200,5,accent,0,0.80));
    p6.push(cpwT(1040,334,848,vision,27,white,'normal','normal','left',1.82));
    p6.push(cpwR(1040,706,96,96,accent,20,0.30));
    p6.push(cpwT(1040,720,96,'🔭',36,white,'normal','normal','center'));
    /* Footer */
    p6.push(cpwT(76,984,760,d.name||'',24,white,'600'));
    p6.push(cpwT(1040,984,800,yr,24,accent,'800','normal','right'));

    /* ═══════════════════════════════════════════
       P7 — Our Values  (services image: solid dark header + white card body)
       ═══════════════════════════════════════════ */
    var p7 = [];
    if (!bgSvc) p7.push(cpwR(0,0,1920,1080,white));
    p7.push(ov(white,0.88));                      /* white 88% over image */
    p7.push(cpwR(0,0,1920,210,dark));             /* solid dark header (covers overlay) */
    p7.push(cpwR(0,0,12,210,accent));
    p7.push(cpwR(1790,0,130,210,accent,0,0.07));
    p7.push(cpwT(120,48,1200,'Our Values',68,white,'800'));
    p7.push(cpwT(120,144,900,d.name||'',26,accent,'600'));
    values.forEach(function(v,i){
        var vx=120+i*455, vy=248;
        p7.push(cpwR(vx,vy,420,752,white,22));
        p7.push(cpwR(vx,vy,420,9,accent));
        p7.push(cpwMakeBase('textbox',{left:vx+12,top:vy+560,width:396,text:String(i+1).padStart(2,'0'),fontSize:142,fill:accent,fontFamily:'Arial',fontWeight:'800',opacity:0.05,textAlign:'left',lineHeight:1,charSpacing:0,underline:false,overline:false,linethrough:false,textBackgroundColor:'',styles:{}}));
        p7.push(cpwR(vx+165,vy+58,90,90,accent,45));
        var vIcons=['◈','◉','◊','◆'];
        p7.push(cpwT(vx+165,vy+70,90,vIcons[i]||'●',34,white,'800','normal','center'));
        p7.push(cpwT(vx+28,vy+182,364,v.title||'',32,dark,'800','normal','center'));
        p7.push(cpwR(vx+150,vy+228,120,3,accent));
        if(v.desc) p7.push(cpwT(vx+28,vy+252,364,v.desc,22,slate,'normal','normal','center',1.60));
    });

    /* ═══════════════════════════════════════════
       P8 — Portfolio  (cover image: dark overlay, styled cards)
       ═══════════════════════════════════════════ */
    var p8 = [];
    if (!bgCov) p8.push(cpwR(0,0,1920,1080,dark));
    p8.push(ov('#000',0.72));
    p8.push(cpwR(0,0,12,1080,accent));
    p8.push(cpwR(0,0,1920,12,accent));
    p8.push(cpwT(120,70,1200,portHead,66,white,'800'));
    p8.push(cpwR(120,148,140,5,accent));
    p8.push(cpwT(120,176,1400,'Selected Work & Case Studies',28,muted));
    portItems.forEach(function(pt,i){
        var px=120+i*598;
        /* Card frame */
        p8.push(cpwR(px,232,566,700,'#1E293B',18,1));
        p8.push(cpwR(px,232,566,6,accent));
        /* Styled image-placeholder area */
        p8.push(cpwR(px+6,238,554,388,'#0E1E35',16));
        for(var gl=0;gl<4;gl++){
            p8.push(cpwR(px+36+gl*122,272,1,320,accent,0,0.16));
            p8.push(cpwR(px+12,284+gl*68,542,1,accent,0,0.12));
        }
        p8.push(cpwR(px+228,362,110,110,accent,55,0.22));
        p8.push(cpwT(px+228,376,110,'◈',44,accent,'800','normal','center'));
        /* Category tag */
        p8.push(cpwR(px+20,646,240,44,accent,22,0.22));
        p8.push(cpwT(px+20,654,240,pt.category||'Project',20,accent,'700','normal','center'));
        /* Project title + line */
        p8.push(cpwT(px+20,704,520,pt.title||'',28,white,'700','normal','left',1.30));
        p8.push(cpwR(px+20,764,90,3,accent,0,0.65));
    });
    p8.push(cpwT(1380,990,420,'View All Projects →',24,accent,'600','normal','right'));

    /* ═══════════════════════════════════════════
       P9 — Testimonials  (interior image: white texture overlay)
       ═══════════════════════════════════════════ */
    var p9 = [];
    if (!bgInt) p9.push(cpwR(0,0,1920,1080,light));
    p9.push(ov(white,0.87));
    p9.push(cpwR(0,0,1920,12,accent));
    p9.push(cpwR(0,12,12,1068,accent));
    p9.push(cpwT(120,74,1400,'What Our Clients Say',62,dark,'800'));
    p9.push(cpwR(120,150,140,5,accent));
    p9.push(cpwT(120,180,1100,d.name||'',26,muted,'600'));
    p9.push(cpwT(120,220,640,'★ ★ ★ ★ ★  5-star client satisfaction',26,accent,'700'));
    tms.forEach(function(tm,i){
        var tx=120+i*918;
        p9.push(cpwR(tx,284,876,710,white,24));
        p9.push(cpwR(tx,284,876,9,accent));
        /* Decorative large quote mark */
        p9.push(cpwMakeBase('textbox',{left:tx+26,top:298,width:130,text:'“',fontSize:130,fill:accent,fontFamily:'Arial',fontWeight:'800',opacity:0.14,textAlign:'left',lineHeight:1,charSpacing:0,underline:false,overline:false,linethrough:false,textBackgroundColor:'',styles:{}}));
        /* Quote text — longer, italic */
        p9.push(cpwT(tx+42,420,788,'“'+tm.quote+'”',25,slate,'normal','italic','left',1.75));
        /* Divider + attribution */
        p9.push(cpwR(tx+42,652,120,4,accent));
        p9.push(cpwT(tx+42,672,780,tm.name||'',30,dark,'700'));
        p9.push(cpwT(tx+42,716,780,tm.role||'',22,muted));
        p9.push(cpwT(tx+42,758,320,'★ ★ ★ ★ ★',22,accent,'800'));
    });
    p9.push(cpwT(120,998,1680,'Delivering excellence and building lasting partnerships across industries',22,muted,'normal','italic','center'));

    /* ═══════════════════════════════════════════
       P10 — Contact & CTA  (cover image: heavy dark overlay)
       ═══════════════════════════════════════════ */
    var hasSoc=d.instagram||d.facebook||d.twitter||d.linkedin||d.whatsapp;
    var p10=[];
    if (!bgCov) p10.push(cpwR(0,0,1920,1080,dark));
    p10.push(ov('#000',0.80));
    p10.push(cpwR(0,0,12,1080,accent));
    p10.push(cpwR(0,0,1920,12,accent));
    p10.push(cpwR(1760,0,160,1080,accent,0,0.05));
    /* CTA hero */
    p10.push(cpwR(0,0,1920,298,accent,0,0.13));
    p10.push(cpwT(120,56,1680,cta.toUpperCase(),58,white,'800','normal','center'));
    p10.push(cpwR(760,142,400,5,white,0,0.30));
    p10.push(cpwT(120,170,1680,'We would love to hear from you and explore how we can help your business thrive.',26,white,'300','italic','center'));
    /* Contact */
    p10.push(cpwT(120,338,580,'Contact Details',36,white,'800'));
    p10.push(cpwR(120,386,160,4,accent));
    var cy10=414;
    function addC10(icon,val){if(!val)return;p10.push(cpwT(120,cy10,50,icon,28,accent));p10.push(cpwT(178,cy10+2,564,val,24,white,'normal','normal','left',1.35));cy10+=80;}
    addC10('📍',d.address);
    addC10('📞',d.phone);
    addC10('✉',d.email);
    addC10('🌐',d.website);
    /* Social */
    if(hasSoc){
        p10.push(cpwR(812,330,4,610,accent,0,0.25));
        p10.push(cpwT(872,338,580,'Social Media',36,white,'800'));
        p10.push(cpwR(872,386,160,4,accent));
        var sy10=414;
        function addS10(icon,val){if(!val)return;p10.push(cpwT(872,sy10,50,icon,26,accent));p10.push(cpwT(930,sy10+2,644,val,24,white));sy10+=76;}
        addS10('📷',d.instagram?'Instagram: '+d.instagram:null);
        addS10('📘',d.facebook ?'Facebook: ' +d.facebook:null);
        addS10('🐦',d.twitter  ?'Twitter: '  +d.twitter:null);
        addS10('💼',d.linkedin ?'LinkedIn: ' +d.linkedin:null);
        addS10('💬',d.whatsapp ?'WhatsApp: ' +d.whatsapp:null);
    }
    /* Footer */
    p10.push(cpwR(0,946,1920,134,accent,0,0.14));
    p10.push(cpwR(0,946,1920,5,accent));
    if(d.logoUrl) p10.push(cpwMakeBase('image',{left:120,top:966,width:82,height:82,src:d.logoUrl,crossOrigin:'anonymous',scaleX:1,scaleY:1}));
    var bL=d.logoUrl?224:120;
    p10.push(cpwT(bL,968,700,d.name||'',30,white,'700'));
    p10.push(cpwT(bL,1014,780,tagline,20,muted,'normal','italic'));
    p10.push(cpwT(1566,990,234,yr,28,accent,'800','normal','right'));

    return [
        cpwPageJson(p1,  dark,  bgCov),
        cpwPageJson(p2,  white, bgInt),
        cpwPageJson(p3,  white, bgSvc),
        cpwPageJson(p4,  light, bgSvc),
        cpwPageJson(p5,  dark,  bgCov),
        cpwPageJson(p6,  dark,  bgInt),
        cpwPageJson(p7,  white, bgSvc),
        cpwPageJson(p8,  dark,  bgCov),
        cpwPageJson(p9,  light, bgInt),
        cpwPageJson(p10, dark,  bgCov),
    ];
}

/* ─── Fallback template (no AI) — passes empty imgData ─── */
function cpwBuildFallbackPages(d) {
    return cpwBuildAiPages(d, null, {cover:{w:0,h:0,src:null},interior:{w:0,h:0,src:null},services:{w:0,h:0,src:null}});
}
</script>

<script>
/* ══════════════════════════════════════════════════════
   LETTER HEAD CHOICE MODAL
   ══════════════════════════════════════════════════════ */
var lhcAccent = '#3B82F6';
var lhcGenerating = false;
var lhcLoadingTimers = [];

function openLhChoiceModal() {
    lhcAccent = '#3B82F6';
    lhcGenerating = false;
    document.getElementById('lhcViewChoice').style.display = '';
    document.getElementById('lhcViewForm').style.display = 'none';
    document.getElementById('lhcLoading').classList.remove('on');
    document.getElementById('lhcErr').classList.remove('on');
    /* Reset colour swatches to default */
    document.querySelectorAll('#lhcSwatches .cpw-swatch').forEach(function(b) {
        b.classList.toggle('sel', b.dataset.color === '#3B82F6');
    });
    document.getElementById('lhcColorCustom').value = '#3B82F6';
    document.getElementById('lhcBackdrop').classList.add('open');
}
function closeLhChoiceModal() {
    if (lhcGenerating) return;
    document.getElementById('lhcBackdrop').classList.remove('open');
}
document.getElementById('lhcBackdrop').addEventListener('click', function(e) {
    if (e.target === this) closeLhChoiceModal();
});

function lhcChooseManual() {
    closeLhChoiceModal();
    startEditor(794, 1123, 'letterhead');
}
function lhcChooseAI() {
    document.getElementById('lhcViewChoice').style.display = 'none';
    document.getElementById('lhcViewForm').style.display = '';
}
function lhcBackToChoice() {
    document.getElementById('lhcViewForm').style.display = 'none';
    document.getElementById('lhcViewChoice').style.display = '';
}

function lhcPickColor(btn) {
    document.querySelectorAll('#lhcSwatches .cpw-swatch').forEach(function(b) { b.classList.remove('sel'); });
    btn.classList.add('sel');
    lhcAccent = btn.dataset.color;
    document.getElementById('lhcColorCustom').value = lhcAccent;
}
function lhcPickCustomColor(inp) {
    lhcAccent = inp.value;
    document.querySelectorAll('#lhcSwatches .cpw-swatch').forEach(function(b) { b.classList.remove('sel'); });
}

function lhcGetData() {
    return {
        name:    (document.getElementById('lhcName').value    || '').trim(),
        tagline: (document.getElementById('lhcTagline').value || '').trim(),
        address: (document.getElementById('lhcAddress').value || '').trim(),
        phone:   (document.getElementById('lhcPhone').value   || '').trim(),
        email:   (document.getElementById('lhcEmail').value   || '').trim(),
        website: (document.getElementById('lhcWebsite').value || '').trim(),
        color:   lhcAccent,
        logoUrl: @json($business->logoUrl()),
    };
}

function lhcShowLoading() {
    document.getElementById('lhcViewForm').style.display = 'none';
    document.getElementById('lhcLoading').classList.add('on');
    document.getElementById('lhcErr').classList.remove('on');
    /* Reset all steps */
    ['lhcLS1','lhcLS2','lhcLS3'].forEach(function(id) {
        var el = document.getElementById(id);
        el.classList.remove('active', 'done');
    });
    document.getElementById('lhcLS1').classList.add('active');
    /* Advance steps visually */
    lhcLoadingTimers.push(setTimeout(function() {
        document.getElementById('lhcLS1').classList.remove('active');
        document.getElementById('lhcLS1').classList.add('done');
        document.getElementById('lhcLS2').classList.add('active');
    }, 3000));
    lhcLoadingTimers.push(setTimeout(function() {
        document.getElementById('lhcLS2').classList.remove('active');
        document.getElementById('lhcLS2').classList.add('done');
        document.getElementById('lhcLS3').classList.add('active');
    }, 7000));
}
function lhcHideLoading() {
    lhcLoadingTimers.forEach(clearTimeout);
    lhcLoadingTimers = [];
    document.getElementById('lhcLoading').classList.remove('on');
}

function lhcGenerate() {
    if (lhcGenerating) return;
    var d = lhcGetData();
    if (!d.name) { document.getElementById('lhcName').focus(); return; }
    lhcGenerating = true;
    lhcShowLoading();

    var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    fetch('{{ route('designstudio.generate.letterhead') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify(d),
    })
    .then(function(r) { return r.json(); })
    .then(function(result) {
        lhcGenerating = false;
        /* Mark all steps done */
        ['lhcLS1','lhcLS2','lhcLS3'].forEach(function(id) {
            var el = document.getElementById(id);
            el.classList.remove('active');
            el.classList.add('done');
        });
        lhcBuildAndLaunch(d, (result && result.success) ? result : null);
    })
    .catch(function() {
        lhcGenerating = false;
        lhcBuildAndLaunch(d, null);
    });
}

function lhcBuildAndLaunch(d, aiResult) {
    var page = lhcBuildPage(d, aiResult);
    try { sessionStorage.setItem('dsWizardTemplate', JSON.stringify([page])); } catch(e) {}
    lhcHideLoading();
    closeLhChoiceModal();
    startEditor(794, 1123, 'letterhead');
}

/* ══════════════════════════════════════════════════════
   LETTERHEAD CANVAS BUILDER — 794×1123 A4 portrait
   White background, professional corporate layout
   ══════════════════════════════════════════════════════ */
function lhcBuildPage(d, ai) {
    var W = 794, H = 1123;
    var c       = (ai && ai.content) ? ai.content : {};
    var accent  = d.color || '#3B82F6';
    var dark    = '#1E293B';
    var slate   = '#475569';
    var muted   = '#94A3B8';
    var white   = '#FFFFFF';
    var tagline = c.tagline || d.tagline || '';
    var hasLogo = !!d.logoUrl;
    var nameX   = hasLogo ? 126 : 40;

    var objects = [];

    /* ── Top accent strip ── */
    objects.push(cpwR(0, 0, W, 6, accent));

    /* ── Header: left side — logo + company identity ── */
    if (hasLogo) {
        /* Logo: pre-loaded to 76×76 display area */
        objects.push(cpwMakeBase('image', {
            left:40, top:18, width:76, height:76,
            src: d.logoUrl, crossOrigin:'anonymous',
            scaleX:1, scaleY:1
        }));
        /* Thin vertical rule separating logo from text */
        objects.push(cpwR(128, 22, 1.5, 68, accent, 0, 0.35));
    }

    /* Company name */
    objects.push(cpwT(nameX + (hasLogo ? 16 : 0), 20, 400, d.name || 'Company Name', 22, dark, '800', 'normal', 'left', 1.15));

    /* Tagline */
    if (tagline) {
        objects.push(cpwT(nameX + (hasLogo ? 16 : 0), 50, 400, tagline, 11, slate, '400', 'italic', 'left', 1.3));
    }

    /* ── Header: right side — contact block (right-aligned) ── */
    var contactLines = [];
    if (d.address) contactLines.push(d.address);
    if (d.phone)   contactLines.push(d.phone);
    if (d.email)   contactLines.push(d.email);
    if (d.website) contactLines.push(d.website);
    if (contactLines.length) {
        objects.push(cpwT(494, 18, 260, contactLines.join('\n'), 10, slate, '400', 'normal', 'right', 1.75));
    }

    /* ── Divider ── */
    var divY = 106;
    objects.push(cpwR(0, divY, W, 1, 'rgba(0,0,0,0.08)'));
    objects.push(cpwR(0, divY + 1, W, 4, accent, 0, 0.18));

    /* ── Footer ── */
    /* Light separator */
    objects.push(cpwR(0, 1074, W, 1, 'rgba(0,0,0,0.08)'));
    /* Thin accent band */
    objects.push(cpwR(0, 1075, W, 4, accent, 0, 0.35));

    /* Footer contact line (centered, small) */
    var footerParts = [];
    if (d.address) footerParts.push(d.address);
    if (d.phone)   footerParts.push(d.phone);
    if (d.email)   footerParts.push(d.email);
    if (d.website) footerParts.push(d.website);
    if (footerParts.length) {
        objects.push(cpwT(48, 1086, W - 96, footerParts.join('   ·   '), 10, slate, '400', 'normal', 'center', 1.3));
    }

    /* Bottom accent strip */
    objects.push(cpwR(0, 1113, W, 10, accent, 0, 0.12));

    return {json: JSON.stringify({version:'5.3.0', objects: objects, background: white}), thumb: null};
}

function dsLhCopyLink(btn, url) {
    navigator.clipboard.writeText(url).then(function () {
        var icon = btn.querySelector('i');
        var prev = icon ? icon.className : '';
        if (icon) icon.className = 'fa fa-check';
        btn.style.color = '#22c55e';
        btn.style.borderColor = '#22c55e';
        setTimeout(function () {
            if (icon) icon.className = prev;
            btn.style.color = '';
            btn.style.borderColor = '';
        }, 1800);
    }).catch(function () {
        window.prompt('Copy this link:', url);
    });
}
</script>
@endsection
