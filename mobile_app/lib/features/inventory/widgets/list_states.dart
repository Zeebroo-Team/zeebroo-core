import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';

/// Shared "failed to load" placeholder for the Inventory tabs' list bodies.
class ErrorState extends StatelessWidget {
  const ErrorState({super.key, required this.error, required this.onRetry});
  final String error;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.symmetric(horizontal: 32),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(error, textAlign: TextAlign.center, style: const TextStyle(color: AppColors.textMuted, fontSize: 13)),
          const SizedBox(height: 10),
          TextButton.icon(onPressed: onRetry, icon: const Icon(Icons.refresh, size: 16), label: const Text('Retry')),
        ],
      ),
    ),
  );
}

/// Shared "nothing here" placeholder for the Inventory tabs' list bodies.
class EmptyState extends StatelessWidget {
  const EmptyState({super.key, required this.message});
  final String message;

  @override
  Widget build(BuildContext context) => Center(
    child: Text(message, style: const TextStyle(color: AppColors.textMuted, fontSize: 13)),
  );
}

/// Small square accent icon-button used for the per-tab "+ Add" affordance
/// next to each tab's search field.
class AddButton extends StatelessWidget {
  const AddButton({super.key, required this.onTap, this.icon = Icons.add_rounded});
  final VoidCallback onTap;
  final IconData icon;

  @override
  Widget build(BuildContext context) => InkWell(
    onTap: onTap,
    borderRadius: BorderRadius.circular(12),
    child: Container(
      height: 50,
      width: 50,
      decoration: BoxDecoration(color: AppColors.primary, borderRadius: BorderRadius.circular(12)),
      child: Icon(icon, color: Colors.white),
    ),
  );
}
