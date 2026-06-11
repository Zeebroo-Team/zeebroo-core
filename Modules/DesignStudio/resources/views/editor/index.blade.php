@extends('theme::layouts.app', [
    'title'            => ($design?->title ?? 'Design Editor') . ' — Design Studio',
    'minimalAppShell'  => true,
    'hideNavbar'       => true,
])

@section('content')
{{-- Google Fonts are for use INSIDE designs, not for editor chrome --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@400;700&family=Inter:wght@400;500;600;700&family=Lato:ital,wght@0,400;0,700;1,400&family=Montserrat:ital,wght@0,400;0,700;1,400&family=Nunito:wght@400;600;700&family=Open+Sans:ital,wght@0,400;0,600;1,400&family=Oswald:wght@400;600&family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Raleway:wght@400;600;700&family=Roboto:ital,wght@0,400;0,500;0,700;1,400&display=swap" rel="stylesheet">

<style>
/* ── Make the editor fill the full viewport inside the minimal shell ── */
body { overflow: hidden !important; }
.layout { height: 100vh; overflow: hidden; display: flex; flex-direction: column; }
.content--minimal { flex: 1; min-height: 0; overflow: hidden; display: flex; flex-direction: column; border-left: none !important; }
.content-inner { padding: 0 !important; flex: 1; min-height: 0; overflow: hidden; display: flex; flex-direction: column; }

*,*::before,*::after { box-sizing: border-box; }
input,select,button,textarea { font-family: inherit; font-size: inherit; }

/* ── TOPBAR ── */
.dst {
    height: 58px; background: var(--card); border-bottom: 1px solid var(--border);
    display: flex; align-items: center; padding: 0 12px; gap: 6px; flex-shrink: 0;
    position: relative; z-index: 20;
}
.dst-left  { display: flex; align-items: center; gap: 8px; flex: 0 0 auto; }
.dst-center{ display: flex; align-items: center; gap: 5px; flex: 1; justify-content: center; }
.dst-right { display: flex; align-items: center; gap: 8px; flex: 0 0 auto; }

/* Button group pill container */
.dst-group {
    display: flex; align-items: center; gap: 1px;
    background: color-mix(in srgb,var(--bg) 60%,var(--card));
    border: 1px solid var(--border); border-radius: 9px; padding: 2px;
}

.dst-back {
    display: flex; align-items: center; gap: 6px; padding: 7px 12px; border-radius: 8px;
    border: 1px solid var(--border); background: transparent; color: var(--muted);
    font-size: 12px; font-weight: 700; text-decoration: none; transition: all .15s; white-space: nowrap;
}
.dst-back:hover { color: var(--text); background: color-mix(in srgb,var(--text) 5%,transparent); border-color: color-mix(in srgb,var(--border) 180%,var(--text)); }

.dst-title {
    background: transparent; border: 1.5px solid transparent; color: var(--text);
    font-size: 14px; font-weight: 800; padding: 6px 10px; border-radius: 8px; outline: none;
    min-width: 140px; max-width: 280px; text-align: center; transition: all .15s; letter-spacing: -.01em;
}
.dst-title:hover { border-color: var(--border); background: var(--bg); }
.dst-title:focus { border-color: var(--primary); background: var(--bg); box-shadow: 0 0 0 3px color-mix(in srgb,var(--primary) 12%,transparent); }

.dst-sep { width: 1px; height: 20px; background: var(--border); margin: 0 2px; flex-shrink: 0; }

.dst-btn {
    display: flex; align-items: center; justify-content: center; gap: 5px;
    height: 30px; padding: 0 10px; border-radius: 6px; border: none;
    background: transparent; color: var(--text); font-size: 12px; font-weight: 600;
    cursor: pointer; transition: all .12s; white-space: nowrap; font-family: inherit;
}
.dst-btn:hover   { background: color-mix(in srgb,var(--text) 9%,transparent); }
.dst-btn:disabled{ opacity: .25; cursor: default; pointer-events: none; }
.dst-btn-icon    { width: 30px; padding: 0; font-size: 13px; }
.dst-btn-sec {
    height: 34px; padding: 0 14px; border: 1.5px solid var(--border); border-radius: 8px;
    background: transparent; font-family: inherit;
}
.dst-btn-sec:hover { border-color: color-mix(in srgb,var(--border) 180%,var(--text)); background: color-mix(in srgb,var(--text) 5%,transparent); }
.dst-btn-pri {
    height: 34px; padding: 0 18px; border-radius: 8px; border: none;
    background: var(--primary); color: #fff; font-weight: 700;
    box-shadow: 0 2px 8px color-mix(in srgb,var(--primary) 30%,transparent);
    transition: all .15s; font-family: inherit;
}
.dst-btn-pri:hover { background: color-mix(in srgb,var(--primary) 88%,#000); box-shadow: 0 4px 14px color-mix(in srgb,var(--primary) 38%,transparent); transform: translateY(-1px); }

.dst-zoom-lbl {
    font-size: 12px; font-weight: 800; color: var(--text);
    min-width: 44px; text-align: center; cursor: default; letter-spacing: -.01em;
}

.dst-export-wrap  { position: relative; }
.dst-export-menu  {
    position: absolute; top: calc(100% + 8px); right: 0;
    background: var(--card); border: 1px solid var(--border); border-radius: 11px;
    padding: 6px; min-width: 172px;
    box-shadow: 0 12px 32px rgba(0,0,0,.14), 0 2px 8px rgba(0,0,0,.08);
    z-index: 100; display: none;
}
.dst-export-wrap:hover .dst-export-menu,
.dst-export-menu:hover { display: block; }
.dst-export-item {
    display: flex; align-items: center; gap: 9px; padding: 8px 10px; border-radius: 7px;
    color: var(--text); cursor: pointer; font-size: 12px; font-weight: 600; transition: .12s;
    border: none; background: transparent; width: 100%; text-align: left;
}
.dst-export-item i { width: 16px; text-align: center; color: var(--muted); font-style: normal; transition: color .12s; }
.dst-export-item:hover { background: color-mix(in srgb,var(--text) 7%,transparent); }
.dst-export-item:hover i { color: var(--primary); }

/* ── PDF Export Overlay ── */
.ds-pdf-overlay {
    display: none; position: fixed; inset: 0; z-index: 9999;
    background: rgba(0,0,0,.65); backdrop-filter: blur(4px);
    align-items: center; justify-content: center;
}
.ds-pdf-overlay.on { display: flex; }
.ds-pdf-modal {
    background: var(--card); border: 1px solid var(--border); border-radius: 18px;
    padding: 44px 52px; text-align: center; min-width: 320px;
    box-shadow: 0 24px 64px rgba(0,0,0,.35);
}
.ds-pdf-spinner {
    width: 56px; height: 56px; border-radius: 50%; margin: 0 auto 22px;
    border: 5px solid color-mix(in srgb,var(--primary) 20%,transparent);
    border-top-color: var(--primary);
    animation: dsPdfSpin 0.9s linear infinite;
}
@keyframes dsPdfSpin { to { transform: rotate(360deg); } }
.ds-pdf-title { font-size: 17px; font-weight: 700; color: var(--text); margin-bottom: 8px; }
.ds-pdf-status { font-size: 14px; color: var(--muted); }
.ds-pdf-bar-wrap { height: 4px; background: color-mix(in srgb,var(--primary) 18%,transparent); border-radius: 2px; margin-top: 18px; overflow: hidden; }
.ds-pdf-bar { height: 100%; background: var(--primary); border-radius: 2px; width: 0%; transition: width .3s ease; }

/* ── LAYOUT ── */
.ds-editor { display: flex; flex-direction: column; flex: 1; min-height: 0; overflow: hidden; }
.ds-body   { display: flex; flex: 1; min-height: 0; overflow: hidden; }

/* ── LEFT PANEL ── */
.dsl { width: 244px; flex-shrink: 0; background: var(--card); border-right: 1px solid var(--border); display: flex; flex-direction: column; overflow: hidden; }
.dsl-tabs { display: flex; flex-shrink: 0; border-bottom: 1px solid var(--border); padding: 6px 6px 0; gap: 3px; }
.dsl-tab  {
    flex: 1; display: flex; align-items: center; justify-content: center; gap: 5px;
    padding: 8px 4px 10px; font-size: 11px; font-weight: 700; letter-spacing: -.01em;
    cursor: pointer; color: var(--muted); border: none; border-bottom: 2px solid transparent;
    background: none; transition: all .15s; border-radius: 7px 7px 0 0;
}
.dsl-tab.on { color: var(--primary); border-bottom-color: var(--primary); background: color-mix(in srgb,var(--primary) 6%,transparent); }
.dsl-tab:hover:not(.on) { color: var(--text); background: color-mix(in srgb,var(--text) 4%,transparent); }
.dsl-tab i { font-size: 11px; }
.dsl-body  { flex: 1; overflow-y: auto; padding: 0; }
.dsl-pane  { display: none; }
.dsl-pane.on { display: block; }

/* Accordion */
.dsl-accord { border-bottom: 1px solid var(--border); }
.dsl-accord:last-child { border-bottom: none; }
.dsl-accord-hdr {
    display: flex; align-items: center; gap: 9px;
    padding: 10px 12px 10px 10px; cursor: pointer; user-select: none; transition: background .12s;
}
.dsl-accord-hdr:hover { background: color-mix(in srgb,var(--text) 4%,transparent); }
.dsl-accord-icon {
    width: 28px; height: 28px; border-radius: 7px; flex-shrink: 0;
    background: color-mix(in srgb,var(--primary) 12%,var(--bg));
    color: var(--primary); display: flex; align-items: center; justify-content: center;
    font-size: 12px; transition: all .2s;
}
.dsl-accord.open > .dsl-accord-hdr .dsl-accord-icon { background: var(--primary); color: #fff; }
.dsl-accord-label { flex: 1; font-size: 12px; font-weight: 700; color: var(--text); letter-spacing: -.01em; }
.dsl-accord.open > .dsl-accord-hdr .dsl-accord-label { color: var(--primary); }
.dsl-accord-arrow { font-size: 9px; color: var(--muted); flex-shrink: 0; transition: transform .22s; }
.dsl-accord.open > .dsl-accord-hdr .dsl-accord-arrow { transform: rotate(180deg); }
.dsl-accord-body { display: none; padding: 4px 10px 14px; }
.dsl-accord.open > .dsl-accord-body { display: block; }

/* Shape tiles */
.dsl-shapes { display: grid; grid-template-columns: repeat(2, 1fr); gap: 6px; }
.dsl-shape  {
    display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 5px;
    padding: 12px 4px 8px; border-radius: 10px;
    border: 1.5px solid var(--border); background: var(--bg);
    color: var(--muted); font-size: 9px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase;
    cursor: pointer; transition: all .15s; text-align: center;
}
.dsl-shape svg { width: 28px; height: 28px; }
.dsl-shape:hover {
    border-color: var(--primary); background: color-mix(in srgb,var(--primary) 8%,var(--bg));
    color: var(--primary); transform: translateY(-1px);
    box-shadow: 0 4px 12px color-mix(in srgb,var(--primary) 16%,transparent);
}

/* Text style buttons */
.dsl-texts { display: flex; flex-direction: column; gap: 5px; }
.dsl-text  {
    display: flex; align-items: center; gap: 10px; padding: 10px 12px;
    border-radius: 9px; border: 1.5px solid var(--border); background: var(--bg);
    color: var(--text); cursor: pointer; transition: all .15s; text-align: left; width: 100%;
}
.dsl-text:hover {
    border-color: var(--primary); background: color-mix(in srgb,var(--primary) 5%,var(--bg));
    transform: translateY(-1px); box-shadow: 0 3px 10px color-mix(in srgb,var(--primary) 12%,transparent);
}
.dsl-text-lbl { font-size: 10px; font-weight: 700; color: var(--muted); line-height: 1; }
.dsl-text-h1 .dsl-text-pre   { font-size: 22px; font-weight: 900; line-height: 1; }
.dsl-text-h2 .dsl-text-pre   { font-size: 16px; font-weight: 700; line-height: 1; }
.dsl-text-body .dsl-text-pre { font-size: 12px; line-height: 1; }

/* Background panel */
.dsl-color-row   { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
.dsl-native-color{ width: 32px; height: 32px; border-radius: 7px; border: 2px solid var(--border); padding: 1px; background: none; cursor: pointer; flex-shrink: 0; }
.dsl-color-hex   {
    flex: 1; background: var(--bg); border: 1.5px solid var(--border); color: var(--text);
    border-radius: 7px; padding: 6px 9px; font-size: 12px; font-family: monospace; outline: none; transition: border-color .15s;
}
.dsl-color-hex:focus { border-color: var(--primary); }
.dsl-swatches    { display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 10px; }
.dsl-swatch      {
    width: 22px; height: 22px; border-radius: 5px; cursor: pointer;
    border: 2px solid transparent; transition: all .12s; flex-shrink: 0;
    box-shadow: inset 0 0 0 1px rgba(0,0,0,.1);
}
.dsl-swatch:hover{ transform: scale(1.2); box-shadow: 0 2px 8px rgba(0,0,0,.2); }
.dsl-bg-sec-title{ font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--muted); margin: 0 0 7px; }
.dsl-gradients   { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
.dsl-grad        { height: 40px; border-radius: 9px; cursor: pointer; border: 2px solid transparent; transition: all .15s; box-shadow: 0 2px 8px rgba(0,0,0,.1); }
.dsl-grad:hover  { transform: scale(1.04) translateY(-1px); box-shadow: 0 6px 16px rgba(0,0,0,.16); }

/* Image upload */
.dsl-upload {
    border: 2px dashed var(--border); border-radius: 10px;
    padding: 16px 8px; text-align: center; cursor: pointer; transition: all .15s; color: var(--muted);
    margin-bottom: 8px; background: var(--bg);
}
.dsl-upload:hover { border-color: var(--primary); color: var(--primary); background: color-mix(in srgb,var(--primary) 4%,var(--bg)); }
.dsl-upload i  { font-size: 22px; display: block; margin-bottom: 6px; }
.dsl-upload p  { margin: 2px 0; font-size: 10px; font-weight: 600; }
#dsImgInput { display: none; }

/* Image thumbs & library */
.dsl-img-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 5px; }
.dsl-img-thumb {
    width: 100%; aspect-ratio: 1; object-fit: cover; border-radius: 8px;
    cursor: pointer; border: 2px solid transparent; transition: all .15s; display: block;
}
.dsl-img-thumb:hover { border-color: var(--primary); transform: scale(1.04); }
.dsl-lib-img {
    width: 100%; aspect-ratio: 4/3; object-fit: cover; border-radius: 8px;
    cursor: pointer; border: 2px solid transparent; transition: all .15s; display: block;
}
.dsl-lib-img:hover { border-color: var(--primary); transform: scale(1.04); }
.dsl-lib-empty { font-size: 11px; color: var(--muted); text-align: center; padding: 12px 0; }

/* ── CANVAS AREA ── */
.ds-canvas-area {
    flex: 1; overflow: auto; min-width: 0;
    background: color-mix(in srgb,var(--border) 20%,var(--bg));
    display: flex; align-items: flex-start; justify-content: center; padding: 32px;
}
.ds-canvas-wrap {
    display: inline-flex; flex-shrink: 0; transform-origin: top left;
    box-shadow: 0 20px 60px rgba(0,0,0,.18), 0 4px 20px rgba(0,0,0,.1), 0 0 0 1px rgba(0,0,0,.06);
    border-radius: 3px; touch-action: none; user-select: none; -webkit-user-select: none;
}
.ds-canvas-wrap canvas { display: block; touch-action: none; }

/* ── RIGHT PANEL ── */
.dsr { width: 264px; flex-shrink: 0; background: var(--card); border-left: 1px solid var(--border); display: flex; flex-direction: column; overflow: hidden; }
.dsr-tabs { display: flex; flex-shrink: 0; border-bottom: 1px solid var(--border); padding: 6px 6px 0; gap: 3px; }
.dsr-tab  {
    flex: 1; display: flex; align-items: center; justify-content: center; gap: 5px;
    padding: 8px 4px 10px; font-size: 11px; font-weight: 700; letter-spacing: -.01em;
    cursor: pointer; color: var(--muted); border: none; border-bottom: 2px solid transparent;
    background: none; transition: all .15s; border-radius: 7px 7px 0 0;
}
.dsr-tab.on { color: var(--primary); border-bottom-color: var(--primary); background: color-mix(in srgb,var(--primary) 6%,transparent); }
.dsr-tab:hover:not(.on) { color: var(--text); background: color-mix(in srgb,var(--text) 4%,transparent); }
.dsr-tab i { font-size: 11px; }
.dsr-body  { flex: 1; overflow-y: auto; }
.dsr-pane  { display: none; padding: 14px; }
.dsr-pane.on { display: block; }

/* No-selection placeholder */
.dsp-empty {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 10px; padding: 36px 16px; text-align: center; color: var(--muted);
}
.dsp-empty i { font-size: 36px; opacity: .3; }
.dsp-empty p { margin: 0; font-size: 12px; line-height: 1.55; }

/* Properties sections */
.dsp-sec { margin-bottom: 16px; }
.dsp-sec-title {
    font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .07em;
    color: var(--muted); margin: 0 0 9px; display: flex; align-items: center; gap: 7px;
}
.dsp-sec-title::before { content: ''; width: 3px; height: 12px; border-radius: 2px; background: var(--primary); flex-shrink: 0; }
.dsp-row   { display: flex; align-items: center; gap: 6px; margin-bottom: 6px; }
.dsp-lbl   { font-size: 10px; font-weight: 700; color: var(--muted); width: 18px; flex-shrink: 0; text-align: right; text-transform: uppercase; letter-spacing: .02em; }
.dsp-lbl-w { width: auto; min-width: 46px; font-size: 10px; font-weight: 700; color: var(--muted); flex-shrink: 0; text-transform: uppercase; letter-spacing: .02em; }
.dsp-inp   {
    flex: 1; background: var(--bg); border: 1.5px solid var(--border); color: var(--text);
    border-radius: 7px; padding: 6px 8px; font-size: 12px; outline: none; transition: all .15s;
}
.dsp-inp:focus { border-color: var(--primary); box-shadow: 0 0 0 2px color-mix(in srgb,var(--primary) 12%,transparent); }
.dsp-inp-sm  { max-width: 72px; }
.dsp-inp-xs  { max-width: 52px; }

/* Color picker row */
.dsp-color  { display: flex; align-items: center; gap: 7px; margin-bottom: 7px; }
.dsp-cswatch {
    width: 30px; height: 30px; border-radius: 7px; border: 2px solid var(--border);
    cursor: pointer; flex-shrink: 0; position: relative; transition: transform .12s;
}
.dsp-cswatch:hover { transform: scale(1.08); }
.dsp-cswatch input[type=color] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; border: none; }
.dsp-chex {
    flex: 1; background: var(--bg); border: 1.5px solid var(--border); color: var(--text);
    border-radius: 7px; padding: 6px 8px; font-size: 11px; font-family: monospace; outline: none; transition: border-color .15s;
}
.dsp-chex:focus { border-color: var(--primary); }
.ds-no-fill { font-size: 10px; display: flex; align-items: center; gap: 4px; color: var(--muted); margin: 0; font-weight: 700; white-space: nowrap; }
.ds-no-fill input { margin: 0; accent-color: var(--primary); }

/* Opacity slider */
.dsp-slide-row { margin-bottom: 10px; }
.dsp-slide-hdr { display: flex; justify-content: space-between; font-size: 10px; font-weight: 700; color: var(--muted); margin-bottom: 5px; text-transform: uppercase; letter-spacing: .03em; }
.dsp-slide-hdr span { color: var(--text); font-weight: 800; font-size: 11px; }
input.dsp-slider { width: 100%; accent-color: var(--primary); cursor: pointer; }

/* Toggle buttons */
.dsp-toggles { display: flex; gap: 3px; flex-wrap: wrap; }
.dsp-tog {
    height: 30px; padding: 0 10px; border-radius: 7px;
    border: 1.5px solid var(--border); background: var(--bg);
    color: var(--muted); font-size: 12px; cursor: pointer; transition: all .12s; font-family: inherit;
}
.dsp-tog.on   { background: color-mix(in srgb,var(--primary) 14%,transparent); border-color: var(--primary); color: var(--primary); }
.dsp-tog:hover:not(.on) { border-color: color-mix(in srgb,var(--border) 150%,var(--text)); color: var(--text); }

/* Object action buttons */
.dsp-actions { display: flex; gap: 4px; flex-wrap: wrap; }
.dsp-act {
    flex: 1; min-width: 0; height: 28px; display: flex; align-items: center; justify-content: center; gap: 3px;
    border-radius: 7px; border: 1.5px solid var(--border); background: var(--bg);
    color: var(--muted); font-size: 10px; font-weight: 700; cursor: pointer; transition: all .12s; white-space: nowrap; font-family: inherit;
}
.dsp-act:hover { background: color-mix(in srgb,var(--text) 6%,transparent); color: var(--text); border-color: color-mix(in srgb,var(--border) 150%,var(--text)); }
.dsp-act-danger:hover { background: rgba(239,68,68,.1); border-color: rgba(239,68,68,.5); color: #ef4444; }

/* Font select */
.dsp-font-sel {
    width: 100%; background: var(--bg); border: 1.5px solid var(--border); color: var(--text);
    border-radius: 7px; padding: 6px 9px; font-size: 12px; outline: none; cursor: pointer; transition: border-color .15s;
}
.dsp-font-sel:focus { border-color: var(--primary); }

/* Layers panel */
.dsl-layers-empty { padding: 28px 16px; text-align: center; color: var(--muted); font-size: 12px; }
.dsl-layer {
    display: flex; align-items: center; gap: 8px; padding: 7px 14px;
    border-bottom: 1px solid color-mix(in srgb,var(--border) 40%,transparent); cursor: pointer; transition: background .12s;
}
.dsl-layer:hover { background: color-mix(in srgb,var(--text) 4%,transparent); }
.dsl-layer.sel   { background: color-mix(in srgb,var(--primary) 8%,transparent); border-left: 2px solid var(--primary); }
.dsl-layer-ico   { width: 18px; text-align: center; font-size: 11px; color: var(--muted); flex-shrink: 0; }
.dsl-layer-name  { flex: 1; font-size: 12px; color: var(--text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 500; }
.dsl-layer-vis   { color: var(--muted); font-size: 11px; flex-shrink: 0; padding: 3px 5px; border-radius: 5px; background: none; border: none; cursor: pointer; transition: all .12s; }
.dsl-layer-vis:hover { color: var(--text); background: color-mix(in srgb,var(--text) 8%,transparent); }

/* Toast */
.ds-toast {
    position: fixed; bottom: 24px; left: 50%;
    transform: translateX(-50%) translateY(6px);
    background: var(--card); border: 1px solid var(--border); border-radius: 12px;
    padding: 10px 20px; font-size: 13px; font-weight: 700; color: var(--text);
    box-shadow: 0 8px 28px rgba(0,0,0,.14), 0 2px 8px rgba(0,0,0,.06);
    z-index: 9999; opacity: 0; transition: opacity .2s, transform .2s; pointer-events: none; white-space: nowrap;
}
.ds-toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
.ds-toast.ok   { border-color: color-mix(in srgb,#22c55e 60%,var(--border)); color: #16a34a; }
.ds-toast.err  { border-color: color-mix(in srgb,#ef4444 60%,var(--border)); color: #dc2626; }

/* Scrollbars */
.dsl-body::-webkit-scrollbar, .dsr-body::-webkit-scrollbar { width: 3px; }
.dsl-body::-webkit-scrollbar-track, .dsr-body::-webkit-scrollbar-track { background: transparent; }
.dsl-body::-webkit-scrollbar-thumb, .dsr-body::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }
.dsl-body::-webkit-scrollbar-thumb:hover, .dsr-body::-webkit-scrollbar-thumb:hover { background: var(--muted); }

/* ── CANVAS COLUMN & PAGES BAR ── */
.ds-canvas-column {
    flex: 1; display: flex; flex-direction: column; min-width: 0; min-height: 0; overflow: hidden;
}
.ds-canvas-area { min-height: 0; }

.ds-pages-bar {
    flex-shrink: 0; background: var(--card); border-top: 1px solid var(--border);
    padding: 8px 14px;
}
.ds-pages-inner { display: flex; align-items: flex-end; gap: 8px; }
.ds-pages-scroll {
    display: flex; align-items: flex-end; gap: 7px; overflow-x: auto; flex: 1; padding-bottom: 1px;
}
.ds-pages-scroll::-webkit-scrollbar { height: 3px; }
.ds-pages-scroll::-webkit-scrollbar-track { background: transparent; }
.ds-pages-scroll::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }

.ds-page-thumb {
    flex-shrink: 0; cursor: pointer; border-radius: 7px; padding: 3px;
    border: 2px solid var(--border); background: var(--bg); position: relative;
    transition: all .15s; user-select: none;
}
.ds-page-thumb.active {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px color-mix(in srgb,var(--primary) 18%,transparent);
}
.ds-page-thumb:hover:not(.active) { border-color: color-mix(in srgb,var(--primary) 55%,var(--border)); }

.ds-page-preview {
    width: 58px; height: 76px; border-radius: 4px; overflow: hidden;
    background: #fff; display: flex; align-items: center; justify-content: center;
    background-size: cover; background-position: center; color: var(--muted); font-size: 20px;
    border: 1px solid color-mix(in srgb,var(--border) 50%,transparent);
}
.ds-page-num {
    font-size: 9px; font-weight: 800; text-align: center; color: var(--muted);
    margin-top: 4px; letter-spacing: .03em;
}
.ds-page-thumb.active .ds-page-num { color: var(--primary); }

.ds-page-del, .ds-page-dup {
    position: absolute; width: 17px; height: 17px;
    border-radius: 50%; background: var(--card); border: 1.5px solid var(--border);
    color: var(--muted); font-size: 7px; display: none; align-items: center;
    justify-content: center; cursor: pointer; transition: all .12s; padding: 0; line-height: 1;
}
.ds-page-del { top: -5px; right: -5px; }
.ds-page-dup { top: -5px; left: -5px; }
.ds-page-thumb:hover .ds-page-del,
.ds-page-thumb:hover .ds-page-dup { display: flex; }
.ds-page-del:hover { background: #ef4444; border-color: #ef4444; color: #fff; }
.ds-page-dup:hover { background: var(--primary); border-color: var(--primary); color: #fff; }

.ds-page-add {
    flex-shrink: 0; width: 64px; height: 88px; border-radius: 8px;
    border: 2px dashed var(--border); background: transparent; color: var(--muted);
    font-size: 18px; cursor: pointer; display: flex; flex-direction: column;
    align-items: center; justify-content: center; gap: 4px; transition: all .15s; font-family: inherit;
}
.ds-page-add span { font-size: 9px; font-weight: 700; }
.ds-page-add:hover { border-color: var(--primary); color: var(--primary); background: color-mix(in srgb,var(--primary) 5%,transparent); }

/* Page counter in topbar */
.dst-page-ind {
    font-size: 11px; font-weight: 700; color: var(--muted);
    background: color-mix(in srgb,var(--bg) 60%,var(--card));
    border: 1px solid var(--border); border-radius: 7px; padding: 5px 10px;
    letter-spacing: -.01em; white-space: nowrap; cursor: default;
}
</style>

<div class="ds-editor" id="dsEditor">

{{-- TOP BAR --}}
<div class="dst">
    <div class="dst-left">
        <a href="{{ route('designstudio.index') }}" class="dst-back">
            <i class="fa fa-arrow-left"></i> Studio
        </a>
        <div class="dst-sep"></div>
        <input type="text" id="dsTitleInput" class="dst-title"
               value="{{ $design?->title ?? 'Untitled Design' }}" maxlength="120" spellcheck="false">
        <span class="dst-page-ind" id="dsPageInd" style="display:none">1 / 1</span>
    </div>

    <div class="dst-center">
        <div class="dst-group">
            <button class="dst-btn dst-btn-icon" id="dsUndoBtn" onclick="edUndo()" title="Undo (Ctrl+Z)" disabled>
                <i class="fa fa-rotate-left"></i>
            </button>
            <button class="dst-btn dst-btn-icon" id="dsRedoBtn" onclick="edRedo()" title="Redo (Ctrl+Y)" disabled>
                <i class="fa fa-rotate-right"></i>
            </button>
        </div>
        <div class="dst-group">
            <button class="dst-btn dst-btn-icon" onclick="edZoomOut()"  title="Zoom out"><i class="fa fa-magnifying-glass-minus"></i></button>
            <span class="dst-zoom-lbl" id="dsZoomLbl">100%</span>
            <button class="dst-btn dst-btn-icon" onclick="edZoomIn()"   title="Zoom in"><i class="fa fa-magnifying-glass-plus"></i></button>
            <button class="dst-btn dst-btn-icon" onclick="edZoomFit()"  title="Fit screen"><i class="fa fa-compress"></i></button>
        </div>
        <div class="dst-group">
            <button class="dst-btn dst-btn-icon" onclick="edAlignLeft()"    title="Align left"><i class="fa fa-align-left"></i></button>
            <button class="dst-btn dst-btn-icon" onclick="edAlignCenter()"  title="Centre H"><i class="fa fa-align-center"></i></button>
            <button class="dst-btn dst-btn-icon" onclick="edAlignRight()"   title="Align right"><i class="fa fa-align-right"></i></button>
            <button class="dst-btn dst-btn-icon" onclick="edAlignVCenter()" title="Centre V"><i class="fa fa-arrows-up-down"></i></button>
        </div>
    </div>

    <div class="dst-right">
        <div class="dst-export-wrap">
            <button class="dst-btn dst-btn-sec">
                <i class="fa fa-download"></i> Export <i class="fa fa-chevron-down" style="font-size:9px;margin-left:2px;"></i>
            </button>
            <div class="dst-export-menu">
                <button class="dst-export-item" onclick="edExport('png')"><i class="fa fa-image"></i> Export as PNG</button>
                <button class="dst-export-item" onclick="edExport('jpeg')"><i class="fa fa-file-image"></i> Export as JPG</button>
                <button class="dst-export-item" onclick="edExport('svg')"><i class="fa fa-code"></i> Export as SVG</button>
                <div style="height:1px;background:var(--border);margin:4px 0;"></div>
                <button class="dst-export-item" onclick="edExportPdf()"><i class="fa fa-file-pdf"></i> Export as PDF</button>
            </div>
        </div>
        <button class="dst-btn dst-btn-pri" onclick="edSave()" id="dsSaveBtn">
            <i class="fa fa-floppy-disk"></i> Save
        </button>
    </div>
</div>

{{-- BODY --}}
<div class="ds-body">

    {{-- LEFT PANEL --}}
    <div class="dsl">
        <div class="dsl-tabs">
            <button class="dsl-tab on" data-tab="content" onclick="switchLeftTab('content',this)">
                <i class="fa fa-layer-group"></i> Content
            </button>
            <button class="dsl-tab" data-tab="images" onclick="switchLeftTab('images',this)">
                <i class="fa fa-images"></i> Images
            </button>
        </div>
        <div class="dsl-body">

            {{-- ── CONTENT TAB ── --}}
            <div class="dsl-pane on" id="dslp-content">

                {{-- Shapes accordion --}}
                <div class="dsl-accord open" id="accord-shapes">
                    <div class="dsl-accord-hdr" onclick="toggleAccord('shapes')">
                        <div class="dsl-accord-icon"><i class="fa fa-shapes"></i></div>
                        <span class="dsl-accord-label">Shapes</span>
                        <i class="fa fa-chevron-down dsl-accord-arrow"></i>
                    </div>
                    <div class="dsl-accord-body">
                        <div class="dsl-shapes">
                            <button class="dsl-shape" onclick="edAddRect()">
                                <svg viewBox="0 0 40 40"><rect x="4" y="4" width="32" height="32" rx="2" fill="none" stroke="currentColor" stroke-width="2.5"/></svg>
                                Rectangle
                            </button>
                            <button class="dsl-shape" onclick="edAddRoundRect()">
                                <svg viewBox="0 0 40 40"><rect x="4" y="4" width="32" height="32" rx="10" fill="none" stroke="currentColor" stroke-width="2.5"/></svg>
                                Rounded
                            </button>
                            <button class="dsl-shape" onclick="edAddCircle()">
                                <svg viewBox="0 0 40 40"><circle cx="20" cy="20" r="15" fill="none" stroke="currentColor" stroke-width="2.5"/></svg>
                                Circle
                            </button>
                            <button class="dsl-shape" onclick="edAddEllipse()">
                                <svg viewBox="0 0 40 40"><ellipse cx="20" cy="20" rx="18" ry="12" fill="none" stroke="currentColor" stroke-width="2.5"/></svg>
                                Ellipse
                            </button>
                            <button class="dsl-shape" onclick="edAddTriangle()">
                                <svg viewBox="0 0 40 40"><polygon points="20,4 36,36 4,36" fill="none" stroke="currentColor" stroke-width="2.5"/></svg>
                                Triangle
                            </button>
                            <button class="dsl-shape" onclick="edAddLine()">
                                <svg viewBox="0 0 40 40"><line x1="4" y1="20" x2="36" y2="20" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>
                                Line
                            </button>
                            <button class="dsl-shape" onclick="edAddStar()">
                                <svg viewBox="0 0 40 40"><polygon points="20,4 24.7,15.3 37,15.3 27,22.7 30.7,34 20,27 9.3,34 13,22.7 3,15.3 15.3,15.3" fill="none" stroke="currentColor" stroke-width="2.2"/></svg>
                                Star
                            </button>
                            <button class="dsl-shape" onclick="edAddArrow()">
                                <svg viewBox="0 0 40 40"><line x1="4" y1="20" x2="32" y2="20" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/><polyline points="24,12 32,20 24,28" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                Arrow
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Text accordion --}}
                <div class="dsl-accord" id="accord-text">
                    <div class="dsl-accord-hdr" onclick="toggleAccord('text')">
                        <div class="dsl-accord-icon"><i class="fa fa-font"></i></div>
                        <span class="dsl-accord-label">Text</span>
                        <i class="fa fa-chevron-down dsl-accord-arrow"></i>
                    </div>
                    <div class="dsl-accord-body">
                        <div class="dsl-texts">
                            <button class="dsl-text dsl-text-h1" onclick="edAddText(52,'bold','Add a heading')">
                                <div><div class="dsl-text-lbl">Heading</div><div class="dsl-text-pre">Aa</div></div>
                            </button>
                            <button class="dsl-text dsl-text-h2" onclick="edAddText(30,'bold','Add a subheading')">
                                <div><div class="dsl-text-lbl">Subheading</div><div class="dsl-text-pre">Aa</div></div>
                            </button>
                            <button class="dsl-text dsl-text-body" onclick="edAddText(18,'normal','Add body text')">
                                <div><div class="dsl-text-lbl">Body</div><div class="dsl-text-pre">Body text</div></div>
                            </button>
                            <button class="dsl-text dsl-text-body" onclick="edAddText(13,'normal','Caption text')">
                                <div><div class="dsl-text-lbl">Caption</div><div class="dsl-text-pre">Caption</div></div>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Backgrounds accordion --}}
                <div class="dsl-accord" id="accord-bg">
                    <div class="dsl-accord-hdr" onclick="toggleAccord('bg')">
                        <div class="dsl-accord-icon"><i class="fa fa-fill-drip"></i></div>
                        <span class="dsl-accord-label">Backgrounds</span>
                        <i class="fa fa-chevron-down dsl-accord-arrow"></i>
                    </div>
                    <div class="dsl-accord-body">
                        <div class="dsl-color-row">
                            <input type="color" id="dsBgNative" class="dsl-native-color" value="#ffffff" oninput="setCanvasBg(this.value)">
                            <input type="text"  id="dsBgHex"    class="dsl-color-hex" value="#ffffff" maxlength="9" placeholder="#ffffff"
                                   onchange="setCanvasBgFromHex(this.value)" oninput="syncBgSwatch(this.value)">
                        </div>
                        <div class="dsl-swatches">
                            <div class="dsl-swatch" style="background:#ffffff;" onclick="setCanvasBg('#ffffff')"></div>
                            <div class="dsl-swatch" style="background:#000000;" onclick="setCanvasBg('#000000')"></div>
                            <div class="dsl-swatch" style="background:#f8fafc;" onclick="setCanvasBg('#f8fafc')"></div>
                            <div class="dsl-swatch" style="background:#1e293b;" onclick="setCanvasBg('#1e293b')"></div>
                            <div class="dsl-swatch" style="background:#ef4444;" onclick="setCanvasBg('#ef4444')"></div>
                            <div class="dsl-swatch" style="background:#f97316;" onclick="setCanvasBg('#f97316')"></div>
                            <div class="dsl-swatch" style="background:#eab308;" onclick="setCanvasBg('#eab308')"></div>
                            <div class="dsl-swatch" style="background:#22c55e;" onclick="setCanvasBg('#22c55e')"></div>
                            <div class="dsl-swatch" style="background:#3b82f6;" onclick="setCanvasBg('#3b82f6')"></div>
                            <div class="dsl-swatch" style="background:#6366f1;" onclick="setCanvasBg('#6366f1')"></div>
                            <div class="dsl-swatch" style="background:#7c3aed;" onclick="setCanvasBg('#7c3aed')"></div>
                            <div class="dsl-swatch" style="background:#ec4899;" onclick="setCanvasBg('#ec4899')"></div>
                        </div>
                        <p class="dsl-bg-sec-title">Gradients</p>
                        <div class="dsl-gradients">
                            <div class="dsl-grad" style="background:linear-gradient(135deg,#667eea,#764ba2)" onclick="setCanvasGradient('#667eea','#764ba2')"></div>
                            <div class="dsl-grad" style="background:linear-gradient(135deg,#f093fb,#f5576c)" onclick="setCanvasGradient('#f093fb','#f5576c')"></div>
                            <div class="dsl-grad" style="background:linear-gradient(135deg,#4facfe,#00f2fe)" onclick="setCanvasGradient('#4facfe','#00f2fe')"></div>
                            <div class="dsl-grad" style="background:linear-gradient(135deg,#43e97b,#38f9d7)" onclick="setCanvasGradient('#43e97b','#38f9d7')"></div>
                            <div class="dsl-grad" style="background:linear-gradient(135deg,#fa709a,#fee140)" onclick="setCanvasGradient('#fa709a','#fee140')"></div>
                            <div class="dsl-grad" style="background:linear-gradient(135deg,#a18cd1,#fbc2eb)" onclick="setCanvasGradient('#a18cd1','#fbc2eb')"></div>
                            <div class="dsl-grad" style="background:linear-gradient(135deg,#ffecd2,#fcb69f)" onclick="setCanvasGradient('#ffecd2','#fcb69f')"></div>
                            <div class="dsl-grad" style="background:linear-gradient(135deg,#2d3748,#4a5568)" onclick="setCanvasGradient('#2d3748','#4a5568')"></div>
                        </div>
                    </div>
                </div>

            </div>{{-- /dslp-content --}}

            {{-- ── IMAGES TAB ── --}}
            <div class="dsl-pane" id="dslp-images">

                {{-- My Uploads accordion --}}
                <div class="dsl-accord open" id="accord-uploads">
                    <div class="dsl-accord-hdr" onclick="toggleAccord('uploads')">
                        <div class="dsl-accord-icon"><i class="fa fa-cloud-arrow-up"></i></div>
                        <span class="dsl-accord-label">My Uploads</span>
                        <i class="fa fa-chevron-down dsl-accord-arrow"></i>
                    </div>
                    <div class="dsl-accord-body">
                        <div class="dsl-upload" onclick="document.getElementById('dsImgInput').click()">
                            <i class="fa fa-cloud-arrow-up"></i>
                            <p><strong>Click to upload</strong></p>
                            <p>PNG, JPG, SVG, GIF</p>
                        </div>
                        <input type="file" id="dsImgInput" accept="image/*" onchange="edAddImage(this)">
                        <div id="dsImgThumbsWrap" class="dsl-img-grid"></div>
                    </div>
                </div>

                {{-- Image Library accordion --}}
                <div class="dsl-accord open" id="accord-library">
                    <div class="dsl-accord-hdr" onclick="toggleAccord('library')">
                        <div class="dsl-accord-icon"><i class="fa fa-photo-film"></i></div>
                        <span class="dsl-accord-label">Image Library</span>
                        <i class="fa fa-chevron-down dsl-accord-arrow"></i>
                    </div>
                    <div class="dsl-accord-body">
                        <div class="dsl-img-grid">
                            <img class="dsl-lib-img" loading="lazy" crossorigin="anonymous" title="Nature"    src="https://picsum.photos/seed/dsl-n1/300/225"  onclick="edAddLibImg(this.src)">
                            <img class="dsl-lib-img" loading="lazy" crossorigin="anonymous" title="Forest"   src="https://picsum.photos/seed/dsl-n2/300/225"  onclick="edAddLibImg(this.src)">
                            <img class="dsl-lib-img" loading="lazy" crossorigin="anonymous" title="City"     src="https://picsum.photos/seed/dsl-c1/300/225"  onclick="edAddLibImg(this.src)">
                            <img class="dsl-lib-img" loading="lazy" crossorigin="anonymous" title="Street"   src="https://picsum.photos/seed/dsl-c2/300/225"  onclick="edAddLibImg(this.src)">
                            <img class="dsl-lib-img" loading="lazy" crossorigin="anonymous" title="Abstract" src="https://picsum.photos/seed/dsl-a1/300/225"  onclick="edAddLibImg(this.src)">
                            <img class="dsl-lib-img" loading="lazy" crossorigin="anonymous" title="Pattern"  src="https://picsum.photos/seed/dsl-a2/300/225"  onclick="edAddLibImg(this.src)">
                            <img class="dsl-lib-img" loading="lazy" crossorigin="anonymous" title="Office"   src="https://picsum.photos/seed/dsl-b1/300/225"  onclick="edAddLibImg(this.src)">
                            <img class="dsl-lib-img" loading="lazy" crossorigin="anonymous" title="Desk"     src="https://picsum.photos/seed/dsl-b2/300/225"  onclick="edAddLibImg(this.src)">
                            <img class="dsl-lib-img" loading="lazy" crossorigin="anonymous" title="People"   src="https://picsum.photos/seed/dsl-p1/300/225"  onclick="edAddLibImg(this.src)">
                            <img class="dsl-lib-img" loading="lazy" crossorigin="anonymous" title="Team"     src="https://picsum.photos/seed/dsl-p2/300/225"  onclick="edAddLibImg(this.src)">
                            <img class="dsl-lib-img" loading="lazy" crossorigin="anonymous" title="Sky"      src="https://picsum.photos/seed/dsl-s1/300/225"  onclick="edAddLibImg(this.src)">
                            <img class="dsl-lib-img" loading="lazy" crossorigin="anonymous" title="Minimal"  src="https://picsum.photos/seed/dsl-s2/300/225"  onclick="edAddLibImg(this.src)">
                        </div>
                    </div>
                </div>

            </div>{{-- /dslp-images --}}

        </div>
    </div>

    {{-- CANVAS COLUMN --}}
    <div class="ds-canvas-column">
        <div class="ds-canvas-area" id="dsCanvasArea">
            <div class="ds-canvas-wrap" id="dsCanvasWrap">
                <canvas id="dsCanvas"></canvas>
            </div>
        </div>
        {{-- PAGES BAR (multi-page mode) --}}
        <div class="ds-pages-bar" id="dsPagesBar" style="display:none">
            <div class="ds-pages-inner">
                <div class="ds-pages-scroll" id="dsPagesScroll"></div>
                <button class="ds-page-add" onclick="addPage()" title="Add new page">
                    <i class="fa fa-plus"></i>
                    <span>Add</span>
                </button>
            </div>
        </div>
    </div>

    {{-- RIGHT PANEL --}}
    <div class="dsr">
        <div class="dsr-tabs">
            <button class="dsr-tab on" data-rtab="props"  onclick="switchRightTab('props',this)">
                <i class="fa fa-sliders"></i> Properties
            </button>
            <button class="dsr-tab"    data-rtab="layers" onclick="switchRightTab('layers',this)">
                <i class="fa fa-layer-group"></i> Layers
            </button>
        </div>
        <div class="dsr-body">

            {{-- Properties --}}
            <div class="dsr-pane on" id="dsrp-props">
                <div class="dsp-empty" id="dspEmpty">
                    <i class="fa fa-mouse-pointer"></i>
                    <p>Select an object to edit its properties</p>
                </div>
                <div id="dspContent" style="display:none">

                    {{-- Transform --}}
                    <div class="dsp-sec">
                        <p class="dsp-sec-title">Transform</p>
                        <div class="dsp-row">
                            <span class="dsp-lbl">X</span>
                            <input type="number" class="dsp-inp dsp-inp-sm" id="dspX" oninput="setPropXY()">
                            <span class="dsp-lbl">Y</span>
                            <input type="number" class="dsp-inp dsp-inp-sm" id="dspY" oninput="setPropXY()">
                        </div>
                        <div class="dsp-row">
                            <span class="dsp-lbl">W</span>
                            <input type="number" class="dsp-inp dsp-inp-sm" id="dspW" oninput="setPropWH()">
                            <span class="dsp-lbl">H</span>
                            <input type="number" class="dsp-inp dsp-inp-sm" id="dspH" oninput="setPropWH()">
                        </div>
                        <div class="dsp-row">
                            <span class="dsp-lbl" style="width:30px;">Rot</span>
                            <input type="number" class="dsp-inp" id="dspRot" step="1" oninput="setPropRot()">
                        </div>
                    </div>

                    {{-- Appearance --}}
                    <div class="dsp-sec">
                        <p class="dsp-sec-title">Appearance</p>
                        <div class="dsp-color">
                            <div class="dsp-cswatch" id="dspFillSwatch">
                                <input type="color" id="dspFillPicker" value="#7c3aed" oninput="setPropFill(this.value)">
                            </div>
                            <input type="text" class="dsp-chex" id="dspFillHex" maxlength="9"
                                   onchange="setPropFill(this.value)" oninput="syncFillSwatch(this.value)" placeholder="Fill colour">
                            <label class="ds-no-fill">
                                <input type="checkbox" id="dspNoFill" onchange="toggleNoFill(this.checked)"> None
                            </label>
                        </div>
                        <div class="dsp-color">
                            <div class="dsp-cswatch" id="dspStrokeSwatch">
                                <input type="color" id="dspStrokePicker" value="#000000" oninput="setPropStroke(this.value)">
                            </div>
                            <input type="text" class="dsp-chex" id="dspStrokeHex" maxlength="9"
                                   onchange="setPropStroke(this.value)" oninput="syncStrokeSwatch(this.value)" placeholder="No stroke">
                            <input type="number" class="dsp-inp dsp-inp-xs" id="dspStrokeW" min="0" max="50" placeholder="W"
                                   oninput="setPropStrokeWidth(this.value)" title="Stroke width">
                        </div>
                        <div class="dsp-slide-row">
                            <div class="dsp-slide-hdr">Opacity <span id="dspOpacityVal">100%</span></div>
                            <input type="range" class="dsp-slider" id="dspOpacity" min="0" max="100" value="100" oninput="setPropOpacity(this.value)">
                        </div>
                    </div>

                    {{-- Corner radius (rect only) --}}
                    <div class="dsp-sec" id="dspCornerSec" style="display:none">
                        <p class="dsp-sec-title">Shape</p>
                        <div class="dsp-row">
                            <span class="dsp-lbl-w">Corner</span>
                            <input type="number" class="dsp-inp" id="dspCorner" min="0" max="500" placeholder="0" oninput="setPropCorner(this.value)">
                        </div>
                    </div>

                    {{-- Typography (text only) --}}
                    <div class="dsp-sec" id="dspTextSec" style="display:none">
                        <p class="dsp-sec-title">Typography</p>
                        <div class="dsp-row" style="margin-bottom:6px">
                            <select class="dsp-font-sel" id="dspFontFam" onchange="setPropFontFamily(this.value)">
                                <option>Inter</option><option>Roboto</option><option>Open Sans</option>
                                <option>Lato</option><option>Montserrat</option><option>Nunito</option>
                                <option>Raleway</option><option>Oswald</option>
                                <option>Playfair Display</option><option>Dancing Script</option>
                                <option>Arial</option><option>Georgia</option><option>Impact</option>
                                <option>Times New Roman</option><option>Courier New</option>
                                <option>Verdana</option><option>Trebuchet MS</option>
                            </select>
                        </div>
                        <div class="dsp-row">
                            <span class="dsp-lbl-w">Size</span>
                            <input type="number" class="dsp-inp" id="dspFontSize" min="6" max="800" oninput="setPropFontSize(this.value)">
                        </div>
                        <div class="dsp-row">
                            <span class="dsp-lbl-w">Align</span>
                            <div class="dsp-toggles">
                                <button class="dsp-tog" id="dspAlignL" onclick="setPropAlign('left')"><i class="fa fa-align-left"></i></button>
                                <button class="dsp-tog" id="dspAlignC" onclick="setPropAlign('center')"><i class="fa fa-align-center"></i></button>
                                <button class="dsp-tog" id="dspAlignR" onclick="setPropAlign('right')"><i class="fa fa-align-right"></i></button>
                            </div>
                        </div>
                        <div class="dsp-row">
                            <div class="dsp-toggles">
                                <button class="dsp-tog" id="dspBold"        onclick="togglePropBold()"><b>B</b></button>
                                <button class="dsp-tog" id="dspItalic"      onclick="togglePropItalic()"><i>I</i></button>
                                <button class="dsp-tog" id="dspUnderline"   onclick="togglePropUnderline()"><u>U</u></button>
                                <button class="dsp-tog" id="dspLinethrough" onclick="togglePropLinethrough()"><s>S</s></button>
                            </div>
                        </div>
                        <div class="dsp-row" style="margin-top:4px">
                            <span class="dsp-lbl-w">Line H</span>
                            <input type="number" class="dsp-inp" id="dspLineH" min="0.5" max="5" step="0.1" oninput="setPropLineH(this.value)">
                        </div>
                        <div class="dsp-row">
                            <span class="dsp-lbl-w">Spacing</span>
                            <input type="number" class="dsp-inp" id="dspCharSp" min="-500" max="1000" step="5" oninput="setPropCharSpacing(this.value)">
                        </div>
                    </div>

                    {{-- Object actions --}}
                    <div class="dsp-sec">
                        <p class="dsp-sec-title">Object</p>
                        <div class="dsp-actions" style="margin-bottom:5px">
                            <button class="dsp-act" onclick="edBringFwd()"><i class="fa fa-angle-up"></i> Fwd</button>
                            <button class="dsp-act" onclick="edSendBck()"><i class="fa fa-angle-down"></i> Back</button>
                            <button class="dsp-act" onclick="edBringFront()"><i class="fa fa-angles-up"></i> Front</button>
                            <button class="dsp-act" onclick="edSendBack()"><i class="fa fa-angles-down"></i> BBack</button>
                        </div>
                        <div class="dsp-actions">
                            <button class="dsp-act" onclick="edDuplicate()"><i class="fa fa-clone"></i> Dupe</button>
                            <button class="dsp-act" onclick="edFlipH()"><i class="fa fa-arrows-left-right"></i> FlipH</button>
                            <button class="dsp-act" onclick="edFlipV()"><i class="fa fa-arrows-up-down"></i> FlipV</button>
                            <button class="dsp-act dsp-act-danger" onclick="edDelete()"><i class="fa fa-trash"></i> Del</button>
                        </div>
                    </div>

                </div>
            </div>

            {{-- Layers --}}
            <div class="dsr-pane" id="dsrp-layers">
                <div id="dsLayersList">
                    <div class="dsl-layers-empty">No objects yet. Add shapes or text to get started.</div>
                </div>
            </div>

        </div>
    </div>

</div>{{-- /ds-body --}}

{{-- PDF Export Progress Overlay --}}
<div class="ds-pdf-overlay" id="dsPdfOverlay">
    <div class="ds-pdf-modal">
        <div class="ds-pdf-spinner"></div>
        <div class="ds-pdf-title">Generating PDF</div>
        <div class="ds-pdf-status" id="dsPdfStatus">Preparing…</div>
        <div class="ds-pdf-bar-wrap"><div class="ds-pdf-bar" id="dsPdfBar"></div></div>
    </div>
</div>
</div>{{-- /ds-editor --}}

<div class="ds-toast" id="dsToast"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
/* ──────────────────────────────────────────────────────────────
   STATE
   ────────────────────────────────────────────────────────────── */
var canvas;
var designId     = {{ $design?->id ?? 'null' }};
var designW      = {{ $design?->width  ?? (request()->integer('w', 1080)) }};
var designH      = {{ $design?->height ?? (request()->integer('h', 1080)) }};
var designType   = @json($design?->type ?? request('type', ''));
var canvasJson   = @json($design?->canvas_json);

/* multi-page */
var isMultiPage  = (designType === 'company-profile');
var pages        = [];   /* [{json: string|null, thumb: string|null}] */
var currentPage  = 0;

var undoStack    = [];
var undoIdx      = -1;
var inHistory    = false;
var histDebounce = null;
var currentZoom  = 1.0;
var objectNames  = new WeakMap();
var objectCounts = {};
var fabricDragging = false;

/* ──────────────────────────────────────────────────────────────
   INIT
   ────────────────────────────────────────────────────────────── */
window.addEventListener('DOMContentLoaded', function() {
    canvas = new fabric.Canvas('dsCanvas', {
        width: designW, height: designH,
        backgroundColor: '#ffffff',
        selection: true,
        preserveObjectStacking: true,
        stopContextMenu: true,
        fireRightClick: false,
        enableRetinaScaling: false,
    });

    canvas.on('selection:created',  onSelChange);
    canvas.on('selection:updated',  onSelChange);
    canvas.on('selection:cleared',  onSelClear);
    canvas.on('object:modified',    onObjMod);
    canvas.on('object:moving',      onObjMoving);
    canvas.on('object:scaling',     onObjScaling);
    canvas.on('object:rotating',    onObjRotating);
    canvas.on('path:created',       function(){ saveHist(); updateLayers(); });
    canvas.on('object:added',       function(){ if (!inHistory) updateLayers(); });
    canvas.on('object:removed',     function(){ if (!inHistory) updateLayers(); });
    canvas.on('mouse:down', function(opt){ if (opt.target) fabricDragging = true; });
    canvas.on('mouse:up',   function()  { fabricDragging = false; });

    /* ── multi-page init ── */
    if (isMultiPage) {
        document.getElementById('dsPagesBar').style.display = '';
        document.getElementById('dsPageInd').style.display  = '';
    }

    function afterLoad() {
        inHistory = false;
        saveHist(); updateLayers();
        requestAnimationFrame(function(){ edZoomFit(); canvas.calcOffset(); });
        if (isMultiPage) { updatePagesPanel(); updatePageCounter(); }
    }

    if (canvasJson) {
        var parsedCj;
        try { parsedCj = JSON.parse(canvasJson); } catch(e) { parsedCj = null; }

        if (Array.isArray(parsedCj)) {
            /* saved in multi-page format */
            isMultiPage = true;
            document.getElementById('dsPagesBar').style.display = '';
            document.getElementById('dsPageInd').style.display  = '';
            pages = parsedCj;
            currentPage = 0;
            inHistory = true;
            canvas.loadFromJSON(pages[0].json, function() {
                canvas.renderAll(); afterLoad();
            });
        } else {
            if (isMultiPage) { pages = [{ json: canvasJson, thumb: null }]; }
            inHistory = true;
            canvas.loadFromJSON(canvasJson, function() {
                canvas.renderAll(); afterLoad();
            });
        }
    } else {
        /* Check for wizard-generated template (company profile, letterhead, etc.) */
        var wzTemplate = null;
        try {
            var wzRaw = sessionStorage.getItem('dsWizardTemplate');
            if (wzRaw) { wzTemplate = JSON.parse(wzRaw); sessionStorage.removeItem('dsWizardTemplate'); }
        } catch(e) {}

        if (wzTemplate && Array.isArray(wzTemplate) && wzTemplate.length) {
            if (isMultiPage) {
                /* Multi-page design: load all pages from wizard */
                pages = wzTemplate;
                currentPage = 0;
                inHistory = true;
                canvas.loadFromJSON(pages[0].json, function() {
                    canvas.renderAll(); afterLoad();
                });
            } else {
                /* Single-page design (e.g. letterhead): load first page JSON */
                inHistory = true;
                canvas.loadFromJSON(wzTemplate[0].json, function() {
                    canvas.renderAll(); afterLoad();
                });
            }
        } else {
            if (isMultiPage) { pages = [{ json: null, thumb: null }]; }
            saveHist();
            requestAnimationFrame(function(){ edZoomFit(); canvas.calcOffset(); });
            if (isMultiPage) { updatePagesPanel(); updatePageCounter(); }
        }
    }

    document.addEventListener('keydown', onKeyDown);
    document.getElementById('dsTitleInput').addEventListener('keydown', function(e){ e.stopPropagation(); });

    document.getElementById('dsCanvasArea').addEventListener('scroll', function(){
        canvas.calcOffset();
    });

    document.addEventListener('mouseup', function(e){
        if (fabricDragging && canvas) {
            fabricDragging = false;
            try {
                canvas.upperCanvasEl.dispatchEvent(new MouseEvent('mouseup', {
                    bubbles: false, cancelable: true,
                    clientX: e.clientX, clientY: e.clientY
                }));
            } catch(_){}
        }
    });
});

/* ──────────────────────────────────────────────────────────────
   TAB SWITCHERS
   ────────────────────────────────────────────────────────────── */
function switchLeftTab(name, btn) {
    document.querySelectorAll('.dsl-tab').forEach(function(b){ b.classList.remove('on'); });
    document.querySelectorAll('.dsl-pane').forEach(function(p){ p.classList.remove('on'); });
    btn.classList.add('on');
    document.getElementById('dslp-' + name).classList.add('on');
}
function switchRightTab(name, btn) {
    document.querySelectorAll('.dsr-tab').forEach(function(b){ b.classList.remove('on'); });
    document.querySelectorAll('.dsr-pane').forEach(function(p){ p.classList.remove('on'); });
    btn.classList.add('on');
    document.getElementById('dsrp-' + name).classList.add('on');
}
function toggleAccord(id) {
    document.getElementById('accord-' + id).classList.toggle('open');
}

/* ──────────────────────────────────────────────────────────────
   MULTI-PAGE
   ────────────────────────────────────────────────────────────── */
function flushCurrentPage() {
    if (!isMultiPage) return;
    pages[currentPage].json = JSON.stringify(canvas.toJSON(['selectable','evented']));
    try {
        var mult = 80 / (designW * currentZoom);
        pages[currentPage].thumb = canvas.toDataURL({ format: 'jpeg', quality: 0.5, multiplier: mult });
    } catch(e) { /* tainted canvas (cross-origin image) */ }
    updatePageThumb(currentPage);
}

function updatePageThumb(idx) {
    var items = document.querySelectorAll('#dsPagesScroll .ds-page-thumb');
    if (!items[idx]) return;
    var prev = items[idx].querySelector('.ds-page-preview');
    if (prev && pages[idx] && pages[idx].thumb) {
        prev.style.backgroundImage = 'url(' + pages[idx].thumb + ')';
        prev.innerHTML = '';
    }
}

function updatePageCounter() {
    var el = document.getElementById('dsPageInd');
    if (el) el.textContent = (currentPage + 1) + ' / ' + pages.length;
}

function updatePagesPanel() {
    var scroll = document.getElementById('dsPagesScroll');
    if (!scroll) return;
    scroll.innerHTML = '';
    pages.forEach(function(pg, i) {
        var div = document.createElement('div');
        div.className = 'ds-page-thumb' + (i === currentPage ? ' active' : '');
        /* onclick on the outer container so entire card area is clickable */
        div.onclick = (function(idx){ return function(){ switchPage(idx); }; })(i);

        var preview = document.createElement('div');
        preview.className = 'ds-page-preview';
        if (pg.thumb) {
            preview.style.backgroundImage = 'url(' + pg.thumb + ')';
        } else {
            preview.innerHTML = '<i class="fa fa-file-alt"></i>';
        }

        var num = document.createElement('div');
        num.className = 'ds-page-num';
        num.textContent = i + 1;

        var del = document.createElement('button');
        del.className = 'ds-page-del';
        del.innerHTML = '<i class="fa fa-times"></i>';
        del.title = 'Delete page';
        del.onclick = (function(idx){ return function(e){ e.stopPropagation(); deletePage(idx); }; })(i);

        var dup = document.createElement('button');
        dup.className = 'ds-page-dup';
        dup.innerHTML = '<i class="fa fa-copy"></i>';
        dup.title = 'Duplicate page';
        dup.onclick = (function(idx){ return function(e){ e.stopPropagation(); duplicatePage(idx); }; })(i);

        div.appendChild(dup);
        div.appendChild(preview);
        div.appendChild(num);
        div.appendChild(del);
        scroll.appendChild(div);
    });
}

/* Central canvas-load helper — guards inHistory before any clear */
function loadPageIntoCanvas(pageJson, cb) {
    inHistory = true;
    canvas.discardActiveObject();
    if (pageJson) {
        /* loadFromJSON internally calls canvas.clear() */
        canvas.loadFromJSON(pageJson, function() {
            canvas.renderAll();
            inHistory = false;
            cb();
        });
    } else {
        canvas.clear();
        canvas.setBackgroundColor('#ffffff', function() {
            canvas.renderAll();
            inHistory = false;
            cb();
        });
    }
}

function afterPageChange() {
    undoStack = []; undoIdx = -1;
    saveHist(); updateLayers(); onSelClear();
    canvas.calcOffset(); updatePagesPanel(); updatePageCounter();
}

function switchPage(idx) {
    if (idx === currentPage) return;
    flushCurrentPage();
    currentPage = idx;
    loadPageIntoCanvas(pages[idx] ? pages[idx].json : null, afterPageChange);
}

function addPage() {
    flushCurrentPage();
    pages.push({ json: null, thumb: null });
    currentPage = pages.length - 1;
    loadPageIntoCanvas(null, function() {
        afterPageChange();
        showToast('Page ' + pages.length + ' added', 'ok');
    });
}

function deletePage(idx) {
    if (pages.length <= 1) { showToast('Cannot delete the only page', 'err'); return; }
    flushCurrentPage();
    var wasCurrent = (idx === currentPage);
    pages.splice(idx, 1);
    /* Fix index after splice — shift left if deleted before current */
    if (idx < currentPage) {
        currentPage--;
    } else if (currentPage >= pages.length) {
        currentPage = pages.length - 1;
    }
    if (wasCurrent) {
        /* Deleted the active page — load the new current page */
        loadPageIntoCanvas(pages[currentPage] ? pages[currentPage].json : null, afterPageChange);
    } else {
        /* Deleted a different page — canvas unchanged, just refresh the panel */
        updatePagesPanel(); updatePageCounter();
    }
    showToast('Page deleted', 'ok');
}

function duplicatePage(idx) {
    flushCurrentPage();
    var copy = { json: pages[idx].json, thumb: pages[idx].thumb };
    pages.splice(idx + 1, 0, copy);
    currentPage = idx + 1;
    loadPageIntoCanvas(copy.json, function() {
        afterPageChange();
        showToast('Page duplicated', 'ok');
    });
}

function buildCanvasJson() {
    if (!isMultiPage) {
        return JSON.stringify(canvas.toJSON(['selectable','evented']));
    }
    flushCurrentPage();
    /* Strip thumbnails — they are in-memory display cache, not source data */
    return JSON.stringify(pages.map(function(p) { return { json: p.json }; }));
}

/* ──────────────────────────────────────────────────────────────
   OBJECT NAMING
   ────────────────────────────────────────────────────────────── */
function nameFor(obj) {
    if (objectNames.has(obj)) return objectNames.get(obj);
    var t = typeLabel(obj);
    objectCounts[t] = (objectCounts[t] || 0) + 1;
    var name = t + ' ' + objectCounts[t];
    objectNames.set(obj, name);
    return name;
}
function typeLabel(obj) {
    var t = obj.type;
    if (t==='i-text'||t==='text'||t==='textbox') return 'Text';
    if (t==='rect')     return 'Rectangle';
    if (t==='circle')   return 'Circle';
    if (t==='ellipse')  return 'Ellipse';
    if (t==='triangle') return 'Triangle';
    if (t==='line')     return 'Line';
    if (t==='polygon')  return 'Polygon';
    if (t==='image')    return 'Image';
    if (t==='group')    return 'Group';
    if (t==='path')     return 'Path';
    return 'Object';
}
function typeIcon(obj) {
    var t = obj.type;
    if (t==='i-text'||t==='text'||t==='textbox') return 'fa-font';
    if (t==='circle'||t==='ellipse') return 'fa-circle';
    if (t==='triangle') return 'fa-play';
    if (t==='image')    return 'fa-image';
    if (t==='line')     return 'fa-minus';
    if (t==='path')     return 'fa-pen-nib';
    return 'fa-square';
}

/* ──────────────────────────────────────────────────────────────
   HISTORY
   ────────────────────────────────────────────────────────────── */
function saveHist() {
    if (inHistory) return;
    clearTimeout(histDebounce);
    undoStack = undoStack.slice(0, undoIdx + 1);
    undoStack.push(JSON.stringify(canvas.toJSON(['selectable','evented','name'])));
    if (undoStack.length > 60) undoStack.shift();
    undoIdx = undoStack.length - 1;
    updateHistBtns();
}
function saveHistSoon() {
    clearTimeout(histDebounce);
    histDebounce = setTimeout(saveHist, 600);
}
function loadHistState(idx) {
    inHistory = true;
    canvas.loadFromJSON(undoStack[idx], function() {
        canvas.renderAll();
        inHistory = false;
        updateHistBtns(); updateLayers(); onSelClear();
        canvas.calcOffset();
    });
}
function edUndo() { if (undoIdx > 0)                    { undoIdx--; loadHistState(undoIdx); } }
function edRedo() { if (undoIdx < undoStack.length - 1) { undoIdx++; loadHistState(undoIdx); } }
function updateHistBtns() {
    document.getElementById('dsUndoBtn').disabled = undoIdx <= 0;
    document.getElementById('dsRedoBtn').disabled = undoIdx >= undoStack.length - 1;
}
function onObjMod() { clearTimeout(histDebounce); saveHist(); }
function onObjMoving(e) {
    var o = e.target; if (!o) return;
    document.getElementById('dspX').value = Math.round(o.left || 0);
    document.getElementById('dspY').value = Math.round(o.top  || 0);
}
function onObjScaling(e) {
    var o = e.target; if (!o) return;
    document.getElementById('dspW').value = Math.round(o.getScaledWidth());
    document.getElementById('dspH').value = Math.round(o.getScaledHeight());
}
function onObjRotating(e) {
    var o = e.target; if (!o) return;
    document.getElementById('dspRot').value = Math.round(o.angle || 0);
}

/* ──────────────────────────────────────────────────────────────
   ADD SHAPES
   ────────────────────────────────────────────────────────────── */
function centerAdd(obj) {
    canvas.add(obj); canvas.centerObject(obj); canvas.setActiveObject(obj);
    canvas.renderAll(); saveHist(); updateLayers();
}
function edAddRect()      { centerAdd(new fabric.Rect({ width:220, height:140, fill:'#7c3aed', rx:0, ry:0 })); }
function edAddRoundRect() { centerAdd(new fabric.Rect({ width:220, height:140, fill:'#7c3aed', rx:18, ry:18 })); }
function edAddCircle()    { centerAdd(new fabric.Circle({ radius:80, fill:'#3b82f6' })); }
function edAddEllipse()   { centerAdd(new fabric.Ellipse({ rx:110, ry:70, fill:'#22c55e' })); }
function edAddTriangle()  { centerAdd(new fabric.Triangle({ width:180, height:160, fill:'#f97316' })); }
function edAddLine()      { centerAdd(new fabric.Line([60,0,260,0], { stroke:'#374151', strokeWidth:4, strokeLineCap:'round' })); }
function edAddStar()      { centerAdd(new fabric.Polygon(starPoints(5,80,40,0,0), { fill:'#eab308' })); }
function edAddArrow() {
    centerAdd(new fabric.Group([
        new fabric.Line([0,0,180,0], { stroke:'#374151', strokeWidth:4, strokeLineCap:'round' }),
        new fabric.Triangle({ width:22, height:28, fill:'#374151', left:166, top:-14, angle:90 }),
    ]));
}
function starPoints(n, outerR, innerR, cx, cy) {
    var pts = [], step = Math.PI / n;
    for (var i = 0; i < n*2; i++) {
        var r = i%2===0 ? outerR : innerR, a = i*step - Math.PI/2;
        pts.push({ x: cx + r*Math.cos(a), y: cy + r*Math.sin(a) });
    }
    return pts;
}

/* ──────────────────────────────────────────────────────────────
   ADD TEXT
   ────────────────────────────────────────────────────────────── */
function edAddText(fontSize, fontWeight, defaultText) {
    var t = new fabric.IText(defaultText, {
        fontSize: fontSize, fontWeight: fontWeight,
        fill: '#111827', fontFamily: 'Inter', left: 60, top: 60,
    });
    canvas.add(t); canvas.setActiveObject(t); canvas.renderAll();
    t.enterEditing(); t.selectAll();
    saveHist(); updateLayers();
}

/* ──────────────────────────────────────────────────────────────
   ADD IMAGE
   ────────────────────────────────────────────────────────────── */
function edAddImage(input) {
    var file = input.files[0]; if (!file) return;
    var reader = new FileReader();
    reader.onload = function(e) {
        var src = e.target.result;
        fabric.Image.fromURL(src, function(img) {
            if (img.width > designW*0.6) img.scaleToWidth(designW*0.6);
            canvas.add(img); canvas.centerObject(img); canvas.setActiveObject(img);
            canvas.renderAll(); saveHist(); updateLayers(); addImgThumb(src);
        });
    };
    reader.readAsDataURL(file);
    input.value = '';
}
function addImgThumb(src) {
    var wrap = document.getElementById('dsImgThumbsWrap');
    var img  = document.createElement('img');
    img.src  = src;
    img.className = 'dsl-img-thumb';
    img.title = 'Click to re-add';
    img.addEventListener('click', function() {
        fabric.Image.fromURL(src, function(obj) {
            if (obj.width > designW*0.5) obj.scaleToWidth(designW*0.5);
            canvas.add(obj); canvas.centerObject(obj); canvas.setActiveObject(obj);
            canvas.renderAll(); saveHist(); updateLayers();
        });
    });
    wrap.prepend(img);
    if (wrap.children.length > 8) wrap.removeChild(wrap.lastChild);
}
function edAddLibImg(url) {
    showToast('Adding image…');
    fabric.Image.fromURL(url, function(img) {
        if (!img || !img.width) { showToast('Could not load image', 'err'); return; }
        if (img.width > designW * 0.7) img.scaleToWidth(designW * 0.7);
        canvas.add(img); canvas.centerObject(img); canvas.setActiveObject(img);
        canvas.renderAll(); saveHist(); updateLayers();
        showToast('Image added', 'ok');
    }, { crossOrigin: 'anonymous' });
}

/* ──────────────────────────────────────────────────────────────
   BACKGROUND
   ────────────────────────────────────────────────────────────── */
function setCanvasBg(hex) {
    canvas.setBackgroundColor(hex, function(){ canvas.renderAll(); saveHist(); });
    document.getElementById('dsBgNative').value = hexToInputColor(hex);
    document.getElementById('dsBgHex').value    = hex;
}
function setCanvasBgFromHex(val) { if (/^#[0-9a-fA-F]{3,6}$/.test(val)) setCanvasBg(val); }
function syncBgSwatch(val) {
    if (/^#[0-9a-fA-F]{3,6}$/.test(val))
        document.getElementById('dsBgNative').value = hexToInputColor(val);
}
function setCanvasGradient(c1, c2) {
    canvas.setBackgroundColor(new fabric.Gradient({
        type:'linear', gradientUnits:'pixels',
        coords:{x1:0,y1:0,x2:designW,y2:designH},
        colorStops:[{offset:0,color:c1},{offset:1,color:c2}],
    }), function(){ canvas.renderAll(); saveHist(); });
    document.getElementById('dsBgHex').value = c1;
}
function hexToInputColor(hex) {
    if (/^#[0-9a-fA-F]{3}$/.test(hex))
        return '#'+hex[1]+hex[1]+hex[2]+hex[2]+hex[3]+hex[3];
    return hex;
}

/* ──────────────────────────────────────────────────────────────
   SELECTION → PROPERTIES PANEL
   ────────────────────────────────────────────────────────────── */
function onSelChange() {
    var obj = canvas.getActiveObject();
    if (!obj) { onSelClear(); return; }
    showProps(obj); updateLayerHighlight();
}
function onSelClear() {
    document.getElementById('dspEmpty').style.display   = '';
    document.getElementById('dspContent').style.display = 'none';
    updateLayerHighlight();
}
function showProps(obj) {
    document.getElementById('dspEmpty').style.display   = 'none';
    document.getElementById('dspContent').style.display = '';

    var isText = (obj.type==='i-text'||obj.type==='text'||obj.type==='textbox');
    var isRect = (obj.type==='rect');

    document.getElementById('dspX').value   = Math.round(obj.left  || 0);
    document.getElementById('dspY').value   = Math.round(obj.top   || 0);
    document.getElementById('dspW').value   = Math.round(obj.getScaledWidth());
    document.getElementById('dspH').value   = Math.round(obj.getScaledHeight());
    document.getElementById('dspRot').value = Math.round(obj.angle || 0);

    var fillVal  = obj.fill;
    var hasFill  = fillVal && fillVal!=='' && fillVal!=='transparent'
                   && !(fillVal instanceof fabric.Gradient) && !(fillVal instanceof fabric.Pattern);
    var fillColor = hasFill ? String(fillVal) : '#000000';
    document.getElementById('dspNoFill').checked              = !hasFill;
    document.getElementById('dspFillSwatch').style.background = hasFill ? fillColor : '';
    document.getElementById('dspFillHex').value               = hasFill ? fillColor : '';
    if (hasFill && /^#[0-9a-fA-F]{3,6}$/.test(fillColor))
        try { document.getElementById('dspFillPicker').value = hexToInputColor(fillColor); } catch(_){}

    var stroke = obj.stroke || '';
    document.getElementById('dspStrokeHex').value               = stroke;
    document.getElementById('dspStrokeSwatch').style.background = stroke && /^#/.test(stroke) ? stroke : '';
    if (stroke && /^#[0-9a-fA-F]{3,6}$/.test(stroke))
        try { document.getElementById('dspStrokePicker').value = hexToInputColor(stroke); } catch(_){}
    document.getElementById('dspStrokeW').value = obj.strokeWidth || '';

    var op = Math.round((obj.opacity !== undefined ? obj.opacity : 1) * 100);
    document.getElementById('dspOpacity').value          = op;
    document.getElementById('dspOpacityVal').textContent = op + '%';

    document.getElementById('dspCornerSec').style.display = isRect ? '' : 'none';
    if (isRect) document.getElementById('dspCorner').value = obj.rx || 0;

    document.getElementById('dspTextSec').style.display = isText ? '' : 'none';
    if (isText) {
        setFontSelect(obj.fontFamily || 'Inter');
        document.getElementById('dspFontSize').value = obj.fontSize    || 18;
        document.getElementById('dspLineH').value    = obj.lineHeight  || 1.16;
        document.getElementById('dspCharSp').value   = obj.charSpacing || 0;
        document.getElementById('dspBold').classList.toggle('on',        obj.fontWeight==='bold');
        document.getElementById('dspItalic').classList.toggle('on',      obj.fontStyle ==='italic');
        document.getElementById('dspUnderline').classList.toggle('on',   !!obj.underline);
        document.getElementById('dspLinethrough').classList.toggle('on', !!obj.linethrough);
        var align = obj.textAlign || 'left';
        ['L','C','R'].forEach(function(a){ document.getElementById('dspAlign'+a).classList.remove('on'); });
        if (align==='left')   document.getElementById('dspAlignL').classList.add('on');
        if (align==='center') document.getElementById('dspAlignC').classList.add('on');
        if (align==='right')  document.getElementById('dspAlignR').classList.add('on');
    }
}
function setFontSelect(family) {
    var sel = document.getElementById('dspFontFam');
    for (var i=0; i<sel.options.length; i++) {
        if (sel.options[i].value===family) { sel.selectedIndex=i; return; }
    }
    var opt = document.createElement('option');
    opt.value = family; opt.textContent = family;
    sel.add(opt); sel.value = family;
}

/* ──────────────────────────────────────────────────────────────
   SET PROPERTIES FROM PANEL
   ────────────────────────────────────────────────────────────── */
function getActive()    { return canvas.getActiveObject(); }
function repaint()      { canvas.renderAll(); }
function repaintSave()  { canvas.renderAll(); saveHist(); }
function repaintSoon()  { canvas.renderAll(); saveHistSoon(); }

function setPropXY() {
    var o=getActive(); if (!o) return;
    o.set({ left: parseFloat(document.getElementById('dspX').value)||0,
             top:  parseFloat(document.getElementById('dspY').value)||0 });
    o.setCoords(); repaintSoon();
}
function setPropWH() {
    var o=getActive(); if (!o) return;
    var w=parseFloat(document.getElementById('dspW').value);
    var h=parseFloat(document.getElementById('dspH').value);
    if (w>0) o.set('scaleX', w/(o.width||1));
    if (h>0) o.set('scaleY', h/(o.height||1));
    o.setCoords(); repaintSoon();
}
function setPropRot() {
    var o=getActive(); if (!o) return;
    o.set({ angle: parseFloat(document.getElementById('dspRot').value)||0 });
    o.setCoords(); repaintSoon();
}
function setPropFill(val) {
    var o=getActive(); if (!o) return;
    if (/^#[0-9a-fA-F]{3,6}$/.test(val)) {
        o.set({ fill: val });
        document.getElementById('dspFillSwatch').style.background = val;
        document.getElementById('dspFillHex').value = val;
        if (/^#[0-9a-fA-F]{6}$/.test(val)) document.getElementById('dspFillPicker').value = val;
        repaintSave();
    }
}
function syncFillSwatch(val) {
    if (/^#[0-9a-fA-F]{3,6}$/.test(val))
        document.getElementById('dspFillSwatch').style.background = val;
}
function toggleNoFill(checked) {
    var o=getActive(); if (!o) return;
    o.set({ fill: checked ? '' : '#7c3aed' });
    document.getElementById('dspFillSwatch').style.background = checked ? '' : '#7c3aed';
    document.getElementById('dspFillHex').value = checked ? '' : '#7c3aed';
    repaintSave();
}
function setPropStroke(val) {
    var o=getActive(); if (!o) return;
    if (val===''||/^#[0-9a-fA-F]{3,6}$/.test(val)) {
        o.set({ stroke: val||null });
        document.getElementById('dspStrokeSwatch').style.background = val||'';
        repaintSave();
    }
}
function syncStrokeSwatch(val) {
    if (/^#[0-9a-fA-F]{3,6}$/.test(val))
        document.getElementById('dspStrokeSwatch').style.background = val;
}
function setPropStrokeWidth(val) { var o=getActive(); if(o){ o.set({strokeWidth:parseFloat(val)||0}); repaintSoon(); } }
function setPropOpacity(val) {
    var o=getActive(); if (!o) return;
    o.set({ opacity: parseFloat(val)/100 });
    document.getElementById('dspOpacityVal').textContent = val+'%';
    repaintSoon();
}
function setPropCorner(val) {
    var o=getActive(); if (!o||o.type!=='rect') return;
    o.set({rx:parseFloat(val)||0,ry:parseFloat(val)||0}); repaintSoon();
}
function setPropFontFamily(val){ var o=getActive(); if(o){ o.set({fontFamily:val}); repaintSave(); } }
function setPropFontSize(val)  { var o=getActive(); if(o){ o.set({fontSize:parseFloat(val)||18}); repaintSoon(); } }
function setPropAlign(val) {
    var o=getActive(); if (!o) return;
    o.set({textAlign:val});
    ['L','C','R'].forEach(function(a){ document.getElementById('dspAlign'+a).classList.remove('on'); });
    document.getElementById('dspAlign'+val[0].toUpperCase()).classList.add('on');
    repaintSave();
}
function togglePropBold() {
    var o=getActive(); if (!o) return;
    var bold=o.fontWeight==='bold';
    o.set({fontWeight:bold?'normal':'bold'});
    document.getElementById('dspBold').classList.toggle('on',!bold); repaintSave();
}
function togglePropItalic() {
    var o=getActive(); if (!o) return;
    var it=o.fontStyle==='italic';
    o.set({fontStyle:it?'normal':'italic'});
    document.getElementById('dspItalic').classList.toggle('on',!it); repaintSave();
}
function togglePropUnderline()   { var o=getActive(); if(!o) return; o.set({underline:!o.underline});     document.getElementById('dspUnderline').classList.toggle('on',!!o.underline);   repaintSave(); }
function togglePropLinethrough() { var o=getActive(); if(!o) return; o.set({linethrough:!o.linethrough}); document.getElementById('dspLinethrough').classList.toggle('on',!!o.linethrough); repaintSave(); }
function setPropLineH(val)       { var o=getActive(); if(o){ o.set({lineHeight:parseFloat(val)||1.16}); repaintSoon(); } }
function setPropCharSpacing(val) { var o=getActive(); if(o){ o.set({charSpacing:parseFloat(val)||0});    repaintSoon(); } }

/* ──────────────────────────────────────────────────────────────
   OBJECT ACTIONS
   ────────────────────────────────────────────────────────────── */
function edBringFwd()  { var o=getActive(); if(o){ canvas.bringForward(o);  repaintSave(); } }
function edSendBck()   { var o=getActive(); if(o){ canvas.sendBackwards(o); repaintSave(); } }
function edBringFront(){ var o=getActive(); if(o){ canvas.bringToFront(o);  repaintSave(); } }
function edSendBack()  { var o=getActive(); if(o){ canvas.sendToBack(o);    repaintSave(); } }
function edDelete() {
    var objs=canvas.getActiveObjects(); if (!objs.length) return;
    canvas.discardActiveObject();
    objs.forEach(function(o){ canvas.remove(o); });
    repaintSave(); updateLayers();
}
function edDuplicate() {
    var o=getActive(); if (!o) return;
    o.clone(function(cl){
        cl.set({ left:(o.left||0)+20, top:(o.top||0)+20 });
        canvas.add(cl); canvas.setActiveObject(cl);
        repaintSave(); updateLayers();
    });
}
function edFlipH() { var o=getActive(); if(o){ o.set({flipX:!o.flipX}); repaintSave(); } }
function edFlipV() { var o=getActive(); if(o){ o.set({flipY:!o.flipY}); repaintSave(); } }

function edAlignLeft()    { var o=getActive(); if(o){ o.set({left:0}); o.setCoords(); repaintSave(); } }
function edAlignCenter()  { var o=getActive(); if(o){ canvas.centerObjectH(o); o.setCoords(); repaintSave(); } }
function edAlignRight()   { var o=getActive(); if(o){ o.set({left:designW-o.getScaledWidth()}); o.setCoords(); repaintSave(); } }
function edAlignVCenter() { var o=getActive(); if(o){ canvas.centerObjectV(o); o.setCoords(); repaintSave(); } }

/* ──────────────────────────────────────────────────────────────
   ZOOM
   ────────────────────────────────────────────────────────────── */
function setZoom(level) {
    level = Math.max(0.1, Math.min(5, level));
    currentZoom = level;
    canvas.setZoom(level);
    canvas.setDimensions({ width: designW*level, height: designH*level });
    document.getElementById('dsZoomLbl').textContent = Math.round(level*100)+'%';
    requestAnimationFrame(function(){ canvas.calcOffset(); });
}
function edZoomIn()  { setZoom(currentZoom * 1.25); }
function edZoomOut() { setZoom(currentZoom / 1.25); }
function edZoomFit() {
    var area = document.getElementById('dsCanvasArea');
    setZoom(Math.min((area.clientWidth-56)/designW, (area.clientHeight-56)/designH, 1));
}

/* ──────────────────────────────────────────────────────────────
   LAYERS PANEL
   ────────────────────────────────────────────────────────────── */
function updateLayers() {
    var list = document.getElementById('dsLayersList');
    var objs = canvas.getObjects();
    if (!objs.length) {
        list.innerHTML = '<div class="dsl-layers-empty">No objects yet.</div>';
        return;
    }
    var active = canvas.getActiveObjects(), html = '';
    for (var i=objs.length-1; i>=0; i--) {
        var obj=objs[i], sel=active.indexOf(obj)>=0, vis=obj.visible!==false;
        html += '<div class="dsl-layer'+(sel?' sel':'')+'" data-idx="'+i+'" onclick="selectLayerObj('+i+')">'
              + '<span class="dsl-layer-ico"><i class="fa '+typeIcon(obj)+'"></i></span>'
              + '<span class="dsl-layer-name">'+escH(nameFor(obj))+'</span>'
              + '<button class="dsl-layer-vis" onclick="toggleLayerVis(event,'+i+')">'
              +   '<i class="fa '+(vis?'fa-eye':'fa-eye-slash')+'"></i>'
              + '</button></div>';
    }
    list.innerHTML = html;
}
function selectLayerObj(idx) {
    var obj=canvas.getObjects()[idx]; if (!obj) return;
    canvas.setActiveObject(obj); canvas.renderAll(); updateLayerHighlight();
    document.querySelectorAll('.dsr-tab').forEach(function(b){ b.classList.remove('on'); });
    document.querySelectorAll('.dsr-pane').forEach(function(p){ p.classList.remove('on'); });
    document.querySelector('[data-rtab="props"]').classList.add('on');
    document.getElementById('dsrp-props').classList.add('on');
}
function toggleLayerVis(e, idx) {
    e.stopPropagation();
    var obj=canvas.getObjects()[idx]; if (!obj) return;
    obj.set({ visible: obj.visible===false });
    canvas.renderAll(); updateLayers();
}
function updateLayerHighlight() {
    var active=canvas.getActiveObjects();
    document.querySelectorAll('.dsl-layer').forEach(function(el){
        var obj=canvas.getObjects()[parseInt(el.dataset.idx)];
        el.classList.toggle('sel', !!obj && active.indexOf(obj)>=0);
    });
}

/* ──────────────────────────────────────────────────────────────
   EXPORT
   ────────────────────────────────────────────────────────────── */
function edExport(fmt) {
    var savedZoom=currentZoom, savedW=canvas.width, savedH=canvas.height;
    canvas.discardActiveObject();
    canvas.setZoom(1);
    canvas.setDimensions({ width:designW, height:designH });
    canvas.renderAll();
    var dataUrl = fmt==='svg'
        ? 'data:image/svg+xml;charset=utf-8,'+encodeURIComponent(canvas.toSVG())
        : canvas.toDataURL({ format:fmt, quality:0.92, multiplier:1 });
    canvas.setZoom(savedZoom);
    canvas.setDimensions({ width:savedW, height:savedH });
    canvas.renderAll();
    requestAnimationFrame(function(){ canvas.calcOffset(); });
    var link = document.createElement('a');
    link.href     = dataUrl;
    link.download = (document.getElementById('dsTitleInput').value||'design')
                    + '.' + (fmt==='jpeg'?'jpg':fmt);
    link.click();
    showToast('Exported as '+fmt.toUpperCase(), 'ok');
}

/* ──────────────────────────────────────────────────────────────
   PDF EXPORT
   ────────────────────────────────────────────────────────────── */
function edExportPdf() {
    if (!window.jspdf) {
        showToast('PDF library not loaded — please refresh and try again.', 'err');
        return;
    }

    var title   = (document.getElementById('dsTitleInput').value || 'design').replace(/[/\\?%*:|"<>]/g, '-');
    var overlay = document.getElementById('dsPdfOverlay');
    var status  = document.getElementById('dsPdfStatus');
    var bar     = document.getElementById('dsPdfBar');
    var setBar  = function(pct){ bar.style.width = pct + '%'; };

    overlay.classList.add('on');
    status.textContent = 'Preparing…';
    setBar(0);

    /* Flush current page so all pages[] entries are up-to-date */
    flushCurrentPage();

    /* Snapshot canvas state */
    var savedZoom   = currentZoom;
    var savedW      = canvas.width;
    var savedH      = canvas.height;
    var savedPage   = currentPage;

    /* Switch to full 1:1 resolution for rendering */
    canvas.discardActiveObject();
    canvas.setZoom(1);
    canvas.setDimensions({ width: designW, height: designH });

    /* PDF page dimensions (16:9 landscape in mm) */
    var pdfW = 297;
    var pdfH = Math.round(pdfW * (designH / designW) * 100) / 100;

    var jsPDF    = window.jspdf.jsPDF;
    var doc      = new jsPDF({ orientation: 'landscape', unit: 'mm', format: [pdfW, pdfH] });
    var pagelist = isMultiPage ? pages : [{ json: null }];
    var total    = pagelist.length;
    var idx      = 0;

    function renderPage() {
        if (idx >= total) { finish(); return; }
        status.textContent = 'Rendering page ' + (idx + 1) + ' of ' + total + '…';
        setBar(Math.round((idx / total) * 80));

        var pageJson = (isMultiPage && pagelist[idx]) ? pagelist[idx].json : null;

        function capture() {
            requestAnimationFrame(function() {
                var imgData = canvas.toDataURL({ format: 'jpeg', quality: 0.92, multiplier: 1 });
                if (idx > 0) doc.addPage([pdfW, pdfH]);
                doc.addImage(imgData, 'JPEG', 0, 0, pdfW, pdfH);
                idx++;
                renderPage();
            });
        }

        if (pageJson) {
            canvas.loadFromJSON(pageJson, function() { canvas.renderAll(); capture(); });
        } else {
            canvas.renderAll();
            capture();
        }
    }

    function finish() {
        status.textContent = 'Saving PDF file…';
        setBar(92);

        /* Restore canvas to working state */
        canvas.setZoom(savedZoom);
        canvas.setDimensions({ width: savedW, height: savedH });

        var restoreAndClose = function() {
            canvas.renderAll();
            requestAnimationFrame(function() { canvas.calcOffset(); });
            setBar(100);
            doc.save(title + '.pdf');
            showToast('PDF exported — ' + total + (total === 1 ? ' page' : ' pages'), 'ok');
            setTimeout(function() { overlay.classList.remove('on'); setBar(0); }, 600);
        };

        if (isMultiPage && pages[savedPage] && pages[savedPage].json) {
            canvas.loadFromJSON(pages[savedPage].json, restoreAndClose);
        } else {
            restoreAndClose();
        }
    }

    /* Kick off the first page after a single rAF so the overlay renders */
    requestAnimationFrame(renderPage);
}

/* ──────────────────────────────────────────────────────────────
   SAVE
   ────────────────────────────────────────────────────────────── */
function edSave() {
    var btn = document.getElementById('dsSaveBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving…';

    fetch(designId ? '/design-studio/designs/'+designId : '/design-studio/designs', {
        method: designId ? 'PUT' : 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
        body: JSON.stringify({
            title:       document.getElementById('dsTitleInput').value || 'Untitled Design',
            type:        designType || null,
            width:       designW, height: designH,
            canvas_json: buildCanvasJson(),
        }),
    })
    .then(function(r){
        if (!r.ok) {
            return r.json().then(function(e){ throw new Error(e.message || 'Server error '+r.status); });
        }
        return r.json();
    })
    .then(function(data) {
        if (!data.id) { throw new Error('No ID in response'); }
        designId = data.id;
        if (designType && !isMultiPage) {
            showToast('Saved! Returning to Studio…', 'ok');
            setTimeout(function(){ window.location.href = '/design-studio'; }, 900);
        } else {
            window.history.replaceState({}, '', '/design-studio/editor/'+designId);
            showToast('Design saved!', 'ok');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-floppy-disk"></i> Save';
        }
    })
    .catch(function(err){
        showToast('Save failed: ' + (err.message || 'please try again.'), 'err');
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-floppy-disk"></i> Save';
    });
}

/* ──────────────────────────────────────────────────────────────
   KEYBOARD SHORTCUTS
   ────────────────────────────────────────────────────────────── */
function onKeyDown(e) {
    var tag=document.activeElement.tagName.toLowerCase();
    var inInput=(tag==='input'||tag==='textarea'||tag==='select');
    var obj=canvas.getActiveObject();
    if (obj && obj.isEditing) return;

    if ((e.ctrlKey||e.metaKey)&&e.key==='z'&&!e.shiftKey){ edUndo(); e.preventDefault(); return; }
    if ((e.ctrlKey||e.metaKey)&&(e.key==='y'||(e.key==='z'&&e.shiftKey))){ edRedo(); e.preventDefault(); return; }
    if ((e.ctrlKey||e.metaKey)&&e.key==='d'){ edDuplicate(); e.preventDefault(); return; }
    if ((e.ctrlKey||e.metaKey)&&e.key==='s'){ edSave(); e.preventDefault(); return; }
    if ((e.ctrlKey||e.metaKey)&&e.key==='a'){
        canvas.setActiveObject(new fabric.ActiveSelection(canvas.getObjects(),{canvas:canvas}));
        canvas.renderAll(); e.preventDefault(); return;
    }
    if (inInput) return;
    if (e.key==='Delete'||e.key==='Backspace'){ edDelete(); e.preventDefault(); return; }
    if (e.key==='Escape'){ canvas.discardActiveObject(); canvas.renderAll(); return; }
    if (e.key==='t'||e.key==='T'){ edAddText(24,'normal','Add text'); return; }
    if (e.key==='r'||e.key==='R'){ edAddRect(); return; }
    if (e.key==='c'||e.key==='C'){ edAddCircle(); return; }
    if (obj&&['ArrowUp','ArrowDown','ArrowLeft','ArrowRight'].includes(e.key)){
        var step=e.shiftKey?10:1;
        obj.set({ left:(obj.left||0)+(e.key==='ArrowRight'?step:e.key==='ArrowLeft'?-step:0),
                   top: (obj.top ||0)+(e.key==='ArrowDown' ?step:e.key==='ArrowUp'  ?-step:0) });
        obj.setCoords(); canvas.renderAll(); saveHist(); e.preventDefault();
    }
}

/* ──────────────────────────────────────────────────────────────
   TOAST + UTILITY
   ────────────────────────────────────────────────────────────── */
var toastTimer=null;
function showToast(msg, type) {
    var el=document.getElementById('dsToast');
    el.textContent=msg;
    el.className='ds-toast show'+(type?' '+type:'');
    clearTimeout(toastTimer);
    toastTimer=setTimeout(function(){ el.className='ds-toast'; }, 2600);
}
function escH(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

window.addEventListener('resize', function(){
    edZoomFit();
    requestAnimationFrame(function(){ canvas.calcOffset(); });
});
</script>

@endsection
