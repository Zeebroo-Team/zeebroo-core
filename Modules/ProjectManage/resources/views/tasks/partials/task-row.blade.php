{{-- By Milestone view: one task row. Expects: $t, $milestones, $statusTabs, $statusColors, $priorityColor --}}
@php
    $isDone  = $t->isCompleted();
    $overdue = $t->isOverdue();
    $sc      = $statusColors[$t->status] ?? '#6b7280';
    $pc      = $priorityColor[$t->priority] ?? '#6b7280';
@endphp
<tr class="pm-ms-task" draggable="true"
    data-tid="{{ $t->id }}" data-status="{{ $t->status }}" data-done="{{ $isDone ? 1 : 0 }}"
    data-milestone-url="{{ route('pm.tasks.milestone', $t) }}">
    <td class="pm-drag-handle" title="Drag to another milestone"><i class="fa fa-grip-vertical"></i></td>
    <td>
        <form method="POST" action="{{ route($isDone ? 'pm.tasks.reopen' : 'pm.tasks.complete', $t) }}" class="pm-done-form">
            @csrf
            <button type="submit" class="pm-done-toggle{{ $isDone ? ' is-done' : '' }}" title="{{ $isDone ? 'Reopen' : 'Mark done' }}">
                <i class="fa {{ $isDone ? 'fa-circle-check' : 'fa-circle' }}"></i>
            </button>
        </form>
    </td>
    <td><a href="{{ route('pm.tasks.show', $t) }}" class="pm-task-title">{{ $t->title }}</a></td>
    <td>
        <span class="pm-prio" style="color:{{ $pc }};"><span style="background:{{ $pc }};"></span>{{ ucfirst($t->priority) }}</span>
    </td>
    <td>@include('projectmanage::tasks.partials.assignee-btn')</td>
    <td class="pm-muted-cell" style="{{ $overdue ? 'color:#dc2626;font-weight:700;' : '' }}">
        @if($overdue)<i class="fa fa-triangle-exclamation" style="margin-right:3px;"></i>@endif
        {{ $t->due_date ? $t->due_date->format('d M Y') : '—' }}
    </td>
    <td>
        <span class="pcat-badge" style="border-color:{{ $sc }};color:{{ $sc }};">{{ $statusTabs[$t->status] ?? ucfirst(str_replace('_', ' ', $t->status)) }}</span>
    </td>
    <td>
        <select class="pm-ms-select" data-ms-select title="Move to milestone">
            <option value="">No milestone</option>
            @foreach($milestones as $opt)
                <option value="{{ $opt->id }}" @selected((int) $t->milestone_id === (int) $opt->id)>{{ $opt->name }}</option>
            @endforeach
        </select>
    </td>
    <td style="text-align:right;white-space:nowrap;">
        <a href="{{ route('pm.tasks.show', $t) }}" class="pcat-link" title="View"><i class="fa fa-eye"></i></a>
        <form method="POST" action="{{ route('pm.tasks.destroy', $t) }}" style="display:inline;"
              onsubmit="return confirm(@js('Delete task "' . $t->title . '"?'));">
            @csrf
            @method('DELETE')
            <input type="hidden" name="_back" value="1">
            <button type="submit" class="pcat-btn-del" style="padding:3px 7px;font-size:11px;margin-left:4px;" title="Delete"><i class="fa fa-trash"></i></button>
        </form>
    </td>
</tr>
