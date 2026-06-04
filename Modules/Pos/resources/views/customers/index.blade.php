@extends('theme::layouts.app', ['title' => 'Customers', 'heading' => 'POS Customers'])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.cust-toolbar{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;margin-bottom:14px;}
.cust-search{display:flex;gap:6px;flex-wrap:wrap;}
.cust-search input{box-sizing:border-box;padding:8px 10px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);min-width:200px;}
.cust-search input:focus{outline:none;border-color:var(--primary);}
.cust-table-wrap{border:1px solid var(--border);border-radius:11px;overflow:hidden;background:var(--card);}
.cust-table{width:100%;border-collapse:collapse;font-size:13px;}
.cust-table th{text-align:left;padding:9px 12px;background:color-mix(in srgb,var(--card) 92%,transparent);font-size:10px;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);border-bottom:1px solid var(--border);}
.cust-table td{padding:10px 12px;border-bottom:1px solid color-mix(in srgb,var(--border) 70%,transparent);vertical-align:middle;}
.cust-table tr:last-child td{border-bottom:none;}
.cust-table tr:hover td{background:color-mix(in srgb,var(--card) 95%,var(--border) 5%);}
.cust-empty{padding:48px 16px;text-align:center;color:var(--muted);}
.cust-empty i{font-size:32px;display:block;margin-bottom:10px;opacity:.4;}
.cust-empty p{margin:0;font-size:13px;}
.cust-badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;border:1px solid color-mix(in srgb,var(--primary) 30%,var(--border));background:color-mix(in srgb,var(--primary) 10%,transparent);}
.cust-actions{display:flex;gap:5px;justify-content:flex-end;}
.cust-action-btn{display:inline-flex;align-items:center;gap:5px;padding:5px 10px;font-size:11.5px;font-weight:700;border-radius:8px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 90%,transparent);color:var(--text);cursor:pointer;text-decoration:none;transition:all .15s;}
.cust-action-btn:hover{box-shadow:0 2px 8px rgba(0,0,0,.08);}
.cust-action-btn--edit{border-color:color-mix(in srgb,var(--primary) 40%,var(--border));background:color-mix(in srgb,var(--primary) 10%,transparent);}
.cust-action-btn--edit:hover{background:color-mix(in srgb,var(--primary) 20%,transparent);}
.cust-action-btn--del{border-color:color-mix(in srgb,#ef4444 35%,var(--border));background:transparent;color:#f97373;}
.cust-action-btn--del:hover{background:color-mix(in srgb,#ef4444 8%,transparent);}
:is(html[data-theme="light"],html[data-theme="light_blue"]) .cust-action-btn--del{color:#dc2626;}
/* Modal */
.cust-modal{position:fixed;inset:0;z-index:9100;display:flex;align-items:center;justify-content:center;padding:16px;visibility:hidden;opacity:0;pointer-events:none;transition:opacity .22s ease,visibility .22s;}
.cust-modal.is-open{visibility:visible;opacity:1;pointer-events:auto;}
.cust-modal__backdrop{position:absolute;inset:0;background:rgba(2,6,23,.58);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);}
.cust-modal__panel{position:relative;z-index:1;width:min(100%,460px);border-radius:18px;border:1px solid var(--border);background:var(--card);box-shadow:0 28px 64px rgba(0,0,0,.32),0 0 0 1px rgba(255,255,255,.05);overflow:hidden;transform:translateY(10px) scale(.97);transition:transform .28s cubic-bezier(.34,1.15,.64,1);}
.cust-modal.is-open .cust-modal__panel{transform:none;}
.cust-modal__head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:14px 18px;border-bottom:1px solid var(--border);background:color-mix(in srgb,var(--card) 93%,var(--border));}
.cust-modal__head h3{margin:0;font-size:15px;font-weight:800;color:var(--text);}
.cust-modal__close{width:30px;height:30px;display:flex;align-items:center;justify-content:center;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--muted);cursor:pointer;font-size:13px;transition:all .15s;}
.cust-modal__close:hover{border-color:var(--text);color:var(--text);}
.cust-modal__body{padding:18px;display:grid;gap:12px;}
.cust-modal__grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
@media(max-width:480px){.cust-modal__grid{grid-template-columns:1fr;}}
.cust-field label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin-bottom:5px;}
.cust-field input,.cust-field textarea{width:100%;box-sizing:border-box;padding:9px 12px;font-size:13px;border-radius:9px;border:1px solid var(--border);background:var(--card);color:var(--text);outline:none;transition:border-color .15s;}
.cust-field input:focus,.cust-field textarea:focus{border-color:var(--primary);box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 12%,transparent);}
.cust-modal__foot{display:flex;justify-content:flex-end;gap:8px;padding:12px 18px;border-top:1px solid var(--border);background:color-mix(in srgb,var(--card) 96%,var(--border));}
.cust-btn-ghost{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;font-size:13px;font-weight:700;border-radius:9px;border:1px solid var(--border);background:transparent;color:var(--muted);cursor:pointer;transition:all .15s;}
.cust-btn-ghost:hover{border-color:var(--text);color:var(--text);}
.cust-btn-primary{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;font-size:13px;font-weight:700;border-radius:9px;border:1px solid color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 14%,transparent);color:var(--text);cursor:pointer;transition:all .15s;}
.cust-btn-primary:hover{background:color-mix(in srgb,var(--primary) 24%,transparent);}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('pos::partials.pos-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;margin-bottom:14px;">{{ session('status') }}</div>
    @endif

    <div class="cust-toolbar">
        <form method="get" action="{{ route('pos.customers.index') }}" class="cust-search">
            <input type="search" name="q" value="{{ $search }}" placeholder="Search name, phone or email…" autocomplete="off">
            <button type="submit" class="linkbtn" style="padding:8px 10px;font-size:13px;"><i class="fa fa-search"></i></button>
            @if($search)
                <a href="{{ route('pos.customers.index') }}" class="linkbtn" style="padding:8px 12px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">Clear</a>
            @endif
        </form>
        <button type="button" class="linkbtn" id="cust-add-btn" style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
            <i class="fa fa-plus"></i> Add customer
        </button>
    </div>

    <div class="cust-table-wrap">
        @if($customers->isEmpty())
            <div class="cust-empty">
                <i class="fa fa-users" aria-hidden="true"></i>
                <p>{{ $search ? 'No customers matched "'.e($search).'". Try a different search.' : 'No customers yet. Add your first customer to get started.' }}</p>
            </div>
        @else
        <table class="cust-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Address</th>
                    <th>Sales</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($customers as $customer)
                <tr>
                    <td>
                        <strong style="color:var(--text);">{{ $customer->name }}</strong>
                        @if($customer->notes)
                            <div class="muted" style="font-size:11px;margin-top:2px;">{{ \Illuminate\Support\Str::limit($customer->notes, 60) }}</div>
                        @endif
                    </td>
                    <td>{{ $customer->phone ?: '<span class="muted">—</span>' }}</td>
                    <td style="font-size:12px;">{{ $customer->email ?: '<span class="muted">—</span>' }}</td>
                    <td style="font-size:12px;color:var(--muted);">{{ $customer->address ? \Illuminate\Support\Str::limit($customer->address, 40) : '—' }}</td>
                    <td><span class="cust-badge">{{ $customer->sales_count }}</span></td>
                    <td style="text-align:right;">
                        <div class="cust-actions">
                            <button type="button" class="cust-action-btn cust-action-btn--edit"
                                data-edit-customer
                                data-id="{{ $customer->id }}"
                                data-name="{{ e($customer->name) }}"
                                data-phone="{{ e($customer->phone ?? '') }}"
                                data-email="{{ e($customer->email ?? '') }}"
                                data-address="{{ e($customer->address ?? '') }}"
                                data-notes="{{ e($customer->notes ?? '') }}"
                            ><i class="fa fa-pen-to-square"></i> Edit</button>
                            <form method="post" action="{{ route('pos.customers.destroy', $customer) }}" onsubmit="return confirm('Delete {{ addslashes($customer->name) }}?');" style="margin:0;">
                                @csrf @method('DELETE')
                                <button type="submit" class="cust-action-btn cust-action-btn--del" title="Delete">
                                    <i class="fa fa-trash-can"></i>
                                </button>
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
        {{ $customers->count() }} customer{{ $customers->count() === 1 ? '' : 's' }}{{ $search ? ' matching "'.e($search).'"' : '' }}
    </p>
</div>

{{-- Add / Edit Modal --}}
<div id="cust-modal" class="cust-modal" role="dialog" aria-modal="true" aria-labelledby="cust-modal-title" aria-hidden="true">
    <div class="cust-modal__backdrop" id="cust-modal-backdrop"></div>
    <div class="cust-modal__panel">
        <div class="cust-modal__head">
            <h3 id="cust-modal-title">Add customer</h3>
            <button type="button" class="cust-modal__close" id="cust-modal-close" aria-label="Close">
                <i class="fa fa-times"></i>
            </button>
        </div>
        <form id="cust-form" method="post" action="{{ route('pos.customers.store') }}">
            @csrf
            <input type="hidden" name="_method" id="cust-form-method" value="POST">
            <div class="cust-modal__body">
                <div class="cust-modal__grid">
                    <div class="cust-field">
                        <label for="cust-name">Name <span style="color:#f87171;">*</span></label>
                        <input type="text" name="name" id="cust-name" required maxlength="120" placeholder="Full name">
                    </div>
                    <div class="cust-field">
                        <label for="cust-phone">Phone</label>
                        <input type="text" name="phone" id="cust-phone" maxlength="40" placeholder="+1 555 000 0000">
                    </div>
                    <div class="cust-field">
                        <label for="cust-email">Email</label>
                        <input type="email" name="email" id="cust-email" maxlength="160" placeholder="customer@example.com">
                    </div>
                    <div class="cust-field">
                        <label for="cust-address">Address</label>
                        <input type="text" name="address" id="cust-address" maxlength="255" placeholder="Street, city…">
                    </div>
                </div>
                <div class="cust-field">
                    <label for="cust-notes">Notes</label>
                    <textarea name="notes" id="cust-notes" rows="2" maxlength="2000" placeholder="Optional notes…" style="resize:vertical;font-family:inherit;"></textarea>
                </div>
            </div>
            <div class="cust-modal__foot">
                <button type="button" class="cust-btn-ghost" id="cust-modal-cancel">Cancel</button>
                <button type="submit" class="cust-btn-primary">
                    <i class="fa fa-floppy-disk"></i> Save customer
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var modal      = document.getElementById('cust-modal');
    var form       = document.getElementById('cust-form');
    var titleEl    = document.getElementById('cust-modal-title');
    var methodEl   = document.getElementById('cust-form-method');
    var baseAction = @json(route('pos.customers.store'));
    var updateBase = @json(url('/pos/customers'));

    function setOpen(open) {
        modal.classList.toggle('is-open', open);
        modal.setAttribute('aria-hidden', open ? 'false' : 'true');
        if (open) document.getElementById('cust-name').focus();
    }

    function closeModal() {
        setOpen(false);
        form.reset();
        form.action = baseAction;
        methodEl.value = 'POST';
        titleEl.textContent = 'Add customer';
    }

    document.getElementById('cust-add-btn').addEventListener('click', function () {
        closeModal();
        setOpen(true);
    });
    document.getElementById('cust-modal-close').addEventListener('click', closeModal);
    document.getElementById('cust-modal-cancel').addEventListener('click', closeModal);
    document.getElementById('cust-modal-backdrop').addEventListener('click', closeModal);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
    });

    document.querySelectorAll('[data-edit-customer]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.dataset.id;
            form.action = updateBase + '/' + id;
            methodEl.value = 'PUT';
            titleEl.textContent = 'Edit customer';
            document.getElementById('cust-name').value    = btn.dataset.name    || '';
            document.getElementById('cust-phone').value   = btn.dataset.phone   || '';
            document.getElementById('cust-email').value   = btn.dataset.email   || '';
            document.getElementById('cust-address').value = btn.dataset.address || '';
            document.getElementById('cust-notes').value   = btn.dataset.notes   || '';
            setOpen(true);
        });
    });
})();
</script>
@endsection
