import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../models/feature_entry.dart';

/// Full-screen "coming soon" placeholder for a feature that's enabled on the
/// business's plan but doesn't have a screen built yet.
class FeaturePlaceholderScreen extends StatelessWidget {
  const FeaturePlaceholderScreen({super.key, required this.feature});
  final FeatureEntry feature;

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: AppColors.surface,
    appBar: AppBar(
      backgroundColor: AppColors.surface,
      foregroundColor: AppColors.textDark,
      elevation: 0,
      centerTitle: true,
      title: Text(feature.label, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 17)),
    ),
    body: _FeatureBody(feature: feature),
  );
}

/// Body-only variant used inside [HomeShell]'s IndexedStack, where the
/// shell's own glass app bar and bottom nav already frame the screen.
class FeaturePlaceholderBody extends StatelessWidget {
  const FeaturePlaceholderBody({super.key, required this.feature});
  final FeatureEntry feature;

  @override
  Widget build(BuildContext context) => _FeatureBody(feature: feature);
}

class _FeatureBody extends StatelessWidget {
  const _FeatureBody({required this.feature});
  final FeatureEntry feature;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.symmetric(horizontal: 40),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 84,
            height: 84,
            decoration: const BoxDecoration(color: AppColors.primaryLt, shape: BoxShape.circle),
            alignment: Alignment.center,
            child: Icon(feature.activeIcon, size: 38, color: AppColors.primary),
          ),
          const SizedBox(height: 22),
          Text(feature.label,
              style: const TextStyle(fontSize: 19, fontWeight: FontWeight.w800, color: AppColors.textDark)),
          const SizedBox(height: 8),
          Text(
            '${feature.description}\nThis feature is coming soon to the Zeebroo mobile app.',
            textAlign: TextAlign.center,
            style: const TextStyle(fontSize: 13.5, height: 1.5, color: AppColors.textMuted),
          ),
        ],
      ),
    ),
  );
}
