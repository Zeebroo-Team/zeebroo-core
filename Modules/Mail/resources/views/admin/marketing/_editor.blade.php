{{--
    Rich email body editor shared by the template form and the send page.
    Expects: $body (initial HTML), $placeholders (tag => label).
    Optional: $extraSample (tag => preview value), $rawSampleTags (tags whose sample is HTML),
              $testUrl (send-test endpoint), $showUnsubscribe (preview footer link, default true).
    Writes the HTML into <textarea name="body" id="aem-body-input"> on submit.
    The subject input (if any) must have id="aem-subject" so merge tags/preview/test can use it.
--}}
@php
    $previewUser = auth()->user();
    $previewSample = array_merge([
        'name' => $previewUser->name,
        'first_name' => strtok((string) $previewUser->name, ' ') ?: '',
        'email' => $previewUser->email,
        'app_name' => config('app.name'),
    ], $extraSample ?? []);
@endphp
<div class="aem-field" style="margin-bottom:10px;">
    <span class="aem-label">Merge tags <span style="text-transform:none;letter-spacing:0;font-weight:500;">— click to insert; replaced per recipient</span></span>
    <div class="aem-tags">
        @foreach($placeholders as $tag => $label)
            <button type="button" class="aem-tag" data-tag="{{ $tag }}" title="{{ $label }}">&#123;&#123;{{ $tag }}&#125;&#125;</button>
        @endforeach
    </div>
</div>

<div class="aem-editor" id="aem-editor">
    <div class="aem-toolbar" role="toolbar" aria-label="Formatting">
        <select data-block title="Text style">
            <option value="p">Paragraph</option>
            <option value="h1">Heading 1</option>
            <option value="h2">Heading 2</option>
            <option value="h3">Heading 3</option>
            <option value="blockquote">Quote</option>
        </select>
        <span class="sep"></span>
        <button type="button" data-cmd="bold" title="Bold"><i class="fa fa-bold"></i></button>
        <button type="button" data-cmd="italic" title="Italic"><i class="fa fa-italic"></i></button>
        <button type="button" data-cmd="underline" title="Underline"><i class="fa fa-underline"></i></button>
        <button type="button" data-cmd="strikeThrough" title="Strikethrough"><i class="fa fa-strikethrough"></i></button>
        <input type="color" data-color value="#1e293b" title="Text colour">
        <span class="sep"></span>
        <button type="button" data-cmd="insertUnorderedList" title="Bulleted list"><i class="fa fa-list-ul"></i></button>
        <button type="button" data-cmd="insertOrderedList" title="Numbered list"><i class="fa fa-list-ol"></i></button>
        <button type="button" data-cmd="justifyLeft" title="Align left"><i class="fa fa-align-left"></i></button>
        <button type="button" data-cmd="justifyCenter" title="Align centre"><i class="fa fa-align-center"></i></button>
        <button type="button" data-cmd="justifyRight" title="Align right"><i class="fa fa-align-right"></i></button>
        <span class="sep"></span>
        <button type="button" data-action="link" title="Insert link"><i class="fa fa-link"></i></button>
        <button type="button" data-cmd="unlink" title="Remove link"><i class="fa fa-link-slash"></i></button>
        <button type="button" data-action="image" title="Insert image from URL"><i class="fa fa-image"></i></button>
        <button type="button" data-action="button" title="Insert call-to-action button"><i class="fa fa-square-arrow-up-right"></i></button>
        <button type="button" data-cmd="insertHorizontalRule" title="Divider"><i class="fa fa-minus"></i></button>
        <span class="sep"></span>
        <button type="button" data-cmd="removeFormat" title="Clear formatting"><i class="fa fa-text-slash"></i></button>
        <button type="button" data-cmd="undo" title="Undo"><i class="fa fa-rotate-left"></i></button>
        <button type="button" data-cmd="redo" title="Redo"><i class="fa fa-rotate-right"></i></button>
        <span class="sep"></span>
        <button type="button" data-action="source" title="Edit HTML source"><i class="fa fa-code"></i></button>
    </div>
    <div class="aem-canvas">
        <div class="aem-surface" id="aem-surface" contenteditable="true" spellcheck="true"></div>
    </div>
    <textarea class="aem-source" id="aem-source" spellcheck="false" aria-label="HTML source"></textarea>
</div>
<textarea name="body" id="aem-body-input" hidden>{{ $body }}</textarea>

<div class="aem-actions" style="margin-top:10px;justify-content:space-between;">
    <span class="aem-hint">Tip: paste from a document or use <i class="fa fa-code"></i> to paste your own HTML. Images must be public URLs.</span>
    <div class="aem-actions">
        <button type="button" class="aem-btn aem-btn--sm" id="aem-preview-btn"><i class="fa fa-eye"></i> Preview</button>
        <button type="button" class="aem-btn aem-btn--sm" id="aem-test-btn" title="Send this email to {{ auth()->user()->email }}"><i class="fa fa-flask"></i> Send test to me</button>
    </div>
