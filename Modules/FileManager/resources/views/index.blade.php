@extends('theme::layouts.app', ['title' => 'Files', 'heading' => 'File manager'])

@php
    $uploadModalOpen = $hasAnyItems && !$isEmptyHere && $errors->any() && ($errors->has('files') || $errors->has('files.*') || $errors->has('notes'));
    $folderModalOpen = $hasAnyItems && $errors->has('name');
@endphp

@section('content')
@include('filemanager::partials.styles')

<div class="fm-page card" style="max-width:100%;padding:14px;">
    @if(session('status'))
        <div class="fm-banner fm-banner--ok">{{ session('status') }}</div>
    @endif
    @if($errors->has('folder'))
        <div class="fm-banner fm-banner--err" role="alert">{{ $errors->first('folder') }}</div>
    @endif

    <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
        Store and organize documents for <strong style="color:var(--text);">{{ $business->name }}</strong>.
    </p>

    <nav class="fm-crumbs" aria-label="Breadcrumb">
        @foreach($breadcrumbs as $i => $crumb)
            @if($i > 0)<span class="fm-crumbs__sep" aria-hidden="true">/</span>@endif
            @if($loop->last)
                <span class="fm-crumbs__current">{{ $crumb['name'] }}</span>
            @else
                <a href="{{ route('filemanager.index', array_filter(['folder' => $crumb['id']])) }}">{{ $crumb['name'] }}</a>
            @endif
        @endforeach
    </nav>

    <div class="fm-toolbar">
        <span class="muted" style="margin:0;font-size:13px;">
            @if(!$hasAnyItems)
                Upload your <strong style="color:var(--text);">first file</strong> below.
            @else
                {{ $folders->count() }} folder{{ $folders->count() === 1 ? '' : 's' }}, {{ $files->count() }} file{{ $files->count() === 1 ? '' : 's' }} here.
            @endif
        </span>
        @if($hasAnyItems)
            <div class="fm-toolbar__actions">
                <button type="button" id="fm-folder-modal-open" class="linkbtn" style="padding:8px 14px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);">
                    <i class="fa fa-folder-plus"></i> New folder
                </button>
                <button type="button" id="fm-upload-modal-open" class="linkbtn" style="padding:8px 14px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
                    <i class="fa fa-upload"></i> Upload files
                </button>
            </div>
        @endif
    </div>

    @if(!$hasAnyItems)
        <section class="fm-inline" aria-labelledby="fm-first-title">
            <h2 id="fm-first-title">Upload files</h2>
            <p class="fm-muted">Invoices, contracts, product sheets, and other business documents.</p>
            @if($errors->any())
                <div class="fm-banner fm-banner--err" style="margin-top:12px;" role="alert">{{ $errors->first() }}</div>
            @endif
            @include('filemanager::partials.upload-form', ['currentFolder' => $currentFolder, 'fieldIdPrefix' => 'inline-'])
        </section>
    @else
        @if($isEmptyHere)
            <section class="fm-inline">
                <h2>This folder is empty</h2>
                <p class="fm-muted">Upload files here or create a subfolder.</p>
            </section>
        @endif

        @if($folders->isNotEmpty())
            <h3 class="fm-section-title">Folders</h3>
            <div class="fm-grid">
                @foreach($folders as $folderRow)
                    <a href="{{ route('filemanager.index', ['folder' => $folderRow->id]) }}" class="fm-card fm-card--folder">
                        <i class="fa fa-folder fm-card__icon" aria-hidden="true"></i>
                        <span class="fm-card__name">{{ $folderRow->name }}</span>
                        <span class="fm-card__meta">Open folder</span>
                    </a>
                @endforeach
            </div>
        @endif

        @if($files->isNotEmpty())
            <h3 class="fm-section-title">Files</h3>
            <div class="fm-grid">
                @foreach($files as $fileRow)
                    <article class="fm-card">
                        @if($fileRow->isImage())
                            <img src="{{ $fileRow->publicUrl() }}" alt="" class="fm-card__thumb" loading="lazy">
                        @else
                            <i class="fa fa-file-lines fm-card__icon" aria-hidden="true"></i>
                        @endif
                        <span class="fm-card__name" title="{{ $fileRow->original_filename }}">{{ \Illuminate\Support\Str::limit($fileRow->original_filename, 42) }}</span>
                        <span class="fm-card__meta">{{ $fileRow->humanSize() }} · {{ $fileRow->created_at?->format('M j, Y') }}</span>
                        @if($fileRow->notes)
                            <span class="fm-card__meta" style="margin-top:-4px;">{{ \Illuminate\Support\Str::limit($fileRow->notes, 60) }}</span>
                        @endif
                        <div class="fm-card__actions">
                            <a class="fm-link" href="{{ route('filemanager.files.download', $fileRow) }}"><i class="fa fa-download"></i> Download</a>
                            <form method="post" action="{{ route('filemanager.files.destroy', $fileRow) }}" style="margin:0;display:inline;" onsubmit="return confirm('Delete this file?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="fm-btn-del"><i class="fa fa-trash-can"></i></button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif

        @if($folders->isNotEmpty())
            <div style="margin-top:16px;border-top:1px solid var(--border);padding-top:12px;">
                <h3 class="fm-section-title" style="margin-top:0;">Folder actions</h3>
                <ul style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:6px;">
                    @foreach($folders as $folderRow)
                        <li style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;font-size:13px;">
                            <i class="fa fa-folder" style="color:var(--muted);"></i>
                            <a class="fm-link" href="{{ route('filemanager.index', ['folder' => $folderRow->id]) }}">{{ $folderRow->name }}</a>
                            <form method="post" action="{{ route('filemanager.folders.destroy', $folderRow) }}" style="margin:0;margin-left:auto;" onsubmit="return confirm('Delete folder “{{ $folderRow->name }}”? It must be empty.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="fm-btn-del"><i class="fa fa-trash-can"></i> Delete folder</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div id="fm-upload-modal" class="fm-modal {{ $uploadModalOpen ? 'fm-modal--open' : '' }}" role="dialog" aria-modal="true" aria-labelledby="fm-upload-modal-title" aria-hidden="{{ $uploadModalOpen ? 'false' : 'true' }}">
            <div class="fm-modal__backdrop" data-fm-close tabindex="-1"></div>
            <div class="fm-modal__panel">
                <div class="fm-modal__head">
                    <h2 id="fm-upload-modal-title">Upload files</h2>
                    <button type="button" class="fm-modal__close" data-fm-close aria-label="Close">&times;</button>
                </div>
                <div class="fm-modal__body">
                    @if($errors->any() && ($errors->has('files') || $errors->has('files.*') || $errors->has('notes')))
                        <div class="fm-banner fm-banner--err" role="alert">{{ $errors->first() }}</div>
                    @endif
                    @include('filemanager::partials.upload-form', ['currentFolder' => $currentFolder, 'fieldIdPrefix' => 'modal-'])
                </div>
            </div>
        </div>

        <div id="fm-folder-modal" class="fm-modal {{ $folderModalOpen ? 'fm-modal--open' : '' }}" role="dialog" aria-modal="true" aria-labelledby="fm-folder-modal-title" aria-hidden="{{ $folderModalOpen ? 'false' : 'true' }}">
            <div class="fm-modal__backdrop" data-fm-close tabindex="-1"></div>
            <div class="fm-modal__panel">
                <div class="fm-modal__head">
                    <h2 id="fm-folder-modal-title">New folder</h2>
                    <button type="button" class="fm-modal__close" data-fm-close aria-label="Close">&times;</button>
                </div>
                <div class="fm-modal__body">
                    @include('filemanager::partials.folder-form', ['currentFolder' => $currentFolder, 'fieldIdPrefix' => 'modal-'])
                </div>
            </div>
        </div>
    @endif
