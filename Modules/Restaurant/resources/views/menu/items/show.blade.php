@extends('theme::layouts.app', ['title' => $item->name, 'heading' => 'Restaurant'])

@section('content')
@php
  $tagMeta = [
    'vegetarian'  => ['label'=>'Vegetarian',  'color'=>'#16a34a', 'icon'=>'fa-leaf'],
    'vegan'        => ['label'=>'Vegan',        'color'=>'#15803d', 'icon'=>'fa-seedling'],
    'gluten_free'  => ['label'=>'Gluten Free',  'color'=>'#b45309', 'icon'=>'fa-wheat-awn'],
    'halal'        => ['label'=>'Halal',        'color'=>'#0891b2', 'icon'=>'fa-star-and-crescent'],
    'spicy'        => ['label'=>'Spicy',        'color'=>'#dc2626', 'icon'=>'fa-pepper-hot'],
    'nut_free'     => ['label'=>'Nut Free',     'color'=>'#d97706', 'icon'=>'fa-ban'],
    'dairy_free'   => ['label'=>'Dairy Free',   'color'=>'#7c3aed', 'icon'=>'fa-droplet'],
  ];
  $tags      = (array)($item->dietary_tags ?? []);
  $available = $item->is_available;
  $tabUrl    = fn(string $tab) => route('restaurant.menu.items.show', ['menuItem' => $item, 'tab' => $tab]);
@endphp
<style>
.ps{max-width:100%;}

/* ── Header ── */
.ps-head{display:flex;align-items:flex-start;gap:12px;margin:0 0 14px;}
.ps-head__img{flex-shrink:0;width:64px;height:64px;object-fit:cover;border-radius:10px;border:1px solid var(--border);}
.ps-head__ph{flex-shrink:0;width:64px;height:64px;border-radius:10px;border:1px dashed var(--border);display:grid;place-items:center;color:var(--muted);font-size:24px;}
.ps-head__body{flex:1;min-width:0;}
.ps-head__back{font-size:11px;color:var(--muted);text-decoration:none;display:inline-flex;align-items:center;gap:4px;margin-bottom:4px;}
.ps-head__back:hover{color:var(--text);}
.ps-head__name{margin:0;font-size:18px;font-weight:900;color:var(--text);letter-spacing:-.02em;line-height:1.2;}
.ps-head__meta{display:flex;flex-wrap:wrap;align-items:center;gap:6px;margin-top:6px;}
.ps-badge{font-size:10px;font-weight:700;padding:2px 9px;border-radius:999px;border:1px solid var(--border);display:inline-block;}
.ps-badge--on{border-color:color-mix(in srgb,#22c55e 40%,var(--border));background:color-mix(in srgb,#22c55e 10%,transparent);color:#16a34a;}
.ps-badge--off{color:var(--muted);}
.ps-head__actions{display:flex;gap:6px;margin-left:auto;flex-shrink:0;}
.ps-head-btn{display:inline-flex;align-items:center;gap:5px;padding:7px 14px;font-size:12px;font-weight:700;border-radius:9px;border:1px solid var(--border);background:color-mix(in srgb,var(--bg) 90%,transparent);color:var(--text);text-decoration:none;cursor:pointer;transition:all .15s;}
.ps-head-btn:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 8%,transparent);}
.ps-head-btn--primary{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 12%,transparent);}
.ps-head-btn--danger{border-color:color-mix(in srgb,#ef4444 35%,var(--border));background:color-mix(in srgb,#ef4444 6%,var(--bg));color:#ef4444;}

/* ── Stats strip ── */
.ps-stats{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 14px;}
.ps-stat{display:flex;flex-direction:column;gap:2px;padding:8px 14px;border:1px solid var(--border);border-radius:9px;background:color-mix(in srgb,var(--bg) 96%,var(--border) 4%);min-width:100px;}
.ps-stat__lbl{font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);}
.ps-stat__val{font-size:15px;font-weight:800;color:var(--text);line-height:1.1;}

/* ── Tabs ── */
.ps-tabs{display:flex;flex-wrap:wrap;gap:4px;margin:0 0 14px;padding:4px;border-radius:11px;border:1px solid var(--border);background:color-mix(in srgb,var(--bg) 92%,var(--border) 8%);width:fit-content;}
.ps-tab{display:inline-flex;align-items:center;gap:5px;padding:6px 12px;font-size:11.5px;font-weight:700;color:var(--muted);text-decoration:none;border-radius:8px;border:1px solid transparent;background:transparent;transition:all .15s;white-space:nowrap;}
.ps-tab:hover{color:var(--text);background:color-mix(in srgb,var(--bg) 80%,transparent);}
.ps-tab.is-active{color:var(--text);background:var(--bg);border-color:var(--border);box-shadow:0 1px 4px rgba(0,0,0,.1);}
.ps-tab__count{font-size:9px;font-weight:700;padding:1px 5px;border-radius:999px;background:color-mix(in srgb,var(--primary) 14%,transparent);color:var(--primary);border:1px solid color-mix(in srgb,var(--primary) 25%,transparent);}

/* ── Panel ── */
.ps-panel[hidden]{display:none!important;}

/* ── Section label ── */
.ps-label{font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);margin:0 0 8px;display:flex;align-items:center;gap:6px;}
.ps-label::after{content:'';flex:1;height:1px;background:var(--border);}

