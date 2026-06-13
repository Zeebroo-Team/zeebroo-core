@extends('documentation::layouts.public')

@section('title', filled($search) ? 'Search: ' . $search . ' — Documentation' : 'Documentation')
@section('meta_description', 'Browse documentation categories for ' . config('app.name'))
@section('search_placeholder', 'Search documentation…')

@section('head')
<style>
/* ── Hero ── */
.dh-hero {
    padding: 56px 0 48px;
    text-align: center;
    border-bottom: 1px solid var(--border);
    margin-bottom: 48px;
}
.dh-hero__eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .1em;
    color: var(--primary);
    background: color-mix(in srgb, var(--primary) 10%, transparent);
    border: 1px solid color-mix(in srgb, var(--primary) 28%, transparent);
    border-radius: 999px;
    padding: 5px 14px;
    margin-bottom: 20px;
}
.dh-hero__title {
    font-size: clamp(28px, 5vw, 42px);
    font-weight: 900;
    letter-spacing: -.04em;
    line-height: 1.15;
    color: var(--text);
    margin: 0 0 12px;
}
.dh-hero__title span {
    color: var(--primary);
}
.dh-hero__sub {
    font-size: 15px;
    color: var(--muted);
    margin: 0 0 28px;
    max-width: 480px;
    margin-left: auto;
    margin-right: auto;
    line-height: 1.6;
}
.dh-hero__search {
    position: relative;
    max-width: 520px;
    margin: 0 auto 20px;
}
.dh-hero__search-icon {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 14px;
    color: var(--muted);
    pointer-events: none;
}
.dh-hero__search-input {
    width: 100%;
    box-sizing: border-box;
    padding: 14px 130px 14px 44px;
    border-radius: 12px;
    border: 1.5px solid var(--border);
    background: var(--bg);
    font-size: 14px;
    color: var(--text);
    font-family: inherit;
    outline: none;
    transition: border-color .18s, box-shadow .18s;
}
.dh-hero__search-input:focus {
    border-color: color-mix(in srgb, var(--primary) 60%, var(--border));
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 12%, transparent);
}
.dh-hero__search-btn {
    position: absolute;
    right: 6px;
    top: 50%;
    transform: translateY(-50%);
    padding: 8px 20px;
    border-radius: 8px;
    border: none;
    background: var(--primary-dark);
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    font-family: inherit;
    cursor: pointer;
    transition: background .15s;
}
.dh-hero__search-btn:hover { background: var(--primary); color: var(--primary-dark); }
.dh-hero__stats {
    font-size: 12px;
    color: var(--muted);
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 12px;
}
.dh-hero__stats strong { color: var(--text); font-weight: 700; }
.dh-hero__stats-sep { opacity: .4; }

/* ── Section heading ── */
.dh-section-hd {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    margin-bottom: 20px;
}
.dh-section-hd h2 {
    font-size: 16px;
    font-weight: 800;
    color: var(--text);
    margin: 0;
    letter-spacing: -.02em;
}
.dh-section-hd span {
    font-size: 12px;
    color: var(--muted);
}

/* ── Category cards ── */
.dh-cat-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
    margin-bottom: 48px;
}
@media (max-width: 640px) { .dh-cat-grid { grid-template-columns: 1fr; } }

