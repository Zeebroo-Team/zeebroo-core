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
.ds-empty{
    text-align:center;padding:52px 32px 44px;
    border:1.5px dashed var(--border);
    border-radius:16px;background:var(--card);
}
.ds-empty-icon{
    width:80px;height:80px;border-radius:20px;margin:0 auto 20px;
    background:color-mix(in srgb,var(--text) 7%,var(--card));
    border:1px solid var(--border);
    display:flex;align-items:center;justify-content:center;
    font-size:32px;color:var(--muted);
}
.ds-empty h3{margin:0 0 8px;font-size:18px;font-weight:800;letter-spacing:-.02em;color:var(--text);}
.ds-empty-sub{margin:0 auto 24px;font-size:13px;color:var(--muted);max-width:380px;line-height:1.6;}
.ds-create-btn{
    display:inline-flex;align-items:center;justify-content:center;gap:8px;
    padding:10px 20px;border-radius:10px;border:0;cursor:pointer;font-family:inherit;
    background:var(--text);color:var(--bg);font-size:13px;font-weight:700;
    transition:all .2s ease;text-decoration:none;
}
.ds-create-btn:hover{opacity:.85;transform:translateY(-1px);}
.ds-create-btn:active{transform:translateY(0);}
.ds-empty-hint{margin-top:14px;font-size:12px;color:var(--muted);}

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
    border-color:var(--primary);
    transform:translateY(-2px);
}
.ds-ql-card.checked{
    border-color:var(--text);
    background:var(--card);
}

/* header row: icon left, checkbox right */
.ds-ql-head{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:12px;}

.ds-ql-icon{
    width:46px;height:46px;border-radius:11px;flex-shrink:0;
    background:color-mix(in srgb,var(--text) 7%,var(--card));
    border:1px solid var(--border);
    color:var(--muted);display:flex;align-items:center;justify-content:center;
    font-size:20px;transition:background .2s,color .2s,border-color .2s;
}
.ds-ql-card:hover .ds-ql-icon{
    background:color-mix(in srgb,var(--primary) 12%,var(--bg));
    color:var(--primary);
    border-color:color-mix(in srgb,var(--primary) 35%,var(--border));
}
.ds-ql-card.checked .ds-ql-icon{
    background:var(--text);color:var(--bg);border-color:var(--text);
}

.ds-ql-check{
    width:24px;height:24px;border-radius:50%;flex-shrink:0;
    border:2px solid var(--border);background:transparent;
    display:flex;align-items:center;justify-content:center;
    transition:all .2s;
}
.ds-ql-card.checked .ds-ql-check{background:var(--text);border-color:var(--text);}
.ds-ql-check i{font-size:11px;color:var(--bg);display:none;}
.ds-ql-card.checked .ds-ql-check i{display:block;}

/* body text */
.ds-ql-name{font-size:13px;font-weight:800;color:var(--text);margin:0 0 5px;line-height:1.25;}
.ds-ql-desc{font-size:11px;color:var(--muted);margin:0 0 10px;line-height:1.5;}
.ds-ql-size{
    display:inline-block;font-size:10px;font-weight:700;letter-spacing:.03em;
    font-family:monospace;color:var(--muted);
    background:transparent;
    border:1px solid var(--border);
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
.ds-ql-btn:hover{background:var(--border);border-color:var(--border);color:var(--text);}
.ds-ql-btn--edit{
    background:color-mix(in srgb,var(--text) 6%,transparent);
    border-color:var(--border);
    color:var(--text);
}
.ds-ql-btn--edit:hover{background:var(--text);border-color:var(--text);color:var(--bg);}

/* ── Start from a template gallery ─────────────────────────── */
.ds-tpl-filters{display:flex;flex-wrap:wrap;gap:8px;margin:10px 0 16px;}
.ds-tpl-filter{
    border:1px solid var(--border);background:var(--card);color:var(--muted);
    font-size:12px;font-weight:700;padding:7px 14px;border-radius:20px;cursor:pointer;
    font-family:inherit;transition:background .15s ease,color .15s ease,border-color .15s ease;
}
.ds-tpl-filter:hover{color:var(--text);border-color:color-mix(in srgb,var(--primary) 40%,var(--border));}
.ds-tpl-filter.active{background:var(--primary);color:#fff;border-color:var(--primary);}

.ds-tpl-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:16px;margin-bottom:24px;}
.ds-tpl-card{
    display:flex;flex-direction:column;width:100%;margin:0;padding:0;font:inherit;appearance:none;
    border:1px solid var(--border);border-radius:12px;overflow:hidden;cursor:pointer;text-align:left;
    background:var(--card);transition:transform .15s ease,box-shadow .15s ease,border-color .15s ease;
}
.ds-tpl-card:hover{transform:translateY(-3px);box-shadow:0 12px 22px rgba(0,0,0,.14);border-color:color-mix(in srgb,var(--primary) 35%,var(--border));}
.ds-tpl-card::before{content:'';display:block;height:4px;width:100%;flex:0 0 auto;background:var(--tpl-accent,var(--border));}
.ds-tpl-preview-wrap{flex:1 1 auto;display:flex;flex-direction:column;justify-content:center;overflow:hidden;}
.ds-tpl-preview{position:relative;display:block;width:100%;flex:0 0 auto;aspect-ratio:var(--tpl-ar);overflow:hidden;}
.ds-tpl-mock{position:absolute;}
.ds-tpl-overlay{
    position:absolute;inset:0;
    background:linear-gradient(to top,rgba(15,12,28,.55) 0%,rgba(15,12,28,.15) 45%,transparent 65%);
    display:flex;align-items:flex-end;justify-content:flex-start;padding:10px;opacity:0;transition:opacity .15s ease;
}
.ds-tpl-card:hover .ds-tpl-overlay{opacity:1;}
.ds-tpl-use-btn{
    background:#fff;color:#211f38;font-size:11px;font-weight:700;padding:6px 12px;border-radius:20px;
    display:inline-flex;align-items:center;gap:5px;box-shadow:0 4px 10px rgba(0,0,0,.25);
}
.ds-tpl-spinner{
    position:absolute;inset:0;display:none;align-items:center;justify-content:center;
    background:rgba(255,255,255,.85);font-size:20px;color:var(--primary);
}
.ds-tpl-card.ds-tpl-loading{pointer-events:none;}
.ds-tpl-card.ds-tpl-loading .ds-tpl-spinner{display:flex;}
.ds-tpl-card.ds-tpl-loading .ds-tpl-overlay{opacity:0;}
.ds-tpl-info{display:flex;flex-direction:column;gap:3px;padding:9px 11px 11px;}
.ds-tpl-name{font-size:12.5px;font-weight:700;color:var(--text);}
.ds-tpl-type{font-size:10px;font-weight:600;color:var(--tpl-accent,var(--muted));}
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
            ['type'=>'custom',          'label'=>'Custom Design',   'icon'=>'fa-paintbrush',  'w'=>1200, 'h'=>1600, 'desc'=>'Start from a blank canvas for posters, flyers and more.'],
            ['type'=>'sales-campaign',  'label'=>'Sales Campaign',  'icon'=>'fa-bullhorn',    'w'=>1080, 'h'=>1080, 'desc'=>'Promos, discounts and offers for your campaigns.'],
            ['type'=>'hire-designer',   'label'=>'Hire a Designer', 'icon'=>'fa-user-tie',    'w'=>1200, 'h'=>1600, 'desc'=>'Job posting poster to find your next design hire.'],
        ];
    @endphp
    <div class="ds-ql-grid">
        @foreach($qlItems as $item)
            @php $existing = $designsByType->get($item['type']); @endphp
            <div id="{{ $item['type'] }}" class="ds-ql-card {{ $existing ? 'checked' : '' }}">
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

    {{-- ── Start from a template ── --}}
    @php
        $dsSingletonEditUrls = [
            'letterhead' => $designsByType->get('letterhead') ? route('designstudio.editor.edit', $designsByType->get('letterhead')) : null,
            'company-profile' => $designsByType->get('company-profile') ? route('designstudio.editor.edit', $designsByType->get('company-profile')) : null,
        ];
    @endphp
    <p class="ds-hub-section" style="margin-bottom:10px;">
        <i class="fa fa-wand-magic-sparkles" aria-hidden="true"></i> Start from a template
    </p>
    <div class="ds-tpl-filters" id="dsTplFilters">
        <button type="button" class="ds-tpl-filter active" data-tpl-filter="all">All</button>
        <button type="button" class="ds-tpl-filter" data-tpl-filter="letterhead">Letterhead</button>
        <button type="button" class="ds-tpl-filter" data-tpl-filter="business-card">Business Card</button>
        <button type="button" class="ds-tpl-filter" data-tpl-filter="social-media">Social Media</button>
        <button type="button" class="ds-tpl-filter" data-tpl-filter="sales-campaign">Sales Campaign</button>
        <button type="button" class="ds-tpl-filter" data-tpl-filter="hire-designer">Hire a Designer</button>
        <button type="button" class="ds-tpl-filter" data-tpl-filter="custom">Custom Design</button>
    </div>
    <div class="ds-tpl-grid" id="dsTplGrid"></div>
    {{-- ── /Start from a template ── --}}

    <p class="ds-hub-section"><i class="fa fa-pencil-ruler" aria-hidden="true"></i> My Designs ({{ $designs->count() }})</p>

    @if($designs->isEmpty())
        <div class="ds-empty">
            <div class="ds-empty-icon">
                <i class="fa fa-palette" aria-hidden="true"></i>
            </div>
            <h3>No designs yet</h3>
            <p class="ds-empty-sub">Create social media graphics, business cards, letterheads, banners and more — pick a canvas size and open the editor.</p>
            <button class="ds-create-btn" onclick="openNewDesignModal()">
                <i class="fa fa-plus" aria-hidden="true"></i>
                Create First Design
            </button>
            <p class="ds-empty-hint">Or use a <strong style="color:var(--text);">Quick Start</strong> card above to jump into a template</p>
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
     START FROM A TEMPLATE — gallery data + rendering
     ══════════════════════════════════════════════════════ --}}
