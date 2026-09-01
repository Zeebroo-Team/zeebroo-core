@extends('theme::layouts.app', ['title' => 'App Releases', 'heading' => 'App Releases'])

@section('content')
<style>
/* ── layout ── */
.arl-wrap{max-width:1100px;margin:0 auto;}
.arl-header{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:24px;flex-wrap:wrap;}
.arl-title{margin:0;font-size:22px;font-weight:800;letter-spacing:-.025em;}
.arl-sub{margin:4px 0 0;font-size:13px;color:var(--muted);}

/* ── flash ── */
.arl-msg{display:flex;align-items:center;gap:10px;padding:13px 16px;border-radius:12px;margin-bottom:20px;
    font-size:13px;font-weight:600;border:1px solid color-mix(in srgb,#16a34a 38%,var(--border));
    background:color-mix(in srgb,#16a34a 9%,var(--card));}
.arl-msg-err{border-color:color-mix(in srgb,#ef4444 38%,var(--border));background:color-mix(in srgb,#ef4444 9%,var(--card));color:#b91c1c;}

/* ── table card ── */
.arl-card{border:1px solid var(--border);border-radius:16px;overflow:hidden;background:var(--card);}
.arl-table-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch;}
.arl-table{width:100%;min-width:760px;border-collapse:collapse;}
.arl-table th{padding:11px 16px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;
    color:var(--muted);background:color-mix(in srgb,var(--card) 88%,var(--border));text-align:left;
    border-bottom:1px solid var(--border);}
.arl-table td{padding:13px 16px;font-size:13.5px;border-bottom:1px solid color-mix(in srgb,var(--border) 60%,transparent);vertical-align:middle;}
.arl-table tr:last-child td{border-bottom:none;}
.arl-table tr:hover td{background:color-mix(in srgb,var(--primary) 3%,transparent);}

/* ── version chip ── */
.arl-ver{display:inline-flex;align-items:center;gap:7px;font-weight:800;font-size:14.5px;letter-spacing:-.01em;}
.arl-latest-star{color:#f59e0b;font-size:12px;}

/* ── channel badge ── */
.arl-ch{display:inline-flex;align-items:center;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:700;gap:5px;}
.arl-ch--stable{background:color-mix(in srgb,#22c55e 14%,transparent);color:#16a34a;}
.arl-ch--beta{background:color-mix(in srgb,#3b82f6 14%,transparent);color:#2563eb;}
.arl-ch--alpha{background:color-mix(in srgb,#a855f7 14%,transparent);color:#9333ea;}
.arl-ch--rc{background:color-mix(in srgb,#f59e0b 14%,transparent);color:#d97706;}

/* ── latest badge ── */
.arl-latest-pill{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:999px;
    font-size:11px;font-weight:700;background:color-mix(in srgb,#22c55e 14%,transparent);color:#15803d;}

/* ── platform links ── */
.arl-plat{display:flex;gap:6px;flex-wrap:wrap;}
.arl-plat-link{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:8px;
    font-size:12px;font-weight:600;text-decoration:none;border:1px solid var(--border);color:var(--muted);transition:.14s;}
.arl-plat-link:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));color:var(--primary);}
.arl-plat-none{font-size:12px;color:var(--muted);}

/* ── notes ── */
.arl-notes{font-size:12px;color:var(--muted);margin:0;padding:0;list-style:disc inside;}
.arl-notes li{margin-bottom:2px;}

/* ── action buttons ── */
.arl-act{display:flex;gap:6px;flex-wrap:wrap;}
.arl-btn{padding:5px 11px;border-radius:8px;border:1px solid var(--border);background:transparent;
    color:var(--text);font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;white-space:nowrap;
    display:inline-flex;align-items:center;gap:5px;transition:.14s;}
.arl-btn:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 7%,transparent);}
.arl-btn--star:hover{border-color:color-mix(in srgb,#f59e0b 55%,var(--border));background:color-mix(in srgb,#f59e0b 10%,transparent);color:#d97706;}
.arl-btn--del:hover{border-color:color-mix(in srgb,#ef4444 45%,var(--border));background:color-mix(in srgb,#ef4444 7%,transparent);color:#b91c1c;}
.arl-add-btn{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border-radius:11px;
    border:1px solid color-mix(in srgb,var(--btn-bg) 55%,var(--border));background:var(--btn-bg);
    color:#fff;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;transition:.15s ease;}
.arl-add-btn:hover{background:var(--btn-hover);color:#111827;}

/* ── empty ── */
.arl-empty{padding:52px 24px;text-align:center;}
.arl-empty-icon{width:52px;height:52px;border-radius:14px;margin:0 auto 14px;display:grid;place-items:center;
    font-size:22px;background:color-mix(in srgb,var(--primary) 10%,transparent);color:var(--primary);}
.arl-empty-title{margin:0 0 6px;font-size:16px;font-weight:700;}
.arl-empty-sub{margin:0;font-size:13px;color:var(--muted);}

/* ── modal ── */
.arl-modal-overlay{position:fixed;inset:0;z-index:340;display:none;align-items:flex-start;justify-content:center;padding:20px;box-sizing:border-box;overflow-y:auto;}
.arl-modal-overlay.is-open{display:flex;}
.arl-modal-backdrop{position:fixed;inset:0;background:rgba(2,6,23,.55);backdrop-filter:blur(4px);cursor:pointer;}
:is(html[data-theme="light"],html[data-theme="light_blue"]) .arl-modal-backdrop{background:rgba(17,24,39,.42);}
.arl-modal-shell{position:relative;z-index:1;width:100%;max-width:500px;margin:auto 0;border-radius:18px;border:1px solid var(--border);
    background:var(--card);box-shadow:0 24px 56px rgba(0,0,0,.28);}
.arl-modal-head{padding:20px 22px 16px;border-bottom:1px solid var(--border);display:flex;align-items:flex-start;justify-content:space-between;gap:12px;}
.arl-modal-title{margin:0 0 3px;font-size:18px;font-weight:800;letter-spacing:-.02em;}
.arl-modal-sub{margin:0;font-size:13px;color:var(--muted);}
.arl-modal-close{width:34px;height:34px;border-radius:10px;border:1px solid var(--border);background:transparent;
    color:var(--text);cursor:pointer;display:grid;place-items:center;font-size:18px;line-height:1;padding:0;font-family:inherit;flex-shrink:0;}
.arl-modal-close:hover{background:color-mix(in srgb,#ef4444 8%,transparent);border-color:color-mix(in srgb,#ef4444 35%,var(--border));}
.arl-modal-body{padding:20px 22px;}
.arl-modal-foot{padding:14px 22px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px;}
.arl-field{margin-bottom:16px;}
.arl-field:last-child{margin-bottom:0;}
.arl-field label{display:block;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin-bottom:6px;}
.arl-field input,.arl-field select,.arl-field textarea{width:100%;box-sizing:border-box;padding:10px 13px;border-radius:11px;
    border:1px solid var(--border);background:color-mix(in srgb,var(--card) 94%,transparent);color:var(--text);font-size:14px;font-family:inherit;}
.arl-field input:focus,.arl-field select:focus,.arl-field textarea:focus{outline:none;
    border-color:color-mix(in srgb,var(--primary) 55%,var(--border));box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 14%,transparent);}
.arl-field textarea{resize:vertical;min-height:72px;}
.arl-field-hint{margin:5px 0 0;font-size:11.5px;color:var(--muted);}
.arl-field-err{margin:5px 0 0;font-size:12px;font-weight:600;color:#ef4444;}
.arl-field-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.arl-cancel-btn{padding:9px 18px;border-radius:10px;border:1px solid var(--border);background:transparent;
    color:var(--text);font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;}
.arl-cancel-btn:hover{background:color-mix(in srgb,var(--border) 40%,transparent);}
.arl-submit-btn{padding:9px 22px;border-radius:10px;border:none;background:var(--btn-bg);color:#fff;
    font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:7px;}
.arl-submit-btn:hover{background:var(--btn-hover);color:#111827;}
.arl-check-row{display:flex;align-items:center;gap:9px;padding:10px 13px;border-radius:11px;
    border:1px solid var(--border);background:color-mix(in srgb,var(--card) 94%,transparent);cursor:pointer;}
.arl-check-row input[type=checkbox]{width:15px;height:15px;accent-color:var(--primary);cursor:pointer;flex-shrink:0;}
.arl-check-row span{font-size:13.5px;color:var(--text);}

/* ── responsive ── */
@media (max-width:640px){
  .arl-wrap{max-width:100%;}
  .arl-header{gap:12px;}
  .arl-add-btn{width:100%;justify-content:center;}
  .arl-field-row{grid-template-columns:1fr;gap:0;}
  .arl-modal-overlay{padding:12px;}
  .arl-modal-shell{max-width:100%;}
  .arl-modal-body{padding:16px;}
  .arl-modal-head{padding:16px 16px 12px;}
  .arl-modal-foot{padding:12px 16px;flex-wrap:wrap;}
  .arl-modal-foot button{flex:1;justify-content:center;}
}
</style>

<div class="arl-wrap">

  {{-- Header --}}
  <div class="arl-header">
    <div>
      <h1 class="arl-title"><i class="fa fa-rocket" style="color:var(--primary);margin-right:8px"></i>App Releases</h1>
      <p class="arl-sub">Manage desktop app versions. The latest stable release is shown to users checking for updates.</p>
    </div>
    <button class="arl-add-btn" id="arl-open-create">
      <i class="fa fa-plus"></i> New Release
    </button>
  </div>

  {{-- Flash messages --}}
  @if(session('success'))
    <div class="arl-msg"><i class="fa fa-circle-check"></i> {{ session('success') }}</div>
  @endif
  @if($errors->any())
    <div class="arl-msg arl-msg-err"><i class="fa fa-triangle-exclamation"></i> {{ $errors->first() }}</div>
  @endif

  {{-- Releases table --}}
  <div class="arl-card">
    @if($releases->isEmpty())
      <div class="arl-empty">
        <div class="arl-empty-icon"><i class="fa fa-rocket"></i></div>
        <h3 class="arl-empty-title">No releases yet</h3>
        <p class="arl-empty-sub">Click <strong>New Release</strong> to publish your first version.</p>
      </div>
    @else
      <div class="arl-table-scroll">
      <table class="arl-table">
        <thead>
          <tr>
            <th>Version</th>
            <th>Channel</th>
            <th>Release Date</th>
            <th>Downloads</th>
            <th>Release Notes</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @foreach($releases as $release)
          <tr>
            {{-- Version --}}
            <td>
              <div class="arl-ver">
                v{{ $release->version }}
                @if($release->is_latest)
                  <i class="fa fa-star arl-latest-star" title="Latest on {{ $release->channel }}"></i>
                @endif
              </div>
              @if($release->is_latest)
                <div style="margin-top:4px">
                  <span class="arl-latest-pill"><i class="fa fa-circle-check" style="font-size:10px"></i> Latest</span>
                </div>
              @endif
            </td>

            {{-- Channel --}}
            <td>
              <span class="arl-ch arl-ch--{{ $release->channel }}">
                {{ ucfirst($release->channel) }}
              </span>
            </td>

            {{-- Date --}}
            <td style="white-space:nowrap;font-size:13px;">
              {{ $release->release_date?->format('d M Y') }}
              <div style="font-size:11px;color:var(--muted);margin-top:1px">{{ $release->release_date?->diffForHumans() }}</div>
            </td>

            {{-- Download links --}}
            <td>
              @if($release->windows_url || $release->macos_url || $release->linux_url)
                <div class="arl-plat">
                  @if($release->windows_url)
                    <a href="{{ $release->windows_url }}" target="_blank" class="arl-plat-link" title="Windows">
                      <i class="fa fa-brands fa-windows"></i> Win
                    </a>
                  @endif
                  @if($release->macos_url)
                    <a href="{{ $release->macos_url }}" target="_blank" class="arl-plat-link" title="macOS">
                      <i class="fa fa-brands fa-apple"></i> Mac
                    </a>
                  @endif
                  @if($release->linux_url)
                    <a href="{{ $release->linux_url }}" target="_blank" class="arl-plat-link" title="Linux">
                      <i class="fa fa-brands fa-linux"></i> Linux
                    </a>
                  @endif
                </div>
              @else
                <span class="arl-plat-none">—</span>
              @endif
            </td>

            {{-- Notes --}}
            <td style="max-width:260px;">
              @if(!empty($release->notes))
                <ul class="arl-notes">
                  @foreach(array_slice($release->notes, 0, 3) as $note)
                    <li>{{ $note }}</li>
                  @endforeach
                  @if(count($release->notes) > 3)
                    <li style="list-style:none;color:var(--muted);font-style:italic">+{{ count($release->notes) - 3 }} more…</li>
                  @endif
                </ul>
              @else
                <span style="font-size:12px;color:var(--muted)">—</span>
              @endif
            </td>

            {{-- Actions --}}
            <td>
              <div class="arl-act">
                <button type="button" class="arl-btn arl-edit-btn"
                        data-id="{{ $release->id }}"
                        data-version="{{ $release->version }}"
                        data-release-date="{{ $release->release_date?->toDateString() }}"
                        data-channel="{{ $release->channel }}"
                        data-is-latest="{{ $release->is_latest ? '1' : '0' }}"
                        data-notes="{{ implode("\n", $release->notes ?? []) }}"
                        data-windows-url="{{ $release->windows_url }}"
                        data-macos-url="{{ $release->macos_url }}"
                        data-linux-url="{{ $release->linux_url }}">
                  <i class="fa fa-pen"></i> Edit
                </button>
                @unless($release->is_latest)
                  <form method="POST" action="{{ route('admin.releases.set-latest', $release) }}">
                    @csrf
                    <button type="submit" class="arl-btn arl-btn--star" title="Mark as latest">
                      <i class="fa fa-star"></i> Set Latest
                    </button>
                  </form>
                @endunless
                <form method="POST" action="{{ route('admin.releases.destroy', $release) }}"
                      onsubmit="return confirm('Delete v{{ $release->version }}? This cannot be undone.')">
                  @csrf @method('DELETE')
                  <button type="submit" class="arl-btn arl-btn--del">
                    <i class="fa fa-trash"></i> Delete
                  </button>
                </form>
              </div>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
      </div>
    @endif
  </div>

</div>

@php
  $arlIsEditReopen = old('_method') === 'PUT' && old('_release_id');
  $arlFormAction = $arlIsEditReopen ? url('/admin/releases/'.old('_release_id')) : route('admin.releases.store');
@endphp

{{-- ── Create / Edit Release Modal ─────────────────────────────────────── --}}
<div class="arl-modal-overlay @if($errors->any()) is-open @endif" id="arl-modal">
  <div class="arl-modal-backdrop" id="arl-modal-backdrop"></div>
  <div class="arl-modal-shell">
    <div class="arl-modal-head">
      <div>
        <h2 class="arl-modal-title" id="arl-modal-title">{{ $arlIsEditReopen ? 'Edit Release' : 'New Release' }}</h2>
        <p class="arl-modal-sub" id="arl-modal-sub">{{ $arlIsEditReopen ? 'Update this desktop app version.' : 'Publish a new desktop app version.' }}</p>
      </div>
      <button class="arl-modal-close" type="button" id="arl-modal-close">
        <i class="fa fa-xmark"></i>
      </button>
    </div>

    <form method="POST" action="{{ $arlFormAction }}" id="arl-form">
      @csrf
      <input type="hidden" name="_method" id="arl-form-method" value="{{ old('_method', 'POST') }}">
      <input type="hidden" name="_release_id" id="arl-form-release-id" value="{{ old('_release_id') }}">
      <div class="arl-modal-body">

        <div class="arl-field-row">
          <div class="arl-field">
            <label>Version <span style="color:#ef4444">*</span></label>
            <input type="text" name="version" id="arl-f-version" value="{{ old('version', '5.0.0') }}"
                   placeholder="e.g. 5.1.0" required>
            @error('version')<p class="arl-field-err">{{ $message }}</p>@enderror
          </div>
          <div class="arl-field">
            <label>Release Date <span style="color:#ef4444">*</span></label>
            <input type="date" name="release_date" id="arl-f-release-date" value="{{ old('release_date', now()->toDateString()) }}" required>
            @error('release_date')<p class="arl-field-err">{{ $message }}</p>@enderror
          </div>
        </div>

        <div class="arl-field">
          <label>Channel <span style="color:#ef4444">*</span></label>
          <select name="channel" id="arl-f-channel">
            @foreach(['stable','beta','alpha','rc'] as $ch)
              <option value="{{ $ch }}" {{ old('channel','stable') === $ch ? 'selected' : '' }}>
                {{ ucfirst($ch) }}
              </option>
            @endforeach
          </select>
          @error('channel')<p class="arl-field-err">{{ $message }}</p>@enderror
        </div>

        <div class="arl-field">
          <label class="arl-check-row" style="text-transform:none;letter-spacing:0;font-weight:normal;cursor:pointer">
            <input type="checkbox" name="is_latest" id="arl-f-is-latest" value="1" {{ old('is_latest',1) ? 'checked' : '' }}>
            <span><strong>Mark as latest</strong> — this version will appear in the app's update checker</span>
          </label>
        </div>

        <div class="arl-field">
          <label>Release Notes</label>
          <textarea name="notes" id="arl-f-notes" rows="4"
                    placeholder="One note per line, e.g.&#10;Added About modal with live version info&#10;Fixed update download progress bar">{{ old('notes') }}</textarea>
          <p class="arl-field-hint">One bullet point per line. Shown inside the desktop app update prompt.</p>
          @error('notes')<p class="arl-field-err">{{ $message }}</p>@enderror
        </div>

        <div class="arl-field">
          <label><i class="fa fa-brands fa-windows" style="width:14px;text-align:center"></i> Windows Download URL</label>
          <input type="url" name="windows_url" id="arl-f-windows-url" value="{{ old('windows_url') }}"
                 placeholder="https://downloads.sourceforge.net/…/Zeebroo POS-Setup-5.0.0-x64.exe">
          @error('windows_url')<p class="arl-field-err">{{ $message }}</p>@enderror
        </div>

        <div class="arl-field">
          <label><i class="fa fa-brands fa-apple" style="width:14px;text-align:center"></i> macOS Download URL</label>
          <input type="url" name="macos_url" id="arl-f-macos-url" value="{{ old('macos_url') }}"
                 placeholder="https://downloads.sourceforge.net/…/Zeebroo POS-5.0.0-arm64.zip">
          @error('macos_url')<p class="arl-field-err">{{ $message }}</p>@enderror
        </div>

        <div class="arl-field">
          <label><i class="fa fa-brands fa-linux" style="width:14px;text-align:center"></i> Linux Download URL</label>
          <input type="url" name="linux_url" id="arl-f-linux-url" value="{{ old('linux_url') }}"
                 placeholder="https://downloads.sourceforge.net/…/Zeebroo POS-5.0.0-x64.AppImage">
          @error('linux_url')<p class="arl-field-err">{{ $message }}</p>@enderror
        </div>

      </div>
      <div class="arl-modal-foot">
        <button type="button" class="arl-cancel-btn" id="arl-modal-cancel">Cancel</button>
        <button type="submit" class="arl-submit-btn" id="arl-submit-btn">
          <i class="fa fa-rocket" id="arl-submit-icon"></i> <span id="arl-submit-label">{{ $arlIsEditReopen ? 'Save Changes' : 'Publish Release' }}</span>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  var modal      = document.getElementById('arl-modal');
  var backdrop   = document.getElementById('arl-modal-backdrop');
  var closeBtn   = document.getElementById('arl-modal-close');
  var cancelBtn  = document.getElementById('arl-modal-cancel');
  var openBtn    = document.getElementById('arl-open-create');
  var form       = document.getElementById('arl-form');
  var methodEl   = document.getElementById('arl-form-method');
  var releaseIdEl= document.getElementById('arl-form-release-id');
  var title      = document.getElementById('arl-modal-title');
  var sub        = document.getElementById('arl-modal-sub');
  var submitLabel= document.getElementById('arl-submit-label');
  var submitIcon = document.getElementById('arl-submit-icon');

  var versionEl     = document.getElementById('arl-f-version');
  var releaseDateEl = document.getElementById('arl-f-release-date');
  var channelEl     = document.getElementById('arl-f-channel');
  var isLatestEl    = document.getElementById('arl-f-is-latest');
  var notesEl       = document.getElementById('arl-f-notes');
  var windowsEl     = document.getElementById('arl-f-windows-url');
  var macosEl       = document.getElementById('arl-f-macos-url');
  var linuxEl       = document.getElementById('arl-f-linux-url');

  function openModal() { modal.classList.add('is-open'); modal.scrollTop = 0; document.body.style.overflow = 'hidden'; }
  function closeModal() { modal.classList.remove('is-open'); document.body.style.overflow = ''; }

  function setCreateMode() {
    methodEl.value = 'POST';
    releaseIdEl.value = '';
    form.action = '{{ route('admin.releases.store') }}';
    title.textContent = 'New Release';
    sub.textContent = 'Publish a new desktop app version.';
    submitLabel.textContent = 'Publish Release';
    submitIcon.className = 'fa fa-rocket';

    versionEl.value = '5.0.0';
    releaseDateEl.value = new Date().toISOString().slice(0, 10);
    channelEl.value = 'stable';
    isLatestEl.checked = true;
    notesEl.value = '';
    windowsEl.value = '';
    macosEl.value = '';
    linuxEl.value = '';
  }

  openBtn.addEventListener('click', function () { setCreateMode(); openModal(); });
  closeBtn.addEventListener('click', closeModal);
  cancelBtn.addEventListener('click', closeModal);
  backdrop.addEventListener('click', closeModal);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
  });

  document.querySelectorAll('.arl-edit-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id = btn.getAttribute('data-id');

      methodEl.value = 'PUT';
      releaseIdEl.value = id;
      form.action = '{{ url('/admin/releases') }}/' + id;
      title.textContent = 'Edit Release';
      sub.textContent = 'Update v' + btn.getAttribute('data-version') + '.';
      submitLabel.textContent = 'Save Changes';
      submitIcon.className = 'fa fa-floppy-disk';

      versionEl.value = btn.getAttribute('data-version');
      releaseDateEl.value = btn.getAttribute('data-release-date');
      channelEl.value = btn.getAttribute('data-channel');
      isLatestEl.checked = btn.getAttribute('data-is-latest') === '1';
      notesEl.value = btn.getAttribute('data-notes');
      windowsEl.value = btn.getAttribute('data-windows-url');
      macosEl.value = btn.getAttribute('data-macos-url');
      linuxEl.value = btn.getAttribute('data-linux-url');

      openModal();
    });
  });
})();
</script>
@endsection
