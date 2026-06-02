@extends('theme::layouts.app', ['title' => 'Customers', 'heading' => 'POS Customers'])

@section('content')
<style>
.cust-page{max-width:900px;}
.cust-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:16px;}
.cust-search{display:flex;gap:8px;}
.cust-search input{padding:8px 12px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);min-width:220px;}
.cust-btn{padding:8px 14px;font-size:13px;font-weight:700;border-radius:8px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 90%,transparent);color:var(--text);cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px;}
.cust-btn--primary{border-color:color-mix(in srgb,var(--primary) 50%,var(--border));background:color-mix(in srgb,var(--primary) 14%,transparent);}
.cust-table{width:100%;border-collapse:collapse;font-size:13px;}
.cust-table th{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);padding:8px 12px;border-bottom:2px solid var(--border);text-align:left;}
.cust-table td{padding:10px 12px;border-bottom:1px solid var(--border);color:var(--text);vertical-align:middle;}
.cust-table tr:last-child td{border-bottom:none;}
.cust-table tr:hover td{background:color-mix(in srgb,var(--card) 95%,var(--border));}
.cust-empty{padding:40px;text-align:center;color:var(--muted);}
.cust-actions{display:flex;gap:6px;}
.cust-badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;background:color-mix(in srgb,var(--primary) 12%,transparent);color:var(--text);}
/* Add/Edit modal */
.cust-modal{position:fixed;inset:0;z-index:9100;display:flex;align-items:center;justify-content:center;padding:16px;visibility:hidden;opacity:0;pointer-events:none;transition:opacity .2s,visibility .2s;}
.cust-modal.is-open{visibility:visible;opacity:1;pointer-events:auto;}
.cust-modal__backdrop{position:absolute;inset:0;background:rgba(2,6,23,.55);backdrop-filter:blur(5px);}
.cust-modal__panel{position:relative;z-index:1;width:min(100%,480px);border-radius:16px;border:1px solid var(--border);background:var(--card);box-shadow:0 24px 60px rgba(0,0,0,.32);overflow:hidden;transform:translateY(8px) scale(.97);transition:transform .25s cubic-bezier(.34,1.15,.64,1);}
.cust-modal.is-open .cust-modal__panel{transform:none;}
.cust-modal__head{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid var(--border);background:color-mix(in srgb,var(--card) 93%,var(--border));}
.cust-modal__head h3{margin:0;font-size:15px;font-weight:800;}
.cust-modal__close{width:30px;height:30px;display:flex;align-items:center;justify-content:center;border-radius:7px;border:1px solid var(--border);background:transparent;color:var(--muted);cursor:pointer;font-size:13px;}
.cust-modal__body{padding:18px;}
.cust-field{margin-bottom:14px;}
.cust-field:last-child{margin-bottom:0;}
.cust-field label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin-bottom:5px;}
.cust-field input,.cust-field textarea{width:100%;box-sizing:border-box;padding:9px 12px;font-size:13px;border-radius:9px;border:1px solid var(--border);background:var(--card);color:var(--text);}
.cust-field input:focus,.cust-field textarea:focus{outline:none;border-color:var(--primary);}
.cust-modal__foot{display:flex;justify-content:flex-end;gap:8px;padding:12px 18px;border-top:1px solid var(--border);background:color-mix(in srgb,var(--card) 96%,var(--border));}
</style>

