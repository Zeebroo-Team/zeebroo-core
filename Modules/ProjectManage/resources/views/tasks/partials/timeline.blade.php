{{--
    Vertical milestone timeline (Timeline tab + full timeline modal).
    Expects: $project, $groups, $milestones, $today, $stateLabel, $statusTabs, $statusColors, $priorityColor, $tlId
--}}
@php
    $anchor      = fn ($m) => $m->start_date ?? $m->due_date;   // where a milestone begins on the timeline
    $todayPlaced = $milestones->filter(fn ($m) => $anchor($m))->isEmpty();
@endphp
<div class="pm-tl" id="{{ $tlId }}" data-view-root>
    @foreach($groups as $g)
        @php
            $ms    = $g['milestone'];
            $state = $ms ? $ms->timelineState() : 'none';
            $key   = $ms ? (string) $ms->id : '';
            // Tasks run top-to-bottom by due date (undated last)
            $tlTasks = $g['tasks']->sortBy(fn ($t) => [$t->due_date?->toDateString() ?? '9999-12-31', $t->id])->values();
        @endphp
        @if(!$todayPlaced && (!$ms || ($anchor($ms) && $anchor($ms)->gt($today))))
            @php $todayPlaced = true; @endphp
            <div class="pm-tl-today">
                <div class="pm-tl-date">{{ $today->format('d M Y') }}</div>
                <div class="pm-tl-rail"><span class="pm-tl-today-dot"></span></div>
                <div class="pm-tl-today-label">Today</div>
            </div>
        @endif
        <div class="pm-tl-item pm-state--{{ $state }}" data-group="{{ $key }}">
            <div class="pm-tl-date">
                @if($ms)
                    {{ $anchor($ms)?->format('d M Y') ?? '—' }}
                    @if($ms->start_date && $ms->due_date)
                        <small>to {{ $ms->due_date->format('d M Y') }}</small>
                    @elseif(!$ms->start_date && $ms->due_date)
                        <small>end date</small>
                    @endif
                @else
                    Unscheduled
                @endif
            </div>
            <div class="pm-tl-rail">
                <span class="pm-tl-node"><i class="fa {{ $state === 'completed' ? 'fa-check' : ($ms ? 'fa-flag' : 'fa-inbox') }}"></i></span>
            </div>
            <div class="pm-tl-card">
                <div class="pm-ms-head">
                    @if($ms)
                        <span class="pm-ms-num">{{ $loop->iteration }}</span>
                        <div class="pm-ms-titlewrap">
                            <div class="pm-ms-name">{{ $ms->name }}</div>
                            <div class="pm-ms-period"><i class="fa fa-calendar"></i> {{ $ms->periodLabel() }}</div>
                        </div>
                        <span class="pm-ms-state">{{ $stateLabel[$state] }}</span>
                    @else
                        <span class="pm-ms-num"><i class="fa fa-inbox"></i></span>
                        <div class="pm-ms-titlewrap">
                            <div class="pm-ms-name">No Milestone</div>
                            <div class="pm-ms-period">Tasks not assigned to a milestone</div>
                        </div>
                    @endif
                    @include('projectmanage::tasks.partials.ms-actions', ['ms' => $ms, 'reorder' => false])
                </div>
                @if($ms)
                    <div class="pm-ms-prog" data-prog="bar">
                        <div class="pm-bar"><span style="width:0"></span></div>
                        <span data-prog-text></span>
                    </div>
                @endif
                <ul class="pm-tl-tasks" data-task-list>
                    @foreach($tlTasks as $t)
                        @include('projectmanage::tasks.partials.timeline-task')
                    @endforeach
                    <li class="pm-tl-empty" data-empty></li>
                </ul>
            </div>
        </div>
    @endforeach
</div>
