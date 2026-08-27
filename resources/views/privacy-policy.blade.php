<!DOCTYPE html>
<html lang="en" data-theme="">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Privacy Policy — {{ config('app.name') }}</title>
<meta name="description" content="Privacy Policy for {{ config('app.name') }} — how we collect, use, and protect your information.">
<style>
/* ── Tokens ── */
:root {
  --bg:           #FFFFFF;
  --bg-subtle:    #F4F4F4;
  --sidebar-bg:   #FFFFFF;
  --sidebar-hover:#F4F4F4;
  --text:         #111111;
  --text-muted:   #555555;
  --text-faint:   #999999;
  --border:       #E6E6E6;
  --border-strong:#CCCCCC;
  --accent:       #D4A017;
  --accent-bright:#F5C518;
  --accent-light: #FEFCE8;
  --tip-bg:       #FEFCE8;
  --tip-border:   #E8D040;
  --note-bg:      #F4F4F4;
  --note-border:  #AAAAAA;
  --sidebar-text:        #555555;
  --sidebar-text-active: #111111;
  --sidebar-scrollbar:   #CCCCCC;
  --shadow: 0 1px 3px rgba(0,0,0,.07), 0 1px 2px rgba(0,0,0,.04);
}
@media (prefers-color-scheme: dark) {
  :root {
    --bg:           #111111;
    --bg-subtle:    #1A1A1A;
    --sidebar-bg:   #0A0A0A;
    --sidebar-hover:#181818;
    --text:         #F0F0F0;
    --text-muted:   #AAAAAA;
    --text-faint:   #666666;
    --border:       #272727;
    --border-strong:#383838;
    --accent:       #F5C518;
    --accent-bright:#F5C518;
    --accent-light: #1C1800;
    --tip-bg:       #1C1800;
    --tip-border:   #705900;
    --note-bg:      #1A1A1A;
    --note-border:  #444444;
    --sidebar-text:        #666666;
    --sidebar-text-active: #F0F0F0;
    --sidebar-scrollbar:   #333333;
    --shadow: 0 1px 3px rgba(0,0,0,.4);
  }
}
:root[data-theme="dark"] {
  --bg:           #111111;
  --bg-subtle:    #1A1A1A;
  --sidebar-bg:   #0A0A0A;
  --sidebar-hover:#181818;
  --text:         #F0F0F0;
  --text-muted:   #AAAAAA;
  --text-faint:   #666666;
  --border:       #272727;
  --border-strong:#383838;
  --accent:       #F5C518;
  --accent-bright:#F5C518;
  --accent-light: #1C1800;
  --tip-bg:       #1C1800;
  --tip-border:   #705900;
  --note-bg:      #1A1A1A;
  --note-border:  #444444;
  --sidebar-text:        #666666;
  --sidebar-text-active: #F0F0F0;
  --sidebar-scrollbar:   #333333;
  --shadow: 0 1px 3px rgba(0,0,0,.4);
}
:root[data-theme="light"] {
  --bg:           #FFFFFF;
  --bg-subtle:    #F4F4F4;
  --sidebar-bg:   #FFFFFF;
  --sidebar-hover:#F4F4F4;
  --text:         #111111;
  --text-muted:   #555555;
  --text-faint:   #999999;
  --border:       #E6E6E6;
  --border-strong:#CCCCCC;
  --accent:       #D4A017;
  --accent-bright:#F5C518;
  --accent-light: #FEFCE8;
  --tip-bg:       #FEFCE8;
  --tip-border:   #E8D040;
  --note-bg:      #F4F4F4;
  --note-border:  #AAAAAA;
  --sidebar-text:        #555555;
  --sidebar-text-active: #111111;
  --sidebar-scrollbar:   #CCCCCC;
  --shadow: 0 1px 3px rgba(0,0,0,.07);
}

/* ── Reset ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { font-size: 15px; scroll-behavior: smooth; }
body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
  background: var(--bg);
  color: var(--text);
  line-height: 1.65;
  display: flex;
  min-height: 100vh;
}

/* ── Sidebar ── */
#sidebar {
  width: 258px;
  min-width: 258px;
  background: var(--sidebar-bg);
  position: fixed;
  top: 0; left: 0; bottom: 0;
  overflow-y: auto;
  z-index: 100;
  display: flex;
  flex-direction: column;
  border-right: 1px solid var(--border);
}
#sidebar::-webkit-scrollbar { width: 4px; }
#sidebar::-webkit-scrollbar-track { background: transparent; }
#sidebar::-webkit-scrollbar-thumb { background: var(--sidebar-scrollbar); border-radius: 2px; }

