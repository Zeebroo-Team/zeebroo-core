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
        html.business-wizard-active,
        html.business-wizard-active body{
            overflow:hidden;
            height:100%;
        }
        html.business-wizard-active .layout{
            height:100vh;
            max-height:100vh;
            overflow:hidden;
        }
        html.business-wizard-active .content{
            display:flex;
            flex-direction:column;
            min-height:0;
            height:100vh;
            max-height:100vh;
            overflow:hidden;
        }
        html.business-wizard-active .content-inner{
            flex:1;
            min-height:0;
            display:flex;
            flex-direction:column;
            padding:0 !important;
            overflow:hidden;
        }
        .wizard-shell{
            flex:1;
            min-height:0;
            width:100%;
            margin:0;
            padding:0;
            display:flex;
            flex-direction:column;
            background:var(--bg);
            overflow:hidden;
        }
        .wizard-panel{
            position:relative;
            flex:1;
            min-height:0;
            max-height:100%;
            width:100%;
            overflow:hidden;
            display:flex;
            flex-direction:column;
            justify-content:center;
            align-items:center;
            border:none;
            border-radius:0;
            padding:clamp(20px,4vh,36px);
            box-sizing:border-box;
            background:#ffffff;
            color:#1f2937;
            box-shadow:none;
        }
        .wizard-bg{
            position:absolute;
            inset:0;
            overflow:hidden;
            pointer-events:none;
        }
        .wizard-circles{
            position:absolute;
            top:0;
            left:0;
            width:100%;
            height:100%;
            margin:0;
            padding:0;
            list-style:none;
        }
        .wizard-circles li{
            position:absolute;
            display:block;
            width:20px;
            height:20px;
            background:rgba(107,114,128,0.18);
            bottom:-150px;
            animation:wizardFloat 25s linear infinite;
        }
        .wizard-circles li:nth-child(1){left:25%;width:80px;height:80px;animation-delay:0s;}
        .wizard-circles li:nth-child(2){left:10%;width:20px;height:20px;animation-delay:2s;animation-duration:12s;}
        .wizard-circles li:nth-child(3){left:70%;width:20px;height:20px;animation-delay:4s;}
        .wizard-circles li:nth-child(4){left:40%;width:60px;height:60px;animation-delay:0s;animation-duration:18s;}
        .wizard-circles li:nth-child(5){left:65%;width:20px;height:20px;animation-delay:0s;}
        .wizard-circles li:nth-child(6){left:75%;width:110px;height:110px;animation-delay:3s;}
        .wizard-circles li:nth-child(7){left:35%;width:150px;height:150px;animation-delay:7s;}
        .wizard-circles li:nth-child(8){left:50%;width:25px;height:25px;animation-delay:15s;animation-duration:45s;}
        .wizard-circles li:nth-child(9){left:20%;width:15px;height:15px;animation-delay:2s;animation-duration:35s;}
        .wizard-circles li:nth-child(10){left:85%;width:150px;height:150px;animation-delay:0s;animation-duration:11s;}
        .wizard-head{
            flex-shrink:0;
            display:flex;
            justify-content:center;
            align-items:center;
            gap:14px;
            flex-wrap:wrap;
            margin-bottom:clamp(12px,2vh,18px);
        }
        .wizard-head,.wizard-body,.wizard-help,.wizard-step{color:#4b5563}
        .wizard-title{margin:0 0 8px;font-size:34px;line-height:1.1}
        .wizard-sub{margin:0;color:var(--muted)}
        .wizard-step{display:inline-flex;align-items:center;gap:8px;padding:8px 14px;border-radius:999px;border:none;color:var(--muted);font-size:14px}
        .wizard-step .dot{width:8px;height:8px;border-radius:50%;background:#9ca3af}
        .wizard-body{
            flex:0 1 auto;
            min-height:0;
            max-height:100%;
            width:min(760px,100%);
            margin:0 auto;
            text-align:center;
            overflow:hidden;
        }
        .wizard-card{animation:fadeSlide .35s ease}
        .wizard-body,.wizard-head{position:relative;z-index:1}
        .wizard-q{margin:0 0 8px;font-size:clamp(22px,3.2vw,28px);line-height:1.15}
        .wizard-help{margin:0 0 clamp(12px,2vh,18px);color:var(--muted)}
        .wiz-input,.wiz-textarea,.wiz-select{
            width:100%;
            padding:14px 16px;
            border-radius:12px;
            border:1px solid #d1d5db;
            background:#f9fafb;
            color:#111827;
            outline:none;
            transition:all .2s ease;
            box-sizing:border-box;
        }
        .wiz-select{
            appearance:none;
            padding-right:40px;
            background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%2364748b' d='M1 1l5 5 5-5'/%3E%3C/svg%3E");
            background-repeat:no-repeat;
            background-position:right 14px center;
            cursor:pointer;
        }
        .wiz-input:focus,.wiz-textarea:focus,.wiz-select:focus{
            border-color:#a78bfa;
            background:#ffffff;
        }
        .wiz-textarea{resize:vertical;min-height:96px;max-height:min(130px,22vh)}
        .wizard-actions{margin-top:clamp(12px,2vh,18px);display:flex;gap:10px;justify-content:center;flex-shrink:0}
        #wizardStep3 .wizard-step-fields{
            display:grid;
            gap:12px;
            max-height:min(42vh,320px);
            overflow-y:auto;
            overscroll-behavior:contain;
            padding-right:4px;
            text-align:left;
        }
        .btn-soft{background:#475569}
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            width: 100%;
            max-height: min(45vh, 320px);
            overflow-y: auto;
            overscroll-behavior: contain;
            padding: 8px 4px;
            box-sizing: border-box;
            margin-top: 10px;
            margin-bottom: 10px;
        }
        @media (max-width: 480px) {
            .features-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }
        }
        .feature-card {
            background: #ffffff;
            border: 2px solid #e5e7eb;
            border-radius: 14px;
            padding: 14px;
            cursor: pointer;
            transition: all 0.2s ease-in-out;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
            box-sizing: border-box;
            user-select: none;
        }
        .feature-card:hover {
            transform: translateY(-2px);
            border-color: #a78bfa;
            box-shadow: 0 8px 16px rgba(167, 139, 250, 0.15);
        }
        .feature-card.enabled {
            border-color: #a78bfa;
            background: #fdfcff;
        }
        .feature-card.disabled {
            opacity: 0.55;
            border-color: #f3f4f6;
            background: #f9fafb;
            filter: grayscale(0.85);
        }
        .feature-image {
            width: 60px;
            height: 60px;
            object-fit: contain;
            margin-bottom: 10px;
            pointer-events: none;
        }
        .feature-title {
            font-size: 13px;
            font-weight: 700;
            color: #1f2937;
            margin: 0 0 6px 0;
            text-align: center;
            pointer-events: none;
            line-height: 1.2;
        }
        .feature-badge {
            padding: 3px 8px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            pointer-events: none;
        }
        .feature-badge.enabled-badge {
            background: #ecfdf5;
            color: #10b981;
        }
        .feature-badge.disabled-badge {
            background: #f3f4f6;
            color: #6b7280;
        }
        .feature-badge.required-badge {
            background: #ede9fe;
            color: #4f46e5;
        }
        .feature-card--required {
            cursor: default;
        }
        .feature-card--required:hover {
            transform: none;
            border-color: #a78bfa;
            box-shadow: none;
        }
        .feature-card--dep-blocked {
            opacity: 0.45;
            filter: grayscale(.6);
            border-style: dashed;
        }
        .feature-card--dep-blocked:hover {
            transform: none;
            border-color: #fbbf24;
            box-shadow: none;
        }
        @keyframes fadeSlide{
            from{opacity:0;transform:translateY(10px)}
            to{opacity:1;transform:translateY(0)}
        }
        @keyframes wizardFloat{
            0%{transform:translateY(0) rotate(0deg);opacity:1;border-radius:0;}
            100%{transform:translateY(-1000px) rotate(720deg);opacity:0;border-radius:50%;}
        }
    </style>

    <div class="wizard-shell">
        <div class="wizard-panel">
            <div class="wizard-bg" aria-hidden="true">
                <ul class="wizard-circles">
                    <li></li><li></li><li></li><li></li><li></li>
                    <li></li><li></li><li></li><li></li><li></li>
                </ul>
            </div>
            <div class="wizard-head">
                <span class="wizard-step" id="stepBadge"><span class="dot"></span>Step 1 of 2</span>
            </div>

            <form id="businessWizardForm" method="post" action="{{ route('business.onboarding.store') }}" class="wizard-body">
                @csrf
                <div id="wizardStep1" class="wizard-card">
                    <h2 class="wizard-q">What is your business/company name?</h2>
                    <p class="wizard-help">Use your public brand name.</p>
                    <input
                        type="text"
                        name="name"
                        value="{{ old('name') }}"
                        placeholder="e.g. SociBiz Solutions"
                        required
                        class="wiz-input"
                    >
                    @error('name')
                        <div style="color:#ef4444;margin-top:8px;">{{ $message }}</div>
                    @enderror
                    <div class="wizard-actions">
                        <button type="button" class="linkbtn" id="nextStepBtn">Next</button>
                    </div>
                </div>

                <div id="wizardStep3" style="display:none;" class="wizard-card">
                    <h2 class="wizard-q">What is your business category?</h2>
                    <p class="wizard-help">Tell us about your business in this quick quiz.</p>
                    <div class="wizard-step-fields">
                        <div>
                            <label for="wizard-company-category" style="display:block;margin-bottom:6px;color:var(--muted);">Business Category</label>
                            <select
                                id="wizard-company-category"
                                name="company_category_slug"
                                required
                                class="wiz-select"
                            >
                                <option value="" disabled {{ old('company_category_slug') ? '' : 'selected' }}>Select category…</option>
                                @foreach($businessCategoryOptions ?? [] as $categoryOption)
                                    <option value="{{ $categoryOption['value'] }}" @selected((string) old('company_category_slug') === (string) $categoryOption['value'])>
                                        {{ $categoryOption['label'] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('company_category_slug')
                                <div style="color:#ef4444;margin-top:8px;">{{ $message }}</div>
                            @enderror
                        </div>
                        <div>
                            <label style="display:block;margin-bottom:6px;color:var(--muted);">Tell me about that business</label>
                            <textarea
                                name="description"
                                placeholder="Describe what your company does, your target customers, and your key offering."
                                class="wiz-textarea"
                            >{{ old('description') }}</textarea>
                            @error('description')
                                <div style="color:#ef4444;margin-top:8px;">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="wizard-actions">
                        <button type="button" class="linkbtn btn-soft" id="backToStep1Btn">Back</button>
                        <button type="submit" class="linkbtn">Finish Setup</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@elseif(!$hasBankAccountForBusiness)
    <style>
        .account-notice-shell{
            min-height:calc(100vh - 73px);
            display:grid;
            place-items:center;
            width:100%;
            margin:0;
            padding:0;
        }
        .account-notice-panel{
            width:100%;
            min-height:calc(100vh - 92px);
            display:grid;
            place-items:center;
            background:#ffffff;
            color:#1f2937;
        }
        .account-notice-card{
            text-align:center;
            max-width:620px;
            padding:24px;
        }
    </style>
    <div class="account-notice-shell">
        <div class="account-notice-panel">
            <div class="account-notice-card">
                <h2 style="margin:0 0 8px;">No bank account for this business</h2>
                <p style="margin:0 0 18px;color:#6b7280;">
                    Add at least one bank account for <strong>{{ $business?->name ?? 'your business' }}</strong> to continue here.
                </p>
                <a class="linkbtn" href="{{ route('account.onboarding') }}">Please add account</a>
            </div>
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
    const stepBadge   = document.getElementById('stepBadge');
    const wizardStep1 = document.getElementById('wizardStep1');
    const wizardStep3 = document.getElementById('wizardStep3');
    const nextStepBtn  = document.getElementById('nextStepBtn');
    const backToStep1Btn = document.getElementById('backToStep1Btn');
    const wizardForm   = document.getElementById('businessWizardForm');

    if (nextStepBtn && wizardStep1 && wizardStep3 && stepBadge && wizardForm) {
        nextStepBtn.addEventListener('click', () => {
            const nameInput = wizardForm.querySelector('input[name="name"]');
            if (!nameInput.value.trim()) {
                nameInput.focus();
                return;
            }
            wizardStep1.style.display = 'none';
            wizardStep3.style.display = 'block';
            stepBadge.textContent = 'Step 2 of 2';
        });
    }

    if (backToStep1Btn && wizardStep1 && wizardStep3 && stepBadge) {
        backToStep1Btn.addEventListener('click', () => {
            wizardStep3.style.display = 'none';
            wizardStep1.style.display = 'block';
            stepBadge.textContent = 'Step 1 of 2';
        });
    }

    @if($errors->has('company_category_slug') || $errors->has('description'))
        if (wizardStep1 && wizardStep3 && stepBadge) {
            wizardStep1.style.display = 'none';
            wizardStep3.style.display = 'block';
            stepBadge.textContent = 'Step 2 of 2';
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
