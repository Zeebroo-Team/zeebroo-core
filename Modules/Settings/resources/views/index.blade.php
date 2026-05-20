@extends('theme::layouts.app', ['title' => $title, 'heading' => $heading])

@section('content')
<div class="card" style="max-width:100%;padding:16px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;">
        <h1 style="margin:0;font-size:20px;line-height:1.2;">{{ $heading }}</h1>
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
            <a href="{{ route('settings.user') }}" class="linkbtn" style="padding:6px 10px;font-size:12px;{{ $scopeType === 'user' ? '' : 'opacity:.7;' }}">User Settings</a>
            <a href="{{ route('settings.business') }}" class="linkbtn" style="padding:6px 10px;font-size:12px;{{ $scopeType === 'business' ? '' : 'opacity:.7;' }}">Business Settings</a>
        </div>
    </div>

    @if(session('status'))
        <div style="margin-top:10px;border:1px solid color-mix(in srgb,#16a34a 45%,var(--border));background:color-mix(in srgb,#16a34a 10%,var(--card));color:var(--text);border-radius:10px;padding:10px 12px;display:flex;align-items:center;gap:9px;font-size:12px;line-height:1.35;">
            <span style="width:18px;height:18px;display:inline-flex;align-items:center;justify-content:center;border-radius:999px;background:color-mix(in srgb,#22c55e 25%,transparent);flex-shrink:0;">
                <i class="fa fa-check" style="color:#22c55e;font-size:10px;"></i>
            </span>
            <span style="font-weight:600;">{{ session('status') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div style="margin-top:10px;color:#ef4444;">
            {{ $errors->first() }}
        </div>
    @endif

    @if(!$hasScope)
        <div style="margin-top:10px;border:1px solid var(--border);border-radius:10px;padding:10px;font-size:13px;" class="muted">
            {{ $scopeType === 'business' ? 'No business found. Complete business onboarding first.' : 'No user scope found.' }}
        </div>
    @else
    @php
        $tabNames = $tabs->keys()->values();
        $requestedTab = request('tab');
        $activeTab = $requestedTab !== null && $requestedTab !== '' && $tabs->has($requestedTab)
            ? $requestedTab
            : (string) ($tabNames->first() ?? '');
        if ($activeTab === '' || !$tabs->has($activeTab)) {
            $activeTab = (string) ($tabNames->first() ?? '');
        }
        $activeSettings = $tabs->get($activeTab, collect())->values();
    @endphp

    @if($tabs->isNotEmpty())
        <div style="margin-top:10px;">
            <div class="muted" style="font-size:11px;margin-bottom:6px;">
                Sections
            </div>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
            @foreach($tabNames as $tabName)
                <a href="{{ request()->fullUrlWithQuery(['tab' => $tabName]) }}"
                   style="text-decoration:none;padding:5px 9px;border-radius:999px;border:1px solid var(--border);font-size:12px;
                          background:{{ $activeTab === $tabName ? 'color-mix(in srgb,var(--primary) 24%,var(--card))' : 'var(--card)' }};
                          color:var(--text);">
                    {{ $tabName === 'hr' ? 'HR' : ucfirst($tabName) }}
                </a>
            @endforeach
            </div>
        </div>
        <form id="settingsBulkForm" method="post" action="{{ route('settings.bulk.store') }}" enctype="multipart/form-data" style="margin-top:8px;border:1px solid var(--border);border-radius:10px;padding:10px;display:grid;gap:8px;">
            @csrf
            <input type="hidden" name="scope" value="{{ $scopeType }}">
            <input type="hidden" name="tab" value="{{ $activeTab }}">
            <div id="uploadProgressWrap" style="display:none;border:1px solid var(--border);border-radius:8px;padding:8px;background:color-mix(in srgb,var(--primary) 8%,var(--card));">
                <div style="display:flex;justify-content:space-between;font-size:11px;margin-bottom:6px;">
                    <span>Uploading files...</span>
                    <span id="uploadProgressText">0%</span>
                </div>
                <div style="height:7px;border-radius:999px;background:color-mix(in srgb,var(--card) 85%,#000);overflow:hidden;">
                    <div id="uploadProgressBar" style="height:100%;width:0%;background:linear-gradient(90deg,var(--primary),#22c55e);transition:width .2s ease;"></div>
                </div>
            </div>
            @foreach($activeSettings as $setting)
                <div style="display:grid;gap:4px;padding:6px 0;border-bottom:1px solid var(--border);">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;">
                        <div style="font-weight:700;font-size:13px;">{{ $setting['name'] }}</div>
                        <span class="pkg-badge">{{ strtoupper($setting['type']) }}</span>
                    </div>
                    @if($setting['description'])
                        <div class="muted" style="font-size:11px;">{{ $setting['description'] }}</div>
                    @endif

                    @if($setting['type'] === 'select')
                        <select name="values[{{ $setting['key'] }}]"
                                {{ $setting['required'] ? 'required' : '' }}
                                {{ $setting['is_disabled'] ? 'disabled' : '' }}
                                style="padding:8px;border:1px solid var(--border);border-radius:8px;background:var(--card);color:var(--text);font-size:13px;">
                            @foreach($setting['options'] as $option)
                                <option value="{{ $option['value'] ?? '' }}" {{ (string) ($setting['value'] ?? '') === (string) ($option['value'] ?? '') ? 'selected' : '' }}>
                                    {{ $option['label'] ?? $option['value'] ?? '' }}
                                </option>
                            @endforeach
                        </select>
                    @elseif($setting['type'] === 'textarea')
                        <textarea name="values[{{ $setting['key'] }}]"
                                  placeholder="{{ $setting['placeholder'] }}"
                                  {{ $setting['required'] ? 'required' : '' }}
                                  {{ $setting['is_disabled'] ? 'disabled' : '' }}
                                  rows="3"
                                  style="padding:8px;border:1px solid var(--border);border-radius:8px;background:var(--card);color:var(--text);font-size:13px;">{{ $setting['value'] }}</textarea>
                    @elseif($setting['type'] === 'checkbox')
                        <label style="display:flex;align-items:center;gap:8px;">
                            <input type="hidden" name="values[{{ $setting['key'] }}]" value="0">
                            <input type="checkbox"
                                   name="values[{{ $setting['key'] }}]"
                                   value="1"
                                   {{ $setting['value'] ? 'checked' : '' }}
                                   {{ $setting['is_disabled'] ? 'disabled' : '' }}>
                            <span class="muted" style="font-size:12px;">{{ $setting['placeholder'] ?: 'Toggle this setting' }}</span>
                        </label>
                    @elseif($setting['type'] === 'file')
                        @php $fileInputId = 'file_' . md5($setting['key']); @endphp
                        <div data-dropzone data-input-id="{{ $fileInputId }}" style="display:grid;gap:6px;border:1px dashed var(--border);border-radius:10px;padding:10px;background:color-mix(in srgb,var(--card) 94%,transparent);transition:border-color .2s ease, background .2s ease;">
                            <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
                                <label for="{{ $fileInputId }}" style="display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:8px;border:1px solid var(--border);font-size:12px;cursor:pointer;background:var(--card);">
                                    <i class="fa fa-cloud-arrow-up" style="color:var(--primary);"></i>
                                    <span>Choose File</span>
                                </label>
                                <span data-file-name class="muted" style="font-size:11px;">No file selected</span>
                            </div>
                            <div class="muted" style="font-size:11px;">Drag & drop file here, or click choose file.</div>
                            <input type="file"
                                   id="{{ $fileInputId }}"
                                   data-file-input
                                   name="files[{{ $setting['key'] }}]"
                                   {{ $setting['required'] && empty($setting['value']) ? 'required' : '' }}
                                   {{ $setting['is_disabled'] ? 'disabled' : '' }}
                                   onchange="window.handleSettingsFileSelect && window.handleSettingsFileSelect(this)"
                                   style="display:none;">
                            <div data-preview="{{ $fileInputId }}" style="display:none;border:1px solid var(--border);border-radius:8px;padding:8px;background:var(--card);">
                                <img data-preview-image="{{ $fileInputId }}" alt="File preview" style="display:none;max-height:120px;border-radius:6px;border:1px solid var(--border);margin-bottom:6px;">
                                <div data-preview-text="{{ $fileInputId }}" class="muted" style="font-size:11px;"></div>
                            </div>
                        </div>
                        @if(!empty($setting['value']))
                            <a href="{{ asset('storage/' . $setting['value']) }}" target="_blank" style="font-size:11px;color:var(--primary);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                                <i class="fa fa-file-lines"></i><span>View current file</span>
                            </a>
                            @php
                                $ext = strtolower(pathinfo((string) $setting['value'], PATHINFO_EXTENSION));
                                $isPreviewImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'], true);
                            @endphp
                            @if($isPreviewImage)
                                <img src="{{ asset('storage/' . $setting['value']) }}" alt="Current image" style="margin-top:6px;max-height:90px;border:1px solid var(--border);border-radius:8px;display:block;">
                            @endif
                        @endif
                    @else
                        <input type="{{ $setting['type'] === 'number' ? 'number' : 'text' }}"
                               name="values[{{ $setting['key'] }}]"
                               value="{{ $setting['value'] }}"
                               placeholder="{{ $setting['placeholder'] }}"
                               @if($setting['type'] === 'number') step="1"
                                   @if(is_int($setting['min'] ?? null))min="{{ $setting['min'] }}"@endif
                                   @if(is_int($setting['max'] ?? null))max="{{ $setting['max'] }}"@endif
                               @endif
                               {{ $setting['required'] ? 'required' : '' }}
                               {{ $setting['is_disabled'] ? 'disabled' : '' }}
                               style="padding:8px;border:1px solid var(--border);border-radius:8px;background:var(--card);color:var(--text);font-size:13px;">
                    @endif
                </div>
            @endforeach
            <div style="display:flex;justify-content:flex-end;">
                <button type="submit" style="padding:7px 12px;font-size:12px;">Save All Settings</button>
            </div>
        </form>

        @if($scopeType === 'business' && $activeTab === 'hr' && isset($scopeModel) && $scopeModel instanceof \Modules\Business\Models\Business)
            @include('hrmanagement::settings.business-hr-extras', [
                'business' => $scopeModel,
                'holidays' => $businessHolidays ?? collect(),
                'hrPayrollOptedIn' => $hrPayrollOptedIn ?? false,
            ])
        @endif
    @else
        <p style="margin-top:14px;" class="muted">No {{ $scopeType }} settings yet.</p>
    @endif
    @endif
</div>
@endsection

<script>
    window.handleSettingsFileSelect = function (input) {
        const zone = input.closest('[data-dropzone]');
        const target = zone ? zone.querySelector('[data-file-name]') : null;
        const previewWrap = zone ? zone.querySelector(`[data-preview="${input.id}"]`) : null;
        const previewImage = zone ? zone.querySelector(`[data-preview-image="${input.id}"]`) : null;
        const previewText = zone ? zone.querySelector(`[data-preview-text="${input.id}"]`) : null;

        if (!target || !previewWrap || !previewText) return;
        if (!(input.files && input.files.length)) {
            target.textContent = 'No file selected';
            previewWrap.style.display = 'none';
            return;
        }

        const file = input.files[0];
        target.textContent = file.name;
        previewWrap.style.display = 'block';
        previewText.textContent = `${file.type || 'unknown'} • ${(file.size / 1024).toFixed(1)} KB`;

        if (previewImage && file.type.startsWith('image/')) {
            previewImage.src = URL.createObjectURL(file);
            previewImage.style.display = 'block';
        } else if (previewImage) {
            previewImage.style.display = 'none';
            previewImage.removeAttribute('src');
        }
    };

    (function () {
        const form = document.getElementById('settingsBulkForm');
        if (!form) return;

        const progressWrap = document.getElementById('uploadProgressWrap');
        const progressBar = document.getElementById('uploadProgressBar');
        const progressText = document.getElementById('uploadProgressText');
        const fileInputs = form.querySelectorAll('[data-file-input]');
        const dropzones = form.querySelectorAll('[data-dropzone]');

        fileInputs.forEach((input) => {
            input.addEventListener('change', () => window.handleSettingsFileSelect(input));
        });

        dropzones.forEach((zone) => {
            const inputId = zone.getAttribute('data-input-id');
            const input = inputId ? document.getElementById(inputId) : null;
            if (!input) return;

            ['dragenter', 'dragover'].forEach((eventName) => {
                zone.addEventListener(eventName, (event) => {
                    event.preventDefault();
                    zone.style.borderColor = 'var(--primary)';
                    zone.style.background = 'color-mix(in srgb,var(--primary) 12%,var(--card))';
                });
            });

            ['dragleave', 'drop'].forEach((eventName) => {
                zone.addEventListener(eventName, (event) => {
                    event.preventDefault();
                    zone.style.borderColor = 'var(--border)';
                    zone.style.background = 'color-mix(in srgb,var(--card) 94%,transparent)';
                });
            });

            zone.addEventListener('drop', (event) => {
                const files = event.dataTransfer?.files;
                if (!files || !files.length) return;
                input.files = files;
                window.handleSettingsFileSelect(input);
            });
        });

        form.addEventListener('submit', () => {
            const hasSelectedFile = Array.from(fileInputs).some((input) => input.files && input.files.length > 0);
            if (!hasSelectedFile || !progressWrap || !progressBar || !progressText) return;

            progressWrap.style.display = 'block';
            let value = 8;
            progressBar.style.width = value + '%';
            progressText.textContent = value + '%';

            const timer = setInterval(() => {
                value = Math.min(value + 7, 92);
                progressBar.style.width = value + '%';
                progressText.textContent = value + '%';
                if (value >= 92) clearInterval(timer);
            }, 180);
        });
    })();
</script>
