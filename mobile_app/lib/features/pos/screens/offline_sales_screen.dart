import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../../core/offline/offline_customers.dart';
import '../../../core/offline/offline_expenses.dart';
import '../../../core/offline/offline_sales.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/money.dart';
import '../models/sale_receipt.dart';
import '../data/pos_receipt_repository.dart';
import 'sale_receipt_screen.dart';

/// Sales saved on this phone while offline: waiting to upload, uploaded (with
/// their real POS number) or needing attention (the server refused them).
class OfflineSalesScreen extends StatefulWidget {
  const OfflineSalesScreen({super.key});

  @override
  State<OfflineSalesScreen> createState() => _OfflineSalesScreenState();
}

class _OfflineSalesScreenState extends State<OfflineSalesScreen> {
  final _sales = OfflineSales.instance;
  List<OfflineSale> _items = const [];
  List<Map<String, dynamic>> _customers = const [];
  List<OfflineExpense> _expenses = const [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _sales.addListener(_reload);
    _reload();
  }

  @override
  void dispose() {
    _sales.removeListener(_reload);
    super.dispose();
  }

  Future<void> _reload() async {
    final items = await _sales.list();
    final customers = await OfflineCustomers.instance.waiting();
    final expenses = await OfflineExpenses.instance.list();
    if (mounted) {
      setState(() {
        _items = items;
        _customers = customers;
        _expenses = expenses;
        _loading = false;
      });
    }
  }

  Future<bool> _confirmRemove(String title, String message) async =>
      await showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: Text(title),
          content: Text(message),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Keep')),
            FilledButton(
              style: FilledButton.styleFrom(backgroundColor: AppColors.error),
              onPressed: () => Navigator.pop(ctx, true),
              child: const Text('Remove'),
            ),
          ],
        ),
      ) ==
      true;

  Future<void> _discardCustomer(Map<String, dynamic> customer) async {
    final ok = await _confirmRemove(
      'Remove ${customer['name']}?',
      'This customer will not be uploaded, and offline sales made to them will need attention.',
    );
    if (ok) await OfflineCustomers.instance.discard(customer['client_uuid'] as String);
  }

  Future<void> _discardExpense(OfflineExpense expense) async {
    final ok = await _confirmRemove('Remove “${expense.name}”?', 'This expense will not be uploaded.');
    if (ok) await OfflineExpenses.instance.discard(expense.clientUuid);
  }

  Future<void> _retryCustomer(String clientUuid) async {
    await OfflineCustomers.instance.retry(clientUuid);
    // Sales that failed only because of this customer can go again too.
    for (final s in _items.where((s) => s.needsAttention && (s.error ?? '').startsWith('Its new customer'))) {
      await _sales.retry(s.clientUuid);
    }
    await _sales.upload();
  }

  Future<void> _retryExpense(String clientUuid) async {
    await OfflineExpenses.instance.retry(clientUuid);
    await _sales.upload();
  }

  Future<void> _discard(OfflineSale sale) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text('Remove ${sale.offlineRef}?'),
        content: const Text(
          'It will not be uploaded. Only do this after recording the sale another way (for example in Electron).',
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Keep')),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: AppColors.error),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Remove'),
          ),
        ],
      ),
    );
    if (ok == true) await _sales.discard(sale.clientUuid);
  }

  void _openReceipt(OfflineSale sale) {
    final json = {
      ...sale.receipt,
      // Once uploaded, show the real number with the offline one in the notes.
      if (sale.saleNumber != null) 'sale_number': sale.saleNumber,
      if (sale.saleNumber != null) 'offline': false,
    };
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => SaleReceiptScreen(
          receipt: SaleReceipt.fromJson(json),
          settings: PosReceiptRepository().loadSettings(),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final waiting = _items.where((s) => !s.isUploaded).toList();
    final uploaded = _items.where((s) => s.isUploaded).toList();
    return Scaffold(
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
        title: const Text('Offline records', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 17)),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _sales.upload,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
                children: [
                  _UploadCard(sales: _sales),
                  if (_items.isEmpty && _customers.isEmpty && _expenses.isEmpty)
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 40),
                      child: Center(
                        child: Text('No offline records on this phone.', style: TextStyle(color: AppColors.textMuted)),
                      ),
                    ),
                  if (_customers.isNotEmpty) ...[
                    const _SectionLabel('New customers waiting to upload'),
                    for (final c in _customers)
                      _RecordRow(
                        icon: Icons.person_add_alt_1_rounded,
                        title: '${c['name'] ?? ''}',
                        subtitle: '${c['phone'] ?? ''}'.trim(),
                        failed: c['status'] == OfflineCustomers.statusFailed,
                        error: c['error'] as String?,
                        onRetry: c['status'] == OfflineCustomers.statusFailed
                            ? () => _retryCustomer(c['client_uuid'] as String)
                            : null,
                        onDiscard: () => _discardCustomer(c),
                      ),
                  ],
                  if (_expenses.isNotEmpty) ...[
                    const _SectionLabel('Expenses'),
                    for (final e in _expenses)
                      _RecordRow(
                        icon: Icons.receipt_long_rounded,
                        title: e.name,
                        subtitle: DateFormat('MMM d, h:mm a').format(e.createdAt),
                        amount: e.amount,
                        uploaded: e.isUploaded,
                        failed: e.needsAttention,
                        error: e.error,
                        onRetry: e.needsAttention ? () => _retryExpense(e.clientUuid) : null,
                        onDiscard: e.isUploaded ? null : () => _discardExpense(e),
                      ),
                  ],
                  if (waiting.isNotEmpty) ...[
                    const _SectionLabel('Waiting to upload'),
                    for (final s in waiting)
                      _SaleRow(
                        sale: s,
                        onTap: () => _openReceipt(s),
                        onRetry: s.needsAttention ? () => _sales.retry(s.clientUuid) : null,
                        onDiscard: () => _discard(s),
                      ),
                  ],
                  if (uploaded.isNotEmpty) ...[
                    const _SectionLabel('Uploaded (last 7 days)'),
                    for (final s in uploaded) _SaleRow(sale: s, onTap: () => _openReceipt(s)),
                  ],
                ],
              ),
            ),
    );
  }
}

