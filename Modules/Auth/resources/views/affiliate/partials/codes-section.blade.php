{{--
    Referral codes: inline "first code" form when there are none, otherwise the
    code cards plus a "New code" button that opens the same fields in a modal.

    Expects: $codes, $storeAction (form URL), $toggleRoute (route name taking the code),
             $maxCodes, $sectionTitle, $emptySub
--}}
@php
    $codeFormErrors = $errors->has('code') || $errors->has('label');
    $canAddCode = $codes->count() < $maxCodes;
@endphp
<div class="aff-section">
    <div class="aff-section-head">
        <h2 class="aff-section-title">
            <i class="fa fa-ticket" aria-hidden="true"></i>{{ $sectionTitle }}
            @if($codes->isNotEmpty())
                <span class="aff-section-meta">{{ $codes->count() }} of {{ $maxCodes }}</span>
            @endif
        </h2>
        @if($codes->isNotEmpty() && $canAddCode)
            <button type="button" class="aff-add-btn" id="aff-open-code-modal">
                <i class="fa fa-plus" aria-hidden="true"></i> New code
            </button>
        @endif
    </div>

    @if($codes->isEmpty())
        <div class="aff-form-card">
            <h3 class="aff-form-card-title">Create the first referral code</h3>
            <p class="aff-form-card-sub">{{ $emptySub }}</p>
            <form method="POST" action="{{ $storeAction }}">
                @csrf
                @include('auth::affiliate.partials.code-fields', ['idPrefix' => 'aff-inline'])
                <div class="aff-form-card-foot">
                    <button type="submit" class="aff-submit-btn"><i class="fa fa-wand-magic-sparkles" aria-hidden="true"></i> Generate code</button>
                </div>
            </form>
        </div>
    @else
        <div class="aff-codes">
            @foreach($codes as $code)
                <div class="aff-code {{ $code->is_active ? '' : 'aff-code--off' }}">
                    <div class="aff-code-top">
                        <span class="aff-code-value">{{ $code->code }}</span>
                        <span class="aff-status {{ $code->is_active ? 'aff-status--on' : 'aff-status--off' }}">
                            {{ $code->is_active ? 'Active' : 'Disabled' }}
                        </span>
                    </div>
                    @if($code->label)
                        <div class="aff-code-label"><i class="fa fa-tag" aria-hidden="true"></i>{{ $code->label }}</div>
                    @endif
                    <div class="aff-code-link">
                        <input type="text" readonly value="{{ $code->signupUrl() }}" aria-label="Sign-up link for {{ $code->code }}" onfocus="this.select()">
                        <button type="button" class="aff-btn" data-aff-copy="{{ $code->signupUrl() }}" data-aff-copy-label="Copy link">
                            <i class="fa fa-link" aria-hidden="true"></i> <span>Copy link</span>
                        </button>
                    </div>
                    <div class="aff-code-foot">
                        <span class="aff-code-count">
                            <i class="fa fa-user-check" aria-hidden="true"></i>
                            {{ number_format($code->referrals_count) }} {{ Str::plural('sign-up', $code->referrals_count) }}
                        </span>
                        <button type="button" class="aff-btn" data-aff-copy="{{ $code->code }}" data-aff-copy-label="Copy code">
                            <i class="fa fa-copy" aria-hidden="true"></i> <span>Copy code</span>
                        </button>
                        <form method="POST" action="{{ route($toggleRoute, $code) }}"
                              @if($code->is_active) onsubmit="return confirm('Disable code {{ $code->code }}? New sign-ups will no longer be able to use it. Accounts already referred stay credited.')" @endif>
                            @csrf
                            @method('PATCH')
                            @if($code->is_active)
                                <button type="submit" class="aff-btn aff-btn--danger"><i class="fa fa-ban" aria-hidden="true"></i> Disable</button>
                            @else
                                <button type="submit" class="aff-btn"><i class="fa fa-rotate-left" aria-hidden="true"></i> Enable</button>
                            @endif
                        </form>
                    </div>
                    <div class="aff-code-date">Created {{ $code->created_at->format('d M Y') }}</div>
                </div>
            @endforeach
        </div>
    @endif
</div>

@if($codes->isNotEmpty() && $canAddCode)
    <div class="aff-modal-overlay @if($codeFormErrors) is-open @endif" id="aff-code-modal" role="dialog" aria-modal="true" aria-labelledby="aff-code-modal-title">
        <div class="aff-modal-backdrop" data-aff-close></div>
        <div class="aff-modal-shell">
            <div class="aff-modal-head">
                <div>
                    <h2 class="aff-modal-title" id="aff-code-modal-title">New referral code</h2>
                    <p class="aff-modal-sub">Use a separate code for each place you share, to see which one brings sign-ups.</p>
                </div>
                <button class="aff-modal-close" type="button" data-aff-close aria-label="Close"><i class="fa fa-xmark" aria-hidden="true"></i></button>
            </div>
            <form method="POST" action="{{ $storeAction }}">
                @csrf
                <div class="aff-modal-body">
                    @include('auth::affiliate.partials.code-fields', ['idPrefix' => 'aff-modal'])
                </div>
                <div class="aff-modal-foot">
                    <button type="button" class="aff-cancel-btn" data-aff-close>Cancel</button>
                    <button type="submit" class="aff-submit-btn"><i class="fa fa-wand-magic-sparkles" aria-hidden="true"></i> Generate code</button>
                </div>
            </form>
        </div>
    </div>
@endif

<script>
(function () {
    // Copy link / Copy code buttons
    function markCopied(btn) {
        var label = btn.querySelector('span');
        var original = btn.getAttribute('data-aff-copy-label') || '';
        btn.classList.add('is-copied');
        if (label) label.textContent = 'Copied';
        setTimeout(function () {
            btn.classList.remove('is-copied');
            if (label) label.textContent = original;
        }, 1600);
    }
    function fallbackCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(ta);
    }
    document.querySelectorAll('[data-aff-copy]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var text = btn.getAttribute('data-aff-copy');
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(function () { markCopied(btn); }, function () { fallbackCopy(text); markCopied(btn); });
            } else {
                fallbackCopy(text);
                markCopied(btn);
            }
        });
    });

    // New code modal
    var modal = document.getElementById('aff-code-modal');
    var openBtn = document.getElementById('aff-open-code-modal');
    if (!modal || !openBtn) return;
    function openModal() {
        modal.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        var first = modal.querySelector('input[name="code"]');
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
