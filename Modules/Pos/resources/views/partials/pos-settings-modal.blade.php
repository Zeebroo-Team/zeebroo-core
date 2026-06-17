@php
    $posSettings = $posSettings ?? [];
    $redirectUrl = url()->current();
    $isDarkMode = ($posSettings['display_theme'] ?? 'inherit') === 'dark';
    $branchNavOptions = $branchNavOptions ?? [];
@endphp

<div id="pos-settings-modal" class="psm-overlay" role="dialog" aria-modal="true" aria-labelledby="pos-settings-title" aria-hidden="true">
    <div class="psm-backdrop" data-pos-settings-close tabindex="-1" aria-label="Close"></div>
    <div class="psm-dialog">

        {{-- Sidebar nav --}}
        <aside class="psm-sidebar">
            <div class="psm-sidebar__brand">
                <span class="psm-sidebar__icon"><i class="fa fa-gear" aria-hidden="true"></i></span>
                <span class="psm-sidebar__title">Settings</span>
            </div>
            <nav class="psm-nav" role="tablist" aria-label="Settings sections">
                <button type="button" class="psm-nav__item is-active" role="tab" aria-selected="true"
                    aria-controls="psm-panel-general" id="psm-tab-general" data-psm-tab="general">
                    <span class="psm-nav__item-icon"><i class="fa fa-sliders" aria-hidden="true"></i></span>
                    <span class="psm-nav__item-label">General</span>
                    <span class="psm-nav__item-arrow"><i class="fa fa-chevron-right" aria-hidden="true"></i></span>
                </button>
                <button type="button" class="psm-nav__item" role="tab" aria-selected="false"
                    aria-controls="psm-panel-sales" id="psm-tab-sales" data-psm-tab="sales">
                    <span class="psm-nav__item-icon"><i class="fa fa-cash-register" aria-hidden="true"></i></span>
                    <span class="psm-nav__item-label">Sales</span>
                    <span class="psm-nav__item-arrow"><i class="fa fa-chevron-right" aria-hidden="true"></i></span>
                </button>
                <button type="button" class="psm-nav__item" role="tab" aria-selected="false"
                    aria-controls="psm-panel-print" id="psm-tab-print" data-psm-tab="print">
                    <span class="psm-nav__item-icon"><i class="fa fa-print" aria-hidden="true"></i></span>
                    <span class="psm-nav__item-label">Print Layout</span>
                    <span class="psm-nav__item-arrow"><i class="fa fa-chevron-right" aria-hidden="true"></i></span>
                </button>
            </nav>
        </aside>

        {{-- Main content area --}}
        <div class="psm-content">
            <div class="psm-content__head">
                <div>
                    <h2 id="pos-settings-title" class="psm-content__title">POS Settings</h2>
                    <p class="psm-content__subtitle" id="psm-active-tab-desc">Appearance &amp; display preferences</p>
                </div>
                <button type="button" class="psm-close" data-pos-settings-close aria-label="Close">
                    <i class="fa fa-times" aria-hidden="true"></i>
                </button>
            </div>

            <form method="post" action="{{ $settingsFormAction ?? route('pos.settings.save') }}" class="psm-form">
                @csrf
                <input type="hidden" name="redirect" value="{{ $redirectUrl }}">

                {{-- ── General panel ──────────────────────────────── --}}
                <div class="psm-panel is-active" id="psm-panel-general" role="tabpanel" aria-labelledby="psm-tab-general">
                    @if(!empty($branchNavOptions))
                    <div class="psm-section">
                        <p class="psm-section__label"><i class="fa fa-code-branch" aria-hidden="true"></i> Branch</p>
                        <div class="psm-card">
                            <div class="psm-field" style="margin-bottom:0;">
                                <label class="psm-field__label" for="psm-branch-select">Active branch</label>
                                <p class="psm-field__hint">Show products and stock for the selected branch only</p>
                                <div class="psm-select-wrap">
                                    <select id="psm-branch-select" class="psm-select">
                                        @foreach($branchNavOptions as $opt)
                                            <option value="{{ $opt['url'] }}" @selected($opt['selected'])>{{ $opt['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <span class="psm-select-wrap__icon"><i class="fa fa-chevron-down" aria-hidden="true"></i></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                    <div class="psm-section">
                        <p class="psm-section__label"><i class="fa fa-layer-group" aria-hidden="true"></i> Catalog limits</p>
                        <div class="psm-card">
                            <div class="psm-field">
                                <label class="psm-field__label" for="pos-settings-products-limit">Featured products limit</label>
                                <p class="psm-field__hint">Maximum products shown on the POS page. Set to 0 to show all.</p>
                                <input type="number" name="featured_products_limit" id="pos-settings-products-limit" class="psm-input"
                                    min="0" max="9999" step="1"
                                    value="{{ (int) ($posSettings['featured_products_limit'] ?? 0) }}"
                                    placeholder="0 = show all">
                            </div>
                            <div class="psm-field" style="margin-bottom:0;">
                                <label class="psm-field__label" for="pos-settings-categories-limit">Category carousel limit</label>
                                <p class="psm-field__hint">Maximum categories shown in the carousel. Set to 0 to show all.</p>
                                <input type="number" name="featured_categories_limit" id="pos-settings-categories-limit" class="psm-input"
                                    min="0" max="9999" step="1"
                                    value="{{ (int) ($posSettings['featured_categories_limit'] ?? 0) }}"
                                    placeholder="0 = show all">
                            </div>
                        </div>
                    </div>

                    <div class="psm-section">
                        <p class="psm-section__label"><i class="fa fa-palette" aria-hidden="true"></i> Appearance</p>
                        <div class="psm-card">
                            <div class="psm-row">
                                <div class="psm-row__info">
                                    <span class="psm-row__name">Display Theme</span>
                                    <span class="psm-row__desc">Choose between light and dark interface</span>
                                </div>
                                <div class="psm-theme-toggle">
                                    <input type="hidden" name="display_theme" value="{{ $isDarkMode ? 'dark' : 'light' }}" id="pos-settings-theme-value">
                                    <label class="psm-theme-toggle__option {{ !$isDarkMode ? 'is-active' : '' }}" id="psm-theme-light-label">
                                        <input type="radio" name="_theme_ui" value="light" {{ !$isDarkMode ? 'checked' : '' }} style="display:none;">
                                        <i class="fa fa-sun" aria-hidden="true"></i>
                                        <span>Light</span>
                                    </label>
                                    <label class="psm-theme-toggle__option {{ $isDarkMode ? 'is-active' : '' }}" id="psm-theme-dark-label">
                                        <input type="radio" name="_theme_ui" value="dark" {{ $isDarkMode ? 'checked' : '' }} style="display:none;" id="pos-settings-theme-dark">
                                        <i class="fa fa-moon" aria-hidden="true"></i>
                                        <span>Dark</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ── Sales panel ─────────────────────────────────── --}}
                <div class="psm-panel" id="psm-panel-sales" role="tabpanel" aria-labelledby="psm-tab-sales" hidden>
                    <div class="psm-section">
                        <p class="psm-section__label"><i class="fa fa-building-columns" aria-hidden="true"></i> Payment</p>
                        <div class="psm-card">
                            <div class="psm-field">
                                <label class="psm-field__label" for="pos-settings-deposit">Deposit to account</label>
                                <p class="psm-field__hint">Sales proceeds will be deposited to this account by default</p>
                                <div class="psm-select-wrap">
                                    <select name="default_deposit_account_id" id="pos-settings-deposit" class="psm-select">
                                        <option value="">— Choose each sale —</option>
                                        @foreach($accounts as $account)
                                            <option value="{{ $account->id }}" @selected((int) ($posSettings['default_deposit_account_id'] ?? 0) === (int) $account->id)>
                                                {{ $account->deductOptionLabel() }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <span class="psm-select-wrap__icon"><i class="fa fa-chevron-down" aria-hidden="true"></i></span>
                                </div>
                                @if(!($hasAccounts ?? true))
                                    <p class="psm-field__notice"><i class="fa fa-circle-info" aria-hidden="true"></i> Add a <a href="{{ route('account.onboarding') }}" class="psm-link">business account</a> first.</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="psm-section">
                        <p class="psm-section__label"><i class="fa fa-clock" aria-hidden="true"></i> Settlement timing</p>
                        <div class="psm-card">
                            <div class="psm-row">
                                <div class="psm-row__info">
                                    <span class="psm-row__name">Payment settlement mode</span>
                                    <span class="psm-row__desc">When to deposit sales revenue to your bank account</span>
                                </div>
                            </div>
                            <div style="padding:0 16px 14px;">
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                                    <label class="psm-settle-option @if(($posSettings['payment_settlement_mode'] ?? 'immediate') === 'immediate') is-active @endif" id="psm-settle-immediate-label">
                                        <input type="radio" name="_settlement_ui" value="immediate" {{ ($posSettings['payment_settlement_mode'] ?? 'immediate') === 'immediate' ? 'checked' : '' }} style="display:none;">
                                        <i class="fa fa-bolt"></i>
                                        <span class="psm-settle-option__title">Immediate</span>
                                        <span class="psm-settle-option__desc">Deposit after each sale</span>
                                    </label>
                                    <label class="psm-settle-option @if(($posSettings['payment_settlement_mode'] ?? 'immediate') === 'end_of_day') is-active @endif" id="psm-settle-eod-label">
                                        <input type="radio" name="_settlement_ui" value="end_of_day" {{ ($posSettings['payment_settlement_mode'] ?? 'immediate') === 'end_of_day' ? 'checked' : '' }} style="display:none;">
                                        <i class="fa fa-moon"></i>
                                        <span class="psm-settle-option__title">End of day</span>
                                        <span class="psm-settle-option__desc">Batch approve at day end</span>
                                    </label>
                                </div>
                                <input type="hidden" name="payment_settlement_mode" id="psm-settlement-mode-value" value="{{ $posSettings['payment_settlement_mode'] ?? 'immediate' }}">
                            </div>
                        </div>
                    </div>

                    <div class="psm-section">
                        <p class="psm-section__label"><i class="fa fa-cart-shopping" aria-hidden="true"></i> Checkout options</p>
                        <div class="psm-card">
                            <div class="psm-row">
                                <div class="psm-row__info">
                                    <span class="psm-row__name">Discount field on checkout</span>
                                    <span class="psm-row__desc">Show discount input during the checkout process</span>
                                </div>
                                <label class="psm-switch" title="Toggle discount field">
                                    <input type="hidden" name="discount_field_enabled" value="0">
                                    <input type="checkbox" name="discount_field_enabled" value="1" @checked($posSettings['discount_field_enabled'] ?? false)>
                                    <span class="psm-switch__track" aria-hidden="true"><span class="psm-switch__thumb"></span></span>
                                </label>
                            </div>
                            <div class="psm-row psm-row--border">
                                <div class="psm-row__info">
                                    <span class="psm-row__name">Checkout as modal</span>
                                    <span class="psm-row__desc">Open the checkout form in an overlay panel</span>
                                </div>
                                <label class="psm-switch" title="Toggle checkout modal">
                                    <input type="hidden" name="checkout_modal_enabled" value="0">
                                    <input type="checkbox" name="checkout_modal_enabled" value="1" @checked($posSettings['checkout_modal_enabled'] ?? false)>
                                    <span class="psm-switch__track" aria-hidden="true"><span class="psm-switch__thumb"></span></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ── Print Layout panel ───────────────────────────── --}}
                <div class="psm-panel" id="psm-panel-print" role="tabpanel" aria-labelledby="psm-tab-print" hidden>
                    <div class="psm-section">
                        <p class="psm-section__label"><i class="fa fa-align-left" aria-hidden="true"></i> Header &amp; Footer</p>
                        <div class="psm-card">
                            <div class="psm-field">
                                <label class="psm-field__label" for="pos-settings-receipt-header">Receipt header text</label>
                                <input type="text" name="receipt_header" id="pos-settings-receipt-header" class="psm-input" maxlength="200"
                                    value="{{ $posSettings['receipt_header'] ?? '' }}"
                                    placeholder="e.g. Welcome to our store!">
                            </div>
                            <div class="psm-field" style="margin-bottom:0;">
                                <label class="psm-field__label" for="pos-settings-receipt-footer">Receipt footer text</label>
                                <input type="text" name="receipt_footer" id="pos-settings-receipt-footer" class="psm-input" maxlength="200"
                                    value="{{ $posSettings['receipt_footer'] ?? 'Thank you for your purchase!' }}"
                                    placeholder="e.g. Thank you for your purchase!">
                            </div>
                        </div>
                    </div>

                    <div class="psm-section">
                        <p class="psm-section__label"><i class="fa fa-building" aria-hidden="true"></i> Business info on receipt</p>
                        <div class="psm-card">
                            <div class="psm-row">
                                <div class="psm-row__info">
                                    <span class="psm-row__name">Show business name</span>
                                    <span class="psm-row__desc">Print your business name at the top of the receipt</span>
                                </div>
                                <label class="psm-switch">
                                    <input type="hidden" name="show_business_name" value="0">
                                    <input type="checkbox" name="show_business_name" value="1" @checked($posSettings['show_business_name'] ?? true)>
                                    <span class="psm-switch__track" aria-hidden="true"><span class="psm-switch__thumb"></span></span>
                                </label>
                            </div>
                            <div class="psm-row psm-row--border">
                                <div class="psm-row__info">
                                    <span class="psm-row__name">Show business address</span>
                                    <span class="psm-row__desc">Include your address on printed receipts</span>
                                </div>
                                <label class="psm-switch">
                                    <input type="hidden" name="show_business_address" value="0">
                                    <input type="checkbox" name="show_business_address" value="1" @checked($posSettings['show_business_address'] ?? true)>
                                    <span class="psm-switch__track" aria-hidden="true"><span class="psm-switch__thumb"></span></span>
                                </label>
                            </div>
                            <div class="psm-row psm-row--border">
                                <div class="psm-row__info">
                                    <span class="psm-row__name">Show account info</span>
                                    <span class="psm-row__desc">Print the deposit or credit account name on the receipt</span>
                                </div>
                                <label class="psm-switch">
                                    <input type="hidden" name="show_account_info" value="0">
                                    <input type="checkbox" name="show_account_info" value="1" @checked($posSettings['show_account_info'] ?? true)>
                                    <span class="psm-switch__track" aria-hidden="true"><span class="psm-switch__thumb"></span></span>
                                </label>
                            </div>
                            <div class="psm-row psm-row--border">
                                <div class="psm-row__info">
                                    <span class="psm-row__name">Show service bound products</span>
                                    <span class="psm-row__desc">List attached products under each service line on the receipt</span>
                                </div>
                                <label class="psm-switch">
                                    <input type="hidden" name="show_service_bound_products" value="0">
                                    <input type="checkbox" name="show_service_bound_products" value="1" @checked($posSettings['show_service_bound_products'] ?? true)>
                                    <span class="psm-switch__track" aria-hidden="true"><span class="psm-switch__thumb"></span></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="psm-footer">
                    <button type="button" class="psm-btn psm-btn--ghost" data-pos-settings-close>Cancel</button>
                    <button type="submit" class="psm-btn psm-btn--primary">
                        <i class="fa fa-floppy-disk" aria-hidden="true"></i> Save settings
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<button type="button" class="pos-settings-btn" id="pos-settings-open" title="POS settings" aria-haspopup="dialog" aria-controls="pos-settings-modal">
    <i class="fa fa-gear" aria-hidden="true"></i>
