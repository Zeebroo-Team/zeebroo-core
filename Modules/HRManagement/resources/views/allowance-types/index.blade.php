@extends('theme::layouts.app', ['title' => __('Allowance types'), 'heading' => __('Allowance types')])

@php($atCatalogModalOpen = $allowanceTypes->isNotEmpty() && $errors->has('name'))

@section('content')
<style>
.cat-page-card{max-width:100%;margin:0;}
.cat-toolbar{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px;}
.cat-field label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:5px;}
.cat-field input{width:100%;box-sizing:border-box;padding:9px 10px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);}
.cat-banner{margin:0 0 12px;padding:10px 12px;border-radius:10px;font-size:13px;}
.cat-banner--ok{border:1px solid color-mix(in srgb,#22c55e 40%,var(--border));background:color-mix(in srgb,#22c55e 9%,transparent);}
.cat-banner--err{border:1px solid color-mix(in srgb,#f87171 40%,var(--border));background:color-mix(in srgb,#f87171 8%,transparent);}
.cat-inline{border-radius:12px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 98%,transparent);padding:14px 16px 16px;}
.cat-inline h2{margin:0 0 8px;font-size:16px;font-weight:800;}
.cat-muted{margin:6px 0 0;font-size:13px;line-height:1.45;color:var(--muted);max-width:62ch;}
.cat-table-wrap{border:1px solid var(--border);border-radius:11px;overflow:auto;}
.cat-table{width:100%;border-collapse:collapse;font-size:13px;min-width:400px;}
.cat-table th{text-align:left;padding:9px 12px;background:color-mix(in srgb,var(--card) 92%,transparent);font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);border-bottom:1px solid var(--border);}
.cat-table td{padding:10px 12px;border-bottom:1px solid color-mix(in srgb,var(--border) 80%,transparent);}
.cat-table tr:last-child td{border-bottom:none;}
.cat-btn-del{padding:6px 9px;font-size:11px;font-weight:600;border-radius:7px;border:1px solid color-mix(in srgb,#ef4444 42%,var(--border));background:transparent;color:#f97373;cursor:pointer;}
:is(html[data-theme="light"],html[data-theme="light_blue"]) .cat-btn-del{color:#dc2626;}
.cat-modal{
    position:fixed;inset:0;z-index:120;display:flex;justify-content:center;align-items:flex-start;
    padding:max(12px,2.5vh) max(14px,env(safe-area-inset-right)) calc(14px + env(safe-area-inset-bottom)) max(14px,env(safe-area-inset-left));
    overflow:auto;box-sizing:border-box;opacity:0;visibility:hidden;pointer-events:none;transition:opacity .22s ease,visibility .22s ease;
}
.cat-modal.cat-modal--open{opacity:1;visibility:visible;pointer-events:auto;}
.cat-modal__backdrop{position:fixed;inset:0;z-index:0;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);}
:is(html[data-theme="light"],html[data-theme="light_blue"]) .cat-modal__backdrop{background:rgba(17,24,39,.38);}
.cat-modal__panel{position:relative;z-index:1;width:100%;max-width:480px;margin:auto;border-radius:14px;border:1px solid var(--border);background:var(--card);box-shadow:0 20px 48px rgba(0,0,0,.32);display:flex;flex-direction:column;max-height:min(94vh,calc(100dvh - 48px));}
.cat-modal__head{display:flex;justify-content:space-between;align-items:center;padding:11px 14px;border-bottom:1px solid var(--border);}
.cat-modal__head h2{margin:0;font-size:15px;font-weight:800;}
.cat-modal__close{width:32px;height:32px;display:grid;place-items:center;border:1px solid var(--border);border-radius:9px;background:transparent;color:inherit;cursor:pointer;font-size:17px;line-height:1;}
.cat-modal__body{padding:14px;}
html.cat-modal-open-html,html.cat-modal-open-html body{overflow:hidden;}
</style>

<div class="cat-page-card card" style="max-width:100%;padding:14px;">
    @if(session('status'))
        <div class="cat-banner cat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->has('allowance_type'))
        <div class="cat-banner cat-banner--err" role="alert">{{ $errors->first('allowance_type') }}</div>
    @endif

    <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
        {{ __('Predefined allowance labels for this business (e.g. transport, meal). When you create an employee, you can enter amounts per type; monthly gross must equal basic salary plus those amounts.') }}
        <a href="{{ route('hr.index') }}" style="color:var(--primary);font-weight:600;">{{ __('HR hub') }}</a>
        · <a href="{{ route('hr.employees.index') }}" style="color:var(--primary);font-weight:600;">{{ __('Employees') }}</a>
    </p>

    <div class="cat-toolbar">
        <span class="muted" style="margin:0;font-size:13px;">
            @if($allowanceTypes->isEmpty())
                {{ __('Add your') }} <strong style="color:var(--text);">{{ __('first allowance type') }}</strong> {{ __('below (optional until you use allowances on payroll).') }}
            @else
                {{ $allowanceTypes->count() }} {{ $allowanceTypes->count() === 1 ? __('allowance type') : __('allowance types') }}.
            @endif
        </span>
        @if($allowanceTypes->isNotEmpty())
            <button type="button" id="at-catalog-open" class="linkbtn" style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;"><i class="fa fa-plus"></i> {{ __('Add type') }}</button>
        @endif
    </div>

    @if($allowanceTypes->isEmpty())
        <section class="cat-inline" aria-labelledby="at-cat-inline-title">
            <h2 id="at-cat-inline-title">{{ __('Create allowance type') }}</h2>
            <p class="cat-muted">{{ __('Examples: Transport, Phone, Meal, Housing.') }}</p>
            @if($errors->any())
                <div class="cat-banner cat-banner--err" style="margin-top:12px;" role="alert">{{ $errors->first() }}</div>
            @endif
            <form method="post" action="{{ route('hr.allowance-types.store') }}" style="margin-top:14px;">
                @csrf
                <div class="cat-field">
                    <label for="at-catalog-name-inline">{{ __('Name') }}</label>
                    <input type="text" name="name" id="at-catalog-name-inline" value="{{ old('name') }}" required maxlength="255" autocomplete="off" placeholder="{{ __('e.g. Transport') }}">
                </div>
                <div style="margin-top:12px;display:flex;justify-content:flex-end;">
                    <button type="submit" class="linkbtn" style="padding:8px 16px;font-size:13px;">{{ __('Save') }}</button>
                </div>
            </form>
        </section>
    @else
        <div class="cat-table-wrap">
            <table class="cat-table">
                <thead>
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Employees') }}</th>
                        <th style="text-align:right;">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($allowanceTypes as $row)
                        <tr>
                            <td><strong style="color:var(--text);">{{ $row->name }}</strong></td>
                            <td class="muted">{{ (int) $row->employee_allowances_count }}</td>
                            <td style="text-align:right;">
                                @if(((int) $row->employee_allowances_count) === 0)
                                    <form method="post" action="{{ route('hr.allowance-types.destroy', $row) }}" style="margin:0;display:inline;" onsubmit="return confirm(@json(__('Delete this allowance type?')));">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="cat-btn-del"><i class="fa fa-trash-can" style="margin-right:4px;"></i>{{ __('Delete') }}</button>
                                    </form>
                                @else
                                    <span class="muted" style="font-size:12px;">{{ __('In use') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div id="at-catalog-modal" class="cat-modal {{ $atCatalogModalOpen ? 'cat-modal--open' : '' }}" role="dialog" aria-modal="true" aria-labelledby="at-cat-modal-title" aria-hidden="{{ $atCatalogModalOpen ? 'false' : 'true' }}">
            <div class="cat-modal__backdrop" data-at-cat-close tabindex="-1"></div>
            <div class="cat-modal__panel">
                <div class="cat-modal__head">
                    <h2 id="at-cat-modal-title">{{ __('Add allowance type') }}</h2>
                    <button type="button" class="cat-modal__close" data-at-cat-close aria-label="{{ __('Close') }}">&times;</button>
                </div>
                <div class="cat-modal__body">
                    @if($errors->has('name'))
                        <div class="cat-banner cat-banner--err" style="margin-bottom:12px;">{{ $errors->first('name') }}</div>
                    @endif
                    <form method="post" action="{{ route('hr.allowance-types.store') }}">
                        @csrf
                        <div class="cat-field">
                            <label for="at-catalog-name-modal">{{ __('Name') }}</label>
                            <input type="text" name="name" id="at-catalog-name-modal" value="{{ old('name') }}" required maxlength="255" autocomplete="off" placeholder="{{ __('e.g. Meal') }}">
                        </div>
                        <div style="margin-top:14px;display:flex;justify-content:flex-end;">
                            <button type="submit" class="linkbtn" style="padding:8px 16px;font-size:13px;">{{ __('Save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>

<div style="margin-top:14px;">
    <a href="{{ route('hr.index') }}" class="linkbtn" style="padding:7px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fa fa-arrow-left"></i> {{ __('HR hub') }}
    </a>
</div>

@if($allowanceTypes->isNotEmpty())
<script>
(function () {
    function lock(on) {
        document.documentElement.classList.toggle('cat-modal-open-html', Boolean(on));
    }
    var modal = document.getElementById('at-catalog-modal');
    var btn = document.getElementById('at-catalog-open');
    function openM() {
        if (!modal) return;
        modal.classList.add('cat-modal--open');
        modal.setAttribute('aria-hidden', 'false');
        lock(true);
        var i = document.getElementById('at-catalog-name-modal');
        window.requestAnimationFrame(function () { if (i) i.focus(); });
    }
    function closeM() {
        if (!modal) return;
        modal.classList.remove('cat-modal--open');
        modal.setAttribute('aria-hidden', 'true');
        lock(false);
        if (btn) btn.focus();
    }
    btn && btn.addEventListener('click', openM);
    modal && modal.querySelectorAll('[data-at-cat-close]').forEach(function (el) {
        el.addEventListener('click', closeM);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (modal && modal.classList.contains('cat-modal--open')) closeM();
    });
    if (modal && modal.classList.contains('cat-modal--open')) lock(true);
})();
</script>
@endif
@endsection
