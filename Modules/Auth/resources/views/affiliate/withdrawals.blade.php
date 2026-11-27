@extends('theme::layouts.app', ['title' => 'Withdrawals', 'heading' => 'Withdrawals'])

@section('content')
@include('auth::affiliate.partials.styles')

@php
    $canRequest = $blocker === null;
    $hasHistory = $history->total() > 0;
    $money = fn (float $v) => $currency.' '.number_format($v, 2);
@endphp

<div class="aff-wrap">
    <div class="aff-header">
        <div>
            <h1 class="aff-title"><i class="fa fa-money-bill-transfer" aria-hidden="true"></i>Withdrawals</h1>
            <p class="aff-sub">Ask for your commission to be paid to your bank account. An admin reviews each request, then pays it by bank transfer.</p>
        </div>
        @if($canRequest && $hasHistory)
            <div class="aff-header-actions">
                <button type="button" class="aff-add-btn" id="aff-open-withdraw"><i class="fa fa-money-bill-transfer" aria-hidden="true"></i> Request withdrawal</button>
            </div>
        @endif
    </div>

    @if(session('success'))
        <div class="aff-msg"><i class="fa fa-circle-check" aria-hidden="true"></i> {{ session('success') }}</div>
    @endif
    @if($errors->has('withdrawal'))
        <div class="aff-msg aff-msg-err"><i class="fa fa-triangle-exclamation" aria-hidden="true"></i> {{ $errors->first('withdrawal') }}</div>
    @elseif($errors->has('amount') && ! $canRequest)
        {{-- The form is gone (e.g. the balance changed meanwhile), so show why here. --}}
        <div class="aff-msg aff-msg-err"><i class="fa fa-triangle-exclamation" aria-hidden="true"></i> {{ $errors->first('amount') }}</div>
    @endif

    <div class="aff-stats">
        <div class="aff-stat">
            <div class="aff-stat-icon" style="background:color-mix(in srgb,#16a34a 13%,transparent);color:#16a34a"><i class="fa fa-wallet" aria-hidden="true"></i></div>
            <div class="aff-stat-label">Available to withdraw</div>
            <div class="aff-stat-value aff-stat-value--money">{{ $money($balance['available']) }}</div>
            <div class="aff-stat-sub">Earned minus requested</div>
        </div>
        <div class="aff-stat">
            <div class="aff-stat-icon" style="background:color-mix(in srgb,#f59e0b 14%,transparent);color:#d97706"><i class="fa fa-hourglass-half" aria-hidden="true"></i></div>
            <div class="aff-stat-label">In review</div>
            <div class="aff-stat-value aff-stat-value--money">{{ $money($balance['in_review']) }}</div>
            <div class="aff-stat-sub">Requested, not paid yet</div>
        </div>
        <div class="aff-stat">
            <div class="aff-stat-icon" style="background:color-mix(in srgb,#6366f1 13%,transparent);color:#6366f1"><i class="fa fa-building-columns" aria-hidden="true"></i></div>
            <div class="aff-stat-label">Paid out</div>
            <div class="aff-stat-value aff-stat-value--money">{{ $money($balance['paid_out']) }}</div>
            <div class="aff-stat-sub">Sent to your bank</div>
        </div>
        <div class="aff-stat">
            <div class="aff-stat-icon" style="background:color-mix(in srgb,#0ea5e9 13%,transparent);color:#0ea5e9"><i class="fa fa-sack-dollar" aria-hidden="true"></i></div>
            <div class="aff-stat-label">Total earned</div>
            <div class="aff-stat-value aff-stat-value--money">{{ $money($balance['earned']) }}</div>
            <div class="aff-stat-sub"><a href="{{ route('affiliate.payments') }}" style="color:inherit">See commission details</a></div>
        </div>
    </div>

    @if($openRequest)
        <div class="aff-banner">
            <i class="fa fa-hourglass-half" aria-hidden="true"></i>
            <div>
                <strong>{{ $openRequest->formattedAmount() }}</strong> requested on {{ $openRequest->created_at->format('d M Y') }}
                is <span class="aff-status aff-status--{{ $openRequest->statusTone() }}">{{ $openRequest->statusLabel() }}</span>.
                @if($openRequest->isApproved())
                    It is approved and will be sent to your bank soon.
                @else
                    You will see the result here once an admin reviews it.
                @endif
            </div>
            @if($openRequest->isPending())
                <form method="POST" action="{{ route('affiliate.withdrawals.cancel', $openRequest) }}" onsubmit="return confirm('Cancel this withdrawal request? The amount goes back to your available balance.')">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="aff-btn aff-btn--danger aff-btn--tall"><i class="fa fa-xmark" aria-hidden="true"></i> Cancel request</button>
                </form>
            @endif
        </div>
    @elseif($blocker && $blocker['reason'] === 'settings')
        <div class="aff-banner aff-banner--warn">
            <i class="fa fa-list-check" aria-hidden="true"></i>
            <div>{{ $blocker['message'] }}</div>
            <a href="{{ route('affiliate.settings') }}" class="aff-btn aff-btn--tall"><i class="fa fa-gear" aria-hidden="true"></i> Open Settings</a>
        </div>
    @elseif($blocker && $blocker['reason'] === 'balance')
        <div class="aff-banner aff-banner--warn">
            <i class="fa fa-wallet" aria-hidden="true"></i>
            <div>{{ $blocker['message'] }}</div>
        </div>
    @endif

    @if(! $hasHistory && $canRequest)
        <div class="aff-form-card" style="margin-bottom:30px">
            <h3 class="aff-form-card-title">Request your first withdrawal</h3>
            <p class="aff-form-card-sub">Choose how much to withdraw. The money is sent to the bank account in your Settings.</p>
            <form method="POST" action="{{ route('affiliate.withdrawals.store') }}" data-aff-once>
                @csrf
                <div style="max-width:560px">
                    @include('auth::affiliate.partials.withdrawal-fields', ['idPrefix' => 'wd-inline'])
                </div>
                <div class="aff-form-card-foot" style="justify-content:flex-start">
                    <button type="submit" class="aff-submit-btn"><i class="fa fa-paper-plane" aria-hidden="true"></i> Send request</button>
                </div>
            </form>
        </div>
    @endif

    <div class="aff-section">
        <div class="aff-section-head">
            <h2 class="aff-section-title">
                <i class="fa fa-clock-rotate-left" aria-hidden="true"></i>Your requests
                @if($hasHistory)<span class="aff-section-meta">{{ $history->total() }}</span>@endif
            </h2>
        </div>

        @if(! $hasHistory)
            <div class="aff-empty">
                <div class="aff-empty-icon"><i class="fa fa-money-bill-transfer" aria-hidden="true"></i></div>
                <h3 class="aff-empty-title">No withdrawal requests yet</h3>
                <p class="aff-empty-sub">Your requests and their status show here: in review, approved, paid or rejected.</p>
            </div>
        @else
            <div class="aff-card">
                <table class="aff-table">
                    <thead>
                        <tr>
                            <th>Requested on</th>
                            <th style="text-align:right">Amount</th>
                            <th>Paid to</th>
                            <th>Status</th>
                            <th>Details</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($history as $w)
                            <tr>
                                <td data-label="Requested on" style="white-space:nowrap">
                                    <div>{{ $w->created_at->format('d M Y') }}</div>
                                    <div class="aff-muted">{{ $w->created_at->format('h:i A') }}</div>
                                </td>
                                <td data-label="Amount" style="text-align:right;white-space:nowrap"><span class="aff-num">{{ $w->formattedAmount() }}</span></td>
                                <td data-label="Paid to">
                                    <div>{{ $w->bank_name }}</div>
                                    <div class="aff-muted"><span class="aff-mono">{{ $w->maskedAccountNumber() }}</span></div>
                                </td>
                                <td data-label="Status"><span class="aff-status aff-status--{{ $w->statusTone() }}">{{ $w->statusLabel() }}</span></td>
                                <td data-label="Details">
                                    <div class="aff-detail-lines">
                                        @if($w->paid_at)
                                            Paid {{ $w->paid_at->format('d M Y') }} · Ref <strong>{{ $w->payment_reference }}</strong>
                                        @elseif($w->approved_at && $w->isApproved())
                                            Approved {{ $w->approved_at->format('d M Y') }}, payment on the way
                                        @elseif($w->rejected_at)
                                            Rejected {{ $w->rejected_at->format('d M Y') }}: <strong>{{ $w->admin_note }}</strong>
                                        @elseif($w->cancelled_at)
                                            You cancelled it on {{ $w->cancelled_at->format('d M Y') }}
                                        @else
                                            Waiting for review
                                        @endif
                                        @if($w->note)<br>Your note: {{ $w->note }}@endif
                                        @if($w->admin_note && ! $w->rejected_at)<br>Admin note: {{ $w->admin_note }}@endif
                                    </div>
                                </td>
                                <td style="text-align:right">
                                    @if($w->isPending())
                                        <form method="POST" action="{{ route('affiliate.withdrawals.cancel', $w) }}" onsubmit="return confirm('Cancel this withdrawal request? The amount goes back to your available balance.')">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="aff-btn aff-btn--danger"><i class="fa fa-xmark" aria-hidden="true"></i> Cancel</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($history->hasPages())
                <div class="aff-pager">{{ $history->links() }}</div>
            @endif
        @endif
    </div>
