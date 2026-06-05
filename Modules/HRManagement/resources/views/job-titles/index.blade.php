@extends('theme::layouts.app', ['title' => 'Designations', 'heading' => 'Designations'])

@php($jtCatalogModalOpen = $jobTitles->isNotEmpty() && $errors->has('name'))

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
.cat-btn-del{padding:6px 9px;font-size:11px;font-weight:600;border-radius:7px;border:1px solid color-mix(in srgb,#ef4444 42%,var(--border));background:transparent;color:#f97373;cursor:pointer;text-decoration:none;}
:is(html[data-theme="light"],html[data-theme="light_blue"]) .cat-btn-del{color:#dc2626;}
.cat-btn-view{padding:6px 9px;font-size:11px;font-weight:600;border-radius:7px;border:1px solid var(--border);background:transparent;color:var(--primary);cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;}
.cat-btn-view:hover{background:color-mix(in srgb,var(--primary) 8%,transparent);border-color:color-mix(in srgb,var(--primary) 35%,var(--border));}
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
    @if($errors->has('designation'))
        <div class="cat-banner cat-banner--err" role="alert">{{ $errors->first('designation') }}</div>
    @endif

    <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
        Job titles / designations for <strong style="color:var(--text);">{{ $business->name }}</strong>. Names are unique per business. Delete is allowed only when no employees use the designation.
        <a href="{{ route('hr.index') }}" style="color:var(--primary);font-weight:600;">HR hub</a>
    </p>

    <div class="cat-toolbar">
        <span class="muted" style="margin:0;font-size:13px;">
            @if($jobTitles->isEmpty())
                Add your <strong style="color:var(--text);">first designation</strong> below.
            @else
                {{ $jobTitles->count() }} designation{{ $jobTitles->count() === 1 ? '' : 's' }}.
            @endif
        </span>
        @if($jobTitles->isNotEmpty())
            <button type="button" id="jt-catalog-open" class="linkbtn" style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;"><i class="fa fa-plus"></i> Add designation</button>
        @endif
    </div>

    @if($jobTitles->isEmpty())
        <section class="cat-inline" aria-labelledby="jt-cat-inline-title">
            <h2 id="jt-cat-inline-title">Create designation</h2>
            <p class="cat-muted">Roles and titles offered to employees.</p>
            @if($errors->any())
                <div class="cat-banner cat-banner--err" style="margin-top:12px;" role="alert">{{ $errors->first() }}</div>
            @endif
            <form method="post" action="{{ route('hr.job-titles.store') }}" style="margin-top:14px;">
                @csrf
                <div class="cat-field">
                    <label for="jt-catalog-name-inline">Designation name</label>
                    <input type="text" name="name" id="jt-catalog-name-inline" value="{{ old('name') }}" required maxlength="255" autocomplete="organization-title" placeholder="e.g. Accountant">
                </div>
                <div style="margin-top:12px;display:flex;justify-content:flex-end;">
                    <button type="submit" class="linkbtn" style="padding:8px 16px;font-size:13px;">Save designation</button>
                </div>
            </form>
        </section>
    @else
        <div class="cat-table-wrap">
            <table class="cat-table">
                <thead>
                    <tr>
                        <th>Designation</th>
                        <th>Employees</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($jobTitles as $jt)
                        <tr>
                            <td>
                                <a href="{{ route('hr.job-titles.show', $jt) }}" style="color:var(--text);font-weight:700;text-decoration:none;">{{ $jt->name }}</a>
                            </td>
                            <td>
                                @if((int) $jt->employees_count > 0)
                                    <a href="{{ route('hr.job-titles.show', ['jobTitle' => $jt, 'tab' => 'employees']) }}" style="color:var(--primary);font-size:13px;text-decoration:none;font-weight:600;">{{ (int) $jt->employees_count }}</a>
                                @else
                                    <span class="muted">0</span>
                                @endif
                            </td>
                            <td style="text-align:right;">
                                <div style="display:inline-flex;align-items:center;gap:8px;">
                                    <a href="{{ route('hr.job-titles.show', $jt) }}" class="cat-btn-view"><i class="fa fa-eye" style="margin-right:4px;"></i>View</a>
                                    @if(((int) $jt->employees_count) === 0)
                                        <form method="post" action="{{ route('hr.job-titles.destroy', $jt) }}" style="margin:0;display:inline;" onsubmit="return confirm('Delete this designation?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="cat-btn-del"><i class="fa fa-trash-can" style="margin-right:4px;"></i>Delete</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div id="jt-catalog-modal" class="cat-modal {{ $jtCatalogModalOpen ? 'cat-modal--open' : '' }}" role="dialog" aria-modal="true" aria-labelledby="jt-cat-modal-title" aria-hidden="{{ $jtCatalogModalOpen ? 'false' : 'true' }}">
            <div class="cat-modal__backdrop" data-jt-cat-close tabindex="-1"></div>
            <div class="cat-modal__panel">
                <div class="cat-modal__head">
                    <h2 id="jt-cat-modal-title">Add designation</h2>
                    <button type="button" class="cat-modal__close" data-jt-cat-close aria-label="Close">&times;</button>
                </div>
                <div class="cat-modal__body">
                    @if($errors->has('name'))
                        <div class="cat-banner cat-banner--err" style="margin-bottom:12px;">{{ $errors->first('name') }}</div>
                    @endif
                    <form method="post" action="{{ route('hr.job-titles.store') }}">
                        @csrf
                        <div class="cat-field">
                            <label for="jt-catalog-name-modal">Designation name</label>
                            <input type="text" name="name" id="jt-catalog-name-modal" value="{{ old('name') }}" required maxlength="255" autocomplete="organization-title" placeholder="e.g. Accountant">
                        </div>
                        <div style="margin-top:14px;display:flex;justify-content:flex-end;">
                            <button type="submit" class="linkbtn" style="padding:8px 16px;font-size:13px;">Save designation</button>
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

@if($jobTitles->isNotEmpty())
<script>
(function () {
    function lock(on) {
        document.documentElement.classList.toggle('cat-modal-open-html', Boolean(on));
    }
    var modal = document.getElementById('jt-catalog-modal');
    var btn = document.getElementById('jt-catalog-open');
    function openM() {
        if (!modal) return;
        modal.classList.add('cat-modal--open');
        modal.setAttribute('aria-hidden', 'false');
        lock(true);
        var i = document.getElementById('jt-catalog-name-modal');
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
    modal && modal.querySelectorAll('[data-jt-cat-close]').forEach(function (el) {
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