</button>

@once
<style>
/* ── Overlay & backdrop ─────────────────────────────────────────── */
.psm-overlay{position:fixed;inset:0;z-index:200;display:flex;align-items:center;justify-content:center;padding:16px;visibility:hidden;opacity:0;pointer-events:none;transition:opacity .25s cubic-bezier(.4,0,.2,1),visibility .25s;}
.psm-overlay.is-open{visibility:visible;opacity:1;pointer-events:auto;}
.psm-backdrop{position:absolute;inset:0;background:rgba(2,6,23,.6);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);}
html.pos-settings-modal-open,html.pos-settings-modal-open body{overflow:hidden;}

/* ── Dialog shell ───────────────────────────────────────────────── */
.psm-dialog{position:relative;z-index:1;display:flex;width:min(100%,760px);max-height:min(92vh,620px);border-radius:20px;overflow:hidden;box-shadow:0 32px 80px rgba(0,0,0,.36),0 0 0 1px rgba(255,255,255,.06);transform:translateY(12px) scale(.97);transition:transform .28s cubic-bezier(.34,1.2,.64,1);background:var(--card);}
.psm-overlay.is-open .psm-dialog{transform:translateY(0) scale(1);}

/* ── Sidebar ────────────────────────────────────────────────────── */
.psm-sidebar{width:200px;flex-shrink:0;display:flex;flex-direction:column;background:color-mix(in srgb,var(--card) 92%,var(--border) 8%);border-right:1px solid var(--border);padding:0 0 16px;}
.psm-sidebar__brand{display:flex;align-items:center;gap:10px;padding:20px 18px 16px;border-bottom:1px solid color-mix(in srgb,var(--border) 60%,transparent);margin-bottom:8px;}
.psm-sidebar__icon{width:32px;height:32px;border-radius:9px;background:color-mix(in srgb,var(--primary) 18%,transparent);border:1px solid color-mix(in srgb,var(--primary) 30%,transparent);display:flex;align-items:center;justify-content:center;font-size:13px;color:var(--primary);}
.psm-sidebar__title{font-size:13px;font-weight:700;color:var(--text);letter-spacing:-.01em;}
.psm-nav{display:flex;flex-direction:column;gap:2px;padding:0 10px;}
.psm-nav__item{display:flex;align-items:center;gap:10px;padding:9px 10px;border-radius:10px;border:1px solid transparent;background:transparent;color:var(--muted);cursor:pointer;font-size:12.5px;font-weight:600;text-align:left;transition:all .15s ease;width:100%;}
.psm-nav__item:hover{background:color-mix(in srgb,var(--primary) 8%,transparent);color:var(--text);}
.psm-nav__item.is-active{background:color-mix(in srgb,var(--primary) 14%,transparent);border-color:color-mix(in srgb,var(--primary) 30%,transparent);color:var(--text);}
.psm-nav__item-icon{width:22px;height:22px;display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0;border-radius:6px;background:color-mix(in srgb,var(--primary) 10%,transparent);}
.psm-nav__item.is-active .psm-nav__item-icon{background:color-mix(in srgb,var(--primary) 20%,transparent);color:var(--primary);}
.psm-nav__item-label{flex:1;white-space:nowrap;}
.psm-nav__item-arrow{font-size:9px;opacity:0;transition:opacity .15s;}
.psm-nav__item.is-active .psm-nav__item-arrow{opacity:.5;}