.sb-brand {
  padding: 18px 20px 14px;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  gap: 10px;
  text-decoration: none;
  color: var(--text);
}
.sb-brand-mark {
  width: 30px; height: 30px;
  border-radius: 7px;
  background: var(--accent-bright);
  color: #111;
  font-size: 13px;
  font-weight: 800;
  display: grid;
  place-items: center;
  flex-shrink: 0;
}
.sb-brand-name {
  font-size: 13.5px;
  font-weight: 700;
  color: var(--text);
  letter-spacing: -.01em;
}
.sb-brand-sub {
  font-size: 10px;
  color: var(--text-faint);
  font-family: ui-monospace, monospace;
  letter-spacing: .04em;
  margin-top: 1px;
}

.sb-nav { padding: 8px 0 24px; flex: 1; }

.sb-section-label {
  padding: 12px 16px 4px;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: .09em;
  text-transform: uppercase;
  color: var(--text-faint);
}

/* Top-level link (Overview) */
.sb-link {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 7px 16px;
  color: var(--sidebar-text);
  text-decoration: none;
  font-size: 13px;
  transition: background .12s, color .12s;
  cursor: pointer;
  border-left: 2px solid transparent;
}
.sb-link:hover { background: var(--sidebar-hover); color: var(--text); }
.sb-link.active {
  color: var(--text);
  font-weight: 600;
  border-left-color: var(--accent-bright);
  background: var(--accent-light);
}
.sb-link svg { width: 14px; height: 14px; flex-shrink: 0; color: var(--text-faint); }
.sb-link.active svg { color: var(--accent); }

/* ── Group (collapsible) ── */
.sb-group { }
.sb-group-hdr {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 7px 14px 7px 16px;
  cursor: pointer;
  user-select: none;
  gap: 8px;
  border-left: 2px solid transparent;
  transition: background .12s, color .12s;
}
.sb-group-hdr:hover { background: var(--sidebar-hover); }
.sb-group-hdr-left {
  display: flex;
  align-items: center;
  gap: 9px;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .07em;
  color: var(--text-faint);
}
.sb-group-hdr-left svg { width: 13px; height: 13px; color: var(--text-faint); }
/* Chevron */
.sb-chevron {
  width: 14px; height: 14px;
  color: var(--text-faint);
  transition: transform .2s ease;
  flex-shrink: 0;
}
.sb-group.open .sb-chevron { transform: rotate(90deg); }
/* active group header tint */
.sb-group.has-active .sb-group-hdr {
  color: var(--accent);
  border-left-color: color-mix(in srgb, var(--accent-bright) 40%, transparent);
}
.sb-group.has-active .sb-group-hdr-left { color: var(--accent); }
.sb-group.has-active .sb-group-hdr-left svg { color: var(--accent); }

/* Sub-links */
.sb-children {
  overflow: hidden;
  max-height: 0;
  transition: max-height .22s ease;
}
.sb-group.open .sb-children { max-height: 400px; }

.sb-sub {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 14px 6px 34px;
  color: var(--sidebar-text);
  text-decoration: none;
  font-size: 12.5px;
  transition: background .12s, color .12s;
  border-left: 2px solid transparent;
  position: relative;
}
.sb-sub::before {
  content: '';
  position: absolute;
  left: 24px;
  top: 50%; transform: translateY(-50%);
  width: 4px; height: 4px;
  border-radius: 50%;
  background: var(--border-strong);
  transition: background .15s;
}
.sb-sub:hover { background: var(--sidebar-hover); color: var(--text); }
.sb-sub:hover::before { background: var(--accent-bright); }
.sb-sub.active {
  color: var(--text);
  font-weight: 600;
  border-left-color: var(--accent-bright);
  background: var(--accent-light);
}
.sb-sub.active::before { background: var(--accent-bright); }
.sb-sub svg { width: 13px; height: 13px; flex-shrink: 0; color: var(--text-faint); }
.sb-sub.active svg { color: var(--accent); }

.sb-divider { height: 1px; background: var(--border); margin: 8px 0; }

.sb-back {
  display: flex;
  align-items: center;
  gap: 7px;
  padding: 8px 16px;
  font-size: 12px;
  color: var(--text-faint);
  text-decoration: none;
  transition: color .12s;
}
.sb-back:hover { color: var(--text); }

/* ── Main ── */
#main { margin-left: 258px; flex: 1; min-width: 0; }

/* ── Topbar ── */
#topbar {
  position: sticky;
  top: 0; z-index: 50;
  background: var(--bg);
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 40px;
  height: 48px;
}
.tb-title {
  font-size: 12px;
  color: var(--text-muted);
  font-family: ui-monospace, monospace;
  letter-spacing: .03em;
}
.tb-title span { color: var(--text-faint); margin: 0 4px; }
.tb-actions { display: flex; gap: 8px; align-items: center; }
.theme-toggle {
  background: none; border: 1px solid var(--border);
  color: var(--text-muted);
  padding: 4px 10px;
  border-radius: 4px;
  font-size: 11px;
  cursor: pointer;
  transition: border-color .15s, color .15s;
  font-family: inherit;
}
.theme-toggle:hover { border-color: var(--accent); color: var(--accent); }
.version-badge {
  background: var(--accent);
  color: #fff;
  font-size: 10px;
  font-weight: 600;
  padding: 3px 8px;
  border-radius: 3px;
  font-family: ui-monospace, monospace;
  letter-spacing: .04em;
}

