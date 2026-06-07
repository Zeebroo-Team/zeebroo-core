@extends('theme::layouts.app', ['title' => 'Transactions', 'heading' => 'Transactions'])

@section('content')
<div class="tx-page">
    <style>
        .tx-page{max-width:none;width:100%;box-sizing:border-box;}
        .tx-card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:20px 22px;}
        .tx-lead{margin:0 0 12px;font-size:13px;line-height:1.45;color:var(--muted);max-width:72ch;}
        .tx-muted{font-size:12px;color:var(--muted);margin-top:10px;line-height:1.4;}
        .tx-empty{text-align:center;padding:28px 18px;border:1px dashed color-mix(in srgb,var(--primary) 22%,var(--border));border-radius:12px;background:color-mix(in srgb,var(--primary) 5%,transparent);}
        .tx-empty__ico{width:44px;height:44px;margin:0 auto 10px;display:grid;place-items:center;border-radius:12px;background:color-mix(in srgb,var(--primary) 12%,transparent);color:var(--primary);font-size:17px;}
        .tx-empty h2{margin:0;font-size:14px;font-weight:700;color:var(--text);}
        .tx-empty p{margin:6px 0 0;font-size:12px;color:var(--muted);max-width:44ch;margin-inline:auto;line-height:1.45;}
        .tx-wrap{overflow:auto;border:1px solid var(--border);border-radius:10px;margin-top:8px;}
        .tx-table{width:100%;border-collapse:collapse;font-size:12px;min-width:820px;}
        .tx-table th{text-align:left;padding:8px 10px;background:color-mix(in srgb,var(--card) 92%,transparent);font-size:10px;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);border-bottom:1px solid var(--border);white-space:nowrap;}
        .tx-table td{padding:9px 10px;border-bottom:1px solid color-mix(in srgb,var(--border) 80%,transparent);vertical-align:top;}
        .tx-table tbody tr:last-child td{border-bottom:none;}
        .tx-amt{font-weight:700;font-variant-numeric:tabular-nums;color:color-mix(in srgb,var(--primary) 40%,var(--text));}
        .tx-pagination{margin-top:12px;display:flex;justify-content:flex-end;}
        .tx-badge{display:inline-block;font-size:10px;font-weight:600;padding:2px 6px;border-radius:6px;background:color-mix(in srgb,var(--primary) 10%,transparent);border:1px solid color-mix(in srgb,var(--primary) 24%,var(--border));}
    </style>

    @if(!$business)
        <div class="tx-card">
            <p class="tx-lead">Select a business from the navbar to view ledger entries.</p>
        </div>
    @else
        <div class="tx-card">
            @if($transactions instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $transactions->isEmpty())
                <div class="tx-empty">
                    <div class="tx-empty__ico" aria-hidden="true"><i class="fa fa-receipt"></i></div>
                    <h2>No ledger transactions yet</h2>
                    <p>Add loans with first installment date, cadence, and a deduct-from account — entries appear after the next scheduled run.</p>
                </div>
            @elseif($transactions instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                @php($cadenceLabels = \Modules\Account\Models\Loan::recurringTypes())
                <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:8px;flex-wrap:wrap;">
                    <span class="tx-muted" style="margin:0;">
                        {{ $transactions->total() }} transaction{{ $transactions->total() === 1 ? '' : 's' }}
                        @if($transactions->hasPages())
                            &nbsp;·&nbsp; page {{ $transactions->currentPage() }} of {{ $transactions->lastPage() }}
                        @endif
                    </span>
                </div>
                <div class="tx-wrap">
                    <table class="tx-table">
                        <thead>
                            <tr>
                                <th>Logged at</th>
                                <th>Occurred on</th>
                                <th>Type</th>
                                <th>Source</th>
                                <th>Lender bank</th>
                                <th>Period</th>
                                <th>Cadence</th>
                                <th>Deduct account</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($transactions as $row)
                                <tr>
                                    <td>{{ $row->created_at?->timezone(config('app.timezone'))->format('M j, Y · H:i') ?? '—' }}</td>
                                    <td>{{ $row->occurrence_date?->format('M j, Y') ?? '—' }}</td>
                                    <td><span class="tx-badge">{{ $row->sourceKindLabel() }}</span></td>
                                    <td>{{ $row->sourceTitle() }}</td>
                                    <td>{{ $row->counterpartyBankName() ?? '—' }}</td>
                                    <td>@if($row->period_number){{ $row->period_number }}@if($row->periods_total_snapshot) / {{ $row->periods_total_snapshot }}@endif @else — @endif</td>
                                    <td><span class="tx-badge">{{ $cadenceLabels[$row->cadence_snapshot ?? ''] ?? ($row->cadence_snapshot ?: '—') }}</span></td>
                                    <td>{{ $row->deductAccount?->deductOptionLabel() ?? '—' }}</td>
                                    <td class="tx-amt">@if($row->currency)<span style="opacity:.72;font-weight:700;font-size:10px;">{{ $row->currency }}</span> @endif{{ number_format((float) $row->amount, 2, '.', ',') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($transactions->hasPages())
                <div class="tx-pagination">
                    {{ $transactions->withQueryString()->links() }}
                </div>
                @endif
            @else
                <div class="tx-empty"><p>No data.</p></div>
            @endif

            <p class="tx-muted"><i class="fa fa-clock"></i> Scheduler: Transaction module registers <strong>daily 00:10</strong> — ensure your server cron runs <code style="font-size:11px;">php artisan schedule:run</code> every minute.</p>
        </div>
    @endif
</div>
@endsection
