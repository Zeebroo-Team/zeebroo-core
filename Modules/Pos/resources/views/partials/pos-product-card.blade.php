@php
    $outOfStock = (float) $product['stock_quantity'] <= 0;
    $posCardDiscount = $product['discount'] ?? null;
    $posCardDiscounted = $product['discounted_sell_price'] ?? null;
    $posCardEffectivePrice = $posCardDiscounted ?? $product['unit_sell_price'];
@endphp
<button
    type="button"
    class="pos-product @if($outOfStock) is-disabled @endif"
    data-pos-product
    data-product-id="{{ $product['id'] }}"
    data-product-name="{{ e($product['name']) }}"
    data-product-sku="{{ e($product['sku'] ?? '') }}"
    data-unit-price="{{ $posCardEffectivePrice !== null ? number_format((float) $posCardEffectivePrice, 2, '.', '') : '0' }}"
    data-stock="{{ $formatQty((float) $product['stock_quantity']) }}"
    data-product-layers='@json($product['layers'] ?? [])'
    data-selling-units='@json($product['selling_units'] ?? [])'
    data-is-rental="{{ !empty($product['is_rental']) ? '1' : '0' }}"
    data-rental-daily-rate="{{ $product['rental_daily_rate'] ?? '' }}"
    data-rental-max-days="{{ $product['rental_max_days'] ?? '' }}"
    data-is-dynamic-pricing="{{ !empty($product['is_dynamic_pricing']) ? '1' : '0' }}"
    data-dynamic-qty-linked="{{ !empty($product['dynamic_price_qty_linked']) ? '1' : '0' }}"
    data-is-subscription="{{ !empty($product['is_subscription']) ? '1' : '0' }}"
    @if($outOfStock) disabled @endif
>
    @if($product['image_url'])
        <img src="{{ $product['image_url'] }}" alt="" class="pos-product__img" loading="lazy">
    @else
        <div class="pos-product__placeholder"><i class="fa fa-box" aria-hidden="true"></i></div>
    @endif
    <span class="pos-product__name">{{ $product['name'] }}</span>
    <span class="pos-product__meta">
        @if(filled($product['sku'] ?? null)){{ $product['sku'] }} · @endif
        Stock {{ $formatQty((float) $product['stock_quantity']) }}
        @if(!empty($product['layer_count']) && (int) $product['layer_count'] > 1)
            · {{ (int) $product['layer_count'] }} batches
        @endif
    </span>
    <span class="pos-product__price">
        @if(!empty($product['has_multiple_prices']) && count($product['layers'] ?? []) > 1)
            @php
                $prices = collect($product['layers'])->pluck('unit_sell_price')->map(fn ($p) => (float) $p);
            @endphp
            {{ number_format($prices->min(), 2) }}–{{ number_format($prices->max(), 2) }}{{ filled($currency) ? ' '.$currency : '' }}
        @elseif($posCardDiscounted !== null)
            <span class="pos-product__price-was">{{ number_format((float) $product['unit_sell_price'], 2) }}</span>
            {{ number_format((float) $posCardDiscounted, 2) }}{{ filled($currency) ? ' '.$currency : '' }}
            @if($posCardDiscount)
                <span class="pos-product__discount-badge">
                    {{ $posCardDiscount['type'] === 'percentage' ? '-'.number_format($posCardDiscount['value'], 0).'%' : '-'.number_format($posCardDiscount['amount'], 2) }}
                </span>
            @endif
        @elseif($product['unit_sell_price'] !== null)
            {{ number_format((float) $product['unit_sell_price'], 2) }}{{ filled($currency) ? ' '.$currency : '' }}
        @else
            <span class="muted">No price</span>
        @endif
    </span>
    @if($posCardDiscount && !empty($posCardDiscount['campaign_id']) && filled($posCardDiscount['name'] ?? null))
        <span class="pos-product__campaign-tag">{{ $posCardDiscount['name'] }}</span>
    @endif
</button>
