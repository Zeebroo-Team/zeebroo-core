@extends('theme::layouts.app', ['title' => 'Affiliate Withdrawals', 'heading' => 'Affiliate Withdrawals'])

@section('content')
@include('auth::affiliate.partials.styles')

@php
    $statusTabs = ['pending' => 'In review', 'approved' => 'Approved', 'paid' => 'Paid', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled', 'all' => 'All'];
    $baseQuery = array_filter(['affiliate' => $affiliate?->id, 'search' => $search !== '' ? $search : null]);
    $returnFields = ['return_status' => $status, 'return_affiliate' => $affiliate?->id, 'return_search' => $search];
    $reopenModal = $errors->any() ? old('_modal') : null;
@endphp

<div class="aff-wrap">
    <div class="aff-header">
        <div>
            <h1 class="aff-title"><i class="fa fa-money-bill-transfer" aria-hidden="true"></i>Affiliate Withdrawals</h1>
            <p class="aff-sub">Review commission payout requests. Approve a request, pay it by bank transfer to the account shown, then mark it as paid with the transfer reference. Rejecting returns the amount to the affiliate's balance.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="aff-msg"><i class="fa fa-circle-check" aria-hidden="true"></i> {{ session('success') }}</div>
    @endif
    @if($errors->has('withdrawal'))
        <div class="aff-msg aff-msg-err"><i class="fa fa-triangle-exclamation" aria-hidden="true"></i> {{ $errors->first('withdrawal') }}</div>
    @endif

    @if($affiliate)
        <div class="aff-banner">
            <i class="fa fa-filter" aria-hidden="true"></i>
            <div>Showing requests from <strong>{{ $affiliate->name }}</strong> ({{ $affiliate->email }})</div>
            <a href="{{ route('admin.affiliate-withdrawals.index', ['status' => $status]) }}" class="aff-btn aff-btn--tall"><i class="fa fa-xmark" aria-hidden="true"></i> Show all affiliates</a>
        </div>
    @endif

    <div class="aff-section-head">
        <nav class="aff-statusbar" aria-label="Filter by status">
            @foreach($statusTabs as $key => $label)
                <a href="{{ route('admin.affiliate-withdrawals.index', $baseQuery + ['status' => $key]) }}" class="{{ $status === $key ? 'is-active' : '' }}" @if($status === $key) aria-current="page" @endif>
                    {{ $label }} <span>{{ $counts[$key] ?? 0 }}</span>
                </a>
            @endforeach
        </nav>
        <form method="GET" action="{{ route('admin.affiliate-withdrawals.index') }}" class="aff-search" role="search" style="margin-bottom:14px">
            <input type="hidden" name="status" value="{{ $status }}">
            @if($affiliate)<input type="hidden" name="affiliate" value="{{ $affiliate->id }}">@endif
            <i class="fa fa-magnifying-glass" aria-hidden="true"></i>
            <input type="search" name="search" value="{{ $search }}" placeholder="Search affiliate name or email" aria-label="Search affiliates">
        </form>
    </div>

    @if($withdrawals->isEmpty())
        <div class="aff-empty">
            <div class="aff-empty-icon"><i class="fa fa-{{ $status === 'pending' ? 'circle-check' : 'money-bill-transfer' }}" aria-hidden="true"></i></div>
            <h3 class="aff-empty-title">{{ $status === 'pending' ? 'Nothing to review' : 'No requests here' }}</h3>
            <p class="aff-empty-sub">
                @if($status === 'pending')
                    New withdrawal requests from affiliates show up here.
                @else
                    No {{ $status === 'all' ? '' : mb_strtolower($statusTabs[$status]) }} withdrawal requests{{ $search !== '' ? ' match "'.$search.'"' : '' }}.
                @endif
            </p>
        </div>
    @else
        <div class="aff-card">
            <table class="aff-table">
                <thead>
                    <tr>
                        <th style="min-width:220px">Affiliate</th>
                        <th>Requested</th>
                        <th style="text-align:right">Amount</th>
                        <th>Pay to</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($withdrawals as $w)
                        <tr>
                            <td>
                                <a href="{{ route('admin.affiliates.show', $w->user_id) }}" class="aff-user aff-link-row">
                                    <span class="aff-avatar" aria-hidden="true">{{ strtoupper(mb_substr($w->user?->name ?? '?', 0, 1)) }}</span>
                                    <div style="min-width:0">
                                        <div class="aff-user-name">{{ $w->user?->name ?? 'Deleted user' }}</div>
                                        <div class="aff-user-email">{{ $w->user?->email }}</div>
                                    </div>
                                </a>
                            </td>
                            <td data-label="Requested" style="white-space:nowrap">
                                <div>{{ $w->created_at->format('d M Y') }}</div>
                                <div class="aff-muted">{{ $w->created_at->diffForHumans() }}</div>
                            </td>
                            <td data-label="Amount" style="text-align:right;white-space:nowrap"><span class="aff-num">{{ $w->formattedAmount() }}</span></td>
                            <td data-label="Pay to">
                                <div><strong>{{ $w->account_holder_name }}</strong></div>
                                <div class="aff-muted">{{ $w->bank_name }}, {{ $w->bank_branch }}</div>
                                <div class="aff-mono">{{ $w->account_number }}</div>
                            </td>
                            <td data-label="Status">
                                <span class="aff-status aff-status--{{ $w->statusTone() }}">{{ $w->statusLabel() }}</span>
                                @php
                                    $lines = array_values(array_filter([
                                        $w->paid_at ? 'Paid '.$w->paid_at->format('d M Y').' · Ref <strong>'.e($w->payment_reference).'</strong>'
                                            : ($w->rejected_at ? e($w->rejected_at->format('d M Y').': '.$w->admin_note)
                                            : ($w->approved_at ? 'Approved '.$w->approved_at->format('d M Y')
                                            : ($w->cancelled_at ? 'By the affiliate, '.$w->cancelled_at->format('d M Y') : null))),
                                        $w->reviewer && ! $w->cancelled_at ? 'By '.e($w->reviewer->name) : null,
                                        $w->note ? 'Affiliate note: '.e($w->note) : null,
                                        $w->admin_note && ! $w->rejected_at ? 'Admin note: '.e($w->admin_note) : null,
                                    ]));
                                @endphp
                                @if($lines)
                                    <div class="aff-detail-lines">{!! implode('<br>', $lines) !!}</div>
                                @endif
                            </td>
                            <td>
                                @php
                                    $data = 'data-id="'.$w->id.'" data-name="'.e($w->user?->name).'" data-amount="'.e($w->formattedAmount()).'" data-bank="'.e($w->account_holder_name.' · '.$w->bank_name.', '.$w->bank_branch.' · '.$w->account_number).'" data-requested="'.e($w->created_at->format('Y-m-d')).'"';
                                @endphp
                                <div class="aff-row-actions">
                                    @if($w->isPending())
                                        <button type="button" class="aff-btn aff-btn--ok" data-wd-open="approve" {!! $data !!}><i class="fa fa-check" aria-hidden="true"></i> Approve</button>
                                    @endif
                                    @if($w->isApproved())
                                        <button type="button" class="aff-btn aff-btn--ok" data-wd-open="paid" {!! $data !!}><i class="fa fa-building-columns" aria-hidden="true"></i> Mark as paid</button>
                                    @endif
                                    @if($w->isPending() || $w->isApproved())
                                        <button type="button" class="aff-btn aff-btn--danger" data-wd-open="reject" {!! $data !!}><i class="fa fa-ban" aria-hidden="true"></i> Reject</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($withdrawals->hasPages())
            <div class="aff-pager">{{ $withdrawals->links() }}</div>
        @endif
    @endif
</div>

{{-- ── Action modals (filled in by the row's button) ───────────────────────── --}}
@php
    $modals = [
        'approve' => ['Approve withdrawal', 'Approve this request, then pay it by bank transfer.', 'approve', 'fa-check', 'Approve'],
        'paid' => ['Mark as paid', 'Record the bank transfer for this request.', 'paid', 'fa-building-columns', 'Mark as paid'],
        'reject' => ['Reject withdrawal', 'The amount goes back to the affiliate\'s available balance.', 'reject', 'fa-ban', 'Reject request'],
    ];
@endphp
@foreach($modals as $key => [$title, $sub, $routeSuffix, $icon, $submit])
    <div class="aff-modal-overlay" id="wd-modal-{{ $key }}" role="dialog" aria-modal="true" aria-labelledby="wd-modal-{{ $key }}-title"
         @if($reopenModal === $key) data-reopen-id="{{ old('_withdrawal') }}" @endif>
        <div class="aff-modal-backdrop" data-aff-close></div>
        <div class="aff-modal-shell">
            <div class="aff-modal-head">
                <div>
                    <h2 class="aff-modal-title" id="wd-modal-{{ $key }}-title">{{ $title }}</h2>
                    <p class="aff-modal-sub">{{ $sub }}</p>
                </div>
                <button class="aff-modal-close" type="button" data-aff-close aria-label="Close"><i class="fa fa-xmark" aria-hidden="true"></i></button>
            </div>
            <form method="POST" action="" data-action-template="{{ url('/admin/affiliate-withdrawals/__ID__/'.$routeSuffix) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="_modal" value="{{ $key }}">
                <input type="hidden" name="_withdrawal" value="" data-wd-id>
                @foreach($returnFields as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach
                <div class="aff-modal-body">
                    <div class="aff-summary">
                        <div><strong data-wd-name></strong> asked for <strong data-wd-amount></strong></div>
                        <div class="aff-muted" data-wd-bank></div>
                    </div>
                    @if($key === 'paid')
                        <div class="aff-field-row">
                            <div class="aff-field">
                                <label for="wd-paid-ref">Transfer reference <span class="aff-req">*</span></label>
                                <input type="text" id="wd-paid-ref" name="payment_reference" maxlength="120" value="{{ $reopenModal === 'paid' ? old('payment_reference') : '' }}" placeholder="e.g. FT2611273344" class="@if($reopenModal === 'paid') @error('payment_reference') is-invalid @enderror @endif">
                                @if($reopenModal === 'paid') @error('payment_reference')<p class="aff-field-err">{{ $message }}</p>@enderror @endif
                            </div>
                            <div class="aff-field">
                                <label for="wd-paid-on">Paid on <span class="aff-req">*</span></label>
                                <input type="date" id="wd-paid-on" name="paid_on" max="{{ now()->toDateString() }}" value="{{ $reopenModal === 'paid' ? old('paid_on') : now()->toDateString() }}" data-wd-paid-on class="@if($reopenModal === 'paid') @error('paid_on') is-invalid @enderror @endif">
                                @if($reopenModal === 'paid') @error('paid_on')<p class="aff-field-err">{{ $message }}</p>@enderror @endif
                            </div>
                        </div>
                    @endif
                    <div class="aff-field">
                        <label for="wd-{{ $key }}-note">
                            @if($key === 'reject') Reason <span class="aff-req">*</span> @else Note <span class="aff-optional">(optional)</span> @endif
                        </label>
                        <textarea id="wd-{{ $key }}-note" name="admin_note" maxlength="500" rows="3"
                                  placeholder="{{ $key === 'reject' ? 'The affiliate sees this reason' : 'Visible to the affiliate' }}"
                                  class="@if($reopenModal === $key) @error('admin_note') is-invalid @enderror @endif">{{ $reopenModal === $key ? old('admin_note') : '' }}</textarea>
                        @if($reopenModal === $key) @error('admin_note')<p class="aff-field-err">{{ $message }}</p>@enderror @endif
                    </div>
                </div>
                <div class="aff-modal-foot">
                    <button type="button" class="aff-cancel-btn" data-aff-close>Cancel</button>
                    <button type="submit" class="aff-submit-btn" @if($key === 'reject') style="background:#dc2626" @endif><i class="fa {{ $icon }}" aria-hidden="true"></i> {{ $submit }}</button>
                </div>
            </form>
        </div>
    </div>
@endforeach

<script>
(function () {
    function fill(modal, btn, keepValues) {
        var form = modal.querySelector('form');
        var id = btn.getAttribute('data-id');
        form.action = form.getAttribute('data-action-template').replace('__ID__', id);
        modal.querySelector('[data-wd-id]').value = id;
        modal.querySelector('[data-wd-name]').textContent = btn.getAttribute('data-name');
        modal.querySelector('[data-wd-amount]').textContent = btn.getAttribute('data-amount');
        modal.querySelector('[data-wd-bank]').textContent = btn.getAttribute('data-bank');
        var paidOn = modal.querySelector('[data-wd-paid-on]');
        if (paidOn) paidOn.min = btn.getAttribute('data-requested');
        if (!keepValues) {
            modal.querySelectorAll('textarea, input[name=payment_reference]').forEach(function (el) { el.value = ''; });
            modal.querySelectorAll('.aff-field-err').forEach(function (el) { el.remove(); });
            modal.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
        }
    }
    function open(modal) {
        modal.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        var first = modal.querySelector('input[type=text], textarea');
        if (first) setTimeout(function () { first.focus(); }, 30);
    }
    function close(modal) { modal.classList.remove('is-open'); document.body.style.overflow = ''; }

    document.querySelectorAll('[data-wd-open]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var modal = document.getElementById('wd-modal-' + btn.getAttribute('data-wd-open'));
            fill(modal, btn, false);
            open(modal);
        });
    });
    document.querySelectorAll('.aff-modal-overlay').forEach(function (modal) {
        modal.querySelectorAll('[data-aff-close]').forEach(function (el) { el.addEventListener('click', function () { close(modal); }); });
        modal.querySelector('form').addEventListener('submit', function () {
            var btn = modal.querySelector('button[type=submit]');
            setTimeout(function () { btn.disabled = true; }, 0);
        });
        // After a validation error, reopen the same modal for the same request.
        var reopenId = modal.getAttribute('data-reopen-id');
        if (reopenId) {
            var key = modal.id.replace('wd-modal-', '');
            var btn = document.querySelector('[data-wd-open="' + key + '"][data-id="' + reopenId + '"]');
            if (btn) { fill(modal, btn, true); open(modal); }
        }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        document.querySelectorAll('.aff-modal-overlay.is-open').forEach(close);
    });
})();
</script>
@endsection
