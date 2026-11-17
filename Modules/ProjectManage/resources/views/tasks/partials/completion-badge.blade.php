{{-- Completion status of a task (incomplete / complete / cancelled) — separate from its stage. Expects $task. --}}
@php
    $csKey   = $task->completionStatus();
    $csColor = ['incomplete' => '#6b7280', 'complete' => '#16a34a', 'cancelled' => '#dc2626'][$csKey] ?? '#6b7280';
    $csIcon  = ['incomplete' => 'fa-circle-half-stroke', 'complete' => 'fa-circle-check', 'cancelled' => 'fa-circle-xmark'][$csKey] ?? 'fa-circle';
@endphp
<span class="pcat-badge" style="border-color:{{ $csColor }};color:{{ $csColor }};white-space:nowrap;" title="Status">
    <i class="fa {{ $csIcon }}" style="font-size:10px;"></i> {{ $task->completionLabel() }}
</span>
