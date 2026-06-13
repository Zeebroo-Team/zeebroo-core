@extends('documentation::layouts.public')

@section('title', ($activeDocument?->title ?? $category->name) . ' — ' . $category->name . ' — Documentation')
@section('meta_description', $category->description ?? $category->name)
@section('search_placeholder', 'Search in ' . $category->name . '…')

@php
    $isOwner  = $business !== null;
    $catColor = $category->color ?? '#ca8a04';
    $activeId = $activeDocument?->id;
    $readTime = $activeDocument
        ? max(1, (int) ceil(str_word_count(strip_tags($activeDocument->content ?? '')) / 200))
        : 0;
@endphp

@section('head')
<style>
/* Remove pub-main container so hero can be full-width */
.pub-main { max-width: none !important; padding: 0 !important; margin: 0 !important; }

/* ── Hero ── */
.dh-hero {
    background: linear-gradient(135deg, var(--primary-light) 0%, #fefce8 100%);
    border-bottom: 1px solid var(--border);
    padding: 44px 24px 36px;
    text-align: center;
}
.dh-hero__badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: var(--primary);
    background: var(--primary-light);
    border: 1px solid color-mix(in srgb, var(--primary) 25%, var(--border));
    border-radius: 999px;
    padding: 4px 12px;
    margin-bottom: 16px;
}
.dh-hero h1 {
    font-size: clamp(20px, 4vw, 30px);
    font-weight: 800;
    letter-spacing: -.03em;
    color: var(--text);
    margin-bottom: 8px;
    line-height: 1.2;
}
.dh-hero__sub {
    font-size: 14px;
    color: var(--muted);
    max-width: 520px;
    margin: 0 auto 18px;
    line-height: 1.65;
}
.dh-hero__meta {
    display: inline-flex;
    align-items: center;
    gap: 14px;
    font-size: 12px;
    color: var(--muted);
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 6px 14px;
}
.dh-hero__meta span { display: flex; align-items: center; gap: 5px; }
.dh-hero__meta-sep { opacity: .35; }

/* ── Two-column layout (mirrors pp-layout) ── */
.dh-layout {
    display: flex;
    gap: 32px;
    max-width: 1040px;
    margin: 36px auto;
    padding: 0 24px 64px;
    align-items: flex-start;
}
@media (max-width: 740px) {
    .dh-layout { flex-direction: column; padding: 0 14px 48px; margin-top: 24px; }
    .dh-toc { display: none; }
    .dh-content { width: 100%; }
}

