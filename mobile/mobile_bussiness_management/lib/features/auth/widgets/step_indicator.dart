import 'package:flutter/material.dart';
import '../../../core/theme/app_theme.dart';

class StepIndicator extends StatelessWidget {
  const StepIndicator({
    super.key,
    required this.steps,
    required this.current,
  });

  final List<String> steps;
  final int current;

  @override
  Widget build(BuildContext context) => Column(
    children: [
      Row(
        children: [
          for (int i = 0; i < steps.length; i++) ...[
            _Dot(index: i, current: current),
            if (i < steps.length - 1) _Line(filled: i < current),
          ],
        ],
      ),
      const SizedBox(height: 8),
      Text(
        steps[current].toUpperCase(),
        style: TextStyle(
          fontSize: 11, fontWeight: FontWeight.w600,
          color: Colors.white.withValues(alpha: 0.75),
          letterSpacing: 1.2,
        ),
      ),
    ],
  );
}

class _Dot extends StatelessWidget {
  const _Dot({required this.index, required this.current});
  final int index, current;

  @override
  Widget build(BuildContext context) {
    final done   = index < current;
    final active = index == current;
    return Container(
      width: 28, height: 28,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: (done || active) ? Colors.white : Colors.white.withValues(alpha: 0.25),
      ),
      child: Center(
        child: done
            ? Icon(Icons.check, size: 14, color: AppColors.primary)
            : Text(
                '${index + 1}',
                style: TextStyle(
                  fontSize: 12, fontWeight: FontWeight.w700,
                  color: active ? AppColors.primary : Colors.white.withValues(alpha: 0.6),
                ),
              ),
      ),
    );
  }
}

class _Line extends StatelessWidget {
  const _Line({required this.filled});
  final bool filled;

  @override
  Widget build(BuildContext context) => Expanded(
    child: Container(
      height: 2,
      color: filled ? Colors.white : Colors.white.withValues(alpha: 0.25),
    ),
  );
}
