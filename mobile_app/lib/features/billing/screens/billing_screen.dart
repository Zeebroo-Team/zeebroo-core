import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:printing/printing.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/theme/app_theme.dart';

/// Billing & Payments — the business's Zeebroo subscription, its payment
/// history (Paid / Due & Upcoming) and receipts. Mirrors the desktop app's
/// billing modal using the same `/v1/pos/auth/payment/*` endpoints.
class BillingScreen extends StatefulWidget {
  const BillingScreen({super.key});

  @override
  State<BillingScreen> createState() => _BillingScreenState();
}

const _kDueStatuses = {'pending', 'failed', 'canceled'};

bool _isDue(Map<String, dynamic> p) => _kDueStatuses.contains(p['payment_status']);

class _BillingScreenState extends State<BillingScreen> with WidgetsBindingObserver {
  bool _loading = true;
  String? _error;
  Map<String, dynamic> _data = const {};
  List<Map<String, dynamic>> _items = [];
  bool? _showDue; // null until first load picks a default tab
  bool _updatingSubscription = false;

  /// Payment whose Stripe checkout was opened in the browser; re-checked when
  /// the user comes back to the app.
  int? _awaitingPaymentId;
  int? _startingCheckoutId;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _load();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && _awaitingPaymentId != null) {
      _checkPaymentStatus(_awaitingPaymentId!, quiet: true);
    }
  }

  Future<void> _load() async {
    setState(() {
      _error = null;
      if (_items.isEmpty) _loading = true;
    });
    try {
      final res = await ApiClient.instance.get(ApiEndpoints.paymentHistory, bypassCache: true);
      final raw = res.data;
      final data = raw is Map ? raw['data'] : null;
      _data = data is Map ? Map<String, dynamic>.from(data) : const {};
      _items = ((_data['items'] as List?) ?? const [])
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .toList();
      _showDue ??= _items.any(_isDue);
    } catch (e) {
      _error = apiErrorMessage(e);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _snack(String message) =>
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));

  Future<void> _changeCancellation({required bool cancel, required String? accessUntil}) async {
    final until = formatBillingDate(accessUntil);
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(cancel ? 'Cancel subscription?' : 'Keep your subscription?'),
        content: Text(
          cancel
              ? 'You’ll keep full access to Zeebroo until $until. After that your access ends and you won’t be charged again. You can undo this any time before then.'
              : 'Your subscription will continue and renew on $until as normal.',
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: Text(cancel ? 'Keep it' : 'Not now')),
          FilledButton(
            style: cancel ? FilledButton.styleFrom(backgroundColor: AppColors.error) : null,
            onPressed: () => Navigator.pop(ctx, true),
            child: Text(cancel ? 'Cancel subscription' : 'Keep subscription'),
          ),
        ],
      ),
    );
    if (ok != true || !mounted) return;

    setState(() => _updatingSubscription = true);
    try {
      await ApiClient.instance.post(cancel ? ApiEndpoints.subscriptionCancel : ApiEndpoints.subscriptionResume);
      if (!mounted) return;
      _snack(cancel ? 'Subscription cancelled — access until $until' : 'Subscription resumed');
      await _load();
    } catch (e) {
      if (mounted) _snack(apiErrorMessage(e));
    } finally {
      if (mounted) setState(() => _updatingSubscription = false);
    }
  }

  Future<void> _payNow(int id) async {
    setState(() => _startingCheckoutId = id);
    final opened = await startBillingCheckout(context, id);
    if (!mounted) return;
    setState(() {
      _startingCheckoutId = null;
      if (opened) _awaitingPaymentId = id;
    });
  }

  Future<void> _checkPaymentStatus(int id, {bool quiet = false}) async {
    final paid = await isBillingPaymentPaid(id);
    if (!mounted) return;
    if (paid) {
      setState(() => _awaitingPaymentId = null);
      _snack('Payment received — thank you!');
      await _load();
    } else if (!quiet) {
      _snack('Payment not confirmed yet — finish it in the browser, then check again.');
    }
  }

  Future<void> _openDetail(Map<String, dynamic> payment) async {
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => BillingPaymentDetailScreen(paymentId: (payment['id'] as num).toInt())),
    );
    if (changed == true) _load();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: AppColors.surface,
    appBar: AppBar(
      backgroundColor: AppColors.surface,
      foregroundColor: AppColors.textDark,
      elevation: 0,
      centerTitle: true,
      leading: IconButton(
        onPressed: () => Navigator.of(context).pop(),
        icon: const Icon(Icons.arrow_back_rounded),
      ),
      title: const Text('Billing', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 17)),
    ),
    body: _loading
        ? const Center(child: CircularProgressIndicator())
        : RefreshIndicator(
            onRefresh: _load,
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
              children: [
                if (_error != null) _ErrorBox(message: _error!, onRetry: _load),
                ..._buildSummary(),
                const SizedBox(height: 18),
                _buildTabs(),
                const SizedBox(height: 12),
                ..._buildList(),
              ],
            ),
          ),
  );

  List<Widget> _buildSummary() {
    final rawStatus = _data['subscription_status'] as String?;
    final nextRenewal = _data['next_renewal_at'] as String?;
    if (rawStatus == null && nextRenewal == null) return const [];

    final cancelling = _data['cancel_at_period_end'] == true;
    final accessUntil = (_data['access_until'] as String?) ?? nextRenewal;
    final canManage = _data['can_manage_subscription'] == true && rawStatus == 'active';
    final statusLabel = (rawStatus ?? 'unknown')
        .split('_')
        .map((w) => w.isEmpty ? w : w[0].toUpperCase() + w.substring(1))
        .join(' ');

    return [
      Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(color: const Color(0xFFF3F4F8), borderRadius: BorderRadius.circular(16)),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Subscription: ${cancelling ? 'Cancels on ${formatBillingDate(accessUntil)}' : statusLabel}',
              style: const TextStyle(fontSize: 15.5, fontWeight: FontWeight.w800, color: AppColors.textDark),
            ),
            const SizedBox(height: 4),
            if (cancelling)
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Padding(
                    padding: EdgeInsets.only(top: 1),
                    child: Icon(Icons.warning_amber_rounded, size: 16, color: AppColors.warning),
                  ),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      'You’ve cancelled your subscription. You can use Zeebroo until ${formatBillingDate(accessUntil)} — you won’t be charged again.',
                      style: const TextStyle(fontSize: 12.5, height: 1.4, color: AppColors.textMuted),
                    ),
                  ),
                ],
              )
            else if (nextRenewal != null)
              Text(
                'Next renewal on ${formatBillingDate(nextRenewal)}',
                style: const TextStyle(fontSize: 12.5, color: AppColors.textMuted),
              ),
            if (canManage) ...[
              const SizedBox(height: 14),
              SizedBox(
                width: double.infinity,
                child: cancelling
                    ? FilledButton.icon(
                        onPressed: _updatingSubscription
                            ? null
                            : () => _changeCancellation(cancel: false, accessUntil: accessUntil),
                        icon: const Icon(Icons.restart_alt_rounded, size: 18),
                        label: const Text('Keep subscription'),
                      )
                    : OutlinedButton.icon(
                        style: OutlinedButton.styleFrom(
                          foregroundColor: AppColors.error,
                          side: const BorderSide(color: AppColors.error),
                        ),
                        onPressed: _updatingSubscription
                            ? null
                            : () => _changeCancellation(cancel: true, accessUntil: accessUntil),
                        icon: const Icon(Icons.block_rounded, size: 18),
                        label: const Text('Cancel subscription', style: TextStyle(fontWeight: FontWeight.w700)),
                      ),
              ),
            ],
          ],
        ),
      ),
    ];
  }

  Widget _buildTabs() {
    final dueCount = _items.where(_isDue).length;
    final paidCount = _items.length - dueCount;
    final showDue = _showDue ?? false;
    return Container(
      decoration: const BoxDecoration(border: Border(bottom: BorderSide(color: Color(0xFFE5E7EB)))),
      child: Row(
        children: [
          _TabLabel(label: 'Paid', count: paidCount, selected: !showDue, onTap: () => setState(() => _showDue = false)),
          const SizedBox(width: 18),
          _TabLabel(label: 'Due & Upcoming', count: dueCount, selected: showDue, onTap: () => setState(() => _showDue = true)),
        ],
      ),
    );
  }

  List<Widget> _buildList() {
    final showDue = _showDue ?? false;
    final items = _items.where((p) => _isDue(p) == showDue).toList();
    if (items.isEmpty) {
      return [
        Padding(
          padding: const EdgeInsets.symmetric(vertical: 28),
          child: Center(
            child: Text(
              showDue ? 'Nothing due right now — you’re all settled.' : 'No paid payments yet.',
              style: const TextStyle(color: AppColors.textMuted, fontSize: 13),
            ),
          ),
        ),
      ];
    }
    return [
      for (final p in items) ...[
        _PaymentRow(
          payment: p,
          onTap: () => _openDetail(p),
          payState: !_isDue(p)
              ? null
              : _startingCheckoutId == p['id']
              ? _PayState.starting
              : _awaitingPaymentId == p['id']
              ? _PayState.awaiting
              : _PayState.idle,
          onPay: () => _payNow((p['id'] as num).toInt()),
          onCheck: () => _checkPaymentStatus((p['id'] as num).toInt()),
        ),
        const SizedBox(height: 10),
      ],
    ];
  }
}

