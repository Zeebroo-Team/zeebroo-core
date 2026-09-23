@extends('theme::layouts.app', ['title' => 'Salary Sheet '.$sheet['sheet_ref'], 'heading' => 'Salary Sheet'])

@section('content')
@include('product::partials.catalog-hub-styles')
@include('pos::brand-mgmt.partials.crud-styles')
<style>
.ss-header{display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:18px;padding-bottom:16px;border-bottom:1px solid var(--border);}
.ss-header h2{margin:0 0 4px;font-size:18px;font-weight:800;color:var(--text);}
.ss-header .muted{font-size:13px;}
.ss-section{margin-bottom:22px;}
.ss-section h3{font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin:0 0 10px;display:flex;align-items:center;justify-content:space-between;}
.ss-fieldset-row{display:grid;grid-template-columns:1.4fr 1fr 1.6fr auto;gap:8px;margin-bottom:8px;align-items:center;}
.ss-fieldset-row input{box-sizing:border-box;padding:7px 9px;font-size:12.5px;border-radius:7px;border:1px solid var(--border);background:var(--card);color:var(--text);}
.ss-row-remove{width:28px;height:28px;border-radius:7px;border:1px solid var(--border);background:transparent;color:#f97373;cursor:pointer;}
.ss-add-row{display:inline-flex;align-items:center;gap:5px;padding:6px 12px;font-size:12px;font-weight:700;border-radius:8px;border:1px dashed var(--border);background:transparent;color:var(--muted);cursor:pointer;}
.ss-add-row:hover{border-color:var(--primary);color:var(--text);}
.ss-line-card{border:1px solid var(--border);border-radius:12px;padding:14px;margin-bottom:12px;background:var(--card);}
.ss-line-card__grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:10px;}
.ss-line-card__grid label{display:block;font-size:9.5px;font-weight:700;text-transform:uppercase;color:var(--muted);margin-bottom:3px;}
.ss-line-card__grid input,.ss-line-card__grid select{width:100%;box-sizing:border-box;padding:7px 9px;font-size:12.5px;border-radius:7px;border:1px solid var(--border);background:var(--card);color:var(--text);}
.ss-line-card__totals{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:10px;}
.ss-line-card__totals input{background:color-mix(in srgb,var(--card) 90%,var(--border));}
.ss-attendance{display:flex;flex-wrap:wrap;gap:4px;margin-top:8px;}
.ss-att-btn{width:32px;height:32px;border-radius:7px;border:1px solid var(--border);background:transparent;color:var(--muted);font-size:10px;cursor:pointer;display:flex;flex-direction:column;align-items:center;justify-content:center;line-height:1.1;}
.ss-att-btn[data-status="P"]{border-color:color-mix(in srgb,#22c55e 45%,var(--border));background:color-mix(in srgb,#22c55e 14%,transparent);color:#16a34a;}
.ss-line-card__head{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;}
.ss-line-remove{padding:5px 10px;font-size:11px;font-weight:700;border-radius:7px;border:1px solid color-mix(in srgb,#ef4444 35%,var(--border));background:transparent;color:#f97373;cursor:pointer;}
.ss-actions-bar{display:flex;flex-wrap:wrap;gap:8px;padding-top:16px;border-top:1px solid var(--border);}
.ss-actions-bar form{margin:0;}
.ss-actions-bar button{padding:9px 16px;font-size:13px;font-weight:700;border-radius:9px;border:1px solid color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 14%,transparent);color:var(--text);cursor:pointer;}
.ss-actions-bar button.danger{border-color:color-mix(in srgb,#ef4444 40%,var(--border));background:color-mix(in srgb,#ef4444 10%,transparent);color:#dc2626;}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('pos::partials.brand-mgmt-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="pcat-banner pcat-banner--err" style="font-weight:600;">{{ $errors->first() }}</div>
    @endif

    <div class="ss-header">
        <div>
            <h2>{{ $sheet['sheet_ref'] }} <span class="bmg-badge bmg-badge--{{ $sheet['status'] }}">{{ ucfirst($sheet['status']) }}</span></h2>
            <p class="muted">
                {{ $sheet['location'] ?: 'No location set' }}
                @if($sheet['date_from']) &middot; {{ $sheet['date_from'] }} — {{ $sheet['date_to'] }} @endif
            </p>
        </div>
        <button type="button" class="bmg-btn-ghost" id="ss-edit-header-btn"><i class="fa fa-pen-to-square"></i> Edit header</button>
    </div>

    {{-- Line items --}}
    <div class="ss-section">
        <h3>
            <span>Line items</span>
            <button type="button" class="ss-add-row" id="ss-add-line"><i class="fa fa-plus"></i> Add row</button>
        </h3>
        <form id="ss-rows-form" method="post" action="{{ route('pos.brand-mgmt.salary-sheets.save-rows', $sheet['id']) }}">
            @csrf
            @method('PUT')
            <div id="ss-rows"></div>
            <button type="submit" class="bmg-btn-primary"><i class="fa fa-floppy-disk"></i> Save line items</button>
        </form>
    </div>

    {{-- Guarded status actions --}}
    @if(count($allowedTransitions) > 0)
    <div class="ss-actions-bar">
        @foreach($allowedTransitions as $toStatus => $label)
        <form method="post" action="{{ route('pos.brand-mgmt.salary-sheets.transition', $sheet['id']) }}" onsubmit="return confirm('{{ addslashes($label) }}?');">
            @csrf
            <input type="hidden" name="to_status" value="{{ $toStatus }}">
            <button type="submit" class="{{ $toStatus === 'rejected' ? 'danger' : '' }}">{{ $label }}</button>
        </form>
        @endforeach
    </div>
    @endif
</div>

{{-- Edit header modal --}}
<div id="ss-header-modal" class="bmg-modal" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="bmg-modal__backdrop" id="ss-header-backdrop"></div>
    <div class="bmg-modal__panel" style="width:min(100%,640px);">
        <div class="bmg-modal__head">
            <h3>Edit salary sheet</h3>
            <button type="button" class="bmg-modal__close" id="ss-header-close"><i class="fa fa-times"></i></button>
        </div>
        <form method="post" action="{{ route('pos.brand-mgmt.salary-sheets.update', $sheet['id']) }}">
            @csrf
            @method('PUT')
            <div class="bmg-modal__body">
                <div class="bmg-modal__grid">
                    <div class="bmg-field">
                        <label for="ss-job_id">Job</label>
                        <select name="job_id" id="ss-job_id">
                            <option value="">— none —</option>
                            @foreach($jobs as $job)
                                <option value="{{ $job->id }}" @selected($sheet['job_id'] == $job->id)>{{ $job->name }} ({{ $job->job_ref }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="bmg-field">
                        <label for="ss-location">Location</label>
                        <input type="text" name="location" id="ss-location" maxlength="200" value="{{ $sheet['location'] }}">
                    </div>
                    <div class="bmg-field">
                        <label for="ss-date_from">Date from</label>
                        <input type="date" name="date_from" id="ss-date_from" value="{{ $sheet['date_from'] }}">
                    </div>
                    <div class="bmg-field">
                        <label for="ss-date_to">Date to</label>
                        <input type="date" name="date_to" id="ss-date_to" value="{{ $sheet['date_to'] }}">
                    </div>
                    <div class="bmg-field">
                        <label for="ss-default_coordinator_fee">Default coordinator fee</label>
                        <input type="number" step="0.01" min="0" name="default_coordinator_fee" id="ss-default_coordinator_fee" value="{{ $sheet['default_coordinator_fee'] }}">
                    </div>
                </div>
                <div class="bmg-field">
                    <label for="ss-notes">Notes</label>
                    <textarea name="notes" id="ss-notes" rows="2" maxlength="2000" style="resize:vertical;font-family:inherit;">{{ $sheet['notes'] }}</textarea>
                </div>

                <div class="ss-section" style="margin-bottom:0;">
                    <h3><span>Allowances</span><button type="button" class="ss-add-row" id="ss-add-allowance"><i class="fa fa-plus"></i> Add</button></h3>
                    <div id="ss-allowances-rows">
                        @foreach($sheet['allowances'] as $i => $a)
                        <div class="ss-fieldset-row">
                            <input type="hidden" name="allowances[{{ $i }}][id]" value="{{ $a['id'] }}">
                            <input type="text" name="allowances[{{ $i }}][allowance_type]" placeholder="Type" value="{{ $a['allowance_type'] }}">
                            <input type="number" step="0.01" min="0" name="allowances[{{ $i }}][amount]" placeholder="Amount" value="{{ $a['amount'] }}">
                            <input type="text" name="allowances[{{ $i }}][description]" placeholder="Description" value="{{ $a['description'] }}">
                            <button type="button" class="ss-row-remove" onclick="this.closest('.ss-fieldset-row').remove()"><i class="fa fa-trash-can"></i></button>
                        </div>
                        @endforeach
                    </div>
                </div>

                <div class="ss-section" style="margin-bottom:0;">
                    <h3><span>Position rate rules</span><button type="button" class="ss-add-row" id="ss-add-position-rule"><i class="fa fa-plus"></i> Add</button></h3>
                    <div id="ss-position-rules-rows">
                        @foreach($sheet['position_rules'] as $i => $p)
                        <div class="ss-fieldset-row">
                            <input type="hidden" name="position_rules[{{ $i }}][id]" value="{{ $p['id'] }}">
                            <input type="text" name="position_rules[{{ $i }}][position_name]" placeholder="Position" value="{{ $p['position_name'] }}">
                            <input type="number" step="0.01" min="0" name="position_rules[{{ $i }}][daily_rate]" placeholder="Daily rate" value="{{ $p['daily_rate'] }}">
                            <input type="number" step="0.01" min="0" name="position_rules[{{ $i }}][transport_allowance]" placeholder="Transport allowance" value="{{ $p['transport_allowance'] }}">
                            <button type="button" class="ss-row-remove" onclick="this.closest('.ss-fieldset-row').remove()"><i class="fa fa-trash-can"></i></button>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="bmg-modal__foot">
                <button type="button" class="bmg-btn-ghost" id="ss-header-cancel">Cancel</button>
                <button type="submit" class="bmg-btn-primary"><i class="fa fa-floppy-disk"></i> Save</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var DATES = @json($dates);
    var PROMOTERS = @json($promoters);
    var COORDINATORS = @json($coordinators);
    var EXISTING_ROWS = @json($sheet['rows']);
    var rowsContainer = document.getElementById('ss-rows');
    var rowCounter = 0;

    function fmtDateLabel(d) {
        var parts = d.split('-');
        return parts[2] + '/' + parts[1];
    }

    function attendanceLookup(row) {
        var map = {};
        (row.attendances || []).forEach(function (a) { map[a.attendance_date] = a.attendance_status; });
        return map;
    }

    function recomputeRow(card) {
        var pBtns = card.querySelectorAll('.ss-att-btn[data-status="P"]');
        var totalDays = pBtns.length;
        var dailyRate = parseFloat(card.querySelector('[data-field="daily_rate"]').value) || 0;
        var transport = parseFloat(card.querySelector('[data-field="transport_allowance"]').value) || 0;
        var expenses  = parseFloat(card.querySelector('[data-field="expenses"]').value) || 0;
        var hold      = parseFloat(card.querySelector('[data-field="hold_amount"]').value) || 0;

        var attendanceAmount = totalDays * dailyRate;
        var baseAmount = attendanceAmount + (transport * totalDays) + expenses;
        var netAmount = baseAmount - hold;

        card.querySelector('[data-field="total_days"]').value = totalDays;
        card.querySelector('[data-field="attendance_amount"]').value = attendanceAmount.toFixed(2);
        card.querySelector('[data-field="base_amount"]').value = baseAmount.toFixed(2);
        card.querySelector('[data-field="net_amount"]').value = netAmount.toFixed(2);
    }

    function buildRow(idx, row) {
        row = row || {};
        var attMap = attendanceLookup(row);

        var card = document.createElement('div');
        card.className = 'ss-line-card';
        card.dataset.idx = idx;

        var promoterOptions = '<option value="">— free text —</option>' + PROMOTERS.map(function (p) {
            return '<option value="' + p.id + '"' + (row.promoter_id == p.id ? ' selected' : '') + '>' + p.name + '</option>';
        }).join('');
        var coordinatorOptions = '<option value="">— none —</option>' + COORDINATORS.map(function (c) {
            return '<option value="' + c.id + '"' + (row.coordinator_id == c.id ? ' selected' : '') + '>' + c.name + '</option>';
        }).join('');

        card.innerHTML =
            '<div class="ss-line-card__head">' +
                '<strong>Row ' + (idx + 1) + '</strong>' +
                '<button type="button" class="ss-line-remove"><i class="fa fa-trash-can"></i> Remove</button>' +
            '</div>' +
            '<input type="hidden" name="rows[' + idx + '][id]" value="' + (row.id || '') + '">' +
            '<div class="ss-line-card__grid">' +
                '<div><label>Item #</label><input type="text" name="rows[' + idx + '][item_number]" value="' + (row.item_number || '') + '"></div>' +
                '<div><label>Location</label><input type="text" name="rows[' + idx + '][location]" value="' + (row.location || '') + '"></div>' +
                '<div><label>Position</label><input type="text" name="rows[' + idx + '][position]" data-field="position" value="' + (row.position || '') + '"></div>' +
                '<div><label>Promoter</label><select name="rows[' + idx + '][promoter_id]" data-field="promoter_select">' + promoterOptions + '</select></div>' +
                '<div><label>Promoter name</label><input type="text" name="rows[' + idx + '][promoter_name]" data-field="promoter_name" value="' + (row.promoter_name || '') + '"></div>' +
                '<div><label>Bank name</label><input type="text" name="rows[' + idx + '][bank_name]" data-field="bank_name" value="' + (row.bank_name || '') + '"></div>' +
                '<div><label>Bank branch</label><input type="text" name="rows[' + idx + '][bank_branch]" data-field="bank_branch" value="' + (row.bank_branch || '') + '"></div>' +
                '<div><label>Bank account</label><input type="text" name="rows[' + idx + '][bank_account]" data-field="bank_account" value="' + (row.bank_account || '') + '"></div>' +
                '<div><label>Daily rate</label><input type="number" step="0.01" min="0" name="rows[' + idx + '][daily_rate]" data-field="daily_rate" value="' + (row.daily_rate || 0) + '"></div>' +
                '<div><label>Transport</label><input type="number" step="0.01" min="0" name="rows[' + idx + '][transport_allowance]" data-field="transport_allowance" value="' + (row.transport_allowance || 0) + '"></div>' +
                '<div><label>Expenses</label><input type="number" step="0.01" min="0" name="rows[' + idx + '][expenses]" data-field="expenses" value="' + (row.expenses || 0) + '"></div>' +
                '<div><label>Hold amount</label><input type="number" step="0.01" min="0" name="rows[' + idx + '][hold_amount]" data-field="hold_amount" value="' + (row.hold_amount || 0) + '"></div>' +
                '<div><label>Coordinator</label><select name="rows[' + idx + '][coordinator_id]" data-field="coordinator_select">' + coordinatorOptions + '</select></div>' +
                '<div><label>Coordinator name</label><input type="text" name="rows[' + idx + '][coordinator_name]" data-field="coordinator_name" value="' + (row.coordinator_name || '') + '"></div>' +
                '<div><label>Coordination fee</label><input type="number" step="0.01" min="0" name="rows[' + idx + '][coordination_fee]" value="' + (row.coordination_fee || 0) + '"></div>' +
                '<div><label>Coordinator bank</label><input type="text" name="rows[' + idx + '][coordinator_bank_details]" data-field="coordinator_bank" value="' + (row.coordinator_bank_details || '') + '"></div>' +
            '</div>' +
            '<div class="ss-line-card__totals">' +
                '<div><label>Total days</label><input type="number" name="rows[' + idx + '][total_days]" data-field="total_days" readonly value="' + (row.total_days || 0) + '"></div>' +
                '<div><label>Attendance amount</label><input type="number" step="0.01" name="rows[' + idx + '][attendance_amount]" data-field="attendance_amount" readonly value="' + (row.attendance_amount || 0) + '"></div>' +
                '<div><label>Base amount</label><input type="number" step="0.01" name="rows[' + idx + '][base_amount]" data-field="base_amount" readonly value="' + (row.base_amount || 0) + '"></div>' +
                '<div><label>Net amount</label><input type="number" step="0.01" name="rows[' + idx + '][net_amount]" data-field="net_amount" readonly value="' + (row.net_amount || 0) + '"></div>' +
            '</div>' +
            (DATES.length ? '<div class="ss-attendance" data-attendance></div>' : '<p class="muted" style="font-size:11px;">Set a date range or custom dates in the header to enable attendance tracking.</p>');

        rowsContainer.appendChild(card);

        if (DATES.length) {
            var attWrap = card.querySelector('[data-attendance]');
            DATES.forEach(function (d) {
                var status = attMap[d] || 'A';
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'ss-att-btn';
                btn.dataset.date = d;
                btn.dataset.status = status;
                btn.innerHTML = fmtDateLabel(d) + '<br>' + status;
                btn.addEventListener('click', function () {
                    var next = btn.dataset.status === 'P' ? 'A' : 'P';
                    btn.dataset.status = next;
                    btn.innerHTML = fmtDateLabel(d) + '<br>' + next;
                    recomputeRow(card);
                });
                attWrap.appendChild(btn);
            });
        }

        card.querySelector('[data-field="promoter_select"]').addEventListener('change', function (e) {
            var p = PROMOTERS.find(function (x) { return String(x.id) === e.target.value; });
            if (!p) return;
            card.querySelector('[data-field="promoter_name"]').value = p.name;
            card.querySelector('[data-field="bank_name"]').value = p.bank_name || '';
            card.querySelector('[data-field="bank_branch"]').value = p.bank_branch || '';
            card.querySelector('[data-field="bank_account"]').value = p.bank_account || '';
            if (p.position) card.querySelector('[data-field="position"]').value = p.position;
        });
        card.querySelector('[data-field="coordinator_select"]').addEventListener('change', function (e) {
            var c = COORDINATORS.find(function (x) { return String(x.id) === e.target.value; });
            if (!c) return;
            card.querySelector('[data-field="coordinator_name"]').value = c.name;
            card.querySelector('[data-field="coordinator_bank"]').value = [c.bank_name, c.bank_branch, c.bank_account].filter(Boolean).join(' / ');
        });
        card.querySelector('.ss-line-remove').addEventListener('click', function () { card.remove(); });
        ['daily_rate', 'transport_allowance', 'expenses', 'hold_amount'].forEach(function (f) {
            card.querySelector('[data-field="' + f + '"]').addEventListener('input', function () { recomputeRow(card); });
        });

        recomputeRow(card);
    }

    EXISTING_ROWS.forEach(function (row) { buildRow(rowCounter++, row); });
    document.getElementById('ss-add-line').addEventListener('click', function () { buildRow(rowCounter++, {}); });

    // ── Header modal ──────────────────────────────────────────────────
    var headerModal = document.getElementById('ss-header-modal');
    function setHeaderOpen(open) {
        headerModal.classList.toggle('is-open', open);
        headerModal.setAttribute('aria-hidden', open ? 'false' : 'true');
    }
    document.getElementById('ss-edit-header-btn').addEventListener('click', function () { setHeaderOpen(true); });
    document.getElementById('ss-header-close').addEventListener('click', function () { setHeaderOpen(false); });
    document.getElementById('ss-header-cancel').addEventListener('click', function () { setHeaderOpen(false); });
    document.getElementById('ss-header-backdrop').addEventListener('click', function () { setHeaderOpen(false); });

    var allowanceCounter = document.querySelectorAll('#ss-allowances-rows .ss-fieldset-row').length;
    document.getElementById('ss-add-allowance').addEventListener('click', function () {
        var div = document.createElement('div');
        div.className = 'ss-fieldset-row';
        var i = allowanceCounter++;
        div.innerHTML =
            '<input type="hidden" name="allowances[' + i + '][id]" value="">' +
            '<input type="text" name="allowances[' + i + '][allowance_type]" placeholder="Type">' +
            '<input type="number" step="0.01" min="0" name="allowances[' + i + '][amount]" placeholder="Amount">' +
            '<input type="text" name="allowances[' + i + '][description]" placeholder="Description">' +
            '<button type="button" class="ss-row-remove"><i class="fa fa-trash-can"></i></button>';
        div.querySelector('.ss-row-remove').addEventListener('click', function () { div.remove(); });
        document.getElementById('ss-allowances-rows').appendChild(div);
    });

    var positionRuleCounter = document.querySelectorAll('#ss-position-rules-rows .ss-fieldset-row').length;
    document.getElementById('ss-add-position-rule').addEventListener('click', function () {
        var div = document.createElement('div');
        div.className = 'ss-fieldset-row';
        var i = positionRuleCounter++;
        div.innerHTML =
            '<input type="hidden" name="position_rules[' + i + '][id]" value="">' +
            '<input type="text" name="position_rules[' + i + '][position_name]" placeholder="Position">' +
            '<input type="number" step="0.01" min="0" name="position_rules[' + i + '][daily_rate]" placeholder="Daily rate">' +
            '<input type="number" step="0.01" min="0" name="position_rules[' + i + '][transport_allowance]" placeholder="Transport allowance">' +
            '<button type="button" class="ss-row-remove"><i class="fa fa-trash-can"></i></button>';
        div.querySelector('.ss-row-remove').addEventListener('click', function () { div.remove(); });
        document.getElementById('ss-position-rules-rows').appendChild(div);
    });

    document.querySelectorAll('#ss-allowances-rows .ss-row-remove, #ss-position-rules-rows .ss-row-remove').forEach(function (btn) {
        btn.addEventListener('click', function () { btn.closest('.ss-fieldset-row').remove(); });
    });
})();
</script>
@endsection
