@extends('theme::layouts.app', ['title' => $definition['name'], 'heading' => 'Automated Emails'])

@section('content')
@include('mail::admin.marketing._styles')
@include('mail::admin.automated._styles')

@php
    $s = $email->settings ?? [];
    $hours = collect(range(0, 23))->mapWithKeys(fn ($h) => [$h => sprintf('%02d:00', $h)]);
    $weekdays = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
@endphp

<div class="aem-wrap">
    <a href="{{ route('admin.automated-emails.index') }}" class="aem-back"><i class="fa fa-arrow-left"></i> Automated Emails</a>
    <div class="aem-header">
        <div>
            <h1 class="aem-title"><i class="fa {{ $definition['icon'] }}" style="color:var(--primary);margin-right:8px;"></i>{{ $definition['name'] }}</h1>
            <p class="aem-sub">{{ $definition['description'] }}</p>
        </div>
    </div>

    @if(session('status'))
        <div class="aem-msg"><i class="fa fa-circle-check" style="color:#22c55e;"></i> {{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="aem-msg aem-msg--err"><i class="fa fa-circle-exclamation"></i> {{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('admin.automated-emails.update', $key) }}" id="aae-form">
        @csrf @method('PUT')

        <div class="aae-layout">
            <div class="aem-card">
                <div class="aem-card-head">
                    <p class="aem-card-title"><i class="fa fa-pen-ruler"></i> Email template</p>
                    <button type="submit" form="aae-reset-form" class="aem-btn aem-btn--sm" title="Replace the subject and body with the default design">
                        <i class="fa fa-rotate-left"></i> Restore default
                    </button>
                </div>
                <div class="aem-card-body">
                    <label class="aem-field">
                        <span class="aem-label">Email subject</span>
                        <input type="text" name="subject" id="aem-subject" class="aem-input" maxlength="200" required value="{{ old('subject', $email->subject) }}">
                    </label>

                    @include('mail::admin.marketing._editor', [
                        'body' => old('body', $email->body),
                        'placeholders' => $placeholders,
                        'extraSample' => $sample,
                        'rawSampleTags' => ['report_summary', 'release_notes'],
                        'testUrl' => route('admin.automated-emails.send-test', $key),
                        'showUnsubscribe' => $definition['marketing'],
                    ])
                </div>
            </div>

            <aside class="aae-side">
                <div class="aem-card">
                    <div class="aem-card-head"><p class="aem-card-title"><i class="fa fa-sliders"></i> Settings</p></div>
                    <div class="aem-card-body">
                        @if($definition['required'])
                            <p class="aem-hint" style="margin:0 0 14px;"><i class="fa fa-lock"></i> Always on — users need this email to recover their account.</p>
                        @else
                            <label class="aae-check">
                                <input type="hidden" name="is_enabled" value="0">
                                <input type="checkbox" name="is_enabled" value="1" {{ old('is_enabled', $email->is_enabled) ? 'checked' : '' }}>
                                <span><strong>Send this email automatically</strong><br><span class="aem-hint">Turn off to stop sending without losing your template.</span></span>
                            </label>
                        @endif

                        @switch($key)
                            @case('email_verification')
                                <label class="aem-field">
                                    <span class="aem-label">Code valid for (minutes)</span>
                                    <input type="number" name="settings[otp_minutes]" class="aem-input" min="5" max="60" required value="{{ old('settings.otp_minutes', $s['otp_minutes'] ?? 15) }}">
                                    <span class="aem-hint">Between 5 and 60. Shown in the email as &#123;&#123;expiry_minutes&#125;&#125;.</span>
                                </label>
                                <p class="aem-hint" style="margin:0;"><i class="fa fa-circle-info"></i> While on, people who sign up see a "Verify your email" banner in the web app, POS desktop and POS Lite until they enter the code. Turning it off hides the banner. Accounts created by admins, employees and Google sign-ins are never asked.</p>
                                @break

                            @case('password_reset')
                                <label class="aem-field">
                                    <span class="aem-label">Code valid for (minutes)</span>
                                    <input type="number" name="settings[otp_minutes]" class="aem-input" min="5" max="60" required value="{{ old('settings.otp_minutes', $s['otp_minutes'] ?? 10) }}">
                                    <span class="aem-hint">Between 5 and 60. Shown in the email as &#123;&#123;expiry_minutes&#125;&#125;.</span>
                                </label>
                                @break

                            @case('inactivity_reminder')
                                <label class="aem-field">
                                    <span class="aem-label">Remind after (days inactive)</span>
                                    <input type="number" name="settings[days]" class="aem-input" min="1" max="365" required value="{{ old('settings.days', $s['days'] ?? 2) }}">
                                    <span class="aem-hint">Days since the user last used the web app or desktop app. Each user gets one reminder per inactive stretch.</span>
                                </label>
                                <label class="aem-field">
                                    <span class="aem-label">Check & send daily at</span>
                                    <select name="settings[send_hour]" class="aem-select">
                                        @foreach($hours as $h => $label)
                                            <option value="{{ $h }}" @selected((int) old('settings.send_hour', $s['send_hour'] ?? 9) === $h)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                @break

                            @case('activity_report')
                                <label class="aem-field">
                                    <span class="aem-label">Frequency</span>
                                    <select name="settings[frequency]" class="aem-select" id="aae-frequency">
                                        @foreach(\Modules\Mail\Services\AutomatedEmailService::FREQUENCIES as $value => $label)
                                            <option value="{{ $value }}" @selected(old('settings.frequency', $s['frequency'] ?? 'weekly') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <span class="aem-hint" id="aae-frequency-hint"></span>
                                </label>
                                <div class="aae-row">
                                    <label class="aem-field" id="aae-day-field">
                                        <span class="aem-label">Day</span>
                                        <select name="settings[day_of_week]" class="aem-select">
                                            @foreach($weekdays as $d => $label)
                                                <option value="{{ $d }}" @selected((int) old('settings.day_of_week', $s['day_of_week'] ?? 1) === $d)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="aem-field">
                                        <span class="aem-label">Time</span>
                                        <select name="settings[send_hour]" class="aem-select">
                                            @foreach($hours as $h => $label)
                                                <option value="{{ $h }}" @selected((int) old('settings.send_hour', $s['send_hour'] ?? 8) === $h)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                </div>
                                <label class="aae-check">
                                    <input type="hidden" name="settings[skip_empty]" value="0">
                                    <input type="checkbox" name="settings[skip_empty]" value="1" {{ old('settings.skip_empty', $s['skip_empty'] ?? true) ? 'checked' : '' }}>
                                    <span>Skip users with no sales or new customers in the period</span>
                                </label>
                                @break

                            @case('new_release')
                                <div class="aem-field">
                                    <span class="aem-label">Announce releases on</span>
                                    @foreach(\Modules\Mail\Services\AutomatedEmailService::RELEASE_CHANNELS as $channel)
                                        <label class="aae-check" style="margin-bottom:4px;">
                                            <input type="checkbox" name="settings[channels][]" value="{{ $channel }}" @checked(in_array($channel, old('settings.channels', $s['channels'] ?? ['stable']), true))>
                                            <span>{{ ucfirst($channel) }} channel</span>
                                        </label>
                                    @endforeach
                                    <span class="aem-hint">Sent when you publish a release in <a href="{{ route('admin.releases.index') }}">Release Management</a> with "Email users" ticked. Each release is announced once.</span>
                                </div>
                                @break

                            @default
                                <p class="aem-hint" style="margin:0;">Sent once, right after sign-up (web form, Google or the desktop app). No extra settings.</p>
                        @endswitch

                        <button type="submit" class="aem-btn aem-btn--primary" style="width:100%;justify-content:center;margin-top:6px;">
                            <i class="fa fa-floppy-disk"></i> Save changes
                        </button>
                    </div>
                </div>

                <div class="aem-card">
                    <div class="aem-card-head"><p class="aem-card-title"><i class="fa fa-clock-rotate-left"></i> Recent sends</p></div>
                    <div class="aae-log-list">
                        @forelse($logs as $log)
                            <div class="aae-log" title="{{ $log->error }}">
                                <div style="min-width:0;">
                                    <div class="aae-log-email">{{ $log->email }}</div>
                                    <div class="aem-meta">{{ $log->created_at?->format('d M Y, H:i') }}</div>
                                </div>
                                <span class="aem-badge aem-badge--{{ $log->status }}">{{ $log->status }}</span>
                            </div>
                        @empty
                            <p class="aem-hint" style="padding:14px 18px;margin:0;">Nothing sent yet.</p>
                        @endforelse
                    </div>
                </div>
            </aside>
        </div>
    </form>

    <form method="POST" action="{{ route('admin.automated-emails.reset', $key) }}" id="aae-reset-form" onsubmit="return confirm('Replace the subject and body with the default template? Your current text will be lost.');">
        @csrf
    </form>
</div>

<script>
document.getElementById('aae-form').addEventListener('submit', function (e) {
    window.aemEditor.sync();
    if (window.aemEditor.isEmpty()) {
        e.preventDefault();
        alert('The email body is empty.');
    }
});

(function () {
    const freq = document.getElementById('aae-frequency');
    if (!freq) return;
    const dayField = document.getElementById('aae-day-field');
    const hint = document.getElementById('aae-frequency-hint');
    const hints = {
        daily: 'Covers the previous day.',
        weekly: 'Covers the previous 7 days.',
        monthly: 'Sent on the 1st, covering the previous month.',
    };
    function update() {
        dayField.style.display = freq.value === 'weekly' ? '' : 'none';
        hint.textContent = hints[freq.value] || '';
    }
    freq.addEventListener('change', update);
    update();
})();
</script>
@endsection
