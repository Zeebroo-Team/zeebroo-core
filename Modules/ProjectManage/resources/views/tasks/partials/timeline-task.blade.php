{{-- Timeline view: one task line. Expects: $t, $statusTabs, $statusColors, $priorityColor --}}
@php
    $isDone  = $t->isCompleted();
    $overdue = $t->isOverdue();
    $sc      = $statusColors[$t->status] ?? '#6b7280';
    $pc      = $priorityColor[$t->priority] ?? '#6b7280';
@endphp
<li class="pm-tl-task{{ $isDone ? ' is-done' : '' }}{{ $overdue ? ' is-overdue' : '' }}" draggable="true"
    data-tid="{{ $t->id }}" data-status="{{ $t->status }}" data-done="{{ $isDone ? 1 : 0 }}" data-cancelled="{{ $t->isCancelled() ? 1 : 0 }}"
    data-milestone-url="{{ route('pm.tasks.milestone', $t) }}">
    <form method="POST" action="{{ route($isDone ? 'pm.tasks.reopen' : 'pm.tasks.complete', $t) }}" class="pm-done-form">
        @csrf
        <button type="submit" class="pm-done-toggle{{ $isDone ? ' is-done' : '' }}" title="{{ $isDone ? 'Reopen' : 'Mark done' }}">
            <i class="fa {{ $isDone ? 'fa-circle-check' : 'fa-circle' }}"></i>
        </button>
    </form>
    <a href="{{ route('pm.tasks.show', $t) }}" class="pm-task-title pm-tl-task-title">{{ $t->title }}</a>
    <span class="pm-prio" style="color:{{ $pc }};"><span style="background:{{ $pc }};"></span>{{ ucfirst($t->priority) }}</span>
    @include('projectmanage::tasks.partials.assignee-btn')
    <span class="pm-tl-meta" style="{{ $overdue ? 'color:#dc2626;font-weight:700;' : '' }}">
        <i class="fa {{ $overdue ? 'fa-triangle-exclamation' : 'fa-calendar' }}"></i>
        {{ $t->due_date ? $t->due_date->format('d M Y') : 'No due date' }}
    </span>
    <span class="pcat-badge" style="border-color:{{ $sc }};color:{{ $sc }};" title="Stage">{{ $statusTabs[$t->status] ?? 'Not Defined' }}</span>
    @include('projectmanage::tasks.partials.completion-badge', ['task' => $t])
</li>
