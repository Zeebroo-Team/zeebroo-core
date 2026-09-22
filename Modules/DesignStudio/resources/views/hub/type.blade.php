@extends('theme::layouts.app', ['title' => $config['label'], 'heading' => $config['label']])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.dtp-back{display:inline-flex;align-items:center;gap:7px;font-size:13px;font-weight:700;color:var(--muted);text-decoration:none;margin-bottom:16px;transition:color .15s;}
.dtp-back:hover{color:var(--text);}

/* ── Stats row ────────────────────────────────────────────────── */
.dtp-stats{display:flex;flex-wrap:wrap;gap:12px;margin-bottom:22px;}
.dtp-stat{flex:1;min-width:140px;border:1px solid var(--border);border-radius:12px;padding:14px 16px 14px 20px;background:var(--card);position:relative;overflow:hidden;}
.dtp-stat::before{content:'';position:absolute;left:0;top:0;bottom:0;width:4px;border-radius:12px 0 0 12px;background:var(--dtp-accent);}
.dtp-stat__icon{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px;margin-bottom:10px;background:color-mix(in srgb,var(--dtp-accent) 13%,transparent);color:var(--dtp-accent);}
.dtp-stat__label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin:0 0 3px;}
.dtp-stat__value{font-size:22px;font-weight:800;color:var(--text);line-height:1.2;margin:0;}

/* ── Preset size guide ────────────────────────────────────────── */
.dtp-presets{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px;margin-bottom:24px;}
.dtp-preset{border:1px solid var(--border);border-radius:10px;padding:12px 14px;background:var(--card);cursor:pointer;transition:border-color .15s,transform .12s;}
.dtp-preset:hover{border-color:color-mix(in srgb,var(--dtp-accent) 55%,var(--border));transform:translateY(-1px);}
.dtp-preset__icon{font-size:18px;margin-bottom:7px;color:var(--dtp-accent);}
.dtp-preset__name{font-size:12px;font-weight:700;color:var(--text);margin:0 0 2px;}
.dtp-preset__size{font-size:10px;color:var(--muted);font-family:monospace;}

