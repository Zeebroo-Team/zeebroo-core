@extends('theme::layouts.app', ['title' => 'Cheque '.$cheque->cheque_number, 'heading' => 'Cheque payment'])

@section('content')
@php
    $grn = $cheque->goodsReceiveNote;
    $ledger = $cheque->ledgerTransaction;
@endphp
@include('product::partials.catalog-hub-styles')
<style>
.cheque-status{font-size:11px;font-weight:700;padding:3px 10px;border-radius:999px;border:1px solid var(--border);white-space:nowrap;}
.cheque-status--cleared{border-color:color-mix(in srgb,#22c55e 45%,var(--border));background:color-mix(in srgb,#22c55e 12%,transparent);color:color-mix(in srgb,#22c55e 80%,var(--text));}
.cheque-status--pending,.cheque-status--due{border-color:color-mix(in srgb,#3b82f6 40%,var(--border));background:color-mix(in srgb,#3b82f6 10%,transparent);color:color-mix(in srgb,#3b82f6 75%,var(--text));}
.cheque-status--overdue{border-color:color-mix(in srgb,#f59e0b 45%,var(--border));background:color-mix(in srgb,#f59e0b 12%,transparent);color:color-mix(in srgb,#f59e0b 85%,var(--text));}
.cheque-show-cards{
    display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin:0 0 16px;
}
@media(max-width:640px){.cheque-show-cards{grid-template-columns:1fr;}}
.cheque-show-card{
    position:relative;box-sizing:border-box;border-radius:12px;overflow:hidden;
    border:1px solid color-mix(in srgb,var(--border) 92%,transparent);
    background:color-mix(in srgb,var(--card) 98%,transparent);
    box-shadow:0 1px 2px rgba(0,0,0,.05);
}
.cheque-show-card__rail{
    position:absolute;left:0;top:0;bottom:0;width:3px;
    background:color-mix(in srgb,var(--cheque-card-accent,var(--primary)) 72%,transparent);
}
.cheque-show-card__body{padding:10px 12px 10px 15px;}
.cheque-show-card__label{
    margin:0 0 4px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);
}
.cheque-show-card__value{
    margin:0;font-size:20px;font-weight:800;color:var(--text);font-variant-numeric:tabular-nums;line-height:1.2;
}
.cheque-show-card__suffix{display:block;margin-top:2px;font-size:11px;font-weight:600;color:var(--muted);}
.cheque-show-details{
    display:grid;gap:12px 20px;grid-template-columns:repeat(2,minmax(0,1fr));
    margin:0 0 14px;padding:12px 14px;border:1px solid var(--border);border-radius:10px;
}
@media(max-width:560px){.cheque-show-details{grid-template-columns:1fr;}}
.cheque-show-details dt{margin:0;font-size:11px;color:var(--muted);}
.cheque-show-details dd{margin:2px 0 0;font-size:14px;font-weight:700;color:var(--text);}
.cheque-due--overdue{color:color-mix(in srgb,#f59e0b 90%,var(--text));}
</style>

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
            <p class="muted" style="margin:0 0 4px;font-size:12px;">Due {{ $cheque->due_date->format('M j, Y') }}</p>
            <h2 style="margin:0;font-size:18px;font-weight:800;color:var(--text);">Cheque #{{ $cheque->cheque_number }}</h2>
            @if($grn?->purchase?->supplier)
                <p class="muted" style="margin:6px 0 0;font-size:12px;">{{ $grn->purchase->supplier->name }}</p>
            @endif
        </div>
        <span class="cheque-status cheque-status--{{ $displayStatus }}">{{ $cheque->displayStatusLabel() }}</span>
    </div>

    <div class="cheque-show-cards" role="region" aria-label="Cheque summary">
        <article class="cheque-show-card" style="--cheque-card-accent:#6366f1;">
            <span class="cheque-show-card__rail" aria-hidden="true"></span>
            <div class="cheque-show-card__body">
                <p class="cheque-show-card__label">Amount</p>
                <p class="cheque-show-card__value">{{ number_format((float) $cheque->amount, 2) }}</p>
                @if(filled($currency))
                    <span class="cheque-show-card__suffix">{{ $currency }}</span>
                @endif
            </div>
        </article>
        <article class="cheque-show-card" style="--cheque-card-accent:{{ $displayStatus === 'overdue' ? '#f59e0b' : '#3b82f6' }};">
            <span class="cheque-show-card__rail" aria-hidden="true"></span>
            <div class="cheque-show-card__body">
                <p class="cheque-show-card__label">Due date</p>
                <p class="cheque-show-card__value @if($displayStatus === 'overdue') cheque-due--overdue @endif">{{ $cheque->due_date->format('M j, Y') }}</p>
            </div>
        </article>
        <article class="cheque-show-card" style="--cheque-card-accent:{{ $cheque->isCleared() ? '#22c55e' : '#94a3b8' }};">
            <span class="cheque-show-card__rail" aria-hidden="true"></span>
            <div class="cheque-show-card__body">
                <p class="cheque-show-card__label">{{ $cheque->isCleared() ? 'Cleared' : 'Recorded' }}</p>
                <p class="cheque-show-card__value" style="font-size:16px;">{{ $cheque->paidAt()?->format('M j, Y g:i A') ?? '—' }}</p>
            </div>
        </article>
    </div>

    <h3 style="margin:0 0 10px;font-size:14px;font-weight:700;color:var(--text);">Details</h3>
    <dl class="cheque-show-details">
        <div>
            <dt>Pay from account</dt>
            <dd>{{ $cheque->deductAccount?->deductOptionLabel() ?? '—' }}</dd>
        </div>
        <div>
            <dt>Goods receive note</dt>
            <dd>
                @if($grn)
                    <a href="{{ route('purchase.grn.show', ['goodsReceiveNote' => $grn, 'tab' => 'payment']) }}" class="pcat-link">{{ $grn->grn_number }}</a>
                @else
                    —
                @endif
            </dd>
        </div>
        <div>
            <dt>Purchase order</dt>
            <dd>
                @if($grn?->purchase)
                    <a href="{{ route('purchase.show', $grn->purchase) }}" class="pcat-link">{{ $grn->purchase->po_number }}</a>
                @else
                    —
                @endif
            </dd>
        </div>
        <div>
            <dt>Supplier</dt>
            <dd>{{ $grn?->purchase?->supplier?->name ?? '—' }}</dd>
        </div>
        @if($ledger)
            <div>
                <dt>Ledger payment</dt>
                <dd>{{ number_format((float) $ledger->amount, 2) }}@if(filled($currency)) {{ $currency }}@endif</dd>
            </div>
            <div>
                <dt>Ledger posted</dt>
                <dd class="muted" style="font-weight:600;font-size:13px;">{{ $ledger->created_at->format('M j, Y g:i A') }}</dd>
            </div>
        @endif
        @if($cheque->user)
            <div>
                <dt>Recorded by</dt>
                <dd class="muted" style="font-weight:600;font-size:13px;">{{ $cheque->user->name ?? $cheque->user->email }}</dd>
            </div>
        @endif
    </dl>

    @if(filled($cheque->notes))
        <div style="margin-bottom:14px;padding:12px 14px;border:1px solid var(--border);border-radius:10px;">
            <h3 style="margin:0 0 8px;font-size:13px;font-weight:700;color:var(--text);">Notes</h3>
            <p class="muted" style="margin:0;font-size:13px;line-height:1.45;">{{ $cheque->notes }}</p>
        </div>
    @endif

    @if($canDeduct ?? false)
        <div style="margin-bottom:14px;padding:12px 14px;border:1px solid var(--border);border-radius:10px;">
            <h3 style="margin:0 0 8px;font-size:14px;font-weight:700;color:var(--text);">Deduct from account</h3>
            <p class="muted" style="margin:0 0 12px;font-size:12px;line-height:1.45;">
                When this cheque is presented, deduct <strong style="color:var(--text);">{{ number_format((float) $cheque->amount, 2) }}</strong>@if(filled($currency)) {{ $currency }}@endif from your current account and mark the cheque cleared.
            </p>
            @if($cheque->deduct_account_id || ($canPayByCheque ?? false))
                <form method="post" action="{{ route('purchase.cheques.deduct', $cheque) }}" style="margin:0;" onsubmit="return confirm('Deduct {{ number_format((float) $cheque->amount, 2) }}@if(filled($currency)) {{ $currency }}@endif from the selected account?');">
                    @csrf
                    @if(! $cheque->deduct_account_id)
                        <div class="pcat-field" style="margin-bottom:12px;max-width:360px;">
                            <label for="cheque-deduct-account">Pay from account</label>
                            <select id="cheque-deduct-account" name="deduct_account_id" required>
                                <option value="">Select account…</option>
                                @foreach($accounts as $accountRow)
                                    @if(($accountRow->bankType?->slug ?? '') === 'current-account')
                                        <option value="{{ $accountRow->id }}" @selected((string) old('deduct_account_id') === (string) $accountRow->id)>{{ $accountRow->deductOptionLabel() }}</option>
                                    @endif
                                @endforeach
                            </select>
                            @error('deduct_account_id')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
                        </div>
                    @else
                        <p class="muted" style="margin:0 0 12px;font-size:12px;">
                            Account: <strong style="color:var(--text);">{{ $cheque->deductAccount?->deductOptionLabel() }}</strong>
                        </p>
                    @endif
                    <button type="submit" class="linkbtn" style="padding:10px 18px;font-size:13px;display:inline-flex;align-items:center;gap:8px;">
                        <i class="fa fa-building-columns" aria-hidden="true"></i> Deduct from account
                    </button>
                </form>
            @else
                <p class="muted" style="margin:0;font-size:12px;">
                    @if(Route::has('account.onboarding'))
                        <a href="{{ route('account.onboarding') }}" class="pcat-link">Add a current account</a> to deduct this cheque.
                    @else
                        Add a current account for this business to deduct this cheque.
                    @endif
                </p>
            @endif
            @if(! ($canPayByCheque ?? false) && ($hasPaymentAccounts ?? false))
                <p class="muted" style="margin:8px 0 0;font-size:11px;">Cheque deduction requires a <strong style="color:var(--text);">current account</strong>.</p>
            @endif
        </div>
    @elseif($cheque->isCleared())
        <p class="muted" style="margin:0 0 14px;font-size:13px;">
            <i class="fa fa-check" aria-hidden="true"></i> This cheque amount has been deducted from the account.
        </p>
    @endif

    <div style="display:flex;flex-wrap:wrap;gap:8px;">
        <a href="{{ route('purchase.cheques.index') }}" class="linkbtn" style="padding:7px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
            <i class="fa fa-arrow-left"></i> All cheques
        </a>
        @if($grn)
            <a href="{{ route('purchase.grn.show', ['goodsReceiveNote' => $grn, 'tab' => 'payment']) }}" class="linkbtn" style="padding:7px 12px;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                <i class="fa fa-receipt"></i> View GRN
            </a>
        @endif
    </div>
</div>
@endsection
