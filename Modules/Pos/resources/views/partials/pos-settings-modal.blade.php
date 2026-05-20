@php
    $posSettings = $posSettings ?? [];
    $redirectUrl = url()->current();
    $isDarkMode = ($posSettings['display_theme'] ?? 'inherit') === 'dark';
@endphp

<div id="pos-settings-modal" class="pos-settings-modal" role="dialog" aria-modal="true" aria-labelledby="pos-settings-title" aria-hidden="true">
    <div class="pos-settings-modal__backdrop" data-pos-settings-close tabindex="-1" aria-label="Close"></div>
    <div class="pos-settings-modal__panel">
        <div class="pos-settings-modal__head">
            <h2 id="pos-settings-title">POS settings</h2>
            <button type="button" class="pos-settings-modal__close" data-pos-settings-close aria-label="Close">&times;</button>
        </div>
        <form method="post" action="{{ route('pos.settings.save') }}">
            @csrf
            <input type="hidden" name="redirect" value="{{ $redirectUrl }}">

            <div class="pos-settings-field">
                <label for="pos-settings-deposit">Deposit to account</label>
                <select name="default_deposit_account_id" id="pos-settings-deposit">
                    <option value="">— Choose each sale —</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}" @selected((int) ($posSettings['default_deposit_account_id'] ?? 0) === (int) $account->id)>
                            {{ $account->deductOptionLabel() }}
                        </option>
                    @endforeach
                </select>
                @if(!($hasAccounts ?? true))
                    <p class="muted" style="margin:6px 0 0;font-size:11px;">Add a <a href="{{ route('account.onboarding') }}" class="pcat-link">business account</a> first.</p>
                @endif
            </div>

            <div class="pos-settings-row">
                <span class="pos-settings-row__label">Discount field on checkout</span>
                <label class="pos-walking-switch" style="flex-shrink:0;">
                    <input type="hidden" name="discount_field_enabled" value="0">
                    <input type="checkbox" name="discount_field_enabled" value="1" @checked($posSettings['discount_field_enabled'] ?? false)>
                    <span class="pos-walking-switch__slider" aria-hidden="true"></span>
                </label>
            </div>

            <div class="pos-settings-row pos-settings-row--theme">
                <span class="pos-settings-row__label"><i class="fa fa-sun" aria-hidden="true"></i> Light</span>
                <label class="pos-walking-switch" style="flex-shrink:0;" title="Dark mode">
                    <input type="hidden" name="display_theme" value="{{ $isDarkMode ? 'dark' : 'light' }}" id="pos-settings-theme-value">
                    <input type="checkbox" id="pos-settings-theme-dark" @checked($isDarkMode)>
                    <span class="pos-walking-switch__slider" aria-hidden="true"></span>
                </label>
                <span class="pos-settings-row__label pos-settings-row__label--end"><i class="fa fa-moon" aria-hidden="true"></i> Dark</span>
            </div>

            <button type="submit" class="pos-settings-save">Save settings</button>
        </form>
    </div>
</div>

<button type="button" class="pos-settings-btn" id="pos-settings-open" title="POS settings" aria-haspopup="dialog" aria-controls="pos-settings-modal">
    <i class="fa fa-gear" aria-hidden="true"></i>
</button>

@once
<script>
(function () {
    const modal = document.getElementById('pos-settings-modal');
    const openBtn = document.getElementById('pos-settings-open');
    if (!modal) return;

    function setOpen(open) {
        modal.classList.toggle('is-open', open);
        modal.setAttribute('aria-hidden', open ? 'false' : 'true');
        document.documentElement.classList.toggle('pos-settings-modal-open', open);
        if (open) {
            modal.querySelector('select, input[type="checkbox"], button.pos-settings-save')?.focus();
        }
    }

    function bindOpen(el) {
        if (!el) return;
        el.addEventListener('click', function () { setOpen(true); });
    }

    bindOpen(openBtn);
    document.querySelectorAll('[data-pos-settings-open]').forEach(bindOpen);

    modal.querySelectorAll('[data-pos-settings-close]').forEach(function (el) {
        el.addEventListener('click', function () { setOpen(false); });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) {
            setOpen(false);
        }
    });

    const themeDark = document.getElementById('pos-settings-theme-dark');
    const themeValue = document.getElementById('pos-settings-theme-value');
    if (themeDark && themeValue) {
        themeDark.addEventListener('change', function () {
            themeValue.value = themeDark.checked ? 'dark' : 'light';
        });
    }
})();
</script>
@endonce
