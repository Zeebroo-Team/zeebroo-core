import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/utils/money.dart';
import '../data/gift_card_repository.dart';

String giftCardStatusLabel(String status) => switch (status) {
  'used' => 'Used',
  'expired' => 'Expired',
  'disabled' => 'Disabled',
  'scheduled' => 'Scheduled',
  'mixed' => 'Mixed',
  _ => 'Active',
};

String giftCardGroupStatus(GiftCardRecord group) {
  if (!group.isActive) return 'disabled';
  final counts = group.statusCounts;
  if (counts.length == 1) return counts.keys.first;
  return 'mixed';
}

class GiftCardStatusBadge extends StatelessWidget {
  const GiftCardStatusBadge({
    super.key,
    required this.status,
    this.onDark = false,
  });
  final String status;
  final bool onDark;
  @override
  Widget build(BuildContext context) {
    final color = switch (status) {
      'active' => AppColors.success,
      'scheduled' => AppColors.primary,
      'expired' => AppColors.error,
      'used' => const Color(0xFFD97706),
      _ => AppColors.textMuted,
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
      decoration: BoxDecoration(
        color: onDark
            ? Colors.white.withValues(alpha: 0.2)
            : color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        giftCardStatusLabel(status),
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w700,
          color: onDark ? Colors.white : color,
        ),
      ),
    );
  }
}

class GiftCardSummaryCard extends StatelessWidget {
  const GiftCardSummaryCard({super.key, required this.record});
  final GiftCardRecord record;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(20),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [Color(0xFF7C3AED), Color(0xFFDB2777)],
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
      ),
      borderRadius: BorderRadius.circular(20),
      boxShadow: [
        BoxShadow(
          color: const Color(0xFF7C3AED).withValues(alpha: 0.22),
          blurRadius: 16,
          offset: const Offset(0, 6),
        ),
      ],
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                record.name,
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w700,
                  fontSize: 16,
                ),
              ),
            ),
            const SizedBox(width: 8),
            GiftCardStatusBadge(
              status: record.isGroup
                  ? giftCardGroupStatus(record)
                  : record.status,
              onDark: true,
            ),
          ],
        ),
        const SizedBox(height: 12),
        if (record.isGroup)
          Text(
            '${record.cardCount} cards × ${formatMoney(record.initialValue)} each',
            style: const TextStyle(color: Colors.white70, fontSize: 13),
          )
        else
          SelectableText(
            record.code,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 19,
              fontWeight: FontWeight.w800,
              letterSpacing: 1.1,
            ),
          ),
        const SizedBox(height: 14),
        Text(
          record.isGroup ? 'REMAINING ACROSS ALL CARDS' : 'AVAILABLE BALANCE',
          style: const TextStyle(color: Colors.white70, fontSize: 11),
        ),
        FittedBox(
          fit: BoxFit.scaleDown,
          alignment: Alignment.centerLeft,
          child: Text(
            formatMoney(record.balance),
            style: const TextStyle(
              color: Colors.white,
              fontSize: 32,
              fontWeight: FontWeight.w900,
            ),
          ),
        ),
        const SizedBox(height: 14),
        ClipRRect(
          borderRadius: BorderRadius.circular(4),
          child: LinearProgressIndicator(
            value: record.totalValue <= 0
                ? 0
                : (record.balance / record.totalValue).clamp(0.0, 1.0),
            minHeight: 6,
            color: Colors.white,
            backgroundColor: Colors.white.withValues(alpha: 0.25),
          ),
        ),
        const SizedBox(height: 10),
        Text(
          record.isGroup
              ? 'Issued ${formatMoney(record.totalValue)} · ${record.statusCounts['active'] ?? 0} active'
              : 'Value ${formatMoney(record.initialValue)} · Used ${formatMoney(record.usedAmount)}',
          style: const TextStyle(color: Colors.white, fontSize: 12),
        ),
        const SizedBox(height: 6),
        Text(
          record.validityLabel,
          style: const TextStyle(color: Colors.white, fontSize: 12),
        ),
      ],
    ),
  );
}
