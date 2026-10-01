@extends('theme::layouts.app', [
    'title' => 'Contact Support',
    'heading' => 'Contact Support',
    'minimalAppShell' => true,
    'hideNavbar' => true,
])

@section('content')
@include('business::get-started.partials.topnav', ['gsActive' => 'support'])
@php
    $supWhatsappDigits = $support['whatsapp'] ? preg_replace('/\D+/', '', $support['whatsapp']) : null;
    $supChannels = array_values(array_filter([
        $support['email'] ? [
            'icon' => 'fa fa-envelope', 'label' => 'Email', 'value' => $support['email'],
            'hint' => 'We reply within 1 business day', 'href' => 'mailto:'.$support['email'], 'external' => false,
        ] : null,
        $support['phone'] ? [
            'icon' => 'fa fa-phone', 'label' => 'Phone', 'value' => $support['phone'],
            'hint' => $support['hours'], 'href' => 'tel:'.preg_replace('/[^\d+]/', '', $support['phone']), 'external' => false,
        ] : null,
        $supWhatsappDigits ? [
            'icon' => 'fa-brands fa-whatsapp', 'label' => 'WhatsApp', 'value' => $support['whatsapp'],
            'hint' => 'Chat with our team', 'href' => 'https://wa.me/'.$supWhatsappDigits, 'external' => true,
        ] : null,
        $support['website'] ? [
            'icon' => 'fa fa-globe', 'label' => 'Website', 'value' => preg_replace('#^https?://#', '', rtrim($support['website'], '/')),
            'hint' => 'Product news and updates', 'href' => $support['website'], 'external' => true,
        ] : null,
    ]));
    $supUser = auth()->user();