/* ── Content area ───────────────────────────────────────────────── */
.psm-content{flex:1;min-width:0;display:flex;flex-direction:column;overflow:hidden;}
.psm-content__head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding:20px 24px 16px;border-bottom:1px solid var(--border);flex-shrink:0;}
.psm-content__title{margin:0;font-size:15px;font-weight:800;color:var(--text);letter-spacing:-.02em;}
.psm-content__subtitle{margin:3px 0 0;font-size:11.5px;color:var(--muted);}
.psm-close{width:32px;height:32px;flex-shrink:0;display:flex;align-items:center;justify-content:center;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--muted);cursor:pointer;font-size:13px;transition:all .15s;}
.psm-close:hover{border-color:var(--text);color:var(--text);background:color-mix(in srgb,var(--border) 40%,transparent);}

/* ── Form & panels ──────────────────────────────────────────────── */
.psm-form{display:flex;flex-direction:column;flex:1;min-height:0;}
.psm-panel{display:none;flex:1;overflow-y:auto;padding:20px 24px;}
.psm-panel.is-active{display:block;}

/* ── Section ────────────────────────────────────────────────────── */
.psm-section{margin-bottom:20px;}
.psm-section:last-child{margin-bottom:0;}
.psm-section__label{display:flex;align-items:center;gap:6px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);margin:0 0 10px;padding:0 2px;}
.psm-section__label i{font-size:10px;}

