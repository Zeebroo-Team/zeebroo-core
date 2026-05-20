@extends('theme::layouts.app', ['title' => 'Departments', 'heading' => 'Departments'])

@php($deptCatalogModalOpen = $departments->isNotEmpty() && $errors->has('name'))

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
    @if($errors->has('department'))
        <div class="cat-banner cat-banner--err" role="alert">{{ $errors->first('department') }}</div>
    @endif

    <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
        Departments for <strong style="color:var(--text);">{{ $business->name }}</strong>. Names are unique per business. Delete is allowed only when no employees reference the department.
        <a href="{{ route('hr.index') }}" style="color:var(--primary);font-weight:600;">HR hub</a>
    </p>

    <div class="cat-toolbar">
        <span class="muted" style="margin:0;font-size:13px;">
            @if($departments->isEmpty())
                Add your <strong style="color:var(--text);">first department</strong> below.
            @else
                {{ $departments->count() }} department{{ $departments->count() === 1 ? '' : 's' }}.
            @endif
        </span>
        <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
            <a href="{{ route('hr.departments.growth') }}" class="linkbtn" style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;text-decoration:none;background:transparent;color:var(--text);border:1px solid var(--border);">
                <i class="fa fa-chart-line"></i>{{ __('Growth overview') }}
            </a>
            @if($departments->isNotEmpty())
                <button type="button" id="dept-catalog-open" class="linkbtn" style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;"><i class="fa fa-plus"></i> Add department</button>
            @endif
        </div>
    </div>

    @if($departments->isEmpty())
        <section class="cat-inline" aria-labelledby="dept-cat-inline-title">
            <h2 id="dept-cat-inline-title">Create department</h2>
            <p class="cat-muted">Teams, divisions, or sites—used when registering employees.</p>
            @if($errors->any())
                <div class="cat-banner cat-banner--err" style="margin-top:12px;" role="alert">{{ $errors->first() }}</div>
            @endif
            <form method="post" action="{{ route('hr.departments.store') }}" style="margin-top:14px;">
                @csrf
                <div class="cat-field">
                    <label for="dept-catalog-name-inline">Department name</label>
                    <input type="text" name="name" id="dept-catalog-name-inline" value="{{ old('name') }}" required maxlength="255" autocomplete="organization" placeholder="e.g. Operations">
                </div>
                <div style="margin-top:12px;display:flex;justify-content:flex-end;">
                    <button type="submit" class="linkbtn" style="padding:8px 16px;font-size:13px;">Save department</button>
                </div>
            </form>
        </section>
    @else
        @php($deptListCurrency = trim((string) get_settings('business.currency', '', $business)))
        @php($ccReport = $costCenterReport ?? [])
        @php($ccAvail = (bool) ($ccReport['available'] ?? false))
        @php($ccCur = (string) ($ccReport['currency'] ?? ''))
        @php($ccRowsById = [])
        @if($ccAvail)
            @foreach(($ccReport['rows'] ?? []) as $_ccRow)
                @php($ccRowsById[$_ccRow['department']->id] = $_ccRow)
            @endforeach
        @endif
        <div class="cat-table-wrap">
            <table class="cat-table">
                <thead>
                    <tr>
                        <th>{{ __('Department') }}</th>
                        <th>{{ __('Employees') }}</th>
                        <th>{{ __('Salary guide') }}</th>
                        <th>{{ __('Cost center') }}</th>
                        <th style="text-align:right;">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($departments as $dept)
                        @php($dmin = $dept->salary_range_min)
                        @php($dmax = $dept->salary_range_max)
                        @php($ccRow = $ccRowsById[$dept->id] ?? null)
                        <tr>
                            <td>
                                <a href="{{ route('hr.departments.show', $dept) }}" style="color:var(--primary);font-weight:700;text-decoration:none;">
                                    {{ $dept->name }}
                                </a>
                            </td>
                            <td class="muted">{{ (int) $dept->employees_count }}</td>
                            <td class="muted" style="font-size:12px;line-height:1.35;white-space:nowrap;">
                                @if($dmin === null && $dmax === null)
                                    —
                                @elseif($dmin !== null && $dmax !== null)
                                    @if($deptListCurrency !== '')<span style="opacity:.72;text-transform:uppercase;font-size:10px;">{{ $deptListCurrency }}</span> @endif{{ number_format((float) $dmin, 0) }}–{{ number_format((float) $dmax, 0) }}
                                @elseif($dmin !== null)
                                    {{ __('Min') }} @if($deptListCurrency !== ''){{ $deptListCurrency }} @endif{{ number_format((float) $dmin, 0) }}
                                @else
                                    {{ __('Max') }} @if($deptListCurrency !== ''){{ $deptListCurrency }} @endif{{ number_format((float) $dmax, 0) }}
                                @endif
                            </td>
                            <td class="muted" style="font-size:12px;line-height:1.4;max-width:15rem;">
                                @if(! $ccAvail)
                                    <span title="{{ __('Bill–department linking not available') }}">—</span>
                                @elseif($ccRow === null)
                                    —
                                @else
                                    <span style="font-weight:700;color:var(--text);font-variant-numeric:tabular-nums;">
                                        @if($ccCur !== '')<span style="opacity:.72;text-transform:uppercase;font-size:10px;">{{ $ccCur }}</span> @endif{{ number_format((float) $ccRow['cost_center_total'], 2, '.', ',') }}
                                    </span>
                                    <span style="display:block;font-size:11px;opacity:.88;margin-top:3px;font-variant-numeric:tabular-nums;">
                                        {{ number_format((float) $ccRow['assigned_total'], 2, '.', ',') }} + {{ number_format((float) $ccRow['unallocated_share'], 2, '.', ',') }}
                                    </span>
                                @endif
                            </td>
                            <td style="text-align:right;">
                                @if(((int) $dept->employees_count) === 0)
                                    <form method="post" action="{{ route('hr.departments.destroy', $dept) }}" style="margin:0;display:inline;" onsubmit="return confirm('Delete this department?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="cat-btn-del"><i class="fa fa-trash-can" style="margin-right:4px;"></i>Delete</button>
                                    </form>
                                @else
                                    <span class="muted" style="font-size:12px;">In use</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div id="dept-catalog-modal" class="cat-modal {{ $deptCatalogModalOpen ? 'cat-modal--open' : '' }}" role="dialog" aria-modal="true" aria-labelledby="dept-cat-modal-title" aria-hidden="{{ $deptCatalogModalOpen ? 'false' : 'true' }}">
            <div class="cat-modal__backdrop" data-dept-cat-close tabindex="-1"></div>
            <div class="cat-modal__panel">
                <div class="cat-modal__head">
                    <h2 id="dept-cat-modal-title">Add department</h2>
                    <button type="button" class="cat-modal__close" data-dept-cat-close aria-label="Close">&times;</button>
                </div>
                <div class="cat-modal__body">
                    @if($errors->has('name'))
                        <div class="cat-banner cat-banner--err" style="margin-bottom:12px;">{{ $errors->first('name') }}</div>
                    @endif
                    <form method="post" action="{{ route('hr.departments.store') }}">
                        @csrf
                        <div class="cat-field">
                            <label for="dept-catalog-name-modal">Department name</label>
                            <input type="text" name="name" id="dept-catalog-name-modal" value="{{ old('name') }}" required maxlength="255" autocomplete="organization" placeholder="e.g. Operations">
                        </div>
                        <div style="margin-top:14px;display:flex;justify-content:flex-end;">
                            <button type="submit" class="linkbtn" style="padding:8px 16px;font-size:13px;">Save department</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>

<div style="margin-top:14px;">
    <a href="{{ route('hr.index') }}" class="linkbtn" style="padding:7px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fa fa-arrow-left"></i> HR hub
    </a>
</div>

@if($departments->isNotEmpty())
<script>
(function () {
    function lock(on) {
        document.documentElement.classList.toggle('cat-modal-open-html', Boolean(on));
    }
    var modal = document.getElementById('dept-catalog-modal');
    var btn = document.getElementById('dept-catalog-open');
    function openM() {
        if (!modal) return;
        modal.classList.add('cat-modal--open');
        modal.setAttribute('aria-hidden', 'false');
        lock(true);
        var i = document.getElementById('dept-catalog-name-modal');
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
    modal && modal.querySelectorAll('[data-dept-cat-close]').forEach(function (el) {
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