.dh-cat-card {
    display: flex;
    align-items: flex-start;
    gap: 18px;
    padding: 24px 22px;
    border-radius: 14px;
    border: 1.5px solid var(--border);
    background: var(--card);
    text-decoration: none;
    color: var(--text);
    transition: border-color .18s, box-shadow .18s, transform .18s;
    border-left: 4px solid var(--cat-color, var(--primary));
}
.dh-cat-card:hover {
    box-shadow: 0 6px 24px rgba(0,0,0,.09);
    transform: translateY(-2px);
    border-color: color-mix(in srgb, var(--cat-color, var(--primary)) 50%, var(--border));
    border-left-color: var(--cat-color, var(--primary));
}
.dh-cat-card__icon-wrap {
    width: 52px;
    height: 52px;
    border-radius: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
    background: color-mix(in srgb, var(--cat-color, var(--primary)) 12%, transparent);
    color: var(--cat-color, var(--primary));
    border: 1px solid color-mix(in srgb, var(--cat-color, var(--primary)) 22%, transparent);
}
.dh-cat-card__body {
    flex: 1;
    min-width: 0;
}
.dh-cat-card__name {
    font-size: 15px;
    font-weight: 800;
    color: var(--text);
    margin: 0 0 6px;
    letter-spacing: -.02em;
}
.dh-cat-card__desc {
    font-size: 12.5px;
    color: var(--muted);
    line-height: 1.6;
    margin: 0 0 12px;
}
.dh-cat-card__foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.dh-cat-card__count {
    font-size: 11px;
    font-weight: 700;
    color: var(--cat-color, var(--primary));
    background: color-mix(in srgb, var(--cat-color, var(--primary)) 10%, transparent);
    border: 1px solid color-mix(in srgb, var(--cat-color, var(--primary)) 25%, transparent);
    border-radius: 999px;
    padding: 3px 10px;
}
.dh-cat-card__arrow {
    font-size: 12px;
    color: var(--muted);
    transition: color .15s, transform .15s;
}
.dh-cat-card:hover .dh-cat-card__arrow {
    color: var(--cat-color, var(--primary));
    transform: translateX(4px);
}

