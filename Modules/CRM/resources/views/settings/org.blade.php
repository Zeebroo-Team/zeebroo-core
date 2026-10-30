@extends('theme::layouts.app', ['title' => 'CRM Org Structure', 'heading' => 'CRM Org Structure'])

@section('content')
@include('product::partials.catalog-hub-styles')

<style>
    .crm-org-dept{border:1px solid var(--border);border-radius:10px;padding:14px;margin-bottom:14px;}
    .crm-org-dept__head{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:10px;}
    .crm-org-dept__head h2{margin:0;font-size:16px;}
    .crm-org-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px;}
    .crm-org-box{border:1px solid var(--border);border-radius:8px;padding:12px;}
    .crm-org-box h3{margin:0 0 8px;font-size:13px;text-transform:uppercase;letter-spacing:.03em;color:var(--muted,#6b7280);}
    .crm-org-row{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:8px;}
    .crm-org-row label{font-size:12px;}
    .crm-org-row input[type=text],.crm-org-row input[type=number],.crm-org-row select{padding:6px 8px;font-size:13px;border:1px solid var(--border);border-radius:6px;background:var(--card,#fff);color:var(--text);}
    .crm-org-warn{background:#fef3c7;color:#92400e;border-radius:6px;padding:6px 10px;font-size:12px;margin:0 0 6px;display:flex;gap:6px;align-items:center;}
    .crm-org-btn{padding:6px 12px;font-size:12px;border-radius:6px;border:1px solid var(--border);background:transparent;color:var(--text);cursor:pointer;}
    .crm-org-btn--primary{background:#7c3aed;border-color:#7c3aed;color:#fff;}
    .crm-org-btn--danger{color:#dc2626;}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('crm::partials.crm-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="pcat-banner pcat-banner--err">{{ $errors->first() }}</div>
    @endif

    <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
        Leads are routed through <strong style="color:var(--text);">departments</strong> (from HR) and their CRM
        <strong style="color:var(--text);">divisions</strong>. Each department works its leads in one pipeline. The HR
        department head is the CRM department head; a division head overrides it for that division.
    </p>

    @if(empty($departments))
        <section class="pcat-inline">
            <h2>No departments yet</h2>
            <p class="pcat-muted">Create departments in HR Management first, then come back to set up the CRM.</p>
        </section>
    @endif

    @foreach($departments as $d)
        @php
            $linkable = $pipelines->filter(fn ($p) => $p->department_id === null || (int) $p->department_id === $d['id']);
        @endphp
        <section class="crm-org-dept" id="dept-{{ $d['id'] }}">
            <div class="crm-org-dept__head">
                <h2><i class="fa fa-building"></i> {{ $d['name'] }}</h2>
                @if($d['settings']['is_marketing_intake'])
                    <span class="pcat-badge pcat-badge--on"><i class="fa fa-inbox"></i> Marketing intake</span>
                @endif
                <span class="muted" style="font-size:12px;">
                    Head: {{ $d['head']['name'] ?? '—' }}@if($d['co_head']) · Co-head: {{ $d['co_head']['name'] }}@endif
                    · {{ $d['staff_count'] }} staff
                </span>
            </div>

            @foreach($d['warnings'] as $w)
                <p class="crm-org-warn"><i class="fa fa-triangle-exclamation"></i> {{ $w }}</p>
            @endforeach

            <div class="crm-org-grid">
                {{-- Pipeline --}}
                <div class="crm-org-box">
                    <h3>Pipeline</h3>
                    @if($d['pipeline'])
                        <div class="crm-org-row">
                            <a href="{{ route('crm.projects.show', $d['pipeline']['id']) }}" class="pcat-link">
                                <i class="fa fa-diagram-project"></i> {{ $d['pipeline']['name'] }}
                            </a>
                            <form method="POST" action="{{ route('crm.org.pipeline.unlink', $d['id']) }}"
                                  onsubmit="return confirm('Unlink this pipeline? Its leads stay where they are.');">
                                @csrf @method('DELETE')
                                <button class="crm-org-btn crm-org-btn--danger" type="submit"><i class="fa fa-link-slash"></i> Unlink</button>
                            </form>
                        </div>
                    @else
                        <p class="pcat-muted" style="margin:0 0 8px;">No pipeline linked.</p>
                    @endif
                    <form method="POST" action="{{ route('crm.org.pipeline.link', $d['id']) }}" class="crm-org-row">
                        @csrf
                        <select name="project_id">
                            <option value="">+ Create a new pipeline</option>
                            @foreach($linkable as $p)
                                <option value="{{ $p->id }}" @selected($d['pipeline'] && $d['pipeline']['id'] === $p->id)>{{ $p->name }}</option>
                            @endforeach
                        </select>
                        <button class="crm-org-btn crm-org-btn--primary" type="submit">{{ $d['pipeline'] ? 'Change' : 'Link' }}</button>
                    </form>
                </div>

                {{-- Settings --}}
                <div class="crm-org-box">
                    <h3>Settings</h3>
                    <form method="POST" action="{{ route('crm.org.settings.update', $d['id']) }}">
                        @csrf @method('PUT')
                        <div class="crm-org-row">
                            <input type="hidden" name="is_marketing_intake" value="0">
                            <label><input type="checkbox" name="is_marketing_intake" value="1" @checked($d['settings']['is_marketing_intake'])>
                                Marketing intake (new leads land here first)</label>
                        </div>
                        <div class="crm-org-row">
                            <label style="min-width:170px;">Discount approval limit (%)</label>
                            <input type="number" name="discount_approval_limit_percent" min="0" max="100" step="0.01" style="width:100px;"
                                   value="{{ $d['settings']['discount_approval_limit_percent'] }}" placeholder="No limit">
                        </div>
                        <div class="crm-org-row">
                            <label style="min-width:170px;">Follow-up days</label>
                            <input type="text" name="followup_days" style="width:100px;"
                                   value="{{ implode(', ', $d['settings']['followup_days']) }}" placeholder="2, 5">
                        </div>
                        <button class="crm-org-btn crm-org-btn--primary" type="submit"><i class="fa fa-check"></i> Save settings</button>
                    </form>
                </div>

                {{-- Divisions --}}
                <div class="crm-org-box" style="grid-column:1 / -1;">
                    <h3>Divisions</h3>
                    @forelse($d['divisions'] as $v)
                        <form method="POST" action="{{ route('crm.org.divisions.update', $v['id']) }}" class="crm-org-row">
                            @csrf @method('PUT')
                            <input type="text" name="name" value="{{ $v['name'] }}" required maxlength="150" style="flex:1;min-width:160px;">
                            <select name="head_user_id" title="Division head">
                                <option value="">Head: department head</option>
                                @foreach($users as $u)
                                    <option value="{{ $u->id }}" @selected($v['head_user_id'] === $u->id)>Head: {{ $u->name }}</option>
                                @endforeach
                            </select>
                            <input type="number" name="sort_order" value="{{ $v['sort_order'] }}" min="0" style="width:70px;" title="Sort order">
                            <label><input type="checkbox" name="is_active" value="1" @checked($v['is_active'])> Active</label>
                            <button class="crm-org-btn" type="submit"><i class="fa fa-check"></i> Save</button>
                            <button class="crm-org-btn crm-org-btn--danger" type="submit" form="del-div-{{ $v['id'] }}"><i class="fa fa-trash"></i></button>
                        </form>
                        <form method="POST" action="{{ route('crm.org.divisions.destroy', $v['id']) }}" id="del-div-{{ $v['id'] }}"
                              onsubmit="return confirm('Delete this division?');">
                            @csrf @method('DELETE')
                        </form>
                    @empty
                        <p class="pcat-muted" style="margin:0 0 8px;">No divisions yet.</p>
                    @endforelse

                    <form method="POST" action="{{ route('crm.org.divisions.store', $d['id']) }}" class="crm-org-row" style="margin-top:10px;">
                        @csrf
                        <input type="hidden" name="is_active" value="1">
                        <input type="text" name="name" placeholder="New division name" required maxlength="150" style="flex:1;min-width:160px;">
                        <select name="head_user_id" title="Division head">
                            <option value="">Head: department head</option>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}">Head: {{ $u->name }}</option>
                            @endforeach
                        </select>
                        <button class="crm-org-btn crm-org-btn--primary" type="submit"><i class="fa fa-plus"></i> Add division</button>
                    </form>
                </div>
            </div>
        </section>
    @endforeach
</div>
@endsection
