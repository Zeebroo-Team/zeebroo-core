{{--
    Shared letterhead header/footer for print views.
    Requires: $accentColor, $business, $mainBranch, $letterheadCanvasJson (nullable)
    Slots: #lhHeaderZone, #lhFooterZone — replaced by rendered canvas image when Fabric.js loads.
--}}

{{-- ─────────────────── HEADER ZONE ─────────────────── --}}
<div id="lhHeaderZone">
    {{-- Rendered letterhead header (JS populates this) --}}
    <img id="lhHeaderImg" style="display:none;width:100%;vertical-align:top;max-width:100%;" alt="">

    {{-- HTML fallback shown until canvas renders --}}
    <div id="lhHeaderFallback">
        <div style="height:6px;background:{{ $accentColor }};"></div>
        <div style="display:flex;align-items:flex-start;justify-content:space-between;padding:18px 40px 14px;">
            <div style="display:flex;align-items:flex-start;gap:14px;">
                @php $logoUrl = $business->logoUrl(); @endphp
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" style="width:64px;height:64px;object-fit:contain;object-position:left center;flex-shrink:0;" alt="{{ $business->name }}">
                    <div style="width:1.5px;height:56px;background:{{ $accentColor }};opacity:.35;align-self:center;flex-shrink:0;"></div>
                @endif
                <div>
                    <div style="font-size:20px;font-weight:800;color:#1e293b;letter-spacing:-.01em;line-height:1.2;">{{ $business->name }}</div>
                    @if($business->short_description)
                        <div style="font-size:11px;color:#475569;font-style:italic;margin-top:3px;">{{ $business->short_description }}</div>
                    @endif
                </div>
            </div>
            @php
                $lhContactLines = array_values(array_filter([
                    $mainBranch?->address ?? '',
                    $mainBranch?->phone   ?? '',
                    $mainBranch?->email   ?? '',
                ]));
            @endphp
            @if(count($lhContactLines))
                <div style="text-align:right;font-size:10px;color:#475569;line-height:1.75;">
                    @foreach($lhContactLines as $ln){{ $ln }}<br>@endforeach
                </div>
            @endif
        </div>
        <div style="height:1px;background:rgba(0,0,0,.08);margin:0 40px;"></div>
        <div style="height:4px;background:{{ $accentColor }};opacity:.18;margin:0 40px;"></div>
    </div>
</div>