/* ── Design grid ──────────────────────────────────────────────── */
.dtp-grid{display:grid;gap:14px;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));margin-top:6px;}
.dtp-card{border:1px solid var(--border);border-radius:12px;overflow:hidden;background:var(--card);transition:border-color .2s,transform .15s;}
.dtp-card:hover{border-color:color-mix(in srgb,var(--dtp-accent) 45%,var(--border));transform:translateY(-2px);}
.dtp-card__preview{height:110px;display:flex;align-items:center;justify-content:center;font-size:32px;color:var(--dtp-accent);background:linear-gradient(135deg,color-mix(in srgb,var(--dtp-accent) 8%,var(--bg)) 0%,color-mix(in srgb,var(--dtp-accent) 18%,var(--bg)) 100%);}
.dtp-card__info{padding:10px 12px;}
.dtp-card__title{font-size:13px;font-weight:700;color:var(--text);margin:0 0 2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.dtp-card__meta{font-size:11px;color:var(--muted);}
.dtp-card__actions{display:flex;gap:6px;padding:0 12px 10px;margin-top:2px;}
.dtp-card__btn{flex:1;padding:5px 8px;border-radius:6px;border:1px solid var(--border);background:transparent;color:var(--muted);font-size:11px;font-weight:700;cursor:pointer;text-align:center;transition:all .15s;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:4px;}
.dtp-card__btn:hover{background:var(--dtp-accent);border-color:var(--dtp-accent);color:#fff;}
.dtp-card__btn--del{color:var(--danger,#ef4444);}
.dtp-card__btn--del:hover{background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.4);color:#ef4444;}

/* ── New design card ──────────────────────────────────────────── */
.dtp-new-card{border:1.5px dashed var(--border);border-radius:12px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;padding:24px;cursor:pointer;transition:border-color .2s,background .15s;min-height:180px;}
.dtp-new-card:hover{border-color:color-mix(in srgb,var(--dtp-accent) 60%,var(--border));background:color-mix(in srgb,var(--dtp-accent) 4%,transparent);}
.dtp-new-card__icon{width:44px;height:44px;border-radius:12px;background:color-mix(in srgb,var(--dtp-accent) 12%,transparent);color:var(--dtp-accent);display:flex;align-items:center;justify-content:center;font-size:18px;}
.dtp-new-card__label{font-size:12px;font-weight:700;color:var(--muted);}

/* ── Empty state ──────────────────────────────────────────────── */
.dtp-empty{text-align:center;padding:40px 20px 48px;display:flex;flex-direction:column;align-items:center;gap:10px;}
.dtp-empty__icon{width:60px;height:60px;border-radius:18px;display:flex;align-items:center;justify-content:center;font-size:24px;background:color-mix(in srgb,var(--dtp-accent) 13%,transparent);color:var(--dtp-accent);margin-bottom:4px;}
.dtp-empty__title{font-size:16px;font-weight:800;color:var(--text);margin:0;}
.dtp-empty__desc{font-size:13px;color:var(--muted);max-width:400px;line-height:1.6;margin:0;}
</style>

@php
    $defaultPreset = $config['presets'][0];
    $newDesignUrl  = route('designstudio.editor.create') . '?w=' . $defaultPreset['w'] . '&h=' . $defaultPreset['h'] . '&type=' . $type;
@endphp

<div class="pcat-page-card card" style="max-width:100%;padding:18px 20px;--dtp-accent:{{ $config['color'] }};">

    <a href="{{ route('designstudio.index') }}" class="dtp-back">
        <i class="fa fa-arrow-left"></i> Design Studio
    </a>

    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:18px;">
        <div>
            <h2 style="margin:0 0 4px;font-size:18px;font-weight:800;letter-spacing:-.02em;">{{ $config['label'] }}</h2>
            <p style="margin:0;font-size:13px;color:var(--muted);">{{ $config['desc'] }}</p>
        </div>
        <a href="{{ $newDesignUrl }}" class="linkbtn" style="padding:9px 18px;font-size:13px;display:inline-flex;align-items:center;gap:7px;">
            <i class="fa fa-plus"></i> New Design
        </a>
    </div>

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;margin-bottom:16px;">{{ session('status') }}</div>
    @endif

    @if($designs->isNotEmpty())
    {{-- ── Stats ── --}}
    <div class="dtp-stats">
        <div class="dtp-stat">
            <div class="dtp-stat__icon"><i class="fa {{ $config['icon'] }}"></i></div>
            <p class="dtp-stat__label">Total designs</p>
            <p class="dtp-stat__value">{{ $designs->count() }}</p>
        </div>
        <div class="dtp-stat">
            <div class="dtp-stat__icon"><i class="fa fa-calendar-check"></i></div>
            <p class="dtp-stat__label">This month</p>
            <p class="dtp-stat__value">{{ $designs->filter(fn($d) => $d->created_at->isCurrentMonth())->count() }}</p>
        </div>
        <div class="dtp-stat">
            <div class="dtp-stat__icon"><i class="fa fa-clock-rotate-left"></i></div>
            <p class="dtp-stat__label">Last updated</p>
            <p class="dtp-stat__value" style="font-size:14px;">{{ $designs->first()?->updated_at->diffForHumans() ?? '—' }}</p>
        </div>
    </div>
    @endif

    {{-- ── Suggested sizes ── --}}
    <p style="margin-bottom:10px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);display:flex;align-items:center;gap:8px;">
        <i class="fa fa-ruler-combined" aria-hidden="true"></i> Suggested sizes
        <span style="flex:1;height:1px;background:var(--border);display:block;"></span>
    </p>
    <div class="dtp-presets" style="margin-bottom:20px;">
        @foreach($config['presets'] as $preset)
            <div class="dtp-preset" onclick="window.location.href='{{ route('designstudio.editor.create') }}?w={{ $preset['w'] }}&h={{ $preset['h'] }}&type={{ $type }}'">
                <div class="dtp-preset__icon"><i class="fa {{ $config['icon'] }}"></i></div>
                <p class="dtp-preset__name">{{ $preset['label'] }}</p>
                <p class="dtp-preset__size">{{ $preset['w'] }} × {{ $preset['h'] }} px</p>
            </div>
        @endforeach
    </div>

    {{-- ── Designs ── --}}
    <p style="margin-bottom:10px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);display:flex;align-items:center;gap:8px;">
        <i class="fa fa-images" aria-hidden="true"></i> My {{ $config['label'] }} Designs ({{ $designs->count() }})
        <span style="flex:1;height:1px;background:var(--border);display:block;"></span>
    </p>

    @if($designs->isEmpty())
        <section class="pcat-inline">
            <div class="dtp-empty">
                <div class="dtp-empty__icon"><i class="fa {{ $config['icon'] }}"></i></div>
                <p class="dtp-empty__title">No {{ strtolower($config['label']) }} designs yet</p>
                <p class="dtp-empty__desc">{{ $config['desc'] }} Pick a size above or start from a custom canvas.</p>
                <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:center;margin-top:6px;">
                    <a href="{{ $newDesignUrl }}" class="linkbtn" style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;">
                        <i class="fa fa-plus"></i> Create first design
                    </a>
                </div>
            </div>
        </section>
    @else
        <div class="dtp-grid">
            @foreach($designs as $d)
                <div class="dtp-card">
                    <div class="dtp-card__preview">
                        <i class="fa {{ $config['icon'] }}" aria-hidden="true"></i>
                    </div>
                    <div class="dtp-card__info">
                        <p class="dtp-card__title">{{ $d->title }}</p>
                        <p class="dtp-card__meta">{{ $d->width }} × {{ $d->height }}px &bull; {{ $d->updated_at->diffForHumans() }}</p>
                    </div>
                    <div class="dtp-card__actions">
                        <a href="{{ route('designstudio.editor.edit', $d) }}" class="dtp-card__btn">
                            <i class="fa fa-pen" aria-hidden="true"></i> Edit
                        </a>
                        <form action="{{ route('designstudio.designs.destroy', $d) }}" method="POST" onsubmit="return confirm('Delete this design?')" style="flex:1;display:contents;">
                            @csrf @method('DELETE')
                            <button type="submit" class="dtp-card__btn dtp-card__btn--del">
                                <i class="fa fa-trash" aria-hidden="true"></i>
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach

            {{-- New design card --}}
            <div class="dtp-new-card" onclick="window.location.href='{{ $newDesignUrl }}'">
                <div class="dtp-new-card__icon"><i class="fa fa-plus" aria-hidden="true"></i></div>
                <span class="dtp-new-card__label">New Design</span>
            </div>
        </div>
    @endif

</div>

@endsection
