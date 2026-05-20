@extends('theme::layouts.app', ['title' => 'Employees', 'heading' => 'Employees'])

@php($empModalOpen = $employees->isNotEmpty() && $errors->any())

@section('content')
<style>
.emp-page{max-width:100%;margin:0;}
.emp-toolbar{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px;}
.emp-form-grid{display:flex;flex-direction:column;gap:18px;}
.emp-fieldset{margin:0;padding:0;border:1px solid var(--border);border-radius:12px;background:color-mix(in srgb,var(--card) 98%,transparent);padding:14px 16px;}
.emp-legend{font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin:0 0 12px;padding:0;width:100%;}
.emp-form-rows{display:grid;gap:10px 14px;}@media (min-width:720px){.emp-form-rows{grid-template-columns:repeat(2,minmax(0,1fr));}}
.emp-field label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:5px;}
@media (min-width:720px){.emp-field--full{grid-column:1/-1;}}
.emp-req{color:color-mix(in srgb,#f87171 85%,var(--text));}
.emp-field input,.emp-field textarea,.emp-field select{
    width:100%;box-sizing:border-box;padding:9px 10px;font-size:13px;border-radius:8px;border:1px solid var(--border);
    background:var(--card);color:var(--text);font-family:inherit;
}
.emp-field textarea{min-height:72px;line-height:1.45;resize:vertical;}
.emp-field select{cursor:pointer;}
.emp-form-actions{display:flex;justify-content:flex-end;padding-top:4px;}
.emp-inline-form__banner,.emp-modal__banner{margin:0 0 12px;padding:10px 12px;border-radius:10px;border:1px solid color-mix(in srgb,#f87171 40%,var(--border));background:color-mix(in srgb,#f87171 8%,transparent);font-size:13px;color:var(--text);}
.emp-inline-create{
    box-sizing:border-box;width:100%;max-width:none;margin-top:8px;padding:14px 16px 16px;
    border-radius:12px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 98%,transparent);
}
.emp-inline-create__head{margin:0 0 14px;padding-bottom:12px;border-bottom:1px solid var(--border);}
.emp-inline-create__head h2{margin:0;font-size:16px;font-weight:800;letter-spacing:-.02em;}
.emp-inline-create__lead{margin:6px 0 0;font-size:13px;line-height:1.45;color:var(--muted);max-width:72ch;}
.emp-table-wrap{margin-top:12px;border:1px solid var(--border);border-radius:11px;overflow:auto;}
.emp-table{width:100%;border-collapse:collapse;font-size:13px;min-width:640px;}
.emp-table th{text-align:left;padding:9px 12px;background:color-mix(in srgb,var(--card) 92%,transparent);font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);border-bottom:1px solid var(--border);}
.emp-table td{padding:10px 12px;border-bottom:1px solid color-mix(in srgb,var(--border) 80%,transparent);vertical-align:top;}
.emp-table tr:last-child td{border-bottom:none;}
.emp-table tbody tr[data-show-url]{cursor:pointer;transition:background .14s ease;}
.emp-table tbody tr[data-show-url]:hover{background:color-mix(in srgb,var(--primary)7%,transparent);}
.emp-table tbody tr[data-show-url]:focus-visible{outline:2px solid color-mix(in srgb,var(--primary)52%,transparent);outline-offset:-2px;}
.emp-table__namecell{display:flex;align-items:center;gap:10px;min-width:0;}
.emp-table__thumb{
    flex-shrink:0;width:32px;height:32px;border-radius:50%;overflow:hidden;border:1px solid color-mix(in srgb,var(--border)88%,transparent);
    background:color-mix(in srgb,var(--primary)12%,var(--card));font-size:11px;font-weight:800;display:flex;align-items:center;justify-content:center;
    color:color-mix(in srgb,var(--primary)50%,var(--text));
}
.emp-table__thumb img{width:100%;height:100%;object-fit:cover;display:block;}
.emp-modal{
    position:fixed;inset:0;z-index:120;display:flex;justify-content:center;align-items:flex-start;
    padding:max(12px,2.5vh) max(14px,env(safe-area-inset-right)) calc(14px + env(safe-area-inset-bottom)) max(14px,env(safe-area-inset-left));
    overflow:auto;box-sizing:border-box;
    opacity:0;visibility:hidden;pointer-events:none;
    transition:opacity .22s ease,visibility .22s ease;
}
.emp-modal.emp-modal--open{opacity:1;visibility:visible;pointer-events:auto;}
.emp-modal__backdrop{position:fixed;inset:0;z-index:0;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);}
:is(html[data-theme="light"],html[data-theme="light_blue"]) .emp-modal__backdrop{background:rgba(17,24,39,.38);}
.emp-modal__panel{
    position:relative;z-index:1;box-sizing:border-box;width:100%;max-width:840px;
    flex:0 1 auto;
    max-height:min(94vh,calc(100dvh - 48px));
    display:flex;flex-direction:column;
    border-radius:14px;border:1px solid var(--border);background:var(--card);
    box-shadow:0 20px 48px rgba(0,0,0,.32);margin:auto;
}
.emp-modal__head{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:11px 14px;border-bottom:1px solid var(--border);flex-shrink:0;}
.emp-modal__head h2{margin:0;font-size:15px;font-weight:800;}
.emp-modal__close{width:32px;height:32px;display:grid;place-items:center;padding:0;border:1px solid var(--border);border-radius:9px;background:color-mix(in srgb,var(--card) 88%,transparent);color:var(--text);cursor:pointer;font-size:17px;line-height:1;}
.emp-modal__close:hover{border-color:color-mix(in srgb,var(--primary) 40%,var(--border));}
.emp-modal__body{padding:14px 14px 16px;overflow:auto;-webkit-overflow-scrolling:touch;}
html.emp-modal-open-html,html.emp-modal-open-html body{overflow:hidden;}
.emp-salary-hint--bad{color:color-mix(in srgb,#f87171 92%,var(--text));font-weight:600;}
</style>

<div class="emp-page card" style="max-width:100%;padding:14px;">
    @if(session('status'))
        <div style="margin:0 0 12px;padding:10px 12px;border-radius:10px;border:1px solid color-mix(in srgb,#22c55e 40%,var(--border));background:color-mix(in srgb,#22c55e 9%,transparent);font-size:13px;font-weight:600;">{{ session('status') }}</div>
    @endif
    @if(session('warning'))
        <div style="margin:0 0 12px;padding:10px 12px;border-radius:10px;border:1px solid color-mix(in srgb,#f59e0b 45%,var(--border));background:color-mix(in srgb,#f59e0b 10%,transparent);font-size:13px;font-weight:600;">{{ session('warning') }}</div>
    @endif
    <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
        Employees for <strong style="color:var(--text);">{{ $business->name }}</strong>. Departments and designations live in catalogues—you can extend them inline here or centrally on <a href="{{ route('hr.departments.index') }}" style="color:var(--primary);font-weight:600;">Departments</a> and <a href="{{ route('hr.job-titles.index') }}" style="color:var(--primary);font-weight:600;">Designations</a>. <a href="{{ route('hr.allowance-types.index') }}" style="color:var(--primary);font-weight:600;">Allowance types</a> define payroll add-ons per business. New rows from this save add to the catalogue when you submit. Dates use <strong>Y–M–D</strong> here.
        <a href="{{ route('hr.index') }}" style="color:var(--primary);font-weight:600;">HR hub</a>
    </p>

    @if($departments->isNotEmpty() || $jobTitles->isNotEmpty())
        <p class="muted" style="margin:-6px 0 14px;font-size:13px;line-height:1.45;">
            @if($departments->isNotEmpty())<strong style="color:var(--text);">Departments:</strong> {{ $departments->pluck('name')->join(' · ') }}@endif
            @if($departments->isNotEmpty() && $jobTitles->isNotEmpty())<span class="muted"> · </span>@endif
            @if($jobTitles->isNotEmpty())<strong style="color:var(--text);">Job titles:</strong> {{ $jobTitles->pluck('name')->join(' · ') }}@endif
        </p>
    @endif

    <div class="emp-toolbar">
        <span class="muted" style="margin:0;font-size:13px;">
            @if($employees->isEmpty())
                Register your <strong style="color:var(--text);">first employee</strong> below.
            @else
                {{ $employees->count() }} employee{{ $employees->count() === 1 ? '' : 's' }}.
            @endif
        </span>
        @if($employees->isNotEmpty())
            <button type="button" id="emp-modal-open" class="linkbtn" style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;"><i class="fa fa-user-plus"></i> Create employee</button>
        @endif
    </div>

    @if($employees->isEmpty())
        <section class="emp-inline-create" aria-labelledby="emp-inline-title">
            <header class="emp-inline-create__head">
                <h2 id="emp-inline-title">Create employee</h2>
                <p class="emp-inline-create__lead">Legal identity, employment, basic salary &amp; monthly gross (with optional allowance lines from your catalogue), emergency contact, bank details, and optional statutory references — stored only for this business.</p>
            </header>
            @include('hrmanagement::employees.partials.create-form', [
                'business' => $business,
                'formBannerClass' => 'emp-inline-form__banner',
                'showFormErrorBanner' => $errors->any(),
                'employmentTypeLabels' => $employmentTypeLabels,
                'banks' => $banks,
                'departments' => $departments,
                'jobTitles' => $jobTitles,
                'allowanceTypes' => $allowanceTypes,
                'submitLabel' => 'Create employee',
            ])
        </section>
    @else
        <div class="emp-table-wrap">
            <table class="emp-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Employee ID</th>
                        <th>Role</th>
                        <th>Department</th>
                        <th>Phone</th>
                        <th>Bank</th>
                        <th>Joined</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($employees as $employee)
                        <tr
                            tabindex="0"
                            role="link"
                            data-show-url="{{ route('hr.employees.show', $employee) }}"
                            aria-label="{{ __('Open profile for :name', ['name' => $employee->full_name]) }}"
                        >
                            <td>
                                <div class="emp-table__namecell">
                                    @if($employee->profilePhotoUrl())
                                        <span class="emp-table__thumb" aria-hidden="true"><img src="{{ $employee->profilePhotoUrl() }}" alt="" width="32" height="32"></span>
                                    @else
                                        <span class="emp-table__thumb" aria-hidden="true">{{ $employee->avatarInitials() }}</span>
                                    @endif
                                    <strong style="color:var(--text);min-width:0;">{{ $employee->full_name }}</strong>
                                </div>
                            </td>
                            <td>{{ $employee->employee_id }}</td>
                            <td>{{ $employee->jobTitle?->name ?? '—' }}</td>
                            <td>{{ $employee->department?->name ?? '—' }}</td>
                            <td>{{ $employee->phone_number }}</td>
                            <td>{{ $employee->bank?->name ?? '—' }}</td>
                            <td class="muted">{{ $employee->date_of_joining?->format('Y-m-d') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div id="emp-modal" class="emp-modal {{ $empModalOpen ? 'emp-modal--open' : '' }}" role="dialog" aria-modal="true" aria-labelledby="emp-modal-title" aria-hidden="{{ $empModalOpen ? 'false' : 'true' }}">
            <div class="emp-modal__backdrop" data-emp-modal-close tabindex="-1"></div>
            <div class="emp-modal__panel">
                <div class="emp-modal__head">
                    <h2 id="emp-modal-title">Create employee</h2>
                    <button type="button" class="emp-modal__close" data-emp-modal-close aria-label="Close">&times;</button>
                </div>
                <div class="emp-modal__body">
                    @include('hrmanagement::employees.partials.create-form', [
                        'business' => $business,
                        'formBannerClass' => 'emp-modal__banner',
                        'showFormErrorBanner' => $errors->any(),
                        'employmentTypeLabels' => $employmentTypeLabels,
                        'banks' => $banks,
                        'departments' => $departments,
                        'jobTitles' => $jobTitles,
                        'allowanceTypes' => $allowanceTypes,
                        'submitLabel' => 'Create employee',
                    ])
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

<script>
(function () {
    var modal = document.getElementById('emp-modal');
    var openBtn = document.getElementById('emp-modal-open');

    function lockScroll(on) {
        document.documentElement.classList.toggle('emp-modal-open-html', Boolean(on));
    }

    function openEmpModal() {
        if (!modal) return;
        modal.classList.add('emp-modal--open');
        modal.setAttribute('aria-hidden', 'false');
        lockScroll(true);
        var first = document.getElementById('emp-full-name');
        window.requestAnimationFrame(function () { if (first) first.focus(); });
    }

    function closeEmpModal() {
        if (!modal) return;
        modal.classList.remove('emp-modal--open');
        modal.setAttribute('aria-hidden', 'true');
        lockScroll(false);
        if (openBtn) openBtn.focus();
    }

    if (openBtn) openBtn.addEventListener('click', openEmpModal);
    if (modal) {
        modal.querySelectorAll('[data-emp-modal-close]').forEach(function (el) {
            el.addEventListener('click', closeEmpModal);
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (!modal || !modal.classList.contains('emp-modal--open')) return;
        closeEmpModal();
    });

    if (modal && modal.classList.contains('emp-modal--open')) {
        lockScroll(true);
    }

    document.querySelectorAll('.emp-table tbody tr[data-show-url]').forEach(function (row) {
        function go () {
            var u = row.getAttribute('data-show-url');
            if (u) window.location.href = u;
        }
        row.addEventListener('click', go);
        row.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                go();
            }
        });
    });
})();
</script>
@endsection