class _UploadCard extends StatelessWidget {
  const _UploadCard({required this.sales});
  final OfflineSales sales;

  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.only(bottom: 8),
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(16),
      border: Border.all(color: const Color(0xFFE5E7EB)),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          sales.allWaitingCount == 0
              ? 'Everything is uploaded.'
              : '${sales.pendingCount + OfflineCustomers.instance.pendingCount + OfflineExpenses.instance.pendingCount} waiting · '
                    '${sales.failedCount + OfflineCustomers.instance.failedCount + OfflineExpenses.instance.failedCount} need attention',
          style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15),
        ),
        const SizedBox(height: 4),
        const Text(
          'Offline sales, new customers and expenses upload automatically when the connection is back, and every minute.',
          style: TextStyle(fontSize: 12.5, color: AppColors.textMuted),
        ),
        if (sales.lastError != null && !sales.uploading) ...[
          const SizedBox(height: 8),
          Text(sales.lastError!, style: const TextStyle(fontSize: 12.5, color: AppColors.error)),
        ],
        const SizedBox(height: 12),
        FilledButton.icon(
          key: const ValueKey('offline-upload-now'),
          onPressed: sales.uploading || !sales.hasPendingUploads ? null : sales.upload,
          icon: sales.uploading
              ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
              : const Icon(Icons.cloud_upload_rounded, size: 18),
          label: Text(sales.uploading ? 'Uploading…' : 'Upload now'),
        ),
      ],
    ),
  );
}

class _SectionLabel extends StatelessWidget {
  const _SectionLabel(this.text);
  final String text;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(4, 16, 4, 8),
    child: Text(
      text.toUpperCase(),
      style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700, letterSpacing: 0.6, color: AppColors.textMuted),
    ),
  );
}

