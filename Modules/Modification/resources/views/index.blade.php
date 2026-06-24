@extends('theme::layouts.app', ['title' => __('Modification'), 'heading' => __('Modification')])

@section('content')
@php
    $currency = get_settings('business.currency', '', $business ?? null) ?: '';
@endphp
<div class="mod-page">
<style>
.mod-page{max-width:none;width:100%;margin:0;box-sizing:border-box;--mod-r:12px;--mod-r-sm:9px;}
.mod-hero{display:flex;flex-wrap:wrap;gap:12px 20px;justify-content:space-between;align-items:center;padding:0 2px 16px;margin-bottom:4px;border-bottom:1px solid var(--border);}
.mod-hero__badge{display:inline-flex;align-items:center;gap:6px;width:fit-content;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--primary);padding:4px 10px;border-radius:999px;border:1px solid color-mix(in srgb,var(--primary) 38%,var(--border));background:color-mix(in srgb,var(--primary) 9%,transparent);}
.mod-hero__actions{display:flex;flex-wrap:wrap;gap:9px;align-items:center;margin-left:auto;}
.mod-btn--primary{display:inline-flex;align-items:center;gap:8px;padding:9px 16px;border-radius:10px;font-size:13px;font-weight:700;border:1px solid color-mix(in srgb,var(--btn-bg) 72%,var(--border));background:var(--btn-bg);color:#fff;cursor:pointer;box-shadow:0 8px 20px -12px color-mix(in srgb,var(--btn-bg) 55%,transparent);transition:background .18s ease,transform .18s ease;}
.mod-btn--primary:hover{background:var(--btn-hover);color:#111827;transform:translateY(-1px);}
.mod-btn--ghost{display:inline-flex;align-items:center;gap:7px;padding:8px 14px;border-radius:10px;font-size:13px;font-weight:600;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 94%,transparent);color:var(--text);text-decoration:none;cursor:pointer;transition:background .18s ease,border-color .18s ease,transform .18s ease;}
.mod-btn--ghost:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 6%,transparent);transform:translateY(-1px);}

