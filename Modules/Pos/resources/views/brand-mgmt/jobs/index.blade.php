@extends('theme::layouts.app', ['title' => 'Jobs', 'heading' => 'Jobs'])

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
        <form method="get" action="{{ route('pos.brand-mgmt.jobs.index') }}" class="bmg-search">
            <input type="search" name="q" value="{{ $search }}" placeholder="Search name, ref, brand…" autocomplete="off">
            <select name="status" onchange="this.form.submit()">
                <option value="">All statuses</option>
                @foreach(['pending','in_progress','completed','cancelled'] as $s)
                    <option value="{{ $s }}" @selected($status === $s)>{{ ucfirst(str_replace('_',' ', $s)) }}</option>
                @endforeach
            </select>
            <button type="submit" class="linkbtn" style="padding:8px 10px;font-size:13px;"><i class="fa fa-search"></i></button>
            @if($search || $status)
                <a href="{{ route('pos.brand-mgmt.jobs.index') }}" class="linkbtn" style="padding:8px 12px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">Clear</a>
            @endif
        </form>
        <div class="bmg-toolbar-actions">
            <button type="button" class="bmg-btn-ghost" id="bmg-import-btn"><i class="fa fa-file-csv"></i> Import CSV</button>
            <button type="button" class="linkbtn" id="bmg-add-btn" style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
                <i class="fa fa-plus"></i> Add job
            </button>
        </div>
    </div>

    <div class="bmg-table-wrap">
        @if($jobs->isEmpty())
            <div class="bmg-empty">
                <i class="fa fa-briefcase" aria-hidden="true"></i>
                <p>{{ $search || $status ? 'No jobs matched your filters.' : 'No jobs yet. Add your first job to get started.' }}</p>
            </div>
        @else
        <table class="bmg-table">
            <thead>
                <tr><th>Ref</th><th>Name</th><th>Brand</th><th>Officer</th><th>Reporter</th><th>Start date</th><th>Status</th><th style="text-align:right;">Actions</th></tr>
            </thead>
            <tbody>
                @foreach($jobs as $job)
                <tr>
                    <td><span class="bmg-badge">{{ $job['job_ref'] }}</span></td>
                    <td><strong style="color:var(--text);">{{ $job['name'] }}</strong></td>
                    <td style="font-size:12px;">{{ $job['client_brand_name'] ?: '—' }}</td>
                    <td style="font-size:12px;">{{ $job['officer_name'] ?: '—' }}</td>
                    <td style="font-size:12px;">{{ $job['reporter_name'] ?: '—' }}</td>
                    <td style="font-size:12px;">{{ $job['start_date'] ?: '—' }}</td>
                    <td><span class="bmg-badge bmg-badge--{{ $job['status'] }}">{{ ucfirst(str_replace('_',' ', $job['status'])) }}</span></td>
                    <td style="text-align:right;">
                        <div class="bmg-actions">
                            <button type="button" class="bmg-action-btn bmg-action-btn--edit"
                                data-edit
                                data-id="{{ $job['id'] }}"
                                data-name="{{ e($job['name']) }}"
                                data-client_brand_id="{{ $job['client_brand_id'] }}"
                                data-officer_id="{{ $job['officer_id'] }}"
                                data-reporter_id="{{ $job['reporter_id'] }}"
                                data-description="{{ e($job['description'] ?? '') }}"
                                data-status="{{ $job['status'] }}"
                                data-start_date="{{ $job['start_date'] }}"
                            ><i class="fa fa-pen-to-square"></i> Edit</button>
                            <form method="post" action="{{ route('pos.brand-mgmt.jobs.destroy', $job['id']) }}" onsubmit="return confirm('Delete {{ addslashes($job['name']) }}?');" style="margin:0;">
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
        {{ $jobs->count() }} job{{ $jobs->count() === 1 ? '' : 's' }}
    </p>
</div>