/* ── Content ── */
#content {
  max-width: 760px;
  margin: 0 auto;
  padding: 0 40px 80px;
}

/* ── Hero ── */
.hero {
  padding: 52px 0 40px;
  border-bottom: 1px solid var(--border);
  margin-bottom: 56px;
}
.hero-eyebrow {
  font-size: 11px;
  font-weight: 600;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: var(--accent);
  font-family: ui-monospace, monospace;
  margin-bottom: 12px;
}
.hero h1 {
  font-size: 34px;
  font-weight: 800;
  letter-spacing: -.03em;
  color: var(--text);
  line-height: 1.15;
  text-wrap: balance;
  margin-bottom: 14px;
}
.hero p {
  font-size: 15.5px;
  color: var(--text-muted);
  max-width: 560px;
  line-height: 1.7;
}
.hero-meta {
  margin-top: 20px;
  display: flex;
  gap: 20px;
  flex-wrap: wrap;
}
.hero-meta-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--text-faint);
}
.hero-meta-item strong { color: var(--text-muted); font-weight: 500; }

/* ── TOC ── */
.toc {
  background: var(--bg-subtle);
  border: 1px solid var(--border);
  border-radius: 6px;
  padding: 20px 24px;
  margin-bottom: 56px;
}
.toc-title {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: var(--text-faint);
  margin-bottom: 14px;
}
.toc-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 4px 20px;
}
.toc-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 5px 0;
  text-decoration: none;
  color: var(--text-muted);
  font-size: 13px;
  transition: color .12s;
}
.toc-item:hover { color: var(--accent); }
.toc-num {
  font-family: ui-monospace, monospace;
  font-size: 10px;
  color: var(--text-faint);
  min-width: 26px;
}

/* ── Chapter ── */
.chapter {
  margin-bottom: 64px;
  scroll-margin-top: 64px;
}
.chapter-header {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  padding-bottom: 20px;
  border-bottom: 2px solid var(--border);
  margin-bottom: 32px;
}
.chapter-num {
  font-family: ui-monospace, monospace;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: .06em;
  color: var(--text-faint);
  padding-top: 4px;
  white-space: nowrap;
}
.chapter-meta { flex: 1; }
.chapter-icon {
  width: 38px; height: 38px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  flex-shrink: 0;
  margin-top: 2px;
}
.chapter-icon svg { width: 20px; height: 20px; }
.chapter h2 {
  font-size: 22px;
  font-weight: 800;
  letter-spacing: -.025em;
  color: var(--text);
  text-wrap: balance;
  line-height: 1.25;
}
.chapter-desc {
  font-size: 13.5px;
  color: var(--text-muted);
  margin-top: 5px;
  line-height: 1.6;
}

/* ── Section ── */
.section { margin-bottom: 36px; scroll-margin-top: 64px; }
.section h3 {
  font-size: 15.5px;
  font-weight: 700;
  letter-spacing: -.015em;
  color: var(--text);
  margin-bottom: 14px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.section h3::before {
  content: '';
  display: block;
  width: 3px; height: 16px;
  border-radius: 2px;
  background: var(--accent-bright);
  opacity: .7;
  flex-shrink: 0;
}
.section p {
  font-size: 14px;
  color: var(--text-muted);
  margin-bottom: 14px;
  line-height: 1.7;
}
.section p:last-child { margin-bottom: 0; }
.section ul, .section ol {
  padding-left: 20px;
  margin-bottom: 14px;
}
.section li {
  font-size: 14px;
  color: var(--text-muted);
  margin-bottom: 7px;
  line-height: 1.6;
}
.section li strong { color: var(--text); }
.section a { color: var(--accent); text-decoration: underline; }
.section a:hover { color: var(--accent-bright); }

/* ── Callouts ── */
.callout {
  border-radius: 5px;
  padding: 13px 16px;
  margin: 16px 0;
  font-size: 13.5px;
  line-height: 1.6;
  display: flex;
  gap: 10px;
}
.callout-icon { flex-shrink: 0; margin-top: 1px; display: flex; align-items: center; }
.callout-icon svg { width: 16px; height: 16px; }
.callout-body { flex: 1; color: var(--text-muted); }
.callout-body strong { display: block; font-size: 11px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; margin-bottom: 3px; }
.callout.tip { background: var(--tip-bg); border-left: 3px solid var(--accent-bright); }
.callout.tip .callout-icon { color: var(--accent); }
.callout.tip strong { color: var(--accent); }
.callout.note { background: var(--note-bg); border-left: 3px solid var(--note-border); }
.callout.note .callout-icon { color: var(--text-faint); }
.callout.note strong { color: var(--text-muted); }

/* ── Info grid ── */
.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px,1fr));
  gap: 10px;
  margin: 16px 0;
}
.info-card {
  background: var(--bg-subtle);
  border: 1px solid var(--border);
  border-radius: 6px;
  padding: 12px 14px;
}
.info-card-label {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .07em;
  color: var(--text-faint);
  margin-bottom: 4px;
}
.info-card-val {
  font-size: 13.5px;
  font-weight: 700;
  color: var(--text);
}

