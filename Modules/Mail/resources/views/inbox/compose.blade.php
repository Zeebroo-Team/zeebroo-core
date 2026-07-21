@extends('theme::layouts.app', ['title' => 'Compose', 'heading' => $business->name])

@section('content')
@include('product::partials.catalog-hub-styles')

<div class="pcat-page-card card" style="max-width:100%;padding:20px;">
    <div style="margin-bottom:14px;">
        <a href="{{ route('mail.inbox.index') }}" class="pcat-link">
            <i class="fa fa-arrow-left"></i> Mail
        </a>
    </div>

    @if($errors->any())
        <div class="pcat-banner pcat-banner--err">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('mail.inbox.send') }}" class="pcat-form-grid">
        @csrf

        @if($templates->isNotEmpty())
            <div class="pcat-field" style="grid-column:1/-1;">
                <label for="compose-template">Use a template</label>
                <select id="compose-template">
                    <option value="">— None —</option>
                    @foreach($templates as $t)
                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div class="pcat-field" style="grid-column:1/-1;">
            <label for="compose-to">To</label>
            <input type="email" id="compose-to" name="to" required maxlength="190"
                   value="{{ old('to', $replyTo?->from_address) }}" placeholder="someone@example.com">
            @error('to')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
        </div>
        <div class="pcat-field" style="grid-column:1/-1;">
            <label for="compose-subject">Subject</label>
            <input type="text" id="compose-subject" name="subject" required maxlength="200"
                   value="{{ old('subject', $replyTo ? 'Re: ' . $replyTo->subject : '') }}">
            @error('subject')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
        </div>
        <div class="pcat-field" style="grid-column:1/-1;">
            <label for="compose-body">Message</label>
            <textarea id="compose-body" name="body" rows="10" required>{{ old('body', $replyTo ? "\n\nOn " . $replyTo->occurred_at?->format('d M Y, H:i') . ", " . ($replyTo->from_name ?: $replyTo->from_address) . " wrote:\n> " . str_replace("\n", "\n> ", $replyTo->body_text) : '') }}</textarea>
            @error('body')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
        </div>

        <div class="pcat-active-row" style="grid-column:1/-1;">
            <label class="pcat-active-row__lbl" for="compose-schedule-toggle">Send later</label>
            <label class="pcat-switch">
                <input type="checkbox" id="compose-schedule-toggle">
                <span class="pcat-switch-slider"></span>
            </label>
        </div>
        <div class="pcat-field" style="grid-column:1/-1;" id="compose-schedule-field" hidden>
            <label for="compose-send-at">Send at</label>
            <input type="datetime-local" id="compose-send-at" name="send_at" value="{{ old('send_at') }}">
            @error('send_at')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
        </div>

        <div style="grid-column:1/-1;display:flex;justify-content:flex-end;gap:8px;">
            <a href="{{ route('mail.inbox.index') }}" class="linkbtn" style="padding:8px 16px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">Cancel</a>
            <button type="submit" class="linkbtn" style="padding:8px 18px;font-size:13px;" id="compose-submit">Send</button>
        </div>
    </form>
</div>

<script>
(function () {
    var templates = @json($templates->map(fn ($t) => ['id' => $t->id, 'subject' => $t->subject, 'body' => $t->body])->values());
    var templateSelect = document.getElementById('compose-template');
    templateSelect?.addEventListener('change', function () {
        var tpl = templates.find(function (t) { return String(t.id) === templateSelect.value; });
        if (!tpl) return;
        document.getElementById('compose-subject').value = tpl.subject;
        document.getElementById('compose-body').value = tpl.body;
    });

    var scheduleToggle = document.getElementById('compose-schedule-toggle');
    var scheduleField  = document.getElementById('compose-schedule-field');
    var submitBtn      = document.getElementById('compose-submit');
    scheduleToggle?.addEventListener('change', function () {
        scheduleField.hidden = !scheduleToggle.checked;
        submitBtn.textContent = scheduleToggle.checked ? 'Schedule' : 'Send';
        document.getElementById('compose-send-at').required = scheduleToggle.checked;
    });
    if (document.getElementById('compose-send-at')?.value) {
        scheduleToggle.checked = true;
        scheduleField.hidden = false;
        submitBtn.textContent = 'Schedule';
    }
})();
</script>
@endsection
