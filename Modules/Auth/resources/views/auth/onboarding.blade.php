@extends('theme::layouts.auth', ['title' => __('Set up your workspace')])

@php
$obFeatures = [
    ['key' => 'account_management',   'label' => 'Account Management',   'icon' => 'fa-wallet',             'desc' => 'Track bank accounts, income, expenses and ledgers',         'required' => true],
    ['key' => 'bill_management',      'label' => 'Bill Management',       'icon' => 'fa-file-invoice-dollar','desc' => 'Manage recurring utility and service bills'],
    ['key' => 'human_resources',      'label' => 'Human Resources',       'icon' => 'fa-users-gear',         'desc' => 'Payroll, leave, attendance and employee management'],
    ['key' => 'mail',                 'label' => 'Mail',                  'icon' => 'fa-envelope',           'desc' => 'Business inbox, templates, filters and scheduled sending'],
    ['key' => 'product_management',   'label' => 'Product Management',    'icon' => 'fa-boxes-stacked',      'desc' => 'Product catalogue, pricing, variants and categories'],
    ['key' => 'stock_management',     'label' => 'Stock Management',      'icon' => 'fa-warehouse',          'desc' => 'Inventory levels, stock transfers and low-stock alerts'],
    ['key' => 'point_of_sale',        'label' => 'Point of Sale',         'icon' => 'fa-cash-register',      'desc' => 'Counter sales, receipts, daily float and cashier shifts', 'requires' => ['product_management','stock_management']],
    ['key' => 'service_management',   'label' => 'Service Management',    'icon' => 'fa-screwdriver-wrench', 'desc' => 'Service catalog, requests and delivery management'],
    ['key' => 'social_media_campaign','label' => 'Social Media Campaign', 'icon' => 'fa-bullhorn',           'desc' => 'Design, schedule and track marketing campaigns'],
];

$obSteps = [
    ['label' => 'Welcome'],
    ['label' => 'Business'],
    ['label' => 'Features'],
    ['label' => 'Location'],
    ['label' => 'Data Vault'],
];
@endphp

@section('content')

