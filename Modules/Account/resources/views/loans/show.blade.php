@extends('theme::layouts.app', ['title' => $loan->name, 'heading' => $loan->name])

@section('content')
<div class="loan-show">
    <style>
        .loan-show{max-width:none;width:100%;margin:0;box-sizing:border-box;}
        .loan-show__top{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-start;justify-content:space-between;margin-bottom:14px;}
        .loan-show__back{display:inline-flex;align-items:center;gap:5px;padding:6px 11px;border-radius:9px;font-size:12px;font-weight:600;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 92%,transparent);color:var(--text);text-decoration:none;}
        .loan-show__back:hover{border-color:color-mix(in srgb,var(--primary) 40%,var(--border));background:color-mix(in srgb,var(--primary) 8%,transparent);}
        .loan-show__danger{display:inline-flex;align-items:center;gap:5px;padding:6px 11px;border-radius:9px;font-size:12px;font-weight:600;border:1px solid color-mix(in srgb,#ef4444 50%,var(--border));background:transparent;color:#f97373;cursor:pointer;}
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .loan-show__danger{color:#dc2626;}
        .loan-show__card{border:1px solid var(--border);border-radius:14px;background:var(--card);padding:16px 18px;box-shadow:0 12px 40px -28px rgba(0,0,0,.35);margin-bottom:12px;}
        .loan-show__head{display:flex;flex-wrap:wrap;align-items:flex-start;gap:10px;margin-bottom:10px;}
        .loan-show__head h1{margin:0;font-size:18px;font-weight:800;letter-spacing:-.03em;color:var(--text);}
        .loan-show__bank{margin:4px 0 0;font-size:12px;color:var(--muted);}
        .loan-show__pill{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:999px;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;border:1px solid color-mix(in srgb,var(--primary) 38%,var(--border));background:color-mix(in srgb,var(--primary) 11%,transparent);color:color-mix(in srgb,var(--primary) 72%,var(--text));}
        .loan-show__pill--overdue{border-color:color-mix(in srgb,#f97316 55%,var(--border));background:color-mix(in srgb,#f97316 14%,transparent);}
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .loan-show__pill--overdue{color:#9a3412;}
        .loan-show__desc{margin:0 0 12px;font-size:13px;line-height:1.45;color:var(--muted);white-space:pre-wrap;}
        .loan-show__grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;}
        .loan-show__tile{border-radius:10px;padding:10px 11px;border:1px solid color-mix(in srgb,var(--border) 85%,transparent);background:color-mix(in srgb,var(--card) 94%,transparent);}
        .loan-show__tile--hero{border-color:color-mix(in srgb,var(--primary) 40%,var(--border));background:linear-gradient(160deg,color-mix(in srgb,var(--primary) 12%,transparent),color-mix(in srgb,var(--card) 92%,transparent));}
        .loan-show__tile-lab{font-size:9px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);font-weight:700;margin-bottom:4px;display:block;}
        .loan-show__tile-val{font-size:14px;font-weight:800;font-variant-numeric:tabular-nums;color:var(--text);}
        .loan-show__tile-cur{font-size:10px;font-weight:600;opacity:.75;margin-right:.12em;text-transform:uppercase;}
        .loan-show__section{margin-top:14px;padding-top:14px;border-top:1px solid color-mix(in srgb,var(--border) 80%,transparent);}
        .loan-show__section h2{margin:0 0 8px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);}
        .loan-show__kv{display:grid;gap:8px;font-size:12px;color:var(--muted);}
        .loan-show__kv strong{color:var(--text);font-weight:600;display:inline-block;min-width:9em;}
        .loan-show__scroll{max-height:280px;overflow:auto;border:1px solid var(--border);border-radius:10px;}
        .loan-show__table{width:100%;border-collapse:collapse;font-size:12px;}
        .loan-show__table th{text-align:left;padding:8px 10px;background:color-mix(in srgb,var(--card) 92%,transparent);color:var(--muted);font-size:10px;text-transform:uppercase;letter-spacing:.04em;border-bottom:1px solid var(--border);position:sticky;top:0;z-index:1;}
        .loan-show__table td{padding:8px 10px;border-bottom:1px solid color-mix(in srgb,var(--border) 75%,transparent);vertical-align:top;}
        .loan-show__table tr:last-child td{border-bottom:none;}
        .loan-show__empty{padding:18px;text-align:center;color:var(--muted);font-size:12px;}
        .loan-show__status{display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;padding:3px 7px;border-radius:999px;border:1px solid var(--border);}
        .loan-show__status--paid{border-color:color-mix(in srgb,#22c55e 45%,var(--border));background:color-mix(in srgb,#22c55e 12%,transparent);color:color-mix(in srgb,#bbf7d0 70%,var(--text));}
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .loan-show__status--paid{color:#166534;}
        .loan-show__status--open{border-color:color-mix(in srgb,var(--border) 90%,transparent);background:color-mix(in srgb,var(--card) 88%,transparent);color:var(--muted);}
        .loan-show__status--late{border-color:color-mix(in srgb,#fb923c 55%,var(--border));background:color-mix(in srgb,#f97316 16%,transparent);color:color-mix(in srgb,#fed7aa 82%,var(--text));}
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .loan-show__status--late{color:#9a3412;}
        .loan-show__status--external-paid{border-color:color-mix(in srgb,#38bdf8 50%,var(--border));background:color-mix(in srgb,#0ea5e9 14%,transparent);color:color-mix(in srgb,#e0f2fe 80%,var(--text));}
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .loan-show__status--external-paid{color:#0369a1;}
        .loan-show-modal__mode{display:grid;gap:6px;margin:10px 0 0;}
        .loan-show-modal__mode label{display:flex;align-items:center;gap:7px;cursor:pointer;font-size:12px;color:var(--text);}
        .loan-show-modal__mode input{accent-color:var(--primary);}
        .loan-show__inline-form{display:inline;}
        @keyframes loan-show-row-overdue{
            0%,100%{background-color:color-mix(in srgb,#f97316 11%,transparent);}
            50%{background-color:color-mix(in srgb,#ea580c 17%,transparent);}
        }
        .loan-show__table tr.loan-show__row--late > td{background-color:color-mix(in srgb,#f97316 11%,transparent);animation:loan-show-row-overdue 2.15s ease-in-out infinite;border-left:none;}
        .loan-show__table tr.loan-show__row--late > td:first-child{box-shadow:inset 3px 0 0 #ea580c;}
        @media (prefers-reduced-motion:reduce){
            .loan-show__table tr.loan-show__row--late > td{animation:none;}
        }
        .loan-show__amt{font-weight:800;font-variant-numeric:tabular-nums;}
        .loan-show__actions{display:flex;flex-wrap:wrap;gap:5px;}
        .loan-show__cell-actions{vertical-align:middle;width:1%;white-space:nowrap;}
        .loan-show__btn{
            display:inline-flex;align-items:center;justify-content:center;gap:4px;padding:4px 8px;font-size:10px;font-weight:700;
            border-radius:7px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 90%,transparent);color:var(--text);cursor:pointer;text-decoration:none;
        }
        .loan-show__btn:hover{border-color:color-mix(in srgb,var(--primary) 40%,var(--border));background:color-mix(in srgb,var(--primary) 8%,transparent);}
        .loan-show__btn:disabled{opacity:.45;cursor:not-allowed;}
        .loan-show__btn--go{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 12%,transparent);}
        .loan-show__notify{margin:0 0 14px;font-size:12px;padding:8px 11px;border-radius:10px;display:flex;align-items:flex-start;gap:8px;line-height:1.4;border:1px solid color-mix(in srgb,#22c55e 45%,var(--border));background:color-mix(in srgb,#22c55e 10%,transparent);}
        .loan-show__notify--err{border-color:color-mix(in srgb,#f87171 45%,var(--border));background:color-mix(in srgb,#f87171 10%,transparent);}
        .loan-show-modal{
            position:fixed;inset:0;z-index:140;display:flex;justify-content:center;align-items:flex-start;
            padding:max(14px,2.8vh) 14px calc(14px + env(safe-area-inset-bottom));overflow:auto;box-sizing:border-box;
            opacity:0;visibility:hidden;pointer-events:none;transition:opacity .22s ease,visibility .22s ease;
        }
        .loan-show-modal.loan-show-modal--open{opacity:1;visibility:visible;pointer-events:auto;}
        .loan-show-modal__backdrop{position:fixed;inset:0;z-index:0;background:rgba(15,23,42,.54);backdrop-filter:blur(3px);}
        .loan-show-modal__panel{
            position:relative;z-index:1;width:100%;max-width:440px;background:var(--card);border:1px solid var(--border);
            border-radius:14px;box-shadow:0 22px 50px rgba(0,0,0,.32);overflow:hidden;display:flex;flex-direction:column;max-height:min(92vh,720px);
        }
        .loan-show-modal__head{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:11px 14px;border-bottom:1px solid var(--border);}
        .loan-show-modal__head h2{margin:0;font-size:14px;font-weight:800;color:var(--text);}
        .loan-show-modal__close{width:31px;height:31px;display:grid;place-items:center;border-radius:9px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 88%,transparent);color:var(--text);cursor:pointer;font-size:18px;line-height:1;}
        .loan-show-modal__body{padding:12px 14px 14px;font-size:12px;overflow:auto;line-height:1.45;}
        .loan-show-modal__lbl{display:block;margin:8px 0 4px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.055em;color:var(--muted);}
        .loan-show-modal__summ{padding:10px 11px;border-radius:10px;border:1px solid color-mix(in srgb,var(--border) 80%,transparent);background:color-mix(in srgb,var(--primary) 6%,transparent);margin-bottom:4px;font-variant-numeric:tabular-nums;}
        .loan-show-modal__summ strong{font-size:17px;display:block;color:var(--text);margin-top:3px;font-weight:800;}
        .loan-show-modal select,.loan-show-modal input[type=text]{
            width:100%;box-sizing:border-box;padding:8px 9px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);
        }
        .loan-show-modal__submit{width:100%;margin-top:12px;padding:9px;border-radius:9px;font-size:13px;font-weight:700;border:1px solid color-mix(in srgb,var(--btn-bg) 72%,var(--border));background:var(--btn-bg);color:#fff;cursor:pointer;}
        .loan-show-modal__submit:hover{background:var(--btn-hover);color:#111827;}
        html.loan-show-modal-html-open, html.loan-show-modal-html-open body{overflow:hidden;}
        .loan-show-receipt-toolbar{display:flex;flex-wrap:wrap;gap:7px;margin-top:12px;margin-bottom:4px;}
        .loan-show-copy-toast{font-size:11px;margin-top:8px;color:color-mix(in srgb,#22c55e 70%,var(--muted));min-height:16px;}
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .loan-show-copy-toast{color:#166534;}
    </style>

    <div class="loan-show__top">
        <a href="{{ route('account.loans.index') }}" class="loan-show__back"><i class="fa fa-arrow-left"></i> All loans</a>
        <form method="post" action="{{ route('account.loans.destroy', $loan) }}" onsubmit="return confirm('Remove this loan record?');">
            @csrf
            @method('delete')
            <button type="submit" class="loan-show__danger"><i class="fa fa-trash-can"></i> Remove loan</button>
        </form>
    </div>

    <div class="loan-show__card">
        @if(session('status'))
            <div class="loan-show__notify" role="status"><i class="fa fa-circle-check" style="margin-top:2px;"></i>{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="loan-show__notify loan-show__notify--err" role="alert">
                <i class="fa fa-circle-exclamation" style="margin-top:2px;"></i>
                <ul style="margin:0;padding-left:18px;">
                    @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
                </ul>
            </div>
        @endif

        <div class="loan-show__head">
            <div style="flex:1;min-width:0;">
                <h1>{{ $loan->name }}</h1>
                <p class="loan-show__bank"><i class="fa fa-building-columns"></i> {{ $loan->bank?->name ?? '—' }}</p>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:6px;">
                @if($installmentOverdue)
                    <span class="loan-show__pill loan-show__pill--overdue"><i class="fa fa-circle-exclamation"></i> Overdue</span>
                @endif
                @if(!empty($loanSummary['cadence_label']))
                    <span class="loan-show__pill"><i class="fa fa-clock"></i> {{ $loanSummary['cadence_label'] }}</span>
                @endif
            </div>
        </div>

        @if($loan->description)
            <p class="loan-show__desc">{{ $loan->description }}</p>
        @endif

        <div class="loan-show__grid">
            <div class="loan-show__tile loan-show__tile--hero">
                <span class="loan-show__tile-lab">Principal</span>
                <span class="loan-show__tile-val">@if($loanCurrency)<span class="loan-show__tile-cur">{{ $loanCurrency }}</span>@endif{{ number_format((float) $loan->borrowed_amount, 2, '.', ',') }}</span>
            </div>
            <div class="loan-show__tile">
                <span class="loan-show__tile-lab">Payment · per period</span>
                <span class="loan-show__tile-val">@if($loanCurrency)<span class="loan-show__tile-cur">{{ $loanCurrency }}</span>@endif{{ $loanSummary['payment_formatted'] }}</span>
            </div>
            <div class="loan-show__tile">
                <span class="loan-show__tile-lab">Budget · monthly equiv.</span>
                <span class="loan-show__tile-val">@if($loanCurrency)<span class="loan-show__tile-cur">{{ $loanCurrency }}</span>@endif{{ $loanSummary['approx_monthly_formatted'] }}</span>
            </div>
        </div>

        <div class="loan-show__section">
            <h2>Terms</h2>
            <div class="loan-show__kv">
                <div><strong>Interest</strong> {{ $interestRateTypes[$loan->interest_rate_type] ?? $loan->interest_rate_type }} · {{ rtrim(rtrim(number_format((float) $loan->interest_rate, 4, '.', ''), '0'), '.') }}{{ $loan->interest_rate_type === \Modules\Account\Models\Loan::INTEREST_RATE_PERCENTAGE ? '% APR' : ' flat fee' }}</div>
                <div><strong>Installments</strong> {{ $loanSummary['period_count'] }} periods — {{ \Illuminate\Support\Str::limit($loanSummary['period_source'], 120) }}</div>
                @if($loan->first_installment_due_date || $loan->loan_ending_date)
                    <div>
                        <strong>Schedule</strong>
                        @if($loan->first_installment_due_date) First {{ $loan->first_installment_due_date->format('M j, Y') }} @endif
                        @if($loan->first_installment_due_date && $loan->loan_ending_date) · @endif
                        @if($loan->loan_ending_date) Last {{ $loan->loan_ending_date->format('M j, Y') }} @endif
                    </div>
                @endif
                @if($loan->deductAccount)
                    <div><strong>Debit account</strong> {{ $loan->deductAccount->deductOptionLabel() }}</div>
                @endif
                @if($loan->remind_before_days !== null)
                    <div><strong>Reminder</strong> {{ (int) $loan->remind_before_days }} day{{ (int) $loan->remind_before_days === 1 ? '' : 's' }} before each due date</div>
                @endif
            </div>
        </div>

        <div class="loan-show__section">
            <h2>Installment schedule @if($scheduleRows->isNotEmpty())({{ $scheduleRows->count() }} {{ $scheduleRows->count() === 1 ? 'date' : 'dates' }})@endif</h2>
            @if($scheduleRows->isEmpty())
                <p class="loan-show__empty">Set a first installment due date on the loan to build a schedule.</p>
            @else
                <p style="margin:0 0 10px;font-size:11px;line-height:1.45;color:var(--muted);max-width:82ch;"><strong style="color:var(--text);">Make payment</strong> posts the installment to your ledger and debits the account you choose. <strong style="color:var(--text);">Already paid</strong> marks the due date as settled without a ledger entry (for cash or external transfers). <strong style="color:var(--text);">Paid</strong> means a ledger row exists; <strong style="color:var(--text);">Already paid (outside ledger)</strong> is a manual mark only.</p>
                @if($accounts->isEmpty())
                    <p style="margin:-4px 0 10px;font-size:11px;color:color-mix(in srgb,#f97316 70%,var(--muted));"><i class="fa fa-wallet"></i> Add a business account to use <strong style="color:var(--text);">Make payment</strong> (ledger). You can still use <strong style="color:var(--text);">Already paid</strong> without an account.</p>
                @endif
                <div class="loan-show__scroll" style="max-height:400px;">
                    <table class="loan-show__table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Due date</th>
                                <th>Installment</th>
                                <th>Status</th>
                                <th class="loan-show__cell-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($scheduleRows as $srow)
                                @php($leg = $srow['ledger'] ?? null)
                                <tr @class(['loan-show__row--late' => $srow['past_due_unpaid']])>
                                    <td>{{ $srow['period'] }}</td>
                                    <td>{{ $srow['due']->format('M j, Y') }}</td>
                                    <td class="loan-show__amt">@if($loanCurrency)<span class="loan-show__tile-cur">{{ $loanCurrency }}</span>@endif{{ $srow['amount_formatted'] }}</td>
                                    <td>
                                        @if($srow['paid_via_ledger'] ?? false)
                                            <span class="loan-show__status loan-show__status--paid"><i class="fa fa-circle-check"></i>Paid</span>
                                        @elseif($srow['paid_outside_ledger_only'] ?? false)
                                            <span class="loan-show__status loan-show__status--external-paid"><i class="fa fa-check-double"></i>Already paid (outside ledger)</span>
                                        @elseif($srow['past_due_unpaid'])
                                            <span class="loan-show__status loan-show__status--late"><i class="fa fa-circle-exclamation"></i>Past due · unpaid</span>
                                        @else
                                            <span class="loan-show__status loan-show__status--open"><i class="fa fa-clock"></i>Outstanding</span>
                                        @endif
                                    </td>
                                    <td class="loan-show__cell-actions">
                                        <div class="loan-show__actions">
                                            @if(($srow['paid_via_ledger'] ?? false) && $leg)
                                                @php($accLabel = $leg->deductAccount?->deductOptionLabel() ?? '—')
                                                <button type="button" class="loan-show__btn js-loan-payment-open-receipt"
                                                    data-payment-due-human="{{ $srow['due']->format('M j, Y') }}"
                                                    data-payment-amount-fmt="{{ trim($loanCurrency.' '.number_format((float) $leg->amount, 2, '.', ',')) }}"
                                                    data-payment-account="{{ e($accLabel) }}"><i class="fa fa-receipt"></i>View receipt</button>
                                            @elseif($srow['paid_outside_ledger_only'] ?? false)
                                                <form method="post" action="{{ route('account.loans.installments.unmark-external', $loan) }}" class="loan-show__inline-form" onsubmit="return confirm('Remove this mark? The installment will show as unpaid until you record a payment.');">
                                                    @csrf
                                                    <input type="hidden" name="occurrence_date" value="{{ $srow['due_ymd'] }}">
                                                    <button type="submit" class="loan-show__btn"><i class="fa fa-rotate-left"></i>Undo mark</button>
                                                </form>
                                            @elseif(! ($srow['paid'] ?? false))
                                                <button type="button"
                                                    class="loan-show__btn loan-show__btn--go js-loan-payment-open-settle"
                                                    data-occurrence="{{ $srow['due_ymd'] }}"
                                                    data-due-human="{{ $srow['due']->format('M j, Y') }}"
                                                    data-amount-fmt-display="@if($loanCurrency){{ $loanCurrency }} @endif{{ $srow['amount_formatted'] }}"
                                                    data-has-accounts="{{ $accounts->isNotEmpty() ? '1' : '0' }}"
                                                    @if($accounts->isEmpty()) disabled title="Add an account first, or use Already paid" @endif><i class="fa fa-money-bill-wave"></i>Make payment</button>
                                                <button type="button"
                                                    class="loan-show__btn js-loan-payment-open-settle-external"
                                                    data-occurrence="{{ $srow['due_ymd'] }}"
                                                    data-due-human="{{ $srow['due']->format('M j, Y') }}"
                                                    data-amount-fmt-display="@if($loanCurrency){{ $loanCurrency }} @endif{{ $srow['amount_formatted'] }}"
                                                    data-has-accounts="{{ $accounts->isNotEmpty() ? '1' : '0' }}"><i class="fa fa-check"></i>Already paid</button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="loan-show__section">
            <h2>Ledger installments logged</h2>
            @if($ledgerRows->isEmpty())
                <p class="loan-show__empty">No installments in the ledger yet. Scheduled installments appear after the nightly task applies them.</p>
            @else
                <div class="loan-show__scroll" style="max-height:340px;">
                    <table class="loan-show__table">
                        <thead>
                            <tr>
                                <th>Occurred</th>
                                <th>Period</th>
                                <th>Amount</th>
                                <th>Account</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($ledgerRows as $row)
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
                                    <td>{{ $row->deductAccount?->account_name ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>

@php($settleModalShouldOpen = $errors->has('occurrence_date') || $errors->has('deduct_account_id'))
@php($settleDueHumanFromOld = old('occurrence_date') ? \Carbon\Carbon::parse(old('occurrence_date'))->format('M j, Y') : null)
@php($settleIntent = old('settle_intent', $accounts->isEmpty() ? 'external' : 'ledger'))

<div id="loan-settle-modal"
    class="loan-show-modal{{ $settleModalShouldOpen ? ' loan-show-modal--open' : '' }}"
    data-has-accounts="{{ $accounts->isNotEmpty() ? '1' : '0' }}"
    role="dialog"
    aria-modal="true"
    aria-labelledby="loan-settle-modal-title"
    aria-hidden="{{ $settleModalShouldOpen ? 'false' : 'true' }}">
    <div class="loan-show-modal__backdrop" data-close-loan-settle tabindex="-1"></div>
    <div class="loan-show-modal__panel">
        <div class="loan-show-modal__head">
            <h2 id="loan-settle-modal-title">Record installment</h2>
            <button type="button" class="loan-show-modal__close" data-close-loan-settle aria-label="Close">&times;</button>
        </div>
        <div class="loan-show-modal__body">
            @error('occurrence_date')
                <p style="margin:0 0 8px;font-size:11px;color:color-mix(in srgb,#f97316 85%,var(--text));">{{ $message }}</p>
            @enderror
            @error('deduct_account_id')
                <p style="margin:0 0 8px;font-size:11px;color:color-mix(in srgb,#f97316 85%,var(--text));">{{ $message }}</p>
            @enderror

            <div class="loan-show-modal__summ">
                <span style="color:var(--muted);font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Due date</span>
                <strong id="loan-settle-due-display" style="font-size:14px;">{{ $settleDueHumanFromOld ?? '—' }}</strong>
            </div>
            <span class="loan-show-modal__lbl">Installment amount</span>
            <div class="loan-show-modal__summ" style="margin-top:5px;"><strong id="loan-settle-amount-display">{{ $loanSummary['payment_formatted'] }}</strong></div>

            <div class="loan-show-modal__mode js-loan-settle-mode-wrap">
                <span class="loan-show-modal__lbl" style="margin-top:8px;">Recording option</span>
                <label><input type="radio" name="loan_settle_ui_mode" value="ledger" class="js-loan-settle-mode" id="loan-settle-mode-ledger" {{ $settleIntent === 'ledger' ? 'checked' : '' }} {{ $accounts->isEmpty() ? 'disabled' : '' }}> Record in ledger (debit account)</label>
                <label><input type="radio" name="loan_settle_ui_mode" value="external" class="js-loan-settle-mode" id="loan-settle-mode-external" {{ $settleIntent === 'external' ? 'checked' : '' }}> Already paid — no ledger entry</label>
            </div>

            <div class="js-loan-settle-panel-ledger" @style(['display' => (! $accounts->isEmpty() && $settleIntent === 'ledger') ? 'block' : 'none'])>
                <form method="post" action="{{ route('account.loans.installments.settle', $loan) }}">
                    @csrf
                    <input type="hidden" name="settle_intent" value="ledger">
                    <input type="hidden" name="occurrence_date" id="loan-settle-occurrence-ledger" class="js-loan-settle-occurrence" value="{{ old('occurrence_date') }}">
                    <span class="loan-show-modal__lbl">Debit from account</span>
                    @if($accounts->isEmpty())
                        <p style="margin:8px 0 0;color:var(--muted);">Create an account first (Accounts in your business), or use “Already paid” above.</p>
                    @else
                        <select name="deduct_account_id" id="loan-settle-account" required>
                            <option value="">Select account…</option>
                            @foreach($accounts as $acc)
                                <option value="{{ $acc->id }}" {{ (int) old('deduct_account_id', $loan->deduct_account_id) === (int) $acc->id ? 'selected' : '' }}>
                                    {{ $acc->deductOptionLabel() }}
                                </option>
                            @endforeach
                        </select>
                    @endif
                    <button type="submit" class="loan-show-modal__submit" {{ $accounts->isEmpty() ? 'disabled' : '' }}><i class="fa fa-circle-check"></i> Confirm payment</button>
                    <p style="margin:10px 0 0;font-size:10px;color:var(--muted);line-height:1.4;">Creates a ledger row for this due date and reduces the selected account balance by the installment amount.</p>
                </form>
            </div>

            <div class="js-loan-settle-panel-external" @style(['display' => ($accounts->isEmpty() || $settleIntent === 'external') ? 'block' : 'none'])>
                <form method="post" action="{{ route('account.loans.installments.mark-external', $loan) }}">
                    @csrf
                    <input type="hidden" name="settle_intent" value="external">
                    <input type="hidden" name="occurrence_date" id="loan-settle-occurrence-external" class="js-loan-settle-occurrence" value="{{ old('occurrence_date') }}">
                    <button type="submit" class="loan-show-modal__submit" style="background:color-mix(in srgb,#0ea5e9 55%,var(--btn-bg));border-color:color-mix(in srgb,#0ea5e9 40%,var(--border));"><i class="fa fa-check"></i> Mark as already paid</button>
                    <p style="margin:10px 0 0;font-size:10px;color:var(--muted);line-height:1.4;">Use when this installment was settled outside SociBiz. Does not post to the ledger or change account balances.</p>
                </form>
            </div>
        </div>
    </div>
</div>

<div id="loan-receipt-modal" class="loan-show-modal" role="dialog" aria-modal="true" aria-labelledby="loan-receipt-title" aria-hidden="true">
    <div class="loan-show-modal__backdrop" data-close-loan-receipt tabindex="-1"></div>
    <div class="loan-show-modal__panel">
        <div class="loan-show-modal__head">
            <h2 id="loan-receipt-title">Recorded payment</h2>
            <button type="button" class="loan-show-modal__close" data-close-loan-receipt aria-label="Close">&times;</button>
        </div>
        <div class="loan-show-modal__body">
            <div class="loan-show-modal__summ">
                <span style="color:var(--muted);font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Installment due</span>
                <strong id="loan-receipt-due" style="font-size:14px;">—</strong>
            </div>
            <span class="loan-show-modal__lbl">Amount</span>
            <div class="loan-show-modal__summ" style="margin-top:5px;"><strong id="loan-receipt-amount">—</strong></div>
            <span class="loan-show-modal__lbl">Debited account</span>
            <p id="loan-receipt-account" style="margin:4px 0 0;color:var(--text);font-weight:600;">—</p>
            <div class="loan-show-receipt-toolbar" aria-label="Receipt actions">
                <button type="button" class="loan-show__btn" id="loan-receipt-btn-print" title="Opens print dialog"><i class="fa fa-print" aria-hidden="true"></i>Print</button>
                <button type="button" class="loan-show__btn" id="loan-receipt-btn-copy" title="Copy receipt text"><i class="fa fa-copy" aria-hidden="true"></i>Copy</button>
                <button type="button" class="loan-show__btn loan-show__btn--go" id="loan-receipt-btn-pdf" title="Choose “Save as PDF” in the print dialog"><i class="fa fa-file-pdf" aria-hidden="true"></i>PDF</button>
            </div>
            <div id="loan-receipt-copy-toast" class="loan-show-copy-toast" role="status" aria-live="polite"></div>
            <button type="button" class="loan-show-modal__submit" style="margin-top:8px;background:color-mix(in srgb,var(--card) 70%,transparent);color:var(--text);border-color:var(--border);" data-close-loan-receipt><i class="fa fa-times"></i> Close</button>
        </div>
    </div>
</div>

<script>
var loanReceiptCtx = {
    loanName: @json($loan->name),
    businessName: @json($business->name ?? ''),
    printedAtHint: ''
};
(function(){
    function setHtmlOpen(on){
        document.documentElement.classList.toggle('loan-show-modal-html-open', on);
    }
    function openModal(el){
        if(!el)return;
        el.classList.add('loan-show-modal--open');
        el.setAttribute('aria-hidden','false');
        setHtmlOpen(true);
    }
    function closeModal(el){
        if(!el)return;
        el.classList.remove('loan-show-modal--open');
        el.setAttribute('aria-hidden','true');
        if(!document.querySelector('.loan-show-modal.loan-show-modal--open'))setHtmlOpen(false);
    }

    var settleModal=document.getElementById('loan-settle-modal');
    var settleOccInputs=[].slice.call(document.querySelectorAll('.js-loan-settle-occurrence'));
    var settleDue=document.getElementById('loan-settle-due-display');
    var settleAmt=document.getElementById('loan-settle-amount-display');
    var modeLedger=document.getElementById('loan-settle-mode-ledger');
    var modeExternal=document.getElementById('loan-settle-mode-external');
    var panelLedger=settleModal?settleModal.querySelector('.js-loan-settle-panel-ledger'):null;
    var panelExternal=settleModal?settleModal.querySelector('.js-loan-settle-panel-external'):null;

    function hasAccountsForSettle(){
        return settleModal&&settleModal.getAttribute('data-has-accounts')==='1';
    }

    function syncSettleOccurrence(ymd){
        settleOccInputs.forEach(function(inp){inp.value=ymd||'';});
    }

    function applyLoanSettleMode(mode){
        var ha=hasAccountsForSettle();
        if(mode==='ledger'&&!ha)mode='external';
        if(modeLedger&&modeExternal){
            if(mode==='ledger'){modeLedger.checked=true;}else{modeExternal.checked=true;}
        }
        if(panelLedger&&panelExternal){
            if(mode==='ledger'){
                panelLedger.style.display='block';
                panelExternal.style.display='none';
            }else{
                panelLedger.style.display='none';
                panelExternal.style.display='block';
            }
        }
    }

    if(modeLedger)modeLedger.addEventListener('change',function(){if(this.checked)applyLoanSettleMode('ledger');});
    if(modeExternal)modeExternal.addEventListener('change',function(){if(this.checked)applyLoanSettleMode('external');});

    if(settleModal){
        settleModal.querySelectorAll('[data-close-loan-settle]').forEach(function(b){
            b.addEventListener('click',function(){closeModal(settleModal);});
        });
    }

    document.querySelectorAll('.js-loan-payment-open-settle').forEach(function(btn){
        btn.addEventListener('click',function(){
            if(btn.disabled)return;
            var ymd=btn.getAttribute('data-occurrence')||'';
            var human=btn.getAttribute('data-due-human')||'—';
            var amtDisp=btn.getAttribute('data-amount-fmt-display')||btn.getAttribute('data-amount-num')||'—';
            syncSettleOccurrence(ymd);
            if(settleDue)settleDue.textContent=human;
            if(settleAmt)settleAmt.textContent=amtDisp;
            var haRow=(btn.getAttribute('data-has-accounts')||'')==='1';
            if(haRow){
                applyLoanSettleMode('ledger');
            }else{
                applyLoanSettleMode('external');
            }
            openModal(settleModal);
        });
    });

    document.querySelectorAll('.js-loan-payment-open-settle-external').forEach(function(btn){
        btn.addEventListener('click',function(){
            var ymd=btn.getAttribute('data-occurrence')||'';
            var human=btn.getAttribute('data-due-human')||'—';
            var amtDisp=btn.getAttribute('data-amount-fmt-display')||btn.getAttribute('data-amount-num')||'—';
            syncSettleOccurrence(ymd);
            if(settleDue)settleDue.textContent=human;
            if(settleAmt)settleAmt.textContent=amtDisp;
            applyLoanSettleMode('external');
            openModal(settleModal);
        });
    });

    if(settleModal&&settleModal.classList.contains('loan-show-modal--open')&&modeLedger&&modeExternal){
        applyLoanSettleMode(modeLedger.checked?'ledger':'external');
    }

    var receipt=document.getElementById('loan-receipt-modal');
    if(receipt){
        receipt.querySelectorAll('[data-close-loan-receipt]').forEach(function(b){
            b.addEventListener('click',function(){closeModal(receipt);});
        });
    }

    function escHtml(s){
        return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function getReceiptSnapshot(){
        return {
            due: (document.getElementById('loan-receipt-due')||{}).textContent||'—',
            amount: (document.getElementById('loan-receipt-amount')||{}).textContent||'—',
            account: (document.getElementById('loan-receipt-account')||{}).textContent||'—'
        };
    }

    function buildReceiptPlainText(){
        var r=getReceiptSnapshot();
        var lines=[
            'Installment payment receipt',
            'Loan: '+(loanReceiptCtx.loanName||'—'),
            'Business: '+(loanReceiptCtx.businessName||'—'),
            'Installment due: '+r.due,
            'Amount: '+r.amount,
            'Debited account: '+r.account,
            'Printed: '+(loanReceiptCtx.printedAtHint||'')
        ];
        return lines.join('\n');
    }

    function openReceiptPrintWindow(docTitle){
        var r=getReceiptSnapshot();
        var w=window.open('','_blank');
        if(!w){window.alert('Allow pop-ups to print or save as PDF.');return;}
        var title=docTitle||'Payment receipt';
        var html='<!DOCTYPE html><html><head><meta charset="utf-8"><title>'+escHtml(title)+'</title>';
        html+='<style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;padding:28px 32px;color:#111;line-height:1.45;}h1{font-size:20px;margin:0 0 6px;}h2{font-size:13px;font-weight:600;color:#444;margin:20px 0 8px;text-transform:uppercase;letter-spacing:.04em}.row{margin:6px 0;font-size:14px}.row strong{display:inline-block;min-width:9.5em;color:#333}.foot{margin-top:28px;font-size:11px;color:#666}</style></head><body>';
        html+='<h1>Installment payment receipt</h1>';
        html+='<div class="row"><strong>Loan</strong> '+escHtml(loanReceiptCtx.loanName)+'</div>';
        if(loanReceiptCtx.businessName){
            html+='<div class="row"><strong>Business</strong> '+escHtml(loanReceiptCtx.businessName)+'</div>';
        }
        html+='<h2>Payment</h2>';
        html+='<div class="row"><strong>Installment due</strong> '+escHtml(r.due)+'</div>';
        html+='<div class="row"><strong>Amount</strong> '+escHtml(r.amount)+'</div>';
        html+='<div class="row"><strong>Debited account</strong> '+escHtml(r.account)+'</div>';
        html+='<p class="foot">Generated '+escHtml(loanReceiptCtx.printedAtHint)+' · Use print dialog to print or save as PDF.</p>';
        html+='</body></html>';
        w.document.open();
        w.document.write(html);
        w.document.close();
        w.focus();
        var closeAfter=function(){try{w.close();}catch(e){}};
        if('onafterprint' in w){
            w.addEventListener('afterprint',closeAfter);
        }else{
            setTimeout(closeAfter,800);
        }
        setTimeout(function(){w.print();},150);
    }

    var copyToast=document.getElementById('loan-receipt-copy-toast');
    function showCopyToast(msg){
        if(copyToast)copyToast.textContent=msg||'';
    }

    var btnPrint=document.getElementById('loan-receipt-btn-print');
    var btnPdf=document.getElementById('loan-receipt-btn-pdf');
    var btnCopy=document.getElementById('loan-receipt-btn-copy');
    if(btnPrint)btnPrint.addEventListener('click',function(){openReceiptPrintWindow('Payment receipt');});
    if(btnPdf)btnPdf.addEventListener('click',function(){openReceiptPrintWindow('Payment receipt — PDF');});
    if(btnCopy)btnCopy.addEventListener('click',function(){
        var t=buildReceiptPlainText();
        showCopyToast('');
        if(navigator.clipboard&&navigator.clipboard.writeText){
            navigator.clipboard.writeText(t).then(function(){
                showCopyToast('Copied to clipboard.');
            }).catch(function(){
                window.prompt('Copy this receipt:',t);
            });
        }else{
            window.prompt('Copy this receipt:',t);
        }
    });

    document.querySelectorAll('.js-loan-payment-open-receipt').forEach(function(btn){
        btn.addEventListener('click',function(){
            loanReceiptCtx.printedAtHint = new Date().toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
            document.getElementById('loan-receipt-due').textContent=btn.getAttribute('data-payment-due-human')||'—';
            document.getElementById('loan-receipt-amount').textContent=btn.getAttribute('data-payment-amount-fmt')||'—';
            document.getElementById('loan-receipt-account').textContent=btn.getAttribute('data-payment-account')||'—';
            showCopyToast('');
            openModal(receipt);
        });
    });

    var open=@json($settleModalShouldOpen);
    var dueHumanOld=@json($settleDueHumanFromOld);
    if(open&&dueHumanOld&&document.getElementById('loan-settle-due-display')){
        document.getElementById('loan-settle-due-display').textContent=dueHumanOld;
        setHtmlOpen(true);
    }
})();
</script>
@endsection