/* ── Contact row ── */
.contact-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  background: var(--bg-subtle);
  border: 1px solid var(--border);
  border-radius: 6px;
  margin-top: 10px;
}
.contact-row svg { width: 16px; height: 16px; color: var(--accent); flex-shrink: 0; }
.contact-label { font-size: 11px; color: var(--text-faint); }
.contact-val { font-size: 13.5px; font-weight: 600; color: var(--text); }
.contact-val a { color: var(--accent); text-decoration: none; }
.contact-val a:hover { text-decoration: underline; }

/* ── Footer ── */
.pp-footer {
  text-align: center;
  padding: 24px 40px;
  font-size: 12px;
  color: var(--text-faint);
  border-top: 1px solid var(--border);
  margin-top: 24px;
}
.pp-footer a { color: var(--text-faint); text-decoration: none; }
.pp-footer a:hover { color: var(--accent); }

/* ── Mobile ── */
@media (max-width: 720px) {
  #sidebar { display: none; }
  #main { margin-left: 0; }
  #topbar { padding: 0 16px; }
  #content { padding: 0 16px 60px; }
}
</style>
</head>
<body>

<!-- ── Sidebar ── -->
<nav id="sidebar">
  <a href="{{ url('/') }}" class="sb-brand">
    <span class="sb-brand-mark">Z</span>
    <div>
      <div class="sb-brand-name">{{ config('app.name') }}</div>
      <div class="sb-brand-sub">Privacy Policy</div>
    </div>
  </a>

  <div class="sb-nav">
    <div class="sb-section-label">Contents</div>

    <!-- Overview (top-level) -->
    <a class="sb-link active" href="#overview" data-section="overview">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      Overview
    </a>

    <!-- Group 1: Your Data -->
    <div class="sb-group open" id="grp-data">
      <div class="sb-group-hdr" onclick="toggleGroup('grp-data')">
        <div class="sb-group-hdr-left">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
          Your Data
        </div>
        <svg class="sb-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
      </div>
      <div class="sb-children">
        <a class="sb-sub" href="#data-collected" data-section="data-collected">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
          Data We Collect
        </a>
        <a class="sb-sub" href="#how-we-use" data-section="how-we-use">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
          How We Use It
        </a>
        <a class="sb-sub" href="#data-sharing" data-section="data-sharing">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
          Data Sharing
        </a>
      </div>
    </div>

    <!-- Group 2: Authentication -->
    <div class="sb-group open" id="grp-auth">
      <div class="sb-group-hdr" onclick="toggleGroup('grp-auth')">
        <div class="sb-group-hdr-left">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          Authentication
        </div>
        <svg class="sb-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
      </div>
      <div class="sb-children">
        <a class="sb-sub" href="#google-oauth" data-section="google-oauth">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/><path d="M17.5 12H12V8"/></svg>
          Google Sign-In
        </a>
        <a class="sb-sub" href="#cookies" data-section="cookies">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5"/><path d="M8.5 8.5v.01"/><path d="M16 15.5v.01"/><path d="M12 12v.01"/></svg>
          Cookies &amp; Sessions
        </a>
      </div>
    </div>

    <!-- Group 3: Privacy Rights -->
    <div class="sb-group open" id="grp-rights">
      <div class="sb-group-hdr" onclick="toggleGroup('grp-rights')">
        <div class="sb-group-hdr-left">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          Privacy Rights
        </div>
        <svg class="sb-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
      </div>
      <div class="sb-children">
        <a class="sb-sub" href="#data-retention" data-section="data-retention">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          Data Retention
        </a>
        <a class="sb-sub" href="#security" data-section="security">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          Security
        </a>
        <a class="sb-sub" href="#your-rights" data-section="your-rights">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          Your Rights
        </a>
      </div>
    </div>

    <!-- Group 4: Additional -->
    <div class="sb-group open" id="grp-extra">
      <div class="sb-group-hdr" onclick="toggleGroup('grp-extra')">
        <div class="sb-group-hdr-left">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
          Additional
        </div>
        <svg class="sb-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
      </div>
      <div class="sb-children">
        <a class="sb-sub" href="#children" data-section="children">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          Children's Privacy
        </a>
        <a class="sb-sub" href="#changes" data-section="changes">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          Policy Changes
        </a>
        <a class="sb-sub" href="#contact" data-section="contact">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
          Contact Us
        </a>
      </div>
    </div>

    <div class="sb-divider"></div>
    <a href="{{ url('/') }}" class="sb-back">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
      Back to {{ config('app.name') }}
    </a>
  </div>
