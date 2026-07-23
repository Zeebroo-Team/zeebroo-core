@extends('theme::layouts.app', ['title' => 'Mail settings', 'heading' => $business->name])

@section('content')
@include('product::partials.catalog-hub-styles')

<div class="pcat-page-card card" style="max-width:640px;padding:14px;">
    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if(session('error'))
        <div class="pcat-banner pcat-banner--err">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="pcat-banner pcat-banner--err">{{ $errors->first() }}</div>
    @endif

    <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
        Every email <strong style="color:var(--text);">{{ $business->name }}</strong> sends (lead stage automations, notifications, etc.) goes out through this configuration. Leave it on the platform default, or connect your own Resend account or SMTP server so mail comes from your own address.
    </p>

    <form method="POST" action="{{ route('mail.settings.update') }}" class="pcat-form-grid" id="mail-settings-form">
        @csrf
        @method('PUT')

        <div class="pcat-field" style="grid-column:1/-1;">
            <label for="mail-provider">Provider</label>
            <select id="mail-provider" name="provider">
                @foreach($providers as $key => $label)
                    <option value="{{ $key }}" @selected(old('provider', $settings['provider']) === $key)>{{ $label }}</option>
                @endforeach
            </select>
            @error('provider')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
        </div>

        <div class="pcat-field">
            <label for="mail-from-address">From address</label>
            <input type="email" id="mail-from-address" name="from_address" maxlength="190"
                   value="{{ old('from_address', $settings['from_address']) }}" placeholder="hello@yourbusiness.com">
            @error('from_address')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
        </div>

        <div class="pcat-field">
            <label for="mail-from-name">From name</label>
            <input type="text" id="mail-from-name" name="from_name" maxlength="150"
                   value="{{ old('from_name', $settings['from_name']) }}" placeholder="{{ $business->name }}">
            @error('from_name')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
        </div>

        <div class="pcat-active-row" style="grid-column:1/-1;">
            <label class="pcat-active-row__lbl" for="mail-letterhead-toggle">
                Show letterhead on every email
                <div class="muted" style="font-size:11.5px;font-weight:400;margin-top:2px;">
                    Adds your business logo and name as a header banner to every outgoing email (Compose, replies, templates, automations).
                </div>
            </label>
            <label class="pcat-switch">
                <input type="checkbox" id="mail-letterhead-toggle" name="letterhead_enabled" value="1"
                       @checked(old('letterhead_enabled', $settings['letterhead_enabled']))>
                <span class="pcat-switch-slider"></span>
            </label>
        </div>

        <div data-mail-provider-panel="resend" class="pcat-field" style="grid-column:1/-1;" hidden>
            <label for="mail-resend-key">Resend API key</label>
            <input type="password" id="mail-resend-key" name="resend_api_key" maxlength="255"
                   placeholder="{{ $hasResendKey ? '•••• saved — leave blank to keep it' : 're_xxxxxxxxxxxx' }}">
            <div class="muted" style="font-size:11px;margin-top:4px;">
                Find this in your Resend dashboard under API Keys. Leave blank to keep the currently saved key.
            </div>
            @error('resend_api_key')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
        </div>

        <div data-mail-provider-panel="smtp" class="pcat-form-grid" style="grid-column:1/-1;padding:0;" hidden>
            <div class="pcat-field">
                <label for="mail-smtp-host">SMTP host</label>
                <input type="text" id="mail-smtp-host" name="smtp_host" maxlength="190"
                       value="{{ old('smtp_host', $settings['smtp_host']) }}" placeholder="smtp.example.com">
                @error('smtp_host')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
            </div>
            <div class="pcat-field">
                <label for="mail-smtp-port">Port</label>
                <input type="number" id="mail-smtp-port" name="smtp_port" min="1" max="65535"
                       value="{{ old('smtp_port', $settings['smtp_port'] ?? 587) }}">
                @error('smtp_port')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
            </div>
            <div class="pcat-field">
                <label for="mail-smtp-username">Username</label>
                <input type="text" id="mail-smtp-username" name="smtp_username" maxlength="190"
                       value="{{ old('smtp_username', $settings['smtp_username']) }}">
                @error('smtp_username')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
            </div>
            <div class="pcat-field">
                <label for="mail-smtp-password">Password</label>
                <input type="password" id="mail-smtp-password" name="smtp_password" maxlength="255"
                       placeholder="{{ $hasSmtpPassword ? '•••• saved — leave blank to keep it' : '' }}">
                @error('smtp_password')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
            </div>
            <div class="pcat-field" style="grid-column:1/-1;">
                <label for="mail-smtp-encryption">Encryption</label>
                <select id="mail-smtp-encryption" name="smtp_encryption">
                    @foreach(['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'None'] as $key => $label)
                        <option value="{{ $key }}" @selected(old('smtp_encryption', $settings['smtp_encryption'] ?: 'tls') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div style="grid-column:1/-1;display:flex;justify-content:flex-end;">
            <button type="submit" class="linkbtn" style="padding:8px 18px;font-size:13px;">Save mail settings</button>
        </div>
    </form>

    <div class="pcat-inline" style="margin-top:18px;">
        <h2 style="font-size:13px;">Send a test email</h2>
        <p class="pcat-muted" style="margin:0 0 10px;">Verify the settings above actually work before relying on them.</p>
        <form method="POST" action="{{ route('mail.settings.test') }}" class="pcat-form-grid" style="grid-template-columns:1fr auto;align-items:end;">
            @csrf
            <div class="pcat-field" style="margin:0;">
                <label for="mail-test-email">Send to</label>
                <input type="email" id="mail-test-email" name="test_email" required placeholder="you@example.com">
            </div>
            <button type="submit" class="linkbtn" style="padding:8px 16px;font-size:13px;">Send test</button>
        </form>
    </div>

    <div class="pcat-inline" style="margin-top:18px;" data-mail-provider-panel="resend" hidden>
        <h2 style="font-size:13px;">Domain authentication</h2>
        <p class="pcat-muted" style="margin:0 0 10px;">
            Register your sending domain with Resend and automatically add the required SPF/DKIM/DMARC records to Cloudflare — no manual DNS copy-pasting.
        </p>

        @if($settings['domain_name'])
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:12px;padding:9px 12px;border:1px solid var(--border);border-radius:10px;background:var(--card);font-size:12.5px;">
                <i class="fa fa-shield-halved" style="color:var(--muted);"></i>
                <strong style="color:var(--text);">{{ $settings['domain_name'] }}</strong>
                @if($settings['resend_domain_status'] === 'verified')
                    <span class="pcat-badge pcat-badge--on">Verified</span>
                @else
                    <span class="pcat-badge">{{ $settings['resend_domain_status'] ?? 'not started' }}</span>
                @endif
                <form method="POST" action="{{ route('mail.settings.domain.status') }}" style="margin:0 0 0 auto;">
                    @csrf
                    <button type="submit" class="pcat-link" style="background:none;border:none;cursor:pointer;font-size:12.5px;padding:0;">Check status</button>
                </form>
            </div>
        @endif

        <form method="POST" action="{{ route('mail.settings.domain.configure') }}" class="pcat-form-grid">
            @csrf
            <div class="pcat-field">
                <label for="domain-name">Domain</label>
                <input type="text" id="domain-name" name="domain_name" maxlength="190" required
                       value="{{ old('domain_name', $settings['domain_name']) }}" placeholder="yourbusiness.com">
                @error('domain_name')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
            </div>
            <div class="pcat-field">
                <label for="cloudflare-token">Cloudflare API token</label>
                <input type="password" id="cloudflare-token" name="cloudflare_api_token" maxlength="255"
                       placeholder="{{ $hasCloudflareToken ? '•••• saved — leave blank to keep it' : 'Scoped token with Zone:DNS:Edit' }}">
                @error('cloudflare_api_token')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
            </div>
            <div style="grid-column:1/-1;display:flex;justify-content:flex-end;">
                <button type="submit" class="linkbtn" style="padding:8px 18px;font-size:13px;">Auto-configure domain</button>
            </div>
        </form>
    </div>

    <div class="pcat-inline" style="margin-top:18px;">
        <h2 style="font-size:13px;">Inbox (receive mail)</h2>
        <p class="pcat-muted" style="margin:0 0 10px;">
            Connect a real mailbox you own (Gmail, Outlook, cPanel email, etc.) via IMAP so its messages show up in
            <a href="{{ route('mail.inbox.index') }}" class="pcat-link">Mail</a>. Synced automatically every few minutes.
        </p>

        @if($mailbox)
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:12px;padding:9px 12px;border:1px solid var(--border);border-radius:10px;background:var(--card);font-size:12.5px;">
                <i class="fa fa-inbox" style="color:var(--muted);"></i>
                <strong style="color:var(--text);">{{ $mailbox->email_address }}</strong>
                @if($mailbox->last_sync_error)
                    <span class="pcat-badge" style="border-color:color-mix(in srgb,#ef4444 45%,var(--border));color:#f97373;">Sync error</span>
                @elseif($mailbox->last_synced_at)
                    <span class="muted">Last synced {{ $mailbox->last_synced_at->diffForHumans() }}</span>
                @else
                    <span class="muted">Not synced yet</span>
                @endif
                <div style="margin-left:auto;display:flex;gap:8px;">
                    <form method="POST" action="{{ route('mail.settings.mailbox.sync') }}" style="margin:0;">
                        @csrf
                        <button type="submit" class="pcat-link" style="background:none;border:none;cursor:pointer;font-size:12.5px;padding:0;">Sync now</button>
                    </form>
                    <form method="POST" action="{{ route('mail.settings.mailbox.disconnect') }}" style="margin:0;" onsubmit="return confirm('Disconnect this mailbox?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="pcat-link" style="background:none;border:none;cursor:pointer;font-size:12.5px;padding:0;color:#f87171;">Disconnect</button>
                    </form>
                </div>
            </div>
            @if($mailbox->last_sync_error)
                <div class="pcat-banner pcat-banner--err" style="margin-bottom:12px;">{{ $mailbox->last_sync_error }}</div>
            @endif
        @endif

        <form method="POST" action="{{ route('mail.settings.mailbox.connect') }}" class="pcat-form-grid">
            @csrf
            <div class="pcat-field" style="grid-column:1/-1;">
                <label for="mailbox-email">Email address</label>
                <input type="email" id="mailbox-email" name="email_address" maxlength="190" required
                       value="{{ old('email_address', $mailbox->email_address ?? '') }}" placeholder="you@yourbusiness.com">
                @error('email_address')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
            </div>
            <div class="pcat-field">
                <label for="mailbox-host">IMAP host</label>
                <input type="text" id="mailbox-host" name="imap_host" maxlength="190" required
                       value="{{ old('imap_host', $mailbox->imap_host ?? '') }}" placeholder="imap.gmail.com">
                @error('imap_host')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
            </div>
            <div class="pcat-field">
                <label for="mailbox-port">Port</label>
                <input type="number" id="mailbox-port" name="imap_port" min="1" max="65535"
                       value="{{ old('imap_port', $mailbox->imap_port ?? 993) }}">
                @error('imap_port')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
            </div>
            <div class="pcat-field">
                <label for="mailbox-username">Username</label>
                <input type="text" id="mailbox-username" name="imap_username" maxlength="190" required
                       value="{{ old('imap_username', $mailbox->imap_username ?? '') }}">
                @error('imap_username')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
            </div>
            <div class="pcat-field">
                <label for="mailbox-password">Password</label>
                <input type="password" id="mailbox-password" name="imap_password" maxlength="255"
                       placeholder="{{ $mailbox ? '•••• saved — leave blank to keep it' : '' }}">
                @error('imap_password')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
            </div>
            <div class="pcat-field" style="grid-column:1/-1;">
                <label for="mailbox-encryption">Encryption</label>
                <select id="mailbox-encryption" name="imap_encryption">
                    @foreach(['ssl' => 'SSL', 'tls' => 'TLS', 'none' => 'None'] as $key => $label)
                        <option value="{{ $key }}" @selected(old('imap_encryption', $mailbox->imap_encryption ?? 'ssl') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div style="grid-column:1/-1;display:flex;justify-content:flex-end;">
                <button type="submit" class="linkbtn" style="padding:8px 18px;font-size:13px;">{{ $mailbox ? 'Update mailbox' : 'Connect mailbox' }}</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var select = document.getElementById('mail-provider');
    function syncPanels() {
        document.querySelectorAll('[data-mail-provider-panel]').forEach(function (panel) {
            panel.hidden = panel.getAttribute('data-mail-provider-panel') !== select.value;
        });
    }
    select?.addEventListener('change', syncPanels);
    syncPanels();
})();
</script>
@endsection
