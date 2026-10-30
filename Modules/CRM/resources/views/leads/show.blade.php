@extends('theme::layouts.app', ['title' => $lead->name, 'heading' => $lead->name])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.ld-stage{display:inline-block;font-size:11px;font-weight:700;padding:3px 8px;border-radius:999px;border:1px solid var(--border);}
.ps-tab__count{font-size:9px;font-weight:700;padding:1px 5px;border-radius:999px;background:color-mix(in srgb,var(--primary) 14%,transparent);color:var(--primary);border:1px solid color-mix(in srgb,var(--primary) 25%,transparent);}
.ps-panel[hidden]{display:none!important;}
.ld-detail-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px;margin-top:8px;}
.ld-detail-grid__field{min-width:0;border:1px solid var(--border);border-radius:9px;padding:8px 10px;background:var(--card);}
.ld-detail-grid__label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:4px;}
.ld-detail-grid__value{font-size:13px;color:var(--text);overflow-wrap:break-word;word-break:break-word;}
.ld-action-menu{position:relative;}
.ld-action-menu summary{list-style:none;cursor:pointer;display:inline-flex;align-items:center;gap:6px;}
.ld-action-menu summary::-webkit-details-marker{display:none;}
.ld-action-menu__panel{position:absolute;right:0;top:calc(100% + 6px);z-index:5;background:var(--card);border:1px solid var(--border);border-radius:10px;box-shadow:0 6px 18px rgba(0,0,0,.18);padding:10px;width:260px;display:flex;flex-direction:column;gap:8px;font-size:12px;}
.ld-action-menu__panel label{font-weight:700;color:var(--muted);font-size:11px;}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('crm::partials.project-nav', ['project' => $lead->project])

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="pcat-banner pcat-banner--err" role="alert">{{ $errors->first() }}</div>
    @endif

    <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:14px;">
        <div>
            <h2 style="margin:0 0 4px;font-size:18px;font-weight:800;">{{ $lead->name }}</h2>
            <div class="muted" style="font-size:13px;">
                {{ $lead->company ?? 'No company' }}
                @if($lead->email) · {{ $lead->email }} @endif
                @if($lead->phone) · {{ $lead->phone }} @endif
            </div>
            <div style="margin-top:8px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <span class="ld-stage" style="border-color:{{ $lead->stageColor() }};color:{{ $lead->stageColor() }};">{{ $lead->stageLabel() }}</span>
                @if($lead->score)
                    <span class="ld-stage" style="border-color:{{ \Modules\CRM\Models\Lead::SCORES[$lead->score][1] }};color:{{ \Modules\CRM\Models\Lead::SCORES[$lead->score][1] }};">{{ \Modules\CRM\Models\Lead::scoreLabel($lead->score) }}</span>
                @endif
                @if($lead->estimated_value !== null)
                    <span class="muted" style="font-size:12px;">Est. value: <strong style="color:var(--text);">{{ number_format((float) $lead->estimated_value, 2) }}</strong></span>
                @endif
                @if($lead->assignedTo)
                    <span class="muted" style="font-size:12px;"><i class="fa fa-user"></i> {{ $lead->assignedTo->name }}</span>
                @endif
            </div>
        </div>

        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            @if($can['can_edit'])
            <a href="{{ route('crm.leads.edit', $lead) }}" class="linkbtn" style="padding:7px 14px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">
                <i class="fa fa-pen"></i> Edit
            </a>
            @endif
            @if($can['can_edit'] && $lead->isOpen())
                <form method="POST" action="{{ route('crm.leads.convert', $lead) }}" onsubmit="return confirm('Convert this lead to a customer?');">
                    @csrf
                    <button type="submit" class="linkbtn" style="padding:7px 14px;font-size:12px;">
                        <i class="fa fa-check"></i> Convert to customer
                    </button>
                </form>
                @if($can['can_move'])
                    @if($lead->isOnHold())
                        <form method="POST" action="{{ route('crm.leads.resume', $lead) }}">
                            @csrf
                            <button type="submit" class="linkbtn" style="padding:7px 14px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);">
                                <i class="fa fa-play"></i> Resume{{ $lead->onHoldFromStage ? ' (back to ' . $lead->onHoldFromStage->name . ')' : '' }}
                            </button>
                        </form>
                    @elseif($hasOnHoldStage)
                        <details class="ld-action-menu">
                            <summary class="linkbtn" style="padding:7px 14px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);">
                                <i class="fa fa-pause"></i> Put on hold
                            </summary>
                            <form method="POST" action="{{ route('crm.leads.hold', $lead) }}" class="ld-action-menu__panel">
                                @csrf
                                <label for="ld-hold-reason">Reason (optional)</label>
                                <input id="ld-hold-reason" type="text" name="reason" maxlength="500" placeholder="e.g. Customer travelling until next month">
                                <button type="submit" class="linkbtn" style="padding:7px 14px;font-size:12px;">Put on hold</button>
                            </form>
                        </details>
                    @endif
                    <details class="ld-action-menu">
                        <summary class="linkbtn" style="padding:7px 14px;font-size:12px;background:transparent;border:1px solid var(--border);color:#f97373;">
                            <i class="fa fa-xmark"></i> Mark lost
                        </summary>
                        <form method="POST" action="{{ route('crm.leads.mark-lost', $lead) }}" class="ld-action-menu__panel">
                            @csrf
                            <label for="ld-lost-reason-id">Lost reason</label>
                            <select id="ld-lost-reason-id" name="lost_reason_id" required>
                                <option value="">— Pick a reason —</option>
                                @foreach($lostReasons as $r)
                                    <option value="{{ $r->id }}">{{ $r->label }}</option>
                                @endforeach
                            </select>
                            <input type="text" name="lost_reason" maxlength="255" placeholder="Note (optional)">
                            <button type="submit" class="linkbtn" style="padding:7px 14px;font-size:12px;background:#ef4444;">Mark lost</button>
                        </form>
                    </details>
                @endif
            @elseif($can['can_move'] && $lead->isLost())
                <form method="POST" action="{{ route('crm.leads.reopen', $lead) }}">
                    @csrf
                    <button type="submit" class="linkbtn" style="padding:7px 14px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);">
                        <i class="fa fa-rotate-left"></i> Reopen
                    </button>
                </form>
            @endif
            @if($can['can_delete'])
            <form method="POST" action="{{ route('crm.leads.destroy', $lead) }}" onsubmit="return confirm('Delete this lead? This cannot be undone.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="pcat-btn-del" style="padding:7px 12px;">
                    <i class="fa fa-trash"></i>
                </button>
            </form>
            @endif
        </div>
    </div>

    @php
        $hasOverviewContent = $lead->customer || $lead->notes || $lead->customFieldValues->isNotEmpty()
            || $lead->products->isNotEmpty() || $lead->product_interest_note;
    @endphp

    <nav class="ps-tabs" role="tablist">
        <button type="button" class="ps-tab is-active" data-ld-tab="overview">
            <i class="fa fa-circle-info"></i> Overview
        </button>
        <button type="button" class="ps-tab" data-ld-tab="tasks">
            <i class="fa fa-list-check"></i> Tasks
            @if($lead->tasks->isNotEmpty())<span class="ps-tab__count">{{ $lead->tasks->count() }}</span>@endif
        </button>
        <button type="button" class="ps-tab" data-ld-tab="activity">
            <i class="fa fa-clock-rotate-left"></i> Activity
            @if($lead->activities->isNotEmpty())<span class="ps-tab__count">{{ $lead->activities->count() }}</span>@endif
        </button>
    </nav>

    <section id="tab-overview" class="ps-panel" data-ld-panel>
        <div class="pcat-inline" style="margin-bottom:16px;">
            <h2 style="font-size:13px;">Lead details</h2>
            <div class="ld-detail-grid">
                <div class="ld-detail-grid__field">
                    <div class="ld-detail-grid__label">Name</div>
                    <div class="ld-detail-grid__value">{{ $lead->name }}</div>
                </div>
                <div class="ld-detail-grid__field">
                    <div class="ld-detail-grid__label">Company</div>
                    <div class="ld-detail-grid__value">{{ $lead->company ?? '—' }}</div>
                </div>
                <div class="ld-detail-grid__field">
                    <div class="ld-detail-grid__label">Email</div>
                    <div class="ld-detail-grid__value">{{ $lead->email ?? '—' }}</div>
                </div>
                <div class="ld-detail-grid__field">
                    <div class="ld-detail-grid__label">Phone</div>
                    <div class="ld-detail-grid__value">{{ $lead->phone ?? '—' }}</div>
                </div>
                <div class="ld-detail-grid__field">
                    <div class="ld-detail-grid__label">Source</div>
                    <div class="ld-detail-grid__value">{{ $lead->leadSource?->label ?? \Modules\CRM\Models\Lead::sourceLabel($lead->source) ?? '—' }}</div>
                </div>
                <div class="ld-detail-grid__field">
                    <div class="ld-detail-grid__label">Stage</div>
                    <div class="ld-detail-grid__value">
                        <span class="ld-stage" style="border-color:{{ $lead->stageColor() }};color:{{ $lead->stageColor() }};">{{ $lead->stageLabel() }}</span>
                    </div>
                </div>
                <div class="ld-detail-grid__field">
                    <div class="ld-detail-grid__label">Estimated value</div>
                    <div class="ld-detail-grid__value">{{ $lead->estimated_value !== null ? number_format((float) $lead->estimated_value, 2) : '—' }}</div>
                </div>
                <div class="ld-detail-grid__field">
                    <div class="ld-detail-grid__label">Expected close date</div>
                    <div class="ld-detail-grid__value">{{ $lead->expected_close_date?->format('M j, Y') ?? '—' }}</div>
                </div>
                <div class="ld-detail-grid__field">
                    <div class="ld-detail-grid__label">Assigned to</div>
                    <div class="ld-detail-grid__value">{{ $lead->assignedTo?->name ?? '—' }}</div>
                </div>
                <div class="ld-detail-grid__field">
                    <div class="ld-detail-grid__label">Status</div>
                    <div class="ld-detail-grid__value">{{ \Modules\CRM\Models\LeadStage::statusLabel($lead->statusKey()) ?? '—' }}</div>
                </div>
                <div class="ld-detail-grid__field">
                    <div class="ld-detail-grid__label">Score</div>
                    <div class="ld-detail-grid__value">{{ \Modules\CRM\Models\Lead::scoreLabel($lead->score) ?? '—' }}</div>
                </div>
                @if($lead->department)
                    <div class="ld-detail-grid__field">
                        <div class="ld-detail-grid__label">Department</div>
                        <div class="ld-detail-grid__value">{{ $lead->department->name }}{{ $lead->division ? ' · ' . $lead->division->name : '' }}</div>
                    </div>
                @endif
                @if($lead->isLost() && ($lead->lostReason || $lead->lost_reason))
                    <div class="ld-detail-grid__field">
                        <div class="ld-detail-grid__label">Lost reason</div>
                        <div class="ld-detail-grid__value">{{ collect([$lead->lostReason?->label, $lead->lost_reason])->filter()->implode(' — ') }}</div>
                    </div>
                @endif
                @if($lead->isOnHold() && $lead->onHoldFromStage)
                    <div class="ld-detail-grid__field">
                        <div class="ld-detail-grid__label">On hold from</div>
                        <div class="ld-detail-grid__value">{{ $lead->onHoldFromStage->name }}</div>
                    </div>
                @endif
                @if($lead->closed_at)
                    <div class="ld-detail-grid__field">
                        <div class="ld-detail-grid__label">Closed</div>
                        <div class="ld-detail-grid__value">{{ $lead->closed_at->format('M j, Y') }}</div>
                    </div>
                @endif
                <div class="ld-detail-grid__field">
                    <div class="ld-detail-grid__label">Created</div>
                    <div class="ld-detail-grid__value">{{ $lead->created_at->format('M j, Y') }}</div>
                </div>
            </div>
        </div>

        @if($lead->customer)
            <p class="muted" style="font-size:13px;margin-bottom:14px;">
                Converted to customer <a href="{{ route('crm.contacts.show', $lead->customer) }}" class="pcat-link">{{ $lead->customer->name }}</a>
                @if($lead->converted_at) on {{ $lead->converted_at->format('M j, Y') }} @endif.
            </p>
        @endif

        @if($lead->products->isNotEmpty() || $lead->product_interest_note)
            <div class="pcat-inline" style="margin-bottom:16px;">
                <h2 style="font-size:13px;">Products of interest</h2>
                @if($lead->products->isNotEmpty())
                    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;">
                        @foreach($lead->products as $p)
                            <span class="ld-stage" style="color:var(--text);">
                                {{ $p->name }}@if($p->pivot->quantity !== null) × {{ rtrim(rtrim(number_format((float) $p->pivot->quantity, 2, '.', ''), '0'), '.') }}@endif
                            </span>
                        @endforeach
                    </div>
                @endif
                @if($lead->product_interest_note)
                    <p class="pcat-muted" style="white-space:pre-line;margin-top:8px;">{{ $lead->product_interest_note }}</p>
                @endif
            </div>
        @endif

        @if($lead->notes)
            <div class="pcat-inline" style="margin-bottom:16px;">
                <h2 style="font-size:13px;">Notes</h2>
                <p class="pcat-muted" style="white-space:pre-line;">{{ $lead->notes }}</p>
            </div>
        @endif

        @if($lead->customFieldValues->isNotEmpty())
            <div class="pcat-inline" style="margin-bottom:16px;">
                <h2 style="font-size:13px;">Custom fields</h2>
                <div class="ld-detail-grid" style="margin-top:8px;">
                    @foreach($lead->customFieldValues as $value)
                        @continue(!$value->customField || $value->displayValue() === '')
                        <div class="ld-detail-grid__field">
                            <div class="ld-detail-grid__label">{{ $value->customField->label }}</div>
                            <div class="ld-detail-grid__value">{{ $value->displayValue() }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @unless($hasOverviewContent)
            <p class="muted" style="font-size:13px;">No notes or custom field values yet.</p>
        @endunless

        <div class="pcat-inline" style="margin-top:16px;">
            <h2 style="font-size:13px;">Stage history</h2>
            <div style="margin-top:8px;">
                @include('crm::partials.stage-log-timeline', ['stageLogs' => $stageLogs])
            </div>
        </div>
    </section>

    <section id="tab-tasks" class="ps-panel" data-ld-panel hidden>
        @include('crm::partials.task-form', ['subjectType' => 'lead', 'subjectId' => $lead->id])
        <div style="margin-top:12px;">
            @include('crm::partials.task-list', ['tasks' => $lead->tasks])
        </div>
    </section>

    <section id="tab-activity" class="ps-panel" data-ld-panel hidden>
        @include('crm::partials.activity-form', ['subjectType' => 'lead', 'subjectId' => $lead->id])
        <div style="margin-top:12px;">
            @include('crm::partials.activity-timeline', ['activities' => $lead->activities])
        </div>
    </section>
</div>

<script>
(function () {
    document.querySelectorAll('[data-ld-tab]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var tab = btn.getAttribute('data-ld-tab');
            document.querySelectorAll('[data-ld-tab]').forEach(function (b) { b.classList.remove('is-active'); });
            document.querySelectorAll('[data-ld-panel]').forEach(function (p) { p.hidden = true; });
            btn.classList.add('is-active');
            var panel = document.getElementById('tab-' + tab);
            if (panel) panel.hidden = false;
        });
    });
})();
</script>

<div style="margin-top:14px;">
    <a href="{{ route('crm.projects.leads.index', $lead->project) }}" class="linkbtn"
       style="padding:7px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fa fa-arrow-left"></i> All leads
    </a>
</div>
@endsection
