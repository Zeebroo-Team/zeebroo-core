import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/utils/money.dart';

String _percentLabel(double value, double total) {
  final percent = (value / total * 100).round();
  return percent == 0 && value > 0 ? '<1%' : '$percent%';
}

/// One expense type (bills, loans, rentals…) in an [ExpenseBreakdownChart].
class ExpenseSlice {
  const ExpenseSlice({required this.label, required this.value, required this.color, this.due = 0});
  final String label;
  final double value;
  final Color color;

  /// Part of [value] that is scheduled but not paid yet.
  final double due;

  /// Part of [value] already paid.
  double get paid => value - due;
}

/// Pie (donut) chart of expenses split by type, with a tappable legend.
/// Tapping a slice or a legend row highlights it and shows its amount and
/// share in the middle of the ring. Drawn with a [CustomPainter] — the app has
/// no charting dependency.
class ExpenseBreakdownChart extends StatefulWidget {
  const ExpenseBreakdownChart({
    super.key,
    required this.slices,
    this.overdueCount = 0,
    this.footnote,
    this.emptyMessage = 'No expenses yet. Add a bill, loan or rental below to see where your money goes.',
  });

  final List<ExpenseSlice> slices;
  final int overdueCount;

  /// Small muted line under the chart, e.g. the date range it covers.
  final String? footnote;

  /// Shown instead of the ring when every slice is zero.
  final String emptyMessage;

  @override
  State<ExpenseBreakdownChart> createState() => _ExpenseBreakdownChartState();
}

class _ExpenseBreakdownChartState extends State<ExpenseBreakdownChart> {
  static const _size = 136.0;
  static const _thickness = 24.0;
  static const _pad = 4.0;

  String? _selectedLabel;

  void _toggle(String label) => setState(() => _selectedLabel = _selectedLabel == label ? null : label);

  void _onDonutTap(Offset position, List<ExpenseSlice> slices, double total) {
    final offset = position - const Offset(_size / 2, _size / 2);
    final distance = offset.distance;
    const outer = _size / 2 - _pad + 3;
    const inner = _size / 2 - _pad - _thickness - 3;
    if (total <= 0 || distance < inner || distance > outer) {
      setState(() => _selectedLabel = null);
      return;
    }
    var angle = math.atan2(offset.dy, offset.dx) + math.pi / 2;
    if (angle < 0) angle += 2 * math.pi;
    var end = 0.0;
    for (final s in slices) {
      end += s.value / total * 2 * math.pi;
      if (angle <= end) {
        _toggle(s.label);
        return;
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final slices = widget.slices.where((s) => s.value > 0).toList();
    final total = slices.fold<double>(0, (sum, s) => sum + s.value);
    final selected = slices.where((s) => s.label == _selectedLabel).firstOrNull;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            const Icon(Icons.pie_chart_outline_rounded, size: 17, color: AppColors.textMuted),
            const SizedBox(width: 8),
            const Text('Expense breakdown', style: TextStyle(fontSize: 15.5, fontWeight: FontWeight.w800, color: AppColors.textDark)),
            const Spacer(),
            if (widget.overdueCount > 0) _OverduePill(count: widget.overdueCount),
          ],
        ),
        const SizedBox(height: 10),
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(16),
            boxShadow: const [BoxShadow(color: AppColors.shadow, blurRadius: 16, offset: Offset(0, 4))],
          ),
          child: total <= 0
              ? _buildEmpty()
              : Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        _buildDonut(slices, total, selected),
                        const SizedBox(width: 12),
                        Expanded(child: _buildLegend(slices, total)),
                      ],
                    ),
                    if (widget.footnote != null) ...[
                      const SizedBox(height: 12),
                      Text(widget.footnote!, style: const TextStyle(fontSize: 10.5, color: AppColors.textMuted)),
                    ],
                  ],
                ),
        ),
      ],
    );
  }

  Widget _buildEmpty() => Row(
    children: [
      SizedBox(
        width: 56,
        height: 56,
        child: CustomPaint(painter: _DonutPainter(slices: const [], total: 0, progress: 1, selectedLabel: null, thickness: 9, pad: 2)),
      ),
      const SizedBox(width: 14),
      Expanded(
        child: Text(
          widget.emptyMessage,
          style: const TextStyle(fontSize: 12.5, color: AppColors.textMuted, height: 1.35),
        ),
      ),
    ],
  );

  Widget _buildDonut(List<ExpenseSlice> slices, double total, ExpenseSlice? selected) {
    final summary = slices.map((s) => '${s.label} ${formatMoney(s.value)}').join(', ');
    return Semantics(
      label: 'Expense breakdown: $summary',
      child: GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTapUp: (d) => _onDonutTap(d.localPosition, slices, total),
        child: SizedBox(
          width: _size,
          height: _size,
          child: TweenAnimationBuilder<double>(
            tween: Tween(begin: 0, end: 1),
            duration: const Duration(milliseconds: 900),
            curve: Curves.easeOutCubic,
            builder: (_, progress, child) => CustomPaint(
              painter: _DonutPainter(slices: slices, total: total, progress: progress, selectedLabel: _selectedLabel, thickness: _thickness, pad: _pad),
              child: child,
            ),
            child: Center(child: SizedBox(width: 64, child: _buildCenter(selected, total))),
          ),
        ),
      ),
    );
  }

  Widget _buildCenter(ExpenseSlice? selected, double total) => Column(
    mainAxisSize: MainAxisSize.min,
    children: [
      FittedBox(
        fit: BoxFit.scaleDown,
        child: Text(selected?.label ?? 'Total', style: const TextStyle(fontSize: 10.5, fontWeight: FontWeight.w600, color: AppColors.textMuted)),
      ),
      FittedBox(
        fit: BoxFit.scaleDown,
        child: Text(formatMoney(selected?.value ?? total), style: const TextStyle(fontSize: 14.5, fontWeight: FontWeight.w800, color: AppColors.textDark)),
      ),
      if (selected != null)
        Text(_percentLabel(selected.value, total), style: TextStyle(fontSize: 10.5, fontWeight: FontWeight.w700, color: selected.color)),
    ],
  );

  Widget _buildLegend(List<ExpenseSlice> slices, double total) => Column(
    mainAxisSize: MainAxisSize.min,
    children: [
      for (final s in slices)
        _LegendRow(
          slice: s,
          percentLabel: _percentLabel(s.value, total),
          selected: s.label == _selectedLabel,
          dimmed: _selectedLabel != null && s.label != _selectedLabel,
          onTap: () => _toggle(s.label),
        ),
    ],
  );
}

