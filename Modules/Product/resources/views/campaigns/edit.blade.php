@extends('theme::layouts.app', ['title' => 'Edit campaign', 'heading' => 'Edit campaign'])

@section('content')
@include('product::partials.catalog-hub-styles')

<style>
.pd-section{border:1px solid var(--border);border-radius:14px;background:color-mix(in srgb,var(--card) 98%,transparent);overflow:hidden;}
.pd-section + .pd-section{margin-top:16px;}
.pd-section__head{display:flex;align-items:flex-start;gap:12px;padding:14px 16px;border-bottom:1px solid var(--border);background:color-mix(in srgb,var(--card) 93%,var(--border) 7%);}
.pd-section__icon{width:34px;height:34px;flex-shrink:0;display:grid;place-items:center;border-radius:9px;border:1px solid color-mix(in srgb,var(--primary) 28%,var(--border));background:color-mix(in srgb,var(--primary) 10%,transparent);color:var(--primary);font-size:14px;}
.pd-section__title{font-size:13px;font-weight:800;color:var(--text);line-height:1.2;}
.pd-section__sub{font-size:12px;color:var(--muted);margin-top:2px;line-height:1.35;}
.pd-section__body{padding:18px 16px;display:flex;flex-direction:column;gap:14px;}
.pd-two-col{display:grid;grid-template-columns:1fr 1fr;gap:12px 16px;}
@media(max-width:600px){.pd-two-col{grid-template-columns:1fr;}}
.pd-field-label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin-bottom:6px;}
.pd-req{color:color-mix(in srgb,#f87171 80%,var(--text));font-weight:700;}
.pd-optional{font-size:10px;font-weight:400;color:var(--muted);text-transform:none;letter-spacing:0;margin-left:4px;}
.pd-err{color:#f87171;font-size:12px;margin:4px 0 0;}
.pd-type-cards{display:grid;grid-template-columns:1fr 1fr;gap:8px;}
.pd-type-card{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;border:2px solid var(--border);background:color-mix(in srgb,var(--card) 97%,transparent);cursor:pointer;transition:border-color .15s,background .15s;position:relative;}
.pd-type-card:hover{border-color:color-mix(in srgb,var(--primary) 40%,var(--border));}
.pd-type-card input[type="radio"]{position:absolute;opacity:0;width:0;height:0;}
.pd-type-card--selected{border-color:var(--primary) !important;background:color-mix(in srgb,var(--primary) 8%,transparent);}
.pd-type-card__icon{width:32px;height:32px;flex-shrink:0;display:grid;place-items:center;border-radius:8px;font-size:13px;background:color-mix(in srgb,var(--card) 90%,var(--border) 10%);color:var(--muted);border:1px solid var(--border);transition:background .15s,color .15s;}
.pd-type-card--selected .pd-type-card__icon{background:color-mix(in srgb,var(--primary) 15%,transparent);color:var(--primary);border-color:color-mix(in srgb,var(--primary) 35%,var(--border));}
.pd-type-card__body{flex:1;min-width:0;}
.pd-type-card__label{display:block;font-size:12px;font-weight:700;color:var(--text);line-height:1.2;}
.pd-type-card__hint{display:block;font-size:10px;color:var(--muted);margin-top:1px;}
.pd-type-card__check{font-size:14px;color:var(--primary);opacity:0;transition:opacity .15s;}
.pd-type-card--selected .pd-type-card__check{opacity:1;}
</style>

<div class="pcat-page-card card" style="max-width:900px;margin:0 auto;padding:14px;">
    @include('product::partials.product-hub-nav')

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px;">
        <h2 style="margin:0;font-size:17px;font-weight:800;color:var(--text);">{{ $campaign->name }}</h2>
        <a href="{{ route('product.campaigns.index') }}"
           class="linkbtn" style="padding:8px 14px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">
            ← Back
        </a>
    </div>

    @if($errors->any())
        <div class="pcat-banner pcat-banner--err" style="margin-bottom:16px;">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="{{ route('product.campaigns.update', $campaign) }}">
        @csrf
        @method('PUT')
        @include('product::campaigns.partials.form-fields', ['idPfx' => 'sc-edit', 'campaign' => $campaign])
        <div style="display:flex;justify-content:flex-end;margin-top:20px;">
            <button type="submit" class="linkbtn" style="padding:10px 24px;font-size:13px;display:inline-flex;align-items:center;gap:7px;">
                <i class="fa fa-check"></i> Save changes
            </button>
        </div>
    </form>
</div>
@endsection
