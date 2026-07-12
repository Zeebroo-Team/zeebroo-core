{{-- Requires: $fieldIdPrefix (unique per instance). Optional: $value = ['id'=>, 'url'=>, 'name'=>] --}}
@php
    $rootId = 'crm-image-' . ($fieldIdPrefix ?? \Illuminate\Support\Str::random(6));
    $current = $value ?? null;
    $currentId = $current['id'] ?? null;
    $currentUrl = $current['url'] ?? null;
    $currentName = $current['name'] ?? null;
    $geminiAvailable = filled(config('aibot.gemini.api_key'));
@endphp

<div class="crm-image-field" id="{{ $rootId }}-wrap" data-crm-image-root data-gemini-available="{{ $geminiAvailable ? '1' : '0' }}">
    <input type="hidden" id="{{ $rootId }}-file-id" value="{{ $currentId }}" data-crm-image-file-id>
    <input type="hidden" id="{{ $rootId }}-url" value="{{ $currentUrl }}" data-crm-image-url>

    <div class="crm-image-field__panel">
        <div class="crm-image-field__preview" id="{{ $rootId }}-preview" data-crm-image-preview @if(!$currentUrl) hidden @endif>
            <img @if($currentUrl) src="{{ $currentUrl }}" @endif alt="" id="{{ $rootId }}-preview-img" data-crm-image-preview-img>
            <div class="crm-image-field__preview-meta" data-crm-image-preview-name>{{ $currentName }}</div>
        </div>
        <div class="crm-image-field__placeholder" id="{{ $rootId }}-placeholder" data-crm-image-placeholder @if($currentUrl) hidden @endif>
            <i class="fa fa-image" aria-hidden="true"></i>
            <span>No image selected</span>
        </div>

        <div class="crm-image-field__actions">
            <button type="button" class="linkbtn crm-image-field__btn" style="padding:7px 12px;font-size:12px;display:inline-flex;align-items:center;gap:6px;" data-crm-image-pick-open>
                <i class="fa fa-images"></i> Choose image
            </button>
            <button type="button" class="linkbtn crm-image-field__btn crm-image-field__btn--muted" style="padding:7px 10px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--muted);" data-crm-image-clear @if(!$currentUrl) hidden @endif>
                Remove
            </button>
        </div>
    </div>

    <div id="{{ $rootId }}-picker-modal" class="crm-image-picker" data-crm-image-picker hidden role="dialog" aria-modal="true" aria-labelledby="{{ $rootId }}-picker-title">
        <div class="crm-image-picker__backdrop" data-crm-image-picker-close tabindex="-1"></div>
        <div class="crm-image-picker__panel">
            <div class="crm-image-picker__head">
                <h3 id="{{ $rootId }}-picker-title">Choose image</h3>
                <button type="button" class="crm-image-picker__close" data-crm-image-picker-close aria-label="Close">&times;</button>
            </div>

            <div class="crm-image-picker__tabs" role="tablist" aria-label="Image source">
                <button type="button" class="crm-image-picker__tab is-active" role="tab" aria-selected="true" data-crm-image-tab="upload">Upload</button>
                <button type="button" class="crm-image-picker__tab" role="tab" aria-selected="false" data-crm-image-tab="files">File manager</button>
                <button type="button" class="crm-image-picker__tab" role="tab" aria-selected="false" data-crm-image-tab="generate">Generate</button>
            </div>

            <div class="crm-image-picker__body">
                <div class="crm-image-picker__panel-pane is-active" data-crm-image-tab-panel="upload" role="tabpanel">
                    <div class="crm-image-picker__upload-wrap">
                        <label class="crm-image-picker__upload-zone" data-crm-image-modal-upload-zone for="{{ $rootId }}-modal-upload">
                            <div class="crm-image-picker__upload-inner">
                                <div class="crm-image-picker__upload-icon" aria-hidden="true"><i class="fa fa-cloud-arrow-up"></i></div>
                                <p class="crm-image-picker__upload-title">Drop your image here</p>
                                <p class="crm-image-picker__upload-sub">Add an image for this block.</p>
                                <div class="crm-image-picker__upload-actions">
                                    <span class="crm-image-picker__upload-browse"><i class="fa fa-folder-open" aria-hidden="true"></i> Browse files</span>
                                    <span class="crm-image-picker__upload-or">or drag and drop</span>
                                </div>
                                <span class="crm-image-picker__upload-limit">Maximum file size 5 MB</span>
                            </div>
                            <input type="file" id="{{ $rootId }}-modal-upload" accept="image/jpeg,image/png,image/gif,image/webp" hidden data-crm-image-modal-upload>
                        </label>
                    </div>
                    <p class="crm-image-picker__status" data-crm-image-upload-status hidden role="status"></p>
                </div>

                <div class="crm-image-picker__panel-pane" data-crm-image-tab-panel="files" role="tabpanel" hidden>
                    <p class="crm-image-picker__loading muted" data-crm-image-picker-loading>Loading images…</p>
                    <p class="crm-image-picker__empty muted" data-crm-image-picker-empty hidden>No images yet. Upload one in the first tab.</p>
                    <div class="crm-image-picker__grid" data-crm-image-picker-grid hidden></div>
                </div>

                <div class="crm-image-picker__panel-pane" data-crm-image-tab-panel="generate" role="tabpanel" hidden>
                    @unless($geminiAvailable)
                        <p class="crm-image-picker__empty muted">GEMINI_API_KEY is not configured. Add a key in your environment to generate images.</p>
                    @else
                        <div class="crm-image-picker__gen-form">
                            <label class="crm-image-field__label" for="{{ $rootId }}-gen-prompt">Describe the image</label>
                            <textarea id="{{ $rootId }}-gen-prompt" class="crm-image-picker__textarea" rows="4" maxlength="500" placeholder="e.g. friendly customer support team, bright office…" data-crm-image-gen-prompt></textarea>
                            <button type="button" class="linkbtn" style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;" data-crm-image-gen-btn>
                                <i class="fa fa-wand-magic-sparkles"></i> Generate with Gemini
                            </button>
                        </div>
                        <p class="crm-image-picker__status muted" data-crm-image-gen-status hidden></p>
                        <div class="crm-image-picker__gen-preview" data-crm-image-gen-preview hidden>
                            <img alt="" data-crm-image-gen-preview-img>
                        </div>
                    @endunless
                </div>
            </div>

            <div class="crm-image-picker__footer" data-crm-image-picker-footer hidden>
                <a href="{{ route('filemanager.index') }}" data-crm-image-files-link target="_blank" rel="noopener" class="crm-image-picker__footer-link">
                    <i class="fa fa-arrow-up-right-from-square" aria-hidden="true"></i> Open full file manager
                </a>
            </div>
        </div>
    </div>
