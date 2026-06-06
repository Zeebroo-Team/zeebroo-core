@if ($paginator->hasPages())
<style>
.zpg{display:flex;align-items:center;flex-wrap:wrap;gap:4px;font-size:12px;}
.zpg__btn{display:inline-flex;align-items:center;justify-content:center;min-width:30px;height:30px;padding:0 8px;border-radius:7px;border:1px solid var(--border);background:transparent;color:var(--text);text-decoration:none;font-size:12px;font-weight:500;cursor:pointer;line-height:1;transition:border-color .13s,background .13s,color .13s;}
.zpg__btn:hover{border-color:color-mix(in srgb,var(--primary) 55%,var(--border));background:color-mix(in srgb,var(--primary) 9%,transparent);color:var(--text);}
.zpg__btn--active{border-color:color-mix(in srgb,var(--primary) 55%,var(--border));background:color-mix(in srgb,var(--primary) 16%,transparent);font-weight:700;cursor:default;}
.zpg__btn--disabled{opacity:.38;cursor:not-allowed;pointer-events:none;}
.zpg__dots{display:inline-flex;align-items:center;justify-content:center;min-width:24px;height:30px;color:var(--muted);font-size:12px;user-select:none;}
.zpg__info{font-size:11px;color:var(--muted);margin-right:6px;white-space:nowrap;}
</style>
<nav class="zpg" aria-label="Pagination" role="navigation">
    <span class="zpg__info">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}</span>

    {{-- Previous --}}
    @if ($paginator->onFirstPage())
        <span class="zpg__btn zpg__btn--disabled" aria-disabled="true" aria-label="Previous page">
            <i class="fa fa-chevron-left" aria-hidden="true" style="font-size:10px;"></i>
        </span>
    @else
        <a href="{{ $paginator->previousPageUrl() }}" class="zpg__btn" aria-label="Previous page" rel="prev">
            <i class="fa fa-chevron-left" aria-hidden="true" style="font-size:10px;"></i>
        </a>
    @endif

    {{-- Page numbers --}}
    @foreach ($elements as $element)
        @if (is_string($element))
            <span class="zpg__dots">…</span>
        @endif

        @if (is_array($element))
            @foreach ($element as $page => $url)
                @if ($page == $paginator->currentPage())
                    <span class="zpg__btn zpg__btn--active" aria-current="page">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" class="zpg__btn">{{ $page }}</a>
                @endif
            @endforeach
        @endif
    @endforeach

    {{-- Next --}}
    @if ($paginator->hasMorePages())
        <a href="{{ $paginator->nextPageUrl() }}" class="zpg__btn" aria-label="Next page" rel="next">
            <i class="fa fa-chevron-right" aria-hidden="true" style="font-size:10px;"></i>
        </a>
    @else
        <span class="zpg__btn zpg__btn--disabled" aria-disabled="true" aria-label="Next page">
            <i class="fa fa-chevron-right" aria-hidden="true" style="font-size:10px;"></i>
        </span>
    @endif
</nav>
@endif