</nav>

<!-- ── Main ── -->
<div id="main">

  <!-- Topbar -->
  <div id="topbar">
    <div class="tb-title">
      {{ config('app.name') }}<span>/</span>Privacy Policy
    </div>
    <div class="tb-actions">
      <span class="version-badge">{{ now()->format('M Y') }}</span>
      <button class="theme-toggle" id="themeToggle">Dark</button>
    </div>
  </div>

  <!-- Content -->
  <div id="content">

    <!-- Hero -->
    <div class="hero" id="overview">
      <div class="hero-eyebrow">Legal · Privacy</div>
      <h1>Privacy Policy</h1>
      <p>We keep your data safe, use it only to run the platform, and never sell it to anyone. Here is exactly how we handle your information.</p>
      <div class="hero-meta">
        <div class="hero-meta-item">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          <span>Last updated: <strong>{{ now()->format('d F Y') }}</strong></span>
        </div>
        <div class="hero-meta-item">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
          <span>Jurisdiction: <strong>Sri Lanka</strong></span>
        </div>
        <div class="hero-meta-item">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <span>Applies to: <strong>all users</strong></span>
        </div>
      </div>
    </div>

    <!-- TOC -->
    <div class="toc">
      <div class="toc-title">On this page</div>
      <div class="toc-grid">
        <a class="toc-item" href="#data-collected"><span class="toc-num">01</span> Data We Collect</a>
        <a class="toc-item" href="#how-we-use"><span class="toc-num">02</span> How We Use It</a>
        <a class="toc-item" href="#data-sharing"><span class="toc-num">03</span> Data Sharing</a>
        <a class="toc-item" href="#google-oauth"><span class="toc-num">04</span> Google Sign-In</a>
        <a class="toc-item" href="#cookies"><span class="toc-num">05</span> Cookies</a>
        <a class="toc-item" href="#data-retention"><span class="toc-num">06</span> Data Retention</a>
        <a class="toc-item" href="#security"><span class="toc-num">07</span> Security</a>
        <a class="toc-item" href="#your-rights"><span class="toc-num">08</span> Your Rights</a>
        <a class="toc-item" href="#children"><span class="toc-num">09</span> Children</a>
        <a class="toc-item" href="#changes"><span class="toc-num">10</span> Policy Changes</a>
        <a class="toc-item" href="#contact"><span class="toc-num">11</span> Contact Us</a>
      </div>
    </div>

    <!-- 01 · Data We Collect -->
    <div class="chapter" id="data-collected">
      <div class="chapter-header">
        <div class="chapter-num">01</div>
        <div class="chapter-icon" style="background:color-mix(in srgb,var(--accent-bright) 14%,transparent)">
          <svg viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
        </div>
        <div class="chapter-meta">
          <h2>Data We Collect</h2>
          <p class="chapter-desc">We only collect information necessary to provide and improve the service.</p>
        </div>
      </div>
      <div class="section">
        <h3>Account information</h3>
        <p>When you register directly, we collect your <strong>name</strong>, <strong>email address</strong>, and a <strong>bcrypt-hashed password</strong>. We never store or transmit plain-text passwords.</p>
      </div>
      <div class="section">
        <h3>Google profile data</h3>
        <p>When you sign in with Google, we receive your <strong>name</strong>, <strong>email address</strong>, and <strong>Google account ID</strong>. We never access your Google Drive, Gmail, or other Google services beyond what is needed for authentication.</p>
      </div>
      <div class="section">
        <h3>Business &amp; operational data</h3>
        <p>Data you enter into the platform: business names, branches, products, customers, sales records, inventory, accounts, payroll context, employee details, and HR records. This data belongs to you and your organisation.</p>
      </div>
      <div class="section">
        <h3>Technical &amp; usage data</h3>
        <ul>
          <li><strong>Activity logs</strong> — login, registration, and key platform actions for security and auditing.</li>
          <li><strong>IP address &amp; browser info</strong> — collected automatically when you use the web service.</li>
          <li><strong>Desktop app config</strong> — API URL and authentication tokens stored locally on your device in a system-protected config directory, never on our servers.</li>
        </ul>
      </div>
    </div>

    <!-- 02 · How We Use It -->
    <div class="chapter" id="how-we-use">
      <div class="chapter-header">
        <div class="chapter-num">02</div>
        <div class="chapter-icon" style="background:color-mix(in srgb,var(--accent-bright) 14%,transparent)">
          <svg viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        </div>
        <div class="chapter-meta">
          <h2>How We Use Your Data</h2>
          <p class="chapter-desc">Your information is used solely to operate the platform — nothing else.</p>
        </div>
      </div>
      <div class="section">
        <ul>
          <li>Create and manage your account and authenticate your identity.</li>
          <li>Provide platform features — POS, accounts, HR, CRM, inventory, and reporting.</li>
          <li>Send transactional communications such as password resets or important service notices.</li>
          <li>Detect and prevent fraud, abuse, and security incidents.</li>
          <li>Maintain audit trails required for business and compliance purposes.</li>
          <li>Improve the platform through anonymous, aggregated usage analytics.</li>
          <li>Deliver desktop app updates and notify you of new versions.</li>
        </ul>
        <div class="callout tip">
          <div class="callout-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          </div>
          <div class="callout-body">
            <strong>Our commitment</strong>
            We do not use your data for advertising, profiling, or any purpose beyond operating the platform.
          </div>
        </div>
      </div>
    </div>

    <!-- 03 · Data Sharing -->
    <div class="chapter" id="data-sharing">
      <div class="chapter-header">
        <div class="chapter-num">03</div>
        <div class="chapter-icon" style="background:color-mix(in srgb,var(--accent-bright) 14%,transparent)">
          <svg viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
        </div>
        <div class="chapter-meta">
          <h2>Data Sharing</h2>
          <p class="chapter-desc">We do not sell, rent, or trade your personal information — ever.</p>
        </div>
      </div>
      <div class="section">
        <ul>
          <li><strong>Within your organisation:</strong> users with admin or HR roles in your workspace can see the data they have been granted access to.</li>
          <li><strong>Infrastructure providers:</strong> our hosting and database providers process data strictly on our behalf under data processing agreements.</li>
          <li><strong>Google (OAuth only):</strong> when you sign in with Google, Google shares your basic profile with us. We do not share your data back with Google beyond what the OAuth handshake requires.</li>
          <li><strong>Legal obligations:</strong> if required by law, court order, or to protect user safety, we may disclose data to relevant authorities.</li>
        </ul>
      </div>
    </div>

    <!-- 04 · Google Sign-In -->
    <div class="chapter" id="google-oauth">
      <div class="chapter-header">
        <div class="chapter-num">04</div>
        <div class="chapter-icon" style="background:#F4F4F4">
          <svg viewBox="0 0 24 24" width="20" height="20"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
        </div>
        <div class="chapter-meta">
          <h2>Google Sign-In</h2>
          <p class="chapter-desc">What we receive from Google and how you can revoke access.</p>
        </div>
      </div>
      <div class="section">
        <p>When you sign in with Google, we receive only your <strong>name</strong>, <strong>email address</strong>, and a unique <strong>Google account identifier</strong>. We do not access your Google Drive, Gmail, contacts, calendar, or any other Google service.</p>
        <p>The Google OAuth consent screen will clearly show exactly which permissions are requested before you authorise sign-in.</p>
        <p>To unlink your Google account, visit <a href="https://myaccount.google.com/permissions" target="_blank" rel="noopener">myaccount.google.com/permissions</a>. Your {{ config('app.name') }} account and data will remain unless you also request deletion.</p>
      </div>
    </div>

    <!-- 05 · Cookies -->
    <div class="chapter" id="cookies">
      <div class="chapter-header">
        <div class="chapter-num">05</div>
        <div class="chapter-icon" style="background:color-mix(in srgb,var(--accent-bright) 14%,transparent)">
          <svg viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5"/><path d="M8.5 8.5v.01"/><path d="M16 15.5v.01"/><path d="M12 12v.01"/></svg>
        </div>
        <div class="chapter-meta">
          <h2>Cookies &amp; Sessions</h2>
          <p class="chapter-desc">We use only essential cookies — no advertising or tracking.</p>
        </div>
      </div>
      <div class="section">
        <ul>
          <li><strong>Session cookie:</strong> keeps you signed in during your session. Stored in our database and linked to your account.</li>
          <li><strong>CSRF token:</strong> a security token that prevents cross-site request forgery attacks on form submissions.</li>
          <li><strong>Desktop app:</strong> authentication tokens are stored locally on your device in a system-protected config directory, not in a browser cookie.</li>
        </ul>
        <div class="callout note">
          <div class="callout-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          </div>
          <div class="callout-body">
            <strong>No tracking</strong>
            We do not use third-party analytics cookies, advertising networks, or pixel trackers of any kind.
          </div>
        </div>
      </div>
    </div>

    <!-- 06 · Data Retention -->
    <div class="chapter" id="data-retention">
      <div class="chapter-header">
        <div class="chapter-num">06</div>
        <div class="chapter-icon" style="background:color-mix(in srgb,var(--accent-bright) 14%,transparent)">
          <svg viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="chapter-meta">
          <h2>Data Retention</h2>
          <p class="chapter-desc">How long we keep your data and what happens when you delete your account.</p>
        </div>
      </div>
      <div class="section">
        <div class="info-grid">
          <div class="info-card">
            <div class="info-card-label">Profile &amp; credentials</div>
            <div class="info-card-val">Deleted within 30 days</div>
          </div>
          <div class="info-card">
            <div class="info-card-label">Business data</div>
            <div class="info-card-val">Up to 90 days</div>
          </div>
          <div class="info-card">
            <div class="info-card-label">Activity logs</div>
            <div class="info-card-val">Up to 12 months</div>
          </div>
        </div>
        <p style="margin-top:16px">You may request deletion at any time by contacting us at the address below. Business data may be retained for the 90-day window for accounting and legal compliance before permanent deletion.</p>
      </div>
    </div>

    <!-- 07 · Security -->
    <div class="chapter" id="security">
      <div class="chapter-header">
        <div class="chapter-num">07</div>
        <div class="chapter-icon" style="background:color-mix(in srgb,var(--accent-bright) 14%,transparent)">
          <svg viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        </div>
        <div class="chapter-meta">
          <h2>Security</h2>
          <p class="chapter-desc">Industry-standard measures to keep your data protected.</p>
        </div>
      </div>
      <div class="section">
        <ul>
          <li><strong>Bcrypt hashing:</strong> passwords are never stored or transmitted in plain text.</li>
          <li><strong>HTTPS/TLS:</strong> all communication between your browser or desktop app and our servers is encrypted in transit.</li>
          <li><strong>Token expiry:</strong> desktop app authentication tokens expire automatically and are stored only in a system-protected config directory on your device.</li>
          <li><strong>Role-based access:</strong> users only see data their assigned role permits.</li>
          <li><strong>Activity logs:</strong> login and key events are logged for security monitoring.</li>
        </ul>
        <p>If you discover a security vulnerability, please contact us immediately at <a href="mailto:support@zeebroo.com">support@zeebroo.com</a> so we can address it promptly.</p>
      </div>
    </div>

    <!-- 08 · Your Rights -->
    <div class="chapter" id="your-rights">
      <div class="chapter-header">
        <div class="chapter-num">08</div>
        <div class="chapter-icon" style="background:color-mix(in srgb,var(--accent-bright) 14%,transparent)">
          <svg viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </div>
        <div class="chapter-meta">
          <h2>Your Rights</h2>
          <p class="chapter-desc">You are in control of your personal data. We will respond within 14 business days.</p>
        </div>
      </div>
      <div class="section">
        <ul>
          <li><strong>Access:</strong> request a copy of the personal data we hold about you.</li>
          <li><strong>Correction:</strong> ask us to correct inaccurate or incomplete data.</li>
          <li><strong>Deletion:</strong> request that your account and personal data be deleted.</li>
          <li><strong>Portability:</strong> request your data in a machine-readable format.</li>
          <li><strong>Objection:</strong> object to processing your data in certain circumstances.</li>
          <li><strong>Withdraw consent:</strong> if processing is based on consent, you may withdraw it at any time without affecting prior lawful processing.</li>
        </ul>
      </div>
    </div>

    <!-- 09 · Children -->
    <div class="chapter" id="children">
      <div class="chapter-header">
        <div class="chapter-num">09</div>
        <div class="chapter-icon" style="background:color-mix(in srgb,var(--accent-bright) 14%,transparent)">
          <svg viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div class="chapter-meta">
          <h2>Children's Privacy</h2>
          <p class="chapter-desc">Our platform is intended for adults and organisations only.</p>
        </div>
      </div>
      <div class="section">
        <p>{{ config('app.name') }} is a business management platform intended for use by adults and organisations. We do not knowingly collect personal data from anyone under the age of <strong>16</strong>. If you believe a child has provided us with personal information, please contact us and we will promptly delete it.</p>
      </div>
    </div>

    <!-- 10 · Policy Changes -->
    <div class="chapter" id="changes">
      <div class="chapter-header">
        <div class="chapter-num">10</div>
        <div class="chapter-icon" style="background:color-mix(in srgb,var(--accent-bright) 14%,transparent)">
          <svg viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        </div>
        <div class="chapter-meta">
          <h2>Policy Changes</h2>
          <p class="chapter-desc">We will notify you before any significant changes take effect.</p>
        </div>
      </div>
      <div class="section">
        <p>We may update this Privacy Policy from time to time. When we do, we will revise the "Last updated" date at the top of the page. For significant changes that affect how we handle your data, we will notify registered users by email or a prominent notice in the platform at least <strong>14 days</strong> before the change takes effect.</p>
        <p>Continued use of the platform after a policy update constitutes acceptance of the revised terms.</p>
      </div>
    </div>

    <!-- 11 · Contact -->
    <div class="chapter" id="contact">
      <div class="chapter-header">
        <div class="chapter-num">11</div>
        <div class="chapter-icon" style="background:color-mix(in srgb,var(--accent-bright) 14%,transparent)">
          <svg viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        </div>
        <div class="chapter-meta">
          <h2>Contact Us</h2>
          <p class="chapter-desc">Questions, requests, or concerns — we respond within 14 business days.</p>
        </div>
      </div>
      <div class="section">
        <div class="contact-row">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          <div>
            <div class="contact-label">Email</div>
            <div class="contact-val"><a href="mailto:support@zeebroo.com">support@zeebroo.com</a></div>
          </div>
        </div>
        <div class="contact-row">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
          <div>
            <div class="contact-label">Website</div>
            <div class="contact-val"><a href="https://zeebroo.com" target="_blank" rel="noopener">zeebroo.com</a></div>
          </div>
        </div>
      </div>
    </div>

    <footer class="pp-footer">
      &copy; {{ now()->year }} {{ config('app.name') }}. All rights reserved.
      &nbsp;·&nbsp; <a href="{{ url('/') }}">Home</a>
      &nbsp;·&nbsp; <a href="{{ route('privacy-policy') }}">Privacy Policy</a>
    </footer>

  </div><!-- /#content -->
