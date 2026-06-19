<div style="display:flex;gap:4px;flex-wrap:wrap;margin-bottom:18px;border-bottom:1px solid var(--border);padding-bottom:12px;">
    @php
        $navLinks = [
            ['route' => 'restaurant.orders.index',          'match' => 'restaurant.orders.*',           'icon' => 'fa fa-receipt',       'label' => 'Orders'],
            ['route' => 'restaurant.tables.index',          'match' => 'restaurant.tables.*',           'icon' => 'fa fa-chair',         'label' => 'Tables'],
            ['route' => 'restaurant.reservations.index',    'match' => 'restaurant.reservations.*',     'icon' => 'fa fa-calendar-check','label' => 'Reservations'],
            ['route' => 'restaurant.menu.items.index',      'match' => 'restaurant.menu.items.*',       'icon' => 'fa fa-utensils',      'label' => 'Menu'],
            ['route' => 'restaurant.menu.categories.index', 'match' => 'restaurant.menu.categories.*', 'icon' => 'fa fa-layer-group',   'label' => 'Categories'],
        ];
    @endphp
    @foreach($navLinks as $nav)
        <a href="{{ route($nav['route']) }}"
           style="display:flex;align-items:center;gap:6px;padding:7px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;
                  {{ request()->routeIs($nav['match']) ? 'background:var(--primary);color:#fff;' : 'color:var(--muted);background:transparent;' }}">
            <i class="{{ $nav['icon'] }}" style="font-size:12px;"></i>
            {{ $nav['label'] }}
        </a>
    @endforeach
</div>
