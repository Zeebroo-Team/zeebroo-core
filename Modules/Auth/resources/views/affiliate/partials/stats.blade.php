{{-- Headline numbers. Expects $stats from AffiliateService::stats(). --}}
<div class="aff-stats">
    <div class="aff-stat">
        <div class="aff-stat-icon" style="background:color-mix(in srgb,#6366f1 13%,transparent);color:#6366f1"><i class="fa fa-user-plus" aria-hidden="true"></i></div>
        <div class="aff-stat-label">Total sign-ups</div>
        <div class="aff-stat-value">{{ number_format($stats['total']) }}</div>
        <div class="aff-stat-sub">Accounts created with a code</div>
    </div>
    <div class="aff-stat">
        <div class="aff-stat-icon" style="background:color-mix(in srgb,#0ea5e9 13%,transparent);color:#0ea5e9"><i class="fa fa-calendar-check" aria-hidden="true"></i></div>
        <div class="aff-stat-label">This month</div>
        <div class="aff-stat-value">{{ number_format($stats['this_month']) }}</div>
        <div class="aff-stat-sub">Since {{ now()->startOfMonth()->format('d M') }}</div>
    </div>
    <div class="aff-stat">
        <div class="aff-stat-icon" style="background:color-mix(in srgb,#f59e0b 14%,transparent);color:#d97706"><i class="fa fa-store" aria-hidden="true"></i></div>
        <div class="aff-stat-label">Business set up</div>
        <div class="aff-stat-value">{{ number_format($stats['with_business']) }}</div>
        <div class="aff-stat-sub">Finished the business setup</div>
    </div>
    <div class="aff-stat">
        <div class="aff-stat-icon" style="background:color-mix(in srgb,#16a34a 13%,transparent);color:#16a34a"><i class="fa fa-credit-card" aria-hidden="true"></i></div>
        <div class="aff-stat-label">Paid a plan</div>
        <div class="aff-stat-value">{{ number_format($stats['paid']) }}</div>
        <div class="aff-stat-sub">Made at least one payment</div>
    </div>
</div>
