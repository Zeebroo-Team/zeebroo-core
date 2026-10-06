@extends('theme::layouts.app', ['title' => 'Send email', 'heading' => 'Email Marketing'])

@section('content')
@include('mail::admin.marketing._styles')
<style>
.aem-compose{display:grid;grid-template-columns:minmax(0,1fr) 400px;gap:18px;align-items:start;}
@media(max-width:1100px){.aem-compose{grid-template-columns:1fr;}}
.aem-rcpt{position:sticky;top:16px;display:flex;flex-direction:column;max-height:calc(100vh - 32px);}
@media(max-width:1100px){.aem-rcpt{position:static;max-height:none;}}
.aem-rcpt-tools{padding:12px 14px;border-bottom:1px solid var(--border);display:flex;flex-direction:column;gap:10px;}
.aem-search{position:relative;}
.aem-search i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:12px;}
.aem-search input{padding-left:34px;}
.aem-chips{display:flex;gap:6px;flex-wrap:wrap;}
.aem-chip{padding:5px 11px;border-radius:999px;border:1px solid var(--border);background:transparent;color:var(--muted);font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;}
.aem-chip:hover{color:var(--text);background:color-mix(in srgb,var(--primary) 6%,transparent);transform:none;}
.aem-chip.is-on{background:color-mix(in srgb,var(--primary) 14%,transparent);border-color:color-mix(in srgb,var(--primary) 45%,var(--border));color:var(--primary);}
.aem-rcpt-bar{display:flex;align-items:center;justify-content:space-between;gap:8px;font-size:12px;color:var(--muted);}
.aem-linkbtn{background:none;border:0;padding:0;color:var(--primary);font-size:12px;font-weight:650;cursor:pointer;font-family:inherit;}
.aem-linkbtn:hover{background:none;color:var(--primary);text-decoration:underline;transform:none;}
.aem-rcpt-list{overflow-y:auto;flex:1;min-height:220px;max-height:560px;}
@media(max-width:1100px){.aem-rcpt-list{max-height:420px;}}
.aem-user{display:flex;align-items:center;gap:11px;padding:9px 14px;border-bottom:1px solid color-mix(in srgb,var(--border) 55%,transparent);cursor:pointer;}
.aem-user:hover{background:color-mix(in srgb,var(--primary) 4%,transparent);}
.aem-user.is-disabled{cursor:not-allowed;opacity:.55;}
.aem-user input{width:16px;height:16px;accent-color:var(--primary);flex-shrink:0;margin:0;}
.aem-avatar{width:30px;height:30px;border-radius:50%;display:grid;place-items:center;font-size:12px;font-weight:700;flex-shrink:0;background:color-mix(in srgb,var(--primary) 14%,transparent);color:var(--primary);}
.aem-user-name{font-size:13px;font-weight:650;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.aem-user-email{font-size:11.5px;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.aem-rcpt-foot{padding:14px;border-top:1px solid var(--border);display:flex;flex-direction:column;gap:10px;}
.aem-selected-count{font-size:13px;font-weight:700;}
</style>

<div class="aem-wrap">
    <a href="{{ route('admin.email-marketing.index') }}" class="aem-back"><i class="fa fa-arrow-left"></i> Email Marketing</a>
    <div class="aem-header">
        <div>
            <h1 class="aem-title">Send email</h1>
            <p class="aem-sub">Pick a template (or write a one-off email), choose who gets it, and send to everyone at once. Each person receives their own copy.</p>
        </div>
    </div>

    @if(session('status'))
        <div class="aem-msg"><i class="fa fa-circle-check" style="color:#22c55e;"></i> {{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="aem-msg aem-msg--err"><i class="fa fa-circle-exclamation"></i> {{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('admin.email-marketing.send') }}" id="aem-send-form" class="aem-compose">
        @csrf
        <div id="aem-user-ids" hidden></div>

        {{-- ── Message ── --}}
        <div class="aem-card">
            <div class="aem-card-head">
                <p class="aem-card-title"><i class="fa fa-pen-to-square"></i> Message</p>
            </div>
            <div class="aem-card-body">
                <label class="aem-field">
                    <span class="aem-label">Template</span>
                    <select name="template_id" id="aem-template" class="aem-select">
                        <option value="">— Write a custom email —</option>
                        @foreach($templates as $t)
                            <option value="{{ $t->id }}" @selected((int) old('template_id', $selectedTemplate?->id) === $t->id)>{{ $t->name }}</option>
                        @endforeach
                    </select>
                    <span class="aem-hint">
                        Changes you make here apply to this send only; the saved template stays the same.
                        @if($templates->isEmpty()) <a href="{{ route('admin.email-marketing.templates.create') }}" style="color:var(--primary);">Create a template</a> to reuse it later. @endif
                    </span>
                </label>
                <label class="aem-field">
                    <span class="aem-label">Subject</span>
                    <input type="text" name="subject" id="aem-subject" class="aem-input" maxlength="200" required value="{{ old('subject', $selectedTemplate?->subject) }}" placeholder="Email subject">
                </label>

                @include('mail::admin.marketing._editor', ['body' => old('body', $selectedTemplate?->body ?? '')])
            </div>
        </div>

        {{-- ── Recipients ── --}}
        <div class="aem-card aem-rcpt">
            <div class="aem-card-head">
                <p class="aem-card-title"><i class="fa fa-users"></i> Recipients</p>
                <span class="aem-meta">{{ number_format($recipients->count()) }} users</span>
            </div>
            <div class="aem-rcpt-tools">
                <div class="aem-search">
                    <i class="fa fa-magnifying-glass"></i>
                    <input type="search" id="aem-rcpt-search" class="aem-input" placeholder="Search name or email…" autocomplete="off">
                </div>
                <div class="aem-chips" id="aem-rcpt-filters">
                    <button type="button" class="aem-chip is-on" data-filter="all">All</button>
                    <button type="button" class="aem-chip" data-filter="active">Active</button>
                    <button type="button" class="aem-chip" data-filter="inactive">Disabled</button>
                    <button type="button" class="aem-chip" data-filter="business">Has business</button>
                    <button type="button" class="aem-chip" data-filter="no_business">No business</button>
                </div>
                <div class="aem-rcpt-bar">
                    <span id="aem-rcpt-shown"></span>
                    <span>
                        <button type="button" class="aem-linkbtn" id="aem-select-shown">Select all shown</button>
                        &nbsp;·&nbsp;
                        <button type="button" class="aem-linkbtn" id="aem-clear">Clear</button>
                    </span>
                </div>
            </div>
            <div class="aem-rcpt-list" id="aem-rcpt-list" role="list"></div>
            <div class="aem-rcpt-foot">
                <div class="aem-selected-count"><span id="aem-selected-count">0</span> selected</div>
                <button type="submit" class="aem-btn aem-btn--primary" id="aem-send-btn" style="justify-content:center;padding:12px;" disabled>
                    <i class="fa fa-paper-plane"></i> <span id="aem-send-label">Select recipients</span>
                </button>
                <span class="aem-hint" style="text-align:center;">Unsubscribed users can't be selected.</span>
            </div>
        </div>
    </form>
</div>

<script>
(function () {
    const users     = @json($recipients->values());
    const templates = @json($templates->mapWithKeys(fn ($t) => [$t->id => ['subject' => $t->subject, 'body' => $t->body]]));
    const selected  = new Set(@json(array_map('intval', (array) old('user_ids', []))));

    const list     = document.getElementById('aem-rcpt-list');
    const search   = document.getElementById('aem-rcpt-search');
    const shownEl  = document.getElementById('aem-rcpt-shown');
    const countEl  = document.getElementById('aem-selected-count');
    const sendBtn  = document.getElementById('aem-send-btn');
    const sendLbl  = document.getElementById('aem-send-label');
    const form     = document.getElementById('aem-send-form');
    const subject  = document.getElementById('aem-subject');
    let filter = 'all';
    let visible = [];

    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

    function matches(u) {
        const q = search.value.trim().toLowerCase();
        if (q && !(u.name || '').toLowerCase().includes(q) && !(u.email || '').toLowerCase().includes(q)) return false;
        switch (filter) {
            case 'active': return u.active;
            case 'inactive': return !u.active;
            case 'business': return u.has_business;
            case 'no_business': return !u.has_business;
            default: return true;
        }
    }

    function render() {
        visible = users.filter(matches);
        if (!visible.length) {
            list.innerHTML = '<div class="aem-empty" style="padding:30px 16px;"><p class="aem-empty-sub" style="margin:0;">No users match.</p></div>';
        } else {
            list.innerHTML = visible.map(u => {
                const tag = u.opted_out
                    ? '<span class="aem-badge aem-badge--muted">Unsubscribed</span>'
                    : (!u.active ? '<span class="aem-badge aem-badge--failed">Disabled</span>' : '');
                return '<label class="aem-user' + (u.opted_out ? ' is-disabled' : '') + '" role="listitem" title="Joined ' + esc(u.joined) + '">'
                    + '<input type="checkbox" value="' + u.id + '"' + (selected.has(u.id) ? ' checked' : '') + (u.opted_out ? ' disabled' : '') + '>'
                    + '<span class="aem-avatar">' + esc((u.name || u.email || '?').charAt(0).toUpperCase()) + '</span>'
                    + '<span style="flex:1;min-width:0;"><div class="aem-user-name">' + esc(u.name || '—') + '</div><div class="aem-user-email">' + esc(u.email) + '</div></span>'
                    + tag + '</label>';
            }).join('');
        }
        shownEl.textContent = visible.length + ' shown';
        updateCount();
    }

    function updateCount() {
        const n = selected.size;
        countEl.textContent = n.toLocaleString();
        sendBtn.disabled = n === 0;
        sendLbl.textContent = n === 0 ? 'Select recipients' : 'Send to ' + n.toLocaleString() + (n === 1 ? ' user' : ' users');
    }

    list.addEventListener('change', function (e) {
        if (e.target.type !== 'checkbox') return;
        const id = Number(e.target.value);
        e.target.checked ? selected.add(id) : selected.delete(id);
        updateCount();
    });
    search.addEventListener('input', render);
    document.getElementById('aem-rcpt-filters').addEventListener('click', function (e) {
        const chip = e.target.closest('[data-filter]');
        if (!chip) return;
        filter = chip.dataset.filter;
        this.querySelectorAll('.aem-chip').forEach(c => c.classList.toggle('is-on', c === chip));
        render();
    });
    document.getElementById('aem-select-shown').addEventListener('click', function () {
        visible.forEach(u => { if (!u.opted_out) selected.add(u.id); });
        render();
    });
    document.getElementById('aem-clear').addEventListener('click', function () {
        selected.clear();
        render();
    });

    // Loading a template replaces the subject + body (asks first if something was already written).
    const tplSelect = document.getElementById('aem-template');
    let lastTpl = tplSelect.value;
    tplSelect.addEventListener('change', function () {
        const tpl = templates[this.value];
        if (!tpl) { lastTpl = this.value; return; }
        if ((subject.value.trim() || !window.aemEditor.isEmpty()) && !confirm('Replace the current subject and message with this template?')) {
            this.value = lastTpl;
            return;
        }
        lastTpl = this.value;
        subject.value = tpl.subject;
        window.aemEditor.setHtml(tpl.body);
    });

    form.addEventListener('submit', function (e) {
        window.aemEditor.sync();
        if (window.aemEditor.isEmpty()) { e.preventDefault(); alert('The email body is empty.'); return; }
        if (!selected.size) { e.preventDefault(); return; }
        const n = selected.size;
        if (!confirm('Send "' + subject.value + '" to ' + n + (n === 1 ? ' user' : ' users') + '? This cannot be undone.')) { e.preventDefault(); return; }

        const box = document.getElementById('aem-user-ids');
        box.innerHTML = '';
        selected.forEach(id => {
            const i = document.createElement('input');
            i.type = 'hidden'; i.name = 'user_ids[]'; i.value = id;
            box.appendChild(i);
        });
        sendBtn.disabled = true;
        sendLbl.textContent = 'Sending…';
    });

    render();
})();
</script>
@endsection