</div>

@if($canRequest && $hasHistory)
    <div class="aff-modal-overlay @if($errors->has('amount') || $errors->has('note')) is-open @endif" id="aff-withdraw-modal" role="dialog" aria-modal="true" aria-labelledby="aff-withdraw-title">
        <div class="aff-modal-backdrop" data-aff-close></div>
        <div class="aff-modal-shell">
            <div class="aff-modal-head">
                <div>
                    <h2 class="aff-modal-title" id="aff-withdraw-title">Request withdrawal</h2>
                    <p class="aff-modal-sub">The money is sent to the bank account in your Settings.</p>
                </div>
                <button class="aff-modal-close" type="button" data-aff-close aria-label="Close"><i class="fa fa-xmark" aria-hidden="true"></i></button>
            </div>
            <form method="POST" action="{{ route('affiliate.withdrawals.store') }}" data-aff-once>
                @csrf
                <div class="aff-modal-body">
                    @include('auth::affiliate.partials.withdrawal-fields', ['idPrefix' => 'wd-modal'])
                </div>
                <div class="aff-modal-foot">
                    <button type="button" class="aff-cancel-btn" data-aff-close>Cancel</button>
                    <button type="submit" class="aff-submit-btn"><i class="fa fa-paper-plane" aria-hidden="true"></i> Send request</button>
                </div>
            </form>
        </div>
    </div>
