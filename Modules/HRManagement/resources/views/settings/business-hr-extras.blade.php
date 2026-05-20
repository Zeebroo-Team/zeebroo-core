@php
    /** @var \Modules\Business\Models\Business $business */
    /** @var \Illuminate\Support\Collection<int, \Modules\HRManagement\Models\HrBusinessHoliday> $holidays */
    $holidays = $holidays ?? collect();
@endphp

<div style="margin-top:18px;padding-top:16px;border-top:1px solid var(--border);">
    <h2 style="margin:0 0 8px;font-size:15px;font-weight:800;">{{ __('Allowance catalogue') }}</h2>
    <p class="muted" style="margin:0 0 12px;font-size:13px;line-height:1.45;max-width:72ch;">
        {{ __('Allowance types are stored per business (transport, meal, etc.) and used when creating employees.') }}
        @if(Route::has('hr.allowance-types.index'))
            <a href="{{ route('hr.allowance-types.index') }}" style="color:var(--primary);font-weight:600;">{{ __('Open allowance types') }}</a>
        @endif
        @if(Route::has('hr.employees.index'))
            · <a href="{{ route('hr.employees.index') }}" style="color:var(--primary);font-weight:600;">{{ __('Employees') }}</a>
        @endif
    </p>
</div>

<div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border);">
    <h2 style="margin:0 0 8px;font-size:15px;font-weight:800;">{{ __('Company holidays') }}</h2>
    <p class="muted" style="margin:0 0 12px;font-size:13px;line-height:1.45;max-width:72ch;">
        {{ __('Public or company-wide days off. One entry per calendar date.') }}
    </p>

    @if(! ($hrPayrollOptedIn ?? false))
        <p class="muted" style="margin:0;font-size:13px;line-height:1.45;">
            {{ __('Complete HR payroll setup to add or remove holidays from here.') }}
            @if(Route::has('hr.onboarding'))
                <a href="{{ route('hr.onboarding') }}" style="color:var(--primary);font-weight:600;">{{ __('HR setup') }}</a>
            @endif
        </p>
    @else
        @if($holidays->isNotEmpty())
            <div style="border:1px solid var(--border);border-radius:10px;overflow:auto;margin-bottom:12px;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;min-width:320px;">
                    <thead>
                        <tr style="background:color-mix(in srgb,var(--card) 92%,transparent);">
                            <th style="text-align:left;padding:8px 10px;border-bottom:1px solid var(--border);font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);">{{ __('Date') }}</th>
                            <th style="text-align:left;padding:8px 10px;border-bottom:1px solid var(--border);font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);">{{ __('Name') }}</th>
                            <th style="text-align:right;padding:8px 10px;border-bottom:1px solid var(--border);font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($holidays as $h)
                            <tr>
                                <td style="padding:8px 10px;border-bottom:1px solid color-mix(in srgb,var(--border) 80%,transparent);white-space:nowrap;">{{ $h->holiday_date?->format('Y-m-d') }}</td>
                                <td style="padding:8px 10px;border-bottom:1px solid color-mix(in srgb,var(--border) 80%,transparent);">{{ $h->name }}</td>
                                <td style="padding:8px 10px;border-bottom:1px solid color-mix(in srgb,var(--border) 80%,transparent);text-align:right;">
                                    <form method="post" action="{{ route('hr.settings.holidays.destroy', $h) }}" style="margin:0;display:inline;" onsubmit="return confirm(@json(__('Remove this holiday?')));">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" style="padding:4px 8px;font-size:11px;border-radius:6px;border:1px solid color-mix(in srgb,#ef4444 40%,var(--border));background:transparent;color:#f97373;cursor:pointer;">{{ __('Remove') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <form method="post" action="{{ route('hr.settings.holidays.store') }}" style="display:grid;gap:10px;max-width:520px;">
            @csrf
            <div>
                <label for="hr-holiday-name" style="display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:4px;">{{ __('Holiday name') }} <span style="color:#f87171;">*</span></label>
                <input type="text" name="name" id="hr-holiday-name" value="{{ old('name') }}" required maxlength="255" style="width:100%;box-sizing:border-box;padding:8px 10px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);font-size:13px;">
            </div>
            <div>
                <label for="hr-holiday-date" style="display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:4px;">{{ __('Date') }} <span style="color:#f87171;">*</span></label>
                <input type="date" name="holiday_date" id="hr-holiday-date" value="{{ old('holiday_date') }}" required style="width:100%;max-width:220px;box-sizing:border-box;padding:8px 10px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);font-size:13px;">
            </div>
            <div>
                <label for="hr-holiday-notes" style="display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:4px;">{{ __('Notes') }}</label>
                <input type="text" name="notes" id="hr-holiday-notes" value="{{ old('notes') }}" maxlength="500" style="width:100%;box-sizing:border-box;padding:8px 10px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);font-size:13px;">
            </div>
            <div style="display:flex;justify-content:flex-end;">
                <button type="submit" class="linkbtn" style="padding:7px 14px;font-size:13px;">{{ __('Add holiday') }}</button>
            </div>
        </form>
    @endif
</div>