<div id="bmg-modal" class="bmg-modal" role="dialog" aria-modal="true" aria-labelledby="bmg-modal-title" aria-hidden="true">
    <div class="bmg-modal__backdrop" id="bmg-modal-backdrop"></div>
    <div class="bmg-modal__panel">
        <div class="bmg-modal__head">
            <h3 id="bmg-modal-title">Add job</h3>
            <button type="button" class="bmg-modal__close" id="bmg-modal-close" aria-label="Close"><i class="fa fa-times"></i></button>
        </div>
        <form id="bmg-form" method="post" action="{{ route('pos.brand-mgmt.jobs.store') }}">
            @csrf
            <input type="hidden" name="_method" id="bmg-form-method" value="POST">
            <div class="bmg-modal__body">
                <div class="bmg-modal__grid">
                    <div class="bmg-field">
                        <label for="bmg-name">Name <span style="color:#f87171;">*</span></label>
                        <input type="text" name="name" id="bmg-name" required maxlength="200">
                    </div>
                    <div class="bmg-field">
                        <label for="bmg-client_brand_id">Brand</label>
                        <select name="client_brand_id" id="bmg-client_brand_id">
                            <option value="">— none —</option>
                            @foreach($brands as $b)
                                <option value="{{ $b->id }}">{{ $b->name }} ({{ $b->short_code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="bmg-field">
                        <label for="bmg-officer_id">Officer</label>
                        <select name="officer_id" id="bmg-officer_id">
                            <option value="">— none —</option>
                            @foreach($officers as $o)
                                <option value="{{ $o->id }}">{{ $o->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="bmg-field">
                        <label for="bmg-reporter_id">Reporter</label>
                        <select name="reporter_id" id="bmg-reporter_id">
                            <option value="">— none —</option>
                            @foreach($reporters as $r)
                                <option value="{{ $r->id }}">{{ $r->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="bmg-field">
                        <label for="bmg-status">Status <span style="color:#f87171;">*</span></label>
                        <select name="status" id="bmg-status" required>
                            <option value="pending">Pending</option>
                            <option value="in_progress">In progress</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="bmg-field">
                        <label for="bmg-start_date">Start date</label>
                        <input type="date" name="start_date" id="bmg-start_date">
                    </div>
                </div>
                <div class="bmg-field">
                    <label for="bmg-description">Description</label>
                    <textarea name="description" id="bmg-description" rows="3" maxlength="2000" style="resize:vertical;font-family:inherit;"></textarea>
                </div>
            </div>
            <div class="bmg-modal__foot">
                <button type="button" class="bmg-btn-ghost" id="bmg-modal-cancel">Cancel</button>
                <button type="submit" class="bmg-btn-primary"><i class="fa fa-floppy-disk"></i> Save job</button>
            </div>
        </form>
    </div>
</div>

{{-- Import CSV Modal --}}
<div id="bmg-import-modal" class="bmg-modal" role="dialog" aria-modal="true" aria-labelledby="bmg-import-title" aria-hidden="true">
    <div class="bmg-modal__backdrop" id="bmg-import-backdrop"></div>
    <div class="bmg-modal__panel" style="width:min(100%,420px);">
        <div class="bmg-modal__head">
            <h3 id="bmg-import-title">Import jobs from CSV</h3>
            <button type="button" class="bmg-modal__close" id="bmg-import-close" aria-label="Close"><i class="fa fa-times"></i></button>
        </div>
        <form method="post" action="{{ route('pos.brand-mgmt.jobs.import') }}" enctype="multipart/form-data">
            @csrf
            <div class="bmg-modal__body">
                <p class="muted" style="font-size:12px;margin:0;">
                    Expected columns: <code>name, brand_code, status, start_date, description, officer, reporter</code>. Brand/officer/reporter are matched by code or name; unmatched values are still imported with a note. Up to 500 rows.
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
    var baseAction = @json(route('pos.brand-mgmt.jobs.store'));
    var updateBase = @json(url('/pos/brand-mgmt/jobs'));
    var selectFields = ['client_brand_id','officer_id','reporter_id','status'];
    var textFields = ['name','description','start_date'];

    function setOpen(el, open) {
        el.classList.toggle('is-open', open);
        el.setAttribute('aria-hidden', open ? 'false' : 'true');
    }
    function closeModal() {
        setOpen(modal, false);
        form.reset();
        form.action = baseAction;
        methodEl.value = 'POST';
        titleEl.textContent = 'Add job';
    }
    document.getElementById('bmg-add-btn').addEventListener('click', function () { closeModal(); setOpen(modal, true); document.getElementById('bmg-name').focus(); });
    document.getElementById('bmg-modal-close').addEventListener('click', closeModal);
    document.getElementById('bmg-modal-cancel').addEventListener('click', closeModal);
    document.getElementById('bmg-modal-backdrop').addEventListener('click', closeModal);

    document.querySelectorAll('[data-edit]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            form.action = updateBase + '/' + btn.dataset.id;
            methodEl.value = 'PUT';
            titleEl.textContent = 'Edit job';
            textFields.forEach(function (f) {
                var el = document.getElementById('bmg-' + f);
                if (el) el.value = btn.dataset[f] || '';
            });
            selectFields.forEach(function (f) {
                var el = document.getElementById('bmg-' + f);
                if (el) el.value = (btn.dataset[f] && btn.dataset[f] !== '') ? btn.dataset[f] : '';
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
