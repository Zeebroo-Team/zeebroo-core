{{--
    Payments made by referred accounts and the commission each one earns.
    Expects (from AffiliateCommissionService::pageData): $summary, $entries (paginator),
    $filteredTotal, $months, $month, $search, $commissions; plus $filterAction (URL).
--}}
@php
    $first = \Modules\Auth\Services\AffiliateCommissionService::FIRST_PAYMENTS;
    $rate = (int) (\Modules\Auth\Services\AffiliateCommissionService::FIRST_PAYMENTS_RATE * 100);
    $later = \Modules\Auth\Services\AffiliateCommissionService::LATER_PAYMENT_CURRENCY.' '.number_format(\Modules\Auth\Services\AffiliateCommissionService::LATER_PAYMENT_COMMISSION);
    $hasFilters = $month !== null || $search !== '';
@endphp

<div class="aff-stats">
    <div class="aff-stat">
        <div class="aff-stat-icon" style="background:color-mix(in srgb,#16a34a 13%,transparent);color:#16a34a"><i class="fa fa-sack-dollar" aria-hidden="true"></i></div>
        <div class="aff-stat-label">Total commission</div>
        <div class="aff-stat-value aff-stat-value--money">{{ $commissions->formatTotals($summary['total']) }}</div>
        <div class="aff-stat-sub">All time</div>
    </div>
    <div class="aff-stat">
        <div class="aff-stat-icon" style="background:color-mix(in srgb,#0ea5e9 13%,transparent);color:#0ea5e9"><i class="fa fa-calendar-check" aria-hidden="true"></i></div>
        <div class="aff-stat-label">This month</div>
        <div class="aff-stat-value aff-stat-value--money">{{ $commissions->formatTotals($summary['this_month']) }}</div>
        <div class="aff-stat-sub">{{ now()->format('F Y') }}</div>
    </div>
    <div class="aff-stat">
        <div class="aff-stat-icon" style="background:color-mix(in srgb,#6366f1 13%,transparent);color:#6366f1"><i class="fa fa-receipt" aria-hidden="true"></i></div>
        <div class="aff-stat-label">Commission payments</div>
        <div class="aff-stat-value">{{ number_format($summary['payments']) }}</div>
        <div class="aff-stat-sub">Plan payments that earned commission</div>
    </div>
    <div class="aff-stat">
        <div class="aff-stat-icon" style="background:color-mix(in srgb,#f59e0b 14%,transparent);color:#d97706"><i class="fa fa-user-check" aria-hidden="true"></i></div>
        <div class="aff-stat-label">Paying accounts</div>
        <div class="aff-stat-value">{{ number_format($summary['paying_accounts']) }}</div>
        <div class="aff-stat-sub">Referred accounts that paid</div>
    </div>
</div>

<div class="aff-note">
    <i class="fa fa-circle-info" aria-hidden="true"></i>
    <div>
        <strong>How commission works.</strong>
        For each referred account, the first {{ $first }} plan payments earn {{ $rate }}% of the amount paid.
        Every plan payment after that earns {{ $later }}.
        Only successful payments count. Failed, refunded, free and trial payments are left out.
    </div>
</div>

