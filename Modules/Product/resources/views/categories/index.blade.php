@extends('theme::layouts.app', ['title' => 'Product categories', 'heading' => 'Product categories'])

@php
    $catalogModalOpen = $categories->isNotEmpty() && $errors->any() && ! $errors->has('category');
    $clearUrl = route('product.categories.index');
@endphp

@section('content')
@include('product::partials.catalog-hub-styles')

<style>
.pcat-search-bar{display:flex;flex-wrap:wrap;align-items:center;gap:7px;margin-bottom:12px;}
.pcat-search-input-wrap{position:relative;flex:1 1 200px;min-width:160px;}
.pcat-search-icon{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:13px;pointer-events:none;}
.pcat-search-input{width:100%;box-sizing:border-box;padding:8px 10px 8px 32px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);outline:none;transition:border-color .15s,box-shadow .15s;}
.pcat-search-input:focus{border-color:var(--primary);box-shadow:0 0 0 2px color-mix(in srgb,var(--primary) 18%,transparent);}
.pcat-filter-select{padding:8px 28px 8px 10px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2394a3b8' d='M2.5 4.5 6 8l3.5-3.5'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 8px center;outline:none;transition:border-color .15s;}
.pcat-filter-select:focus{border-color:var(--primary);}
.pcat-filter-btn{display:inline-flex;align-items:center;gap:5px;padding:8px 14px;font-size:13px;font-weight:700;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);cursor:pointer;transition:border-color .15s,background .15s;}
.pcat-filter-btn:hover{border-color:color-mix(in srgb,var(--primary) 55%,var(--border));background:color-mix(in srgb,var(--primary) 8%,transparent);}
.pcat-filter-clear{display:inline-flex;align-items:center;gap:5px;padding:8px 12px;font-size:13px;font-weight:600;border-radius:8px;border:1px solid color-mix(in srgb,#ef4444 35%,var(--border));background:transparent;color:#f97373;text-decoration:none;cursor:pointer;transition:border-color .15s,background .15s;}
.pcat-filter-clear:hover{background:color-mix(in srgb,#ef4444 8%,transparent);border-color:color-mix(in srgb,#ef4444 55%,var(--border));}
:is(html[data-theme="light"],html[data-theme="light_blue"]) .pcat-filter-clear{color:#dc2626;}
.pcat-results-table{width:100%;border-collapse:collapse;font-size:13px;min-width:540px;}
.pcat-results-table th{text-align:left;padding:9px 12px;background:color-mix(in srgb,var(--card) 92%,transparent);font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);border-bottom:1px solid var(--border);}
.pcat-results-table td{padding:10px 12px;border-bottom:1px solid color-mix(in srgb,var(--border) 80%,transparent);vertical-align:middle;}
.pcat-results-table tr:last-child td{border-bottom:none;}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('product::partials.product-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->has('category'))
        <div class="pcat-banner pcat-banner--err" role="alert">{{ $errors->first('category') }}</div>
    @endif

    <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
        Group products for <strong style="color:var(--text);">{{ $business->name }}</strong>. Use top-level categories and nested subcategories at any depth (e.g. Electronics → Phones → Cases).
    </p>

    {{-- Search & filter bar --}}
    @if($totalCount > 0 || $isFiltering)
    <form method="get" action="{{ route('product.categories.index') }}" class="pcat-search-bar" role="search">
        <div class="pcat-search-input-wrap">
            <i class="fa fa-magnifying-glass pcat-search-icon" aria-hidden="true"></i>
            <input type="search" name="q" value="{{ $search }}" placeholder="Search categories…"
                class="pcat-search-input" autocomplete="off" aria-label="Search categories">
        </div>
        <select name="status" class="pcat-filter-select" aria-label="Filter by status">
            <option value="">All status</option>
            <option value="active"   @selected($filterStatus === 'active')>Active</option>
            <option value="inactive" @selected($filterStatus === 'inactive')>Inactive</option>
        </select>
        <button type="submit" class="pcat-filter-btn"><i class="fa fa-filter" aria-hidden="true"></i> Filter</button>
        @if($isFiltering)
            <a href="{{ $clearUrl }}" class="pcat-filter-clear"><i class="fa fa-xmark" aria-hidden="true"></i> Clear</a>
        @endif
    </form>
    @endif

    <div class="pcat-toolbar">
        <span class="muted" style="margin:0;font-size:13px;">
            @if($totalCount === 0)
                Add your <strong style="color:var(--text);">first category</strong> below.
            @elseif($isFiltering)
                {{ $searchResults->total() }} result{{ $searchResults->total() === 1 ? '' : 's' }} of {{ $totalCount }} {{ $totalCount === 1 ? 'category' : 'categories' }}.
            @else
                {{ $totalCount }} {{ $totalCount === 1 ? 'category' : 'categories' }}. Drag cards between lists to reorder or change parent at any level.
            @endif
        </span>
        @if($totalCount > 0)
            <span id="pcat-reorder-status" class="pcat-reorder-status" hidden aria-live="polite"></span>
            <button type="button" id="pcat-modal-open" class="linkbtn" style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;"><i class="fa fa-plus"></i> Add category</button>
        @endif
    </div>

    @if($totalCount === 0)
        <section class="pcat-inline">
            <h2>Create category</h2>
            <p class="pcat-muted">Examples: Office supplies, Raw materials, Finished goods.</p>
            @if($errors->any())
                <div class="pcat-banner pcat-banner--err" style="margin-top:12px;" role="alert">{{ $errors->first() }}</div>
            @endif
            <form method="post" action="{{ route('product.categories.store') }}" class="pcat-form-grid pcat-form-grid--2" style="margin-top:14px;">
                @csrf
                @include('product::categories.partials.form-fields', ['showSortOrder' => false, 'parentOptions' => $parentOptions, 'presetParentId' => $presetParentId])
                <div style="grid-column:1/-1;display:flex;justify-content:flex-end;">
                    <button type="submit" class="linkbtn" style="padding:8px 16px;font-size:13px;">Save category</button>
                </div>
            </form>
        </section>
    @elseif($isFiltering)
        {{-- Flat search results table --}}
        @if($searchResults->isEmpty())
            <div style="padding:32px 16px;text-align:center;border:1px dashed var(--border);border-radius:10px;color:var(--muted);">
                <i class="fa fa-magnifying-glass" aria-hidden="true" style="font-size:22px;margin-bottom:10px;display:block;opacity:.45;"></i>
                <p style="margin:0 0 10px;font-size:14px;font-weight:600;">No categories match your filters.</p>
                <a href="{{ $clearUrl }}" class="pcat-filter-clear" style="font-size:13px;"><i class="fa fa-xmark"></i> Clear filters</a>
            </div>
        @else
            <div style="border:1px solid var(--border);border-radius:11px;overflow:auto;margin-bottom:14px;">
                <table class="pcat-results-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Parent</th>
                            <th>Products</th>
                            <th>Subcategories</th>
                            <th>Status</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($searchResults as $cat)
                        <tr>
                            <td><strong style="color:var(--text);">{{ $cat->name }}</strong></td>
                            <td>
                                @if($cat->parent)
                                    <span style="font-size:12px;color:var(--muted);">{{ $cat->parent->name }}</span>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td>{{ $cat->products_count }}</td>
                            <td>{{ $cat->children_count }}</td>
                            <td>
                                @if($cat->is_active)
                                    <span class="product-badge product-badge--on">Active</span>
                                @else
                                    <span class="product-badge product-badge--off">Inactive</span>
                                @endif
                            </td>
                            <td style="text-align:right;">
                                <a href="{{ route('product.categories.edit', $cat) }}" class="product-action-btn product-action-btn--edit" style="display:inline-flex;align-items:center;gap:5px;padding:6px 11px;font-size:11.5px;font-weight:700;border-radius:8px;border:1px solid var(--border);text-decoration:none;color:var(--text);background:color-mix(in srgb,var(--primary) 12%,transparent);border-color:color-mix(in srgb,var(--primary) 45%,var(--border));">
                                    <i class="fa fa-pen-to-square" aria-hidden="true"></i> Edit
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($searchResults->hasPages())
                <div style="margin-bottom:14px;">
                    {{ $searchResults->withQueryString()->links() }}
                </div>
            @endif
        @endif
    @else
        <div id="pcat-sort-top" class="pcat-list">
            @foreach($categoryTree as $root)
                @include('product::categories.partials.index-tree-node', ['row' => $root, 'depth' => 0])
            @endforeach
            <div class="pcat-block-group pcat-block-group--promote" data-category-id="">
                <div class="pcat-parent-slot pcat-parent-slot--promote">
                    <p class="pcat-drop-hint muted">Drop here to make a top-level category</p>
                </div>
            </div>
        </div>
    @endif

        <div id="pcat-modal" class="pcat-modal {{ $catalogModalOpen ? 'pcat-modal--open' : '' }}" role="dialog" aria-modal="true" aria-labelledby="pcat-modal-title" aria-hidden="{{ $catalogModalOpen ? 'false' : 'true' }}">
            <div class="pcat-modal__backdrop" data-pcat-close tabindex="-1"></div>
            <div class="pcat-modal__panel">
                <div class="pcat-modal__head">
                    <h2 id="pcat-modal-title">Add category</h2>
                    <button type="button" class="pcat-modal__close" data-pcat-close aria-label="Close">&times;</button>
                </div>
                <div class="pcat-modal__body">
                    @if($errors->any())
                        <div class="pcat-banner pcat-banner--err" style="margin-bottom:12px;">{{ $errors->first() }}</div>
                    @endif
                    <form method="post" action="{{ route('product.categories.store') }}" class="pcat-form-grid pcat-form-grid--2">
                        @csrf
                        @include('product::categories.partials.form-fields', ['fieldIdPrefix' => 'modal-cat', 'showSortOrder' => false, 'parentOptions' => $parentOptions, 'presetParentId' => $presetParentId])
                        <div style="grid-column:1/-1;display:flex;justify-content:flex-end;">
                            <button type="submit" class="linkbtn" style="padding:8px 16px;font-size:13px;">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>

<div style="margin-top:14px;">
    <a href="{{ route('product.index') }}" class="linkbtn" style="padding:7px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fa fa-arrow-left"></i> Products
    </a>
</div>

@if($categories->isNotEmpty() && ! $isFiltering)
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
(function () {
    var modal = document.getElementById('pcat-modal');
    var btn = document.getElementById('pcat-modal-open');
    var parentSelect = document.getElementById('modal-cat-parent');
    function lock(on) { document.documentElement.classList.toggle('pcat-modal-open-html', Boolean(on)); }
    function openM(parentId) {
        if (!modal) return;
        if (parentSelect) parentSelect.value = parentId ? String(parentId) : '';
        modal.classList.add('pcat-modal--open');
        modal.setAttribute('aria-hidden', 'false');
        lock(true);
        var i = document.getElementById('modal-cat-name');
        window.requestAnimationFrame(function () { if (i) i.focus(); });
    }
    function closeM() {
        if (!modal) return;
        modal.classList.remove('pcat-modal--open');
        modal.setAttribute('aria-hidden', 'true');
        lock(false);
        if (btn) btn.focus();
    }
    btn && btn.addEventListener('click', function () { openM(null); });
    document.querySelectorAll('.pcat-add-sub').forEach(function (subBtn) {
        subBtn.addEventListener('click', function () {
            openM(subBtn.getAttribute('data-parent-id'));
        });
    });
    modal && modal.querySelectorAll('[data-pcat-close]').forEach(function (el) { el.addEventListener('click', closeM); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal && modal.classList.contains('pcat-modal--open')) closeM();
    });
    if (modal && modal.classList.contains('pcat-modal--open')) lock(true);
})();

(function () {
    if (typeof Sortable === 'undefined') return;
    var statusEl = document.getElementById('pcat-reorder-status');
    var reorderUrl = @json(route('product.categories.reorder'));
    var topList = document.getElementById('pcat-sort-top');
    if (!topList) return;

    var cardGroup = { name: 'pcat-cards', pull: true, put: true };
    var cardSortables = [];

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            || document.querySelector('input[name="_token"]')?.value || '';
    }

    function setStatus(text, state) {
        if (!statusEl) return;
        statusEl.textContent = text || '';
        statusEl.hidden = !text;
        statusEl.classList.remove('is-saving', 'is-error');
        if (state) statusEl.classList.add(state);
    }

    function collectNodesFromSublist(subList) {
        var nodes = [];
        Array.from(subList.children).forEach(function (el) {
            var card = null;
            var branchRoot = el;
            if (el.classList.contains('pcat-tree-item')) {
                card = el.querySelector(':scope > .pcat-card');
            } else if (el.classList.contains('pcat-card')) {
                card = el;
            }
            if (!card) return;
            var node = { id: parseInt(card.getAttribute('data-category-id'), 10), children: [] };
            var nestedSub = branchRoot.querySelector(':scope > .pcat-sublist-wrap > .pcat-sublist');
            if (nestedSub) node.children = collectNodesFromSublist(nestedSub);
            nodes.push(node);
        });
        return nodes;
    }

    function collectTree() {
        var tree = [];
        topList.querySelectorAll(':scope > .pcat-block-group:not(.pcat-block-group--promote)').forEach(function (group) {
            var card = group.querySelector('.pcat-parent-slot .pcat-card');
            if (!card) return;
            var node = { id: parseInt(card.getAttribute('data-category-id'), 10), children: [] };
            var subList = group.querySelector(':scope > .pcat-sublist-wrap > .pcat-sublist');
            if (subList) node.children = collectNodesFromSublist(subList);
            tree.push(node);
        });
        return tree;
    }

    function nestingDepth(subList) {
        var depth = 0;
        var el = subList;
        while (el && el !== topList) {
            if (el.classList && el.classList.contains('pcat-sublist')) depth++;
            el = el.parentElement;
        }
        return Math.max(1, depth);
    }

    function ensureTreeItemForCard(card) {
        if (!card || card.closest('.pcat-tree-item') || card.closest('.pcat-parent-slot')) return;
        var subList = card.parentElement;
        if (!subList || !subList.classList.contains('pcat-sublist')) return;
        var item = document.createElement('div');
        item.className = 'pcat-tree-item';
        item.style.setProperty('--pcat-depth', String(nestingDepth(subList)));
        subList.insertBefore(item, card);
        item.appendChild(card);
        var wrap = document.createElement('div');
        wrap.className = 'pcat-sublist-wrap pcat-sublist-wrap--nested';
        var nested = document.createElement('div');
        nested.className = 'pcat-sublist';
        nested.setAttribute('data-parent-id', card.getAttribute('data-category-id') || '');
        wrap.appendChild(nested);
        item.appendChild(wrap);
        initCardSortable(nested);
    }

    function updateCardLevel(card) {
        var inSlot = card.closest('.pcat-parent-slot');
        var inSub = card.closest('.pcat-sublist');
        if (inSlot && !inSlot.classList.contains('pcat-parent-slot--promote')) {
            card.classList.add('pcat-card--parent');
            card.classList.remove('pcat-card--sub');
            card.setAttribute('data-parent-id', '');
            card.setAttribute('data-depth', '0');
        } else if (inSub) {
            card.classList.add('pcat-card--sub');
            card.classList.remove('pcat-card--parent');
            card.setAttribute('data-parent-id', inSub.getAttribute('data-parent-id') || '');
            var treeItem = card.closest('.pcat-tree-item');
            if (treeItem) treeItem.style.setProperty('--pcat-depth', String(nestingDepth(inSub)));
            card.setAttribute('data-depth', String(nestingDepth(inSub)));
        }
    }

    function ensureSublistWrap(group) {
        if (!group || group.classList.contains('pcat-block-group--promote')) return;
        var card = group.querySelector('.pcat-parent-slot .pcat-card');
        if (!card) return;
        var id = card.getAttribute('data-category-id');
        var name = card.querySelector('.pcat-card__title');
        group.setAttribute('data-category-id', id);
        var wrap = group.querySelector('.pcat-sublist-wrap');
        if (!wrap) {
            wrap = document.createElement('div');
            wrap.className = 'pcat-sublist-wrap';
            wrap.innerHTML = '<div class="pcat-sublist-head"><span class="pcat-sublist-head__label"><i class="fa fa-level-down-alt fa-rotate-90" aria-hidden="true"></i> Subcategories under <strong class="pcat-sublist-parent-name"></strong><span class="muted"> — drop here to make a subcategory</span></span></div><div class="pcat-sublist"></div>';
            group.appendChild(wrap);
            initCardSortable(wrap.querySelector('.pcat-sublist'));
        }
        var subList = wrap.querySelector('.pcat-sublist');
        if (subList) subList.setAttribute('data-parent-id', id);
        var nameEl = wrap.querySelector('.pcat-sublist-parent-name');
        if (nameEl && name) nameEl.textContent = name.textContent;
    }

    function normalizeDom() {
        topList.querySelectorAll('.pcat-parent-slot').forEach(function (slot) {
            if (slot.classList.contains('pcat-parent-slot--promote')) return;
            var cards = Array.from(slot.querySelectorAll('.pcat-card'));
            if (cards.length <= 1) return;
            var group = slot.closest('.pcat-block-group');
            var subList = group && group.querySelector('.pcat-sublist');
            cards.slice(1).forEach(function (extra) {
                if (subList) subList.appendChild(extra);
                else extra.remove();
            });
        });

        topList.querySelectorAll('.pcat-block-group:not(.pcat-block-group--promote)').forEach(function (group) {
            var slot = group.querySelector('.pcat-parent-slot');
            if (!slot || !slot.querySelector('.pcat-card')) {
                group.remove();
                return;
            }
            ensureSublistWrap(group);
        });

        topList.querySelectorAll('.pcat-sublist > .pcat-card').forEach(ensureTreeItemForCard);
        topList.querySelectorAll('.pcat-card').forEach(updateCardLevel);

        var promote = topList.querySelector('.pcat-block-group--promote');
        if (promote) {
            var promoteSlot = promote.querySelector('.pcat-parent-slot');
            var promoteCard = promoteSlot && promoteSlot.querySelector('.pcat-card');
            var hint = promoteSlot && promoteSlot.querySelector('.pcat-drop-hint');
            if (promoteCard) {
                if (hint) hint.remove();
                promote.classList.remove('pcat-block-group--promote');
                ensureSublistWrap(promote);
                appendPromoteTarget();
            } else if (hint) {
                hint.hidden = false;
            }
        }
    }

    function appendPromoteTarget() {
        if (topList.querySelector('.pcat-block-group--promote')) return;
        var group = document.createElement('div');
        group.className = 'pcat-block-group pcat-block-group--promote';
        group.setAttribute('data-category-id', '');
        group.innerHTML = '<div class="pcat-parent-slot pcat-parent-slot--promote"><p class="pcat-drop-hint muted">Drop here to make a top-level category</p></div>';
        topList.appendChild(group);
        initCardSortable(group.querySelector('.pcat-parent-slot'));
    }

    function saveTree() {
        normalizeDom();
        var tree = collectTree();
        if (!tree.length) return;
        setStatus('Saving…', 'is-saving');
        fetch(reorderUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ tree: tree }),
        })
            .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
            .then(function (r) {
                if (!r.ok) {
                    setStatus((r.data && r.data.error) || 'Could not save.', 'is-error');
                    window.setTimeout(function () { window.location.reload(); }, 1400);
                    return;
                }
                window.location.reload();
            })
            .catch(function () {
                setStatus('Could not save.', 'is-error');
                window.setTimeout(function () { window.location.reload(); }, 1400);
            });
    }

    function initCardSortable(el) {
        if (!el || el._pcatSortable) return;
        el._pcatSortable = Sortable.create(el, {
            group: cardGroup,
            animation: 150,
            ghostClass: 'pcat-sort-ghost',
            chosenClass: 'pcat-sort-chosen',
            draggable: el.classList.contains('pcat-sublist') ? '.pcat-tree-item' : '.pcat-card',
            handle: '.pcat-drag-handle',
            emptyInsertThreshold: 8,
            onMove: function (evt) {
                var toSub = evt.to.classList && evt.to.classList.contains('pcat-sublist');
                if (!toSub) return true;
                var dragged = evt.dragged;
                var card = dragged.classList.contains('pcat-card') ? dragged : dragged.querySelector('.pcat-card');
                if (!card) return true;
                var ownParentId = card.getAttribute('data-category-id');
                var targetParentId = evt.to.getAttribute('data-parent-id');
                if (ownParentId && targetParentId === ownParentId) return false;
                return true;
            },
            onAdd: function (evt) {
                var slot = evt.to;
                if (slot && slot.classList.contains('pcat-parent-slot') && !slot.classList.contains('pcat-parent-slot--promote')) {
                    var group = slot.closest('.pcat-block-group');
                    var subList = group && group.querySelector(':scope > .pcat-sublist-wrap > .pcat-sublist');
                    var item = evt.item;
                    if (item.classList.contains('pcat-tree-item')) {
                        var card = item.querySelector(':scope > .pcat-card');
                        var nestedWrap = item.querySelector(':scope > .pcat-sublist-wrap');
                        if (card) slot.appendChild(card);
                        if (nestedWrap && group) {
                            var existingWrap = group.querySelector(':scope > .pcat-sublist-wrap');
                            if (!existingWrap) {
                                group.appendChild(nestedWrap);
                            } else {
                                var targetSub = existingWrap.querySelector('.pcat-sublist');
                                var sourceSub = nestedWrap.querySelector('.pcat-sublist');
                                if (targetSub && sourceSub) {
                                    Array.from(sourceSub.children).forEach(function (ch) { targetSub.appendChild(ch); });
                                }
                                nestedWrap.remove();
                            }
                            var sub = group.querySelector(':scope > .pcat-sublist-wrap > .pcat-sublist');
                            if (sub) initCardSortable(sub);
                        }
                        item.remove();
                    } else {
                        slot.querySelectorAll('.pcat-card').forEach(function (other) {
                            if (other !== evt.item && subList) subList.appendChild(other);
                        });
                    }
                }
                normalizeDom();
            },
            onEnd: function () { saveTree(); },
        });
        cardSortables.push(el._pcatSortable);
    }

    Sortable.create(topList, {
        animation: 150,
        ghostClass: 'pcat-sort-ghost',
        chosenClass: 'pcat-sort-chosen',
        draggable: '.pcat-block-group:not(.pcat-block-group--promote)',
        handle: '.pcat-card--parent .pcat-drag-handle',
        filter: '.pcat-sublist-wrap',
        preventOnFilter: false,
        onEnd: function () { saveTree(); },
    });

    topList.querySelectorAll('.pcat-parent-slot, .pcat-sublist').forEach(initCardSortable);
    appendPromoteTarget();
})();
</script>
@endif
@endsection
