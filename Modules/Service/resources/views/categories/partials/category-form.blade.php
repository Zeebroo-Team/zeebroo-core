<form method="post"
      action="{{ isset($category) ? route('service.categories.update', $category) : route('service.categories.store') }}"
      style="display:flex;flex-direction:column;gap:12px;">
    @csrf
    @if(isset($category)) @method('PUT') @endif

    <div>
        <label style="display:block;font-size:12px;font-weight:700;margin-bottom:4px;">Name <span style="color:#ef4444;">*</span></label>
        <input id="scat-name" type="text" name="name" value="{{ old('name', $category->name ?? '') }}"
               placeholder="e.g. Cleaning, Repair, Consultation"
               style="width:100%;box-sizing:border-box;padding:9px 11px;border-radius:8px;border:1px solid var(--border);background:var(--bg);color:var(--text);font-size:13px;"
               required maxlength="255" autocomplete="off">
        @error('name')<p style="margin:4px 0 0;font-size:12px;color:#ef4444;">{{ $message }}</p>@enderror
    </div>

    <div>
        <label style="display:block;font-size:12px;font-weight:700;margin-bottom:4px;">Description</label>
        <textarea name="description" placeholder="Optional description…" rows="3"
                  style="width:100%;box-sizing:border-box;padding:9px 11px;border-radius:8px;border:1px solid var(--border);background:var(--bg);color:var(--text);font-size:13px;resize:vertical;">{{ old('description', $category->description ?? '') }}</textarea>
        @error('description')<p style="margin:4px 0 0;font-size:12px;color:#ef4444;">{{ $message }}</p>@enderror
    </div>

    <div style="display:flex;align-items:center;gap:10px;">
        <label style="display:flex;align-items:center;gap:7px;font-size:13px;cursor:pointer;">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1"
                   {{ old('is_active', ($category->is_active ?? true) ? '1' : '0') ? 'checked' : '' }}
                   style="width:15px;height:15px;accent-color:var(--primary);">
            Active
        </label>
    </div>

    <div style="display:flex;gap:8px;margin-top:4px;">
        <button type="submit" class="linkbtn" style="padding:9px 20px;font-size:13px;">
            {{ isset($category) ? 'Update Category' : 'Add Category' }}
        </button>
        @if(isset($category))
            <a href="{{ route('service.categories.index') }}" class="linkbtn"
               style="padding:9px 16px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">
                Cancel
            </a>
        @endif
    </div>
</form>