<div class="aff-section">
    <div class="aff-section-head">
        <h2 class="aff-section-title">
            <i class="fa fa-money-bill-wave" aria-hidden="true"></i>Payments
            @if($entries->total() > 0)
                <span class="aff-section-meta">{{ number_format($entries->total()) }} {{ Str::plural('payment', $entries->total()) }}</span>
            @endif
        </h2>
        @if($summary['payments'] > 0)
            <form method="GET" action="{{ $filterAction }}" class="aff-filters" role="search">
                <select name="month" aria-label="Month" onchange="this.form.submit()">
                    <option value="">All months</option>
                    @foreach($months as $value => $label)
                        <option value="{{ $value }}" @selected($month === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <div class="aff-search">
                    <i class="fa fa-magnifying-glass" aria-hidden="true"></i>
                    <input type="search" name="search" value="{{ $search }}" placeholder="Search name or email" aria-label="Search payments by account">
                </div>
                @if($hasFilters)
                    <a href="{{ $filterAction }}" class="aff-btn aff-btn--tall"><i class="fa fa-xmark" aria-hidden="true"></i> Clear</a>
                @endif
            </form>
        @endif
    </div>

    @if($entries->isEmpty())
        <div class="aff-empty">
            <div class="aff-empty-icon"><i class="fa fa-{{ $hasFilters ? 'magnifying-glass' : 'money-bill-wave' }}" aria-hidden="true"></i></div>
            @if($hasFilters)
                <h3 class="aff-empty-title">No matching payments</h3>
                <p class="aff-empty-sub">No payment matches these filters. <a href="{{ $filterAction }}">Show all payments</a></p>
            @else
                <h3 class="aff-empty-title">No payments yet</h3>
                <p class="aff-empty-sub">When an account signed up with your code pays for a plan, the payment and its commission show here.</p>
            @endif
        </div>
    @else
        <div class="aff-card">
            <table class="aff-table">
                <thead>
                    <tr>
                        <th>Paid on</th>
                        <th>Account</th>
                        <th>Business</th>
                        <th>Payment</th>
                        <th style="text-align:right">Amount paid</th>
                        <th>Commission rule</th>
                        <th style="text-align:right">Commission</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($entries as $entry)
                        @php
                            $payment = $entry['payment'];
                            $referred = $entry['referred'];
                            $isFirst = $entry['rule'] === \Modules\Auth\Services\AffiliateCommissionService::RULE_FIRST;
                        @endphp
                        <tr>
                            <td data-label="Paid on" style="white-space:nowrap">
                                <div>{{ $entry['paid_at']->format('d M Y') }}</div>
                                <div class="aff-muted">{{ $entry['paid_at']->format('h:i A') }}</div>
                            </td>
                            <td>
                                <div class="aff-user">
                                    <span class="aff-avatar" aria-hidden="true">{{ strtoupper(mb_substr($referred?->name ?? '?', 0, 1)) }}</span>
                                    <div style="min-width:0">
                                        <div class="aff-user-name">{{ $referred?->name ?? 'Deleted account' }}</div>
                                        <div class="aff-user-email">{{ $referred?->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Business">{{ $payment->business?->name ?? '-' }}</td>
                            <td data-label="Payment">
                                <div><strong>{{ $commissions->ordinal($entry['number']) }} payment</strong></div>
                                <div class="aff-muted">
                                    {{ $payment->package?->name ?? 'Plan' }}@if($payment->billing_cycle) · {{ ucfirst($payment->billing_cycle) }}@endif
                                </div>
                            </td>
                            <td data-label="Amount paid" style="text-align:right;white-space:nowrap">
                                <span class="aff-num">{{ $entry['currency'] }} {{ number_format($entry['amount'], 2) }}</span>
                            </td>
                            <td data-label="Commission rule">
                                <span class="aff-status {{ $isFirst ? 'aff-status--on' : 'aff-status--info' }}">{{ $commissions->ruleLabel($entry['rule']) }}</span>
                            </td>
                            <td data-label="Commission" style="text-align:right;white-space:nowrap">
                                <span class="aff-num aff-commission">{{ $entry['commission_currency'] }} {{ number_format($entry['commission'], 2) }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="6" class="aff-tfoot-label">
                            {{ $hasFilters ? 'Commission for these filters' : 'Total commission' }}
                            @if($entries->hasPages()) <span class="aff-muted">(all pages)</span> @endif
                        </td>
                        <td data-label="Total" style="text-align:right;white-space:nowrap"><span class="aff-num aff-commission">{{ $filteredTotal }}</span></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @if($entries->hasPages())
            <div class="aff-pager">{{ $entries->links() }}</div>
        @endif
    @endif
</div>