<script>
var DS_SINGLETON_EDIT_URLS = @json($dsSingletonEditUrls);

var DS_TPL_TYPES = {
    'letterhead':     { label: 'Letterhead',      color: '#6366f1' },
    'business-card':  { label: 'Business Card',   color: '#f59e0b' },
    'social-media':   { label: 'Social Media',    color: '#ec4899' },
    'sales-campaign': { label: 'Sales Campaign',  color: '#dc2626' },
    'hire-designer':  { label: 'Hire a Designer', color: '#0284c7' },
    'custom':         { label: 'Custom',          color: '#10b981' },
};

// Starter templates for the "Start from a template" gallery. Each `build()`
// returns a Fabric.js 5.3.0 canvas payload ({version, background, objects})
// that is stashed in sessionStorage as `dsWizardTemplate` and picked up by
// the editor on load — nothing is written to the server until the user Saves.
var DS_TEMPLATES = [
    // ── Letterhead (7) ──────────────────────────────────────────────────────
    { id: 'letterhead-modern', type: 'letterhead', title: 'Modern Letterhead', width: 794, height: 1123,
      build: function() { return {
        version: '5.3.0', background: '#ffffff',
        objects: [
          { type: 'rect', left: 0, top: 0, width: 794, height: 150, fill: '#6366f1' },
          { type: 'textbox', text: 'Your Company Name', left: 48, top: 42, width: 500, fontSize: 30, fontWeight: 'bold', fill: '#ffffff', fontFamily: 'Montserrat' },
          { type: 'textbox', text: 'Tagline goes here', left: 48, top: 90, width: 500, fontSize: 14, fill: 'rgba(255,255,255,.85)', fontFamily: 'Inter' },
          { type: 'i-text', text: 'Dear [Recipient Name],', left: 48, top: 210, fontSize: 15, fill: '#111827', fontFamily: 'Inter' },
          { type: 'i-text', text: 'Start typing your letter here…', left: 48, top: 250, width: 680, fontSize: 13, fontStyle: 'italic', fill: '#6b7280', fontFamily: 'Inter' },
          { type: 'rect', left: 0, top: 1063, width: 794, height: 60, fill: '#f3f1fa' },
          { type: 'textbox', text: 'www.yourcompany.com   •   hello@yourcompany.com   •   +1 234 567 8900', left: 48, top: 1083, width: 700, fontSize: 11, fill: '#6b6b85', fontFamily: 'Inter' },
        ],
      }; } },
    { id: 'letterhead-classic', type: 'letterhead', title: 'Classic Letterhead', width: 794, height: 1123,
      build: function() { return {
        version: '5.3.0', background: '#ffffff',
        objects: [
          { type: 'rect', left: 0, top: 0, width: 794, height: 8, fill: '#0ea5e9' },
          { type: 'textbox', text: 'YOUR COMPANY NAME', left: 0, top: 60, width: 794, fontSize: 26, fontWeight: 'bold', fill: '#1f2430', fontFamily: 'Montserrat', textAlign: 'center' },
          { type: 'textbox', text: '123 Business Street, City, Country   •   +1 234 567 8900', left: 0, top: 100, width: 794, fontSize: 12, fill: '#6b7280', fontFamily: 'Inter', textAlign: 'center' },
          { type: 'rect', left: 140, top: 140, width: 514, height: 2, fill: '#e5e7eb' },
          { type: 'i-text', text: 'Dear [Recipient Name],', left: 70, top: 200, fontSize: 15, fill: '#111827', fontFamily: 'Inter' },
          { type: 'i-text', text: 'Start typing your letter here…', left: 70, top: 240, width: 650, fontSize: 13, fontStyle: 'italic', fill: '#6b7280', fontFamily: 'Inter' },
          { type: 'rect', left: 0, top: 1115, width: 794, height: 8, fill: '#0ea5e9' },
        ],
      }; } },
    { id: 'letterhead-elegant', type: 'letterhead', title: 'Elegant Letterhead', width: 794, height: 1123,
      build: function() { return {
        version: '5.3.0', background: '#ffffff',
        objects: [
          { type: 'rect', left: 0, top: 0, width: 794, height: 140, fill: '#7c3aed' },
          { type: 'circle', left: 48, top: 35, radius: 35, fill: 'rgba(255,255,255,.2)' },
          { type: 'textbox', text: 'CO', left: 58, top: 58, width: 60, fontSize: 24, fontWeight: 'bold', fill: '#ffffff', fontFamily: 'Montserrat', textAlign: 'center' },
          { type: 'textbox', text: 'Your Company Name', left: 140, top: 42, width: 500, fontSize: 26, fontWeight: 'bold', fill: '#ffffff', fontFamily: 'Montserrat' },
          { type: 'textbox', text: 'Elegant tagline goes here', left: 140, top: 84, width: 500, fontSize: 13, fill: 'rgba(255,255,255,.85)', fontFamily: 'Inter' },
          { type: 'i-text', text: 'Dear [Recipient Name],', left: 48, top: 200, fontSize: 15, fill: '#111827', fontFamily: 'Inter' },
          { type: 'i-text', text: 'Start typing your letter here…', left: 48, top: 240, width: 680, fontSize: 13, fontStyle: 'italic', fill: '#6b7280', fontFamily: 'Inter' },
          { type: 'rect', left: 0, top: 1113, width: 794, height: 10, fill: '#7c3aed' },
        ],
      }; } },
    { id: 'letterhead-bold', type: 'letterhead', title: 'Bold Letterhead', width: 794, height: 1123,
      build: function() { return {
        version: '5.3.0', background: '#ffffff',
        objects: [
          { type: 'rect', left: 0, top: 0, width: 794, height: 200, fill: '#111827' },
          { type: 'textbox', text: 'YOUR COMPANY', left: 48, top: 60, width: 600, fontSize: 34, fontWeight: 'bold', fill: '#ffffff', fontFamily: 'Montserrat' },
          { type: 'rect', left: 48, top: 115, width: 120, height: 6, fill: '#ef4444' },
          { type: 'textbox', text: 'Tagline goes here', left: 48, top: 135, width: 500, fontSize: 14, fill: 'rgba(255,255,255,.75)', fontFamily: 'Inter' },
          { type: 'i-text', text: 'Dear [Recipient Name],', left: 48, top: 260, fontSize: 15, fill: '#111827', fontFamily: 'Inter' },
          { type: 'i-text', text: 'Start typing your letter here…', left: 48, top: 300, width: 680, fontSize: 13, fontStyle: 'italic', fill: '#6b7280', fontFamily: 'Inter' },
        ],
      }; } },
    { id: 'letterhead-minimal', type: 'letterhead', title: 'Minimal Letterhead', width: 794, height: 1123,
      build: function() { return {
        version: '5.3.0', background: '#ffffff',
        objects: [
          { type: 'rect', left: 0, top: 0, width: 794, height: 4, fill: '#10b981' },
          { type: 'textbox', text: 'Your Company Name', left: 48, top: 40, width: 600, fontSize: 24, fill: '#1f2430', fontFamily: 'Inter' },
          { type: 'textbox', text: 'hello@yourcompany.com', left: 48, top: 78, width: 400, fontSize: 12, fill: '#6b7280', fontFamily: 'Inter' },
          { type: 'rect', left: 48, top: 112, width: 698, height: 1, fill: '#e5e7eb' },
          { type: 'i-text', text: 'Dear [Recipient Name],', left: 48, top: 150, fontSize: 15, fill: '#111827', fontFamily: 'Inter' },
          { type: 'i-text', text: 'Start typing your letter here…', left: 48, top: 190, width: 680, fontSize: 13, fontStyle: 'italic', fill: '#6b7280', fontFamily: 'Inter' },
        ],
      }; } },
    { id: 'letterhead-corporate', type: 'letterhead', title: 'Corporate Letterhead', width: 794, height: 1123,
      build: function() { return {
        version: '5.3.0', background: '#ffffff',
        objects: [
          { type: 'rect', left: 0, top: 0, width: 794, height: 10, fill: '#1e3a5f' },
          { type: 'textbox', text: 'YOUR COMPANY', left: 48, top: 50, width: 400, fontSize: 24, fontWeight: 'bold', fill: '#1e3a5f', fontFamily: 'Montserrat' },
          { type: 'textbox', text: 'Corporate Solutions', left: 48, top: 84, width: 400, fontSize: 12, fill: '#6b7280', fontFamily: 'Inter' },
          { type: 'rect', left: 650, top: 40, width: 96, height: 96, fill: '#eef2f6', rx: 8, ry: 8 },
          { type: 'rect', left: 48, top: 160, width: 698, height: 1, fill: '#e5e7eb' },
          { type: 'i-text', text: 'Dear [Recipient Name],', left: 48, top: 210, fontSize: 15, fill: '#111827', fontFamily: 'Inter' },
          { type: 'i-text', text: 'Start typing your letter here…', left: 48, top: 250, width: 680, fontSize: 13, fontStyle: 'italic', fill: '#6b7280', fontFamily: 'Inter' },
          { type: 'rect', left: 0, top: 1113, width: 794, height: 10, fill: '#1e3a5f' },
        ],
      }; } },
    { id: 'letterhead-creative', type: 'letterhead', title: 'Creative Letterhead', width: 794, height: 1123,
      build: function() { return {
        version: '5.3.0', background: '#ffffff',
        objects: [
          { type: 'rect', left: 0, top: 0, width: 794, height: 12, fill: '#ec4899' },
          { type: 'circle', left: 660, top: 40, radius: 70, fill: '#fbcfe8' },
          { type: 'circle', left: 600, top: 100, radius: 40, fill: '#f9a8d4' },
          { type: 'textbox', text: 'Your Company Name', left: 48, top: 60, width: 500, fontSize: 28, fontWeight: 'bold', fill: '#1f2430', fontFamily: 'Montserrat' },
          { type: 'textbox', text: 'Creative Studio', left: 48, top: 102, width: 400, fontSize: 13, fill: '#ec4899', fontFamily: 'Inter' },
          { type: 'i-text', text: 'Dear [Recipient Name],', left: 48, top: 220, fontSize: 15, fill: '#111827', fontFamily: 'Inter' },
          { type: 'i-text', text: 'Start typing your letter here…', left: 48, top: 260, width: 680, fontSize: 13, fontStyle: 'italic', fill: '#6b7280', fontFamily: 'Inter' },
        ],
      }; } },
    { id: 'letterhead-generate', type: 'letterhead', title: 'Generate Letter', width: 794, height: 1123,
      build: function() { return {
        version: '5.3.0', background: '#ffffff',
        objects: [
          { type: 'textbox', text: 'Your Company Name', left: 48, top: 48, width: 500, fontSize: 22, fontWeight: 'bold', fill: '#1f2430', fontFamily: 'Montserrat' },
          { type: 'textbox', text: '123 Business Street, City, Country   •   hello@yourcompany.com', left: 48, top: 78, width: 600, fontSize: 11, fill: '#6b7280', fontFamily: 'Inter' },
          { type: 'i-text', text: '[Date]', left: 640, top: 48, fontSize: 12, fill: '#6b7280', fontFamily: 'Inter' },
          { type: 'i-text', text: '[Recipient Name]', left: 48, top: 170, fontSize: 13, fill: '#111827', fontFamily: 'Inter' },
          { type: 'i-text', text: '[Recipient Address]', left: 48, top: 195, fontSize: 13, fill: '#111827', fontFamily: 'Inter' },
          { type: 'i-text', text: 'Subject: [Letter Subject]', left: 48, top: 260, fontSize: 14, fontWeight: 'bold', fill: '#111827', fontFamily: 'Inter' },
          { type: 'i-text', text: 'Dear [Recipient Name],', left: 48, top: 310, fontSize: 13, fill: '#111827', fontFamily: 'Inter' },
          { type: 'i-text', text: 'I am writing to inform you about… Start typing the first paragraph of your letter here, covering the main point you want to make.', left: 48, top: 350, width: 698, fontSize: 12.5, fill: '#374151', fontFamily: 'Inter', lineHeight: 1.5 },
          { type: 'i-text', text: 'Use this second paragraph to add supporting details, next steps, or any additional context the recipient needs to know.', left: 48, top: 430, width: 698, fontSize: 12.5, fill: '#374151', fontFamily: 'Inter', lineHeight: 1.5 },
          { type: 'i-text', text: 'Please feel free to reach out if you have any questions.', left: 48, top: 510, width: 698, fontSize: 12.5, fill: '#374151', fontFamily: 'Inter', lineHeight: 1.5 },
          { type: 'i-text', text: 'Sincerely,', left: 48, top: 600, fontSize: 13, fill: '#111827', fontFamily: 'Inter' },
          { type: 'i-text', text: '[Your Name]', left: 48, top: 660, fontSize: 13, fontWeight: 'bold', fill: '#111827', fontFamily: 'Inter' },
          { type: 'i-text', text: '[Your Title]', left: 48, top: 682, fontSize: 12, fill: '#6b7280', fontFamily: 'Inter' },
        ],
      }; } },
    // ── Business Card (6) ───────────────────────────────────────────────────
    { id: 'business-card-minimal', type: 'business-card', title: 'Minimal Card', width: 1050, height: 600,
      build: function() { return {
        version: '5.3.0', background: '#ffffff',
        objects: [
          { type: 'rect', left: 0, top: 0, width: 18, height: 600, fill: '#f59e0b' },
          { type: 'textbox', text: 'Full Name', left: 70, top: 210, width: 600, fontSize: 34, fontWeight: 'bold', fill: '#1f2430', fontFamily: 'Montserrat' },
          { type: 'textbox', text: 'Job Title  •  Company Name', left: 70, top: 262, width: 600, fontSize: 15, fill: '#6b7280', fontFamily: 'Inter' },
          { type: 'textbox', text: 'phone: +1 234 567 8900', left: 70, top: 420, width: 600, fontSize: 12, fill: '#374151', fontFamily: 'Inter' },
          { type: 'textbox', text: 'email: hello@company.com   •   www.company.com', left: 70, top: 448, width: 700, fontSize: 12, fill: '#374151', fontFamily: 'Inter' },
        ],
      }; } },
    { id: 'business-card-bold', type: 'business-card', title: 'Bold Card', width: 1050, height: 600,
      build: function() { return {
        version: '5.3.0', background: '#f59e0b',
        objects: [
          { type: 'circle', left: 850, top: 40, radius: 70, fill: 'rgba(255,255,255,.18)' },
          { type: 'textbox', text: 'COMPANY', left: 70, top: 230, width: 700, fontSize: 40, fontWeight: 'bold', fill: '#ffffff', fontFamily: 'Montserrat' },
          { type: 'textbox', text: 'Tagline / Slogan', left: 70, top: 290, width: 600, fontSize: 16, fill: 'rgba(255,255,255,.85)', fontFamily: 'Inter' },
          { type: 'textbox', text: 'Full Name  •  Job Title', left: 70, top: 500, width: 700, fontSize: 13, fill: '#ffffff', fontFamily: 'Inter' },
        ],
      }; } },
    { id: 'business-card-elegant', type: 'business-card', title: 'Elegant Card', width: 1050, height: 600,
      build: function() { return {
        version: '5.3.0', background: '#faf8fc',
        objects: [
          { type: 'textbox', text: 'Full Name', left: 0, top: 220, width: 1050, fontSize: 36, fontWeight: 'bold', fill: '#241f38', fontFamily: 'Montserrat', textAlign: 'center' },
          { type: 'textbox', text: 'Job Title', left: 0, top: 272, width: 1050, fontSize: 15, fill: '#7c3aed', fontFamily: 'Inter', textAlign: 'center' },
          { type: 'rect', left: 425, top: 330, width: 200, height: 2, fill: '#d8c9f5' },
          { type: 'textbox', text: 'email@company.com   •   +1 234 567 8900   •   www.company.com', left: 0, top: 360, width: 1050, fontSize: 12, fill: '#4b4560', fontFamily: 'Inter', textAlign: 'center' },
        ],
      }; } },
    { id: 'business-card-split', type: 'business-card', title: 'Modern Split Card', width: 1050, height: 600,
      build: function() { return {
        version: '5.3.0', background: '#ffffff',
        objects: [
          { type: 'rect', left: 525, top: 0, width: 525, height: 600, fill: '#0ea5e9' },
          { type: 'textbox', text: 'Full Name', left: 70, top: 220, width: 420, fontSize: 32, fontWeight: 'bold', fill: '#1f2430', fontFamily: 'Montserrat' },
          { type: 'textbox', text: 'Job Title', left: 70, top: 270, width: 420, fontSize: 15, fill: '#6b7280', fontFamily: 'Inter' },
          { type: 'textbox', text: 'COMPANY', left: 575, top: 260, width: 400, fontSize: 26, fontWeight: 'bold', fill: '#ffffff', fontFamily: 'Montserrat' },
        ],
      }; } },
    { id: 'business-card-navy', type: 'business-card', title: 'Professional Navy', width: 1050, height: 600,
      build: function() { return {
        version: '5.3.0', background: '#ffffff',
        objects: [
          { type: 'rect', left: 0, top: 540, width: 1050, height: 60, fill: '#1e3a5f' },
          { type: 'textbox', text: 'Full Name', left: 70, top: 180, width: 600, fontSize: 32, fontWeight: 'bold', fill: '#1f2430', fontFamily: 'Montserrat' },
          { type: 'textbox', text: 'Job Title', left: 70, top: 228, width: 600, fontSize: 15, fill: '#1e3a5f', fontFamily: 'Inter' },
          { type: 'rect', left: 70, top: 270, width: 60, height: 4, fill: '#1e3a5f' },
          { type: 'textbox', text: '+1 234 567 8900   •   hello@company.com', left: 70, top: 560, width: 700, fontSize: 12, fill: '#ffffff', fontFamily: 'Inter' },
        ],
      }; } },
    { id: 'business-card-gradient', type: 'business-card', title: 'Duotone Card', width: 1050, height: 600,
      build: function() { return {
        version: '5.3.0', background: '#6366f1',
        objects: [
          { type: 'rect', left: 525, top: 0, width: 525, height: 600, fill: '#8b5cf6' },
          { type: 'textbox', text: 'Full Name', left: 70, top: 240, width: 450, fontSize: 32, fontWeight: 'bold', fill: '#ffffff', fontFamily: 'Montserrat' },
          { type: 'textbox', text: 'Job Title', left: 70, top: 288, width: 450, fontSize: 14, fill: 'rgba(255,255,255,.85)', fontFamily: 'Inter' },
          { type: 'textbox', text: 'COMPANY', left: 600, top: 260, width: 380, fontSize: 24, fontWeight: 'bold', fill: 'rgba(255,255,255,.95)', fontFamily: 'Montserrat' },
        ],
      }; } },
    // ── Social Media (6) ────────────────────────────────────────────────────
    { id: 'social-sale-promo', type: 'social-media', title: 'Sale Promo', width: 1080, height: 1080,
      build: function() { return {
        version: '5.3.0', background: '#ec4899',
        objects: [
          { type: 'textbox', text: 'SALE', left: 0, top: 330, width: 1080, fontSize: 160, fontWeight: 'bold', fill: '#ffffff', fontFamily: 'Montserrat', textAlign: 'center' },
          { type: 'textbox', text: 'UP TO 50% OFF', left: 0, top: 520, width: 1080, fontSize: 50, fontWeight: 'bold', fill: '#ffffff', fontFamily: 'Inter', textAlign: 'center' },
          { type: 'rect', left: 390, top: 640, width: 300, height: 70, fill: '#ffffff', rx: 10, ry: 10 },
          { type: 'textbox', text: 'SHOP NOW', left: 390, top: 662, width: 300, fontSize: 22, fontWeight: 'bold', fill: '#ec4899', fontFamily: 'Inter', textAlign: 'center' },
        ],
      }; } },
    { id: 'social-announcement', type: 'social-media', title: 'Announcement', width: 1080, height: 1080,
      build: function() { return {
        version: '5.3.0', background: '#fdf3e7',
        objects: [
          { type: 'circle', left: 440, top: 120, radius: 100, fill: '#d1c4f9' },
          { type: 'textbox', text: 'Big News!', left: 0, top: 380, width: 1080, fontSize: 70, fontWeight: 'bold', fill: '#241f38', fontFamily: 'Montserrat', textAlign: 'center' },
          { type: 'textbox', text: "We're excited to announce something special is coming your way.", left: 140, top: 480, width: 800, fontSize: 24, fill: '#4b4560', fontFamily: 'Inter', textAlign: 'center', lineHeight: 1.4 },
          { type: 'textbox', text: '@yourbusiness', left: 0, top: 950, width: 1080, fontSize: 18, fill: '#8b5cf6', fontFamily: 'Inter', textAlign: 'center' },
        ],
      }; } },
    { id: 'social-quote', type: 'social-media', title: 'Quote Post', width: 1080, height: 1080,
      build: function() { return {
        version: '5.3.0', background: '#eafaf1',
        objects: [
          { type: 'textbox', text: 'Great things never came from comfort zones.', left: 140, top: 420, width: 800, fontSize: 40, fontWeight: 'bold', fill: '#1f2430', fontFamily: 'Montserrat', textAlign: 'center', lineHeight: 1.3 },
          { type: 'textbox', text: '— Anonymous', left: 0, top: 640, width: 1080, fontSize: 20, fill: '#059669', fontFamily: 'Inter', textAlign: 'center' },
        ],
      }; } },
    { id: 'social-product-launch', type: 'social-media', title: 'Product Launch', width: 1080, height: 1080,
      build: function() { return {
        version: '5.3.0', background: '#6366f1',
        objects: [
          { type: 'rect', left: 240, top: 140, width: 600, height: 500, fill: '#ffffff', rx: 16, ry: 16 },
          { type: 'rect', left: 70, top: 70, width: 140, height: 50, fill: '#f59e0b', rx: 25, ry: 25 },
          { type: 'textbox', text: 'NEW', left: 70, top: 84, width: 140, fontSize: 18, fontWeight: 'bold', fill: '#ffffff', fontFamily: 'Inter', textAlign: 'center' },
          { type: 'textbox', text: 'Product Name', left: 0, top: 690, width: 1080, fontSize: 44, fontWeight: 'bold', fill: '#ffffff', fontFamily: 'Montserrat', textAlign: 'center' },
          { type: 'rect', left: 390, top: 790, width: 300, height: 70, fill: '#ffffff', rx: 35, ry: 35 },
          { type: 'textbox', text: 'SHOP NOW', left: 390, top: 812, width: 300, fontSize: 20, fontWeight: 'bold', fill: '#6366f1', fontFamily: 'Inter', textAlign: 'center' },
        ],
      }; } },
    { id: 'social-minimal-grid', type: 'social-media', title: 'Minimal Grid', width: 1080, height: 1080,
      build: function() { return {
        version: '5.3.0', background: '#ffffff',
        objects: [
          { type: 'rect', left: 0, top: 0, width: 540, height: 1080, fill: '#f3f4f6' },
          { type: 'rect', left: 540, top: 0, width: 540, height: 1080, fill: '#1f2430' },
          { type: 'textbox', text: 'Minimal.', left: 600, top: 460, width: 400, fontSize: 50, fontWeight: 'bold', fill: '#ffffff', fontFamily: 'Montserrat' },
          { type: 'textbox', text: 'Design that speaks for itself.', left: 600, top: 530, width: 400, fontSize: 16, fill: 'rgba(255,255,255,.75)', fontFamily: 'Inter' },
        ],
      }; } },
    { id: 'social-testimonial', type: 'social-media', title: 'Testimonial', width: 1080, height: 1080,
      build: function() { return {
        version: '5.3.0', background: '#f0fdfa',
        objects: [
          { type: 'circle', left: 440, top: 100, radius: 90, fill: '#99f6e4' },
          { type: 'textbox', text: '"Absolutely love this product, highly recommend it to everyone!"', left: 140, top: 330, width: 800, fontSize: 30, fontWeight: 'bold', fill: '#134e4a', fontFamily: 'Montserrat', textAlign: 'center', lineHeight: 1.4 },
          { type: 'textbox', text: '— Happy Customer', left: 0, top: 560, width: 1080, fontSize: 18, fill: '#0d9488', fontFamily: 'Inter', textAlign: 'center' },
        ],
      }; } },
    // ── Custom Design (5) ───────────────────────────────────────────────────
    { id: 'custom-poster', type: 'custom', title: 'Poster Layout', width: 1200, height: 1600,
      build: function() { return {
        version: '5.3.0', background: '#ffffff',
        objects: [
          { type: 'rect', left: 80, top: 80, width: 1040, height: 700, fill: '#d7f3e6', rx: 16, ry: 16 },
          { type: 'textbox', text: 'Poster Headline', left: 80, top: 820, width: 1040, fontSize: 56, fontWeight: 'bold', fill: '#1f2430', fontFamily: 'Montserrat' },
          { type: 'textbox', text: 'A short supporting subheading goes here to set the context.', left: 80, top: 900, width: 1040, fontSize: 22, fill: '#4b4560', fontFamily: 'Inter', lineHeight: 1.4 },
          { type: 'rect', left: 80, top: 1420, width: 260, height: 70, fill: '#10b981', rx: 10, ry: 10 },
          { type: 'textbox', text: 'LEARN MORE', left: 80, top: 1442, width: 260, fontSize: 18, fontWeight: 'bold', fill: '#ffffff', fontFamily: 'Inter', textAlign: 'center' },
        ],
      }; } },
    { id: 'custom-flyer', type: 'custom', title: 'Event Flyer', width: 1200, height: 1600,
      build: function() { return {
        version: '5.3.0', background: '#ffffff',
        objects: [
          { type: 'rect', left: 0, top: 0, width: 1200, height: 600, fill: '#fde7c7' },
          { type: 'textbox', text: 'EVENT NAME', left: 80, top: 650, width: 1040, fontSize: 50, fontWeight: 'bold', fill: '#1f2430', fontFamily: 'Montserrat' },
          { type: 'textbox', text: 'Date  •  Time  •  Venue', left: 80, top: 720, width: 1040, fontSize: 20, fill: '#6b7280', fontFamily: 'Inter' },
          { type: 'rect', left: 80, top: 780, width: 200, height: 4, fill: '#f59e0b' },
          { type: 'textbox', text: 'Join us for an unforgettable evening with music, food and fun for the whole family.', left: 80, top: 820, width: 1040, fontSize: 18, fill: '#4b4560', fontFamily: 'Inter', lineHeight: 1.5 },
          { type: 'rect', left: 80, top: 1420, width: 260, height: 70, fill: '#f59e0b', rx: 10, ry: 10 },
          { type: 'textbox', text: 'GET TICKETS', left: 80, top: 1442, width: 260, fontSize: 18, fontWeight: 'bold', fill: '#ffffff', fontFamily: 'Inter', textAlign: 'center' },
        ],
      }; } },
    { id: 'custom-certificate', type: 'custom', title: 'Certificate', width: 1600, height: 1200,
      build: function() { return {
        version: '5.3.0', background: '#ffffff',
        objects: [
          { type: 'rect', left: 20, top: 20, width: 1560, height: 1160, fill: 'transparent', stroke: '#7c3aed', strokeWidth: 6 },
          { type: 'textbox', text: 'CERTIFICATE OF ACHIEVEMENT', left: 0, top: 220, width: 1600, fontSize: 42, fontWeight: 'bold', fill: '#241f38', fontFamily: 'Montserrat', textAlign: 'center' },
          { type: 'textbox', text: 'This certificate is proudly presented to', left: 0, top: 340, width: 1600, fontSize: 18, fill: '#6b7280', fontFamily: 'Inter', textAlign: 'center' },
          { type: 'textbox', text: '[Recipient Name]', left: 0, top: 420, width: 1600, fontSize: 56, fontWeight: 'bold', fill: '#7c3aed', fontFamily: 'Georgia', textAlign: 'center' },
          { type: 'textbox', text: 'for outstanding performance and dedication', left: 0, top: 540, width: 1600, fontSize: 18, fill: '#4b4560', fontFamily: 'Inter', textAlign: 'center' },
          { type: 'textbox', text: 'Signature', left: 260, top: 980, width: 300, fontSize: 14, fill: '#6b7280', fontFamily: 'Inter', textAlign: 'center' },
          { type: 'textbox', text: 'Date', left: 1040, top: 980, width: 300, fontSize: 14, fill: '#6b7280', fontFamily: 'Inter', textAlign: 'center' },
        ],
      }; } },
    { id: 'custom-report', type: 'custom', title: 'Report Cover', width: 1200, height: 1600,
      build: function() { return {
        version: '5.3.0', background: '#ffffff',
        objects: [
          { type: 'rect', left: 0, top: 0, width: 1200, height: 500, fill: '#1e3a5f' },
          { type: 'textbox', text: 'ANNUAL REPORT', left: 80, top: 380, width: 1040, fontSize: 30, fontWeight: 'bold', fill: 'rgba(255,255,255,.75)', fontFamily: 'Inter' },
          { type: 'textbox', text: '2026 Business Overview', left: 80, top: 420, width: 1040, fontSize: 50, fontWeight: 'bold', fill: '#ffffff', fontFamily: 'Montserrat' },
          { type: 'rect', left: 80, top: 600, width: 200, height: 6, fill: '#1e3a5f' },
          { type: 'textbox', text: 'Prepared by Your Company', left: 80, top: 650, width: 1040, fontSize: 18, fill: '#4b4560', fontFamily: 'Inter' },
        ],
      }; } },
    { id: 'custom-webinar', type: 'custom', title: 'Webinar Banner', width: 1200, height: 800,
      build: function() { return {
        version: '5.3.0', background: '#f5f3ff',
        objects: [
          { type: 'circle', left: 900, top: -60, radius: 220, fill: '#ede9fe' },
          { type: 'textbox', text: 'LIVE WEBINAR', left: 80, top: 100, width: 600, fontSize: 20, fontWeight: 'bold', fill: '#7c3aed', fontFamily: 'Inter' },
          { type: 'textbox', text: 'Growing Your Business in 2026', left: 80, top: 150, width: 800, fontSize: 48, fontWeight: 'bold', fill: '#1f2430', fontFamily: 'Montserrat' },
          { type: 'textbox', text: 'Join us live — Thursday, 7 PM', left: 80, top: 260, width: 600, fontSize: 20, fill: '#4b4560', fontFamily: 'Inter' },
          { type: 'rect', left: 80, top: 340, width: 220, height: 60, fill: '#7c3aed', rx: 30, ry: 30 },
          { type: 'textbox', text: 'REGISTER', left: 80, top: 358, width: 220, fontSize: 16, fontWeight: 'bold', fill: '#ffffff', fontFamily: 'Inter', textAlign: 'center' },
        ],
      }; } },
    // ── Hire a Designer (4) ─────────────────────────────────────────────────
    { id: 'hire-a-designer', type: 'hire-designer', title: 'Graphic Designer Wanted', width: 1200, height: 1600,
      build: function() { return {
        version: '5.3.0', background: '#0f172a',
        objects: [
          { type: 'rect', left: 0, top: 0, width: 1200, height: 12, fill: '#f59e0b' },
          { type: 'textbox', text: "WE'RE HIRING", left: 80, top: 120, width: 1040, fontSize: 26, fontWeight: 'bold', fill: '#f59e0b', fontFamily: 'Inter' },
          { type: 'textbox', text: 'Graphic Designer', left: 80, top: 165, width: 1040, fontSize: 64, fontWeight: 'bold', fill: '#ffffff', fontFamily: 'Montserrat' },
          { type: 'textbox', text: 'Full-time  •  Remote / On-site', left: 80, top: 260, width: 1040, fontSize: 20, fill: 'rgba(255,255,255,.7)', fontFamily: 'Inter' },
          { type: 'rect', left: 80, top: 330, width: 1040, height: 2, fill: 'rgba(255,255,255,.15)' },
          { type: 'textbox', text: 'What you’ll do', left: 80, top: 380, width: 1040, fontSize: 22, fontWeight: 'bold', fill: '#f59e0b', fontFamily: 'Inter' },
          { type: 'textbox', text: '•  Create branded graphics, layouts and marketing assets\n•  Collaborate with the team on new design concepts\n•  Deliver print-ready and digital-ready designs on time', left: 80, top: 420, width: 1040, fontSize: 19, fill: 'rgba(255,255,255,.85)', fontFamily: 'Inter', lineHeight: 1.7 },
          { type: 'textbox', text: 'What we’re looking for', left: 80, top: 580, width: 1040, fontSize: 22, fontWeight: 'bold', fill: '#f59e0b', fontFamily: 'Inter' },
          { type: 'textbox', text: '•  A strong portfolio and eye for detail\n•  Comfortable with Figma, Illustrator or similar tools\n•  Good communication and a fast turnaround', left: 80, top: 620, width: 1040, fontSize: 19, fill: 'rgba(255,255,255,.85)', fontFamily: 'Inter', lineHeight: 1.7 },
          { type: 'rect', left: 80, top: 1420, width: 300, height: 80, fill: '#f59e0b', rx: 40, ry: 40 },
          { type: 'textbox', text: 'APPLY NOW', left: 80, top: 1447, width: 300, fontSize: 22, fontWeight: 'bold', fill: '#0f172a', fontFamily: 'Inter', textAlign: 'center' },
          { type: 'textbox', text: 'careers@yourcompany.com', left: 420, top: 1447, width: 700, fontSize: 20, fill: 'rgba(255,255,255,.75)', fontFamily: 'Inter' },
        ],
      }; } },
    { id: 'hire-designer-bold', type: 'hire-designer', title: 'Creative Director Wanted', width: 1200, height: 1600,
      build: function() { return {
        version: '5.3.0', background: '#dc2626',
        objects: [
          { type: 'rect', left: 80, top: 120, width: 300, height: 50, fill: '#111827', rx: 25, ry: 25 },
          { type: 'textbox', text: "WE'RE HIRING", left: 80, top: 135, width: 300, fontSize: 18, fontWeight: 'bold', fill: '#ffffff', fontFamily: 'Inter', textAlign: 'center' },
          { type: 'textbox', text: 'Creative Director', left: 80, top: 195, width: 1040, fontSize: 62, fontWeight: 'bold', fill: '#ffffff', fontFamily: 'Montserrat' },
          { type: 'textbox', text: 'Full-time  •  On-site', left: 80, top: 285, width: 1040, fontSize: 20, fill: 'rgba(255,255,255,.85)', fontFamily: 'Inter' },
          { type: 'rect', left: 80, top: 400, width: 1040, height: 2, fill: 'rgba(17,24,39,.35)' },
          { type: 'textbox', text: 'What you’ll do', left: 80, top: 440, width: 1040, fontSize: 22, fontWeight: 'bold', fill: '#111827', fontFamily: 'Inter' },
          { type: 'textbox', text: '•  Lead the creative vision across every campaign\n•  Mentor and direct a team of designers\n•  Own the brand’s visual identity end-to-end', left: 80, top: 480, width: 1040, fontSize: 19, fill: 'rgba(17,24,39,.85)', fontFamily: 'Inter', lineHeight: 1.7 },
          { type: 'textbox', text: 'What we’re looking for', left: 80, top: 640, width: 1040, fontSize: 22, fontWeight: 'bold', fill: '#111827', fontFamily: 'Inter' },
          { type: 'textbox', text: '•  5+ years leading a creative team\n•  A bold, standout portfolio\n•  Confidence pitching ideas to clients', left: 80, top: 680, width: 1040, fontSize: 19, fill: 'rgba(17,24,39,.85)', fontFamily: 'Inter', lineHeight: 1.7 },
          { type: 'rect', left: 80, top: 1420, width: 320, height: 80, fill: '#111827', rx: 40, ry: 40 },
          { type: 'textbox', text: 'APPLY NOW', left: 80, top: 1447, width: 320, fontSize: 22, fontWeight: 'bold', fill: '#ffffff', fontFamily: 'Inter', textAlign: 'center' },
          { type: 'textbox', text: 'careers@yourcompany.com', left: 440, top: 1447, width: 700, fontSize: 20, fill: 'rgba(17,24,39,.75)', fontFamily: 'Inter' },
        ],
      }; } },
    { id: 'hire-designer-minimal', type: 'hire-designer', title: 'UI/UX Designer Wanted', width: 1200, height: 1600,
      build: function() { return {
        version: '5.3.0', background: '#f8fafc',
        objects: [
          { type: 'rect', left: 80, top: 110, width: 90, height: 6, fill: '#0284c7' },
          { type: 'textbox', text: "WE'RE HIRING", left: 80, top: 130, width: 1040, fontSize: 20, fontWeight: 'bold', fill: '#0284c7', fontFamily: 'Inter' },
          { type: 'textbox', text: 'UI/UX Designer', left: 80, top: 170, width: 1040, fontSize: 58, fontWeight: 'bold', fill: '#0f172a', fontFamily: 'Montserrat' },
          { type: 'textbox', text: 'Full-time  •  Remote', left: 80, top: 255, width: 1040, fontSize: 20, fill: '#64748b', fontFamily: 'Inter' },
          { type: 'rect', left: 80, top: 330, width: 1040, height: 1, fill: '#e2e8f0' },
          { type: 'textbox', text: 'What you’ll do', left: 80, top: 380, width: 1040, fontSize: 22, fontWeight: 'bold', fill: '#0284c7', fontFamily: 'Inter' },
          { type: 'textbox', text: '•  Design intuitive flows and interfaces for our product\n•  Run user research and turn insights into wireframes\n•  Partner closely with engineering on implementation', left: 80, top: 420, width: 1040, fontSize: 19, fill: '#334155', fontFamily: 'Inter', lineHeight: 1.7 },
          { type: 'textbox', text: 'What we’re looking for', left: 80, top: 580, width: 1040, fontSize: 22, fontWeight: 'bold', fill: '#0284c7', fontFamily: 'Inter' },
          { type: 'textbox', text: '•  2+ years of product design experience\n•  Fluency in Figma and design systems\n•  A portfolio that shows your process, not just polish', left: 80, top: 620, width: 1040, fontSize: 19, fill: '#334155', fontFamily: 'Inter', lineHeight: 1.7 },
          { type: 'rect', left: 80, top: 1420, width: 300, height: 76, fill: '#0284c7', rx: 8, ry: 8 },
          { type: 'textbox', text: 'APPLY NOW', left: 80, top: 1446, width: 300, fontSize: 21, fontWeight: 'bold', fill: '#ffffff', fontFamily: 'Inter', textAlign: 'center' },
          { type: 'textbox', text: 'careers@yourcompany.com', left: 420, top: 1446, width: 700, fontSize: 20, fill: '#64748b', fontFamily: 'Inter' },
        ],
      }; } },
    { id: 'hire-designer-colorful', type: 'hire-designer', title: 'Freelance Illustrator Wanted', width: 1200, height: 1600,
      build: function() { return {
        version: '5.3.0', background: '#7c3aed',
        objects: [
          { type: 'circle', left: 780, top: -140, radius: 260, fill: '#f472b6' },
          { type: 'rect', left: 80, top: 120, width: 260, height: 50, fill: '#fde047', rx: 25, ry: 25 },
          { type: 'textbox', text: "WE'RE HIRING", left: 80, top: 135, width: 260, fontSize: 18, fontWeight: 'bold', fill: '#4c1d95', fontFamily: 'Inter', textAlign: 'center' },
          { type: 'textbox', text: 'Freelance Illustrator', left: 80, top: 195, width: 1040, fontSize: 58, fontWeight: 'bold', fill: '#ffffff', fontFamily: 'Montserrat' },
          { type: 'textbox', text: 'Contract  •  Remote  •  Project-based', left: 80, top: 280, width: 1040, fontSize: 20, fill: 'rgba(255,255,255,.85)', fontFamily: 'Inter' },
          { type: 'rect', left: 80, top: 400, width: 1040, height: 2, fill: 'rgba(255,255,255,.25)' },
          { type: 'textbox', text: 'What you’ll do', left: 80, top: 440, width: 1040, fontSize: 22, fontWeight: 'bold', fill: '#fde047', fontFamily: 'Inter' },
          { type: 'textbox', text: '•  Illustrate characters, icons and campaign artwork\n•  Turn rough briefs into polished, on-brand visuals\n•  Deliver a handful of projects per month, on your schedule', left: 80, top: 480, width: 1040, fontSize: 19, fill: 'rgba(255,255,255,.9)', fontFamily: 'Inter', lineHeight: 1.7 },
          { type: 'textbox', text: 'What we’re looking for', left: 80, top: 640, width: 1040, fontSize: 22, fontWeight: 'bold', fill: '#fde047', fontFamily: 'Inter' },
          { type: 'textbox', text: '•  A distinctive illustration style\n•  Reliable turnaround on freelance timelines\n•  Comfortable working from a creative brief', left: 80, top: 680, width: 1040, fontSize: 19, fill: 'rgba(255,255,255,.9)', fontFamily: 'Inter', lineHeight: 1.7 },
          { type: 'rect', left: 80, top: 1420, width: 320, height: 80, fill: '#fde047', rx: 40, ry: 40 },
          { type: 'textbox', text: 'APPLY NOW', left: 80, top: 1447, width: 320, fontSize: 22, fontWeight: 'bold', fill: '#4c1d95', fontFamily: 'Inter', textAlign: 'center' },
          { type: 'textbox', text: 'careers@yourcompany.com', left: 440, top: 1447, width: 700, fontSize: 20, fill: 'rgba(255,255,255,.85)', fontFamily: 'Inter' },
        ],
      }; } },
    // ── Sales Campaign (4) ──────────────────────────────────────────────────
    { id: 'sales-campaign-flash-sale', type: 'sales-campaign', title: 'Flash Sale Banner', width: 1080, height: 1080,
      build: function() { return {
        version: '5.3.0', background: '#dc2626',
        objects: [
          { type: 'textbox', text: 'FLASH SALE', left: 0, top: 280, width: 1080, fontSize: 130, fontWeight: 'bold', fill: '#ffffff', fontFamily: 'Montserrat', textAlign: 'center' },
          { type: 'textbox', text: '24 HOURS ONLY  •  UP TO 50% OFF', left: 0, top: 560, width: 1080, fontSize: 34, fontWeight: 'bold', fill: 'rgba(255,255,255,.9)', fontFamily: 'Inter', textAlign: 'center' },
          { type: 'rect', left: 390, top: 680, width: 300, height: 70, fill: '#111827', rx: 35, ry: 35 },
          { type: 'textbox', text: 'SHOP NOW', left: 390, top: 702, width: 300, fontSize: 22, fontWeight: 'bold', fill: '#ffffff', fontFamily: 'Inter', textAlign: 'center' },
        ],
      }; } },
    { id: 'sales-campaign-discount', type: 'sales-campaign', title: 'Discount Blast', width: 1080, height: 1080,
      build: function() { return {
        version: '5.3.0', background: '#111827',
        objects: [
          { type: 'rect', left: 110, top: 80, width: 300, height: 60, fill: '#f97316', rx: 30, ry: 30 },
          { type: 'textbox', text: 'LIMITED TIME', left: 110, top: 98, width: 300, fontSize: 20, fontWeight: 'bold', fill: '#ffffff', fontFamily: 'Inter', textAlign: 'center' },
          { type: 'textbox', text: '30% OFF', left: 0, top: 340, width: 1080, fontSize: 150, fontWeight: 'bold', fill: '#ffffff', fontFamily: 'Montserrat', textAlign: 'center' },
          { type: 'textbox', text: 'Use code SAVE30 at checkout', left: 0, top: 660, width: 1080, fontSize: 28, fill: 'rgba(255,255,255,.75)', fontFamily: 'Inter', textAlign: 'center' },
          { type: 'rect', left: 390, top: 750, width: 300, height: 70, fill: '#f97316', rx: 35, ry: 35 },
          { type: 'textbox', text: 'SHOP NOW', left: 390, top: 772, width: 300, fontSize: 22, fontWeight: 'bold', fill: '#ffffff', fontFamily: 'Inter', textAlign: 'center' },
        ],
      }; } },
    { id: 'sales-campaign-countdown', type: 'sales-campaign', title: 'Campaign Countdown', width: 1080, height: 1080,
      build: function() { return {
        version: '5.3.0', background: '#fef3c7',
        objects: [
          { type: 'textbox', text: 'SALE ENDS SOON', left: 0, top: 90, width: 1080, fontSize: 46, fontWeight: 'bold', fill: '#1f2430', fontFamily: 'Montserrat', textAlign: 'center' },
          { type: 'rect', left: 120, top: 440, width: 180, height: 180, fill: '#ffffff', rx: 16, ry: 16 },
          { type: 'rect', left: 330, top: 440, width: 180, height: 180, fill: '#ffffff', rx: 16, ry: 16 },
          { type: 'rect', left: 540, top: 440, width: 180, height: 180, fill: '#ffffff', rx: 16, ry: 16 },
          { type: 'rect', left: 750, top: 440, width: 180, height: 180, fill: '#ffffff', rx: 16, ry: 16 },
          { type: 'textbox', text: '02', left: 120, top: 480, width: 180, fontSize: 60, fontWeight: 'bold', fill: '#d97706', fontFamily: 'Montserrat', textAlign: 'center' },
          { type: 'textbox', text: 'DAYS', left: 120, top: 555, width: 180, fontSize: 15, fill: '#6b7280', fontFamily: 'Inter', textAlign: 'center' },
          { type: 'textbox', text: '14', left: 330, top: 480, width: 180, fontSize: 60, fontWeight: 'bold', fill: '#d97706', fontFamily: 'Montserrat', textAlign: 'center' },
          { type: 'textbox', text: 'HOURS', left: 330, top: 555, width: 180, fontSize: 15, fill: '#6b7280', fontFamily: 'Inter', textAlign: 'center' },
          { type: 'textbox', text: '32', left: 540, top: 480, width: 180, fontSize: 60, fontWeight: 'bold', fill: '#d97706', fontFamily: 'Montserrat', textAlign: 'center' },
          { type: 'textbox', text: 'MINS', left: 540, top: 555, width: 180, fontSize: 15, fill: '#6b7280', fontFamily: 'Inter', textAlign: 'center' },
          { type: 'textbox', text: '09', left: 750, top: 480, width: 180, fontSize: 60, fontWeight: 'bold', fill: '#d97706', fontFamily: 'Montserrat', textAlign: 'center' },
          { type: 'textbox', text: 'SECS', left: 750, top: 555, width: 180, fontSize: 15, fill: '#6b7280', fontFamily: 'Inter', textAlign: 'center' },
          { type: 'textbox', text: "Don't miss out — grab your discount before time runs out.", left: 140, top: 780, width: 800, fontSize: 22, fill: '#374151', fontFamily: 'Inter', textAlign: 'center' },
        ],
      }; } },
    { id: 'sales-campaign-bogo', type: 'sales-campaign', title: 'Buy One Get One', width: 1080, height: 1080,
      build: function() { return {
        version: '5.3.0', background: '#0d9488',
        objects: [
          { type: 'textbox', text: 'BOGO', left: 0, top: 260, width: 1080, fontSize: 170, fontWeight: 'bold', fill: '#ffffff', fontFamily: 'Montserrat', textAlign: 'center' },
          { type: 'textbox', text: 'BUY ONE, GET ONE FREE', left: 0, top: 500, width: 1080, fontSize: 36, fontWeight: 'bold', fill: 'rgba(255,255,255,.9)', fontFamily: 'Inter', textAlign: 'center' },
          { type: 'textbox', text: 'Limited time offer — in-store and online', left: 0, top: 560, width: 1080, fontSize: 20, fill: 'rgba(255,255,255,.75)', fontFamily: 'Inter', textAlign: 'center' },
          { type: 'rect', left: 390, top: 660, width: 300, height: 70, fill: '#f59e0b', rx: 35, ry: 35 },
          { type: 'textbox', text: 'SHOP NOW', left: 390, top: 682, width: 300, fontSize: 22, fontWeight: 'bold', fill: '#111827', fontFamily: 'Inter', textAlign: 'center' },
        ],
      }; } },
];

