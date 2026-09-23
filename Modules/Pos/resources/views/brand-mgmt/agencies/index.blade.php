@extends('theme::layouts.app', ['title' => 'Agencies', 'heading' => 'Agencies'])

@section('content')
@include('product::partials.catalog-hub-styles')
@include('pos::brand-mgmt.partials.crud-styles')

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('pos::partials.brand-mgmt-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif

    <div class="bmg-toolbar">
        <form method="get" action="{{ route('pos.brand-mgmt.agencies.index') }}" class="bmg-search">
            <input type="search" name="q" value="{{ $search }}" placeholder="Search name, contact, email…" autocomplete="off">
            <button type="submit" class="linkbtn" style="padding:8px 10px;font-size:13px;"><i class="fa fa-search"></i></button>
            @if($search)
                <a href="{{ route('pos.brand-mgmt.agencies.index') }}" class="linkbtn" style="padding:8px 12px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">Clear</a>
            @endif
        </form>
        <button type="button" class="linkbtn" id="bmg-add-btn" style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
            <i class="fa fa-plus"></i> Add agency
        </button>
    </div>

    <div class="bmg-table-wrap">
        @if($agencies->isEmpty())
            <div class="bmg-empty">
                <i class="fa fa-building" aria-hidden="true"></i>
                <p>{{ $search ? 'No agencies matched "'.e($search).'".' : 'No agencies yet. Add your first agency to get started.' }}</p>
            </div>
        @else
        <table class="bmg-table">
            <thead>
                <tr><th>Name</th><th>Contact</th><th>Email</th><th>Phone</th><th>Status</th><th style="text-align:right;">Actions</th></tr>
            </thead>
            <tbody>
                @foreach($agencies as $agency)
                <tr>
                    <td><strong style="color:var(--text);">{{ $agency['name'] }}</strong></td>
                    <td style="font-size:12px;">{{ $agency['contact_person'] ?: '—' }}</td>
                    <td style="font-size:12px;">{{ $agency['email'] ?: '—' }}</td>
                    <td style="font-size:12px;">{{ $agency['phone'] ?: '—' }}</td>
                    <td><span class="bmg-badge bmg-badge--{{ $agency['status'] }}">{{ ucfirst($agency['status']) }}</span></td>
                    <td style="text-align:right;">
                        <div class="bmg-actions">
                            <button type="button" class="bmg-action-btn bmg-action-btn--edit"
                                data-edit
                                data-id="{{ $agency['id'] }}"
                                data-name="{{ e($agency['name']) }}"
                                data-contact_person="{{ e($agency['contact_person'] ?? '') }}"
                                data-email="{{ e($agency['email'] ?? '') }}"
                                data-phone="{{ e($agency['phone'] ?? '') }}"
                                data-address="{{ e($agency['address'] ?? '') }}"
                                data-status="{{ $agency['status'] }}"
                            ><i class="fa fa-pen-to-square"></i> Edit</button>
                            <form method="post" action="{{ route('pos.brand-mgmt.agencies.destroy', $agency['id']) }}" onsubmit="return confirm('Delete {{ addslashes($agency['name']) }}?');" style="margin:0;">
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
        {{ $agencies->count() }} agenc{{ $agencies->count() === 1 ? 'y' : 'ies' }}{{ $search ? ' matching "'.e($search).'"' : '' }}
    </p>
</div>

<div id="bmg-modal" class="bmg-modal" role="dialog" aria-modal="true" aria-labelledby="bmg-modal-title" aria-hidden="true">
    <div class="bmg-modal__backdrop" id="bmg-modal-backdrop"></div>
    <div class="bmg-modal__panel">
        <div class="bmg-modal__head">
            <h3 id="bmg-modal-title">Add agency</h3>
            <button type="button" class="bmg-modal__close" id="bmg-modal-close" aria-label="Close"><i class="fa fa-times"></i></button>
        </div>
        <form id="bmg-form" method="post" action="{{ route('pos.brand-mgmt.agencies.store') }}">
            @csrf
            <input type="hidden" name="_method" id="bmg-form-method" value="POST">
            <div class="bmg-modal__body">
                <div class="bmg-modal__grid">
                    <div class="bmg-field">
                        <label for="bmg-name">Name <span style="color:#f87171;">*</span></label>
                        <input type="text" name="name" id="bmg-name" required maxlength="200">
                    </div>
                    <div class="bmg-field">
                        <label for="bmg-contact_person">Contact person</label>
                        <input type="text" name="contact_person" id="bmg-contact_person" maxlength="150">
                    </div>
                    <div class="bmg-field">
                        <label for="bmg-email">Email</label>
                        <input type="email" name="email" id="bmg-email" maxlength="150">
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
                </div>
                <div class="bmg-field">
                    <label for="bmg-address">Address</label>
                    <textarea name="address" id="bmg-address" rows="2" maxlength="300" style="resize:vertical;font-family:inherit;"></textarea>
                </div>
            </div>
            <div class="bmg-modal__foot">
                <button type="button" class="bmg-btn-ghost" id="bmg-modal-cancel">Cancel</button>
                <button type="submit" class="bmg-btn-primary"><i class="fa fa-floppy-disk"></i> Save agency</button>
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
    var baseAction = @json(route('pos.brand-mgmt.agencies.store'));
    var updateBase = @json(url('/pos/brand-mgmt/agencies'));
    var fields = ['name','contact_person','email','phone','address','status'];

    function setOpen(open) {
        modal.classList.toggle('is-open', open);
        modal.setAttribute('aria-hidden', open ? 'false' : 'true');
    }
    function closeModal() {
        setOpen(false);
        form.reset();
        form.action = baseAction;
        methodEl.value = 'POST';
        titleEl.textContent = 'Add agency';
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
            titleEl.textContent = 'Edit agency';
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
