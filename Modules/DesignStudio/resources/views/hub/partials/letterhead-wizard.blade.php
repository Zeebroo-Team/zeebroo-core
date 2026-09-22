<style>
/* ═══════════════════════════════════════════════════
   LETTER HEAD CHOICE MODAL
   ═══════════════════════════════════════════════════ */
.lhc-backdrop{
    position:fixed;inset:0;z-index:1200;display:flex;align-items:center;justify-content:center;
    background:rgba(0,0,0,.6);backdrop-filter:blur(4px);
    opacity:0;visibility:hidden;transition:opacity .25s,visibility .25s;
}
.lhc-backdrop.open{opacity:1;visibility:visible;}
.lhc-panel{
    background:var(--card);border:1px solid var(--border);border-radius:20px;
    width:min(100% - 32px,540px);max-height:calc(100vh - 48px);
    display:flex;flex-direction:column;overflow:hidden;
    box-shadow:0 32px 80px rgba(0,0,0,.35);
    transform:scale(.96) translateY(10px);transition:transform .25s;
    position:relative;
}
.lhc-backdrop.open .lhc-panel{transform:scale(1) translateY(0);}
.lhc-header{
    flex-shrink:0;padding:20px 24px 16px;border-bottom:1px solid var(--border);
    display:flex;align-items:flex-start;gap:14px;
}
.lhc-header-icon{
    width:44px;height:44px;border-radius:12px;flex-shrink:0;
    background:color-mix(in srgb,var(--primary) 14%,var(--bg));
    color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:18px;
}
.lhc-header-text{flex:1;min-width:0;}
.lhc-header-text h2{margin:0 0 2px;font-size:17px;font-weight:800;letter-spacing:-.02em;}
.lhc-header-text p{margin:0;font-size:12px;color:var(--muted);}
.lhc-close{
    width:34px;height:34px;border-radius:9px;border:1px solid var(--border);
    background:transparent;color:var(--muted);cursor:pointer;
    display:flex;align-items:center;justify-content:center;font-size:14px;
    transition:all .15s;flex-shrink:0;
}
.lhc-close:hover{background:color-mix(in srgb,var(--text) 6%,transparent);color:var(--text);}
.lhc-choices{display:grid;grid-template-columns:1fr 1fr;gap:14px;padding:22px 24px 20px;}
@media(max-width:440px){.lhc-choices{grid-template-columns:1fr;}}
.lhc-card{
    position:relative;background:var(--bg);border:2px solid var(--border);border-radius:16px;
    padding:26px 18px 20px;cursor:pointer;
    display:flex;flex-direction:column;align-items:center;text-align:center;gap:10px;
    transition:border-color .18s,transform .15s,box-shadow .18s;
}
.lhc-card:hover{
    border-color:var(--primary);transform:translateY(-2px);
    box-shadow:0 8px 24px color-mix(in srgb,var(--primary) 18%,transparent);
}
.lhc-card-icon{
    width:54px;height:54px;border-radius:14px;flex-shrink:0;
    background:color-mix(in srgb,var(--primary) 12%,var(--bg));
    color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:22px;
    transition:background .18s,color .18s;
}
.lhc-card:hover .lhc-card-icon{background:var(--primary);color:#fff;}
.lhc-card h3{margin:0;font-size:14px;font-weight:800;color:var(--text);}
.lhc-card p{margin:0;font-size:11px;color:var(--muted);line-height:1.55;}
.lhc-badge{
    position:absolute;top:-11px;right:12px;
    background:var(--primary);color:#fff;
    font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;
    padding:3px 9px;border-radius:20px;
}
.lhc-form-body{flex:1;overflow-y:auto;padding:20px 24px;-webkit-overflow-scrolling:touch;}
.lhc-field{margin-bottom:13px;}
.lhc-field label{display:block;font-size:12px;font-weight:700;color:var(--text);margin-bottom:5px;}
.lhc-input{
    width:100%;box-sizing:border-box;
    background:var(--bg);border:1.5px solid var(--border);color:var(--text);
    border-radius:9px;padding:9px 12px;font-size:13px;font-family:inherit;
    outline:none;transition:border-color .15s;
}
.lhc-input:focus{border-color:var(--primary);}
.lhc-grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
@media(max-width:440px){.lhc-grid2{grid-template-columns:1fr;}}
.lhc-footer{
    flex-shrink:0;display:flex;align-items:center;justify-content:flex-end;gap:10px;
    padding:14px 24px;border-top:1px solid var(--border);
    background:color-mix(in srgb,var(--bg) 50%,var(--card));
}
.lhc-btn{
    display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:9px;
    border:1px solid var(--border);background:transparent;color:var(--text);
    font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;transition:all .15s;
}
.lhc-btn:hover{background:color-mix(in srgb,var(--text) 6%,transparent);}
.lhc-btn-primary{
    background:linear-gradient(135deg,var(--primary),color-mix(in srgb,var(--primary) 70%,#7c3aed));
    border-color:var(--primary);color:#fff;
    box-shadow:0 4px 16px color-mix(in srgb,var(--primary) 28%,transparent);
}
.lhc-btn-primary:hover{filter:brightness(1.07);}
.lhc-loading{
    position:absolute;inset:0;z-index:20;border-radius:20px;
    background:var(--card);
    display:none;flex-direction:column;align-items:center;justify-content:center;
    padding:36px 32px;text-align:center;
}
.lhc-loading.on{display:flex;}
.lhc-loading-orb{
    width:64px;height:64px;border-radius:50%;
    background:linear-gradient(135deg,var(--primary),color-mix(in srgb,var(--primary) 60%,#7c3aed));
    display:flex;align-items:center;justify-content:center;
    font-size:24px;color:#fff;margin-bottom:18px;
    animation:lhcPulse 2s ease-in-out infinite;
}
@keyframes lhcPulse{
    0%,100%{box-shadow:0 0 0 0 color-mix(in srgb,var(--primary) 40%,transparent);}
    50%{box-shadow:0 0 0 18px color-mix(in srgb,var(--primary) 0%,transparent);}
}
.lhc-loading h3{margin:0 0 5px;font-size:18px;font-weight:800;letter-spacing:-.02em;}
.lhc-loading-sub{margin:0 0 22px;font-size:12px;color:var(--muted);}
.lhc-lsteps{display:flex;flex-direction:column;gap:12px;width:100%;max-width:280px;}
.lhc-lstep{display:flex;align-items:center;gap:10px;}
.lhc-lstep-dot{
    width:20px;height:20px;border-radius:50%;flex-shrink:0;
    border:2px solid var(--border);background:var(--bg);
    display:flex;align-items:center;justify-content:center;transition:all .3s;
}
.lhc-lstep.active .lhc-lstep-dot{border-color:var(--primary);background:var(--primary);animation:cpwSpin 1s linear infinite;}
.lhc-lstep.done .lhc-lstep-dot{border-color:var(--primary);background:var(--primary);}
.lhc-lstep.done .lhc-lstep-dot::after{content:'✓';color:#fff;font-size:10px;font-weight:800;}
.lhc-lstep-text{font-size:12px;font-weight:600;color:var(--muted);transition:color .3s;}
.lhc-lstep.active .lhc-lstep-text{color:var(--text);}
.lhc-lstep.done .lhc-lstep-text{color:var(--primary);text-decoration:line-through;text-decoration-thickness:1px;}
.lhc-err{
    margin-top:14px;padding:9px 14px;border-radius:9px;
    background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);
    color:#ef4444;font-size:12px;font-weight:600;display:none;
}
.lhc-err.on{display:block;}
</style>

{{-- ══════════════════════════════════════════════════════
     LETTER HEAD CHOICE MODAL
     ══════════════════════════════════════════════════════ --}}
<div class="lhc-backdrop" id="lhcBackdrop">
<div class="lhc-panel">

    {{-- AI Loading Overlay --}}
    <div class="lhc-loading" id="lhcLoading">
        <div class="lhc-loading-orb"><i class="fa fa-wand-magic-sparkles" aria-hidden="true"></i></div>
        <h3>Creating your letterhead…</h3>
        <p class="lhc-loading-sub">Gemini AI is generating your professional design</p>
        <div class="lhc-lsteps">
            <div class="lhc-lstep active" id="lhcLS1">
                <div class="lhc-lstep-dot"></div>
                <span class="lhc-lstep-text">Analysing your brand identity</span>
            </div>
            <div class="lhc-lstep" id="lhcLS2">
                <div class="lhc-lstep-dot"></div>
                <span class="lhc-lstep-text">Generating professional content</span>
            </div>
            <div class="lhc-lstep" id="lhcLS3">
                <div class="lhc-lstep-dot"></div>
                <span class="lhc-lstep-text">Building canvas design</span>
            </div>
        </div>
        <div class="lhc-err" id="lhcErr"></div>
    </div>

    {{-- View 1: Choice --}}
    <div id="lhcViewChoice">
        <div class="lhc-header">
            <div class="lhc-header-icon"><i class="fa fa-file-lines" aria-hidden="true"></i></div>
            <div class="lhc-header-text">
                <h2>Create Letter Head</h2>
                <p>Choose how you want to create your company letterhead (794 × 1123 px)</p>
            </div>
            <button class="lhc-close" onclick="closeLhChoiceModal()" title="Close"><i class="fa fa-times" aria-hidden="true"></i></button>
        </div>
        <div class="lhc-choices">
            <div class="lhc-card" onclick="lhcChooseAI()">
                <div class="lhc-badge">Recommended</div>
                <div class="lhc-card-icon"><i class="fa fa-wand-magic-sparkles" aria-hidden="true"></i></div>
                <h3>Generate with AI</h3>
                <p>Let Gemini AI create a polished letterhead with your brand, professional background image, and content</p>
            </div>
            <div class="lhc-card" onclick="lhcChooseManual()">
                <div class="lhc-card-icon"><i class="fa fa-pen-ruler" aria-hidden="true"></i></div>
                <h3>Create Manually</h3>
                <p>Open the editor with a blank A4 canvas and design your letterhead from scratch</p>
            </div>
        </div>
    </div>

    {{-- View 2: AI Form --}}
    <div id="lhcViewForm" style="display:none">
        <div class="lhc-header">
            <button class="lhc-close" onclick="lhcBackToChoice()" title="Back" style="margin-right:4px;order:-1;">
                <i class="fa fa-arrow-left" aria-hidden="true"></i>
            </button>
            <div class="lhc-header-icon"><i class="fa fa-wand-magic-sparkles" aria-hidden="true"></i></div>
            <div class="lhc-header-text">
                <h2>AI Letterhead Generator</h2>
                <p>Fill in your details — AI handles the rest</p>
            </div>
            <button class="lhc-close" onclick="closeLhChoiceModal()" title="Close"><i class="fa fa-times" aria-hidden="true"></i></button>
        </div>
        <div class="lhc-form-body">
            <div class="lhc-grid2">
                <div class="lhc-field">
                    <label>Company Name</label>
                    <input class="lhc-input" id="lhcName" type="text" value="{{ $business->name }}" placeholder="Your company name">
                </div>
                <div class="lhc-field">
                    <label>Tagline <small style="font-weight:400;color:var(--muted);">Optional</small></label>
                    <input class="lhc-input" id="lhcTagline" type="text" value="{{ $business->short_description ?? '' }}" placeholder="Your trusted partner">
                </div>
            </div>
            <div class="lhc-field">
                <label>Address <small style="font-weight:400;color:var(--muted);">Optional</small></label>
                <input class="lhc-input" id="lhcAddress" type="text" value="{{ $mainBranch?->address ?? '' }}" placeholder="123 Business Street, City, Country">
            </div>
            <div class="lhc-grid2">
                <div class="lhc-field">
                    <label>Phone <small style="font-weight:400;color:var(--muted);">Optional</small></label>
                    <input class="lhc-input" id="lhcPhone" type="text" value="{{ $mainBranch?->phone ?? '' }}" placeholder="+1 234 567 890">
                </div>
                <div class="lhc-field">
                    <label>Email <small style="font-weight:400;color:var(--muted);">Optional</small></label>
                    <input class="lhc-input" id="lhcEmail" type="text" value="{{ $mainBranch?->email ?? '' }}" placeholder="hello@company.com">
                </div>
            </div>
            <div class="lhc-grid2">
                <div class="lhc-field">
                    <label>Website <small style="font-weight:400;color:var(--muted);">Optional</small></label>
                    <input class="lhc-input" id="lhcWebsite" type="text" value="" placeholder="www.company.com">
                </div>
                <div class="lhc-field">
                    <label>Theme Color</label>
                    <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;padding-top:2px;" id="lhcSwatches">
                        <button type="button" class="cpw-swatch sel" data-color="#3B82F6" style="background:#3B82F6;width:28px;height:28px;" onclick="lhcPickColor(this)"></button>
                        <button type="button" class="cpw-swatch" data-color="#8B5CF6" style="background:#8B5CF6;width:28px;height:28px;" onclick="lhcPickColor(this)"></button>
                        <button type="button" class="cpw-swatch" data-color="#F43F5E" style="background:#F43F5E;width:28px;height:28px;" onclick="lhcPickColor(this)"></button>
                        <button type="button" class="cpw-swatch" data-color="#10B981" style="background:#10B981;width:28px;height:28px;" onclick="lhcPickColor(this)"></button>
                        <button type="button" class="cpw-swatch" data-color="#F59E0B" style="background:#F59E0B;width:28px;height:28px;" onclick="lhcPickColor(this)"></button>
                        <button type="button" class="cpw-swatch" data-color="#0EA5E9" style="background:#0EA5E9;width:28px;height:28px;" onclick="lhcPickColor(this)"></button>
                        <div class="cpw-swatch-custom" title="Custom color" style="width:28px;height:28px;">
                            <i class="fa fa-eyedropper" aria-hidden="true"></i>
                            <input type="color" id="lhcColorCustom" value="#3B82F6" oninput="lhcPickCustomColor(this)">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="lhc-footer">
            <button class="lhc-btn" onclick="closeLhChoiceModal()">Cancel</button>
            <button class="lhc-btn lhc-btn-primary" id="lhcGenBtn" onclick="lhcGenerate()">
                <i class="fa fa-wand-magic-sparkles" aria-hidden="true"></i> Generate Letterhead
            </button>
        </div>
    </div>

</div>{{-- /.lhc-panel --}}
</div>{{-- /.lhc-backdrop --}}


<script>
/* ══════════════════════════════════════════════════════
   LETTER HEAD CHOICE MODAL
   ══════════════════════════════════════════════════════ */
var lhcAccent = '#3B82F6';
var lhcGenerating = false;
var lhcLoadingTimers = [];

function openLhChoiceModal() {
    lhcAccent = '#3B82F6';
    lhcGenerating = false;
    document.getElementById('lhcViewChoice').style.display = '';
    document.getElementById('lhcViewForm').style.display = 'none';
    document.getElementById('lhcLoading').classList.remove('on');
    document.getElementById('lhcErr').classList.remove('on');
    /* Reset colour swatches to default */
    document.querySelectorAll('#lhcSwatches .cpw-swatch').forEach(function(b) {
        b.classList.toggle('sel', b.dataset.color === '#3B82F6');
    });
    document.getElementById('lhcColorCustom').value = '#3B82F6';
    document.getElementById('lhcBackdrop').classList.add('open');
}
function closeLhChoiceModal() {
    if (lhcGenerating) return;
    document.getElementById('lhcBackdrop').classList.remove('open');
}
document.getElementById('lhcBackdrop').addEventListener('click', function(e) {
    if (e.target === this) closeLhChoiceModal();
});

function lhcChooseManual() {
    closeLhChoiceModal();
    startEditor(794, 1123, 'letterhead');
}
function lhcChooseAI() {
    document.getElementById('lhcViewChoice').style.display = 'none';
    document.getElementById('lhcViewForm').style.display = '';
}
function lhcBackToChoice() {
    document.getElementById('lhcViewForm').style.display = 'none';
    document.getElementById('lhcViewChoice').style.display = '';
}

function lhcPickColor(btn) {
    document.querySelectorAll('#lhcSwatches .cpw-swatch').forEach(function(b) { b.classList.remove('sel'); });
    btn.classList.add('sel');
    lhcAccent = btn.dataset.color;
    document.getElementById('lhcColorCustom').value = lhcAccent;
}
function lhcPickCustomColor(inp) {
    lhcAccent = inp.value;
    document.querySelectorAll('#lhcSwatches .cpw-swatch').forEach(function(b) { b.classList.remove('sel'); });
}

function lhcGetData() {
    return {
        name:    (document.getElementById('lhcName').value    || '').trim(),
        tagline: (document.getElementById('lhcTagline').value || '').trim(),
        address: (document.getElementById('lhcAddress').value || '').trim(),
        phone:   (document.getElementById('lhcPhone').value   || '').trim(),
        email:   (document.getElementById('lhcEmail').value   || '').trim(),
        website: (document.getElementById('lhcWebsite').value || '').trim(),
        color:   lhcAccent,
        logoUrl: @json($business->logoUrl()),
    };
}

function lhcShowLoading() {
    document.getElementById('lhcViewForm').style.display = 'none';
    document.getElementById('lhcLoading').classList.add('on');
    document.getElementById('lhcErr').classList.remove('on');
    /* Reset all steps */
    ['lhcLS1','lhcLS2','lhcLS3'].forEach(function(id) {
        var el = document.getElementById(id);
        el.classList.remove('active', 'done');
    });
    document.getElementById('lhcLS1').classList.add('active');
    /* Advance steps visually */
    lhcLoadingTimers.push(setTimeout(function() {
        document.getElementById('lhcLS1').classList.remove('active');
        document.getElementById('lhcLS1').classList.add('done');
        document.getElementById('lhcLS2').classList.add('active');
    }, 3000));
    lhcLoadingTimers.push(setTimeout(function() {
        document.getElementById('lhcLS2').classList.remove('active');
        document.getElementById('lhcLS2').classList.add('done');
        document.getElementById('lhcLS3').classList.add('active');
    }, 7000));
}
function lhcHideLoading() {
    lhcLoadingTimers.forEach(clearTimeout);
    lhcLoadingTimers = [];
    document.getElementById('lhcLoading').classList.remove('on');
}

function lhcGenerate() {
    if (lhcGenerating) return;
    var d = lhcGetData();
    if (!d.name) { document.getElementById('lhcName').focus(); return; }
    lhcGenerating = true;
    lhcShowLoading();

    var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    fetch('{{ route('designstudio.generate.letterhead') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify(d),
    })
    .then(function(r) { return r.json(); })
    .then(function(result) {
        lhcGenerating = false;
        /* Mark all steps done */
        ['lhcLS1','lhcLS2','lhcLS3'].forEach(function(id) {
            var el = document.getElementById(id);
            el.classList.remove('active');
            el.classList.add('done');
        });
        lhcBuildAndLaunch(d, (result && result.success) ? result : null);
    })
    .catch(function() {
        lhcGenerating = false;
        lhcBuildAndLaunch(d, null);
    });
}

function lhcBuildAndLaunch(d, aiResult) {
    var page = lhcBuildPage(d, aiResult);
    try { sessionStorage.setItem('dsWizardTemplate', JSON.stringify([page])); } catch(e) {}
    lhcHideLoading();
    closeLhChoiceModal();
    startEditor(794, 1123, 'letterhead');
}

/* ══════════════════════════════════════════════════════
   LETTERHEAD CANVAS BUILDER — 794×1123 A4 portrait
   White background, professional corporate layout
   ══════════════════════════════════════════════════════ */
function lhcBuildPage(d, ai) {
    var W = 794, H = 1123;
    var c       = (ai && ai.content) ? ai.content : {};
    var accent  = d.color || '#3B82F6';
    var dark    = '#1E293B';
    var slate   = '#475569';
    var muted   = '#94A3B8';
    var white   = '#FFFFFF';
    var tagline = c.tagline || d.tagline || '';
    var hasLogo = !!d.logoUrl;
    var nameX   = hasLogo ? 126 : 40;

    var objects = [];

    /* ── Top accent strip ── */
    objects.push(cpwR(0, 0, W, 6, accent));

    /* ── Header: left side — logo + company identity ── */
    if (hasLogo) {
        /* Logo: pre-loaded to 76×76 display area */
        objects.push(cpwMakeBase('image', {
            left:40, top:18, width:76, height:76,
            src: d.logoUrl, crossOrigin:'anonymous',
            scaleX:1, scaleY:1
        }));
        /* Thin vertical rule separating logo from text */
        objects.push(cpwR(128, 22, 1.5, 68, accent, 0, 0.35));
    }

    /* Company name */
    objects.push(cpwT(nameX + (hasLogo ? 16 : 0), 20, 400, d.name || 'Company Name', 22, dark, '800', 'normal', 'left', 1.15));

    /* Tagline */
    if (tagline) {
        objects.push(cpwT(nameX + (hasLogo ? 16 : 0), 50, 400, tagline, 11, slate, '400', 'italic', 'left', 1.3));
    }

    /* ── Header: right side — contact block (right-aligned) ── */
    var contactLines = [];
    if (d.address) contactLines.push(d.address);
    if (d.phone)   contactLines.push(d.phone);
    if (d.email)   contactLines.push(d.email);
    if (d.website) contactLines.push(d.website);
    if (contactLines.length) {
        objects.push(cpwT(494, 18, 260, contactLines.join('\n'), 10, slate, '400', 'normal', 'right', 1.75));
    }

    /* ── Divider ── */
    var divY = 106;
    objects.push(cpwR(0, divY, W, 1, 'rgba(0,0,0,0.08)'));
    objects.push(cpwR(0, divY + 1, W, 4, accent, 0, 0.18));

    /* ── Footer ── */
    /* Light separator */
    objects.push(cpwR(0, 1074, W, 1, 'rgba(0,0,0,0.08)'));
    /* Thin accent band */
    objects.push(cpwR(0, 1075, W, 4, accent, 0, 0.35));

    /* Footer contact line (centered, small) */
    var footerParts = [];
    if (d.address) footerParts.push(d.address);
    if (d.phone)   footerParts.push(d.phone);
    if (d.email)   footerParts.push(d.email);
    if (d.website) footerParts.push(d.website);
    if (footerParts.length) {
        objects.push(cpwT(48, 1086, W - 96, footerParts.join('   ·   '), 10, slate, '400', 'normal', 'center', 1.3));
    }

    /* Bottom accent strip */
    objects.push(cpwR(0, 1113, W, 10, accent, 0, 0.12));

    return {json: JSON.stringify({version:'5.3.0', objects: objects, background: white}), thumb: null};
}

function dsLhCopyLink(btn, url) {
    navigator.clipboard.writeText(url).then(function () {
        var icon = btn.querySelector('i');
        var prev = icon ? icon.className : '';
        if (icon) icon.className = 'fa fa-check';
        btn.style.color = '#22c55e';
        btn.style.borderColor = '#22c55e';
        setTimeout(function () {
            if (icon) icon.className = prev;
            btn.style.color = '';
            btn.style.borderColor = '';
        }, 1800);
    }).catch(function () {
        window.prompt('Copy this link:', url);
    });
}
</script>
