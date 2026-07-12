@php
    $stageLogs = $stageLogs ?? collect();
    $showLeadName = $showLeadName ?? false;
@endphp
@once('crm-stage-log-timeline-style')
<style>
.pj-timeline{display:flex;flex-direction:column;gap:2px;max-height:360px;overflow-y:auto;}
.pj-timeline__item{display:flex;gap:10px;padding:8px 0;border-bottom:1px solid color-mix(in srgb,var(--border) 70%,transparent);}
.pj-timeline__item:last-child{border-bottom:none;}
.pj-timeline__dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;margin-top:5px;}
.pj-timeline__body{min-width:0;flex:1;}
.pj-timeline__text{font-size:12.5px;color:var(--text);line-height:1.4;}
.pj-timeline__meta{font-size:11px;color:var(--muted);margin-top:2px;}
</style>
@endonce

@if($stageLogs->isEmpty())
    <p class="muted" style="font-size:13px;">No stage changes logged yet.</p>
@else
    <div class="pj-timeline">
        @foreach($stageLogs as $log)
            <div class="pj-timeline__item">
                <span class="pj-timeline__dot" style="background:{{ $log->toStage?->color ?? '#64748b' }};"></span>
                <div class="pj-timeline__body">
                    <div class="pj-timeline__text">
                        @if($showLeadName)
                            <strong>{{ $log->lead?->name ?? 'Deleted lead' }}</strong>
                        @endif
                        @if($log->fromStage)
                            moved from <strong>{{ $log->fromStage->name }}</strong> to <strong>{{ $log->toStage?->name ?? '—' }}</strong>
                        @else
                            created in <strong>{{ $log->toStage?->name ?? '—' }}</strong>
                        @endif
                    </div>
                    <div class="pj-timeline__meta">
                        {{ $log->created_at->diffForHumans() }} · {{ $log->changedBy?->name ?? 'System' }}
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
