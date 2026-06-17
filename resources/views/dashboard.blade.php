@extends('theme::layouts.app', ['title' => 'Overview', 'heading' => 'Overview'])

@section('content')
@php
    $business = \Modules\Business\Models\Business::currentForNavbar(auth()->user());
    $hasBankAccountForBusiness = $business
        ? \Modules\Account\Models\Account::query()
            ->where('user_id', auth()->id())
            ->where('business_id', $business->id)
            ->exists()
        : false;

    $wizFeatureItems = [
        ['key' => 'account_management',   'label' => 'Account Management',   'icon' => 'fa-wallet',             'image' => 'account-management.png',        'desc' => 'Track bank accounts, income, expenses and ledgers',      'required' => true],
        ['key' => 'bill_management',      'label' => 'Bill Management',       'icon' => 'fa-file-invoice-dollar','image' => 'bill-management.png',           'desc' => 'Manage recurring utility and service bills with reminders'],
        ['key' => 'human_resources',      'label' => 'Human Resources',       'icon' => 'fa-users-gear',         'image' => 'human-resource-management.png', 'desc' => 'Payroll, leave, attendance and employee management'],
        ['key' => 'product_management',   'label' => 'Product Management',    'icon' => 'fa-boxes-stacked',      'image' => 'product-management.svg',        'desc' => 'Product catalogue, pricing, variants and categories'],
        ['key' => 'stock_management',     'label' => 'Stock Management',      'icon' => 'fa-warehouse',          'image' => 'stock-management.png',          'desc' => 'Inventory levels, stock transfers and low-stock alerts'],
        ['key' => 'point_of_sale',        'label' => 'Point of Sale',         'icon' => 'fa-cash-register',      'image' => 'point-of-sale.png',             'desc' => 'Counter sales, receipts, daily float and cashier shifts', 'dependsOn' => ['product_management', 'stock_management']],
        ['key' => 'social_media_campaign','label' => 'Social Media Campaign', 'icon' => 'fa-bullhorn',           'image' => 'social-media-campaign.png',     'desc' => 'Design, schedule and track marketing campaigns'],
    ];
@endphp

