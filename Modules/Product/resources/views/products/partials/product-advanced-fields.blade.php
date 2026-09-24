@php
    $pfxRaw = isset($fieldIdPrefix) ? (string) $fieldIdPrefix : '';
    $rootId = $pfxRaw !== '' ? $pfxRaw . '-advanced' : 'product-advanced';
    $productModel = $product ?? null;
    $modalField = $pfxRaw === 'modal';
    $currencyLabel = filled($currency ?? null) ? (string) $currency : '';

    $val = fn (string $key, $default = null) => old($key, $productModel?->{$key} ?? $default);
    $checked = fn (string $key) => (bool) old($key, $productModel?->{$key} ?? false);

    $tagsValue = old('tags', $productModel?->tags ? implode(', ', $productModel->tags) : '');

    $deliveryPartners = collect($deliveryPartners ?? []);
    $rawOldDelivery = old('delivery_methods');
    if (is_array($rawOldDelivery)) {
        $deliverySelections = collect($rawOldDelivery)->mapWithKeys(fn ($row, $key) => [
            (string) $key => [
                'selected' => is_array($row) && !empty($row['selected']),
                'price' => is_array($row) ? ($row['price'] ?? null) : null,
            ],
        ]);
    } else {
        $deliverySelections = collect($productModel?->delivery_methods ?? [])
            ->filter(fn ($m) => is_array($m) && !empty($m['key']))
            ->mapWithKeys(fn ($m) => [(string) $m['key'] => ['selected' => true, 'price' => $m['price'] ?? null]]);
    }

    $id = fn (string $suffix) => $rootId . '-' . $suffix;
@endphp