</div><!-- /#main -->

<script>
// ── Theme toggle ──────────────────────────────────────────────────────────────
(function () {
  var btn = document.getElementById('themeToggle');
  var root = document.documentElement;
  try {
    var stored = localStorage.getItem('pp-theme') || '';
    if (stored) { root.setAttribute('data-theme', stored); btn.textContent = stored === 'dark' ? 'Light' : 'Dark'; }
  } catch (_) {}
  btn.addEventListener('click', function () {
    var cur = root.getAttribute('data-theme');
    var next = cur === 'dark' ? 'light' : 'dark';
    root.setAttribute('data-theme', next);
    btn.textContent = next === 'dark' ? 'Light' : 'Dark';
    try { localStorage.setItem('pp-theme', next); } catch (_) {}
  });
})();

// ── Group collapse / expand ───────────────────────────────────────────────────
var groupState = {};
try { groupState = JSON.parse(localStorage.getItem('pp-groups') || '{}'); } catch (_) {}

function toggleGroup(id) {
  var el = document.getElementById(id);
  if (!el) return;
  var isOpen = el.classList.toggle('open');
  groupState[id] = isOpen;
  try { localStorage.setItem('pp-groups', JSON.stringify(groupState)); } catch (_) {}
}

// Restore saved group states (default all open, so only collapse if explicitly false)
(function () {
  ['grp-data', 'grp-auth', 'grp-rights', 'grp-extra'].forEach(function (id) {
    var el = document.getElementById(id);
    if (!el) return;
    if (groupState[id] === false) el.classList.remove('open');
    else el.classList.add('open');
  });
})();

