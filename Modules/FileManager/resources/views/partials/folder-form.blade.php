@php
    $parentId = $currentFolder?->id;
@endphp
<form method="post" action="{{ route('filemanager.folders.store') }}" class="fm-form-grid">
    @csrf
    @if($parentId)
        <input type="hidden" name="parent_id" value="{{ $parentId }}">
    @endif
    <div class="fm-field" style="grid-column:1/-1;">
        <label for="{{ ($fieldIdPrefix ?? '').'fm-folder-name' }}">Folder name</label>
        <input type="text" id="{{ ($fieldIdPrefix ?? '').'fm-folder-name' }}" name="name" value="{{ old('name') }}" maxlength="120" required placeholder="e.g. Contracts">
        @error('name')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>
    <div style="grid-column:1/-1;display:flex;justify-content:flex-end;">
        <button type="submit" class="linkbtn" style="padding:8px 14px;font-size:13px;">Create folder</button>
    </div>
</form>