// Renders a lightweight CSS approximation of a template's Fabric objects for
// the gallery card preview — derived straight from the same build() data used
// to create the real design, so there's no separate set of mockups to keep in sync.
function dsRenderTplPreview(data, w, h) {
    var objs = (data && data.objects) || [];
    var html = '';
    objs.forEach(function(o) {
        var left = o.left || 0, top = o.top || 0, width, height;
        if (o.type === 'circle') {
            var r = o.radius || 20;
            width = r * 2; height = r * 2;
        } else if (o.type === 'rect') {
            width = o.width || 40; height = o.height || 20;
        } else {
            var lines = String(o.text || '').split('\n').length;
            var fs = o.fontSize || 14;
            height = fs * 1.3 * lines;
            width = o.width || Math.min(w - left, Math.max(60, String(o.text || '').length * fs * 0.55));
        }
        var bg = (o.fill && typeof o.fill === 'string' && o.fill !== 'transparent') ? o.fill : 'rgba(0,0,0,.08)';
        var radius = o.type === 'circle' ? '50%' : (o.rx ? Math.min(o.rx, 12) + 'px' : '0');
        html += '<span class="ds-tpl-mock" style="left:' + (left / w * 100).toFixed(2) + '%;top:' + (top / h * 100).toFixed(2) +
            '%;width:' + (width / w * 100).toFixed(2) + '%;height:' + (height / h * 100).toFixed(2) +
            '%;background:' + bg + ';border-radius:' + radius + ';"></span>';
    });
    return html;
}

