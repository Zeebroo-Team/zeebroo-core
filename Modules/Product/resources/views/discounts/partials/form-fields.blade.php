@php $p = $idPfx ?? 'pd'; @endphp

<div class="pd-form-root">

    {{-- ═══════════════════════════════════════════════════════
         SECTION 1 — Product
    ═══════════════════════════════════════════════════════ --}}
    <div class="pd-section">
        <div class="pd-section__head">
            <span class="pd-section__icon"><i class="fa fa-box"></i></span>
            <div>
                <div class="pd-section__title">Product</div>
                <div class="pd-section__sub">Choose which product this discount applies to</div>
            </div>
        </div>
        <div class="pd-section__body">

            <div class="pcat-field">
                <label for="{{ $p }}-product">Product <span class="pd-req">*</span></label>
                <select id="{{ $p }}-product" name="product_id" required class="pd-product-select">
                    <option value="">— select a product —</option>
                    @foreach($products as $prod)
                        <option value="{{ $prod->id }}" @selected(old('product_id') == $prod->id)>
                            {{ $prod->name }}{{ $prod->sku ? ' (' . $prod->sku . ')' : '' }}
                        </option>
                    @endforeach
                </select>
                @error('product_id')<p class="pd-err">{{ $message }}</p>@enderror
            </div>

            {{-- Product info card — shown after selection --}}
            <div class="pd-info-card" style="display:none;">
                <div class="pd-info-card__grid">
                    <div class="pd-info-stat">
                        <span class="pd-info-stat__lbl">Product</span>
                        <span class="pd-info-stat__val pd-ic-name"></span>
                    </div>
                    <div class="pd-info-stat">
                        <span class="pd-info-stat__lbl">SKU</span>
                        <span class="pd-info-stat__val pd-ic-sku" style="font-family:monospace;font-size:12px;"></span>
                    </div>
                    <div class="pd-info-stat pd-info-stat--highlight">
                        <span class="pd-info-stat__lbl">Selling price</span>
                        <span class="pd-info-stat__val pd-ic-sell"></span>
                    </div>
                    <div class="pd-info-stat">
                        <span class="pd-info-stat__lbl">Cost price</span>
                        <span class="pd-info-stat__val pd-ic-cost" style="color:var(--muted);"></span>
                    </div>
                </div>
            </div>

            {{-- Apply-to selector — shown after selection --}}
            <div class="pd-su-section" style="display:none;">
                <label class="pd-field-label" style="margin-bottom:8px;display:block;">
                    Apply discount to
                </label>
                <div class="pd-su-list"></div>
                <input type="hidden" name="product_selling_unit_id" class="pd-su-hidden" value="">
            </div>

        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════
         SECTION 2 — Discount details
    ═══════════════════════════════════════════════════════ --}}
    <div class="pd-section">
        <div class="pd-section__head">
            <span class="pd-section__icon"><i class="fa fa-percent"></i></span>
            <div>
                <div class="pd-section__title">Discount details</div>
                <div class="pd-section__sub">Set the discount name, type and amount</div>
            </div>
        </div>
        <div class="pd-section__body">

            <div class="pcat-field">
                <label for="{{ $p }}-name">Discount name <span class="pd-req">*</span></label>
                <input id="{{ $p }}-name" name="name" value="{{ old('name') }}" maxlength="255"
                       required placeholder="e.g. Summer sale 10%">
                @error('name')<p class="pd-err">{{ $message }}</p>@enderror
            </div>

            <div class="pd-two-col" style="margin-top:4px;">

                {{-- Discount type — styled card buttons --}}
                <div>
                    <label class="pd-field-label">Discount type <span class="pd-req">*</span></label>
                    <div class="pd-type-cards">
                        <label class="pd-type-card" id="{{ $p }}-type-pct-card">
                            <input type="radio" name="discount_type" value="percentage"
                                   class="pd-type-pct pd-type-radio"
                                   @checked(old('discount_type','percentage')==='percentage')>
                            <span class="pd-type-card__icon"><i class="fa fa-percent"></i></span>
                            <span class="pd-type-card__body">
                                <span class="pd-type-card__label">Percentage</span>
                                <span class="pd-type-card__hint">e.g. 10%</span>
                            </span>
                            <span class="pd-type-card__check"><i class="fa fa-circle-check"></i></span>
                        </label>
                        <label class="pd-type-card" id="{{ $p }}-type-flat-card">
                            <input type="radio" name="discount_type" value="flat"
                                   class="pd-type-flat pd-type-radio"
                                   @checked(old('discount_type')==='flat')>
                            <span class="pd-type-card__icon"><i class="fa fa-tag"></i></span>
                            <span class="pd-type-card__body">
                                <span class="pd-type-card__label">Flat amount</span>
                                <span class="pd-type-card__hint">fixed off</span>
                            </span>
                            <span class="pd-type-card__check"><i class="fa fa-circle-check"></i></span>
                        </label>
                    </div>
                    @error('discount_type')<p class="pd-err">{{ $message }}</p>@enderror
                </div>

                {{-- Value input --}}
                <div>
                    <div class="pcat-field">
                        <label for="{{ $p }}-value">
                            Value
                            <span class="pd-value-label pd-req" style="font-weight:700;">%</span>
                        </label>
                        <div class="pd-value-wrap">
                            <input id="{{ $p }}-value" type="number" name="discount_value"
                                   value="{{ old('discount_value') }}"
                                   min="0.01" step="0.01" required placeholder="e.g. 10"
                                   class="pd-value-input pd-value-inp">
                            <span class="pd-value-suffix pd-value-suffix-sym">%</span>
                        </div>
                        @error('discount_value')<p class="pd-err">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            {{-- Live final-price preview --}}
            <div class="pd-preview" style="display:none;">
                <div class="pd-preview__inner">
                    <div class="pd-preview__left">
                        <span class="pd-preview__lbl">Final price after discount</span>
                        <div class="pd-preview__prices">
                            <span class="pd-preview__orig pd-preview-old"></span>
                            <i class="fa fa-arrow-right pd-preview__arrow"></i>
                            <span class="pd-preview__final pd-preview-new"></span>
                        </div>
                    </div>
                    <div class="pd-preview__right">
                        <span class="pd-preview__saving pd-preview-save"></span>
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════
         SECTION 3 — Validity & status
    ═══════════════════════════════════════════════════════ --}}
    <div class="pd-section">
        <div class="pd-section__head">
            <span class="pd-section__icon"><i class="fa fa-calendar-days"></i></span>
            <div>
                <div class="pd-section__title">Validity &amp; status</div>
                <div class="pd-section__sub">Optionally limit when this discount is active</div>
            </div>
        </div>
        <div class="pd-section__body">

            <div class="pd-two-col">
                <div class="pcat-field">
                    <label for="{{ $p }}-starts">
                        Start date
                        <span class="pd-optional">optional</span>
                    </label>
                    <input id="{{ $p }}-starts" type="date" name="starts_at" value="{{ old('starts_at') }}">
                    @error('starts_at')<p class="pd-err">{{ $message }}</p>@enderror
                </div>
                <div class="pcat-field">
                    <label for="{{ $p }}-ends">
                        End date
                        <span class="pd-optional">optional</span>
                    </label>
                    <input id="{{ $p }}-ends" type="date" name="ends_at" value="{{ old('ends_at') }}">
                    @error('ends_at')<p class="pd-err">{{ $message }}</p>@enderror
                </div>
            </div>

            @include('product::partials.active-toggle', [
                'toggleId' => $p . '-active',
                'model'    => null,
                'label'    => 'Discount is active',
            ])

        </div>
    </div>

</div>{{-- /.pd-form-root --}}
