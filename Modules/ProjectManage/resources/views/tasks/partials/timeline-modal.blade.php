{{--
    Full project timeline modal: summary + status filter + milestone jump list + timeline.
    Opened by "View Timeline"; stays open across form posts via ?tl=1.
    Expects the Tasks page variables (see tasks/index).
--}}
<div class="pm-tlm-overlay" id="pm-tlm" hidden>
    <div class="pm-tlm" role="dialog" aria-modal="true" aria-labelledby="pm-tlm-title">
        <div class="pm-tlm-hdr">
            <span class="pm-tlm-icon"><i class="fa fa-timeline"></i></span>
            <div class="pm-tlm-titlewrap">
                <div class="pm-tlm-title" id="pm-tlm-title">Project Timeline — {{ $project->name }}</div>
                <div class="pm-tlm-sub">
                    <i class="fa fa-calendar"></i>
                    @if($starts->isNotEmpty())
                        {{ $starts->first()->format('d M Y') }} → {{ $ends->last()->format('d M Y') }}
                    @else
                        No milestone dates yet
                    @endif
                    · Today {{ $today->format('d M Y') }}
                </div>
            </div>
            <div class="pm-tlm-actions">
                <button type="button" class="pm-tlm-btn" data-tlm="today" title="Scroll to today"><i class="fa fa-location-crosshairs"></i> Today</button>
                <button type="button" class="pm-tlm-btn" data-tlm="compact" title="Show / hide tasks under each milestone"><i class="fa fa-compress"></i> <span>Hide Tasks</span></button>
                <button type="button" class="pm-tlm-btn" data-ms-new title="New milestone"><i class="fa fa-flag"></i> Milestone</button>
                <button type="button" class="pm-tlm-btn pm-tlm-btn--primary" data-task-new title="New task"><i class="fa fa-plus"></i> Task</button>
                <button type="button" class="pm-tlm-icon-btn" data-tlm="max" title="Maximize"><i class="fa fa-up-right-and-down-left-from-center"></i></button>
                <button type="button" class="pm-tlm-icon-btn" data-tlm="close" title="Close (Esc)"><i class="fa fa-xmark"></i></button>
            </div>
        </div>

        <div class="pm-tlm-bar">
            <div class="pm-tlm-stats">
                <span class="pm-tlm-stat"><i class="fa fa-flag"></i> <b>{{ $msDone }}/{{ $milestones->count() }}</b> milestones done</span>
                <span class="pm-tlm-stat"><i class="fa fa-list-check"></i> <b>{{ $tDone }}/{{ $tCounted }}</b> tasks done</span>
                <span class="pm-tlm-stat {{ $tOverdue ? 'pm-tlm-stat--warn' : '' }}"><i class="fa fa-triangle-exclamation"></i> <b>{{ $tOverdue }}</b> overdue</span>
                <span class="pm-ms-prog pm-tlm-overall" title="{{ $pctAll }}% of tasks done"><span class="pm-bar"><span style="width:{{ $pctAll }}%"></span></span> {{ $pctAll }}%</span>
            </div>
            <div class="pm-chips">
                @foreach($statusTabs as $key => $label)
                    <button type="button" class="pm-chip {{ $statusFilter === $key ? 'active' : '' }}" data-status-filter="{{ $key }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>
        @if(session('status'))
            <div class="pm-tlm-flash"><i class="fa fa-circle-check"></i> {{ session('status') }}</div>
        @endif

        <div class="pm-tlm-main">
            <nav class="pm-tlm-nav">
                <div class="pm-tlm-nav-title">Milestones</div>
                @php $navCount = 0; @endphp
                @foreach($groups as $g)
                    @php
                        $ms = $g['milestone'];
                        if (!$ms && $g['tasks']->isEmpty()) continue;
                        $navCount++;
                        $state = $ms ? $ms->timelineState() : 'none';
                    @endphp
                    <button type="button" class="pm-tlm-nav-item pm-state--{{ $state }}" data-nav-group="{{ $ms ? $ms->id : '' }}">
                        <span class="pm-tlm-nav-dot">@if($ms){{ $loop->iteration }}@else<i class="fa fa-inbox"></i>@endif</span>
                        <span class="pm-tlm-nav-text">
                            <span class="pm-tlm-nav-name">{{ $ms ? $ms->name : 'No Milestone' }}</span>
                            <span class="pm-tlm-nav-meta">{{ $ms ? $stateLabel[$state] : 'Unscheduled' }} · <span data-nav-count></span></span>
                        </span>
                    </button>
                @endforeach
                @if(!$navCount)
                    <div class="pm-tlm-nav-empty">No milestones yet</div>
                @endif
            </nav>
            <div class="pm-tlm-scroll" id="pm-tlm-scroll">
                @if($isEmpty)
                    <div class="pm-empty">
                        <i class="fa fa-flag" style="font-size:22px;opacity:.4;display:block;margin-bottom:8px;"></i>
                        No milestones or tasks yet.<br>Add a milestone to split this project into phases.
                    </div>
                @else
                    @include('projectmanage::tasks.partials.timeline', ['tlId' => 'pm-tlm-timeline'])
                @endif
            </div>
        </div>
    </div>
</div>
