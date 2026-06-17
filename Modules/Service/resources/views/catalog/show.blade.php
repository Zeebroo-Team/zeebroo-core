@extends('theme::layouts.app', ['title' => $item->name, 'heading' => 'Service'])

@section('content')
@include('product::partials.catalog-hub-styles')

@once
<style>
/* ── discount section ── */
.svc-disc-section{margin-top:20px;border:1px solid var(--border);border-radius:12px;overflow:hidden;}
.svc-disc-section__head{
    display:flex;align-items:center;justify-content:space-between;
    padding:12px 16px;background:color-mix(in srgb,var(--card) 80%,transparent);
    border-bottom:1px solid var(--border);
}
.svc-disc-section__title{font-size:13px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:7px;}
.svc-disc-section__toggle{
    display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:600;
    padding:5px 12px;border:1px solid var(--primary);border-radius:7px;
    background:color-mix(in srgb,var(--primary) 10%,transparent);color:var(--primary);
    cursor:pointer;
}
.svc-disc-section__toggle:hover{background:color-mix(in srgb,var(--primary) 18%,transparent);}

/* discount list */
.svc-disc-list{list-style:none;margin:0;padding:0;}
.svc-disc-list:empty::after{
    display:block;padding:16px;text-align:center;font-size:13px;
    color:var(--muted);font-style:italic;content:'No discounts yet.';
}
.svc-disc-item{
    display:flex;align-items:center;gap:12px;padding:12px 16px;
    border-bottom:1px solid var(--border);
}
.svc-disc-item:last-child{border-bottom:none;}
.svc-disc-item__badge{
    display:inline-flex;align-items:center;justify-content:center;
    min-width:54px;padding:4px 10px;border-radius:999px;font-size:13px;font-weight:800;
    background:color-mix(in srgb,var(--primary) 12%,transparent);
    border:1px solid color-mix(in srgb,var(--primary) 30%,var(--border));
    color:var(--primary);white-space:nowrap;
}
.svc-disc-item__badge--flat{
    background:color-mix(in srgb,#10b981 10%,transparent);
    border-color:color-mix(in srgb,#10b981 30%,var(--border));
    color:#10b981;
}
.svc-disc-item__info{flex:1;min-width:0;}
.svc-disc-item__name{font-size:13px;font-weight:700;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.svc-disc-item__meta{font-size:11px;color:var(--muted);margin-top:2px;}
.svc-disc-item__status{font-size:11px;font-weight:700;padding:2px 8px;border-radius:999px;}
.svc-disc-item__status--active{background:color-mix(in srgb,#10b981 12%,transparent);color:#10b981;border:1px solid color-mix(in srgb,#10b981 30%,var(--border));}
.svc-disc-item__status--inactive{background:color-mix(in srgb,var(--muted) 10%,transparent);color:var(--muted);border:1px solid var(--border);}
.svc-disc-item__del{
    display:grid;place-items:center;width:28px;height:28px;border:none;border-radius:7px;
    background:transparent;color:var(--muted);font-size:13px;cursor:pointer;flex-shrink:0;
}
.svc-disc-item__del:hover{background:color-mix(in srgb,#f87171 14%,transparent);color:#f87171;}

/* add form */
.svc-disc-form{padding:16px;border-top:1px solid var(--border);background:color-mix(in srgb,var(--card) 60%,transparent);}
.svc-disc-form__grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.svc-disc-form__field label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:4px;}
.svc-disc-form__field input,.svc-disc-form__field select{
    width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:8px;
    background:var(--card);color:var(--text);font-size:13px;outline:none;box-sizing:border-box;
    transition:border-color .15s,box-shadow .15s;
}
.svc-disc-form__field input:focus,.svc-disc-form__field select:focus{
    border-color:color-mix(in srgb,var(--primary) 45%,var(--border));
    box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 18%,transparent);
}
.svc-disc-type-cards{display:flex;gap:8px;margin-top:2px;}
.svc-disc-type-card{
    flex:1;display:flex;align-items:center;gap:8px;padding:8px 12px;
    border:2px solid var(--border);border-radius:8px;cursor:pointer;transition:border-color .15s,background .15s;
}
.svc-disc-type-card input[type=radio]{display:none;}
.svc-disc-type-card__icon{font-size:15px;color:var(--muted);}
.svc-disc-type-card__label{font-size:12px;font-weight:700;color:var(--text);}
.svc-disc-type-card__hint{font-size:10px;color:var(--muted);}
.svc-disc-type-card:has(input:checked){
    border-color:var(--primary);
    background:color-mix(in srgb,var(--primary) 8%,transparent);
}
.svc-disc-type-card:has(input:checked) .svc-disc-type-card__icon{color:var(--primary);}
.svc-disc-preview{
    display:none;padding:10px 14px;border-radius:8px;margin-top:10px;
    background:color-mix(in srgb,var(--primary) 7%,transparent);
    border:1px solid color-mix(in srgb,var(--primary) 20%,var(--border));
    font-size:13px;color:var(--text);
}
.svc-disc-preview b{color:var(--primary);}
.svc-disc-form__foot{display:flex;justify-content:flex-end;gap:8px;margin-top:14px;}
.svc-disc-form__cancel{
    padding:7px 14px;border:1px solid var(--border);border-radius:8px;
    background:transparent;color:var(--text);font-size:13px;font-weight:600;cursor:pointer;
}
.svc-disc-form__cancel:hover{background:color-mix(in srgb,var(--border) 50%,transparent);}
.svc-disc-form__submit{
    padding:7px 16px;border:none;border-radius:8px;
    background:var(--primary);color:#fff;font-size:13px;font-weight:700;cursor:pointer;
}
.svc-disc-form__submit:hover{opacity:.9;}
</style>
@endonce

<div class="pcat-page-card card" style="max-width:760px;margin:0 auto;padding:14px;">
    @include('service::partials.service-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;margin-bottom:12px;">{{ session('status') }}</div>
    @endif

    {{-- Header --}}
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:20px;">
        <div>
            <h2 style="margin:0 0 4px;font-size:19px;font-weight:800;">{{ $item->name }}</h2>
            @if($item->categories->isNotEmpty())
                <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:4px;">
                    @foreach($item->categories as $cat)
                        <span style="font-size:11px;padding:2px 8px;border-radius:999px;background:color-mix(in srgb,var(--primary) 10%,transparent);border:1px solid color-mix(in srgb,var(--primary) 25%,var(--border));color:var(--primary);font-weight:600;">{{ $cat->name }}</span>
                    @endforeach
                </div>
            @endif
            <div style="margin-top:8px;">
                <span style="display:inline-block;font-size:11px;font-weight:700;padding:2px 9px;border-radius:999px;
                    {{ $item->is_active ? 'background:color-mix(in srgb,#10b981 12%,transparent);border:1px solid color-mix(in srgb,#10b981 40%,var(--border));color:#10b981;' : 'background:color-mix(in srgb,var(--muted) 12%,transparent);border:1px solid var(--border);color:var(--muted);' }}">
                    {{ $item->is_active ? 'Active' : 'Inactive' }}
                </span>
            </div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="{{ route('service.catalog.edit', $item) }}" class="linkbtn"
               style="padding:8px 14px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">
                <i class="fa fa-pen"></i> Edit
            </a>
            <form method="POST" action="{{ route('service.catalog.destroy', $item) }}" style="display:inline;" onsubmit="return confirm('Delete this service?')">
                @csrf @method('DELETE')
                <button type="submit" class="pcat-btn-del" style="padding:8px 10px;"><i class="fa fa-trash"></i></button>
            </form>
        </div>
    </div>

    {{-- Price + Duration --}}
    @php
        $activeDiscount = $item->discounts->first(fn ($d) => $d->isCurrentlyActive());
        $servicePrice   = $item->price !== null ? (float) $item->price : null;
        $discountedPrice = ($activeDiscount && $servicePrice !== null) ? $activeDiscount->finalPrice($servicePrice) : null;
    @endphp
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px;">
        <div style="border:1px solid var(--border);border-radius:10px;padding:14px;">
            <p class="muted" style="margin:0 0 4px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">Price</p>
            @if($servicePrice !== null)
                @if($discountedPrice !== null && $discountedPrice < $servicePrice)
                    <p style="margin:0;font-size:18px;font-weight:800;color:var(--text);">
                        <span style="text-decoration:line-through;color:var(--muted);font-size:14px;">{{ ($currency ? $currency . ' ' : '') . number_format($servicePrice, 2) }}</span>
                        <span style="color:#10b981;"> {{ ($currency ? $currency . ' ' : '') . number_format($discountedPrice, 2) }}</span>
                    </p>
                    <p style="margin:3px 0 0;font-size:11px;color:var(--muted);">{{ $activeDiscount->name }}</p>
                @else
                    <p style="margin:0;font-size:18px;font-weight:800;color:var(--text);">{{ ($currency ? $currency . ' ' : '') . number_format($servicePrice, 2) }}</p>
                @endif
            @else
                <p style="margin:0;font-size:18px;font-weight:800;color:var(--text);">—</p>
            @endif
        </div>
        <div style="border:1px solid var(--border);border-radius:10px;padding:14px;">
            <p class="muted" style="margin:0 0 4px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">Duration</p>
            <p style="margin:0;font-size:18px;font-weight:800;color:var(--text);">{{ $item->durationLabel() }}</p>
        </div>
    </div>

    {{-- Description --}}
    @if($item->description)
    <div style="border-left:3px solid var(--border);padding:10px 14px;border-radius:0 8px 8px 0;background:color-mix(in srgb,var(--card) 96%,transparent);margin-bottom:16px;">
        <p class="muted" style="margin:0 0 4px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">Description</p>
        <p style="margin:0;font-size:13px;color:var(--text);white-space:pre-line;">{{ $item->description }}</p>
    </div>
    @endif

    {{-- Employees --}}
    @if($item->employees->isNotEmpty())
    <div style="border:1px solid var(--border);border-radius:10px;padding:14px;margin-bottom:16px;">
        <p class="muted" style="margin:0 0 8px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;"><i class="fa fa-users" style="margin-right:4px;"></i>Assigned Employees</p>
        <div style="display:flex;flex-wrap:wrap;gap:6px;">
            @foreach($item->employees as $emp)
                <span style="display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:600;border:1px solid color-mix(in srgb,#6366f1 30%,var(--border));background:color-mix(in srgb,#6366f1 8%,transparent);color:var(--text);">
                    <i class="fa fa-user" style="font-size:10px;opacity:.7;"></i>
                    {{ $emp->full_name }}
                    @if($emp->jobTitle) <span style="font-weight:400;color:var(--muted);">· {{ $emp->jobTitle->name }}</span> @endif
                </span>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Products / Materials with cost --}}
    @if($item->products->isNotEmpty())
    @php
        $totalProductCost  = 0.0;
        $costKnown         = true;
        $prodRows = $item->products->map(function ($prod) use (&$totalProductCost, &$costKnown, $currency) {
            $qty   = (float) ($prod->pivot->qty ?? 1);
            $layers = $prod->stockLayers;

            // weighted-average unit cost across all stock layers
            $totalQty  = $layers->sum(fn ($l) => (float) $l->quantity ?? 0);
            $unitCost  = null;
            if ($layers->isNotEmpty()) {
                if ($totalQty > 0) {
                    $weighted = $layers->sum(fn ($l) => (float) $l->unit_cost * max(0, (float) ($l->quantity ?? 0)));
                    $unitCost = $weighted / $totalQty;
                } else {
                    $unitCost = (float) $layers->last()?->unit_cost ?? null;
                }
            }

            $lineCost = $unitCost !== null ? round($unitCost * $qty, 2) : null;
            if ($lineCost === null) $costKnown = false;
            else $totalProductCost += $lineCost;

            return compact('prod', 'qty', 'unitCost', 'lineCost');
        });
        $fmt = fn ($n) => ($currency ? $currency . ' ' : '') . number_format((float) $n, 2);
    @endphp
    <div style="border:1px solid var(--border);border-radius:10px;overflow:hidden;margin-bottom:16px;">
        {{-- Header row --}}
        <div style="display:grid;grid-template-columns:1fr repeat(3,auto);gap:0;padding:8px 14px;border-bottom:1px solid var(--border);background:color-mix(in srgb,var(--card) 80%,transparent);">
            <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);"><i class="fa fa-box" style="margin-right:4px;"></i>Required Products / Materials</span>
            <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);text-align:right;padding-right:18px;">Unit Cost</span>
            <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);text-align:right;padding-right:18px;">Qty</span>
            <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);text-align:right;">Line Cost</span>
        </div>

        {{-- Product rows --}}
        @foreach($prodRows as $row)
        <div style="display:grid;grid-template-columns:1fr repeat(3,auto);gap:0;align-items:center;padding:9px 14px;border-bottom:1px solid var(--border);">
            <div style="min-width:0;">
                <span style="font-size:13px;font-weight:600;color:var(--text);">{{ $row['prod']->name }}</span>
                @if($row['prod']->sku)
                    <span class="muted" style="font-size:11px;margin-left:6px;">{{ $row['prod']->sku }}</span>
                @endif
            </div>
            <span style="font-size:12px;color:var(--muted);text-align:right;padding-right:18px;white-space:nowrap;">
                {{ $row['unitCost'] !== null ? $fmt($row['unitCost']) : '—' }}
            </span>
            <span style="font-size:12px;font-weight:600;color:var(--muted);text-align:right;padding-right:18px;">
                {{ rtrim(rtrim(number_format($row['qty'], 3), '0'), '.') }}
            </span>
            <span style="font-size:13px;font-weight:700;color:var(--text);text-align:right;white-space:nowrap;">
                {{ $row['lineCost'] !== null ? $fmt($row['lineCost']) : '—' }}
            </span>
        </div>
        @endforeach

        {{-- Total row --}}
        <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:color-mix(in srgb,var(--primary) 5%,transparent);">
            <span style="font-size:12px;font-weight:700;color:var(--muted);"><i class="fa fa-calculator" style="margin-right:5px;opacity:.7;"></i>Total Material Cost</span>
            <span style="font-size:15px;font-weight:800;color:var(--primary);">
                {{ $costKnown ? $fmt($totalProductCost) : ($totalProductCost > 0 ? $fmt($totalProductCost) . '+' : '—') }}
            </span>
        </div>

        {{-- Margin hint if service has a price --}}
        @if($servicePrice !== null && $costKnown && $totalProductCost > 0)
        @php
            $displayPrice = $discountedPrice ?? $servicePrice;
            $margin       = $displayPrice - $totalProductCost;
            $marginPct    = $displayPrice > 0 ? round($margin / $displayPrice * 100, 1) : null;
        @endphp
        <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 14px;border-top:1px solid var(--border);font-size:12px;">
            <span style="color:var(--muted);font-weight:600;">
                <i class="fa fa-chart-line" style="margin-right:5px;opacity:.7;"></i>Gross margin
                @if($marginPct !== null)
                    <span style="font-weight:400;color:var(--muted);">({{ $marginPct }}%)</span>
                @endif
            </span>
            <span style="font-weight:800;color:{{ $margin >= 0 ? '#10b981' : '#f87171' }};">
                {{ $fmt($margin) }}
            </span>
        </div>
        @endif
    </div>
    @endif

    {{-- ─────────────────────────── DISCOUNTS ─────────────────────────── --}}
    <div class="svc-disc-section">
        <div class="svc-disc-section__head">
            <span class="svc-disc-section__title">
                <i class="fa fa-tag"></i> Discounts
                @if($item->discounts->isNotEmpty())
                    <span style="font-size:11px;font-weight:400;color:var(--muted);">({{ $item->discounts->count() }})</span>
                @endif
            </span>
            <button type="button" class="svc-disc-section__toggle" id="svcDiscToggle">
                <i class="fa fa-plus"></i> Add Discount
            </button>
        </div>

        {{-- Existing discounts --}}
        <ul class="svc-disc-list">
            @foreach($item->discounts->sortByDesc('created_at') as $disc)
            @php
                $isActive = $disc->isCurrentlyActive();
                $valLabel = $disc->discount_type === 'percentage'
                    ? rtrim(rtrim(number_format((float)$disc->discount_value, 2), '0'), '.') . '%'
                    : ($currency ? $currency . ' ' : '') . number_format((float)$disc->discount_value, 2);
                $meta = [];
                if ($disc->starts_at) $meta[] = 'From ' . $disc->starts_at->format('d M Y');
                if ($disc->ends_at)   $meta[] = 'Until ' . $disc->ends_at->format('d M Y');
            @endphp
            <li class="svc-disc-item">
                <span class="svc-disc-item__badge {{ $disc->discount_type === 'flat' ? 'svc-disc-item__badge--flat' : '' }}">
                    {{ $valLabel }}
                </span>
                <div class="svc-disc-item__info">
                    <div class="svc-disc-item__name">{{ $disc->name }}</div>
                    @if(count($meta))
                        <div class="svc-disc-item__meta">{{ implode(' · ', $meta) }}</div>
                    @endif
                </div>
                <span class="svc-disc-item__status {{ $isActive ? 'svc-disc-item__status--active' : 'svc-disc-item__status--inactive' }}">
                    {{ $isActive ? 'Active' : 'Inactive' }}
                </span>
                <form method="POST" action="{{ route('service.discounts.destroy', [$item, $disc]) }}"
                      onsubmit="return confirm('Remove this discount?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="svc-disc-item__del" title="Remove discount">
                        <i class="fa fa-times"></i>
                    </button>
                </form>
            </li>
            @endforeach
        </ul>

        {{-- Add discount form (hidden by default) --}}
        <div class="svc-disc-form" id="svcDiscForm" style="display:none;">
            <form method="POST" action="{{ route('service.discounts.store', $item) }}">
                @csrf

                <div class="svc-disc-form__grid">
                    <div class="svc-disc-form__field" style="grid-column:1/-1;">
                        <label for="disc-name">Discount name <span style="color:#ef4444;">*</span></label>
                        <input id="disc-name" name="name" value="{{ old('name') }}" maxlength="255"
                               required placeholder="e.g. Summer sale 10%">
                        @error('name')<p style="color:#f87171;font-size:12px;margin:3px 0 0;">{{ $message }}</p>@enderror
                    </div>

                    <div class="svc-disc-form__field" style="grid-column:1/-1;">
                        <label>Discount type <span style="color:#ef4444;">*</span></label>
                        <div class="svc-disc-type-cards">
                            <label class="svc-disc-type-card">
                                <input type="radio" name="discount_type" value="percentage" class="svc-disc-type-radio"
                                       @checked(old('discount_type','percentage')==='percentage')>
                                <span class="svc-disc-type-card__icon"><i class="fa fa-percent"></i></span>
                                <div>
                                    <div class="svc-disc-type-card__label">Percentage</div>
                                    <div class="svc-disc-type-card__hint">e.g. 10%</div>
                                </div>
                            </label>
                            <label class="svc-disc-type-card">
                                <input type="radio" name="discount_type" value="flat" class="svc-disc-type-radio"
                                       @checked(old('discount_type')==='flat')>
                                <span class="svc-disc-type-card__icon"><i class="fa fa-tag"></i></span>
                                <div>
                                    <div class="svc-disc-type-card__label">Flat amount</div>
                                    <div class="svc-disc-type-card__hint">fixed off</div>
                                </div>
                            </label>
                        </div>
                        @error('discount_type')<p style="color:#f87171;font-size:12px;margin:3px 0 0;">{{ $message }}</p>@enderror
                    </div>

                    <div class="svc-disc-form__field">
                        <label for="disc-value">
                            Value <span id="disc-value-suffix" style="font-weight:700;color:var(--primary);">%</span>
                            <span style="color:#ef4444;">*</span>
                        </label>
                        <input id="disc-value" name="discount_value" type="number" min="0.01" step="0.01"
                               value="{{ old('discount_value') }}" placeholder="e.g. 10" required>
                        @error('discount_value')<p style="color:#f87171;font-size:12px;margin:3px 0 0;">{{ $message }}</p>@enderror
                    </div>

                    <div class="svc-disc-form__field">
                        <label for="disc-active" style="margin-bottom:8px;display:block;">Status</label>
                        <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;font-weight:600;">
                            <input type="checkbox" id="disc-active" name="is_active" value="1"
                                   @checked(old('is_active', '1') === '1')
                                   style="width:15px;height:15px;accent-color:var(--primary);">
                            Active
                        </label>
                    </div>

                    <div class="svc-disc-form__field">
                        <label for="disc-starts">Start date <span style="color:var(--muted);font-weight:400;">(optional)</span></label>
                        <input id="disc-starts" name="starts_at" type="date" value="{{ old('starts_at') }}">
                        @error('starts_at')<p style="color:#f87171;font-size:12px;margin:3px 0 0;">{{ $message }}</p>@enderror
                    </div>

                    <div class="svc-disc-form__field">
                        <label for="disc-ends">End date <span style="color:var(--muted);font-weight:400;">(optional)</span></label>
                        <input id="disc-ends" name="ends_at" type="date" value="{{ old('ends_at') }}">
                        @error('ends_at')<p style="color:#f87171;font-size:12px;margin:3px 0 0;">{{ $message }}</p>@enderror
                    </div>
                </div>

                {{-- Live preview --}}
                @if($item->price !== null)
                <div class="svc-disc-preview" id="svcDiscPreview">
                    <i class="fa fa-circle-info" style="margin-right:6px;opacity:.7;"></i>
                    Final price: <b id="svcDiscPreviewFinal">—</b>
                    <span id="svcDiscPreviewSaving" style="color:var(--muted);font-size:11px;margin-left:8px;"></span>
                </div>
                @endif

                <div class="svc-disc-form__foot">
                    <button type="button" class="svc-disc-form__cancel" id="svcDiscCancel">Cancel</button>
                    <button type="submit" class="svc-disc-form__submit">
                        <i class="fa fa-plus" style="margin-right:5px;"></i>Add Discount
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div style="margin-top:14px;max-width:760px;margin-left:auto;margin-right:auto;">
    <a href="{{ route('service.catalog.index') }}" class="linkbtn"
       style="padding:7px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fa fa-arrow-left"></i> All services
    </a>
