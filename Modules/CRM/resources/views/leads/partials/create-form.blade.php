@php
    use Modules\CRM\Models\Lead;
    $editing = isset($lead) && $lead instanceof Lead;
    $assignableUsers = $assignableUsers ?? collect();
    $stageOptions = $stageOptions ?? collect();
    $customFields = $customFields ?? collect();
    $leadForm = $leadForm ?? null;
    $defaultStageId = $editing ? $lead->stage_id : optional($stageOptions->first(fn ($s) => !$s->is_won && !$s->is_lost))->id;
@endphp

@if($errors->any())
    <div class="pcat-banner pcat-banner--err" role="alert">{{ $errors->first() }}</div>
@endif

<form method="POST"
      action="{{ $editing ? route('crm.leads.update', $lead) : route('crm.projects.leads.store', $project) }}"
      class="pcat-form-grid pcat-form-grid--2">
    @csrf
    @if($editing) @method('PUT') @endif

    <div class="pcat-field">
        <label for="ld-name">Name <span style="color:#ef4444;">*</span></label>
        <input id="ld-name" type="text" name="name" maxlength="150" required
               value="{{ old('name', $editing ? $lead->name : '') }}">
        @error('name')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>

    <div class="pcat-field">
        <label for="ld-stage">Stage</label>
        <select id="ld-stage" name="stage_id">
            @foreach($stageOptions as $opt)
                <option value="{{ $opt->id }}" @selected((int) old('stage_id', $defaultStageId) === $opt->id)>{{ $opt->name }}</option>
            @endforeach
        </select>
        @error('stage_id')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
        <div class="muted" style="font-size:11px;margin-top:4px;">
            Need a different stage? <a href="{{ route('crm.projects.stages.index', $project) }}" class="pcat-link">Manage stages</a>.
        </div>
    </div>

    {{--
        These fields (and their labels, placeholders, help text, required flags, and
        order) come straight from the project's lead form builder — the same fields a
        public visitor would fill out. Rendered flat, in path order: the builder's
        row/column layout is a public-page/preview concern only, not used here. Name
        is skipped since it already has its own fixed input above.
    --}}
    @if($leadForm)
        @foreach($leadForm->fieldBlocksWithPaths() as $path => $block)
            @continue(($block['field'] ?? '') === 'name')
            @include('crm::leads.partials.lead-form-field', ['path' => $path, 'block' => $block, 'customFields' => $customFields, 'lead' => $editing ? $lead : null])
        @endforeach
    @endif

    {{--
        Pipeline-management fields: staff-only concepts a public form can't express.
        Hidden on quick "Add lead" to keep that form short — set them later via Edit.
    --}}
    @if($editing)
        <div class="pcat-field">
            <label for="ld-value">Estimated value</label>
            <input id="ld-value" type="number" name="estimated_value" min="0" step="0.01" inputmode="decimal"
                   value="{{ old('estimated_value', $lead->estimated_value) }}">
            @error('estimated_value')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
        </div>

        <div class="pcat-field">
            <label for="ld-close">Expected close date</label>
            <input id="ld-close" type="date" name="expected_close_date"
                   value="{{ old('expected_close_date', $lead->expected_close_date ? $lead->expected_close_date->format('Y-m-d') : '') }}">
            @error('expected_close_date')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
        </div>

        <div class="pcat-field">
            <label for="ld-source">Source</label>
            <select id="ld-source" name="source">
                <option value="">— Unknown —</option>
                @foreach(['website' => 'Website', 'referral' => 'Referral', 'walk-in' => 'Walk-in', 'social' => 'Social media', 'phone' => 'Phone', 'other' => 'Other'] as $key => $label)
                    <option value="{{ $key }}" @selected(old('source', $lead->source) === $key)>{{ $label }}</option>
                @endforeach
            </select>
            @error('source')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
        </div>

        <div class="pcat-field">
            <label for="ld-assignee">Assigned to</label>
            <select id="ld-assignee" name="assigned_to">
                <option value="">— Unassigned —</option>
                @foreach($assignableUsers as $u)
                    <option value="{{ $u->id }}" @selected(old('assigned_to', $lead->assigned_to) == $u->id)>{{ $u->name }}</option>
                @endforeach
            </select>
            @error('assigned_to')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
        </div>
    @endif

    <div class="pcat-field" style="grid-column:1/-1;">
        <label for="ld-notes">Notes</label>
        <textarea id="ld-notes" name="notes" maxlength="5000"
                  placeholder="Background, requirements, next steps…">{{ old('notes', $editing ? $lead->notes : '') }}</textarea>
        @error('notes')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>

    <div style="grid-column:1/-1;display:flex;justify-content:flex-end;gap:8px;">
        @if($editing)
            <a href="{{ route('crm.leads.show', $lead) }}"
               class="linkbtn" style="padding:8px 16px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">
                Cancel
            </a>
        @endif
        <button type="submit" class="linkbtn" style="padding:8px 16px;font-size:13px;">
            {{ $editing ? 'Save changes' : 'Add lead' }}
        </button>
    </div>
</form>
