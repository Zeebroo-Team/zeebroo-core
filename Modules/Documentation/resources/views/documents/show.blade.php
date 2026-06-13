@extends('documentation::layouts.public')

@section('title', $document->title . ' — Documentation')
@section('meta_description', Str::limit(strip_tags($document->content ?? ''), 155))
@section('search_placeholder', 'Search in document…')

@section('head')
<style>
/* ── Breadcrumb ── */
.dh-bc {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 12px;
    color: var(--muted);
    margin-bottom: 28px;
    flex-wrap: wrap;
}
.dh-bc a { color: var(--muted); text-decoration: none; font-weight: 600; transition: color .15s; }
.dh-bc a:hover { color: var(--text); }
.dh-bc__sep { font-size: 9px; opacity: .45; }
.dh-bc__current { color: var(--text); font-weight: 600; }

/* ── Two-column layout ── */
.dh-show-layout {
    display: grid;
    grid-template-columns: 1fr 256px;
    gap: 32px;
    align-items: start;
}
@media (max-width: 860px) {
    .dh-show-layout { grid-template-columns: 1fr; }
    .dh-sidebar { order: 2; }
    .dh-article { order: 1; }
}

/* ── Article ── */
.dh-article {
    background: var(--card);
    border: 1.5px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
}
.dh-article__header {
    padding: 32px 36px 24px;
    border-bottom: 1px solid var(--border);
}
.dh-article__category-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: var(--cat-color, var(--primary-dark));
    background: color-mix(in srgb, var(--cat-color, var(--primary)) 10%, transparent);
    border: 1px solid color-mix(in srgb, var(--cat-color, var(--primary)) 25%, transparent);
    border-radius: 999px;
    padding: 4px 12px;
    text-decoration: none;
    margin-bottom: 16px;
}
.dh-article__category-badge:hover {
    background: color-mix(in srgb, var(--cat-color, var(--primary)) 18%, transparent);
}
.dh-article__title {
    font-size: clamp(20px, 3vw, 28px);
    font-weight: 900;
    letter-spacing: -.04em;
    line-height: 1.2;
    color: var(--text);
    margin: 0 0 20px;
}
.dh-article__meta {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}
.dh-article__meta-item {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    color: var(--muted);
}
.dh-article__meta-item strong { color: var(--text); font-weight: 600; }
.dh-doc-status {
    display: inline-block;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 999px;
    border: 1px solid var(--border);
}
.dh-doc-status--draft { opacity: .7; }
.dh-doc-status--published {
    border-color: color-mix(in srgb,#10b981 45%,var(--border));
    background: color-mix(in srgb,#10b981 12%,transparent);
    color: #065f46;
}

.dh-article__body {
    padding: 32px 36px;
    font-size: 14.5px;
    line-height: 1.85;
    color: var(--text);
    white-space: pre-wrap;
    word-break: break-word;
}

.dh-article__footer {
    padding: 18px 36px;
    border-top: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    background: color-mix(in srgb, var(--bg) 60%, var(--card));
}
.dh-article__footer-meta {
    font-size: 12px;
    color: var(--muted);
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
}
.dh-back-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    font-size: 12.5px;
    font-weight: 700;
    color: var(--muted);
    text-decoration: none;
    padding: 7px 14px;
    border-radius: 9px;
    border: 1px solid var(--border);
    background: var(--bg);
    transition: color .15s, border-color .15s;
}
.dh-back-btn:hover { color: var(--text); border-color: var(--text); }

/* ── Sidebar ── */
.dh-sidebar {
    position: sticky;
    top: 80px;
    display: flex;
    flex-direction: column;
    gap: 14px;
}
.dh-sidebar-card {
    background: var(--card);
    border: 1.5px solid var(--border);
    border-radius: 14px;
    overflow: hidden;
}
.dh-sidebar-card__head {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 14px 16px 12px;
    border-bottom: 1px solid var(--border);
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: var(--muted);
}
.dh-sidebar-card__head i { font-size: 12px; color: var(--cat-color, var(--primary)); }

.dh-related-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 11px 16px;
    text-decoration: none;
    color: var(--text);
    border-bottom: 1px solid color-mix(in srgb, var(--border) 55%, transparent);
    transition: background .12s;
}
.dh-related-item:last-child { border-bottom: none; }
.dh-related-item:hover { background: color-mix(in srgb, var(--cat-color, var(--primary)) 5%, transparent); }
.dh-related-item__dot {
    width: 7px;
    height: 7px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--cat-color, var(--primary)) 45%, var(--border));
    flex-shrink: 0;
    margin-top: 5px;
}
.dh-related-item__title {
    font-size: 12.5px;
    font-weight: 600;
    line-height: 1.4;
    color: var(--text);
}
.dh-related-item:hover .dh-related-item__title { color: var(--cat-color, var(--primary-dark)); }