// ─── Payment detail ────────────────────────────────────────────────────────

/// One payment's details with Pay Now (due) or Download Receipt (paid).
/// Pops `true` if the payment was settled here, so the list reloads.
class BillingPaymentDetailScreen extends StatefulWidget {
  const BillingPaymentDetailScreen({super.key, required this.paymentId});

  final int paymentId;

  @override
  State<BillingPaymentDetailScreen> createState() => _BillingPaymentDetailScreenState();
}

class _BillingPaymentDetailScreenState extends State<BillingPaymentDetailScreen> with WidgetsBindingObserver {
  bool _loading = true;
  String? _error;
  Map<String, dynamic> _p = const {};
  bool _startingCheckout = false;
  bool _awaitingCheckout = false;
  bool _downloading = false;
  bool _changed = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _load();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && _awaitingCheckout) _checkStatus(quiet: true);
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final res = await ApiClient.instance.get(ApiEndpoints.payment(widget.paymentId), bypassCache: true);
      final raw = res.data;
      final data = raw is Map ? raw['data'] : null;
      _p = data is Map ? Map<String, dynamic>.from(data) : const {};
    } catch (e) {
      _error = apiErrorMessage(e);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _snack(String message) =>
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));

  Future<void> _payNow() async {
    setState(() => _startingCheckout = true);
    final opened = await startBillingCheckout(context, widget.paymentId);
    if (!mounted) return;
    setState(() {
      _startingCheckout = false;
      if (opened) _awaitingCheckout = true;
    });
  }

  Future<void> _checkStatus({bool quiet = false}) async {
    final paid = await isBillingPaymentPaid(widget.paymentId);
    if (!mounted) return;
    if (paid) {
      setState(() {
        _awaitingCheckout = false;
        _changed = true;
      });
      _snack('Payment received — thank you!');
      await _load();
    } else if (!quiet) {
      _snack('Payment not confirmed yet — finish it in the browser, then check again.');
    }
  }

  Future<void> _downloadReceipt() async {
    setState(() => _downloading = true);
    try {
      final bytes = await ApiClient.instance.getBytes(ApiEndpoints.paymentReceipt(widget.paymentId));
      await Printing.sharePdf(bytes: Uint8List.fromList(bytes), filename: 'receipt-${widget.paymentId}.pdf');
    } catch (e) {
      if (mounted) _snack('Could not download the receipt. ${apiErrorMessage(e)}');
    } finally {
      if (mounted) setState(() => _downloading = false);
    }
  }

  @override
  Widget build(BuildContext context) => PopScope(
    canPop: false,
    onPopInvokedWithResult: (didPop, _) {
      if (!didPop) Navigator.of(context).pop(_changed);
    },
    child: Scaffold(
      backgroundColor: AppColors.surface,
      appBar: AppBar(
        backgroundColor: AppColors.surface,
        foregroundColor: AppColors.textDark,
        elevation: 0,
        centerTitle: true,
        leading: IconButton(
          onPressed: () => Navigator.of(context).pop(_changed),
          icon: const Icon(Icons.arrow_back_rounded),
        ),
        title: const Text('Payment details', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 17)),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
          ? Padding(padding: const EdgeInsets.all(16), child: _ErrorBox(message: _error!, onRetry: _load))
          : _buildBody(),
    ),
  );

  Widget _buildBody() {
    final status = _p['payment_status'] as String?;
    final rows = <(String, Widget)>[
      ('Plan', _value((_p['plan'] as String?) ?? 'Subscription')),
      ('Status', _StatusBadge(status: status)),
      ('Amount', _value(formatBillingMoney(_p['amount'], _p['currency']))),
      ('Billing cycle', _value(_capitalize(_p['billing_cycle'] as String?))),
      ('Payment method', _value(_capitalize(_p['gateway'] as String?))),
      ('Paid on', _value(_p['paid_at'] != null ? formatBillingDate(_p['paid_at'] as String?, withTime: true) : '—')),
      if (_p['current_period_end'] != null)
        ('Next renewal', _value(formatBillingDate(_p['current_period_end'] as String?))),
      if ((_p['failure_reason'] as String?)?.isNotEmpty == true)
        ('Failure reason', _value(_p['failure_reason'] as String)),
      ('Created', _value(formatBillingDate(_p['created_at'] as String?, withTime: true))),
    ];
    final canPay = _kDueStatuses.contains(status);
    final canDownload = status == 'succeeded';

    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 32),
      children: [
        for (final (label, value) in rows)
          Container(
            padding: const EdgeInsets.symmetric(vertical: 14),
            decoration: const BoxDecoration(border: Border(bottom: BorderSide(color: Color(0xFFE5E7EB)))),
            child: Row(
              children: [
                Text(label, style: const TextStyle(fontSize: 13.5, color: AppColors.textMuted)),
                const SizedBox(width: 12),
                Expanded(child: Align(alignment: Alignment.centerRight, child: value)),
              ],
            ),
          ),
        const SizedBox(height: 20),
        if (canPay)
          FilledButton.icon(
            onPressed: _startingCheckout ? null : (_awaitingCheckout ? _checkStatus : _payNow),
            icon: _startingCheckout
                ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                : Icon(_awaitingCheckout ? Icons.sync_rounded : Icons.credit_card_rounded, size: 18),
            label: Text(
              _startingCheckout
                  ? 'Starting checkout…'
                  : _awaitingCheckout
                  ? 'Check payment status'
                  : 'Pay Now',
            ),
          ),
        if (_awaitingCheckout) ...[
          const SizedBox(height: 10),
          const Text(
            'Complete the payment in your browser, then come back here.',
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 12.5, color: AppColors.textMuted),
          ),
        ],
        if (canDownload)
          OutlinedButton.icon(
            onPressed: _downloading ? null : _downloadReceipt,
            icon: _downloading
                ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                : const Icon(Icons.download_rounded, size: 18),
            label: Text(_downloading ? 'Downloading…' : 'Download Receipt'),
          ),
      ],
    );
  }

  static Widget _value(String text) => Text(
    text,
    textAlign: TextAlign.right,
    style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: AppColors.textDark),
  );

  static String _capitalize(String? s) =>
      s == null || s.isEmpty ? '—' : s[0].toUpperCase() + s.substring(1);
}

