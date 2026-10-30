{{--
    Who works a lead, as chips (primary first, starred). $assignees: the lead's `assignees`
    relation (with user) or LeadAssignmentService::present() rows. $compact hides names past two.
--}}
@php
    $chips = collect($assignees)->map(fn ($a) => is_array($a)
        ? $a
        : ['id' => $a->user_id, 'name' => $a->user?->name ?? 'Deleted user', 'is_primary' => (bool) $a->is_primary]);
    $compact = $compact ?? false;
    $shown = $compact ? $chips->take(2) : $chips;
@endphp
@once
<style>
.ld-assignees{display:inline-flex;flex-wrap:wrap;gap:4px;align-items:center;}
.ld-assignee{display:inline-flex;align-items:center;gap:4px;max-width:160px;font-size:11px;font-weight:600;line-height:1;padding:3px 8px 3px 3px;border-radius:999px;background:color-mix(in srgb,var(--primary) 9%,transparent);border:1px solid color-mix(in srgb,var(--primary) 22%,transparent);color:var(--text);}
.ld-assignee__avatar{width:16px;height:16px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:9px;font-weight:800;color:#fff;background:var(--primary);flex-shrink:0;}
.ld-assignee__name{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.ld-assignee--primary{border-color:color-mix(in srgb,#f59e0b 55%,transparent);}
.ld-assignee--more{padding:3px 7px;}
</style>
@endonce
@if($chips->isEmpty())
    <span class="muted" style="font-size:11px;">{{ $emptyText ?? 'Unassigned' }}</span>
@else
    <span class="ld-assignees" title="{{ $chips->pluck('name')->implode(', ') }}">
        @foreach($shown as $chip)
            <span class="ld-assignee{{ $chip['is_primary'] ? ' ld-assignee--primary' : '' }}">
                <span class="ld-assignee__avatar">{{ mb_strtoupper(mb_substr($chip['name'], 0, 1)) }}</span>
                <span class="ld-assignee__name">{{ $chip['name'] }}</span>
                @if($chip['is_primary'] && $chips->count() > 1)<i class="fa fa-star" style="color:#f59e0b;font-size:9px;" title="Primary"></i>@endif
            </span>
        @endforeach
        @if($chips->count() > $shown->count())
            <span class="ld-assignee ld-assignee--more">+{{ $chips->count() - $shown->count() }}</span>
        @endif
    </span>
@endif
