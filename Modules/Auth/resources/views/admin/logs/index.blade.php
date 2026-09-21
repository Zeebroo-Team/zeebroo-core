@extends('theme::layouts.app', ['title' => 'Error Logs', 'heading' => 'Error Logs'])

@section('content')
<style>
.log-wrap{max-width:1200px;margin:0 auto;}
.log-header{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:20px;flex-wrap:wrap;}
.log-title{margin:0;font-size:19px;font-weight:800;letter-spacing:-.025em;}
.log-sub{margin:4px 0 0;font-size:12.5px;color:var(--muted);}
.log-filters{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;}
.log-filters select,.log-filters input{padding:8px 11px;border-radius:9px;border:1px solid var(--border);background:var(--card);
    color:var(--text);font-size:12.5px;font-family:inherit;}
.log-filters input{flex:1;min-width:200px;}
.log-btn{padding:8px 15px;border-radius:9px;border:1px solid color-mix(in srgb,var(--btn-bg) 55%,var(--border));
    background:var(--btn-bg);color:#fff;font-size:12.5px;font-weight:700;cursor:pointer;font-family:inherit;text-decoration:none;}
.log-btn:hover{background:var(--btn-hover);color:#111827;}
.log-list{border:1px solid var(--border);border-radius:14px;background:var(--card);overflow:hidden;}
.log-item{border-bottom:1px solid var(--border);}
.log-item:last-child{border-bottom:0;}
.log-item summary{list-style:none;cursor:pointer;display:flex;align-items:flex-start;gap:12px;padding:11px 16px;}
.log-item summary::-webkit-details-marker{display:none;}
.log-item summary:hover{background:color-mix(in srgb,var(--primary) 5%,transparent);}
.log-time{font-size:11.5px;color:var(--muted);white-space:nowrap;font-variant-numeric:tabular-nums;padding-top:2px;}
.log-level{font-size:9.5px;font-weight:800;text-transform:uppercase;padding:2px 8px;border-radius:999px;flex-shrink:0;margin-top:1px;
    background:color-mix(in srgb,#6b7280 16%,transparent);color:#6b7280;}
.log-level--emergency,.log-level--alert,.log-level--critical,.log-level--error{background:color-mix(in srgb,#ef4444 14%,transparent);color:#b91c1c;}
.log-level--warning{background:color-mix(in srgb,#f59e0b 16%,transparent);color:#b45309;}
.log-level--notice,.log-level--info{background:color-mix(in srgb,#3b82f6 14%,transparent);color:#1d4ed8;}
.log-msg{flex:1;min-width:0;font-size:12.5px;line-height:1.5;word-break:break-word;}
.log-trace{margin:0;padding:12px 16px 14px;font-size:11px;line-height:1.55;overflow-x:auto;white-space:pre-wrap;word-break:break-all;
    background:color-mix(in srgb,var(--border) 25%,transparent);font-family:ui-monospace,Consolas,monospace;}
.log-empty{padding:48px 24px;text-align:center;color:var(--muted);font-size:13px;}
</style>

<div class="log-wrap">
    <div class="log-header">
        <div>
            <h2 class="log-title">Error Logs</h2>
            <p class="log-sub">
                @if($file) Latest entries from <strong>{{ $file }}</strong> (newest first). @else No log files found. @endif
            </p>
        </div>
    </div>

    <form method="GET" class="log-filters">
        <select name="file" onchange="this.form.submit()">
            @foreach($files as $f)
                <option value="{{ $f['name'] }}" @selected($f['name'] === $file)>{{ $f['name'] }} ({{ number_format($f['size'] / 1024, 0) }} KB)</option>
            @endforeach
        </select>
        <select name="level" onchange="this.form.submit()">
            <option value="">All levels</option>
            @foreach($levels as $l)
                <option value="{{ $l }}" @selected($l === $level)>{{ ucfirst($l) }}</option>
            @endforeach
        </select>
        <input type="search" name="q" value="{{ $search }}" placeholder="Search messages…">
        <button type="submit" class="log-btn">Filter</button>
    </form>

    <div class="log-list">
        @forelse($entries as $entry)
            <details class="log-item">
                <summary>
                    <span class="log-time">{{ $entry['time'] }}</span>
                    <span class="log-level log-level--{{ $entry['level'] }}">{{ $entry['level'] }}</span>
                    <span class="log-msg">{{ \Illuminate\Support\Str::limit($entry['message'], 300) }}</span>
                </summary>
                <pre class="log-trace">{{ $entry['message'] }}{{ $entry['trace'] !== '' ? "\n\n".$entry['trace'] : '' }}</pre>
            </details>
        @empty
            <div class="log-empty">No log entries match.</div>
        @endforelse
    </div>

    <div style="margin-top:16px;">{{ $entries->links() }}</div>
</div>
@endsection
