@extends('theme::layouts.app', [
    'title' => 'Community',
    'heading' => 'Community',
    'minimalAppShell' => true,
    'hideNavbar' => true,
])

@section('content')
@include('business::get-started.partials.topnav', ['gsActive' => 'community'])
<style>
    .cm-wrap{max-width:880px;margin:0 auto;}
    .cm-hero{text-align:center;padding:clamp(12px,4vh,36px) 0 clamp(24px,5vh,40px);}
    .cm-eyebrow{
        display:inline-flex;align-items:center;gap:7px;padding:5px 13px;border-radius:999px;margin-bottom:16px;
        background:color-mix(in srgb,var(--gs-gold) 10%,#fff);color:#8a6510;border:1px solid color-mix(in srgb,var(--gs-gold) 30%,transparent);
        font-size:11.5px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;
    }
    .cm-title{margin:0 0 10px;font-size:clamp(26px,3.6vw,38px);font-weight:800;color:var(--text);letter-spacing:-.03em;line-height:1.15;}
    .cm-sub{margin:0 auto;max-width:520px;font-size:15px;color:var(--muted);line-height:1.6;}
    .cm-list{display:flex;flex-direction:column;border:1px solid var(--border);border-radius:18px;background:rgba(255,255,255,.45);overflow:hidden;
        -webkit-backdrop-filter:blur(12px) saturate(160%);backdrop-filter:blur(12px) saturate(160%);}
    .cm-row{
        display:flex;align-items:center;gap:16px;padding:20px 22px;text-decoration:none;color:inherit;
        transition:background .16s;
    }
    .cm-row + .cm-row{border-top:1px solid var(--border);}
    a.cm-row:hover{background:var(--gs-soft);}
    a.cm-row:hover .cm-arrow{transform:translateX(3px);color:var(--primary);}
    .cm-icon{
        width:44px;height:44px;border-radius:12px;flex-shrink:0;display:grid;place-items:center;font-size:18px;
        background:var(--gs-soft);color:var(--text);
    }
    .cm-icon--yt{background:color-mix(in srgb,#ef4444 12%,transparent);color:#ef4444;}
    .cm-body{flex:1;min-width:0;}
    .cm-row-title{font-size:15px;font-weight:800;color:var(--text);letter-spacing:-.01em;display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
    .cm-row-desc{display:block;margin-top:3px;font-size:13px;color:var(--muted);line-height:1.5;}
    .cm-arrow{color:var(--muted);font-size:13px;transition:transform .16s,color .16s;}
    .cm-soon{
        padding:2px 9px;border-radius:999px;font-size:10px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;
        background:color-mix(in srgb,var(--muted) 14%,transparent);color:var(--muted);
    }
    .cm-row--muted .cm-icon{background:color-mix(in srgb,var(--muted) 14%,transparent);color:var(--muted);}
    .cm-foot{text-align:center;margin:28px 0 8px;font-size:13px;color:var(--muted);}
    .cm-foot a{color:var(--primary);font-weight:700;text-decoration:none;}
    .cm-foot a:hover{text-decoration:underline;}
</style>
<div class="gs-shell">
    <div class="cm-wrap">
        <div class="cm-hero">
            <span class="cm-eyebrow"><i class="fa fa-users" aria-hidden="true"></i> Community</span>
            <h1 class="cm-title">Learn, share and grow<br>with other Zeebroo users</h1>
            <p class="cm-sub">Tutorials, guides and a place to swap ideas with business owners running on Zeebroo.</p>
        </div>

        <div class="cm-list">
            <a href="{{ $support['youtube_url'] }}" target="_blank" rel="noopener" class="cm-row">
                <span class="cm-icon cm-icon--yt"><i class="fa-brands fa-youtube" aria-hidden="true"></i></span>
                <span class="cm-body">
                    <span class="cm-row-title">Video tutorials</span>
                    <span class="cm-row-desc">Step-by-step walkthroughs on our YouTube channel. Subscribe for new features.</span>
                </span>
                <i class="fa fa-arrow-right cm-arrow" aria-hidden="true"></i>
            </a>
            <a href="{{ $support['docs_url'] }}" target="_blank" rel="noopener" class="cm-row">
                <span class="cm-icon"><i class="fa fa-book-open" aria-hidden="true"></i></span>
                <span class="cm-body">
                    <span class="cm-row-title">Documentation</span>
                    <span class="cm-row-desc">Guides for every module — POS, inventory, accounts, HR and more.</span>
                </span>
                <i class="fa fa-arrow-right cm-arrow" aria-hidden="true"></i>
            </a>
            @if($support['email'])
                <a href="mailto:{{ $support['email'] }}?subject={{ rawurlencode('Feature idea for Zeebroo') }}" class="cm-row">
                    <span class="cm-icon"><i class="fa fa-lightbulb" aria-hidden="true"></i></span>
                    <span class="cm-body">
                        <span class="cm-row-title">Share an idea</span>
                        <span class="cm-row-desc">Missing something? Tell us — product feedback shapes what we build next.</span>
                    </span>
                    <i class="fa fa-arrow-right cm-arrow" aria-hidden="true"></i>
                </a>
            @endif
            <div class="cm-row cm-row--muted">
                <span class="cm-icon"><i class="fa fa-comments" aria-hidden="true"></i></span>
                <span class="cm-body">
                    <span class="cm-row-title">Community forum <span class="cm-soon">Coming soon</span></span>
                    <span class="cm-row-desc">Ask questions, share tips and connect with other Zeebroo users.</span>
                </span>
            </div>
        </div>

        <p class="cm-foot">Need a hand with your account? <a href="{{ route('business.get-started.support') }}">Contact support</a></p>
    </div>
</div>
@endsection
