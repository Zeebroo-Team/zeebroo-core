{{-- Milestone header actions. Expects: $project, $ms (Milestone|null), $reorder (bool) --}}
<div class="pm-ms-actions">
    @if($ms)
        @if($reorder)
            <button type="button" data-ms-move="up" title="Move up"><i class="fa fa-arrow-up"></i></button>
            <button type="button" data-ms-move="down" title="Move down"><i class="fa fa-arrow-down"></i></button>
        @endif
        <button type="button" data-ms-add="{{ $ms->id }}" title="Add task to this milestone"><i class="fa fa-plus"></i></button>
        @if($ms->isCompleted())
            <form method="POST" action="{{ route('pm.projects.milestones.reopen', [$project, $ms]) }}">
                @csrf
                <button type="submit" title="Reopen milestone"><i class="fa fa-rotate-left"></i></button>
            </form>
        @else
            <form method="POST" action="{{ route('pm.projects.milestones.complete', [$project, $ms]) }}">
                @csrf
                <button type="submit" title="Mark milestone complete"><i class="fa fa-check"></i></button>
            </form>
        @endif
        <button type="button" title="Edit milestone"
                data-ms-edit="{{ json_encode([
                    'id'          => $ms->id,
                    'name'        => $ms->name,
                    'description' => $ms->description,
                    'start_date'  => $ms->start_date?->toDateString(),
                    'due_date'    => $ms->due_date?->toDateString(),
                    'sort_order'  => (int) $ms->sort_order,
                ]) }}"><i class="fa fa-pen"></i></button>
        @php $msTaskCount = (int) ($ms->tasks_count ?? 0); @endphp
        <form method="POST" action="{{ route('pm.projects.milestones.destroy', [$project, $ms]) }}"
              onsubmit="return confirm(@js('Delete milestone "' . $ms->name . '"?' . ($msTaskCount ? "\nIts {$msTaskCount} task(s) will be kept under \"No Milestone\"." : '')));">
            @csrf
            @method('DELETE')
            <button type="submit" class="danger" title="Delete milestone"><i class="fa fa-trash"></i></button>
        </form>
    @else
        <button type="button" data-ms-add="" title="Add task"><i class="fa fa-plus"></i></button>
    @endif
</div>
