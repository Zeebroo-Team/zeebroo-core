{{--
    Accounts that signed up with the affiliate's codes.
    Expects: $referrals (paginator), $search, $searchAction (URL), $sectionTitle, $emptySub
--}}
<div class="aff-section">
    <div class="aff-section-head">
        <h2 class="aff-section-title">
            <i class="fa fa-user-check" aria-hidden="true"></i>{{ $sectionTitle }}
            @if($referrals->total() > 0)
                <span class="aff-section-meta">{{ number_format($referrals->total()) }} {{ Str::plural('account', $referrals->total()) }}</span>
            @endif
        </h2>
        @if($referrals->total() > 0 || $search !== '')
            <form method="GET" action="{{ $searchAction }}" class="aff-search" role="search">
                <i class="fa fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" name="search" value="{{ $search }}" placeholder="Search name or email" aria-label="Search referred accounts">
            </form>
        @endif
    </div>

    @if($referrals->isEmpty())
        <div class="aff-empty">
            <div class="aff-empty-icon"><i class="fa fa-{{ $search !== '' ? 'magnifying-glass' : 'share-nodes' }}" aria-hidden="true"></i></div>
            @if($search !== '')
                <h3 class="aff-empty-title">No matching accounts</h3>
                <p class="aff-empty-sub">No referred account matches "{{ $search }}". <a href="{{ $searchAction }}">Clear the search</a></p>
            @else
                <h3 class="aff-empty-title">No sign-ups yet</h3>
                <p class="aff-empty-sub">{{ $emptySub }}</p>
            @endif
        </div>
    @else
        <div class="aff-card">
            <table class="aff-table">
                <thead>
                    <tr>
                        <th>Account</th>
                        <th>Signed up</th>
                        <th>Code</th>
                        <th>Signed up from</th>
                        <th>Business</th>
                        <th>Plan</th>
                        <th>Payment</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($referrals as $referral)
                        @php
                            $referred = $referral->referredUser;
                            $business = $referred?->businesses->sortBy('created_at')->first();
                        @endphp
                        <tr>
                            <td>
                                <div class="aff-user">
                                    <span class="aff-avatar" aria-hidden="true">{{ strtoupper(mb_substr($referred?->name ?? '?', 0, 1)) }}</span>
                                    <div style="min-width:0">
                                        <div class="aff-user-name">{{ $referred?->name ?? 'Deleted account' }}</div>
                                        <div class="aff-user-email">{{ $referred?->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Signed up" style="white-space:nowrap">
                                <div>{{ $referral->created_at->format('d M Y') }}</div>
                                <div class="aff-muted">{{ $referral->created_at->diffForHumans() }}</div>
                            </td>
                            <td data-label="Code"><span class="aff-mono">{{ $referral->code }}</span></td>
                            <td data-label="Signed up from"><span class="aff-muted">{{ $referral->platformLabel() }}</span></td>
                            <td data-label="Business">
                                @if($business)
                                    {{ $business->name }}
                                    @if($referred->businesses->count() > 1)
                                        <span class="aff-muted">+{{ $referred->businesses->count() - 1 }} more</span>
                                    @endif
                                @else
                                    <span class="aff-muted">Not set up yet</span>
                                @endif
                            </td>
                            <td data-label="Plan">
                                @if($business?->package)
                                    {{ $business->package->name }}
                                @else
                                    <span class="aff-muted">-</span>
                                @endif
                            </td>
                            <td data-label="Payment">
                                @if($referral->has_paid)
                                    <span class="aff-status aff-status--on"><i class="fa fa-circle-check" aria-hidden="true"></i> Paid</span>
                                @else
                                    <span class="aff-status aff-status--off">Not paid yet</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($referrals->hasPages())
            <div class="aff-pager">{{ $referrals->links() }}</div>
        @endif
    @endif
</div>
