@extends('theme::layouts.app', [
    'title' => $modification->name,
    'heading' => __('Modification'),
])

@section('content')
@php
    $modShowUrl = route('modification.show', $modification);
    $currency = get_settings('business.currency', '', $business ?? null) ?: '';
@endphp
<div class="mod-page">
<style>
.mod-page{max-width:none;width:100%;margin:0;box-sizing:border-box;--mod-r:12px;--mod-r-sm:9px;}
.mod-hero{display:flex;flex-wrap:wrap;gap:12px 20px;justify-content:space-between;align-items:flex-start;padding:0 2px 16px;margin-bottom:4px;border-bottom:1px solid var(--border);}
.mod-hero__eyebrow{margin:0 0 4px;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--primary);display:flex;align-items:center;gap:6px;}
.mod-hero__title{margin:0;font-size:22px;line-height:1.25;font-weight:800;letter-spacing:-.03em;}
.mod-hero__meta{margin:6px 0 0;font-size:12px;color:var(--muted);line-height:1.45;}
.mod-hero__actions{display:flex;flex-wrap:wrap;gap:9px;align-items:center;padding-top:4px;}
.mod-btn--ghost{display:inline-flex;align-items:center;gap:7px;padding:8px 14px;border-radius:10px;font-size:13px;font-weight:600;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 94%,transparent);color:var(--text);text-decoration:none;cursor:pointer;transition:background .18s ease,border-color .18s ease;}
.mod-btn--ghost:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 6%,transparent);}
.mod-btn--danger{display:inline-flex;align-items:center;gap:7px;padding:8px 14px;border-radius:10px;font-size:13px;font-weight:600;border:1px solid color-mix(in srgb,#ef4444 45%,var(--border));background:transparent;color:#f87171;cursor:pointer;transition:background .18s ease,border-color .18s ease;}
.mod-btn--danger:hover{background:color-mix(in srgb,#ef4444 9%,transparent);border-color:color-mix(in srgb,#ef4444 60%,var(--border));}
:is(html[data-theme="light"],html[data-theme="light_blue"]) .mod-btn--danger{color:#dc2626;}

.mod-stat-row{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 14px;}
.mod-stat{flex:1 1 130px;padding:12px 14px;border-radius:var(--mod-r-sm);border:1px solid var(--border);background:linear-gradient(145deg,color-mix(in srgb,var(--card) 97%,transparent),color-mix(in srgb,var(--card) 93%,transparent));box-shadow:0 4px 18px -16px rgba(0,0,0,.3);}
.mod-stat__label{display:block;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:4px;}
.mod-stat__value{display:block;font-size:15px;font-weight:800;color:var(--text);letter-spacing:-.02em;font-variant-numeric:tabular-nums;}
.mod-stat__value--primary{color:color-mix(in srgb,var(--primary) 45%,var(--text));}
.mod-pill{display:inline-flex;align-items:center;gap:3px;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:color-mix(in srgb,var(--primary) 72%,var(--text));padding:3px 7px;border-radius:999px;border:1px solid color-mix(in srgb,var(--primary) 30%,var(--border));background:color-mix(in srgb,var(--primary) 7%,transparent);line-height:1.2;vertical-align:middle;margin-left:6px;}

.mod-tabs{display:flex;flex-wrap:wrap;gap:0 2px;border-bottom:1px solid var(--border);margin-bottom:16px;padding:0;}
.mod-tab{display:inline-flex;align-items:center;gap:7px;padding:10px 14px;margin-bottom:-1px;font-size:12px;font-weight:600;color:color-mix(in srgb,var(--text) 65%,transparent);text-decoration:none;border:1px solid transparent;border-bottom-color:transparent;border-radius:10px 10px 0 0;transition:color .15s ease,background .15s ease;white-space:nowrap;}
.mod-tab:hover{color:var(--text);background:color-mix(in srgb,var(--primary) 5%,transparent);}
.mod-tab.is-active{color:var(--text);font-weight:800;border-color:var(--border);border-bottom-color:var(--card);background:var(--card);}
.mod-tab__count{font-size:10px;font-weight:800;opacity:.7;}
.mod-tab-panel{padding:2px 0 4px;}

.mod-dl{display:grid;gap:8px;}
@media(min-width:580px){.mod-dl{grid-template-columns:repeat(2,1fr);}}
.mod-dd{padding:12px 14px;border-radius:var(--mod-r-sm);border:1px solid var(--border);background:color-mix(in srgb,var(--card) 97%,transparent);}
.mod-dd--full{grid-column:1/-1;}
.mod-dd dt{margin:0 0 4px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);}
.mod-dd dd{margin:0;font-size:13px;font-weight:600;line-height:1.4;color:var(--text);}
.mod-dd dd.is-empty{color:var(--muted);font-style:italic;}

.mod-alert{padding:11px 14px;border-radius:12px;font-size:13px;margin-bottom:16px;display:flex;align-items:flex-start;gap:10px;line-height:1.45;border:1px solid;}
.mod-alert--ok{border-color:color-mix(in srgb,#22c55e 38%,var(--border));background:color-mix(in srgb,#22c55e 8%,transparent);}
</style>

@if(session('status'))
<div class="mod-alert mod-alert--ok">
    <i class="fa fa-circle-check" style="color:#22c55e;margin-top:1px;flex-shrink:0;"></i>
    <span>{{ session('status') }}</span>
</div>
@endif

<div class="mod-hero">
    <div>
        <p class="mod-hero__eyebrow">
            <i class="fa fa-screwdriver-wrench" style="font-size:9px;"></i>
            {{ __('Modification') }}
            @php
                $typeLabel = match($modification->assignment_type) {
                    'property'   => __('Property'),
                    'renovation' => __('Renovation'),
                    default      => __('Other'),
                };
            @endphp
            <span class="mod-pill">{{ $typeLabel }}</span>
        </p>
        <h1 class="mod-hero__title">{{ $modification->name }}</h1>
        <p class="mod-hero__meta">
            {{ __('Created') }} {{ $modification->created_at?->format('d M Y, H:i') ?? '—' }}
            @if($referenceDisplay) &middot; {{ $referenceDisplay }} @endif
        </p>
    </div>
    <div class="mod-hero__actions">
        <a href="{{ route('modification.index') }}" class="mod-btn--ghost">
            <i class="fa fa-arrow-left" style="font-size:11px;"></i>{{ __('Back') }}
        </a>
        <form method="post" action="{{ route('modification.destroy', $modification) }}"
              onsubmit="return confirm(@json(__('Delete this modification? Bills assigned to it will be unlinked.')))"
              style="margin:0;line-height:0;">
            @csrf
            @method('DELETE')
            <button type="submit" class="mod-btn--danger">
                <i class="fa fa-trash" style="font-size:11px;"></i>{{ __('Delete') }}
            </button>
        </form>
    </div>
</div>

<div class="mod-stat-row">
    <div class="mod-stat">
        <span class="mod-stat__label">{{ __('Estimated cost') }}</span>
        <span class="mod-stat__value mod-stat__value--primary">
            @if($currency)<small style="font-size:10px;font-weight:700;opacity:.7;margin-right:2px;">{{ $currency }}</small>@endif{{ number_format((float) $modification->estimated_cost, 2) }}
        </span>
    </div>
    <div class="mod-stat">
        <span class="mod-stat__label">{{ __('Assigned bills') }}</span>
        <span class="mod-stat__value">{{ (int) ($modification->bills_count ?? 0) }}</span>
    </div>
    @if($modification->duration)
    <div class="mod-stat">
        <span class="mod-stat__label">{{ __('Duration') }}</span>
        <span class="mod-stat__value">{{ $modification->duration }}</span>
    </div>
    @endif
</div>

<nav class="mod-tabs" role="tablist" aria-label="{{ __('Modification sections') }}">
    <a id="tab-trigger-details" role="tab" aria-selected="{{ $activeTab === 'details' ? 'true' : 'false' }}"
       href="{{ $modShowUrl }}"
       class="mod-tab {{ $activeTab === 'details' ? 'is-active' : '' }}">
        <i class="fa fa-file-lines" style="font-size:11px;"></i>{{ __('Details') }}
    </a>
    <a id="tab-trigger-bills" role="tab" aria-selected="{{ $activeTab === 'bills' ? 'true' : 'false' }}"
       href="{{ $modShowUrl }}?tab=bills"
       class="mod-tab {{ $activeTab === 'bills' ? 'is-active' : '' }}">
        <i class="fa fa-receipt" style="font-size:11px;"></i>{{ __('Assigned bills') }}
        <span class="mod-tab__count">({{ (int) ($modification->bills_count ?? 0) }})</span>
    </a>
    <a id="tab-trigger-property" role="tab" aria-selected="{{ $activeTab === 'property' ? 'true' : 'false' }}"
       href="{{ $modShowUrl }}?tab=property"
       class="mod-tab {{ $activeTab === 'property' ? 'is-active' : '' }}">
        <i class="fa fa-building" style="font-size:11px;"></i>{{ __('Property') }}
    </a>
</nav>

<div id="tab-panel-details" role="tabpanel" aria-labelledby="tab-trigger-details"
     class="mod-tab-panel" @if($activeTab !== 'details') hidden @endif>
    <dl class="mod-dl">
        <div class="mod-dd">
            <dt>{{ __('Assign to') }}</dt>
            <dd>{{ ucfirst((string) $modification->assignment_type) }}</dd>
        </div>
        <div class="mod-dd">
            <dt>{{ __('Reference') }}</dt>
            <dd @if(!$referenceDisplay) class="is-empty" @endif>{{ $referenceDisplay ?: __('—') }}</dd>
        </div>
        @if(($modification->assignment_type ?? '') === 'property')
            @php
                $workTypeLabels = \Modules\Modification\Models\Modification::propertyWorkTypeLabels();
                $workTypeKey = (string) ($modification->property_work_type ?? '');
                $workTypeLabel = $workTypeLabels[$workTypeKey] ?? ($workTypeKey !== '' ? $workTypeKey : null);
                $workTypeOther = (string) ($modification->property_work_type_other ?? '');
            @endphp
            <div class="mod-dd">
                <dt>{{ __('Renovation type') }}</dt>
                <dd @if(!$workTypeLabel) class="is-empty" @endif>
                    {{ $workTypeLabel ?: '—' }}
                    @if($workTypeKey === \Modules\Modification\Models\Modification::PROPERTY_WORK_TYPE_OTHER && $workTypeOther !== '')
                        · {{ $workTypeOther }}
                    @endif
                </dd>
            </div>
        @endif
        <div class="mod-dd">
            <dt>{{ __('Duration') }}</dt>
            <dd @if(!$modification->duration) class="is-empty" @endif>{{ $modification->duration ?: '—' }}</dd>
        </div>
        @if($modification->description)
        <div class="mod-dd mod-dd--full">
            <dt>{{ __('Description') }}</dt>
            <dd style="white-space:pre-wrap;font-weight:500;">{{ $modification->description }}</dd>
        </div>
        @endif
    </dl>
</div>

<div id="tab-panel-bills" role="tabpanel" aria-labelledby="tab-trigger-bills"
     class="mod-tab-panel" @if($activeTab !== 'bills') hidden @endif>
    @include('modification::partials.assigned-bills-table', ['bills' => $billsAssigned, 'business' => $business])
    <p style="margin:14px 0 0;font-size:12px;color:var(--muted);">
        <a href="{{ route('modification.bills', $modification) }}"
           style="color:var(--primary);font-weight:600;text-decoration:none;">
            {{ __('Open assigned bills as full page') }} <i class="fa fa-arrow-up-right-from-square" style="font-size:10px;"></i>
        </a>
    </p>
</div>

<div id="tab-panel-property" role="tabpanel" aria-labelledby="tab-trigger-property"
     class="mod-tab-panel" @if($activeTab !== 'property') hidden @endif>
    @if(($modification->assignment_type ?? '') !== 'property')
        <p style="color:var(--muted);line-height:1.55;font-size:13px;margin:0;">
            {{ __('Property details are shown when this modification is assigned to Property.') }}
        </p>
    @elseif($linkedProperty === null)
        <p style="color:var(--muted);line-height:1.55;font-size:13px;margin:0;">
            {{ __('No property record was found for this assignment. The reference may be in an older format, or the property may have been removed.') }}
        </p>
    @else
        @php $propCurrency = get_settings('business.currency', '', $business ?? null) ?: ''; @endphp
        <dl class="mod-dl">
            <div class="mod-dd">
                <dt>{{ __('Property name') }}</dt>
                <dd>{{ $linkedProperty->property_name }}</dd>
            </div>
            <div class="mod-dd">
                <dt>{{ __('Property type') }}</dt>
                <dd>{{ $linkedProperty->property_type }}</dd>
            </div>
            <div class="mod-dd">
                <dt>{{ __('Cost') }}</dt>
                <dd>{{ trim(($propCurrency !== '' ? $propCurrency.' ' : '').number_format((float) $linkedProperty->cost, 2)) }}</dd>
            </div>
            <div class="mod-dd">
                <dt>{{ __('Expiry') }}</dt>
                <dd>
                    @if($linkedProperty->has_expiry)
                        {{ __('Yes') }}
                        @if($linkedProperty->expire_date)
                            · {{ $linkedProperty->expire_date->format('Y-m-d') }}
                        @endif
                    @else
                        {{ __('No') }}
                    @endif
                </dd>
            </div>
            @if($linkedProperty->description)
            <div class="mod-dd mod-dd--full">
                <dt>{{ __('Description') }}</dt>
                <dd style="white-space:pre-wrap;font-weight:500;">{{ $linkedProperty->description }}</dd>
            </div>
            @endif
        </dl>
        <p style="margin:12px 0 0;font-size:12px;">
            <a href="{{ route('account.properties.index') }}"
               style="color:var(--primary);font-weight:600;text-decoration:none;">
                {{ __('Manage properties') }} <i class="fa fa-arrow-up-right-from-square" style="font-size:10px;"></i>
            </a>
        </p>
    @endif
</div>

</div>
@endsection
