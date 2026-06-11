@extends('theme::layouts.app', ['title' => 'Social Media', 'heading' => 'Social Media'])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
/* ── Page toolbar ─────────────────────────────────────────────── */
.sm-back{display:inline-flex;align-items:center;gap:7px;font-size:13px;font-weight:700;color:var(--muted);text-decoration:none;margin-bottom:16px;transition:color .15s;}
.sm-back:hover{color:var(--text);}

/* ── Stats row ────────────────────────────────────────────────── */
.sm-stats{display:flex;flex-wrap:wrap;gap:12px;margin-bottom:22px;}
.sm-stat{flex:1;min-width:140px;border:1px solid var(--border);border-radius:12px;padding:14px 16px 14px 20px;background:var(--card);position:relative;overflow:hidden;}
.sm-stat::before{content:'';position:absolute;left:0;top:0;bottom:0;width:4px;border-radius:12px 0 0 12px;}
.sm-stat--rose::before{background:#f43f5e;}
.sm-stat--violet::before{background:#8b5cf6;}
.sm-stat--sky::before{background:#0ea5e9;}
.sm-stat__icon{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px;margin-bottom:10px;}
.sm-stat--rose   .sm-stat__icon{background:color-mix(in srgb,#f43f5e 13%,transparent);color:#e11d48;}
.sm-stat--violet .sm-stat__icon{background:color-mix(in srgb,#8b5cf6 13%,transparent);color:#7c3aed;}
.sm-stat--sky    .sm-stat__icon{background:color-mix(in srgb,#0ea5e9 13%,transparent);color:#0284c7;}
.sm-stat__label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin:0 0 3px;}
.sm-stat__value{font-size:22px;font-weight:800;color:var(--text);line-height:1.2;margin:0;}

/* ── Platform size guide ──────────────────────────────────────── */
.sm-platforms{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px;margin-bottom:24px;}
.sm-platform{border:1px solid var(--border);border-radius:10px;padding:12px 14px;background:var(--card);cursor:pointer;transition:border-color .15s,transform .12s;}
.sm-platform:hover{border-color:color-mix(in srgb,var(--primary) 55%,var(--border));transform:translateY(-1px);}
.sm-platform__icon{font-size:18px;margin-bottom:7px;}
.sm-platform__name{font-size:12px;font-weight:700;color:var(--text);margin:0 0 2px;}
.sm-platform__size{font-size:10px;color:var(--muted);font-family:monospace;}

/* ── Design grid ──────────────────────────────────────────────── */
.sm-grid{display:grid;gap:14px;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));margin-top:6px;}
.sm-card{border:1px solid var(--border);border-radius:12px;overflow:hidden;background:var(--card);transition:border-color .2s,transform .15s;}
.sm-card:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));transform:translateY(-2px);}
.sm-card__preview{height:110px;display:flex;align-items:center;justify-content:center;font-size:32px;color:var(--primary);background:linear-gradient(135deg,color-mix(in srgb,var(--primary) 8%,var(--bg)) 0%,color-mix(in srgb,var(--primary) 18%,var(--bg)) 100%);}
.sm-card__preview img{width:100%;height:100%;object-fit:cover;}
.sm-card__info{padding:10px 12px;}
.sm-card__title{font-size:13px;font-weight:700;color:var(--text);margin:0 0 2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.sm-card__meta{font-size:11px;color:var(--muted);}
.sm-card__actions{display:flex;gap:6px;padding:0 12px 10px;margin-top:2px;}
.sm-card__btn{flex:1;padding:5px 8px;border-radius:6px;border:1px solid var(--border);background:transparent;color:var(--muted);font-size:11px;font-weight:700;cursor:pointer;text-align:center;transition:all .15s;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:4px;}
.sm-card__btn:hover{background:var(--primary);border-color:var(--primary);color:#fff;}
.sm-card__btn--del{color:var(--danger,#ef4444);}
.sm-card__btn--del:hover{background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.4);color:#ef4444;}

/* ── New design card ──────────────────────────────────────────── */
.sm-new-card{border:1.5px dashed var(--border);border-radius:12px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;padding:24px;cursor:pointer;transition:border-color .2s,background .15s;min-height:180px;}
.sm-new-card:hover{border-color:color-mix(in srgb,var(--primary) 60%,var(--border));background:color-mix(in srgb,var(--primary) 4%,transparent);}
.sm-new-card__icon{width:44px;height:44px;border-radius:12px;background:color-mix(in srgb,var(--primary) 12%,transparent);color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:18px;}
.sm-new-card__label{font-size:12px;font-weight:700;color:var(--muted);}

/* ── Empty state ──────────────────────────────────────────────── */
.sm-empty{text-align:center;padding:40px 20px 48px;display:flex;flex-direction:column;align-items:center;gap:10px;}
.sm-empty__icon{width:60px;height:60px;border-radius:18px;display:flex;align-items:center;justify-content:center;font-size:24px;background:color-mix(in srgb,#8b5cf6 13%,transparent);color:#7c3aed;margin-bottom:4px;}
.sm-empty__title{font-size:16px;font-weight:800;color:var(--text);margin:0;}
.sm-empty__desc{font-size:13px;color:var(--muted);max-width:400px;line-height:1.6;margin:0;}

/* ── Connect modal ────────────────────────────────────────────── */
.smcon-backdrop{
    position:fixed;inset:0;z-index:1100;display:flex;align-items:center;justify-content:center;
    background:rgba(0,0,0,.55);backdrop-filter:blur(4px);
    opacity:0;visibility:hidden;transition:opacity .22s,visibility .22s;
}
.smcon-backdrop.open{opacity:1;visibility:visible;}
.smcon-panel{
    background:var(--card);border:1px solid var(--border);border-radius:20px;
    width:min(100% - 32px,560px);max-height:calc(100vh - 48px);overflow-y:auto;
    box-shadow:0 28px 70px rgba(0,0,0,.28);
    transform:scale(.96) translateY(10px);transition:transform .22s;
    position:relative;
}
.smcon-backdrop.open .smcon-panel{transform:scale(1) translateY(0);}

/* header */
.smcon-header{
    display:flex;align-items:flex-start;gap:14px;
    padding:20px 22px 16px;border-bottom:1px solid var(--border);
    position:sticky;top:0;background:var(--card);z-index:2;border-radius:20px 20px 0 0;
}
.smcon-header-icon{
    width:44px;height:44px;border-radius:12px;flex-shrink:0;
    display:flex;align-items:center;justify-content:center;font-size:18px;
    background:color-mix(in srgb,var(--primary) 13%,var(--bg));color:var(--primary);
}
.smcon-header-text{flex:1;min-width:0;}
.smcon-header-text h2{margin:0 0 2px;font-size:16px;font-weight:800;letter-spacing:-.02em;}
.smcon-header-text p{margin:0;font-size:12px;color:var(--muted);}
.smcon-close{
    width:34px;height:34px;border-radius:9px;border:1px solid var(--border);
    background:transparent;color:var(--muted);cursor:pointer;flex-shrink:0;
    display:flex;align-items:center;justify-content:center;font-size:14px;
    transition:all .15s;
}
.smcon-close:hover{background:color-mix(in srgb,var(--text) 7%,transparent);color:var(--text);}

/* platform cards grid */
.smcon-body{padding:20px 22px 24px;}
.smcon-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
@media(max-width:440px){.smcon-grid{grid-template-columns:1fr;}}

.smcon-card{
    border:1.5px solid var(--border);border-radius:16px;padding:20px 18px 18px;
    display:flex;flex-direction:column;align-items:center;text-align:center;gap:10px;
    background:var(--bg);transition:border-color .18s,transform .15s,box-shadow .18s;
}
.smcon-card:hover{
    border-color:color-mix(in srgb,var(--primary) 50%,var(--border));
    transform:translateY(-2px);
    box-shadow:0 6px 22px color-mix(in srgb,var(--primary) 10%,transparent);
}
.smcon-card__logo{
    width:56px;height:56px;border-radius:16px;display:flex;align-items:center;justify-content:center;
    font-size:24px;flex-shrink:0;
}
.smcon-card__name{font-size:14px;font-weight:800;color:var(--text);margin:0;}
.smcon-card__desc{font-size:11px;color:var(--muted);line-height:1.5;margin:0;}
.smcon-card__btn{
    display:inline-flex;align-items:center;justify-content:center;gap:6px;
    padding:8px 20px;border-radius:9px;font-size:12px;font-weight:700;
    border:none;cursor:pointer;transition:all .16s;text-decoration:none;
    width:100%;box-sizing:border-box;margin-top:4px;
}
/* per-platform colours */
.smcon-card--facebook .smcon-card__logo{background:#1877F214;color:#1877F2;}
.smcon-card--facebook .smcon-card__btn{background:#1877F2;color:#fff;}
.smcon-card--facebook .smcon-card__btn:hover{background:color-mix(in srgb,#1877F2 85%,#000);color:#fff;}

.smcon-card--linkedin .smcon-card__logo{background:#0077B514;color:#0077B5;}
.smcon-card--linkedin .smcon-card__btn{background:#0077B5;color:#fff;}
.smcon-card--linkedin .smcon-card__btn:hover{background:color-mix(in srgb,#0077B5 85%,#000);color:#fff;}

.smcon-card--youtube .smcon-card__logo{background:#FF000014;color:#FF0000;}
.smcon-card--youtube .smcon-card__btn{background:#FF0000;color:#fff;}
.smcon-card--youtube .smcon-card__btn:hover{background:color-mix(in srgb,#FF0000 85%,#000);color:#fff;}

.smcon-card--tiktok .smcon-card__logo{background:#00000014;color:#010101;}
.smcon-card--tiktok .smcon-card__btn{background:#010101;color:#fff;}
.smcon-card--tiktok .smcon-card__btn:hover{background:#333;color:#fff;}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:18px 20px;">

    <a href="{{ route('designstudio.index') }}" class="sm-back">
        <i class="fa fa-arrow-left"></i> Design Studio
    </a>

    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:18px;">
        <div>
            <h2 style="margin:0 0 4px;font-size:18px;font-weight:800;letter-spacing:-.02em;">Social Media</h2>
            <p style="margin:0;font-size:13px;color:var(--muted);">Design and manage posts for <strong style="color:var(--text);">{{ $business->name }}</strong></p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button type="button" onclick="openConnectModal()"
                class="linkbtn" style="padding:9px 18px;font-size:13px;display:inline-flex;align-items:center;gap:7px;background:transparent;border:1px solid var(--border);color:var(--text);">
                <i class="fa fa-plug"></i> Connect
            </button>
            <a href="{{ route('designstudio.editor.create') }}?w=1080&h=1080&type=social-media"
               class="linkbtn" style="padding:9px 18px;font-size:13px;display:inline-flex;align-items:center;gap:7px;">
                <i class="fa fa-plus"></i> New Post Design
            </a>
        </div>
    </div>

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;margin-bottom:16px;">{{ session('status') }}</div>
    @endif
    @if(session('error'))
        <div class="pcat-banner pcat-banner--err" style="font-weight:600;margin-bottom:16px;">{{ session('error') }}</div>
    @endif

    {{-- ── Connected Facebook Pages ── --}}
    @if($facebookPages->isNotEmpty())
    <div style="margin-bottom:22px;">
        <p style="margin-bottom:10px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);display:flex;align-items:center;gap:8px;">
            <i class="fa fa-plug" aria-hidden="true"></i> Connected accounts
            <span style="flex:1;height:1px;background:var(--border);display:block;"></span>
        </p>
        <div style="display:flex;flex-wrap:wrap;gap:10px;">
            @foreach($facebookPages as $page)
            <div style="display:flex;align-items:center;gap:10px;border:1px solid color-mix(in srgb,#1877F2 38%,var(--border));border-radius:12px;padding:10px 14px;background:color-mix(in srgb,#1877F2 5%,var(--bg));min-width:220px;">
                @if($page->picture_url)
                    <img src="{{ $page->picture_url }}" alt="{{ $page->name }}" style="width:36px;height:36px;border-radius:9px;object-fit:cover;flex-shrink:0;">
                @else
                    <div style="width:36px;height:36px;border-radius:9px;background:#1877F214;color:#1877F2;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;"><i class="fa fa-facebook"></i></div>
                @endif
                <div style="flex:1;min-width:0;">
                    <p style="margin:0;font-size:13px;font-weight:700;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $page->name }}</p>
                    <p style="margin:0;font-size:11px;color:#1877F2;font-weight:600;"><i class="fa fa-circle" style="font-size:7px;margin-right:3px;"></i> Connected</p>
                </div>
                <form method="POST" action="{{ route('designstudio.facebook.disconnect', $page) }}" onsubmit="return confirm('Disconnect {{ addslashes($page->name) }}?');" style="margin:0;">
                    @csrf @method('DELETE')
                    <button type="submit" title="Disconnect" style="width:28px;height:28px;border-radius:7px;border:1px solid var(--border);background:transparent;color:var(--muted);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:11px;transition:all .15s;" onmouseover="this.style.background='rgba(239,68,68,.08)';this.style.borderColor='rgba(239,68,68,.4)';this.style.color='#ef4444'" onmouseout="this.style.background='transparent';this.style.borderColor='var(--border)';this.style.color='var(--muted)'">
                        <i class="fa fa-times" aria-hidden="true"></i>
                    </button>
                </form>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    @if($designs->isNotEmpty())
    {{-- ── Stats ── --}}
    <div class="sm-stats">
        <div class="sm-stat sm-stat--rose">
            <div class="sm-stat__icon"><i class="fa fa-share-nodes"></i></div>
            <p class="sm-stat__label">Total designs</p>
            <p class="sm-stat__value">{{ $designs->count() }}</p>
        </div>
        <div class="sm-stat sm-stat--violet">
            <div class="sm-stat__icon"><i class="fa fa-calendar-check"></i></div>
            <p class="sm-stat__label">This month</p>
            <p class="sm-stat__value">{{ $designs->filter(fn($d) => $d->created_at->isCurrentMonth())->count() }}</p>
        </div>
        <div class="sm-stat sm-stat--sky">
            <div class="sm-stat__icon"><i class="fa fa-clock-rotate-left"></i></div>
            <p class="sm-stat__label">Last updated</p>
            <p class="sm-stat__value" style="font-size:14px;">{{ $designs->first()?->updated_at->diffForHumans() ?? '—' }}</p>
        </div>
    </div>
    @endif

    {{-- ── Platform size guide ── --}}
    <p style="margin-bottom:10px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);display:flex;align-items:center;gap:8px;">
        <i class="fa fa-ruler-combined" aria-hidden="true"></i> Platform sizes
        <span style="flex:1;height:1px;background:var(--border);display:block;"></span>
    </p>
    <div class="sm-platforms" style="margin-bottom:20px;">
        <div class="sm-platform" onclick="window.location.href='{{ route('designstudio.editor.create') }}?w=1080&h=1080&type=social-media'">
            <div class="sm-platform__icon" style="color:#E1306C;"><i class="fa fa-instagram"></i></div>
            <p class="sm-platform__name">Instagram Post</p>
            <p class="sm-platform__size">1080 × 1080 px</p>
        </div>
        <div class="sm-platform" onclick="window.location.href='{{ route('designstudio.editor.create') }}?w=1080&h=1920&type=social-media'">
            <div class="sm-platform__icon" style="color:#E1306C;"><i class="fa fa-mobile-screen"></i></div>
            <p class="sm-platform__name">Story / Reel</p>
            <p class="sm-platform__size">1080 × 1920 px</p>
        </div>
        <div class="sm-platform" onclick="window.location.href='{{ route('designstudio.editor.create') }}?w=1200&h=630&type=social-media'">
            <div class="sm-platform__icon" style="color:#1877F2;"><i class="fa fa-facebook"></i></div>
            <p class="sm-platform__name">Facebook Post</p>
            <p class="sm-platform__size">1200 × 630 px</p>
        </div>
        <div class="sm-platform" onclick="window.location.href='{{ route('designstudio.editor.create') }}?w=1280&h=720&type=social-media'">
            <div class="sm-platform__icon" style="color:#FF0000;"><i class="fa fa-youtube"></i></div>
            <p class="sm-platform__name">YouTube Thumb</p>
            <p class="sm-platform__size">1280 × 720 px</p>
        </div>
        <div class="sm-platform" onclick="window.location.href='{{ route('designstudio.editor.create') }}?w=1500&h=500&type=social-media'">
            <div class="sm-platform__icon" style="color:#1DA1F2;"><i class="fa fa-twitter"></i></div>
            <p class="sm-platform__name">Twitter Banner</p>
            <p class="sm-platform__size">1500 × 500 px</p>
        </div>
        <div class="sm-platform" onclick="window.location.href='{{ route('designstudio.editor.create') }}?w=1128&h=191&type=social-media'">
            <div class="sm-platform__icon" style="color:#0077B5;"><i class="fa fa-linkedin"></i></div>
            <p class="sm-platform__name">LinkedIn Banner</p>
            <p class="sm-platform__size">1128 × 191 px</p>
        </div>
    </div>

    {{-- ── Designs ── --}}
    <p style="margin-bottom:10px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);display:flex;align-items:center;gap:8px;">
        <i class="fa fa-images" aria-hidden="true"></i> My Post Designs ({{ $designs->count() }})
        <span style="flex:1;height:1px;background:var(--border);display:block;"></span>
    </p>

    @if($designs->isEmpty())
        <section class="pcat-inline">
            <div class="sm-empty">
                <div class="sm-empty__icon"><i class="fa fa-share-nodes"></i></div>
                <p class="sm-empty__title">No social media designs yet</p>
                <p class="sm-empty__desc">Create eye-catching posts for Instagram, Facebook, LinkedIn and more. Pick a platform size above or start from a custom canvas.</p>
                <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:center;margin-top:6px;">
                    <a href="{{ route('designstudio.editor.create') }}?w=1080&h=1080&type=social-media"
                       class="linkbtn" style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;">
                        <i class="fa fa-plus"></i> Create first design
                    </a>
                </div>
            </div>
        </section>
    @else
        <div class="sm-grid">
            @foreach($designs as $d)
                <div class="sm-card">
                    <div class="sm-card__preview">
                        <i class="fa fa-share-nodes" aria-hidden="true"></i>
                    </div>
                    <div class="sm-card__info">
                        <p class="sm-card__title">{{ $d->title }}</p>
                        <p class="sm-card__meta">{{ $d->width }} × {{ $d->height }}px &bull; {{ $d->updated_at->diffForHumans() }}</p>
                    </div>
                    <div class="sm-card__actions">
                        <a href="{{ route('designstudio.editor.edit', $d) }}" class="sm-card__btn">
                            <i class="fa fa-pen" aria-hidden="true"></i> Edit
                        </a>
                        <form action="{{ route('designstudio.designs.destroy', $d) }}" method="POST" onsubmit="return confirm('Delete this design?')" style="flex:1;display:contents;">
                            @csrf @method('DELETE')
                            <button type="submit" class="sm-card__btn sm-card__btn--del">
                                <i class="fa fa-trash" aria-hidden="true"></i>
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach

            {{-- New design card --}}
            <div class="sm-new-card" onclick="window.location.href='{{ route('designstudio.editor.create') }}?w=1080&h=1080&type=social-media'">
                <div class="sm-new-card__icon"><i class="fa fa-plus" aria-hidden="true"></i></div>
                <span class="sm-new-card__label">New Post Design</span>
            </div>
        </div>
    @endif

</div>

{{-- ══════════════════════════════════════
     CONNECT ACCOUNTS MODAL
     ══════════════════════════════════════ --}}
<div class="smcon-backdrop" id="smconBackdrop">
    <div class="smcon-panel">

        <div class="smcon-header">
            <div class="smcon-header-icon"><i class="fa fa-plug" aria-hidden="true"></i></div>
            <div class="smcon-header-text">
                <h2>Connect Social Accounts</h2>
                <p>Link your business accounts to publish designs directly</p>
            </div>
            <button class="smcon-close" onclick="closeConnectModal()" title="Close">
                <i class="fa fa-times" aria-hidden="true"></i>
            </button>
        </div>

        <div class="smcon-body">
            <div class="smcon-grid">

                {{-- Facebook --}}
                <div class="smcon-card smcon-card--facebook">
                    <div class="smcon-card__logo">
                        <i class="fa fa-facebook" aria-hidden="true"></i>
                    </div>
                    <p class="smcon-card__name">Facebook</p>
                    <p class="smcon-card__desc">Share posts and stories to your Facebook page and reach your audience.</p>
                    @if($facebookPages->isNotEmpty())
                        <a href="{{ route('designstudio.facebook.redirect') }}" class="smcon-card__btn" style="background:color-mix(in srgb,#1877F2 14%,transparent);border:1px solid color-mix(in srgb,#1877F2 40%,var(--border));color:#1877F2;">
                            <i class="fa fa-plus" aria-hidden="true"></i> Add Another Page
                        </a>
                    @else
                        <a href="{{ route('designstudio.facebook.redirect') }}" class="smcon-card__btn">
                            <i class="fa fa-plug" aria-hidden="true"></i> Connect
                        </a>
                    @endif
                </div>

                {{-- LinkedIn --}}
                <div class="smcon-card smcon-card--linkedin">
                    <div class="smcon-card__logo">
                        <i class="fa fa-linkedin" aria-hidden="true"></i>
                    </div>
                    <p class="smcon-card__name">LinkedIn</p>
                    <p class="smcon-card__desc">Publish professional content to your LinkedIn company page.</p>
                    <button type="button" class="smcon-card__btn" onclick="smconConnect('linkedin')">
                        <i class="fa fa-plug" aria-hidden="true"></i> Connect
                    </button>
                </div>

                {{-- YouTube --}}
                <div class="smcon-card smcon-card--youtube">
                    <div class="smcon-card__logo">
                        <i class="fa fa-youtube" aria-hidden="true"></i>
                    </div>
                    <p class="smcon-card__name">YouTube</p>
                    <p class="smcon-card__desc">Upload thumbnails and channel artwork directly to your YouTube channel.</p>
                    <button type="button" class="smcon-card__btn" onclick="smconConnect('youtube')">
                        <i class="fa fa-plug" aria-hidden="true"></i> Connect
                    </button>
                </div>

                {{-- TikTok --}}
                <div class="smcon-card smcon-card--tiktok">
                    <div class="smcon-card__logo">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-2.88 2.5 2.89 2.89 0 0 1-2.89-2.89 2.89 2.89 0 0 1 2.89-2.89c.28 0 .54.04.79.1V9.01a6.33 6.33 0 0 0-.79-.05 6.34 6.34 0 0 0-6.34 6.34 6.34 6.34 0 0 0 6.34 6.34 6.34 6.34 0 0 0 6.33-6.34V8.69a8.18 8.18 0 0 0 4.78 1.52V6.75a4.85 4.85 0 0 1-1.01-.06z"/></svg>
                    </div>
                    <p class="smcon-card__name">TikTok</p>
                    <p class="smcon-card__desc">Share short-form video covers and promotional graphics on TikTok.</p>
                    <button type="button" class="smcon-card__btn" onclick="smconConnect('tiktok')">
                        <i class="fa fa-plug" aria-hidden="true"></i> Connect
                    </button>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
function openConnectModal() {
    document.getElementById('smconBackdrop').classList.add('open');
    document.documentElement.style.overflow = 'hidden';
}
function closeConnectModal() {
    document.getElementById('smconBackdrop').classList.remove('open');
    document.documentElement.style.overflow = '';
}
document.getElementById('smconBackdrop').addEventListener('click', function(e) {
    if (e.target === this) closeConnectModal();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeConnectModal();
});
function smconConnect(platform) {
    alert('Connect to ' + platform.charAt(0).toUpperCase() + platform.slice(1) + ' — integration coming soon.');
}
</script>

@endsection
