@php
    $project     = $project ?? null;
    $projectType = old('project_type', $project?->project_type ?? 'in_house');
    $assignType  = old('assignment_type', $project?->assignment_type ?? 'none');
@endphp

<div class="pcat-field" style="margin-bottom:12px;">
    <label>Project Image <span style="font-weight:400;color:var(--muted);">(optional)</span></label>
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        <div style="width:52px;height:52px;border-radius:8px;background:var(--border);display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;">
            @if($project?->imageFile)
                <img src="{{ $project->imageFile->publicUrl() }}" alt="" style="width:100%;height:100%;object-fit:cover;">
            @else
                <i class="fa fa-image" style="color:var(--muted);"></i>
            @endif
        </div>
        <input type="file" name="image" accept="image/png,image/jpeg,image/gif,image/webp" style="flex:1;min-width:200px;width:auto;">
        @if($project?->imageFile)
            <label style="display:inline-flex;align-items:center;gap:6px;font-weight:400;font-size:12px;color:var(--muted);white-space:nowrap;margin:0;">
                <input type="checkbox" name="remove_image" value="1"
                       style="width:auto;height:auto;padding:0;border:none;background:none;border-radius:0;accent-color:var(--primary);">
                Remove image
            </label>
        @endif
    </div>
    @error('image')<p style="color:#f87171;font-size:11px;margin:4px 0 0;">{{ $message }}</p>@enderror
</div>

<div class="pcat-form-grid pcat-form-grid--2" style="margin-bottom:12px;">
    <div class="pcat-field">
        <label>Project Type</label>
        <select name="project_type" class="pm-af-type">
            <option value="in_house" @selected($projectType !== 'customer')>In-house Project</option>
            <option value="customer" @selected($projectType === 'customer')>Customer Project</option>
        </select>
    </div>
    <div class="pcat-field pm-af-customer-wrap" style="{{ $projectType === 'customer' ? '' : 'display:none;' }}">
        <label>Customer</label>
        <select name="customer_id">
            <option value="">Select customer…</option>
            @foreach($customers as $c)
                <option value="{{ $c->id }}" @selected((string) old('customer_id', $project?->customer_id) === (string) $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
    </div>
</div>

<div class="pm-af-assign-wrap" style="{{ $projectType === 'customer' ? 'display:none;' : '' }}">
    <div class="pcat-form-grid pcat-form-grid--2" style="margin-bottom:12px;">
        <div class="pcat-field">
            <label>Assign To</label>
            <select name="assignment_type" class="pm-af-assign-type">
                <option value="none"         @selected($assignType === 'none')>None</option>
                <option value="branch"       @selected($assignType === 'branch')>Branch</option>
                <option value="department"   @selected($assignType === 'department')>Department</option>
                <option value="property"     @selected($assignType === 'property')>Property</option>
                <option value="employee"     @selected($assignType === 'employee')>Employee</option>
                <option value="modification" @selected($assignType === 'modification')>Modification</option>
                <option value="rental"       @selected($assignType === 'rental')>Rental</option>
                <option value="other"        @selected($assignType === 'other')>Other</option>
            </select>
        </div>

        <div class="pcat-field" data-assign-target="branch" style="{{ $assignType === 'branch' ? '' : 'display:none;' }}">
            <label>Branch</label>
            <select name="branch_id">
                <option value="">Select branch…</option>
                @foreach($assignableTargets['branches'] as $t)
                    <option value="{{ $t->id }}" @selected((string) old('branch_id', $project?->branch_id) === (string) $t->id)>{{ $t->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="pcat-field" data-assign-target="department" style="{{ $assignType === 'department' ? '' : 'display:none;' }}">
            <label>Department</label>
            <select name="department_id">
                <option value="">Select department…</option>
                @foreach($assignableTargets['departments'] as $t)
                    <option value="{{ $t->id }}" @selected((string) old('department_id', $project?->department_id) === (string) $t->id)>{{ $t->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="pcat-field" data-assign-target="property" style="{{ $assignType === 'property' ? '' : 'display:none;' }}">
            <label>Property</label>
            <select name="property_id">
                <option value="">Select property…</option>
                @foreach($assignableTargets['properties'] as $t)
                    <option value="{{ $t->id }}" @selected((string) old('property_id', $project?->property_id) === (string) $t->id)>{{ $t->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="pcat-field" data-assign-target="employee" style="{{ $assignType === 'employee' ? '' : 'display:none;' }}">
            <label>Employee</label>
            <select name="employee_id">
                <option value="">Select employee…</option>
                @foreach($assignableTargets['employees'] as $t)
                    <option value="{{ $t->id }}" @selected((string) old('employee_id', $project?->employee_id) === (string) $t->id)>{{ $t->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="pcat-field" data-assign-target="modification" style="{{ $assignType === 'modification' ? '' : 'display:none;' }}">
            <label>Modification</label>
            <select name="modification_id">
                <option value="">Select modification…</option>
                @foreach($assignableTargets['modifications'] as $t)
                    <option value="{{ $t->id }}" @selected((string) old('modification_id', $project?->modification_id) === (string) $t->id)>{{ $t->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="pcat-field" data-assign-target="rental" style="{{ $assignType === 'rental' ? '' : 'display:none;' }}">
            <label>Rental</label>
            <select name="rental_id">
                <option value="">Select rental…</option>
                @foreach($assignableTargets['rentals'] as $t)
                    <option value="{{ $t->id }}" @selected((string) old('rental_id', $project?->rental_id) === (string) $t->id)>{{ $t->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="pcat-field" data-assign-target="other" style="{{ $assignType === 'other' ? '' : 'display:none;' }}">
            <label>Reference</label>
            <input type="text" name="assignment_reference" maxlength="255" placeholder="e.g. Building A, Floor 2"
                   value="{{ old('assignment_reference', $project?->assignment_reference) }}">
        </div>
    </div>
</div>

<div class="pcat-form-grid pcat-form-grid--2" style="margin-bottom:12px;">
    <div class="pcat-field">
        <label>Priority</label>
        <select name="priority">
            <option value="normal" @selected(old('priority', $project?->priority ?? 'normal') === 'normal')>Normal</option>
            <option value="high"   @selected(old('priority', $project?->priority) === 'high')>High</option>
            <option value="low"    @selected(old('priority', $project?->priority) === 'low')>Low</option>
        </select>
    </div>
    <div class="pcat-field">
        <label>Color</label>
        <input type="color" name="color" value="{{ old('color', $project?->color ?: '#4e8ef7') }}" style="height:38px;padding:2px 6px;">
    </div>
</div>

<script>
(function () {
    document.querySelectorAll('.pm-af-type').forEach(function (typeSel) {
        var root = typeSel.closest('form');
        if (!root || root.dataset.pmAfBound) return;
        root.dataset.pmAfBound = '1';

        function syncType() {
            var isCustomer = typeSel.value === 'customer';
            root.querySelectorAll('.pm-af-customer-wrap').forEach(function (el) { el.style.display = isCustomer ? '' : 'none'; });
            root.querySelectorAll('.pm-af-assign-wrap').forEach(function (el) { el.style.display = isCustomer ? 'none' : ''; });
        }
        typeSel.addEventListener('change', syncType);

        var assignSel = root.querySelector('.pm-af-assign-type');
        if (assignSel) {
            assignSel.addEventListener('change', function () {
                root.querySelectorAll('[data-assign-target]').forEach(function (el) {
                    el.style.display = el.dataset.assignTarget === assignSel.value ? '' : 'none';
                });
            });
        }
    });
})();
</script>