</div>

@once('crm-image-field-assets')
<style>
.crm-image-field [hidden],.crm-image-picker [hidden]{display:none!important;}
.crm-image-field__label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:6px;}
.crm-image-field__panel{display:grid;gap:10px;}
.crm-image-field__preview{display:flex;align-items:center;gap:10px;}
.crm-image-field__preview[hidden],.crm-image-field__placeholder[hidden]{display:none!important;}
.crm-image-field__preview img{width:56px;height:56px;object-fit:cover;border-radius:8px;border:1px solid var(--border);background:var(--card);}
.crm-image-field__preview-meta{font-size:11px;color:var(--muted);word-break:break-word;}
.crm-image-field__placeholder{display:flex;align-items:center;gap:8px;padding:10px;border-radius:8px;border:1px dashed var(--border);color:var(--muted);font-size:12px;}
.crm-image-field__actions{display:flex;flex-wrap:wrap;gap:8px;}
.crm-image-picker{position:fixed;inset:0;z-index:130;display:flex;justify-content:center;align-items:center;padding:10vh 10vw;overflow:auto;box-sizing:border-box;}
.crm-image-picker[hidden]{display:none;}
.crm-image-picker__backdrop{position:fixed;inset:0;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);}
.crm-image-picker__panel{position:relative;z-index:1;width:64vw;max-width:640px;max-height:74vh;margin:auto;border-radius:14px;border:1px solid var(--border);background:var(--card);box-shadow:0 20px 48px rgba(0,0,0,.32);display:flex;flex-direction:column;box-sizing:border-box;}
.crm-image-picker__head{display:flex;justify-content:space-between;align-items:center;padding:11px 14px;border-bottom:1px solid var(--border);flex-shrink:0;}
.crm-image-picker__head h3{margin:0;font-size:15px;font-weight:800;}
.crm-image-picker__close{width:32px;height:32px;display:grid;place-items:center;border:1px solid var(--border);border-radius:9px;background:transparent;cursor:pointer;font-size:17px;line-height:1;}
.crm-image-picker__tabs{display:flex;gap:6px;padding:10px 14px 0;border-bottom:1px solid var(--border);flex-shrink:0;}
.crm-image-picker__tab{flex:1;padding:8px 10px;font-size:12px;font-weight:600;border:1px solid var(--border);border-radius:8px 8px 0 0;background:color-mix(in srgb,var(--card) 96%,transparent);color:var(--muted);cursor:pointer;}
.crm-image-picker__tab.is-active{color:var(--text);background:var(--card);border-bottom-color:var(--card);position:relative;z-index:1;}
.crm-image-picker__body{padding:14px;overflow:auto;flex:1 1 auto;min-height:0;}
.crm-image-picker__panel-pane{display:none;}
.crm-image-picker__panel-pane.is-active{display:block;}
.crm-image-picker__upload-wrap{display:flex;align-items:center;justify-content:center;width:100%;}
.crm-image-picker__upload-zone{position:relative;width:100%;display:flex;align-items:center;justify-content:center;padding:24px;box-sizing:border-box;border:2px dashed color-mix(in srgb,var(--primary) 38%,var(--border));border-radius:14px;cursor:pointer;text-align:center;}
.crm-image-picker__upload-zone.is-dragover{border-style:solid;background:color-mix(in srgb,var(--primary) 10%,var(--card));}
.crm-image-picker__upload-zone.is-uploading{pointer-events:none;opacity:.85;}
.crm-image-picker__upload-inner{display:flex;flex-direction:column;align-items:center;gap:4px;}
.crm-image-picker__upload-icon{width:48px;height:48px;margin-bottom:8px;border-radius:12px;display:grid;place-items:center;font-size:20px;color:var(--primary);background:color-mix(in srgb,var(--primary) 14%,var(--card));border:1px solid color-mix(in srgb,var(--primary) 28%,var(--border));}
.crm-image-picker__upload-title{margin:0 0 4px;font-size:14px;font-weight:800;color:var(--text);}
.crm-image-picker__upload-sub{margin:0 0 10px;font-size:12px;color:var(--muted);}
.crm-image-picker__upload-actions{display:flex;align-items:center;gap:8px;margin-bottom:8px;}
.crm-image-picker__upload-browse{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;font-size:12px;font-weight:700;border-radius:9px;background:var(--btn-bg,var(--primary));color:#fff;}
.crm-image-picker__upload-or{font-size:11px;color:var(--muted);}
.crm-image-picker__upload-limit{display:block;font-size:10px;color:var(--muted);}
.crm-image-picker__status{margin:10px 0 0;padding:8px 10px;font-size:12px;border-radius:8px;color:var(--text);background:color-mix(in srgb,var(--card) 94%,transparent);border:1px solid var(--border);}
.crm-image-picker__grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(96px,1fr));gap:8px;}
.crm-image-picker__item{display:flex;flex-direction:column;gap:4px;padding:5px;border-radius:8px;border:2px solid transparent;background:color-mix(in srgb,var(--card) 96%,transparent);cursor:pointer;text-align:left;}
.crm-image-picker__item:hover,.crm-image-picker__item.is-selected{border-color:color-mix(in srgb,var(--primary) 50%,var(--border));}
.crm-image-picker__item img{width:100%;aspect-ratio:1;object-fit:cover;border-radius:6px;border:1px solid var(--border);}
.crm-image-picker__item span{font-size:9px;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.crm-image-picker__gen-form{display:grid;gap:8px;}
.crm-image-picker__textarea{width:100%;box-sizing:border-box;padding:8px;font-size:12px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);resize:vertical;min-height:70px;font-family:inherit;}
.crm-image-picker__gen-preview{margin-top:10px;text-align:center;}
.crm-image-picker__gen-preview img{max-width:100%;max-height:180px;border-radius:8px;border:1px solid var(--border);}
.crm-image-picker__footer{flex-shrink:0;display:flex;justify-content:flex-end;gap:12px;padding:10px 14px;border-top:1px solid var(--border);}
.crm-image-picker__footer[hidden]{display:none;}
.crm-image-picker__footer-link{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--primary);text-decoration:none;}
html.crm-image-picker-open,html.crm-image-picker-open body{overflow:hidden;}
</style>
<script>
(function () {
    if (window.__crmImageFieldInit) return;
    window.__crmImageFieldInit = true;

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            || document.querySelector('input[name="_token"]')?.value || '';
    }

    var pickerUrl   = @json(route('crm.images.picker'));
    var uploadUrl   = @json(route('crm.images.upload'));
    var generateUrl = @json(route('crm.images.generate'));

    function initCrmImageRoot(root) {
        if (!root || root.dataset.crmImageReady === '1') return;
        root.dataset.crmImageReady = '1';

        var fileIdInput = root.querySelector('[data-crm-image-file-id]');
        var urlInput = root.querySelector('[data-crm-image-url]');
        var preview = root.querySelector('[data-crm-image-preview]');
        var previewImg = root.querySelector('[data-crm-image-preview-img]');
        var previewName = root.querySelector('[data-crm-image-preview-name]');
        var placeholder = root.querySelector('[data-crm-image-placeholder]');
        var clearBtn = root.querySelector('[data-crm-image-clear]');
        var pickOpenBtn = root.querySelector('[data-crm-image-pick-open]');
        var pickerModal = root.querySelector('[data-crm-image-picker]');
        var geminiAvailable = root.dataset.geminiAvailable === '1';

        var tabBtns = pickerModal ? Array.from(pickerModal.querySelectorAll('[data-crm-image-tab]')) : [];
        var tabPanels = pickerModal ? Array.from(pickerModal.querySelectorAll('[data-crm-image-tab-panel]')) : [];
        var modalUploadInput = root.querySelector('[data-crm-image-modal-upload]');
        var uploadZone = root.querySelector('[data-crm-image-modal-upload-zone]');
        var uploadStatus = root.querySelector('[data-crm-image-upload-status]');
        var pickerGrid = root.querySelector('[data-crm-image-picker-grid]');
        var pickerLoading = root.querySelector('[data-crm-image-picker-loading]');
        var pickerEmpty = root.querySelector('[data-crm-image-picker-empty]');
        var filesLink = root.querySelector('[data-crm-image-files-link]');
        var pickerFooter = root.querySelector('[data-crm-image-picker-footer]');
        var genPrompt = root.querySelector('[data-crm-image-gen-prompt]');
        var genBtn = root.querySelector('[data-crm-image-gen-btn]');
        var genStatus = root.querySelector('[data-crm-image-gen-status]');
        var genPreview = root.querySelector('[data-crm-image-gen-preview]');
        var genPreviewImg = root.querySelector('[data-crm-image-gen-preview-img]');

        var filesLoaded = false;

        function setSelection(id, url, name) {
            if (fileIdInput) fileIdInput.value = id || '';
            if (urlInput) urlInput.value = url || '';
            if (previewImg) previewImg.src = url;
            if (previewName) previewName.textContent = name || '';
            if (preview) preview.hidden = !url;
            if (placeholder) placeholder.hidden = !!url;
            if (clearBtn) clearBtn.hidden = !url;
            root.dispatchEvent(new CustomEvent('crm-image:change', { bubbles: true, detail: { id: id, url: url, name: name } }));
        }

        function clearSelection() { setSelection('', '', ''); }

        clearBtn?.addEventListener('click', clearSelection);

        function uploadFiles(fileList) {
            var files = fileList ? Array.from(fileList) : [];
            if (!files.length) return;
            var fd = new FormData();
            fd.append('image', files[0]);
            if (uploadStatus) { uploadStatus.hidden = false; uploadStatus.textContent = 'Uploading…'; }
            uploadZone?.classList.add('is-uploading');
            fetch(uploadUrl, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                body: fd,
            })
            .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
            .then(function (r) {
                if (!r.ok) { if (uploadStatus) uploadStatus.textContent = (r.data && r.data.message) || 'Upload failed.'; return; }
                setSelection(r.data.id, r.data.url, r.data.name);
                if (uploadStatus) uploadStatus.textContent = 'Image added.';
                filesLoaded = false;
                closePicker();
            })
            .catch(function () { if (uploadStatus) uploadStatus.textContent = 'Could not upload image.'; })
            .finally(function () { uploadZone?.classList.remove('is-uploading'); if (modalUploadInput) modalUploadInput.value = ''; });
        }

        modalUploadInput?.addEventListener('change', function () { uploadFiles(modalUploadInput.files); });

        if (uploadZone) {
            uploadZone.addEventListener('dragover', function (e) { e.preventDefault(); uploadZone.classList.add('is-dragover'); });
            uploadZone.addEventListener('dragleave', function () { uploadZone.classList.remove('is-dragover'); });
            uploadZone.addEventListener('drop', function (e) { e.preventDefault(); uploadZone.classList.remove('is-dragover'); uploadFiles(e.dataTransfer && e.dataTransfer.files); });
        }

        function switchTab(tabId) {
            tabBtns.forEach(function (btn) {
                var active = btn.getAttribute('data-crm-image-tab') === tabId;
                btn.classList.toggle('is-active', active);
            });
            tabPanels.forEach(function (pane) {
                var active = pane.getAttribute('data-crm-image-tab-panel') === tabId;
                pane.classList.toggle('is-active', active);
                pane.hidden = !active;
            });
            if (pickerFooter) pickerFooter.hidden = tabId !== 'files';
            if (tabId === 'files' && !filesLoaded) loadFileManagerImages();
        }

        tabBtns.forEach(function (btn) { btn.addEventListener('click', function () { switchTab(btn.getAttribute('data-crm-image-tab')); }); });

        function loadFileManagerImages() {
            if (pickerLoading) pickerLoading.hidden = false;
            if (pickerEmpty) pickerEmpty.hidden = true;
            if (pickerGrid) { pickerGrid.hidden = true; pickerGrid.innerHTML = ''; }

            fetch(pickerUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    filesLoaded = true;
                    if (pickerLoading) pickerLoading.hidden = true;
                    if (filesLink && data.files_url) filesLink.href = data.files_url;
                    var images = data.images || [];
                    if (!images.length) { if (pickerEmpty) pickerEmpty.hidden = false; return; }
                    if (!pickerGrid) return;
                    pickerGrid.hidden = false;
                    images.forEach(function (img) {
                        var btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'crm-image-picker__item';
                        var safeUrl = String(img.url).replace(/"/g, '&quot;');
                        var safeName = String(img.name || 'Image').replace(/</g, '&lt;');
                        btn.innerHTML = '<img src="' + safeUrl + '" alt=""><span>' + safeName + '</span>';
                        btn.addEventListener('click', function () { setSelection(img.id, img.url, img.name); closePicker(); });
                        pickerGrid.appendChild(btn);
                    });
                })
                .catch(function () { if (pickerLoading) pickerLoading.hidden = true; if (pickerEmpty) pickerEmpty.hidden = false; });
        }

        function runGenerate() {
            if (!geminiAvailable || !genBtn) return;
            var prompt = genPrompt ? String(genPrompt.value || '').trim() : '';
            var prevHtml = genBtn.innerHTML;
            genBtn.disabled = true;
            genBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Generating…';
            if (genStatus) { genStatus.hidden = false; genStatus.textContent = 'Generating image with Gemini…'; }
            if (genPreview) genPreview.hidden = true;

            fetch(generateUrl, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ prompt: prompt }),
            })
            .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
            .then(function (r) {
                if (!r.ok || !r.data || !r.data.id) { if (genStatus) genStatus.textContent = (r.data && r.data.error) || 'Generation failed.'; return; }
                if (genStatus) genStatus.textContent = 'Image saved.';
                if (genPreview && genPreviewImg) { genPreviewImg.src = r.data.url; genPreview.hidden = false; }
                setSelection(r.data.id, r.data.url, r.data.name);
                filesLoaded = false;
            })
            .catch(function () { if (genStatus) genStatus.textContent = 'Could not reach the server.'; })
            .finally(function () { genBtn.disabled = false; genBtn.innerHTML = prevHtml; });
        }

        genBtn?.addEventListener('click', runGenerate);

        function closePicker() {
            if (!pickerModal) return;
            pickerModal.hidden = true;
            document.documentElement.classList.remove('crm-image-picker-open');
        }

        function openPicker() {
            if (!pickerModal) return;
            pickerModal.hidden = false;
            document.documentElement.classList.add('crm-image-picker-open');
            switchTab('upload');
            if (uploadStatus) uploadStatus.hidden = true;
            if (genStatus) genStatus.hidden = true;
        }

        pickOpenBtn?.addEventListener('click', openPicker);
        pickerModal?.querySelectorAll('[data-crm-image-picker-close]').forEach(function (el) { el.addEventListener('click', closePicker); });
    }

    window.initCrmImageFields = function (container) {
        (container || document).querySelectorAll('[data-crm-image-root]').forEach(initCrmImageRoot);
    };

    document.addEventListener('DOMContentLoaded', function () { window.initCrmImageFields(); });
})();
</script>
@endonce
