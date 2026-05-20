@php
    $billCurrency = get_settings('business.currency', '', $business ?? null) ?: '';
@endphp

@if(($bills ?? collect())->isEmpty())
    <p class="muted" style="margin:0;line-height:1.5;font-size:12px;">{{ __('No bills are assigned to this modification yet.') }}</p>
    <p class="muted" style="margin-top:10px;line-height:1.5;font-size:12px;">{{ __('When you create or edit a bill, choose Assignment type “Modification” and select this modification.') }}</p>
@else
    <div style="overflow:auto;border:1px solid var(--border);border-radius:12px;">
        <table style="width:100%;border-collapse:collapse;min-width:640px;">
            <thead>
            <tr style="background:color-mix(in srgb,var(--card) 96%,transparent);">
                <th style="text-align:left;padding:10px;border-bottom:1px solid var(--border);font-size:12px;">{{ __('Bill') }}</th>
                <th style="text-align:left;padding:10px;border-bottom:1px solid var(--border);font-size:12px;">{{ __('Category') }}</th>
                <th style="text-align:left;padding:10px;border-bottom:1px solid var(--border);font-size:12px;">{{ __('Payment') }}</th>
                <th style="text-align:right;padding:10px;border-bottom:1px solid var(--border);font-size:12px;">{{ __('Amount') }}</th>
                <th style="text-align:left;padding:10px;border-bottom:1px solid var(--border);font-size:12px;">{{ __('Due') }}</th>
                <th style="text-align:right;padding:10px;border-bottom:1px solid var(--border);font-size:12px;"></th>
            </tr>
            </thead>
            <tbody>
            @foreach($bills as $bill)
                @php
                    $cat = $bill->bill_category === \Modules\Account\Models\Bill::CATEGORY_OTHER
                        ? ($bill->bill_category_other ?: __('Other'))
                        : ucfirst(str_replace('_', ' ', (string) $bill->bill_category));
                @endphp
                <tr>
                    <td style="padding:9px 10px;border-bottom:1px solid color-mix(in srgb,var(--border) 70%,transparent);font-weight:600;font-size:12px;">{{ $bill->name }}</td>
                    <td style="padding:9px 10px;border-bottom:1px solid color-mix(in srgb,var(--border) 70%,transparent);font-size:12px;">{{ $cat }}</td>
                    <td style="padding:9px 10px;border-bottom:1px solid color-mix(in srgb,var(--border) 70%,transparent);font-size:12px;">{{ str_replace('_', ' ', ucfirst((string) $bill->payment_mode)) }}</td>
                    <td style="padding:9px 10px;border-bottom:1px solid color-mix(in srgb,var(--border) 70%,transparent);font-size:12px;text-align:right;font-variant-numeric:tabular-nums;">
                        {{ trim(($billCurrency !== '' ? $billCurrency.' ' : '').number_format((float) $bill->recurring_cost, 2)) }}
                    </td>
                    <td style="padding:9px 10px;border-bottom:1px solid color-mix(in srgb,var(--border) 70%,transparent);font-size:12px;">{{ $bill->due_date?->format('Y-m-d') ?? '—' }}</td>
                    <td style="padding:9px 10px;border-bottom:1px solid color-mix(in srgb,var(--border) 70%,transparent);font-size:12px;text-align:right;">
                        <a href="{{ route('account.bills.show', $bill) }}" style="padding:7px 10px;font-size:12px;border-radius:8px;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-block;">{{ __('Open') }}</a>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @if(method_exists($bills, 'links'))
        <div style="margin-top:12px;">
            {{ $bills->withQueryString()->links() }}
        </div>
    @endif
@endif