/* ── Card ───────────────────────────────────────────────────────── */
.psm-card{background:color-mix(in srgb,var(--card) 96%,var(--border) 4%);border:1px solid var(--border);border-radius:12px;overflow:hidden;}

/* ── Row ────────────────────────────────────────────────────────── */
.psm-row{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:13px 16px;}
.psm-row--border{border-top:1px solid var(--border);}
.psm-row__info{display:flex;flex-direction:column;gap:2px;min-width:0;}
.psm-row__name{font-size:13px;font-weight:600;color:var(--text);}
.psm-row__desc{font-size:11px;color:var(--muted);line-height:1.4;}

/* ── Field ──────────────────────────────────────────────────────── */
.psm-field{padding:14px 16px;border-bottom:1px solid var(--border);}
.psm-field:last-child{border-bottom:none;}
.psm-field__label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:4px;}
.psm-field__hint{font-size:11px;color:var(--muted);margin:0 0 8px;line-height:1.4;}
.psm-field__notice{font-size:11px;color:var(--muted);margin:8px 0 0;display:flex;align-items:center;gap:5px;}
.psm-input{width:100%;box-sizing:border-box;padding:9px 12px;font-size:13px;border-radius:9px;border:1px solid var(--border);background:var(--card);color:var(--text);outline:none;transition:border-color .15s;}
.psm-input:focus{border-color:var(--primary);box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 14%,transparent);}
.psm-select-wrap{position:relative;}
.psm-select{width:100%;box-sizing:border-box;padding:9px 36px 9px 12px;font-size:13px;border-radius:9px;border:1px solid var(--border);background:var(--card);color:var(--text);outline:none;appearance:none;cursor:pointer;transition:border-color .15s;}
.psm-select:focus{border-color:var(--primary);box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 14%,transparent);}
.psm-select-wrap__icon{position:absolute;right:11px;top:50%;transform:translateY(-50%);font-size:10px;color:var(--muted);pointer-events:none;}
.psm-link{color:var(--primary);text-decoration:none;font-weight:600;}
.psm-link:hover{text-decoration:underline;}

