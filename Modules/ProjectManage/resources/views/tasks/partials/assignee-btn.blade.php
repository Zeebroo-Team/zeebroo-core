{{-- Assignee avatar chips (first 3 + "+N") that open the assign dialog — same look as the desktop app. Expects: $t. Re-rendered client-side by renderChips() in tasks/index. --}}
<button type="button" class="pm-assignees-btn" title="Assign project members"
        data-assign-url="{{ route('pm.tasks.assignees', $t) }}"
        data-assign-title="{{ $t->title }}"
        data-assignees='@json($t->assignees->map(fn ($u) => ['id' => (int) $u->id, 'name' => $u->name])->values())'>
    @forelse($t->assignees->take(3) as $u)
        <span class="pm-assignee-chip" title="{{ $u->name }}">{{ strtoupper(mb_substr($u->name, 0, 1)) }}</span>
    @empty
        <span class="pm-assignees-empty"><i class="fa fa-user-plus"></i> Assign</span>
    @endforelse
    @if($t->assignees->count() > 3)
        <span class="pm-assignee-chip pm-assignee-chip--more">+{{ $t->assignees->count() - 3 }}</span>
    @endif
    @if($t->assignees->count() === 1)
        <span class="pm-assignees-name">{{ $t->assignees->first()->name }}</span>
    @endif
</button>
