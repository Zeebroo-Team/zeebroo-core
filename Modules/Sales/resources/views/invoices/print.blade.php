@php
    $templateFile = in_array($template ?? 'classic', ['classic', 'bold', 'minimal', 'compact', 'executive'], true)
        ? ($template ?? 'classic')
        : 'classic';
@endphp
@include('sales::invoices.templates.'.$templateFile, [
    'doc'                  => $doc,
    'accent'               => $accent,
    'geomW'                => $geomW,
    'geomMinH'             => $geomMinH,
    'hdrCss'               => $hdrCss,
    'mg'                   => $mg,
    'letterheadCanvasJson' => $letterheadCanvasJson ?? null,
    'business'             => $business,
    'mainBranch'           => $mainBranch,
    'currency'             => $currency,
    'backUrl'              => $backUrl ?? null,
    'hideActions'          => $hideActions ?? false,
])
