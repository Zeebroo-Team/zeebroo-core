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
.ld-ws-box{border:1px solid var(--border);border-radius:10px;padding:12px;background:var(--card);margin-bottom:14px;}
.ld-ws-box__head{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:8px;}
.ld-ws-box__head h2{margin:0;font-size:13px;}
.ld-ws-btn{padding:5px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);border-radius:8px;cursor:pointer;}
.ld-ws-list{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:8px;}
.ld-ws-item{display:flex;gap:10px;align-items:flex-start;font-size:12px;}
.ld-ws-icon{width:26px;height:26px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:11px;color:var(--ic);background:color-mix(in srgb,var(--ic) 14%,transparent);}
.ld-ws-meta{color:var(--muted);font-size:11px;margin-top:2px;}
.ld-ws-body{white-space:pre-line;margin-top:3px;color:var(--text);}
.ld-ws-filters{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;}
.ld-ws-filter{font-size:11px;padding:3px 10px;border-radius:999px;border:1px solid var(--border);background:transparent;color:var(--muted);cursor:pointer;}
.ld-ws-filter.is-active{background:var(--primary);border-color:var(--primary);color:#fff;}
.ld-msg{border:1px solid var(--border);border-radius:10px;padding:10px 12px;background:var(--card);}
.ld-msg--reply{margin-left:34px;}
.ld-msg__head{display:flex;justify-content:space-between;gap:8px;font-size:12px;}
.ld-msg__actions{display:flex;gap:10px;font-size:11px;}
.ld-msg__actions button,.ld-msg__actions summary{background:none;border:none;color:var(--muted);cursor:pointer;padding:0;font-size:11px;}
.ld-msg__mention{font-size:11px;color:var(--primary);font-weight:600;}
.ld-msg__files{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;}
.ld-msg__files img{width:160px;height:120px;object-fit:cover;border-radius:8px;border:1px solid var(--border);display:block;}
.ld-msg__file{display:inline-flex;align-items:center;gap:6px;font-size:12px;padding:6px 10px;border:1px solid var(--border);border-radius:8px;color:var(--text);text-decoration:none;}
.ld-files{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:10px;}
.ld-file{border:1px solid var(--border);border-radius:10px;overflow:hidden;background:var(--card);display:flex;flex-direction:column;}
.ld-file__thumb{height:110px;display:flex;align-items:center;justify-content:center;background:color-mix(in srgb,var(--primary) 6%,transparent);font-size:30px;color:var(--muted);}
.ld-file__thumb img{width:100%;height:100%;object-fit:cover;}
.ld-file__info{padding:8px 10px;font-size:12px;display:flex;flex-direction:column;gap:2px;min-width:0;}
.ld-file__name{font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.ld-assign-dialog{border:1px solid var(--border);border-radius:12px;padding:0;background:var(--card);color:var(--text);width:min(440px,94vw);}
.ld-assign-dialog::backdrop{background:rgba(0,0,0,.45);}
.ld-assign-row{display:flex;align-items:center;gap:8px;padding:6px 4px;border-bottom:1px solid var(--border);font-size:13px;}
.ld-assign-row:last-child{border-bottom:none;}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('crm::partials.project-nav', ['project' => $lead->project])

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="pcat-banner pcat-banner--err" role="alert">{{ $errors->first() }}</div>
    @endif
    @if($lead->needs_review)
        <div class="pcat-banner" style="background:#fef3c7;color:#92400e;">
            <i class="fa fa-flag"></i> {{ \Modules\CRM\Models\Lead::reviewReasonLabel($lead->review_reason) ?? 'Needs review' }} — flagged for Marketing.
            @if($can['can_review'])
                <a href="{{ route('crm.inbox.index', ['filter' => 'review']) }}#lead-{{ $lead->id }}" class="pcat-link">Review in the inbox</a>
            @endif
        </div>
    @endif
    @if($lead->forwarded_at || $lead->verified_at)
        <p class="muted" style="margin:0 0 10px;font-size:12px;">
            @if($lead->verified_at)<i class="fa fa-circle-check"></i> Verified {{ $lead->verified_at->diffForHumans() }}{{ $lead->verifiedBy ? ' by ' . $lead->verifiedBy->name : '' }}.@endif
            @if($lead->forwarded_at)<i class="fa fa-share"></i> Forwarded {{ $lead->forwarded_at->diffForHumans() }}{{ $lead->forwardedBy ? ' by ' . $lead->forwardedBy->name : '' }}.@endif
        </p>
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
                @include('crm::partials.assignee-chips', ['assignees' => $assignees, 'emptyText' => 'Not assigned yet'])
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
        <button type="button" class="ps-tab" data-ld-tab="timeline">
            <i class="fa fa-clock-rotate-left"></i> Timeline
        </button>
        <button type="button" class="ps-tab" data-ld-tab="messages">
            <i class="fa fa-comments"></i> Messages
            @if($messages->isNotEmpty())<span class="ps-tab__count">{{ $messages->count() }}</span>@endif
        </button>
        <button type="button" class="ps-tab" data-ld-tab="files">
            <i class="fa fa-paperclip"></i> Files
            @if($attachments->isNotEmpty())<span class="ps-tab__count">{{ $attachments->count() }}</span>@endif
        </button>
        <button type="button" class="ps-tab" data-ld-tab="tasks">
            <i class="fa fa-list-check"></i> Tasks
            @if($lead->tasks->isNotEmpty())<span class="ps-tab__count">{{ $lead->tasks->count() }}</span>@endif
        </button>
    </nav>

    <section id="tab-overview" class="ps-panel" data-ld-panel>
        {{-- Who works the lead: visible to everyone who sees it; the head / admin manages it --}}
        <div class="ld-ws-box">
            <div class="ld-ws-box__head">
                <h2><i class="fa fa-user-group"></i> Assignees</h2>
                @if($can['can_assign'])
                    <button type="button" class="ld-ws-btn" data-ld-assign-open><i class="fa fa-user-pen"></i> Assign</button>
                @endif
            </div>
            @include('crm::partials.assignee-chips', ['assignees' => $assignees, 'emptyText' => 'Nobody is working this lead yet.'])
            @if($assignmentHistory !== [])
                <details style="margin-top:10px;">
                    <summary class="muted" style="font-size:12px;cursor:pointer;">Assignment history ({{ count($assignmentHistory) }})</summary>
                    <ul class="ld-ws-list" style="margin-top:8px;">
                        @foreach($assignmentHistory as $h)
                            <li class="ld-ws-item">
                                <span class="ld-ws-icon" style="--ic:{{ $h['action'] === 'forwarded' ? '#6366f1' : '#0ea5e9' }};"><i class="fa {{ $h['action'] === 'forwarded' ? 'fa-share' : 'fa-user-tag' }}"></i></span>
                                <div>
                                    <div>{{ $h['title'] }}</div>
                                    @if($h['note'])<div class="ld-ws-body">{{ $h['note'] }}</div>@endif
                                    <div class="ld-ws-meta">{{ $h['by']['name'] ?? 'System' }} · {{ $h['created_at'] ? \Illuminate\Support\Carbon::parse($h['created_at'])->format('M j, Y g:i A') : '' }}</div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </div>

        @if($can['can_assign'])
            <dialog class="ld-assign-dialog" id="ld-assign-dialog">
                <form method="POST" action="{{ route('crm.leads.assignees.sync', $lead) }}" style="padding:16px;display:flex;flex-direction:column;gap:10px;">
                    @csrf
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <strong>Assign lead</strong>
                        <button type="button" class="ld-ws-btn" data-ld-assign-close><i class="fa fa-xmark"></i></button>
                    </div>
                    <p class="muted" style="margin:0;font-size:12px;">Tick everyone who works this lead; the starred one is primary. Only people in {{ $lead->department?->name ?? 'this pipeline' }} can be picked.</p>
                    @php
                        $assignedIds = collect($assignees)->pluck('id')->all();
                        $primaryId   = collect($assignees)->firstWhere('is_primary', true)['id'] ?? null;
                        // Someone already on the lead who has since left the department stays listed (unticking removes them)
                        $pickable    = collect($assignees)->reject(fn ($a) => $assignableUsers->contains('id', $a['id']))
                            ->map(fn ($a) => (object) ['id' => $a['id'], 'name' => $a['name'] . ' (not in the department)'])
                            ->concat($assignableUsers);
                    @endphp
                    <div style="max-height:280px;overflow-y:auto;">
                        @forelse($pickable as $u)
                            <label class="ld-assign-row">
                                <input type="checkbox" name="user_ids[]" value="{{ $u->id }}" @checked(in_array($u->id, $assignedIds))>
                                <span style="flex:1;">{{ $u->name }}</span>
                                <span title="Primary" style="font-size:11px;color:var(--muted);">
                                    <input type="radio" name="primary_user_id" value="{{ $u->id }}" @checked($primaryId === $u->id)> <i class="fa fa-star" style="color:#f59e0b;"></i>
                                </span>
                            </label>
                        @empty
                            <p class="muted" style="font-size:12px;">Nobody in this department has a Zeebroo login yet. Link the staff to users in HR.</p>
                        @endforelse
                    </div>
                    <textarea name="note" rows="2" maxlength="2000" placeholder="Note for the team (optional)" style="resize:vertical;"></textarea>
                    <div style="display:flex;justify-content:flex-end;gap:8px;">
                        <button type="button" class="ld-ws-btn" data-ld-assign-close>Cancel</button>
                        <button type="submit" class="linkbtn" style="padding:7px 14px;font-size:12px;">Save assignees</button>
                    </div>
                </form>
            </dialog>
        @endif

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

    {{-- Everything that happened on the lead, newest first (TimelineService) --}}
    <section id="tab-timeline" class="ps-panel" data-ld-panel hidden>
        @if($can['can_edit'])
            <div class="ld-ws-box">
                <div class="ld-ws-box__head"><h2><i class="fa fa-pen"></i> Log an activity</h2></div>
                @include('crm::partials.activity-form', ['subjectType' => 'lead', 'subjectId' => $lead->id])
            </div>
        @endif
        <div class="ld-ws-filters">
            <button type="button" class="ld-ws-filter is-active" data-ld-tl-filter="">All</button>
            @foreach($timelineTypes as $t)
                <button type="button" class="ld-ws-filter" data-ld-tl-filter="{{ $t['key'] }}">{{ $t['label'] }}</button>
            @endforeach
        </div>
        @if($timeline === [])
            <p class="muted" style="font-size:13px;">Nothing has happened on this lead yet.</p>
        @else
            <ul class="ld-ws-list">
                @foreach($timeline as $e)
                    <li class="ld-ws-item" data-ld-tl-type="{{ $e['type'] }}">
                        <span class="ld-ws-icon" style="--ic:{{ $e['color'] }};"><i class="fa {{ $e['icon'] }}"></i></span>
                        <div style="min-width:0;">
                            <div><strong>{{ $e['user']['name'] ?? 'System' }}</strong> · {{ $e['title'] }}</div>
                            @if($e['body'])<div class="ld-ws-body">{{ $e['body'] }}</div>@endif
                            <div class="ld-ws-meta">{{ $e['at'] ? \Illuminate\Support\Carbon::parse($e['at'])->format('M j, Y g:i A') . ' · ' . \Illuminate\Support\Carbon::parse($e['at'])->diffForHumans() : '' }}</div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Internal discussion — never shown to the customer --}}
    <section id="tab-messages" class="ps-panel" data-ld-panel hidden>
        @php $byParent = $messages->groupBy(fn ($m) => $m['parent_id'] ?? 0); $mentionNames = $mentionableUsers->pluck('name', 'id'); @endphp
        @if($can['can_contribute'])
            <form method="POST" action="{{ route('crm.leads.messages.store', $lead) }}" enctype="multipart/form-data" class="ld-ws-box" id="ld-msg-form" style="display:flex;flex-direction:column;gap:8px;">
                @csrf
                <input type="hidden" name="parent_id" id="ld-msg-parent">
                <div id="ld-msg-replying" class="muted" style="display:none;font-size:12px;">
                    <i class="fa fa-reply"></i> Replying to <strong id="ld-msg-replying-to"></strong>
                    <button type="button" class="ld-ws-btn" id="ld-msg-cancel-reply" style="padding:1px 8px;">Cancel</button>
                </div>
                <textarea name="body" rows="3" maxlength="5000" placeholder="Write an internal note for the team…" style="resize:vertical;"></textarea>
                <label class="muted" style="font-size:12px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                    <i class="fa fa-paperclip"></i> Attach files or images
                    <input type="file" name="files[]" multiple accept="{{ collect($attachmentRules['mimes'])->map(fn ($m) => '.' . $m)->implode(',') }}" style="font-size:12px;">
                </label>
                <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;">
                    <details>
                        <summary class="muted" style="font-size:12px;cursor:pointer;"><i class="fa fa-at"></i> Mention people</summary>
                        <div style="display:flex;flex-wrap:wrap;gap:6px 14px;margin-top:6px;max-width:520px;font-size:12px;">
                            @foreach($mentionableUsers as $u)
                                <label><input type="checkbox" name="mentions[]" value="{{ $u->id }}"> {{ $u->name }}</label>
                            @endforeach
                        </div>
                    </details>
                    <button type="submit" class="linkbtn" style="padding:7px 14px;font-size:12px;"><i class="fa fa-paper-plane"></i> Post</button>
                </div>
            </form>
        @else
            <p class="muted" style="font-size:12px;"><i class="fa fa-lock"></i> You can read the messages on this lead but not post — that needs edit rights or membership of its department.</p>
        @endif

        @if($messages->isEmpty())
            <p class="muted" style="font-size:13px;">No messages yet.</p>
        @else
            <div class="ld-ws-list">
                @foreach($byParent->get(0, collect()) as $m)
                    @foreach(collect([$m])->concat($byParent->get($m['id'], collect())) as $msg)
                        <div class="ld-msg{{ $msg['parent_id'] ? ' ld-msg--reply' : '' }}" id="message-{{ $msg['id'] }}">
                            <div class="ld-msg__head">
                                <span><strong>{{ $msg['user']['name'] ?? 'Deleted user' }}</strong>
                                    <span class="muted" style="font-size:11px;">· {{ \Illuminate\Support\Carbon::parse($msg['created_at'])->diffForHumans() }}{{ $msg['edited_at'] ? ' · edited' : '' }}</span>
                                </span>
                                <span class="ld-msg__actions">
                                    @if($can['can_contribute'] && ! $msg['parent_id'])
                                        <button type="button" data-ld-reply="{{ $msg['id'] }}" data-ld-reply-name="{{ $msg['user']['name'] ?? 'message' }}"><i class="fa fa-reply"></i> Reply</button>
                                    @endif
                                    @if($msg['can_edit'])
                                        <details>
                                            <summary><i class="fa fa-pen"></i> Edit</summary>
                                            <form method="POST" action="{{ route('crm.leads.messages.update', [$lead, $msg['id']]) }}" style="display:flex;flex-direction:column;gap:6px;margin-top:6px;min-width:260px;">
                                                @csrf @method('PUT')
                                                @foreach($msg['mentions'] as $mid)<input type="hidden" name="mentions[]" value="{{ $mid }}">@endforeach
                                                <textarea name="body" rows="3" maxlength="5000" required>{{ $msg['body'] }}</textarea>
                                                <button type="submit" class="linkbtn" style="padding:5px 10px;font-size:12px;">Save</button>
                                            </form>
                                        </details>
                                    @endif
                                    @if($msg['can_delete'])
                                        <form method="POST" action="{{ route('crm.leads.messages.destroy', [$lead, $msg['id']]) }}" onsubmit="return confirm('Delete this message{{ $msg['parent_id'] ? '' : ' and its replies' }}?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" style="color:#ef4444;"><i class="fa fa-trash"></i></button>
                                        </form>
                                    @endif
                                </span>
                            </div>
                            @if($msg['body'] !== '')<div class="ld-ws-body">{{ $msg['body'] }}</div>@endif
                            @if($msg['attachments'] !== [])
                                <div class="ld-msg__files">
                                    @foreach($msg['attachments'] as $f)
                                        @if($f['is_image'])
                                            <a href="{{ route('crm.leads.attachments.download', [$lead, $f['id']]) }}" title="Download {{ $f['name'] }}">
                                                <img src="{{ route('crm.leads.attachments.download', [$lead, $f['id'], 'inline' => 1]) }}" alt="{{ $f['name'] }}" loading="lazy">
                                            </a>
                                        @else
                                            <a class="ld-msg__file" href="{{ route('crm.leads.attachments.download', [$lead, $f['id']]) }}">
                                                <i class="fa {{ str_contains((string) $f['mime_type'], 'pdf') ? 'fa-file-pdf' : 'fa-file' }}"></i> {{ $f['name'] }} <span class="muted">{{ $f['size'] }}</span>
                                            </a>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                            @if($msg['mentions'] !== [])
                                <div style="margin-top:4px;">
                                    @foreach($msg['mentions'] as $mid)<span class="ld-msg__mention">{{ '@' . $mentionNames->get($mid, 'user #' . $mid) }}</span> @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                @endforeach
            </div>
        @endif
    </section>

    {{-- Files: stored privately, only served through the authorised download route --}}
    <section id="tab-files" class="ps-panel" data-ld-panel hidden>
        @if($can['can_contribute'])
            <form method="POST" action="{{ route('crm.leads.attachments.store', $lead) }}" enctype="multipart/form-data" class="ld-ws-box" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                @csrf
                <input type="file" name="files[]" multiple required accept="{{ collect($attachmentRules['mimes'])->map(fn ($m) => '.' . $m)->implode(',') }}">
                <button type="submit" class="linkbtn" style="padding:7px 14px;font-size:12px;"><i class="fa fa-upload"></i> Upload</button>
                <span class="muted" style="font-size:11px;">Up to 10 files, {{ round($attachmentRules['max_kb'] / 1024) }} MB each · {{ implode(', ', $attachmentRules['mimes']) }}</span>
            </form>
        @endif
        @if($attachments->isEmpty())
            <p class="muted" style="font-size:13px;">No files on this lead yet.</p>
        @else
            <div class="ld-files">
                @foreach($attachments as $f)
                    <div class="ld-file">
                        <a class="ld-file__thumb" href="{{ route('crm.leads.attachments.download', [$lead, $f['id']]) }}" title="Download {{ $f['name'] }}">
                            @if($f['is_image'])
                                <img src="{{ route('crm.leads.attachments.download', [$lead, $f['id'], 'inline' => 1]) }}" alt="{{ $f['name'] }}" loading="lazy">
                            @else
                                <i class="fa {{ str_contains((string) $f['mime_type'], 'pdf') ? 'fa-file-pdf' : 'fa-file' }}"></i>
                            @endif
                        </a>
                        <div class="ld-file__info">
                            <span class="ld-file__name" title="{{ $f['name'] }}">{{ $f['name'] }}</span>
                            <span class="muted" style="font-size:11px;">{{ $f['size'] }} · {{ $f['uploaded_by']['name'] ?? 'Unknown' }} · {{ \Illuminate\Support\Carbon::parse($f['created_at'])->format('M j, Y') }}</span>
                            <span style="display:flex;gap:10px;margin-top:4px;font-size:12px;">
                                <a href="{{ route('crm.leads.attachments.download', [$lead, $f['id']]) }}" class="pcat-link"><i class="fa fa-download"></i> Download</a>
                                @if($f['can_delete'])
                                    <form method="POST" action="{{ route('crm.leads.attachments.destroy', [$lead, $f['id']]) }}" onsubmit="return confirm('Delete this file?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" style="background:none;border:none;color:#ef4444;cursor:pointer;padding:0;font-size:12px;"><i class="fa fa-trash"></i> Delete</button>
                                    </form>
                                @endif
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <section id="tab-tasks" class="ps-panel" data-ld-panel hidden>
        @include('crm::partials.task-form', ['subjectType' => 'lead', 'subjectId' => $lead->id, 'assignableUsers' => $taskAssignees])
        <div style="margin-top:12px;">
            @include('crm::partials.task-list', ['tasks' => $lead->tasks])
        </div>
    </section>
