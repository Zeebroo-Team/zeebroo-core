@extends('theme::layouts.app', ['title' => 'Automations', 'heading' => 'Automations'])

@section('content')
<style>
.ae-hub-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:20px;}
.ae-hub-toolbar p{margin:0;font-size:13px;line-height:1.45;color:var(--muted);}
.ae-hub-new-btn{display:inline-flex;align-items:center;gap:8px;padding:9px 18px;border-radius:10px;background:var(--primary);color:#fff;font-size:13px;font-weight:800;border:none;cursor:pointer;text-decoration:none;transition:background .15s;}
.ae-hub-new-btn:hover{background:color-mix(in srgb,var(--primary) 85%,#000);color:#fff;}

.ae-hub-grid{display:grid;gap:14px;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));margin-top:8px;}
.ae-hub-card{border:1px solid var(--border);border-radius:12px;background:var(--card);padding:16px;display:flex;flex-direction:column;gap:10px;transition:border-color .2s,transform .15s;}
.ae-hub-card:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));transform:translateY(-2px);}
.ae-hub-card-hdr{display:flex;align-items:flex-start;gap:10px;}
.ae-hub-card-ico{width:38px;height:38px;border-radius:9px;flex-shrink:0;background:color-mix(in srgb,#f59e0b 15%,var(--card));border:1px solid var(--border);color:#c2740c;display:flex;align-items:center;justify-content:center;font-size:15px;}
.ae-hub-card-title{font-size:14px;font-weight:800;color:var(--text);margin:0 0 3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.ae-hub-card-trigger{font-size:11px;color:var(--muted);}
.ae-hub-card-desc{font-size:12px;color:var(--muted);line-height:1.5;min-height:18px;}
.ae-hub-card-meta{display:flex;align-items:center;gap:10px;font-size:11px;color:var(--muted);}
.ae-hub-status{display:inline-flex;align-items:center;gap:5px;font-size:10.5px;font-weight:700;padding:2px 9px;border-radius:20px;}
.ae-hub-status--on{background:#d1fae5;color:#065f46;}
.ae-hub-status--off{background:color-mix(in srgb,var(--text) 8%,var(--card));color:var(--muted);}
.ae-hub-card-actions{display:flex;gap:6px;margin-top:auto;padding-top:4px;}
.ae-hub-action{flex:1;padding:6px 8px;border-radius:6px;border:1px solid var(--border);background:transparent;color:var(--muted);font-size:11.5px;font-weight:700;cursor:pointer;text-align:center;transition:all .15s;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:5px;}
.ae-hub-action:hover{background:var(--border);color:var(--text);}
.ae-hub-action--del{color:#ef4444;}
.ae-hub-action--del:hover{background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.4);}
.ae-hub-toggle{position:relative;width:34px;height:19px;flex-shrink:0;border-radius:20px;border:none;background:var(--border);cursor:pointer;transition:background .15s;}
.ae-hub-toggle::after{content:'';position:absolute;top:2px;left:2px;width:15px;height:15px;border-radius:50%;background:#fff;transition:transform .15s;}
.ae-hub-toggle.on{background:#16a34a;}
.ae-hub-toggle.on::after{transform:translateX(15px);}

.ae-empty{text-align:center;padding:52px 32px 44px;border:1.5px dashed var(--border);border-radius:16px;background:var(--card);}
.ae-empty-icon{width:80px;height:80px;border-radius:20px;margin:0 auto 20px;background:color-mix(in srgb,var(--text) 7%,var(--card));border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:32px;color:var(--muted);}
.ae-empty h3{margin:0 0 8px;font-size:18px;font-weight:800;letter-spacing:-.02em;color:var(--text);}
.ae-empty-sub{margin:0 auto 24px;font-size:13px;color:var(--muted);max-width:420px;line-height:1.6;}

/* New automation modal */
.ae-modal-backdrop{position:fixed;inset:0;z-index:1050;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.55);backdrop-filter:blur(3px);opacity:0;visibility:hidden;transition:all .2s;}
.ae-modal-backdrop.open{opacity:1;visibility:visible;}
.ae-modal-panel{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:24px;width:min(100% - 32px,480px);box-shadow:0 24px 60px rgba(0,0,0,.3);transform:scale(.95);transition:transform .2s;}
.ae-modal-backdrop.open .ae-modal-panel{transform:scale(1);}
.ae-modal-title{font-size:17px;font-weight:800;letter-spacing:-.02em;margin:0 0 18px;}
.ae-field{display:flex;flex-direction:column;gap:5px;margin-bottom:14px;}
.ae-field label{font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;}
.ae-field input,.ae-field select,.ae-field textarea{border:1px solid var(--border);border-radius:8px;padding:9px 10px;background:var(--bg);color:var(--text);outline:none;font-size:13px;font-family:inherit;resize:vertical;}
.ae-field input:focus,.ae-field select:focus,.ae-field textarea:focus{border-color:var(--primary);}
.ae-modal-actions{display:flex;gap:8px;margin-top:16px;justify-content:flex-end;}
.ae-modal-btn{padding:9px 18px;border-radius:8px;font-size:13px;font-weight:700;border:1px solid var(--border);background:transparent;color:var(--text);cursor:pointer;transition:all .15s;}
.ae-modal-btn:hover{background:var(--border);}
.ae-modal-btn--primary{background:var(--primary);border-color:var(--primary);color:#fff;}
.ae-modal-btn--primary:hover{background:color-mix(in srgb,var(--primary) 85%,#000);}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:18px 20px;">

    <div class="ae-hub-toolbar">
        <div>
            <h2 style="margin:0 0 4px;font-size:18px;font-weight:800;letter-spacing:-.02em;">Automations</h2>
            <p>Visual workflow automation for <strong style="color:var(--text);">{{ $business->name }}</strong> — build triggers, conditions, and actions without code.</p>
        </div>
        <button class="ae-hub-new-btn" onclick="aeOpenNewModal()">
            <i class="fa fa-plus" aria-hidden="true"></i> New Automation
        </button>
    </div>

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;margin-bottom:16px;">{{ session('status') }}</div>
    @endif

    @if($flows->isEmpty())
        <div class="ae-empty">
            <div class="ae-empty-icon"><i class="fa fa-bolt" aria-hidden="true"></i></div>
            <h3>No automations yet</h3>
            <p class="ae-empty-sub">Automatically send emails, create CRM tasks and leads, call webhooks, or send AI-written messages whenever something happens in your business — a sale, a new customer, a low-stock alert and more.</p>
            <button class="ae-hub-new-btn" onclick="aeOpenNewModal()" style="display:inline-flex;">
                <i class="fa fa-plus" aria-hidden="true"></i> Create First Automation
            </button>
        </div>
    @else
        <div class="ae-hub-grid">
            @foreach($flows as $flow)
                <div class="ae-hub-card" id="ae-card-{{ $flow->id }}">
                    <div class="ae-hub-card-hdr">
                        <div class="ae-hub-card-ico"><i class="fa fa-bolt" aria-hidden="true"></i></div>
                        <div style="flex:1;min-width:0;">
                            <p class="ae-hub-card-title" title="{{ $flow->name }}">{{ $flow->name }}</p>
                            <p class="ae-hub-card-trigger">{{ $flow->trigger_type ? ($triggerLabels[$flow->trigger_type] ?? $flow->trigger_type) : 'No trigger set' }}</p>
                        </div>
                        <button type="button" class="ae-hub-toggle {{ $flow->is_active ? 'on' : '' }}" id="ae-toggle-{{ $flow->id }}" onclick="aeToggleActive({{ $flow->id }})" title="{{ $flow->is_active ? 'Active — click to deactivate' : 'Inactive — click to activate' }}"></button>
                    </div>
                    <p class="ae-hub-card-desc">{{ $flow->description ?: 'No description.' }}</p>
                    <div class="ae-hub-card-meta">
                        <span><i class="fa fa-rotate-right" aria-hidden="true"></i> {{ $flow->run_count }} runs</span>
                        <span>{{ $flow->last_run_at ? 'Last run '.$flow->last_run_at->diffForHumans() : 'Never run' }}</span>
                    </div>
                    <div class="ae-hub-card-actions">
                        <a href="{{ route('automations.edit', $flow) }}" class="ae-hub-action">
                            <i class="fa fa-diagram-project" aria-hidden="true"></i> Open Builder
                        </a>
                        <form action="{{ route('automations.destroy', $flow) }}" method="POST" onsubmit="return confirm('Delete this automation? This cannot be undone.')" style="flex:1;display:contents;">
                            @csrf @method('DELETE')
                            <button type="submit" class="ae-hub-action ae-hub-action--del" style="flex:0 0 auto;width:auto;padding:6px 12px;">
                                <i class="fa fa-trash" aria-hidden="true"></i>
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

{{-- New Automation Modal --}}
<div class="ae-modal-backdrop" id="aeNewModal">
    <div class="ae-modal-panel">
        <h3 class="ae-modal-title"><i class="fa fa-bolt" aria-hidden="true" style="color:var(--primary);margin-right:8px;"></i>New Automation</h3>

        <form action="{{ route('automations.store') }}" method="POST">
            @csrf
            <div class="ae-field">
                <label>Name</label>
                <input type="text" name="name" placeholder="e.g. Welcome new customers" required maxlength="120">
            </div>
            <div class="ae-field">
                <label>Description (optional)</label>
                <textarea name="description" rows="2" placeholder="What does this automation do?" maxlength="500"></textarea>
            </div>
            <div class="ae-field">
                <label>Trigger Category</label>
                <select id="aeNewTriggerCat">
                    <option value="">— choose later in the builder —</option>
                    @foreach($triggerGroups as $group => $items)
                        <option value="{{ $group }}">{{ $group }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ae-field">
                <label>Trigger Event</label>
                <select name="trigger_type" id="aeNewTriggerEvent" disabled>
                    <option value="">— choose a category first —</option>
                </select>
            </div>
            <div class="ae-modal-actions">
                <button type="button" class="ae-modal-btn" onclick="aeCloseNewModal()">Cancel</button>
                <button type="submit" class="ae-modal-btn ae-modal-btn--primary">
                    <i class="fa fa-arrow-right" aria-hidden="true"></i> Create &amp; Open Builder
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const AE_TRIGGER_GROUPS = @json($triggerGroups);

function aeOpenNewModal() { document.getElementById('aeNewModal').classList.add('open'); }
function aeCloseNewModal() { document.getElementById('aeNewModal').classList.remove('open'); }
document.getElementById('aeNewModal').addEventListener('click', function (e) {
    if (e.target === this) aeCloseNewModal();
});

document.getElementById('aeNewTriggerCat').addEventListener('change', function (e) {
    const sel = document.getElementById('aeNewTriggerEvent');
    const items = AE_TRIGGER_GROUPS[e.target.value] || null;
    if (!items) {
        sel.disabled = true;
        sel.innerHTML = '<option value="">— choose a category first —</option>';
        return;
    }
    sel.disabled = false;
    sel.innerHTML = '<option value="">— choose —</option>' +
        Object.entries(items).map(([k, v]) => `<option value="${k}">${v}</option>`).join('');
});

function aeCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function aeToggleActive(id) {
    const btn = document.getElementById('ae-toggle-' + id);
    const nextState = !btn.classList.contains('on');
    try {
        const res = await fetch(`/automations/${id}`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': aeCsrfToken(),
            },
            body: JSON.stringify({ is_active: nextState }),
        });
        if (!res.ok) throw new Error('Request failed');
        btn.classList.toggle('on', nextState);
        btn.title = nextState ? 'Active — click to deactivate' : 'Inactive — click to activate';
    } catch (e) {
        alert('Could not update automation status.');
    }
}
</script>
@endsection
