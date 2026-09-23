@extends('theme::layouts.app', ['title' => 'Proposals', 'heading' => 'Proposals'])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.psp-back{display:inline-flex;align-items:center;gap:7px;font-size:13px;font-weight:700;color:var(--muted);text-decoration:none;margin-bottom:16px;transition:color .15s;}
.psp-back:hover{color:var(--text);}

.psp-grid{display:grid;gap:14px;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));margin-top:6px;}
.psp-card{border:1px solid var(--border);border-radius:12px;overflow:hidden;background:var(--card);transition:border-color .2s,transform .15s;display:flex;flex-direction:column;}
.psp-card:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));transform:translateY(-2px);}
.psp-card__top{padding:16px 16px 12px;background:linear-gradient(135deg,color-mix(in srgb,var(--primary) 8%,var(--bg)) 0%,color-mix(in srgb,var(--primary) 16%,var(--bg)) 100%);}
.psp-card__icon{width:38px;height:38px;border-radius:10px;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:16px;margin-bottom:10px;}
.psp-card__title{font-size:14px;font-weight:800;color:var(--text);margin:0 0 3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.psp-card__client{font-size:12px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.psp-card__body{padding:12px 16px;flex:1;display:flex;flex-direction:column;gap:6px;}
.psp-card__meta{font-size:11px;color:var(--muted);display:flex;align-items:center;gap:6px;}
.psp-card__actions{display:flex;gap:6px;padding:0 16px 14px;}
.psp-card__btn{flex:1;padding:7px 10px;border-radius:7px;border:1px solid var(--border);background:transparent;color:var(--text);font-size:12px;font-weight:700;cursor:pointer;text-align:center;transition:all .15s;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:5px;}
.psp-card__btn:hover{background:var(--primary);border-color:var(--primary);color:#fff;}
.psp-card__btn--del{flex:0 0 auto;width:auto;padding:7px 12px;color:var(--danger,#ef4444);}
.psp-card__btn--del:hover{background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.4);color:#ef4444;}

.psp-empty{text-align:center;padding:52px 32px 44px;border:1.5px dashed var(--border);border-radius:16px;background:var(--card);}
.psp-empty-icon{width:80px;height:80px;border-radius:20px;margin:0 auto 20px;background:color-mix(in srgb,var(--primary) 10%,var(--card));border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:32px;color:var(--primary);}
.psp-empty h3{margin:0 0 8px;font-size:18px;font-weight:800;letter-spacing:-.02em;color:var(--text);}
.psp-empty-sub{margin:0 auto 24px;font-size:13px;color:var(--muted);max-width:420px;line-height:1.6;}

.psp-modal-backdrop{position:fixed;inset:0;z-index:1050;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.55);backdrop-filter:blur(3px);opacity:0;visibility:hidden;transition:all .2s;}
.psp-modal-backdrop.open{opacity:1;visibility:visible;}
.psp-modal-panel{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:24px;width:min(100% - 32px,520px);box-shadow:0 24px 60px rgba(0,0,0,.3);transform:scale(.95);transition:transform .2s;}
.psp-modal-backdrop.open .psp-modal-panel{transform:scale(1);}
.psp-modal-title{font-size:17px;font-weight:800;letter-spacing:-.02em;margin:0 0 6px;display:flex;align-items:center;gap:8px;}
.psp-modal-sub{font-size:12px;color:var(--muted);margin:0 0 18px;line-height:1.5;}
.psp-field{margin-bottom:14px;}
.psp-field label{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:6px;}
.psp-field input,.psp-field textarea{width:100%;box-sizing:border-box;background:var(--bg);border:1px solid var(--border);color:var(--text);border-radius:8px;padding:9px 11px;font-size:13px;font-family:inherit;outline:none;}
.psp-field input:focus,.psp-field textarea:focus{border-color:var(--primary);}
.psp-field textarea{resize:vertical;min-height:80px;}
.psp-modal-actions{display:flex;gap:8px;margin-top:16px;justify-content:flex-end;}
.psp-modal-btn{padding:8px 18px;border-radius:8px;font-size:13px;font-weight:700;border:1px solid var(--border);background:transparent;color:var(--text);cursor:pointer;transition:all .15s;}
.psp-modal-btn:hover{background:var(--border);}
.psp-modal-btn--primary{background:var(--primary);border-color:var(--primary);color:#fff;}
.psp-modal-btn--primary:hover{background:color-mix(in srgb,var(--primary) 85%,#000);}
.psp-modal-btn--primary:disabled{opacity:.6;cursor:not-allowed;}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:18px 20px;">

    <a href="{{ route('designstudio.index') }}" class="psp-back">
        <i class="fa fa-arrow-left"></i> Design Studio
    </a>

    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:18px;">
        <div>
            <h2 style="margin:0 0 4px;font-size:18px;font-weight:800;letter-spacing:-.02em;">Proposals</h2>
            <p style="margin:0;font-size:13px;color:var(--muted);">AI-assisted sales proposals — cover, summary, services, pricing and next steps, ready to send or link to an invoice.</p>
        </div>
        <button class="linkbtn" style="padding:9px 18px;font-size:13px;display:inline-flex;align-items:center;gap:7px;" onclick="pspOpenModal()">
            <i class="fa fa-plus"></i> New Proposal
        </button>
    </div>

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;margin-bottom:16px;">{{ session('status') }}</div>
    @endif
    @if(session('proposal_error'))
        <div class="pcat-banner pcat-banner--err" style="font-weight:600;margin-bottom:16px;">{{ session('proposal_error') }}</div>
    @endif

    @if($groups->isEmpty())
        <div class="psp-empty">
            <div class="psp-empty-icon"><i class="fa fa-file-invoice" aria-hidden="true"></i></div>
            <h3>No proposals yet</h3>
            <p class="psp-empty-sub">Create an AI-filled sales proposal in seconds — cover page, executive summary, services, timeline, pricing and next steps, all generated for you.</p>
            <button class="linkbtn" style="display:inline-flex;align-items:center;gap:7px;padding:10px 20px;" onclick="pspOpenModal()">
                <i class="fa fa-plus"></i> Create First Proposal
            </button>
        </div>
    @else
        <div class="psp-grid">
            @foreach($groups as $g)
                <div class="psp-card">
                    <div class="psp-card__top">
                        <div class="psp-card__icon"><i class="fa fa-file-invoice" aria-hidden="true"></i></div>
                        <p class="psp-card__title">{{ $g['title'] ?: 'Untitled Proposal' }}</p>
                        @if($g['client'])
                            <p class="psp-card__client">{{ $g['client'] }}</p>
                        @endif
                    </div>
                    <div class="psp-card__body">
                        <span class="psp-card__meta"><i class="fa fa-layer-group" aria-hidden="true"></i> {{ $g['page_count'] }} {{ Str::plural('page', $g['page_count']) }}</span>
                        <span class="psp-card__meta"><i class="fa fa-clock" aria-hidden="true"></i> Updated {{ $g['updated_at']?->diffForHumans() ?? '—' }}</span>
                    </div>
                    <div class="psp-card__actions">
                        <a href="{{ route('designstudio.proposals.show', $g['group']) }}" class="psp-card__btn">
                            <i class="fa fa-arrow-right" aria-hidden="true"></i> Open
                        </a>
                        <form action="{{ route('designstudio.proposals.destroy', $g['group']) }}" method="POST" onsubmit="return confirm('Delete this proposal and all its pages?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="psp-card__btn psp-card__btn--del">
                                <i class="fa fa-trash" aria-hidden="true"></i>
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

</div>

{{-- New Proposal Modal --}}
<div class="psp-modal-backdrop" id="pspModal">
    <div class="psp-modal-panel">
        <h3 class="psp-modal-title"><i class="fa fa-wand-magic-sparkles" aria-hidden="true" style="color:var(--primary);"></i> New Proposal</h3>
        <p class="psp-modal-sub">Describe the project and our AI will draft a full 6-page proposal — you can edit every page afterward.</p>

        <form action="{{ route('designstudio.proposals.store') }}" method="POST" id="pspForm">
            @csrf
            <div class="psp-field">
                <label for="pspTitle">Project Title</label>
                <input type="text" id="pspTitle" name="title" maxlength="100" required placeholder="e.g. Website Redesign Project">
            </div>
            <div class="psp-field">
                <label for="pspClient">Client (optional)</label>
                <input type="text" id="pspClient" name="client" maxlength="100" placeholder="e.g. Acme Corporation">
            </div>
            <div class="psp-field">
                <label for="pspDescription">Project Description</label>
                <textarea id="pspDescription" name="description" maxlength="1500" required placeholder="Briefly describe the scope, goals and deliverables — the more detail, the better the AI-generated content."></textarea>
            </div>
            <div class="psp-modal-actions">
                <button type="button" class="psp-modal-btn" onclick="pspCloseModal()">Cancel</button>
                <button type="submit" class="psp-modal-btn psp-modal-btn--primary" id="pspSubmitBtn">
                    <i class="fa fa-wand-magic-sparkles" aria-hidden="true"></i> Create & Generate
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function pspOpenModal() { document.getElementById('pspModal').classList.add('open'); }
function pspCloseModal() { document.getElementById('pspModal').classList.remove('open'); }
document.getElementById('pspModal').addEventListener('click', function(e) {
    if (e.target === this) pspCloseModal();
});
document.getElementById('pspForm').addEventListener('submit', function() {
    var btn = document.getElementById('pspSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Generating…';
});
</script>

@endsection