<div class="pcat-page-card card cust-page" style="max-width:900px;">
    @include('pos::partials.pos-hub-nav')

    @if(session('status'))
        <div style="margin-bottom:12px;padding:10px 14px;border-radius:9px;border:1px solid color-mix(in srgb,#22c55e 40%,var(--border));background:color-mix(in srgb,#22c55e 8%,transparent);font-size:13px;">
            {{ session('status') }}
        </div>
    @endif

    <div class="cust-toolbar">
        <form method="get" action="{{ route('pos.customers.index') }}" class="cust-search">
            <input type="search" name="q" value="{{ $search }}" placeholder="Search by name or phone…" autocomplete="off">
            <button type="submit" class="cust-btn"><i class="fa fa-search"></i></button>
            @if($search)
                <a href="{{ route('pos.customers.index') }}" class="cust-btn">Clear</a>
            @endif
        </form>
        <button type="button" class="cust-btn cust-btn--primary" id="cust-add-btn">
            <i class="fa fa-plus"></i> Add customer
        </button>
    </div>

    <div style="border:1px solid var(--border);border-radius:12px;overflow:hidden;background:var(--card);">
        @if($customers->isEmpty())
            <div class="cust-empty">
                <i class="fa fa-users" style="font-size:28px;margin-bottom:8px;display:block;"></i>
                No customers yet.{{ $search ? ' Try a different search.' : '' }}
            </div>
        @else
        <table class="cust-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Sales</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($customers as $customer)
                <tr data-customer-id="{{ $customer->id }}">
                    <td><strong>{{ $customer->name }}</strong></td>
                    <td>{{ $customer->phone ?: '—' }}</td>
                    <td>{{ $customer->email ?: '—' }}</td>
                    <td><span class="cust-badge">{{ $customer->sales_count }}</span></td>
                    <td>
                        <div class="cust-actions">
                            <button type="button" class="cust-btn"
                                data-edit-customer
                                data-id="{{ $customer->id }}"
                                data-name="{{ e($customer->name) }}"
                                data-phone="{{ e($customer->phone ?? '') }}"
                                data-email="{{ e($customer->email ?? '') }}"
                                data-address="{{ e($customer->address ?? '') }}"
                                data-notes="{{ e($customer->notes ?? '') }}"
                            ><i class="fa fa-pencil"></i></button>
                            <form method="post" action="{{ route('pos.customers.destroy', $customer) }}" onsubmit="return confirm('Delete {{ addslashes($customer->name) }}?');" style="display:inline;">
                                @csrf @method('DELETE')
                                <button type="submit" class="cust-btn" style="color:#f87171;border-color:color-mix(in srgb,#ef4444 40%,var(--border));"><i class="fa fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>

{{-- Add / Edit Modal --}}
<div id="cust-modal" class="cust-modal" aria-hidden="true">
    <div class="cust-modal__backdrop" id="cust-modal-backdrop"></div>
    <div class="cust-modal__panel">
        <div class="cust-modal__head">
            <h3 id="cust-modal-title">Add customer</h3>
            <button type="button" class="cust-modal__close" id="cust-modal-close"><i class="fa fa-times"></i></button>
        </div>
        <form id="cust-form" method="post" action="{{ route('pos.customers.store') }}">
            @csrf
            <input type="hidden" name="_method" id="cust-form-method" value="POST">
            <input type="hidden" name="_id" id="cust-form-id" value="">
            <div class="cust-modal__body">
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
                <div class="cust-field">
                    <label for="cust-notes">Notes</label>
                    <textarea name="notes" id="cust-notes" rows="2" maxlength="2000" placeholder="Optional notes…"></textarea>
                </div>
            </div>
            <div class="cust-modal__foot">
                <button type="button" class="cust-btn" id="cust-modal-cancel">Cancel</button>
                <button type="submit" class="cust-btn cust-btn--primary">Save customer</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var modal  = document.getElementById('cust-modal');
    var form   = document.getElementById('cust-form');
    var title  = document.getElementById('cust-modal-title');
    var method = document.getElementById('cust-form-method');
    var idEl   = document.getElementById('cust-form-id');
    var baseAction = @json(route('pos.customers.store'));
    var updateBase = @json(url('/pos/customers'));

    function openModal(editing) {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.getElementById('cust-name').focus();
    }
    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        form.reset();
        form.action = baseAction;
        method.value = 'POST';
        idEl.value = '';
        title.textContent = 'Add customer';
    }

    document.getElementById('cust-add-btn').addEventListener('click', function () {
        closeModal();
        openModal(false);
    });
    document.getElementById('cust-modal-close').addEventListener('click', closeModal);
    document.getElementById('cust-modal-cancel').addEventListener('click', closeModal);
    document.getElementById('cust-modal-backdrop').addEventListener('click', closeModal);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });

    document.querySelectorAll('[data-edit-customer]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.dataset.id;
            form.action = updateBase + '/' + id;
            method.value = 'PUT';
            idEl.value = id;
            title.textContent = 'Edit customer';
            document.getElementById('cust-name').value    = btn.dataset.name    || '';
            document.getElementById('cust-phone').value   = btn.dataset.phone   || '';
            document.getElementById('cust-email').value   = btn.dataset.email   || '';
            document.getElementById('cust-address').value = btn.dataset.address || '';
            document.getElementById('cust-notes').value   = btn.dataset.notes   || '';
            openModal(true);
        });
    });
})();
</script>
@endsection
