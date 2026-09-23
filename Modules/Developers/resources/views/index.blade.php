@extends('theme::layouts.app', ['title' => 'Developer Tools', 'heading' => 'Developer Tools'])

@section('content')
<style>
.dev-lead{margin:0 0 20px;font-size:14px;line-height:1.55;color:var(--muted);}

.dev-section{border:1px solid var(--border);border-radius:14px;background:var(--card);margin-bottom:20px;overflow:hidden;}
.dev-section__head{padding:16px 20px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;border-bottom:1px solid var(--border);}
.dev-section__titles{flex:1;min-width:200px;}
.dev-section__title{margin:0 0 3px;font-size:15px;font-weight:800;color:var(--text);letter-spacing:-.02em;}
.dev-section__desc{margin:0;font-size:12.5px;color:var(--muted);line-height:1.4;}
.dev-section__body{padding:18px 20px;}

.dev-new-btn{display:inline-flex;align-items:center;gap:8px;padding:9px 16px;border-radius:10px;background:var(--primary);color:#fff;font-size:13px;font-weight:800;border:none;cursor:pointer;text-decoration:none;transition:background .15s;white-space:nowrap;}
.dev-new-btn:hover{background:color-mix(in srgb,var(--primary) 85%,#000);color:#fff;}

.dev-grid{display:grid;gap:14px;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));}
.dev-card{border:1px solid var(--border);border-radius:12px;background:var(--bg);padding:16px;display:flex;flex-direction:column;gap:10px;}
.dev-card-hdr{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;}
.dev-card-title{font-size:14px;font-weight:800;color:var(--text);margin:0 0 3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.dev-card-sub{font-size:11.5px;color:var(--muted);font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;word-break:break-all;}
.dev-card-meta{display:flex;flex-wrap:wrap;align-items:center;gap:8px;font-size:11px;color:var(--muted);}
.dev-status{display:inline-flex;align-items:center;gap:5px;font-size:10.5px;font-weight:700;padding:2px 9px;border-radius:20px;flex-shrink:0;}
.dev-status--on{background:#d1fae5;color:#065f46;}
.dev-status--off{background:color-mix(in srgb,var(--text) 8%,var(--card));color:var(--muted);}
.dev-events{display:flex;flex-wrap:wrap;gap:5px;}
.dev-event-chip{font-size:10.5px;font-weight:650;padding:2px 8px;border-radius:20px;background:color-mix(in srgb,var(--primary) 12%,var(--card));color:var(--primary);border:1px solid color-mix(in srgb,var(--primary) 30%,var(--border));}
.dev-card-actions{display:flex;gap:6px;margin-top:auto;padding-top:4px;flex-wrap:wrap;}
.dev-action{flex:1;padding:6px 8px;border-radius:6px;border:1px solid var(--border);background:transparent;color:var(--muted);font-size:11.5px;font-weight:700;cursor:pointer;text-align:center;transition:all .15s;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:5px;min-width:0;}
.dev-action:hover{background:var(--border);color:var(--text);}
.dev-action--del{color:#ef4444;}
.dev-action--del:hover{background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.4);}
.dev-toggle{position:relative;width:34px;height:19px;flex-shrink:0;border-radius:20px;border:none;background:var(--border);cursor:pointer;transition:background .15s;}
.dev-toggle::after{content:'';position:absolute;top:2px;left:2px;width:15px;height:15px;border-radius:50%;background:#fff;transition:transform .15s;}
.dev-toggle.on{background:#16a34a;}
.dev-toggle.on::after{transform:translateX(15px);}

.dev-secret-row{display:flex;align-items:center;gap:6px;}
.dev-secret-row input{flex:1;min-width:0;border:1px solid var(--border);border-radius:6px;padding:6px 8px;background:var(--card);color:var(--text);font-size:11.5px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;}
.dev-icon-btn{border:1px solid var(--border);background:transparent;color:var(--muted);border-radius:6px;padding:6px 9px;cursor:pointer;flex-shrink:0;}
.dev-icon-btn:hover{background:var(--border);color:var(--text);}

.dev-empty{text-align:center;padding:36px 24px;border:1.5px dashed var(--border);border-radius:12px;}
.dev-empty i{font-size:26px;color:var(--muted);margin-bottom:10px;display:block;}
.dev-empty p{margin:0;font-size:13px;color:var(--muted);}

.dev-alert{margin:0 0 16px;padding:10px 14px;border-radius:10px;font-size:13px;line-height:1.5;border:1px solid;}
.dev-alert--ok{border-color:color-mix(in srgb,#22c55e 45%,var(--border));background:color-mix(in srgb,#22c55e 10%,transparent);color:color-mix(in srgb,#16a34a 80%,var(--text));}
.dev-alert--err{border-color:color-mix(in srgb,#f87171 45%,var(--border));background:color-mix(in srgb,#f87171 10%,transparent);color:color-mix(in srgb,#dc2626 80%,var(--text));}
.dev-alert--token{border-color:color-mix(in srgb,#f59e0b 45%,var(--border));background:color-mix(in srgb,#f59e0b 10%,transparent);color:color-mix(in srgb,#b45309 85%,var(--text));}

/* Modal */
.dev-modal-backdrop{position:fixed;inset:0;z-index:1050;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.55);backdrop-filter:blur(3px);opacity:0;visibility:hidden;transition:all .2s;padding:16px;}
.dev-modal-backdrop.open{opacity:1;visibility:visible;}
.dev-modal-panel{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:24px;width:min(100%,480px);box-shadow:0 24px 60px rgba(0,0,0,.3);transform:scale(.95);transition:transform .2s;max-height:90vh;overflow-y:auto;}
.dev-modal-backdrop.open .dev-modal-panel{transform:scale(1);}
.dev-modal-title{font-size:17px;font-weight:800;letter-spacing:-.02em;margin:0 0 18px;}
.dev-field{display:flex;flex-direction:column;gap:5px;margin-bottom:14px;}
.dev-field label{font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;}
.dev-field input{border:1px solid var(--border);border-radius:8px;padding:9px 10px;background:var(--bg);color:var(--text);outline:none;font-size:13px;font-family:inherit;}
.dev-field input:focus{border-color:var(--primary);}
.dev-field-hint{margin:0;font-size:11px;color:var(--muted);}
.dev-checklist{display:flex;flex-direction:column;gap:8px;max-height:220px;overflow-y:auto;border:1px solid var(--border);border-radius:8px;padding:10px;}
.dev-checklist label{display:flex;align-items:center;gap:8px;font-size:12.5px;color:var(--text);font-weight:500;cursor:pointer;}
.dev-modal-actions{display:flex;gap:8px;margin-top:16px;justify-content:flex-end;}
.dev-modal-btn{padding:9px 18px;border-radius:8px;font-size:13px;font-weight:700;border:1px solid var(--border);background:transparent;color:var(--text);cursor:pointer;transition:all .15s;}
.dev-modal-btn:hover{background:var(--border);}
.dev-modal-btn--primary{background:var(--primary);border-color:var(--primary);color:#fff;}
.dev-modal-btn--primary:hover{background:color-mix(in srgb,var(--primary) 85%,#000);}
</style>

<p class="dev-lead">
    Manage API keys and webhooks so third-party tools can read and write data for <strong style="color:var(--text);">{{ $business->name }}</strong>. Keys and webhooks are used by the Electron desktop app and any external integrations you build.
</p>

@if(session('status'))
    <div class="dev-alert dev-alert--ok"><i class="fa fa-circle-check"></i> {{ session('status') }}</div>
@endif
@if($errors->any())
    <div class="dev-alert dev-alert--err"><i class="fa fa-triangle-exclamation"></i> {{ $errors->first() }}</div>
@endif
@if($newToken)
    <div class="dev-alert dev-alert--token">
        <strong><i class="fa fa-key"></i> Copy your new API key now</strong> — for security, it will not be shown again.
        <div class="dev-secret-row" style="margin-top:8px;">
            <input type="text" readonly value="{{ $newToken }}" id="dev-new-token-input" onclick="this.select();">
            <button type="button" class="dev-icon-btn" onclick="devCopy('dev-new-token-input')" title="Copy"><i class="fa fa-copy"></i></button>
        </div>
    </div>
@endif

{{-- ── API Keys ─────────────────────────────────────────────────────── --}}
<div class="dev-section">
    <div class="dev-section__head">
        <div class="dev-section__titles">
            <h2 class="dev-section__title">API Keys</h2>
            <p class="dev-section__desc">Tokens third-party apps use to authenticate against the API.</p>
        </div>
        <button type="button" class="dev-new-btn" onclick="devOpenModal('devNewKeyModal')">
            <i class="fa fa-plus"></i> New API Key
        </button>
    </div>
    <div class="dev-section__body">
        @if($keys->isEmpty())
            <div class="dev-empty">
                <i class="fa fa-key"></i>
                <p>No API keys yet. Create one to let an external app authenticate as this business.</p>
            </div>
        @else
            <div class="dev-grid">
                @foreach($keys as $key)
                    <div class="dev-card" id="dev-key-card-{{ $key->id }}">
                        <div class="dev-card-hdr">
                            <div style="min-width:0;flex:1;">
                                <p class="dev-card-title" title="{{ $key->name }}">{{ $key->name }}</p>
                                <p class="dev-card-sub">{{ $key->token_prefix }}</p>
                            </div>
                            <button type="button" class="dev-toggle {{ $key->is_active ? 'on' : '' }}" id="dev-key-toggle-{{ $key->id }}" onclick="devToggleKey({{ $key->id }})" title="{{ $key->is_active ? 'Active — click to deactivate' : 'Inactive — click to activate' }}"></button>
                        </div>
                        <div class="dev-card-meta">
                            <span class="dev-status {{ $key->is_active ? 'dev-status--on' : 'dev-status--off' }}" id="dev-key-status-{{ $key->id }}">{{ $key->is_active ? 'Active' : 'Inactive' }}</span>
                            @if($key->expires_at)
                                <span><i class="fa fa-hourglass-half"></i> Expires {{ $key->expires_at->toFormattedDateString() }}</span>
                            @endif
                            <span><i class="fa fa-clock-rotate-left"></i> {{ $key->last_used_at ? 'Used '.$key->last_used_at->diffForHumans() : 'Never used' }}</span>
                        </div>
                        <div class="dev-card-actions">
                            <form action="{{ route('developers.keys.destroy', $key->id) }}" method="POST" onsubmit="return confirm('Delete this API key? Any integration using it will stop working.')" style="flex:1;display:contents;">
                                @csrf @method('DELETE')
                                <button type="submit" class="dev-action dev-action--del">
                                    <i class="fa fa-trash"></i> Delete
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

{{-- ── Webhooks ─────────────────────────────────────────────────────── --}}
<div class="dev-section">
    <div class="dev-section__head">
        <div class="dev-section__titles">
            <h2 class="dev-section__title">Webhooks</h2>
            <p class="dev-section__desc">Get an HTTP POST whenever something happens in your business.</p>
        </div>
        <button type="button" class="dev-new-btn" onclick="devOpenModal('devNewWebhookModal')">
            <i class="fa fa-plus"></i> New Webhook
        </button>
    </div>
    <div class="dev-section__body">
        @if($webhooks->isEmpty())
            <div class="dev-empty">
                <i class="fa fa-tower-broadcast"></i>
                <p>No webhooks yet. Add one to notify an external URL when business events occur.</p>
            </div>
        @else
            <div class="dev-grid">
                @foreach($webhooks as $webhook)
                    <div class="dev-card" id="dev-webhook-card-{{ $webhook->id }}">
                        <div class="dev-card-hdr">
                            <div style="min-width:0;flex:1;">
                                <p class="dev-card-title" title="{{ $webhook->name }}">{{ $webhook->name }}</p>
                                <p class="dev-card-sub" title="{{ $webhook->url }}">{{ $webhook->url }}</p>
                            </div>
                            <button type="button" class="dev-toggle {{ $webhook->is_active ? 'on' : '' }}" id="dev-webhook-toggle-{{ $webhook->id }}" onclick="devToggleWebhook({{ $webhook->id }})" title="{{ $webhook->is_active ? 'Active — click to deactivate' : 'Inactive — click to activate' }}"></button>
                        </div>
                        <div class="dev-events">
                            @foreach($webhook->events as $event)
                                <span class="dev-event-chip">{{ $availableEvents[$event] ?? $event }}</span>
                            @endforeach
                        </div>
                        <div class="dev-card-meta">
                            <span class="dev-status {{ $webhook->is_active ? 'dev-status--on' : 'dev-status--off' }}" id="dev-webhook-status-{{ $webhook->id }}">{{ $webhook->is_active ? 'Active' : 'Inactive' }}</span>
                            @if($webhook->failure_count > 0)
                                <span style="color:#dc2626;"><i class="fa fa-triangle-exclamation"></i> {{ $webhook->failure_count }} failed {{ $webhook->failure_count === 1 ? 'delivery' : 'deliveries' }}</span>
                            @endif
                            <span>{{ $webhook->last_triggered_at ? 'Last sent '.$webhook->last_triggered_at->diffForHumans() : 'Never triggered' }}</span>
                        </div>
                        <div class="dev-secret-row">
                            <input type="password" readonly value="{{ $webhook->secret }}" id="dev-webhook-secret-{{ $webhook->id }}">
                            <button type="button" class="dev-icon-btn" onclick="devToggleSecretVisibility('dev-webhook-secret-{{ $webhook->id }}', this)" title="Show/hide secret"><i class="fa fa-eye"></i></button>
                            <button type="button" class="dev-icon-btn" onclick="devCopy('dev-webhook-secret-{{ $webhook->id }}')" title="Copy secret"><i class="fa fa-copy"></i></button>
                        </div>
                        <div class="dev-card-actions">
                            <form action="{{ route('developers.webhooks.regenerate-secret', $webhook->id) }}" method="POST" onsubmit="return confirm('Regenerate this webhook secret? Existing signatures will stop validating.')" style="flex:1;display:contents;">
                                @csrf
                                <button type="submit" class="dev-action">
                                    <i class="fa fa-rotate"></i> Regenerate secret
                                </button>
                            </form>
                            <form action="{{ route('developers.webhooks.destroy', $webhook->id) }}" method="POST" onsubmit="return confirm('Delete this webhook?')" style="flex:0 0 auto;display:contents;">
                                @csrf @method('DELETE')
                                <button type="submit" class="dev-action dev-action--del" style="flex:0 0 auto;width:auto;padding:6px 12px;">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

{{-- New API Key Modal --}}
<div class="dev-modal-backdrop" id="devNewKeyModal">
    <div class="dev-modal-panel">
        <h3 class="dev-modal-title"><i class="fa fa-key" style="color:var(--primary);margin-right:8px;"></i>New API Key</h3>
        <form action="{{ route('developers.keys.store') }}" method="POST">
            @csrf
            <div class="dev-field">
                <label>Name</label>
                <input type="text" name="name" placeholder="e.g. Accounting sync" required maxlength="100">
            </div>
            <div class="dev-field">
                <label>Expires on (optional)</label>
                <input type="date" name="expires_at" min="{{ now()->addDay()->toDateString() }}">
                <p class="dev-field-hint">Leave blank for a key that never expires.</p>
            </div>
            <div class="dev-modal-actions">
                <button type="button" class="dev-modal-btn" onclick="devCloseModal('devNewKeyModal')">Cancel</button>
                <button type="submit" class="dev-modal-btn dev-modal-btn--primary"><i class="fa fa-plus"></i> Create Key</button>
            </div>
        </form>
    </div>
</div>

{{-- New Webhook Modal --}}
<div class="dev-modal-backdrop" id="devNewWebhookModal">
    <div class="dev-modal-panel">
        <h3 class="dev-modal-title"><i class="fa fa-tower-broadcast" style="color:var(--primary);margin-right:8px;"></i>New Webhook</h3>
        <form action="{{ route('developers.webhooks.store') }}" method="POST">
            @csrf
            <div class="dev-field">
                <label>Name</label>
                <input type="text" name="name" placeholder="e.g. Order alerts" required maxlength="100">
            </div>
            <div class="dev-field">
                <label>URL</label>
                <input type="url" name="url" placeholder="https://example.com/webhooks/socibiz" required maxlength="500">
            </div>
            <div class="dev-field">
                <label>Events</label>
                <div class="dev-checklist">
                    @foreach($availableEvents as $eventKey => $eventLabel)
                        <label>
                            <input type="checkbox" name="events[]" value="{{ $eventKey }}">
                            {{ $eventLabel }}
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="dev-modal-actions">
                <button type="button" class="dev-modal-btn" onclick="devCloseModal('devNewWebhookModal')">Cancel</button>
                <button type="submit" class="dev-modal-btn dev-modal-btn--primary"><i class="fa fa-plus"></i> Create Webhook</button>
            </div>
        </form>
    </div>
</div>

<script>
function devOpenModal(id) { document.getElementById(id).classList.add('open'); }
function devCloseModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.dev-modal-backdrop').forEach(function (el) {
    el.addEventListener('click', function (e) { if (e.target === this) devCloseModal(this.id); });
});

function devCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function devCopy(inputId) {
    const input = document.getElementById(inputId);
    const text = input.value;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function () { window.showToast && window.showToast('Copied to clipboard.', 'success'); });
    } else {
        input.select();
        document.execCommand('copy');
        window.showToast && window.showToast('Copied to clipboard.', 'success');
    }
}

function devToggleSecretVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    const isHidden = input.type === 'password';
    input.type = isHidden ? 'text' : 'password';
    btn.querySelector('i').className = isHidden ? 'fa fa-eye-slash' : 'fa fa-eye';
}

async function devToggleKey(id) {
    const btn = document.getElementById('dev-key-toggle-' + id);
    const nextState = !btn.classList.contains('on');
    try {
        const res = await fetch(`{{ url('settings/developers/keys') }}/${id}/toggle`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': devCsrfToken() },
        });
        if (!res.ok) throw new Error('Request failed');
        btn.classList.toggle('on', nextState);
        btn.title = nextState ? 'Active — click to deactivate' : 'Inactive — click to activate';
        const status = document.getElementById('dev-key-status-' + id);
        status.textContent = nextState ? 'Active' : 'Inactive';
        status.className = 'dev-status ' + (nextState ? 'dev-status--on' : 'dev-status--off');
    } catch (e) {
        window.showToast ? window.showToast('Could not update API key status.', 'error') : alert('Could not update API key status.');
    }
}

async function devToggleWebhook(id) {
    const btn = document.getElementById('dev-webhook-toggle-' + id);
    const nextState = !btn.classList.contains('on');
    try {
        const res = await fetch(`{{ url('settings/developers/webhooks') }}/${id}/toggle`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': devCsrfToken() },
        });
        if (!res.ok) throw new Error('Request failed');
        btn.classList.toggle('on', nextState);
        btn.title = nextState ? 'Active — click to deactivate' : 'Inactive — click to activate';
        const status = document.getElementById('dev-webhook-status-' + id);
        status.textContent = nextState ? 'Active' : 'Inactive';
        status.className = 'dev-status ' + (nextState ? 'dev-status--on' : 'dev-status--off');
    } catch (e) {
        window.showToast ? window.showToast('Could not update webhook status.', 'error') : alert('Could not update webhook status.');
    }
}

@if($newToken)
window.showToast && window.showToast('API key created — copy the token now.', 'success');
@endif
</script>
@endsection