@if(($needsWarehouseBranchIntro ?? false) === true)
<style>
.wh-intro-overlay{position:fixed;inset:0;z-index:330;display:flex;align-items:center;justify-content:center;padding:max(12px,2vw);box-sizing:border-box;pointer-events:auto;}
.wh-intro-backdrop{position:absolute;inset:0;background:rgba(15,23,42,.5);}
:is(html[data-theme="light"],html[data-theme="light_blue"]) .wh-intro-backdrop{background:rgba(17,24,39,.35);}
.wh-intro-shell{position:relative;z-index:1;width:100%;max-width:520px;margin:0 auto;}
.wh-intro-card{
    position:relative;display:flex;flex-direction:column;justify-content:flex-start;box-sizing:border-box;
    overflow:auto;overflow-x:hidden;max-height:min(90vh,680px);
    height:fit-content;width:100%;max-width:520px;margin:0 auto;
    padding:20px 20px 16px;border-radius:12px;border:1px solid var(--border);
    background:var(--card);
    box-shadow:0 12px 32px rgba(0,0,0,.26);
}
:is(html[data-theme="light"],html[data-theme="light_blue"]) .wh-intro-card{box-shadow:0 12px 28px rgba(0,0,0,.12);}
.wh-intro-icon{
    width:40px;height:40px;display:grid;place-items:center;border-radius:8px;margin:0 auto 12px;
    border:1px solid var(--border);background:color-mix(in srgb,var(--card) 94%,var(--border));
    color:var(--muted);font-size:17px;
}
.wh-intro-title{margin:0 0 6px;text-align:center;font-size:clamp(16px,2.2vw,18px);font-weight:700;letter-spacing:-.02em;line-height:1.3;color:var(--text);}
.wh-intro-copy{margin:0 0 12px;text-align:center;font-size:13px;line-height:1.5;color:var(--muted);}
.wh-intro-step--2 .wh-intro-copy{margin-bottom:6px;}
.wh-intro-pill-strong{font-size:12px;font-weight:800;color:var(--text);letter-spacing:-.01em;transition:color .22s ease,opacity .22s ease;}
.wh-intro-muted-soft{font-size:12px;font-weight:500;color:var(--muted);opacity:.68;transition:color .22s ease,opacity .22s ease;}
.wh-intro-switch-wrap{
    display:flex;flex-direction:column;align-items:center;gap:10px;margin-bottom:10px;
}
.wh-intro-labels{display:flex;align-items:center;justify-content:center;gap:14px;width:100%;}
.wh-intro-switch{
    display:inline-flex;align-items:center;justify-content:center;
    margin:2px auto 0;padding:0;border:0;background:none;cursor:pointer;
    outline:none;-webkit-appearance:none;appearance:none;
}
.wh-intro-switch:focus-visible .wh-intro-switch-track{outline:2px solid color-mix(in srgb,var(--primary) 55%,transparent);outline-offset:2px;}
.wh-intro-switch-track{
    position:relative;width:48px;height:26px;border-radius:999px;flex-shrink:0;
    background:color-mix(in srgb,var(--muted) 40%,var(--border));
    transition:background .2s ease;
}
.wh-intro-switch[aria-checked="true"] .wh-intro-switch-track{background:#22c55e;}
.wh-intro-switch-knob{
    position:absolute;top:3px;left:3px;
    width:20px;height:20px;border-radius:50%;box-sizing:border-box;
    background:#fff;border:1px solid color-mix(in srgb,var(--border) 80%,transparent);
    transition:left .22s ease;
    box-shadow:0 1px 2px rgba(0,0,0,.2);
}
.wh-intro-switch[aria-checked="true"] .wh-intro-switch-knob{
    left:calc(100% - 3px - 20px);
}
.wh-intro-form{margin:0;padding:0;width:100%;flex:0 0 auto;box-sizing:border-box;min-height:0;}
.wh-intro-wizard-stack{display:flex;flex-direction:column;gap:6px;width:100%;align-items:stretch;flex:0 0 auto;box-sizing:border-box;min-height:0;}
.wh-intro-step{width:100%;}
/* [hidden] alone loses to #ids in the cascade—force hide when a step is not active */
#wh-step-1[hidden],
#wh-step-2[hidden]{display:none!important;}
#wh-step-1{display:flex;flex-direction:column;align-items:center;gap:8px;width:100%;box-sizing:border-box;}
#wh-step-2{display:flex;flex-direction:column;align-items:stretch;gap:6px;width:100%;box-sizing:border-box;flex:0 0 auto;min-height:0;}
#wh-intro-next{width:100%;max-width:280px;align-self:center;box-sizing:border-box;}
.wh-intro-step-h{display:flex;flex-direction:column;align-items:center;text-align:center;width:100%;}
.wh-intro-stepbadge{margin:0 0 6px;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);}
.wh-intro-step--2 .wh-intro-title{margin:0 0 4px;text-align:center;}
.wh-intro-step--2 .wh-intro-copy{text-align:center;}
.wh-intro-back{display:block;margin:0 0 4px;padding:4px 2px;font-size:12px;font-weight:600;color:var(--primary);cursor:pointer;background:none;border:0;text-decoration:none;text-align:left;}
.wh-intro-back:hover{text-decoration:underline;}
.wh-intro-fieldset{border:none;margin:0;padding:0;min-width:0;flex:0 0 auto;min-height:0;}
.wh-intro-fieldset:disabled{opacity:.55;}
.wh-intro-branch-grid{display:grid;gap:6px;width:100%;text-align:left;align-content:start;}
@media (min-width:480px){
    .wh-intro-branch-grid--2{grid-template-columns:repeat(2,minmax(0,1fr));gap:6px 10px;}
}
.wh-intro-branch-grid .branch-field label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:3px;}
.wh-intro-branch-grid .branch-field input,.wh-intro-branch-grid .branch-field textarea{width:100%;box-sizing:border-box;padding:7px 9px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);}
.wh-intro-branch-grid .branch-field textarea{min-height:52px;line-height:1.45;resize:vertical;font-family:inherit;}
.wh-intro-branch-grid .branch-active-row{display:flex;align-items:center;justify-content:space-between;gap:10px;width:100%;padding:8px 12px;box-sizing:border-box;border-radius:10px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 94%,transparent);}
.wh-intro-branch-grid .branch-active-row__lbl{margin:0;font-size:13px;font-weight:600;color:var(--text);cursor:pointer;}
.wh-intro-branch-grid .branch-switch{position:relative;display:inline-block;width:46px;height:26px;flex-shrink:0;}
.wh-intro-branch-grid .branch-switch input{opacity:0;width:0;height:0;margin:0;position:absolute;}
.wh-intro-branch-grid .branch-switch-slider{position:absolute;inset:0;cursor:pointer;background:#475569;border-radius:999px;transition:.2s;}
.wh-intro-branch-grid .branch-switch-slider:before{content:"";position:absolute;height:20px;width:20px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.2s;box-shadow:0 1px 3px rgba(0,0,0,.22);}
.wh-intro-branch-grid .branch-switch input:checked + .branch-switch-slider{background:#22c55e;}
.wh-intro-branch-grid .branch-switch input:checked + .branch-switch-slider:before{transform:translateX(20px);}
:is(html[data-theme="light"],html[data-theme="light_blue"]) .wh-intro-branch-grid .branch-switch-slider{background:color-mix(in srgb,#475569 75%,var(--border));}
.wh-intro-branch-grid .branch-switch input:focus-visible + .branch-switch-slider{box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 45%,transparent);}
.wh-intro-submit{width:100%;max-width:280px;display:inline-flex;align-items:center;justify-content:center;gap:10px;padding:11px 20px;font-size:13px;font-weight:800;border-radius:12px;box-sizing:border-box;}
#wh-intro-finish.wh-intro-submit{
    align-self:stretch;width:100%;max-width:none;margin-top:8px;margin-bottom:0;display:flex;justify-content:center;box-sizing:border-box;padding:10px 16px;
}
.wh-intro-submit:disabled{opacity:.55;cursor:wait;}
html.wh-intro-html-noscroll,html.wh-intro-html-noscroll body{overflow:hidden;height:100%;}
</style>
<div id="wh-intro-overlay" class="wh-intro-overlay" role="dialog" aria-modal="true" aria-labelledby="wh-intro-title">
    <div class="wh-intro-backdrop"></div>
    <div class="wh-intro-shell">
        <div class="wh-intro-card">
            <form class="wh-intro-form wh-intro-form--wizard" method="post" action="{{ route('business.warehouse-intro.store') }}" id="wh-intro-form" novalidate>
                <div class="wh-intro-wizard-stack">
                @csrf
                <input type="hidden" name="multi_warehouse_branch" id="wh-intro-mw-val" value="0">

                <div id="wh-step-1" class="wh-intro-step" aria-hidden="false">
                    <div class="wh-intro-step-h">
                        <div class="wh-intro-icon" aria-hidden="true"><i class="fa fa-warehouse"></i></div>
                        <p class="wh-intro-stepbadge">Step 1 of 2</p>
                        <h2 class="wh-intro-title" id="wh-intro-title">Multiple warehouses or branches?</h2>
                        <p class="wh-intro-copy">Choose how many sites you operate. Next, you’ll add your primary location—we only show this onboarding once.</p>
                        <div class="wh-intro-switch-wrap">
                            <div class="wh-intro-labels">
                                <span id="wh-intro-lbl-single" class="wh-intro-pill-strong">Single location</span>
                                <span id="wh-intro-lbl-multi" class="wh-intro-muted-soft">Multi locations</span>
                            </div>
                            <button type="button" class="wh-intro-switch" id="wh-intro-switch" role="switch" aria-checked="false" aria-labelledby="wh-intro-label-toggle">
                                <span class="sr-only wh-intro-visually-hidden" id="wh-intro-label-toggle">Enable multi warehouse and branch mode</span>
                                <span class="wh-intro-switch-track" aria-hidden="true">
                                    <span class="wh-intro-switch-knob"></span>
                                </span>
                            </button>
                        </div>
                    </div>
                    <button type="button" class="linkbtn wh-intro-submit" id="wh-intro-next">Continue</button>
                </div>

                <div id="wh-step-2" class="wh-intro-step wh-intro-step--2" hidden aria-hidden="true">
                    <button type="button" class="wh-intro-back" id="wh-intro-back">← Back</button>
                    <p class="wh-intro-stepbadge">Step 2 of 2</p>
                    <h2 class="wh-intro-title" id="wh-intro-branch-head">Your primary location</h2>
                    <p class="wh-intro-copy" id="wh-intro-branch-copy">Add the details we’ll attach to <strong>{{ $business?->name ?? 'your business' }}</strong>.</p>
                    <fieldset class="wh-intro-fieldset" id="wh-intro-branch-fieldset" disabled>
                        <div class="wh-intro-branch-grid wh-intro-branch-grid--2">
                            @include('business::branches.partials.branch-fields-body', ['fieldIdPrefix' => 'wh-intro-b', 'requireName' => false, 'defaultBranchName' => $business?->name ?? ''])
                        </div>
                    </fieldset>
                    <button type="submit" class="linkbtn wh-intro-submit" id="wh-intro-finish" disabled>Finish setup</button>
                </div>
                </div>
            </form>
        </div>
    </div>
</div>
<style>
.wh-intro-visually-hidden{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;}
</style>
<script>
(function(){
    var overlay = document.getElementById('wh-intro-overlay');
    if (!overlay) return;
    document.documentElement.classList.add('wh-intro-html-noscroll');
    var toggle = document.getElementById('wh-intro-switch');
    var hiddenMw = document.getElementById('wh-intro-mw-val');
    var lblSingle = document.getElementById('wh-intro-lbl-single');
    var lblMulti = document.getElementById('wh-intro-lbl-multi');
    var form = document.getElementById('wh-intro-form');
    var step1 = document.getElementById('wh-step-1');
    var step2 = document.getElementById('wh-step-2');
    var nextBtn = document.getElementById('wh-intro-next');
    var backBtn = document.getElementById('wh-intro-back');
    var branchFs = document.getElementById('wh-intro-branch-fieldset');
    var finishBtn = document.getElementById('wh-intro-finish');
    var nameInput = document.getElementById('wh-intro-b-name');
    function sync(on){
        toggle.setAttribute('aria-checked', on ? 'true' : 'false');
        hiddenMw.value = on ? '1' : '0';
        lblSingle.classList.toggle('wh-intro-pill-strong', !on);
        lblSingle.classList.toggle('wh-intro-muted-soft', on);
        lblMulti.classList.toggle('wh-intro-pill-strong', on);
        lblMulti.classList.toggle('wh-intro-muted-soft', !on);
    }
    function showStep(which){
        var onTwo = which === 2;
        step1.hidden = onTwo;
        step2.hidden = !onTwo;
        step1.setAttribute('aria-hidden', onTwo ? 'true' : 'false');
        step2.setAttribute('aria-hidden', onTwo ? 'false' : 'true');
        overlay.setAttribute('aria-labelledby', onTwo ? 'wh-intro-branch-head' : 'wh-intro-title');
        if (branchFs) branchFs.disabled = !onTwo;
        if (finishBtn) finishBtn.disabled = !onTwo;
        if (nameInput) {
            nameInput.required = Boolean(onTwo);
            if (onTwo) window.requestAnimationFrame(function(){ nameInput.focus(); });
        }
        var activeCb = document.getElementById('wh-intro-b-active');
        if (activeCb) activeCb.dispatchEvent(new Event('change'));
    }
    toggle.addEventListener('click', function(){
        sync(toggle.getAttribute('aria-checked') !== 'true');
    });
    toggle.addEventListener('keydown', function(e){
        if (e.key === ' ' || e.key === 'Enter') {
            e.preventDefault();
            sync(toggle.getAttribute('aria-checked') !== 'true');
        }
    });
    toggle.removeAttribute('tabindex');
    nextBtn?.addEventListener('click', function(){ showStep(2); });
    backBtn?.addEventListener('click', function(){ showStep(1); });
    document.getElementById('wh-intro-b-active')?.addEventListener('change', function(){
        this.setAttribute('aria-checked', this.checked ? 'true' : 'false');
    });
    form.addEventListener('submit', function(){ if (finishBtn) finishBtn.disabled = true; });
    /** After validation errors, reopen step 2 with fields enabled */
    {{ ($errors->any() && ($needsWarehouseBranchIntro ?? false)) ? 'showStep(2);' : '' }}
    window.addEventListener('pageshow', function(ev){
        var needIntro = {{ ($needsWarehouseBranchIntro ?? false) === true ? 'true' : 'false' }};
        var stale = document.getElementById('wh-intro-overlay');
        if (!needIntro && stale && stale.parentNode) {
            document.documentElement.classList.remove('wh-intro-html-noscroll');
            stale.parentNode.removeChild(stale);
        }
        if (needIntro && ev.persisted) {
            location.reload();
        }
    });
})();
</script>
@endif

@if(!$business)
    <script>document.documentElement.classList.add('business-wizard-active');</script>
    <style>
        html.business-wizard-active,html.business-wizard-active body{overflow:hidden;height:100%;}
        html.business-wizard-active .layout{height:100vh;max-height:100vh;overflow:hidden;}
        html.business-wizard-active .sidebar,
        html.business-wizard-active .sidebar-mobile-backdrop,
        html.business-wizard-active .navbar{display:none!important;}
        html.business-wizard-active .content{display:flex;flex-direction:column;min-height:0;height:100vh;max-height:100vh;overflow:hidden;margin-left:0!important;border-left:0!important;width:100%!important;}
        html.business-wizard-active .content-inner{flex:1;min-height:0;display:flex;flex-direction:column;padding:0!important;overflow:hidden;max-width:100%!important;}

        /* Shell & panel */
        .wiz-shell{flex:1;min-height:0;width:100%;display:flex;flex-direction:column;overflow:hidden;}
        .wiz-panel{
            position:relative;flex:1;min-height:0;width:100%;overflow:hidden;
            display:flex;flex-direction:column;align-items:center;justify-content:center;
            padding:clamp(32px,7vh,80px) 0;padding-left:240px;padding-bottom:104px;box-sizing:border-box;
            background:var(--bg);
        }
        @media(max-width:760px){.wiz-panel{padding-left:0;}}
        .wiz-panel::before{
            content:'';position:absolute;inset:0;pointer-events:none;
            background-image:radial-gradient(circle,color-mix(in srgb,var(--primary) 7%,transparent) 1px,transparent 1px);
            background-size:32px 32px;
        }
        /* Decorative blobs */
        .wiz-blob{position:absolute;border-radius:50%;pointer-events:none;filter:blur(80px);opacity:.45;}
        .wiz-blob--a{width:480px;height:480px;top:-120px;left:-120px;background:radial-gradient(circle,color-mix(in srgb,var(--primary) 55%,transparent),transparent 70%);}
        .wiz-blob--b{width:380px;height:380px;bottom:-90px;right:-60px;background:radial-gradient(circle,color-mix(in srgb,var(--primary) 25%,transparent),transparent 70%);}

        /* Step indicator — vertical sidebar */
        .wiz-stepper{
            position:fixed;top:0;left:0;bottom:0;z-index:50;width:240px;
            display:flex;flex-direction:column;align-items:flex-start;justify-content:center;gap:0;
            padding:40px 28px;box-sizing:border-box;
            background:color-mix(in srgb,var(--bg) 92%,transparent);
            backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);
            border-right:1px solid var(--border);
        }
        @media(max-width:760px){.wiz-stepper{display:none;}}
        .wiz-step-item{display:flex;flex-direction:row;align-items:center;gap:12px;}
        .wiz-step-dot{
            width:28px;height:28px;border-radius:50%;display:grid;place-items:center;flex-shrink:0;
            font-size:12px;font-weight:800;transition:all .3s ease;
            background:var(--border);color:var(--muted);border:2px solid transparent;
        }
        .wiz-step-dot--current{background:var(--card);color:var(--primary);border-color:var(--primary);box-shadow:0 0 0 4px color-mix(in srgb,var(--primary) 15%,transparent);}
        .wiz-step-dot--done{background:var(--primary);color:var(--card);border-color:var(--primary);}
        .wiz-step-lbl{font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);transition:color .3s;}
        .wiz-step-lbl--active{color:var(--primary);}
        .wiz-step-connector{width:2px;height:28px;background:var(--border);margin:2px 0 2px 13px;flex-shrink:0;transition:background .3s ease;border-radius:2px;}
        .wiz-step-connector--done{background:var(--primary);}

        /* Card */
        .wiz-card{
            position:relative;z-index:1;width:100%;max-width:none;
            flex-shrink:1;min-height:0;overflow-y:auto;overflow-x:hidden;
            padding:0 clamp(20px,5vw,64px);box-sizing:border-box;
        }
        #wizardStep1,#wizardStep4{
            max-width:640px;margin:0 auto;
        }
        #wizardStep2,#wizardStep3{
            max-width:900px;margin:0 auto;
        }
        .wiz-card-eyebrow{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--primary);margin:0 0 8px;text-align:center;}
        .wiz-card-title{font-size:clamp(26px,3.4vw,36px);font-weight:800;color:var(--text);margin:0 0 8px;line-height:1.2;letter-spacing:-.025em;text-align:center;}
        .wiz-card-sub{font-size:15.5px;color:var(--muted);margin:0 0 30px;line-height:1.55;text-align:center;}

        /* Fields */
        .wiz-field{margin-bottom:18px;}
        .wiz-field label{display:block;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text);margin-bottom:7px;}
        .wiz-input,.wiz-select,.wiz-textarea{
            width:100%;padding:15px 18px;border-radius:12px;
            border:1.5px solid var(--border);background:color-mix(in srgb,var(--card) 94%,transparent);
            color:var(--text);font-size:15.5px;font-family:inherit;
            outline:none;transition:border-color .2s,box-shadow .2s,background .2s;box-sizing:border-box;
        }
        .wiz-input::placeholder,.wiz-textarea::placeholder{color:var(--muted);}
        .wiz-input:focus,.wiz-select:focus,.wiz-textarea:focus{
            border-color:var(--primary);background:var(--card);
            box-shadow:0 0 0 4px color-mix(in srgb,var(--primary) 12%,transparent);
        }
        .wiz-select{
            appearance:none;padding-right:40px;cursor:pointer;
            background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%236b7280' d='M1 1l5 5 5-5'/%3E%3C/svg%3E");
            background-repeat:no-repeat;background-position:right 16px center;background-color:color-mix(in srgb,var(--card) 94%,transparent);
        }
        .wiz-select:focus{background-color:var(--card);}
        .wiz-textarea{resize:vertical;min-height:104px;max-height:min(180px,24vh);line-height:1.55;}
        .wiz-field-error{color:#ef4444;font-size:12.5px;margin-top:6px;}

        /* Buttons */
        .wiz-btn-primary{
            display:flex;align-items:center;justify-content:center;gap:9px;
            width:100%;padding:16px 24px;border-radius:13px;border:none;
            background:var(--btn-bg);
            color:var(--card);font-size:15.5px;font-weight:700;cursor:pointer;font-family:inherit;
            transition:opacity .2s,transform .15s,box-shadow .2s;
            box-shadow:0 6px 18px color-mix(in srgb,var(--primary) 38%,transparent);margin-top:26px;
        }
        .wiz-btn-primary:hover{opacity:.9;transform:translateY(-1px);box-shadow:0 8px 24px color-mix(in srgb,var(--primary) 45%,transparent);}
        .wiz-btn-primary:active{transform:translateY(0);}
        .wiz-btn-back{
            display:inline-flex;align-items:center;gap:7px;
            padding:16px 24px;border-radius:13px;
            background:transparent;border:1.5px solid var(--border);
            font-size:15.5px;font-weight:700;color:var(--muted);
            cursor:pointer;font-family:inherit;
            transition:border-color .2s,color .2s,background .2s;
        }
        .wiz-btn-back:hover{border-color:var(--primary);color:var(--text);background:color-mix(in srgb,var(--primary) 6%,transparent);}
        .wiz-actions-row{
            position:fixed;bottom:0;left:240px;right:0;z-index:50;
            display:flex;justify-content:center;
            padding:16px clamp(20px,5vw,64px);box-sizing:border-box;
            background:color-mix(in srgb,var(--bg) 92%,transparent);
            backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);
            border-top:1px solid var(--border);
        }
        @media(max-width:760px){.wiz-actions-row{left:0;}}
        .wiz-actions-row-inner{width:100%;max-width:none;display:flex;align-items:center;justify-content:space-between;gap:14px;}
        .wiz-actions-row-inner--end{justify-content:flex-end;}
        .wiz-actions-row-inner .wiz-btn-primary,
        .wiz-actions-row-inner .wiz-btn-back{
            width:auto;flex:0 0 auto;margin-top:0;
            padding:11px 22px;font-size:13.5px;border-radius:10px;
        }

        /* Guide character + bubble (right side) */
        .wiz-guide{
            position:fixed;right:0;bottom:72px;z-index:60;
            display:flex;flex-direction:column;align-items:flex-end;gap:0;
            pointer-events:none;
        }
        @media(max-width:900px){.wiz-guide{display:none;}}
        /* Speech bubble */
        .wiz-guide-bubble{
            position:relative;margin-right:28px;margin-bottom:10px;
            max-width:220px;padding:13px 15px;border-radius:16px;
            background:var(--card);
            border:1.5px solid var(--border);
            box-shadow:0 8px 28px -8px rgba(0,0,0,.18);
            pointer-events:auto;
        }
        .wiz-guide-bubble::after{
            content:'';position:absolute;bottom:-9px;right:36px;
            width:16px;height:10px;
            background:var(--card);
            clip-path:polygon(0 0,100% 0,50% 100%);
            border-left:1.5px solid var(--border);
            border-right:1.5px solid var(--border);
        }
        /* small triangle border trick */
        .wiz-guide-bubble::before{
            content:'';position:absolute;bottom:-11px;right:35px;
            width:18px;height:11px;
            background:var(--border);
            clip-path:polygon(0 0,100% 0,50% 100%);
        }
        .wiz-guide-bubble-text{font-size:12.5px;line-height:1.55;color:var(--text);font-weight:500;margin:0 0 10px;}
        .wiz-guide-bubble-step{display:inline-flex;align-items:center;gap:5px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--primary);margin-bottom:5px;}
        .wiz-guide-close{
            display:flex;align-items:center;justify-content:center;gap:5px;width:100%;
            padding:6px 10px;border-radius:8px;border:1.5px solid var(--border);
            background:transparent;color:var(--muted);font-size:11px;font-weight:700;
            font-family:inherit;cursor:pointer;transition:border-color .18s,color .18s,background .18s;
        }
        .wiz-guide-close:hover{border-color:color-mix(in srgb,#ef4444 50%,var(--border));color:#ef4444;background:color-mix(in srgb,#ef4444 6%,transparent);}
        .wiz-guide--hidden{display:none!important;}
        @keyframes wizGuideFadeOut{from{opacity:1;transform:translateY(0)}to{opacity:0;transform:translateY(18px)}}
        /* Character image */
        .wiz-guide-char{
            width:200px;height:auto;display:block;
            margin-right:0;
            filter:drop-shadow(0 12px 28px rgba(0,0,0,.22));
        }
        /* Bubble entrance animation */
        .wiz-guide-bubble{
            animation:wizBubblePop .4s cubic-bezier(.34,1.56,.64,1) both;
        }
        .wiz-guide-bubble.wiz-bubble-change{
            animation:wizBubbleChange .35s cubic-bezier(.34,1.56,.64,1) both;
        }
        @keyframes wizCharFloat{
            0%,100%{transform:translateY(0);}
            50%{transform:translateY(-8px);}
        }
        @keyframes wizBubblePop{
            from{opacity:0;transform:translateY(10px) scale(.92);}
            to{opacity:1;transform:translateY(0) scale(1);}
        }
        @keyframes wizBubbleChange{
            0%{opacity:0;transform:translateY(6px) scale(.95);}
            100%{opacity:1;transform:translateY(0) scale(1);}
        }

        /* Footer note */
        .wiz-footer-note{
            position:relative;z-index:1;margin-top:22px;font-size:13px;
            color:var(--muted);text-align:center;display:flex;align-items:center;
            justify-content:center;gap:6px;flex-shrink:0;
        }

        /* Step slide animations */
        @keyframes wizSlideIn{from{opacity:0;transform:translateX(20px);}to{opacity:1;transform:translateX(0);}}
        @keyframes wizSlideBack{from{opacity:0;transform:translateX(-20px);}to{opacity:1;transform:translateX(0);}}
        .wiz-slide{animation:wizSlideIn .28s cubic-bezier(.4,0,.2,1) forwards;}
        .wiz-slide-back{animation:wizSlideBack .28s cubic-bezier(.4,0,.2,1) forwards;}

        /* Feature selection grid (step 3) */
        /* Step 3 two-column shell */
        .wiz-feat-shell{display:flex;gap:20px;align-items:flex-start;}
        .wiz-feat-main{flex:1;min-width:0;}
        .wiz-feat-sidebar{
            width:220px;flex-shrink:0;border-radius:14px;
            border:1.5px solid var(--border);
            background:color-mix(in srgb,var(--card) 96%,transparent);
            overflow:hidden;position:sticky;top:16px;
        }
        @media(max-width:760px){.wiz-feat-shell{flex-direction:column;}.wiz-feat-sidebar{width:100%;position:static;}}
        .wiz-feat-sidebar-head{
            padding:12px 14px 10px;border-bottom:1px solid var(--border);
            display:flex;align-items:center;justify-content:space-between;gap:8px;
        }
        .wiz-feat-sidebar-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text);}
        .wiz-feat-sidebar-count{
            font-size:11px;font-weight:700;padding:2px 8px;border-radius:999px;
            background:color-mix(in srgb,var(--primary) 14%,transparent);color:var(--primary);
        }
        .wiz-feat-sidebar-list{display:flex;flex-direction:column;padding:8px 0;}
        .wiz-feat-sidebar-item{
            display:flex;align-items:center;gap:10px;padding:8px 14px;
            transition:background .15s;
        }
        .wiz-feat-sidebar-item--off{opacity:.38;}
        .wiz-feat-sidebar-ico{
            width:28px;height:28px;border-radius:8px;flex-shrink:0;
            display:grid;place-items:center;font-size:12px;
            background:color-mix(in srgb,var(--primary) 12%,transparent);
            color:var(--primary);transition:background .18s,color .18s;
        }
        .wiz-feat-sidebar-item--off .wiz-feat-sidebar-ico{background:color-mix(in srgb,var(--muted) 10%,transparent);color:var(--muted);}
        .wiz-feat-sidebar-info{flex:1;min-width:0;}
        .wiz-feat-sidebar-name{display:block;font-size:12px;font-weight:700;color:var(--text);line-height:1.2;}
        .wiz-feat-sidebar-status{display:flex;align-items:center;gap:4px;margin-top:2px;font-size:10px;font-weight:600;}
        .wiz-feat-sidebar-status--on{color:var(--primary);}
        .wiz-feat-sidebar-status--off{color:var(--muted);}
        .wiz-feat-sidebar-status--req{color:#6366f1;}

        .wiz-feat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:8px;padding:3px;}
        @media(max-width:700px){.wiz-feat-grid{grid-template-columns:repeat(2,1fr);}}
        .wiz-feat-card{
            position:relative;border:2px solid var(--border);border-radius:16px;overflow:hidden;cursor:pointer;
            background:var(--card);transition:border-color .2s ease,box-shadow .2s ease,opacity .2s ease;user-select:none;
            display:flex;flex-direction:column;
        }
        .wiz-feat-card:hover{border-color:color-mix(in srgb,var(--primary) 60%,var(--border));box-shadow:0 6px 24px -8px color-mix(in srgb,var(--primary) 18%,transparent);}
        .wiz-feat-card--on{border-color:var(--primary);box-shadow:0 6px 24px -8px color-mix(in srgb,var(--primary) 28%,transparent);}
        .wiz-feat-card:not(.wiz-feat-card--on){opacity:.52;filter:grayscale(.55);}
        .wiz-feat-card--required{cursor:default;}
        .wiz-feat-card--required:hover{box-shadow:0 6px 24px -8px color-mix(in srgb,var(--primary) 28%,transparent);}
        .wiz-feat-card--dep-blocked{opacity:.35;filter:grayscale(.75);border-style:dashed;}
        /* Image area */
        .wiz-feat-img-wrap{position:relative;width:100%;height:128px;overflow:hidden;background:color-mix(in srgb,var(--primary) 6%,var(--bg));}
        .wiz-feat-img{width:100%;height:100%;object-fit:cover;transition:transform .35s ease;}
        .wiz-feat-card:hover .wiz-feat-img{transform:scale(1.05);}
        .wiz-feat-img-overlay{position:absolute;inset:0;background:linear-gradient(to bottom,transparent 40%,rgba(0,0,0,.28));}
        /* Check circle */
        .wiz-feat-check{
            position:absolute;top:9px;right:9px;width:26px;height:26px;border-radius:50%;
            border:2px solid rgba(255,255,255,.7);background:rgba(255,255,255,.2);
            display:grid;place-items:center;font-size:11px;color:transparent;
            transition:background .2s,border-color .2s,color .2s;
        }
        .wiz-feat-card--on .wiz-feat-check{background:var(--primary);border-color:var(--primary);color:#fff;}
        /* Body */
        .wiz-feat-body{padding:12px 13px 13px;display:flex;flex-direction:column;gap:5px;flex:1;}
        .wiz-feat-top{display:flex;align-items:flex-start;justify-content:space-between;gap:6px;}
        .wiz-feat-name{font-size:13px;font-weight:700;color:var(--text);line-height:1.3;flex:1;}
        .wiz-feat-badge{flex-shrink:0;font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;padding:3px 8px;border-radius:999px;white-space:nowrap;background:color-mix(in srgb,var(--primary) 13%,transparent);color:var(--primary);}
        .wiz-feat-card--required .wiz-feat-badge{background:color-mix(in srgb,#6366f1 13%,transparent);color:#6366f1;}
        .wiz-feat-card:not(.wiz-feat-card--on) .wiz-feat-badge{background:color-mix(in srgb,var(--muted) 13%,transparent);color:var(--muted);}
        .wiz-feat-desc{font-size:11.5px;color:var(--muted);line-height:1.45;margin:0;}
        .wiz-feat-dep-hint{font-size:10px;font-weight:600;color:#b45309;}

        /* Category search bar (step 2) */
        .wiz-cat-search-wrap{position:relative;display:flex;align-items:center;margin-bottom:12px;}
        .wiz-cat-search-ico{position:absolute;left:14px;font-size:13.5px;color:var(--muted);pointer-events:none;}
        .wiz-cat-search-input{
            width:100%;padding:11px 40px 11px 38px;border-radius:11px;
            border:1.5px solid var(--border);background:color-mix(in srgb,var(--card) 94%,transparent);
            color:var(--text);font-size:14px;font-family:inherit;outline:none;
            transition:border-color .2s,box-shadow .2s;box-sizing:border-box;
        }
        .wiz-cat-search-input::placeholder{color:var(--muted);}
        .wiz-cat-search-input:focus{border-color:var(--primary);box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 12%,transparent);background:var(--card);}
        .wiz-cat-search-clear{position:absolute;right:10px;width:26px;height:26px;border-radius:50%;border:none;background:color-mix(in srgb,var(--muted) 16%,transparent);color:var(--muted);cursor:pointer;display:grid;place-items:center;font-size:11px;transition:background .18s,color .18s;}
        .wiz-cat-search-clear:hover{background:color-mix(in srgb,var(--primary) 14%,transparent);color:var(--primary);}
        .wiz-cat-noresults{text-align:center;color:var(--muted);font-size:13.5px;padding:24px 0;margin:0;}

        /* Business category card grid (step 2) */
        .wiz-cat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:4px;max-height:min(520px,56vh);overflow-y:auto;padding:3px;}
        @media(max-width:760px){.wiz-cat-grid{grid-template-columns:repeat(2,1fr);}}
        @media(max-width:480px){.wiz-cat-grid{grid-template-columns:1fr;}}
        .wiz-cat-card{
            display:flex;flex-direction:column;gap:0;text-align:left;
            border-radius:14px;border:2px solid var(--border);overflow:hidden;
            background:color-mix(in srgb,var(--card) 94%,transparent);
            transition:border-color .18s ease,background .18s ease,box-shadow .18s ease;
        }
        .wiz-cat-card:hover{border-color:color-mix(in srgb,var(--primary) 55%,var(--border));box-shadow:0 4px 18px -8px color-mix(in srgb,var(--primary) 18%,transparent);}
        .wiz-cat-card--on{border-color:var(--primary);background:color-mix(in srgb,var(--primary) 5%,var(--card));box-shadow:0 6px 22px -8px color-mix(in srgb,var(--primary) 28%,transparent);}
        /* Card head */
        .wiz-cat-head{display:flex;align-items:center;gap:10px;padding:14px 14px 8px;}
        .wiz-cat-ico{width:38px;height:38px;border-radius:10px;flex-shrink:0;display:grid;place-items:center;font-size:17px;background:color-mix(in srgb,var(--muted) 12%,transparent);color:var(--muted);transition:color .18s,background .18s;}
        .wiz-cat-card--on .wiz-cat-ico{color:var(--primary);background:color-mix(in srgb,var(--primary) 16%,transparent);}
        .wiz-cat-title-row{flex:1;min-width:0;}
        .wiz-cat-name{display:block;font-size:13.5px;font-weight:700;color:var(--text);line-height:1.3;}
        .wiz-cat-users{display:flex;align-items:center;gap:4px;margin-top:2px;font-size:11px;font-weight:600;color:var(--muted);}
        .wiz-cat-users i{font-size:10px;}
        /* Card desc */
        .wiz-cat-desc{padding:0 14px 8px;font-size:12px;color:var(--muted);line-height:1.45;}
        /* Module chips */
        .wiz-cat-modules{display:flex;flex-wrap:wrap;gap:5px;padding:8px 12px;border-bottom:1px solid var(--border);}
        .wiz-cat-mod{font-size:10px;font-weight:700;letter-spacing:.02em;padding:3px 8px;border-radius:999px;background:color-mix(in srgb,var(--muted) 10%,transparent);color:var(--muted);transition:background .18s,color .18s;}
        .wiz-cat-mod--on{background:color-mix(in srgb,var(--primary) 13%,transparent);color:var(--primary);}
        .wiz-cat-card--on .wiz-cat-mod--on{background:color-mix(in srgb,var(--primary) 20%,transparent);}
        /* Card action buttons */
        .wiz-cat-actions{display:flex;gap:7px;padding:10px 12px;}
        .wiz-cat-btn-detail{
            flex:1;padding:7px 8px;border-radius:9px;border:1.5px solid var(--border);
            background:transparent;color:var(--muted);font-size:11.5px;font-weight:700;
            font-family:inherit;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:5px;
            transition:border-color .18s,color .18s,background .18s;
        }
        .wiz-cat-btn-detail:hover{border-color:var(--primary);color:var(--text);background:color-mix(in srgb,var(--primary) 5%,transparent);}
        .wiz-cat-btn-select{
            flex:1;padding:7px 8px;border-radius:9px;
            border:1.5px solid color-mix(in srgb,var(--primary) 40%,var(--border));
            background:color-mix(in srgb,var(--primary) 9%,transparent);color:var(--primary);
            font-size:11.5px;font-weight:700;font-family:inherit;cursor:pointer;
            display:flex;align-items:center;justify-content:center;gap:5px;transition:all .18s;
        }
        .wiz-cat-btn-select:hover,.wiz-cat-card--on .wiz-cat-btn-select{background:var(--primary);color:#fff;border-color:var(--primary);}

        /* Category details modal */
        .wiz-cat-modal{position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px;box-sizing:border-box;}
        .wiz-cat-modal[hidden]{display:none;}
        .wiz-cat-modal__backdrop{position:absolute;inset:0;background:rgba(0,0,0,.55);backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);}
        .wiz-cat-modal__box{
            position:relative;z-index:1;width:100%;max-width:520px;border-radius:20px;
            background:var(--card);box-shadow:0 28px 72px -16px rgba(0,0,0,.5);
            display:flex;flex-direction:column;max-height:calc(100vh - 40px);overflow:hidden;
        }
        .wiz-cat-modal__close{
            position:absolute;top:14px;right:14px;width:30px;height:30px;border-radius:50%;
            border:1.5px solid var(--border);background:var(--card);color:var(--muted);
            cursor:pointer;display:grid;place-items:center;font-size:13px;z-index:2;
            transition:border-color .18s,color .18s;
        }
        .wiz-cat-modal__close:hover{border-color:var(--primary);color:var(--text);}
        .wiz-cat-modal__body{overflow-y:auto;padding:26px 26px 0;flex:1;min-height:0;}
        .wiz-cat-modal__hero{display:flex;align-items:center;gap:14px;margin-bottom:16px;}
        .wiz-cat-modal__ico{width:52px;height:52px;flex-shrink:0;border-radius:14px;display:grid;place-items:center;font-size:22px;background:color-mix(in srgb,var(--primary) 14%,transparent);color:var(--primary);}
        .wiz-cat-modal__title{margin:0 0 4px;font-size:19px;font-weight:800;color:var(--text);letter-spacing:-.02em;}
        .wiz-cat-modal__users{margin:0;font-size:12px;color:var(--muted);display:flex;align-items:center;gap:5px;font-weight:600;}
        .wiz-cat-modal__fulldesc{font-size:13.5px;color:var(--muted);line-height:1.65;margin:0 0 18px;}
        .wiz-cat-modal__section{margin-bottom:18px;}
        .wiz-cat-modal__section-title{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);margin:0 0 9px;}
        .wiz-cat-modal__highlights{margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:7px;}
        .wiz-cat-modal__highlights li{display:flex;align-items:flex-start;gap:9px;font-size:13px;color:var(--text);line-height:1.4;}
        .wiz-cat-modal__highlights li i{color:var(--primary);margin-top:2px;font-size:12px;flex-shrink:0;}
        .wiz-cat-modal__mods{display:flex;flex-wrap:wrap;gap:7px;}
        .wiz-cat-modal__mod{font-size:12px;font-weight:700;padding:5px 12px;border-radius:999px;display:flex;align-items:center;gap:5px;}
        .wiz-cat-modal__mod--on{background:color-mix(in srgb,var(--primary) 13%,transparent);color:var(--primary);}
        .wiz-cat-modal__mod--off{background:color-mix(in srgb,var(--muted) 10%,transparent);color:var(--muted);opacity:.6;}
        .wiz-cat-modal__footer{padding:14px 26px;border-top:1px solid var(--border);display:flex;gap:8px;flex-shrink:0;justify-content:flex-end;}
        .wiz-cat-modal__footer .wiz-btn-primary{margin-top:0;flex:0 0 auto;padding:9px 18px;font-size:13px;border-radius:9px;box-shadow:none;}
        .wiz-cat-modal__footer .wiz-btn-back{margin-top:0;padding:9px 16px;font-size:13px;border-radius:9px;}

        /* Branch / location toggle (step 4) */
        .wiz-branch-toggle-row{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:16px 18px;border-radius:12px;border:1.5px solid var(--border);background:color-mix(in srgb,var(--card) 94%,transparent);margin-bottom:10px;}
        .wiz-branch-toggle-lbl{font-size:14.5px;font-weight:700;color:var(--text);}
        .wiz-branch-toggle-sub{font-size:12px;color:var(--muted);margin-top:3px;}
        .wiz-switch{display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;margin:0;padding:0;border:0;background:none;cursor:pointer;outline:none;-webkit-appearance:none;appearance:none;}
        .wiz-switch:focus-visible .wiz-switch-track{outline:2px solid color-mix(in srgb,var(--primary) 55%,transparent);outline-offset:2px;}
        .wiz-switch-track{position:relative;width:54px;height:30px;border-radius:999px;flex-shrink:0;background:color-mix(in srgb,var(--muted) 40%,var(--border));transition:background .2s ease;}
        .wiz-switch[aria-checked="true"] .wiz-switch-track{background:#22c55e;}
        .wiz-switch-knob{position:absolute;top:3px;left:3px;width:24px;height:24px;border-radius:50%;box-sizing:border-box;background:#fff;border:1px solid color-mix(in srgb,var(--border) 80%,transparent);transition:left .22s ease;box-shadow:0 1px 2px rgba(0,0,0,.2);}
        .wiz-switch[aria-checked="true"] .wiz-switch-knob{left:calc(100% - 3px - 24px);}
    </style>

    <div class="wiz-shell">
        <div class="wiz-panel">
            <div class="wiz-blob wiz-blob--a" aria-hidden="true"></div>
            <div class="wiz-blob wiz-blob--b" aria-hidden="true"></div>

            {{-- Step indicator --}}
            <div class="wiz-stepper" aria-label="Setup progress">
                <div class="wiz-step-item">
                    <div class="wiz-step-dot wiz-step-dot--current" id="wizDot1">1</div>
                    <span class="wiz-step-lbl wiz-step-lbl--active" id="wizLbl1">Business</span>
                </div>
                <div class="wiz-step-connector" id="wizStepLine1"></div>
                <div class="wiz-step-item">
                    <div class="wiz-step-dot" id="wizDot2">2</div>
                    <span class="wiz-step-lbl" id="wizLbl2">Category</span>
                </div>
                <div class="wiz-step-connector" id="wizStepLine2"></div>
                <div class="wiz-step-item">
                    <div class="wiz-step-dot" id="wizDot3">3</div>
                    <span class="wiz-step-lbl" id="wizLbl3">Features</span>
                </div>
                <div class="wiz-step-connector" id="wizStepLine3"></div>
                <div class="wiz-step-item">
                    <div class="wiz-step-dot" id="wizDot4">4</div>
                    <span class="wiz-step-lbl" id="wizLbl4">Location</span>
                </div>
            </div>

            {{-- Card --}}
            <div class="wiz-card">
                <form id="businessWizardForm" method="post" action="{{ route('business.onboarding.store') }}">
                    @csrf
                    @php
                        // Static, per-category recommended modules (placeholder until this is data-driven).
                        $wizCategoryFeatureMap = [
                            'education'              => ['account_management', 'bill_management', 'human_resources', 'social_media_campaign'],
                            'software_industry'      => ['account_management', 'bill_management', 'human_resources', 'social_media_campaign', 'product_management'],
                            'local_retail'            => ['account_management', 'point_of_sale', 'product_management', 'stock_management', 'bill_management', 'social_media_campaign', 'human_resources'],
                            'food_beverage'           => ['account_management', 'point_of_sale', 'product_management', 'stock_management', 'bill_management', 'social_media_campaign', 'human_resources'],
                            'healthcare'              => ['account_management', 'point_of_sale', 'bill_management', 'human_resources', 'product_management'],
                            'finance'                 => ['account_management', 'bill_management', 'human_resources', 'social_media_campaign'],
                            'creative_media'          => ['account_management', 'social_media_campaign', 'bill_management', 'human_resources'],
                            'ecommerce'               => ['account_management', 'point_of_sale', 'product_management', 'stock_management', 'social_media_campaign', 'bill_management', 'human_resources'],
                            'manufacturing'           => ['account_management', 'product_management', 'stock_management', 'bill_management', 'human_resources', 'point_of_sale'],
                            'real_estate'             => ['account_management', 'bill_management', 'human_resources', 'social_media_campaign'],
                            'nonprofit'               => ['account_management', 'bill_management', 'human_resources', 'social_media_campaign'],
                            'professional_services'   => ['account_management', 'bill_management', 'human_resources', 'social_media_campaign'],
                            'other'                   => ['account_management', 'bill_management', 'social_media_campaign'],
                        ];
                    @endphp

                    {{-- Step 1: Business name --}}
                    <div id="wizardStep1" class="wiz-slide">
                        <h2 class="wiz-card-title">What's your business name?</h2>
                        <p class="wiz-card-sub">Use your public brand name — you can update it any time.</p>
                        <div class="wiz-field">
                            <label for="wiz-name-input">Business name</label>
                            <input
                                id="wiz-name-input"
                                type="text"
                                name="name"
                                value="{{ old('name') }}"
                                placeholder="e.g. Zeebroo Solutions"
                                required
                                autocomplete="organization"
                                class="wiz-input"
                            >
                            @error('name')
                                <div class="wiz-field-error">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Step 2: Category --}}
                    <div id="wizardStep2" style="display:none;">
                        <h2 class="wiz-card-title">Tell us about your business</h2>
                        <p class="wiz-card-sub">Help us personalise your workspace from day one.</p>

                        {{-- hidden input holds the submitted value; data attrs used by JS --}}
                        <input type="hidden" id="wizard-company-category" name="company_category_slug"
                               value="{{ old('company_category_slug', '') }}"
                               data-wiz-feat-meta="{{ json_encode($wizFeatureItems) }}"
                               data-wiz-category-map="{{ json_encode($wizCategoryFeatureMap) }}">

                        <div class="wiz-field">
                            <label>Business category</label>
                            @php
                                $wizCatMeta = [
                                    'education'             => ['icon'=>'fa-graduation-cap',     'desc'=>'Schools, tutoring centres & online courses',       'users'=>'2.4k','fullDesc'=>'Perfect for schools, colleges, tutoring centres, online course creators and training providers. Manage student billing cycles, staff payroll and HR, fee collection tracking, and promotional campaigns — all from a single workspace.','highlights'=>['Billing cycles for tuition & fees','Staff payroll and leave management','Course & session scheduling support','Promotional campaign tracking']],
                                    'software_industry'     => ['icon'=>'fa-laptop-code',        'desc'=>'SaaS, agencies & app development studios',        'users'=>'1.8k','fullDesc'=>'Built for software companies, digital agencies, IT consultancies and app development studios. Track subscription revenue, manage developer payroll, handle vendor bills, and run targeted outreach campaigns without switching tools.','highlights'=>['Subscription & retainer billing','Developer & contractor payroll','Vendor and SaaS expense tracking','Client campaign management']],
                                    'local_retail'          => ['icon'=>'fa-store',              'desc'=>'Boutiques, supermarkets & specialty shops',        'users'=>'5.1k','fullDesc'=>'Designed for brick-and-mortar retailers of all sizes — from neighbourhood boutiques to supermarkets. Run a full point-of-sale, manage inventory levels, track supplier bills, and keep your team paid on time.','highlights'=>['Point-of-sale with receipt printing','Real-time stock & inventory tracking','Supplier bill management','Staff scheduling and payroll']],
                                    'food_beverage'         => ['icon'=>'fa-utensils',           'desc'=>'Restaurants, cafés, bakeries & catering',          'users'=>'4.3k','fullDesc'=>'Ideal for restaurants, cafés, bakeries, catering businesses and cloud kitchens. Manage table-side or counter POS, ingredient stock levels, supplier invoices, and kitchen staff payroll efficiently.','highlights'=>['Table & counter point-of-sale','Ingredient & menu stock control','Supplier invoice tracking','Kitchen staff payroll & HR']],
                                    'healthcare'            => ['icon'=>'fa-heart-pulse',        'desc'=>'Clinics, pharmacies & wellness centres',           'users'=>'3.2k','fullDesc'=>'Tailored for clinics, pharmacies, diagnostic labs and wellness centres. Handle patient billing at the counter, manage medical inventory, track utility and supplier bills, and administer medical staff HR seamlessly.','highlights'=>['Counter billing & receipt management','Medical stock & consumables tracking','Utility and supplier bill management','Doctor & staff payroll']],
                                    'finance'               => ['icon'=>'fa-landmark',           'desc'=>'Insurance brokers & financial consultants',        'users'=>'1.5k','fullDesc'=>'Suited for insurance agencies, financial advisory firms, accounting practices and investment consultants. Keep precise account ledgers, manage recurring service bills, run HR for your team, and coordinate client outreach campaigns.','highlights'=>['Multi-account ledger management','Recurring service bill tracking','Client outreach & campaign tools','Consultant payroll & HR']],
                                    'creative_media'        => ['icon'=>'fa-palette',            'desc'=>'Design studios, photography & content creators',   'users'=>'2.1k','fullDesc'=>'For creative agencies, design studios, photographers, videographers and content creators. Invoice clients, track project expenses, manage freelancer payments, and schedule social media campaigns — in one place.','highlights'=>['Client project invoicing','Freelancer & staff payment tracking','Creative project expense management','Social media campaign scheduling']],
                                    'ecommerce'             => ['icon'=>'fa-cart-shopping',      'desc'=>'Online stores, dropshipping & marketplaces',       'users'=>'3.7k','fullDesc'=>'Optimised for e-commerce businesses, online stores, dropshippers and marketplace sellers. Manage your product catalogue, track warehouse stock, process online orders at the POS, and run promotional campaigns across channels.','highlights'=>['Product catalogue management','Warehouse & fulfilment stock tracking','Online order processing at POS','Multi-channel campaign management']],
                                    'manufacturing'         => ['icon'=>'fa-industry',           'desc'=>'Factories, workshops & production lines',          'users'=>'1.2k','fullDesc'=>'Built for manufacturers, factories, workshops and assembly operations. Track raw material inventory, manage production staff HR and payroll, handle supplier bills, and monitor finished goods stock levels with ease.','highlights'=>['Raw material & finished goods stock','Production staff HR & payroll','Supplier and utility bill tracking','Output and quality record management']],
                                    'real_estate'           => ['icon'=>'fa-house-chimney',      'desc'=>'Agents, property management & rentals',            'users'=>'890', 'fullDesc'=>'Designed for real estate agencies, property management firms and rental businesses. Track rental income and expenses, manage property-related bills, handle agent and staff HR, and run lead generation campaigns effectively.','highlights'=>['Rental income & expense tracking','Property bill and maintenance costs','Agent commission and staff payroll','Lead generation campaign management']],
                                    'nonprofit'             => ['icon'=>'fa-hands-holding-heart','desc'=>'Charities, NGOs & community organisations',        'users'=>'640', 'fullDesc'=>'Purpose-built for non-profits, charities, NGOs and community organisations. Track donations and operational budgets, manage volunteer and staff HR, handle utility bills, and run awareness campaigns for maximum outreach.','highlights'=>['Donation and grant budget tracking','Volunteer and staff HR management','Operational and utility bill tracking','Awareness campaign coordination']],
                                    'professional_services' => ['icon'=>'fa-briefcase',          'desc'=>'Law firms, consultants & accounting practices',    'users'=>'2.9k','fullDesc'=>'Optimised for law firms, management consultants, accounting practices and professional service providers. Manage retainer billing and client accounts, track professional bills, handle staff payroll, and coordinate business development campaigns.','highlights'=>['Retainer & time-based billing','Client account management','Professional expense tracking','Business development campaigns']],
                                    'other'                 => ['icon'=>'fa-ellipsis',           'desc'=>'Anything that doesn\'t fit the categories above', 'users'=>'1.1k','fullDesc'=>'A flexible workspace for any business type not listed above. All core modules are available and can be customised to match your unique operational needs — from billing and HR to stock management and campaigns.','highlights'=>['Full module access with no restrictions','Customisable billing and HR setup','Flexible stock and product tracking','General-purpose campaign management']],
                                ];
                                $wizFeatShortLabels = [
                                    'account_management'    => 'Accounts',
                                    'bill_management'       => 'Bills',
                                    'human_resources'       => 'HR',
                                    'product_management'    => 'Products',
                                    'stock_management'      => 'Stock',
                                    'point_of_sale'         => 'POS',
                                    'social_media_campaign' => 'Campaigns',
                                ];
                            @endphp
                            <div class="wiz-cat-search-wrap">
                                <i class="fa fa-magnifying-glass wiz-cat-search-ico" aria-hidden="true"></i>
                                <input type="text" id="wizCatSearch" class="wiz-cat-search-input"
                                    placeholder="Search business type…" autocomplete="off" spellcheck="false">
                                <button type="button" id="wizCatSearchClear" class="wiz-cat-search-clear" hidden aria-label="Clear search">
                                    <i class="fa fa-xmark" aria-hidden="true"></i>
                                </button>
                            </div>
                            <p id="wizCatNoResults" class="wiz-cat-noresults" hidden>No categories match your search.</p>
                            <div class="wiz-cat-grid">
                                @foreach($businessCategoryOptions ?? [] as $categoryOption)
                                    @php
                                        $slug = $categoryOption['value'];
                                        $meta = $wizCatMeta[$slug] ?? ['icon'=>'fa-tag','desc'=>'','users'=>'0','fullDesc'=>'','highlights'=>[]];
                                        $mods = $wizCategoryFeatureMap[$slug] ?? ['account_management'];
                                    @endphp
                                    <div class="wiz-cat-card {{ (string) old('company_category_slug') === $slug ? 'wiz-cat-card--on' : '' }}"
                                         data-cat-slug="{{ $slug }}">
                                        <div class="wiz-cat-head">
                                            <span class="wiz-cat-ico"><i class="fa {{ $meta['icon'] }}" aria-hidden="true"></i></span>
                                            <span class="wiz-cat-title-row">
                                                <span class="wiz-cat-name">{{ $categoryOption['label'] }}</span>
                                                <span class="wiz-cat-users"><i class="fa fa-users" aria-hidden="true"></i> {{ $meta['users'] }} businesses</span>
                                            </span>
                                        </div>
                                        <p class="wiz-cat-desc">{{ $meta['desc'] }}</p>
                                        <div class="wiz-cat-modules">
                                            @foreach($wizFeatShortLabels as $modKey => $modLabel)
                                                <span class="wiz-cat-mod {{ in_array($modKey, $mods) ? 'wiz-cat-mod--on' : '' }}">{{ $modLabel }}</span>
                                            @endforeach
                                        </div>
                                        <div class="wiz-cat-actions">
                                            <button type="button" class="wiz-cat-btn-detail"
                                                data-cat-slug="{{ $slug }}"
                                                data-cat-label="{{ $categoryOption['label'] }}"
                                                data-cat-icon="{{ $meta['icon'] }}"
                                                data-cat-users="{{ $meta['users'] }}"
                                                data-cat-fulldesc="{{ $meta['fullDesc'] }}"
                                                data-cat-highlights="{{ json_encode($meta['highlights']) }}"
                                                data-cat-mods="{{ json_encode($mods) }}">
                                                <i class="fa fa-circle-info" aria-hidden="true"></i> Details
                                            </button>
                                            <button type="button" class="wiz-cat-btn-select" data-cat-slug="{{ $slug }}">
                                                <i class="fa fa-check" aria-hidden="true"></i>
                                                <span>{{ (string) old('company_category_slug') === $slug ? 'Selected' : 'Select' }}</span>
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            @error('company_category_slug')
                                <div class="wiz-field-error">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Step 3: Feature selection --}}
                    <div id="wizardStep3" style="display:none;">
                        <h2 class="wiz-card-title">Which features do you need?</h2>
                        <p class="wiz-card-sub">Turn on the modules you'll use — you can change these any time from your account menu.</p>
                        <div class="wiz-feat-shell">
                            {{-- Left: search + grid --}}
                            <div class="wiz-feat-main">
                                <div class="wiz-cat-search-wrap" style="margin-bottom:14px;">
                                    <i class="fa fa-magnifying-glass wiz-cat-search-ico" aria-hidden="true"></i>
                                    <input type="text" id="wizFeatSearch" class="wiz-cat-search-input"
                                        placeholder="Search features…" autocomplete="off" spellcheck="false">
                                    <button type="button" id="wizFeatSearchClear" class="wiz-cat-search-clear" hidden aria-label="Clear search">
                                        <i class="fa fa-xmark" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <p id="wizFeatNoResults" class="wiz-cat-noresults" hidden>No features match your search.</p>
                                <div class="wiz-feat-grid">
                                    @foreach($wizFeatureItems as $wizFeat)
                                        @php $wizFeatOn = !empty($wizFeat['required']) || (bool) old('features.'.$wizFeat['key'], true); @endphp
                                        <div class="wiz-feat-card {{ !empty($wizFeat['required']) ? 'wiz-feat-card--required' : '' }} {{ $wizFeatOn ? 'wiz-feat-card--on' : '' }}"
                                             data-feature="{{ $wizFeat['key'] }}"
                                             @if(!empty($wizFeat['dependsOn'])) data-depends-on="{{ implode(',', $wizFeat['dependsOn']) }}" @endif
                                             role="checkbox" aria-checked="{{ $wizFeatOn ? 'true' : 'false' }}" tabindex="0">
                                            <div class="wiz-feat-img-wrap">
                                                <img src="{{ asset('features/' . $wizFeat['image']) }}"
                                                     alt="{{ $wizFeat['label'] }}"
                                                     class="wiz-feat-img"
                                                     draggable="false">
                                                <div class="wiz-feat-img-overlay" aria-hidden="true"></div>
                                                <div class="wiz-feat-check" aria-hidden="true">
                                                    <i class="fa fa-check"></i>
                                                </div>
                                            </div>
                                            <div class="wiz-feat-body">
                                                <div class="wiz-feat-top">
                                                    <span class="wiz-feat-name">{{ $wizFeat['label'] }}</span>
                                                    <span class="wiz-feat-badge">{{ !empty($wizFeat['required']) ? 'Required' : ($wizFeatOn ? 'Enabled' : 'Disabled') }}</span>
                                                </div>
                                                <p class="wiz-feat-desc">{{ $wizFeat['desc'] ?? '' }}</p>
                                                @if(!empty($wizFeat['dependsOn']))
                                                    <span class="wiz-feat-dep-hint" style="display:none;"><i class="fa fa-triangle-exclamation" aria-hidden="true"></i> Needs Stock + Product</span>
                                                @endif
                                            </div>
                                            @if(empty($wizFeat['required']))
                                                <input type="checkbox" name="features[{{ $wizFeat['key'] }}]" value="1" @checked($wizFeatOn) style="position:absolute;opacity:0;pointer-events:none;width:0;height:0;">
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            {{-- Right: enabled features panel --}}
                            <div class="wiz-feat-sidebar">
                                <div class="wiz-feat-sidebar-head">
                                    <span class="wiz-feat-sidebar-title">Your setup</span>
                                    <span class="wiz-feat-sidebar-count" id="wizFeatEnabledCount">0</span>
                                </div>
                                <div class="wiz-feat-sidebar-list" id="wizFeatSidebarList">
                                    @foreach($wizFeatureItems as $wizFeat)
                                        @php $wizFeatOn = !empty($wizFeat['required']) || (bool) old('features.'.$wizFeat['key'], true); @endphp
                                        <div class="wiz-feat-sidebar-item {{ $wizFeatOn ? '' : 'wiz-feat-sidebar-item--off' }}"
                                             id="wizSidebarItem_{{ $wizFeat['key'] }}">
                                            <span class="wiz-feat-sidebar-ico">
                                                <i class="fa {{ $wizFeat['icon'] }}" aria-hidden="true"></i>
                                            </span>
                                            <span class="wiz-feat-sidebar-info">
                                                <span class="wiz-feat-sidebar-name">{{ $wizFeat['label'] }}</span>
                                                <span class="wiz-feat-sidebar-status {{ !empty($wizFeat['required']) ? 'wiz-feat-sidebar-status--req' : ($wizFeatOn ? 'wiz-feat-sidebar-status--on' : 'wiz-feat-sidebar-status--off') }}"
                                                      id="wizSidebarStatus_{{ $wizFeat['key'] }}">
                                                    @if(!empty($wizFeat['required']))
                                                        <i class="fa fa-lock" aria-hidden="true"></i> Required
                                                    @elseif($wizFeatOn)
                                                        <i class="fa fa-circle-check" aria-hidden="true"></i> Enabled
                                                    @else
                                                        <i class="fa fa-circle-xmark" aria-hidden="true"></i> Disabled
                                                    @endif
                                                </span>
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Step 4: Locations --}}
                    <div id="wizardStep4" style="display:none;">
                        <h2 class="wiz-card-title">Set up your first location</h2>
                        <p class="wiz-card-sub">Tell us if you run multiple branches, then add your primary location.</p>

                        <div class="wiz-field">
                            <div class="wiz-branch-toggle-row">
                                <div>
                                    <div class="wiz-branch-toggle-lbl" id="wizMwLblOff">Single location</div>
                                    <div class="wiz-branch-toggle-sub">Switch on if you operate multiple warehouses or branches</div>
                                </div>
                                <input type="hidden" name="multi_warehouse_branch" id="wiz-mw-val" value="0">
                                <button type="button" class="wiz-switch" id="wiz-mw-switch" role="switch" aria-checked="false">
                                    <span class="wiz-switch-track" aria-hidden="true"><span class="wiz-switch-knob"></span></span>
                                </button>
                            </div>
                        </div>

                        <div class="wiz-field">
                            <label for="wiz-branch-name">Location name</label>
                            <input id="wiz-branch-name" type="text" name="branch_name" value="{{ old('branch_name', old('name')) }}" maxlength="255" placeholder="e.g. Colombo HQ" required class="wiz-input">
                            @error('branch_name')<div class="wiz-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="wiz-field">
                            <label for="wiz-branch-address">Address <span style="font-weight:500;text-transform:none;letter-spacing:0;color:var(--muted);">(optional)</span></label>
                            <textarea id="wiz-branch-address" name="branch_address" maxlength="2000" placeholder="Street, city, postal code" class="wiz-textarea" style="min-height:64px;">{{ old('branch_address') }}</textarea>
                        </div>
                        <div style="display:flex;gap:10px;">
                            <div class="wiz-field" style="flex:1;">
                                <label for="wiz-branch-phone">Phone <span style="font-weight:500;text-transform:none;letter-spacing:0;color:var(--muted);">(optional)</span></label>
                                <input id="wiz-branch-phone" type="text" name="branch_phone" value="{{ old('branch_phone') }}" maxlength="40" inputmode="tel" placeholder="+94 …" class="wiz-input">
                            </div>
                            <div class="wiz-field" style="flex:1;">
                                <label for="wiz-branch-email">Email <span style="font-weight:500;text-transform:none;letter-spacing:0;color:var(--muted);">(optional)</span></label>
                                <input id="wiz-branch-email" type="email" name="branch_email" value="{{ old('branch_email', auth()->user()->email) }}" maxlength="255" placeholder="branch@example.com" class="wiz-input">
                            </div>
                        </div>
                        <input type="hidden" name="branch_is_active" value="1">
                    </div>

                    {{-- Fixed bottom action bar — outside the animated step divs so position:fixed
                         anchors to the viewport (an ancestor with `transform` creates a new
                         containing block for fixed descendants, which broke this before). --}}
                    <div class="wiz-actions-row">
                        <div class="wiz-actions-row-inner wiz-actions-row-inner--end" data-wiz-actions="1">
                            <button type="button" class="wiz-btn-primary" id="nextStepBtn">
                                Continue <i class="fa fa-arrow-right" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="wiz-actions-row-inner" data-wiz-actions="2" style="display:none;">
                            <button type="button" class="wiz-btn-back" data-wiz-back="1">
                                <i class="fa fa-arrow-left" aria-hidden="true"></i> Back
                            </button>
                            <button type="button" class="wiz-btn-primary" data-wiz-next="3">
                                Continue <i class="fa fa-arrow-right" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="wiz-actions-row-inner" data-wiz-actions="3" style="display:none;">
                            <button type="button" class="wiz-btn-back" data-wiz-back="2">
                                <i class="fa fa-arrow-left" aria-hidden="true"></i> Back
                            </button>
                            <button type="button" class="wiz-btn-primary" data-wiz-next="4">
                                Continue <i class="fa fa-arrow-right" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="wiz-actions-row-inner" data-wiz-actions="4" style="display:none;">
                            <button type="button" class="wiz-btn-back" data-wiz-back="3">
                                <i class="fa fa-arrow-left" aria-hidden="true"></i> Back
                            </button>
                            <button type="submit" class="wiz-btn-primary">
                                Finish setup <i class="fa fa-check" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <p class="wiz-footer-note"><i class="fa fa-lock" aria-hidden="true"></i> Your data is secure and private.</p>
        </div>
    </div>

    {{-- Guide character --}}
    <div class="wiz-guide" id="wizGuide" aria-hidden="true">
        <div class="wiz-guide-bubble" id="wizGuideBubble">
            <div class="wiz-guide-bubble-step" id="wizGuideBubbleStep">
                <i class="fa fa-circle-info"></i> <span id="wizGuideBubbleStepLabel">Step 1</span>
            </div>
            <p class="wiz-guide-bubble-text" id="wizGuideBubbleText">
                Hi there! What do you call your business? Choose a name that your customers already know.
            </p>
            <button type="button" class="wiz-guide-close" id="wizGuideClose" aria-label="Dismiss guide">
                <i class="fa fa-xmark" aria-hidden="true"></i> Dismiss
            </button>
        </div>
        <img src="{{ asset('bussiness_man.png') }}" alt="Guide character" class="wiz-guide-char" id="wizGuideChar">
    </div>

    {{-- Category details modal (outside form, inside wizard shell) --}}
    <div id="wizCatModal" class="wiz-cat-modal" hidden aria-modal="true" role="dialog" aria-labelledby="wizCatModalTitle">
        <div class="wiz-cat-modal__backdrop" id="wizCatModalBackdrop"></div>
        <div class="wiz-cat-modal__box">
            <button type="button" class="wiz-cat-modal__close" id="wizCatModalClose" aria-label="Close">
                <i class="fa fa-xmark" aria-hidden="true"></i>
            </button>
            <div class="wiz-cat-modal__body" id="wizCatModalBody">
                {{-- Filled by JS --}}
            </div>
            <div class="wiz-cat-modal__footer">
                <button type="button" class="wiz-btn-back" id="wizCatModalCloseBtn">
                    <i class="fa fa-arrow-left" aria-hidden="true"></i> Back
                </button>
                <button type="button" class="wiz-btn-primary" id="wizCatModalSelectBtn">
                    <i class="fa fa-check" aria-hidden="true"></i> Select this category
                </button>
            </div>
        </div>
    </div>
