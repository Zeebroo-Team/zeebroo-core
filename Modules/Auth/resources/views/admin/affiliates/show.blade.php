@extends('theme::layouts.app', ['title' => $affiliate->name.' - Affiliate', 'heading' => 'Affiliates'])

@section('content')
@include('auth::affiliate.partials.styles')

<div class="aff-wrap">
    <a href="{{ route('admin.affiliates.index') }}" class="aff-back"><i class="fa fa-arrow-left" aria-hidden="true"></i> All affiliates</a>

    <div class="aff-header">
        <div>
            <h1 class="aff-title">
                <i class="fa fa-handshake" aria-hidden="true"></i>{{ $affiliate->name }}
                @if($affiliate->is_active)
                    <span class="aff-status aff-status--on">Login active</span>
                @else
                    <span class="aff-status aff-status--off">Login disabled</span>
                @endif
            </h1>
            <p class="aff-sub">{{ $affiliate->email }} · Affiliate since {{ $affiliate->created_at?->format('d M Y') }}</p>
        </div>
        <div class="aff-header-actions">
            <a href="{{ route('admin.affiliates.payments', $affiliate) }}" class="aff-btn aff-btn--tall">
                <i class="fa fa-sack-dollar" aria-hidden="true"></i> Commission: <strong>{{ $commissionTotal }}</strong>
            </a>
            <a href="{{ route('admin.affiliate-withdrawals.index', ['affiliate' => $affiliate->id, 'status' => 'all']) }}" class="aff-btn aff-btn--tall">
                <i class="fa fa-money-bill-transfer" aria-hidden="true"></i> Withdrawals
            </a>
            <a href="{{ route('admin.users.show', $affiliate) }}" class="aff-btn aff-btn--tall">
                <i class="fa fa-user-gear" aria-hidden="true"></i> Manage login
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="aff-msg"><i class="fa fa-circle-check" aria-hidden="true"></i> {{ session('success') }}</div>
    @endif
    @if($errors->any() && ! $errors->has('code') && ! $errors->has('label'))
        <div class="aff-msg aff-msg-err"><i class="fa fa-triangle-exclamation" aria-hidden="true"></i> {{ $errors->first() }}</div>
    @endif

    @include('auth::affiliate.partials.stats', ['stats' => $stats])

    {{-- Details the affiliate entered on their Settings page (needed to verify and pay them) --}}
    <div class="aff-section">
        <div class="aff-section-head">
            <h2 class="aff-section-title"><i class="fa fa-id-badge" aria-hidden="true"></i>Contact, payout and ID details</h2>
        </div>
        <div class="aff-details">
            <div class="aff-detail-card">
                <h3><i class="fa fa-address-book" aria-hidden="true"></i>Contact &amp; address
                    <span class="aff-status {{ $completion['contact'] ? 'aff-status--on' : 'aff-status--warn' }}">{{ $completion['contact'] ? 'Complete' : 'Missing' }}</span>
                </h3>
                <dl class="aff-dl">
                    <dt>Mobile</dt><dd>{{ $profile->phone ?: '-' }}</dd>
                    <dt>Other phone</dt><dd>{{ $profile->phone_alt ?: '-' }}</dd>
                    <dt>Address</dt><dd>{{ $profile->addressLine() ?: '-' }}</dd>
                </dl>
            </div>
            <div class="aff-detail-card">
                <h3><i class="fa fa-building-columns" aria-hidden="true"></i>Payout bank
                    <span class="aff-status {{ $completion['payout'] ? 'aff-status--on' : 'aff-status--warn' }}">{{ $completion['payout'] ? 'Complete' : 'Missing' }}</span>
                </h3>
                <dl class="aff-dl">
                    <dt>Bank</dt><dd>{{ $profile->bank_name ?: '-' }}</dd>
                    <dt>Branch</dt><dd>{{ $profile->bank_branch ?: '-' }}</dd>
                    <dt>Account name</dt><dd>{{ $profile->account_holder_name ?: '-' }}</dd>
                    <dt>Account no.</dt><dd><span class="aff-mono">{{ $profile->account_number ?: '-' }}</span></dd>
                </dl>
            </div>
            <div class="aff-detail-card">
                <h3><i class="fa fa-id-card" aria-hidden="true"></i>ID verification
                    <span class="aff-status {{ $completion['identity'] ? 'aff-status--on' : 'aff-status--warn' }}">{{ $completion['identity'] ? 'Complete' : 'Missing' }}</span>
                </h3>
                <dl class="aff-dl">
                    <dt>ID type</dt><dd>{{ \Modules\Auth\Models\AffiliateProfile::ID_TYPES[$profile->id_type] ?? '-' }}</dd>
                    <dt>ID number</dt><dd><span class="aff-mono">{{ $profile->id_number ?: '-' }}</span></dd>
                    <dt>Copies</dt>
                    <dd>
                        @forelse(collect(\Modules\Auth\Models\AffiliateDocument::types())->filter(fn ($t) => $documents->has($t)) as $type)
                            @php $doc = $documents[$type]; @endphp
                            <a href="{{ route('admin.affiliates.documents.show', [$affiliate, $type]) }}" target="_blank" rel="noopener" class="aff-btn" style="margin:0 4px 4px 0">
                                <i class="fa {{ $doc->isImage() ? 'fa-image' : 'fa-file-pdf' }}" aria-hidden="true"></i> {{ \Modules\Auth\Models\AffiliateDocument::labelFor($type) }}
                            </a>
                        @empty
                            -
                        @endforelse
                    </dd>
                </dl>
            </div>
        </div>
    </div>

    @include('auth::affiliate.partials.codes-section', [
        'codes' => $codes,
        'storeAction' => route('admin.affiliates.codes.store', $affiliate),
        'toggleRoute' => 'admin.affiliates.codes.toggle',
        'maxCodes' => $maxCodes,
        'sectionTitle' => 'Referral codes',
        'emptySub' => 'This affiliate has no codes. Generate one for them, or let them create their own from their dashboard.',
    ])

    @include('auth::affiliate.partials.referrals', [
        'referrals' => $referrals,
        'search' => $search,
        'searchAction' => route('admin.affiliates.show', $affiliate),
        'sectionTitle' => 'Referred accounts',
        'emptySub' => 'Nobody has signed up with this affiliate\'s codes yet.',
    ])
</div>
@endsection
