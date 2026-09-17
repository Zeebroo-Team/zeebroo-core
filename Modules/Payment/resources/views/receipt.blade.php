@php
  $statusMeta = [
    'succeeded' => ['label' => 'Paid', 'verb' => 'paid'],
    'refunded'  => ['label' => 'Refunded', 'verb' => 'refunded'],
  ];
  $meta = $statusMeta[$payment->payment_status] ?? $statusMeta['succeeded'];

  $receiptNo = 'RCPT-' . str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT);
  $planName = $payment->package?->name ?? 'Subscription';
  $planDescription = $payment->package?->description;
  $billingCycle = ucfirst($payment->billing_cycle ?? 'Monthly');
  $gateway = $payment->gateway ? ucfirst($payment->gateway) : 'Card';
  $owner = $business->user;

  $issuedAt = $payment->paid_at ?? $payment->created_at;
  $paidAt = $payment->paid_at;
  $periodStart = $paidAt ?? $payment->created_at;
  $periodEnd = $payment->current_period_end;

  $currency = strtoupper($payment->currency ?? 'USD');
  $symbol = $currency === 'USD' ? '$' : '';
  $amountText = $symbol . number_format((float) $payment->amount, 2) . ' ' . $currency;

  // This receipt is issued by the platform for the business's subscription
  // payment — the business (and its owner) is the paying customer, not the seller.
  $issuerName = config('app.name', 'Zeebroo') . ' POS';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $receiptNo }}</title>
