import 'package:flutter/material.dart';

import '../theme/app_theme.dart';

/// Small step-progress indicator, mirroring the desktop register wizard's dots.
class StepDots extends StatelessWidget {
  const StepDots({super.key, required this.total, required this.current});

  final int total;
  final int current;

  @override
  Widget build(BuildContext context) => Row(
    mainAxisSize: MainAxisSize.min,
    children: List.generate(total, (i) {
      final active = i == current;
      return AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        margin: const EdgeInsets.symmetric(horizontal: 3),
        width: active ? 18 : 7,
        height: 7,
        decoration: BoxDecoration(
          color: active ? AppColors.primary : AppColors.border,
          borderRadius: BorderRadius.circular(4),
        ),
      );
    }),
  );
}
