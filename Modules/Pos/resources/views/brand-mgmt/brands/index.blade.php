@extends('theme::layouts.app', ['title' => 'Brands', 'heading' => 'Client Brands'])

@section('content')
@include('product::partials.catalog-hub-styles')
@include('pos::brand-mgmt.partials.crud-styles')

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('pos::partials.brand-mgmt-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->any() && !session('import_results'))
        <div class="pcat-banner pcat-banner--err" style="font-weight:600;">{{ $errors->first() }}</div>
    @endif

    @include('pos::brand-mgmt.partials.import-results')

    <div class="bmg-toolbar">
        <form method="get" action="{{ route('pos.brand-mgmt.brands.index') }}" class="bmg-search">
            <input type="search" name="q" value="{{ $search }}" placeholder="Search name, code, email…" autocomplete="off">
            <button type="submit" class="linkbtn" style="padding:8px 10px;font-size:13px;"><i class="fa fa-search"></i></button>
            @if($search)
                <a href="{{ route('pos.brand-mgmt.brands.index') }}" class="linkbtn" style="padding:8px 12px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">Clear</a>
            @endif
        </form>
        <div class="bmg-toolbar-actions">
            <button type="button" class="bmg-btn-ghost" id="bmg-import-btn"><i class="fa fa-file-csv"></i> Import CSV</button>
            <button type="button" class="linkbtn" id="bmg-add-btn" style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
                <i class="fa fa-plus"></i> Add brand
            </button>
        </div>
    </div>

    <div class="bmg-table-wrap">
        @if($brands->isEmpty())
            <div class="bmg-empty">
                <i class="fa fa-tag" aria-hidden="true"></i>
                <p>{{ $search ? 'No brands matched "'.e($search).'".' : 'No brands yet. Add your first brand to get started.' }}</p>
            </div>
        @else
        <table class="bmg-table">
            <thead>
                <tr><th>Name</th><th>Code</th><th>Company</th><th>Contact</th><th>Email</th><th>Phone</th><th style="text-align:right;">Actions</th></tr>
            </thead>
            <tbody>
                @foreach($brands as $brand)
                <tr>
                    <td><strong style="color:var(--text);">{{ $brand->name }}</strong></td>
                    <td><span class="bmg-badge">{{ $brand->short_code }}</span></td>
                    <td style="font-size:12px;">{{ $brand->company_name ?: '—' }}</td>
                    <td style="font-size:12px;">{{ $brand->contact_person ?: '—' }}</td>
                    <td style="font-size:12px;">{{ $brand->email ?: '—' }}</td>
                    <td style="font-size:12px;">{{ $brand->phone ?: '—' }}</td>
                    <td style="text-align:right;">
                        <div class="bmg-actions">
                            <button type="button" class="bmg-action-btn bmg-action-btn--edit"
                                data-edit
                                data-id="{{ $brand->id }}"
                                data-name="{{ e($brand->name) }}"
                                data-short_code="{{ e($brand->short_code) }}"
                                data-email="{{ e($brand->email ?? '') }}"
                                data-phone="{{ e($brand->phone ?? '') }}"
                                data-company_name="{{ e($brand->company_name ?? '') }}"
                                data-contact_person="{{ e($brand->contact_person ?? '') }}"
                                data-address="{{ e($brand->address ?? '') }}"
                            ><i class="fa fa-pen-to-square"></i> Edit</button>
                            <form method="post" action="{{ route('pos.brand-mgmt.brands.destroy', $brand) }}" onsubmit="return confirm('Delete {{ addslashes($brand->name) }}?');" style="margin:0;">
                                @csrf @method('DELETE')
                                <button type="submit" class="bmg-action-btn bmg-action-btn--del" title="Delete"><i class="fa fa-trash-can"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    <p class="muted" style="margin:10px 0 0;font-size:12px;">
        {{ $brands->count() }} brand{{ $brands->count() === 1 ? '' : 's' }}{{ $search ? ' matching "'.e($search).'"' : '' }}
    </p>
</div>

{{-- Add / Edit Modal --}}
<div id="bmg-modal" class="bmg-modal" role="dialog" aria-modal="true" aria-labelledby="bmg-modal-title" aria-hidden="true">
    <div class="bmg-modal__backdrop" id="bmg-modal-backdrop"></div>
    <div class="bmg-modal__panel">
        <div class="bmg-modal__head">
            <h3 id="bmg-modal-title">Add brand</h3>
            <button type="button" class="bmg-modal__close" id="bmg-modal-close" aria-label="Close"><i class="fa fa-times"></i></button>
        </div>
        <form id="bmg-form" method="post" action="{{ route('pos.brand-mgmt.brands.store') }}">
            @csrf
            <input type="hidden" name="_method" id="bmg-form-method" value="POST">
            <div class="bmg-modal__body">
                <div class="bmg-modal__grid">
                    <div class="bmg-field">
                        <label for="bmg-name">Name <span style="color:#f87171;">*</span></label>
                        <input type="text" name="name" id="bmg-name" required maxlength="150">
                    </div>
                    <div class="bmg-field">
                        <label for="bmg-short_code">Short code <span style="color:#f87171;">*</span></label>
                        <input type="text" name="short_code" id="bmg-short_code" required maxlength="3" minlength="3" pattern="[A-Za-z]{3}" placeholder="ABC" style="text-transform:uppercase;">
                    </div>
                    <div class="bmg-field">
                        <label for="bmg-email">Email</label>
                        <input type="email" name="email" id="bmg-email" maxlength="150">
                    </div>
                    <div class="bmg-field">
                        <label for="bmg-phone">Phone</label>
                        <input type="text" name="phone" id="bmg-phone" maxlength="100">
                    </div>
                    <div class="bmg-field">
                        <label for="bmg-company_name">Company name</label>
                        <input type="text" name="company_name" id="bmg-company_name" maxlength="150">
                    </div>
                    <div class="bmg-field">
                        <label for="bmg-contact_person">Contact person</label>
                        <input type="text" name="contact_person" id="bmg-contact_person" maxlength="150">
                    </div>
                </div>
                <div class="bmg-field">
                    <label for="bmg-address">Address</label>
                    <textarea name="address" id="bmg-address" rows="2" maxlength="500" style="resize:vertical;font-family:inherit;"></textarea>
                </div>
            </div>
            <div class="bmg-modal__foot">
                <button type="button" class="bmg-btn-ghost" id="bmg-modal-cancel">Cancel</button>
                <button type="submit" class="bmg-btn-primary"><i class="fa fa-floppy-disk"></i> Save brand</button>
            </div>
        </form>
    </div>
</div>

{{-- Import CSV Modal --}}
<div id="bmg-import-modal" class="bmg-modal" role="dialog" aria-modal="true" aria-labelledby="bmg-import-title" aria-hidden="true">
    <div class="bmg-modal__backdrop" id="bmg-import-backdrop"></div>
    <div class="bmg-modal__panel" style="width:min(100%,420px);">
        <div class="bmg-modal__head">
            <h3 id="bmg-import-title">Import brands from CSV</h3>
            <button type="button" class="bmg-modal__close" id="bmg-import-close" aria-label="Close"><i class="fa fa-times"></i></button>
        </div>
        <form method="post" action="{{ route('pos.brand-mgmt.brands.import') }}" enctype="multipart/form-data">
            @csrf
            <div class="bmg-modal__body">
                <p class="muted" style="font-size:12px;margin:0;">
                    Expected columns: <code>name, short_code, email, phone, company_name, contact_person, address</code>. Up to 500 rows.
                </p>
                <div class="bmg-field">
                    <label for="bmg-import-file">CSV file <span style="color:#f87171;">*</span></label>
                    <input type="file" name="file" id="bmg-import-file" accept=".csv,.txt" required>
                </div>
            </div>
            <div class="bmg-modal__foot">
                <button type="button" class="bmg-btn-ghost" id="bmg-import-cancel">Cancel</button>
                <button type="submit" class="bmg-btn-primary"><i class="fa fa-upload"></i> Upload &amp; import</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var modal      = document.getElementById('bmg-modal');
    var form       = document.getElementById('bmg-form');
    var titleEl    = document.getElementById('bmg-modal-title');
    var methodEl   = document.getElementById('bmg-form-method');
    var baseAction = @json(route('pos.brand-mgmt.brands.store'));
    var updateBase = @json(url('/pos/brand-mgmt/brands'));
    var fields = ['name','short_code','email','phone','company_name','contact_person','address'];

    function setOpen(el, open) {
        el.classList.toggle('is-open', open);
        el.setAttribute('aria-hidden', open ? 'false' : 'true');
    }
    function closeModal() {
        setOpen(modal, false);
        form.reset();
        form.action = baseAction;
        methodEl.value = 'POST';
        titleEl.textContent = 'Add brand';
    }
    document.getElementById('bmg-add-btn').addEventListener('click', function () { closeModal(); setOpen(modal, true); document.getElementById('bmg-name').focus(); });
    document.getElementById('bmg-modal-close').addEventListener('click', closeModal);
    document.getElementById('bmg-modal-cancel').addEventListener('click', closeModal);
    document.getElementById('bmg-modal-backdrop').addEventListener('click', closeModal);

    document.querySelectorAll('[data-edit]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            form.action = updateBase + '/' + btn.dataset.id;
            methodEl.value = 'PUT';
            titleEl.textContent = 'Edit brand';
            fields.forEach(function (f) {
                var el = document.getElementById('bmg-' + f);
                if (el) el.value = btn.dataset[f] || '';
            });
            setOpen(modal, true);
        });
    });

    var importModal = document.getElementById('bmg-import-modal');
    document.getElementById('bmg-import-btn').addEventListener('click', function () { setOpen(importModal, true); });
    document.getElementById('bmg-import-close').addEventListener('click', function () { setOpen(importModal, false); });
    document.getElementById('bmg-import-cancel').addEventListener('click', function () { setOpen(importModal, false); });
    document.getElementById('bmg-import-backdrop').addEventListener('click', function () { setOpen(importModal, false); });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (modal.classList.contains('is-open')) closeModal();
        if (importModal.classList.contains('is-open')) setOpen(importModal, false);
    });
})();
</script>
@endsection
