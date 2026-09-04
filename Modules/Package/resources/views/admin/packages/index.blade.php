@extends('theme::layouts.app', ['title' => 'Package Management', 'heading' => 'Package Management'])

@section('content')
<style>
/* ── layout ── */
.pkg-wrap{max-width:1200px;margin:0 auto;}
.pkg-header{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:24px;flex-wrap:wrap;}
.pkg-title{margin:0;font-size:19px;font-weight:800;letter-spacing:-.025em;}
.pkg-sub{margin:4px 0 0;font-size:12.5px;color:var(--muted);}

/* ── flash ── */
.pkg-msg{display:flex;align-items:center;gap:10px;padding:13px 16px;border-radius:12px;margin-bottom:20px;
    font-size:13px;font-weight:600;border:1px solid color-mix(in srgb,#16a34a 38%,var(--border));
    background:color-mix(in srgb,#16a34a 9%,var(--card));}
.pkg-msg-err{border-color:color-mix(in srgb,#ef4444 38%,var(--border));background:color-mix(in srgb,#ef4444 9%,var(--card));color:#b91c1c;}

/* ── grid ── */
.pkg-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;}
.pkg-card{border:1px solid var(--border);border-radius:12px;overflow:hidden;background:var(--card);display:flex;flex-direction:column;transition:border-color .14s,box-shadow .14s;}
.pkg-card:hover{border-color:color-mix(in srgb,var(--primary) 30%,var(--border));box-shadow:0 4px 14px rgba(0,0,0,.06);}
.pkg-card-img{width:100%;height:76px;overflow:hidden;flex-shrink:0;}
.pkg-card-img img{width:100%;height:100%;object-fit:cover;}
.pkg-card-img-default{width:100%;height:100%;display:grid;place-items:center;font-size:20px;color:var(--primary);
    background:linear-gradient(135deg,color-mix(in srgb,var(--primary) 16%,transparent),color-mix(in srgb,var(--primary) 5%,transparent));}
.pkg-card-body{padding:12px 13px;display:flex;flex-direction:column;gap:6px;flex:1;}
.pkg-card-top{display:flex;align-items:center;gap:8px;}
.pkg-card-name{margin:0;font-size:13.5px;font-weight:700;letter-spacing:-.01em;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.pkg-status{display:inline-flex;align-items:center;padding:1.5px 7px;border-radius:999px;font-size:9.5px;font-weight:700;white-space:nowrap;flex-shrink:0;}
.pkg-status--on{background:color-mix(in srgb,#22c55e 14%,transparent);color:#16a34a;}
.pkg-status--off{background:color-mix(in srgb,#6b7280 16%,transparent);color:#6b7280;}
.pkg-card-desc{margin:0;font-size:11.5px;color:var(--muted);line-height:1.45;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
.pkg-price-row{display:flex;align-items:baseline;gap:6px;}
.pkg-price{font-size:16px;font-weight:800;letter-spacing:-.02em;}
.pkg-price-strike{font-size:11.5px;color:var(--muted);text-decoration:line-through;}
.pkg-free-badge{display:inline-flex;align-items:center;padding:2px 8px;border-radius:999px;font-size:10px;font-weight:700;
    background:color-mix(in srgb,#3b82f6 14%,transparent);color:#2563eb;}
.pkg-feat-list{display:flex;flex-wrap:wrap;gap:4px;}
.pkg-feat-chip{font-size:9.5px;font-weight:600;padding:1.5px 7px;border-radius:999px;border:1px solid var(--border);color:var(--muted);}
.pkg-feat-more{font-size:9.5px;color:var(--muted);font-style:italic;padding:1.5px 3px;}
.pkg-card-foot{margin-top:auto;padding-top:8px;display:flex;gap:5px;}
.pkg-btn{flex:1;padding:5px 8px;border-radius:7px;border:1px solid var(--border);background:transparent;
    color:var(--text);font-size:11px;font-weight:600;cursor:pointer;font-family:inherit;transition:.14s;
    display:inline-flex;align-items:center;justify-content:center;gap:5px;}
.pkg-btn:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 7%,transparent);}
.pkg-btn--del:hover{border-color:color-mix(in srgb,#ef4444 45%,var(--border));background:color-mix(in srgb,#ef4444 7%,transparent);color:#b91c1c;}
.pkg-add-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 15px;border-radius:9px;
    border:1px solid color-mix(in srgb,var(--btn-bg) 55%,var(--border));background:var(--btn-bg);
    color:#fff;font-size:12.5px;font-weight:700;cursor:pointer;font-family:inherit;transition:.15s ease;}
.pkg-add-btn:hover{background:var(--btn-hover);color:#111827;}

/* ── empty ── */
.pkg-empty{padding:52px 24px;text-align:center;border:1px solid var(--border);border-radius:16px;background:var(--card);}
.pkg-empty-icon{width:52px;height:52px;border-radius:14px;margin:0 auto 14px;display:grid;place-items:center;
    font-size:22px;background:color-mix(in srgb,var(--primary) 10%,transparent);color:var(--primary);}
.pkg-empty-title{margin:0 0 6px;font-size:16px;font-weight:700;}
.pkg-empty-sub{margin:0;font-size:13px;color:var(--muted);}

/* ── modal ── */
.pkg-modal-overlay{position:fixed;inset:0;z-index:340;display:none;align-items:flex-start;justify-content:center;padding:20px;box-sizing:border-box;overflow-y:auto;}
.pkg-modal-overlay.is-open{display:flex;}
.pkg-modal-backdrop{position:fixed;inset:0;background:rgba(2,6,23,.55);backdrop-filter:blur(4px);cursor:pointer;}
:is(html[data-theme="light"],html[data-theme="light_blue"]) .pkg-modal-backdrop{background:rgba(17,24,39,.42);}
.pkg-modal-shell{position:relative;z-index:1;width:100%;max-width:560px;margin:auto 0;border-radius:18px;border:1px solid var(--border);
    background:var(--card);box-shadow:0 24px 56px rgba(0,0,0,.28);}
.pkg-modal-head{padding:20px 22px 16px;border-bottom:1px solid var(--border);display:flex;align-items:flex-start;justify-content:space-between;gap:12px;}
.pkg-modal-title{margin:0 0 3px;font-size:18px;font-weight:800;letter-spacing:-.02em;}
.pkg-modal-sub{margin:0;font-size:13px;color:var(--muted);}
.pkg-modal-close{width:34px;height:34px;border-radius:10px;border:1px solid var(--border);background:transparent;
    color:var(--text);cursor:pointer;display:grid;place-items:center;font-size:18px;line-height:1;padding:0;font-family:inherit;flex-shrink:0;}
.pkg-modal-close:hover{background:color-mix(in srgb,#ef4444 8%,transparent);border-color:color-mix(in srgb,#ef4444 35%,var(--border));}
.pkg-modal-body{padding:20px 22px;max-height:70vh;overflow-y:auto;}
.pkg-modal-foot{padding:14px 22px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px;}
.pkg-field{margin-bottom:16px;}
.pkg-field:last-child{margin-bottom:0;}
.pkg-field label{display:block;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin-bottom:6px;}
.pkg-field input,.pkg-field select,.pkg-field textarea{width:100%;box-sizing:border-box;padding:10px 13px;border-radius:11px;
    border:1px solid var(--border);background:color-mix(in srgb,var(--card) 94%,transparent);color:var(--text);font-size:14px;font-family:inherit;}
.pkg-field input:focus,.pkg-field select:focus,.pkg-field textarea:focus{outline:none;
    border-color:color-mix(in srgb,var(--primary) 55%,var(--border));box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 14%,transparent);}
.pkg-field textarea{resize:vertical;min-height:64px;}
.pkg-field-hint{margin:5px 0 0;font-size:11.5px;color:var(--muted);}
.pkg-field-err{margin:5px 0 0;font-size:12px;font-weight:600;color:#ef4444;}
.pkg-field-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.pkg-cancel-btn{padding:9px 18px;border-radius:10px;border:1px solid var(--border);background:transparent;
    color:var(--text);font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;}
.pkg-cancel-btn:hover{background:color-mix(in srgb,var(--border) 40%,transparent);}
.pkg-submit-btn{padding:9px 22px;border-radius:10px;border:none;background:var(--btn-bg);color:#fff;
    font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:7px;}
.pkg-submit-btn:hover{background:var(--btn-hover);color:#111827;}
.pkg-check-row{display:flex;align-items:center;gap:9px;padding:10px 13px;border-radius:11px;
    border:1px solid var(--border);background:color-mix(in srgb,var(--card) 94%,transparent);cursor:pointer;}
.pkg-check-row input[type=checkbox]{width:15px;height:15px;accent-color:var(--primary);cursor:pointer;flex-shrink:0;}
.pkg-check-row span{font-size:13.5px;color:var(--text);}
.pkg-feat-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;}
.pkg-feat-check{display:flex;align-items:center;gap:8px;padding:8px 11px;border-radius:10px;
    border:1px solid var(--border);background:color-mix(in srgb,var(--card) 94%,transparent);cursor:pointer;}
.pkg-feat-check input[type=checkbox]{width:14px;height:14px;accent-color:var(--primary);cursor:pointer;flex-shrink:0;}
.pkg-feat-check span{font-size:12.5px;color:var(--text);}
.pkg-img-preview{width:100%;height:110px;border-radius:11px;border:1px dashed var(--border);margin-top:8px;
    display:flex;align-items:center;justify-content:center;overflow:hidden;background:color-mix(in srgb,var(--card) 94%,transparent);}
.pkg-img-preview img{width:100%;height:100%;object-fit:cover;}
.pkg-img-preview span{font-size:11.5px;color:var(--muted);}

/* ── responsive ── */
@media (max-width:640px){
  .pkg-header{gap:12px;}
  .pkg-add-btn{width:100%;justify-content:center;}
  .pkg-field-row{grid-template-columns:1fr;gap:0;}
  .pkg-feat-grid{grid-template-columns:1fr;}
  .pkg-modal-overlay{padding:12px;}
  .pkg-modal-shell{max-width:100%;}
  .pkg-modal-body{padding:16px;}
  .pkg-modal-head{padding:16px 16px 12px;}
  .pkg-modal-foot{padding:12px 16px;flex-wrap:wrap;}
  .pkg-modal-foot button{flex:1;justify-content:center;}
}
</style>

<div class="pkg-wrap">

  {{-- Header --}}
  <div class="pkg-header">
    <div>
      <h1 class="pkg-title"><i class="fa fa-box-open" style="color:var(--primary);margin-right:8px"></i>Package Management</h1>
      <p class="pkg-sub">Create pricing packages and choose which features each one unlocks.</p>
    </div>
    <button class="pkg-add-btn" id="pkg-open-create">
      <i class="fa fa-plus"></i> New Package
    </button>
  </div>

  {{-- Flash messages --}}
  @if(session('success'))
    <div class="pkg-msg"><i class="fa fa-circle-check"></i> {{ session('success') }}</div>
  @endif
  @if($errors->any())
    <div class="pkg-msg pkg-msg-err"><i class="fa fa-triangle-exclamation"></i> {{ $errors->first() }}</div>
  @endif

  {{-- Packages grid --}}
  @if($packages->isEmpty())
    <div class="pkg-empty">
      <div class="pkg-empty-icon"><i class="fa fa-box-open"></i></div>
      <h3 class="pkg-empty-title">No packages yet</h3>
      <p class="pkg-empty-sub">Click <strong>New Package</strong> to create your first pricing tier.</p>
    </div>
  @else
    <div class="pkg-grid">
      @foreach($packages as $package)
      <div class="pkg-card">
        <div class="pkg-card-img">
          @if($package->image)
            <img src="{{ asset('storage/' . $package->image) }}" alt="{{ $package->name }}">
          @else
            <div class="pkg-card-img-default"><i class="fa fa-box-open"></i></div>
          @endif
        </div>
        <div class="pkg-card-body">
          <div class="pkg-card-top">
            <h3 class="pkg-card-name" title="{{ $package->name }}">{{ $package->name }}</h3>
            <span class="pkg-status {{ $package->is_active ? 'pkg-status--on' : 'pkg-status--off' }}">
              {{ $package->is_active ? 'Active' : 'Inactive' }}
            </span>
          </div>

          @if($package->description)
            <p class="pkg-card-desc">{{ $package->description }}</p>
          @endif

          <div class="pkg-price-row">
            @if($package->is_free)
              <span class="pkg-free-badge"><i class="fa fa-gift" style="font-size:10px"></i> Free</span>
            @else
              @if($package->discounted_price !== null)
                <span class="pkg-price">${{ number_format((float) $package->discounted_price, 2) }}</span>
                <span class="pkg-price-strike">${{ number_format((float) $package->price, 2) }}</span>
              @else
                <span class="pkg-price">${{ number_format((float) $package->price, 2) }}</span>
              @endif
            @endif
          </div>

          @php $featLabels = $package->featureLabels(); @endphp
          @if(!empty($featLabels))
            <div class="pkg-feat-list">
              @foreach(array_slice($featLabels, 0, 3) as $label)
                <span class="pkg-feat-chip">{{ $label }}</span>
              @endforeach
              @if(count($featLabels) > 3)
                <span class="pkg-feat-more">+{{ count($featLabels) - 3 }}</span>
              @endif
            </div>
          @endif

          <div class="pkg-card-foot">
            <button type="button" class="pkg-btn pkg-edit-btn"
                    data-id="{{ $package->id }}"
                    data-name="{{ $package->name }}"
                    data-description="{{ $package->description }}"
                    data-price="{{ $package->price }}"
                    data-discounted-price="{{ $package->discounted_price }}"
                    data-is-free="{{ $package->is_free ? '1' : '0' }}"
                    data-is-active="{{ $package->is_active ? '1' : '0' }}"
                    data-sort-order="{{ $package->sort_order }}"
                    data-image="{{ $package->image ? asset('storage/' . $package->image) : '' }}"
                    data-features="{{ json_encode($package->features ?? []) }}">
              <i class="fa fa-pen"></i> Edit
            </button>
            <form method="POST" action="{{ route('admin.packages.destroy', $package) }}"
                  onsubmit="return confirm('Delete the &quot;{{ $package->name }}&quot; package? This cannot be undone.')" style="flex:1">
              @csrf @method('DELETE')
              <button type="submit" class="pkg-btn pkg-btn--del" style="width:100%">
                <i class="fa fa-trash"></i> Delete
              </button>
            </form>
          </div>
        </div>
      </div>
      @endforeach
    </div>
  @endif

</div>

{{-- ── Create / Edit Package Modal ─────────────────────────────────────── --}}
<div class="pkg-modal-overlay @if($errors->any()) is-open @endif" id="pkg-modal">
  <div class="pkg-modal-backdrop" id="pkg-modal-backdrop"></div>
  <div class="pkg-modal-shell">
    <div class="pkg-modal-head">
      <div>
        <h2 class="pkg-modal-title" id="pkg-modal-title">New Package</h2>
        <p class="pkg-modal-sub" id="pkg-modal-sub">Create a new pricing package.</p>
      </div>
      <button class="pkg-modal-close" type="button" id="pkg-modal-close">
        <i class="fa fa-xmark"></i>
      </button>
    </div>

    <form method="POST" action="{{ route('admin.packages.store') }}" id="pkg-form" enctype="multipart/form-data">
      @csrf
      <input type="hidden" name="_method" id="pkg-form-method" value="POST">
      <div class="pkg-modal-body">

        <div class="pkg-field">
          <label>Name <span style="color:#ef4444">*</span></label>
          <input type="text" name="name" id="pkg-f-name" value="{{ old('name') }}" placeholder="e.g. Silver" required>
          @error('name')<p class="pkg-field-err">{{ $message }}</p>@enderror
        </div>

        <div class="pkg-field">
          <label>Description</label>
          <textarea name="description" id="pkg-f-description" rows="3" placeholder="What's included in this package…">{{ old('description') }}</textarea>
          @error('description')<p class="pkg-field-err">{{ $message }}</p>@enderror
        </div>

        <div class="pkg-field">
          <label>Image</label>
          <input type="file" name="image" id="pkg-f-image" accept="image/*">
          <div class="pkg-img-preview" id="pkg-img-preview">
            <span>No image selected</span>
          </div>
          @error('image')<p class="pkg-field-err">{{ $message }}</p>@enderror
        </div>

        <div class="pkg-field">
          <label class="pkg-check-row" style="text-transform:none;letter-spacing:0;font-weight:normal;cursor:pointer">
            <input type="checkbox" name="is_free" id="pkg-f-is-free" value="1" {{ old('is_free') ? 'checked' : '' }}>
            <span><strong>This package is free</strong> — price fields will be ignored</span>
          </label>
        </div>

        <div class="pkg-field-row" id="pkg-price-fields">
          <div class="pkg-field">
            <label>Price ($) <span style="color:#ef4444">*</span></label>
            <input type="number" step="0.01" min="0" name="price" id="pkg-f-price" value="{{ old('price', 0) }}" required>
            @error('price')<p class="pkg-field-err">{{ $message }}</p>@enderror
          </div>
          <div class="pkg-field">
            <label>Discounted Price ($)</label>
            <input type="number" step="0.01" min="0" name="discounted_price" id="pkg-f-discounted-price" value="{{ old('discounted_price') }}">
            @error('discounted_price')<p class="pkg-field-err">{{ $message }}</p>@enderror
          </div>
        </div>

        <div class="pkg-field">
          <label>Sort Order</label>
          <input type="number" min="0" name="sort_order" id="pkg-f-sort-order" value="{{ old('sort_order', 0) }}">
          <p class="pkg-field-hint">Lower numbers appear first.</p>
        </div>

        <div class="pkg-field">
          <label class="pkg-check-row" style="text-transform:none;letter-spacing:0;font-weight:normal;cursor:pointer">
            <input type="checkbox" name="is_active" id="pkg-f-is-active" value="1" {{ old('is_active', 1) ? 'checked' : '' }}>
            <span><strong>Active</strong> — visible and purchasable</span>
          </label>
        </div>

        <div class="pkg-field">
          <label>Features</label>
          <div class="pkg-feat-grid">
            @foreach($features as $key => $label)
              <label class="pkg-feat-check">
                <input type="checkbox" name="features[]" value="{{ $key }}" class="pkg-f-feature"
                       {{ collect(old('features', []))->contains($key) ? 'checked' : '' }}>
                <span>{{ $label }}</span>
              </label>
            @endforeach
          </div>
          @error('features')<p class="pkg-field-err">{{ $message }}</p>@enderror
        </div>

      </div>
      <div class="pkg-modal-foot">
        <button type="button" class="pkg-cancel-btn" id="pkg-modal-cancel">Cancel</button>
        <button type="submit" class="pkg-submit-btn" id="pkg-submit-btn">
          <i class="fa fa-box-open" id="pkg-submit-icon"></i> <span id="pkg-submit-label">Create Package</span>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  var modal      = document.getElementById('pkg-modal');
  var backdrop   = document.getElementById('pkg-modal-backdrop');
  var closeBtn   = document.getElementById('pkg-modal-close');
  var cancelBtn  = document.getElementById('pkg-modal-cancel');
  var openBtn    = document.getElementById('pkg-open-create');
  var form       = document.getElementById('pkg-form');
  var methodEl   = document.getElementById('pkg-form-method');
  var title      = document.getElementById('pkg-modal-title');
  var sub        = document.getElementById('pkg-modal-sub');
  var submitLabel= document.getElementById('pkg-submit-label');
  var submitIcon = document.getElementById('pkg-submit-icon');

  var nameEl       = document.getElementById('pkg-f-name');
  var descEl       = document.getElementById('pkg-f-description');
  var imageEl      = document.getElementById('pkg-f-image');
  var imgPreview   = document.getElementById('pkg-img-preview');
  var isFreeEl     = document.getElementById('pkg-f-is-free');
  var priceFields  = document.getElementById('pkg-price-fields');
  var priceEl      = document.getElementById('pkg-f-price');
  var discPriceEl  = document.getElementById('pkg-f-discounted-price');
  var sortOrderEl  = document.getElementById('pkg-f-sort-order');
  var isActiveEl   = document.getElementById('pkg-f-is-active');
  var featureBoxes = document.querySelectorAll('.pkg-f-feature');

  function openModal() { modal.classList.add('is-open'); modal.scrollTop = 0; document.body.style.overflow = 'hidden'; }
  function closeModal() { modal.classList.remove('is-open'); document.body.style.overflow = ''; }

  function setPreview(url) {
    if (url) {
      imgPreview.innerHTML = '<img src="' + url + '" alt="">';
    } else {
      imgPreview.innerHTML = '<span>No image selected</span>';
    }
  }

  function togglePriceFields() {
    priceFields.style.display = isFreeEl.checked ? 'none' : '';
  }

  function setCreateMode() {
    methodEl.value = 'POST';
    form.action = '{{ route('admin.packages.store') }}';
    title.textContent = 'New Package';
    sub.textContent = 'Create a new pricing package.';
    submitLabel.textContent = 'Create Package';
    submitIcon.className = 'fa fa-box-open';

    nameEl.value = '';
    descEl.value = '';
    imageEl.value = '';
    setPreview(null);
    isFreeEl.checked = false;
    priceEl.value = 0;
    discPriceEl.value = '';
    sortOrderEl.value = 0;
    isActiveEl.checked = true;
    featureBoxes.forEach(function (cb) { cb.checked = false; });
    togglePriceFields();
  }

  isFreeEl.addEventListener('change', togglePriceFields);
  imageEl.addEventListener('change', function () {
    if (imageEl.files && imageEl.files[0]) {
      setPreview(URL.createObjectURL(imageEl.files[0]));
    }
  });

  openBtn.addEventListener('click', function () { setCreateMode(); openModal(); });
  closeBtn.addEventListener('click', closeModal);
  cancelBtn.addEventListener('click', closeModal);
  backdrop.addEventListener('click', closeModal);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
  });

  document.querySelectorAll('.pkg-edit-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id = btn.getAttribute('data-id');

      methodEl.value = 'PUT';
      form.action = '{{ url('/admin/packages') }}/' + id;
      title.textContent = 'Edit Package';
      sub.textContent = 'Update "' + btn.getAttribute('data-name') + '".';
      submitLabel.textContent = 'Save Changes';
      submitIcon.className = 'fa fa-floppy-disk';

      nameEl.value = btn.getAttribute('data-name');
      descEl.value = btn.getAttribute('data-description') || '';
      imageEl.value = '';
      setPreview(btn.getAttribute('data-image') || null);
      isFreeEl.checked = btn.getAttribute('data-is-free') === '1';
      priceEl.value = btn.getAttribute('data-price');
      discPriceEl.value = btn.getAttribute('data-discounted-price') || '';
      sortOrderEl.value = btn.getAttribute('data-sort-order') || 0;
      isActiveEl.checked = btn.getAttribute('data-is-active') === '1';
      togglePriceFields();

      var selected = [];
      try { selected = JSON.parse(btn.getAttribute('data-features') || '[]'); } catch (e) {}
      featureBoxes.forEach(function (cb) { cb.checked = selected.indexOf(cb.value) !== -1; });

      openModal();
    });
  });
})();
</script>
@endsection