<div class="ob-wizard">
    {{-- Left step indicator --}}
    <nav class="ob-stepper" aria-label="{{ __('Setup steps') }}">
        <div class="ob-stepper__brand">
            <span class="ob-stepper__brand-mark">SB</span>
            <span class="ob-stepper__brand-name">{{ config('app.name') }}</span>
        </div>
        <ol class="ob-stepper__list">
            @foreach($obSteps as $i => $step)
                @php $n = $i + 1; @endphp
                <li class="ob-stepper__item" data-stepper-item="{{ $n }}">
                    <span class="ob-stepper__dot" data-stepper-dot="{{ $n }}" aria-hidden="true">{{ $n }}</span>
                    <span class="ob-stepper__lbl" data-stepper-lbl="{{ $n }}">{{ $step['label'] }}</span>
                </li>
                @if($n < count($obSteps))
                    <li class="ob-stepper__connector" data-stepper-conn="{{ $n }}" aria-hidden="true"></li>
                @endif
            @endforeach
        </ol>
    </nav>

    {{-- Main scrollable area --}}
    <div class="ob-main" id="obMain">
        <div class="ob-dot-bg" aria-hidden="true"></div>
        <div class="ob-blob ob-blob--a" aria-hidden="true"></div>
        <div class="ob-blob ob-blob--b" aria-hidden="true"></div>

        <form method="post" action="{{ route('business.onboarding.store') }}" id="obForm" novalidate>
            @csrf

            {{-- ═══════════════════════════════════════════════════ STEP 1 — Welcome --}}
            <div id="obStep1" class="ob-step ob-step--welcome" data-ob-step="1">
                <div class="ob-step__content ob-step__content--narrow">
                    <div class="ob-welcome-icon" aria-hidden="true">
                        <i class="fa fa-rocket"></i>
                    </div>
                    <p class="ob-eyebrow">{{ __('Account created') }}</p>
                    <h1 class="ob-title">{{ __('Welcome, :name!', ['name' => explode(' ', trim($user->name))[0]]) }}</h1>
                    <p class="ob-sub">{{ __("Let's get your workspace ready in four quick steps.") }}</p>
                    <div class="ob-welcome-items">
                        <div class="ob-welcome-item">
                            <span class="ob-welcome-item__num">1</span>
                            <div>
                                <strong>{{ __('Business profile') }}</strong>
                                <p>{{ __('Name, type, and a brief description') }}</p>
                            </div>
                        </div>
                        <div class="ob-welcome-item">
                            <span class="ob-welcome-item__num">2</span>
                            <div>
                                <strong>{{ __('Features') }}</strong>
                                <p>{{ __('Choose which modules to activate') }}</p>
                            </div>
                        </div>
                        <div class="ob-welcome-item">
                            <span class="ob-welcome-item__num">3</span>
                            <div>
                                <strong>{{ __('Location') }}</strong>
                                <p>{{ __('Set up your first branch or site') }}</p>
                            </div>
                        </div>
                        <div class="ob-welcome-item ob-welcome-item--optional">
                            <span class="ob-welcome-item__num ob-welcome-item__num--opt">4</span>
                            <div>
                                <strong>{{ __('Data Vault') }} <span class="ob-optional-badge">{{ __('optional') }}</span></strong>
                                <p>{{ __('Store sensitive data on your own server') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ═══════════════════════════════════════════════════ STEP 2 — Business profile --}}
            <div id="obStep2" class="ob-step" data-ob-step="2" hidden>
                <div class="ob-step__content ob-step__content--narrow">
                    <p class="ob-eyebrow">{{ __('Step 1 of 4') }}</p>
                    <h2 class="ob-title">{{ __('Tell us about your business') }}</h2>
                    <p class="ob-sub">{{ __('This becomes your business profile inside SociBiz.') }}</p>

                    <div class="ob-field" id="obFieldName">
                        <label for="obBizName">{{ __('Business name') }} <span class="ob-req" aria-hidden="true">*</span></label>
                        <input id="obBizName" name="name" type="text" class="ob-input" value="{{ old('name') }}"
                            placeholder="{{ __('e.g. Sunrise Grocery') }}" maxlength="255" autocomplete="organization">
                        <div class="ob-field-error" id="obErrName"></div>
                    </div>

                    <div class="ob-field" id="obFieldCat">
                        <label for="obBizCat">{{ __('Business type') }} <span class="ob-req" aria-hidden="true">*</span></label>
                        <select id="obBizCat" name="company_category_slug" class="ob-select">
                            <option value="">{{ __('Select a category…') }}</option>
                            @foreach($categoryOptions as $opt)
                                <option value="{{ $opt['value'] }}" @selected(old('company_category_slug') === $opt['value'])>{{ $opt['label'] }}</option>
                            @endforeach
                        </select>
                        <div class="ob-field-error" id="obErrCat"></div>
                    </div>

                    <div class="ob-field">
                        <label for="obBizDesc">{{ __('Short description') }} <span class="ob-optional">{{ __('(optional)') }}</span></label>
                        <textarea id="obBizDesc" name="description" class="ob-textarea" maxlength="2000"
                            placeholder="{{ __('What does your business do?') }}">{{ old('description') }}</textarea>
                    </div>
                </div>
            </div>

            {{-- ═══════════════════════════════════════════════════ STEP 3 — Features --}}
            <div id="obStep3" class="ob-step" data-ob-step="3" hidden>
                <div class="ob-step__content ob-step__content--wide">
                    <p class="ob-eyebrow">{{ __('Step 2 of 4') }}</p>
                    <h2 class="ob-title">{{ __('Choose your features') }}</h2>
                    <p class="ob-sub">{{ __('Select the modules your business needs. You can change these later in Settings.') }}</p>

                    <div class="ob-feat-grid">
                        @foreach($obFeatures as $feat)
                            <label class="ob-feat-card {{ ($feat['required'] ?? false) ? 'ob-feat-card--required' : '' }}"
                                   data-feat-key="{{ $feat['key'] }}"
                                   @if($feat['required'] ?? false) data-feat-required="1" @endif
                                   @isset($feat['requires']) data-feat-requires="{{ implode(',', $feat['requires']) }}" @endisset>
                                <input type="checkbox" name="features[{{ $feat['key'] }}]" value="1"
                                    class="ob-feat-check" id="obFeat_{{ $feat['key'] }}"
                                    @if($feat['required'] ?? false) checked disabled @endif>
                                <span class="ob-feat-card__icon" aria-hidden="true">
                                    <i class="fa {{ $feat['icon'] }}"></i>
                                </span>
                                <span class="ob-feat-card__body">
                                    <strong class="ob-feat-card__name">{{ $feat['label'] }}</strong>
                                    <span class="ob-feat-card__desc">{{ $feat['desc'] }}</span>
                                    @isset($feat['requires'])
                                        <span class="ob-feat-card__note">
                                            <i class="fa fa-link" aria-hidden="true"></i>
                                            {{ __('Requires :deps', ['deps' => implode(' + ', array_map(fn($k) => collect($obFeatures)->firstWhere('key', $k)['label'] ?? $k, $feat['requires']))]) }}
                                        </span>
                                    @endisset
                                </span>
                                <span class="ob-feat-card__check" aria-hidden="true">
                                    <i class="fa fa-check"></i>
                                </span>
                            </label>
                        @endforeach
                    </div>

                    <p class="ob-feat-note">
                        <i class="fa fa-circle-info" aria-hidden="true"></i>
                        {{ __('Account Management is always enabled as it powers all financial tracking.') }}
                    </p>
                </div>
            </div>

            {{-- ═══════════════════════════════════════════════════ STEP 4 — Branch/Location --}}
            <div id="obStep4" class="ob-step" data-ob-step="4" hidden>
                <div class="ob-step__content ob-step__content--narrow">
                    <p class="ob-eyebrow">{{ __('Step 3 of 4') }}</p>
                    <h2 class="ob-title">{{ __('Set up your first location') }}</h2>
                    <p class="ob-sub">{{ __('Add details for your main branch or premises. You can add more locations later.') }}</p>

                    <div class="ob-field" id="obFieldBranch">
                        <label for="obBranchName">{{ __('Location name') }} <span class="ob-req" aria-hidden="true">*</span></label>
                        <input id="obBranchName" name="branch_name" type="text" class="ob-input"
                            value="{{ old('branch_name', 'Main Branch') }}"
                            placeholder="{{ __('e.g. Main Branch, Head Office') }}" maxlength="255">
                        <div class="ob-field-error" id="obErrBranch"></div>
                    </div>

                    <div class="ob-field">
                        <label for="obBranchAddr">{{ __('Address') }} <span class="ob-optional">{{ __('(optional)') }}</span></label>
                        <textarea id="obBranchAddr" name="branch_address" class="ob-textarea ob-textarea--sm"
                            placeholder="{{ __('Street, city, postal code…') }}" maxlength="2000">{{ old('branch_address') }}</textarea>
                    </div>

                    <div class="ob-fields-row">
                        <div class="ob-field">
                            <label for="obBranchPhone">{{ __('Phone') }} <span class="ob-optional">{{ __('(optional)') }}</span></label>
                            <input id="obBranchPhone" name="branch_phone" type="tel" class="ob-input"
                                value="{{ old('branch_phone') }}" placeholder="{{ __('+1 555 000 0000') }}" maxlength="40">
                        </div>
                        <div class="ob-field">
                            <label for="obBranchEmail">{{ __('Email') }} <span class="ob-optional">{{ __('(optional)') }}</span></label>
                            <input id="obBranchEmail" name="branch_email" type="email" class="ob-input"
                                value="{{ old('branch_email') }}" placeholder="{{ __('branch@company.com') }}" maxlength="255">
                        </div>
                    </div>

                    <div class="ob-field">
                        <label class="ob-label-inline">{{ __('Multiple warehouse locations?') }}</label>
                        <p class="ob-field-hint">{{ __('Enable if you track stock across separate warehouses or sites. You can change this later.') }}</p>
                        <div class="ob-toggle-row">
                            <button type="button" class="ob-toggle" id="obMultiToggle" role="switch" aria-checked="false">
                                <span class="ob-toggle__track">
                                    <span class="ob-toggle__knob"></span>
                                </span>
                            </button>
                            <span class="ob-toggle__label" id="obMultiLabel">{{ __('Single location') }}</span>
                        </div>
                        <input type="hidden" name="multi_warehouse_branch" id="obMultiField" value="0">
                    </div>
                </div>
            </div>

            {{-- ═══════════════════════════════════════════════════ STEP 5 — Data Vault --}}
            <div id="obStep5" class="ob-step" data-ob-step="5" hidden>
                <div class="ob-step__content ob-step__content--narrow">
                    <p class="ob-eyebrow ob-eyebrow--opt">{{ __('Step 4 of 4 — Optional') }}</p>
                    <h2 class="ob-title">{{ __('Keep sensitive data on your own server') }}</h2>
                    <p class="ob-sub">{{ __('Connect a self-hosted Data Vault to store sales records, employee salaries and payroll on your own infrastructure. Leave blank to skip — you can always configure this later in Settings.') }}</p>

                    {{-- How it works strip --}}
                    <div class="ob-vault-how">
                        <div class="ob-vault-how__item">
                            <span class="ob-vault-how__num">1</span>
                            <span>{{ __('Install the') }} <a href="https://github.com/socibiz/data-vault" target="_blank" rel="noopener" class="ob-link">socibiz-data-vault</a> {{ __('app on your server') }}</span>
                        </div>
                        <div class="ob-vault-how__item">
                            <span class="ob-vault-how__num">2</span>
                            <span>{{ __('Copy the shared secret here — it signs every request with HMAC-SHA256') }}</span>
                        </div>
                        <div class="ob-vault-how__item">
                            <span class="ob-vault-how__num">3</span>
                            <span>{{ __('Enable it and SociBiz routes the selected modules to your vault automatically') }}</span>
                        </div>
                    </div>

                    {{-- Vault URL --}}
                    <div class="ob-field" id="obFieldVaultUrl">
                        <label for="obVaultUrl">{{ __('Vault URL') }} <span class="ob-optional">{{ __('(optional)') }}</span></label>
                        <input id="obVaultUrl" name="vault_url" type="url" class="ob-input"
                            value="{{ old('vault_url') }}"
                            placeholder="{{ __('https://vault.yourcompany.com') }}"
                            maxlength="512" autocomplete="off">
                        <div class="ob-field-error" id="obErrVaultUrl"></div>
                    </div>

                    {{-- Shared Secret --}}
                    <div class="ob-field">
                        <label for="obVaultSecret">{{ __('Shared Secret') }} <span class="ob-optional">{{ __('(min 32 characters)') }}</span></label>
                        <div class="ob-secret-wrap">
                            <input id="obVaultSecret" name="vault_secret" type="password" class="ob-input ob-input--secret"
                                placeholder="{{ __('Paste the secret from your vault .env') }}"
                                maxlength="512" autocomplete="new-password">
                            <button type="button" class="ob-secret-toggle" id="obVaultSecretToggle"
                                    aria-label="{{ __('Show or hide secret') }}">
                                <i class="fa fa-eye" id="obVaultSecretIcon"></i>
                            </button>
                        </div>
                        <p class="ob-field-hint">{{ __('Must match') }} <code>VAULT_SHARED_SECRET</code> {{ __('in your vault') }} <code>.env</code>.</p>
                    </div>

                    {{-- Module routing --}}
                    <div class="ob-field">
                        <label class="ob-label-inline">{{ __('Data modules to route to vault') }}</label>
                        <div class="ob-vault-chips" id="obVaultChips">
                            <label class="ob-vault-chip" data-chip-key="sales">
                                <input type="checkbox" name="vault_modules[]" value="sales" class="ob-vault-chip__check" id="obVaultMod_sales">
                                <span class="ob-vault-chip__box"><i class="fa fa-check"></i></span>
                                <i class="fa fa-receipt" aria-hidden="true"></i>
                                {{ __('Sales') }}
                            </label>
                            <label class="ob-vault-chip" data-chip-key="employees">
                                <input type="checkbox" name="vault_modules[]" value="employees" class="ob-vault-chip__check" id="obVaultMod_employees">
                                <span class="ob-vault-chip__box"><i class="fa fa-check"></i></span>
                                <i class="fa fa-users" aria-hidden="true"></i>
                                {{ __('Employees & Salaries') }}
                            </label>
                            <label class="ob-vault-chip" data-chip-key="payroll">
                                <input type="checkbox" name="vault_modules[]" value="payroll" class="ob-vault-chip__check" id="obVaultMod_payroll">
                                <span class="ob-vault-chip__box"><i class="fa fa-check"></i></span>
                                <i class="fa fa-money-bill-wave" aria-hidden="true"></i>
                                {{ __('Payroll') }}
                            </label>
                        </div>
                        <p class="ob-field-hint">{{ __('Only selected modules route to the vault. Others use SociBiz normally.') }}</p>
                    </div>

                    {{-- Enable toggle --}}
                    <div class="ob-vault-enable-row" id="obVaultEnableRow">
                        <div>
                            <div class="ob-vault-enable-lbl">{{ __('Enable Data Vault routing now') }}</div>
                            <div class="ob-vault-enable-sub">{{ __('If off, the config is saved but routing stays disabled until you activate it in Settings.') }}</div>
                        </div>
                        <button type="button" class="ob-toggle ob-toggle--sm" id="obVaultEnableToggle" role="switch" aria-checked="false">
                            <span class="ob-toggle__track">
                                <span class="ob-toggle__knob"></span>
                            </span>
                        </button>
                        <input type="hidden" name="vault_is_enabled" id="obVaultEnabledField" value="0">
                    </div>

                    <p class="ob-vault-skip-note">
                        <i class="fa fa-circle-info" aria-hidden="true"></i>
                        {{ __('If you leave the Vault URL empty, this step is skipped automatically.') }}
                    </p>
                </div>
            </div>

        </form>
    </div>

    {{-- Bottom action bar --}}
    <div class="ob-actions" id="obActions">
        <div class="ob-actions__inner">
            <button type="button" class="ob-btn ob-btn--back" id="obBackBtn" hidden>
                <i class="fa fa-arrow-left" aria-hidden="true"></i> {{ __('Back') }}
            </button>
            <div class="ob-actions__right">
                <span class="ob-actions__progress" id="obProgress" aria-live="polite"></span>
                <button type="button" class="ob-btn ob-btn--secondary" id="obSkipBtn" hidden>
                    {{ __('Skip') }} <i class="fa fa-forward" aria-hidden="true"></i>
                </button>
                <button type="button" class="ob-btn ob-btn--primary" id="obNextBtn">
                    {{ __('Get started') }} <i class="fa fa-arrow-right" aria-hidden="true"></i>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('auth-styles')
