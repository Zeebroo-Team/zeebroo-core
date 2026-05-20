@extends('theme::layouts.app', ['title' => 'Your location', 'heading' => 'Your primary location', 'minimalAppShell' => true])

@section('content')
<style>
.setup-loc-shell{max-width:520px;margin:0 auto;padding:8px 0 28px;}
.setup-loc-lead{font-size:14px;line-height:1.55;margin:0 0 18px;color:var(--text);}
.branch-form-grid{display:grid;gap:10px;}@media (min-width:720px){.branch-form-grid--2{grid-template-columns:repeat(2,minmax(0,1fr));gap:12px 16px}}
.branch-field label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:5px;}
.branch-field input,.branch-field textarea{width:100%;box-sizing:border-box;padding:9px 10px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);}
.branch-field textarea{min-height:70px;line-height:1.45;resize:vertical;font-family:inherit;}
.branch-active-row{display:flex;align-items:center;justify-content:space-between;gap:14px;width:100%;padding:11px 14px;box-sizing:border-box;border-radius:10px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 94%,transparent);}
.branch-active-row__lbl{margin:0;font-size:13px;font-weight:600;color:var(--text);cursor:pointer;}
.branch-switch{position:relative;display:inline-block;width:46px;height:26px;flex-shrink:0;}
.branch-switch input{opacity:0;width:0;height:0;margin:0;position:absolute;}
.branch-switch-slider{position:absolute;inset:0;cursor:pointer;background:#475569;border-radius:999px;transition:.2s;}
.branch-switch-slider:before{content:"";position:absolute;height:20px;width:20px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.2s;box-shadow:0 1px 3px rgba(0,0,0,.22);}
.branch-switch input:checked + .branch-switch-slider{background:#22c55e;}
.branch-switch input:checked + .branch-switch-slider:before{transform:translateX(20px);}
:is(html[data-theme="light"],html[data-theme="light_blue"]) .branch-switch-slider{background:color-mix(in srgb,#475569 75%,var(--border));}
.branch-switch input:focus-visible + .branch-switch-slider{box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 45%,transparent);}
</style>

<div class="setup-loc-shell card" style="max-width:560px;margin-inline:auto;padding:20px;">
    @if(session('status'))
        <div style="margin:0 0 14px;padding:10px 12px;border-radius:10px;border:1px solid color-mix(in srgb,#22c55e 40%,var(--border));background:color-mix(in srgb,#22c55e 9%,transparent);font-size:13px;font-weight:600;color:var(--text);">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div style="margin:0 0 14px;padding:10px 12px;border-radius:10px;border:1px solid color-mix(in srgb,#f87171 40%,var(--border));background:color-mix(in srgb,#f87171 8%,transparent);font-size:13px;color:var(--text);">{{ $errors->first() }}</div>
    @endif

    <p class="setup-loc-lead">
        You chose <strong>single location</strong> for <strong>{{ $business->name }}</strong>. Enter this site’s details once—we’ll hide branch management navigation so Overview stays simpler.
    </p>

    @include('business::branches.partials.create-form', [
        'singleLocationSetup' => true,
        'submitLabel' => 'Save and continue',
        'showBranchCreateErrorBanner' => false,
    ])
</div>
@endsection