/* ── Overview grid ── */
.ps-overview-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1px;border:1px solid var(--border);border-radius:10px;overflow:hidden;margin:0 0 12px;background:var(--border);}
@media(max-width:600px){.ps-overview-grid{grid-template-columns:repeat(2,minmax(0,1fr));}}
.ps-overview-cell{padding:9px 12px;background:color-mix(in srgb,var(--bg) 97%,var(--border) 3%);}
.ps-overview-cell dt{font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin:0 0 3px;}
.ps-overview-cell dd{font-size:13px;font-weight:700;color:var(--text);margin:0;}

/* ── Tags row ── */
.ps-tag-row{display:flex;flex-wrap:wrap;gap:5px;margin:0 0 12px;}
.ps-chip{display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:700;padding:4px 11px;border-radius:999px;border:1.5px solid var(--border);}

/* ── Description block ── */
.ps-desc{margin:0 0 12px;padding:10px 12px;border:1px solid var(--border);border-radius:9px;font-size:13px;line-height:1.6;color:var(--text);background:color-mix(in srgb,var(--bg) 97%,var(--border) 3%);}

/* ── Image ── */
.ps-img{width:100%;max-height:260px;object-fit:cover;border-radius:10px;border:1px solid var(--border);display:block;margin:0 0 12px;}

/* ── Ingredient table ── */
.ps-table-wrap{border:1px solid var(--border);border-radius:10px;overflow:hidden;margin:0 0 12px;}
.ps-table{width:100%;border-collapse:collapse;font-size:13px;}
.ps-table th{padding:8px 12px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);background:color-mix(in srgb,var(--bg) 92%,var(--border) 8%);border-bottom:1px solid var(--border);text-align:left;}
.ps-table td{padding:10px 12px;border-bottom:1px solid color-mix(in srgb,var(--border) 70%,transparent);vertical-align:middle;}
.ps-table tr:last-child td{border-bottom:none;}
.ps-table tr:hover td{background:color-mix(in srgb,var(--primary) 3%,var(--bg));}

/* ── Empty state ── */
.ps-empty{text-align:center;padding:40px 20px;border:1.5px dashed var(--border);border-radius:10px;color:var(--muted);}
.ps-empty i{font-size:28px;margin-bottom:10px;display:block;opacity:.4;}
.ps-empty p{margin:4px 0 12px;font-size:13px;}
</style>

