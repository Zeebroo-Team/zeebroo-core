@extends('theme::layouts.app', ['title' => __('Create Modification'), 'heading' => __('Modification')])

@section('content')
<div class="mod-page">
<style>
.mod-page{max-width:840px;width:100%;box-sizing:border-box;--mod-r:12px;--mod-r-sm:9px;}
.mod-hero{display:flex;flex-wrap:wrap;gap:12px 20px;justify-content:space-between;align-items:center;padding:0 2px 16px;margin-bottom:4px;border-bottom:1px solid var(--border);}
.mod-hero__badge{display:inline-flex;align-items:center;gap:6px;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--primary);padding:4px 10px;border-radius:999px;border:1px solid color-mix(in srgb,var(--primary) 38%,var(--border));background:color-mix(in srgb,var(--primary) 9%,transparent);}
.mod-hero__actions{display:flex;flex-wrap:wrap;gap:9px;}
.mod-btn--primary{display:inline-flex;align-items:center;gap:8px;padding:9px 16px;border-radius:10px;font-size:13px;font-weight:700;border:1px solid color-mix(in srgb,var(--btn-bg) 72%,var(--border));background:var(--btn-bg);color:#fff;cursor:pointer;transition:background .18s ease;}
.mod-btn--primary:hover{background:var(--btn-hover);color:#111827;}
.mod-btn--ghost{display:inline-flex;align-items:center;gap:7px;padding:8px 14px;border-radius:10px;font-size:13px;font-weight:600;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 94%,transparent);color:var(--text);text-decoration:none;cursor:pointer;transition:background .18s ease,border-color .18s ease;}
.mod-btn--ghost:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 6%,transparent);}
.mod-alert{padding:11px 14px;border-radius:12px;font-size:13px;margin-bottom:14px;display:flex;align-items:flex-start;gap:10px;line-height:1.45;border:1px solid;}
.mod-alert--err{border-color:color-mix(in srgb,#f87171 42%,var(--border));background:color-mix(in srgb,#f87171 7%,transparent);}
.mod-submit-wrap{margin-top:14px;display:flex;flex-wrap:wrap;gap:10px;align-items:center;padding-top:14px;border-top:1px solid var(--border);}
</style>

<div class="mod-hero">
    <span class="mod-hero__badge"><i class="fa fa-screwdriver-wrench"></i>{{ __('New modification') }}</span>
    <div class="mod-hero__actions">
        <a href="{{ route('modification.index') }}" class="mod-btn--ghost">
            <i class="fa fa-arrow-left" style="font-size:11px;"></i>{{ __('Back') }}
        </a>
    </div>
</div>

@if($errors->any())
<div class="mod-alert mod-alert--err">
    <i class="fa fa-circle-exclamation" style="color:#f87171;margin-top:1px;flex-shrink:0;"></i>
    <div>
        <strong style="display:block;margin-bottom:4px;">{{ __('Please correct the highlighted fields.') }}</strong>
        <ul style="margin:0;padding-left:16px;font-size:12px;">
            @foreach($errors->all() as $msg)<li>{{ $msg }}</li>@endforeach
        </ul>
    </div>
</div>
@endif

<form method="post" action="{{ route('modification.store') }}" style="margin-top:14px;">
    @csrf
    @include('modification::partials.create-form')
    <div class="mod-submit-wrap">
        <button type="submit" class="mod-btn--primary">
            <i class="fa fa-floppy-disk"></i>{{ __('Save modification') }}
        </button>
        <a href="{{ route('modification.index') }}" class="mod-btn--ghost">{{ __('Cancel') }}</a>
    </div>
</form>

</div>
@endsection
