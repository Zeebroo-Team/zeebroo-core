@php
    $block = $block ?? ['type' => 'text'];
    $type = $block['type'] ?? 'text';
    $customFields = $customFields ?? collect();
    $idPrefix = $idPrefix ?? ('blk' . \Illuminate\Support\Str::random(6));
@endphp
@if($type === 'heading')
    <div class="lf-prop-field">
        <label class="lf-mini-label">Heading text</label>
        <input type="text" class="lf-block__input" data-field="text" placeholder="Heading text" value="{{ $block['text'] ?? '' }}">
    </div>
    <div class="lf-prop-field">
        <label class="lf-mini-label">Size</label>
        <select class="lf-block__input" data-field="size">
            <option value="lg" @selected(($block['size'] ?? 'lg') === 'lg')>Large heading</option>
            <option value="md" @selected(($block['size'] ?? 'lg') === 'md')>Medium heading</option>
        </select>
    </div>
@elseif($type === 'text')
    <div class="lf-prop-field">
        <label class="lf-mini-label">Text</label>
        <textarea class="lf-block__input" data-field="text" rows="5" placeholder="Paragraph text…">{{ $block['text'] ?? '' }}</textarea>
    </div>
@elseif($type === 'image')
    <div class="lf-prop-field">
        <label class="lf-mini-label">Image</label>
        @include('crm::partials.crm-image-field', [
            'fieldIdPrefix' => $idPrefix,
            'value' => filled($block['url'] ?? null) ? ['id' => $block['file_id'] ?? null, 'url' => $block['url'], 'name' => $block['alt'] ?? ''] : null,
        ])
    </div>
    <div class="lf-prop-field">
        <label class="lf-mini-label">Alt text</label>
        <input type="text" class="lf-block__input" data-field="alt" placeholder="Alt text (optional)" value="{{ $block['alt'] ?? '' }}">
    </div>
@elseif($type === 'field')
    <div class="lf-prop-field">
        <label class="lf-mini-label">Maps to</label>
        <select class="lf-block__input" data-field="field">
            <option value="name" @selected(($block['field'] ?? '') === 'name')>Name</option>
            <option value="email" @selected(($block['field'] ?? '') === 'email')>Email</option>
            <option value="phone" @selected(($block['field'] ?? '') === 'phone')>Phone</option>
            <option value="company" @selected(($block['field'] ?? '') === 'company')>Company</option>
            @foreach($customFields as $cf)
                <option value="custom:{{ $cf->id }}" @selected(($block['field'] ?? '') === "custom:{$cf->id}")>{{ $cf->label }}</option>
            @endforeach
            <option value="__new__">+ Add new field…</option>
        </select>
    </div>
    <div class="lf-prop-field" data-lf-new-field-panel hidden style="border:1px solid var(--border);border-radius:10px;padding:10px;background:var(--card);">
        <label class="lf-mini-label">New field label</label>
        <input type="text" class="lf-block__input" data-lf-new-field-label placeholder="e.g. How did you hear about us?">
        <label class="lf-mini-label" style="margin-top:8px;">Field type</label>
        <select class="lf-block__input" data-lf-new-field-type>
            <option value="text">Text</option>
            <option value="textarea">Long text</option>
            <option value="number">Number</option>
            <option value="date">Date</option>
            <option value="select">Select (dropdown)</option>
            <option value="checkbox">Switch (yes/no)</option>
        </select>
        <div data-lf-new-field-options-wrap style="margin-top:8px;" hidden>
            <label class="lf-mini-label">Options <span class="muted" style="font-weight:400;text-transform:none;">one per line</span></label>
            <textarea class="lf-block__input" data-lf-new-field-options rows="3" placeholder="Small&#10;Medium&#10;Large"></textarea>
        </div>
        <p class="muted" data-lf-new-field-error style="font-size:11px;color:#ef4444;margin:8px 0 0;" hidden></p>
        <div style="display:flex;gap:8px;margin-top:10px;">
            <button type="button" class="linkbtn" style="padding:6px 14px;font-size:12px;" data-lf-new-field-create>Add field</button>
            <button type="button" class="pcat-btn-del" style="padding:6px 14px;font-size:12px;border-color:var(--border);color:var(--muted);background:transparent;" data-lf-new-field-cancel>Cancel</button>
        </div>
    </div>
    <div class="lf-prop-field">
        <label class="lf-mini-label">Field label</label>
        <input type="text" class="lf-block__input" data-field="label" placeholder="Field label" value="{{ $block['label'] ?? '' }}">
    </div>
    <div class="lf-prop-field">
        <label class="lf-mini-label">Placeholder</label>
        <input type="text" class="lf-block__input" data-field="placeholder" placeholder="e.g. Enter your email" value="{{ $block['placeholder'] ?? '' }}">
    </div>
    <div class="lf-prop-field">
        <label class="lf-mini-label">Help text</label>
        <input type="text" class="lf-block__input" data-field="help_text" placeholder="Shown below the field (optional)" value="{{ $block['help_text'] ?? '' }}">
    </div>
    <div class="lf-prop-field">
        <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--muted);">
            <input type="checkbox" data-field="required" @checked($block['required'] ?? false)> Required
        </label>
    </div>
@elseif($type === 'divider')
    <p class="muted" style="font-size:12px;">A horizontal divider line. No settings for this block.</p>
@elseif($type === 'row')
    <p class="muted" style="font-size:12px;">A row holds one or more columns side by side. Drag a "Column" block from the left into it.</p>
@elseif($type === 'column')
    <p class="muted" style="font-size:12px;">A column holds content. Drag Heading, Text, Image, Form field, or Divider blocks into it.</p>
@endif
