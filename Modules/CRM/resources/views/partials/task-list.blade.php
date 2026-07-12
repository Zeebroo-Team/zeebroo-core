@php
    $tasks = $tasks ?? collect();
    $showSubject = $showSubject ?? false;
@endphp

<div style="display:flex;flex-direction:column;gap:8px;">
    @forelse($tasks as $t)
        <div class="pcat-card" style="grid-template-columns:1fr auto;padding:10px 12px;">
            <div style="min-width:0;">
                <div style="font-size:13px;font-weight:700;color:var(--text);{{ $t->isCompleted() ? 'text-decoration:line-through;opacity:.65;' : '' }}">
                    {{ $t->title }}
                </div>
                @if($t->description)
                    <p class="pcat-muted" style="margin:4px 0 0;">{{ $t->description }}</p>
                @endif
                <div class="muted" style="font-size:11px;margin-top:4px;display:flex;gap:8px;flex-wrap:wrap;">
                    @if($t->due_at)
                        <span style="{{ $t->isOverdue() ? 'color:#ef4444;font-weight:700;' : '' }}">
                            <i class="fa fa-clock"></i> {{ $t->due_at->format('M j, Y g:ia') }}
                        </span>
                    @endif
                    @if($t->assignedTo)
                        <span><i class="fa fa-user"></i> {{ $t->assignedTo->name }}</span>
                    @endif
                    @if($showSubject && $t->subject)
                        <span>
                            <i class="fa fa-link"></i>
                            @if($t->subject_type === \Modules\Pos\Models\Customer::class)
                                <a href="{{ route('crm.contacts.show', $t->subject_id) }}" class="pcat-link">{{ $t->subject->name }}</a>
                            @else
                                <a href="{{ route('crm.leads.show', $t->subject_id) }}" class="pcat-link">{{ $t->subject->name }}</a>
                            @endif
                        </span>
                    @endif
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:14px;">
                @if($t->isCompleted())
                    <form method="POST" action="{{ route('crm.tasks.reopen', $t) }}">
                        @csrf
                        <button type="submit" class="pcat-link" style="border:none;background:transparent;cursor:pointer;padding:0;font:inherit;">
                            <i class="fa fa-rotate-left"></i> Reopen
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('crm.tasks.complete', $t) }}">
                        @csrf
                        <button type="submit" class="pcat-link" style="border:none;background:transparent;cursor:pointer;padding:0;font:inherit;">
                            <i class="fa fa-check"></i> Complete
                        </button>
                    </form>
                @endif
                <form method="POST" action="{{ route('crm.tasks.destroy', $t) }}" onsubmit="return confirm('Remove this task?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="pcat-btn-del" title="Remove">
                        <i class="fa fa-trash-can"></i>
                    </button>
                </form>
            </div>
        </div>
    @empty
        <p class="muted" style="font-size:13px;">No tasks yet.</p>
    @endforelse
</div>
