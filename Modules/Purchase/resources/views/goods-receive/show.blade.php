@extends('theme::layouts.app', ['title' => 'Goods receive note', 'heading' => 'Goods receive note'])

@section('content')
@php
    $activeTab = $activeTab ?? 'overview';
    $grnShowTabUrl = fn (string $tab) => route('purchase.grn.show', ['goodsReceiveNote' => $grn, 'tab' => $tab]);
@endphp
@include('product::partials.catalog-hub-styles')
<style>
.grn-pay-cards{
    display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin:0 0 16px;
}
@media(max-width:640px){.grn-pay-cards{grid-template-columns:1fr;}}
.grn-pay-card{
    position:relative;box-sizing:border-box;border-radius:12px;overflow:hidden;
    border:1px solid color-mix(in srgb,var(--border) 92%,transparent);
    background:color-mix(in srgb,var(--card) 98%,transparent);
    box-shadow:0 1px 2px rgba(0,0,0,.05);
}
.grn-pay-card__rail{
    position:absolute;left:0;top:0;bottom:0;width:3px;
    background:color-mix(in srgb,var(--grn-pay-accent,var(--primary)) 72%,transparent);
}
.grn-pay-card__body{padding:10px 12px 10px 15px;}
.grn-pay-card__label{
    margin:0 0 4px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);
}
.grn-pay-card__value{
    margin:0;font-size:20px;font-weight:800;color:var(--text);font-variant-numeric:tabular-nums;line-height:1.2;
}
.grn-pay-card__suffix{display:block;margin-top:2px;font-size:11px;font-weight:600;color:var(--muted);}
.grn-show-tabs{
    display:flex;flex-wrap:wrap;gap:6px;margin:0 0 14px;padding:0;
    border-bottom:1px solid color-mix(in srgb,var(--border) 80%,transparent);
}
.grn-show-tabs__tab{
    display:inline-flex;align-items:center;gap:6px;
    padding:8px 14px 10px;margin:0 0 -1px;
    font-size:12px;font-weight:700;color:var(--muted);text-decoration:none;
    border:1px solid transparent;border-bottom:none;border-radius:8px 8px 0 0;
    background:transparent;font-family:inherit;cursor:pointer;
}
.grn-show-tabs__tab:hover{color:var(--text);border-color:color-mix(in srgb,var(--border) 70%,transparent);background:color-mix(in srgb,var(--card) 90%,transparent);}
.grn-show-tabs__tab.is-active{
    color:var(--text);
    border-color:color-mix(in srgb,var(--primary) 30%,var(--border));
    background:color-mix(in srgb,var(--primary) 8%,var(--card));
}
.grn-show-tabs__badge{
    font-size:10px;font-weight:700;padding:1px 6px;border-radius:999px;
    background:color-mix(in srgb,#f59e0b 18%,transparent);color:var(--text);
}
.grn-show-panel[hidden]{display:none !important;}
.grn-show-overview-grid{
    display:grid;gap:12px 20px;grid-template-columns:repeat(2,minmax(0,1fr));
    margin:0 0 14px;padding:12px 14px;border:1px solid var(--border);border-radius:10px;
}
@media(max-width:560px){.grn-show-overview-grid{grid-template-columns:1fr;}}
.grn-show-overview-grid dt{margin:0;font-size:11px;color:var(--muted);}
.grn-show-overview-grid dd{margin:2px 0 0;font-size:14px;font-weight:700;color:var(--text);}
.grn-ledger-table .grn-ledger-cheque-detail strong{font-weight:700;}
</style>
@include('purchase::goods-receive.partials.grn-payment-styles')

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('purchase::partials.purchase-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="pcat-banner pcat-banner--err" role="alert">{{ $errors->first() }}</div>
    @endif

    <div style="display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:14px;">
        <div>
            <p class="muted" style="margin:0 0 4px;font-size:12px;">Received {{ $grn->received_date->format('M j, Y') }}</p>
            <h2 style="margin:0;font-size:18px;font-weight:800;color:var(--text);">{{ $grn->grn_number }}</h2>
            <p class="muted" style="margin:6px 0 0;font-size:12px;">
                PO <a href="{{ route('purchase.show', $grn->purchase) }}" class="pcat-link">{{ $grn->purchase?->po_number }}</a>
                @if($grn->purchase?->supplier) · {{ $grn->purchase->supplier->name }}@endif
            </p>
        </div>
        <div style="max-width:280px;">
            @include('purchase::goods-receive.partials.payment-status', [
                'grn' => $grn,
                'currency' => $currency,
                'compact' => false,
            ])
        </div>
    </div>

    <nav class="grn-show-tabs" aria-label="GRN sections">
        <a href="{{ $grnShowTabUrl('overview') }}" class="grn-show-tabs__tab @if($activeTab === 'overview') is-active @endif" @if($activeTab === 'overview') aria-current="page" @endif>
            <i class="fa fa-circle-info" aria-hidden="true"></i> Overview
        </a>
        <a href="{{ $grnShowTabUrl('items') }}" class="grn-show-tabs__tab @if($activeTab === 'items') is-active @endif" @if($activeTab === 'items') aria-current="page" @endif>
            <i class="fa fa-boxes-stacked" aria-hidden="true"></i> Line items
            <span class="grn-show-tabs__badge" style="background:color-mix(in srgb,var(--primary) 12%,transparent);color:var(--muted);">{{ $grn->items->count() }}</span>
        </a>
        <a href="{{ $grnShowTabUrl('payment') }}" class="grn-show-tabs__tab @if($activeTab === 'payment') is-active @endif" @if($activeTab === 'payment') aria-current="page" @endif>
            <i class="fa fa-wallet" aria-hidden="true"></i> Payment
            @if($amountOutstanding > 0.005 && $grnTotal > 0.005)
                <span class="grn-show-tabs__badge">{{ number_format($amountOutstanding, 2) }} due</span>
            @endif
        </a>
    </nav>

    {{-- Overview --}}
    <section class="grn-show-panel" id="grn-show-panel-overview" @if($activeTab !== 'overview') hidden @endif>
        <dl class="grn-show-overview-grid">
            <div>
                <dt>Received date</dt>
                <dd>{{ $grn->received_date->format('M j, Y') }}</dd>
            </div>
            <div>
                <dt>GRN total @if(filled($currency))({{ $currency }})@endif</dt>
                <dd>{{ number_format((float) $grn->total, 2) }}</dd>
            </div>
            <div>
                <dt>Purchase order</dt>
                <dd><a href="{{ route('purchase.show', $grn->purchase) }}" class="pcat-link">{{ $grn->purchase?->po_number ?? '—' }}</a></dd>
            </div>
            <div>
                <dt>Supplier</dt>
                <dd>{{ $grn->purchase?->supplier?->name ?? '—' }}</dd>
            </div>
            @if($grn->reference)
                <div>
                    <dt>Reference</dt>
                    <dd>{{ $grn->reference }}</dd>
                </div>
            @endif
            <div>
                <dt>Stock</dt>
                <dd>@if($grn->stock_applied)<span style="color:color-mix(in srgb,#22c55e 80%,var(--text));"><i class="fa fa-check"></i> Updated</span>@else<span class="muted">Not applied</span>@endif</dd>
            </div>
            <div>
                <dt>Payment status</dt>
                <dd>
                    @if($isFullyPaid)
                        Fully paid
                    @elseif($hasPayment)
                        Partially paid · {{ number_format($amountOutstanding, 2) }} outstanding
                    @else
                        Unpaid
                    @endif
                </dd>
            </div>
            @if($grn->payment_method === \Modules\Purchase\Models\Purchase::PAYMENT_CHEQUE && ($grn->payment_reference || $grn->cheque_due_date))
                <div style="grid-column:1/-1;">
                    <dt>Cheque</dt>
                    <dd class="muted" style="font-weight:600;font-size:13px;">
                        @if($grn->payment_reference)#{{ $grn->payment_reference }}@endif
                        @if($grn->payment_reference && $grn->cheque_due_date) · @endif
                        @if($grn->cheque_due_date)Due {{ $grn->cheque_due_date->format('M j, Y') }}@endif
                    </dd>
                </div>
            @endif
        </dl>

        @if($grn->notes)
            <div style="margin-bottom:14px;padding:12px 14px;border:1px solid var(--border);border-radius:10px;">
                <h3 style="margin:0 0 8px;font-size:13px;font-weight:700;color:var(--text);">Notes</h3>
                <p class="muted" style="margin:0;font-size:13px;line-height:1.45;">{{ $grn->notes }}</p>
            </div>
        @endif

        <p class="muted" style="margin:0;font-size:12px;">
            <a href="{{ $grnShowTabUrl('items') }}" class="pcat-link">View line items</a>
            ·
            <a href="{{ $grnShowTabUrl('payment') }}" class="pcat-link">View payment details</a>
        </p>
    </section>

    {{-- Line items --}}
    <section class="grn-show-panel" id="grn-show-panel-items" @if($activeTab !== 'items') hidden @endif>
        <div class="pcat-table-wrap">
            <table class="pcat-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Qty received</th>
                        <th>Unit cost @if(filled($currency))({{ $currency }})@endif</th>
                        <th>Sell price @if(filled($currency))({{ $currency }})@endif</th>
                        <th style="text-align:right;">Line total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($grn->items as $item)
                        <tr>
                            <td>
                                <strong style="color:var(--text);">{{ $item->product?->name ?? 'Product #'.$item->product_id }}</strong>
                            </td>
                            <td class="muted">{{ rtrim(rtrim(number_format((float) $item->quantity_received, 3, '.', ''), '0'), '.') }}</td>
                            <td class="muted">{{ number_format((float) $item->unit_cost, 2) }}</td>
                            <td>
                                @if($item->selling_unit_price !== null)
                                    <strong style="color:var(--text);">{{ number_format((float) $item->selling_unit_price, 2) }}</strong>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td style="text-align:right;"><strong style="color:var(--text);">{{ number_format((float) $item->line_total, 2) }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" style="text-align:right;font-weight:700;">Total</td>
                        <td style="text-align:right;font-weight:800;font-size:15px;color:var(--text);">{{ number_format((float) $grn->total, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>

    {{-- Payment --}}
    <section class="grn-show-panel" id="grn-show-panel-payment" @if($activeTab !== 'payment') hidden @endif>
        <div class="grn-pay-cards" role="region" aria-label="Payment summary">
            <article class="grn-pay-card" style="--grn-pay-accent:#6366f1;">
                <span class="grn-pay-card__rail" aria-hidden="true"></span>
                <div class="grn-pay-card__body">
                    <p class="grn-pay-card__label">GRN total</p>
                    <p class="grn-pay-card__value">{{ number_format($grnTotal, 2) }}</p>
                    @if(filled($currency))
                        <span class="grn-pay-card__suffix">{{ $currency }}</span>
                    @endif
                </div>
            </article>
            <article class="grn-pay-card" style="--grn-pay-accent:#22c55e;">
                <span class="grn-pay-card__rail" aria-hidden="true"></span>
                <div class="grn-pay-card__body">
                    <p class="grn-pay-card__label">Paid</p>
                    <p class="grn-pay-card__value">{{ number_format($amountPaid, 2) }}</p>
                    @if(filled($currency))
                        <span class="grn-pay-card__suffix">{{ $currency }}</span>
                    @endif
                </div>
            </article>
            <article class="grn-pay-card" style="--grn-pay-accent:{{ $amountOutstanding > 0.005 ? '#f59e0b' : '#94a3b8' }};">
                <span class="grn-pay-card__rail" aria-hidden="true"></span>
                <div class="grn-pay-card__body">
                    <p class="grn-pay-card__label">Outstanding</p>
                    <p class="grn-pay-card__value" style="color:{{ $amountOutstanding > 0.005 ? 'var(--text)' : 'var(--muted)' }};">{{ number_format($amountOutstanding, 2) }}</p>
                    @if(filled($currency))
                        <span class="grn-pay-card__suffix">{{ $currency }}</span>
                    @endif
                </div>
            </article>
        </div>

        @php
            $pendingCheques = $grn->chequePayments->filter(fn ($row) => ! $row->isCleared());
        @endphp
        @if($pendingCheques->isNotEmpty())
            <div style="margin-bottom:14px;padding:12px 14px;border:1px solid color-mix(in srgb,#3b82f6 35%,var(--border));border-radius:10px;background:color-mix(in srgb,#3b82f6 8%,var(--card));">
                <h3 style="margin:0 0 8px;font-size:13px;font-weight:700;color:var(--text);">Cheque payments awaiting deduction</h3>
                <p class="muted" style="margin:0 0 10px;font-size:12px;line-height:1.45;">
                    These cheques are recorded but the account has not been debited yet. Open each cheque and use <strong style="color:var(--text);">Deduct from account</strong> when presented.
                </p>
                <ul style="margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:8px;">
                    @foreach($pendingCheques as $pendingCheque)
                        <li style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:8px;font-size:12px;">
                            <span>
                                <strong style="color:var(--text);">#{{ $pendingCheque->cheque_number }}</strong>
                                <span class="muted"> · {{ number_format((float) $pendingCheque->amount, 2) }}@if(filled($currency)) {{ $currency }}@endif · Due {{ $pendingCheque->due_date->format('M j, Y') }}</span>
                            </span>
                            @if(Route::has('purchase.cheques.show'))
                                <a href="{{ route('purchase.cheques.show', $pendingCheque) }}" class="linkbtn" style="padding:6px 12px;font-size:11px;text-decoration:none;">Deduct from account</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($grn->ledgerTransactions->isNotEmpty())
            @php
                $chequeByLedgerId = $grn->chequePayments->keyBy('ledger_transaction_id');
            @endphp
            <h3 style="margin:0 0 8px;font-size:13px;font-weight:700;color:var(--text);">Ledger payments</h3>
            <div class="pcat-table-wrap grn-ledger-table" style="margin-bottom:14px;">
                <table class="pcat-table" style="font-size:12px;">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Account</th>
                            <th>Payment</th>
                            <th style="text-align:right;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($grn->ledgerTransactions as $ledgerRow)
                            <tr>
                                <td class="muted">{{ $ledgerRow->created_at->format('M j, Y g:i A') }}</td>
                                <td>{{ $ledgerRow->deductAccount?->deductOptionLabel() ?? '—' }}</td>
                                <td>
                                    @include('purchase::goods-receive.partials.ledger-payment-details', [
                                        'ledger' => $ledgerRow,
                                        'cheque' => $chequeByLedgerId->get($ledgerRow->id),
                                    ])
                                </td>
                                <td style="text-align:right;"><strong style="color:var(--text);">{{ number_format((float) $ledgerRow->amount, 2) }}</strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($grn->chequePayments->isNotEmpty() && Route::has('purchase.cheques.index'))
                <p class="muted" style="margin:0 0 14px;font-size:12px;">
                    <a href="{{ route('purchase.cheques.index') }}" class="pcat-link">View all cheques</a>
                </p>
            @endif
        @endif

        @if(!$isFullyPaid && $grnTotal > 0.005)
            @if($hasPaymentAccounts)
                @php
                    $recordPayOption = old('payment_option', 'full');
                    $showRecordPartial = $recordPayOption === 'partial';
                    $outstandingFormatted = number_format($amountOutstanding, 2);
                    $recordPaymentMethod = old('payment_method', in_array($grn->payment_method, [\Modules\Purchase\Models\Purchase::PAYMENT_CASH, \Modules\Purchase\Models\Purchase::PAYMENT_CHEQUE], true) ? $grn->payment_method : \Modules\Purchase\Models\Purchase::PAYMENT_CASH);
                    if (! $canPayByCheque && $recordPaymentMethod === \Modules\Purchase\Models\Purchase::PAYMENT_CHEQUE) {
                        $recordPaymentMethod = \Modules\Purchase\Models\Purchase::PAYMENT_CASH;
                    }
                    $showRecordChequeRef = $canPayByCheque && $recordPaymentMethod === \Modules\Purchase\Models\Purchase::PAYMENT_CHEQUE;
                    $recordPaymentMethods = \Modules\Purchase\Models\Purchase::paymentMethods();
                @endphp
                <form method="post" action="{{ route('purchase.grn.pay', $grn) }}" class="grn-record-payment-panel" data-grn-record-payment data-can-pay-by-cheque="{{ $canPayByCheque ? '1' : '0' }}">
                    @csrf
                    <input type="hidden" name="return_to" value="show">

                    <div class="pcat-field" style="margin-bottom:12px;">
                        <label for="grn-record-payment-method">Payment method</label>
                        <select id="grn-record-payment-method" name="payment_method" required data-grn-record-payment-method>
                            <option value="{{ \Modules\Purchase\Models\Purchase::PAYMENT_CASH }}" @selected($recordPaymentMethod === \Modules\Purchase\Models\Purchase::PAYMENT_CASH)>{{ $recordPaymentMethods[\Modules\Purchase\Models\Purchase::PAYMENT_CASH] }}</option>
                            <option value="{{ \Modules\Purchase\Models\Purchase::PAYMENT_CHEQUE }}" @selected($recordPaymentMethod === \Modules\Purchase\Models\Purchase::PAYMENT_CHEQUE) @disabled(! $canPayByCheque)>{{ $recordPaymentMethods[\Modules\Purchase\Models\Purchase::PAYMENT_CHEQUE] }}</option>
                        </select>
                        @error('payment_method')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
                        @if(! $canPayByCheque)
                            <p class="muted" style="margin:6px 0 0;font-size:11px;line-height:1.4;">
                                Cheque requires a <strong style="color:var(--text);">current account</strong>.
                                @if(Route::has('account.onboarding'))
                                    <a href="{{ route('account.onboarding') }}" class="pcat-link">Add one</a>
                                @endif
                            </p>
                        @endif
                    </div>

                    <div class="pcat-field" id="grn-record-cheque-wrap" style="margin-bottom:12px;{{ $showRecordChequeRef ? '' : 'display:none;' }}">
                        <label for="grn-record-payment-reference">Cheque number</label>
                        <input id="grn-record-payment-reference" type="text" name="payment_reference" value="{{ old('payment_reference', $grn->payment_reference) }}" maxlength="120" placeholder="e.g. 001245" data-grn-record-cheque-ref @if($showRecordChequeRef) required @endif>
                        @error('payment_reference')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
                        <label for="grn-record-cheque-due-date" style="display:block;margin-top:10px;">Cheque due date</label>
                        <input id="grn-record-cheque-due-date" type="date" name="cheque_due_date" value="{{ old('cheque_due_date', $grn->cheque_due_date?->format('Y-m-d')) }}" data-grn-record-cheque-due @if($showRecordChequeRef) required @endif>
                        @error('cheque_due_date')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
                    </div>

                    <div class="grn-pay-settlement">
                        <div class="grn-pay-settlement__head">
                            <div>
                                <h4 class="grn-pay-settlement__title">Record payment</h4>
                                <p class="grn-pay-settlement__lead">Post a full or partial debit against this receipt.</p>
                            </div>
                            <div class="grn-pay-settlement__total">
                                <span class="grn-pay-settlement__total-label">Outstanding</span>
                                <span class="grn-pay-settlement__total-value">{{ $outstandingFormatted }}</span>
                                @if(filled($currency))
                                    <span class="muted" style="display:block;font-size:11px;margin-top:2px;">{{ $currency }}</span>
                                @endif
                            </div>
                        </div>

                        <fieldset class="grn-pay-choices" aria-label="Payment amount">
                            <label class="grn-pay-choices__card">
                                <input type="radio" name="payment_option" value="full" data-grn-record-pay-option @checked($recordPayOption !== 'partial')>
                                <span>
                                    <span class="grn-pay-choices__title">Pay in full</span>
                                    <span class="grn-pay-choices__hint">Clear the remaining balance in one posting.</span>
                                    <span class="grn-pay-choices__amount">{{ $outstandingFormatted }}@if(filled($currency)) {{ $currency }}@endif</span>
                                </span>
                            </label>
                            <label class="grn-pay-choices__card">
                                <input type="radio" name="payment_option" value="partial" data-grn-record-pay-option @checked($showRecordPartial)>
                                <span>
                                    <span class="grn-pay-choices__title">Partial payment</span>
                                    <span class="grn-pay-choices__hint">Pay less now; record another payment later.</span>
                                </span>
                            </label>
                        </fieldset>

                        <div id="grn-record-pay-amount-wrap" class="grn-pay-partial-box" style="{{ $showRecordPartial ? '' : 'display:none;' }}">
                            <div class="pcat-field" style="margin:0;">
                                <label for="grn-record-pay-amount">Amount for this payment</label>
                                <input id="grn-record-pay-amount" type="number" name="pay_amount" value="{{ old('pay_amount') }}" step="0.01" min="0.01" max="{{ $amountOutstanding }}" inputmode="decimal" placeholder="0.00" data-grn-record-pay-amount @if($showRecordPartial) required @endif>
                                <p class="grn-pay-partial-cap">Maximum {{ $outstandingFormatted }}@if(filled($currency)) {{ $currency }}@endif for this receipt.</p>
                                @error('pay_amount')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="pcat-field grn-pay-account-field">
                            <label for="grn-pay-account">Pay from account</label>
                            <select id="grn-pay-account" name="deduct_account_id" required data-grn-record-account-select>
                                <option value="">Select account…</option>
                                @foreach($accounts as $accountRow)
                                    <option
                                        value="{{ $accountRow->id }}"
                                        data-bank-slug="{{ $accountRow->bankType?->slug ?? '' }}"
                                        @selected((string) old('deduct_account_id') === (string) $accountRow->id)
                                    >{{ $accountRow->deductOptionLabel() }}</option>
                                @endforeach
                            </select>
                            @error('deduct_account_id')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <button type="submit" class="linkbtn grn-pay-submit" style="padding:10px 18px;font-size:13px;display:inline-flex;align-items:center;gap:8px;">
                        <i class="fa fa-circle-check" aria-hidden="true"></i> Confirm payment
                    </button>
                </form>
            @else
                <p class="muted" style="margin:0;font-size:12px;">
                    @if(Route::has('account.onboarding'))
                        <a href="{{ route('account.onboarding') }}" class="pcat-link">Add a bank account</a> to record payment.
                    @else
                        Add a bank account for this business to record payment.
                    @endif
                </p>
            @endif
        @elseif($isFullyPaid)
            <p class="muted" style="margin:0;font-size:13px;">This receipt is fully paid.</p>
        @endif
    </section>

    <div style="margin-top:14px;display:flex;flex-wrap:wrap;gap:8px;">
        <a href="{{ route('purchase.grn.index') }}" class="linkbtn" style="padding:7px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
            <i class="fa fa-arrow-left"></i> All GRNs
        </a>
        <a href="{{ route('purchase.grn.print', $grn) }}" target="_blank" class="linkbtn" style="padding:7px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
            <i class="fa fa-print"></i> Print
        </a>
        @if($grn->purchase?->canReceiveGoods())
            <a href="{{ route('purchase.grn.create', $grn->purchase) }}" class="linkbtn" style="padding:7px 12px;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                <i class="fa fa-plus"></i> Another receipt
            </a>
        @endif
    </div>
</div>

@once
<script>
(function () {
    if (window.__grnRecordPaymentInit) return;
    window.__grnRecordPaymentInit = true;

    var PAYMENT_CASH = @json(\Modules\Purchase\Models\Purchase::PAYMENT_CASH);
    var PAYMENT_CHEQUE = @json(\Modules\Purchase\Models\Purchase::PAYMENT_CHEQUE);

    document.querySelectorAll('[data-grn-record-payment]').forEach(function (form) {
        if (form.hasAttribute('data-grn-pay-form')) return;

        var partialWrap = form.querySelector('#grn-record-pay-amount-wrap');
        var partialInput = form.querySelector('[data-grn-record-pay-amount]');
        var methodSelect = form.querySelector('[data-grn-record-payment-method]');
        var chequeWrap = form.querySelector('#grn-record-cheque-wrap');
        var chequeRef = form.querySelector('[data-grn-record-cheque-ref]');
        var chequeDue = form.querySelector('[data-grn-record-cheque-due]');
        var accountSelect = form.querySelector('[data-grn-record-account-select]');
        var canPayByCheque = form.getAttribute('data-can-pay-by-cheque') === '1';

        function filterAccounts(method) {
            if (!accountSelect) return;
            var isCheque = method === PAYMENT_CHEQUE;
            Array.prototype.forEach.call(accountSelect.options, function (opt, idx) {
                if (idx === 0) {
                    opt.hidden = false;
                    return;
                }
                var slug = opt.getAttribute('data-bank-slug') || '';
                var hide = isCheque && slug !== 'current-account';
                opt.hidden = hide;
                if (hide && opt.selected) accountSelect.value = '';
            });
        }

        function syncPaymentMethod() {
            var method = methodSelect?.value || PAYMENT_CASH;
            if (!canPayByCheque && method === PAYMENT_CHEQUE) {
                method = PAYMENT_CASH;
                if (methodSelect) methodSelect.value = method;
            }
            var isCheque = method === PAYMENT_CHEQUE;
            if (chequeWrap) chequeWrap.style.display = isCheque ? '' : 'none';
            if (chequeRef) {
                chequeRef.required = isCheque;
                if (!isCheque) chequeRef.value = '';
            }
            if (chequeDue) {
                chequeDue.required = isCheque;
                if (!isCheque) chequeDue.value = '';
            }
            filterAccounts(method);
        }

        function syncPartial() {
            var isPartial = form.querySelector('[data-grn-record-pay-option][value="partial"]')?.checked;
            if (partialWrap) partialWrap.style.display = isPartial ? '' : 'none';
            if (partialInput) partialInput.required = !!isPartial;
        }

        methodSelect?.addEventListener('change', syncPaymentMethod);
        form.querySelectorAll('[data-grn-record-pay-option]').forEach(function (r) {
            r.addEventListener('change', syncPartial);
        });
        syncPaymentMethod();
        syncPartial();
    });
})();
</script>
@endonce
@endsection
