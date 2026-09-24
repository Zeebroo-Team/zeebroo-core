@extends('theme::layouts.app', ['title' => 'Invoice Setup', 'heading' => 'Invoice Setup'])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.isetup-layout{display:grid;grid-template-columns:360px 1fr;gap:16px;align-items:start;}
@media (max-width:960px){.isetup-layout{grid-template-columns:1fr;}}
.isetup-section{margin-bottom:18px;}
.isetup-section__label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin:0 0 8px;}
.isetup-tpl-list{display:grid;gap:8px;}
.isetup-tpl-card{display:flex;align-items:center;gap:10px;padding:10px 12px;border:1.5px solid var(--border);border-radius:10px;background:var(--card);cursor:pointer;text-align:left;}
.isetup-tpl-card.is-active{border-color:var(--primary);background:color-mix(in srgb,var(--primary) 8%,transparent);}
.isetup-tpl-card__swatch{width:28px;height:28px;border-radius:8px;flex-shrink:0;}
.isetup-tpl-card__name{font-size:13px;font-weight:700;color:var(--text);}
.isetup-tpl-card__desc{font-size:11px;color:var(--muted);margin-top:1px;}
.isetup-color-row{display:flex;flex-wrap:wrap;gap:8px;align-items:center;}
.isetup-color-swatch{width:26px;height:26px;border-radius:999px;border:2px solid transparent;cursor:pointer;padding:0;}
.isetup-color-swatch.is-active{border-color:var(--text);box-shadow:0 0 0 2px var(--card),0 0 0 3px var(--text);}
.isetup-grid2{display:grid;grid-template-columns:1fr 1fr;gap:8px;}
.isetup-grid4{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;}
.isetup-field label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:5px;}
.isetup-field select,.isetup-field input[type=number]{width:100%;box-sizing:border-box;padding:8px 10px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);}
.isetup-preview-wrap{border:1px solid var(--border);border-radius:12px;background:color-mix(in srgb,var(--card) 92%,var(--border) 8%);padding:14px;min-height:600px;display:flex;justify-content:center;}
.isetup-preview-frame{width:100%;max-width:900px;height:1150px;border:1px solid var(--border);border-radius:8px;background:#fff;}
.isetup-letterhead-note{font-size:12px;color:var(--muted);line-height:1.5;padding:10px 12px;border:1px dashed var(--border);border-radius:10px;}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('sales::partials.sales-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif

    <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
        Choose how every invoice for <strong style="color:var(--text);">{{ $business->name }}</strong> looks when printed —
        manually created invoices and invoices auto-generated from a POS sale (Invoice mode) both use this design.
    </p>

    <form method="POST" action="{{ route('sales.invoice-setup.update') }}" id="isetup-form">
        @csrf
        <div class="isetup-layout">
            <div>
                <div class="isetup-section">
                    <p class="isetup-section__label">Template</p>
                    <div class="isetup-tpl-list" id="isetup-tpl-list">
                        @foreach($templates as $id => $tpl)
                            <button type="button" class="isetup-tpl-card @if($settings['template'] === $id) is-active @endif" data-isetup-template="{{ $id }}">
                                <span class="isetup-tpl-card__swatch" style="background:{{ $tpl['accent'] }};"></span>
                                <span>
                                    <span class="isetup-tpl-card__name">{{ $tpl['name'] }}</span>
                                    <span class="isetup-tpl-card__desc">{{ $tpl['desc'] }}</span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                    <input type="hidden" name="template" id="isetup-template" value="{{ $settings['template'] }}">
                </div>

                <div class="isetup-section">
                    <p class="isetup-section__label">Accent color</p>
                    <div class="isetup-color-row">
                        @foreach($colorPresets as $color)
                            <button type="button" class="isetup-color-swatch @if(strtolower($settings['accent_color']) === strtolower($color)) is-active @endif" data-isetup-color="{{ $color }}" style="background:{{ $color }};"></button>
                        @endforeach
                        <input type="color" id="isetup-color-custom" value="{{ $settings['accent_color'] ?: '#1d4ed8' }}" style="width:30px;height:30px;padding:0;border:1px solid var(--border);border-radius:8px;cursor:pointer;">
                        <button type="button" class="pos-btn" id="isetup-color-reset" style="padding:5px 10px;font-size:11px;">Use template default</button>
                    </div>
                    <input type="hidden" name="accent_color" id="isetup-accent-color" value="{{ $settings['accent_color'] }}">
                </div>

                <div class="isetup-section">
                    <p class="isetup-section__label">Printer &amp; paper</p>
                    <div class="isetup-grid2">
                        <div class="isetup-field">
                            <label for="isetup-paper">Paper size</label>
                            <select name="paper_size" id="isetup-paper">
                                @foreach(['a4' => 'A4', 'a5' => 'A5', 'letter' => 'Letter', 'legal' => 'Legal'] as $val => $label)
                                    <option value="{{ $val }}" @selected($settings['paper_size'] === $val)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="isetup-field">
                            <label for="isetup-orientation">Orientation</label>
                            <select name="orientation" id="isetup-orientation">
                                <option value="portrait" @selected($settings['orientation'] === 'portrait')>Portrait</option>
                                <option value="landscape" @selected($settings['orientation'] === 'landscape')>Landscape</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="isetup-section">
                    <p class="isetup-section__label">Margins (mm)</p>
                    <div class="isetup-grid4">
                        <div class="isetup-field"><label for="isetup-mg-top">Top</label><input type="number" min="0" max="80" name="margin_top" id="isetup-mg-top" value="{{ $settings['margin_top'] }}"></div>
                        <div class="isetup-field"><label for="isetup-mg-bottom">Bottom</label><input type="number" min="0" max="80" name="margin_bottom" id="isetup-mg-bottom" value="{{ $settings['margin_bottom'] }}"></div>
                        <div class="isetup-field"><label for="isetup-mg-left">Left</label><input type="number" min="0" max="80" name="margin_left" id="isetup-mg-left" value="{{ $settings['margin_left'] }}"></div>
                        <div class="isetup-field"><label for="isetup-mg-right">Right</label><input type="number" min="0" max="80" name="margin_right" id="isetup-mg-right" value="{{ $settings['margin_right'] }}"></div>
                    </div>
                </div>

                <div class="isetup-section">
                    <p class="isetup-section__label">Arrangement</p>
                    <div class="isetup-grid2">
                        <div class="isetup-field">
                            <label for="isetup-header-layout">Header layout</label>
                            <select name="header_layout" id="isetup-header-layout">
                                <option value="num-left" @selected($settings['header_layout'] === 'num-left')>Number left, status right</option>
                                <option value="num-right" @selected($settings['header_layout'] === 'num-right')>Number right</option>
                                <option value="num-center" @selected($settings['header_layout'] === 'num-center')>Centered</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="isetup-section">
                    <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:700;color:var(--text);cursor:pointer;">
                        <input type="checkbox" id="isetup-letterhead-enabled" name="letterhead_enabled" value="1" @checked($letterheadEnabled) style="width:16px;height:16px;">
                        Enable Letterhead
                    </label>
                    <p class="isetup-letterhead-note" style="margin-top:8px;">
                        Overlays your Design Studio letterhead as a full-width banner at the top of printed invoices,
                        replacing the business name shown there. Design the artwork itself from
                        <a href="{{ route('designstudio.letterhead.links') }}">Design Studio → Letterhead</a>.
                    </p>
                </div>

                <button type="submit" class="pos-btn pos-btn--primary">Save Setup</button>
            </div>

            <div>
                <p class="isetup-section__label">Preview</p>
                <div class="isetup-preview-wrap">
                    <iframe id="isetup-preview-frame" class="isetup-preview-frame" title="Invoice preview"></iframe>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
(function () {
    const previewUrl = @json(route('sales.invoice-setup.preview'));
    const frame = document.getElementById('isetup-preview-frame');
    const form = document.getElementById('isetup-form');
    const templateInput = document.getElementById('isetup-template');
    const accentInput = document.getElementById('isetup-accent-color');
    const colorCustom = document.getElementById('isetup-color-custom');

    function currentParams() {
        return {
            template: templateInput.value,
            accent_color: accentInput.value,
            paper_size: document.getElementById('isetup-paper').value,
            orientation: document.getElementById('isetup-orientation').value,
            margin_top: document.getElementById('isetup-mg-top').value,
            margin_bottom: document.getElementById('isetup-mg-bottom').value,
            margin_left: document.getElementById('isetup-mg-left').value,
            margin_right: document.getElementById('isetup-mg-right').value,
            header_layout: document.getElementById('isetup-header-layout').value,
            letterhead_enabled: document.getElementById('isetup-letterhead-enabled').checked ? '1' : '0',
        };
    }

    let debounceTimer = null;
    function refreshPreview() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () {
            const params = new URLSearchParams(currentParams());
            frame.src = previewUrl + '?' + params.toString();
        }, 150);
    }

    document.querySelectorAll('[data-isetup-template]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('[data-isetup-template]').forEach(function (b) { b.classList.toggle('is-active', b === btn); });
            templateInput.value = btn.dataset.isetupTemplate;
            refreshPreview();
        });
    });

    document.querySelectorAll('[data-isetup-color]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('[data-isetup-color]').forEach(function (b) { b.classList.toggle('is-active', b === btn); });
            accentInput.value = btn.dataset.isetupColor;
            colorCustom.value = btn.dataset.isetupColor;
            refreshPreview();
        });
    });

    colorCustom.addEventListener('input', function () {
        document.querySelectorAll('[data-isetup-color]').forEach(function (b) { b.classList.remove('is-active'); });
        accentInput.value = colorCustom.value;
        refreshPreview();
    });

    document.getElementById('isetup-color-reset').addEventListener('click', function () {
        document.querySelectorAll('[data-isetup-color]').forEach(function (b) { b.classList.remove('is-active'); });
        accentInput.value = '';
        refreshPreview();
    });

    form.querySelectorAll('select, input[type=number], input[type=checkbox]').forEach(function (el) {
        el.addEventListener('change', refreshPreview);
    });

    refreshPreview();
})();
</script>
@endsection