/* ── Theme toggle ───────────────────────────────────────────────── */
.psm-theme-toggle{display:flex;gap:4px;background:color-mix(in srgb,var(--border) 50%,transparent);border-radius:10px;padding:3px;}
.psm-theme-toggle__option{display:flex;align-items:center;gap:6px;padding:7px 14px;border-radius:8px;font-size:12px;font-weight:600;color:var(--muted);cursor:pointer;transition:all .15s;user-select:none;}
.psm-theme-toggle__option.is-active{background:var(--card);color:var(--text);box-shadow:0 1px 4px rgba(0,0,0,.12);}
.psm-theme-toggle__option i{font-size:12px;}

/* ── Toggle switch ──────────────────────────────────────────────── */
.psm-switch{position:relative;flex-shrink:0;display:inline-flex;cursor:pointer;}
.psm-switch input{position:absolute;opacity:0;width:0;height:0;pointer-events:none;}
.psm-switch__track{display:block;width:42px;height:24px;border-radius:100px;background:color-mix(in srgb,var(--border) 80%,transparent);border:1px solid var(--border);transition:background .2s,border-color .2s;position:relative;}
.psm-switch__thumb{position:absolute;top:3px;left:3px;width:16px;height:16px;border-radius:50%;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.2);transition:transform .2s cubic-bezier(.34,1.3,.64,1);}
.psm-switch input:checked~.psm-switch__track{background:var(--primary);border-color:var(--primary);}
.psm-switch input:checked~.psm-switch__track .psm-switch__thumb{transform:translateX(18px);}

