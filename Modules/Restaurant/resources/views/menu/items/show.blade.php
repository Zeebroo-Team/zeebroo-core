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
  $tags = (array)($item->dietary_tags ?? []);
  $available = $item->is_available;
@endphp
<style>
.mi-show-wrap { max-width:680px; }
.mi-show-card { background:var(--bg);border:1px solid var(--border);border-radius:16px;overflow:hidden; }
.mi-show-accent { height:6px; }
.mi-show-body   { padding:28px 28px 24px; }
.mi-show-top    { display:flex;align-items:flex-start;gap:16px;margin-bottom:20px;flex-wrap:wrap; }
.mi-show-info   { flex:1;min-width:200px; }
.mi-show-name   { font-size:24px;font-weight:900;letter-spacing:-.3px;margin:0 0 6px; }
.mi-show-cat    { display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:700;
                   padding:4px 12px;border-radius:999px;margin-bottom:10px;
                   background:color-mix(in srgb,var(--primary) 10%,transparent);color:var(--primary); }
.mi-show-desc   { font-size:14px;color:var(--muted);line-height:1.6;margin:0; }
.mi-show-price-col { text-align:right;flex-shrink:0; }
.mi-show-price  { font-size:32px;font-weight:900;color:var(--primary);letter-spacing:-.5px;line-height:1;margin-bottom:6px; }
.mi-show-status { display:inline-block;font-size:12px;font-weight:700;padding:4px 12px;border-radius:999px; }
.mi-show-meta   { display:flex;flex-wrap:wrap;gap:8px;padding-top:16px;border-top:1px solid var(--border);margin-top:16px; }
.mi-show-chip   { display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:700;
                   padding:5px 13px;border-radius:999px;border:1.5px solid var(--border); }
.mi-show-foot   { display:flex;align-items:center;gap:8px;padding:16px 28px;border-top:1px solid var(--border);
                   background:color-mix(in srgb,var(--border) 10%,var(--bg)); }
</style>

<div class="mi-show-wrap">
  @include('restaurant::partials.nav')

  @if(session('status'))
    <div class="pcat-banner pcat-banner--ok" style="margin-bottom:16px;font-weight:600;">{{ session('status') }}</div>
  @endif

  {{-- Breadcrumb --}}
  <div style="display:flex;align-items:center;gap:10px;margin-bottom:18px;">
    <a href="{{ route('restaurant.menu.items.index') }}"
       style="display:flex;align-items:center;gap:6px;padding:7px 12px;border-radius:9px;
              border:1px solid var(--border);color:var(--muted);text-decoration:none;font-size:13px;">
      <i class="fa fa-arrow-left"></i>
    </a>
    <span style="font-size:12px;color:var(--muted);">Menu</span>
    <i class="fa fa-chevron-right" style="font-size:9px;color:var(--muted);"></i>
    <span style="font-size:12px;font-weight:700;">{{ $item->name }}</span>
  </div>

  <div class="mi-show-card">
    <div class="mi-show-accent" style="background:{{ $available ? '#22c55e' : '#9ca3af' }};"></div>
    @if($item->imageFile)
      <img src="{{ $item->imageFile->publicUrl() }}" alt="{{ $item->name }}"
           style="width:100%;height:220px;object-fit:cover;display:block;border-bottom:1px solid var(--border);">
    @endif
    <div class="mi-show-body">
      <div class="mi-show-top">
        <div class="mi-show-info">
          <h1 class="mi-show-name">{{ $item->name }}</h1>
          @if($item->category)
            <div class="mi-show-cat">
              <i class="fa fa-layer-group" style="font-size:10px;"></i>{{ $item->category->name }}
            </div>
          @endif
          @if($item->description)
            <p class="mi-show-desc">{{ $item->description }}</p>
          @endif
        </div>
        <div class="mi-show-price-col">
          <div class="mi-show-price">{{ $currency }}{{ number_format((float)$item->price, 2) }}</div>
          <span class="mi-show-status"
                style="background:{{ $available ? 'color-mix(in srgb,#22c55e 12%,transparent)' : 'color-mix(in srgb,#9ca3af 12%,transparent)' }};
                       color:{{ $available ? '#16a34a' : '#6b7280' }};">
            <i class="fa {{ $available ? 'fa-circle-check' : 'fa-circle-xmark' }}" style="font-size:10px;margin-right:3px;"></i>
            {{ $available ? 'Available' : 'Unavailable' }}
          </span>
        </div>
      </div>

      {{-- Meta chips --}}
      @if($item->prep_time_minutes || !empty($tags))
        <div class="mi-show-meta">
          @if($item->prep_time_minutes)
            <span class="mi-show-chip" style="color:var(--muted);">
              <i class="fa fa-clock" style="font-size:10px;"></i>{{ $item->prepLabel() }} prep time
            </span>
          @endif
          @foreach($tags as $tag)
            @if(isset($tagMeta[$tag]))
              @php $tm = $tagMeta[$tag]; @endphp
              <span class="mi-show-chip"
                    style="background:color-mix(in srgb,{{ $tm['color'] }} 8%,transparent);
                           color:{{ $tm['color'] }};border-color:color-mix(in srgb,{{ $tm['color'] }} 20%,var(--border));">
                <i class="fa {{ $tm['icon'] }}" style="font-size:10px;"></i>{{ $tm['label'] }}
              </span>
            @endif
          @endforeach
        </div>
      @endif
    </div>

    <div class="mi-show-foot">
      <a href="{{ route('restaurant.menu.items.edit', $item) }}" class="linkbtn"
         style="padding:9px 20px;font-size:13px;text-decoration:none;display:flex;align-items:center;gap:6px;">
        <i class="fa fa-pen" style="font-size:11px;"></i> Edit Item
      </a>
      <form method="POST" action="{{ route('restaurant.menu.items.destroy', $item) }}"
            onsubmit="return confirm('Delete {{ addslashes($item->name) }}?')">
        @csrf @method('DELETE')
        <button type="submit"
                style="padding:9px 18px;border-radius:9px;border:1.5px solid color-mix(in srgb,#ef4444 30%,var(--border));
                       background:color-mix(in srgb,#ef4444 6%,var(--bg));color:#ef4444;
                       font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:6px;">
          <i class="fa fa-trash" style="font-size:11px;"></i> Delete
        </button>
      </form>
    </div>
  </div>
</div>
@endsection