class _LegendRow extends StatelessWidget {
  const _LegendRow({required this.slice, required this.percentLabel, required this.selected, required this.dimmed, required this.onTap});

  final ExpenseSlice slice;
  final String percentLabel;
  final bool selected;
  final bool dimmed;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Semantics(
    button: true,
    selected: selected,
    child: InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(10),
      child: AnimatedOpacity(
        duration: const Duration(milliseconds: 150),
        opacity: dimmed ? 0.45 : 1,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 150),
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
          decoration: BoxDecoration(
            color: selected ? slice.color.withValues(alpha: 0.1) : Colors.transparent,
            borderRadius: BorderRadius.circular(10),
          ),
          child: Row(
            children: [
              Container(width: 10, height: 10, decoration: BoxDecoration(color: slice.color, borderRadius: BorderRadius.circular(3))),
              const SizedBox(width: 8),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(slice.label, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: AppColors.textDark)),
                    FittedBox(
                      fit: BoxFit.scaleDown,
                      alignment: Alignment.centerLeft,
                      child: Text.rich(
                        TextSpan(
                          children: [
                            TextSpan(text: formatMoney(slice.value), style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: AppColors.textDark)),
                            TextSpan(text: '  $percentLabel', style: const TextStyle(fontSize: 11, color: AppColors.textMuted)),
                          ],
                        ),
                      ),
                    ),
                    if (slice.paid > 0)
                      FittedBox(
                        fit: BoxFit.scaleDown,
                        alignment: Alignment.centerLeft,
                        child: Text('Paid ${formatMoney(slice.paid)}', style: const TextStyle(fontSize: 10.5, fontWeight: FontWeight.w700, color: AppColors.success)),
                      ),
                    if (slice.due > 0)
                      FittedBox(
                        fit: BoxFit.scaleDown,
                        alignment: Alignment.centerLeft,
                        child: Text('Due ${formatMoney(slice.due)}', style: const TextStyle(fontSize: 10.5, fontWeight: FontWeight.w700, color: AppColors.warning)),
                      ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    ),
  );
}

class _OverduePill extends StatelessWidget {
  const _OverduePill({required this.count});
  final int count;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
    decoration: BoxDecoration(color: AppColors.error.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(20)),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        const Icon(Icons.warning_amber_rounded, size: 13, color: AppColors.error),
        const SizedBox(width: 4),
        Text('$count overdue', style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: AppColors.error)),
      ],
    ),
  );
}

class _DonutPainter extends CustomPainter {
  _DonutPainter({
    required this.slices,
    required this.total,
    required this.progress,
    required this.selectedLabel,
    required this.thickness,
    required this.pad,
  });

  final List<ExpenseSlice> slices;
  final double total;
  final double progress;
  final String? selectedLabel;
  final double thickness;
  final double pad;

  @override
  void paint(Canvas canvas, Size size) {
    final center = size.center(Offset.zero);
    final radius = math.min(size.width, size.height) / 2 - pad - thickness / 2;
    final rect = Rect.fromCircle(center: center, radius: radius);

    canvas.drawCircle(
      center,
      radius,
      Paint()
        ..style = PaintingStyle.stroke
        ..strokeWidth = thickness
        ..color = const Color(0xFFF3F4F6),
    );
    if (total <= 0) return;

    const fullCircle = 2 * math.pi;
    final gap = slices.length > 1 ? 0.045 : 0.0;
    final limit = progress * fullCircle;
    var start = -math.pi / 2;
    var consumed = 0.0;

    for (final s in slices) {
      final sweep = s.value / total * fullCircle;
      final drawn = math.min(sweep, limit - consumed);
      if (drawn <= 0) break;

      final isSelected = s.label == selectedLabel;
      final dimmed = selectedLabel != null && !isSelected;
      final arc = math.max(drawn - (drawn >= sweep ? gap : 0), 0.02);
      final dueShare = s.value > 0 ? (s.due / s.value).clamp(0.0, 1.0) : 0.0;
      final paidArc = arc * (1 - dueShare);
      final width = thickness + (isSelected ? 6 : 0);
      final arcStart = start + gap / 2;

      // Solid = paid, lighter = due but not paid yet.
      if (paidArc > 0) {
        canvas.drawArc(
          rect,
          arcStart,
          paidArc,
          false,
          Paint()
            ..style = PaintingStyle.stroke
            ..strokeWidth = width
            ..color = dimmed ? s.color.withValues(alpha: 0.35) : s.color,
        );
      }
      if (arc - paidArc > 0) {
        canvas.drawArc(
          rect,
          arcStart + paidArc,
          arc - paidArc,
          false,
          Paint()
            ..style = PaintingStyle.stroke
            ..strokeWidth = width
            ..color = s.color.withValues(alpha: dimmed ? 0.15 : 0.42),
        );
      }
      start += sweep;
      consumed += sweep;
    }
  }

  @override
  bool shouldRepaint(_DonutPainter old) => true;
}
