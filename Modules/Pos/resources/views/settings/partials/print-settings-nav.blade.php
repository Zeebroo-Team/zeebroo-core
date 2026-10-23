{{-- Tabs shared by Settings → Printer / Receipt Setting / Invoice Setup (mirrors POS Lite's Settings window) --}}
@php
    $printNavLinks = [
        ['route' => 'settings.printer', 'icon' => 'fa-print', 'label' => 'Printer'],
        ['route' => 'settings.receipt', 'icon' => 'fa-receipt', 'label' => 'Receipt Setting'],
    ];
    if (Route::has('sales.invoice-setup.edit')) {
        $printNavLinks[] = ['route' => 'sales.invoice-setup.edit', 'icon' => 'fa-file-invoice', 'label' => 'Invoice Setup'];
    }
@endphp
<style>
.psn-nav{display:flex;gap:6px;flex-wrap:wrap;margin:0 0 14px;}
.psn-nav a{display:inline-flex;align-items:center;gap:7px;padding:7px 12px;border-radius:999px;border:1px solid var(--border);background:var(--card);color:var(--text);font-size:12.5px;font-weight:600;text-decoration:none;}
.psn-nav a:hover{border-color:color-mix(in srgb,var(--primary) 40%,var(--border));}
.psn-nav a.is-active{background:color-mix(in srgb,var(--primary) 16%,var(--card));border-color:color-mix(in srgb,var(--primary) 45%,var(--border));}
.psn-nav a i{color:var(--primary);font-size:12px;}
.prs-ok{display:flex;align-items:center;gap:8px;margin:0 0 12px;border:1px solid color-mix(in srgb,#16a34a 45%,var(--border));background:color-mix(in srgb,#16a34a 10%,var(--card));color:var(--text);border-radius:10px;padding:10px 12px;font-size:12.5px;font-weight:600;}
.prs-ok i{color:#22c55e;}
</style>
<nav class="psn-nav" aria-label="Print settings">
    @foreach($printNavLinks as $link)
        <a href="{{ route($link['route']) }}" @class(['is-active' => request()->routeIs($link['route'])])>
            <i class="fa {{ $link['icon'] }}" aria-hidden="true"></i>{{ $link['label'] }}
        </a>
    @endforeach
</nav>
