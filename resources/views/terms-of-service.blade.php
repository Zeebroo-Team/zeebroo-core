<!DOCTYPE html>
<html lang="en" data-theme="">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Terms of Service — {{ config('app.name') }}</title>
<meta name="description" content="Terms of Service for {{ config('app.name') }} — the rules and conditions for using the platform.">
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
  --warn-bg:      #FFF7ED;
  --warn-border:  #F97316;
  --note-bg:      #F4F4F4;
  --note-border:  #AAAAAA;
  --sidebar-text:        #555555;
  --sidebar-text-active: #111111;
  --sidebar-scrollbar:   #CCCCCC;
  --shadow: 0 1px 3px rgba(0,0,0,.07), 0 1px 2px rgba(0,0,0,.04);
}
@media (prefers-color-scheme: dark) {
  :root:not([data-theme="light"]) {
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
    --warn-bg:      #1A0A00;
    --warn-border:  #C2410C;
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
  --warn-bg:      #1A0A00;
  --warn-border:  #C2410C;
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
  --warn-bg:      #FFF7ED;
  --warn-border:  #F97316;
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

/* ── Progress bar ── */
#progress-bar {
  position: fixed;
  top: 0; left: 0;
  height: 2px;
  background: var(--accent);
  z-index: 200;
  transition: width .15s;
  width: 0%;
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

.sb-brand { padding: 18px 20px 14px; border-bottom: 1px solid var(--border); }
.sb-logo { display: block; width: 100%; max-width: 160px; height: auto; margin-bottom: 6px; }
.sb-brand-name-text { font-size: 15px; font-weight: 700; color: var(--text); margin-bottom: 4px; display: none; }
.sb-brand-sub {
  font-size: 11px;
  color: var(--text-faint);
  font-family: ui-monospace, 'Cascadia Code', monospace;
  letter-spacing: .04em;
}

.sb-nav { padding: 8px 0 24px; flex: 1; }

/* ── Tree group (level 1) ── */
.tree-group { margin: 1px 0; }
.tree-header {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 7px;
  padding: 8px 14px 8px 12px;
  background: none;
  border: none;
  border-left: 3px solid transparent;
  cursor: pointer;
  color: var(--text);
  font-size: 11.5px;
  font-weight: 700;
  letter-spacing: .06em;
  text-transform: uppercase;
  text-align: left;
  transition: background .12s, border-color .12s;
  font-family: inherit;
}
.tree-header:hover { background: var(--sidebar-hover); }
.tree-group.open > .tree-header { border-left-color: var(--accent-bright); }
.tree-chevron {
  width: 10px; height: 10px;
  flex-shrink: 0;
  color: var(--text-faint);
  transition: transform .22s ease;
  margin-left: auto;
}
.tree-group.open > .tree-header .tree-chevron { transform: rotate(90deg); }
.tree-icon {
  width: 15px; height: 15px;
  flex-shrink: 0;
  color: var(--accent);
  opacity: .85;
}
.tree-group.open > .tree-header .tree-icon { opacity: 1; }
.tree-label { flex: 1; }

/* ── Tree children ── */
.tree-children {
  overflow: hidden;
  max-height: 0;
  transition: max-height .28s cubic-bezier(.4,0,.2,1);
  position: relative;
}
.tree-children::before {
  content: '';
  position: absolute;
  left: 25px; top: 6px; bottom: 6px;
  width: 1px;
  background: var(--border);
}
.tree-group.open .tree-children { max-height: 600px; }

/* ── Leaf links (level 2) ── */
.sb-link {
  display: flex;
  align-items: center;
  padding: 6px 14px 6px 40px;
  color: var(--sidebar-text);
  text-decoration: none;
  font-size: 12.5px;
  transition: background .12s, color .12s;
  cursor: pointer;
  border-left: 3px solid transparent;
  position: relative;
}
.sb-link::before {
  content: '';
  position: absolute;
  left: 25px; top: 50%;
  width: 10px; height: 1px;
  background: var(--border);
  transform: translateY(-50%);
}
.sb-link:hover { background: var(--sidebar-hover); color: var(--text); text-decoration: none; }
.sb-link:hover::before { background: var(--border-strong); }
.sb-link.active {
  color: #111111;
  font-weight: 600;
  border-left-color: var(--accent-bright);
  background: var(--accent-light);
}
.sb-link.active::before { background: var(--accent-bright); }

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
.hero-meta-item svg { width: 15px; height: 15px; flex-shrink: 0; }

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
  flex-shrink: 0;
  margin-top: 2px;
  background: var(--bg-subtle);
  border: 1px solid var(--border);
}
.chapter-icon svg { width: 20px; height: 20px; }
.chapter-icon.ch-gold { background: var(--accent-bright); border-color: transparent; }
.chapter-icon.ch-gold svg { color: #111; }
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
  background: currentColor;
  opacity: .25;
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
.callout.warn { background: var(--warn-bg); border-left: 3px solid var(--warn-border); }
.callout.warn .callout-icon { color: var(--warn-border); }
.callout.warn strong { color: var(--warn-border); }
.callout.note { background: var(--note-bg); border-left: 3px solid var(--note-border); }
.callout.note .callout-icon { color: var(--text-faint); }
.callout.note strong { color: var(--text-muted); }

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
@media (max-width: 860px) {
  #sidebar { transform: translateX(-258px); transition: transform .2s; }
  #main { margin-left: 0; }
  #topbar { padding: 0 16px; }
  #content { padding: 0 16px 60px; }
  .toc-grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<div id="progress-bar"></div>

<!-- ── Sidebar ── -->
<nav id="sidebar">
  <div class="sb-brand">
    <img class="sb-logo" src="https://zeebroo.com/images/tutorial/logo.png" alt="{{ config('app.name') }}" onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
    <div class="sb-brand-name-text">{{ config('app.name') }}</div>
    <div class="sb-brand-sub">TERMS OF SERVICE · {{ now()->format('Y') }}</div>
  </div>

  <div class="sb-nav">

    <!-- Introduction -->
    <div class="tree-group open">
      <button class="tree-header" onclick="treeToggle(this)">
        <svg class="tree-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="8" r="6.5"/><line x1="8" y1="5.5" x2="8" y2="8"/><circle cx="8" cy="10.5" r=".5" fill="currentColor"/></svg>
        <span class="tree-label">Introduction</span>
        <svg class="tree-chevron" viewBox="0 0 10 10" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2l4 3-4 3"/></svg>
      </button>
      <div class="tree-children">
        <a class="sb-link" href="#overview">Overview</a>
        <a class="sb-link" href="#acceptance">Acceptance</a>
      </div>
    </div>

    <!-- Your Account -->
    <div class="tree-group open">
      <button class="tree-header" onclick="treeToggle(this)">
        <svg class="tree-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="5" r="3"/><path d="M2 14s1-4 6-4 6 4 6 4"/></svg>
        <span class="tree-label">Your Account</span>
        <svg class="tree-chevron" viewBox="0 0 10 10" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2l4 3-4 3"/></svg>
      </button>
      <div class="tree-children">
        <a class="sb-link" href="#registration">Registration</a>
        <a class="sb-link" href="#account-security">Account Security</a>
        <a class="sb-link" href="#acceptable-use">Acceptable Use</a>
      </div>
    </div>

    <!-- The Service -->
    <div class="tree-group open">
      <button class="tree-header" onclick="treeToggle(this)">
        <svg class="tree-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="12" height="10" rx="1.5"/><path d="M5 7h6M5 9.5h4"/></svg>
        <span class="tree-label">The Service</span>
        <svg class="tree-chevron" viewBox="0 0 10 10" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2l4 3-4 3"/></svg>
      </button>
      <div class="tree-children">
        <a class="sb-link" href="#what-we-provide">What We Provide</a>
        <a class="sb-link" href="#availability">Availability</a>
        <a class="sb-link" href="#updates">Updates &amp; Changes</a>
      </div>
    </div>

    <!-- Your Content -->
    <div class="tree-group open">
      <button class="tree-header" onclick="treeToggle(this)">
        <svg class="tree-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="8" cy="4" rx="6" ry="2"/><path d="M14 8c0 1.1-2.69 2-6 2S2 9.1 2 8"/><path d="M2 4v8c0 1.1 2.69 2 6 2s6-.9 6-2V4"/></svg>
        <span class="tree-label">Your Content</span>
        <svg class="tree-chevron" viewBox="0 0 10 10" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2l4 3-4 3"/></svg>
      </button>
      <div class="tree-children">
        <a class="sb-link" href="#data-ownership">Data Ownership</a>
        <a class="sb-link" href="#backups">Backups</a>
      </div>
    </div>

    <!-- Legal -->
    <div class="tree-group open">
      <button class="tree-header" onclick="treeToggle(this)">
        <svg class="tree-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8 1.5L2 4v4.5c0 3.5 2.5 6.2 6 7 3.5-.8 6-3.5 6-7V4L8 1.5z"/></svg>
        <span class="tree-label">Legal</span>
        <svg class="tree-chevron" viewBox="0 0 10 10" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2l4 3-4 3"/></svg>
      </button>
      <div class="tree-children">
        <a class="sb-link" href="#payments">Payments</a>
        <a class="sb-link" href="#termination">Termination</a>
        <a class="sb-link" href="#liability">Limitation of Liability</a>
        <a class="sb-link" href="#governing-law">Governing Law</a>
        <a class="sb-link" href="#contact">Contact</a>
      </div>
    </div>

  </div>
</nav>

<!-- ── Main ── -->
<div id="main">

  <!-- Topbar -->
  <div id="topbar">
    <div class="tb-title">
      {{ config('app.name') }}<span>/</span>Terms of Service
    </div>
    <div class="tb-actions">
      <span class="version-badge">{{ now()->format('M Y') }}</span>
      <button class="theme-toggle" id="themeToggle" onclick="toggleTheme()">Toggle Theme</button>
    </div>
  </div>

  <!-- Content -->
  <div id="content">

    <!-- Hero -->
    <div class="hero" id="overview">
      <div class="hero-eyebrow">Legal · Terms</div>
      <h1>Terms of Service</h1>
      <p>By using {{ config('app.name') }} you agree to these terms. Please read them — they are written plainly and exist to protect both you and us.</p>
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
        <a class="toc-item" href="#acceptance"><span class="toc-num">01</span> Acceptance of Terms</a>
        <a class="toc-item" href="#registration"><span class="toc-num">02</span> Registration</a>
        <a class="toc-item" href="#account-security"><span class="toc-num">03</span> Account Security</a>
        <a class="toc-item" href="#acceptable-use"><span class="toc-num">04</span> Acceptable Use</a>
        <a class="toc-item" href="#what-we-provide"><span class="toc-num">05</span> What We Provide</a>
        <a class="toc-item" href="#availability"><span class="toc-num">06</span> Availability</a>
        <a class="toc-item" href="#updates"><span class="toc-num">07</span> Updates &amp; Changes</a>
        <a class="toc-item" href="#data-ownership"><span class="toc-num">08</span> Data Ownership</a>
        <a class="toc-item" href="#backups"><span class="toc-num">09</span> Backups</a>
        <a class="toc-item" href="#payments"><span class="toc-num">10</span> Payments</a>
        <a class="toc-item" href="#termination"><span class="toc-num">11</span> Termination</a>
        <a class="toc-item" href="#liability"><span class="toc-num">12</span> Limitation of Liability</a>
        <a class="toc-item" href="#governing-law"><span class="toc-num">13</span> Governing Law</a>
        <a class="toc-item" href="#contact"><span class="toc-num">14</span> Contact</a>
      </div>
    </div>

    <!-- 01 · Acceptance -->
    <div class="chapter" id="acceptance">
      <div class="chapter-header">
        <div class="chapter-num">01</div>
        <div class="chapter-icon ch-gold">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
        </div>
        <div class="chapter-meta">
          <h2>Acceptance of Terms</h2>
          <p class="chapter-desc">Using {{ config('app.name') }} means you agree to these terms.</p>
        </div>
      </div>
      <div class="section">
        <p>By creating an account or accessing any part of the {{ config('app.name') }} platform — including the web application and the desktop app — you agree to be bound by these Terms of Service and our <a href="{{ route('privacy-policy') }}">Privacy Policy</a>.</p>
        <p>If you are using {{ config('app.name') }} on behalf of an organisation, you represent that you have the authority to bind that organisation to these terms, and "you" refers to that organisation.</p>
        <div class="callout warn">
          <div class="callout-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          </div>
          <div class="callout-body">
            <strong>Important</strong>
            If you do not agree to these terms, you must not use the platform. Continued use after any update to these terms constitutes acceptance of the revised version.
          </div>
        </div>
      </div>
    </div>

    <!-- 02 · Registration -->
    <div class="chapter" id="registration">
      <div class="chapter-header">
        <div class="chapter-num">02</div>
        <div class="chapter-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
        </div>
        <div class="chapter-meta">
          <h2>Registration</h2>
          <p class="chapter-desc">Who can use {{ config('app.name') }} and what you agree to when signing up.</p>
        </div>
      </div>
      <div class="section">
        <h3>Eligibility</h3>
        <p>You must be at least <strong>16 years old</strong> to use {{ config('app.name') }}. By registering, you confirm that all information you provide is accurate, current, and complete.</p>
      </div>
      <div class="section">
        <h3>Account details</h3>
        <ul>
          <li>You may register with a valid email address and password, or via Google OAuth.</li>
          <li>Each person must maintain only one account unless expressly permitted in writing.</li>
          <li>You are responsible for all activity that occurs under your account.</li>
          <li>You must notify us immediately at <a href="mailto:support@zeebroo.com">support@zeebroo.com</a> if you suspect unauthorised access.</li>
        </ul>
      </div>
    </div>

    <!-- 03 · Account Security -->
    <div class="chapter" id="account-security">
      <div class="chapter-header">
        <div class="chapter-num">03</div>
        <div class="chapter-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        </div>
        <div class="chapter-meta">
          <h2>Account Security</h2>
          <p class="chapter-desc">Keeping your credentials safe is your responsibility.</p>
        </div>
      </div>
      <div class="section">
        <ul>
          <li>Choose a strong, unique password and keep it confidential. Do not share it with anyone.</li>
          <li>{{ config('app.name') }} will never ask for your password by email or chat.</li>
          <li>If you use Google OAuth, your account security is also governed by Google's own security practices.</li>
          <li>We reserve the right to suspend accounts that show signs of compromise or abuse.</li>
        </ul>
        <div class="callout tip">
          <div class="callout-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          </div>
          <div class="callout-body">
            <strong>Tip</strong>
            Use a password manager to generate and store a unique password for your {{ config('app.name') }} account.
          </div>
        </div>
      </div>
    </div>

    <!-- 04 · Acceptable Use -->
    <div class="chapter" id="acceptable-use">
      <div class="chapter-header">
        <div class="chapter-num">04</div>
        <div class="chapter-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        </div>
        <div class="chapter-meta">
          <h2>Acceptable Use</h2>
          <p class="chapter-desc">What you may and may not do with the platform.</p>
        </div>
      </div>
      <div class="section">
        <h3>Permitted uses</h3>
        <p>{{ config('app.name') }} is a business management platform. You may use it to manage sales, inventory, accounts, HR records, customers, and other legitimate business operations.</p>
      </div>
      <div class="section">
        <h3>Prohibited conduct</h3>
        <p>You must not:</p>
        <ul>
          <li>Use the platform for any unlawful purpose or in violation of any applicable law.</li>
          <li>Attempt to gain unauthorised access to any part of the platform or another user's account.</li>
          <li>Reverse-engineer, decompile, or attempt to extract the source code of the platform.</li>
          <li>Transmit malware, viruses, or any malicious or harmful code.</li>
          <li>Scrape, crawl, or use automated tools to extract data without express written permission.</li>
          <li>Resell, sublicense, or otherwise commercially exploit the platform or its API without a written agreement.</li>
          <li>Store or process data belonging to a third party without that party's consent.</li>
          <li>Impersonate another person or organisation.</li>
        </ul>
        <p>We reserve the right to terminate accounts that violate these restrictions without notice.</p>
      </div>
    </div>

    <!-- 05 · What We Provide -->
    <div class="chapter" id="what-we-provide">
      <div class="chapter-header">
        <div class="chapter-num">05</div>
        <div class="chapter-icon ch-gold">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
        </div>
        <div class="chapter-meta">
          <h2>What We Provide</h2>
          <p class="chapter-desc">The scope of the {{ config('app.name') }} service.</p>
        </div>
      </div>
      <div class="section">
        <p>{{ config('app.name') }} provides a cloud-based business management platform including point-of-sale (POS), accounts and cash flow management, inventory, human resources, CRM, and a desktop application that connects to the platform via API.</p>
        <p>Features available to you depend on your subscription plan and any feature flags enabled for your account. Not all features described in marketing materials may be enabled by default.</p>
        <div class="callout note">
          <div class="callout-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          </div>
          <div class="callout-body">
            <strong>Scope</strong>
            The platform is provided "as is" for business management purposes. It is not financial, legal, or accounting advice. Consult a qualified professional for those needs.
          </div>
        </div>
      </div>
    </div>

    <!-- 06 · Availability -->
    <div class="chapter" id="availability">
      <div class="chapter-header">
        <div class="chapter-num">06</div>
        <div class="chapter-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="chapter-meta">
          <h2>Availability</h2>
          <p class="chapter-desc">Uptime expectations and planned maintenance.</p>
        </div>
      </div>
      <div class="section">
        <p>We aim to keep {{ config('app.name') }} available 24/7, but we do not guarantee uninterrupted service. Planned maintenance windows, infrastructure updates, and unforeseen incidents may cause temporary unavailability.</p>
        <p>We will make reasonable efforts to announce planned downtime in advance. We are not liable for losses arising from service interruptions beyond our reasonable control.</p>
      </div>
    </div>

    <!-- 07 · Updates & Changes -->
    <div class="chapter" id="updates">
      <div class="chapter-header">
        <div class="chapter-num">07</div>
        <div class="chapter-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/></svg>
        </div>
        <div class="chapter-meta">
          <h2>Updates &amp; Changes</h2>
          <p class="chapter-desc">How we evolve the platform and notify you of changes.</p>
        </div>
      </div>
      <div class="section">
        <h3>Platform updates</h3>
        <p>We continuously improve {{ config('app.name') }}. Features may be added, modified, or removed. Where a change materially affects existing functionality, we will provide reasonable notice.</p>
      </div>
      <div class="section">
        <h3>Terms updates</h3>
        <p>We may update these Terms of Service from time to time. We will notify registered users of significant changes by email or a prominent notice in the platform at least <strong>14 days</strong> before the change takes effect. Continued use after that date constitutes acceptance.</p>
      </div>
      <div class="section">
        <h3>Desktop app updates</h3>
        <p>The desktop application checks for updates automatically. We recommend keeping it up to date to receive security patches and feature improvements. Older versions may eventually lose API compatibility.</p>
      </div>
    </div>

    <!-- 08 · Data Ownership -->
    <div class="chapter" id="data-ownership">
      <div class="chapter-header">
        <div class="chapter-num">08</div>
        <div class="chapter-icon ch-gold">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
        </div>
        <div class="chapter-meta">
          <h2>Data Ownership</h2>
          <p class="chapter-desc">Your business data belongs to you — always.</p>
        </div>
      </div>
      <div class="section">
        <p>All business data you enter into {{ config('app.name') }} — including sales records, customers, inventory, employees, and financial entries — remains <strong>your property</strong>. We do not claim ownership over your data.</p>
        <p>You grant us a limited, non-exclusive licence to store, process, and transmit your data solely to provide the service. We will never sell your data or use it for purposes beyond operating the platform as described in our <a href="{{ route('privacy-policy') }}">Privacy Policy</a>.</p>
        <p>Upon account termination or on request, you may export your data. We will provide your data in a machine-readable format within a reasonable timeframe.</p>
      </div>
    </div>

    <!-- 09 · Backups -->
    <div class="chapter" id="backups">
      <div class="chapter-header">
        <div class="chapter-num">09</div>
        <div class="chapter-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        </div>
        <div class="chapter-meta">
          <h2>Backups</h2>
          <p class="chapter-desc">We back up data on your behalf, but you share responsibility.</p>
        </div>
      </div>
      <div class="section">
        <p>We maintain regular automated backups of platform data. However, we recommend that you periodically export critical business data as an independent safeguard. We are not liable for data loss resulting from user error, account deletion at your request, or events beyond our control.</p>
        <div class="callout tip">
          <div class="callout-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          </div>
          <div class="callout-body">
            <strong>Best practice</strong>
            Export your sales, inventory, and financial reports regularly and store them in a location you control independently of the platform.
          </div>
        </div>
      </div>
    </div>

    <!-- 10 · Payments -->
    <div class="chapter" id="payments">
      <div class="chapter-header">
        <div class="chapter-num">10</div>
        <div class="chapter-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
        </div>
        <div class="chapter-meta">
          <h2>Payments</h2>
          <p class="chapter-desc">Billing, subscriptions, and refund conditions.</p>
        </div>
      </div>
      <div class="section">
        <h3>Subscription fees</h3>
        <p>Certain features or tiers of {{ config('app.name') }} may require a paid subscription. Fees, billing cycles, and payment methods will be clearly stated before any charge is made.</p>
      </div>
      <div class="section">
        <h3>Refunds</h3>
        <p>Refund eligibility depends on the plan and circumstances. If you believe a charge was made in error, contact us within <strong>14 days</strong> of the charge at <a href="mailto:support@zeebroo.com">support@zeebroo.com</a> and we will review it.</p>
      </div>
      <div class="section">
        <h3>Non-payment</h3>
        <p>We reserve the right to suspend or downgrade access to paid features if a subscription payment fails after reasonable notice. Your data will not be deleted immediately upon non-payment; you will have a grace period to resolve the issue.</p>
      </div>
    </div>

    <!-- 11 · Termination -->
    <div class="chapter" id="termination">
      <div class="chapter-header">
        <div class="chapter-num">11</div>
        <div class="chapter-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        </div>
        <div class="chapter-meta">
          <h2>Termination</h2>
          <p class="chapter-desc">How accounts can be closed — by you or by us.</p>
        </div>
      </div>
      <div class="section">
        <h3>By you</h3>
        <p>You may close your account at any time by contacting us at <a href="mailto:support@zeebroo.com">support@zeebroo.com</a>. Your data will be retained for up to 90 days after closure (for legal and accounting compliance) before permanent deletion, unless you request immediate deletion.</p>
      </div>
      <div class="section">
        <h3>By us</h3>
        <p>We may suspend or terminate your account if you violate these terms, engage in fraudulent or abusive activity, fail to pay applicable fees after notice, or if we are required to do so by law. Where possible, we will give advance notice and an opportunity to remedy the breach.</p>
      </div>
      <div class="section">
        <h3>Effect of termination</h3>
        <p>On termination all licences granted under these terms cease immediately. Provisions that by their nature should survive termination — including data ownership, payment obligations, limitation of liability, and governing law — will continue to apply.</p>
      </div>
    </div>

    <!-- 12 · Limitation of Liability -->
    <div class="chapter" id="liability">
      <div class="chapter-header">
        <div class="chapter-num">12</div>
        <div class="chapter-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        </div>
        <div class="chapter-meta">
          <h2>Limitation of Liability</h2>
          <p class="chapter-desc">The extent of our responsibility for losses or damages.</p>
        </div>
      </div>
      <div class="section">
        <p>To the maximum extent permitted by applicable law, {{ config('app.name') }} and its operators are not liable for any indirect, incidental, special, consequential, or punitive damages — including loss of profits, data, or goodwill — arising from your use of or inability to use the platform.</p>
        <p>Our total aggregate liability to you for any claim arising out of these terms or your use of the service will not exceed the greater of: (a) the amount you paid us in the <strong>12 months</strong> preceding the claim, or (b) <strong>LKR 5,000</strong>.</p>
        <p>Some jurisdictions do not allow the exclusion or limitation of certain warranties or liabilities, so some of these limitations may not apply to you.</p>
      </div>
    </div>

    <!-- 13 · Governing Law -->
    <div class="chapter" id="governing-law">
      <div class="chapter-header">
        <div class="chapter-num">13</div>
        <div class="chapter-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
        </div>
        <div class="chapter-meta">
          <h2>Governing Law</h2>
          <p class="chapter-desc">Which laws apply and how disputes are resolved.</p>
        </div>
      </div>
      <div class="section">
        <p>These Terms of Service are governed by and construed in accordance with the laws of <strong>Sri Lanka</strong>, without regard to its conflict-of-law provisions.</p>
        <p>Any dispute arising from or relating to these terms or the use of the platform that cannot be resolved informally will be subject to the exclusive jurisdiction of the courts of Sri Lanka.</p>
        <p>We encourage you to contact us first at <a href="mailto:support@zeebroo.com">support@zeebroo.com</a> — most concerns can be resolved quickly and informally.</p>
      </div>
    </div>

    <!-- 14 · Contact -->
    <div class="chapter" id="contact">
      <div class="chapter-header">
        <div class="chapter-num">14</div>
        <div class="chapter-icon ch-gold">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        </div>
        <div class="chapter-meta">
          <h2>Contact Us</h2>
          <p class="chapter-desc">Questions about these terms? We will reply within 5 business days.</p>
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
      &nbsp;·&nbsp; <a href="{{ route('terms-of-service') }}">Terms of Service</a>
    </footer>

  </div><!-- /#content -->
</div><!-- /#main -->

<script>
// ── Tree group toggle ─────────────────────────────────────────────────────────
function treeToggle(btn) {
  btn.closest('.tree-group').classList.toggle('open');
}

// ── Active link tracking via IntersectionObserver ─────────────────────────────
const chapters = document.querySelectorAll('.chapter');
const chLinks  = document.querySelectorAll('.sb-link');

function setActive(id) {
  chLinks.forEach(function(l) {
    const active = l.getAttribute('href') === '#' + id;
    l.classList.toggle('active', active);
    if (active) l.closest('.tree-group')?.classList.add('open');
  });
}

const obs = new IntersectionObserver(function(entries) {
  entries.forEach(function(e) {
    if (e.isIntersecting) setActive(e.target.id);
  });
}, { rootMargin: '-10% 0px -75% 0px', threshold: 0 });

chapters.forEach(function(s) { obs.observe(s); });

// ── Reading progress bar ──────────────────────────────────────────────────────
const bar = document.getElementById('progress-bar');
window.addEventListener('scroll', function() {
  const h = document.documentElement.scrollHeight - window.innerHeight;
  bar.style.width = (h > 0 ? (window.scrollY / h) * 100 : 0) + '%';
}, { passive: true });

// ── Theme toggle ──────────────────────────────────────────────────────────────
function toggleTheme() {
  const root = document.documentElement;
  const cur  = root.getAttribute('data-theme');
  const next = cur === 'dark' ? 'light' : 'dark';
  root.setAttribute('data-theme', next);
  const btn = document.getElementById('themeToggle');
  if (btn) btn.textContent = next === 'dark' ? 'Light' : 'Dark';
  try { localStorage.setItem('tos-theme', next); } catch(_) {}
}

// Restore saved theme
try {
  const saved = localStorage.getItem('tos-theme');
  if (saved) {
    document.documentElement.setAttribute('data-theme', saved);
    const btn = document.getElementById('themeToggle');
    if (btn) btn.textContent = saved === 'dark' ? 'Light' : 'Dark';
  }
} catch(_) {}
</script>
</body>
</html>
