@extends('theme::layouts.app', ['title' => 'Branches', 'heading' => 'Branch management'])

@section('content')
<style>
.branch-page{max-width:100%;margin:0;}
.branch-card{padding:14px 16px;margin-bottom:12px;border:1px solid var(--border);border-radius:12px;background:var(--card);}
.branch-form-grid{display:grid;gap:10px;}@media (min-width:720px){.branch-form-grid--2{grid-template-columns:repeat(2,minmax(0,1fr));gap:12px 16px}}
.branch-field label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:5px;}
.branch-field input,.branch-field textarea{width:100%;box-sizing:border-box;padding:9px 10px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);}
.branch-field textarea{min-height:70px;line-height:1.45;resize:vertical;font-family:inherit;}
.branch-table-wrap{margin-top:12px;border:1px solid var(--border);border-radius:11px;overflow:auto;}
.branch-table{width:100%;border-collapse:collapse;font-size:13px;min-width:520px;}
.branch-table th{text-align:left;padding:9px 12px;background:color-mix(in srgb,var(--card) 92%,transparent);font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);border-bottom:1px solid var(--border);}
.branch-table td{padding:10px 12px;border-bottom:1px solid color-mix(in srgb,var(--border) 80%,transparent);vertical-align:top;}
.branch-table tr:last-child td{border-bottom:none;}
.branch-badge{font-size:11px;font-weight:700;padding:3px 8px;border-radius:999px;border:1px solid var(--border);display:inline-block;}
.branch-badge--on{border-color:color-mix(in srgb,#22c55e 45%,var(--border));background:color-mix(in srgb,#22c55e 12%,transparent);color:color-mix(in srgb,#bbf7d0 70%,var(--text));}
.branch-badge--off{opacity:.8;color:var(--muted);}
.branch-actions{display:flex;flex-wrap:wrap;gap:6px;}
.branch-link{color:var(--primary);font-weight:600;text-decoration:none;font-size:12px;} .branch-link:hover{text-decoration:underline;}
.branch-btn-del{padding:6px 9px;font-size:11px;font-weight:600;border-radius:7px;border:1px solid color-mix(in srgb,#ef4444 42%,var(--border));background:transparent;color:#f97373;cursor:pointer;}
:is(html[data-theme="light"],html[data-theme="light_blue"]) .branch-btn-del{color:#dc2626;}
.branch-toolbar{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px;}
.branch-modal{
    position:fixed;inset:0;z-index:120;display:flex;justify-content:center;align-items:flex-start;
    padding:max(12px,2.5vh) max(14px,env(safe-area-inset-right)) calc(14px + env(safe-area-inset-bottom)) max(14px,env(safe-area-inset-left));
    overflow:auto;box-sizing:border-box;
    opacity:0;visibility:hidden;pointer-events:none;
    transition:opacity .22s ease,visibility .22s ease;
}
.branch-modal.branch-modal--open{opacity:1;visibility:visible;pointer-events:auto;}
.branch-modal__backdrop{position:fixed;inset:0;z-index:0;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);}
:is(html[data-theme="light"],html[data-theme="light_blue"]) .branch-modal__backdrop{background:rgba(17,24,39,.38);}
.branch-modal__panel{
    position:relative;z-index:1;box-sizing:border-box;width:100%;max-width:560px;
    flex:0 1 auto;
    max-height:min(94vh,calc(100dvh - 48px));
    display:flex;flex-direction:column;
    border-radius:14px;border:1px solid var(--border);background:var(--card);
    box-shadow:0 20px 48px rgba(0,0,0,.32);margin:auto;
}
.branch-modal__head{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:11px 14px;border-bottom:1px solid var(--border);flex-shrink:0;background:color-mix(in srgb,var(--card) 95%,transparent);}
.branch-modal__head h2{margin:0;font-size:15px;font-weight:800;letter-spacing:-.02em;}
.branch-modal__close{
    width:32px;height:32px;display:grid;place-items:center;padding:0;border:1px solid var(--border);
    border-radius:9px;background:color-mix(in srgb,var(--card) 88%,transparent);color:var(--text);cursor:pointer;font-size:17px;line-height:1;
}
.branch-modal__close:hover{border-color:color-mix(in srgb,var(--primary) 40%,var(--border));background:color-mix(in srgb,var(--primary) 8%,transparent);}
.branch-modal__body{padding:14px 14px 16px;overflow:auto;overscroll-behavior:contain;-webkit-overflow-scrolling:touch;}
.branch-modal__banner{margin:0 0 12px;padding:10px 12px;border-radius:10px;border:1px solid color-mix(in srgb,#f87171 40%,var(--border));background:color-mix(in srgb,#f87171 8%,transparent);font-size:13px;color:var(--text);}
html.branch-modal-open-html,html.branch-modal-open-html body{overflow:hidden;}
.branch-active-row{display:flex;align-items:center;justify-content:space-between;gap:14px;width:100%;padding:11px 14px;box-sizing:border-box;border-radius:10px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 94%,transparent);}
.branch-active-row__lbl{margin:0;font-size:13px;font-weight:600;color:var(--text);cursor:pointer;}
.branch-switch{position:relative;display:inline-block;width:46px;height:26px;flex-shrink:0;}
.branch-switch input{opacity:0;width:0;height:0;margin:0;position:absolute;}
.branch-switch-slider{position:absolute;inset:0;cursor:pointer;background:#475569;border-radius:999px;transition:.2s;}
.branch-switch-slider:before{content:"";position:absolute;height:20px;width:20px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.2s;box-shadow:0 1px 3px rgba(0,0,0,.22);}
.branch-switch input:checked + .branch-switch-slider{background:#22c55e;}
.branch-switch input:checked + .branch-switch-slider:before{transform:translateX(20px);}
:is(html[data-theme="light"],html[data-theme="light_blue"]) .branch-switch-slider{background:color-mix(in srgb,#475569 75%,var(--border));}
.branch-switch input:focus-visible + .branch-switch-slider{box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 45%,transparent);}
.branch-inline-form__banner{margin:0 0 12px;padding:10px 12px;border-radius:10px;border:1px solid color-mix(in srgb,#f87171 40%,var(--border));background:color-mix(in srgb,#f87171 8%,transparent);font-size:13px;color:var(--text);}
.branch-inline-create{
    box-sizing:border-box;width:100%;max-width:none;margin-top:8px;padding:14px 16px 16px;
    border-radius:12px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 98%,transparent);
}
.branch-inline-create__head{margin:0 0 14px;padding-bottom:12px;border-bottom:1px solid var(--border);}
.branch-inline-create__head h2{margin:0;font-size:16px;font-weight:800;letter-spacing:-.02em;color:var(--text);}
.branch-inline-create__lead{margin:6px 0 0;font-size:13px;line-height:1.45;color:var(--muted);max-width:62ch;}
</style>

<div class="branch-page card" style="max-width:100%;padding:14px;">
    @if(session('status'))
        <div style="margin:0 0 12px;padding:10px 12px;border-radius:10px;border:1px solid color-mix(in srgb,#22c55e 40%,var(--border));background:color-mix(in srgb,#22c55e 9%,transparent);font-size:13px;font-weight:600;color:var(--text);">{{ session('status') }}</div>
    @endif
    <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">Branches for <strong style="color:var(--text);">{{ $business->name }}</strong>. Add warehouses, shops, or regional offices—visible only under this business.</p>

    @php $branchModalOpen = $branches->isNotEmpty() && $errors->any(); @endphp
    <div class="branch-toolbar">
        <span class="muted" style="margin:0;font-size:13px;">
            @if($branches->isEmpty())
                Use the form below to create your <strong style="color:var(--text);">first branch</strong>.
            @else
                {{ $branches->count() }} branch{{ $branches->count() === 1 ? '' : 'es' }}.
            @endif
        </span>
        @if($branches->isNotEmpty())
            <button type="button" id="branch-modal-open" class="linkbtn" style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;"><i class="fa fa-plus"></i> Add branch</button>
        @endif
    </div>

    @if($branches->isEmpty())
        <section class="branch-inline-create" aria-labelledby="branch-inline-title">
            <header class="branch-inline-create__head">
                <h2 id="branch-inline-title">Create your first branch</h2>
                <p class="branch-inline-create__lead">Warehouses, shops, or offices under this business. You can edit or add more locations later.</p>
            </header>
            @include('business::branches.partials.create-form', ['branchFormErrorBannerClass' => 'branch-inline-form__banner'])
        </section>
    @else
        <div class="branch-table-wrap" style="margin-top:12px;">
            <table class="branch-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Location</th>
                        <th>Active</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($branches as $branch)
                        <tr>
                            <td>
                                <strong style="color:var(--text);">{{ $branch->name }}</strong>
                                @if($branch->description)
                                    <div class="muted" style="font-size:12px;line-height:1.4;margin-top:4px;">{{ \Illuminate\Support\Str::limit($branch->description, 140) }}</div>
                                @endif
                            </td>
                            <td>
                                @if($branch->phone)<div>{{ $branch->phone }}</div>@else<span class="muted">—</span>@endif
                                @if($branch->email)<div style="margin-top:3px;"><a href="mailto:{{ $branch->email }}" class="branch-link">{{ $branch->email }}</a></div>@endif
                            </td>
                            <td>@if($branch->address)<span style="white-space:pre-wrap;">{{ \Illuminate\Support\Str::limit($branch->address, 120) }}</span>@else<span class="muted">—</span>@endif</td>
                            <td>
                                @if($branch->is_active)
                                    <span class="branch-badge branch-badge--on">Active</span>
                                @else
                                    <span class="branch-badge branch-badge--off">Inactive</span>
                                @endif
                            </td>
                            <td style="text-align:right;">
                                <div class="branch-actions" style="justify-content:flex-end;">
                                    <a class="branch-link" href="{{ route('business.branches.edit', $branch) }}"><i class="fa fa-pen" style="margin-right:5px;"></i>Edit</a>
                                    <form method="post" action="{{ route('business.branches.destroy', $branch) }}" style="margin:0;" onsubmit="return confirm('Delete this branch?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="branch-btn-del"><i class="fa fa-trash-can" style="margin-right:4px;"></i>Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div id="branch-modal"
            class="branch-modal {{ $branchModalOpen ? 'branch-modal--open' : '' }}"
            role="dialog"
            aria-modal="true"
            aria-labelledby="branch-modal-title"
            aria-hidden="{{ $branchModalOpen ? 'false' : 'true' }}">
            <div class="branch-modal__backdrop" data-branch-modal-close tabindex="-1"></div>
            <div class="branch-modal__panel">
                <div class="branch-modal__head">
                    <h2 id="branch-modal-title">Add branch</h2>
                    <button type="button" class="branch-modal__close" data-branch-modal-close aria-label="Close dialog">&times;</button>
                </div>
                <div class="branch-modal__body">
                    @include('business::branches.partials.create-form', ['branchFormErrorBannerClass' => 'branch-modal__banner'])
                </div>
            </div>
        </div>
    @endif
</div>

<div style="margin-top:14px;">
    <a href="{{ route('dashboard') }}" class="linkbtn" style="padding:7px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fa fa-arrow-left"></i> Overview
    </a>
</div>

<script>
(function () {
    const modal = document.getElementById('branch-modal');
    const openBtn = document.getElementById('branch-modal-open');

    function lockScroll(on) {
        document.documentElement.classList.toggle('branch-modal-open-html', Boolean(on));
    }

    function openBranchModal() {
        if (!modal) return;
        modal.classList.add('branch-modal--open');
        modal.setAttribute('aria-hidden', 'false');
        lockScroll(true);
        const first = document.getElementById('branch-name');
        window.requestAnimationFrame(() => first?.focus());
    }

    function closeBranchModal() {
        if (!modal) return;
        modal.classList.remove('branch-modal--open');
        modal.setAttribute('aria-hidden', 'true');
        lockScroll(false);
        openBtn?.focus();
    }

    openBtn?.addEventListener('click', openBranchModal);
    document.getElementById('branch-active')?.addEventListener('change', function () {
        this.setAttribute('aria-checked', this.checked ? 'true' : 'false');
    });
    modal?.querySelectorAll('[data-branch-modal-close]').forEach((el) =>
        el.addEventListener('click', () => closeBranchModal()),
    );

    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        if (!modal?.classList.contains('branch-modal--open')) return;
        closeBranchModal();
    });

    if (modal?.classList.contains('branch-modal--open')) {
        lockScroll(true);
    }
})();
</script>
@endsection