</div>
<div class="aem-hint" id="aem-test-result" style="margin-top:6px;text-align:right;"></div>

<div class="aem-modal" id="aem-preview-modal" role="dialog" aria-modal="true" aria-label="Email preview">
    <div class="aem-modal-box">
        <div class="aem-card-head">
            <div style="min-width:0;">
                <p class="aem-card-title" style="margin:0;"><i class="fa fa-eye"></i> Preview</p>
                <div class="aem-meta" id="aem-preview-subject" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></div>
            </div>
            <button type="button" class="aem-btn aem-btn--sm" data-close-preview><i class="fa fa-xmark"></i> Close</button>
        </div>
        <iframe id="aem-preview-frame" sandbox title="Email preview"></iframe>
    </div>
</div>

<script>
(function () {
    const editor  = document.getElementById('aem-editor');
    const surface = document.getElementById('aem-surface');
    const source  = document.getElementById('aem-source');
    const input   = document.getElementById('aem-body-input');
    const subject = document.getElementById('aem-subject');
    const form    = input.closest('form');
    const sample  = @json($previewSample);
    const rawTags = @json(array_values($rawSampleTags ?? []));
    const tagRe   = new RegExp('(?:\\{\\{|%7B%7B)\\s*(' + Object.keys(sample).join('|') + ')\\s*(?:\\}\\}|%7D%7D)', 'gi');

    surface.innerHTML = input.value || '<p><br></p>';
    try { document.execCommand('styleWithCSS', false, true); } catch (e) {}

    let isSource = false;
    let savedRange = null;
    let lastField = surface;

    const html = () => isSource ? source.value : surface.innerHTML;
    const sync = () => { input.value = html(); };

    // Keep the caret position so toolbar clicks / prompts insert where the user was typing.
    document.addEventListener('selectionchange', function () {
        const sel = window.getSelection();
        if (sel.rangeCount && surface.contains(sel.anchorNode)) savedRange = sel.getRangeAt(0).cloneRange();
    });
    function restore() {
        surface.focus();
        if (savedRange) {
            const sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(savedRange);
        }
    }
    function exec(cmd, val) {
        restore();
        document.execCommand(cmd, false, val ?? null);
        refreshState();
    }
    function esc(s) { return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
    function safeUrl(u) {
        u = (u || '').trim();
        if (!u) return null;
        if (/^(https?:|mailto:|tel:)/i.test(u) || /^\{\{\s*\w+\s*\}\}$/.test(u)) return u;
        return 'https://' + u.replace(/^\/+/, '');
    }

    function refreshState() {
        editor.querySelectorAll('[data-cmd]').forEach(function (b) {
            try { b.classList.toggle('is-on', document.queryCommandState(b.dataset.cmd)); } catch (e) {}
        });
    }
    surface.addEventListener('keyup', refreshState);
    surface.addEventListener('mouseup', refreshState);
    surface.addEventListener('focus', () => { lastField = surface; });
    if (subject) subject.addEventListener('focus', () => { lastField = subject; });

    editor.querySelectorAll('.aem-toolbar button, .aem-toolbar select, .aem-toolbar input').forEach(function (el) {
        // Prevent toolbar buttons from stealing the selection.
        if (el.tagName === 'BUTTON') el.addEventListener('mousedown', e => e.preventDefault());
    });
    editor.querySelectorAll('[data-cmd]').forEach(b => b.addEventListener('click', () => exec(b.dataset.cmd)));
    editor.querySelector('[data-block]').addEventListener('change', function () { exec('formatBlock', '<' + this.value + '>'); });
    editor.querySelector('[data-color]').addEventListener('input', function () { exec('foreColor', this.value); });

    editor.querySelector('[data-action="link"]').addEventListener('click', function () {
        const url = safeUrl(prompt('Link URL', 'https://'));
        if (!url) return;
        restore();
        const sel = window.getSelection();
        if (sel.isCollapsed) exec('insertHTML', '<a href="' + esc(url) + '">' + esc(url) + '</a>');
        else exec('createLink', url);
    });
    editor.querySelector('[data-action="image"]').addEventListener('click', function () {
        const url = safeUrl(prompt('Image URL (must be publicly accessible)', 'https://'));
        if (url) exec('insertHTML', '<img src="' + esc(url) + '" alt="" style="max-width:100%;height:auto;">');
    });
    editor.querySelector('[data-action="button"]').addEventListener('click', function () {
        const label = prompt('Button text', 'Get started');
        if (!label) return;
        const url = safeUrl(prompt('Button link', 'https://'));
        if (!url) return;
        exec('insertHTML', '<p style="text-align:center;margin:22px 0;"><a href="' + esc(url) + '" style="display:inline-block;padding:12px 26px;background:#ca8a04;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:600;">' + esc(label) + '</a></p><p><br></p>');
    });
    editor.querySelector('[data-action="source"]').addEventListener('click', function () {
        if (isSource) {
            surface.innerHTML = source.value;
        } else {
            source.value = surface.innerHTML;
        }
        isSource = !isSource;
        editor.classList.toggle('is-source', isSource);
        this.classList.toggle('is-on', isSource);
        editor.querySelectorAll('.aem-toolbar [data-cmd], .aem-toolbar [data-action]:not([data-action="source"]), .aem-toolbar select, .aem-toolbar input')
            .forEach(el => el.disabled = isSource);
        (isSource ? source : surface).focus();
    });

    document.querySelectorAll('.aem-tag').forEach(function (b) {
        b.addEventListener('mousedown', e => e.preventDefault());
        b.addEventListener('click', function () {
            const token = '{' + '{' + b.dataset.tag + '}' + '}';
            if (lastField === subject && subject) {
                const s = subject.selectionStart ?? subject.value.length, e = subject.selectionEnd ?? s;
                subject.setRangeText(token, s, e, 'end');
                subject.focus();
            } else if (isSource) {
                source.setRangeText(token, source.selectionStart, source.selectionEnd, 'end');
                source.focus();
            } else {
                exec('insertText', token);
            }
        });
    });

    // ── Preview ──
    const modal = document.getElementById('aem-preview-modal');
    function fill(text, asHtml) {
        return text.replace(tagRe, function (m, k) {
            k = k.toLowerCase();
            const v = sample[k] == null ? '' : String(sample[k]);
            if (!asHtml) return v.replace(/<[^>]*>/g, '');
            return rawTags.includes(k) ? v : esc(v);
        });
    }
    document.getElementById('aem-preview-btn').addEventListener('click', function () {
        document.getElementById('aem-preview-subject').textContent = subject ? fill(subject.value || '(no subject)', false) : '';
        document.getElementById('aem-preview-frame').srcdoc =
            '<!doctype html><html><head><meta charset="utf-8"></head><body style="margin:0;padding:0;background:#f1f5f9;font-family:Segoe UI,Arial,sans-serif;">'
            + '<div style="max-width:600px;margin:0 auto;padding:32px 16px;"><div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:28px 24px;font-size:14px;line-height:1.6;color:#1e293b;">'
            + fill(html(), true)
            + '</div><div style="text-align:center;font-size:11.5px;color:#94a3b8;padding:16px 8px 0;">You\'re receiving this email because you have a ' + esc(sample.app_name) + ' account.'
            + (@json($showUnsubscribe ?? true) ? '<br><u>Unsubscribe from marketing emails</u>' : '') + '</div></div></body></html>';
        modal.classList.add('is-open');
    });
    modal.addEventListener('click', e => { if (e.target === modal || e.target.closest('[data-close-preview]')) modal.classList.remove('is-open'); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') modal.classList.remove('is-open'); });

    // ── Test send ──
    const testBtn = document.getElementById('aem-test-btn');
    const testOut = document.getElementById('aem-test-result');
    testBtn.addEventListener('click', async function () {
        sync();
        if (!subject || !subject.value.trim()) { testOut.style.color = '#dc2626'; testOut.textContent = 'Add a subject first.'; subject && subject.focus(); return; }
        testBtn.disabled = true;
        testOut.style.color = ''; testOut.textContent = 'Sending test…';
        try {
            const res = await fetch(@json($testUrl ?? route('admin.email-marketing.send-test')), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ subject: subject.value, body: input.value }),
            });
            const data = await res.json().catch(() => ({}));
            testOut.style.color = res.ok ? '#16a34a' : '#dc2626';
            testOut.textContent = data.message || (data.errors ? Object.values(data.errors)[0][0] : 'Could not send the test email.');
        } catch (e) {
            testOut.style.color = '#dc2626'; testOut.textContent = 'Network error — test not sent.';
        } finally {
            testBtn.disabled = false;
        }
    });

    if (form) form.addEventListener('submit', sync);
    window.aemEditor = {
        sync,
        setHtml: function (h) { surface.innerHTML = h || '<p><br></p>'; source.value = h || ''; sync(); },
        isEmpty: function () { const d = document.createElement('div'); d.innerHTML = html(); return !d.textContent.trim() && !d.querySelector('img'); },
    };
})();
</script>