<style>
  @page { margin: 54px 50px; }
  body { font-family: DejaVu Sans, sans-serif; color: #111111; font-size: 12px; margin: 0; }

  table { border-collapse: collapse; width: 100%; }
  td { vertical-align: top; }

  .label { color: #6b7280; font-size: 9px; text-transform: uppercase; letter-spacing: .6px; font-weight: 700; }
  .value { color: #111111; }

  /* ---- letterhead ---- */
  table.letterhead td.brand { }
  table.letterhead .business-name { font-size: 17px; font-weight: 700; color: #111111; }
  table.letterhead .business-sub { font-size: 10px; color: #6b7280; margin-top: 3px; }
  table.letterhead td.title { text-align: right; }
  table.letterhead .doc-title { font-size: 26px; font-weight: 700; color: #111111; letter-spacing: .5px; }
  table.letterhead .doc-number { font-size: 10.5px; color: #6b7280; margin-top: 4px; }

  .badge { display: inline-block; margin-top: 8px; padding: 4px 11px; border: 1px solid #111111; color: #111111; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; }

  .rule { border-top: 1px solid #111111; margin: 18px 0 20px; }
  .rule-light { border-top: 1px solid #e0e0e0; margin: 16px 0; }

  /* ---- meta / parties ---- */
  table.info-grid td { width: 33.33%; padding-right: 20px; }
  table.info-grid .info-value { font-size: 12px; color: #111111; font-weight: 700; margin-top: 3px; }

  table.parties { margin-top: 22px; }
  table.parties td { width: 50%; padding-right: 24px; font-size: 11.5px; line-height: 1.65; }
  table.parties .party-heading { font-size: 9px; text-transform: uppercase; letter-spacing: .6px; color: #6b7280; font-weight: 700; margin-bottom: 6px; }
  table.parties .party-name { color: #111111; font-weight: 700; font-size: 12.5px; }
  table.parties .party-line { color: #4b5563; }

  /* ---- summary strip ---- */
  .summary-strip { margin-top: 24px; padding: 16px 18px; background: #f7f7f7; border: 1px solid #e5e5e5; }
  .summary-strip table td { vertical-align: middle; }
  .summary-strip .summary-label { font-size: 9px; text-transform: uppercase; letter-spacing: .6px; color: #6b7280; font-weight: 700; }
  .summary-strip .summary-amount { font-size: 20px; font-weight: 700; color: #111111; margin-top: 3px; }
  .summary-strip .summary-date { font-size: 10.5px; color: #6b7280; margin-top: 3px; }
  .summary-strip .summary-badge-cell { text-align: right; }

  /* ---- items table ---- */
  table.items { margin-top: 26px; }
  table.items thead td { border-top: 1px solid #111111; border-bottom: 1px solid #111111; color: #111111; font-size: 9.5px; text-transform: uppercase; letter-spacing: .5px; font-weight: 700; padding: 8px 0; }
  table.items tbody td { padding: 13px 0; border-bottom: 1px solid #e5e5e5; font-size: 12px; }
  table.items .item-title { font-weight: 700; color: #111111; }
  table.items .item-sub { color: #6b7280; font-size: 10.5px; margin-top: 3px; }
  table.items td.num { text-align: right; white-space: nowrap; }
  table.items col.desc { width: 52%; }
  table.items col.qty { width: 10%; }
  table.items col.unit { width: 18%; }
  table.items col.amt { width: 20%; }

  /* ---- totals ---- */
  table.totals { margin-top: 4px; }
  table.totals td { padding: 8px 0; font-size: 11.5px; }
  table.totals td.spacer { width: 58%; }
  table.totals td.label { color: #6b7280; text-align: right; padding-right: 22px; }
  table.totals td.value { text-align: right; width: 120px; font-weight: 600; color: #111111; }
  table.totals tr.grand td { border-top: 1.5px solid #111111; padding-top: 12px; font-size: 14px; font-weight: 700; color: #111111; }

  .footer { margin-top: 52px; padding-top: 16px; border-top: 1px solid #e5e5e5; }
  .footer .thanks { font-size: 11px; font-weight: 700; color: #111111; margin-bottom: 3px; }
  .footer .fine { color: #9ca3af; font-size: 9.5px; }
</style>
</head>
<body>

  <table class="letterhead">
    <tr>
      <td class="brand" style="width:55%">
        <div class="business-name">{{ $issuerName }}</div>
        <div class="business-sub">Subscription billing</div>
      </td>
      <td class="title" style="width:45%">
        <div class="doc-title">RECEIPT</div>
        <div class="doc-number">{{ $receiptNo }}</div>
        <div class="badge">{{ $meta['label'] }}</div>
      </td>
    </tr>
  </table>

  <div class="rule"></div>

  <table class="info-grid">
    <tr>
      <td>
        <div class="label">Date of issue</div>
        <div class="info-value">{{ $issuedAt?->format('F j, Y') ?? '—' }}</div>
      </td>
      <td>
        <div class="label">Date paid</div>
        <div class="info-value">{{ $paidAt?->format('F j, Y') ?? '—' }}</div>
      </td>
      <td>
        <div class="label">Payment method</div>
        <div class="info-value">{{ $gateway }}</div>
      </td>
    </tr>
  </table>

  <table class="parties">
    <tr>
      <td>
        <div class="party-heading">From</div>
        <div class="party-name">{{ $issuerName }}</div>
      </td>
      <td>
        <div class="party-heading">Bill to</div>
        <div class="party-name">{{ $business->name }}</div>
        @if($owner?->name)
          <div class="party-line">{{ $owner->name }}</div>
        @endif
        @if($owner?->email)
          <div class="party-line">{{ $owner->email }}</div>
        @endif
      </td>
    </tr>
  </table>

  <div class="summary-strip">
    <table>
      <tr>
        <td>
          <div class="summary-label">Amount {{ strtolower($meta['label']) }}</div>
          <div class="summary-amount">{{ $amountText }}</div>
          @if($paidAt)
            <div class="summary-date">{{ $meta['verb'] === 'refunded' ? 'Refunded' : 'Paid' }} on {{ $paidAt->format('F j, Y \a\t g:i A') }}</div>
          @endif
        </td>
        <td class="summary-badge-cell">
          <div class="label">Billing cycle</div>
          <div class="info-value">{{ $billingCycle }}</div>
        </td>
      </tr>
    </table>
  </div>

  <table class="items">
    <colgroup>
      <col class="desc"><col class="qty"><col class="unit"><col class="amt">
    </colgroup>
    <thead>
      <tr>
        <td>Description</td>
        <td class="num">Qty</td>
        <td class="num">Unit price</td>
        <td class="num">Amount</td>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>
          <div class="item-title">{{ $planName }} — {{ $billingCycle }} plan</div>
          @if($periodStart && $periodEnd)
            <div class="item-sub">{{ $periodStart->format('M j') }} – {{ $periodEnd->format('M j, Y') }}</div>
          @endif
          @if($planDescription)
            <div class="item-sub">{{ $planDescription }}</div>
          @endif
        </td>
        <td class="num">1</td>
        <td class="num">{{ $amountText }}</td>
        <td class="num">{{ $amountText }}</td>
      </tr>
    </tbody>
  </table>

  <table class="totals">
    <tr>
      <td class="spacer"></td>
      <td class="label">Subtotal</td>
      <td class="value">{{ $amountText }}</td>
    </tr>
    <tr>
      <td class="spacer"></td>
      <td class="label">Total</td>
      <td class="value">{{ $amountText }}</td>
    </tr>
    <tr class="grand">
      <td class="spacer"></td>
      <td class="label" style="color:#111111;font-weight:700;">{{ $meta['label'] === 'Refunded' ? 'Amount refunded' : 'Amount paid' }}</td>
      <td class="value">{{ $amountText }}</td>
    </tr>
  </table>

  <div class="footer">
    <div class="thanks">Thank you for your business.</div>
    <div class="fine">This receipt was generated by Zeebroo POS and serves as proof of payment. Keep it for your records.</div>
  </div>

</body>
</html>