@endif

<script>
(function () {
    document.querySelectorAll('[data-aff-withdraw-all]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = btn.closest('form').querySelector('[data-aff-amount]');
            if (input) { input.value = btn.getAttribute('data-aff-withdraw-all'); input.focus(); }
        });
    });
    // One submit only: a double click must not send two requests.
    document.querySelectorAll('form[data-aff-once]').forEach(function (form) {
        form.addEventListener('submit', function () {
            var btn = form.querySelector('button[type=submit]');
            if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa fa-spinner fa-spin" aria-hidden="true"></i> Sending'; }
        });
    });

    var modal = document.getElementById('aff-withdraw-modal');
    var openBtn = document.getElementById('aff-open-withdraw');
    if (!modal || !openBtn) return;
    function openModal() {
        modal.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        var input = modal.querySelector('[data-aff-amount]');
        if (input) setTimeout(function () { input.focus(); input.select(); }, 30);
    }
    function closeModal() { modal.classList.remove('is-open'); document.body.style.overflow = ''; }
    openBtn.addEventListener('click', openModal);
    modal.querySelectorAll('[data-aff-close]').forEach(function (el) { el.addEventListener('click', closeModal); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal(); });
    if (modal.classList.contains('is-open')) document.body.style.overflow = 'hidden';
})();
</script>
@endsection
