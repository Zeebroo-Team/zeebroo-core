@extends('theme::layouts.app', ['title' => 'Promoters', 'heading' => 'Promoters'])

@section('content')
@include('product::partials.catalog-hub-styles')
@include('pos::brand-mgmt.partials.crud-styles')

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('pos::partials.brand-mgmt-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif

    <div class="bmg-toolbar">
        <form method="get" action="{{ route('pos.brand-mgmt.promoters.index') }}" class="bmg-search">
            <input type="search" name="q" value="{{ $search }}" placeholder="Search name, position, NIC…" autocomplete="off">
            <button type="submit" class="linkbtn" style="padding:8px 10px;font-size:13px;"><i class="fa fa-search"></i></button>
            @if($search)
                <a href="{{ route('pos.brand-mgmt.promoters.index') }}" class="linkbtn" style="padding:8px 12px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">Clear</a>
            @endif
        </form>
        <button type="button" class="linkbtn" id="bmg-add-btn" style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
            <i class="fa fa-plus"></i> Add promoter
        </button>
    </div>

    <div class="bmg-table-wrap">
        @if($promoters->isEmpty())
            <div class="bmg-empty">
                <i class="fa fa-bullhorn" aria-hidden="true"></i>
                <p>{{ $search ? 'No promoters matched "'.e($search).'".' : 'No promoters yet. Add your first promoter to get started.' }}</p>
            </div>
        @else
        <table class="bmg-table">
            <thead>
                <tr><th>Name</th><th>Position</th><th>NIC</th><th>Phone</th><th>Bank</th><th style="text-align:right;">Actions</th></tr>
            </thead>
            <tbody>
                @foreach($promoters as $promoter)
                <tr>
                    <td><strong style="color:var(--text);">{{ $promoter['name'] }}</strong></td>
                    <td>@if($promoter['position'])<span class="bmg-badge">{{ $promoter['position'] }}</span>@else <span class="muted">—</span> @endif</td>
                    <td style="font-size:12px;">{{ $promoter['nic'] ?: '—' }}</td>
                    <td style="font-size:12px;">{{ $promoter['phone'] ?: '—' }}</td>
                    <td style="font-size:12px;color:var(--muted);">{{ $promoter['bank_name'] }} · {{ $promoter['bank_branch'] }} · {{ $promoter['bank_account'] }}</td>
                    <td style="text-align:right;">
                        <div class="bmg-actions">
                            <button type="button" class="bmg-action-btn bmg-action-btn--edit"
                                data-edit
                                data-id="{{ $promoter['id'] }}"
                                data-name="{{ e($promoter['name']) }}"
                                data-position="{{ e($promoter['position'] ?? '') }}"
                                data-nic="{{ e($promoter['nic'] ?? '') }}"
                                data-phone="{{ e($promoter['phone'] ?? '') }}"
                                data-bank_name="{{ e($promoter['bank_name'] ?? '') }}"
                                data-bank_branch="{{ e($promoter['bank_branch'] ?? '') }}"
                                data-bank_account="{{ e($promoter['bank_account'] ?? '') }}"
                            ><i class="fa fa-pen-to-square"></i> Edit</button>
                            <form method="post" action="{{ route('pos.brand-mgmt.promoters.destroy', $promoter['id']) }}" onsubmit="return confirm('Delete {{ addslashes($promoter['name']) }}?');" style="margin:0;">
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
        {{ $promoters->count() }} promoter{{ $promoters->count() === 1 ? '' : 's' }}{{ $search ? ' matching "'.e($search).'"' : '' }}
    </p>
</div>

<div id="bmg-modal" class="bmg-modal" role="dialog" aria-modal="true" aria-labelledby="bmg-modal-title" aria-hidden="true">
    <div class="bmg-modal__backdrop" id="bmg-modal-backdrop"></div>
    <div class="bmg-modal__panel">
        <div class="bmg-modal__head">
            <h3 id="bmg-modal-title">Add promoter</h3>
            <button type="button" class="bmg-modal__close" id="bmg-modal-close" aria-label="Close"><i class="fa fa-times"></i></button>
        </div>
        <form id="bmg-form" method="post" action="{{ route('pos.brand-mgmt.promoters.store') }}">
            @csrf
            <input type="hidden" name="_method" id="bmg-form-method" value="POST">
            <div class="bmg-modal__body">
                <div class="bmg-modal__grid">
                    <div class="bmg-field">
                        <label for="bmg-name">Name <span style="color:#f87171;">*</span></label>
                        <input type="text" name="name" id="bmg-name" required maxlength="150">
                    </div>
                    <div class="bmg-field">
                        <label for="bmg-position">Position</label>
                        <div class="bmg-field-inline">
                            <select name="position" id="bmg-position">
                                <option value="">— none —</option>
                                @foreach($positions as $p)
                                    <option value="{{ $p['name'] }}">{{ $p['name'] }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="bmg-btn-ghost bmg-btn-sm" id="bmg-position-add" title="Add a new position">+</button>
                        </div>
                    </div>
                    <div class="bmg-field">
                        <label for="bmg-nic">NIC</label>
                        <input type="text" name="nic" id="bmg-nic" maxlength="50">
                    </div>
                    <div class="bmg-field">
                        <label for="bmg-phone">Phone</label>
                        <input type="text" name="phone" id="bmg-phone" maxlength="30">
                    </div>
                    <div class="bmg-field">
                        <label for="bmg-bank_name">Bank name <span style="color:#f87171;">*</span></label>
                        <input type="text" name="bank_name" id="bmg-bank_name" required maxlength="100">
                    </div>
                    <div class="bmg-field">
                        <label for="bmg-bank_branch">Bank branch <span style="color:#f87171;">*</span></label>
                        <input type="text" name="bank_branch" id="bmg-bank_branch" required maxlength="100">
                    </div>
                    <div class="bmg-field">
                        <label for="bmg-bank_account">Bank account <span style="color:#f87171;">*</span></label>
                        <input type="text" name="bank_account" id="bmg-bank_account" required maxlength="50">
                    </div>
                </div>
            </div>
            <div class="bmg-modal__foot">
                <button type="button" class="bmg-btn-ghost" id="bmg-modal-cancel">Cancel</button>
                <button type="submit" class="bmg-btn-primary"><i class="fa fa-floppy-disk"></i> Save promoter</button>
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
    var positionEl = document.getElementById('bmg-position');
    var baseAction = @json(route('pos.brand-mgmt.promoters.store'));
    var updateBase = @json(url('/pos/brand-mgmt/promoters'));
    var quickStoreUrl = @json(route('pos.brand-mgmt.promoter-positions.quick-store'));
    var csrfToken = @json(csrf_token());
    var fields = ['name','position','nic','phone','bank_name','bank_branch','bank_account'];

    function setOpen(open) {
        modal.classList.toggle('is-open', open);
        modal.setAttribute('aria-hidden', open ? 'false' : 'true');
    }
    function closeModal() {
        setOpen(false);
        form.reset();
        form.action = baseAction;
        methodEl.value = 'POST';
        titleEl.textContent = 'Add promoter';
    }
    document.getElementById('bmg-add-btn').addEventListener('click', function () { closeModal(); setOpen(true); document.getElementById('bmg-name').focus(); });
    document.getElementById('bmg-modal-close').addEventListener('click', closeModal);
    document.getElementById('bmg-modal-cancel').addEventListener('click', closeModal);
    document.getElementById('bmg-modal-backdrop').addEventListener('click', closeModal);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal(); });

    document.querySelectorAll('[data-edit]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            form.action = updateBase + '/' + btn.dataset.id;
            methodEl.value = 'PUT';
            titleEl.textContent = 'Edit promoter';
            fields.forEach(function (f) {
                var el = document.getElementById('bmg-' + f);
                if (el) el.value = btn.dataset[f] || '';
            });
            setOpen(true);
        });
    });

    document.getElementById('bmg-position-add').addEventListener('click', function () {
        var name = window.prompt('New position name:');
        if (!name || !name.trim()) return;
        fetch(quickStoreUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ name: name.trim() }),
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (!data || !data.name) return;
            var opt = document.createElement('option');
            opt.value = data.name;
            opt.textContent = data.name;
            positionEl.appendChild(opt);
            positionEl.value = data.name;
        }).catch(function () {
            alert('Could not add the position. Please try again.');
        });
    });
})();
</script>
@endsection