@endphp
<style>
    .sp-wrap{max-width:980px;margin:0 auto;}
    .sp-hero{text-align:center;padding:clamp(12px,4vh,36px) 0 clamp(22px,4vh,36px);}
    .sp-eyebrow{
        display:inline-flex;align-items:center;gap:7px;padding:5px 13px;border-radius:999px;margin-bottom:16px;
        background:color-mix(in srgb,var(--gs-gold) 10%,#fff);color:#8a6510;border:1px solid color-mix(in srgb,var(--gs-gold) 30%,transparent);
        font-size:11.5px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;
    }
    .sp-title{margin:0 0 10px;font-size:clamp(26px,3.6vw,38px);font-weight:800;color:var(--text);letter-spacing:-.03em;line-height:1.15;}
    .sp-sub{margin:0 auto;max-width:520px;font-size:15px;color:var(--muted);line-height:1.6;}
    .sp-grid{display:grid;grid-template-columns:1.25fr 1fr;gap:18px;align-items:start;}
    @media(max-width:820px){.sp-grid{grid-template-columns:1fr;}}
    .sp-card{border:1px solid var(--border);border-radius:18px;background:rgba(255,255,255,.45);padding:22px;
        -webkit-backdrop-filter:blur(12px) saturate(160%);backdrop-filter:blur(12px) saturate(160%);}
    .sp-card-title{margin:0 0 4px;font-size:15px;font-weight:800;color:var(--text);letter-spacing:-.01em;}
    .sp-card-sub{margin:0 0 18px;font-size:13px;color:var(--muted);line-height:1.5;}
    .sp-channels{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;}
    @media(max-width:520px){.sp-channels{grid-template-columns:1fr;}}
    .sp-channel{
        display:flex;align-items:flex-start;gap:12px;padding:14px;border-radius:13px;text-decoration:none;color:inherit;
        border:1px solid var(--border);transition:border-color .16s,background .16s;min-width:0;
    }
    .sp-channel:hover{border-color:var(--primary);background:var(--gs-soft);}
    .sp-ch-icon{
        width:38px;height:38px;border-radius:11px;flex-shrink:0;display:grid;place-items:center;font-size:16px;
        background:var(--gs-soft);color:var(--text);
    }
    .sp-ch-body{min-width:0;}
    .sp-ch-label{font-size:11px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;color:var(--muted);}
    .sp-ch-value{font-size:13.5px;font-weight:700;color:var(--text);margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .sp-ch-hint{font-size:11.5px;color:var(--muted);margin-top:2px;}
    .sp-meta{display:flex;flex-direction:column;gap:12px;margin-top:18px;padding-top:18px;border-top:1px solid var(--border);}
    .sp-meta-row{display:flex;align-items:center;gap:10px;font-size:13px;color:var(--muted);}
    .sp-meta-row i{width:16px;text-align:center;color:var(--gs-gold);}
    .sp-meta-row strong{color:var(--text);font-weight:700;}
    .sp-help{display:flex;flex-direction:column;}
    .sp-help a{
        display:flex;align-items:center;gap:12px;padding:12px 4px;text-decoration:none;color:var(--text);
        font-size:13.5px;font-weight:700;
    }
    .sp-help a + a{border-top:1px solid var(--border);}
    .sp-help a i:first-child{width:18px;text-align:center;color:var(--muted);}
    .sp-help a span{flex:1;}
    .sp-help a .fa-arrow-up-right-from-square,.sp-help a .fa-arrow-right{font-size:11px;color:var(--muted);transition:transform .16s,color .16s;}
    .sp-help a:hover span{color:var(--primary);}
    .sp-help a:hover .fa-arrow-right{transform:translateX(3px);color:var(--primary);}
    .sp-ref{
        margin-top:16px;padding:12px 14px;border-radius:12px;font-size:12px;color:var(--muted);line-height:1.5;
        background:color-mix(in srgb,var(--muted) 8%,transparent);
    }
    .sp-ref strong{color:var(--text);}
</style>
<div class="gs-shell">
    <div class="sp-wrap">
        <div class="sp-hero">
            <span class="sp-eyebrow"><i class="fa fa-headset" aria-hidden="true"></i> Support</span>
            <h1 class="sp-title">We're here to help</h1>
            <p class="sp-sub">Questions about setup, billing or your workspace? Reach our team through any of the channels below.</p>
        </div>

        <div class="sp-grid">
            <div class="sp-card">
                <h2 class="sp-card-title">Get in touch</h2>
                <p class="sp-card-sub">Pick whichever is easiest — every message reaches the same support team.</p>
                <div class="sp-channels">
                    @foreach($supChannels as $ch)
                        <a href="{{ $ch['href'] }}" class="sp-channel" @if($ch['external']) target="_blank" rel="noopener" @endif>
                            <span class="sp-ch-icon"><i class="{{ $ch['icon'] }}" aria-hidden="true"></i></span>
                            <span class="sp-ch-body">
                                <span class="sp-ch-label">{{ $ch['label'] }}</span>
                                <span class="sp-ch-value" style="display:block">{{ $ch['value'] }}</span>
                                @if($ch['hint'])<span class="sp-ch-hint" style="display:block">{{ $ch['hint'] }}</span>@endif
                            </span>
                        </a>
                    @endforeach
                </div>
                <div class="sp-meta">
                    @if($support['hours'])
                        <div class="sp-meta-row"><i class="fa fa-clock" aria-hidden="true"></i> Support hours: <strong>{{ $support['hours'] }}</strong></div>
                    @endif
                    @if($support['address'])
                        <div class="sp-meta-row"><i class="fa fa-location-dot" aria-hidden="true"></i> {{ $support['address'] }}</div>
                    @endif
                </div>
            </div>

            <div class="sp-card">
                <h2 class="sp-card-title">Self-help</h2>
                <p class="sp-card-sub">Most answers are a click away.</p>
                <div class="sp-help">
                    <a href="{{ $support['docs_url'] }}" target="_blank" rel="noopener">
                        <i class="fa fa-book-open" aria-hidden="true"></i><span>Read the docs</span>
                        <i class="fa fa-arrow-up-right-from-square" aria-hidden="true"></i>
                    </a>
                    <a href="{{ $support['youtube_url'] }}" target="_blank" rel="noopener">
                        <i class="fa-brands fa-youtube" aria-hidden="true"></i><span>Watch video tutorials</span>
                        <i class="fa fa-arrow-up-right-from-square" aria-hidden="true"></i>
                    </a>
                    <a href="{{ route('business.get-started.billing') }}">
                        <i class="fa fa-credit-card" aria-hidden="true"></i><span>Billing &amp; payments</span>
                        <i class="fa fa-arrow-right" aria-hidden="true"></i>
                    </a>
                    <a href="{{ route('business.get-started.community') }}">
                        <i class="fa fa-users" aria-hidden="true"></i><span>Community</span>
                        <i class="fa fa-arrow-right" aria-hidden="true"></i>
                    </a>
                </div>
                <div class="sp-ref">
                    When you contact us, mention your account email
                    <strong>{{ $supUser?->email }}</strong>@if($business) and business <strong>{{ $business->name }}</strong>@endif so we can help faster.
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
