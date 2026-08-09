@extends('theme::layouts.app', ['title' => $campaign->name, 'heading' => 'Advertising Agency'])

@section('content')
@include('product::partials.catalog-hub-styles')

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('advertising-agency::partials.hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok">{{ session('status') }}</div>
    @endif

    {{-- Header --}}
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:18px;flex-wrap:wrap;">
        <div>
            <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px;">
                <a href="{{ route('advertising-agency.clients.show', $campaign->client) }}" class="pcat-link" style="font-size:12px;">
                    <i class="fa fa-building"></i> {{ $campaign->client->name }}
                </a>
            </div>
            <h2 style="margin:0 0 6px;font-size:18px;">{{ $campaign->name }}</h2>
            @php
                $colors = ['draft'=>'#94a3b8','active'=>'#22c55e','paused'=>'#f59e0b','completed'=>'#3b82f6','cancelled'=>'#ef4444'];
            @endphp
            <span class="pcat-badge" style="background:{{ $colors[$campaign->status] ?? '#94a3b8' }}20;color:{{ $colors[$campaign->status] ?? '#94a3b8' }};">
                {{ ucfirst($campaign->status) }}
            </span>
            @if($campaign->channel)
                <span class="pcat-badge" style="background:var(--surface-raised);color:var(--text-muted);margin-left:4px;">
                    {{ ucwords(str_replace('_',' ',$campaign->channel)) }}
                </span>
            @endif
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="{{ route('advertising-agency.campaigns.edit', $campaign) }}" class="pcat-link">
                <i class="fa fa-pencil"></i> Edit
            </a>
            <form method="POST" action="{{ route('advertising-agency.campaigns.destroy', $campaign) }}"
                  onsubmit="return confirm('Delete this campaign and all its creatives and tasks?')">
                @csrf @method('DELETE')
                <button type="submit" class="pcat-link pcat-link--danger" style="background:none;border:none;cursor:pointer;padding:0;">
                    <i class="fa fa-trash"></i> Delete
                </button>
            </form>
        </div>
    </div>

    {{-- Stats --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px;margin-bottom:22px;">
        <div class="pcat-info-cell">
            <span class="muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;">Budget</span>
            <span style="font-size:16px;font-weight:700;">{{ number_format($campaign->budget,2) }}</span>
        </div>
        <div class="pcat-info-cell">
            <span class="muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;">Spent</span>
            <span style="font-size:16px;font-weight:700;color:{{ (float)$campaign->spent > (float)$campaign->budget ? '#ef4444' : 'var(--text)' }};">
                {{ number_format($campaign->spent,2) }}
            </span>
            @if((float)$campaign->budget > 0)
                <div style="margin-top:4px;height:5px;border-radius:3px;background:var(--border);overflow:hidden;">
                    <div style="height:100%;background:{{ (float)$campaign->spent > (float)$campaign->budget ? '#ef4444' : '#22c55e' }};width:{{ $campaign->spentPercent() }}%;transition:width .4s;"></div>
                </div>
                <span style="font-size:10px;color:var(--text-muted);">{{ $campaign->spentPercent() }}%</span>
            @endif
        </div>
        <div class="pcat-info-cell">
            <span class="muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;">Remaining</span>
            <span style="font-size:16px;font-weight:700;">{{ number_format($campaign->remainingBudget(),2) }}</span>
        </div>
        @if($campaign->start_date || $campaign->end_date)
        <div class="pcat-info-cell">
            <span class="muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;">Dates</span>
            <span style="font-size:13px;">
                {{ $campaign->start_date?->format('d M Y') ?? '—' }}<br>
                <span class="muted">to {{ $campaign->end_date?->format('d M Y') ?? '—' }}</span>
            </span>
        </div>
        @endif
        @if($campaign->goal)
        <div class="pcat-info-cell">
            <span class="muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;">Goal</span>
            <span style="font-size:13px;">{{ $campaign->goal }}</span>
        </div>
        @endif
        @if($campaign->target_audience)
        <div class="pcat-info-cell">
            <span class="muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;">Audience</span>
            <span style="font-size:13px;">{{ $campaign->target_audience }}</span>
        </div>
        @endif
    </div>

    @if($campaign->description)
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:20px;line-height:1.5;">{{ $campaign->description }}</p>
    @endif

    {{-- ===================== AD CREATIVES ===================== --}}
    <div style="margin-bottom:28px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
            <h3 style="margin:0;font-size:14px;font-weight:700;">
                <i class="fa fa-image" style="margin-right:5px;color:var(--accent);"></i>
                Ad Creatives ({{ $creatives->count() }})
            </h3>
            <button type="button" id="cr-add-btn" class="pcat-link" style="font-size:12px;">
                <i class="fa fa-plus"></i> Add creative
            </button>
        </div>

        {{-- Add creative form --}}
        <div id="cr-add-form" style="display:none;background:var(--surface-raised);border:1px solid var(--border);border-radius:6px;padding:14px;margin-bottom:12px;">
            <form method="POST" action="{{ route('advertising-agency.campaigns.creatives.store', $campaign) }}">
                @csrf
                <div class="pcat-form-grid">
                    <div class="pcat-form-field">
                        <label>Title <span class="pcat-req">*</span></label>
                        <input type="text" name="title" placeholder="e.g. Banner 728×90" maxlength="150" required>
                        @error('title') <span class="pcat-err">{{ $message }}</span> @enderror
                    </div>
                    <div class="pcat-form-field">
                        <label>Format</label>
                        <select name="format">
                            <option value="">— select format —</option>
                            @foreach(\Modules\AdvertisingAgency\Models\AdCreative::FORMATS as $fmt)
                                <option value="{{ $fmt }}">{{ ucfirst($fmt) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pcat-form-field">
                        <label>Headline</label>
                        <input type="text" name="headline" placeholder="Ad headline" maxlength="255">
                    </div>
                    <div class="pcat-form-field">
                        <label>Call to action</label>
                        <input type="text" name="call_to_action" placeholder="e.g. Shop now" maxlength="100">
                    </div>
                    <div class="pcat-form-field">
                        <label>Dimensions</label>
                        <input type="text" name="dimensions" placeholder="e.g. 728×90" maxlength="50">
                    </div>
                    <div class="pcat-form-field">
                        <label>Status</label>
                        <select name="status">
                            @foreach(\Modules\AdvertisingAgency\Models\AdCreative::STATUSES as $s)
                                <option value="{{ $s }}">{{ ucfirst($s) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pcat-form-field pcat-form-field--full">
                        <label>Body copy</label>
                        <textarea name="body_copy" rows="2" maxlength="2000" placeholder="Ad body text…"></textarea>
                    </div>
                    <div class="pcat-form-field pcat-form-field--full">
                        <label>File URL</label>
                        <input type="url" name="file_url" placeholder="https://…" maxlength="1000">
                    </div>
                    <div class="pcat-form-field pcat-form-field--full">
                        <label>Notes</label>
                        <textarea name="notes" rows="2" maxlength="2000" placeholder="Internal notes…"></textarea>
                    </div>
                </div>
                <div style="display:flex;gap:10px;margin-top:10px;justify-content:flex-end;">
                    <button type="button" id="cr-add-cancel" class="pcat-link" style="color:var(--text-muted);font-size:13px;">Cancel</button>
                    <button type="submit" class="linkbtn" style="padding:6px 16px;font-size:13px;">
                        <i class="fa fa-floppy-disk"></i> Save creative
                    </button>
                </div>
            </form>
        </div>

        <div class="pcat-table-wrap">
            <table class="pcat-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Format</th>
                        <th>Headline</th>
                        <th>Dimensions</th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $crColors = ['draft'=>'#94a3b8','review'=>'#f59e0b','approved'=>'#22c55e','rejected'=>'#ef4444','published'=>'#3b82f6'];
                    @endphp
                    @forelse($creatives as $cr)
                        <tr>
                            <td>
                                <strong>{{ $cr->title }}</strong>
                                @if($cr->file_url)
                                    <a href="{{ $cr->file_url }}" target="_blank" rel="noopener" class="muted" style="font-size:11px;margin-left:4px;">
                                        <i class="fa fa-external-link"></i>
                                    </a>
                                @endif
                            </td>
                            <td><span class="muted" style="font-size:12px;">{{ $cr->format ? ucfirst($cr->format) : '—' }}</span></td>
                            <td><span style="font-size:12px;">{{ Str::limit($cr->headline ?? '—', 40) }}</span></td>
                            <td><span class="muted" style="font-size:12px;">{{ $cr->dimensions ?: '—' }}</span></td>
                            <td>
                                <span class="pcat-badge" style="background:{{ $crColors[$cr->status] ?? '#94a3b8' }}20;color:{{ $crColors[$cr->status] ?? '#94a3b8' }};">
                                    {{ ucfirst($cr->status) }}
                                </span>
                            </td>
                            <td style="text-align:right;">
                                <form method="POST" action="{{ route('advertising-agency.campaigns.creatives.destroy', [$campaign, $cr]) }}"
                                      onsubmit="return confirm('Delete this creative?')" style="display:inline;">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="pcat-link pcat-link--danger" style="background:none;border:none;cursor:pointer;padding:0;font-size:12px;">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="pcat-empty">No creatives yet. Add one above.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ===================== TASKS ===================== --}}
    <div>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
            <h3 style="margin:0;font-size:14px;font-weight:700;">
                <i class="fa fa-check-square" style="margin-right:5px;color:var(--accent);"></i>
                Tasks ({{ $tasks->count() }})
            </h3>
            <button type="button" id="tk-add-btn" class="pcat-link" style="font-size:12px;">
                <i class="fa fa-plus"></i> Add task
            </button>
        </div>

        {{-- Add task form --}}
        <div id="tk-add-form" style="display:none;background:var(--surface-raised);border:1px solid var(--border);border-radius:6px;padding:14px;margin-bottom:12px;">
            <form method="POST" action="{{ route('advertising-agency.campaigns.tasks.store', $campaign) }}">
                @csrf
                <div class="pcat-form-grid">
                    <div class="pcat-form-field pcat-form-field--full">
                        <label>Title <span class="pcat-req">*</span></label>
                        <input type="text" name="title" placeholder="Task title" maxlength="150" required>
                        @error('title') <span class="pcat-err">{{ $message }}</span> @enderror
                    </div>
                    <div class="pcat-form-field">
                        <label>Priority</label>
                        <select name="priority">
                            @foreach(\Modules\AdvertisingAgency\Models\AgencyTask::PRIORITIES as $p)
                                <option value="{{ $p }}" @selected($p === 'medium')>{{ ucfirst($p) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pcat-form-field">
                        <label>Status</label>
                        <select name="status">
                            @foreach(\Modules\AdvertisingAgency\Models\AgencyTask::STATUSES as $s)
                                <option value="{{ $s }}" @selected($s === 'todo')>{{ ucwords(str_replace('_',' ',$s)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pcat-form-field">
                        <label>Due date</label>
                        <input type="datetime-local" name="due_at">
                    </div>
                    <div class="pcat-form-field">
                        <label>Assigned to</label>
                        <input type="text" name="assigned_to" placeholder="User ID or name" maxlength="100">
                    </div>
                    <div class="pcat-form-field pcat-form-field--full">
                        <label>Description</label>
                        <textarea name="description" rows="2" maxlength="2000" placeholder="Task details…"></textarea>
                    </div>
                </div>
                <div style="display:flex;gap:10px;margin-top:10px;justify-content:flex-end;">
                    <button type="button" id="tk-add-cancel" class="pcat-link" style="color:var(--text-muted);font-size:13px;">Cancel</button>
                    <button type="submit" class="linkbtn" style="padding:6px 16px;font-size:13px;">
                        <i class="fa fa-floppy-disk"></i> Save task
                    </button>
                </div>
            </form>
        </div>

        <div class="pcat-table-wrap">
            <table class="pcat-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Priority</th>
                        <th>Assigned</th>
                        <th>Due</th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $tkColors  = ['todo'=>'#94a3b8','in_progress'=>'#f59e0b','review'=>'#3b82f6','done'=>'#22c55e'];
                        $priColors = ['low'=>'#94a3b8','medium'=>'#3b82f6','high'=>'#f59e0b','urgent'=>'#ef4444'];
                    @endphp
                    @forelse($tasks as $tk)
                        <tr class="{{ $tk->isDone() ? 'muted' : '' }}">
                            <td>
                                <span style="{{ $tk->isDone() ? 'text-decoration:line-through;' : '' }}">{{ $tk->title }}</span>
                                @if($tk->isOverdue())
                                    <span style="font-size:10px;color:#ef4444;margin-left:4px;"><i class="fa fa-exclamation-circle"></i> Overdue</span>
                                @endif
                            </td>
                            <td>
                                <span style="font-size:12px;color:{{ $priColors[$tk->priority] ?? '#94a3b8' }};">
                                    {{ ucfirst($tk->priority) }}
                                </span>
                            </td>
                            <td><span class="muted" style="font-size:12px;">{{ $tk->assigned_to ?: '—' }}</span></td>
                            <td>
                                <span class="muted" style="font-size:12px;">
                                    {{ $tk->due_at?->format('d M Y H:i') ?? '—' }}
                                </span>
                            </td>
                            <td>
                                <form method="POST" action="{{ route('advertising-agency.campaigns.tasks.update', [$campaign, $tk]) }}">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="title"       value="{{ $tk->title }}">
                                    <input type="hidden" name="priority"    value="{{ $tk->priority }}">
                                    <input type="hidden" name="assigned_to" value="{{ $tk->assigned_to }}">
                                    <select name="status" onchange="this.form.submit()" style="font-size:12px;padding:2px 4px;border-radius:4px;">
                                        @foreach(\Modules\AdvertisingAgency\Models\AgencyTask::STATUSES as $s)
                                            <option value="{{ $s }}" @selected($tk->status === $s)>
                                                {{ ucwords(str_replace('_',' ',$s)) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </form>
                            </td>
                            <td style="text-align:right;">
                                <form method="POST" action="{{ route('advertising-agency.campaigns.tasks.destroy', [$campaign, $tk]) }}"
                                      onsubmit="return confirm('Delete this task?')" style="display:inline;">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="pcat-link pcat-link--danger" style="background:none;border:none;cursor:pointer;padding:0;font-size:12px;">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="pcat-empty">No tasks yet. Add one above.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    // Creatives toggle
    const crBtn    = document.getElementById('cr-add-btn');
    const crForm   = document.getElementById('cr-add-form');
    const crCancel = document.getElementById('cr-add-cancel');
    if (crBtn)    crBtn.addEventListener('click',    () => { crForm.style.display = ''; crForm.scrollIntoView({behavior:'smooth',block:'nearest'}); });
    if (crCancel) crCancel.addEventListener('click', () => { crForm.style.display = 'none'; });

    // Tasks toggle
    const tkBtn    = document.getElementById('tk-add-btn');
    const tkForm   = document.getElementById('tk-add-form');
    const tkCancel = document.getElementById('tk-add-cancel');
    if (tkBtn)    tkBtn.addEventListener('click',    () => { tkForm.style.display = ''; tkForm.scrollIntoView({behavior:'smooth',block:'nearest'}); });
    if (tkCancel) tkCancel.addEventListener('click', () => { tkForm.style.display = 'none'; });

    @if($errors->has('title'))
        // Re-open relevant form on validation error
        if (document.getElementById('cr-add-form')) document.getElementById('cr-add-form').style.display = '';
    @endif
})();
</script>
@endsection