</div>

<div style="margin-top:14px;">
    <a href="{{ route('dashboard') }}" class="linkbtn" style="padding:7px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fa fa-arrow-left"></i> Overview
    </a>
</div>

<script>
(function () {
    @if($hasAnyItems)
    function bindModal(modalId, openId) {
        var modal = document.getElementById(modalId);
        var openBtn = document.getElementById(openId);
        if (!modal) return;
        function lock(on) { document.documentElement.classList.toggle('fm-modal-open-html', Boolean(on)); }
        function openM() {
            modal.classList.add('fm-modal--open');
            modal.setAttribute('aria-hidden', 'false');
            lock(true);
        }
        function closeM() {
            modal.classList.remove('fm-modal--open');
            modal.setAttribute('aria-hidden', 'true');
            lock(false);
        }
        openBtn && openBtn.addEventListener('click', openM);
        modal.querySelectorAll('[data-fm-close]').forEach(function (el) { el.addEventListener('click', closeM); });
        if (modal.classList.contains('fm-modal--open')) lock(true);
    }
    bindModal('fm-upload-modal', 'fm-upload-modal-open');
    bindModal('fm-folder-modal', 'fm-folder-modal-open');
    @endif

    document.querySelectorAll('.fm-upload-zone').forEach(function (zone) {
        var input = zone.querySelector('input[type="file"]');
        var list = document.getElementById(input && input.id ? input.id + '-list' : '');
        if (!input) return;
        function syncList() {
            if (!list) return;
            var names = Array.from(input.files || []).map(function (f) { return f.name; });
            if (!names.length) { list.hidden = true; list.textContent = ''; return; }
            list.hidden = false;
            list.textContent = names.length + ' file(s): ' + names.join(', ');
        }
        input.addEventListener('change', syncList);
        zone.addEventListener('dragover', function (e) { e.preventDefault(); zone.classList.add('is-dragover'); });
        zone.addEventListener('dragleave', function () { zone.classList.remove('is-dragover'); });
        zone.addEventListener('drop', function (e) {
            e.preventDefault();
            zone.classList.remove('is-dragover');
            if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                syncList();
            }
        });
    });
})();
</script>
@endsection
