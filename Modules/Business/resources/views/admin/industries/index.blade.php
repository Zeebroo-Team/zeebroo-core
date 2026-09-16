@extends('theme::layouts.app', ['title' => 'Industry Management', 'heading' => 'Industry Management'])

@section('content')
<style>
/* ── layout ── */
.ind-wrap{max-width:1200px;margin:0 auto;}
.ind-header{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:24px;flex-wrap:wrap;}
.ind-title{margin:0;font-size:19px;font-weight:800;letter-spacing:-.025em;}
.ind-sub{margin:4px 0 0;font-size:12.5px;color:var(--muted);}

/* ── flash ── */
.ind-msg{display:flex;align-items:center;gap:10px;padding:13px 16px;border-radius:12px;margin-bottom:20px;
    font-size:13px;font-weight:600;border:1px solid color-mix(in srgb,#16a34a 38%,var(--border));
    background:color-mix(in srgb,#16a34a 9%,var(--card));}
.ind-msg-err{border-color:color-mix(in srgb,#ef4444 38%,var(--border));background:color-mix(in srgb,#ef4444 9%,var(--card));color:#b91c1c;}

/* ── grid ── */
.ind-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;}
.ind-card{border:1px solid var(--border);border-radius:12px;background:var(--card);display:flex;flex-direction:column;
    padding:14px;gap:10px;transition:border-color .14s,box-shadow .14s;}
.ind-card:hover{border-color:color-mix(in srgb,var(--primary) 30%,var(--border));box-shadow:0 4px 14px rgba(0,0,0,.06);}
.ind-card-top{display:flex;align-items:center;gap:10px;}
.ind-icon{width:38px;height:38px;border-radius:10px;display:grid;place-items:center;flex-shrink:0;font-size:16px;color:#fff;}
.ind-card-name{margin:0;font-size:13.5px;font-weight:700;letter-spacing:-.01em;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.ind-status{display:inline-flex;align-items:center;padding:1.5px 7px;border-radius:999px;font-size:9.5px;font-weight:700;white-space:nowrap;flex-shrink:0;}
.ind-status--on{background:color-mix(in srgb,#22c55e 14%,transparent);color:#16a34a;}
.ind-status--off{background:color-mix(in srgb,#6b7280 16%,transparent);color:#6b7280;}
.ind-meta{display:flex;align-items:center;gap:8px;font-size:11px;color:var(--muted);}
.ind-meta code{font-size:10.5px;background:color-mix(in srgb,var(--border) 40%,transparent);padding:1px 6px;border-radius:5px;}
.ind-usage{font-size:10.5px;color:var(--muted);}
.ind-card-foot{margin-top:auto;padding-top:4px;display:flex;gap:5px;}
.ind-btn{flex:1;padding:5px 8px;border-radius:7px;border:1px solid var(--border);background:transparent;
    color:var(--text);font-size:11px;font-weight:600;cursor:pointer;font-family:inherit;transition:.14s;
    display:inline-flex;align-items:center;justify-content:center;gap:5px;}
.ind-btn:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 7%,transparent);}
.ind-btn--del:hover{border-color:color-mix(in srgb,#ef4444 45%,var(--border));background:color-mix(in srgb,#ef4444 7%,transparent);color:#b91c1c;}
.ind-add-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 15px;border-radius:9px;
    border:1px solid color-mix(in srgb,var(--btn-bg) 55%,var(--border));background:var(--btn-bg);
    color:#fff;font-size:12.5px;font-weight:700;cursor:pointer;font-family:inherit;transition:.15s ease;}
.ind-add-btn:hover{background:var(--btn-hover);color:#111827;}

/* ── empty ── */
.ind-empty{padding:52px 24px;text-align:center;border:1px solid var(--border);border-radius:16px;background:var(--card);}
.ind-empty-icon{width:52px;height:52px;border-radius:14px;margin:0 auto 14px;display:grid;place-items:center;
    font-size:22px;background:color-mix(in srgb,var(--primary) 10%,transparent);color:var(--primary);}
.ind-empty-title{margin:0 0 6px;font-size:16px;font-weight:700;}
.ind-empty-sub{margin:0;font-size:13px;color:var(--muted);}

/* ── modal ── */
.ind-modal-overlay{position:fixed;inset:0;z-index:340;display:none;align-items:flex-start;justify-content:center;padding:20px;box-sizing:border-box;overflow-y:auto;}
.ind-modal-overlay.is-open{display:flex;}
.ind-modal-backdrop{position:fixed;inset:0;background:rgba(2,6,23,.55);backdrop-filter:blur(4px);cursor:pointer;}
:is(html[data-theme="light"],html[data-theme="light_blue"]) .ind-modal-backdrop{background:rgba(17,24,39,.42);}
.ind-modal-shell{position:relative;z-index:1;width:100%;max-width:480px;margin:auto 0;border-radius:18px;border:1px solid var(--border);
    background:var(--card);box-shadow:0 24px 56px rgba(0,0,0,.28);}
.ind-modal-head{padding:20px 22px 16px;border-bottom:1px solid var(--border);display:flex;align-items:flex-start;justify-content:space-between;gap:12px;}
.ind-modal-title{margin:0 0 3px;font-size:18px;font-weight:800;letter-spacing:-.02em;}
.ind-modal-sub{margin:0;font-size:13px;color:var(--muted);}
.ind-modal-close{width:34px;height:34px;border-radius:10px;border:1px solid var(--border);background:transparent;
    color:var(--text);cursor:pointer;display:grid;place-items:center;font-size:18px;line-height:1;padding:0;font-family:inherit;flex-shrink:0;}
.ind-modal-close:hover{background:color-mix(in srgb,#ef4444 8%,transparent);border-color:color-mix(in srgb,#ef4444 35%,var(--border));}
.ind-modal-body{padding:20px 22px;max-height:70vh;overflow-y:auto;}
.ind-modal-foot{padding:14px 22px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px;}
.ind-field{margin-bottom:16px;}
.ind-field:last-child{margin-bottom:0;}
.ind-field label{display:block;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin-bottom:6px;}
.ind-field input{width:100%;box-sizing:border-box;padding:10px 13px;border-radius:11px;
    border:1px solid var(--border);background:color-mix(in srgb,var(--card) 94%,transparent);color:var(--text);font-size:14px;font-family:inherit;}
.ind-field input:focus{outline:none;border-color:color-mix(in srgb,var(--primary) 55%,var(--border));box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 14%,transparent);}
.ind-field-hint{margin:5px 0 0;font-size:11.5px;color:var(--muted);}
.ind-field-err{margin:5px 0 0;font-size:12px;font-weight:600;color:#ef4444;}
.ind-field-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.ind-icon-row{display:flex;align-items:center;gap:10px;}
.ind-icon-preview{width:42px;height:42px;border-radius:10px;display:grid;place-items:center;flex-shrink:0;font-size:17px;color:#fff;transition:background .15s;}
.ind-color-row{display:flex;align-items:center;gap:10px;}
.ind-color-swatch{width:42px;height:42px;border-radius:10px;border:1px solid var(--border);padding:0;cursor:pointer;background:none;flex-shrink:0;}
.ind-cancel-btn{padding:9px 18px;border-radius:10px;border:1px solid var(--border);background:transparent;
    color:var(--text);font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;}
.ind-cancel-btn:hover{background:color-mix(in srgb,var(--border) 40%,transparent);}
.ind-submit-btn{padding:9px 22px;border-radius:10px;border:none;background:var(--btn-bg);color:#fff;
    font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:7px;}
.ind-submit-btn:hover{background:var(--btn-hover);color:#111827;}
.ind-check-row{display:flex;align-items:center;gap:9px;padding:10px 13px;border-radius:11px;
    border:1px solid var(--border);background:color-mix(in srgb,var(--card) 94%,transparent);cursor:pointer;}
.ind-check-row input[type=checkbox]{width:15px;height:15px;accent-color:var(--primary);cursor:pointer;flex-shrink:0;}
.ind-check-row span{font-size:13.5px;color:var(--text);}

@media (max-width:640px){
  .ind-header{gap:12px;}
  .ind-add-btn{width:100%;justify-content:center;}
  .ind-field-row{grid-template-columns:1fr;gap:0;}
  .ind-modal-overlay{padding:12px;}
  .ind-modal-shell{max-width:100%;}
  .ind-modal-body{padding:16px;}
  .ind-modal-head{padding:16px 16px 12px;}
  .ind-modal-foot{padding:12px 16px;flex-wrap:wrap;}
  .ind-modal-foot button{flex:1;justify-content:center;}
}
</style>

<div class="ind-wrap">

  {{-- Header --}}
  <div class="ind-header">
    <div>
      <h1 class="ind-title"><i class="fa fa-industry" style="color:var(--primary);margin-right:8px"></i>Industry Management</h1>
      <p class="ind-sub">Manage the industries shown on the business setup wizard — name, icon, colour and order.</p>
    </div>
    <button class="ind-add-btn" id="ind-open-create">
      <i class="fa fa-plus"></i> New Industry
    </button>
  </div>

  {{-- Flash messages --}}
  @if(session('success'))
    <div class="ind-msg"><i class="fa fa-circle-check"></i> {{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="ind-msg ind-msg-err"><i class="fa fa-triangle-exclamation"></i> {{ session('error') }}</div>
  @endif
  @if($errors->any())
    <div class="ind-msg ind-msg-err"><i class="fa fa-triangle-exclamation"></i> {{ $errors->first() }}</div>
  @endif

  {{-- Industries grid --}}
  @if($industries->isEmpty())
    <div class="ind-empty">
      <div class="ind-empty-icon"><i class="fa fa-industry"></i></div>
      <h3 class="ind-empty-title">No industries yet</h3>
      <p class="ind-empty-sub">Click <strong>New Industry</strong> to add the first one.</p>
    </div>
  @else
    <div class="ind-grid">
      @foreach($industries as $industry)
      @php $usage = (int) ($usageBySlug[$industry->slug] ?? 0); @endphp
      <div class="ind-card">
        <div class="ind-card-top">
          <div class="ind-icon" style="background:{{ $industry->color }}"><i class="fa {{ $industry->icon }}"></i></div>
          <h3 class="ind-card-name" title="{{ $industry->name }}">{{ $industry->name }}</h3>
          <span class="ind-status {{ $industry->is_active ? 'ind-status--on' : 'ind-status--off' }}">
            {{ $industry->is_active ? 'Active' : 'Inactive' }}
          </span>
        </div>

        <div class="ind-meta">
          <code>{{ $industry->slug }}</code>
          <span>· sort {{ $industry->sort_order }}</span>
        </div>
        @if($usage > 0)
          <div class="ind-usage"><i class="fa fa-building" aria-hidden="true"></i> used by {{ $usage }} {{ Str::plural('business', $usage) }}</div>
        @endif

        <div class="ind-card-foot">
          <button type="button" class="ind-btn ind-edit-btn"
                  data-id="{{ $industry->id }}"
                  data-name="{{ $industry->name }}"
                  data-slug="{{ $industry->slug }}"
                  data-icon="{{ $industry->icon }}"
                  data-color="{{ $industry->color }}"
                  data-sort-order="{{ $industry->sort_order }}"
                  data-is-active="{{ $industry->is_active ? '1' : '0' }}">
            <i class="fa fa-pen"></i> Edit
          </button>
          <form method="POST" action="{{ route('admin.industries.destroy', $industry) }}"
                onsubmit="return confirm('Delete the &quot;{{ $industry->name }}&quot; industry? This cannot be undone.')" style="flex:1">
            @csrf @method('DELETE')
            <button type="submit" class="ind-btn ind-btn--del" style="width:100%">
              <i class="fa fa-trash"></i> Delete
            </button>
          </form>
        </div>
      </div>
      @endforeach
    </div>
  @endif

</div>

{{-- ── Create / Edit Industry Modal ─────────────────────────────────────── --}}
<div class="ind-modal-overlay @if($errors->any()) is-open @endif" id="ind-modal">
  <div class="ind-modal-backdrop" id="ind-modal-backdrop"></div>
  <div class="ind-modal-shell">
    <div class="ind-modal-head">
      <div>
        <h2 class="ind-modal-title" id="ind-modal-title">New Industry</h2>
        <p class="ind-modal-sub" id="ind-modal-sub">Add a new industry option to the setup wizard.</p>
      </div>
      <button class="ind-modal-close" type="button" id="ind-modal-close">
        <i class="fa fa-xmark"></i>
      </button>
    </div>

    <form method="POST" action="{{ route('admin.industries.store') }}" id="ind-form">
      @csrf
      <input type="hidden" name="_method" id="ind-form-method" value="POST">
      <div class="ind-modal-body">

        <div class="ind-field">
          <label>Name <span style="color:#ef4444">*</span></label>
          <input type="text" name="name" id="ind-f-name" value="{{ old('name') }}" placeholder="e.g. Hospitality" required maxlength="120">
          @error('name')<p class="ind-field-err">{{ $message }}</p>@enderror
          <p class="ind-field-hint" id="ind-slug-hint"></p>
        </div>

        <div class="ind-field">
          <label>Icon (Font Awesome class) <span style="color:#ef4444">*</span></label>
          <div class="ind-icon-row">
            <div class="ind-icon-preview" id="ind-icon-preview" style="background:#4e8ef7"><i class="fa fa-briefcase" id="ind-icon-preview-i"></i></div>
            <input type="text" name="icon" id="ind-f-icon" value="{{ old('icon', 'fa-briefcase') }}" placeholder="fa-store" required list="ind-icon-list">
          </div>
          <datalist id="ind-icon-list">
            <option value="fa-graduation-cap"><option value="fa-laptop-code"><option value="fa-store">
            <option value="fa-utensils"><option value="fa-heart-pulse"><option value="fa-chart-pie">
            <option value="fa-palette"><option value="fa-cart-shopping"><option value="fa-industry">
            <option value="fa-building"><option value="fa-hand-holding-heart"><option value="fa-briefcase">
            <option value="fa-wine-glass"><option value="fa-truck"><option value="fa-scissors">
            <option value="fa-dumbbell"><option value="fa-car"><option value="fa-house">
            <option value="fa-camera"><option value="fa-plane"><option value="fa-ellipsis">
          </datalist>
          <p class="ind-field-hint">Any <a href="https://fontawesome.com/search?o=r&m=free" target="_blank" rel="noopener">Font Awesome free</a> icon class, e.g. <code>fa-store</code>.</p>
          @error('icon')<p class="ind-field-err">{{ $message }}</p>@enderror
        </div>

        <div class="ind-field">
          <label>Colour <span style="color:#ef4444">*</span></label>
          <div class="ind-color-row">
            <input type="color" name="color" id="ind-f-color" value="{{ old('color', '#4e8ef7') }}" class="ind-color-swatch" required>
            <input type="text" id="ind-f-color-hex" value="{{ old('color', '#4e8ef7') }}" placeholder="#4e8ef7" maxlength="7" style="width:100%;box-sizing:border-box;padding:10px 13px;border-radius:11px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 94%,transparent);color:var(--text);font-size:14px;font-family:inherit;">
          </div>
          @error('color')<p class="ind-field-err">{{ $message }}</p>@enderror
        </div>

        <div class="ind-field-row">
          <div class="ind-field">
            <label>Sort Order</label>
            <input type="number" min="0" name="sort_order" id="ind-f-sort-order" value="{{ old('sort_order', 0) }}">
            <p class="ind-field-hint">Lower numbers appear first.</p>
          </div>
        </div>

        <div class="ind-field">
          <label class="ind-check-row" style="text-transform:none;letter-spacing:0;font-weight:normal;cursor:pointer">
            <input type="checkbox" name="is_active" id="ind-f-is-active" value="1" {{ old('is_active', 1) ? 'checked' : '' }}>
            <span><strong>Active</strong> — shown on the setup wizard</span>
          </label>
        </div>

      </div>
      <div class="ind-modal-foot">
        <button type="button" class="ind-cancel-btn" id="ind-modal-cancel">Cancel</button>
        <button type="submit" class="ind-submit-btn" id="ind-submit-btn">
          <i class="fa fa-industry" id="ind-submit-icon"></i> <span id="ind-submit-label">Create Industry</span>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  var modal      = document.getElementById('ind-modal');
  var backdrop   = document.getElementById('ind-modal-backdrop');
  var closeBtn   = document.getElementById('ind-modal-close');
  var cancelBtn  = document.getElementById('ind-modal-cancel');
  var openBtn    = document.getElementById('ind-open-create');
  var form       = document.getElementById('ind-form');
  var methodEl   = document.getElementById('ind-form-method');
  var title      = document.getElementById('ind-modal-title');
  var sub        = document.getElementById('ind-modal-sub');
  var submitLabel= document.getElementById('ind-submit-label');
  var submitIcon = document.getElementById('ind-submit-icon');

  var nameEl       = document.getElementById('ind-f-name');
  var slugHint     = document.getElementById('ind-slug-hint');
  var iconEl       = document.getElementById('ind-f-icon');
  var iconPreview  = document.getElementById('ind-icon-preview');
  var iconPreviewI = document.getElementById('ind-icon-preview-i');
  var colorEl      = document.getElementById('ind-f-color');
  var colorHexEl   = document.getElementById('ind-f-color-hex');
  var sortOrderEl  = document.getElementById('ind-f-sort-order');
  var isActiveEl   = document.getElementById('ind-f-is-active');

  function slugify(name) {
    return name.toLowerCase().trim().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '') || 'industry';
  }

  function updateIconPreview() {
    var cls = (iconEl.value || 'fa-briefcase').trim();
    iconPreviewI.className = 'fa ' + cls;
  }
  function updateColorPreview() {
    var color = colorEl.value || '#4e8ef7';
    iconPreview.style.background = color;
  }

  function openModal() { modal.classList.add('is-open'); modal.scrollTop = 0; document.body.style.overflow = 'hidden'; }
  function closeModal() { modal.classList.remove('is-open'); document.body.style.overflow = ''; }

  function setCreateMode() {
    methodEl.value = 'POST';
    form.action = '{{ route('admin.industries.store') }}';
    title.textContent = 'New Industry';
    sub.textContent = 'Add a new industry option to the setup wizard.';
    submitLabel.textContent = 'Create Industry';
    submitIcon.className = 'fa fa-industry';

    nameEl.value = '';
    iconEl.value = 'fa-briefcase';
    colorEl.value = '#4e8ef7';
    colorHexEl.value = '#4e8ef7';
    sortOrderEl.value = 0;
    isActiveEl.checked = true;
    slugHint.textContent = '';
    updateIconPreview();
    updateColorPreview();
  }

  nameEl.addEventListener('input', function () {
    slugHint.textContent = form.action.indexOf('/industries/') === -1 || methodEl.value === 'POST'
      ? 'Slug: ' + slugify(nameEl.value)
      : '';
  });
  iconEl.addEventListener('input', updateIconPreview);
  colorEl.addEventListener('input', function () { colorHexEl.value = colorEl.value; updateColorPreview(); });
  colorHexEl.addEventListener('input', function () {
    if (/^#[0-9a-fA-F]{6}$/.test(colorHexEl.value)) { colorEl.value = colorHexEl.value; updateColorPreview(); }
  });

  openBtn.addEventListener('click', function () { setCreateMode(); openModal(); });
  closeBtn.addEventListener('click', closeModal);
  cancelBtn.addEventListener('click', closeModal);
  backdrop.addEventListener('click', closeModal);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
  });

  document.querySelectorAll('.ind-edit-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id = btn.getAttribute('data-id');

      methodEl.value = 'PUT';
      form.action = '{{ url('/admin/industries') }}/' + id;
      title.textContent = 'Edit Industry';
      sub.textContent = 'Update "' + btn.getAttribute('data-name') + '".';
      submitLabel.textContent = 'Save Changes';
      submitIcon.className = 'fa fa-floppy-disk';

      nameEl.value = btn.getAttribute('data-name');
      iconEl.value = btn.getAttribute('data-icon');
      colorEl.value = btn.getAttribute('data-color');
      colorHexEl.value = btn.getAttribute('data-color');
      sortOrderEl.value = btn.getAttribute('data-sort-order') || 0;
      isActiveEl.checked = btn.getAttribute('data-is-active') === '1';
      slugHint.textContent = 'Slug: ' + btn.getAttribute('data-slug') + ' (fixed)';
      updateIconPreview();
      updateColorPreview();

      openModal();
    });
  });

  updateIconPreview();
  updateColorPreview();
})();
</script>
@endsection
