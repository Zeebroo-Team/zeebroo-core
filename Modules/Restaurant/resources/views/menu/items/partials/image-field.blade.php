@php
  $fieldKey   = $fieldKey ?? 'mi';
  $rootId     = 'mi-img-' . $fieldKey;
  $hasImage   = !empty($fileId) && !empty($fileUrl);
  $geminiAvail= filled(config('aibot.gemini.api_key'));
@endphp

<div id="{{ $rootId }}-wrap"
     data-product-image-root
     data-prefix="{{ $rootId }}-"
     data-multiple="0"
     data-max-images="1"
     data-gemini-available="{{ $geminiAvail ? '1' : '0' }}"
     style="margin-top:2px;">

  {{-- Hidden inputs --}}
  <input type="hidden" name="file_manager_file_id" id="{{ $rootId }}-file-id"
         value="{{ $hasImage ? $fileId : '' }}" data-product-image-file-id>
  <input type="hidden" name="remove_product_images" id="{{ $rootId }}-remove-flag"
         value="0" data-product-image-remove>
  <div data-product-image-ids hidden>
    @if($hasImage)
      <input type="hidden" name="file_manager_file_ids[]" value="{{ $fileId }}" data-product-image-id-input>
    @endif
  </div>

  <div class="product-image-field__panel">

    {{-- Required by renderGallery() to avoid early-return; always hidden in single-image mode --}}
    <div class="product-image-field__gallery" id="{{ $rootId }}-gallery"
         data-product-image-gallery hidden style="display:none!important;"></div>

    <div class="product-image-field__preview" id="{{ $rootId }}-preview"
         data-product-image-preview @if(!$hasImage) hidden @endif>
      <img @if($hasImage) src="{{ $fileUrl }}" @endif alt=""
           id="{{ $rootId }}-preview-img" data-product-image-preview-img
           style="width:72px;height:72px;">
      <div class="product-image-field__preview-meta">
        <span id="{{ $rootId }}-preview-name" data-product-image-preview-name>{{ $fileName ?? '' }}</span>
      </div>
    </div>

    <div class="product-image-field__placeholder" id="{{ $rootId }}-placeholder"
         data-product-image-placeholder @if($hasImage) hidden @endif>
      <i class="fa fa-image" aria-hidden="true"></i>
      <span>No image selected</span>
    </div>

    <div class="product-image-field__actions">
      <button type="button" class="linkbtn product-image-field__btn"
              style="padding:8px 14px;font-size:13px;display:inline-flex;align-items:center;gap:6px;"
              data-product-image-pick-open>
        <i class="fa fa-images"></i> Choose image
      </button>
      <button type="button" class="linkbtn product-image-field__btn product-image-field__btn--muted"
              style="padding:8px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--muted);"
              data-product-image-clear @if(!$hasImage) hidden @endif>
        Remove
      </button>
    </div>
    <p class="product-image-field__hint">Upload, pick from file manager, or generate with AI.</p>
  </div>

  {{-- Picker modal --}}
  <div id="{{ $rootId }}-picker-modal" class="product-image-picker"
       data-product-image-picker hidden role="dialog" aria-modal="true"
       aria-labelledby="{{ $rootId }}-picker-title">
    <div class="product-image-picker__backdrop" data-product-image-picker-close tabindex="-1"></div>
    <div class="product-image-picker__panel">
      <div class="product-image-picker__head">
        <h3 id="{{ $rootId }}-picker-title">Choose image</h3>
        <button type="button" class="product-image-picker__close"
                data-product-image-picker-close aria-label="Close">&times;</button>
      </div>

      <div class="product-image-picker__tabs" role="tablist" aria-label="Image source">
        <button type="button" class="product-image-picker__tab is-active" role="tab"
                aria-selected="true" data-product-image-tab="upload"
                id="{{ $rootId }}-tab-upload">Upload</button>
        <button type="button" class="product-image-picker__tab" role="tab"
                aria-selected="false" data-product-image-tab="files"
                id="{{ $rootId }}-tab-files">File manager</button>
        <button type="button" class="product-image-picker__tab" role="tab"
                aria-selected="false" data-product-image-tab="generate"
                id="{{ $rootId }}-tab-generate">Generate</button>
      </div>

      <div class="product-image-picker__body">
        <div class="product-image-picker__panel-pane product-image-picker__panel-pane--upload is-active"
             data-product-image-tab-panel="upload" role="tabpanel"
             aria-labelledby="{{ $rootId }}-tab-upload">
          <div class="product-image-picker__upload-wrap">
            <label class="product-image-picker__upload-zone"
                   data-product-image-modal-upload-zone
                   for="{{ $rootId }}-modal-upload">
              <div class="product-image-picker__upload-inner">
                <div class="product-image-picker__upload-icon" aria-hidden="true">
                  <i class="fa fa-cloud-arrow-up"></i>
                </div>
                <p class="product-image-picker__upload-title">Drop your image here</p>
                <p class="product-image-picker__upload-sub">Clear food photo for your menu listing.</p>
                <div class="product-image-picker__upload-formats" aria-hidden="true">
                  <span>JPG</span><span>PNG</span><span>WebP</span>
                </div>
                <div class="product-image-picker__upload-actions">
                  <span class="product-image-picker__upload-browse">
                    <i class="fa fa-folder-open" aria-hidden="true"></i> Browse files
                  </span>
                  <span class="product-image-picker__upload-or">or drag and drop</span>
                </div>
                <span class="product-image-picker__upload-limit">Maximum file size 5 MB</span>
              </div>
              <input type="file" id="{{ $rootId }}-modal-upload"
                     accept="image/jpeg,image/png,image/gif,image/webp" hidden
                     data-product-image-modal-upload>
            </label>
          </div>
          <p class="product-image-picker__status" data-product-image-upload-status hidden role="status"></p>
        </div>

        <div class="product-image-picker__panel-pane" data-product-image-tab-panel="files"
             role="tabpanel" aria-labelledby="{{ $rootId }}-tab-files" hidden>
          <p class="product-image-picker__loading muted" data-product-image-picker-loading>Loading images…</p>
          <p class="product-image-picker__empty muted" data-product-image-picker-empty hidden>No images in file manager yet. Upload one in the first tab.</p>
          <div class="product-image-picker__grid" data-product-image-picker-grid hidden></div>
        </div>

        <div class="product-image-picker__panel-pane" data-product-image-tab-panel="generate"
             role="tabpanel" aria-labelledby="{{ $rootId }}-tab-generate" hidden>
          @unless($geminiAvail)
            <p class="product-image-picker__empty muted">GEMINI_API_KEY is not configured.</p>
          @else
            <div class="product-image-picker__gen-form">
              <label class="product-image-field__label" for="{{ $rootId }}-gen-prompt">Describe the dish</label>
              <textarea id="{{ $rootId }}-gen-prompt" class="product-image-picker__textarea"
                        rows="4" maxlength="500"
                        placeholder="e.g. grilled chicken on white plate, top view, studio lighting…"
                        data-product-image-gen-prompt></textarea>
              <p class="product-image-picker__upload-hint">Uses the item name from the form. Images are saved to your Files.</p>
              <button type="button" class="linkbtn"
                      style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;"
                      data-product-image-gen-btn>
                <i class="fa fa-wand-magic-sparkles"></i> Generate with Gemini
              </button>
            </div>
            <p class="product-image-picker__status muted" data-product-image-gen-status hidden></p>
            <div class="product-image-picker__gen-preview" data-product-image-gen-preview hidden>
              <img alt="" data-product-image-gen-preview-img>
              <p class="muted" style="margin:8px 0 0;font-size:12px;" data-product-image-gen-preview-name></p>
            </div>
          @endunless
        </div>
      </div>

      <div class="product-image-picker__footer" data-product-image-picker-footer hidden>
        <a href="{{ route('filemanager.index') }}" class="product-image-picker__footer-link"
           data-product-image-files-link data-product-image-footer-files hidden
           target="_blank" rel="noopener">
          <i class="fa fa-arrow-up-right-from-square" aria-hidden="true"></i> Open file manager
        </a>
      </div>
    </div>
  </div>
