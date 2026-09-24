@extends('theme::layouts.app', ['title' => 'New Stock Transfer', 'heading' => 'New Stock Transfer'])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.st-line-search-wrap{position:relative;margin-bottom:10px;}
.st-line-search{width:100%;box-sizing:border-box;padding:9px 10px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);}
.st-line-suggest{position:absolute;z-index:40;left:0;right:0;top:calc(100% + 4px);margin:0;padding:4px 0;list-style:none;max-height:200px;overflow:auto;border:1px solid var(--border);border-radius:10px;background:var(--card);box-shadow:0 12px 28px rgba(0,0,0,.22);}
.st-line-suggest[hidden]{display:none;}
.st-line-suggest button{display:block;width:100%;text-align:left;padding:8px 12px;border:none;background:transparent;font-size:13px;color:var(--text);cursor:pointer;}
.st-line-suggest button:hover,.st-line-suggest button:focus-visible{background:color-mix(in srgb,var(--primary) 10%,transparent);outline:none;}
.st-line-list{list-style:none;margin:0;padding:8px;display:flex;flex-direction:column;gap:6px;border:1px dashed color-mix(in srgb,var(--border) 80%,transparent);border-radius:9px;background:color-mix(in srgb,var(--card) 94%,transparent);min-height:44px;}
.st-line-list:empty::before{content:"No products added yet — search above to add.";display:block;padding:6px 4px;font-size:12px;color:var(--muted);text-align:center;}
.st-line-row{display:grid;grid-template-columns:1fr auto auto;gap:8px 10px;align-items:center;padding:8px 10px;border:1px solid var(--border);border-radius:8px;background:var(--card);}
.st-line-row__name{font-size:13px;font-weight:700;}
.st-line-row__sku{font-size:11px;}
.st-line-row__qty{display:flex;align-items:center;gap:6px;font-size:11px;}
.st-line-row__qty input{width:80px;padding:6px 8px;font-size:13px;border-radius:7px;border:1px solid var(--border);background:var(--card);color:var(--text);}
.st-line-row__remove{width:28px;height:28px;padding:0;border:1px solid var(--border);border-radius:7px;background:transparent;color:var(--muted);font-size:18px;line-height:1;cursor:pointer;}
.st-line-row__remove:hover{border-color:color-mix(in srgb,#f87171 45%,var(--border));color:#f87171;}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('pos::partials.pos-hub-nav')

    @if($errors->any())
        <div class="pcat-banner pcat-banner--err" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('pos.stock-transfers.store') }}" class="pcat-form-grid pcat-form-grid--2" id="st-form">
        @csrf

        <div class="pcat-field">
            <label for="st-from-branch">From branch <span style="color:#ef4444;">*</span></label>
            <select id="st-from-branch" name="from_branch_id" required>
                <option value="">— Select —</option>
                @foreach($branches as $branch)
                    <option value="{{ $branch->id }}" @selected((string) old('from_branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
            @error('from_branch_id')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
        </div>

        <div class="pcat-field">
            <label for="st-to-branch">To branch <span style="color:#ef4444;">*</span></label>
            <select id="st-to-branch" name="to_branch_id" required>
                <option value="">— Select —</option>
                @foreach($branches as $branch)
                    <option value="{{ $branch->id }}" @selected((string) old('to_branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
            @error('to_branch_id')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
        </div>

        <div class="pcat-field" style="grid-column:1/-1;">
            <label>Products to transfer <span style="color:#ef4444;">*</span></label>
            <div class="st-line-search-wrap">
                <input type="text" id="st-line-search" class="st-line-search" placeholder="Search products to add…" autocomplete="off">
                <ul id="st-line-suggest" class="st-line-suggest" hidden></ul>
            </div>
            <ul id="st-line-list" class="st-line-list" data-catalog='@json(collect($catalog)->values())'></ul>
            @error('lines')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
        </div>

        <div class="pcat-field" style="grid-column:1/-1;">
            <label for="st-notes">Notes (optional)</label>
            <textarea id="st-notes" name="notes" maxlength="2000" placeholder="Reason for transfer…">{{ old('notes') }}</textarea>
            @error('notes')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
        </div>

        <div style="grid-column:1/-1;display:flex;justify-content:flex-end;gap:8px;padding-top:8px;border-top:1px solid var(--border);">
            <a href="{{ route('pos.stock-transfers.index') }}"
               class="linkbtn" style="padding:8px 16px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">
                Cancel
            </a>
            <button type="submit" class="linkbtn" style="padding:8px 18px;font-size:13px;">
                <i class="fa fa-truck-arrow-right"></i> Create transfer
            </button>
        </div>
    </form>
</div>

<script>
(function () {
    var form = document.getElementById('st-form');
    var list = document.getElementById('st-line-list');
    var search = document.getElementById('st-line-search');
    var suggest = document.getElementById('st-line-suggest');
    var catalog = [];
    try { catalog = JSON.parse(list.getAttribute('data-catalog') || '[]'); } catch (e) { catalog = []; }

    function escapeHtml(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    }

    function existingIds() {
        return Array.from(list.querySelectorAll('[data-line-row]')).map(function (row) {
            return parseInt(row.getAttribute('data-product-id') || '0', 10);
        });
    }

    function reindexRows() {
        list.querySelectorAll('[data-line-row]').forEach(function (row, index) {
            var pid = row.querySelector('[data-line-product-id]');
            var qty = row.querySelector('[data-line-qty]');
            if (pid) pid.name = 'lines[' + index + '][product_id]';
            if (qty) qty.name = 'lines[' + index + '][quantity]';
        });
    }

    function addRow(product) {
        if (existingIds().indexOf(product.id) !== -1) return;
        var li = document.createElement('li');
        li.className = 'st-line-row';
        li.setAttribute('data-line-row', '');
        li.setAttribute('data-product-id', String(product.id));
        var skuHtml = product.sku ? '<span class="muted st-line-row__sku"> (' + escapeHtml(product.sku) + ')</span>' : '';
        li.innerHTML =
            '<div><span class="st-line-row__name">' + escapeHtml(product.name) + '</span>' + skuHtml + '</div>' +
            '<label class="st-line-row__qty"><span class="muted">Qty</span><input type="number" value="1" min="0.001" step="any" inputmode="decimal" data-line-qty></label>' +
            '<input type="hidden" value="' + product.id + '" data-line-product-id>' +
            '<button type="button" class="st-line-row__remove" data-line-remove aria-label="Remove">&times;</button>';
        list.appendChild(li);
        reindexRows();
    }

    function renderSuggest(query) {
        var q = String(query || '').trim().toLowerCase();
        if (!q) { suggest.hidden = true; suggest.innerHTML = ''; return; }
        var used = existingIds();
        var matches = catalog.filter(function (p) {
            if (used.indexOf(p.id) !== -1) return false;
            var hay = (p.name + ' ' + (p.sku || '')).toLowerCase();
            return hay.indexOf(q) !== -1;
        }).slice(0, 12);
        if (!matches.length) { suggest.hidden = true; suggest.innerHTML = ''; return; }
        suggest.innerHTML = matches.map(function (p) {
            var sub = p.sku ? ' <span class="muted">(' + escapeHtml(p.sku) + ')</span>' : '';
            return '<li><button type="button" data-pick-id="' + p.id + '">' + escapeHtml(p.name) + sub + '</button></li>';
        }).join('');
        suggest.hidden = false;
    }

    search.addEventListener('input', function () { renderSuggest(search.value); });
    search.addEventListener('focus', function () { renderSuggest(search.value); });
    suggest.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-pick-id]');
        if (!btn) return;
        var id = parseInt(btn.getAttribute('data-pick-id'), 10);
        var product = catalog.find(function (p) { return p.id === id; });
        if (product) {
            addRow(product);
            search.value = '';
            renderSuggest('');
        }
    });
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.st-line-search-wrap')) suggest.hidden = true;
    });
    list.addEventListener('click', function (e) {
        if (e.target.closest('[data-line-remove]')) {
            e.target.closest('[data-line-row]')?.remove();
            reindexRows();
        }
    });

    form.addEventListener('submit', function (e) {
        if (!list.querySelector('[data-line-row]')) {
            e.preventDefault();
            alert('Add at least one product to transfer.');
        }
    });
})();
</script>
@endsection
