@extends('theme::layouts.app', ['title' => $budget['name'], 'heading' => $budget['name']])

@section('content')
@include('product::partials.catalog-hub-styles')
<div class="bud-show">
    <style>
        .bud-show{max-width:none;width:100%;margin:0;box-sizing:border-box;}
        .bud-show__top{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;margin-bottom:14px;}
        .bud-show__actions{display:flex;flex-wrap:wrap;gap:7px;}
        .bud-btn--ghost{display:inline-flex;align-items:center;gap:5px;padding:6px 11px;border-radius:9px;font-size:12px;font-weight:600;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 92%,transparent);color:var(--text);text-decoration:none;cursor:pointer;}
        .bud-btn--ghost:hover{border-color:color-mix(in srgb,var(--primary) 40%,var(--border));background:color-mix(in srgb,var(--primary) 8%,transparent);}
        .bud-btn--primary{display:inline-flex;align-items:center;gap:6px;padding:7px 13px;border-radius:9px;font-size:12px;font-weight:700;border:1px solid color-mix(in srgb,var(--btn-bg) 72%,var(--border));background:var(--btn-bg);color:#fff;cursor:pointer;}
        .bud-btn--primary:hover{background:var(--btn-hover);color:#111827;}
        .bud-btn--danger{display:inline-flex;align-items:center;gap:5px;padding:6px 11px;border-radius:9px;font-size:12px;font-weight:600;border:1px solid color-mix(in srgb,#ef4444 50%,var(--border));background:transparent;color:#f97373;cursor:pointer;}
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .bud-btn--danger{color:#dc2626;}
        .bud-show__card{border:1px solid var(--border);border-radius:14px;background:var(--card);padding:16px 18px;box-shadow:0 12px 40px -28px rgba(0,0,0,.35);margin-bottom:14px;}
        .bud-show__head{display:flex;flex-wrap:wrap;align-items:flex-start;gap:10px;margin-bottom:6px;}
        .bud-show__head h1{margin:0;font-size:18px;font-weight:800;color:var(--text);}
        .bud-show__sub{margin:4px 0 0;font-size:12px;color:var(--muted);}
        .bud-show__section{margin-top:16px;padding-top:16px;border-top:1px solid color-mix(in srgb,var(--border) 80%,transparent);}
        .bud-show__section h2{margin:0 0 10px;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);display:flex;align-items:center;justify-content:space-between;gap:8px;}
        .bud-items-grid{display:grid;grid-template-columns:1.2fr 1.4fr 1fr 1fr;gap:8px;align-items:center;font-size:12px;}
        .bud-items-grid input{box-sizing:border-box;width:100%;padding:7px 9px;font-size:12.5px;border-radius:7px;border:1px solid var(--border);background:var(--card);color:var(--text);}
        .bud-items-grid .bud-items-head{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);padding-bottom:4px;}
        .bud-cat-name{font-weight:700;color:var(--text);font-size:12.5px;}
        .bud-view-row{display:flex;flex-wrap:wrap;gap:10px;align-items:center;}
        .bud-view-row select{padding:7px 10px;font-size:12.5px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);}
        .bud-status-pill{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:999px;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;border:1px solid var(--border);white-space:nowrap;}
        .bud-status-pill--over{border-color:color-mix(in srgb,#ef4444 50%,var(--border));background:color-mix(in srgb,#ef4444 12%,transparent);color:#f97373;}
        .bud-status-pill--ok{border-color:color-mix(in srgb,#22c55e 45%,var(--border));background:color-mix(in srgb,#22c55e 12%,transparent);color:color-mix(in srgb,#bbf7d0 70%,var(--text));}
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .bud-status-pill--ok{color:#166534;}
        .bud-toggle-months{background:transparent;border:none;color:var(--primary);font-size:11px;font-weight:700;cursor:pointer;padding:2px 4px;}
        .bud-months-row td{background:color-mix(in srgb,var(--card) 92%,transparent);padding:0 !important;}
        .bud-months-row.is-collapsed{display:none;}
        .bud-months-inner{padding:10px 14px;}
        .bud-actuals-form{display:grid;grid-template-columns:1.2fr 1fr 1fr 1.4fr auto;gap:8px;align-items:end;margin-bottom:14px;}
        .bud-actuals-form label{display:block;font-size:9.5px;font-weight:700;text-transform:uppercase;color:var(--muted);margin-bottom:4px;}
        .bud-actuals-form input,.bud-actuals-form select{width:100%;box-sizing:border-box;padding:7px 9px;font-size:12.5px;border-radius:7px;border:1px solid var(--border);background:var(--card);color:var(--text);}
        @media (max-width:820px){.bud-items-grid{grid-template-columns:1fr;}.bud-actuals-form{grid-template-columns:1fr 1fr;}}
    </style>

    <div class="bud-show__top">
        <a href="{{ route('budget.index') }}" class="bud-btn--ghost"><i class="fa fa-arrow-left"></i> All budgets</a>
        <div class="bud-show__actions">
            @if($budget['is_active'])
                <form method="post" action="{{ route('budget.deactivate', $budget['id']) }}">
                    @csrf
                    <button type="submit" class="bud-btn--ghost"><i class="fa fa-pause"></i> Deactivate</button>
                </form>
            @else
                <form method="post" action="{{ route('budget.activate', $budget['id']) }}">
                    @csrf
                    <button type="submit" class="bud-btn--ghost"><i class="fa fa-play"></i> Activate</button>
                </form>
            @endif
            <button type="button" class="bud-btn--ghost" id="bud-edit-open"><i class="fa fa-pen-to-square"></i> Edit</button>
            <form method="post" action="{{ route('budget.destroy', $budget['id']) }}" onsubmit="return confirm('Delete this budget and all its allocations/actuals?');">
                @csrf @method('delete')
                <button type="submit" class="bud-btn--danger"><i class="fa fa-trash-can"></i> Delete</button>
            </form>
        </div>
    </div>

    <div class="bud-show__card">
        @if(session('status'))
            <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="pcat-banner pcat-banner--err" style="font-weight:600;">{{ $errors->first() }}</div>
        @endif

        <div class="bud-show__head">
            <div style="flex:1;min-width:0;">
                <h1>{{ $budget['name'] }}
                    @if($budget['is_active'])
                        <span class="bud-status-pill bud-status-pill--ok"><i class="fa fa-circle-check"></i> Active</span>
                    @endif
                </h1>
                <p class="bud-show__sub">{{ $budget['type_label'] }} &middot; {{ $budget['start_date'] }} — {{ $budget['end_date'] }}</p>
            </div>
            <div style="display:flex;gap:8px;">
                <div style="text-align:right;">
                    <div style="font-size:9px;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);font-weight:700;">Monthly total</div>
                    <div style="font-size:15px;font-weight:800;color:var(--text);">{{ $budget['total_monthly_fmt'] }}</div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:9px;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);font-weight:700;">Yearly total</div>
                    <div style="font-size:15px;font-weight:800;color:var(--text);">{{ $budget['total_yearly_fmt'] }}</div>
                </div>
            </div>
        </div>

        {{-- Category allocations --}}
        <div class="bud-show__section">
            <h2><span>Category allocations</span></h2>
            <form method="post" action="{{ route('budget.items.update', $budget['id']) }}">
                @csrf @method('put')
                <div class="bud-items-grid">
                    <div class="bud-items-head">Category</div>
                    <div class="bud-items-head">Custom label (optional)</div>
                    <div class="bud-items-head">Monthly amount</div>
                    <div class="bud-items-head">Yearly amount</div>
                    @foreach($budget['items'] as $i => $item)
                        <input type="hidden" name="items[{{ $i }}][category]" value="{{ $item['category'] }}">
                        <div class="bud-cat-name">{{ $categoryLabels[$item['category']] ?? $item['category'] }}</div>
                        <input type="text" name="items[{{ $i }}][label]" maxlength="100" value="{{ $item['label'] }}" placeholder="Override display label">
                        <input type="number" step="0.01" min="0" name="items[{{ $i }}][monthly_amount]" value="{{ $item['monthly_amount'] }}">
                        <input type="number" step="0.01" min="0" name="items[{{ $i }}][yearly_amount]" value="{{ $item['yearly_amount'] }}">
                    @endforeach
                </div>
                <div style="margin-top:12px;">
                    <button type="submit" class="bud-btn--primary"><i class="fa fa-floppy-disk"></i> Save allocations</button>
                </div>
            </form>
        </div>

        {{-- View period --}}
        <div class="bud-show__section">
            <h2><span>Report view</span></h2>
            <form method="post" action="{{ route('budget.view-period.update', $budget['id']) }}" class="bud-view-row">
                @csrf @method('patch')
                <select name="view_period" onchange="this.form.submit()">
                    @foreach($viewPeriods as $key => $label)
                        <option value="{{ $key }}" @selected($budget['view_period'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <span style="font-size:11px;color:var(--muted);">Controls how the spending report below groups dates (informational — the breakdown is always monthly).</span>
            </form>
        </div>

        {{-- Spending report --}}
        <div class="bud-show__section">
            <h2><span>Actual spend vs. budget</span></h2>
            <p style="margin:0 0 10px;font-size:11px;line-height:1.4;color:var(--muted);max-width:90ch;">Spend is pulled automatically from bills, loans, rentals, payroll, purchasing and completed sales for this budget's date range, plus any manual entries below (used for categories with no automatic source, like Marketing).</p>
            <div class="pcat-table-wrap">
                <table class="pcat-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Yearly limit</th>
                            <th>Total spent</th>
                            <th>Remaining</th>
                            <th>% used</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($spending['categories'] as $idx => $cat)
                            <tr>
                                <td>{{ $cat['category_label'] }} @unless($cat['has_auto_source'])<span title="No automatic source — log manually below" style="color:var(--muted);"><i class="fa fa-pen"></i></span>@endunless</td>
                                <td>{{ number_format($cat['yearly_limit'], 2, '.', ',') }}</td>
                                <td>{{ number_format($cat['total_spent'], 2, '.', ',') }}</td>
                                <td>{{ number_format($cat['remaining'], 2, '.', ',') }}</td>
                                <td>{{ $cat['percent_used'] !== null ? $cat['percent_used'].'%' : '—' }}</td>
                                <td>
                                    @if($cat['over_limit'])
                                        <span class="bud-status-pill bud-status-pill--over"><i class="fa fa-triangle-exclamation"></i> Over</span>
                                    @else
                                        <span class="bud-status-pill bud-status-pill--ok"><i class="fa fa-check"></i> OK</span>
                                    @endif
                                </td>
                                <td><button type="button" class="bud-toggle-months" data-bud-toggle="{{ $idx }}">Months <i class="fa fa-chevron-down"></i></button></td>
                            </tr>
                            <tr class="bud-months-row is-collapsed" id="bud-months-{{ $idx }}">
                                <td colspan="7">
                                    <div class="bud-months-inner">
                                        <table class="pcat-table" style="min-width:0;">
                                            <thead>
                                                <tr><th>Month</th><th>Auto</th><th>Manual</th><th>Spent</th><th>Cumulative</th><th>Remaining</th></tr>
                                            </thead>
                                            <tbody>
                                                @foreach($cat['months'] as $m)
                                                    <tr @class(['bud-status-pill--over' => $m['over_limit']])>
                                                        <td>{{ $m['month_label'] }}</td>
                                                        <td>{{ number_format($m['auto_amount'], 2, '.', ',') }}</td>
                                                        <td>{{ number_format($m['manual_amount'], 2, '.', ',') }}</td>
                                                        <td>{{ number_format($m['spent'], 2, '.', ',') }}</td>
                                                        <td>{{ number_format($m['cumulative_spent'], 2, '.', ',') }}</td>
                                                        <td>{{ number_format($m['remaining'], 2, '.', ',') }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Manual actual spend entries --}}
        <div class="bud-show__section">
            <h2><span>Manual actual spend entries</span></h2>
            <form method="post" action="{{ route('budget.actuals.store', $budget['id']) }}" class="bud-actuals-form">
                @csrf
                <div>
                    <label for="bud-actual-category">Category</label>
                    <select name="category" id="bud-actual-category" required>
                        @foreach($budget['items'] as $item)
                            <option value="{{ $item['category'] }}">{{ $categoryLabels[$item['category']] ?? $item['category'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="bud-actual-month">Month</label>
                    <input type="month" name="month" id="bud-actual-month" required value="{{ now()->format('Y-m') }}">
                </div>
                <div>
                    <label for="bud-actual-amount">Amount</label>
                    <input type="number" step="0.01" min="0" name="amount" id="bud-actual-amount" required>
                </div>
                <div>
                    <label for="bud-actual-note">Note (optional)</label>
                    <input type="text" name="note" id="bud-actual-note" maxlength="255">
                </div>
                <div>
                    <button type="submit" class="bud-btn--primary" style="width:100%;justify-content:center;"><i class="fa fa-plus"></i> Add</button>
                </div>
            </form>

            @if($actuals->isEmpty())
                <p style="font-size:12px;color:var(--muted);">No manual entries logged yet.</p>
            @else
                <div class="pcat-table-wrap">
                    <table class="pcat-table">
                        <thead>
                            <tr><th>Month</th><th>Category</th><th>Amount</th><th>Note</th><th></th></tr>
                        </thead>
                        <tbody>
                            @foreach($actuals as $entry)
                                <tr>
                                    <td>{{ \Illuminate\Support\Carbon::parse($entry['month'])->format('M Y') }}</td>
                                    <td>{{ $categoryLabels[$entry['category']] ?? $entry['category'] }}</td>
                                    <td>{{ $entry['amount_fmt'] }}</td>
                                    <td>{{ $entry['note'] ?: '—' }}</td>
                                    <td style="text-align:right;">
                                        <form method="post" action="{{ route('budget.actuals.destroy', [$budget['id'], $entry['id']]) }}" onsubmit="return confirm('Remove this entry?');">
                                            @csrf @method('delete')
                                            <button type="submit" class="bud-btn--danger" style="padding:4px 8px;"><i class="fa fa-trash-can"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Edit budget modal --}}
<div id="bud-edit-modal" class="bud-modal" role="dialog" aria-modal="true" aria-hidden="true" style="position:fixed;inset:0;z-index:130;display:flex;justify-content:center;align-items:flex-start;padding:max(12px,2.5vh) 14px;overflow:auto;opacity:0;visibility:hidden;pointer-events:none;transition:opacity .2s ease,visibility .2s ease;">
    <div class="bud-modal__backdrop" data-bud-edit-close style="position:fixed;inset:0;background:rgba(15,23,42,.55);"></div>
    <div class="bud-modal__panel" style="position:relative;width:100%;max-width:480px;margin:auto;border-radius:14px;border:1px solid var(--border);background:var(--card);box-shadow:0 20px 48px rgba(0,0,0,.32);">
        <div class="bud-modal__head" style="display:flex;justify-content:space-between;align-items:center;padding:11px 14px;border-bottom:1px solid var(--border);">
            <h2 style="margin:0;font-size:15px;font-weight:800;">Edit budget</h2>
            <button type="button" data-bud-edit-close style="width:32px;height:32px;border:1px solid var(--border);border-radius:9px;background:transparent;color:inherit;cursor:pointer;font-size:17px;">&times;</button>
        </div>
        <form method="post" action="{{ route('budget.update', $budget['id']) }}" style="padding:14px;">
            @csrf @method('put')
            <div class="bud-field">
                <label for="bud-edit-name">Name</label>
                <input type="text" name="name" id="bud-edit-name" maxlength="255" required value="{{ $budget['name'] }}">
            </div>
            <div class="bud-field">
                <label for="bud-edit-type">Type</label>
                <select name="type" id="bud-edit-type" required>
                    @foreach($types as $key => $label)
                        <option value="{{ $key }}" @selected($budget['type'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="bud-field">
                <label for="bud-edit-start">Start date</label>
                <input type="date" name="start_date" id="bud-edit-start" required value="{{ $budget['start_date'] }}">
            </div>
            <div class="bud-field">
                <label for="bud-edit-end">End date</label>
                <input type="date" name="end_date" id="bud-edit-end" value="{{ $budget['end_date'] }}">
            </div>
            <button type="submit" class="bud-btn--primary" style="width:100%;justify-content:center;"><i class="fa fa-floppy-disk"></i> Save changes</button>
        </form>
    </div>
</div>

<script>
(function () {
    document.querySelectorAll('[data-bud-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var row = document.getElementById('bud-months-' + btn.getAttribute('data-bud-toggle'));
            if (!row) return;
            row.classList.toggle('is-collapsed');
        });
    });

    var editModal = document.getElementById('bud-edit-modal');
    var editOpen = document.getElementById('bud-edit-open');
    function setEditOpen(open) {
        editModal.style.opacity = open ? '1' : '0';
        editModal.style.visibility = open ? 'visible' : 'hidden';
        editModal.style.pointerEvents = open ? 'auto' : 'none';
        editModal.setAttribute('aria-hidden', open ? 'false' : 'true');
    }
    editOpen?.addEventListener('click', function () { setEditOpen(true); });
    editModal.querySelectorAll('[data-bud-edit-close]').forEach(function (el) {
        el.addEventListener('click', function () { setEditOpen(false); });
    });
})();
</script>
@endsection