/* ── Highlight ── */
mark.doc-hl { background: #fef08a; color: #0a0a0a; border-radius: 3px; padding: 0 1px; }
</style>
@endsection

@php
    $catColor = $document->category?->color ?? 'var(--primary)';
    $readTime = max(1, (int) ceil(str_word_count(strip_tags($document->content ?? '')) / 200));
@endphp

@section('content')

{{-- Breadcrumb --}}
<nav class="dh-bc" aria-label="breadcrumb">
    <a href="{{ route('documentation.documents.index') }}">
        <i class="fa fa-house" aria-hidden="true" style="margin-right:4px;"></i>Documentation
    </a>
    @if($document->category)
    <i class="fa fa-chevron-right dh-bc__sep" aria-hidden="true"></i>
    <a href="{{ route('documentation.documents.category', $document->category->slug) }}">{{ $document->category->name }}</a>
    @endif
    <i class="fa fa-chevron-right dh-bc__sep" aria-hidden="true"></i>
    <span class="dh-bc__current">{{ Str::limit($document->title, 50) }}</span>
</nav>

<div class="dh-show-layout" style="--cat-color: {{ $catColor }};">

    {{-- Main article --}}
    <article class="dh-article">

        <header class="dh-article__header">
            {{-- Category badge --}}
            @if($document->category)
            <div>
                <a href="{{ route('documentation.documents.category', $document->category->slug) }}" class="dh-article__category-badge">
                    <i class="{{ $document->category->iconClass() }}" aria-hidden="true"></i>
                    {{ $document->category->name }}
                </a>
            </div>
            @endif

            {{-- Title --}}
            <h1 class="dh-article__title">{{ $document->title }}</h1>

            {{-- Meta --}}
            <div class="dh-article__meta">
                @if($business)
                <span class="dh-doc-status dh-doc-status--{{ $document->status }}">
                    {{ $document->statusLabel() }}
                </span>
                @endif
                @if($document->author)
                <div class="dh-article__meta-item">
                    <i class="fa fa-user-pen" aria-hidden="true"></i>
                    <strong>{{ $document->author->name }}</strong>
                </div>
                @endif
                <div class="dh-article__meta-item">
                    <i class="fa fa-calendar-days" aria-hidden="true"></i>
                    <span>{{ $document->created_at->format('M j, Y') }}</span>
                </div>
                @if($document->updated_at->gt($document->created_at))
                <div class="dh-article__meta-item">
                    <i class="fa fa-rotate" aria-hidden="true"></i>
                    <span>Updated {{ $document->updated_at->diffForHumans() }}</span>
                </div>
                @endif
                <div class="dh-article__meta-item">
                    <i class="fa fa-clock" aria-hidden="true"></i>
                    <span>{{ $readTime }} min read</span>
                </div>
            </div>
        </header>

        {{-- Content --}}
        <div class="dh-article__body" id="docBody">
            @if(filled($document->content))
                {{ $document->content }}
            @else
                <span style="color:var(--muted);font-style:italic;">No content has been added to this article yet.</span>
            @endif
        </div>

        {{-- Footer --}}
        <footer class="dh-article__footer">
            <div class="dh-article__footer-meta">
                <span>
                    <i class="fa fa-circle-check" aria-hidden="true" style="margin-right:4px;color:#10b981;"></i>
                    Last updated {{ $document->updated_at->format('F j, Y') }}
                </span>
            </div>
            <a href="{{ route('documentation.documents.category', $document->category?->slug ?? '') }}" class="dh-back-btn">
                <i class="fa fa-arrow-left" aria-hidden="true"></i>
                Back to {{ $document->category?->name ?? 'Documentation' }}
            </a>
        </footer>

    </article>

    {{-- Sidebar --}}
    <aside class="dh-sidebar">

        {{-- In this category --}}
        @if($related->isNotEmpty())
        <div class="dh-sidebar-card">
            <div class="dh-sidebar-card__head">
                <i class="{{ $document->category?->iconClass() ?? 'fa fa-layer-group' }}" aria-hidden="true"></i>
                More in {{ $document->category?->name ?? 'this category' }}
            </div>
            @foreach($related as $rel)
            <a href="{{ route('documentation.documents.show', $rel) }}" class="dh-related-item">
                <span class="dh-related-item__dot"></span>
                <span class="dh-related-item__title">{{ $rel->title }}</span>
            </a>
            @endforeach
        </div>
        @endif

        {{-- All categories --}}
        <div class="dh-sidebar-card">
            <div class="dh-sidebar-card__head">
                <i class="fa fa-grid-2" aria-hidden="true"></i>
                All categories
            </div>
            <a href="{{ route('documentation.documents.index') }}" class="dh-related-item">
                <span class="dh-related-item__dot" style="background:var(--primary);"></span>
                <span class="dh-related-item__title">Documentation Home</span>
            </a>
        </div>

    </aside>

</div>

@endsection

@section('scripts')
<script>
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
        if (node.nodeType === 3) {
            if (re.test(node.textContent)) {
                var span = document.createElement('span');
                span.innerHTML = node.textContent.replace(re, '<mark class="doc-hl">$1</mark>');
                node.parentNode.replaceChild(span, node);
            }
        } else if (node.nodeType === 1 && node.nodeName !== 'MARK') {
            Array.from(node.childNodes).forEach(walk);
        }
    })(body);
    // Scroll to first highlight
    var first = body.querySelector('mark.doc-hl');
    if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
};
</script>
@endsection
