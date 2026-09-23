@extends('theme::layouts.app', ['title' => $title ?: 'Proposal', 'heading' => $title ?: 'Proposal'])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.psh-back{display:inline-flex;align-items:center;gap:7px;font-size:13px;font-weight:700;color:var(--muted);text-decoration:none;margin-bottom:16px;transition:color .15s;}
.psh-back:hover{color:var(--text);}

.psh-header{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:18px;}
.psh-header h2{margin:0 0 4px;font-size:19px;font-weight:800;letter-spacing:-.02em;}
.psh-header p{margin:0;font-size:13px;color:var(--muted);}
.psh-actions{display:flex;gap:8px;flex-wrap:wrap;}
.psh-btn{padding:8px 16px;border-radius:8px;font-size:12.5px;font-weight:700;border:1px solid var(--border);background:transparent;color:var(--text);cursor:pointer;transition:all .15s;text-decoration:none;display:inline-flex;align-items:center;gap:6px;}
.psh-btn:hover{background:var(--border);}
.psh-btn--primary{background:var(--primary);border-color:var(--primary);color:#fff;}
.psh-btn--primary:hover{background:color-mix(in srgb,var(--primary) 85%,#000);color:#fff;}
.psh-btn--danger{color:var(--danger,#ef4444);}
.psh-btn--danger:hover{background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.4);color:#ef4444;}

.psh-panel{border:1px solid var(--border);border-radius:14px;background:var(--card);padding:16px 18px;margin-bottom:20px;}
.psh-panel__title{font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin:0 0 12px;display:flex;align-items:center;gap:8px;}
.psh-invoice-row{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.psh-invoice-row select{flex:1;min-width:220px;background:var(--bg);border:1px solid var(--border);color:var(--text);border-radius:8px;padding:8px 10px;font-size:13px;outline:none;}
.psh-invoice-row select:focus{border-color:var(--primary);}
.psh-linked{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.psh-linked-badge{display:inline-flex;align-items:center;gap:7px;padding:7px 12px;border-radius:8px;background:color-mix(in srgb,var(--primary) 12%,transparent);color:var(--primary);font-size:12.5px;font-weight:700;}

.psh-grid{display:grid;gap:14px;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));margin-top:6px;}
.psh-card{border:1px solid var(--border);border-radius:12px;overflow:hidden;background:var(--card);transition:border-color .2s,transform .15s;}
.psh-card:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));transform:translateY(-2px);}
.psh-card__preview{height:110px;display:flex;align-items:center;justify-content:center;font-size:30px;color:var(--primary);background:linear-gradient(135deg,color-mix(in srgb,var(--primary) 8%,var(--bg)) 0%,color-mix(in srgb,var(--primary) 18%,var(--bg)) 100%);position:relative;}
.psh-card__num{position:absolute;top:8px;left:10px;font-size:10px;font-weight:800;color:color-mix(in srgb,var(--primary) 60%,var(--muted));background:var(--card);border-radius:5px;padding:2px 7px;}
.psh-card__info{padding:10px 12px;}
.psh-card__title{font-size:13px;font-weight:700;color:var(--text);margin:0 0 2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.psh-card__meta{font-size:11px;color:var(--muted);}
.psh-card__actions{display:flex;gap:6px;padding:0 12px 10px;margin-top:2px;}
.psh-card__btn{flex:1;padding:6px 8px;border-radius:6px;border:1px solid var(--border);background:transparent;color:var(--muted);font-size:11px;font-weight:700;cursor:pointer;text-align:center;transition:all .15s;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:4px;}
.psh-card__btn:hover{background:var(--primary);border-color:var(--primary);color:#fff;}

.psh-new-card{border:1.5px dashed var(--border);border-radius:12px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;padding:24px;cursor:pointer;transition:border-color .2s,background .15s;min-height:160px;background:transparent;width:100%;font-family:inherit;}
.psh-new-card:hover{border-color:color-mix(in srgb,var(--primary) 60%,var(--border));background:color-mix(in srgb,var(--primary) 4%,transparent);}
.psh-new-card__icon{width:40px;height:40px;border-radius:12px;background:color-mix(in srgb,var(--primary) 12%,transparent);color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:16px;}
.psh-new-card__label{font-size:12px;font-weight:700;color:var(--muted);}

/* Regenerate modal — same pattern as the list page's create modal */
.psh-modal-backdrop{position:fixed;inset:0;z-index:1050;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.55);backdrop-filter:blur(3px);opacity:0;visibility:hidden;transition:all .2s;}
.psh-modal-backdrop.open{opacity:1;visibility:visible;}
.psh-modal-panel{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:24px;width:min(100% - 32px,520px);box-shadow:0 24px 60px rgba(0,0,0,.3);transform:scale(.95);transition:transform .2s;}
.psh-modal-backdrop.open .psh-modal-panel{transform:scale(1);}
.psh-modal-title{font-size:17px;font-weight:800;letter-spacing:-.02em;margin:0 0 6px;display:flex;align-items:center;gap:8px;}
.psh-modal-sub{font-size:12px;color:var(--muted);margin:0 0 18px;line-height:1.5;}
.psh-field{margin-bottom:14px;}
.psh-field label{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:6px;}
.psh-field input,.psh-field textarea{width:100%;box-sizing:border-box;background:var(--bg);border:1px solid var(--border);color:var(--text);border-radius:8px;padding:9px 11px;font-size:13px;font-family:inherit;outline:none;}
.psh-field input:focus,.psh-field textarea:focus{border-color:var(--primary);}
.psh-field textarea{resize:vertical;min-height:80px;}
.psh-modal-actions{display:flex;gap:8px;margin-top:16px;justify-content:flex-end;}
.psh-modal-btn{padding:8px 18px;border-radius:8px;font-size:13px;font-weight:700;border:1px solid var(--border);background:transparent;color:var(--text);cursor:pointer;transition:all .15s;}
.psh-modal-btn:hover{background:var(--border);}
.psh-modal-btn--primary{background:var(--primary);border-color:var(--primary);color:#fff;}
.psh-modal-btn--primary:hover{background:color-mix(in srgb,var(--primary) 85%,#000);}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:18px 20px;">

    <a href="{{ route('designstudio.proposals.index') }}" class="psh-back">
        <i class="fa fa-arrow-left"></i> Proposals
    </a>

    <div class="psh-header">
        <div>
            <h2>{{ $title ?: 'Untitled Proposal' }}</h2>
            <p>@if($client){{ $client }} &bull; @endif{{ $pages->count() }} {{ Str::plural('page', $pages->count()) }}</p>
        </div>
        <div class="psh-actions">
            <button class="psh-btn psh-btn--primary" onclick="pshOpenModal()">
                <i class="fa fa-wand-magic-sparkles" aria-hidden="true"></i> Regenerate with AI
            </button>
            <form action="{{ route('designstudio.proposals.destroy', $group) }}" method="POST" onsubmit="return confirm('Delete this proposal and all its pages? This cannot be undone.')">
                @csrf @method('DELETE')
                <button type="submit" class="psh-btn psh-btn--danger">
                    <i class="fa fa-trash" aria-hidden="true"></i> Delete
                </button>
            </form>
        </div>
    </div>

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;margin-bottom:16px;">{{ session('status') }}</div>
    @endif
    @if(session('proposal_error'))
        <div class="pcat-banner pcat-banner--err" style="font-weight:600;margin-bottom:16px;">{{ session('proposal_error') }}</div>
    @endif

    {{-- ── Link to invoice ── --}}
    <div class="psh-panel">
        <p class="psh-panel__title"><i class="fa fa-link" aria-hidden="true"></i> Link to Invoice</p>

        @if($linkedInvoice)
            <div class="psh-linked">
                <span class="psh-linked-badge">
                    <i class="fa fa-file-invoice-dollar" aria-hidden="true"></i>
                    {{ $linkedInvoice->invoice_number }}
                </span>
                @if(Route::has('sales.invoices.show'))
                    <a href="{{ route('sales.invoices.show', $linkedInvoice) }}" class="psh-btn" style="padding:6px 12px;">
                        <i class="fa fa-up-right-from-square" aria-hidden="true"></i> View Invoice
                    </a>
                @endif
                <form action="{{ route('designstudio.proposals.link-invoice', $group) }}" method="POST">
                    @csrf
                    <input type="hidden" name="invoice_id" value="">
                    <button type="submit" class="psh-btn" style="padding:6px 12px;">
                        <i class="fa fa-link-slash" aria-hidden="true"></i> Unlink
                    </button>
                </form>
            </div>
        @else
            <form action="{{ route('designstudio.proposals.link-invoice', $group) }}" method="POST" class="psh-invoice-row">
                @csrf
                <select name="invoice_id" required>
                    <option value="">Select an invoice…</option>
                    @foreach($invoices as $inv)
                        <option value="{{ $inv->id }}">
                            {{ $inv->invoice_number }} — {{ $inv->customer?->name ?? 'No customer' }} — {{ number_format((float) $inv->total, 2) }}
                        </option>
                    @endforeach
                </select>
                <button type="submit" class="psh-btn psh-btn--primary" style="padding:8px 16px;">
                    <i class="fa fa-link" aria-hidden="true"></i> Link
                </button>
            </form>
            @if($invoices->isEmpty())
                <p style="margin:10px 0 0;font-size:12px;color:var(--muted);">No invoices found for this business yet.</p>
            @endif
        @endif
    </div>

    {{-- ── Pages ── --}}
    <p style="margin-bottom:10px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);display:flex;align-items:center;gap:8px;">
        <i class="fa fa-images" aria-hidden="true"></i> Pages
        <span style="flex:1;height:1px;background:var(--border);display:block;"></span>
    </p>

    <div class="psh-grid">
        @foreach($pages as $i => $page)
            <div class="psh-card">
                <div class="psh-card__preview">
                    <span class="psh-card__num">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span>
                    <i class="fa {{ $page->canvas_json ? 'fa-file-lines' : 'fa-file' }}" aria-hidden="true"></i>
                </div>
                <div class="psh-card__info">
                    <p class="psh-card__title">{{ Str::afterLast($page->title, ' — ') }}</p>
                    <p class="psh-card__meta">{{ $page->updated_at->diffForHumans() }}</p>
                </div>
                <div class="psh-card__actions">
                    <a href="{{ route('designstudio.editor.edit', $page) }}" class="psh-card__btn">
                        <i class="fa fa-pen" aria-hidden="true"></i> Edit
                    </a>
                </div>
            </div>
        @endforeach

        <form action="{{ route('designstudio.proposals.add-page', $group) }}" method="POST" style="display:contents;">
            @csrf
            <button type="submit" class="psh-new-card">
                <div class="psh-new-card__icon"><i class="fa fa-plus" aria-hidden="true"></i></div>
                <span class="psh-new-card__label">Add Page</span>
            </button>
        </form>
    </div>

</div>

{{-- Regenerate with AI Modal --}}
<div class="psh-modal-backdrop" id="pshModal">
    <div class="psh-modal-panel">
        <h3 class="psh-modal-title"><i class="fa fa-wand-magic-sparkles" aria-hidden="true" style="color:var(--primary);"></i> Regenerate with AI</h3>
        <p class="psh-modal-sub">Re-describe the project and every page's content will be replaced with freshly generated AI content. Layout edits made in the page editor will be overwritten.</p>

        <form action="{{ route('designstudio.proposals.ai-fill', $group) }}" method="POST" id="pshForm">
            @csrf
            <div class="psh-field">
                <label for="pshTitle">Project Title</label>
                <input type="text" id="pshTitle" name="title" maxlength="120" required value="{{ $title }}">
            </div>
            <div class="psh-field">
                <label for="pshClient">Client (optional)</label>
                <input type="text" id="pshClient" name="client" maxlength="100" value="{{ $client }}">
            </div>
            <div class="psh-field">
                <label for="pshDescription">Project Description</label>
                <textarea id="pshDescription" name="description" maxlength="1500" required placeholder="Briefly describe the scope, goals and deliverables…"></textarea>
            </div>
            <div class="psh-modal-actions">
                <button type="button" class="psh-modal-btn" onclick="pshCloseModal()">Cancel</button>
                <button type="submit" class="psh-modal-btn psh-modal-btn--primary" id="pshSubmitBtn">
                    <i class="fa fa-wand-magic-sparkles" aria-hidden="true"></i> Regenerate
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function pshOpenModal() { document.getElementById('pshModal').classList.add('open'); }
function pshCloseModal() { document.getElementById('pshModal').classList.remove('open'); }
document.getElementById('pshModal').addEventListener('click', function(e) {
    if (e.target === this) pshCloseModal();
});
document.getElementById('pshForm').addEventListener('submit', function() {
    var btn = document.getElementById('pshSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Generating…';
});
</script>

@endsection
