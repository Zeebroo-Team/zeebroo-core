@php $assignableUsers = $assignableUsers ?? collect(); @endphp

<form method="POST" action="{{ route('crm.tasks.store') }}" class="pcat-form-grid pcat-form-grid--2">
    @csrf
    @if($subjectType ?? null)
        <input type="hidden" name="subject_type" value="{{ $subjectType }}">
        <input type="hidden" name="subject_id" value="{{ $subjectId }}">
    @endif

    <div class="pcat-field" style="grid-column:1/-1;">
        <label for="tsk-title">Task</label>
        <input id="tsk-title" type="text" name="title" maxlength="200" required placeholder="Follow up call, send proposal…">
    </div>

    <div class="pcat-field">
        <label for="tsk-due">Due</label>
        <input id="tsk-due" type="datetime-local" name="due_at">
    </div>

    <div class="pcat-field">
        <label for="tsk-assignee">Assign to</label>
        <select id="tsk-assignee" name="assigned_to">
            <option value="">— Unassigned —</option>
            @foreach($assignableUsers as $u)
                <option value="{{ $u->id }}">{{ $u->name }}</option>
            @endforeach
        </select>
    </div>

    <div style="grid-column:1/-1;display:flex;justify-content:flex-end;">
        <button type="submit" class="linkbtn" style="padding:7px 14px;font-size:12px;">
            <i class="fa fa-plus"></i> Add task
        </button>
    </div>
</form>
