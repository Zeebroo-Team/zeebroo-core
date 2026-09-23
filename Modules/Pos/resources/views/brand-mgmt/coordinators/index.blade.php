@extends('theme::layouts.app', ['title' => 'Coordinators', 'heading' => 'Coordinators'])

@section('content')
@include('product::partials.catalog-hub-styles')
@include('pos::brand-mgmt.partials.crud-styles')

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('pos::partials.brand-mgmt-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif

    <div class="bmg-toolbar">
        <form method="get" action="{{ route('pos.brand-mgmt.coordinators.index') }}" class="bmg-search">
            <input type="search" name="q" value="{{ $search }}" placeholder="Search name, NIC, phone…" autocomplete="off">
            <button type="submit" class="linkbtn" style="padding:8px 10px;font-size:13px;"><i class="fa fa-search"></i></button>
            @if($search)
                <a href="{{ route('pos.brand-mgmt.coordinators.index') }}" class="linkbtn" style="padding:8px 12px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">Clear</a>
            @endif
        </form>
        <button type="button" class="linkbtn" id="bmg-add-btn" style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
            <i class="fa fa-plus"></i> Add coordinator
        </button>
    </div>

    <div class="bmg-table-wrap">
        @if($coordinators->isEmpty())
            <div class="bmg-empty">
                <i class="fa fa-people-arrows" aria-hidden="true"></i>
                <p>{{ $search ? 'No coordinators matched "'.e($search).'".' : 'No coordinators yet. Add your first coordinator to get started.' }}</p>
            </div>
        @else
        <table class="bmg-table">
            <thead>
                <tr><th>Name</th><th>NIC</th><th>Phone</th><th>Bank</th><th>Status</th><th style="text-align:right;">Actions</th></tr>
            </thead>
            <tbody>
                @foreach($coordinators as $coordinator)
                <tr>
                    <td><strong style="color:var(--text);">{{ $coordinator['name'] }}</strong></td>
                    <td style="font-size:12px;">{{ $coordinator['nic'] ?: '—' }}</td>
                    <td style="font-size:12px;">{{ $coordinator['phone'] ?: '—' }}</td>
                    <td style="font-size:12px;color:var(--muted);">{{ $coordinator['bank_name'] }} · {{ $coordinator['bank_branch'] }} · {{ $coordinator['bank_account'] }}</td>
                    <td><span class="bmg-badge bmg-badge--{{ $coordinator['status'] }}">{{ ucfirst($coordinator['status']) }}</span></td>
                    <td style="text-align:right;">
                        <div class="bmg-actions">
                            <button type="button" class="bmg-action-btn bmg-action-btn--edit"
                                data-edit
                                data-id="{{ $coordinator['id'] }}"
                                data-name="{{ e($coordinator['name']) }}"
                                data-nic="{{ e($coordinator['nic'] ?? '') }}"
                                data-phone="{{ e($coordinator['phone'] ?? '') }}"
                                data-bank_name="{{ e($coordinator['bank_name'] ?? '') }}"
                                data-bank_branch="{{ e($coordinator['bank_branch'] ?? '') }}"
                                data-bank_account="{{ e($coordinator['bank_account'] ?? '') }}"
                                data-status="{{ $coordinator['status'] }}"
                            ><i class="fa fa-pen-to-square"></i> Edit</button>
                            <form method="post" action="{{ route('pos.brand-mgmt.coordinators.destroy', $coordinator['id']) }}" onsubmit="return confirm('Delete {{ addslashes($coordinator['name']) }}?');" style="margin:0;">
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
        {{ $coordinators->count() }} coordinator{{ $coordinators->count() === 1 ? '' : 's' }}{{ $search ? ' matching "'.e($search).'"' : '' }}
    </p>
</div>

<div id="bmg-modal" class="bmg-modal" role="dialog" aria-modal="true" aria-labelledby="bmg-modal-title" aria-hidden="true">
    <div class="bmg-modal__backdrop" id="bmg-modal-backdrop"></div>
    <div class="bmg-modal__panel">
        <div class="bmg-modal__head">
            <h3 id="bmg-modal-title">Add coordinator</h3>
            <button type="button" class="bmg-modal__close" id="bmg-modal-close" aria-label="Close"><i class="fa fa-times"></i></button>
        </div>
        <form id="bmg-form" method="post" action="{{ route('pos.brand-mgmt.coordinators.store') }}">
            @csrf
            <input type="hidden" name="_method" id="bmg-form-method" value="POST">
            <div class="bmg-modal__body">
                <div class="bmg-modal__grid">
                    <div class="bmg-field">
                        <label for="bmg-name">Name <span style="color:#f87171;">*</span></label>
                        <input type="text" name="name" id="bmg-name" required maxlength="150">
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
                        <label for="bmg-status">Status <span style="color:#f87171;">*</span></label>
                        <select name="status" id="bmg-status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
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
                <button type="submit" class="bmg-btn-primary"><i class="fa fa-floppy-disk"></i> Save coordinator</button>
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
    var baseAction = @json(route('pos.brand-mgmt.coordinators.store'));
    var updateBase = @json(url('/pos/brand-mgmt/coordinators'));
    var fields = ['name','nic','phone','bank_name','bank_branch','bank_account','status'];

    function setOpen(open) {
        modal.classList.toggle('is-open', open);
        modal.setAttribute('aria-hidden', open ? 'false' : 'true');
    }
    function closeModal() {
        setOpen(false);
        form.reset();
        form.action = baseAction;
        methodEl.value = 'POST';
        titleEl.textContent = 'Add coordinator';
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
            titleEl.textContent = 'Edit coordinator';
            fields.forEach(function (f) {
                var el = document.getElementById('bmg-' + f);
                if (el) el.value = btn.dataset[f] || '';
            });
            setOpen(true);
        });
    });
})();
</script>
@endsection
