import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';

/// Small colored pill for statuses shared across the Inventory tabs (PO
/// status, GRN payment/approval status, cheque status, audit/transfer
/// status, active/inactive flags). Pass a raw status string — recognized
/// values get a semantic color, anything else falls back to neutral.
class StatusChip extends StatelessWidget {
  const StatusChip({super.key, required this.label, this.tone});

  final String label;

  /// Explicit color override. If omitted, the tone is inferred from [label].
  final StatusTone? tone;

  static const _positive = {
    'active', 'in_stock', 'paid_full', 'approved', 'received', 'cleared',
    'completed', 'ordered',
  };
  static const _warning = {
    'low_stock', 'partially_received', 'paid_partial', 'pending', 'draft',
    'due', 'open', 'in_transit',
  };
  static const _negative = {
    'inactive', 'out_of_stock', 'cancelled', 'rejected', 'overdue',
  };

  StatusTone _inferTone() {
    final key = label.toLowerCase().replaceAll(' ', '_');
    if (_positive.contains(key)) return StatusTone.positive;
    if (_warning.contains(key)) return StatusTone.warning;
    if (_negative.contains(key)) return StatusTone.negative;
    return StatusTone.neutral;
  }

  @override
  Widget build(BuildContext context) {
    final resolved = tone ?? _inferTone();
    final Color color;
    switch (resolved) {
      case StatusTone.positive:
        color = AppColors.success;
        break;
      case StatusTone.warning:
        color = AppColors.warning;
        break;
      case StatusTone.negative:
        color = AppColors.error;
        break;
      case StatusTone.neutral:
        color = AppColors.textMuted;
        break;
    }
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        label,
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w700,
          color: color,
        ),
      ),
    );
  }
}

enum StatusTone { positive, warning, negative, neutral }