.mod-alert{padding:11px 14px;border-radius:12px;font-size:13px;margin-bottom:16px;display:flex;align-items:flex-start;gap:10px;line-height:1.45;border:1px solid;}
.mod-alert--ok{border-color:color-mix(in srgb,#22c55e 38%,var(--border));background:linear-gradient(135deg,color-mix(in srgb,#22c55e 8%,transparent),color-mix(in srgb,var(--card) 96%,transparent));}
.mod-alert--err{border-color:color-mix(in srgb,#f87171 42%,var(--border));background:color-mix(in srgb,#f87171 7%,transparent);}

.mod-empty{text-align:center;padding:40px 24px;color:var(--muted);border:1px dashed color-mix(in srgb,var(--primary) 24%,var(--border));border-radius:var(--mod-r);background:linear-gradient(165deg,color-mix(in srgb,var(--primary) 7%,transparent),color-mix(in srgb,var(--card) 98%,transparent));}
.mod-empty__ico{width:54px;height:54px;margin:0 auto 14px;display:grid;place-items:center;border-radius:16px;background:linear-gradient(145deg,color-mix(in srgb,var(--primary) 22%,transparent),color-mix(in srgb,var(--primary) 6%,transparent));color:var(--primary);font-size:22px;box-shadow:0 12px 32px -20px color-mix(in srgb,var(--primary) 40%,transparent);}
.mod-empty h2{margin:0;font-size:16px;font-weight:800;color:var(--text);}
.mod-empty p{margin:8px auto 0;max-width:38ch;line-height:1.55;font-size:13px;}

.mod-cards{display:flex;flex-direction:column;gap:6px;}
.mod-card{position:relative;display:flex;align-items:stretch;border-radius:10px;border:1px solid var(--border);overflow:hidden;background:color-mix(in srgb,var(--card) 98%,transparent);box-shadow:0 4px 18px -16px rgba(0,0,0,.35);transition:border-color .18s ease,box-shadow .18s ease;}
:is(html[data-theme="light"],html[data-theme="light_blue"]) .mod-card{background:var(--card);}
.mod-card:hover{border-color:color-mix(in srgb,var(--primary) 28%,var(--border));box-shadow:0 8px 24px -18px color-mix(in srgb,var(--primary) 12%,#000);}
.mod-card__ribbon{position:absolute;left:0;top:0;bottom:0;width:3px;background:linear-gradient(180deg,var(--primary),color-mix(in srgb,var(--primary) 42%,#1e293b));pointer-events:none;}
:is(html[data-theme="light"],html[data-theme="light_blue"]) .mod-card__ribbon{background:linear-gradient(180deg,var(--primary),color-mix(in srgb,var(--primary) 25%,var(--text)));}
.mod-card__hit{flex:1;min-width:0;margin-left:3px;text-decoration:none;color:inherit;display:flex;align-items:center;}
.mod-card__hit:focus-visible{outline:2px solid color-mix(in srgb,var(--primary) 55%,transparent);outline-offset:2px;border-radius:8px;}
.mod-card__inner{padding:10px 8px 10px 14px;display:flex;align-items:center;gap:12px 16px;flex-wrap:wrap;width:100%;box-sizing:border-box;}
.mod-card__tail{display:flex;align-items:center;padding:10px 12px 10px 4px;flex-shrink:0;gap:8px;border-left:1px solid color-mix(in srgb,var(--border) 70%,transparent);}
.mod-card__main{flex:1;min-width:min(160px,100%);}
.mod-card__title{margin:0;font-size:14px;font-weight:800;letter-spacing:-.02em;line-height:1.2;color:var(--text);}
.mod-card__pill{display:inline-flex;align-items:center;gap:3px;margin:0;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:color-mix(in srgb,var(--primary) 72%,var(--text));padding:3px 7px;border-radius:999px;border:1px solid color-mix(in srgb,var(--primary) 30%,var(--border));background:color-mix(in srgb,var(--primary) 7%,transparent);line-height:1.2;}
.mod-card__meta{margin:4px 0 0;font-size:11px;line-height:1.35;color:var(--muted);}
.mod-card__aside{display:flex;align-items:center;gap:14px;margin-left:auto;flex-wrap:wrap;text-align:right;}
.mod-card__cost{text-align:right;min-width:7rem;}
.mod-card__cost-lab{display:block;font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:2px;line-height:1.2;}
.mod-card__cost-val{font-weight:800;font-size:15px;color:color-mix(in srgb,var(--primary) 45%,var(--text));letter-spacing:-.03em;line-height:1.15;font-variant-numeric:tabular-nums;}
.mod-card__stat{text-align:right;}
.mod-card__stat-lab{display:block;font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:2px;line-height:1.2;}
.mod-card__stat-val{font-size:12px;font-weight:700;color:var(--text);font-variant-numeric:tabular-nums;}
.mod-btn-del{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;border:1px solid color-mix(in srgb,#ef4444 45%,var(--border));background:transparent;color:#f97373;cursor:pointer;transition:background .18s ease,border-color .18s ease;flex-shrink:0;}
.mod-btn-del:hover{background:color-mix(in srgb,#ef4444 12%,transparent);border-color:color-mix(in srgb,#ef4444 55%,var(--border));}
:is(html[data-theme="light"],html[data-theme="light_blue"]) .mod-btn-del{color:#dc2626;}

.mod-inline-create{box-sizing:border-box;width:100%;margin-top:6px;padding:22px;border-radius:var(--mod-r);border:1px solid color-mix(in srgb,var(--primary) 16%,var(--border));background:linear-gradient(160deg,color-mix(in srgb,var(--primary) 5%,transparent),var(--card));box-shadow:0 14px 44px -30px rgba(0,0,0,.38);}
.mod-inline-create__head{margin-bottom:16px;padding-bottom:14px;border-bottom:1px solid var(--border);display:flex;gap:14px;align-items:flex-start;}
.mod-inline-create__head-icon{width:44px;height:44px;border-radius:12px;display:grid;place-items:center;background:linear-gradient(145deg,var(--primary),color-mix(in srgb,var(--primary) 62%,#0f172a));color:#fff;font-size:18px;flex-shrink:0;box-shadow:0 12px 28px -14px color-mix(in srgb,var(--primary) 45%,transparent);}
:is(html[data-theme="light"],html[data-theme="light_blue"]) .mod-inline-create__head-icon{background:linear-gradient(145deg,var(--primary),#292524);color:#fef9c3;box-shadow:0 12px 28px -14px rgba(0,0,0,.18);}
.mod-inline-create__head h2{margin:0;font-size:18px;font-weight:800;letter-spacing:-.03em;line-height:1.2;color:var(--text);}
.mod-inline-create__lead{margin:6px 0 0;font-size:13px;color:var(--muted);line-height:1.5;}

.mod-modal,.mod-modal *{box-sizing:border-box;}
.mod-modal{position:fixed;inset:0;z-index:120;display:flex;justify-content:center;align-items:flex-start;padding:max(16px,3vh) 16px;overflow:auto;opacity:0;visibility:hidden;pointer-events:none;transition:opacity .24s ease,visibility .24s ease;}
.mod-modal.mod-modal--open{opacity:1;visibility:visible;pointer-events:auto;}
.mod-modal__backdrop{position:fixed;inset:0;z-index:0;background:rgba(15,23,42,.52);backdrop-filter:blur(5px);}
:is(html[data-theme="light"],html[data-theme="light_blue"]) .mod-modal__backdrop{background:rgba(17,24,39,.36);}
.mod-modal__panel{position:relative;z-index:1;width:100%;max-width:820px;max-height:min(93vh,calc(100dvh - 40px));display:flex;flex-direction:column;border-radius:var(--mod-r);border:1px solid var(--border);background:var(--card);box-shadow:0 28px 64px rgba(0,0,0,.4);margin:auto;}
.mod-modal__head{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:14px 18px;border-bottom:1px solid var(--border);flex-shrink:0;background:linear-gradient(180deg,color-mix(in srgb,var(--card) 98%,transparent),color-mix(in srgb,var(--card) 92%,transparent));}
.mod-modal__head h2{margin:0;font-size:16px;font-weight:900;letter-spacing:-.02em;}
.mod-modal__close{width:36px;height:36px;display:grid;place-items:center;padding:0;border:1px solid var(--border);border-radius:10px;background:color-mix(in srgb,var(--card) 90%,transparent);cursor:pointer;color:var(--text);font-size:18px;line-height:1;transition:background .18s ease,border-color .18s ease;}
.mod-modal__close:hover{border-color:color-mix(in srgb,var(--primary) 40%,var(--border));background:color-mix(in srgb,var(--primary) 7%,transparent);}
.mod-modal__body{padding:18px 20px 24px;overflow:auto;overscroll-behavior:contain;}
html.mod-modal-open{overflow:hidden;}

.mod-form-section{margin-bottom:14px;padding:14px 16px;border-radius:var(--mod-r-sm);border:1px solid color-mix(in srgb,var(--border) 88%,transparent);background:linear-gradient(180deg,color-mix(in srgb,var(--card) 97%,transparent),color-mix(in srgb,var(--card) 92%,transparent));box-shadow:0 8px 24px -22px rgba(0,0,0,.2);}
.mod-form-section__head{display:flex;align-items:center;gap:10px;margin-bottom:12px;font-size:13px;font-weight:800;color:var(--text);letter-spacing:-.01em;}
.mod-form-section__head i{color:var(--primary);width:20px;text-align:center;}
.mod-fields-grid{display:grid;gap:12px;}
@media(min-width:580px){.mod-fields-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:14px 18px;}}
.mod-field--full{grid-column:1/-1;}
.mod-field label,.mod-lbl{display:block;margin-bottom:5px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);}
.mod-field input,.mod-field select,.mod-field textarea,.mod-select{width:100%;box-sizing:border-box;padding:10px 12px;font-size:14px;border:1px solid var(--border);border-radius:10px;background:var(--card);color:var(--text);transition:border-color .15s ease,box-shadow .15s ease;font-family:inherit;}
.mod-field textarea{min-height:76px;resize:vertical;line-height:1.45;}
.mod-field input:focus,.mod-field select:focus,.mod-field textarea:focus,.mod-select:focus{border-color:color-mix(in srgb,var(--primary) 50%,var(--border));outline:none;box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 16%,transparent);}
.mod-field-err{display:block;color:#f87171;font-size:12px;margin-top:5px;line-height:1.35;}
.mod-submit-wrap{margin-top:14px;display:flex;flex-wrap:wrap;gap:10px;align-items:center;padding-top:14px;border-top:1px solid var(--border);}
.mod-pagination{margin-top:14px;}
</style>

<div class="mod-hero">
    <span class="mod-hero__badge"><i class="fa fa-screwdriver-wrench"></i> {{ __('Modification hub') }}</span>
    <div class="mod-hero__actions">
        @if(($modifications ?? collect())->isNotEmpty())
            <button type="button" id="mod-modal-open" class="mod-btn--primary"><i class="fa fa-plus"></i>{{ __('Add modification') }}</button>
        @endif
    </div>
</div>

@if(session('status'))
    <div class="mod-alert mod-alert--ok" style="margin-top:12px;">
        <i class="fa fa-circle-check" style="color:#22c55e;margin-top:1px;"></i>
        <span>{{ session('status') }}</span>
    </div>
@endif

@if($errors->any())
    <div class="mod-alert mod-alert--err" style="margin-top:12px;">
        <i class="fa fa-circle-exclamation" style="color:#f87171;margin-top:1px;"></i>
        <div>
            <strong style="display:block;margin-bottom:4px;">{{ __('Please correct the highlighted fields.') }}</strong>
            <ul style="margin:0;padding-left:16px;font-size:12px;">
                @foreach($errors->all() as $msg)
                    <li>{{ $msg }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

<div class="mod-body" style="padding:4px 0 0;">
@if(($modifications ?? collect())->isEmpty())
    <div class="mod-empty">
        <div class="mod-empty__ico"><i class="fa fa-screwdriver-wrench"></i></div>
        <h2>{{ __('No modifications yet') }}</h2>
        <p>{{ __('Track renovation, repair, or improvement costs by creating your first modification below.') }}</p>
    </div>

    <div class="mod-inline-create" style="margin-top:16px;">
        <div class="mod-inline-create__head">
            <div class="mod-inline-create__head-icon"><i class="fa fa-screwdriver-wrench"></i></div>
            <div>
                <h2>{{ __('Create modification') }}</h2>
                <p class="mod-inline-create__lead">{{ __('Add details about the renovation or improvement work.') }}</p>
            </div>
        </div>
        <form method="post" action="{{ route('modification.store') }}">
            @csrf
            @include('modification::partials.create-form')
            <div class="mod-submit-wrap">
                <button type="submit" class="mod-btn--primary"><i class="fa fa-floppy-disk"></i>{{ __('Save modification') }}</button>
            </div>
        </form>
    </div>

@else
    <div class="mod-cards">
        @foreach($modifications as $modification)
        @php
            $refLabel = \Modules\Modification\Models\Modification::displayAssignmentReference(
                $modification->assignment_type,
                $modification->assignment_reference,
                $assignmentPropertyLookup ?? [],
            );
            $typeLabel = match($modification->assignment_type) {
                'property'   => __('Property'),
                'renovation' => __('Renovation'),
                default      => __('Other'),
            };
        @endphp
        <div class="mod-card">
            <div class="mod-card__ribbon"></div>
            <a href="{{ route('modification.show', $modification) }}" class="mod-card__hit">
                <div class="mod-card__inner">
                    <div class="mod-card__main">
                        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:6px;">
                            <h3 class="mod-card__title">{{ $modification->name }}</h3>
                            <span class="mod-card__pill">{{ $typeLabel }}</span>
                        </div>
                        <p class="mod-card__meta">
                            @if($refLabel){{ $refLabel }} &middot; @endif
                            {{ __('Added') }} {{ $modification->created_at?->format('d M Y') ?? '—' }}
                        </p>
                    </div>
                    <div class="mod-card__aside">
                        @if((int)($modification->bills_count ?? 0) > 0)
                        <div class="mod-card__stat">
                            <span class="mod-card__stat-lab">{{ __('Bills') }}</span>
                            <span class="mod-card__stat-val">{{ (int) $modification->bills_count }}</span>
                        </div>
                        @endif
                        <div class="mod-card__cost">
                            <span class="mod-card__cost-lab">{{ __('Est. cost') }}</span>
                            <span class="mod-card__cost-val">
                                @if($currency)<span style="font-size:9px;opacity:.7;font-weight:700;margin-right:.1em;">{{ $currency }}</span>@endif
                                {{ number_format((float) $modification->estimated_cost, 2) }}
                            </span>
                        </div>
                        @if($modification->duration)
                        <div class="mod-card__stat">
                            <span class="mod-card__stat-lab">{{ __('Duration') }}</span>
                            <span class="mod-card__stat-val">{{ $modification->duration }}</span>
                        </div>
                        @endif
                    </div>
                </div>
            </a>
            <div class="mod-card__tail">
                <form method="post" action="{{ route('modification.destroy', $modification) }}"
                      onsubmit="return confirm(@json(__('Delete this modification? Bills assigned to it will be unlinked.')))"
                      style="margin:0;line-height:0;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="mod-btn-del" title="{{ __('Delete') }}">
                        <i class="fa fa-trash" style="font-size:11px;"></i>
                    </button>
                </form>
            </div>
        </div>
        @endforeach
    </div>

    @if($modifications->hasPages())
        <div class="mod-pagination">{{ $modifications->links() }}</div>
    @endif
@endif
</div>

{{-- Create modal (only when records exist) --}}
@if(($modifications ?? collect())->isNotEmpty())
<div class="mod-modal" id="mod-create-modal" aria-modal="true" role="dialog" aria-label="{{ __('Add modification') }}">
    <div class="mod-modal__backdrop" id="mod-modal-backdrop"></div>
    <div class="mod-modal__panel">
        <div class="mod-modal__head">
            <h2><i class="fa fa-screwdriver-wrench" style="color:var(--primary);margin-right:8px;font-size:14px;"></i>{{ __('Add modification') }}</h2>
            <button type="button" class="mod-modal__close" id="mod-modal-close" aria-label="{{ __('Close') }}">&times;</button>
        </div>
        <div class="mod-modal__body">
            <form method="post" action="{{ route('modification.store') }}" id="mod-create-form">
                @csrf
                @include('modification::partials.create-form')
                <div class="mod-submit-wrap">
                    <button type="submit" class="mod-btn--primary"><i class="fa fa-floppy-disk"></i>{{ __('Save modification') }}</button>
                    <button type="button" id="mod-modal-cancel" class="mod-btn--ghost">{{ __('Cancel') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
(function () {
    var modal   = document.getElementById('mod-create-modal');
    var openBtn = document.getElementById('mod-modal-open');
    var closeBtn = document.getElementById('mod-modal-close');
    var cancelBtn = document.getElementById('mod-modal-cancel');
    var backdrop = document.getElementById('mod-modal-backdrop');
    if (!modal) return;

    function openModal() {
        modal.classList.add('mod-modal--open');
        document.documentElement.classList.add('mod-modal-open');
    }
    function closeModal() {
        modal.classList.remove('mod-modal--open');
        document.documentElement.classList.remove('mod-modal-open');
    }
    if (openBtn)   openBtn.addEventListener('click', openModal);
    if (closeBtn)  closeBtn.addEventListener('click', closeModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
    if (backdrop)  backdrop.addEventListener('click', closeModal);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('mod-modal--open')) closeModal();
    });
    @if($errors->any())
        openModal();
    @endif
})();
</script>
@endif

</div>
@endsection