// ── Active link on scroll ─────────────────────────────────────────────────────
(function () {
  // All sections in document order
  var sectionIds = [
    'overview',
    'data-collected', 'how-we-use', 'data-sharing',
    'google-oauth', 'cookies',
    'data-retention', 'security', 'your-rights',
    'children', 'changes', 'contact'
  ];

  // Map section id → group id (null = top-level)
  var sectionGroup = {
    'overview':       null,
    'data-collected': 'grp-data',
    'how-we-use':     'grp-data',
    'data-sharing':   'grp-data',
    'google-oauth':   'grp-auth',
    'cookies':        'grp-auth',
    'data-retention': 'grp-rights',
    'security':       'grp-rights',
    'your-rights':    'grp-rights',
    'children':       'grp-extra',
    'changes':        'grp-extra',
    'contact':        'grp-extra'
  };

  function setActiveSection(id) {
    // Clear all
    document.querySelectorAll('.sb-link, .sb-sub').forEach(function (a) {
      a.classList.remove('active');
    });
    document.querySelectorAll('.sb-group').forEach(function (g) {
      g.classList.remove('has-active');
    });

    // Mark active link
    var link = document.querySelector('[data-section="' + id + '"]');
    if (link) link.classList.add('active');

    // Mark parent group
    var grpId = sectionGroup[id];
    if (grpId) {
      var grpEl = document.getElementById(grpId);
      if (grpEl) grpEl.classList.add('has-active');
    }
  }

  function onScroll() {
    var scrollY = window.scrollY + 72;
    var current = sectionIds[0];
    sectionIds.forEach(function (sid) {
      var el = document.getElementById(sid);
      if (el && el.offsetTop <= scrollY) current = sid;
    });
    setActiveSection(current);
  }

  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();
})();
</script>
</body>
</html>