@elseif(!$hasBankAccountForBusiness)
    <script>document.documentElement.classList.add('business-wizard-active');</script>
    <style>
        .account-notice-shell{
            position:relative;flex:1;min-height:0;width:100%;overflow:hidden;
            display:flex;align-items:center;justify-content:center;
            padding:clamp(24px,5vh,56px) clamp(16px,4vw,32px);box-sizing:border-box;
            background:var(--bg);
        }
        .account-notice-shell::before{
            content:'';position:absolute;inset:0;pointer-events:none;
            background-image:radial-gradient(circle,color-mix(in srgb,var(--primary) 7%,transparent) 1px,transparent 1px);
            background-size:28px 28px;
        }
        .account-notice-blob{position:absolute;border-radius:50%;pointer-events:none;filter:blur(60px);opacity:.45;}
        .account-notice-blob--a{width:320px;height:320px;top:-80px;left:-80px;background:radial-gradient(circle,color-mix(in srgb,var(--primary) 55%,transparent),transparent 70%);}
        .account-notice-blob--b{width:260px;height:260px;bottom:-60px;right:-40px;background:radial-gradient(circle,color-mix(in srgb,var(--primary) 25%,transparent),transparent 70%);}
        .account-notice-card{
            position:relative;z-index:1;text-align:center;width:100%;max-width:440px;
        }
        .account-notice-icon{
            width:52px;height:52px;border-radius:14px;margin:0 auto 18px;
            background:var(--btn-bg);
            display:grid;place-items:center;color:var(--card);font-size:22px;
            box-shadow:0 4px 14px color-mix(in srgb,var(--primary) 38%,transparent);
        }
        .account-notice-title{margin:0 0 8px;font-size:clamp(18px,2.5vw,22px);font-weight:800;color:var(--text);letter-spacing:-.02em;line-height:1.2;}
        .account-notice-sub{margin:0 0 24px;font-size:14px;color:var(--muted);line-height:1.5;}
        .account-notice-btn{
            display:inline-flex;align-items:center;justify-content:center;gap:8px;
            padding:12px 24px;border-radius:11px;border:none;cursor:pointer;
            background:var(--btn-bg);
            color:var(--card);font-size:14px;font-weight:700;text-decoration:none;
            box-shadow:0 4px 14px color-mix(in srgb,var(--primary) 38%,transparent);
            transition:opacity .2s,transform .15s;
        }
        .account-notice-btn:hover{opacity:.9;transform:translateY(-1px);}
        /* Guide character on the no-account panel */
        .acn-guide{
            position:fixed;right:0;bottom:60px;z-index:60;
            display:flex;flex-direction:column;align-items:flex-end;gap:0;
            pointer-events:none;
        }
        @media(max-width:860px){.acn-guide{display:none;}}
        .acn-guide--hidden{display:none!important;}
        .acn-guide-bubble{
            position:relative;margin-right:28px;margin-bottom:10px;
            max-width:240px;padding:14px 16px;border-radius:16px;
            background:var(--card);
            border:1.5px solid var(--border);
            box-shadow:0 8px 28px -8px rgba(0,0,0,.18);
            pointer-events:auto;
            animation:acnBubblePop .45s cubic-bezier(.34,1.56,.64,1) both;
        }
        .acn-guide-bubble::before{
            content:'';position:absolute;bottom:-11px;right:35px;
            width:18px;height:11px;
            background:var(--border);
            clip-path:polygon(0 0,100% 0,50% 100%);
        }
        .acn-guide-bubble::after{
            content:'';position:absolute;bottom:-9px;right:36px;
            width:16px;height:10px;
            background:var(--card);
            clip-path:polygon(0 0,100% 0,50% 100%);
        }
        .acn-guide-bubble-tag{
            display:inline-flex;align-items:center;gap:5px;
            font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;
            color:var(--primary);margin-bottom:6px;
        }
        .acn-guide-bubble-text{font-size:12.5px;line-height:1.55;color:var(--text);font-weight:500;margin:0 0 12px;}
        .acn-guide-reasons{margin:0 0 12px;padding:0;list-style:none;display:flex;flex-direction:column;gap:5px;}
        .acn-guide-reasons li{display:flex;align-items:flex-start;gap:7px;font-size:12px;color:var(--text);line-height:1.4;}
        .acn-guide-reasons li i{color:var(--primary);font-size:11px;margin-top:2px;flex-shrink:0;}
        .acn-guide-close{
            display:flex;align-items:center;justify-content:center;gap:5px;width:100%;
            padding:6px 10px;border-radius:8px;border:1.5px solid var(--border);
            background:transparent;color:var(--muted);font-size:11px;font-weight:700;
            font-family:inherit;cursor:pointer;pointer-events:auto;
            transition:border-color .18s,color .18s,background .18s;
        }
        .acn-guide-close:hover{border-color:color-mix(in srgb,#ef4444 50%,var(--border));color:#ef4444;background:color-mix(in srgb,#ef4444 6%,transparent);}
        .acn-guide-char{width:200px;height:auto;display:block;filter:drop-shadow(0 12px 28px rgba(0,0,0,.22));}
        @keyframes acnBubblePop{from{opacity:0;transform:scale(.88) translateY(10px)}to{opacity:1;transform:scale(1) translateY(0)}}
        @keyframes acnGuideFadeOut{from{opacity:1;transform:translateY(0)}to{opacity:0;transform:translateY(18px)}}
    </style>
    <div class="account-notice-shell">
        <div class="account-notice-blob account-notice-blob--a" aria-hidden="true"></div>
        <div class="account-notice-blob account-notice-blob--b" aria-hidden="true"></div>
        <div class="account-notice-card">
            <div class="account-notice-icon"><i class="fa fa-building-columns" aria-hidden="true"></i></div>
            <h2 class="account-notice-title">No bank account yet</h2>
            <p class="account-notice-sub">
                Add at least one bank account for <strong>{{ $business?->name ?? 'your business' }}</strong> to unlock your workspace.
            </p>
            <a class="account-notice-btn" href="{{ route('account.onboarding') }}">
                <i class="fa fa-plus" aria-hidden="true"></i> Add bank account
            </a>
        </div>
    </div>
    {{-- Guide character --}}
    <div class="acn-guide" id="acnGuide" aria-hidden="true">
        <div class="acn-guide-bubble" id="acnGuideBubble">
            <div class="acn-guide-bubble-tag">
                <i class="fa fa-circle-info" aria-hidden="true"></i> Why do I need this?
            </div>
            <p class="acn-guide-bubble-text">A bank account connects your business to financial tracking. Here's what it unlocks:</p>
            <ul class="acn-guide-reasons">
                <li><i class="fa fa-circle-check" aria-hidden="true"></i> Record income &amp; expenses automatically</li>
                <li><i class="fa fa-circle-check" aria-hidden="true"></i> Track cash flow and balances in real time</li>
                <li><i class="fa fa-circle-check" aria-hidden="true"></i> Run payroll and pay bills from one place</li>
                <li><i class="fa fa-circle-check" aria-hidden="true"></i> Generate financial reports instantly</li>
            </ul>
            <button type="button" class="acn-guide-close" id="acnGuideClose" aria-label="Dismiss">
                <i class="fa fa-xmark" aria-hidden="true"></i> Dismiss
            </button>
        </div>
        <img src="{{ asset('bussiness_man.png') }}" alt="Guide character" class="acn-guide-char">
    </div>
    <script>
    (function(){
        const guide = document.getElementById('acnGuide');
        const closeBtn = document.getElementById('acnGuideClose');
        if (closeBtn && guide) {
            closeBtn.addEventListener('click', function () {
                guide.style.animation = 'acnGuideFadeOut .3s ease forwards';
                setTimeout(function () { guide.classList.add('acn-guide--hidden'); }, 300);
            });
        }
    })();
    </script>
@else
    @if(session('status'))
        <div class="card" style="margin-bottom:14px;max-width:100%;padding:0;border:none;">
            <div style="display:flex;gap:12px;align-items:flex-start;padding:14px 16px;border-radius:14px;background:linear-gradient(135deg,#ecfdf5,#dcfce7);border:1px solid #86efac;">
                <div style="width:28px;height:28px;border-radius:999px;background:#22c55e;color:#fff;display:grid;place-items:center;font-weight:700;flex-shrink:0;">✓</div>
                <div>
                    <div style="color:#166534;font-weight:700;">{{ session('status') }}</div>
                    <div style="color:#15803d;font-size:13px;margin-top:2px;">
                        Great job! Your current account is now connected to this business. You can continue with daily operations and financial tracking.
                    </div>
                </div>
            </div>
        </div>
    @endif
    <div id="overview-add-panel-wrap" style="display:none;justify-content:flex-end;align-items:center;margin-bottom:8px;">
        <button type="button" id="overview-add-panel-btn" class="linkbtn" style="padding:7px 11px;font-size:12px;border-radius:8px;">
            <i class="fa fa-plus" style="margin-right:6px;"></i>Add panel
        </button>
    </div>
    <div id="overview-panels-stack" style="display:grid;gap:14px;">
    <div class="card overview-panel-card" data-panel-id="expense" style="max-width:100%;position:relative;">
        <span class="overview-panel-handle" title="Drag panel"><i class="fa fa-grip-lines" aria-hidden="true"></i></span>
        <h2 class="overview-panel-title" data-panel-title="expense" style="margin:0 0 8px;">Do you want manage your business expenses?</h2>
        <p class="muted overview-panel-subtitle" data-panel-subtitle="expense" style="margin:0 0 14px;">Choose expense categories to start tracking your business spending professionally.</p>
        <div id="overview-expense-grid" class="overview-panel-grid" data-panel-key="expense" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;">
            @if($loanOverviewTooltip && ($loanOverviewTooltip['hasLoans'] ?? false))
            <style>
                #dash-loan-summary-pop{position:fixed;z-index:220;opacity:0;visibility:hidden;width:min(340px,calc(100vw - 20px));max-height:70vh;overflow:auto;pointer-events:none;
                    transition:opacity .14s ease,visibility .14s ease;
                    padding:14px 16px;border-radius:14px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 98%,transparent);
                    box-shadow:0 20px 50px rgba(0,0,0,.38);backdrop-filter:blur(8px);font-size:12px;line-height:1.45;}
                #dash-loan-summary-pop.dash-loan-summary-pop--on{opacity:1;visibility:visible;}
                #dash-loan-summary-pop .dls-title{font-weight:800;font-size:13px;margin:0 0 6px;letter-spacing:-.02em;}
                #dash-loan-summary-pop .dls-sub{color:var(--muted);margin:0 0 12px;font-size:11px;}
                #dash-loan-summary-pop .dls-loan{border-top:1px solid color-mix(in srgb,var(--border) 80%,transparent);padding:10px 0 10px;margin:0;}
                #dash-loan-summary-pop .dls-loan:first-of-type{border-top:0;padding-top:0;}
                #dash-loan-summary-pop .dls-loan-name{font-weight:700;font-size:13px;margin:0 0 2px;}
                #dash-loan-summary-pop .dls-row{display:flex;justify-content:space-between;gap:10px;margin-top:4px;color:var(--muted);flex-wrap:wrap;}
                #dash-loan-summary-pop .dls-strong{color:var(--text);font-weight:700;}
                #dash-loan-summary-pop .dls-foot{margin-top:12px;padding-top:10px;border-top:1px solid var(--border);font-weight:800;font-size:13px;display:flex;justify-content:space-between;gap:8px;}
                #dash-loan-summary-pop .dls-hint{font-size:10px;color:var(--muted);margin-top:10px;line-height:1.4;}
                #dash-loan-summary-trigger{outline:none;border-radius:12px;}
            </style>
            @endif
            @if($loanOverviewTooltip && ($loanOverviewTooltip['hasLoans'] ?? false))
            <span id="dash-loan-summary-trigger" class="overview-tile-item" data-tile-id="expense-loan" style="display:block;margin:0;padding:0;">
            @endif
            <a class="{{ ($loanOverviewTooltip && ($loanOverviewTooltip['hasLoans'] ?? false)) ? '' : 'overview-tile-item' }}" data-tile-id="{{ ($loanOverviewTooltip && ($loanOverviewTooltip['hasLoans'] ?? false)) ? '' : 'expense-loan' }}" href="{{ route('account.loans.index') }}" style="border:1px solid var(--border);border-radius:12px;padding:12px;text-decoration:none;color:inherit;display:block;transition:border-color .2s ease;"
               onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='var(--border)'">
                <div style="font-weight:700;"><i class="fa fa-hand-holding-dollar" style="margin-right:6px;"></i>Loan</div>
                <div class="muted" style="font-size:12px;margin-top:4px;">Track repayments and interest payments.</div>
            </a>
            @if($loanOverviewTooltip && ($loanOverviewTooltip['hasLoans'] ?? false))
            </span>
            @endif
            <a class="overview-tile-item" data-tile-id="expense-rental" href="{{ route('account.rentals.index') }}" style="border:1px solid var(--border);border-radius:12px;padding:12px;text-decoration:none;color:inherit;display:block;transition:border-color .2s ease;"
               onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='var(--border)'">
                <div style="font-weight:700;"><i class="fa fa-house" style="margin-right:6px;"></i>Rentenal</div>
                <div class="muted" style="font-size:12px;margin-top:4px;">Manage office/shop monthly rental costs.</div>
            </a>
            <a class="overview-tile-item" data-tile-id="expense-property" href="{{ route('account.properties.index') }}" style="border:1px solid var(--border);border-radius:12px;padding:12px;text-decoration:none;color:inherit;display:block;transition:border-color .2s ease;"
               onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='var(--border)'">
                <div style="font-weight:700;"><i class="fa fa-building" style="margin-right:6px;"></i>Property</div>
                <div class="muted" style="font-size:12px;margin-top:4px;">Track property and lease-related expenses.</div>
            </a>
            <a class="overview-tile-item" data-tile-id="expense-bills" href="{{ route('account.bills.index') }}" style="border:1px solid var(--border);border-radius:12px;padding:12px;text-decoration:none;color:inherit;display:block;transition:border-color .2s ease;"
               onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='var(--border)'">
                <div style="font-weight:700;"><i class="fa fa-file-invoice-dollar" style="margin-right:6px;"></i>Bills</div>
                <div class="muted" style="font-size:12px;margin-top:4px;">Record utility and service bill payments on a schedule.</div>
            </a>
            <a class="overview-tile-item" data-tile-id="expense-employee-salary" href="{{ route('hr.onboarding') }}" style="border:1px solid var(--border);border-radius:12px;padding:12px;text-decoration:none;color:inherit;display:block;transition:border-color .2s ease;"
               onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='var(--border)'">
                <div style="font-weight:700;"><i class="fa fa-users-gear" style="margin-right:6px;"></i>Employee Salary</div>
                <div class="muted" style="font-size:12px;margin-top:4px;">Start the payroll wizard or say you manage staff outside SociBiz.</div>
            </a>
            <a class="overview-tile-item" data-tile-id="expense-modification" href="{{ route('modification.index') }}" style="border:1px solid var(--border);border-radius:12px;padding:12px;text-decoration:none;color:inherit;display:block;transition:border-color .2s ease;"
               onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='var(--border)'">
                <div style="font-weight:700;"><i class="fa fa-screwdriver-wrench" style="margin-right:6px;"></i>Modification</div>
                <div class="muted" style="font-size:12px;margin-top:4px;">Capture renovation or improvement costs.</div>
            </a>
            <a class="overview-tile-item" data-tile-id="expense-purchases" href="{{ Route::has('purchase.index') ? route('purchase.index') : route('product.index') }}" style="border:1px solid var(--border);border-radius:12px;padding:12px;text-decoration:none;color:inherit;display:block;transition:border-color .2s ease;"
               onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='var(--border)'">
                <div style="font-weight:700;"><i class="fa fa-file-invoice" style="margin-right:6px;"></i>Purchase orders</div>
                <div class="muted" style="font-size:12px;margin-top:4px;">Create POs, place with suppliers, and receive stock.</div>
            </a>
            @if(Route::has('filemanager.index'))
            <a class="overview-tile-item" data-tile-id="expense-files" href="{{ route('filemanager.index') }}" style="border:1px solid var(--border);border-radius:12px;padding:12px;text-decoration:none;color:inherit;display:block;transition:border-color .2s ease;"
               onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='var(--border)'">
                <div style="font-weight:700;"><i class="fa fa-folder-open" style="margin-right:6px;"></i>Files</div>
                <div class="muted" style="font-size:12px;margin-top:4px;">Upload and organize business documents.</div>
            </a>
            @endif
            <div class="overview-tile-item" data-tile-id="expense-legal" style="border:1px solid var(--border);border-radius:12px;padding:12px;">
                <div style="font-weight:700;"><i class="fa fa-scale-balanced" style="margin-right:6px;"></i>Legal</div>
                <div class="muted" style="font-size:12px;margin-top:4px;">Manage legal and compliance-related fees.</div>
            </div>
            <div class="overview-tile-item" data-tile-id="expense-transport" style="border:1px solid var(--border);border-radius:12px;padding:12px;">
                <div style="font-weight:700;"><i class="fa fa-truck" style="margin-right:6px;"></i>Transport</div>
                <div class="muted" style="font-size:12px;margin-top:4px;">Record logistics and travel expenses.</div>
            </div>
            <div class="overview-tile-item" data-tile-id="expense-marketing" style="border:1px solid var(--border);border-radius:12px;padding:12px;">
                <div style="font-weight:700;"><i class="fa fa-bullhorn" style="margin-right:6px;"></i>Marketing</div>
                <div class="muted" style="font-size:12px;margin-top:4px;">Track campaign and marketing spend.</div>
            </div>
            <div class="overview-tile-item" data-tile-id="expense-promotions" style="border:1px solid var(--border);border-radius:12px;padding:12px;">
                <div style="font-weight:700;"><i class="fa fa-gift" style="margin-right:6px;"></i>Promotions</div>
                <div class="muted" style="font-size:12px;margin-top:4px;">Manage discounts and promo-related costs.</div>
            </div>
            <div class="overview-tile-item" data-tile-id="expense-other" style="border:1px solid var(--border);border-radius:12px;padding:12px;">
                <div style="font-weight:700;"><i class="fa fa-layer-group" style="margin-right:6px;"></i>Other Expenses</div>
                <div class="muted" style="font-size:12px;margin-top:4px;">Capture any uncategorized business expenses.</div>
            </div>
        </div>
    </div>
    <div class="card overview-panel-card" data-panel-id="income" style="max-width:100%;position:relative;">
        <span class="overview-panel-handle" title="Drag panel"><i class="fa fa-grip-lines" aria-hidden="true"></i></span>
        <h2 class="overview-panel-title" data-panel-title="income" style="margin:0 0 8px;">Hows your income?</h2>
        <p class="muted overview-panel-subtitle" data-panel-subtitle="income" style="margin:0 0 14px;">Monitor your revenue performance and growth metrics in one place.</p>
        <div id="overview-income-grid" class="overview-panel-grid" data-panel-key="income" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;">
            @if(Route::has('pos.online'))
            <a class="overview-tile-item" data-tile-id="income-sales" href="{{ route('pos.online') }}" style="border:1px solid var(--border);border-radius:12px;padding:12px;text-decoration:none;color:inherit;display:block;transition:border-color .2s ease;"
                onmouseover="this.style.borderColor='color-mix(in srgb,var(--primary) 45%,var(--border))'" onmouseout="this.style.borderColor='var(--border)'">
                <div style="font-weight:700;"><i class="fa fa-chart-line" style="margin-right:6px;"></i>Sales</div>
                <div class="muted" style="font-size:12px;margin-top:4px;">Open the online retail POS terminal and track sales.</div>
            </a>
            @else
            <div class="overview-tile-item" data-tile-id="income-sales" style="border:1px solid var(--border);border-radius:12px;padding:12px;">
                <div style="font-weight:700;"><i class="fa fa-chart-line" style="margin-right:6px;"></i>Sales</div>
                <div class="muted" style="font-size:12px;margin-top:4px;">Track total sales trends and daily performance.</div>
            </div>
            @endif
            <div class="overview-tile-item" data-tile-id="income-report" style="border:1px solid var(--border);border-radius:12px;padding:12px;">
                <div style="font-weight:700;"><i class="fa fa-file-lines" style="margin-right:6px;"></i>Income Report</div>
                <div class="muted" style="font-size:12px;margin-top:4px;">Review detailed income summaries by period.</div>
            </div>
            <div class="overview-tile-item" data-tile-id="income-customer-growth" style="border:1px solid var(--border);border-radius:12px;padding:12px;">
                <div style="font-weight:700;"><i class="fa fa-user-plus" style="margin-right:6px;"></i>Customer Growth</div>
                <div class="muted" style="font-size:12px;margin-top:4px;">Measure new customer acquisition over time.</div>
            </div>
            <div class="overview-tile-item" data-tile-id="income-credit-recovery" style="border:1px solid var(--border);border-radius:12px;padding:12px;">
                <div style="font-weight:700;"><i class="fa fa-money-bill-wave" style="margin-right:6px;"></i>Credit Recovery</div>
                <div class="muted" style="font-size:12px;margin-top:4px;">Follow outstanding credit collection progress.</div>
            </div>
            <div class="overview-tile-item" data-tile-id="income-lead-management" style="border:1px solid var(--border);border-radius:12px;padding:12px;">
                <div style="font-weight:700;"><i class="fa fa-funnel-dollar" style="margin-right:6px;"></i>Lead Management</div>
                <div class="muted" style="font-size:12px;margin-top:4px;">Track lead pipeline and conversion value.</div>
            </div>
        </div>
    </div>
    </div>
