@extends('theme::layouts.app', ['title' => 'Leads board', 'heading' => $project->name])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.ld-board{display:flex;gap:12px;overflow-x:auto;padding-bottom:8px;align-items:flex-start;}
.ld-board__col{flex:0 0 260px;border:1px solid var(--border);border-radius:11px;background:color-mix(in srgb,var(--card) 98%,transparent);display:flex;flex-direction:column;max-height:calc(100vh - 260px);}
.ld-board__head{padding:10px 12px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px;}
.ld-board__head .swatch{width:10px;height:10px;border-radius:50%;flex-shrink:0;}
.ld-board__head strong{font-size:13px;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.ld-board__count{font-size:11px;font-weight:700;color:var(--muted);background:color-mix(in srgb,var(--muted) 16%,transparent);border-radius:999px;padding:2px 8px;flex-shrink:0;}
.ld-board__value{padding:0 12px 8px;font-size:11px;color:var(--muted);}
.ld-board__body{flex:1;overflow-y:auto;padding:8px;display:flex;flex-direction:column;gap:8px;min-height:60px;}
.ld-board__card{border:1px solid var(--border);border-radius:9px;padding:9px 10px;background:var(--card);}
.ld-board__card-top{display:flex;align-items:center;gap:6px;}
.ld-board__card-top .pcat-drag-handle{width:20px;height:20px;flex-shrink:0;}
.ld-board__card-name{font-size:13px;font-weight:700;color:var(--text);text-decoration:none;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.ld-board__card-name:hover{text-decoration:underline;}
.ld-board__card-meta{display:flex;justify-content:space-between;align-items:center;margin-top:6px;margin-left:26px;font-size:11px;color:var(--muted);gap:6px;}
.ld-board__card-meta span:last-child{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.ld-board__empty{font-size:12px;color:var(--muted);text-align:center;padding:14px 6px;margin:0;}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('crm::partials.project-nav', ['project' => $project])

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif

    <div class="pcat-toolbar">
        <span class="muted" style="margin:0;font-size:13px;">
            Drag a card by its handle to move it to a different stage for <strong style="color:var(--text);">{{ $project->name }}</strong>.
        </span>
        <div style="display:flex;gap:10px;align-items:center;">
            <span id="ld-board-status" class="pcat-reorder-status" hidden aria-live="polite"></span>
            <a href="{{ route('crm.projects.leads.index', $project) }}" class="linkbtn"
               style="padding:8px 16px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                <i class="fa fa-list"></i> List view
            </a>
        </div>
    </div>

    <div class="ld-board">
        @foreach($stages as $stage)
            @php
                $stageLeads = $leadsByStage->get($stage->id, collect());
                $stageValue = $stageLeads->sum('estimated_value');
            @endphp
            <div class="ld-board__col">
                <div class="ld-board__head">
                    <span class="swatch" style="background:{{ $stage->color }};"></span>
                    <strong title="{{ $stage->name }}">{{ $stage->name }}</strong>
                    <span class="ld-board__count">{{ $stageLeads->count() }}</span>
                </div>
                @if($stageValue > 0)
                    <div class="ld-board__value">{{ number_format($stageValue, 2) }}</div>
                @endif
                <div class="ld-board__body" data-stage-dropzone data-stage-id="{{ $stage->id }}">
                    @forelse($stageLeads as $lead)
                        <div class="ld-board__card" data-lead-id="{{ $lead->id }}">
                            <div class="ld-board__card-top">
                                <span class="pcat-drag-handle" title="Drag to move">
                                    <i class="fa fa-grip-vertical"></i>
                                </span>
                                <a href="{{ route('crm.leads.show', $lead) }}" class="ld-board__card-name">{{ $lead->name }}</a>
                            </div>
                            @if($lead->company)
                                <div class="muted" style="font-size:11px;margin:4px 0 0 26px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $lead->company }}</div>
                            @endif
                            <div class="ld-board__card-meta">
                                <span>{{ $lead->estimated_value !== null ? number_format((float) $lead->estimated_value, 2) : '' }}</span>
                                <span>{{ $lead->assignedTo?->name }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="ld-board__empty">No leads</p>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</div>

<div style="margin-top:14px;">
    <a href="{{ route('dashboard') }}" class="linkbtn"
       style="padding:7px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fa fa-arrow-left"></i> Overview
    </a>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
(function () {
    var statusEl = document.getElementById('ld-board-status');
    var moveUrlBase = @json(url('/crm/leads'));

    function setStatus(text, cls) {
        if (!statusEl) return;
        statusEl.textContent = text || '';
        statusEl.hidden = !text;
        statusEl.classList.remove('is-saving', 'is-error');
        if (cls) statusEl.classList.add(cls);
    }

    function csrf() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            || document.querySelector('input[name="_token"]')?.value || '';
    }

    function bumpCount(dropzone, delta) {
        var head = dropzone.closest('.ld-board__col')?.querySelector('.ld-board__count');
        if (head) head.textContent = String(Math.max(0, parseInt(head.textContent, 10) + delta));
    }

    document.querySelectorAll('[data-stage-dropzone]').forEach(function (col) {
        Sortable.create(col, {
            group: 'ld-board',
            animation: 150,
            handle: '.pcat-drag-handle',
            ghostClass: 'pcat-sort-ghost',
            chosenClass: 'pcat-sort-chosen',
            onAdd: function (evt) {
                var item     = evt.item;
                var leadId   = item.getAttribute('data-lead-id');
                var stageId  = evt.to.getAttribute('data-stage-id');
                var fromCol  = evt.from;
                var oldIndex = evt.oldIndex;

                setStatus('Saving…', 'is-saving');
                fetch(moveUrlBase + '/' + leadId + '/move-stage', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ stage_id: stageId }),
                })
                .then(function (res) { if (!res.ok) throw new Error('failed'); return res.json(); })
                .then(function () {
                    bumpCount(evt.to, 1);
                    bumpCount(fromCol, -1);
                    setStatus('Saved', '');
                    setTimeout(function () { setStatus('', ''); }, 1500);
                })
                .catch(function () {
                    var ref = fromCol.children[oldIndex] || null;
                    fromCol.insertBefore(item, ref);
                    setStatus('Could not move lead.', 'is-error');
                });
            },
        });
    });
})();
</script>
@endsection
