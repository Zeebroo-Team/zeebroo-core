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
.ld-board__card{border:1px solid var(--border);border-radius:9px;padding:9px 10px;background:var(--card);position:relative;}
.ld-board__card-top{display:flex;align-items:center;gap:6px;}
.ld-board__card-top .pcat-drag-handle{width:20px;height:20px;flex-shrink:0;}
.ld-board__card-name{font-size:13px;font-weight:700;color:var(--text);text-decoration:none;min-width:0;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.ld-board__card-name:hover{text-decoration:underline;}
.ld-board__card-meta{display:flex;justify-content:space-between;align-items:center;margin-top:6px;margin-left:26px;font-size:11px;color:var(--muted);gap:6px;}
.ld-board__card-meta span:last-child{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.ld-board__empty{font-size:12px;color:var(--muted);text-align:center;padding:14px 6px;margin:0;}
.ld-board__menu{flex-shrink:0;}
.ld-board__menu summary{list-style:none;cursor:pointer;width:20px;height:20px;display:flex;align-items:center;justify-content:center;border-radius:6px;color:var(--muted);}
.ld-board__menu summary::-webkit-details-marker{display:none;}
.ld-board__menu summary:hover{background:color-mix(in srgb,var(--muted) 16%,transparent);color:var(--text);}
.ld-board__menu[open] summary{background:color-mix(in srgb,var(--muted) 16%,transparent);}
.ld-board__menu-panel{position:absolute;top:30px;right:8px;z-index:5;background:var(--card);border:1px solid var(--border);border-radius:9px;box-shadow:0 6px 18px rgba(0,0,0,.18);padding:4px;min-width:150px;display:flex;flex-direction:column;gap:2px;}
.ld-board__menu-panel form,.ld-board__menu-panel a{margin:0;}
.ld-board__menu-panel button,.ld-board__menu-panel a{width:100%;text-align:left;background:transparent;border:none;padding:7px 9px;font-size:12px;color:var(--text);border-radius:6px;cursor:pointer;display:flex;align-items:center;gap:7px;text-decoration:none;}
.ld-board__menu-panel button:hover,.ld-board__menu-panel a:hover{background:color-mix(in srgb,var(--muted) 14%,transparent);}
.ld-board__menu-panel .is-danger{color:#f97373;}
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
                    @if($stage->isReserved())
                        <i class="fa fa-lock muted" style="font-size:10px;" title="Set by the quotation workflow — leads can't be dropped here"></i>
                    @endif
                    <span class="ld-board__count">{{ $stageLeads->count() }}</span>
                </div>
                @if($stageValue > 0)
                    <div class="ld-board__value">{{ number_format($stageValue, 2) }}</div>
                @endif
                <div class="ld-board__body" data-stage-dropzone data-stage-id="{{ $stage->id }}"
                     data-stage-name="{{ $stage->name }}"
                     data-reserved="{{ $stage->isReserved() ? 1 : 0 }}"
                     data-lost="{{ $stage->isLostStage() ? 1 : 0 }}"
                     data-reason="{{ $stage->requires_reason && !$stage->isLostStage() ? 1 : 0 }}">
                    @forelse($stageLeads as $lead)
                        <div class="ld-board__card" data-lead-id="{{ $lead->id }}">
                            <div class="ld-board__card-top">
                                <span class="pcat-drag-handle" title="Drag to move">
                                    <i class="fa fa-grip-vertical"></i>
                                </span>
                                <a href="{{ route('crm.leads.show', $lead) }}" class="ld-board__card-name">{{ $lead->name }}</a>
                                <details class="ld-board__menu">
                                    <summary title="Actions"><i class="fa fa-ellipsis-vertical"></i></summary>
                                    <div class="ld-board__menu-panel">
                                        <a href="{{ route('crm.leads.edit', $lead) }}"><i class="fa fa-pen"></i> Edit</a>
                                        @if(!$stage->is_won && !$stage->is_lost)
                                            <form method="POST" action="{{ route('crm.leads.convert', $lead) }}" onsubmit="return confirm('Convert this lead to a customer?');">
                                                @csrf
                                                <button type="submit"><i class="fa fa-check"></i> Convert to customer</button>
                                            </form>
                                            @if($stage->isOnHold())
                                                <form method="POST" action="{{ route('crm.leads.resume', $lead) }}">
                                                    @csrf
                                                    <button type="submit"><i class="fa fa-play"></i> Resume</button>
                                                </form>
                                            @endif
                                            <button type="button" class="is-danger" data-ld-mark-lost="{{ $lead->id }}"><i class="fa fa-xmark"></i> Mark lost</button>
                                        @elseif($stage->is_lost)
                                            <form method="POST" action="{{ route('crm.leads.reopen', $lead) }}">
                                                @csrf
                                                <button type="submit"><i class="fa fa-rotate-left"></i> Reopen</button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('crm.leads.destroy', $lead) }}" onsubmit="return confirm('Delete this lead? This cannot be undone.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="is-danger"><i class="fa fa-trash"></i> Delete</button>
                                        </form>
                                    </div>
                                </details>
                            </div>
                            @if($lead->company)
                                <div class="muted" style="font-size:11px;margin:4px 0 0 26px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $lead->company }}</div>
                            @endif
                            @if($lead->score)
                                <div style="margin:4px 0 0 26px;font-size:10px;font-weight:700;color:{{ \Modules\CRM\Models\Lead::SCORES[$lead->score][1] }};">
                                    <i class="fa fa-fire"></i> {{ \Modules\CRM\Models\Lead::scoreLabel($lead->score) }}
                                </div>
                            @endif
                            <div class="ld-board__card-meta">
                                <span>
                                    @if($lead->source)
                                        @php
                                            [$srcLabel, $srcIcon, $srcColor] = \Modules\CRM\Models\Lead::SOURCES[$lead->source] ?? [\Modules\CRM\Models\Lead::sourceLabel($lead->source), 'fa fa-tag', '#94a3b8'];
                                        @endphp
                                        <i class="{{ $srcIcon }}" style="color:{{ $srcColor }};" title="Source: {{ $srcLabel }}"></i>
                                    @endif
                                    {{ $lead->estimated_value !== null ? number_format((float) $lead->estimated_value, 2) : '' }}
                                </span>
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

{{-- Asks for the lost reason / stage reason when a move needs one --}}
<div id="ld-move-modal" class="pcat-modal" role="dialog" aria-modal="true" aria-labelledby="ld-move-title" aria-hidden="true">
    <div class="pcat-modal__backdrop" data-ld-move-cancel tabindex="-1"></div>
    <div class="pcat-modal__panel" style="max-width:min(94vw,420px);">
        <div class="pcat-modal__head">
            <h2 id="ld-move-title">Move lead</h2>
            <button type="button" class="pcat-modal__close" data-ld-move-cancel aria-label="Close">&times;</button>
        </div>
        <div class="pcat-modal__body">
            <form id="ld-move-form" class="pcat-form-grid">
                <div class="pcat-field" data-ld-move-lost>
                    <label for="ld-move-lost-reason">Lost reason <span style="color:#ef4444;">*</span></label>
                    <select id="ld-move-lost-reason">
                        <option value="">— Pick a reason —</option>
                        @foreach($lostReasons as $r)
                            <option value="{{ $r->id }}">{{ $r->label }}</option>
                        @endforeach
                    </select>
                    <input id="ld-move-lost-note" type="text" maxlength="255" placeholder="Note (optional)" style="margin-top:6px;">
                </div>
                <div class="pcat-field" data-ld-move-reason>
                    <label for="ld-move-reason">Reason <span style="color:#ef4444;">*</span></label>
                    <input id="ld-move-reason" type="text" maxlength="500">
                </div>
                <div style="display:flex;justify-content:flex-end;gap:8px;">
                    <button type="button" class="pcat-btn-del" style="border-color:var(--border);color:var(--muted);background:transparent;" data-ld-move-cancel>Cancel</button>
                    <button type="submit" class="linkbtn" style="padding:8px 18px;font-size:13px;">Move</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div style="margin-top:14px;padding-left:14px;">
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

    document.addEventListener('click', function (e) {
        document.querySelectorAll('.ld-board__menu[open]').forEach(function (d) {
            if (!d.contains(e.target)) d.removeAttribute('open');
        });
    });
    document.querySelectorAll('.ld-board__menu').forEach(function (d) {
        d.addEventListener('toggle', function () {
            if (!d.open) return;
            document.querySelectorAll('.ld-board__menu[open]').forEach(function (other) {
                if (other !== d) other.removeAttribute('open');
            });
        });
    });

    // Lost stages need a lost reason, some stages a free-text reason: ask before saving the move.
    // Resolves the extra payload, or null when cancelled.
    var moveModal = document.getElementById('ld-move-modal');
    function askMoveDetails(zone) {
        var lost = zone.getAttribute('data-lost') === '1';
        var needsReason = zone.getAttribute('data-reason') === '1';
        if (!lost && !needsReason) return Promise.resolve({});

        var form = document.getElementById('ld-move-form');
        document.getElementById('ld-move-title').textContent = 'Move to ' + zone.getAttribute('data-stage-name');
        moveModal.querySelector('[data-ld-move-lost]').hidden = !lost;
        moveModal.querySelector('[data-ld-move-reason]').hidden = !needsReason;
        form.reset();
        moveModal.classList.add('pcat-modal--open');
        moveModal.setAttribute('aria-hidden', 'false');
        requestAnimationFrame(function () { (lost ? document.getElementById('ld-move-lost-reason') : document.getElementById('ld-move-reason')).focus(); });

        return new Promise(function (resolve) {
            function finish(value) {
                moveModal.classList.remove('pcat-modal--open');
                moveModal.setAttribute('aria-hidden', 'true');
                form.removeEventListener('submit', onSubmit);
                moveModal.querySelectorAll('[data-ld-move-cancel]').forEach(function (el) { el.removeEventListener('click', onCancel); });
                resolve(value);
            }
            function onSubmit(e) {
                e.preventDefault();
                var data = {};
                if (lost) {
                    data.lost_reason_id = document.getElementById('ld-move-lost-reason').value;
                    data.lost_reason = document.getElementById('ld-move-lost-note').value.trim();
                    if (!data.lost_reason_id) { document.getElementById('ld-move-lost-reason').focus(); return; }
                }
                if (needsReason) {
                    data.reason = document.getElementById('ld-move-reason').value.trim();
                    if (!data.reason) { document.getElementById('ld-move-reason').focus(); return; }
                }
                finish(data);
            }
            function onCancel() { finish(null); }
            form.addEventListener('submit', onSubmit);
            moveModal.querySelectorAll('[data-ld-move-cancel]').forEach(function (el) { el.addEventListener('click', onCancel); });
        });
    }

    function postMove(leadId, stageId, extra) {
        return fetch(moveUrlBase + '/' + leadId + '/move-stage', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(Object.assign({ stage_id: stageId }, extra || {})),
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (body) {
                if (!res.ok) {
                    var first = body && body.errors ? Object.values(body.errors)[0] : null;
                    throw new Error((first && first[0]) || (body && body.message) || 'Could not move lead.');
                }
                return body;
            });
        });
    }

    document.querySelectorAll('[data-stage-dropzone]').forEach(function (col) {
        Sortable.create(col, {
            // Reserved statuses (pending approval / quotation sent) only take leads from the quotation workflow
            group: { name: 'ld-board', put: col.getAttribute('data-reserved') !== '1' },
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
                function revert(message) {
                    var ref = fromCol.children[oldIndex] || null;
                    fromCol.insertBefore(item, ref);
                    setStatus(message || '', message ? 'is-error' : '');
                }

                askMoveDetails(evt.to).then(function (extra) {
                    if (extra === null) { revert(''); return; }
                    setStatus('Saving…', 'is-saving');
                    return postMove(leadId, stageId, extra)
                        .then(function () {
                            // Won / lost / on hold change the card's actions, so reload for those
                            if (evt.to.getAttribute('data-lost') === '1' || evt.to.getAttribute('data-reason') === '1') { window.location.reload(); return; }
                            bumpCount(evt.to, 1);
                            bumpCount(fromCol, -1);
                            setStatus('Saved', '');
                            setTimeout(function () { setStatus('', ''); }, 1500);
                        })
                        .catch(function (err) { revert(err.message); });
                });
            },
        });
    });

    document.querySelectorAll('[data-ld-mark-lost]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var lostZone = document.querySelector('[data-stage-dropzone][data-lost="1"]');
            if (!lostZone) { setStatus('This pipeline has no lost stage.', 'is-error'); return; }
            btn.closest('details')?.removeAttribute('open');
            askMoveDetails(lostZone).then(function (extra) {
                if (extra === null) return;
                setStatus('Saving…', 'is-saving');
                postMove(btn.getAttribute('data-ld-mark-lost'), lostZone.getAttribute('data-stage-id'), extra)
                    .then(function () { window.location.reload(); })
                    .catch(function (err) { setStatus(err.message, 'is-error'); });
            });
        });
    });
})();
</script>
@endsection
