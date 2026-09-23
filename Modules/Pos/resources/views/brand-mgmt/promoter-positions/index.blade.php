@extends('theme::layouts.app', ['title' => 'Promoter Positions', 'heading' => 'Promoter Positions'])

@section('content')
@include('product::partials.catalog-hub-styles')
@include('pos::brand-mgmt.partials.crud-styles')

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('pos::partials.brand-mgmt-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif

    <div class="bmg-toolbar">
        <form method="get" action="{{ route('pos.brand-mgmt.promoter-positions.index') }}" class="bmg-search">
            <input type="search" name="q" value="{{ $search }}" placeholder="Search positions…" autocomplete="off">
            <button type="submit" class="linkbtn" style="padding:8px 10px;font-size:13px;"><i class="fa fa-search"></i></button>
            @if($search)
                <a href="{{ route('pos.brand-mgmt.promoter-positions.index') }}" class="linkbtn" style="padding:8px 12px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">Clear</a>
            @endif
        </form>
        <button type="button" class="linkbtn" id="bmg-add-btn" style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
            <i class="fa fa-plus"></i> Add position
        </button>
    </div>

    <div class="bmg-table-wrap">
        @if($positions->isEmpty())
            <div class="bmg-empty">
                <i class="fa fa-list" aria-hidden="true"></i>
                <p>{{ $search ? 'No positions matched "'.e($search).'".' : 'No promoter positions yet. Add one to get started.' }}</p>
            </div>
        @else
        <table class="bmg-table">
            <thead>
                <tr><th>Name</th><th>Description</th><th style="text-align:right;">Actions</th></tr>
            </thead>
            <tbody>
                @foreach($positions as $position)
                <tr>
                    <td><strong style="color:var(--text);">{{ $position['name'] }}</strong></td>
                    <td style="font-size:12px;color:var(--muted);">{{ $position['description'] ?: '—' }}</td>
                    <td style="text-align:right;">
                        <div class="bmg-actions">
                            <button type="button" class="bmg-action-btn bmg-action-btn--edit"
                                data-edit
                                data-id="{{ $position['id'] }}"
                                data-name="{{ e($position['name']) }}"
                                data-description="{{ e($position['description'] ?? '') }}"
                            ><i class="fa fa-pen-to-square"></i> Edit</button>
                            <form method="post" action="{{ route('pos.brand-mgmt.promoter-positions.destroy', $position['id']) }}" onsubmit="return confirm('Delete {{ addslashes($position['name']) }}?');" style="margin:0;">
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
        {{ $positions->count() }} position{{ $positions->count() === 1 ? '' : 's' }}{{ $search ? ' matching "'.e($search).'"' : '' }}
    </p>
</div>

<div id="bmg-modal" class="bmg-modal" role="dialog" aria-modal="true" aria-labelledby="bmg-modal-title" aria-hidden="true">
    <div class="bmg-modal__backdrop" id="bmg-modal-backdrop"></div>
    <div class="bmg-modal__panel">
        <div class="bmg-modal__head">
            <h3 id="bmg-modal-title">Add position</h3>
            <button type="button" class="bmg-modal__close" id="bmg-modal-close" aria-label="Close"><i class="fa fa-times"></i></button>
        </div>
        <form id="bmg-form" method="post" action="{{ route('pos.brand-mgmt.promoter-positions.store') }}">
            @csrf
            <input type="hidden" name="_method" id="bmg-form-method" value="POST">
            <div class="bmg-modal__body">
                <div class="bmg-field">
                    <label for="bmg-name">Name <span style="color:#f87171;">*</span></label>
                    <input type="text" name="name" id="bmg-name" required maxlength="150" placeholder="e.g. Field Promoter">
                </div>
                <div class="bmg-field">
                    <label for="bmg-description">Description</label>
                    <textarea name="description" id="bmg-description" rows="3" maxlength="2000" style="resize:vertical;font-family:inherit;"></textarea>
                </div>
            </div>
            <div class="bmg-modal__foot">
                <button type="button" class="bmg-btn-ghost" id="bmg-modal-cancel">Cancel</button>
                <button type="submit" class="bmg-btn-primary"><i class="fa fa-floppy-disk"></i> Save</button>
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
    var baseAction = @json(route('pos.brand-mgmt.promoter-positions.store'));
    var updateBase = @json(url('/pos/brand-mgmt/promoter-positions'));

    function setOpen(open) {
        modal.classList.toggle('is-open', open);
        modal.setAttribute('aria-hidden', open ? 'false' : 'true');
        if (open) document.getElementById('bmg-name').focus();
    }
    function closeModal() {
        setOpen(false);
        form.reset();
        form.action = baseAction;
        methodEl.value = 'POST';
        titleEl.textContent = 'Add position';
    }
    document.getElementById('bmg-add-btn').addEventListener('click', function () { closeModal(); setOpen(true); });
    document.getElementById('bmg-modal-close').addEventListener('click', closeModal);
    document.getElementById('bmg-modal-cancel').addEventListener('click', closeModal);
    document.getElementById('bmg-modal-backdrop').addEventListener('click', closeModal);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal(); });

    document.querySelectorAll('[data-edit]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            form.action = updateBase + '/' + btn.dataset.id;
            methodEl.value = 'PUT';
            titleEl.textContent = 'Edit position';
            document.getElementById('bmg-name').value = btn.dataset.name || '';
            document.getElementById('bmg-description').value = btn.dataset.description || '';
            setOpen(true);
        });
    });
})();
</script>
@endsection
