@extends('theme::layouts.app', ['title' => 'Automated Emails', 'heading' => 'Automated Emails'])

@section('content')
@include('mail::admin.marketing._styles')
@include('mail::admin.automated._styles')

@php
    $days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
    $hour = fn ($h) => sprintf('%02d:00', (int) $h);
@endphp

<div class="aem-wrap">
    <div class="aem-header">
        <div>
            <h1 class="aem-title"><i class="fa fa-envelope-circle-check" style="color:var(--primary);margin-right:8px;"></i>Automated Emails</h1>
            <p class="aem-sub">Emails the platform sends on its own. Edit each template, choose when it goes out, or switch it off. Reminders, reports and release news respect each user's unsubscribe choice.</p>
        </div>
    </div>

    @if(session('status'))
        <div class="aem-msg"><i class="fa fa-circle-check" style="color:#22c55e;"></i> {{ session('status') }}</div>
    @endif

    <div class="aae-list">
        @foreach($definitions as $key => $def)
            @php
                $email = $emails[$key];
                $s = $email->settings ?? [];
                $stat = $stats[$key] ?? ['sent' => 0, 'failed' => 0, 'last' => null];
                $trigger = match ($key) {
                    'welcome' => 'Right after a new account is created',
                    'password_reset' => 'When a user requests a reset · code valid '.($s['otp_minutes'] ?? 10).' min',
                    'inactivity_reminder' => 'After '.($s['days'] ?? 2).' '.(($s['days'] ?? 2) == 1 ? 'day' : 'days').' without activity · checked daily at '.$hour($s['send_hour'] ?? 9),
                    'activity_report' => match ($s['frequency'] ?? 'weekly') {
                        'daily' => 'Every day at '.$hour($s['send_hour'] ?? 8),
                        'monthly' => 'On the 1st of every month at '.$hour($s['send_hour'] ?? 8),
                        default => 'Every '.($days[$s['day_of_week'] ?? 1] ?? 'Monday').' at '.$hour($s['send_hour'] ?? 8),
                    },
                    'new_release' => 'When a '.implode(' / ', $s['channels'] ?? ['stable']).' release is published',
                    default => '',
                };
            @endphp
            <article class="aem-card aae-item {{ $email->is_enabled ? '' : 'is-off' }}">
                <div class="aae-icon"><i class="fa {{ $def['icon'] }}"></i></div>
                <div class="aae-info">
                    <div class="aae-name-row">
                        <h3 class="aae-name">{{ $def['name'] }}</h3>
                        @if($def['required'])
                            <span class="aem-badge aem-badge--completed" title="Needed for password recovery, can't be switched off">Always on</span>
                        @elseif($email->is_enabled)
                            <span class="aem-badge aem-badge--completed">On</span>
                        @else
                            <span class="aem-badge aem-badge--muted">Off</span>
                        @endif
                    </div>
                    <p class="aae-desc">{{ $def['description'] }}</p>
                    <div class="aae-meta">
                        <span><i class="fa fa-clock"></i> {{ $trigger }}</span>
                        <span><i class="fa fa-quote-left"></i> <span class="aae-subject">{{ $email->subject }}</span></span>
                    </div>
                </div>
                <div class="aae-stats" title="Last 30 days">
                    <div><strong>{{ number_format($stat['sent']) }}</strong><span>sent</span></div>
                    <div class="{{ $stat['failed'] ? 'is-bad' : '' }}"><strong>{{ number_format($stat['failed']) }}</strong><span>failed</span></div>
                    <div class="aae-last">{{ $stat['last'] ? 'Last '.\Illuminate\Support\Carbon::parse($stat['last'])->diffForHumans() : 'None in 30 days' }}</div>
                </div>
                <div class="aae-actions">
                    @unless($def['required'])
                        <form method="POST" action="{{ route('admin.automated-emails.toggle', $key) }}" style="margin:0;">
                            @csrf
                            <button type="submit" class="aae-switch {{ $email->is_enabled ? 'is-on' : '' }}" role="switch" aria-checked="{{ $email->is_enabled ? 'true' : 'false' }}" title="{{ $email->is_enabled ? 'Turn off' : 'Turn on' }}">
                                <span class="aae-switch-knob"></span>
                            </button>
                        </form>
                    @endunless
                    <a href="{{ route('admin.automated-emails.edit', $key) }}" class="aem-btn aem-btn--sm"><i class="fa fa-pen"></i> Edit</a>
                </div>
            </article>
        @endforeach
    </div>

    <p class="aem-hint" style="margin-top:14px;"><i class="fa fa-circle-info"></i> Scheduled emails need the server cron (<code>php artisan schedule:run</code>) and a queue worker running. Times use the server timezone ({{ config('app.timezone') }}).</p>
</div>
@endsection
