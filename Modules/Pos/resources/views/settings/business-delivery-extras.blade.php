@php
    /** @var \Modules\Business\Models\Business $business */
    $deliveryMethods = $deliveryMethods ?? [];
    $deliveryPartners = [
        ['key' => 'dhl', 'icon' => 'fa-globe', 'name' => 'DHL Express', 'desc' => 'International express courier with time-definite delivery worldwide.'],
        ['key' => 'fedex', 'icon' => 'fa-box-open', 'name' => 'FedEx', 'desc' => 'Fast and reliable global shipping with real-time package tracking.'],
        ['key' => 'uber', 'icon' => 'fa-car-side', 'name' => 'Uber', 'desc' => 'On-demand local delivery via the Uber courier network.'],
        ['key' => 'pickme', 'icon' => 'fa-motorcycle', 'name' => 'PickMe', 'desc' => "Sri Lanka's leading ride-hailing platform with parcel delivery."],
        ['key' => 'koobiyo', 'icon' => 'fa-bicycle', 'name' => 'Koobiyo', 'desc' => 'Last-mile e-commerce delivery built for Sri Lanka businesses.'],
        ['key' => 'pronto', 'icon' => 'fa-truck-fast', 'name' => 'Pronto Lanka', 'desc' => 'Scheduled and same-day delivery across Sri Lanka.'],
    ];
@endphp

<div style="margin-top:18px;padding-top:16px;border-top:1px solid var(--border);">
    <h2 style="margin:0 0 8px;font-size:15px;font-weight:800;">{{ __('Active delivery partners') }}</h2>
    <p class="muted" style="margin:0 0 12px;font-size:13px;line-height:1.45;max-width:72ch;">
        {{ __('Pick which couriers customers can choose from at checkout. Turning "Enable Delivery Methods" off above hides delivery entirely.') }}
    </p>

    <form method="post" action="{{ route('pos.settings.save') }}" style="display:grid;gap:10px;">
        @csrf
        <input type="hidden" name="redirect" value="{{ route('settings.business') }}?tab=delivery">
        <input type="hidden" name="delivery_methods[]" value="">

        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:10px;">
            @foreach($deliveryPartners as $partner)
                @php $isDmChecked = in_array($partner['key'], $deliveryMethods, true); @endphp
                <div data-dm-card style="display:flex;flex-direction:column;gap:8px;padding:14px;border:1px solid var(--border);border-radius:10px;background:color-mix(in srgb,var(--card) 96%,var(--border) 4%);">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <span style="width:28px;height:28px;flex-shrink:0;display:flex;align-items:center;justify-content:center;border-radius:8px;background:color-mix(in srgb,var(--primary) 12%,transparent);color:var(--primary);font-size:12px;">
                            <i class="fa {{ $partner['icon'] }}" aria-hidden="true"></i>
                        </span>
                        <span style="font-size:13px;font-weight:700;">{{ $partner['name'] }}</span>
                    </div>
                    <p class="muted" style="margin:0;font-size:11px;line-height:1.45;flex:1;">{{ $partner['desc'] }}</p>
                    <div style="display:flex;align-items:center;justify-content:space-between;padding-top:8px;border-top:1px solid var(--border);">
                        <span class="muted" style="font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;" data-dm-status>{{ $isDmChecked ? __('Enabled') : __('Disabled') }}</span>
                        <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;">
                            <input type="checkbox" name="delivery_methods[]" value="{{ $partner['key'] }}" data-dm-toggle @checked($isDmChecked)>
                        </label>
                    </div>
                </div>
            @endforeach
        </div>

        <div style="display:flex;justify-content:flex-end;">
            <button type="submit" class="linkbtn" style="padding:7px 14px;font-size:13px;">{{ __('Save delivery partners') }}</button>
        </div>
    </form>
</div>

<script>
(function () {
    document.querySelectorAll('[data-dm-toggle]').forEach(function (cb) {
        cb.addEventListener('change', function () {
            var statusEl = cb.closest('[data-dm-card]')?.querySelector('[data-dm-status]');
            if (statusEl) statusEl.textContent = cb.checked ? @json(__('Enabled')) : @json(__('Disabled'));
        });
    });
})();
</script>
