@php $activities = $activities ?? collect(); @endphp

<div style="display:flex;flex-direction:column;gap:8px;">
    @forelse($activities as $a)
        <div class="pcat-card" style="grid-template-columns:auto 1fr auto;padding:10px 12px;">
            <div style="width:28px;height:28px;border-radius:50%;display:grid;place-items:center;background:color-mix(in srgb,var(--primary) 12%,transparent);color:var(--primary);flex-shrink:0;">
                <i class="fa {{ $a->typeIcon() }}" style="font-size:12px;"></i>
            </div>
            <div style="min-width:0;">
                <div style="font-size:12px;font-weight:700;color:var(--text);">
                    {{ $a->typeLabel() }} <span class="muted" style="font-weight:400;">· {{ $a->createdBy?->name ?? 'System' }}</span>
                </div>
                @if($a->body)
                    <p class="pcat-muted" style="margin:4px 0 0;white-space:pre-line;">{{ $a->body }}</p>
                @endif
                <div class="muted" style="font-size:11px;margin-top:4px;">{{ $a->occurred_at->format('M j, Y g:ia') }}</div>
            </div>
            <form method="POST" action="{{ route('crm.activities.destroy', $a) }}" onsubmit="return confirm('Remove this activity?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="pcat-btn-del" title="Remove">
                    <i class="fa fa-trash-can"></i>
                </button>
            </form>
        </div>
    @empty
        <p class="muted" style="font-size:13px;">No activity logged yet.</p>
    @endforelse
</div>