/* ── Footer bar ─────────────────────────────────────────────────── */
.psm-footer{display:flex;align-items:center;justify-content:flex-end;gap:8px;padding:14px 24px;border-top:1px solid var(--border);flex-shrink:0;background:color-mix(in srgb,var(--card) 94%,var(--border) 6%);}
.psm-btn{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;font-size:13px;font-weight:700;border-radius:9px;border:1px solid transparent;cursor:pointer;transition:all .15s;}
.psm-btn--ghost{background:transparent;border-color:var(--border);color:var(--muted);}
.psm-btn--ghost:hover{border-color:var(--text);color:var(--text);background:color-mix(in srgb,var(--border) 30%,transparent);}
.psm-btn--primary{background:var(--primary);border-color:var(--primary);color:#fff;}
.psm-btn--primary:hover{opacity:.9;box-shadow:0 4px 14px color-mix(in srgb,var(--primary) 35%,transparent);}

/* ── Settlement mode toggle ─────────────────────────────────────── */
.psm-settle-option{display:flex;flex-direction:column;align-items:center;gap:4px;padding:12px 10px;border-radius:10px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 94%,var(--border) 6%);cursor:pointer;transition:all .15s;text-align:center;}
.psm-settle-option i{font-size:18px;color:var(--muted);}
.psm-settle-option.is-active{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 10%,transparent);}
.psm-settle-option.is-active i{color:var(--primary);}
.psm-settle-option__title{font-size:12px;font-weight:700;color:var(--text);}
.psm-settle-option__desc{font-size:10px;color:var(--muted);}

