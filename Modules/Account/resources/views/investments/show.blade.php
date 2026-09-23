@extends('theme::layouts.app', ['title' => $investment->name, 'heading' => $investment->name])

@section('content')
@include('product::partials.catalog-hub-styles')
<div class="inv-show">
    <style>
        .inv-show{max-width:none;width:100%;margin:0;box-sizing:border-box;}
        .inv-show__top{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-start;justify-content:space-between;margin-bottom:14px;}
        .inv-show__back{display:inline-flex;align-items:center;gap:5px;padding:6px 11px;border-radius:9px;font-size:12px;font-weight:600;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 92%,transparent);color:var(--text);text-decoration:none;}
        .inv-show__back:hover{border-color:color-mix(in srgb,var(--primary) 40%,var(--border));background:color-mix(in srgb,var(--primary) 8%,transparent);}
        .inv-show__danger{display:inline-flex;align-items:center;gap:5px;padding:6px 11px;border-radius:9px;font-size:12px;font-weight:600;border:1px solid color-mix(in srgb,#ef4444 50%,var(--border));background:transparent;color:#f97373;cursor:pointer;}
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .inv-show__danger{color:#dc2626;}
        .inv-show__card{border:1px solid var(--border);border-radius:14px;background:var(--card);padding:16px 18px;box-shadow:0 12px 40px -28px rgba(0,0,0,.35);margin-bottom:12px;}
        .inv-show__head{display:flex;flex-wrap:wrap;align-items:flex-start;gap:10px;margin-bottom:10px;}
        .inv-show__head h1{margin:0;font-size:18px;font-weight:800;letter-spacing:-.03em;color:var(--text);}
        .inv-show__meta{margin:4px 0 0;font-size:12px;color:var(--muted);}
        .inv-show__pill{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:999px;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;border:1px solid color-mix(in srgb,var(--primary) 38%,var(--border));background:color-mix(in srgb,var(--primary) 11%,transparent);color:color-mix(in srgb,var(--primary) 72%,var(--text));}
        .inv-show__pill--overdue{border-color:color-mix(in srgb,#f97316 55%,var(--border));background:color-mix(in srgb,#f97316 14%,transparent);}
        .inv-show__desc{margin:0 0 12px;font-size:13px;line-height:1.45;color:var(--muted);white-space:pre-wrap;}
        .inv-show__grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;}
        .inv-show__tile{border-radius:10px;padding:10px 11px;border:1px solid color-mix(in srgb,var(--border) 85%,transparent);background:color-mix(in srgb,var(--card) 94%,transparent);}
        .inv-show__tile--hero{border-color:color-mix(in srgb,var(--primary) 40%,var(--border));background:linear-gradient(160deg,color-mix(in srgb,var(--primary) 12%,transparent),color-mix(in srgb,var(--card) 92%,transparent));}
        .inv-show__tile-lab{font-size:9px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);font-weight:700;margin-bottom:4px;display:block;}
        .inv-show__tile-val{font-size:14px;font-weight:800;font-variant-numeric:tabular-nums;color:var(--text);}
        .inv-show__section{margin-top:14px;padding-top:14px;border-top:1px solid color-mix(in srgb,var(--border) 80%,transparent);}
        .inv-show__section h2{margin:0 0 8px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);}
        .inv-show__kv{display:grid;gap:8px;font-size:12px;color:var(--muted);}
        .inv-show__kv strong{color:var(--text);font-weight:600;display:inline-block;min-width:9em;}
        .inv-show__scroll{max-height:340px;overflow:auto;border:1px solid var(--border);border-radius:10px;}
        .inv-show__table{width:100%;border-collapse:collapse;font-size:12px;}
        .inv-show__table th{text-align:left;padding:8px 10px;background:color-mix(in srgb,var(--card) 92%,transparent);color:var(--muted);font-size:10px;text-transform:uppercase;letter-spacing:.04em;border-bottom:1px solid var(--border);position:sticky;top:0;}
        .inv-show__table td{padding:8px 10px;border-bottom:1px solid color-mix(in srgb,var(--border) 75%,transparent);vertical-align:top;}
        .inv-show__table tr:last-child td{border-bottom:none;}
        .inv-show__empty{padding:18px;text-align:center;color:var(--muted);font-size:12px;}
        .inv-show__status{display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;padding:3px 7px;border-radius:999px;border:1px solid var(--border);}
        .inv-show__status--paid{border-color:color-mix(in srgb,#22c55e 45%,var(--border));background:color-mix(in srgb,#22c55e 12%,transparent);}
        .inv-show__status--late{border-color:color-mix(in srgb,#fb923c 55%,var(--border));background:color-mix(in srgb,#f97316 16%,transparent);}
        .inv-show__status--open{border-color:color-mix(in srgb,var(--border) 90%,transparent);background:color-mix(in srgb,var(--card) 88%,transparent);color:var(--muted);}
        .inv-show__btn{display:inline-flex;align-items:center;justify-content:center;gap:4px;padding:4px 8px;font-size:10px;font-weight:700;border-radius:7px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 90%,transparent);color:var(--text);cursor:pointer;text-decoration:none;}
        .inv-show__btn:hover{border-color:color-mix(in srgb,var(--primary) 40%,var(--border));background:color-mix(in srgb,var(--primary) 8%,transparent);}
        .inv-show__btn:disabled{opacity:.45;cursor:not-allowed;}
        .inv-show__btn--go{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 12%,transparent);}
        .inv-show-modal{position:fixed;inset:0;z-index:140;display:flex;justify-content:center;align-items:flex-start;padding:max(14px,2.8vh) 14px calc(14px + env(safe-area-inset-bottom));overflow:auto;box-sizing:border-box;opacity:0;visibility:hidden;pointer-events:none;transition:opacity .22s ease,visibility .22s ease;}
        .inv-show-modal.inv-show-modal--open{opacity:1;visibility:visible;pointer-events:auto;}
        .inv-show-modal__backdrop{position:fixed;inset:0;z-index:0;background:rgba(15,23,42,.54);backdrop-filter:blur(3px);}
        .inv-show-modal__panel{position:relative;z-index:1;width:100%;max-width:440px;background:var(--card);border:1px solid var(--border);border-radius:14px;box-shadow:0 22px 50px rgba(0,0,0,.32);overflow:hidden;display:flex;flex-direction:column;max-height:min(92vh,720px);}
        .inv-show-modal__head{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:11px 14px;border-bottom:1px solid var(--border);}
        .inv-show-modal__body{padding:12px 14px 14px;font-size:12px;overflow:auto;line-height:1.45;}
        .inv-show-modal__lbl{display:block;margin:8px 0 4px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.055em;color:var(--muted);}
        .inv-show-modal__summ{padding:10px 11px;border-radius:10px;border:1px solid color-mix(in srgb,var(--border) 80%,transparent);background:color-mix(in srgb,var(--primary) 6%,transparent);margin-bottom:4px;}
        .inv-show-modal__summ strong{font-size:17px;display:block;color:var(--text);margin-top:3px;font-weight:800;}
        .inv-show-modal select,.inv-show-modal input{width:100%;box-sizing:border-box;padding:8px 9px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);}
        .inv-show-modal__submit{width:100%;margin-top:12px;padding:9px;border-radius:9px;font-size:13px;font-weight:700;border:1px solid color-mix(in srgb,var(--btn-bg) 72%,var(--border));background:var(--btn-bg);color:#fff;cursor:pointer;}
        html.inv-show-modal-html-open,html.inv-show-modal-html-open body{overflow:hidden;}
        .inv-mode{display:grid;gap:6px;margin:10px 0 0;}
        .inv-mode label{display:flex;align-items:center;gap:7px;cursor:pointer;font-size:12px;color:var(--text);}
    </style>

    <div class="inv-show__top">
        <a href="{{ route('account.investments.index') }}" class="inv-show__back"><i class="fa fa-arrow-left"></i> All investments</a>
        <form method="post" action="{{ route('account.investments.destroy', $investment) }}" onsubmit="return confirm('Remove this investment record?');">
            @csrf @method('delete')
            <button type="submit" class="inv-show__danger"><i class="fa fa-trash-can"></i> Remove</button>
        </form>
    </div>

    <div class="inv-show__card">
        @if(session('status'))
            <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="pcat-banner pcat-banner--err" style="font-weight:600;">{{ $errors->first() }}</div>
        @endif

        <div class="inv-show__head">
            <div style="flex:1;min-width:0;">
                <h1>{{ $investment->name }}</h1>
                <p class="inv-show__meta"><i class="fa fa-tag"></i> {{ $investment->typeDisplayLabel() }} @if($investment->provider) &middot; {{ $investment->provider }} @endif</p>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:6px;">
                @if($summary['overdue_count'] > 0)
                    <span class="inv-show__pill inv-show__pill--overdue"><i class="fa fa-circle-exclamation"></i> Overdue</span>
                @endif
                <span class="inv-show__pill">{{ ucfirst($investment->status) }}</span>
            </div>
        </div>

        @if($investment->description)
            <p class="inv-show__desc">{{ $investment->description }}</p>
        @endif

        <div class="inv-show__grid">
            <div class="inv-show__tile inv-show__tile--hero">
                <span class="inv-show__tile-lab">Total invested</span>
                <span class="inv-show__tile-val">@if($currency){{ $currency }} @endif{{ number_format($summary['total_invested'], 2, '.', ',') }}</span>
            </div>
            <div class="inv-show__tile">
                <span class="inv-show__tile-lab">Contribution / period</span>
                <span class="inv-show__tile-val">@if($currency){{ $currency }} @endif{{ number_format((float) $investment->contribution_amount, 2, '.', ',') }}</span>
            </div>
            <div class="inv-show__tile">
                <span class="inv-show__tile-lab">Goal</span>
                <span class="inv-show__tile-val">@if($currency){{ $currency }} @endif{{ number_format($summary['goal_amount'], 2, '.', ',') }}</span>
            </div>
            <div class="inv-show__tile">
                <span class="inv-show__tile-lab">Progress</span>
                <span class="inv-show__tile-val">{{ $summary['progress_pct'] }}%</span>
            </div>
        </div>

        <div class="inv-show__section">
            <h2>Terms</h2>
            <div class="inv-show__kv">
                <div><strong>Payment mode</strong> {{ $investment->payment_mode === 'recurring' ? 'Recurring ('.str_replace('per_', 'per ', $investment->recurring_type).')' : 'One-time' }}</div>
                @if($investment->reference_number)
                    <div><strong>Reference</strong> {{ $investment->reference_number }}</div>
                @endif
                <div>
                    <strong>Schedule</strong>
                    Starts {{ $investment->start_date?->format('M j, Y') }}
                    @if($investment->maturity_date) &middot; Matures {{ $investment->maturity_date->format('M j, Y') }} @endif
                    @if($investment->schedule_valid_until_year) &middot; Valid until {{ $investment->schedule_valid_until_year }} @endif
                </div>
                @if($investment->expected_return_rate !== null)
                    <div><strong>Expected return</strong> {{ rtrim(rtrim(number_format((float) $investment->expected_return_rate, 4, '.', ''), '0'), '.') }}% / year</div>
                @endif
                @if($investment->deductAccount)
                    <div><strong>Default account</strong> {{ $investment->deductAccount->deductOptionLabel() }}</div>
                @endif
                @if($investment->remind_before_days !== null)
                    <div><strong>Reminder</strong> {{ (int) $investment->remind_before_days }} day{{ (int) $investment->remind_before_days === 1 ? '' : 's' }} before each due date</div>
                @endif
                @if($investment->notes)
                    <div><strong>Notes</strong> {{ $investment->notes }}</div>
                @endif
            </div>
        </div>

        <div class="inv-show__section">
            <h2>Contribution schedule @if($summary['schedule']->isNotEmpty())({{ $summary['schedule']->count() }} {{ $summary['schedule']->count() === 1 ? 'period' : 'periods' }})@endif</h2>
            @if($summary['schedule']->isEmpty())
                <p class="inv-show__empty">No schedule available.</p>
            @else
                @if($accounts->isEmpty())
                    <p style="margin:-4px 0 10px;font-size:11px;color:color-mix(in srgb,#f97316 70%,var(--muted));"><i class="fa fa-wallet"></i> Add a business account, or pay from the cash drawer instead.</p>
                @endif
                <div class="inv-show__scroll">
                    <table class="inv-show__table">
                        <thead>
                            <tr><th>#</th><th>Due date</th><th>Amount</th><th>Paid</th><th>Outstanding</th><th>Status</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            @foreach($summary['schedule'] as $row)
                                <tr>
                                    <td>{{ $row['period'] }}</td>
                                    <td>{{ \Illuminate\Support\Carbon::parse($row['due_ymd'])->format('M j, Y') }}</td>
                                    <td>@if($currency){{ $currency }} @endif{{ $row['amount_formatted'] }}</td>
                                    <td>@if($currency){{ $currency }} @endif{{ $row['paid_total_formatted'] }}</td>
                                    <td>@if($currency){{ $currency }} @endif{{ $row['outstanding_formatted'] }}</td>
                                    <td>
                                        @if($row['paid'])
                                            <span class="inv-show__status inv-show__status--paid"><i class="fa fa-circle-check"></i> Paid</span>
                                        @elseif($row['past_due_unpaid'])
                                            <span class="inv-show__status inv-show__status--late"><i class="fa fa-circle-exclamation"></i> {{ $row['status_label'] }}</span>
                                        @else
                                            <span class="inv-show__status inv-show__status--open"><i class="fa fa-clock"></i> {{ $row['status_label'] }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @unless($row['paid'])
                                            <button type="button" class="inv-show__btn inv-show__btn--go js-inv-contribute-open"
                                                data-occurrence="{{ $row['due_ymd'] }}"
                                                data-due-human="{{ \Illuminate\Support\Carbon::parse($row['due_ymd'])->format('M j, Y') }}"
                                                data-outstanding="{{ $row['outstanding'] }}"
                                                data-outstanding-fmt="@if($currency){{ $currency }} @endif{{ $row['outstanding_formatted'] }}"
                                            ><i class="fa fa-money-bill-wave"></i> Contribute</button>
                                        @endunless
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="inv-show__section">
            <h2>Ledger contributions logged</h2>
            @if($investment->ledgerTransactions->isEmpty())
                <p class="inv-show__empty">No contributions recorded yet.</p>
            @else
                <div class="inv-show__scroll" style="max-height:280px;">
                    <table class="inv-show__table">
                        <thead>
                            <tr><th>Occurred</th><th>Period</th><th>Amount</th><th>Account</th></tr>
                        </thead>
                        <tbody>
                            @foreach($investment->ledgerTransactions as $row)
                                <tr>
                                    <td>{{ $row->occurrence_date?->format('M j, Y') ?? '—' }}</td>
                                    <td>
                                        @if($row->period_number)
                                            {{ $row->period_number }}
                                            @if($row->periods_total_snapshot) / {{ $row->periods_total_snapshot }} @endif
                                        @else — @endif
                                    </td>
                                    <td style="font-weight:700;font-variant-numeric:tabular-nums;">
                                        @if($row->currency)<span style="opacity:.72;font-size:10px;">{{ $row->currency }}</span> @endif
                                        {{ number_format((float) $row->amount, 2, '.', ',') }}
                                    </td>
                                    <td>
                                        @if(($row->meta['pay_from'] ?? null) === 'cash_drawer')
                                            Cash drawer (till)
                                        @else
                                            {{ $row->deductAccount?->account_name ?? '—' }}
                                        @endif
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

@php($contributeModalShouldOpen = $errors->has('occurrence_date') || $errors->has('deduct_account_id') || $errors->has('amount'))
@php($contributeDueHumanFromOld = old('occurrence_date') ? \Carbon\Carbon::parse(old('occurrence_date'))->format('M j, Y') : null)
@php($contributePayFrom = old('pay_from', $accounts->isEmpty() ? 'cash_drawer' : 'account'))

<div id="inv-contribute-modal"
    class="inv-show-modal{{ $contributeModalShouldOpen ? ' inv-show-modal--open' : '' }}"
    role="dialog" aria-modal="true" aria-hidden="{{ $contributeModalShouldOpen ? 'false' : 'true' }}">
    <div class="inv-show-modal__backdrop" data-close-inv-contribute></div>
    <div class="inv-show-modal__panel">
        <div class="inv-show-modal__head">
            <h2 style="margin:0;font-size:14px;font-weight:800;">Record contribution</h2>
            <button type="button" style="width:31px;height:31px;border-radius:9px;border:1px solid var(--border);background:transparent;color:inherit;cursor:pointer;font-size:18px;" data-close-inv-contribute aria-label="Close">&times;</button>
        </div>
        <div class="inv-show-modal__body">
            @error('occurrence_date')<p style="margin:0 0 8px;font-size:11px;color:color-mix(in srgb,#f97316 85%,var(--text));">{{ $message }}</p>@enderror
            @error('deduct_account_id')<p style="margin:0 0 8px;font-size:11px;color:color-mix(in srgb,#f97316 85%,var(--text));">{{ $message }}</p>@enderror
            @error('amount')<p style="margin:0 0 8px;font-size:11px;color:color-mix(in srgb,#f97316 85%,var(--text));">{{ $message }}</p>@enderror

            <div class="inv-show-modal__summ">
                <span style="color:var(--muted);font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Due date</span>
                <strong id="inv-contribute-due-display">{{ $contributeDueHumanFromOld ?? '—' }}</strong>
            </div>

            <form method="post" action="{{ route('account.investments.contribute', $investment) }}">
                @csrf
                <input type="hidden" name="occurrence_date" id="inv-contribute-occurrence" value="{{ old('occurrence_date') }}">
                <span class="inv-show-modal__lbl">Amount (leave blank for the full outstanding amount)</span>
                <input type="number" step="0.01" min="0.01" name="amount" id="inv-contribute-amount" value="{{ old('amount') }}" placeholder="Outstanding amount">

                <div class="inv-mode">
                    <span class="inv-show-modal__lbl" style="margin-top:8px;">Pay from</span>
                    <label><input type="radio" name="pay_from" value="account" id="inv-pay-account" {{ $contributePayFrom === 'account' ? 'checked' : '' }} {{ $accounts->isEmpty() ? 'disabled' : '' }}> Business account</label>
                    <label><input type="radio" name="pay_from" value="cash_drawer" id="inv-pay-cash" {{ $contributePayFrom === 'cash_drawer' ? 'checked' : '' }}> Cash drawer (till)</label>
                </div>

                <div id="inv-pay-account-wrap" style="display:{{ $accounts->isNotEmpty() && $contributePayFrom === 'account' ? 'block' : 'none' }};margin-top:8px;">
                    <span class="inv-show-modal__lbl">Debit from account</span>
                    @if($accounts->isEmpty())
                        <p style="margin:8px 0 0;color:var(--muted);">Create a business account first, or pay from the cash drawer above.</p>
                    @else
                        <select name="deduct_account_id" id="inv-contribute-account">
                            <option value="">Select account…</option>
                            @foreach($accounts as $acc)
                                <option value="{{ $acc->id }}" {{ (int) old('deduct_account_id', $investment->deduct_account_id) === (int) $acc->id ? 'selected' : '' }}>{{ $acc->deductOptionLabel() }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>

                <button type="submit" class="inv-show-modal__submit"><i class="fa fa-circle-check"></i> Confirm contribution</button>
                <p style="margin:10px 0 0;font-size:10px;color:var(--muted);line-height:1.4;">Posts a ledger entry for this due date and reduces the chosen account or cash drawer balance.</p>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    function setHtmlOpen(on) { document.documentElement.classList.toggle('inv-show-modal-html-open', on); }
    function openModal(el) { if (!el) return; el.classList.add('inv-show-modal--open'); el.setAttribute('aria-hidden', 'false'); setHtmlOpen(true); }
    function closeModal(el) { if (!el) return; el.classList.remove('inv-show-modal--open'); el.setAttribute('aria-hidden', 'true'); setHtmlOpen(false); }

    var modal = document.getElementById('inv-contribute-modal');
    var occInput = document.getElementById('inv-contribute-occurrence');
    var dueDisplay = document.getElementById('inv-contribute-due-display');
    var amountInput = document.getElementById('inv-contribute-amount');
    var payAccount = document.getElementById('inv-pay-account');
    var payCash = document.getElementById('inv-pay-cash');
    var accountWrap = document.getElementById('inv-pay-account-wrap');

    function syncPayFromUi() {
        if (accountWrap) {
            accountWrap.style.display = (payAccount && payAccount.checked) ? 'block' : 'none';
        }
    }
    payAccount?.addEventListener('change', syncPayFromUi);
    payCash?.addEventListener('change', syncPayFromUi);

    modal?.querySelectorAll('[data-close-inv-contribute]').forEach(function (b) {
        b.addEventListener('click', function () { closeModal(modal); });
    });

    document.querySelectorAll('.js-inv-contribute-open').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var ymd = btn.getAttribute('data-occurrence') || '';
            var human = btn.getAttribute('data-due-human') || '—';
            var outstandingFmt = btn.getAttribute('data-outstanding-fmt') || '';
            if (occInput) occInput.value = ymd;
            if (dueDisplay) dueDisplay.textContent = human;
            if (amountInput) amountInput.placeholder = outstandingFmt || 'Outstanding amount';
            openModal(modal);
        });
    });

    if (modal && modal.classList.contains('inv-show-modal--open')) {
        setHtmlOpen(true);
    }
})();
</script>
@endsection