</div>

<script>
(function () {
    function openTab(tab) {
        var btn = document.querySelector('[data-ld-tab="' + tab + '"]');
        if (!btn) return;
        document.querySelectorAll('[data-ld-tab]').forEach(function (b) { b.classList.remove('is-active'); });
        document.querySelectorAll('[data-ld-panel]').forEach(function (p) { p.hidden = true; });
        btn.classList.add('is-active');
        var panel = document.getElementById('tab-' + tab);
        if (panel) panel.hidden = false;
    }
    document.querySelectorAll('[data-ld-tab]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var tab = btn.getAttribute('data-ld-tab');
            openTab(tab);
            history.replaceState(null, '', '#' + tab);
        });
    });
    // Actions redirect back with #messages / #files — reopen that tab
    if (location.hash) openTab(location.hash.slice(1));

    document.querySelectorAll('[data-ld-tl-filter]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var type = btn.getAttribute('data-ld-tl-filter');
            document.querySelectorAll('[data-ld-tl-filter]').forEach(function (b) { b.classList.toggle('is-active', b === btn); });
            document.querySelectorAll('[data-ld-tl-type]').forEach(function (row) {
                row.hidden = type !== '' && row.getAttribute('data-ld-tl-type') !== type;
            });
        });
    });

    var dialog = document.getElementById('ld-assign-dialog');
    document.querySelectorAll('[data-ld-assign-open]').forEach(function (b) { b.addEventListener('click', function () { dialog && dialog.showModal(); }); });
    document.querySelectorAll('[data-ld-assign-close]').forEach(function (b) { b.addEventListener('click', function () { dialog && dialog.close(); }); });
    if (dialog) {
        // The primary must be ticked: picking a star ticks its row
        dialog.addEventListener('change', function (e) {
            if (e.target.name === 'primary_user_id') e.target.closest('label').querySelector('input[type=checkbox]').checked = true;
            if (e.target.type === 'checkbox' && !e.target.checked) {
                var star = e.target.closest('label').querySelector('input[type=radio]');
                if (star) star.checked = false;
            }
        });
    }

    document.querySelectorAll('[data-ld-reply]').forEach(function (b) {
        b.addEventListener('click', function () {
            document.getElementById('ld-msg-parent').value = b.getAttribute('data-ld-reply');
            document.getElementById('ld-msg-replying-to').textContent = b.getAttribute('data-ld-reply-name');
            document.getElementById('ld-msg-replying').style.display = '';
            document.querySelector('#ld-msg-form textarea').focus();
        });
    });
    var cancelReply = document.getElementById('ld-msg-cancel-reply');
    if (cancelReply) cancelReply.addEventListener('click', function () {
        document.getElementById('ld-msg-parent').value = '';
        document.getElementById('ld-msg-replying').style.display = 'none';
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
