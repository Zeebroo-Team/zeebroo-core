@if($errors->any())
    <div class="pcat-banner pcat-banner--err" role="alert">{{ $errors->first() }}</div>
@endif

<form method="POST" action="{{ route('crm.projects.forms.store', $project) }}" class="pcat-form-grid">
    @csrf
    <div class="pcat-field">
        <label for="lf-name">Form name</label>
        <input id="lf-name" name="name" maxlength="150" required placeholder="e.g. Contact us" value="{{ old('name') }}">
        @error('name')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>
    @include('crm::leads.forms.partials.template-picker', ['templates' => $templates])
    @if(($stages ?? collect())->isNotEmpty())
        <div class="pcat-field">
            <label for="lf-default-stage">Default stage for leads from this form</label>
            <select id="lf-default-stage" name="default_stage_id">
                <option value="">Use pipeline default</option>
                @foreach($stages as $stage)
                    <option value="{{ $stage->id }}" @selected((string) old('default_stage_id') === (string) $stage->id)>{{ $stage->name }}</option>
                @endforeach
            </select>
            @error('default_stage_id')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
        </div>
    @endif
    <div style="display:flex;justify-content:flex-end;">
        <button type="submit" class="linkbtn" style="padding:8px 16px;font-size:13px;">Create &amp; open builder</button>
    </div>
</form>