// ─── Shared helpers ────────────────────────────────────────────────────────

String formatBillingDate(String? iso, {bool withTime = false}) {
  final d = DateTime.tryParse(iso ?? '')?.toLocal();
  if (d == null) return '—';
  return withTime ? DateFormat('MMM d, y, h:mm a').format(d) : DateFormat('MMMM d, y').format(d);
}

String formatBillingMoney(dynamic amount, dynamic currency) {
  final value = amount is num ? amount : num.tryParse('${amount ?? ''}') ?? 0;
  return '${value.toStringAsFixed(2)} ${(currency ?? '').toString().toUpperCase()}'.trim();
}

/// Starts Stripe Checkout for [paymentId] and opens it in the browser.
/// Returns whether the checkout page was opened.
Future<bool> startBillingCheckout(BuildContext context, int paymentId) async {
  final messenger = ScaffoldMessenger.of(context);
  try {
    final res = await ApiClient.instance.post(ApiEndpoints.paymentCheckout, data: {'payment_id': paymentId});
    final raw = res.data;
    final url = raw is Map && raw['data'] is Map ? raw['data']['checkout_url'] as String? : null;
    if (url == null || url.isEmpty) throw Exception('No checkout link was returned.');
    final opened = await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
    if (!opened) throw Exception('Could not open the browser.');
    return true;
  } catch (e) {
    messenger.showSnackBar(
      SnackBar(content: Text('Could not start Stripe checkout. ${apiErrorMessage(e)}')),
    );
    return false;
  }
}

