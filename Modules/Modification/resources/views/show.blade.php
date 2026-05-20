@extends('theme::layouts.app', [
    'title' => $modification->name,
    'heading' => __('Modification'),
])

@section('content')
@php
    $modShowUrl = route('modification.show', $modification);
@endphp
<div class="card modification-show-card" style="max-width:none;font-size:13px;">
    <style>
        .modification-show-card .mod-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 4px 8px;
            border-bottom: 1px solid var(--border);
            margin-top: 14px;
            padding: 0;
        }
        .modification-show-card .mod-tab {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 14px;
            margin-bottom: -1px;
            font-size: 12px;
            font-weight: 600;
            color: color-mix(in srgb, var(--text) 72%, transparent);
            text-decoration: none;
            border: 1px solid transparent;
            border-bottom-color: transparent;
            border-radius: 10px 10px 0 0;
        }
        .modification-show-card .mod-tab:hover {
            color: var(--text);
            background: color-mix(in srgb, var(--primary) 5%, transparent);
        }
        .modification-show-card .mod-tab.is-active {
            color: var(--text);
            font-weight: 800;
            border-color: var(--border);
            border-bottom-color: var(--card);
            background: var(--card);
        }
        .modification-show-card .mod-tab-panel {
            padding: 18px 2px 4px;
        }
    </style>

    <div style="display:flex;flex-wrap:wrap;gap:10px 14px;align-items:flex-start;justify-content:space-between;">
        <div style="min-width:0;">
            <p class="muted" style="margin:0 0 6px;font-size:11px;">{{ __('Modification') }}</p>
            <h1 style="margin:0;font-size:22px;line-height:1.25;font-weight:800;">{{ $modification->name }}</h1>
            <p class="muted" style="margin:8px 0 0;font-size:12px;line-height:1.45;">
                {{ __('Created') }} {{ $modification->created_at?->format('Y-m-d H:i') ?? '—' }}
            </p>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:8px;">
            <a href="{{ route('modification.index') }}" class="linkbtn" style="padding:8px 11px;font-size:12px;border-radius:8px;text-decoration:none;display:inline-flex;align-items:center;">
                ← {{ __('Back') }}
            </a>
        </div>
    </div>

    <nav class="mod-tabs" role="tablist" aria-label="{{ __('Modification sections') }}">
        <a id="tab-trigger-details" role="tab" aria-selected="{{ $activeTab === 'details' ? 'true' : 'false' }}"
           href="{{ $modShowUrl }}"
           class="mod-tab {{ $activeTab === 'details' ? 'is-active' : '' }}">{{ __('Modification details') }}</a>
        <a id="tab-trigger-bills" role="tab" aria-selected="{{ $activeTab === 'bills' ? 'true' : 'false' }}"
           href="{{ $modShowUrl }}?tab=bills"
           class="mod-tab {{ $activeTab === 'bills' ? 'is-active' : '' }}">
            {{ __('Assigned bills') }}
            <span class="muted" style="font-size:10px;font-weight:800;">({{ (int) ($modification->bills_count ?? 0) }})</span>
        </a>
        <a id="tab-trigger-property" role="tab" aria-selected="{{ $activeTab === 'property' ? 'true' : 'false' }}"
           href="{{ $modShowUrl }}?tab=property"
           class="mod-tab {{ $activeTab === 'property' ? 'is-active' : '' }}">{{ __('Property details') }}</a>
    </nav>

    <div id="tab-panel-details" role="tabpanel" aria-labelledby="tab-trigger-details" class="mod-tab-panel" @if($activeTab !== 'details') hidden @endif>
        <dl style="margin:0;display:grid;gap:14px;font-size:12px;line-height:1.45;">
            <div style="margin:0;padding:12px 14px;border-radius:12px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 97%,transparent);">
                <dt class="muted" style="margin:0 0 4px;font-weight:700;font-size:11px;">{{ __('Assign to') }}</dt>
                <dd style="margin:0;font-weight:600;">{{ ucfirst((string) $modification->assignment_type) }}</dd>
            </div>
            <div style="margin:0;padding:12px 14px;border-radius:12px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 97%,transparent);">
                <dt class="muted" style="margin:0 0 4px;font-weight:700;font-size:11px;">{{ __('Reference') }}</dt>
                <dd style="margin:0;font-weight:600;">{{ $referenceDisplay ?: '—' }}</dd>
            </div>
            @if(($modification->assignment_type ?? '') === 'property')
                @php
                    $workTypeLabels = \Modules\Modification\Models\Modification::propertyWorkTypeLabels();
                    $workTypeKey = (string) ($modification->property_work_type ?? '');
                    $workTypeLabel = $workTypeLabels[$workTypeKey] ?? ($workTypeKey !== '' ? $workTypeKey : null);
                    $workTypeOther = (string) ($modification->property_work_type_other ?? '');
                @endphp
                <div style="margin:0;padding:12px 14px;border-radius:12px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 97%,transparent);">
                    <dt class="muted" style="margin:0 0 4px;font-weight:700;font-size:11px;">{{ __('Renovation type') }}</dt>
                    <dd style="margin:0;font-weight:600;">
                        {{ $workTypeLabel ?: '—' }}
                        @if($workTypeKey === \Modules\Modification\Models\Modification::PROPERTY_WORK_TYPE_OTHER && $workTypeOther !== '')
                            · {{ $workTypeOther }}
                        @endif
                    </dd>
                </div>
            @endif
            <div style="margin:0;padding:12px 14px;border-radius:12px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 97%,transparent);">
                <dt class="muted" style="margin:0 0 4px;font-weight:700;font-size:11px;">{{ __('Estimation cost') }}</dt>
                <dd style="margin:0;font-weight:700;font-variant-numeric:tabular-nums;">{{ number_format((float) $modification->estimated_cost, 2) }}</dd>
            </div>
            <div style="margin:0;padding:12px 14px;border-radius:12px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 97%,transparent);">
                <dt class="muted" style="margin:0 0 4px;font-weight:700;font-size:11px;">{{ __('Duration') }}</dt>
                <dd style="margin:0;font-weight:600;">{{ $modification->duration ?: '—' }}</dd>
            </div>
            <div style="margin:0;padding:12px 14px;border-radius:12px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 97%,transparent);">
                <dt class="muted" style="margin:0 0 4px;font-weight:700;font-size:11px;">{{ __('Description') }}</dt>
                <dd style="margin:0;font-weight:500;color:var(--text);white-space:pre-wrap;">{{ $modification->description ?: '—' }}</dd>
            </div>
        </dl>
    </div>

    <div id="tab-panel-bills" role="tabpanel" aria-labelledby="tab-trigger-bills" class="mod-tab-panel" @if($activeTab !== 'bills') hidden @endif>
        @include('modification::partials.assigned-bills-table', ['bills' => $billsAssigned, 'business' => $business])
        <p class="muted" style="margin:14px 0 0;font-size:11px;line-height:1.45;">
            <a href="{{ route('modification.bills', $modification) }}" style="color:var(--primary);font-weight:600;text-decoration:none;">{{ __('Open assigned bills as full page') }}</a>
        </p>
    </div>

    <div id="tab-panel-property" role="tabpanel" aria-labelledby="tab-trigger-property" class="mod-tab-panel" @if($activeTab !== 'property') hidden @endif>
        @if(($modification->assignment_type ?? '') !== 'property')
            <p class="muted" style="margin:0;line-height:1.55;font-size:12px;">{{ __('Property details are shown when this modification is assigned to Property.') }}</p>
        @elseif($linkedProperty === null)
            <p class="muted" style="margin:0;line-height:1.55;font-size:12px;">{{ __('No property record was found for this assignment. The reference may be in an older format, or the property may have been removed.') }}</p>
        @else
            @php
                $propCurrency = get_settings('business.currency', '', $business ?? null) ?: '';
            @endphp
            <dl style="margin:0;display:grid;gap:14px;font-size:12px;line-height:1.45;">
                <div style="margin:0;padding:12px 14px;border-radius:12px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 97%,transparent);">
                    <dt class="muted" style="margin:0 0 4px;font-weight:700;font-size:11px;">{{ __('Property name') }}</dt>
                    <dd style="margin:0;font-weight:600;">{{ $linkedProperty->property_name }}</dd>
                </div>
                <div style="margin:0;padding:12px 14px;border-radius:12px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 97%,transparent);">
                    <dt class="muted" style="margin:0 0 4px;font-weight:700;font-size:11px;">{{ __('Property type') }}</dt>
                    <dd style="margin:0;font-weight:600;">{{ $linkedProperty->property_type }}</dd>
                </div>
                <div style="margin:0;padding:12px 14px;border-radius:12px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 97%,transparent);">
                    <dt class="muted" style="margin:0 0 4px;font-weight:700;font-size:11px;">{{ __('Cost') }}</dt>
                    <dd style="margin:0;font-weight:700;font-variant-numeric:tabular-nums;">
                        {{ trim(($propCurrency !== '' ? $propCurrency.' ' : '').number_format((float) $linkedProperty->cost, 2)) }}
                    </dd>
                </div>
                <div style="margin:0;padding:12px 14px;border-radius:12px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 97%,transparent);">
                    <dt class="muted" style="margin:0 0 4px;font-weight:700;font-size:11px;">{{ __('Expiry') }}</dt>
                    <dd style="margin:0;font-weight:600;">
                        @if($linkedProperty->has_expiry)
                            {{ __('Yes') }}
                            @if($linkedProperty->expire_date)
                                · {{ __('Expire date') }} {{ $linkedProperty->expire_date->format('Y-m-d') }}
                            @endif
                        @else
                            {{ __('No') }}
                        @endif
                    </dd>
                </div>
                <div style="margin:0;padding:12px 14px;border-radius:12px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 97%,transparent);">
                    <dt class="muted" style="margin:0 0 4px;font-weight:700;font-size:11px;">{{ __('Description') }}</dt>
                    <dd style="margin:0;font-weight:500;white-space:pre-wrap;">{{ $linkedProperty->description ?: '—' }}</dd>
                </div>
            </dl>
            <p class="muted" style="margin:14px 0 0;font-size:11px;">
                <a href="{{ route('account.properties.index') }}" style="color:var(--primary);font-weight:600;text-decoration:none;">{{ __('Manage properties') }}</a>
            </p>
        @endif
    </div>
</div>
@endsection
