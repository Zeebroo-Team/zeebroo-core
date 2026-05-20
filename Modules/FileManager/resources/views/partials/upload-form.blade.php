@php
    $folderId = $currentFolder?->id;
    $formId = ($fieldIdPrefix ?? '').'fm-upload-form';
    $fileInputId = ($fieldIdPrefix ?? '').'fm-upload-files';
@endphp
<form id="{{ $formId }}" method="post" action="{{ route('filemanager.files.store') }}" enctype="multipart/form-data" class="fm-form-grid">
    @csrf
    @if($folderId)
        <input type="hidden" name="folder_id" value="{{ $folderId }}">
    @endif
    <div class="fm-field" style="grid-column:1/-1;">
        <label for="{{ $fileInputId }}">Files</label>
        <label class="fm-upload-zone" id="{{ $fileInputId }}-zone" for="{{ $fileInputId }}">
            <i class="fa fa-cloud-arrow-up fm-card__icon" style="font-size:24px;" aria-hidden="true"></i>
            <span class="fm-upload-zone__title">Choose files or drag here</span>
            <span class="fm-upload-zone__hint">PDF, images, Office docs, archives — up to 20 MB each</span>
            <input type="file" id="{{ $fileInputId }}" name="files[]" multiple required accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.svg,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.rar,.7z">
        </label>
        <p class="fm-file-list" id="{{ $fileInputId }}-list" hidden></p>
        @error('files')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
        @error('files.*')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>
    <div class="fm-field" style="grid-column:1/-1;">
        <label for="{{ ($fieldIdPrefix ?? '').'fm-upload-notes' }}">Notes <span class="muted" style="font-weight:600;text-transform:none;letter-spacing:0;">(optional)</span></label>
        <textarea id="{{ ($fieldIdPrefix ?? '').'fm-upload-notes' }}" name="notes" maxlength="2000" placeholder="Shared with these uploads…">{{ old('notes') }}</textarea>
        @error('notes')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>
    <div style="grid-column:1/-1;display:flex;justify-content:flex-end;">
        <button type="submit" class="linkbtn" style="padding:8px 16px;font-size:13px;"><i class="fa fa-upload"></i> Upload</button>
    </div>
</form>