Future<bool> isBillingPaymentPaid(int paymentId) async {
  try {
    final res = await ApiClient.instance.get(ApiEndpoints.paymentStatus(paymentId), bypassCache: true);
    final raw = res.data;
    return raw is Map && raw['data'] is Map && raw['data']['payment_status'] == 'succeeded';
  } catch (_) {
    return false;
  }
}

({Color color, String label, IconData icon}) _statusMeta(String? status) => switch (status) {
  'succeeded' => (color: AppColors.success, label: 'Paid', icon: Icons.check_circle_rounded),
  'pending' => (color: AppColors.warning, label: 'Pending', icon: Icons.schedule_rounded),
  'processing' => (color: AppColors.warning, label: 'Processing', icon: Icons.schedule_rounded),
  'failed' => (color: AppColors.error, label: 'Failed', icon: Icons.warning_amber_rounded),
  'canceled' => (color: AppColors.error, label: 'Canceled', icon: Icons.cancel_rounded),
  'refunded' => (color: AppColors.primaryDk, label: 'Refunded', icon: Icons.replay_rounded),
  _ => (color: AppColors.textMuted, label: status ?? 'Unknown', icon: Icons.receipt_long_rounded),
};

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.status});
  final String? status;

  @override
  Widget build(BuildContext context) {
    final meta = _statusMeta(status);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(color: meta.color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(20)),
      child: Text(meta.label, style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: meta.color)),
    );
  }
}