<style>
/* ─── Break auth layout constraints ─── */
.auth-split__visual{display:none!important;}
.auth-split__main{padding:0!important;align-items:stretch!important;background:#f5f5f4!important;}
.auth-shell{max-width:none!important;width:100%!important;height:100vh!important;display:flex!important;position:relative!important;}
.auth-panel{flex:1!important;display:flex!important;height:100vh!important;overflow:hidden!important;background:transparent!important;}

/* ─── Wizard shell ─── */
.ob-wizard{display:flex;width:100%;height:100vh;overflow:hidden;background:#f5f5f4;position:relative;}

/* ─── Left stepper ─── */
.ob-stepper{
    position:fixed;top:0;left:0;bottom:0;z-index:50;width:240px;
    display:flex;flex-direction:column;
    padding:36px 28px;box-sizing:border-box;
    background:rgba(255,255,255,.96);
    backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);
    border-right:1px solid #e5e7eb;
}
@media(max-width:760px){.ob-stepper{display:none;}}
.ob-stepper__brand{display:flex;align-items:center;gap:10px;margin-bottom:36px;}
.ob-stepper__brand-mark{
    width:32px;height:32px;border-radius:8px;display:grid;place-items:center;
    font-size:11px;font-weight:900;background:linear-gradient(135deg,#171717,#404040);color:#facc15;flex-shrink:0;
}
.ob-stepper__brand-name{font-size:12px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#374151;}
.ob-stepper__list{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:0;}
.ob-stepper__item{display:flex;align-items:center;gap:12px;}
.ob-stepper__dot{
    width:28px;height:28px;border-radius:50%;display:grid;place-items:center;flex-shrink:0;
    font-size:11px;font-weight:800;transition:all .3s ease;
    background:#e5e7eb;color:#9ca3af;border:2px solid transparent;
}
.ob-stepper__dot--current{background:#fff;color:#111827;border-color:#111827;box-shadow:0 0 0 4px rgba(17,24,39,.12);}
.ob-stepper__dot--done{background:#111827;color:#fff;border-color:#111827;}
.ob-stepper__dot--opt{background:#f3f4f6;color:#9ca3af;border:2px dashed #d1d5db;}
.ob-stepper__dot--opt-current{background:#fff;color:#6366f1;border-color:#6366f1;border-style:solid;box-shadow:0 0 0 4px rgba(99,102,241,.12);}
.ob-stepper__dot--opt-done{background:#6366f1;color:#fff;border-color:#6366f1;border-style:solid;}
.ob-stepper__lbl{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;transition:color .3s;}
.ob-stepper__lbl--active{color:#111827;}
.ob-stepper__lbl--opt-active{color:#6366f1;}
.ob-stepper__connector{width:2px;height:22px;background:#e5e7eb;margin:3px 0 3px 13px;flex-shrink:0;border-radius:2px;transition:background .3s ease;}
.ob-stepper__connector--done{background:#111827;}

/* ─── Main scrollable area ─── */
.ob-main{
    flex:1;min-width:0;margin-left:240px;height:100vh;
    overflow-y:auto;overflow-x:hidden;
    display:flex;flex-direction:column;align-items:center;justify-content:center;
    padding:clamp(32px,7vh,80px) clamp(20px,5vw,64px) 104px;
    box-sizing:border-box;position:relative;
}
@media(max-width:760px){.ob-main{margin-left:0;}}

.ob-dot-bg{position:absolute;inset:0;pointer-events:none;background-image:radial-gradient(circle,rgba(17,24,39,.055) 1px,transparent 1px);background-size:32px 32px;}
.ob-blob{position:absolute;border-radius:50%;pointer-events:none;filter:blur(90px);opacity:.35;}
.ob-blob--a{width:480px;height:480px;top:-100px;left:-100px;background:radial-gradient(circle,rgba(17,24,39,.5),transparent 70%);}
.ob-blob--b{width:340px;height:340px;bottom:-80px;right:-60px;background:radial-gradient(circle,rgba(250,204,21,.45),transparent 70%);}

/* ─── Steps ─── */
.ob-step{display:none;animation:obFadeUp .28s ease both;position:relative;z-index:1;width:100%;}
.ob-step.ob-step--active{display:block;}
.ob-step__content{width:100%;margin:0 auto;}
.ob-step__content--narrow{max-width:560px;}
.ob-step__content--wide{max-width:960px;}
@keyframes obFadeUp{from{opacity:0;transform:translateY(14px);}to{opacity:1;transform:translateY(0);}}

/* ─── Typography ─── */
.ob-eyebrow{font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:#6b7280;margin:0 0 10px;}
.ob-eyebrow--opt{color:#6366f1;}
.ob-title{font-size:clamp(22px,3.5vw,34px);font-weight:800;color:#111827;margin:0 0 10px;line-height:1.18;letter-spacing:-.025em;}
.ob-sub{font-size:15px;color:#6b7280;margin:0 0 22px;line-height:1.6;}

/* ─── Welcome step ─── */
.ob-step--welcome .ob-step__content{text-align:center;}
.ob-welcome-icon{
    width:56px;height:56px;border-radius:16px;display:grid;place-items:center;
    font-size:22px;color:#fff;background:linear-gradient(135deg,#111827,#374151);
    margin:0 auto 20px;box-shadow:0 8px 24px rgba(17,24,39,.22);
}
.ob-welcome-items{display:grid;gap:8px;margin-top:12px;text-align:left;}
.ob-welcome-item{
    display:flex;align-items:flex-start;gap:14px;
    padding:12px 14px;border-radius:12px;
    background:#fff;border:1px solid #e5e7eb;
}
.ob-welcome-item--optional{border-style:dashed;border-color:#c7d2fe;background:#fafafa;}
.ob-welcome-item__num{
    width:24px;height:24px;border-radius:50%;display:grid;place-items:center;flex-shrink:0;
    font-size:11px;font-weight:800;background:#111827;color:#fff;
}
.ob-welcome-item__num--opt{background:#6366f1;}
.ob-welcome-item strong{display:block;font-size:13.5px;font-weight:700;color:#111827;margin:2px 0 2px;}
.ob-welcome-item p{margin:0;font-size:12.5px;color:#6b7280;line-height:1.4;}
.ob-optional-badge{
    display:inline-block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;
    padding:1px 6px;border-radius:4px;background:#e0e7ff;color:#4338ca;vertical-align:middle;margin-left:4px;
}

/* ─── Form fields ─── */
.ob-field{margin-bottom:18px;}
.ob-field label,.ob-label-inline{display:block;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#374151;margin-bottom:7px;}
.ob-req{color:#ef4444;}
.ob-optional{font-size:11px;font-weight:500;color:#9ca3af;text-transform:none;letter-spacing:0;}
.ob-field-hint{font-size:12px;color:#9ca3af;margin:5px 0 0;line-height:1.5;}
.ob-field-hint code{font-size:11.5px;background:#f3f4f6;padding:1px 5px;border-radius:4px;color:#374151;}
.ob-input,.ob-select,.ob-textarea{
    width:100%;padding:13px 16px;border-radius:10px;
    border:1.5px solid #d1d5db;background:#fff;
    color:#111827;font-size:15px;font-family:inherit;
    outline:none;transition:border-color .2s,box-shadow .2s;box-sizing:border-box;
}
.ob-input::placeholder,.ob-textarea::placeholder{color:#9ca3af;}
.ob-input:focus,.ob-select:focus,.ob-textarea:focus{border-color:#111827;box-shadow:0 0 0 3px rgba(17,24,39,.1);}
.ob-input.ob-input--error,.ob-select.ob-select--error{border-color:#ef4444;box-shadow:0 0 0 3px rgba(239,68,68,.12);}
.ob-select{
    appearance:none;padding-right:40px;cursor:pointer;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%236b7280' d='M1 1l5 5 5-5'/%3E%3C/svg%3E");
    background-repeat:no-repeat;background-position:right 14px center;
}
.ob-textarea{resize:vertical;min-height:100px;line-height:1.55;}
.ob-textarea--sm{min-height:72px;}
.ob-field-error{font-size:12px;color:#ef4444;margin-top:4px;min-height:16px;}
.ob-fields-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
@media(max-width:560px){.ob-fields-row{grid-template-columns:1fr;}}
.ob-link{color:#4f46e5;text-decoration:underline;text-underline-offset:2px;}
.ob-link:hover{color:#3730a3;}

/* ─── Feature cards ─── */
.ob-feat-grid{
    display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));
    gap:10px;margin-bottom:14px;
}
.ob-feat-card{
    position:relative;display:flex;align-items:flex-start;gap:12px;
    padding:14px;border-radius:12px;
    border:1.5px solid #e5e7eb;background:#fff;
    cursor:pointer;transition:border-color .2s,background .2s;user-select:none;
}
.ob-feat-card:hover{border-color:#d1d5db;background:#f9fafb;}
.ob-feat-card.ob-feat-card--selected{border-color:#111827;background:color-mix(in srgb,#111827 5%,#fff);}
.ob-feat-card.ob-feat-card--required{border-color:rgba(17,24,39,.3);background:#f9fafb;cursor:default;}
.ob-feat-check{position:absolute;opacity:0;pointer-events:none;}
.ob-feat-card__icon{width:32px;height:32px;border-radius:8px;display:grid;place-items:center;flex-shrink:0;font-size:13px;background:#f3f4f6;color:#6b7280;transition:background .2s,color .2s;}
.ob-feat-card--selected .ob-feat-card__icon{background:#111827;color:#fff;}
.ob-feat-card--required .ob-feat-card__icon{background:#111827;color:#facc15;}
.ob-feat-card__body{flex:1;min-width:0;}
.ob-feat-card__name{display:block;font-size:13px;font-weight:700;color:#111827;margin-bottom:2px;}
.ob-feat-card__desc{display:block;font-size:12px;color:#6b7280;line-height:1.4;}
.ob-feat-card__note{display:block;font-size:11px;color:#9ca3af;margin-top:4px;font-weight:600;}
.ob-feat-card__note .fa{font-size:10px;}
.ob-feat-card__check{
    position:absolute;top:10px;right:10px;
    width:18px;height:18px;border-radius:50%;display:grid;place-items:center;
    background:#e5e7eb;color:transparent;font-size:8px;transition:background .2s,color .2s;
}
.ob-feat-card--selected .ob-feat-card__check,.ob-feat-card--required .ob-feat-card__check{background:#111827;color:#fff;}
.ob-feat-note{font-size:12.5px;color:#6b7280;display:flex;align-items:flex-start;gap:7px;margin:0;}
.ob-feat-note .fa{color:#9ca3af;margin-top:2px;flex-shrink:0;}

/* ─── Toggle ─── */
.ob-toggle-row{display:flex;align-items:center;gap:12px;margin-top:4px;}
.ob-toggle{
    display:inline-flex;align-items:center;padding:0;border:none;
    background:none;cursor:pointer;outline:none;-webkit-appearance:none;appearance:none;flex-shrink:0;
}
.ob-toggle:focus-visible .ob-toggle__track{outline:3px solid rgba(17,24,39,.25);outline-offset:2px;}
.ob-toggle__track{position:relative;width:48px;height:26px;border-radius:999px;background:#d1d5db;transition:background .2s;}
.ob-toggle[aria-checked="true"] .ob-toggle__track{background:#111827;}
.ob-toggle__knob{position:absolute;top:3px;left:3px;width:20px;height:20px;border-radius:50%;background:#fff;border:1px solid rgba(0,0,0,.12);transition:left .22s ease;box-shadow:0 1px 3px rgba(0,0,0,.18);}
.ob-toggle[aria-checked="true"] .ob-toggle__knob{left:calc(100% - 23px);}
.ob-toggle__label{font-size:14px;font-weight:600;color:#374151;}
.ob-toggle--sm .ob-toggle__track{width:40px;height:22px;}
.ob-toggle--sm .ob-toggle__knob{width:16px;height:16px;top:3px;}
.ob-toggle--sm[aria-checked="true"] .ob-toggle__knob{left:calc(100% - 19px);}
.ob-toggle--sm[aria-checked="true"] .ob-toggle__track{background:#6366f1;}

/* ─── Vault-specific step 5 styles ─── */
.ob-vault-how{
    display:flex;flex-direction:column;gap:8px;
    padding:14px 16px;border-radius:12px;
    background:#f0f0ff;border:1px solid #c7d2fe;
    margin-bottom:22px;
}
.ob-vault-how__item{display:flex;align-items:flex-start;gap:10px;font-size:13px;color:#374151;line-height:1.5;}
.ob-vault-how__num{
    width:20px;height:20px;border-radius:50%;display:grid;place-items:center;flex-shrink:0;
    font-size:10px;font-weight:800;background:#6366f1;color:#fff;margin-top:1px;
}

.ob-secret-wrap{position:relative;}
.ob-input--secret{padding-right:44px;}
.ob-secret-toggle{
    position:absolute;right:10px;top:50%;transform:translateY(-50%);
    background:none;border:none;cursor:pointer;color:#9ca3af;font-size:14px;
    padding:4px;outline:none;
}
.ob-secret-toggle:hover{color:#374151;}
.ob-secret-toggle:focus-visible{outline:2px solid #111827;border-radius:4px;}

.ob-vault-chips{display:flex;flex-wrap:wrap;gap:8px;margin-top:2px;}
.ob-vault-chip{
    display:inline-flex;align-items:center;gap:7px;
    padding:8px 13px;border-radius:10px;
    border:1.5px solid #d1d5db;background:#fff;
    cursor:pointer;font-size:13px;color:#374151;font-weight:500;
    user-select:none;transition:border-color .15s,background .15s;
}
.ob-vault-chip:hover{border-color:#a5b4fc;background:#f5f3ff;}
.ob-vault-chip__check{
    width:15px;height:15px;border-radius:4px;border:1.5px solid #d1d5db;
    display:inline-flex;align-items:center;justify-content:center;
    font-size:8px;color:transparent;flex-shrink:0;transition:all .15s;
}
.ob-vault-chip.is-checked{border-color:#6366f1;background:#eef2ff;}
.ob-vault-chip.is-checked .ob-vault-chip__check{background:#6366f1;border-color:#6366f1;color:#fff;}
.ob-vault-chip input[type="checkbox"]{position:absolute;opacity:0;width:0;height:0;}

.ob-vault-enable-row{
    display:flex;align-items:center;gap:14px;
    padding:14px 16px;border-radius:12px;
    border:1px solid #d1d5db;background:#f9fafb;
    margin-top:4px;
}
.ob-vault-enable-row>div{flex:1;min-width:0;}
.ob-vault-enable-lbl{font-size:13.5px;font-weight:700;color:#111827;margin-bottom:2px;}
.ob-vault-enable-sub{font-size:12px;color:#6b7280;line-height:1.4;}

.ob-vault-skip-note{
    font-size:12.5px;color:#6b7280;display:flex;align-items:flex-start;gap:7px;
    margin:16px 0 0;line-height:1.5;
}
.ob-vault-skip-note .fa{color:#a5b4fc;flex-shrink:0;margin-top:2px;}

/* ─── Bottom action bar ─── */
.ob-actions{
    position:fixed;bottom:0;left:240px;right:0;z-index:50;
    padding:12px clamp(20px,5vw,64px);
    background:rgba(245,245,244,.94);
    backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);
    border-top:1px solid #e5e7eb;
}
@media(max-width:760px){.ob-actions{left:0;}}
.ob-actions__inner{display:flex;align-items:center;justify-content:space-between;gap:12px;}
.ob-actions__right{display:flex;align-items:center;gap:10px;}
.ob-actions__progress{font-size:13px;color:#9ca3af;font-weight:600;}

.ob-btn{
    display:inline-flex;align-items:center;gap:8px;
    padding:11px 22px;border-radius:10px;font-size:14px;font-weight:700;
    font-family:inherit;cursor:pointer;transition:all .18s ease;border:1.5px solid;
}
.ob-btn--back{background:transparent;border-color:#d1d5db;color:#6b7280;}
.ob-btn--back:hover{border-color:#9ca3af;color:#374151;background:#f9fafb;}
.ob-btn--secondary{background:transparent;border-color:#d1d5db;color:#6b7280;font-size:13px;padding:9px 16px;}
.ob-btn--secondary:hover{border-color:#a5b4fc;color:#4338ca;background:#f5f3ff;}
.ob-btn--primary{background:#111827;border-color:#111827;color:#fff;box-shadow:0 4px 14px rgba(17,24,39,.3);}
.ob-btn--primary:hover{background:#374151;border-color:#374151;}
.ob-btn--primary:active{transform:translateY(1px);}
.ob-btn--submit{background:#16a34a;border-color:#16a34a;}
.ob-btn--submit:hover{background:#15803d;border-color:#15803d;}
.ob-btn--vault-submit{background:#6366f1;border-color:#6366f1;}
.ob-btn--vault-submit:hover{background:#4f46e5;border-color:#4f46e5;}
</style>
@endpush

@push('auth-scripts')
<script>
(function () {
    var TOTAL_STEPS = 5;
    var currentStep = 1;

    var form    = document.getElementById('obForm');
    var nextBtn = document.getElementById('obNextBtn');
    var backBtn = document.getElementById('obBackBtn');
    var skipBtn = document.getElementById('obSkipBtn');
    var prog    = document.getElementById('obProgress');

    function getStep(n) { return document.getElementById('obStep' + n); }
    function getDot(n)  { return document.querySelector('[data-stepper-dot="' + n + '"]'); }
    function getLbl(n)  { return document.querySelector('[data-stepper-lbl="' + n + '"]'); }
    function getConn(n) { return document.querySelector('[data-stepper-conn="' + n + '"]'); }

    function updateStepper(active) {
        for (var i = 1; i <= TOTAL_STEPS; i++) {
            var dot  = getDot(i);
            var lbl  = getLbl(i);
            var conn = getConn(i);
            if (!dot) continue;
            dot.classList.remove(
                'ob-stepper__dot--current','ob-stepper__dot--done',
                'ob-stepper__dot--opt','ob-stepper__dot--opt-current','ob-stepper__dot--opt-done'
            );
            if (lbl) lbl.classList.remove('ob-stepper__lbl--active','ob-stepper__lbl--opt-active');
            if (conn) conn.classList.remove('ob-stepper__connector--done');

            if (i === TOTAL_STEPS) {
                if (i < active) {
                    dot.classList.add('ob-stepper__dot--opt-done');
                } else if (i === active) {
                    dot.classList.add('ob-stepper__dot--opt-current');
                    if (lbl) lbl.classList.add('ob-stepper__lbl--opt-active');
                } else {
                    dot.classList.add('ob-stepper__dot--opt');
                }
            } else {
                if (i < active) {
                    dot.classList.add('ob-stepper__dot--done');
                    if (conn) conn.classList.add('ob-stepper__connector--done');
                } else if (i === active) {
                    dot.classList.add('ob-stepper__dot--current');
                    if (lbl) lbl.classList.add('ob-stepper__lbl--active');
                }
            }
        }
    }

    function setStep(n) {
        var prev = getStep(currentStep);
        if (prev) { prev.classList.remove('ob-step--active'); prev.hidden = true; }

        currentStep = n;
        var curr = getStep(n);
        if (curr) { curr.hidden = false; curr.classList.add('ob-step--active'); }

        updateStepper(n);
        backBtn.hidden = (n === 1);
        skipBtn.hidden = (n !== TOTAL_STEPS);

        if (n === TOTAL_STEPS) {
            nextBtn.innerHTML = '<i class="fa fa-check" aria-hidden="true"></i> {{ __("Finish setup") }}';
            nextBtn.classList.add('ob-btn--vault-submit');
            nextBtn.classList.remove('ob-btn--submit');
        } else if (n === TOTAL_STEPS - 1) {
            nextBtn.innerHTML = '{{ __("Continue") }} <i class="fa fa-arrow-right" aria-hidden="true"></i>';
            nextBtn.classList.remove('ob-btn--submit','ob-btn--vault-submit');
        } else if (n === 1) {
            nextBtn.innerHTML = '{{ __("Get started") }} <i class="fa fa-arrow-right" aria-hidden="true"></i>';
            nextBtn.classList.remove('ob-btn--submit','ob-btn--vault-submit');
        } else {
            nextBtn.innerHTML = '{{ __("Continue") }} <i class="fa fa-arrow-right" aria-hidden="true"></i>';
            nextBtn.classList.remove('ob-btn--submit','ob-btn--vault-submit');
        }

        if (n > 1 && n < TOTAL_STEPS) {
            prog.textContent = '{{ __("Step") }} ' + (n - 1) + ' {{ __("of") }} 4';
        } else if (n === TOTAL_STEPS) {
            prog.textContent = '{{ __("Optional") }}';
        } else {
            prog.textContent = '';
        }

        window.scrollTo(0, 0);
        var main = document.getElementById('obMain');
        if (main) main.scrollTop = 0;
    }

    function clearError(inputEl, errEl) {
        if (inputEl) inputEl.classList.remove('ob-input--error','ob-select--error');
        if (errEl)   errEl.textContent = '';
    }
    function showError(inputEl, errEl, msg) {
        if (inputEl) inputEl.classList.add(inputEl.tagName === 'SELECT' ? 'ob-select--error' : 'ob-input--error');
        if (errEl)   errEl.textContent = msg;
    }

    function validateStep(n) {
        if (n === 2) {
            var nameEl  = document.getElementById('obBizName');
            var catEl   = document.getElementById('obBizCat');
            var errName = document.getElementById('obErrName');
            var errCat  = document.getElementById('obErrCat');
            clearError(nameEl, errName); clearError(catEl, errCat);
            var ok = true;
            if (!nameEl.value.trim()) {
                showError(nameEl, errName, '{{ __("Business name is required.") }}');
                nameEl.focus(); ok = false;
            }
            if (ok && !catEl.value) {
                showError(catEl, errCat, '{{ __("Please select a business type.") }}');
                catEl.focus(); ok = false;
            }
            return ok;
        }
        if (n === 4) {
            var branchEl  = document.getElementById('obBranchName');
            var errBranch = document.getElementById('obErrBranch');
            clearError(branchEl, errBranch);
            if (!branchEl.value.trim()) {
                showError(branchEl, errBranch, '{{ __("Location name is required.") }}');
                branchEl.focus(); return false;
            }
        }
        if (n === 5) {
            var vaultUrl    = document.getElementById('obVaultUrl');
            var errVaultUrl = document.getElementById('obErrVaultUrl');
            clearError(vaultUrl, errVaultUrl);
            if (vaultUrl.value.trim() && !vaultUrl.value.trim().match(/^https?:\/\/.+/)) {
                showError(vaultUrl, errVaultUrl, '{{ __("Enter a valid URL starting with http:// or https://") }}');
                vaultUrl.focus(); return false;
            }
        }
        return true;
    }

    nextBtn.addEventListener('click', function () {
        if (currentStep < TOTAL_STEPS) {
            if (!validateStep(currentStep)) return;
            setStep(currentStep + 1);
        } else {
            if (!validateStep(currentStep)) return;
            form.submit();
        }
    });

    backBtn.addEventListener('click', function () {
        if (currentStep > 1) setStep(currentStep - 1);
    });

    skipBtn.addEventListener('click', function () {
        /* Clear vault fields so the server ignores them, then submit */
        var vaultUrl = document.getElementById('obVaultUrl');
        if (vaultUrl) vaultUrl.value = '';
        form.submit();
    });

    /* ── Feature card toggles ── */
    document.querySelectorAll('.ob-feat-card:not(.ob-feat-card--required)').forEach(function (card) {
        card.setAttribute('tabindex', '0');
        card.addEventListener('click', function () {
            var key   = card.dataset.featKey;
            var check = document.getElementById('obFeat_' + key);
            if (!check || check.disabled) return;
            check.checked = !check.checked;
            card.classList.toggle('ob-feat-card--selected', check.checked);

            var requires = card.dataset.featRequires ? card.dataset.featRequires.split(',') : [];
            if (check.checked) {
                requires.forEach(function (dep) {
                    var depCard  = document.querySelector('[data-feat-key="' + dep + '"]');
                    var depCheck = document.getElementById('obFeat_' + dep);
                    if (depCheck && !depCheck.checked && !depCheck.disabled) {
                        depCheck.checked = true;
                        if (depCard) depCard.classList.add('ob-feat-card--selected');
                    }
                });
            }
            document.querySelectorAll('[data-feat-requires]').forEach(function (depCard) {
                var depReqs  = depCard.dataset.featRequires.split(',');
                var depKey   = depCard.dataset.featKey;
                var depCheck = document.getElementById('obFeat_' + depKey);
                if (!depCheck || depCheck.disabled) return;
                var allMet = depReqs.every(function (r) {
                    var rCheck = document.getElementById('obFeat_' + r);
                    return rCheck && (rCheck.checked || rCheck.disabled);
                });
                if (!allMet && depCheck.checked) {
                    depCheck.checked = false;
                    depCard.classList.remove('ob-feat-card--selected');
                }
            });
        });
        card.addEventListener('keydown', function (e) {
            if (e.key === ' ' || e.key === 'Enter') { e.preventDefault(); card.click(); }
        });
    });

    /* ── Multi-warehouse toggle ── */
    var multiToggle = document.getElementById('obMultiToggle');
    var multiField  = document.getElementById('obMultiField');
    var multiLabel  = document.getElementById('obMultiLabel');
    if (multiToggle) {
        multiToggle.addEventListener('click', function () {
            var on = multiToggle.getAttribute('aria-checked') !== 'true';
            multiToggle.setAttribute('aria-checked', on ? 'true' : 'false');
            multiField.value = on ? '1' : '0';
            multiLabel.textContent = on ? '{{ __("Multiple warehouse locations") }}' : '{{ __("Single location") }}';
        });
    }

    /* ── Vault secret show/hide ── */
    var vaultSecretInput  = document.getElementById('obVaultSecret');
    var vaultSecretIcon   = document.getElementById('obVaultSecretIcon');
    var vaultSecretToggle = document.getElementById('obVaultSecretToggle');
    if (vaultSecretToggle && vaultSecretInput) {
        vaultSecretToggle.addEventListener('click', function () {
            var hidden = vaultSecretInput.type === 'password';
            vaultSecretInput.type = hidden ? 'text' : 'password';
            vaultSecretIcon.className = hidden ? 'fa fa-eye-slash' : 'fa fa-eye';
        });
    }

    /* ── Vault module chip toggles ── */
    document.querySelectorAll('.ob-vault-chip').forEach(function (chip) {
        var cb = chip.querySelector('input[type="checkbox"]');
        if (!cb) return;
        chip.addEventListener('click', function () {
            cb.checked = !cb.checked;
            chip.classList.toggle('is-checked', cb.checked);
        });
        chip.addEventListener('keydown', function (e) {
            if (e.key === ' ' || e.key === 'Enter') { e.preventDefault(); chip.click(); }
        });
        chip.setAttribute('tabindex', '0');
    });

    /* ── Vault enable toggle ── */
    var vaultEnableToggle = document.getElementById('obVaultEnableToggle');
    var vaultEnabledField = document.getElementById('obVaultEnabledField');
    if (vaultEnableToggle) {
        vaultEnableToggle.addEventListener('click', function () {
            var on = vaultEnableToggle.getAttribute('aria-checked') !== 'true';
            vaultEnableToggle.setAttribute('aria-checked', on ? 'true' : 'false');
            vaultEnabledField.value = on ? '1' : '0';
        });
    }

    setStep(1);
})();
</script>
@endpush
