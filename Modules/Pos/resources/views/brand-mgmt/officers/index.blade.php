@extends('theme::layouts.app', ['title' => 'Officers', 'heading' => 'Officers'])

@section('content')
@include('product::partials.catalog-hub-styles')
@include('pos::brand-mgmt.partials.crud-styles')

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('pos::partials.brand-mgmt-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="pcat-banner pcat-banner--err" style="font-weight:600;">{{ $errors->first() }}</div>
    @endif

    <div class="bmg-toolbar">
        <form method="get" action="{{ route('pos.brand-mgmt.officers.index') }}" class="bmg-search">
            <input type="search" name="q" value="{{ $search }}" placeholder="Search name or email…" autocomplete="off">
            <button type="submit" class="linkbtn" style="padding:8px 10px;font-size:13px;"><i class="fa fa-search"></i></button>
            @if($search)
                <a href="{{ route('pos.brand-mgmt.officers.index') }}" class="linkbtn" style="padding:8px 12px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">Clear</a>
            @endif
        </form>
        <button type="button" class="linkbtn" id="bmg-add-btn" style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
            <i class="fa fa-plus"></i> Add officer
        </button>
    </div>

    <div class="bmg-table-wrap">
        @if($officers->isEmpty())
            <div class="bmg-empty">
                <i class="fa fa-user-shield" aria-hidden="true"></i>
                <p>{{ $search ? 'No officers matched "'.e($search).'".' : 'No officers yet. Add your first officer to get started.' }}</p>
            </div>
        @else
        <table class="bmg-table">
            <thead>
                <tr><th>Name</th><th>Email</th><th style="text-align:right;">Actions</th></tr>
            </thead>
            <tbody>
                @foreach($officers as $officer)
                <tr>
                    <td><strong style="color:var(--text);">{{ $officer->name }}</strong></td>
                    <td style="font-size:12px;">{{ $officer->email }}</td>
                    <td style="text-align:right;">
                        <div class="bmg-actions">
                            <button type="button" class="bmg-action-btn bmg-action-btn--edit"
                                data-edit
                                data-id="{{ $officer->id }}"
                                data-name="{{ e($officer->name) }}"
                                data-email="{{ e($officer->email) }}"
                            ><i class="fa fa-pen-to-square"></i> Edit</button>
                            <form method="post" action="{{ route('pos.brand-mgmt.officers.destroy', $officer) }}" onsubmit="return confirm('Delete {{ addslashes($officer->name) }}?');" style="margin:0;">
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
        {{ $officers->count() }} officer{{ $officers->count() === 1 ? '' : 's' }}{{ $search ? ' matching "'.e($search).'"' : '' }}
    </p>
</div>

<div id="bmg-modal" class="bmg-modal" role="dialog" aria-modal="true" aria-labelledby="bmg-modal-title" aria-hidden="true">
    <div class="bmg-modal__backdrop" id="bmg-modal-backdrop"></div>
    <div class="bmg-modal__panel">
        <div class="bmg-modal__head">
            <h3 id="bmg-modal-title">Add officer</h3>
            <button type="button" class="bmg-modal__close" id="bmg-modal-close" aria-label="Close"><i class="fa fa-times"></i></button>
        </div>
        <form id="bmg-form" method="post" action="{{ route('pos.brand-mgmt.officers.store') }}">
            @csrf
            <input type="hidden" name="_method" id="bmg-form-method" value="POST">
            <div class="bmg-modal__body">
                <p class="muted" style="font-size:12px;margin:0;">Adding an officer also creates a portal login they can use to sign in.</p>
                <div class="bmg-field">
                    <label for="bmg-name">Name <span style="color:#f87171;">*</span></label>
                    <input type="text" name="name" id="bmg-name" required maxlength="150">
                </div>
                <div class="bmg-field">
                    <label for="bmg-email">Email <span style="color:#f87171;">*</span></label>
                    <input type="email" name="email" id="bmg-email" required maxlength="150">
                </div>
                <div class="bmg-field">
                    <label for="bmg-password" id="bmg-password-label">Password <span style="color:#f87171;">*</span></label>
                    <input type="password" name="password" id="bmg-password" minlength="8" maxlength="100" autocomplete="new-password">
                </div>
            </div>
            <div class="bmg-modal__foot">
                <button type="button" class="bmg-btn-ghost" id="bmg-modal-cancel">Cancel</button>
                <button type="submit" class="bmg-btn-primary"><i class="fa fa-floppy-disk"></i> Save officer</button>
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
    var pwdInput   = document.getElementById('bmg-password');
    var pwdLabel   = document.getElementById('bmg-password-label');
    var baseAction = @json(route('pos.brand-mgmt.officers.store'));
    var updateBase = @json(url('/pos/brand-mgmt/officers'));

    function setOpen(open) {
        modal.classList.toggle('is-open', open);
        modal.setAttribute('aria-hidden', open ? 'false' : 'true');
    }
    function closeModal() {
        setOpen(false);
        form.reset();
        form.action = baseAction;
        methodEl.value = 'POST';
        titleEl.textContent = 'Add officer';
        pwdInput.required = true;
        pwdLabel.innerHTML = 'Password <span style="color:#f87171;">*</span>';
        pwdInput.placeholder = '';
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
            titleEl.textContent = 'Edit officer';
            document.getElementById('bmg-name').value = btn.dataset.name || '';
            document.getElementById('bmg-email').value = btn.dataset.email || '';
            pwdInput.value = '';
            pwdInput.required = false;
            pwdLabel.textContent = 'Password';
            pwdInput.placeholder = 'Leave blank to keep unchanged';
            setOpen(true);
        });
    });
})();
</script>
@endsection
