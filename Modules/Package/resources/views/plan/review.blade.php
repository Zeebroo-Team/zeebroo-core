@extends('theme::layouts.app', [
    'title' => 'Confirm your plan',
    'heading' => 'Confirm your plan',
    'minimalAppShell' => true,
    'hideNavbar' => true,
])

@section('content')
@include('package::plan._styles')
@php
    $pkg = $preview['package'];
    $lost = $preview['lost_features'];
    $seats = $preview['seats'];
    $isFree = $pkg->is_free || $pkg->effectivePrice() <= 0;
@endphp
<div class="pl-wrap" style="max-width:780px">
    <a href="{{ route('plan.choose') }}" class="pl-btn pl-btn--ghost" style="margin-bottom:16px;display:inline-flex"><i class="fa fa-arrow-left"></i> All plans</a>

    <h1 class="pl-title">Switch to {{ $pkg->name }}</h1>
    <p class="pl-sub">
        @if($isFree)
            This plan is free — you'll switch right away.
        @else
            {{ $pkg->currencySymbol() }}{{ number_format($pkg->effectivePrice(), 2) }} / month, paid securely with Stripe. Your plan changes as soon as the payment goes through.
        @endif
    </p>

    @if($errors->any())
        <div class="pl-err"><i class="fa fa-triangle-exclamation"></i> {{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('plan.change', $pkg) }}" id="plForm">
        @csrf

        @if($lost !== [])
            <div class="pl-box pl-warn">
                <h3><i class="fa fa-triangle-exclamation" style="color:#d97706"></i> {{ $pkg->name }} doesn't include some features you used</h3>
                <p>After switching, the data below will be <strong>hidden and you won't be able to access it</strong> on the {{ $pkg->name }} plan.
                   It isn't deleted — if you move to a plan that includes these features later, it comes back.</p>
                @foreach($lost as $f)
                    <div class="pl-loss">
                        <i class="fa fa-lock"></i>
                        <div>
                            <strong>{{ $f['label'] }}</strong><br>
                            <span style="color:var(--muted)">{{ collect($f['items'])->map(fn ($i) => number_format($i['count']).' '.$i['label'])->implode(' · ') }}</span>
                        </div>
                    </div>
                @endforeach
            </div>

            @if($preview['recommended']->isNotEmpty())
                <div class="pl-box">
                    <h3><i class="fa fa-thumbs-up" style="color:#16a34a"></i> Recommended for you</h3>
                    <p>These plans keep everything you've been using — lowest price first:</p>
                    <div class="pl-grid">
                        @foreach($preview['recommended'] as $rec)
                            @php $diff = $rec->effectivePrice() - $pkg->effectivePrice(); @endphp
                            <div class="pl-card pl-card--rec">
                                <h3 class="pl-card-name">{{ $rec->name }}</h3>
                                <div class="pl-price">
                                    @if($rec->is_free || $rec->effectivePrice() <= 0) Free
                                    @else {{ $rec->currencySymbol() }}{{ number_format($rec->effectivePrice(), 2) }}<small>/mo</small>
                                    @endif
                                </div>
                                <div style="font-size:12.5px;color:#15803d;font-weight:600">
                                    @if($loop->first)<span class="pl-chip" style="background:#dcfce7;color:#15803d"><i class="fa fa-tag"></i> Best value</span>@endif
                                    @if($diff > 0) only {{ $rec->currencySymbol() }}{{ number_format($diff, 2) }}/mo more than {{ $pkg->name }}
                                    @elseif($diff < 0) {{ $rec->currencySymbol() }}{{ number_format(abs($diff), 2) }}/mo less than {{ $pkg->name }}
                                    @else same price as {{ $pkg->name }}
                                    @endif
                                </div>
                                <div class="pl-chips"><span class="pl-chip"><i class="fa fa-users"></i> {{ $rec->userLimitLabel() }}</span></div>
                                <a class="pl-btn" href="{{ route('plan.review', $rec) }}">Choose {{ $rec->name }}</a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @endif

        @if($seats['needs_selection'])
            <div class="pl-box pl-warn">
                <h3><i class="fa fa-users"></i> {{ $pkg->name }} allows {{ $seats['limit'] }} {{ \Illuminate\Support\Str::plural('user', $seats['limit']) }} — you have {{ $seats['used'] }}</h3>
                <p>You keep your own account. Choose up to <strong>{{ $seats['must_keep'] }}</strong> team {{ \Illuminate\Support\Str::plural('member', $seats['must_keep']) }} to keep.
                   The others will be deactivated (not deleted) and pending invitations will be cancelled.</p>
                @foreach($seats['members'] as $m)
                    <label class="pl-member">
                        <input type="checkbox" name="keep_members[]" value="{{ $m['id'] }}" class="pl-keep"
                               {{ in_array($m['id'], old('keep_members', []), false) ? 'checked' : '' }}>
                        <span><strong>{{ $m['name'] }}</strong> <small>{{ $m['email'] }} · {{ $m['role'] }}</small></span>
                    </label>
                @endforeach
                <p id="plKeepHint" style="margin-top:8px"></p>
            </div>
        @endif

        @if($lost !== [])
            <div class="pl-box">
                <h3>Confirm</h3>
                <p>I understand that my {{ collect($lost)->pluck('label')->implode(', ') }} data will be hidden on the {{ $pkg->name }} plan. Type <strong>CONFIRM</strong> to continue.</p>
                <input class="pl-input" type="text" name="confirm_text" id="plConfirm" autocomplete="off" placeholder="CONFIRM" value="{{ old('confirm_text') }}">
            </div>
        @endif

        <div class="pl-actions">
            <button type="submit" class="pl-btn {{ $lost !== [] ? 'pl-btn--danger' : '' }}" id="plSubmit">
                @if($isFree) <i class="fa fa-check"></i> Switch to {{ $pkg->name }}
                @else <i class="fa fa-lock"></i> Continue to payment
                @endif
            </button>
            <a href="{{ route('plan.choose') }}" class="pl-btn pl-btn--ghost">Cancel</a>
        </div>
    </form>
</div>

<script>
(function () {
    var submit = document.getElementById('plSubmit');
    var confirmEl = document.getElementById('plConfirm');
    var keeps = document.querySelectorAll('.pl-keep');
    var hint = document.getElementById('plKeepHint');
    var maxKeep = {{ (int) ($seats['must_keep'] ?? 0) }};
    var needsSeats = {{ $seats['needs_selection'] ? 'true' : 'false' }};

    function refresh() {
        var ok = true;
        if (confirmEl) ok = ok && confirmEl.value.trim().toUpperCase() === 'CONFIRM';
        if (needsSeats) {
            var picked = Array.prototype.filter.call(keeps, function (k) { return k.checked; }).length;
            Array.prototype.forEach.call(keeps, function (k) { k.disabled = !k.checked && picked >= maxKeep; });
            if (hint) hint.textContent = picked + ' of ' + maxKeep + ' selected';
            ok = ok && picked <= maxKeep;
        }
        submit.disabled = !ok;
    }

    if (confirmEl) confirmEl.addEventListener('input', refresh);
    Array.prototype.forEach.call(keeps, function (k) { k.addEventListener('change', refresh); });
    refresh();
})();
</script>
@endsection
