import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../core/api/api_client.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/money.dart';
import '../data/coupon_repository.dart';
import '../widgets/coupon_summary_card.dart';
import 'coupon_form_screen.dart';

class CouponDetailScreen extends StatefulWidget {
  const CouponDetailScreen({
    super.key,
    required this.coupon,
    this.repository = const CouponRepository(),
  });
  final CouponData coupon;
  final CouponRepository repository;

  @override
  State<CouponDetailScreen> createState() => _CouponDetailScreenState();
}

class _CouponDetailScreenState extends State<CouponDetailScreen> {
  late CouponData _coupon;
  bool _loading = true;
  bool _deleting = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _coupon = widget.coupon;
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final coupon = await widget.repository.detail(_coupon.id);
      if (mounted) setState(() => _coupon = coupon);
    } catch (error) {
      if (mounted) setState(() => _error = apiErrorMessage(error));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _edit() async {
    final saved = await Navigator.of(context).push<CouponData>(
      MaterialPageRoute(
        builder: (_) =>
            CouponFormScreen(existing: _coupon, repository: widget.repository),
      ),
    );
    if (!mounted || saved == null) return;
    setState(() => _coupon = saved);
    await _load();
  }

  Future<void> _copy() async {
    await Clipboard.setData(ClipboardData(text: _coupon.code));
    if (mounted) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Coupon code copied')));
    }
  }

  Future<void> _delete() async {
    if (_deleting) return;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Delete coupon'),
        content: Text('Delete ${_coupon.code} permanently?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: const Text(
              'Delete',
              style: TextStyle(color: AppColors.error),
            ),
          ),
        ],
      ),
    );
    if (!mounted || confirmed != true) return;
    setState(() {
      _deleting = true;
      _error = null;
    });
    try {
      await widget.repository.delete(_coupon.id);
      if (mounted) Navigator.of(context).pop(true);
    } catch (error) {
      if (mounted) setState(() => _error = apiErrorMessage(error));
    } finally {
      if (mounted) setState(() => _deleting = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: AppColors.surface,
    appBar: AppBar(
      backgroundColor: AppColors.surface,
      foregroundColor: AppColors.textDark,
      centerTitle: true,
      title: const Text('Coupon details'),
    ),
    body: RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(20),
        children: [
          CouponSummaryCard(coupon: _coupon),
          const SizedBox(height: 24),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  style: OutlinedButton.styleFrom(
                    minimumSize: const Size(0, 46),
                    padding: const EdgeInsets.symmetric(horizontal: 8),
                    textStyle: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  onPressed: _deleting ? null : _copy,
                  icon: const Icon(Icons.copy_rounded, size: 18),
                  label: const Text('Copy Code'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: OutlinedButton.icon(
                  style: OutlinedButton.styleFrom(
                    minimumSize: const Size(0, 46),
                    padding: const EdgeInsets.symmetric(horizontal: 8),
                    textStyle: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  onPressed: _deleting ? null : _edit,
                  icon: const Icon(Icons.edit_outlined, size: 18),
                  label: const Text('Edit'),
                ),
              ),
            ],
          ),
          const SizedBox(height: 20),
          _field(
            'Uses',
            '${_coupon.usedCount} used · ${_coupon.remaining} left of ${_coupon.quantity}',
          ),
          _field('Valid', _coupon.validityLabel),
          _field('Notes', _coupon.notes.isEmpty ? '—' : _coupon.notes),
          const Divider(height: 24),
          const Text(
            'Usage history',
            style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 12),
          if (_loading)
            const Center(child: CircularProgressIndicator())
          else if (_error == null && _coupon.redemptions.isEmpty)
            const Text(
              'Not used yet',
              style: TextStyle(color: AppColors.textMuted),
            )
          else if (!_loading) ...[
            Text(
              '${formatMoney(_coupon.totalDiscount)} total discount given',
              style: const TextStyle(color: AppColors.textMuted, fontSize: 12),
            ),
            const SizedBox(height: 6),
            ..._coupon.redemptions.map(_historyRow),
          ],
          if (_error != null) ...[
            const SizedBox(height: 12),
            Text(_error!, style: const TextStyle(color: AppColors.error)),
            TextButton(onPressed: _load, child: const Text('Refresh details')),
          ],
          const SizedBox(height: 24),
          if (_coupon.redemptions.isNotEmpty || _coupon.usedCount > 0)
            const Text(
              'Coupons used in sales cannot be deleted. Edit and turn off Active to disable this coupon.',
              style: TextStyle(fontSize: 12, color: AppColors.textMuted),
            ),
          TextButton.icon(
            onPressed:
                _deleting ||
                    _loading ||
                    _coupon.redemptions.isNotEmpty ||
                    _coupon.usedCount > 0
                ? null
                : _delete,
            style: TextButton.styleFrom(foregroundColor: AppColors.error),
            icon: const Icon(Icons.delete_outline),
            label: Text(_deleting ? 'Deleting...' : 'Delete coupon'),
          ),
        ],
      ),
    ),
  );

  Widget _field(String label, String value) => Padding(
    padding: const EdgeInsets.only(bottom: 14),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label.toUpperCase(),
          style: const TextStyle(
            fontSize: 11,
            fontWeight: FontWeight.w700,
            color: AppColors.textMuted,
          ),
        ),
        const SizedBox(height: 5),
        Text(value),
      ],
    ),
  );

  Widget _historyRow(Map<String, dynamic> row) {
    final reversed = row['reversed'] == true;
    final when = DateTime.tryParse(
      row['created_at']?.toString() ?? '',
    )?.toLocal();
    final user = row['user_name']?.toString() ?? '';
    final amount = double.tryParse(row['discount_amount'].toString()) ?? 0;
    return ListTile(
      contentPadding: EdgeInsets.zero,
      title: Text(
        '${row['sale_number'] ?? 'Sale'}${reversed ? ' · Voided' : ''}',
        style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
      ),
      subtitle: Text(
        [
          if (when != null)
            '${couponDate(when)} ${TimeOfDay.fromDateTime(when).format(context)}',
          if (user.isNotEmpty) user,
        ].join(' · '),
        style: const TextStyle(fontSize: 11),
      ),
      trailing: Text(
        '${reversed ? '' : '−'}${formatMoney(amount)}',
        style: TextStyle(
          color: reversed ? AppColors.textMuted : AppColors.error,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}