</div>

@once
<style>
.product-image-field__label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:8px;}
.product-image-field__panel{display:grid;gap:12px;padding:12px;border-radius:10px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 98%,transparent);}
.product-image-field__preview{display:flex;align-items:center;gap:12px;}
.product-image-field__preview[hidden],.product-image-field__placeholder[hidden]{display:none!important;}
.product-image-field__preview img{width:72px;height:72px;object-fit:cover;border-radius:10px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 90%,transparent);}
.product-image-field__preview img:not([src]){display:none;}
.product-image-field__preview-meta{font-size:12px;color:var(--muted);word-break:break-word;}
.product-image-field__gallery{display:flex;flex-wrap:wrap;gap:10px;}
.product-image-field__gallery[hidden]{display:none!important;}
.product-image-field__gallery-item{position:relative;width:72px;height:72px;flex-shrink:0;}
.product-image-field__gallery-item img{width:100%;height:100%;object-fit:cover;border-radius:10px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 90%,transparent);}
.product-image-field__gallery-remove{position:absolute;top:-6px;right:-6px;width:22px;height:22px;border-radius:999px;border:1px solid var(--border);background:var(--card);color:var(--text);font-size:14px;line-height:1;cursor:pointer;display:grid;place-items:center;padding:0;}
.product-image-field__placeholder{display:flex;align-items:center;gap:10px;padding:14px;border-radius:10px;border:1px dashed var(--border);color:var(--muted);font-size:13px;background:color-mix(in srgb,var(--card) 94%,transparent);}
.product-image-field__placeholder i{font-size:22px;opacity:.75;}
.product-image-field__actions{display:flex;flex-wrap:wrap;gap:8px;}
.product-image-field__hint{margin:0;font-size:11px;line-height:1.45;color:var(--muted);}
.product-image-picker{position:fixed;inset:0;z-index:130;display:flex;justify-content:center;align-items:center;padding:15vh 15vw;overflow:auto;box-sizing:border-box;text-transform:none;letter-spacing:normal;}
.product-image-picker[hidden]{display:none;}
.product-field .product-image-picker label.product-image-picker__upload-zone{display:flex;align-items:center;justify-content:center;width:100%;margin:0;font-size:inherit;font-weight:inherit;color:inherit;text-transform:none;letter-spacing:normal;}
.product-image-picker__backdrop{position:fixed;inset:0;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);}
.product-image-picker__panel{position:relative;z-index:1;width:70vw;max-width:70vw;height:70vh;max-height:70vh;margin:auto;border-radius:14px;border:1px solid var(--border);background:var(--card);box-shadow:0 20px 48px rgba(0,0,0,.32);display:flex;flex-direction:column;box-sizing:border-box;}
.product-image-picker__footer{flex-shrink:0;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 14px 12px;border-top:1px solid var(--border);background:color-mix(in srgb,var(--card) 97%,transparent);}
.product-image-picker__footer[hidden]{display:none;}
.product-image-picker__footer-link{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--primary);text-decoration:none;text-transform:none;}
.product-image-picker__footer-link[hidden],.product-image-picker__footer-done[hidden]{display:none;}
.product-image-picker__footer-link:hover{text-decoration:underline;}
.product-image-picker__footer-done{margin-left:auto;}
.product-image-picker__item.is-in-gallery{outline:2px solid color-mix(in srgb,var(--primary) 45%,transparent);outline-offset:2px;}
.product-image-picker__head{display:flex;justify-content:space-between;align-items:center;padding:11px 14px;border-bottom:1px solid var(--border);flex-shrink:0;}
.product-image-picker__head h3{margin:0;font-size:15px;font-weight:800;}
.product-image-picker__close{width:32px;height:32px;display:grid;place-items:center;border:1px solid var(--border);border-radius:9px;background:transparent;cursor:pointer;font-size:17px;line-height:1;}
.product-image-picker__tabs{display:flex;gap:6px;padding:10px 14px 0;border-bottom:1px solid var(--border);flex-shrink:0;}
.product-image-picker__tab{flex:1;padding:8px 10px;font-size:12px;font-weight:600;border:1px solid var(--border);border-radius:8px 8px 0 0;background:color-mix(in srgb,var(--card) 96%,transparent);color:var(--muted);cursor:pointer;transition:background .15s ease,color .15s ease,border-color .15s ease;}
.product-image-picker__tab:hover{color:var(--text);}
.product-image-picker__tab.is-active{color:var(--text);background:var(--card);border-bottom-color:var(--card);box-shadow:0 -1px 0 var(--card);position:relative;z-index:1;}
.product-image-picker__body{padding:14px;overflow:auto;flex:1 1 auto;min-height:0;}
.product-image-picker__body:has(.product-image-picker__panel-pane--upload.is-active){display:flex;flex-direction:column;min-height:0;}
.product-image-picker__panel-pane{display:none;}
.product-image-picker__panel-pane.is-active{display:block;}
.product-image-picker__panel-pane--upload.is-active{display:flex;flex-direction:column;flex:1 1 auto;min-height:0;align-items:stretch;justify-content:center;}
.product-image-picker__upload-wrap{flex:1 1 auto;display:flex;align-items:center;justify-content:center;width:100%;min-height:0;}
.product-image-picker__upload-zone{position:relative;flex:1 1 auto;width:100%;max-width:100%;min-height:min(100%,380px);display:flex;align-items:center;justify-content:center;padding:clamp(20px,4vw,36px) clamp(16px,3vw,28px);box-sizing:border-box;border:2px dashed color-mix(in srgb,var(--primary) 38%,var(--border));border-radius:16px;background:linear-gradient(145deg,color-mix(in srgb,var(--primary) 7%,var(--card)),color-mix(in srgb,var(--card) 97%,transparent));cursor:pointer;text-align:center;transition:border-color .18s ease,background .18s ease,box-shadow .18s ease,transform .12s ease;}
.product-image-picker__upload-zone:hover{border-color:color-mix(in srgb,var(--primary) 50%,var(--border));box-shadow:0 10px 28px rgba(0,0,0,.06);}
.product-image-picker__upload-zone.is-dragover{border-style:solid;border-color:color-mix(in srgb,var(--primary) 58%,var(--border));background:color-mix(in srgb,var(--primary) 12%,var(--card));box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 22%,transparent);transform:scale(1.005);}
.product-image-picker__upload-zone.is-uploading{pointer-events:none;opacity:.88;}
.product-image-picker__upload-inner{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:0;max-width:36rem;width:100%;margin:0 auto;text-align:center;text-transform:none;}
.product-image-picker__upload-icon{width:56px;height:56px;margin:0 auto 16px;border-radius:14px;display:grid;place-items:center;flex-shrink:0;font-size:24px;color:var(--primary);background:color-mix(in srgb,var(--primary) 14%,var(--card));border:1px solid color-mix(in srgb,var(--primary) 28%,var(--border));}
.product-image-picker__upload-title{margin:0 0 6px;font-size:17px;font-weight:800;letter-spacing:-.02em;color:var(--text);line-height:1.25;text-align:center;text-transform:none;width:100%;}
.product-image-picker__upload-sub{margin:0 auto 18px;font-size:13px;line-height:1.5;color:var(--muted);max-width:32ch;text-align:center;text-transform:none;}
.product-image-picker__upload-formats{display:flex;flex-wrap:wrap;justify-content:center;gap:6px;margin:0 0 20px;}
.product-image-picker__upload-formats span{padding:4px 10px;font-size:11px;font-weight:700;letter-spacing:.03em;text-transform:uppercase;border-radius:999px;border:1px solid color-mix(in srgb,var(--border) 85%,var(--primary));background:color-mix(in srgb,var(--card) 92%,var(--primary));color:color-mix(in srgb,var(--muted) 40%,var(--text));}
.product-image-picker__upload-actions{display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:10px 14px;margin-bottom:14px;}
.product-image-picker__upload-browse{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;font-size:13px;font-weight:700;border-radius:12px;border:1px solid color-mix(in srgb,var(--btn-bg) 55%,var(--border));background:var(--btn-bg);color:#fff;pointer-events:none;box-shadow:0 1px 2px rgba(0,0,0,.08);text-transform:none;}
.product-image-picker__upload-or{font-size:12px;color:var(--muted);line-height:1.4;text-align:center;text-transform:none;}
.product-image-picker__upload-limit{display:block;width:100%;font-size:11px;font-weight:600;color:color-mix(in srgb,var(--muted) 88%,var(--text));letter-spacing:.02em;text-align:center;text-transform:none;}
.product-image-picker__upload-hint{font-size:12px;color:var(--muted);line-height:1.4;max-width:40ch;}
.product-image-picker__status{flex-shrink:0;margin:12px 0 0;padding:8px 12px;font-size:12px;font-weight:600;line-height:1.4;border-radius:8px;color:var(--muted);background:color-mix(in srgb,var(--card) 94%,transparent);border:1px solid var(--border);}
.product-image-picker__status:not([hidden]){color:var(--text);}
.product-image-picker__grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px;}
.product-image-picker__item{display:flex;flex-direction:column;gap:4px;padding:6px;border-radius:10px;border:2px solid transparent;background:color-mix(in srgb,var(--card) 96%,transparent);cursor:pointer;text-align:left;}
.product-image-picker__item:hover,.product-image-picker__item.is-selected{border-color:color-mix(in srgb,var(--primary) 50%,var(--border));background:color-mix(in srgb,var(--primary) 8%,transparent);}
.product-image-picker__item img{width:100%;aspect-ratio:1;object-fit:cover;border-radius:8px;border:1px solid var(--border);}
.product-image-picker__item span{font-size:10px;line-height:1.3;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.product-image-picker__gen-form{display:grid;gap:10px;}
.product-image-picker__textarea{width:100%;box-sizing:border-box;padding:9px 10px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);resize:vertical;min-height:88px;font-family:inherit;}
.product-image-picker__gen-preview{margin-top:14px;padding:12px;border-radius:10px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 96%,transparent);text-align:center;}
.product-image-picker__gen-preview img{max-width:100%;max-height:240px;border-radius:8px;border:1px solid var(--border);object-fit:contain;}
html.product-image-picker-open,html.product-image-picker-open body{overflow:hidden;}
@media (prefers-reduced-motion:reduce){
    .product-image-picker__upload-zone,.product-image-picker__upload-zone.is-dragover{transition:none!important;transform:none!important;}
}
</style>
<script>
(function () {
    if (window.__productImageFieldInit) return;
    window.__productImageFieldInit = true;

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            || document.querySelector('input[name="_token"]')?.value || '';
    }

    function initProductImageRoot(root) {
        if (!root || root.dataset.productImageReady === '1') return;
        root.dataset.productImageReady = '1';

        const fileIdInput = root.querySelector('[data-product-image-file-id]');
        const removeInput = root.querySelector('[data-product-image-remove]');
        const preview = root.querySelector('[data-product-image-preview]');
        const previewImg = root.querySelector('[data-product-image-preview-img]');
        const previewName = root.querySelector('[data-product-image-preview-name]');
        const placeholder = root.querySelector('[data-product-image-placeholder]');
        const clearBtn = root.querySelector('[data-product-image-clear]');
        const pickOpenBtn = root.querySelector('[data-product-image-pick-open]');
        const pickerModal = root.querySelector('[data-product-image-picker]');
        const pickerUrl = @json(route('product.images.picker'));
        const uploadUrl = @json(route('product.images.upload'));
        const generateUrl = @json(route('product.images.generate'));
        const geminiAvailable = root.dataset.geminiAvailable === '1';

        const tabBtns = pickerModal ? Array.from(pickerModal.querySelectorAll('[data-product-image-tab]')) : [];
        const tabPanels = pickerModal ? Array.from(pickerModal.querySelectorAll('[data-product-image-tab-panel]')) : [];
        const modalUploadInput = root.querySelector('[data-product-image-modal-upload]');
        const uploadZone = root.querySelector('[data-product-image-modal-upload-zone]');
        const uploadStatus = root.querySelector('[data-product-image-upload-status]');
        const pickerGrid = root.querySelector('[data-product-image-picker-grid]');
        const pickerLoading = root.querySelector('[data-product-image-picker-loading]');
        const pickerEmpty = root.querySelector('[data-product-image-picker-empty]');
        const filesLink = root.querySelector('[data-product-image-files-link]');
        const pickerFooter = root.querySelector('[data-product-image-picker-footer]');
        const footerFilesLink = root.querySelector('[data-product-image-footer-files]');
        const footerDoneBtn = root.querySelector('[data-product-image-picker-done]');
        const gallery = root.querySelector('[data-product-image-gallery]');
        const idsContainer = root.querySelector('[data-product-image-ids]');
        const multiple = root.dataset.multiple === '1';
        const maxImages = parseInt(root.dataset.maxImages || '1', 10) || 1;
        const genPrompt = root.querySelector('[data-product-image-gen-prompt]');
        const genBtn = root.querySelector('[data-product-image-gen-btn]');
        const genStatus = root.querySelector('[data-product-image-gen-status]');
        const genPreview = root.querySelector('[data-product-image-gen-preview]');
        const genPreviewImg = root.querySelector('[data-product-image-gen-preview-img]');
        const genPreviewName = root.querySelector('[data-product-image-gen-preview-name]');

        let filesLoaded = false;
        const galleryMap = new Map();

        function productNameFromForm() {
            const form = root.closest('form');
            const nameInput = form ? form.querySelector('[name="name"]') : null;
            return nameInput ? String(nameInput.value || '').trim() : '';
        }

        function syncPrimaryFromGallery() {
            const first = galleryMap.size ? galleryMap.values().next().value : null;
            if (fileIdInput) fileIdInput.value = first ? String(first.id) : '';
            if (!multiple && first) {
                if (previewImg) previewImg.src = first.url;
                if (previewName) previewName.textContent = first.name || '';
                if (preview) preview.hidden = false;
            }
        }

        function renderGallery() {
            if (!gallery || !idsContainer) return;
            gallery.innerHTML = '';
            idsContainer.innerHTML = '';
            galleryMap.forEach(function (img) {
                const item = document.createElement('div');
                item.className = 'product-image-field__gallery-item';
                item.setAttribute('data-product-image-gallery-item', '');
                item.dataset.id = String(img.id);
                const safeUrl = String(img.url).replace(/"/g, '&quot;');
                item.innerHTML = '<img src="' + safeUrl + '" alt=""><button type="button" class="product-image-field__gallery-remove" data-product-image-gallery-remove aria-label="Remove image">&times;</button>';
                item.querySelector('[data-product-image-gallery-remove]')?.addEventListener('click', function (e) {
                    e.preventDefault(); e.stopPropagation();
                    removeFromGallery(img.id);
                });
                gallery.appendChild(item);
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'file_manager_file_ids[]';
                input.value = String(img.id);
                input.setAttribute('data-product-image-id-input', '');
                idsContainer.appendChild(input);
            });
            const hasImages = galleryMap.size > 0;
            if (multiple) { gallery.hidden = !hasImages; if (preview) preview.hidden = true; }
            if (placeholder) placeholder.hidden = hasImages;
            if (clearBtn) clearBtn.hidden = !hasImages;
            if (removeInput) removeInput.value = hasImages ? '0' : '1';
            syncPrimaryFromGallery();
            markPickerGridItems();
        }

        function markPickerGridItems() {
            if (!pickerGrid) return;
            pickerGrid.querySelectorAll('.product-image-picker__item').forEach(function (btn) {
                const id = btn.dataset.fileId;
                btn.classList.toggle('is-in-gallery', id && galleryMap.has(String(id)));
                btn.classList.toggle('is-selected', !multiple && fileIdInput && String(fileIdInput.value) === String(id));
            });
        }

        function addToGallery(img, closeAfter) {
            if (!img || !img.id || !img.url) return false;
            const key = String(img.id);
            if (galleryMap.has(key)) {
                if (!multiple) { setSelection(img.id, img.url, img.name); if (closeAfter) closePicker(); }
                return true;
            }
            if (multiple && galleryMap.size >= maxImages) { alert('Maximum ' + maxImages + ' images allowed.'); return false; }
            if (multiple) {
                galleryMap.set(key, { id: img.id, url: img.url, name: img.name || '' });
                renderGallery();
                if (closeAfter) closePicker();
                return true;
            }
            galleryMap.clear();
            galleryMap.set(key, { id: img.id, url: img.url, name: img.name || '' });
            renderGallery();
            setSelection(img.id, img.url, img.name);
            if (closeAfter) closePicker();
            return true;
        }

        function removeFromGallery(id) { galleryMap.delete(String(id)); renderGallery(); }

        function setSelection(id, url, name) {
            if (!id || !url) { clearSelection(); return; }
            galleryMap.clear();
            galleryMap.set(String(id), { id: id, url: url, name: name || '' });
            renderGallery();
            if (removeInput) removeInput.value = '0';
            if (previewImg) previewImg.src = url;
            if (previewName) previewName.textContent = name || '';
            if (preview) preview.hidden = multiple;
            if (placeholder) placeholder.hidden = true;
            if (clearBtn) clearBtn.hidden = false;
        }

        function clearSelection() {
            galleryMap.clear();
            renderGallery();
            if (fileIdInput) fileIdInput.value = '';
            if (removeInput) removeInput.value = '1';
            if (previewImg) { previewImg.removeAttribute('src'); previewImg.removeAttribute('srcset'); }
            if (previewName) previewName.textContent = '';
            if (preview) preview.hidden = true;
            if (placeholder) placeholder.hidden = false;
            if (clearBtn) clearBtn.hidden = true;
        }

        root.querySelectorAll('[data-product-image-gallery-item]').forEach(function (item) {
            const id = item.dataset.id;
            const img = item.querySelector('img');
            if (id && img && img.src) galleryMap.set(String(id), { id: id, url: img.src, name: '' });
        });
        if (galleryMap.size) renderGallery();

        clearBtn?.addEventListener('click', clearSelection);

        function applyImagePayload(payload, closeAfter) {
            if (!payload) return false;
            if (multiple && Array.isArray(payload.images) && payload.images.length) {
                let added = 0;
                payload.images.forEach(function (img) { if (addToGallery(img, false)) added++; });
                return added > 0;
            }
            const one = payload.image || payload;
            return addToGallery(one, closeAfter);
        }

        function uploadFiles(fileList, statusEl, inputEl) {
            const files = fileList ? Array.from(fileList) : [];
            if (!files.length) return;
            const fd = new FormData();
            fd.append('image', files[0]);
            if (statusEl) { statusEl.hidden = false; statusEl.textContent = 'Uploading…'; }
            if (inputEl) inputEl.disabled = true;
            uploadZone?.classList.add('is-uploading');
            fetch(uploadUrl, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                body: fd,
            })
                .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
                .then(function (r) {
                    if (!r.ok) {
                        const msg = (r.data && (r.data.message || r.data.error)) || 'Upload failed.';
                        if (statusEl) statusEl.textContent = msg; else alert(msg);
                        return;
                    }
                    applyImagePayload(r.data, true);
                    if (statusEl) statusEl.textContent = 'Image added.';
                    filesLoaded = false;
                })
                .catch(function () {
                    if (statusEl) statusEl.textContent = 'Could not upload image.'; else alert('Could not upload image.');
                })
                .finally(function () {
                    uploadZone?.classList.remove('is-uploading');
                    if (inputEl) { inputEl.value = ''; inputEl.disabled = false; }
                });
        }

        modalUploadInput?.addEventListener('change', function () {
            uploadFiles(modalUploadInput.files, uploadStatus, modalUploadInput);
        });

        if (uploadZone) {
            uploadZone.addEventListener('dragover', function (e) { e.preventDefault(); uploadZone.classList.add('is-dragover'); });
            uploadZone.addEventListener('dragleave', function () { uploadZone.classList.remove('is-dragover'); });
            uploadZone.addEventListener('drop', function (e) {
                e.preventDefault();
                uploadZone.classList.remove('is-dragover');
                uploadFiles(e.dataTransfer && e.dataTransfer.files, uploadStatus, modalUploadInput);
            });
        }

        function updatePickerFooter(tabId) {
            if (!pickerFooter) return;
            pickerFooter.hidden = tabId !== 'files';
            if (footerFilesLink) footerFilesLink.hidden = tabId !== 'files';
        }

        function switchTab(tabId) {
            tabBtns.forEach(function (btn) {
                const active = btn.getAttribute('data-product-image-tab') === tabId;
                btn.classList.toggle('is-active', active);
                btn.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            tabPanels.forEach(function (pane) {
                const active = pane.getAttribute('data-product-image-tab-panel') === tabId;
                pane.classList.toggle('is-active', active);
                pane.hidden = !active;
            });
            updatePickerFooter(tabId);
            if (tabId === 'files' && !filesLoaded) loadFileManagerImages();
        }

        tabBtns.forEach(function (btn) {
            btn.addEventListener('click', function () { switchTab(btn.getAttribute('data-product-image-tab')); });
        });

        function loadFileManagerImages() {
            if (!pickerModal) return;
            if (pickerLoading) pickerLoading.hidden = false;
            if (pickerEmpty) pickerEmpty.hidden = true;
            if (pickerGrid) { pickerGrid.hidden = true; pickerGrid.innerHTML = ''; }
            fetch(pickerUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    filesLoaded = true;
                    if (pickerLoading) pickerLoading.hidden = true;
                    if (filesLink && data.files_url) filesLink.href = data.files_url;
                    const images = data.images || [];
                    if (!images.length) {
                        if (pickerEmpty) { pickerEmpty.hidden = false; pickerEmpty.textContent = 'No images in file manager yet. Upload one in the first tab.'; }
                        return;
                    }
                    if (!pickerGrid) return;
                    pickerGrid.hidden = false;
                    images.forEach(function (img) {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'product-image-picker__item';
                        btn.dataset.fileId = String(img.id);
                        if (!multiple && fileIdInput && String(fileIdInput.value) === String(img.id)) btn.classList.add('is-selected');
                        const safeUrl = String(img.url).replace(/"/g, '&quot;');
                        const safeName = String(img.name || 'Image').replace(/</g, '&lt;');
                        btn.innerHTML = '<img src="' + safeUrl + '" alt=""><span>' + safeName + '</span>';
                        btn.addEventListener('click', function () {
                            setSelection(img.id, img.url, img.name);
                            closePicker();
                        });
                        pickerGrid.appendChild(btn);
                    });
                })
                .catch(function () {
                    if (pickerLoading) pickerLoading.hidden = true;
                    if (pickerEmpty) { pickerEmpty.hidden = false; pickerEmpty.textContent = 'Could not load images.'; }
                });
        }

        function runGenerate() {
            if (!geminiAvailable || !genBtn) return;
            const prompt = genPrompt ? String(genPrompt.value || '').trim() : '';
            const prevHtml = genBtn.innerHTML;
            genBtn.disabled = true;
            genBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Generating…';
            if (genStatus) { genStatus.hidden = false; genStatus.textContent = 'Generating image with Gemini…'; }
            if (genPreview) genPreview.hidden = true;
            fetch(generateUrl, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ product_name: productNameFromForm(), prompt: prompt }),
            })
                .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
                .then(function (r) {
                    if (!r.ok || !r.data || !r.data.id) {
                        const msg = (r.data && (r.data.error || r.data.message)) || 'Generation failed.';
                        if (genStatus) genStatus.textContent = msg;
                        return;
                    }
                    if (genStatus) genStatus.textContent = 'Image saved to file manager.';
                    if (genPreview && genPreviewImg) {
                        genPreviewImg.src = r.data.url;
                        if (genPreviewName) genPreviewName.textContent = r.data.name || '';
                        genPreview.hidden = false;
                    }
                    applyImagePayload(r.data, true);
                    filesLoaded = false;
                })
                .catch(function () { if (genStatus) genStatus.textContent = 'Could not reach the server.'; })
                .finally(function () { genBtn.disabled = false; genBtn.innerHTML = prevHtml; });
        }

        genBtn?.addEventListener('click', runGenerate);

        function closePicker() {
            if (!pickerModal) return;
            pickerModal.hidden = true;
            document.documentElement.classList.remove('product-image-picker-open');
        }

        function openPicker() {
            if (!pickerModal) return;
            pickerModal.hidden = false;
            document.documentElement.classList.add('product-image-picker-open');
            switchTab('upload');
            if (uploadStatus) uploadStatus.hidden = true;
            if (genStatus) genStatus.hidden = true;
        }

        pickOpenBtn?.addEventListener('click', openPicker);
        pickerModal?.querySelectorAll('[data-product-image-picker-close]').forEach(function (el) {
            el.addEventListener('click', closePicker);
        });

        root._resetProductImage = function () {
            galleryMap.clear();
            if (gallery) { gallery.innerHTML = ''; gallery.hidden = true; }
            if (idsContainer) idsContainer.innerHTML = '';
            if (fileIdInput) fileIdInput.value = '';
            if (removeInput) removeInput.value = '0';
            if (previewImg) previewImg.removeAttribute('src');
            if (previewName) previewName.textContent = '';
            if (preview) preview.hidden = true;
            if (placeholder) placeholder.hidden = false;
            if (clearBtn) clearBtn.hidden = true;
            filesLoaded = false;
            closePicker();
        };
    }

    window.initProductImageFields = function (container) {
        (container || document).querySelectorAll('[data-product-image-root]').forEach(initProductImageRoot);
    };

    window.resetProductImageFields = function (container) {
        (container || document).querySelectorAll('[data-product-image-root]').forEach(function (root) {
            if (typeof root._resetProductImage === 'function') root._resetProductImage();
        });
    };

    document.addEventListener('DOMContentLoaded', function () { window.initProductImageFields(); });
})();
</script>
@endonce