<div class="ps">
  @include('restaurant::partials.nav')

  @if(session('status'))
    <div class="pcat-banner pcat-banner--ok" style="margin-bottom:14px;font-weight:600;">{{ session('status') }}</div>
  @endif

  {{-- ── Header ── --}}
  <div class="ps-head">
    @if($item->imageFile)
      <img src="{{ $item->imageFile->publicUrl() }}" alt="{{ $item->name }}" class="ps-head__img">
    @else
      <span class="ps-head__ph"><i class="fa fa-utensils"></i></span>
    @endif

    <div class="ps-head__body">
      <a href="{{ route('restaurant.menu.items.index') }}" class="ps-head__back">
        <i class="fa fa-arrow-left"></i> Menu
      </a>
      <h2 class="ps-head__name">{{ $item->name }}</h2>
      <div class="ps-head__meta">
        @foreach($item->categories as $cat)
          <span class="ps-badge" style="border-color:color-mix(in srgb,var(--primary) 35%,var(--border));color:var(--primary);">
            {{ $cat->name }}
          </span>
        @endforeach
        @if($available)
          <span class="ps-badge ps-badge--on"><i class="fa fa-circle-check" style="font-size:9px;"></i> Available</span>
        @else
          <span class="ps-badge ps-badge--off"><i class="fa fa-circle-xmark" style="font-size:9px;"></i> Unavailable</span>
        @endif
        @if($item->prep_time_minutes)
          <span class="ps-badge"><i class="fa fa-clock" style="font-size:9px;"></i> {{ $item->prepLabel() }}</span>
        @endif
      </div>
    </div>

    <div class="ps-head__actions">
      <a href="{{ route('restaurant.menu.items.edit', $item) }}" class="ps-head-btn ps-head-btn--primary">
        <i class="fa fa-pen"></i> Edit
      </a>
      <form method="POST" action="{{ route('restaurant.menu.items.destroy', $item) }}"
            onsubmit="return confirm('Delete {{ addslashes($item->name) }}?')" style="display:contents;">
        @csrf @method('DELETE')
        <button type="submit" class="ps-head-btn ps-head-btn--danger">
          <i class="fa fa-trash"></i>
        </button>
      </form>
    </div>
  </div>

  {{-- ── Stats strip ── --}}
  <div class="ps-stats">
    <div class="ps-stat">
      <span class="ps-stat__lbl">Price{{ $currency ? ' ('.$currency.')' : '' }}</span>
      <span class="ps-stat__val" style="color:var(--primary);">{{ $currency }}{{ number_format((float)$item->price, 2) }}</span>
    </div>
    <div class="ps-stat">
      <span class="ps-stat__lbl">Ingredients</span>
      <span class="ps-stat__val">{{ $item->ingredients->count() }}</span>
    </div>
    @if($item->prep_time_minutes)
    <div class="ps-stat">
      <span class="ps-stat__lbl">Prep Time</span>
      <span class="ps-stat__val">{{ $item->prepLabel() }}</span>
    </div>
    @endif
    <div class="ps-stat">
      <span class="ps-stat__lbl">Dietary Tags</span>
      <span class="ps-stat__val">{{ count($tags) ?: '—' }}</span>
    </div>
  </div>

  {{-- ── Tab bar ── --}}
  <nav class="ps-tabs">
    <a href="{{ $tabUrl('overview') }}" class="ps-tab {{ $activeTab === 'overview' ? 'is-active' : '' }}">
      <i class="fa fa-circle-info"></i> Overview
    </a>
    <a href="{{ $tabUrl('ingredients') }}" class="ps-tab {{ $activeTab === 'ingredients' ? 'is-active' : '' }}">
      <i class="fa fa-flask"></i> Ingredients
      @if($item->ingredients->count())
        <span class="ps-tab__count">{{ $item->ingredients->count() }}</span>
      @endif
    </a>
  </nav>

  {{-- ── Overview panel ── --}}
  <section class="ps-panel" @if($activeTab !== 'overview') hidden @endif>

    @if($item->imageFile)
      <img src="{{ $item->imageFile->publicUrl() }}" alt="{{ $item->name }}" class="ps-img">
    @endif

    @if($item->description)
      <p class="ps-label"><i class="fa fa-align-left"></i> Description</p>
      <div class="ps-desc">{{ $item->description }}</div>
    @endif

    <p class="ps-label"><i class="fa fa-circle-info"></i> Details</p>
    <div class="ps-overview-grid">
      <div class="ps-overview-cell">
        <dt>Price</dt>
        <dd style="color:var(--primary);font-size:16px;">{{ $currency }}{{ number_format((float)$item->price, 2) }}</dd>
      </div>
      <div class="ps-overview-cell">
        <dt>Status</dt>
        <dd style="color:{{ $available ? '#16a34a' : '#6b7280' }};">
          <i class="fa {{ $available ? 'fa-circle-check' : 'fa-circle-xmark' }}" style="font-size:10px;"></i>
          {{ $available ? 'Available' : 'Unavailable' }}
        </dd>
      </div>
      <div class="ps-overview-cell">
        <dt>{{ $item->categories->count() === 1 ? 'Category' : 'Categories' }}</dt>
        <dd>{{ $item->categories->pluck('name')->join(', ') ?: '—' }}</dd>
      </div>
      <div class="ps-overview-cell">
        <dt>Prep Time</dt>
        <dd>{{ $item->prep_time_minutes ? $item->prepLabel() : '—' }}</dd>
      </div>
      <div class="ps-overview-cell">
        <dt>Recipe Ingredients</dt>
        <dd>{{ $item->ingredients->count() ?: '—' }}</dd>
      </div>
      <div class="ps-overview-cell">
        <dt>Added</dt>
        <dd>{{ $item->created_at->format('d M Y') }}</dd>
      </div>
    </div>

    @if(!empty($tags))
      <p class="ps-label"><i class="fa fa-tags"></i> Dietary Tags</p>
      <div class="ps-tag-row">
        @foreach($tags as $tag)
          @if(isset($tagMeta[$tag]))
            @php $tm = $tagMeta[$tag]; @endphp
            <span class="ps-chip"
                  style="background:color-mix(in srgb,{{ $tm['color'] }} 8%,transparent);
                         color:{{ $tm['color'] }};border-color:color-mix(in srgb,{{ $tm['color'] }} 25%,var(--border));">
              <i class="fa {{ $tm['icon'] }}" style="font-size:9px;"></i>{{ $tm['label'] }}
            </span>
          @endif
        @endforeach
      </div>
    @endif

  </section>

  {{-- ── Ingredients panel ── --}}
  <section class="ps-panel" @if($activeTab !== 'ingredients') hidden @endif>

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
      <p class="ps-label" style="margin:0;flex:1;"><i class="fa fa-flask"></i> Recipe Ingredients</p>
      <a href="{{ route('restaurant.menu.items.edit', ['menuItem' => $item, 'tab' => 'recipe']) }}"
         class="ps-head-btn" style="font-size:11px;padding:5px 12px;">
        <i class="fa fa-pen"></i> Edit Recipe
      </a>
    </div>

    @if($item->ingredients->isEmpty())
      <div class="ps-empty">
        <i class="fa fa-flask"></i>
        <p>No ingredients linked to this item yet.</p>
        <a href="{{ route('restaurant.menu.items.edit', $item) }}" class="ps-head-btn ps-head-btn--primary" style="text-decoration:none;">
          <i class="fa fa-plus"></i> Add Recipe
        </a>
      </div>
    @else
      @php
        $totalCost = $item->ingredients->sum(fn($ing) =>
            (float) $ing->pivot->quantity_required * (float) $ing->cost_per_unit
        );
      @endphp
      <div class="ps-table-wrap">
        <table class="ps-table">
          <thead>
            <tr>
              <th>Ingredient</th>
              <th>Unit</th>
              <th>Qty / Serving</th>
              <th>Cost / Unit</th>
              <th>Line Cost</th>
              <th>Current Stock</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            @foreach($item->ingredients as $ing)
            @php
              $needed   = (float) $ing->pivot->quantity_required;
              $stock    = (float) $ing->quantity;
              $isLow    = $ing->isLowStock();
              $lineCost = $needed * (float) $ing->cost_per_unit;
            @endphp
            <tr>
              <td style="font-weight:700;">{{ $ing->name }}</td>
              <td style="color:var(--muted);font-size:12px;">{{ strtoupper($ing->unit) }}</td>
              <td>{{ rtrim(rtrim(number_format($needed, 3), '0'), '.') }}</td>
              <td style="color:var(--muted);font-size:12px;">
                {{ $ing->cost_per_unit > 0 ? $currency . number_format((float)$ing->cost_per_unit, 2) : '—' }}
              </td>
              <td style="font-weight:700;">
                {{ $lineCost > 0 ? $currency . number_format($lineCost, 2) : '—' }}
              </td>
              <td>
                <span style="font-weight:700;color:{{ $isLow ? '#d97706' : '#16a34a' }};">
                  {{ rtrim(rtrim(number_format($stock, 3), '0'), '.') }}
                </span>
              </td>
              <td>
                @if($isLow)
                  <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:999px;background:#fef3c7;color:#92400e;">
                    <i class="fa fa-triangle-exclamation"></i> Low Stock
                  </span>
                @else
                  <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:999px;background:#dcfce7;color:#15803d;">
                    <i class="fa fa-check"></i> OK
                  </span>
                @endif
              </td>
            </tr>
            @endforeach
          </tbody>
          <tfoot>
            <tr style="background:color-mix(in srgb,var(--primary) 5%,var(--bg));">
              <td colspan="4" style="font-size:12px;font-weight:700;color:var(--muted);padding:10px 12px;">
                Total Ingredient Cost (per serving)
              </td>
              <td colspan="3" style="font-size:15px;font-weight:900;color:var(--primary);padding:10px 12px;">
                {{ $totalCost > 0 ? $currency . number_format($totalCost, 2) : '—' }}
              </td>
            </tr>
          </tfoot>
        </table>
      </div>

      <div style="padding:8px 12px;border:1px solid var(--border);border-radius:9px;font-size:12px;color:var(--muted);background:color-mix(in srgb,var(--bg) 96%,var(--border) 4%);">
        <i class="fa fa-circle-info" style="margin-right:5px;"></i>
        Ingredients are automatically deducted from stock when the order is served.
        @if($totalCost > 0 && (float)$item->price > 0)
          &nbsp;&middot;&nbsp;
          Ingredient cost is <strong>{{ number_format(($totalCost / (float)$item->price) * 100, 1) }}%</strong> of the selling price.
        @endif
      </div>
    @endif

  </section>

</div>
@endsection
