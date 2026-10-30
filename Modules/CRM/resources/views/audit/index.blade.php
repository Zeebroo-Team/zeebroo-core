@extends('theme::layouts.app', ['title' => 'CRM Audit Log', 'heading' => 'CRM Audit Log'])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.crm-audit-filters{display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;margin-bottom:14px;}
.crm-audit-filters label{display:flex;flex-direction:column;gap:3px;font-size:11px;font-weight:700;color:var(--muted);}
.crm-audit-filters select,.crm-audit-filters input{font-size:12px;padding:5px 8px;}
.crm-audit-table{width:100%;border-collapse:collapse;font-size:12px;}
.crm-audit-table th,.crm-audit-table td{text-align:left;padding:8px;border-bottom:1px solid var(--border);vertical-align:top;}
.crm-audit-table th{font-size:10px;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);}
.crm-audit-event{display:inline-block;font-size:10px;font-weight:700;padding:2px 8px;border-radius:999px;border:1px solid currentColor;}
.crm-audit-changes{margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:2px;}
.crm-audit-changes code{font-size:11px;word-break:break-all;}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('crm::partials.crm-hub-nav')

    <p class="muted" style="margin:0 0 14px;font-size:13px;">
        Every change to CRM records, column by column, with who made it and from which IP. Admins only.
    </p>

    <form method="GET" class="crm-audit-filters">
        <label>Record
            <select name="subject">
                <option value="">All</option>
                @foreach($subjects as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['subject'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label>Record ID
            <input type="number" name="subject_id" min="1" value="{{ $filters['subject_id'] ?? '' }}" style="width:90px;">
        </label>
        <label>Event
            <select name="event">
                <option value="">All</option>
                @foreach($events as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['event'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label>Changed by
            <select name="user_id">
                <option value="">Anyone</option>
                @foreach($actors as $u)
                    <option value="{{ $u->id }}" @selected((int) ($filters['user_id'] ?? 0) === $u->id)>{{ $u->name }}</option>
                @endforeach
            </select>
        </label>
        <label>From <input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
        <label>To <input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
        <button type="submit" class="linkbtn" style="padding:6px 14px;font-size:12px;">Filter</button>
        @if(array_filter($filters))
            <a href="{{ route('crm.audit.index') }}" class="pcat-link" style="font-size:12px;">Clear</a>
        @endif
    </form>

    @if($rows->isEmpty())
        <p class="muted" style="font-size:13px;">No audit entries match.</p>
    @else
        <div style="overflow-x:auto;">
            <table class="crm-audit-table">
                <thead><tr><th>When</th><th>Who</th><th>Record</th><th>Event</th><th>Changes</th><th>IP</th></tr></thead>
                <tbody>
                    @foreach($rows as $r)
                        <tr>
                            <td style="white-space:nowrap;">{{ \Illuminate\Support\Carbon::parse($r['created_at'])->format('M j, Y g:i:s A') }}</td>
                            <td>{{ $r['user']['name'] ?? 'System' }}</td>
                            <td style="white-space:nowrap;">
                                @if($r['subject'] === 'lead')
                                    <a href="{{ route('crm.leads.show', $r['subject_id']) }}" class="pcat-link">{{ $r['subject_label'] }} #{{ $r['subject_id'] }}</a>
                                @else
                                    {{ $r['subject_label'] }} #{{ $r['subject_id'] }}
                                @endif
                            </td>
                            <td><span class="crm-audit-event" style="color:{{ ['created' => '#16a34a', 'updated' => '#0ea5e9', 'deleted' => '#ef4444'][$r['event']] ?? '#64748b' }};">{{ $r['event_label'] }}</span></td>
                            <td>
                                <ul class="crm-audit-changes">
                                    @foreach($r['changes'] as $c)
                                        <li><strong>{{ $c['field'] }}</strong>:
                                            @if($r['event'] === 'updated')<code>{{ json_encode($c['old']) }}</code> → @endif
                                            <code>{{ json_encode($r['event'] === 'deleted' ? $c['old'] : $c['new']) }}</code>
                                        </li>
                                    @endforeach
                                </ul>
                            </td>
                            <td style="white-space:nowrap;">{{ $r['ip'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div style="margin-top:12px;">{{ $logs->links() }}</div>
    @endif
</div>
@endsection
