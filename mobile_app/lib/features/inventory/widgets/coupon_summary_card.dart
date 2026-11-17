import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../data/coupon_repository.dart';

Color couponStatusColor(String status) => switch (status) {
  'active' => AppColors.success,
  'scheduled' => AppColors.primary,
  'expired' => AppColors.error,
  'used' => const Color(0xFFD97706),
  _ => AppColors.textMuted,
};

class CouponStatusBadge extends StatelessWidget {
  const CouponStatusBadge({
    super.key,
    required this.coupon,
    this.onDark = false,
  });
  final CouponData coupon;
  final bool onDark;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
    decoration: BoxDecoration(
      color: onDark
          ? Colors.white.withValues(alpha: 0.2)
          : couponStatusColor(coupon.status).withValues(alpha: 0.1),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Text(
      coupon.statusLabel,
      style: TextStyle(
        fontSize: 11,
        fontWeight: FontWeight.w700,
        color: onDark ? Colors.white : couponStatusColor(coupon.status),
      ),
    ),
  );
}

class CouponSummaryCard extends StatelessWidget {
  const CouponSummaryCard({super.key, required this.coupon});
  final CouponData coupon;

  @override
  Widget build(BuildContext context) {
    final progress = coupon.quantity == 0
        ? 0.0
        : (coupon.remaining / coupon.quantity).clamp(0.0, 1.0);
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF0D9488), Color(0xFFD99A13)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0D9488).withValues(alpha: 0.2),
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
                  coupon.name,
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w700,
                    fontSize: 16,
                  ),
                ),
              ),
              const SizedBox(width: 8),
              CouponStatusBadge(coupon: coupon, onDark: true),
            ],
          ),
          const SizedBox(height: 12),
          SelectableText(
            coupon.code,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 21,
              fontWeight: FontWeight.w800,
              letterSpacing: 1.4,
            ),
          ),
          const SizedBox(height: 14),
          const Text(
            'DISCOUNT',
            style: TextStyle(color: Colors.white70, fontSize: 12),
          ),
          Text(
            coupon.discountLabel,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 30,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 14),
          ClipRRect(
            borderRadius: BorderRadius.circular(4),
            child: LinearProgressIndicator(
              value: progress,
              minHeight: 6,
              color: Colors.white,
              backgroundColor: Colors.white.withValues(alpha: 0.25),
            ),
          ),
          const SizedBox(height: 10),
          Text(
            '${coupon.remaining} of ${coupon.quantity} left · Used ${coupon.usedCount} times',
            style: const TextStyle(color: Colors.white, fontSize: 12),
          ),
          const SizedBox(height: 6),
          Text(
            coupon.validityLabel,
            style: const TextStyle(color: Colors.white, fontSize: 12),
          ),
        ],
      ),
    );
  }
}