function dsUseTemplate(tplId) {
    var tpl = DS_TEMPLATES.find(function(t) { return t.id === tplId; });
    if (!tpl) return;
    var cardEl = document.querySelector('.ds-tpl-card[data-tpl-id="' + tplId + '"]');
    if (cardEl) cardEl.classList.add('ds-tpl-loading');
    try {
        sessionStorage.setItem('dsWizardTemplate', JSON.stringify([{ json: JSON.stringify(tpl.build()) }]));
    } catch (e) {}

    var singletonUrl = DS_SINGLETON_EDIT_URLS[tpl.type];
    if (singletonUrl) {
        window.location.href = singletonUrl;
    } else {
        startEditor(tpl.width, tpl.height, tpl.type);
    }
}

(function() {
    var grid = document.getElementById('dsTplGrid');
    if (!grid) return;
    DS_TEMPLATES.forEach(function(tpl) {
        var data = tpl.build();
        var typeInfo = DS_TPL_TYPES[tpl.type] || { label: tpl.type, color: 'var(--muted)' };
        var card = document.createElement('button');
        card.type = 'button';
        card.className = 'ds-tpl-card';
        card.dataset.tplId = tpl.id;
        card.dataset.tplType = tpl.type;
        card.style.setProperty('--tpl-accent', typeInfo.color);
        card.innerHTML =
            '<span class="ds-tpl-preview-wrap"><span class="ds-tpl-preview" style="--tpl-ar:' + tpl.width + '/' + tpl.height + ';background:' + (data.background || '#ffffff') + '">' +
                dsRenderTplPreview(data, tpl.width, tpl.height) +
                '<span class="ds-tpl-overlay"><span class="ds-tpl-use-btn"><i class="fa fa-wand-magic-sparkles" aria-hidden="true"></i> Use template</span></span>' +
                '<span class="ds-tpl-spinner"><i class="fa fa-spinner fa-spin" aria-hidden="true"></i></span>' +
            '</span></span>' +
            '<span class="ds-tpl-info"><span class="ds-tpl-name">' + tpl.title + '</span><span class="ds-tpl-type">' + typeInfo.label + '</span></span>';
        card.addEventListener('click', function() { dsUseTemplate(tpl.id); });
        grid.appendChild(card);
    });

    document.querySelectorAll('#dsTplFilters .ds-tpl-filter').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('#dsTplFilters .ds-tpl-filter').forEach(function(b) { b.classList.toggle('active', b === btn); });
            var type = btn.dataset.tplFilter;
            document.querySelectorAll('#dsTplGrid .ds-tpl-card').forEach(function(card) {
                card.style.display = (type === 'all' || card.dataset.tplType === type) ? '' : 'none';
            });
        });
    });
})();
</script>

{{-- ══════════════════════════════════════════════════════
     COMPANY PROFILE WIZARD MODAL
     ══════════════════════════════════════════════════════ --}}

@include('designstudio::hub.partials.company-profile-wizard')

@include('designstudio::hub.partials.letterhead-wizard')

@endsection