/* ── Help banner ── */
.dh-help-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    background: var(--primary-dark);
    border-radius: 16px;
    padding: 28px 32px;
    margin-bottom: 40px;
    flex-wrap: wrap;
}
.dh-help-banner__left { display: flex; align-items: center; gap: 16px; }
.dh-help-banner__icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: rgba(255,255,255,.08);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: var(--primary);
    flex-shrink: 0;
}
.dh-help-banner__title {
    font-size: 16px;
    font-weight: 800;
    color: #fff;
    margin: 0 0 4px;
}
.dh-help-banner__sub {
    font-size: 13px;
    color: rgba(255,255,255,.55);
    margin: 0;
}
.dh-help-banner__btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 11px 22px;
    border-radius: 10px;
    background: var(--primary);
    color: var(--primary-dark);
    font-size: 13px;
    font-weight: 800;
    text-decoration: none;
    white-space: nowrap;
    transition: background .15s;
}
.dh-help-banner__btn:hover { background: #facc15; }

/* ── Search results ── */
.dh-results-hd {
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.dh-results-hd h2 { font-size: 16px; font-weight: 800; color: var(--text); margin: 0; }
.dh-results-hd span { font-size: 12px; color: var(--muted); }
.dh-results-clear {
    margin-left: auto;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 700;
    color: var(--muted);
    text-decoration: none;
    padding: 4px 10px;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: var(--bg);
    transition: color .15s, border-color .15s;
}
.dh-results-clear:hover { color: var(--text); border-color: var(--text); }

.dh-result-item {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 18px 20px;
    border-radius: 12px;
    border: 1px solid var(--border);
    background: var(--card);
    text-decoration: none;
    color: var(--text);
    margin-bottom: 10px;
    transition: border-color .15s, box-shadow .15s, transform .15s;
}
.dh-result-item:hover {
    border-color: color-mix(in srgb, var(--primary) 45%, var(--border));
    box-shadow: 0 3px 12px rgba(0,0,0,.07);
    transform: translateX(3px);
}
.dh-result-item__icon {
    width: 38px;
    height: 38px;
    border-radius: 9px;
    background: var(--primary-light);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    color: var(--primary-dark);
    flex-shrink: 0;
    margin-top: 1px;
}
.dh-result-item__title {
    font-size: 14px;
    font-weight: 700;
    color: var(--text);
    margin: 0 0 4px;
}
.dh-result-item:hover .dh-result-item__title { color: var(--primary-dark); }
.dh-result-item__excerpt {
    font-size: 12px;
    color: var(--muted);
    margin: 0 0 6px;
    line-height: 1.5;
}
.dh-result-item__meta {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 11px;
    color: var(--muted);
}
.dh-result-item__cat {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: color-mix(in srgb, var(--primary) 8%, transparent);
    border: 1px solid color-mix(in srgb, var(--primary) 22%, var(--border));
    border-radius: 6px;
    padding: 1px 8px;
    font-weight: 600;
    color: var(--primary-dark);
}

.dh-empty {
    text-align: center;
    padding: 72px 24px;
    color: var(--muted);
}
.dh-empty i { font-size: 40px; opacity: .2; display: block; margin-bottom: 16px; }
.dh-empty p { font-size: 14px; margin: 0 0 6px; }
.dh-empty small { font-size: 12px; }
</style>
@endsection

@section('content')

{{-- Hero --}}
<div class="dh-hero">
    <div class="dh-hero__eyebrow">
        <i class="fa fa-book-open" aria-hidden="true"></i>
        Documentation Hub
    </div>
    <h1 class="dh-hero__title">
        Find answers, guides &amp;<br>
        <span>technical references.</span>
    </h1>
    <p class="dh-hero__sub">
        Everything you need to understand, configure, and build with
        <strong>{{ config('app.name') }}</strong>.
    </p>
    <form method="GET" action="{{ route('documentation.documents.index') }}" class="dh-hero__search">
        <i class="fa fa-magnifying-glass dh-hero__search-icon" aria-hidden="true"></i>
        <input
            type="text"
            name="q"
            value="{{ $search }}"
            class="dh-hero__search-input"
            placeholder="Search across all documentation…"
            autocomplete="off"
        >
        <button type="submit" class="dh-hero__search-btn">Search</button>
    </form>
    <div class="dh-hero__stats">
        <span><strong>{{ $categories->count() }}</strong> Categories</span>
        <span class="dh-hero__stats-sep">·</span>
        <span><strong>{{ number_format($totalDocs) }}</strong> Articles</span>
        @if($business)
        <span class="dh-hero__stats-sep">·</span>
        <span><strong>{{ config('app.name') }}</strong> Workspace</span>
        @endif
    </div>
</div>

@if(filled($search))

    {{-- Search results --}}
    <div class="dh-results-hd">
        <h2>
            <i class="fa fa-magnifying-glass" style="color:var(--muted);margin-right:6px;font-size:14px;" aria-hidden="true"></i>
            Results for &ldquo;{{ $search }}&rdquo;
        </h2>
        @if($searchResults)
        <span>{{ $searchResults->total() }} {{ Str::plural('result', $searchResults->total()) }}</span>
        @endif
        <a href="{{ route('documentation.documents.index') }}" class="dh-results-clear">
            <i class="fa fa-xmark" aria-hidden="true"></i> Clear search
        </a>
    </div>

    @if($searchResults && $searchResults->isNotEmpty())
        @foreach($searchResults as $doc)
        <a href="{{ route('documentation.documents.show', $doc) }}" class="dh-result-item">
            <div class="dh-result-item__icon"
                 @if($doc->category) style="background:color-mix(in srgb,{{ $doc->category->color ?? 'var(--primary)' }} 12%,transparent);color:{{ $doc->category->color ?? 'var(--primary-dark)' }};" @endif>
                <i class="{{ $doc->category?->iconClass() ?? 'fa fa-file-lines' }}" aria-hidden="true"></i>
            </div>
            <div>
                <div class="dh-result-item__title">{{ $doc->title }}</div>
                @if(filled($doc->content))
                <div class="dh-result-item__excerpt">{{ Str::limit(strip_tags($doc->content), 130) }}</div>
                @endif
                <div class="dh-result-item__meta">
                    @if($doc->category)
                    <span class="dh-result-item__cat">
                        <i class="{{ $doc->category->iconClass() }}" aria-hidden="true"></i>
                        {{ $doc->category->name }}
                    </span>
                    @endif
                    <span>
                        <i class="fa fa-clock" aria-hidden="true"></i>
                        {{ $doc->updated_at->format('M j, Y') }}
                    </span>
                </div>
            </div>
        </a>
        @endforeach
        <div style="margin-top:16px;">{{ $searchResults->appends(['q' => $search])->links() }}</div>
    @else
        <div class="dh-empty">
            <i class="fa fa-file-circle-xmark" aria-hidden="true"></i>
            <p>No results for &ldquo;{{ $search }}&rdquo;</p>
            <small>Try different keywords or browse a category below.</small>
            <div style="margin-top:20px;">
                <a href="{{ route('documentation.documents.index') }}" style="font-size:13px;font-weight:700;color:var(--primary-dark);text-decoration:none;border:1px solid var(--border);padding:8px 16px;border-radius:9px;background:var(--bg);">
                    <i class="fa fa-grid-2" aria-hidden="true"></i> Browse categories
                </a>
            </div>
        </div>
    @endif

@else

    {{-- Category grid --}}
    <div class="dh-section-hd">
        <h2>Browse by Category</h2>
        <span>{{ $categories->count() }} {{ Str::plural('category', $categories->count()) }}</span>
    </div>

    @if($categories->isEmpty())
        <div class="dh-empty">
            <i class="fa fa-folder-open" aria-hidden="true"></i>
            <p>No documentation categories available yet.</p>
        </div>
    @else
        <div class="dh-cat-grid" id="catGrid">
            @foreach($categories as $cat)
            <a
                href="{{ route('documentation.documents.category', $cat->slug) }}"
                class="dh-cat-card"
                style="--cat-color: {{ $cat->color ?? 'var(--primary)' }};"
            >
                <div class="dh-cat-card__icon-wrap">
                    <i class="{{ $cat->iconClass() }}" aria-hidden="true"></i>
                </div>
                <div class="dh-cat-card__body">
                    <div class="dh-cat-card__name">{{ $cat->name }}</div>
                    @if($cat->description)
                    <div class="dh-cat-card__desc">{{ $cat->description }}</div>
                    @endif
                    <div class="dh-cat-card__foot">
                        <span class="dh-cat-card__count">
                            <i class="fa fa-file-lines" aria-hidden="true" style="margin-right:4px;"></i>
                            {{ $cat->documents_count }} {{ Str::plural('article', $cat->documents_count) }}
                        </span>
                        <i class="fa fa-arrow-right dh-cat-card__arrow" aria-hidden="true"></i>
                    </div>
                </div>
            </a>
            @endforeach
        </div>
    @endif

    {{-- Help banner --}}
    <div class="dh-help-banner">
        <div class="dh-help-banner__left">
            <div class="dh-help-banner__icon">
                <i class="fa fa-headset" aria-hidden="true"></i>
            </div>
            <div>
                <p class="dh-help-banner__title">Can&rsquo;t find what you&rsquo;re looking for?</p>
                <p class="dh-help-banner__sub">Our support team is ready to help you with any questions.</p>
            </div>
        </div>
        <a href="mailto:{{ config('mail.from.address', 'support@' . request()->getHost()) }}" class="dh-help-banner__btn">
            <i class="fa fa-envelope" aria-hidden="true"></i> Contact Support
        </a>
    </div>

@endif

@endsection

@section('scripts')
<script>
// Topbar search: client-side filter category cards (only when not in search-results mode)
window.pubSearchHandler = function (q) {
    var grid = document.getElementById('catGrid');
    if (!grid) return;
    var term = q.trim().toLowerCase();
    grid.querySelectorAll('.dh-cat-card').forEach(function (card) {
        card.style.display = (!term || card.textContent.toLowerCase().includes(term)) ? '' : 'none';
    });
};
</script>
@endsection