class _TabLabel extends StatelessWidget {
  const _TabLabel({required this.label, required this.count, required this.selected, required this.onTap});
  final String label;
  final int count;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final color = selected ? AppColors.primaryDk : AppColors.textMuted;
    return InkWell(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 10),
        decoration: BoxDecoration(
          border: Border(bottom: BorderSide(color: selected ? AppColors.primaryDk : Colors.transparent, width: 2.5)),
        ),
        child: Row(
          children: [
            Text(label, style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: color)),
            const SizedBox(width: 6),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 1),
              decoration: BoxDecoration(color: const Color(0xFFEEF0F6), borderRadius: BorderRadius.circular(10)),
              child: Text('$count', style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.w700, color: color)),
            ),
          ],
        ),
      ),
    );
  }
}

enum _PayState { idle, starting, awaiting }

class _PaymentRow extends StatelessWidget {
  const _PaymentRow({
    required this.payment,
    required this.onTap,
    required this.payState,
    required this.onPay,
    required this.onCheck,
  });

  final Map<String, dynamic> payment;
  final VoidCallback onTap;

  /// null for paid rows (chevron instead of a Pay button).
  final _PayState? payState;
  final VoidCallback onPay;
  final VoidCallback onCheck;

  @override
  Widget build(BuildContext context) {
    final status = payment['payment_status'] as String?;
    final meta = _statusMeta(status);
    final cycle = payment['billing_cycle'] as String?;
    final subtitle = [
      formatBillingDate(payment['created_at'] as String?),
      if (cycle != null && cycle.isNotEmpty) cycle,
    ].join(' · ');

    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: const Color(0xFFE5E7EB)),
          ),
          child: Column(
            children: [
              Row(
                children: [
                  Container(
                    width: 42,
                    height: 42,
                    decoration: BoxDecoration(color: meta.color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(12)),
                    alignment: Alignment.center,
                    child: Icon(meta.icon, size: 20, color: meta.color),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          (payment['plan'] as String?) ?? 'Subscription',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(fontSize: 14.5, fontWeight: FontWeight.w700, color: AppColors.textDark),
                        ),
                        const SizedBox(height: 2),
                        Text(subtitle, style: const TextStyle(fontSize: 12, color: AppColors.textMuted)),
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Text(
                        formatBillingMoney(payment['amount'], payment['currency']),
                        style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w800, color: AppColors.textDark),
                      ),
                      const SizedBox(height: 4),
                      _StatusBadge(status: status),
                    ],
                  ),
                  if (payState == null) ...[
                    const SizedBox(width: 4),
                    const Icon(Icons.chevron_right_rounded, color: AppColors.textHint),
                  ],
                ],
              ),
              if (payState != null) ...[
                const SizedBox(height: 10),
                SizedBox(
                  width: double.infinity,
                  child: FilledButton.icon(
                    onPressed: payState == _PayState.starting
                        ? null
                        : payState == _PayState.awaiting
                        ? onCheck
                        : onPay,
                    icon: payState == _PayState.starting
                        ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                        : Icon(payState == _PayState.awaiting ? Icons.sync_rounded : Icons.credit_card_rounded, size: 18),
                    label: Text(payState == _PayState.awaiting ? 'Check status' : 'Pay'),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _ErrorBox extends StatelessWidget {
  const _ErrorBox({required this.message, required this.onRetry});
  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.only(bottom: 12),
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(color: AppColors.error.withValues(alpha: 0.08), borderRadius: BorderRadius.circular(12)),
    child: Row(
      children: [
        const Icon(Icons.error_outline_rounded, color: AppColors.error, size: 20),
        const SizedBox(width: 10),
        Expanded(child: Text(message, style: const TextStyle(fontSize: 13, color: AppColors.textDark))),
        TextButton(onPressed: onRetry, child: const Text('Retry')),
      ],
    ),
  );
}
