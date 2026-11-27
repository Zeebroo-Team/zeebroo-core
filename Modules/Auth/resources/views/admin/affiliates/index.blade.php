@extends('theme::layouts.app', ['title' => 'Affiliates', 'heading' => 'Affiliates'])

@section('content')
@include('auth::affiliate.partials.styles')

@php
    $hasAffiliates = $affiliates->isNotEmpty() || $search !== '';
@endphp

<div class="aff-wrap">
    <div class="aff-header">
        <div>
            <h1 class="aff-title"><i class="fa fa-handshake" aria-hidden="true"></i>Affiliates</h1>
            <p class="aff-sub">Create logins for affiliate sales marketers. Each affiliate generates referral codes, and every account that signs up with one of their codes is credited to them.</p>
        </div>
        @if($hasAffiliates)
            <div class="aff-header-actions">
                <button type="button" class="aff-add-btn" id="aff-open-create">
                    <i class="fa fa-user-plus" aria-hidden="true"></i> New affiliate
                </button>
            </div>
        @endif
    </div>

    @if(session('success'))
        <div class="aff-msg"><i class="fa fa-circle-check" aria-hidden="true"></i> {{ session('success') }}</div>
    @endif

    @if(! $hasAffiliates)
        <div class="aff-form-card">
            <h3 class="aff-form-card-title">Create the first affiliate login</h3>
            <p class="aff-form-card-sub">The affiliate signs in on the normal login page and lands on their own dashboard, with their referral codes and the accounts they brought in.</p>
            @if($errors->any())
                <div class="aff-msg aff-msg-err"><i class="fa fa-triangle-exclamation" aria-hidden="true"></i> {{ $errors->first() }}</div>
            @endif
            <form method="POST" action="{{ route('admin.affiliates.store') }}">
                @csrf
                @include('auth::admin.affiliates.partials.create-fields', ['idPrefix' => 'aff-inline'])
                <div class="aff-form-card-foot">
                    <button type="submit" class="aff-submit-btn"><i class="fa fa-user-plus" aria-hidden="true"></i> Create affiliate</button>
                </div>
            </form>
        </div>
    @else
        <div class="aff-section">
            <div class="aff-section-head">
                <h2 class="aff-section-title">
                    <i class="fa fa-users" aria-hidden="true"></i>All affiliates
                    <span class="aff-section-meta">{{ $affiliates->count() }}</span>
                </h2>
                <form method="GET" action="{{ route('admin.affiliates.index') }}" class="aff-search" role="search">
                    <i class="fa fa-magnifying-glass" aria-hidden="true"></i>
                    <input type="search" name="search" value="{{ $search }}" placeholder="Search name or email" aria-label="Search affiliates">
                </form>
            </div>

            @if($affiliates->isEmpty())
                <div class="aff-empty">
                    <div class="aff-empty-icon"><i class="fa fa-magnifying-glass" aria-hidden="true"></i></div>
                    <h3 class="aff-empty-title">No matching affiliates</h3>
                    <p class="aff-empty-sub">No affiliate matches "{{ $search }}". <a href="{{ route('admin.affiliates.index') }}">Clear the search</a></p>
                </div>
            @else
                <div class="aff-card">
                    <table class="aff-table">
                        <thead>
                            <tr>
                                <th>Affiliate</th>
                                <th>Login</th>
                                <th>Codes</th>
                                <th>Sign-ups</th>
                                <th>This month</th>
                                <th>Commission</th>
                                <th>Created</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($affiliates as $affiliate)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.affiliates.show', $affiliate) }}" class="aff-user aff-link-row">
                                            <span class="aff-avatar" aria-hidden="true">{{ strtoupper(mb_substr($affiliate->name, 0, 1)) }}</span>
                                            <div style="min-width:0">
                                                <div class="aff-user-name">{{ $affiliate->name }}</div>
                                                <div class="aff-user-email">{{ $affiliate->email }}</div>
                                            </div>
                                        </a>
                                    </td>
                                    <td data-label="Login">
                                        @if($affiliate->is_active)
                                            <span class="aff-status aff-status--on">Active</span>
                                        @else
                                            <span class="aff-status aff-status--off">Disabled</span>
                                        @endif
                                    </td>
                                    <td data-label="Codes"><span class="aff-num">{{ number_format($affiliate->affiliate_codes_count) }}</span></td>
                                    <td data-label="Sign-ups"><span class="aff-num">{{ number_format($affiliate->affiliate_referrals_count) }}</span></td>
                                    <td data-label="This month"><span class="aff-num">{{ number_format($affiliate->referrals_this_month_count) }}</span></td>
                                    <td data-label="Commission" style="white-space:nowrap">
                                        @php $commissionTotal = $commissionTotals[$affiliate->id]; @endphp
                                        <a href="{{ route('admin.affiliates.payments', $affiliate) }}" class="aff-num {{ $commissionTotal === 'LKR 0.00' ? 'aff-muted' : 'aff-commission' }}" style="text-decoration:none">{{ $commissionTotal }}</a>
                                    </td>
                                    <td data-label="Created" style="white-space:nowrap"><span class="aff-muted">{{ $affiliate->created_at?->format('d M Y') }}</span></td>
                                    <td style="text-align:right">
                                        <a href="{{ route('admin.affiliates.show', $affiliate) }}" class="aff-btn"><i class="fa fa-chart-line" aria-hidden="true"></i> View referrals</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</div>

@if($hasAffiliates)
    <div class="aff-modal-overlay @if($errors->any()) is-open @endif" id="aff-create-modal" role="dialog" aria-modal="true" aria-labelledby="aff-create-title">
        <div class="aff-modal-backdrop" data-aff-close></div>
        <div class="aff-modal-shell">
            <div class="aff-modal-head">
                <div>
                    <h2 class="aff-modal-title" id="aff-create-title">New affiliate</h2>
                    <p class="aff-modal-sub">Creates a login with the affiliate role and their first referral code.</p>
                </div>
                <button class="aff-modal-close" type="button" data-aff-close aria-label="Close"><i class="fa fa-xmark" aria-hidden="true"></i></button>
            </div>
            <form method="POST" action="{{ route('admin.affiliates.store') }}">
                @csrf
                <div class="aff-modal-body">
                    @include('auth::admin.affiliates.partials.create-fields', ['idPrefix' => 'aff-modal'])
                </div>
                <div class="aff-modal-foot">
                    <button type="button" class="aff-cancel-btn" data-aff-close>Cancel</button>
                    <button type="submit" class="aff-submit-btn"><i class="fa fa-user-plus" aria-hidden="true"></i> Create affiliate</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    (function () {
        var modal = document.getElementById('aff-create-modal');
        var openBtn = document.getElementById('aff-open-create');
        if (!modal || !openBtn) return;
        function openModal() {
            modal.classList.add('is-open');
            document.body.style.overflow = 'hidden';
            var first = modal.querySelector('input[name="name"]');
            if (first) setTimeout(function () { first.focus(); }, 30);
        }
        function closeModal() { modal.classList.remove('is-open'); document.body.style.overflow = ''; }
        openBtn.addEventListener('click', openModal);
        modal.querySelectorAll('[data-aff-close]').forEach(function (el) { el.addEventListener('click', closeModal); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
        });
        if (modal.classList.contains('is-open')) document.body.style.overflow = 'hidden';
    })();
    </script>
@endif
@endsection
