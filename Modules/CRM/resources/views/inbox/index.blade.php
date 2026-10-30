@extends('theme::layouts.app', ['title' => 'Marketing Inbox', 'heading' => 'Marketing Inbox'])

@section('content')
@include('product::partials.catalog-hub-styles')

<style>
    .crm-inbox-card{border:1px solid var(--border);border-radius:10px;padding:12px 14px;margin-bottom:10px;}
    .crm-inbox-card--flag{border-color:#f59e0b;box-shadow:inset 3px 0 0 #f59e0b;}
    .crm-inbox-head{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
    .crm-inbox-head h3{margin:0;font-size:15px;}
    .crm-inbox-meta{font-size:12px;color:var(--muted,#6b7280);display:flex;gap:12px;flex-wrap:wrap;margin-top:4px;}
    .crm-inbox-tag{font-size:11px;font-weight:600;padding:2px 8px;border-radius:999px;background:var(--border);color:var(--text);}
    .crm-inbox-tag--warn{background:#fef3c7;color:#92400e;}
    .crm-inbox-tag--ok{background:#dcfce7;color:#166534;}
    .crm-inbox-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:12px;margin-top:10px;}
    .crm-inbox-box{border:1px solid var(--border);border-radius:8px;padding:10px;}
    .crm-inbox-box h4{margin:0 0 8px;font-size:12px;text-transform:uppercase;letter-spacing:.03em;color:var(--muted,#6b7280);}
    .crm-inbox-box label{display:block;font-size:11px;color:var(--muted,#6b7280);margin:6px 0 2px;}
    .crm-inbox-box input,.crm-inbox-box select,.crm-inbox-box textarea{width:100%;padding:6px 8px;font-size:13px;border:1px solid var(--border);border-radius:6px;background:var(--card,#fff);color:var(--text);box-sizing:border-box;}
    .crm-inbox-btn{padding:6px 12px;font-size:12px;border-radius:6px;border:1px solid var(--border);background:transparent;color:var(--text);cursor:pointer;margin-top:8px;}
    .crm-inbox-btn--primary{background:#7c3aed;border-color:#7c3aed;color:#fff;}
    .crm-inbox-dup{display:flex;align-items:center;gap:8px;justify-content:space-between;font-size:12px;padding:6px 0;border-top:1px dashed var(--border);}
    .crm-inbox-dup:first-of-type{border-top:0;}
    .crm-inbox-actions{display:flex;gap:6px;flex-wrap:wrap;}
    .crm-inbox-actions form{display:inline;}
</style>

@php
    $keep = ['filter' => $filter, 'q' => $search];
@endphp

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('crm::partials.crm-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="pcat-banner pcat-banner--err">{{ $errors->first() }}</div>
    @endif

    <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
        Every new lead lands here first@if($intakeProject) (pipeline <strong style="color:var(--text);">{{ $intakeProject->name }}</strong>)@endif.
        Check and clean the details, verify, then forward it to the department that will work it.
        Leads flagged <strong style="color:var(--text);">needs review</strong> are repeat enquiries, existing customers or possible duplicates.
    </p>

    @unless($intakeProject)
        <div class="pcat-banner pcat-banner--err">
            No marketing intake pipeline is set up yet, so new leads stay in the pipeline they were entered in.
            An admin can mark the marketing department as the intake under CRM → Org Structure.
        </div>
    @endunless

    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px;align-items:center;">
        @foreach($filters as $key => $label)
            <a href="{{ route('crm.inbox.index', ['filter' => $key, 'q' => $search ?: null]) }}"
               style="padding:4px 12px;border-radius:999px;font-size:12px;font-weight:600;text-decoration:none;border:1px solid var(--border);
                      {{ $filter === $key ? 'background:var(--primary);color:#fff;border-color:var(--primary);' : 'background:transparent;color:var(--muted);' }}">
                {{ $label }} ({{ $counts[$key] ?? 0 }})
            </a>
        @endforeach
        <form method="GET" action="{{ route('crm.inbox.index') }}" style="margin-left:auto;display:flex;gap:6px;">
            <input type="hidden" name="filter" value="{{ $filter }}">
            <input type="search" name="q" value="{{ $search }}" placeholder="Search name, phone, email…"
                   style="padding:6px 10px;font-size:13px;border:1px solid var(--border);border-radius:6px;background:var(--card,#fff);color:var(--text);">
        </form>
    </div>

    @forelse($leads as $lead)
        @php
            $row = $rows[$lead->id];
            // Never offer the pipeline the lead is already in (e.g. a repeat enquiry on a Sales lead)
            $leadTargets = collect($targets)->reject(fn ($t) => $t['pipeline']['id'] === $lead->project_id)->values()->all();
        @endphp
        <section class="crm-inbox-card {{ $row['needs_review'] ? 'crm-inbox-card--flag' : '' }}" id="lead-{{ $lead->id }}">
            <div class="crm-inbox-head">
                <h3>{{ $lead->name }}</h3>
                @if($row['needs_review'])
                    <span class="crm-inbox-tag crm-inbox-tag--warn"><i class="fa fa-flag"></i> {{ $row['review_reason_label'] ?? 'Needs review' }}</span>
                @endif
                @if($row['verified_at'])
                    <span class="crm-inbox-tag crm-inbox-tag--ok"><i class="fa fa-check"></i> Verified{{ $row['verified_by_name'] ? ' by ' . $row['verified_by_name'] : '' }}</span>
                @endif
                @if($row['source_label'])
                    <span class="crm-inbox-tag">{{ $row['source_label'] }}</span>
                @endif
                @unless($row['in_intake'])
                    <span class="crm-inbox-tag">{{ $row['project']['name'] ?? '' }} · {{ $row['stage']['name'] ?? '' }}</span>
                @endunless
                <a href="{{ route('crm.leads.show', $lead) }}" class="pcat-link" style="margin-left:auto;font-size:12px;">Open lead <i class="fa fa-arrow-right"></i></a>
            </div>
            <div class="crm-inbox-meta">
                @if($lead->phone)<span><i class="fa fa-phone"></i> {{ $lead->phone }}</span>@endif
                @if($lead->email)<span><i class="fa fa-envelope"></i> {{ $lead->email }}</span>@endif
                @if($lead->company)<span><i class="fa fa-building"></i> {{ $lead->company }}</span>@endif
                @if($row['requested_department'])<span><i class="fa fa-bullseye"></i> Asked for {{ $row['requested_department']['name'] }}</span>@endif
                @if($row['customer'])<span><i class="fa fa-user-check"></i> Customer: {{ $row['customer']['name'] }}</span>@endif
                <span><i class="fa fa-clock"></i> {{ $lead->created_at?->diffForHumans() }}{{ $row['created_by_name'] ? ' by ' . $row['created_by_name'] : '' }}</span>
            </div>

            <div class="crm-inbox-grid">
                {{-- Details --}}
                <div class="crm-inbox-box">
                    <h4>Details</h4>
                    @if($row['can']['edit'])
                        <form method="POST" action="{{ route('crm.inbox.update', [$lead] + $keep) }}">
                            @csrf @method('PUT')
                            <label>Name</label><input type="text" name="name" value="{{ $lead->name }}" maxlength="150" required>
                            <label>Company</label><input type="text" name="company" value="{{ $lead->company }}" maxlength="255">
                            <label>Phone</label><input type="text" name="phone" value="{{ $lead->phone }}" maxlength="50">
                            <label>Email</label><input type="email" name="email" value="{{ $lead->email }}" maxlength="255">
                            <label>Source</label>
                            <select name="source">
                                @foreach($sources as $s)
                                    <option value="{{ $s['key'] }}" @selected($lead->source === $s['key'])>{{ $s['label'] }}</option>
                                @endforeach
                            </select>
                            <label>Product interest</label><textarea name="product_interest_note" rows="2" maxlength="2000">{{ $lead->product_interest_note }}</textarea>
                            <label>Notes</label><textarea name="notes" rows="2" maxlength="5000">{{ $lead->notes }}</textarea>
                            <button class="crm-inbox-btn" type="submit"><i class="fa fa-floppy-disk"></i> Save details</button>
                        </form>
                    @else
                        <p class="muted" style="font-size:12px;margin:0;">{{ $lead->notes ?: 'No notes.' }}</p>
                    @endif
                    <div class="crm-inbox-actions">
                        @if($row['can']['edit'] && ! $row['verified_at'])
                            <form method="POST" action="{{ route('crm.inbox.verify', [$lead] + $keep) }}">@csrf
                                <button class="crm-inbox-btn" type="submit"><i class="fa fa-circle-check"></i> Mark verified</button>
                            </form>
                        @endif
                        @if($row['can']['review'] && $row['needs_review'])
                            <form method="POST" action="{{ route('crm.inbox.review', [$lead] + $keep) }}">@csrf
                                <button class="crm-inbox-btn" type="submit"><i class="fa fa-flag-checkered"></i> Mark reviewed</button>
                            </form>
                        @endif
                    </div>
                </div>

                {{-- Forward --}}
                @if($row['can']['forward'])
                    <div class="crm-inbox-box">
                        <h4>{{ $row['in_intake'] ? 'Forward' : 'Move to another department' }}</h4>
                        @unless($row['in_intake'])
                            <p class="muted" style="font-size:12px;margin:0 0 6px;">
                                Already forwarded — this lead is in the {{ $row['project']['name'] ?? '' }} pipeline.
                                It is here only to review the flag: use Mark reviewed or Merge.
                            </p>
                        @endunless
                        @if(empty($leadTargets))
                            <p class="muted" style="font-size:12px;margin:0;">No other department has a pipeline. Link pipelines under CRM → Org Structure.</p>
                        @else
                            <form method="POST" action="{{ route('crm.inbox.forward', [$lead] + $keep) }}" data-forward-form>
                                @csrf
                                <label>Department</label>
                                <select name="department_id" required data-forward-dept>
                                    <option value="">Choose…</option>
                                    @foreach($leadTargets as $t)
                                        <option value="{{ $t['id'] }}" @selected(($row['requested_department']['id'] ?? null) === $t['id'])>{{ $t['name'] }}</option>
                                    @endforeach
                                </select>
                                <label>Division</label>
                                <select name="division_id" data-forward-div><option value="">Whole department</option></select>
                                <label>Note for the department (optional)</label>
                                <textarea name="note" rows="2" maxlength="2000"></textarea>
                                <button class="crm-inbox-btn crm-inbox-btn--primary" type="submit"><i class="fa fa-share"></i> Forward</button>
                            </form>
                        @endif
                    </div>
                @endif

                {{-- Duplicates --}}
                @if(! empty($row['duplicates']['leads']) || ! empty($row['duplicates']['customers']) || $row['duplicate_of'])
                    <div class="crm-inbox-box">
                        <h4>Matches</h4>
                        @foreach($row['duplicates']['leads'] ?? [] as $m)
                            <div class="crm-inbox-dup">
                                <span>
                                    <strong>{{ $m['name'] }}</strong> #{{ $m['id'] }}<br>
                                    <span class="muted">{{ $m['project_name'] }} · {{ $m['stage_name'] }}{{ $m['is_open'] ? '' : ' (closed)' }} · {{ $m['phone'] ?: $m['email'] }}</span>
                                </span>
                                @if($row['can']['review'])
                                    <form method="POST" action="{{ route('crm.inbox.merge', [$lead] + $keep) }}"
                                          onsubmit="return confirm('Merge this lead into lead #{{ $m['id'] }}? Its activities and tasks move there and this lead is deleted.');">
                                        @csrf
                                        <input type="hidden" name="target_lead_id" value="{{ $m['id'] }}">
                                        <button class="crm-inbox-btn" type="submit" style="margin:0;"><i class="fa fa-code-merge"></i> Merge into</button>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                        @foreach($row['duplicates']['customers'] ?? [] as $c)
                            <div class="crm-inbox-dup">
                                <span><i class="fa fa-user-check"></i> Customer <strong>{{ $c['name'] }}</strong> · {{ $c['phone'] ?: $c['email'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    @empty
        <p class="muted" style="margin:24px 0;font-size:13px;">
            {{ $search !== '' ? 'No leads match your search.' : 'The inbox is empty — nothing waiting to be verified or forwarded.' }}
        </p>
    @endforelse
</div>

<script>
    (function () {
        const divisions = @json(collect($targets)->mapWithKeys(fn ($t) => [$t['id'] => $t['divisions']]));
        document.querySelectorAll('[data-forward-form]').forEach(function (form) {
            const dept = form.querySelector('[data-forward-dept]');
            const div  = form.querySelector('[data-forward-div]');
            const fill = function () {
                div.innerHTML = '<option value="">Whole department</option>';
                (divisions[dept.value] || []).forEach(function (d) {
                    const o = document.createElement('option');
                    o.value = d.id; o.textContent = d.name;
                    div.appendChild(o);
                });
            };
            dept.addEventListener('change', fill);
            fill();
        });
    })();
</script>
@endsection