</div>

<script>
(function () {
    const toggleBtn  = document.getElementById('svcDiscToggle');
    const cancelBtn  = document.getElementById('svcDiscCancel');
    const formEl     = document.getElementById('svcDiscForm');
    const radios     = document.querySelectorAll('.svc-disc-type-radio');
    const valueInp   = document.getElementById('disc-value');
    const suffixEl   = document.getElementById('disc-value-suffix');
    const previewEl  = document.getElementById('svcDiscPreview');
    const previewFin = document.getElementById('svcDiscPreviewFinal');
    const previewSav = document.getElementById('svcDiscPreviewSaving');
    const PRICE      = @json($item->price !== null ? (float) $item->price : null);
    const CURRENCY   = @json($currency ?: '');

    function showForm() {
        formEl.style.display = '';
        toggleBtn.innerHTML = '<i class="fa fa-times"></i> Cancel';
        formEl.querySelector('[name=name]')?.focus();
    }
    function hideForm() {
        formEl.style.display = 'none';
        toggleBtn.innerHTML = '<i class="fa fa-plus"></i> Add Discount';
    }

    toggleBtn?.addEventListener('click', () => formEl.style.display === 'none' ? showForm() : hideForm());
    cancelBtn?.addEventListener('click', hideForm);

    @if($errors->any())
    showForm();
    @endif

    function getType() {
        return document.querySelector('.svc-disc-type-radio:checked')?.value || 'percentage';
    }

    function updateSuffix() {
        if (!suffixEl) return;
        suffixEl.textContent = getType() === 'percentage' ? '%' : (CURRENCY || '$');
    }

    function updatePreview() {
        if (!previewEl || PRICE === null) return;
        const val = parseFloat(valueInp?.value || 0);
        if (!val || val <= 0) { previewEl.style.display = 'none'; return; }

        const type  = getType();
        const disc  = type === 'percentage' ? Math.round(PRICE * val / 100 * 100) / 100 : Math.min(val, PRICE);
        const final = Math.max(0, Math.round((PRICE - disc) * 100) / 100);
        const fmt   = n => (CURRENCY ? CURRENCY + ' ' : '') + n.toFixed(2);

        previewEl.style.display = '';
        previewFin.textContent  = fmt(final);
        if (previewSav) previewSav.textContent = '(save ' + fmt(disc) + ')';
    }

    radios.forEach(r => r.addEventListener('change', () => { updateSuffix(); updatePreview(); }));
    valueInp?.addEventListener('input', updatePreview);

    updateSuffix();
    updatePreview();
})();
</script>
@endsection
