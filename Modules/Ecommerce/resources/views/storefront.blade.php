@php
    $currency = $store->currency ?: '';
    $title    = $store->seo_title ?: $store->name;
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }}</title>
@if($store->seo_description)<meta name="description" content="{{ $store->seo_description }}">@endif
<meta property="og:title" content="{{ $title }}">
@if($store->banner_url)<meta property="og:image" content="{{ $store->banner_url }}">@endif
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
@include('ecommerce::partials.styles')
</head>
<body class="tpl-{{ $template }}">

@if(! $store->is_published)
    <div class="sf-main" style="text-align:center;padding-top:120px">
        @if($store->logo_url)<img src="{{ $store->logo_url }}" alt="" style="width:72px;height:72px;border-radius:16px;object-fit:cover">@endif
        <h1 style="margin:14px 0 6px">{{ $store->name }}</h1>
        <p style="color:var(--muted)">Our online store is opening soon. Please check back later.</p>
    </div>
@else
    @if($store->announcement)<div class="sf-announce">{{ $store->announcement }}</div>@endif

    <header class="sf-head">
        <div class="sf-head-in">
            <a class="sf-brand" href="{{ $store->publicUrl() }}">
                @if($store->logo_url)<img src="{{ $store->logo_url }}" alt="">@endif
                <span>{{ $store->name }}</span>
            </a>
            <div class="sf-search">
                <i class="fa fa-magnifying-glass"></i>
                <input type="search" id="sf-q" placeholder="Search products…" autocomplete="off">
            </div>
            <button class="sf-cart-btn" id="sf-cart-open" type="button">
                <i class="fa fa-bag-shopping"></i><span class="lbl">Cart</span><span class="sf-cart-count" id="sf-count">0</span>
            </button>
        </div>
    </header>

    <section class="sf-hero">
        @if($slides->isNotEmpty())
            <div class="sf-slider" id="sf-slider" aria-roledescription="carousel">
                @foreach($slides as $i => $slide)
                    <div class="sf-slide{{ $i === 0 ? ' on' : '' }}" aria-hidden="{{ $i === 0 ? 'false' : 'true' }}">
                        <img src="{{ $slide->image_url }}" alt="{{ $slide->title }}" @if($i > 0) loading="lazy" @endif>
                        @if($slide->title || $slide->subtitle || $slide->button_text)
                            <div class="sf-slide-txt">
                                @if($slide->title)<h2>{{ $slide->title }}</h2>@endif
                                @if($slide->subtitle)<p>{{ $slide->subtitle }}</p>@endif
                                @if($slide->button_text)
                                    <a class="sf-slide-btn" href="{{ $slide->button_link ?: '#sf-grid' }}">{{ $slide->button_text }}</a>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
                @if($slides->count() > 1)
                    <button class="sf-arrow prev" type="button" aria-label="Previous slide"><i class="fa fa-chevron-left"></i></button>
                    <button class="sf-arrow next" type="button" aria-label="Next slide"><i class="fa fa-chevron-right"></i></button>
                    <div class="sf-dots">
                        @foreach($slides as $i => $slide)<button type="button" class="{{ $i === 0 ? 'on' : '' }}" aria-label="Slide {{ $i + 1 }}"></button>@endforeach
                    </div>
                @endif
            </div>
        @else
            <div class="sf-hero-in">
                @if($store->banner_url)<img src="{{ $store->banner_url }}" alt="">@endif
                <div class="sf-hero-txt">
                    <h1>{{ $store->name }}</h1>
                    @if($store->tagline)<p>{{ $store->tagline }}</p>@endif
                </div>
            </div>
        @endif
    </section>

    <main class="sf-main">
        <div class="sf-grid" id="sf-grid"></div>
        <div class="sf-empty" id="sf-empty" style="display:none">No products found.</div>

        @if($store->about || $store->contact_email || $store->contact_phone || $store->address)
            <section class="sf-about">
                <h2>About {{ $store->name }}</h2>
                @if($store->about)<p style="white-space:pre-line;margin:0 0 10px">{{ $store->about }}</p>@endif
                <div style="color:var(--muted);font-size:14px;display:flex;flex-wrap:wrap;gap:18px">
                    @if($store->contact_phone)<span><i class="fa fa-phone"></i> {{ $store->contact_phone }}</span>@endif
                    @if($store->contact_email)<span><i class="fa fa-envelope"></i> <a href="mailto:{{ $store->contact_email }}">{{ $store->contact_email }}</a></span>@endif
                    @if($store->address)<span><i class="fa fa-location-dot"></i> {{ $store->address }}</span>@endif
                </div>
            </section>
        @endif
    </main>

    <footer class="sf-foot">
        @foreach(($store->social_links ?? []) as $net => $link)
            @if($link && in_array($net, ['facebook', 'instagram', 'tiktok', 'youtube'], true))<a href="{{ $link }}" target="_blank" rel="noopener"><i class="fa-brands fa-{{ $net }}"></i></a>@endif
        @endforeach
        <div>&copy; {{ date('Y') }} {{ $store->name }}</div>
    </footer>

    <div class="sf-drawer-bg" id="sf-bg"></div>
    <aside class="sf-drawer" aria-label="Cart">
        <div class="sf-drawer-head"><span id="sf-drawer-title">Your cart</span><button class="sf-x" id="sf-close" type="button">&times;</button></div>
        <div class="sf-drawer-body" id="sf-drawer-body"></div>
    </aside>

    <script>
    (function () {
      'use strict';
      const PRODUCTS = @json($products);
      const CUR      = @json($currency);
      const SHIP     = {flat: {{ (float) $store->shipping_flat_rate }}, freeOver: @json($store->free_shipping_over !== null ? (float) $store->free_shipping_over : null)};
      const MIN      = @json($store->min_order_amount !== null ? (float) $store->min_order_amount : null);
      const PAY      = @json($payMethods);
      const BANK     = @json($store->bank_transfer_details);
      const CHECKOUT = @json(route('ecommerce.storefront.checkout', ['slug' => $store->slug]));
      const KEY      = 'sf-cart-' + @json($store->slug);
      const byId     = Object.fromEntries(PRODUCTS.map(p => [p.id, p]));
      const $ = s => document.querySelector(s);
      const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
      const money = n => (CUR ? CUR + ' ' : '') + (Number(n) || 0).toFixed(2);

      // ── Slider ──
      (function slider() {
        const root = $('#sf-slider');
        if (!root) return;
        const slides = [...root.querySelectorAll('.sf-slide')];
        const dots   = [...root.querySelectorAll('.sf-dots button')];
        if (slides.length < 2) return;
        let i = 0, timer;
        const go = n => {
          i = (n + slides.length) % slides.length;
          slides.forEach((s, k) => { s.classList.toggle('on', k === i); s.setAttribute('aria-hidden', k === i ? 'false' : 'true'); });
          dots.forEach((d, k) => d.classList.toggle('on', k === i));
        };
        const play = () => { clearInterval(timer); timer = setInterval(() => go(i + 1), 5000); };
        root.querySelector('.prev').addEventListener('click', () => { go(i - 1); play(); });
        root.querySelector('.next').addEventListener('click', () => { go(i + 1); play(); });
        dots.forEach((d, k) => d.addEventListener('click', () => { go(k); play(); }));
        root.addEventListener('mouseenter', () => clearInterval(timer));
        root.addEventListener('mouseleave', play);
        play();
      })();

      let cart = {};
      try { cart = JSON.parse(localStorage.getItem(KEY) || '{}') || {}; } catch (_) { cart = {}; }
      // Drop items that are no longer listed.
      Object.keys(cart).forEach(id => { if (!byId[id]) delete cart[id]; });
      const save = () => { try { localStorage.setItem(KEY, JSON.stringify(cart)); } catch (_) {} renderCount(); };

      function renderGrid() {
        const q = ($('#sf-q').value || '').trim().toLowerCase();
        const list = PRODUCTS.filter(p => !q || p.title.toLowerCase().includes(q) || (p.description || '').toLowerCase().includes(q));
        $('#sf-empty').style.display = list.length ? 'none' : '';
        $('#sf-grid').innerHTML = list.map(p => `
          <article class="sf-card">
            <div class="sf-card-img">
              ${p.image_url ? `<img src="${esc(p.image_url)}" alt="${esc(p.title)}" loading="lazy">` : '<i class="fa fa-image"></i>'}
              ${p.is_featured ? '<span class="sf-badge">Featured</span>' : ''}
            </div>
            <div class="sf-card-body">
              <div class="sf-card-title">${esc(p.title)}</div>
              <div class="sf-price">${money(p.price)}${p.compare_at_price && p.compare_at_price > p.price ? `<s>${money(p.compare_at_price)}</s>` : ''}</div>
              <button class="sf-add" data-add="${p.id}" ${p.in_stock ? '' : 'disabled'}>${p.in_stock ? '<i class="fa fa-plus"></i> Add to cart' : 'Out of stock'}</button>
            </div>
          </article>`).join('');
      }

      function renderCount() {
        $('#sf-count').textContent = Object.values(cart).reduce((a, b) => a + b, 0);
      }

      function totals() {
        const subtotal = Object.entries(cart).reduce((s, [id, q]) => s + byId[id].price * q, 0);
        const shipping = (SHIP.freeOver && subtotal >= SHIP.freeOver) ? 0 : SHIP.flat;
        return { subtotal, shipping, total: subtotal + shipping };
      }

      function renderCart() {
        $('#sf-drawer-title').textContent = 'Your cart';
        const ids = Object.keys(cart);
        if (!ids.length) { $('#sf-drawer-body').innerHTML = '<div class="sf-empty">Your cart is empty.</div>'; return; }
        const t = totals();
        const belowMin = MIN && t.subtotal < MIN;
        $('#sf-drawer-body').innerHTML = ids.map(id => `
          <div class="sf-line">
            <div class="sf-line-name">${esc(byId[id].title)}<div style="color:var(--muted);font-size:12px">${money(byId[id].price)}</div></div>
            <div class="sf-qty"><button data-dec="${id}">−</button><span>${cart[id]}</span><button data-inc="${id}">+</button></div>
            <strong style="font-size:13px;min-width:76px;text-align:right">${money(byId[id].price * cart[id])}</strong>
          </div>`).join('') + `
          <div style="margin-top:12px">
            <div class="sf-sum"><span>Subtotal</span><span>${money(t.subtotal)}</span></div>
            <div class="sf-sum"><span>Shipping</span><span>${t.shipping ? money(t.shipping) : 'Free'}</span></div>
            <div class="sf-sum total"><span>Total</span><span>${money(t.total)}</span></div>
            ${SHIP.freeOver && t.shipping ? `<div style="font-size:12px;color:var(--muted)">Free shipping on orders over ${money(SHIP.freeOver)}</div>` : ''}
          </div>
          ${PAY.length ? `
          <form class="sf-form" id="sf-form">
            <div><label>Full name *</label><input name="name" required maxlength="150"></div>
            <div><label>Phone *</label><input name="phone" required maxlength="40"></div>
            <div><label>Email</label><input name="email" type="email" maxlength="150"></div>
            <div><label>Delivery address *</label><textarea name="address" rows="2" required maxlength="500"></textarea></div>
            <div><label>City</label><input name="city" maxlength="120"></div>
            <div><label>Order notes</label><textarea name="notes" rows="2" maxlength="1000"></textarea></div>
            <div><label>Payment</label><div class="sf-pay">${PAY.map((p, k) => `<label><input type="radio" name="payment_method" value="${p.key}" ${k === 0 ? 'checked' : ''}> <i class="fa ${p.online ? 'fa-credit-card' : (p.key === 'cod' ? 'fa-money-bill-wave' : 'fa-building-columns')}"></i> ${esc(p.label)}</label>`).join('')}</div></div>
            <div class="sf-msg" id="sf-msg"></div>
            <button class="sf-primary" type="submit" ${belowMin ? 'disabled' : ''}>${belowMin ? 'Minimum order ' + money(MIN) : 'Place order · ' + money(t.total)}</button>
          </form>` : '<p class="sf-msg" style="display:block">Checkout is currently unavailable.</p>'}`;
      }

      function openCart() { renderCart(); document.body.classList.add('sf-open'); }
      function closeCart() { document.body.classList.remove('sf-open'); }

      // PayHere expects a regular browser form POST to its checkout page.
      function postForm(action, fields) {
        const f = document.createElement('form');
        f.method = 'POST'; f.action = action; f.style.display = 'none';
        Object.entries(fields).forEach(([k, v]) => { const i = document.createElement('input'); i.type = 'hidden'; i.name = k; i.value = v ?? ''; f.appendChild(i); });
        document.body.appendChild(f); f.submit();
      }

      async function submit(form) {
        const btn = form.querySelector('button[type=submit]');
        const msg = $('#sf-msg');
        const fd  = Object.fromEntries(new FormData(form).entries());
        fd.items  = Object.entries(cart).map(([id, q]) => ({ listing_id: Number(id), quantity: q }));
        btn.disabled = true; msg.style.display = 'none';
        try {
          const res  = await fetch(CHECKOUT, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify(fd) });
          const body = await res.json().catch(() => ({}));
          if (res.status !== 201) {
            const first = body.errors ? Object.values(body.errors)[0][0] : null;
            throw new Error(first || body.message || 'Could not place your order.');
          }
          cart = {}; save();
          const pay = body.data.payment;
          if (pay?.redirect_url) { btn.textContent = 'Redirecting to secure payment…'; location.href = pay.redirect_url; return; }
          if (pay?.form)         { btn.textContent = 'Redirecting to secure payment…'; postForm(pay.form.action, pay.form.fields); return; }
          $('#sf-drawer-title').textContent = 'Order placed';
          $('#sf-drawer-body').innerHTML = `<div class="sf-done"><i class="fa fa-circle-check"></i>
            <h3>Thank you!</h3><p>Your order <strong>${esc(body.data.order_no)}</strong> has been received.<br>Total: <strong>${money(body.data.total)}</strong></p>
            ${body.data.payment_method === 'bank_transfer' && BANK ? `<p style="font-size:13px;color:var(--muted)">Please transfer the total to:</p><div class="sf-bank">${esc(BANK)}</div><p style="font-size:13px;color:var(--muted)">Use your order number as the payment reference.</p>` : '<p style="color:var(--muted);font-size:14px">We will contact you to confirm delivery.</p>'}
            <a class="sf-primary" href="${esc(body.data.order_url)}">View order</a>
          </div>`;
        } catch (e) {
          msg.textContent = e.message; msg.style.display = 'block'; btn.disabled = false;
        }
      }

      document.addEventListener('click', e => {
        const add = e.target.closest('[data-add]');
        if (add) { const id = add.dataset.add; cart[id] = (cart[id] || 0) + 1; save(); add.innerHTML = '<i class="fa fa-check"></i> Added'; setTimeout(renderGrid, 900); return; }
        const inc = e.target.closest('[data-inc]');
        if (inc) { cart[inc.dataset.inc]++; save(); renderCart(); return; }
        const dec = e.target.closest('[data-dec]');
        if (dec) { const id = dec.dataset.dec; if (--cart[id] <= 0) delete cart[id]; save(); renderCart(); }
      });
      document.addEventListener('submit', e => { if (e.target.id === 'sf-form') { e.preventDefault(); submit(e.target); } });
      $('#sf-cart-open').addEventListener('click', openCart);
      $('#sf-close').addEventListener('click', closeCart);
      $('#sf-bg').addEventListener('click', closeCart);
      $('#sf-q').addEventListener('input', renderGrid);

      renderGrid(); renderCount();
    })();
    </script>
@endif
</body>
</html>