@endif

@php
$wizFeatMetaForJs = array_values(array_map(function ($f) {
    return ['key' => $f['key'], 'label' => $f['label'], 'icon' => $f['icon']];
}, $wizFeatureItems));
@endphp
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>

<script>
(function () {
    const wizardForm = document.getElementById('businessWizardForm');
    if (!wizardForm) return;

    const steps = {
        1: document.getElementById('wizardStep1'),
        2: document.getElementById('wizardStep2'),
        3: document.getElementById('wizardStep3'),
        4: document.getElementById('wizardStep4'),
    };
    const dots = {
        1: document.getElementById('wizDot1'),
        2: document.getElementById('wizDot2'),
        3: document.getElementById('wizDot3'),
        4: document.getElementById('wizDot4'),
    };
    const lbls = {
        1: document.getElementById('wizLbl1'),
        2: document.getElementById('wizLbl2'),
        3: document.getElementById('wizLbl3'),
        4: document.getElementById('wizLbl4'),
    };
    const lines = {
        1: document.getElementById('wizStepLine1'),
        2: document.getElementById('wizStepLine2'),
        3: document.getElementById('wizStepLine3'),
    };
    const actionGroups = wizardForm.querySelectorAll('[data-wiz-actions]');

    function setWizStep(num, goingBack) {
        Object.keys(steps).forEach(function (key) {
            const n = parseInt(key, 10);
            if (!steps[n]) return;
            if (n === num) {
                steps[n].style.display = 'block';
                steps[n].classList.remove('wiz-slide', 'wiz-slide-back');
                steps[n].classList.add(goingBack ? 'wiz-slide-back' : 'wiz-slide');
            } else {
                steps[n].style.display = 'none';
            }
            if (dots[n]) dots[n].className = 'wiz-step-dot' + (n === num ? ' wiz-step-dot--current' : (n < num ? ' wiz-step-dot--done' : ''));
            if (lbls[n]) lbls[n].classList.toggle('wiz-step-lbl--active', n === num);
        });
        Object.keys(lines).forEach(function (key) {
            const n = parseInt(key, 10);
            if (lines[n]) lines[n].classList.toggle('wiz-step-connector--done', n < num);
        });
        actionGroups.forEach(function (group) {
            group.style.display = parseInt(group.getAttribute('data-wiz-actions'), 10) === num ? 'flex' : 'none';
        });
        updateGuide(num);
    }

    // Guide character bubble
    const wizGuide           = document.getElementById('wizGuide');
    const wizGuideBubble     = document.getElementById('wizGuideBubble');
    const wizGuideBubbleText = document.getElementById('wizGuideBubbleText');
    const wizGuideBubbleStepLabel = document.getElementById('wizGuideBubbleStepLabel');
    const wizGuideCloseBtn   = document.getElementById('wizGuideClose');

    if (wizGuideCloseBtn && wizGuide) {
        wizGuideCloseBtn.addEventListener('click', function () {
            wizGuide.style.animation = 'wizGuideFadeOut .3s ease forwards';
            setTimeout(function () { wizGuide.classList.add('wiz-guide--hidden'); }, 300);
        });
    }
    const guideMessages = {
        1: { step: 'Step 1 of 4', text: 'Hi there! What do you call your business? Use the name your customers already know.' },
        2: { step: 'Step 2 of 4', text: 'Pick the category that best describes what you do — it helps me set up the right modules for you!' },
        3: { step: 'Step 3 of 4', text: 'Toggle the features you need. Required ones stay on. You can change these any time later.' },
        4: { step: 'Step 4 of 4', text: 'Almost done! Tell me where you operate — single location or multiple branches?' },
    };
    function updateGuide(num) {
        if (!wizGuideBubble || !wizGuideBubbleText) return;
        const msg = guideMessages[num];
        if (!msg) return;
        wizGuideBubbleText.textContent = msg.text;
        if (wizGuideBubbleStepLabel) wizGuideBubbleStepLabel.textContent = msg.step;
        wizGuideBubble.classList.remove('wiz-bubble-change');
        void wizGuideBubble.offsetWidth; // reflow to retrigger animation
        wizGuideBubble.classList.add('wiz-bubble-change');
    }

    function setGuideBubble(stepLabel, text) {
        if (!wizGuideBubble || wizGuide?.classList.contains('wiz-guide--hidden')) return;
        if (wizGuideBubbleStepLabel) wizGuideBubbleStepLabel.textContent = stepLabel;
        wizGuideBubbleText.textContent = text;
        wizGuideBubble.classList.remove('wiz-bubble-change');
        void wizGuideBubble.offsetWidth;
        wizGuideBubble.classList.add('wiz-bubble-change');
    }

    // Category card hover — show richer info in guide bubble
    document.querySelectorAll('.wiz-cat-card').forEach(function (card) {
        card.addEventListener('mouseenter', function () {
            const btn      = card.querySelector('.wiz-cat-btn-detail');
            if (!btn) return;
            const label    = btn.getAttribute('data-cat-label') || '';
            const users    = btn.getAttribute('data-cat-users') || '';
            const fullDesc = btn.getAttribute('data-cat-fulldesc') || '';
            const short    = fullDesc.length > 120 ? fullDesc.slice(0, 118) + '…' : fullDesc;
            setGuideBubble(label + ' · ' + users + ' businesses', short);
        });
        card.addEventListener('mouseleave', function () {
            const msg = guideMessages[2];
            if (msg) setGuideBubble(msg.step, msg.text);
        });
    });

    wizardForm.querySelectorAll('[data-wiz-next]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const target = parseInt(btn.getAttribute('data-wiz-next'), 10);
            if (target === 3) {
                const catInput = document.getElementById('wizard-company-category');
                if (catInput && !catInput.value) {
                    const firstCard = wizardForm.querySelector('.wiz-cat-card');
                    if (firstCard) firstCard.focus();
                    return;
                }
            }
            if (target === 4) {
                const branchNameInput = document.getElementById('wiz-branch-name');
                const bizNameInput = wizardForm.querySelector('input[name="name"]');
                if (branchNameInput && bizNameInput && !branchNameInput.value.trim()) {
                    branchNameInput.value = bizNameInput.value.trim();
                }
            }
            setWizStep(target, false);
        });
    });
    wizardForm.querySelectorAll('[data-wiz-back]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const target = parseInt(btn.getAttribute('data-wiz-back'), 10);
            setWizStep(target, true);
        });
    });

    const nextStepBtn = document.getElementById('nextStepBtn');
    if (nextStepBtn) {
        nextStepBtn.addEventListener('click', function () {
            const nameInput = wizardForm.querySelector('input[name="name"]');
            if (!nameInput.value.trim()) { nameInput.focus(); return; }
            setWizStep(2, false);
        });
    }

    // Feature cards (step 3)
    const POS_DEPS = ['product_management', 'stock_management'];
    function isFeatureOn(card) {
        return card.classList.contains('wiz-feat-card--on');
    }
    function refreshDepStates() {
        wizardForm.querySelectorAll('[data-depends-on]').forEach(function (card) {
            const deps = card.getAttribute('data-depends-on').split(',');
            const blocked = deps.some(function (depKey) {
                const depCard = wizardForm.querySelector('.wiz-feat-card[data-feature="' + depKey + '"]');
                return depCard && !isFeatureOn(depCard);
            });
            const hint = card.querySelector('.wiz-feat-dep-hint');
            if (blocked && isFeatureOn(card)) {
                toggleFeatureCard(card, false);
            }
            card.classList.toggle('wiz-feat-card--dep-blocked', blocked);
            if (hint) hint.style.display = blocked ? 'block' : 'none';
        });
    }
    function toggleFeatureCard(card, forceOn) {
        if (card.classList.contains('wiz-feat-card--required')) return;
        const on = typeof forceOn === 'boolean' ? forceOn : !isFeatureOn(card);
        card.classList.toggle('wiz-feat-card--on', on);
        card.setAttribute('aria-checked', on ? 'true' : 'false');
        const badge = card.querySelector('.wiz-feat-badge');
        if (badge) badge.textContent = on ? 'Enabled' : 'Disabled';
        const checkbox = card.querySelector('input[type="checkbox"]');
        if (checkbox) checkbox.checked = on;
    }
    function refreshFeatSidebar() {
        let count = 0;
        wizardForm.querySelectorAll('.wiz-feat-card[data-feature]').forEach(function (card) {
            const key = card.getAttribute('data-feature');
            const on = card.classList.contains('wiz-feat-card--on');
            const required = card.classList.contains('wiz-feat-card--required');
            if (on) count++;
            const item = document.getElementById('wizSidebarItem_' + key);
            const status = document.getElementById('wizSidebarStatus_' + key);
            if (!item) return;
            item.classList.toggle('wiz-feat-sidebar-item--off', !on);
            if (status) {
                if (required) {
                    status.className = 'wiz-feat-sidebar-status wiz-feat-sidebar-status--req';
                    status.innerHTML = '<i class="fa fa-lock" aria-hidden="true"></i> Required';
                } else if (on) {
                    status.className = 'wiz-feat-sidebar-status wiz-feat-sidebar-status--on';
                    status.innerHTML = '<i class="fa fa-circle-check" aria-hidden="true"></i> Enabled';
                } else {
                    status.className = 'wiz-feat-sidebar-status wiz-feat-sidebar-status--off';
                    status.innerHTML = '<i class="fa fa-circle-xmark" aria-hidden="true"></i> Disabled';
                }
            }
        });
        const countBadge = document.getElementById('wizFeatEnabledCount');
        if (countBadge) countBadge.textContent = count;
    }
    wizardForm.querySelectorAll('.wiz-feat-card:not(.wiz-feat-card--preview)').forEach(function (card) {
        card.addEventListener('click', function () {
            if (card.getAttribute('data-depends-on') && card.classList.contains('wiz-feat-card--dep-blocked')) return;
            toggleFeatureCard(card);
            refreshDepStates();
            refreshFeatSidebar();
        });
        card.addEventListener('keydown', function (e) {
            if (e.key === ' ' || e.key === 'Enter') { e.preventDefault(); card.click(); }
        });
    });
    refreshDepStates();
    refreshFeatSidebar();

    // Category card grid → select + details modal
    const categoryInput = document.getElementById('wizard-company-category');
    const wizCatModal = document.getElementById('wizCatModal');
    const wizCatModalBody = document.getElementById('wizCatModalBody');
    const wizCatModalSelectBtn = document.getElementById('wizCatModalSelectBtn');
    const wizCatModalClose = document.getElementById('wizCatModalClose');
    const wizCatModalCloseBtn = document.getElementById('wizCatModalCloseBtn');
    const wizCatModalBackdrop = document.getElementById('wizCatModalBackdrop');

    if (categoryInput) {
        let categoryMap = {};
        try { categoryMap = JSON.parse(categoryInput.getAttribute('data-wiz-category-map') || '{}'); } catch (e) {}

        const wizFeatAllMeta = @json($wizFeatMetaForJs);

        function applyCategoryRecommendation(slug) {
            const recommended = categoryMap[slug] || ['account_management'];
            wizardForm.querySelectorAll('.wiz-feat-card[data-feature]').forEach(function (card) {
                if (card.classList.contains('wiz-feat-card--required')) return;
                toggleFeatureCard(card, recommended.indexOf(card.getAttribute('data-feature')) !== -1);
            });
            refreshDepStates();
            refreshFeatSidebar();
        }

        function selectCategory(slug) {
            categoryInput.value = slug;
            document.querySelectorAll('.wiz-cat-card').forEach(function (c) {
                const on = c.getAttribute('data-cat-slug') === slug;
                c.classList.toggle('wiz-cat-card--on', on);
                const btn = c.querySelector('.wiz-cat-btn-select span');
                if (btn) btn.textContent = on ? 'Selected' : 'Select';
            });
            applyCategoryRecommendation(slug);
        }

        document.querySelectorAll('.wiz-cat-btn-select').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                selectCategory(btn.getAttribute('data-cat-slug'));
            });
        });

        // Details modal
        let modalCurrentSlug = null;

        function openCatModal(btn) {
            const slug        = btn.getAttribute('data-cat-slug');
            const label       = btn.getAttribute('data-cat-label');
            const icon        = btn.getAttribute('data-cat-icon');
            const users       = btn.getAttribute('data-cat-users');
            const fullDesc    = btn.getAttribute('data-cat-fulldesc');
            const highlights  = JSON.parse(btn.getAttribute('data-cat-highlights') || '[]');
            const activeMods  = JSON.parse(btn.getAttribute('data-cat-mods') || '[]');
            modalCurrentSlug  = slug;

            const highlightsHtml = highlights.map(function (h) {
                return '<li><i class="fa fa-circle-check" aria-hidden="true"></i>' + h + '</li>';
            }).join('');

            const modsHtml = wizFeatAllMeta.map(function (feat) {
                const on = activeMods.indexOf(feat.key) !== -1;
                return '<span class="wiz-cat-modal__mod ' + (on ? 'wiz-cat-modal__mod--on' : 'wiz-cat-modal__mod--off') + '">'
                    + '<i class="fa ' + feat.icon + '" aria-hidden="true"></i> ' + feat.label + '</span>';
            }).join('');

            wizCatModalBody.innerHTML =
                '<div class="wiz-cat-modal__hero">'
                + '<div class="wiz-cat-modal__ico"><i class="fa ' + icon + '" aria-hidden="true"></i></div>'
                + '<div><h2 class="wiz-cat-modal__title" id="wizCatModalTitle">' + label + '</h2>'
                + '<p class="wiz-cat-modal__users"><i class="fa fa-users" aria-hidden="true"></i> ' + users + ' businesses using this</p></div>'
                + '</div>'
                + '<p class="wiz-cat-modal__fulldesc">' + fullDesc + '</p>'
                + '<div class="wiz-cat-modal__section">'
                + '<p class="wiz-cat-modal__section-title">What you can do</p>'
                + '<ul class="wiz-cat-modal__highlights">' + highlightsHtml + '</ul>'
                + '</div>'
                + '<div class="wiz-cat-modal__section">'
                + '<p class="wiz-cat-modal__section-title">Modules for this category</p>'
                + '<div class="wiz-cat-modal__mods">' + modsHtml + '</div>'
                + '</div>';

            const isSelected = categoryInput.value === slug;
            wizCatModalSelectBtn.innerHTML = isSelected
                ? '<i class="fa fa-check" aria-hidden="true"></i> Selected'
                : '<i class="fa fa-check" aria-hidden="true"></i> Select this category';
            wizCatModalSelectBtn.disabled = isSelected;

            wizCatModal.removeAttribute('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeCatModal() {
            wizCatModal.setAttribute('hidden', '');
            document.body.style.overflow = '';
            modalCurrentSlug = null;
        }

        document.querySelectorAll('.wiz-cat-btn-detail').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                openCatModal(btn);
            });
        });

        wizCatModalSelectBtn && wizCatModalSelectBtn.addEventListener('click', function () {
            if (modalCurrentSlug) selectCategory(modalCurrentSlug);
            wizCatModalSelectBtn.innerHTML = '<i class="fa fa-check" aria-hidden="true"></i> Selected';
            wizCatModalSelectBtn.disabled = true;
        });

        wizCatModalClose    && wizCatModalClose.addEventListener('click', closeCatModal);
        wizCatModalCloseBtn && wizCatModalCloseBtn.addEventListener('click', closeCatModal);
        wizCatModalBackdrop && wizCatModalBackdrop.addEventListener('click', closeCatModal);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && wizCatModal && !wizCatModal.hasAttribute('hidden')) closeCatModal();
        });

        if (categoryInput.value) applyCategoryRecommendation(categoryInput.value);

        // Search bar filter
        const catSearchInput = document.getElementById('wizCatSearch');
        const catSearchClear = document.getElementById('wizCatSearchClear');
        const catNoResults   = document.getElementById('wizCatNoResults');
        if (catSearchInput) {
            catSearchInput.addEventListener('input', function () {
                const q = catSearchInput.value.trim().toLowerCase();
                catSearchClear.hidden = q.length === 0;
                let visible = 0;
                document.querySelectorAll('.wiz-cat-card').forEach(function (card) {
                    const slug  = card.getAttribute('data-cat-slug') || '';
                    const name  = (card.querySelector('.wiz-cat-name')?.textContent || '').toLowerCase();
                    const desc  = (card.querySelector('.wiz-cat-desc')?.textContent || '').toLowerCase();
                    const match = !q || name.includes(q) || desc.includes(q) || slug.includes(q);
                    card.style.display = match ? '' : 'none';
                    if (match) visible++;
                });
                if (catNoResults) catNoResults.hidden = visible > 0;
            });
            catSearchClear.addEventListener('click', function () {
                catSearchInput.value = '';
                catSearchInput.dispatchEvent(new Event('input'));
                catSearchInput.focus();
            });
        }
    }

    // Feature search (step 3)
    const featSearchInput = document.getElementById('wizFeatSearch');
    const featSearchClear = document.getElementById('wizFeatSearchClear');
    const featNoResults   = document.getElementById('wizFeatNoResults');
    if (featSearchInput) {
        featSearchInput.addEventListener('input', function () {
            const q = featSearchInput.value.trim().toLowerCase();
            featSearchClear.hidden = q.length === 0;
            let visible = 0;
            document.querySelectorAll('.wiz-feat-card').forEach(function (card) {
                const name = (card.querySelector('.wiz-feat-name')?.textContent || '').toLowerCase();
                const desc = (card.querySelector('.wiz-feat-desc')?.textContent || '').toLowerCase();
                const match = !q || name.includes(q) || desc.includes(q);
                card.style.display = match ? '' : 'none';
                if (match) visible++;
            });
            if (featNoResults) featNoResults.hidden = visible > 0;
        });
        featSearchClear.addEventListener('click', function () {
            featSearchInput.value = '';
            featSearchInput.dispatchEvent(new Event('input'));
            featSearchInput.focus();
        });
    }

    // Location toggle (step 4)
    const mwSwitch = document.getElementById('wiz-mw-switch');
    const mwVal = document.getElementById('wiz-mw-val');
    const mwLblOff = document.getElementById('wizMwLblOff');
    if (mwSwitch && mwVal) {
        mwSwitch.addEventListener('click', function () {
            const on = mwSwitch.getAttribute('aria-checked') !== 'true';
            mwSwitch.setAttribute('aria-checked', on ? 'true' : 'false');
            mwVal.value = on ? '1' : '0';
            if (mwLblOff) mwLblOff.textContent = on ? 'Multiple locations' : 'Single location';
        });
    }

    @if($errors->has('branch_name') || $errors->has('multi_warehouse_branch'))
        setWizStep(4, false);
    @elseif($errors->has('company_category_slug'))
        setWizStep(2, false);
    @endif
})();
</script>
@if($business && $hasBankAccountForBusiness)
<script>
(function () {
    if (typeof Sortable === 'undefined') return;
    const businessId = @json($business?->id);
    const prefix = 'overview.sort.order.' + String(businessId || 'global') + '.';

    const style = document.createElement('style');
    style.textContent = [
        '.overview-tile-item{position:relative;}',
        '.overview-tile-handle{position:absolute;top:8px;right:8px;width:22px;height:22px;display:inline-flex;align-items:center;justify-content:center;border:1px solid var(--border);border-radius:8px;background:color-mix(in srgb,var(--card) 92%,transparent);color:var(--muted);cursor:grab;z-index:3;}',
        '.overview-tile-handle:active{cursor:grabbing;}',
        '.overview-tile-handle:hover{border-color:color-mix(in srgb,var(--primary) 35%,var(--border));color:var(--text);}',
        '.overview-tile-handle i{font-size:11px;line-height:1;}',
        '.overview-panel-card{padding-top:18px;}',
        '.overview-panel-handle{position:absolute;top:8px;right:8px;width:24px;height:24px;display:inline-flex;align-items:center;justify-content:center;border:1px solid var(--border);border-radius:8px;background:color-mix(in srgb,var(--card) 92%,transparent);color:var(--muted);cursor:grab;z-index:4;}',
        '.overview-panel-handle:active{cursor:grabbing;}',
        '.overview-panel-handle:hover{border-color:color-mix(in srgb,var(--primary) 35%,var(--border));color:var(--text);}',
        '.overview-panel-title{cursor:text;}',
        '.overview-panel-title.overview-panel-title--editing{outline:2px solid color-mix(in srgb,var(--primary) 35%,transparent);outline-offset:2px;border-radius:6px;padding:2px 6px;margin:-2px -6px 6px;}',
        '.overview-panel-subtitle{cursor:text;}',
        '.overview-panel-subtitle.overview-panel-subtitle--editing{outline:2px solid color-mix(in srgb,var(--primary) 28%,transparent);outline-offset:2px;border-radius:6px;padding:2px 6px;margin:-2px -6px 8px;}',
        '.overview-panel-empty{border:1px dashed color-mix(in srgb,var(--primary) 22%,var(--border));border-radius:10px;padding:12px;font-size:12px;color:var(--muted);text-align:center;}',
        '.overview-panel-sort-ghost{opacity:.7;}',
        '.overview-sort-ghost{opacity:.6;transform:scale(.99);}',
        '.overview-sort-chosen{box-shadow:0 8px 22px -14px rgba(0,0,0,.45);}'
    ].join('');
    document.head.appendChild(style);
    let dragMoved = false;

    function ensureHandles(gridEl) {
        Array.from(gridEl.querySelectorAll('.overview-tile-item')).forEach(function (tile) {
            if (tile.querySelector('.overview-tile-handle')) return;
            const handle = document.createElement('span');
            handle.className = 'overview-tile-handle';
            handle.setAttribute('title', 'Drag to reorder');
            handle.innerHTML = '<i class="fa fa-grip-vertical" aria-hidden="true"></i>';
            tile.appendChild(handle);
        });
    }

    function saveOrder(gridEl, key) {
        const ids = Array.from(gridEl.children)
            .map((el) => el.getAttribute('data-tile-id'))
            .filter((v) => !!v);
        localStorage.setItem(prefix + key, JSON.stringify(ids));
    }

    function applyOrder(gridEl, key) {
        const raw = localStorage.getItem(prefix + key);
        if (!raw) return;
        let ids = [];
        try { ids = JSON.parse(raw); } catch (e) { return; }
        if (!Array.isArray(ids)) return;
        const nodes = Array.from(gridEl.children);
        const map = new Map(nodes.map((n) => [n.getAttribute('data-tile-id'), n]));
        ids.forEach((id) => {
            const node = map.get(id);
            if (node) gridEl.appendChild(node);
        });
    }

    function saveAllOrders() {
        document.querySelectorAll('.overview-panel-grid[data-panel-key]').forEach(function (grid) {
            saveOrder(grid, getGridKey(grid));
        });
    }

    function savePanelOrder(stackEl) {
        const ids = Array.from(stackEl.children)
            .map((el) => el.getAttribute('data-panel-id'))
            .filter((v) => !!v);
        localStorage.setItem(prefix + 'panels', JSON.stringify(ids));
    }
    function extraPanelsStorageKey() {
        return prefix + 'extraPanels';
    }

    function titleStorageKey(panelKey) {
        return prefix + 'panelTitle.' + panelKey;
    }
    function subtitleStorageKey(panelKey) {
        return prefix + 'panelSubtitle.' + panelKey;
    }

    function wirePanelTitleInlineEdit() {
        document.querySelectorAll('.overview-panel-title[data-panel-title]').forEach(function (titleEl) {
            if (titleEl.dataset.inlineWiredTitle === '1') return;
            titleEl.dataset.inlineWiredTitle = '1';
            var panelKey = titleEl.getAttribute('data-panel-title');
            if (!panelKey) return;
            var original = titleEl.textContent.trim();
            var saved = localStorage.getItem(titleStorageKey(panelKey));
            if (saved && saved.trim() !== '') {
                titleEl.textContent = saved;
            }

            function beginEdit() {
                if (titleEl.isContentEditable) return;
                titleEl.classList.add('overview-panel-title--editing');
                titleEl.contentEditable = 'true';
                titleEl.dataset.originalTitle = titleEl.textContent.trim() || original;
                var range = document.createRange();
                range.selectNodeContents(titleEl);
                var sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(range);
            }

            function finishEdit(cancel) {
                if (!titleEl.isContentEditable) return;
                var fallback = titleEl.dataset.originalTitle || original;
                var next = cancel ? fallback : titleEl.textContent.replace(/\s+/g, ' ').trim();
                if (next === '') next = fallback;
                titleEl.textContent = next;
                titleEl.contentEditable = 'false';
                titleEl.classList.remove('overview-panel-title--editing');
                localStorage.setItem(titleStorageKey(panelKey), next);
                persistExtraPanels();
            }

            titleEl.addEventListener('dblclick', function (e) {
                e.preventDefault();
                beginEdit();
            });
            titleEl.addEventListener('blur', function () { finishEdit(false); });
            titleEl.addEventListener('keydown', function (e) {
                if (!titleEl.isContentEditable) return;
                if (e.key === 'Enter') {
                    e.preventDefault();
                    titleEl.blur();
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    finishEdit(true);
                }
            });
        });
    }

    function wirePanelSubtitleInlineEdit() {
        document.querySelectorAll('.overview-panel-subtitle[data-panel-subtitle]').forEach(function (subtitleEl) {
            if (subtitleEl.dataset.inlineWiredSubtitle === '1') return;
            subtitleEl.dataset.inlineWiredSubtitle = '1';
            var panelKey = subtitleEl.getAttribute('data-panel-subtitle');
            if (!panelKey) return;
            var original = subtitleEl.textContent.trim();
            var saved = localStorage.getItem(subtitleStorageKey(panelKey));
            if (saved && saved.trim() !== '') {
                subtitleEl.textContent = saved;
            }

            function beginEdit() {
                if (subtitleEl.isContentEditable) return;
                subtitleEl.classList.add('overview-panel-subtitle--editing');
                subtitleEl.contentEditable = 'true';
                subtitleEl.dataset.originalSubtitle = subtitleEl.textContent.trim() || original;
                var range = document.createRange();
                range.selectNodeContents(subtitleEl);
                var sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(range);
            }

            function finishEdit(cancel) {
                if (!subtitleEl.isContentEditable) return;
                var fallback = subtitleEl.dataset.originalSubtitle || original;
                var next = cancel ? fallback : subtitleEl.textContent.replace(/\s+/g, ' ').trim();
                if (next === '') next = fallback;
                subtitleEl.textContent = next;
                subtitleEl.contentEditable = 'false';
                subtitleEl.classList.remove('overview-panel-subtitle--editing');
                localStorage.setItem(subtitleStorageKey(panelKey), next);
                persistExtraPanels();
            }

            subtitleEl.addEventListener('dblclick', function (e) {
                e.preventDefault();
                beginEdit();
            });
            subtitleEl.addEventListener('blur', function () { finishEdit(false); });
            subtitleEl.addEventListener('keydown', function (e) {
                if (!subtitleEl.isContentEditable) return;
                if (e.key === 'Enter') {
                    e.preventDefault();
                    subtitleEl.blur();
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    finishEdit(true);
                }
            });
        });
    }

    function applyPanelOrder(stackEl) {
        const raw = localStorage.getItem(prefix + 'panels');
        if (!raw) return;
        let ids = [];
        try { ids = JSON.parse(raw); } catch (e) { return; }
        if (!Array.isArray(ids)) return;
        const nodes = Array.from(stackEl.children);
        const map = new Map(nodes.map((n) => [n.getAttribute('data-panel-id'), n]));
        ids.forEach((id) => {
            const node = map.get(id);
            if (node) stackEl.appendChild(node);
        });
    }

    function getGridKey(gridEl) {
        return gridEl.getAttribute('data-panel-key') || 'expense';
    }

    function initGrid(grid) {
        if (!grid) return;
        const key = getGridKey(grid);
        applyOrder(grid, key);
        ensureHandles(grid);
        Sortable.create(grid, {
            draggable: '.overview-tile-item',
            handle: '.overview-tile-handle',
            group: 'overview-panels',
            animation: 170,
            ghostClass: 'overview-sort-ghost',
            chosenClass: 'overview-sort-chosen',
            onStart: function () { dragMoved = false; },
            onMove: function () { dragMoved = true; },
            onEnd: function (evt) {
                const targetGrid = evt.to;
                const sourceGrid = evt.from;
                if (sourceGrid) saveOrder(sourceGrid, getGridKey(sourceGrid));
                if (targetGrid) saveOrder(targetGrid, getGridKey(targetGrid));
                saveAllOrders();
            }
        });
    }

    function persistExtraPanels() {
        const stack = document.getElementById('overview-panels-stack');
        if (!stack) return;
        const extras = Array.from(stack.querySelectorAll('.overview-panel-card[data-panel-extra="1"]')).map(function (card) {
            const id = card.getAttribute('data-panel-id') || '';
            const titleEl = card.querySelector('.overview-panel-title');
            const subtitleEl = card.querySelector('.overview-panel-subtitle');
            return {
                id: id,
                title: (titleEl ? titleEl.textContent : 'New panel').trim() || 'New panel',
                subtitle: (subtitleEl ? subtitleEl.textContent : 'Drag items here').trim() || 'Drag items here',
            };
        }).filter(function (x) { return !!x.id; });
        localStorage.setItem(extraPanelsStorageKey(), JSON.stringify(extras));
    }

    function createExtraPanelCard(panel) {
        const panelId = panel.id;
        const card = document.createElement('div');
        card.className = 'card overview-panel-card';
        card.setAttribute('data-panel-id', panelId);
        card.setAttribute('data-panel-extra', '1');
        card.style.cssText = 'max-width:100%;position:relative;';
        card.innerHTML =
            '<span class="overview-panel-handle" title="Drag panel"><i class="fa fa-grip-lines" aria-hidden="true"></i></span>' +
            '<h2 class="overview-panel-title" data-panel-title="' + panelId + '" style="margin:0 0 8px;">' + panel.title + '</h2>' +
            '<p class="muted overview-panel-subtitle" data-panel-subtitle="' + panelId + '" style="margin:0 0 14px;">' + panel.subtitle + '</p>' +
            '<div class="overview-panel-grid" data-panel-key="' + panelId + '" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;">' +
            '<div class="overview-panel-empty">Drag items here</div>' +
            '</div>';
        return card;
    }

    function loadExtraPanels() {
        const stack = document.getElementById('overview-panels-stack');
        if (!stack) return;
        const raw = localStorage.getItem(extraPanelsStorageKey());
        if (!raw) return;
        let panels = [];
        try { panels = JSON.parse(raw); } catch (e) { return; }
        if (!Array.isArray(panels)) return;
        panels.forEach(function (panel) {
            if (!panel || !panel.id) return;
            if (stack.querySelector('[data-panel-id="' + panel.id + '"]')) return;
            const card = createExtraPanelCard({
                id: String(panel.id),
                title: String(panel.title || 'New panel'),
                subtitle: String(panel.subtitle || 'Drag items here')
            });
            stack.appendChild(card);
            const grid = card.querySelector('.overview-panel-grid');
            initGrid(grid);
        });
    }

    function wireAddPanelButton() {
        const btn = document.getElementById('overview-add-panel-btn');
        const btnWrap = document.getElementById('overview-add-panel-wrap');
        const stack = document.getElementById('overview-panels-stack');
        if (!btn || !stack || !btnWrap) return;

        function showAddPanelButton() {
            btnWrap.style.display = 'flex';
        }

        stack.addEventListener('dblclick', function () {
            showAddPanelButton();
        });

        btn.addEventListener('click', function () {
            const id = 'custom-' + Date.now();
            const card = createExtraPanelCard({
                id: id,
                title: 'New panel',
                subtitle: 'Double-click title/description to edit. Drag items here.'
            });
            stack.appendChild(card);
            const grid = card.querySelector('.overview-panel-grid');
            initGrid(grid);
            wirePanelTitleInlineEdit();
            wirePanelSubtitleInlineEdit();
            persistExtraPanels();
            savePanelOrder(stack);
        });
    }

    initGrid(document.getElementById('overview-expense-grid'));
    initGrid(document.getElementById('overview-income-grid'));
    loadExtraPanels();
    const panelStack = document.getElementById('overview-panels-stack');
    if (panelStack) {
        applyPanelOrder(panelStack);
        Sortable.create(panelStack, {
            draggable: '.overview-panel-card',
            handle: '.overview-panel-handle',
            animation: 170,
            ghostClass: 'overview-panel-sort-ghost',
            onEnd: function () {
                savePanelOrder(panelStack);
                persistExtraPanels();
            }
        });
    }
    wirePanelTitleInlineEdit();
    wirePanelSubtitleInlineEdit();
    wireAddPanelButton();
    document.addEventListener('click', function (e) {
        if (!dragMoved) return;
        dragMoved = false;
        e.preventDefault();
        e.stopPropagation();
    }, true);
})();
</script>
@endif
@if($loanOverviewTooltip && ($loanOverviewTooltip['hasLoans'] ?? false))
<script>
(function () {
    const payload = @json($loanOverviewTooltip);
    const trigger = document.getElementById('dash-loan-summary-trigger');
    if (!trigger || !payload || !payload.hasLoans) return;

    function esc(s) {
        return String(s ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function money(code, amount) {
        const c = code ? esc(code) + '&nbsp;' : '';
        return c + esc(String(amount ?? ''));
    }

    const pop = document.createElement('div');
    pop.id = 'dash-loan-summary-pop';
    pop.setAttribute('role', 'tooltip');
    pop.setAttribute('aria-hidden', 'true');
    document.body.appendChild(pop);

    let hideTimer;

    function buildHtml() {
        const biz = esc(payload.businessName || '');
        const cur = payload.currency || '';
        let body = '';

        if (!payload.hasLoans) {
            body += '<p class="dls-title">Loan summary</p>';
            body += '<p class="dls-sub">' + biz + ' — no loans yet. Open Loans to track repayments.</p>';
        } else {
            body += '<p class="dls-title">' + biz + '</p>';
            body += '<p class="dls-sub">' + esc(String(payload.loanCount)) + ' loan';
            body += payload.loanCount === 1 ? '' : 's';
            body += ' · installments use nominal APR amortization or flat total interest (same rules as Loan Management preview).</p>';

            payload.loans.forEach(function (L) {
                body += '<div class="dls-loan">';
                body += '<p class="dls-loan-name">' + esc(L.name);
                body += ' <span style="opacity:.72;font-weight:600;font-size:11px;">' + esc(L.bankName) + '</span></p>';
                body += '<div class="dls-row"><span>Principal</span><span class="dls-strong">' + money(cur, L.principalFormatted) + '</span></div>';
                body += '<div class="dls-row"><span>Interest</span><span>' + esc(L.rateTypeLabel) + '&nbsp;' + esc(L.rateDisplay) + '</span></div>';
                body += '<div class="dls-row"><span>Schedule</span><span>' + esc(L.cadenceLabel) + ' · ' + esc(String(L.periods)) + ' periods <span style="opacity:.85;">(' + esc(L.periodsSource) + ')</span></span></div>';
                body += '<div class="dls-row"><span>Per period</span><span class="dls-strong">' + money(cur, L.installmentFormatted) + '</span></div>';
                body += '<div class="dls-row"><span>Budget equiv. monthly</span><span class="dls-strong">' + money(cur, L.approxMonthlyFormatted) + '</span></div>';
                const dt = [];
                if (L.firstDue) dt.push(esc(L.firstDue));
                if (L.ending) dt.push(esc(L.ending));
                if (dt.length) {
                    body += '<div class="dls-row"><span>Dates</span><span>' + dt.join(' → ') + '</span></div>';
                }
                body += '</div>';
            });

            body += '<div class="dls-foot"><span>Approx. monthly (all loans)</span><span>' + money(cur, payload.formattedTotalMonthly) + '</span></div>';
            body += '<p class="dls-hint">Monthly budgeting scales daily installments ×30 and yearly ÷12.</p>';
        }

        pop.innerHTML = body;
    }

    function positionPop() {
        const margin = 8;
        const rect = trigger.getBoundingClientRect();
        const pw = pop.offsetWidth || 320;
        let left = rect.left + rect.width / 2 - pw / 2;
        left = Math.max(margin, Math.min(left, window.innerWidth - margin - pw));
        let top = rect.bottom + margin;
        const ph = pop.offsetHeight;
        if (ph && top + ph > window.innerHeight - margin && rect.top > ph + margin) {
            top = rect.top - ph - margin;
        }
        pop.style.left = Math.round(left) + 'px';
        pop.style.top = Math.round(top) + 'px';
    }

    function repositionIfVisible() {
        if (pop.classList.contains('dash-loan-summary-pop--on')) positionPop();
    }

    window.addEventListener('resize', repositionIfVisible);
    window.addEventListener('scroll', repositionIfVisible, true);

    function showTip() {
        window.clearTimeout(hideTimer);
        buildHtml();
        pop.classList.add('dash-loan-summary-pop--on');
        pop.setAttribute('aria-hidden', 'false');
        positionPop();
        window.requestAnimationFrame(function () { positionPop(); });
    }

    function hideTip() {
        hideTimer = window.setTimeout(function () {
            pop.classList.remove('dash-loan-summary-pop--on');
            pop.setAttribute('aria-hidden', 'true');
        }, 200);
    }

    trigger.addEventListener('mouseenter', showTip);
    trigger.addEventListener('mouseleave', hideTip);
})();
</script>
@endif
@endsection