class _SaleRow extends StatelessWidget {
  const _SaleRow({required this.sale, required this.onTap, this.onRetry, this.onDiscard});
  final OfflineSale sale;
  final VoidCallback onTap;
  final VoidCallback? onRetry;
  final VoidCallback? onDiscard;

  @override
  Widget build(BuildContext context) {
    final (color, label, icon) = sale.isUploaded
        ? (AppColors.success, 'Uploaded as ${sale.saleNumber ?? ''}', Icons.cloud_done_rounded)
        : sale.needsAttention
        ? (AppColors.error, 'Needs attention', Icons.error_outline_rounded)
        : (AppColors.warning, 'Waiting to upload', Icons.schedule_rounded);
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Icon(icon, size: 20, color: color),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(sale.offlineRef, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
                        Text(
                          '${DateFormat('MMM d, h:mm a').format(sale.createdAt)} · $label',
                          style: TextStyle(fontSize: 12, color: color),
                        ),
                      ],
                    ),
                  ),
                  Text(formatMoney(sale.total), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14)),
                ],
              ),
              if (sale.needsAttention && sale.error != null) ...[
                const SizedBox(height: 8),
                Text(sale.error!, style: const TextStyle(fontSize: 12.5, color: AppColors.textDark)),
              ],
              if (onRetry != null || onDiscard != null)
                Row(
                  mainAxisAlignment: MainAxisAlignment.end,
                  children: [
                    if (onRetry != null)
                      TextButton.icon(onPressed: onRetry, icon: const Icon(Icons.refresh_rounded, size: 18), label: const Text('Retry')),
                    if (onDiscard != null)
                      TextButton.icon(
                        style: TextButton.styleFrom(foregroundColor: AppColors.error),
                        onPressed: onDiscard,
                        icon: const Icon(Icons.delete_outline_rounded, size: 18),
                        label: const Text('Remove'),
                      ),
                  ],
                ),
            ],
          ),
        ),
      ),
    );
  }
}

/// A customer or expense saved on the phone.
class _RecordRow extends StatelessWidget {
  const _RecordRow({
    required this.icon,
    required this.title,
    required this.subtitle,
    this.amount,
    this.uploaded = false,
    this.failed = false,
    this.error,
    this.onRetry,
    this.onDiscard,
  });

  final IconData icon;
  final String title, subtitle;
  final double? amount;
  final bool uploaded, failed;
  final String? error;
  final VoidCallback? onRetry, onDiscard;

  @override
  Widget build(BuildContext context) {
    final (color, label) = uploaded
        ? (AppColors.success, 'Uploaded')
        : failed
        ? (AppColors.error, 'Needs attention')
        : (AppColors.warning, 'Waiting to upload');
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, size: 20, color: color),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
                    Text(
                      [if (subtitle.isNotEmpty) subtitle, label].join(' · '),
                      style: TextStyle(fontSize: 12, color: color),
                    ),
                  ],
                ),
              ),
              if (amount != null)
                Text(formatMoney(amount!), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14)),
            ],
          ),
          if (failed && error != null) ...[
            const SizedBox(height: 8),
            Text(error!, style: const TextStyle(fontSize: 12.5, color: AppColors.textDark)),
          ],
          if (onRetry != null || onDiscard != null)
            Row(
              mainAxisAlignment: MainAxisAlignment.end,
              children: [
                if (onRetry != null)
                  TextButton.icon(onPressed: onRetry, icon: const Icon(Icons.refresh_rounded, size: 18), label: const Text('Retry')),
                if (onDiscard != null)
                  TextButton.icon(
                    style: TextButton.styleFrom(foregroundColor: AppColors.error),
                    onPressed: onDiscard,
                    icon: const Icon(Icons.delete_outline_rounded, size: 18),
                    label: const Text('Remove'),
                  ),
              ],
            ),
        ],
      ),
    );
  }
}
