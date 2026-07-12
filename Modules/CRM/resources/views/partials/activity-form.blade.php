@php use Modules\CRM\Models\Activity; @endphp

<form method="POST" action="{{ route('crm.activities.store') }}" class="pcat-form-grid" style="gap:8px;">
    @csrf
    <input type="hidden" name="subject_type" value="{{ $subjectType }}">
    <input type="hidden" name="subject_id" value="{{ $subjectId }}">

    <div class="pcat-field">
        <label for="act-type">Type</label>
        <select id="act-type" name="type">
            @foreach(Activity::types() as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="pcat-field">
        <label for="act-body">What happened?</label>
        <textarea id="act-body" name="body" maxlength="5000" placeholder="Call notes, email summary…" style="min-height:44px;"></textarea>
    </div>

    <div style="display:flex;justify-content:flex-end;">
        <button type="submit" class="linkbtn" style="padding:7px 14px;font-size:12px;">
            <i class="fa fa-plus"></i> Log activity
        </button>
    </div>
</form>
