@extends('theme::layouts.app', ['title' => __('Attendance'), 'heading' => __('Attendance')])

@section('content')
@php
    $statusOptions = $attendanceStatusOptions ?? [];
    $summaryRows = $attendanceSummaryRows ?? collect();
    $recentRows = $recentAttendanceRecords ?? collect();
    $monthLabel = \Carbon\Carbon::createFromFormat('Y-m', $attendanceMonth ?? now()->format('Y-m'))->translatedFormat('F Y');
    $kpiEmployees = (int) $summaryRows->count();
    $kpiPresent = (float) $summaryRows->sum('present_days');
    $kpiAbsent = (float) $summaryRows->sum('absent_days');
    $kpiWorkedHours = round(((int) $summaryRows->sum('worked_minutes')) / 60, 1);
@endphp
<style>
.hr-att-wrap{max-width:none;}
.hr-att-hero{
    border:1px solid color-mix(in srgb,var(--border)88%,transparent);border-radius:14px;
    background:linear-gradient(152deg,color-mix(in srgb,var(--card)95%,transparent),color-mix(in srgb,var(--primary)6%,transparent));
    padding:16px 16px 14px;margin-bottom:14px;
}
.hr-att-hero__h{margin:0 0 4px;font-size:clamp(1.02rem,1.4vw,1.2rem);font-weight:800;letter-spacing:-.02em;}
.hr-att-hero__p{margin:0;font-size:12px;color:var(--muted);line-height:1.45;}
.hr-att-kpis{display:grid;gap:10px;grid-template-columns:repeat(2,minmax(0,1fr));margin-top:12px;}
@media (min-width:900px){.hr-att-kpis{grid-template-columns:repeat(4,minmax(0,1fr));}}
.hr-att-kpi{border:1px solid color-mix(in srgb,var(--border)88%,transparent);border-left:3px solid color-mix(in srgb,var(--primary)50%,var(--border));border-radius:11px;background:var(--card);padding:10px 11px;}
.hr-att-kpi__k{margin:0 0 3px;font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);font-weight:700;}
.hr-att-kpi__v{margin:0;font-size:17px;font-weight:800;letter-spacing:-.02em;color:var(--text);}
.hr-att-grid{display:grid;gap:14px;grid-template-columns:1fr;}
.hr-att-card{border:1px solid color-mix(in srgb,var(--border)90%,transparent);border-radius:14px;background:var(--card);padding:14px;}
.hr-att-card__h{margin:0 0 10px;font-size:14px;font-weight:800;letter-spacing:-.01em;}
.hr-att-card__top{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;}
.hr-att-btn-row{display:flex;gap:8px;align-items:center;flex-wrap:wrap;}
.hr-att-inline{display:flex;gap:8px;align-items:end;flex-wrap:wrap;margin:0 0 12px;}
.hr-att-form{display:grid;gap:10px;}
.hr-att-label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin:0 0 5px;}
.hr-att-input{width:100%;box-sizing:border-box;padding:9px 10px;border-radius:10px;border:1px solid color-mix(in srgb,var(--border)90%,transparent);background:var(--card);color:var(--text);font-size:13px;line-height:1.35;}
.hr-att-input::placeholder{font-size:12px;color:color-mix(in srgb,var(--muted) 92%,transparent);}
.hr-att-form textarea.hr-att-input{resize:vertical;min-height:84px;}
.hr-att-form select.hr-att-input{padding-right:28px;}
.hr-att-input:focus{outline:none;border-color:color-mix(in srgb,var(--primary)50%,var(--border));box-shadow:0 0 0 3px color-mix(in srgb,var(--primary)14%,transparent);}
.hr-att-btn{padding:9px 13px;font-size:12px;font-weight:700;border-radius:10px;border:1px solid color-mix(in srgb,var(--primary)42%,var(--border));background:color-mix(in srgb,var(--primary)12%,transparent);color:var(--text);cursor:pointer;}
.hr-att-btn:hover{background:color-mix(in srgb,var(--primary)18%,transparent);}
.hr-att-table-wrap{overflow:auto;border:1px solid color-mix(in srgb,var(--border)88%,transparent);border-radius:11px;}
.hr-att-table{width:100%;border-collapse:collapse;min-width:820px;font-size:12px;}
.hr-att-table th,.hr-att-table td{padding:9px 10px;border-bottom:1px solid color-mix(in srgb,var(--border)74%,transparent);text-align:left;vertical-align:top;}
.hr-att-table th{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);background:color-mix(in srgb,var(--card)96%,transparent);}
.hr-att-table tr:last-child td{border-bottom:none;}
.hr-att-pill{display:inline-block;padding:2px 8px;border-radius:999px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;border:1px solid var(--border);}
.hr-att-pill--present{background:color-mix(in srgb,#22c55e 11%,transparent);color:#15803d;border-color:color-mix(in srgb,#22c55e 34%,var(--border));}
.hr-att-pill--half_day{background:color-mix(in srgb,#f59e0b 11%,transparent);color:#b45309;border-color:color-mix(in srgb,#f59e0b 34%,var(--border));}
.hr-att-pill--absent,.hr-att-pill--unpaid_leave{background:color-mix(in srgb,#ef4444 11%,transparent);color:#b91c1c;border-color:color-mix(in srgb,#ef4444 34%,var(--border));}
.hr-att-pill--paid_leave{background:color-mix(in srgb,#3b82f6 11%,transparent);color:#1d4ed8;border-color:color-mix(in srgb,#3b82f6 34%,var(--border));}
.hr-att-pill--holiday,.hr-att-pill--weekend{background:color-mix(in srgb,#64748b 12%,transparent);color:#334155;border-color:color-mix(in srgb,#64748b 28%,var(--border));}
.hr-att-empty{margin:0;padding:18px;border-radius:11px;border:1px dashed color-mix(in srgb,var(--border)84%,var(--muted));font-size:13px;line-height:1.45;color:var(--muted);}
.hr-att-modal[hidden]{display:none!important;}
.hr-att-modal{position:fixed;inset:0;z-index:65;}
.hr-att-modal__backdrop{position:absolute;inset:0;background:rgba(15,23,42,.62);backdrop-filter:blur(2px);}
.hr-att-modal__dialog{
    position:relative;background:var(--card);color:var(--text);
    width:min(560px,calc(100% - 24px));max-height:calc(100vh - 24px);overflow:auto;
    margin:12px auto;border:1px solid color-mix(in srgb,var(--border)90%,transparent);
    border-radius:14px;padding:14px;
}
.hr-att-modal__head{display:flex;align-items:center;justify-content:space-between;gap:8px;margin:0 0 10px;}
.hr-att-modal__title{margin:0;font-size:14px;font-weight:800;letter-spacing:-.01em;}
.hr-att-modal__close{
    border:1px solid color-mix(in srgb,var(--border)90%,transparent);background:transparent;
    color:var(--text);border-radius:10px;padding:6px 10px;cursor:pointer;font-size:12px;font-weight:700;
}
.hr-att-guide{margin:0;padding-left:18px;display:grid;gap:8px;font-size:13px;line-height:1.5;}
.hr-att-guide code{font-size:12px;}
.hr-att-guide__note{margin:10px 0 0;font-size:12px;color:var(--muted);}
.hr-att-code{
    margin:8px 0 0;padding:10px;border-radius:10px;overflow:auto;
    border:1px solid color-mix(in srgb,var(--border)90%,transparent);
    background:color-mix(in srgb,var(--card)94%,transparent);font-size:12px;line-height:1.45;
}
@media (max-width:640px){
    .hr-att-card{padding:12px;}
    .hr-att-inline{gap:7px;align-items:stretch;}
    .hr-att-inline label{width:100%;}
    .hr-att-inline .hr-att-btn{width:100%;}
    .hr-att-card__top .hr-att-btn{width:100%;}
    .hr-att-modal__dialog{margin:10px auto;padding:12px;}
}
</style>

<div class="hr-att-wrap">
    <section class="hr-att-hero">
        <h1 class="hr-att-hero__h">{{ __('Attendance operations') }}</h1>
        <p class="hr-att-hero__p">{{ __('Track daily attendance and monitor monthly workforce availability for :period.', ['period' => $monthLabel]) }}</p>
        <div class="hr-att-kpis" role="region" aria-label="{{ __('Attendance KPIs') }}">
            <article class="hr-att-kpi"><p class="hr-att-kpi__k">{{ __('Employees') }}</p><p class="hr-att-kpi__v">{{ number_format($kpiEmployees) }}</p></article>
            <article class="hr-att-kpi"><p class="hr-att-kpi__k">{{ __('Present days') }}</p><p class="hr-att-kpi__v">{{ number_format($kpiPresent, 1) }}</p></article>
            <article class="hr-att-kpi"><p class="hr-att-kpi__k">{{ __('Absent days') }}</p><p class="hr-att-kpi__v">{{ number_format($kpiAbsent, 1) }}</p></article>
            <article class="hr-att-kpi"><p class="hr-att-kpi__k">{{ __('Worked hours') }}</p><p class="hr-att-kpi__v">{{ number_format($kpiWorkedHours, 1) }}</p></article>
        </div>
    </section>

    @if(session('status'))
        <div class="card" style="max-width:none;margin-bottom:12px;"><strong>{{ session('status') }}</strong></div>
    @endif
    @if($errors->any())
        <div class="card" style="max-width:none;margin-bottom:12px;border-color:color-mix(in srgb,#ef4444 40%,var(--border));background:color-mix(in srgb,#ef4444 8%,transparent);">
            <strong>{{ __('Please correct the highlighted attendance form fields.') }}</strong>
            <ul style="margin:8px 0 0;padding-left:18px;">
                @foreach($errors->all() as $msg)
                    <li>{{ $msg }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <div class="hr-att-grid">
        <section class="hr-att-card">
            <div class="hr-att-card__top">
                <h2 class="hr-att-card__h">{{ __('Monthly attendance summary') }}</h2>
                <div class="hr-att-btn-row">
                    <button type="button" class="hr-att-btn" id="openAttendanceModal">{{ __('Add/Update Attendance Record') }}</button>
                    <button type="button" class="hr-att-btn" id="openAttendanceImportModal">{{ __('Import Excel') }}</button>
                    <button type="button" class="hr-att-btn" id="openBiometricGuideModal">{{ __('Biometric setup guide') }}</button>
                </div>
            </div>
            <form method="get" action="{{ route('hr.attendance.index') }}" class="hr-att-inline">
                <label>
                    <span class="hr-att-label">{{ __('Month') }}</span>
                    <input class="hr-att-input" type="month" name="month" value="{{ $attendanceMonth ?? now()->format('Y-m') }}">
                </label>
                <button class="hr-att-btn" type="submit">{{ __('Load') }}</button>
            </form>
            @if($summaryRows->isEmpty())
                <p class="hr-att-empty">{{ __('No employees found to summarize attendance.') }}</p>
            @else
                <div class="hr-att-table-wrap">
                    <table class="hr-att-table">
                        <thead>
                            <tr>
                                <th>{{ __('Employee') }}</th>
                                <th>{{ __('Present') }}</th>
                                <th>{{ __('Half day') }}</th>
                                <th>{{ __('Paid leave') }}</th>
                                <th>{{ __('Unpaid leave') }}</th>
                                <th>{{ __('Absent') }}</th>
                                <th>{{ __('Worked (hrs)') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($summaryRows as $row)
                                <tr>
                                    <td>{{ $row['employee']->full_name }} <span class="muted">({{ $row['employee']->employee_id }})</span></td>
                                    <td>{{ number_format((float) $row['present_days'], 2) }}</td>
                                    <td>{{ number_format((float) $row['half_days'], 2) }}</td>
                                    <td>{{ number_format((float) $row['paid_leave_days'], 2) }}</td>
                                    <td>{{ number_format((float) $row['unpaid_leave_days'], 2) }}</td>
                                    <td>{{ number_format((float) $row['absent_days'], 2) }}</td>
                                    <td>{{ number_format(((int) $row['worked_minutes']) / 60, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>

    <section class="hr-att-card" style="margin-top:14px;">
        <h2 class="hr-att-card__h">{{ __('Recent attendance records') }}</h2>
        @if($recentRows->isEmpty())
            <p class="hr-att-empty">{{ __('No attendance records yet.') }}</p>
        @else
            <div class="hr-att-table-wrap">
                <table class="hr-att-table">
                    <thead>
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Employee') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Worked mins') }}</th>
                            <th>{{ __('Source') }}</th>
                            <th>{{ __('Note') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentRows as $record)
                            <tr>
                                <td>{{ $record->work_date?->format('Y-m-d') ?? '—' }}</td>
                                <td>{{ $record->employee?->full_name ?? '—' }}</td>
                                <td><span class="hr-att-pill hr-att-pill--{{ $record->status }}">{{ str_replace('_', ' ', ucfirst($record->status)) }}</span></td>
                                <td>{{ $record->worked_minutes !== null ? number_format((int) $record->worked_minutes) : '—' }}</td>
                                <td>{{ ucfirst((string) $record->source) }}</td>
                                <td>{{ $record->notes ? \Illuminate\Support\Str::limit($record->notes, 80) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>

<div class="hr-att-modal" id="attendanceModal" hidden>
    <div class="hr-att-modal__backdrop" data-attendance-modal-close></div>
    <div class="hr-att-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="attendanceModalTitle">
        <div class="hr-att-modal__head">
            <h2 class="hr-att-modal__title" id="attendanceModalTitle">{{ __('Add or update record') }}</h2>
            <button type="button" class="hr-att-modal__close" data-attendance-modal-close>{{ __('Close') }}</button>
        </div>
        <form method="post" action="{{ route('hr.attendance.upsert') }}" class="hr-att-form">
            @csrf
            <label>
                <span class="hr-att-label">{{ __('Employee') }}</span>
                <select class="hr-att-input" name="employee_id" required>
                    <option value="">{{ __('Choose…') }}</option>
                    @foreach(($employees ?? collect()) as $employee)
                        <option value="{{ $employee->id }}" @selected((string) old('employee_id') === (string) $employee->id)>
                            {{ $employee->full_name }} ({{ $employee->employee_id }})
                        </option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="hr-att-label">{{ __('Work date') }}</span>
                <input class="hr-att-input" type="date" name="work_date" value="{{ old('work_date', now()->toDateString()) }}" required>
            </label>
            <label>
                <span class="hr-att-label">{{ __('Status') }}</span>
                <select class="hr-att-input" name="status" required>
                    @foreach($statusOptions as $statusKey => $statusLabel)
                        <option value="{{ $statusKey }}" @selected((string) old('status', \Modules\HRManagement\Models\AttendanceRecord::STATUS_PRESENT) === (string) $statusKey)>{{ $statusLabel }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="hr-att-label">{{ __('Worked minutes') }}</span>
                <input class="hr-att-input" type="number" min="0" max="1440" name="worked_minutes" value="{{ old('worked_minutes') }}" placeholder="{{ __('Optional') }}">
            </label>
            <label>
                <span class="hr-att-label">{{ __('Notes') }}</span>
                <textarea class="hr-att-input" name="notes" rows="3" maxlength="5000" placeholder="{{ __('Optional') }}">{{ old('notes') }}</textarea>
            </label>
            <button type="submit" class="hr-att-btn">{{ __('Save attendance') }}</button>
        </form>
    </div>
</div>

<div class="hr-att-modal" id="attendanceImportModal" hidden>
    <div class="hr-att-modal__backdrop" data-attendance-import-close></div>
    <div class="hr-att-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="attendanceImportModalTitle">
        <div class="hr-att-modal__head">
            <h2 class="hr-att-modal__title" id="attendanceImportModalTitle">{{ __('Import attendance from Excel') }}</h2>
            <button type="button" class="hr-att-modal__close" data-attendance-import-close>{{ __('Close') }}</button>
        </div>
        <form method="post" action="{{ route('hr.attendance.import-excel') }}" class="hr-att-form" enctype="multipart/form-data">
            @csrf
            <label>
                <span class="hr-att-label">{{ __('Attendance file') }}</span>
                <input class="hr-att-input" type="file" name="attendance_file" accept=".xlsx,.xls,.csv" required>
            </label>
            <p class="muted" style="margin:0;font-size:12px;line-height:1.5;">
                {{ __('Required columns: employee_code (or employee_pk), work_date, status. Optional: worked_minutes, check_in_at, check_out_at, notes.') }}
            </p>
            <button type="submit" class="hr-att-btn">{{ __('Import sheet') }}</button>
        </form>
    </div>
</div>

<div class="hr-att-modal" id="biometricGuideModal" hidden>
    <div class="hr-att-modal__backdrop" data-biometric-guide-close></div>
    <div class="hr-att-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="biometricGuideModalTitle">
        <div class="hr-att-modal__head">
            <h2 class="hr-att-modal__title" id="biometricGuideModalTitle">{{ __('Biometric attendance implementation guide') }}</h2>
            <button type="button" class="hr-att-modal__close" data-biometric-guide-close>{{ __('Close') }}</button>
        </div>

        <ol class="hr-att-guide">
            <li>{{ __('Step 1: Connect and sync your fingerprint device with your SociBiz server (through your device middleware or connector app).') }}</li>
            <li>{{ __('Step 2: Assign each fingerprint device user code to the correct employee in SociBiz (so the system knows whose attendance the punch belongs to).') }}</li>
            <li>{{ __('Step 3: Make sure the device integration uses the correct authentication/integration key configured for your business in SociBiz.') }}</li>
            <li>{{ __('Step 4: Send a small test: one employee punches “IN” and “OUT” for a single day.') }}</li>
            <li>{{ __('Step 5: Open Attendance in SociBiz and check the “Recent attendance records” + monthly summary for that date.') }}</li>
            <li>{{ __('Step 6 (if something is missing): verify the employee code on the device matches the employee mapping in SociBiz, and confirm the device date/time is correct.') }}</li>
            <li>{{ __('Step 7: Once the test works, schedule regular syncing (for example every few minutes) so all punches are captured automatically.') }}</li>
        </ol>
        <p class="hr-att-guide__note">{{ __('Tip: if the same punch is sent twice, SociBiz will ignore duplicates automatically as long as your device connector provides a consistent unique event reference.') }}</p>
    </div>
</div>

<script>
    (function () {
        const setBodyScroll = () => {
            const hasOpenModal = Array.from(document.querySelectorAll('.hr-att-modal')).some((el) => !el.hidden);
            document.body.style.overflow = hasOpenModal ? 'hidden' : '';
        };

        const initModal = (modalId, openBtnId, closeAttr) => {
            const modal = document.getElementById(modalId);
            const openBtn = document.getElementById(openBtnId);
            if (!modal || !openBtn) {
                return null;
            }
            const closeTargets = modal.querySelectorAll(`[${closeAttr}]`);

            const openModal = () => {
                modal.hidden = false;
                setBodyScroll();
            };

            const closeModal = () => {
                modal.hidden = true;
                setBodyScroll();
            };

            openBtn.addEventListener('click', openModal);
            closeTargets.forEach((el) => el.addEventListener('click', closeModal));

            return { modal, openModal, closeModal };
        };

        const attendanceModal = initModal('attendanceModal', 'openAttendanceModal', 'data-attendance-modal-close');
        const attendanceImportModal = initModal('attendanceImportModal', 'openAttendanceImportModal', 'data-attendance-import-close');
        const biometricGuideModal = initModal('biometricGuideModal', 'openBiometricGuideModal', 'data-biometric-guide-close');

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') {
                return;
            }
            if (attendanceModal && !attendanceModal.modal.hidden) {
                attendanceModal.closeModal();
            }
            if (biometricGuideModal && !biometricGuideModal.modal.hidden) {
                biometricGuideModal.closeModal();
            }
            if (attendanceImportModal && !attendanceImportModal.modal.hidden) {
                attendanceImportModal.closeModal();
            }
        });

        if (!attendanceModal) {
            return;
        }

        const openAttendanceModal = () => {
            attendanceModal.openModal();
            document.body.style.overflow = 'hidden';
        };

        @if($errors->any())
            @if($errors->has('attendance_file'))
                if (attendanceImportModal) {
                    attendanceImportModal.openModal();
                }
            @else
                openAttendanceModal();
            @endif
        @endif
    })();
</script>
@endsection
