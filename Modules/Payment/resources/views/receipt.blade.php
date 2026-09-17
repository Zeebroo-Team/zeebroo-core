@php
  $statusMeta = [
    'succeeded' => ['label' => 'Paid', 'bg' => '#dcfce7', 'fg' => '#15803d'],
    'refunded'  => ['label' => 'Refunded', 'bg' => '#fee2e2', 'fg' => '#dc2626'],
  ];
  $meta = $statusMeta[$payment->payment_status] ?? $statusMeta['succeeded'];
  $receiptNo = str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT);
  $planName = $payment->package?->name ?? 'Subscription';
  $planDescription = $payment->package?->description;
  $billingCycle = ucfirst($payment->billing_cycle ?? 'Monthly');
  $gateway = $payment->gateway ? ucfirst($payment->gateway) : 'Card';
  $owner = $business->user;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Receipt #{{ $receiptNo }}</title>
<style>
  @page { margin: 0; }
  body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 12px; margin: 0; }

  .brand-bar { background: #4f46e5; color: #ffffff; padding: 22px 44px; }
  .brand-bar .brand-name { font-size: 15px; font-weight: 700; letter-spacing: .4px; }
  .brand-bar .brand-tag { font-size: 10px; color: #e0e7ff; margin-top: 2px; }

  .content { padding: 32px 44px 40px; }

  .header { width: 100%; margin-bottom: 26px; }
  .header td { vertical-align: top; }
  .header h1 { font-size: 19px; margin: 0 0 4px; color: #111827; }
  .header .muted { color: #6b7280; font-size: 11px; }
  .header .issued { color: #6b7280; font-size: 11px; margin-top: 6px; }

  .badge { display: inline-block; padding: 5px 14px; border-radius: 999px; font-size: 11px; font-weight: 700; text-transform: uppercase; background: {{ $meta['bg'] }}; color: {{ $meta['fg'] }}; }

  .cards { width: 100%; margin-bottom: 22px; }
  .card { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px 16px; }
  .card .card-label { font-size: 9px; text-transform: uppercase; letter-spacing: .5px; color: #9ca3af; font-weight: 700; margin-bottom: 6px; }
  .card .card-line { font-size: 12.5px; font-weight: 700; color: #111827; }
  .card .card-sub { font-size: 11px; color: #6b7280; margin-top: 2px; }

  .section-title { font-size: 10px; text-transform: uppercase; letter-spacing: .5px; color: #9ca3af; font-weight: 700; margin: 26px 0 8px; }

  table.items { width: 100%; border-collapse: collapse; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; }
  table.items thead td { background: #f3f4f6; font-size: 10px; text-transform: uppercase; letter-spacing: .4px; color: #6b7280; font-weight: 700; padding: 9px 14px; }
  table.items tbody td { padding: 12px 14px; border-top: 1px solid #e5e7eb; font-size: 12.5px; }
  table.items .item-title { font-weight: 700; color: #111827; }
  table.items .item-sub { color: #6b7280; font-size: 11px; margin-top: 2px; }
  table.items td.num { text-align: right; white-space: nowrap; }

  table.totals { width: 100%; margin-top: 4px; }
  table.totals td { padding: 7px 14px; font-size: 12px; }
  table.totals td.label { color: #6b7280; text-align: right; }
  table.totals td.value { text-align: right; width: 130px; font-weight: 600; }
  table.totals tr.grand td { border-top: 2px solid #111827; padding-top: 12px; font-size: 15px; font-weight: 700; color: #111827; }

  .footer { margin-top: 44px; padding-top: 16px; border-top: 1px solid #e5e7eb; text-align: center; }
  .footer .thanks { font-size: 12px; font-weight: 700; color: #111827; margin-bottom: 4px; }
  .footer .fine { color: #9ca3af; font-size: 10px; }
</style>
</head>
<body>

  <div class="brand-bar">
    <div class="brand-name">ZEEBROO POS</div>
    <div class="brand-tag">Payment Receipt</div>
  </div>

  <div class="content">

    <table class="header">
      <tr>
        <td>
          <h1>{{ $business->name }}</h1>
          <div class="muted">Receipt #{{ $receiptNo }}</div>
          <div class="issued">Issued {{ ($payment->paid_at ?? $payment->created_at)?->format('F j, Y') }}</div>
        </td>
        <td style="text-align:right">
          <span class="badge">{{ $meta['label'] }}</span>
        </td>
      </tr>
    </table>

    <table class="cards">
      <tr>
        <td style="width:50%;padding-right:8px">
          <div class="card">
            <div class="card-label">Billed To</div>
            <div class="card-line">{{ $business->name }}</div>
            @if($owner?->name)
              <div class="card-sub">{{ $owner->name }}</div>
            @endif
            @if($owner?->email)
              <div class="card-sub">{{ $owner->email }}</div>
            @endif
          </div>
        </td>
        <td style="width:50%;padding-left:8px">
          <div class="card">
            <div class="card-label">Payment Details</div>
            <div class="card-line">{{ $gateway }}</div>
            <div class="card-sub">Paid on {{ $payment->paid_at?->format('F j, Y \a\t g:i A') ?? '—' }}</div>
            <div class="card-sub">Billing cycle: {{ $billingCycle }}</div>
          </div>
        </td>
      </tr>
    </table>

    <div class="section-title">Summary</div>
    <table class="items">
      <thead>
        <tr>
          <td>Description</td>
          <td class="num">Amount</td>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>
            <div class="item-title">{{ $planName }} — {{ $billingCycle }} Plan</div>
            @if($planDescription)
              <div class="item-sub">{{ $planDescription }}</div>
            @endif
          </td>
          <td class="num">{{ number_format((float) $payment->amount, 2) }} {{ strtoupper($payment->currency) }}</td>
        </tr>
      </tbody>
    </table>

    <table class="totals">
      <tr class="grand">
        <td class="label">Total Paid</td>
        <td class="value">{{ number_format((float) $payment->amount, 2) }} {{ strtoupper($payment->currency) }}</td>
      </tr>
    </table>

    <div class="footer">
      <div class="thanks">Thank you for your business.</div>
      <div class="fine">This receipt was generated by Zeebroo POS and serves as proof of payment. Keep it for your records.</div>
    </div>

  </div>
</body>
</html>