/* ── Responsive ─────────────────────────────────────────────────── */
@media (max-width:580px){
    .psm-dialog{flex-direction:column;max-height:96vh;}
    .psm-sidebar{width:100%;flex-direction:row;flex-wrap:nowrap;border-right:none;border-bottom:1px solid var(--border);padding:0;}
    .psm-sidebar__brand{display:none;}
    .psm-nav{flex-direction:row;gap:4px;padding:10px 12px;overflow-x:auto;}
    .psm-nav__item{flex-direction:column;gap:4px;padding:8px 12px;font-size:10px;white-space:nowrap;}
    .psm-nav__item-arrow{display:none;}
    .psm-nav__item-icon{width:26px;height:26px;font-size:13px;}
    .psm-panel{padding:14px 16px;}
    .psm-content__head{padding:14px 16px 12px;}
    .psm-footer{padding:10px 16px;}
    .psm-grid{grid-template-columns:1fr;}
}
</style>

<script>
(function () {
    var modal   = document.getElementById('pos-settings-modal');
    var openBtn = document.getElementById('pos-settings-open');
    if (!modal) return;

    var tabDescriptions = {
        general: 'Appearance & display preferences',
        sales:   'Payment accounts & checkout options',
        print:   'Receipt header, footer & business info'
    };

    function setOpen(open) {
        modal.classList.toggle('is-open', open);
        modal.setAttribute('aria-hidden', open ? 'false' : 'true');
        document.documentElement.classList.toggle('pos-settings-modal-open', open);
        if (open) modal.querySelector('.psm-nav__item.is-active')?.focus();
    }

    function bindOpen(el) {
        if (!el) return;
        el.addEventListener('click', function () { setOpen(true); });
    }

    bindOpen(openBtn);
    document.querySelectorAll('[data-pos-settings-open]').forEach(bindOpen);

    modal.querySelectorAll('[data-pos-settings-close]').forEach(function (el) {
        el.addEventListener('click', function () { setOpen(false); });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) setOpen(false);
    });

    // Tab switching
    var tabs   = Array.from(modal.querySelectorAll('.psm-nav__item'));
    var panels = Array.from(modal.querySelectorAll('.psm-panel'));
    var subtitle = document.getElementById('psm-active-tab-desc');

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            var target = tab.dataset.psmTab;
            tabs.forEach(function (t) {
                var active = t.dataset.psmTab === target;
                t.classList.toggle('is-active', active);
                t.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            panels.forEach(function (p) {
                var show = p.id === 'psm-panel-' + target;
                p.classList.toggle('is-active', show);
                if (show) p.removeAttribute('hidden'); else p.setAttribute('hidden', '');
            });
            if (subtitle && tabDescriptions[target]) {
                subtitle.textContent = tabDescriptions[target];
            }
        });

        tab.addEventListener('keydown', function (e) {
            var idx = tabs.indexOf(tab);
            if (e.key === 'ArrowDown' || e.key === 'ArrowRight') { tabs[(idx + 1) % tabs.length].click(); tabs[(idx + 1) % tabs.length].focus(); e.preventDefault(); }
            if (e.key === 'ArrowUp'   || e.key === 'ArrowLeft')  { tabs[(idx - 1 + tabs.length) % tabs.length].click(); tabs[(idx - 1 + tabs.length) % tabs.length].focus(); e.preventDefault(); }
        });
    });

    // Theme toggle
    var themeValue   = document.getElementById('pos-settings-theme-value');
    var themeDark    = document.getElementById('pos-settings-theme-dark');
    var lightLabel   = document.getElementById('psm-theme-light-label');
    var darkLabel    = document.getElementById('psm-theme-dark-label');

    function applyTheme(isDark) {
        if (themeValue) themeValue.value = isDark ? 'dark' : 'light';
        if (lightLabel) lightLabel.classList.toggle('is-active', !isDark);
        if (darkLabel)  darkLabel.classList.toggle('is-active', isDark);
    }

    if (lightLabel) {
        lightLabel.addEventListener('click', function () { applyTheme(false); });
    }
    if (darkLabel) {
        darkLabel.addEventListener('click', function () { applyTheme(true); });
    }

    // Settlement mode toggle
    var settleImmediate = document.getElementById('psm-settle-immediate-label');
    var settleEod = document.getElementById('psm-settle-eod-label');
    var settleModeValue = document.getElementById('psm-settlement-mode-value');

    function applySettlementMode(mode) {
        if (settleModeValue) settleModeValue.value = mode;
        if (settleImmediate) settleImmediate.classList.toggle('is-active', mode === 'immediate');
        if (settleEod) settleEod.classList.toggle('is-active', mode === 'end_of_day');
    }

    if (settleImmediate) settleImmediate.addEventListener('click', function () { applySettlementMode('immediate'); });
    if (settleEod) settleEod.addEventListener('click', function () { applySettlementMode('end_of_day'); });

    // Branch selection — navigate immediately when branch changes
    var branchModalSelect = document.getElementById('psm-branch-select');
    if (branchModalSelect) {
        branchModalSelect.addEventListener('change', function () {
            if (branchModalSelect.value) window.location.href = branchModalSelect.value;
        });
    }
})();
</script>
@endonce