/* ── Sidebar TOC (mirrors pp-toc) ── */
.dh-toc {
    flex-shrink: 0;
    width: 220px;
    position: sticky;
    top: 80px;
}
.dh-toc__back {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 11.5px;
    font-weight: 600;
    color: var(--muted);
    text-decoration: none;
    padding: 6px 8px;
    border-radius: 7px;
    border: 1px solid var(--border);
    background: var(--card);
    margin-bottom: 16px;
    transition: color .13s, border-color .13s;
}
.dh-toc__back:hover { color: var(--text); border-color: var(--text); }
.dh-toc__title {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: var(--muted);
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.dh-toc__count {
    font-size: 10px;
    font-weight: 600;
    color: var(--muted);
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 999px;
    padding: 1px 7px;
}

/* Search filter in sidebar */
.dh-toc__search {
    position: relative;
    margin-bottom: 8px;
}
.dh-toc__search i {
    position: absolute;
    left: 9px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 10px;
    color: var(--muted);
    pointer-events: none;
}
.dh-toc__search input {
    width: 100%;
    box-sizing: border-box;
    padding: 6px 8px 6px 26px;
    border-radius: 7px;
    border: 1px solid var(--border);
    background: var(--bg);
    font-size: 11.5px;
    color: var(--text);
    font-family: inherit;
    outline: none;
    transition: border-color .13s;
}
.dh-toc__search input:focus {
    border-color: color-mix(in srgb, var(--primary) 55%, var(--border));
    background: var(--card);
}

/* Status filter (owner) */
.dh-toc__status {
    margin-bottom: 10px;
}
.dh-toc__status select {
    width: 100%;
    box-sizing: border-box;
    padding: 5px 8px;
    border-radius: 7px;
    border: 1px solid var(--border);
    background: var(--bg);
    font-size: 11px;
    color: var(--text);
    font-family: inherit;
    outline: none;
}

/* Article list */
.dh-toc__list { list-style: none; }
.dh-toc__item { margin-bottom: 1px; }
.dh-toc__link {
    display: block;
    font-size: 12px;
    font-weight: 500;
    color: var(--muted);
    text-decoration: none;
    padding: 6px 8px;
    border-radius: 7px;
    border-left: 2px solid transparent;
    transition: background .12s, color .12s, border-color .12s;
    line-height: 1.35;
}
.dh-toc__link:hover {
    background: var(--primary-light);
    color: var(--primary);
    border-left-color: var(--primary);
}
.dh-toc__link.is-active {
    background: var(--primary-light);
    color: var(--primary);
    border-left-color: var(--primary);
    font-weight: 700;
}
.dh-toc__link-meta {
    display: block;
    font-size: 10px;
    color: var(--muted);
    font-weight: 400;
    margin-top: 1px;
    opacity: .8;
}
.dh-toc__empty {
    font-size: 12px;
    color: var(--muted);
    text-align: center;
    padding: 18px 8px;
    opacity: .7;
}

/* ── Main content (mirrors pp-content) ── */
.dh-content { flex: 1; min-width: 0; }

/* Breadcrumb */
.dh-bc {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 11.5px;
    color: var(--muted);
    margin-bottom: 16px;
    flex-wrap: wrap;
}
.dh-bc a { color: var(--muted); text-decoration: none; font-weight: 600; transition: color .13s; }
.dh-bc a:hover { color: var(--text); }
.dh-bc__sep { font-size: 9px; opacity: .4; }
.dh-bc__current { color: var(--text); font-weight: 600; }

/* Article card (mirrors pp-section) */
.dh-art {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
    scroll-margin-top: 80px;
}

.dh-art__header {
    padding: 28px 30px 20px;
    border-bottom: 1px solid var(--border);
}
.dh-art__badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: var(--primary);
    background: var(--primary-light);
    border: 1px solid color-mix(in srgb, var(--primary) 25%, var(--border));
    border-radius: 999px;
    padding: 3px 11px;
    margin-bottom: 12px;
}
.dh-art__title {
    font-size: clamp(18px, 3vw, 24px);
    font-weight: 800;
    letter-spacing: -.03em;
    line-height: 1.2;
    color: var(--text);
    margin-bottom: 16px;
}
.dh-art__meta {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
}
.dh-art__meta-item {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    color: var(--muted);
}
.dh-art__meta-item strong { color: var(--text); font-weight: 600; }
.dh-doc-status {
    font-size: 10px;
    font-weight: 700;
    padding: 2px 9px;
    border-radius: 999px;
    border: 1px solid var(--border);
}
.dh-doc-status--draft { opacity: .7; }
.dh-doc-status--published {
    border-color: color-mix(in srgb,#10b981 45%,var(--border));
    background: color-mix(in srgb,#10b981 12%,transparent);
    color: #065f46;
}

.dh-art__body {
    padding: 28px 30px;
    font-size: 14px;
    line-height: 1.85;
    color: #1c1917;
    white-space: pre-wrap;
    word-break: break-word;
    min-height: 160px;
}

.dh-art__footer {
    padding: 14px 30px;
    border-top: 1px solid var(--border);
    background: var(--bg);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    font-size: 12px;
    color: var(--muted);
}
.dh-art__open {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 700;
    padding: 6px 14px;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: var(--card);
    color: var(--text);
    text-decoration: none;
    transition: border-color .13s, background .13s;
}
.dh-art__open:hover {
    border-color: var(--primary);
    background: var(--primary-light);
    color: var(--primary);
}

/* Empty state */
.dh-art-empty {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 72px 30px;
    text-align: center;
    color: var(--muted);
}
.dh-art-empty i { font-size: 40px; opacity: .15; display: block; margin-bottom: 14px; }
.dh-art-empty p { font-size: 14px; margin: 0 0 4px; }
.dh-art-empty small { font-size: 12px; }

mark.doc-hl { background: #fef08a; color: #0a0a0a; border-radius: 3px; padding: 0 1px; }
</style>
@endsection

@section('content')

{{-- ── Hero ── --}}
<div class="dh-hero">
    <div class="dh-hero__badge">
        <i class="{{ $category->iconClass() }}" aria-hidden="true"></i>
        {{ $category->name }}
    </div>
    <h1>{{ $activeDocument?->title ?? $category->name }}</h1>
    @if(!$activeDocument && $category->description)
    <p class="dh-hero__sub">{{ $category->description }}</p>
    @elseif($activeDocument && $category->description)
    <p class="dh-hero__sub">{{ $category->description }}</p>
    @endif
    <div class="dh-hero__meta">
        <span>
            <i class="fa fa-file-lines" aria-hidden="true"></i>
            {{ $sidebarDocs->count() }} {{ Str::plural('article', $sidebarDocs->count()) }}
        </span>
        @if($activeDocument)
        <span class="dh-hero__meta-sep">·</span>
        <span>
            <i class="fa fa-clock" aria-hidden="true"></i>
            {{ $readTime }} min read
        </span>
        @if($activeDocument->updated_at)
        <span class="dh-hero__meta-sep">·</span>
        <span>
            <i class="fa fa-calendar-days" aria-hidden="true"></i>
            {{ $activeDocument->updated_at->format('M j, Y') }}
        </span>
        @endif
        @endif
    </div>
</div>

{{-- ── Two-column layout ── --}}
<div class="dh-layout">

    {{-- Left sidebar (TOC) --}}
    <aside class="dh-toc">

        <a href="{{ route('documentation.documents.index') }}" class="dh-toc__back">
            <i class="fa fa-arrow-left" aria-hidden="true"></i> All categories
        </a>

        <div class="dh-toc__title">
            Articles
            <span class="dh-toc__count">{{ $sidebarDocs->count() }}</span>
        </div>

        {{-- Search filter --}}
        <div class="dh-toc__search">
            <i class="fa fa-magnifying-glass" aria-hidden="true"></i>
            <input
                type="text"
                id="tocSearch"
                placeholder="Filter…"
                autocomplete="off"
                aria-label="Filter articles"
            >
        </div>

        @if($isOwner)
        {{-- Status filter --}}
        <div class="dh-toc__status">
            <form method="GET" action="{{ route('documentation.documents.category', $category->slug) }}">
                <input type="hidden" name="doc" value="{{ $activeDocument?->slug }}">
                <select name="status" onchange="this.form.submit()" aria-label="Filter by status">
                    <option value="all"       @selected($statusFilter === 'all')>All statuses</option>
                    <option value="published" @selected($statusFilter === 'published')>Published</option>
                    <option value="draft"     @selected($statusFilter === 'draft')>Draft only</option>
                </select>
            </form>
        </div>
        @endif

        {{-- Article list --}}
        <ul class="dh-toc__list" id="tocList">
            @forelse($sidebarDocs as $doc)
            <li class="dh-toc__item">
                <a
                    href="{{ route('documentation.documents.category', ['category' => $category->slug, 'doc' => $doc->slug, ...($statusFilter !== 'all' ? ['status' => $statusFilter] : [])]) }}"
                    class="dh-toc__link {{ $doc->id === $activeId ? 'is-active' : '' }}"
                    data-title="{{ strtolower($doc->title) }}"
                    @if($doc->id === $activeId) aria-current="page" @endif
                >
                    {{ $doc->title }}
                    @if($isOwner && $doc->status === 'draft')
                    <span class="dh-toc__link-meta">Draft</span>
                    @endif
                </a>
            </li>
            @empty
            <li><div class="dh-toc__empty">No articles yet</div></li>
            @endforelse
        </ul>

    </aside>

    {{-- Main content --}}
    <div class="dh-content">

        {{-- Breadcrumb --}}
        <nav class="dh-bc" aria-label="breadcrumb">
            <a href="{{ route('documentation.documents.index') }}">
                <i class="fa fa-house" aria-hidden="true" style="margin-right:3px;"></i>Docs
            </a>
            <i class="fa fa-chevron-right dh-bc__sep" aria-hidden="true"></i>
            <a href="{{ route('documentation.documents.category', $category->slug) }}">{{ $category->name }}</a>
            @if($activeDocument)
            <i class="fa fa-chevron-right dh-bc__sep" aria-hidden="true"></i>
            <span class="dh-bc__current">{{ Str::limit($activeDocument->title, 48) }}</span>
            @endif
        </nav>

        @if($activeDocument)

        <div class="dh-art" id="docArticle">

            <header class="dh-art__header">
                <div class="dh-art__badge">
                    <i class="{{ $category->iconClass() }}" aria-hidden="true"></i>
                    {{ $category->name }}
                </div>
                <h2 class="dh-art__title">{{ $activeDocument->title }}</h2>
                <div class="dh-art__meta">
                    @if($isOwner)
                    <span class="dh-doc-status dh-doc-status--{{ $activeDocument->status }}">
                        {{ $activeDocument->statusLabel() }}
                    </span>
                    @endif
                    @if($activeDocument->author)
                    <div class="dh-art__meta-item">
                        <i class="fa fa-user-pen" aria-hidden="true"></i>
                        <strong>{{ $activeDocument->author->name }}</strong>
                    </div>
                    @endif
                    <div class="dh-art__meta-item">
                        <i class="fa fa-calendar-days" aria-hidden="true"></i>
                        {{ $activeDocument->created_at->format('M j, Y') }}
                    </div>
                    @if($activeDocument->updated_at->gt($activeDocument->created_at))
                    <div class="dh-art__meta-item">
                        <i class="fa fa-rotate" aria-hidden="true"></i>
                        Updated {{ $activeDocument->updated_at->diffForHumans() }}
                    </div>
                    @endif
                    <div class="dh-art__meta-item">
                        <i class="fa fa-clock" aria-hidden="true"></i>
                        {{ $readTime }} min read
                    </div>
                </div>
            </header>

            <div class="dh-art__body" id="docBody">
                @if(filled($activeDocument->content))
                    {{ $activeDocument->content }}
                @else
                    <span style="color:var(--muted);font-style:italic;">No content has been added to this article yet.</span>
                @endif
            </div>

            <footer class="dh-art__footer">
                <span>
                    <i class="fa fa-circle-check" style="color:#10b981;margin-right:4px;" aria-hidden="true"></i>
                    Last updated {{ $activeDocument->updated_at->format('F j, Y') }}
                </span>
                <a href="{{ route('documentation.documents.show', $activeDocument) }}" class="dh-art__open">
                    <i class="fa fa-arrow-up-right-from-square" aria-hidden="true"></i>
                    Open full page
                </a>
            </footer>

        </div>

        @else

        <div class="dh-art-empty">
            <i class="fa fa-file-circle-xmark" aria-hidden="true"></i>
            <p>No articles in this category yet.</p>
            <small>Check back later or explore other categories.</small>
        </div>

        @endif

    </div>{{-- .dh-content --}}

</div>{{-- .dh-layout --}}

@endsection

@section('scripts')
<script>
// Sidebar article filter (client-side)
(function () {
    var input = document.getElementById('tocSearch');
    var list  = document.getElementById('tocList');
    if (!input || !list) return;
    input.addEventListener('input', function () {
        var term = this.value.trim().toLowerCase();
        list.querySelectorAll('.dh-toc__item').forEach(function (li) {
            var link = li.querySelector('.dh-toc__link');
            li.style.display = (!term || (link && link.dataset.title.includes(term))) ? '' : 'none';
        });
    });
})();

// Scroll active item into view in sidebar
(function () {
    var active = document.querySelector('.dh-toc__link.is-active');
    if (active) active.scrollIntoView({ block: 'nearest' });
})();

// Topbar search highlights text inside the article body
window.pubSearchHandler = function (q) {
    var body = document.getElementById('docBody');
    if (!body) return;
    body.querySelectorAll('mark.doc-hl').forEach(function (m) {
        m.replaceWith(document.createTextNode(m.textContent));
    });
    body.normalize();
    if (!q.trim()) return;
    var re = new RegExp('(' + q.trim().replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
    (function walk(node) {
        if (node.nodeType === 3 && re.test(node.textContent)) {
            var span = document.createElement('span');
            span.innerHTML = node.textContent.replace(re, '<mark class="doc-hl">$1</mark>');
            node.parentNode.replaceChild(span, node);
        } else if (node.nodeType === 1 && node.nodeName !== 'MARK') {
            Array.from(node.childNodes).forEach(walk);
        }
    })(body);
    var first = body.querySelector('mark.doc-hl');
    if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
};
</script>
@endsection
