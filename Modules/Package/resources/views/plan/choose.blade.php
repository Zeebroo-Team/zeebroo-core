@extends('theme::layouts.app', [
    'title' => 'Choose your plan',
    'heading' => 'Choose your plan',
    'minimalAppShell' => true,
    'hideNavbar' => true,
])

@section('content')
@include('package::plan._styles')
<div class="pl-wrap">
    @if($expired)
        <span class="pl-eyebrow pl-eyebrow--bad"><i class="fa fa-hourglass-end"></i> Free trial ended</span>
        <h1 class="pl-title">Your free trial has ended</h1>
        <p class="pl-sub">Choose a plan to keep using {{ $business->name }}. Your data is safe — pick a plan and you'll continue where you left off.</p>
    @else
        @php $daysLeft = $business->trialDaysLeft(); @endphp
        <span class="pl-eyebrow"><i class="fa fa-gift"></i> Free trial · {{ $daysLeft === 0 ? 'last day' : $daysLeft.' '.\Illuminate\Support\Str::plural('day', $daysLeft).' left' }}</span>
        <h1 class="pl-title">Choose your plan</h1>
        <p class="pl-sub">Your trial ends on {{ $business->trial_ends_at->format('d M Y, h:i A') }}. Upgrade any time — you keep everything included in the plan you choose.</p>
    @endif

    @if($errors->any())
        <div class="pl-err"><i class="fa fa-triangle-exclamation"></i> {{ $errors->first() }}</div>
    @endif

    @if(! $isOwner)
        <div class="pl-box pl-warn">
            <h3><i class="fa fa-user-lock"></i> Only the business owner can choose a plan</h3>
            <p>Ask the owner of {{ $business->name }} to sign in and pick a plan.</p>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="pl-btn pl-btn--ghost" type="submit">Sign out</button></form>
        </div>
    @else
        <div class="pl-grid">
            @forelse($packages as $pkg)
                <div class="pl-card">
                    <h3 class="pl-card-name">{{ $pkg->name }}</h3>
                    <div class="pl-price">
                        @if($pkg->is_free || $pkg->effectivePrice() <= 0)
                            Free
                        @else
                            {{ $pkg->currencySymbol() }}{{ number_format($pkg->effectivePrice(), 2) }}<small>/mo</small>
                            @if($pkg->discounted_price !== null && (float) $pkg->discounted_price < (float) $pkg->price)
                                <span class="pl-strike">{{ $pkg->currencySymbol() }}{{ number_format((float) $pkg->price, 2) }}</span>
                            @endif
                        @endif
                    </div>
                    @if($pkg->description)<p class="pl-sub" style="margin:0;font-size:12.5px">{{ $pkg->description }}</p>@endif
                    <div class="pl-chips"><span class="pl-chip"><i class="fa fa-users"></i> {{ $pkg->userLimitLabel() }}</span></div>
                    <div class="pl-chips">
                        @foreach($pkg->featureLabels() as $label)<span class="pl-chip">{{ $label }}</span>@endforeach
                    </div>
                    <a class="pl-btn" href="{{ route('plan.review', $pkg) }}">Choose {{ $pkg->name }} <i class="fa fa-arrow-right"></i></a>
                </div>
            @empty
                <p class="pl-sub">No plans are available right now — please contact support.</p>
            @endforelse
        </div>
    @endif
</div>
@endsection
