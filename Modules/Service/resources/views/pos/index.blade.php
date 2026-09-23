@extends('theme::layouts.app', ['title' => 'Service POS', 'heading' => 'Service POS'])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.svcpos-layout{display:grid;grid-template-columns:1fr 380px;gap:16px;align-items:start;}
@media(max-width:900px){.svcpos-layout{grid-template-columns:1fr;}}
.svcpos-cats{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px;}
.svcpos-cat{padding:4px 12px;border-radius:999px;font-size:12px;font-weight:600;border:1px solid var(--border);background:transparent;color:var(--muted);cursor:pointer;}
.svcpos-cat.is-active{background:var(--primary);color:#fff;border-color:var(--primary);}
.svcpos-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px;}
.svcpos-card{border:1px solid var(--border);border-radius:12px;padding:12px;cursor:pointer;background:color-mix(in srgb,var(--card) 97%,transparent);transition:border-color .12s,background .12s;text-align:left;}
.svcpos-card:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 5%,transparent);}
.svcpos-card__name{font-size:13px;font-weight:700;color:var(--text);margin-bottom:4px;}
.svcpos-card__meta{font-size:11px;color:var(--muted);}
.svcpos-card__price{font-size:14px;font-weight:800;color:var(--primary);margin-top:6px;}
.svcpos-cart{border:1px solid var(--border);border-radius:14px;padding:14px;position:sticky;top:12px;background:color-mix(in srgb,var(--card) 98%,transparent);}
.svcpos-cart h3{margin:0 0 10px;font-size:14px;font-weight:800;color:var(--text);}
.svcpos-line{display:flex;align-items:center;gap:8px;padding:8px 0;border-bottom:1px solid var(--border);}
.svcpos-line__name{flex:1;min-width:0;font-size:12px;font-weight:700;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.svcpos-line input[type="number"]{width:60px;text-align:right;padding:4px 6px;font-size:12px;}
.svcpos-line__total{width:70px;text-align:right;font-size:12px;font-weight:700;color:var(--text);}
.svcpos-line__del{background:none;border:none;color:var(--muted);cursor:pointer;font-size:15px;padding:0 2px;}
.svcpos-empty{padding:20px 0;text-align:center;color:var(--muted);font-size:12px;}
.svcpos-total{display:flex;justify-content:space-between;padding:10px 0;font-size:15px;font-weight:800;color:var(--text);border-top:2px solid var(--border);margin-top:6px;}
.svcpos-receipt{border:1px solid color-mix(in srgb,#22c55e 40%,var(--border));background:color-mix(in srgb,#22c55e 8%,transparent);border-radius:12px;padding:14px 16px;margin-bottom:16px;}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('service::partials.service-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->has('pos'))
        <div class="pcat-banner pcat-banner--err" role="alert">{{ $errors->first('pos') }}</div>
    @endif

    @if($receipt)
        <div class="svcpos-receipt">
            <p style="margin:0 0 6px;font-size:13px;font-weight:800;color:var(--text);">
                <i class="fa fa-receipt" style="margin-right:6px;"></i>
                Billed {{ implode(', ', $receipt['request_numbers']) }}
            </p>
            <p class="muted" style="margin:0 0 8px;font-size:12px;">
                {{ \Illuminate\Support\Carbon::parse($receipt['sold_at'])->format('M j, Y g:ia') }}
                &middot; {{ $receipt['payment_method_label'] }}
            </p>
            <ul style="margin:0 0 8px;padding-left:18px;font-size:12px;color:var(--text);">
                @foreach($receipt['items'] as $ri)
                    <li>{{ $ri['name'] }} &times; {{ $ri['qty'] }} — {{ number_format($ri['total'], 2) }}</li>
                @endforeach
            </ul>
            <p style="margin:0;font-size:14px;font-weight:800;color:var(--text);">
                Total{{ $currency ? ' ('.$currency.')' : '' }}: {{ number_format($receipt['total'], 2) }}
            </p>
        </div>
    @endif

    <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
        Bill a walk-in service job for <strong style="color:var(--text);">{{ $business->name }}</strong>.
        Add services to the cart, then check out to create the service request(s) and deduct any linked stock.
    </p>

    <div class="svcpos-layout">
        <div>
            <input type="text" id="svcpos-search" placeholder="Search services…"
                   style="width:100%;padding:8px 12px;border-radius:8px;border:1px solid var(--border);font-size:13px;background:var(--card);color:var(--text);margin-bottom:10px;">

            <div class="svcpos-cats">
                <button type="button" class="svcpos-cat is-active" data-svcpos-cat="">All</button>
                @foreach($categories as $cat)
                    <button type="button" class="svcpos-cat" data-svcpos-cat="{{ $cat->id }}">{{ $cat->name }}</button>
                @endforeach
            </div>

            <div class="svcpos-grid" id="svcpos-grid">
                @foreach($services as $svc)
                    <button type="button" class="svcpos-card"
                            data-svcpos-name="{{ strtolower($svc->name) }}"
                            data-svcpos-cats="{{ $svc->categories->pluck('id')->implode(',') }}"
                            data-svcpos-add
                            data-svcpos-id="{{ $svc->id }}"
                            data-svcpos-item-name="{{ $svc->name }}"
                            data-svcpos-price="{{ number_format((float) $svc->price, 2, '.', '') }}">
                        <div class="svcpos-card__name">{{ $svc->name }}</div>
                        <div class="svcpos-card__meta">{{ $svc->durationLabel() ?: '—' }}</div>
                        <div class="svcpos-card__price">{{ number_format((float) $svc->price, 2) }}{{ $currency ? ' '.$currency : '' }}</div>
                    </button>
                @endforeach
            </div>
            @if($services->isEmpty())
                <p class="muted" style="font-size:13px;margin-top:20px;">
                    No active services yet. <a href="{{ route('service.catalog.index') }}" class="pcat-link">Add one to the catalog</a>.
                </p>
            @endif
        </div>

        <div class="svcpos-cart">
            <h3><i class="fa fa-cart-shopping" style="margin-right:6px;opacity:.7;"></i> Cart</h3>

            <div id="svcpos-lines"></div>
            <p class="svcpos-empty" id="svcpos-empty">No services added yet.</p>

            <div class="svcpos-total" id="svcpos-total-row" style="display:none;">
                <span>Total{{ $currency ? ' ('.$currency.')' : '' }}</span>
                <span id="svcpos-total">0.00</span>
            </div>

            <form method="POST" action="{{ route('service.pos.checkout') }}" id="svcpos-form" style="margin-top:14px;">
                @csrf
                <div id="svcpos-hidden-items"></div>

                <div class="pcat-field">
                    <label for="svcpos-customer">Customer</label>
                    <select id="svcpos-customer" name="customer_id">
                        <option value="">— Walk-in —</option>
                        @foreach($customers as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}{{ $c->phone ? ' · '.$c->phone : '' }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="pcat-field">
                    <label for="svcpos-scheduled">Scheduled for</label>
                    <input id="svcpos-scheduled" type="datetime-local" name="scheduled_at">
                </div>

                <div class="pcat-field">
                    <label for="svcpos-payment">Payment method <span style="color:#ef4444;">*</span></label>
                    <select id="svcpos-payment" name="payment_method" required>
                        <option value="cash">Cash</option>
                        <option value="card">Card</option>
                        <option value="credit">Credit</option>
                    </select>
                </div>

                <div class="pcat-field">
                    <label for="svcpos-notes">Notes</label>
                    <textarea id="svcpos-notes" name="notes" maxlength="2000" placeholder="Job notes…"></textarea>
                </div>

                <button type="submit" class="linkbtn" id="svcpos-checkout-btn" disabled
                        style="width:100%;padding:10px;font-size:13px;justify-content:center;display:flex;align-items:center;gap:6px;">
                    <i class="fa fa-check"></i> Checkout
                </button>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var cart = [];

    var grid       = document.getElementById('svcpos-grid');
    var search      = document.getElementById('svcpos-search');
    var cats        = document.querySelectorAll('[data-svcpos-cat]');
    var linesEl     = document.getElementById('svcpos-lines');
    var emptyEl     = document.getElementById('svcpos-empty');
    var totalRow    = document.getElementById('svcpos-total-row');
    var totalEl     = document.getElementById('svcpos-total');
    var hiddenItems = document.getElementById('svcpos-hidden-items');
    var checkoutBtn = document.getElementById('svcpos-checkout-btn');
    var form        = document.getElementById('svcpos-form');

    function fmt(n) { return (Math.round(n * 100) / 100).toFixed(2); }

    function addToCart(id, name, price) {
        var existing = cart.find(function (l) { return l.id === id; });
        if (existing) {
            existing.qty += 1;
        } else {
            cart.push({ id: id, name: name, price: price, qty: 1 });
        }
        render();
    }

    function removeFromCart(id) {
        cart = cart.filter(function (l) { return l.id !== id; });
        render();
    }

    function render() {
        linesEl.innerHTML = '';
        hiddenItems.innerHTML = '';

        if (cart.length === 0) {
            emptyEl.style.display = 'block';
            totalRow.style.display = 'none';
            checkoutBtn.disabled = true;
            return;
        }
        emptyEl.style.display = 'none';
        totalRow.style.display = 'flex';
        checkoutBtn.disabled = false;

        var total = 0;
        cart.forEach(function (line, idx) {
            var lineTotal = line.qty * line.price;
            total += lineTotal;

            var row = document.createElement('div');
            row.className = 'svcpos-line';
            row.innerHTML =
                '<span class="svcpos-line__name" title="' + line.name.replace(/"/g,'&quot;') + '">' + line.name + '</span>' +
                '<input type="number" min="1" step="1" value="' + line.qty + '" data-qty>' +
                '<input type="number" min="0" step="0.01" value="' + fmt(line.price) + '" data-price>' +
                '<span class="svcpos-line__total">' + fmt(lineTotal) + '</span>' +
                '<button type="button" class="svcpos-line__del" data-del>&times;</button>';

            row.querySelector('[data-qty]').addEventListener('input', function (e) {
                line.qty = Math.max(1, parseFloat(e.target.value) || 1);
                render();
            });
            row.querySelector('[data-price]').addEventListener('input', function (e) {
                line.price = Math.max(0, parseFloat(e.target.value) || 0);
                render();
            });
            row.querySelector('[data-del]').addEventListener('click', function () {
                removeFromCart(line.id);
            });

            linesEl.appendChild(row);

            hiddenItems.insertAdjacentHTML('beforeend',
                '<input type="hidden" name="items[' + idx + '][service_item_id]" value="' + line.id + '">' +
                '<input type="hidden" name="items[' + idx + '][qty]" value="' + line.qty + '">' +
                '<input type="hidden" name="items[' + idx + '][price]" value="' + line.price + '">'
            );
        });

        totalEl.textContent = fmt(total);
    }

    grid && grid.querySelectorAll('[data-svcpos-add]').forEach(function (card) {
        card.addEventListener('click', function () {
            addToCart(card.dataset.svcposId, card.dataset.svcposItemName, parseFloat(card.dataset.svcposPrice) || 0);
        });
    });

    function applyFilters() {
        var q       = (search.value || '').trim().toLowerCase();
        var activeCat = document.querySelector('.svcpos-cat.is-active');
        var catId   = activeCat ? activeCat.getAttribute('data-svcpos-cat') : '';

        grid && grid.querySelectorAll('.svcpos-card').forEach(function (card) {
            var matchesQ   = !q || card.dataset.svcposName.indexOf(q) !== -1;
            var cardCats   = (card.dataset.svcposCats || '').split(',');
            var matchesCat = !catId || cardCats.indexOf(catId) !== -1;
            card.style.display = (matchesQ && matchesCat) ? '' : 'none';
        });
    }

    search && search.addEventListener('input', applyFilters);
    cats.forEach(function (btn) {
        btn.addEventListener('click', function () {
            cats.forEach(function (b) { b.classList.remove('is-active'); });
            btn.classList.add('is-active');
            applyFilters();
        });
    });

    form && form.addEventListener('submit', function (e) {
        if (cart.length === 0) e.preventDefault();
    });

    render();
})();
</script>
@endsection
