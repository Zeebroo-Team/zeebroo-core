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
        html.business-wizard-active .content{display:flex;flex-direction:column;min-height:0;height:100vh;max-height:100vh;overflow:hidden;}
        html.business-wizard-active .content-inner{flex:1;min-height:0;display:flex;flex-direction:column;padding:0!important;overflow:hidden;}

        /* Shell & panel */
        .wiz-shell{flex:1;min-height:0;width:100%;display:flex;flex-direction:column;overflow:hidden;}
        .wiz-panel{
            position:relative;flex:1;min-height:0;width:100%;overflow:hidden;
            display:flex;flex-direction:column;align-items:center;justify-content:center;
            padding:clamp(24px,5vh,56px) clamp(16px,4vw,32px);box-sizing:border-box;
            background:var(--bg);
        }
        .wiz-panel::before{
            content:'';position:absolute;inset:0;pointer-events:none;
            background-image:radial-gradient(circle,color-mix(in srgb,var(--primary) 7%,transparent) 1px,transparent 1px);
            background-size:28px 28px;
        }
        /* Decorative blobs */
        .wiz-blob{position:absolute;border-radius:50%;pointer-events:none;filter:blur(60px);opacity:.45;}
        .wiz-blob--a{width:320px;height:320px;top:-80px;left:-80px;background:radial-gradient(circle,color-mix(in srgb,var(--primary) 55%,transparent),transparent 70%);}
        .wiz-blob--b{width:260px;height:260px;bottom:-60px;right:-40px;background:radial-gradient(circle,color-mix(in srgb,var(--primary) 25%,transparent),transparent 70%);}

        /* Step indicator */
        .wiz-stepper{
            position:relative;z-index:1;display:flex;align-items:flex-start;gap:0;
            margin-bottom:22px;flex-shrink:0;
        }
        .wiz-step-item{display:flex;flex-direction:column;align-items:center;gap:5px;}
        .wiz-step-dot{
            width:34px;height:34px;border-radius:50%;display:grid;place-items:center;
            font-size:13px;font-weight:800;transition:all .3s ease;
            background:var(--border);color:var(--muted);border:2px solid transparent;
        }
        .wiz-step-dot--current{background:var(--card);color:var(--primary);border-color:var(--primary);box-shadow:0 0 0 4px color-mix(in srgb,var(--primary) 15%,transparent);}
        .wiz-step-dot--done{background:var(--primary);color:var(--card);border-color:var(--primary);}
        .wiz-step-lbl{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);transition:color .3s;}
        .wiz-step-lbl--active{color:var(--primary);}
        .wiz-step-connector{width:56px;height:2px;background:var(--border);margin:17px 4px 0;flex-shrink:0;transition:background .3s ease;border-radius:2px;}
        .wiz-step-connector--done{background:var(--primary);}

        /* Card */
        .wiz-card{
            position:relative;z-index:1;width:100%;max-width:468px;
            flex-shrink:1;min-height:0;overflow-y:auto;overflow-x:hidden;
        }
        .wiz-card-eyebrow{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--primary);margin:0 0 6px;text-align:center;}
        .wiz-card-title{font-size:clamp(20px,2.8vw,25px);font-weight:800;color:var(--text);margin:0 0 6px;line-height:1.2;letter-spacing:-.025em;text-align:center;}
        .wiz-card-sub{font-size:13.5px;color:var(--muted);margin:0 0 22px;line-height:1.5;text-align:center;}

        /* Fields */
        .wiz-field{margin-bottom:14px;}
        .wiz-field label{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text);margin-bottom:6px;}
        .wiz-input,.wiz-select,.wiz-textarea{
            width:100%;padding:12px 14px;border-radius:10px;
            border:1.5px solid var(--border);background:color-mix(in srgb,var(--card) 94%,transparent);
            color:var(--text);font-size:14px;font-family:inherit;
            outline:none;transition:border-color .2s,box-shadow .2s,background .2s;box-sizing:border-box;
        }
        .wiz-input::placeholder,.wiz-textarea::placeholder{color:var(--muted);}
        .wiz-input:focus,.wiz-select:focus,.wiz-textarea:focus{
            border-color:var(--primary);background:var(--card);
            box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 12%,transparent);
        }
        .wiz-select{
            appearance:none;padding-right:36px;cursor:pointer;
            background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%236b7280' d='M1 1l5 5 5-5'/%3E%3C/svg%3E");
            background-repeat:no-repeat;background-position:right 13px center;background-color:color-mix(in srgb,var(--card) 94%,transparent);
        }
        .wiz-select:focus{background-color:var(--card);}
        .wiz-textarea{resize:vertical;min-height:88px;max-height:min(140px,20vh);line-height:1.5;}
        .wiz-field-error{color:#ef4444;font-size:12px;margin-top:5px;}

        /* Buttons */
        .wiz-btn-primary{
            display:flex;align-items:center;justify-content:center;gap:8px;
            width:100%;padding:13px 20px;border-radius:11px;border:none;
            background:var(--btn-bg);
            color:var(--card);font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;
            transition:opacity .2s,transform .15s,box-shadow .2s;
            box-shadow:0 4px 14px color-mix(in srgb,var(--primary) 38%,transparent);margin-top:20px;
        }
        .wiz-btn-primary:hover{opacity:.9;transform:translateY(-1px);box-shadow:0 6px 20px color-mix(in srgb,var(--primary) 45%,transparent);}
        .wiz-btn-primary:active{transform:translateY(0);}
        .wiz-btn-back{
            display:inline-flex;align-items:center;gap:6px;
            padding:13px 20px;border-radius:11px;
            background:transparent;border:1.5px solid var(--border);
            font-size:14px;font-weight:700;color:var(--muted);
            cursor:pointer;font-family:inherit;
            transition:border-color .2s,color .2s,background .2s;
        }
        .wiz-btn-back:hover{border-color:var(--primary);color:var(--text);background:color-mix(in srgb,var(--primary) 6%,transparent);}
        .wiz-actions-row{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:20px;}
        .wiz-actions-row .wiz-btn-primary{flex:1;margin-top:0;}

        /* Footer note */
        .wiz-footer-note{
            position:relative;z-index:1;margin-top:16px;font-size:12px;
            color:var(--muted);text-align:center;display:flex;align-items:center;
            justify-content:center;gap:5px;flex-shrink:0;
        }

        /* Step slide animations */
        @keyframes wizSlideIn{from{opacity:0;transform:translateX(20px);}to{opacity:1;transform:translateX(0);}}
        @keyframes wizSlideBack{from{opacity:0;transform:translateX(-20px);}to{opacity:1;transform:translateX(0);}}
        .wiz-slide{animation:wizSlideIn .28s cubic-bezier(.4,0,.2,1) forwards;}
        .wiz-slide-back{animation:wizSlideBack .28s cubic-bezier(.4,0,.2,1) forwards;}
    </style>

    <div class="wiz-shell">
        <div class="wiz-panel">
            <div class="wiz-blob wiz-blob--a" aria-hidden="true"></div>
            <div class="wiz-blob wiz-blob--b" aria-hidden="true"></div>

            {{-- Step indicator --}}
            <div class="wiz-stepper" aria-label="Setup progress">
                <div class="wiz-step-item">
                    <div class="wiz-step-dot wiz-step-dot--current" id="wizDot1">1</div>
                    <span class="wiz-step-lbl wiz-step-lbl--active">Business</span>
                </div>
                <div class="wiz-step-connector" id="wizStepLine"></div>
                <div class="wiz-step-item">
                    <div class="wiz-step-dot" id="wizDot2">2</div>
                    <span class="wiz-step-lbl" id="wizLbl2">Category</span>
                </div>
            </div>

            {{-- Card --}}
            <div class="wiz-card">
                <form id="businessWizardForm" method="post" action="{{ route('business.onboarding.store') }}">
                    @csrf

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
                        <button type="button" class="wiz-btn-primary" id="nextStepBtn">
                            Continue <i class="fa fa-arrow-right" aria-hidden="true"></i>
                        </button>
                    </div>

                    {{-- Step 2: Category & description --}}
                    <div id="wizardStep3" style="display:none;">
                        <h2 class="wiz-card-title">Tell us about your business</h2>
                        <p class="wiz-card-sub">Help us personalise your workspace from day one.</p>
                        <div class="wiz-field">
                            <label for="wizard-company-category">Business category</label>
                            <select id="wizard-company-category" name="company_category_slug" required class="wiz-select">
                                <option value="" disabled {{ old('company_category_slug') ? '' : 'selected' }}>Select a category…</option>
                                @foreach($businessCategoryOptions ?? [] as $categoryOption)
                                    <option value="{{ $categoryOption['value'] }}" @selected((string) old('company_category_slug') === (string) $categoryOption['value'])>
                                        {{ $categoryOption['label'] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('company_category_slug')
                                <div class="wiz-field-error">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="wiz-field">
                            <label for="wiz-desc">Short description <span style="font-weight:500;text-transform:none;letter-spacing:0;color:var(--muted);">(optional)</span></label>
                            <textarea
                                id="wiz-desc"
                                name="description"
                                placeholder="Describe what your company does, your target customers, and your key offering."
                                class="wiz-textarea"
                            >{{ old('description') }}</textarea>
                            @error('description')
                                <div class="wiz-field-error">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="wiz-actions-row">
                            <button type="button" class="wiz-btn-back" id="backToStep1Btn">
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

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>

<script>
    const wizardStep1    = document.getElementById('wizardStep1');
    const wizardStep3    = document.getElementById('wizardStep3');
    const nextStepBtn    = document.getElementById('nextStepBtn');
    const backToStep1Btn = document.getElementById('backToStep1Btn');
    const wizardForm     = document.getElementById('businessWizardForm');
    const wizDot1        = document.getElementById('wizDot1');
    const wizDot2        = document.getElementById('wizDot2');
    const wizLbl2        = document.getElementById('wizLbl2');
    const wizStepLine    = document.getElementById('wizStepLine');

    function setWizStep(num) {
        if (num === 1) {
            wizDot1.className = 'wiz-step-dot wiz-step-dot--current';
            wizDot2.className = 'wiz-step-dot';
            if (wizStepLine) wizStepLine.classList.remove('wiz-step-connector--done');
            if (wizLbl2) wizLbl2.classList.remove('wiz-step-lbl--active');
        } else {
            wizDot1.className = 'wiz-step-dot wiz-step-dot--done';
            wizDot2.className = 'wiz-step-dot wiz-step-dot--current';
            if (wizStepLine) wizStepLine.classList.add('wiz-step-connector--done');
            if (wizLbl2) wizLbl2.classList.add('wiz-step-lbl--active');
        }
    }

    if (nextStepBtn && wizardStep1 && wizardStep3 && wizardForm) {
        nextStepBtn.addEventListener('click', () => {
            const nameInput = wizardForm.querySelector('input[name="name"]');
            if (!nameInput.value.trim()) { nameInput.focus(); return; }
            wizardStep1.style.display = 'none';
            wizardStep3.style.display = 'block';
            wizardStep3.classList.remove('wiz-slide-back');
            wizardStep3.classList.add('wiz-slide');
            setWizStep(2);
        });
    }

    if (backToStep1Btn && wizardStep1 && wizardStep3) {
        backToStep1Btn.addEventListener('click', () => {
            wizardStep3.style.display = 'none';
            wizardStep1.style.display = 'block';
            wizardStep1.classList.remove('wiz-slide');
            wizardStep1.classList.add('wiz-slide-back');
            setWizStep(1);
        });
    }

    @if($errors->has('company_category_slug') || $errors->has('description'))
        if (wizardStep1 && wizardStep3) {
            wizardStep1.style.display = 'none';
            wizardStep3.style.display = 'block';
            setWizStep(2);
        }
    @endif
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
