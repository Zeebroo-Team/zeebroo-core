import 'package:flutter/material.dart';

/// One line in a [TrendChart] — a label/color plus its y-values, one per
/// x-axis slot (shared across all series in the chart).
class TrendSeries {
  const TrendSeries({required this.label, required this.color, required this.values});
  final String label;
  final Color color;
  final List<double> values;
}

/// Minimal multi-line trend chart with no external charting dependency —
/// just enough to show shape/direction, matching the "minimal design" brief.
class TrendChart extends StatelessWidget {
  const TrendChart({super.key, required this.series, this.height = 150});

  final List<TrendSeries> series;
  final double height;

  @override
  Widget build(BuildContext context) => SizedBox(
    height: height,
    width: double.infinity,
    child: CustomPaint(painter: _TrendChartPainter(series)),
  );
}

class _TrendChartPainter extends CustomPainter {
  _TrendChartPainter(this.series);
  final List<TrendSeries> series;

  @override
  void paint(Canvas canvas, Size size) {
    final allValues = series.expand((s) => s.values).toList();
    if (allValues.isEmpty) return;
    var maxV = allValues.reduce((a, b) => a > b ? a : b);
    var minV = allValues.reduce((a, b) => a < b ? a : b);
    if (minV > 0) minV = 0;
    if (maxV <= minV) maxV = minV + 1;
    final range = maxV - minV;

    canvas.save();
    canvas.clipRect(Offset.zero & size);

    const topPad = 8.0;
    const bottomPad = 3.0;
    final plotHeight = size.height - topPad - bottomPad;

    final gridPaint = Paint()
      ..color = const Color(0xFFE5E7EB)
      ..strokeWidth = 1;
    canvas.drawLine(Offset(0, size.height - 1), Offset(size.width, size.height - 1), gridPaint);

    for (final s in series) {
      if (s.values.length < 2) continue;
      final stepX = size.width / (s.values.length - 1);
      final path = Path();
      for (var i = 0; i < s.values.length; i++) {
        final x = stepX * i;
        final y = size.height - bottomPad - ((s.values[i] - minV) / range) * plotHeight;
        if (i == 0) {
          path.moveTo(x, y);
        } else {
          path.lineTo(x, y);
        }
      }
      final linePaint = Paint()
        ..color = s.color
        ..strokeWidth = 2.4
        ..style = PaintingStyle.stroke
        ..strokeCap = StrokeCap.round
        ..strokeJoin = StrokeJoin.round;
      canvas.drawPath(path, linePaint);
    }

    canvas.restore();
  }

  @override
  bool shouldRepaint(covariant _TrendChartPainter oldDelegate) => oldDelegate.series != series;
}

/// Small color-dot + label legend row for a [TrendChart].
class TrendLegend extends StatelessWidget {
  const TrendLegend({super.key, required this.series});
  final List<TrendSeries> series;

  @override
  Widget build(BuildContext context) => Wrap(
    spacing: 14,
    runSpacing: 6,
    children: [
      for (final s in series)
        Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(width: 8, height: 8, decoration: BoxDecoration(color: s.color, shape: BoxShape.circle)),
            const SizedBox(width: 6),
            Text(s.label, style: const TextStyle(fontSize: 11.5, color: Color(0xFF6B7280), fontWeight: FontWeight.w500)),
          ],
        ),
    ],
  );
}
