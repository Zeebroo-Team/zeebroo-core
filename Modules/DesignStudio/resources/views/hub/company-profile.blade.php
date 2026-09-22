@extends('theme::layouts.app', ['title' => 'Company Profile', 'heading' => 'Company Profile'])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.dsp-back{display:inline-flex;align-items:center;gap:7px;font-size:13px;font-weight:700;color:var(--muted);text-decoration:none;margin-bottom:16px;transition:color .15s;}
.dsp-back:hover{color:var(--text);}

.dsp-hero{
    border:1px solid var(--border);border-radius:16px;background:var(--card);
    padding:28px 24px;display:flex;flex-direction:column;align-items:center;text-align:center;gap:14px;
    margin-bottom:22px;
}
.dsp-hero__icon{
    width:64px;height:64px;border-radius:18px;display:flex;align-items:center;justify-content:center;
    font-size:26px;background:color-mix(in srgb,#0ea5e9 13%,transparent);color:#0284c7;
}
.dsp-hero__title{font-size:17px;font-weight:800;color:var(--text);margin:0;}
.dsp-hero__desc{font-size:13px;color:var(--muted);max-width:420px;line-height:1.6;margin:0;}
.dsp-hero__meta{font-size:12px;color:var(--muted);}

.dsp-preview{
    width:100%;max-width:420px;aspect-ratio:1920/1080;border-radius:12px;overflow:hidden;
    background:linear-gradient(135deg,color-mix(in srgb,#0ea5e9 8%,var(--bg)) 0%,color-mix(in srgb,#0ea5e9 18%,var(--bg)) 100%);
    display:flex;align-items:center;justify-content:center;font-size:40px;color:#0284c7;
    border:1px solid var(--border);
}
</style>

@php
    $existing = $companyProfile ?? null;
@endphp

<div class="pcat-page-card card" style="max-width:100%;padding:18px 20px;">

    <a href="{{ route('designstudio.index') }}" class="dsp-back">
        <i class="fa fa-arrow-left"></i> Design Studio
    </a>

    <div class="dsp-hero">
        <div class="dsp-hero__icon"><i class="fa fa-building"></i></div>
        <h2 class="dsp-hero__title">Business Profile</h2>
        <p class="dsp-hero__desc">Presentation-style company profile for {{ $business->name }} — showcase your business to clients and partners.</p>

        @if($existing)
            <div class="dsp-preview"><i class="fa fa-building"></i></div>
            <p class="dsp-hero__meta">{{ $existing->width }} × {{ $existing->height }}px &bull; updated {{ $existing->updated_at->diffForHumans() }}</p>
            <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:center;">
                <a href="{{ route('designstudio.editor.edit', $existing) }}" class="linkbtn" style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;">
                    <i class="fa fa-pen"></i> Edit Design
                </a>
                <button type="button" class="linkbtn" style="background:transparent;border:1px solid var(--border);color:var(--text);display:inline-flex;align-items:center;gap:7px;padding:9px 18px;" onclick="openCpWizard()">
                    <i class="fa fa-wand-magic-sparkles"></i> Regenerate with Wizard
                </button>
            </div>
        @else
            <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:center;margin-top:4px;">
                <button type="button" class="linkbtn" style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;" onclick="openCpWizard()">
                    <i class="fa fa-wand-magic-sparkles"></i> Create with Wizard
                </button>
            </div>
        @endif
    </div>

</div>

<script>
function startEditor(w, h, type) {
    var url = '{{ route('designstudio.editor.create') }}?w=' + w + '&h=' + h;
    if (type) url += '&type=' + encodeURIComponent(type);
    window.location.href = url;
}
</script>

@include('designstudio::hub.partials.company-profile-wizard')

@endsection
