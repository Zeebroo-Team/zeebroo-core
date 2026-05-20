@extends('theme::layouts.app', ['title' => 'Edit branch', 'heading' => 'Edit branch'])

@section('content')
<style>
.branch-field label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:5px;}
.branch-field input,.branch-field textarea{width:100%;box-sizing:border-box;padding:9px 10px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);}
.branch-field textarea{min-height:80px;line-height:1.45;resize:vertical;font-family:inherit;}
.branch-form-edit{display:grid;gap:10px;}@media (min-width:720px){.branch-form-edit{grid-template-columns:repeat(2,minmax(0,1fr));gap:12px 16px}}
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

<div class="card" style="max-width:640px;margin:0 auto;padding:16px;">
    <p class="muted" style="margin:0 0 14px;font-size:13px;">Updating <strong style="color:var(--text);">{{ $branch->name }}</strong> under {{ $business->name }}</p>

    @if($errors->any())
        <div style="margin:0 0 12px;padding:10px 12px;border-radius:10px;border:1px solid color-mix(in srgb,#f87171 40%,var(--border));font-size:13px;color:var(--text);">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="{{ route('business.branches.update', $branch) }}" class="branch-form-edit">
        @csrf
        @method('PUT')
        <div class="branch-field" style="grid-column:1/-1;">
            <label for="eb-name">Branch name</label>
            <input id="eb-name" name="name" value="{{ old('name', $branch->name) }}" required maxlength="255">
        </div>
        <div class="branch-field" style="grid-column:1/-1;">
            <label for="eb-desc">Description</label>
            <textarea id="eb-desc" name="description" maxlength="5000">{{ old('description', $branch->description) }}</textarea>
        </div>
        <div class="branch-field" style="grid-column:1/-1;">
            <label for="eb-address">Address</label>
            <textarea id="eb-address" name="address" maxlength="2000" rows="2">{{ old('address', $branch->address) }}</textarea>
        </div>
        <div class="branch-field">
            <label for="eb-phone">Phone</label>
            <input id="eb-phone" name="phone" value="{{ old('phone', $branch->phone) }}" maxlength="40">
        </div>
        <div class="branch-field">
            <label for="eb-email">Email</label>
            <input id="eb-email" type="email" name="email" value="{{ old('email', $branch->email) }}" maxlength="255">
        </div>
        <div class="branch-field" style="grid-column:1/-1;">
            <span class="muted" style="display:block;margin-bottom:6px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;">Status</span>
            <input type="hidden" name="is_active" value="0">
            <div class="branch-active-row">
                <label for="eb-active" class="branch-active-row__lbl">Active branch</label>
                <label class="branch-switch">
                    <input type="checkbox" name="is_active" id="eb-active" value="1" role="switch" aria-checked="{{ old('is_active', $branch->is_active ? '1' : '0') === '1' ? 'true' : 'false' }}" @checked(old('is_active', $branch->is_active ? '1' : '0') === '1')>
                    <span class="branch-switch-slider" aria-hidden="true"></span>
                </label>
            </div>
        </div>
        <div style="grid-column:1/-1;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <button type="submit" class="linkbtn" style="padding:8px 16px;font-size:13px;">Save changes</button>
            <a href="{{ route('business.branches.index') }}" style="padding:8px 12px;font-size:13px;border:1px solid var(--border);border-radius:8px;color:var(--text);text-decoration:none;">Cancel</a>
        </div>
    </form>
</div>
@endsection