<div class="product-field product-advanced-field" style="grid-column:1/-1;" id="{{ $rootId }}" data-product-advanced-root @if($modalField) data-product-advanced-modal-field hidden @endif>

    {{-- Basic — extended details --}}
    <section class="product-adv-card">
        <header class="product-adv-card__head">
            <h3 class="product-adv-card__title"><i class="fa fa-circle-info" aria-hidden="true"></i> More details</h3>
        </header>
        <div class="product-adv-card__body product-adv-details-grid">
            <div class="product-field">
                <label for="{{ $id('model-no') }}">Model no.</label>
                <input id="{{ $id('model-no') }}" type="text" name="model_no" value="{{ $val('model_no') }}" maxlength="120" placeholder="e.g. MX-200">
            </div>
            <div class="product-field">
                <label for="{{ $id('size') }}">Size</label>
                <input id="{{ $id('size') }}" type="text" name="size" value="{{ $val('size') }}" maxlength="120" placeholder="e.g. M, 42, 500ml">
            </div>
            <div class="product-field">
                <label for="{{ $id('mfg-date') }}">Manufacture date</label>
                <input id="{{ $id('mfg-date') }}" type="date" name="mfg_date" value="{{ $val('mfg_date') instanceof \Carbon\Carbon ? $val('mfg_date')->format('Y-m-d') : $val('mfg_date') }}">
            </div>
            <div class="product-field" style="grid-column:1/-1;">
                <label for="{{ $id('tags') }}">Tags</label>
                <input id="{{ $id('tags') }}" type="text" name="tags" value="{{ $tagsValue }}" maxlength="1000" placeholder="Comma separated, e.g. seasonal, clearance, new">
            </div>
        </div>
        @error('model_no')<div class="product-adv-card__err">{{ $message }}</div>@enderror
        @error('tags')<div class="product-adv-card__err">{{ $message }}</div>@enderror
    </section>

    {{-- Advanced options --}}
    <section class="product-adv-card">
        <header class="product-adv-card__head">
            <h3 class="product-adv-card__title"><i class="fa fa-sliders" aria-hidden="true"></i> Advanced options</h3>
        </header>
        <div class="product-adv-card__body product-adv-grid">

            <div class="product-adv-toggle-tile">
                <label class="product-adv-toggle-tile__head">
                    <span class="product-adv-toggle-tile__text"><strong>Warranty</strong><small>Product includes a seller or manufacturer warranty</small></span>
                    <span class="product-switch">
                        <input type="checkbox" name="has_warranty" value="1" data-adv-toggle data-adv-panel="{{ $id('warranty-panel') }}" @checked($checked('has_warranty'))>
                        <span class="product-switch-slider" aria-hidden="true"></span>
                    </span>
                </label>
                <div id="{{ $id('warranty-panel') }}" class="product-adv-toggle-tile__panel" @unless($checked('has_warranty')) hidden @endunless>
                    <label for="{{ $id('warranty-duration') }}">Duration</label>
                    <input id="{{ $id('warranty-duration') }}" type="text" name="warranty_duration" value="{{ $val('warranty_duration') }}" maxlength="60" placeholder="e.g. 30 days, 1 Year, Lifetime">
                </div>
            </div>

            <div class="product-adv-toggle-tile">
                <label class="product-adv-toggle-tile__head">
                    <span class="product-adv-toggle-tile__text"><strong>Expiration</strong><small>Track and record this product's expiration date</small></span>
                    <span class="product-switch">
                        <input type="checkbox" name="track_expiry" value="1" data-adv-toggle data-adv-panel="{{ $id('expiry-panel') }}" @checked($checked('track_expiry'))>
                        <span class="product-switch-slider" aria-hidden="true"></span>
                    </span>
                </label>
                <div id="{{ $id('expiry-panel') }}" class="product-adv-toggle-tile__panel" @unless($checked('track_expiry')) hidden @endunless>
                    <label for="{{ $id('exp-date') }}">Expiration date</label>
                    <input id="{{ $id('exp-date') }}" type="date" name="exp_date" value="{{ $val('exp_date') instanceof \Carbon\Carbon ? $val('exp_date')->format('Y-m-d') : $val('exp_date') }}">
                </div>
            </div>

            <div class="product-adv-toggle-tile product-adv-toggle-tile--simple">
                <label class="product-adv-toggle-tile__head">
                    <span class="product-adv-toggle-tile__text"><strong>Loyalty redeemable</strong><small>Customers can redeem loyalty points for this product</small></span>
                    <span class="product-switch">
                        <input type="checkbox" name="loyalty_redeemable" value="1" @checked($checked('loyalty_redeemable'))>
                        <span class="product-switch-slider" aria-hidden="true"></span>
                    </span>
                </label>
            </div>

            <div class="product-adv-toggle-tile product-adv-toggle-tile--simple">
                <label class="product-adv-toggle-tile__head">
                    <span class="product-adv-toggle-tile__text"><strong>Customer required</strong><small>A customer must be selected before selling</small></span>
                    <span class="product-switch">
                        <input type="checkbox" name="is_customer_required" value="1" @checked($checked('is_customer_required'))>
                        <span class="product-switch-slider" aria-hidden="true"></span>
                    </span>
                </label>
            </div>

            <div class="product-adv-toggle-tile">
                <label class="product-adv-toggle-tile__head">
                    <span class="product-adv-toggle-tile__text"><strong>Rental</strong><small>This product can be rented instead of sold</small></span>
                    <span class="product-switch">
                        <input type="checkbox" name="is_rental" value="1" data-adv-toggle data-adv-panel="{{ $id('rental-panel') }}" @checked($checked('is_rental'))>
                        <span class="product-switch-slider" aria-hidden="true"></span>
                    </span>
                </label>
                <div id="{{ $id('rental-panel') }}" class="product-adv-toggle-tile__panel product-adv-toggle-tile__panel--grid" @unless($checked('is_rental')) hidden @endunless>
                    <div class="product-field">
                        <label for="{{ $id('rental-daily-rate') }}">Daily rate @if($currencyLabel)({{ $currencyLabel }})@endif</label>
                        <input id="{{ $id('rental-daily-rate') }}" type="number" name="rental_daily_rate" value="{{ $val('rental_daily_rate') }}" step="0.01" min="0" inputmode="decimal">
                    </div>
                    <div class="product-field">
                        <label for="{{ $id('rental-max-days') }}">Max rental days</label>
                        <input id="{{ $id('rental-max-days') }}" type="number" name="rental_max_days" value="{{ $val('rental_max_days') }}" step="1" min="0" inputmode="numeric">
                    </div>
                    <div class="product-field">
                        <label for="{{ $id('rental-late-fee') }}">Late fee multiplier</label>
                        <input id="{{ $id('rental-late-fee') }}" type="number" name="rental_late_fee_multiplier" value="{{ $val('rental_late_fee_multiplier') }}" step="0.01" min="0" inputmode="decimal">
                    </div>
                    <label class="product-adv-inline-check">
                        <span class="product-switch product-switch--sm">
                            <input type="checkbox" name="rental_needs_cleaning" value="1" @checked($checked('rental_needs_cleaning'))>
                            <span class="product-switch-slider" aria-hidden="true"></span>
                        </span>
                        <span>Needs cleaning between rentals</span>
                    </label>
                </div>
            </div>

            <div class="product-adv-toggle-tile">
                <label class="product-adv-toggle-tile__head">
                    <span class="product-adv-toggle-tile__text"><strong>Subscription</strong><small>Sold as a recurring subscription product</small></span>
                    <span class="product-switch">
                        <input type="checkbox" name="is_subscription" value="1" data-adv-toggle data-adv-panel="{{ $id('subscription-panel') }}" @checked($checked('is_subscription'))>
                        <span class="product-switch-slider" aria-hidden="true"></span>
                    </span>
                </label>
                <div id="{{ $id('subscription-panel') }}" class="product-adv-toggle-tile__panel product-adv-toggle-tile__panel--grid" @unless($checked('is_subscription')) hidden @endunless>
                    <div class="product-field">
                        <label for="{{ $id('subscription-period') }}">Recurring period</label>
                        <select id="{{ $id('subscription-period') }}" name="subscription_recurring_period">
                            @foreach(['weekly' => 'Weekly', 'monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'yearly' => 'Yearly'] as $periodValue => $periodLabel)
                                <option value="{{ $periodValue }}" @selected($val('subscription_recurring_period') === $periodValue)>{{ $periodLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <label class="product-adv-inline-check">
                        <span class="product-switch product-switch--sm">
                            <input type="checkbox" name="subscription_free_trial" value="1" @checked($checked('subscription_free_trial'))>
                            <span class="product-switch-slider" aria-hidden="true"></span>
                        </span>
                        <span>Offer a free trial</span>
                    </label>
                </div>
            </div>

            <div class="product-adv-toggle-tile">
                <label class="product-adv-toggle-tile__head">
                    <span class="product-adv-toggle-tile__text"><strong>Dynamic pricing</strong><small>Cashier enters the price at POS (e.g. mobile reload)</small></span>
                    <span class="product-switch">
                        <input type="checkbox" name="is_dynamic_pricing" value="1" data-adv-toggle data-adv-panel="{{ $id('dynamic-panel') }}" @checked($checked('is_dynamic_pricing'))>
                        <span class="product-switch-slider" aria-hidden="true"></span>
                    </span>
                </label>
                <div id="{{ $id('dynamic-panel') }}" class="product-adv-toggle-tile__panel" @unless($checked('is_dynamic_pricing')) hidden @endunless>
                    <label class="product-adv-inline-check">
                        <span class="product-switch product-switch--sm">
                            <input type="checkbox" name="dynamic_price_qty_linked" value="1" @checked($checked('dynamic_price_qty_linked'))>
                            <span class="product-switch-slider" aria-hidden="true"></span>
                        </span>
                        <span>Price and quantity are linked</span>
                    </label>
                </div>
            </div>

            <div class="product-adv-toggle-tile product-adv-toggle-tile--simple">
                <label class="product-adv-toggle-tile__head">
                    <span class="product-adv-toggle-tile__text"><strong>Item wise tax</strong><small>Tax is calculated per item, not on the order total</small></span>
                    <span class="product-switch">
                        <input type="checkbox" name="item_wise_tax" value="1" @checked($checked('item_wise_tax'))>
                        <span class="product-switch-slider" aria-hidden="true"></span>
                    </span>
                </label>
            </div>

            <div class="product-adv-toggle-tile product-adv-toggle-tile--simple">
                <label class="product-adv-toggle-tile__head">
                    <span class="product-adv-toggle-tile__text"><strong>Item wise discount</strong><small>Discount is applied to each item individually</small></span>
                    <span class="product-switch">
                        <input type="checkbox" name="item_wise_discount" value="1" @checked($checked('item_wise_discount'))>
                        <span class="product-switch-slider" aria-hidden="true"></span>
                    </span>
                </label>
            </div>

        </div>
    </section>

    {{-- Delivery --}}
    <section class="product-adv-card">
        <header class="product-adv-card__head">
            <h3 class="product-adv-card__title"><i class="fa fa-truck" aria-hidden="true"></i> Delivery</h3>
            <label class="product-adv-inline-check">
                <span class="product-switch">
                    <input type="checkbox" name="courier_delivery" value="1" data-adv-toggle data-adv-panel="{{ $id('delivery-panel') }}" @checked($checked('courier_delivery')) @disabled($deliveryPartners->isEmpty())>
                    <span class="product-switch-slider" aria-hidden="true"></span>
                </span>
                <span>Available for courier delivery</span>
            </label>
        </header>
        <div id="{{ $id('delivery-panel') }}" class="product-adv-card__body" @unless($checked('courier_delivery')) hidden @endunless>
            @if($deliveryPartners->isEmpty())
                <p class="product-adv-hint muted">No delivery partners are enabled for this business yet. Enable them under <strong>POS app → Settings → Delivery</strong> to offer courier delivery on products.</p>
            @else
                <p class="product-adv-hint muted">Pick which enabled delivery partners can deliver this product, with an optional price override per partner.</p>
                <ul class="product-adv-delivery-list">
                    @foreach($deliveryPartners as $partner)
                        @php $sel = $deliverySelections->get($partner['key']); @endphp
                        <li class="product-adv-delivery-row">
                            <label class="product-adv-delivery-row__check">
                                <span class="product-switch product-switch--sm">
                                    <input type="checkbox" name="delivery_methods[{{ $partner['key'] }}][selected]" value="1" @checked($sel['selected'] ?? false)>
                                    <span class="product-switch-slider" aria-hidden="true"></span>
                                </span>
                                <span>{{ $partner['label'] }}</span>
                            </label>
                            <input type="number" name="delivery_methods[{{ $partner['key'] }}][price]" value="{{ $sel['price'] ?? '' }}" step="0.01" min="0" inputmode="decimal" placeholder="Price (optional)">
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
        @error('delivery_methods')<div class="product-adv-card__err">{{ $message }}</div>@enderror
    </section>
</div>

@once
<style>
.product-adv-card{border:1px solid var(--border);border-radius:12px;background:color-mix(in srgb,var(--card) 98%,transparent);overflow:hidden;margin-bottom:12px;}
.product-adv-card:last-child{margin-bottom:0;}
.product-adv-card__head{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px 14px;padding:12px 14px;border-bottom:1px solid color-mix(in srgb,var(--border) 85%,transparent);background:color-mix(in srgb,var(--card) 94%,transparent);}
.product-adv-card__title{margin:0;font-size:14px;font-weight:800;color:var(--text);display:flex;align-items:center;gap:8px;letter-spacing:-.02em;}
.product-adv-card__title i{font-size:13px;color:var(--primary);opacity:.9;}
.product-adv-card__body{padding:12px 14px 14px;}
.product-adv-card__body[hidden]{display:none;}
.product-adv-card__err{margin:0;padding:0 14px 12px;font-size:12px;color:#f87171;}
.product-adv-details-grid{display:grid;gap:10px;grid-template-columns:1fr;}
@media (min-width:600px){.product-adv-details-grid{grid-template-columns:repeat(3,minmax(0,1fr));gap:12px 16px;}}
.product-adv-grid{display:grid;gap:10px;grid-template-columns:1fr;}
@media (min-width:640px){.product-adv-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;}}
.product-adv-toggle-tile{border:1px solid var(--border);border-radius:10px;background:color-mix(in srgb,var(--card) 94%,transparent);padding:10px 12px;}
.product-adv-toggle-tile__head{display:flex;align-items:center;justify-content:space-between;gap:12px;cursor:pointer;}
.product-adv-toggle-tile__text{display:block;min-width:0;}
.product-adv-toggle-tile__text strong{display:block;font-size:13px;font-weight:700;color:var(--text);}
.product-adv-toggle-tile__text small{display:block;font-size:11.5px;color:var(--muted);line-height:1.35;margin-top:2px;}
.product-adv-toggle-tile:has(> .product-adv-toggle-tile__head input:checked){border-color:color-mix(in srgb,var(--primary) 40%,var(--border));background:color-mix(in srgb,var(--primary) 8%,transparent);}
.product-adv-toggle-tile__panel{margin-top:10px;padding-top:10px;border-top:1px dashed color-mix(in srgb,var(--border) 80%,transparent);}
.product-adv-toggle-tile__panel[hidden]{display:none;}
.product-adv-toggle-tile__panel--grid{display:grid;gap:8px;grid-template-columns:1fr;}
@media (min-width:480px){.product-adv-toggle-tile__panel--grid{grid-template-columns:repeat(2,minmax(0,1fr));}}
.product-adv-inline-check{display:flex;align-items:center;gap:8px;font-size:12.5px;color:var(--text);cursor:pointer;grid-column:1/-1;}
.product-adv-hint{margin:0 0 10px;font-size:12px;line-height:1.4;}
.product-adv-delivery-list{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:6px;}
.product-adv-delivery-row{display:grid;grid-template-columns:1fr 150px;gap:8px;align-items:center;}
.product-adv-delivery-row input[type="number"]{padding:8px 10px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);}
.product-adv-delivery-row__check{display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:var(--text);cursor:pointer;}
.product-switch--sm{width:34px;height:18px;}
.product-switch--sm .product-switch-slider:before{height:14px;width:14px;left:2px;top:2px;}
.product-switch--sm input:checked+.product-switch-slider:before{transform:translateX(16px);}
</style>
<script>
(function () {
    if (window.__productAdvancedFieldInit) return;
    window.__productAdvancedFieldInit = true;

    document.addEventListener('change', function (e) {
        var toggle = e.target.closest('[data-adv-toggle]');
        if (!toggle) return;
        var panel = document.getElementById(toggle.getAttribute('data-adv-panel'));
        if (panel) panel.hidden = !toggle.checked;
    });
})();
</script>
@endonce
