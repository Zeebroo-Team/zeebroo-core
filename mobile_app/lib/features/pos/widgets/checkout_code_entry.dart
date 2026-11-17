import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';

/// Inline coupon/gift-card entry with bounded button sizing inside a Row.
class CheckoutCodeEntry extends StatelessWidget {
  const CheckoutCodeEntry({
    super.key,
    required this.controller,
    required this.hint,
    required this.color,
    required this.loading,
    required this.error,
    required this.onApply,
    required this.onCancel,
  });

  final TextEditingController controller;
  final String hint;
  final Color color;
  final bool loading;
  final String? error;
  final Future<void> Function() onApply;
  final VoidCallback onCancel;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Row(
        children: [
          Expanded(
            child: TextField(
              controller: controller,
              enabled: !loading,
              textCapitalization: TextCapitalization.characters,
              onSubmitted: (_) => loading ? null : onApply(),
              decoration: InputDecoration(
                hintText: hint,
                isDense: true,
                contentPadding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 13,
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: BorderSide(
                    color: error == null ? AppColors.border : AppColors.error,
                  ),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: BorderSide(color: color, width: 1.5),
                ),
              ),
            ),
          ),
          const SizedBox(width: 8),
          SizedBox(
            height: 44,
            child: ElevatedButton(
              onPressed: loading ? null : onApply,
              style: ElevatedButton.styleFrom(
                backgroundColor: color,
                foregroundColor: Colors.white,
                // The app theme uses an infinite minimum width for full-width
                // buttons. This inline button must have a finite minimum.
                minimumSize: const Size(72, 44),
                tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                padding: const EdgeInsets.symmetric(horizontal: 16),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(10),
                ),
              ),
              child: loading
                  ? const SizedBox(
                      width: 17,
                      height: 17,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: Colors.white,
                      ),
                    )
                  : const Text('Apply'),
            ),
          ),
          const SizedBox(width: 6),
          IconButton(
            onPressed: loading ? null : onCancel,
            icon: const Icon(Icons.close_rounded),
            color: AppColors.textMuted,
            tooltip: 'Cancel',
          ),
        ],
      ),
      if (error != null) ...[
        const SizedBox(height: 5),
        Text(
          error!,
          style: const TextStyle(color: AppColors.error, fontSize: 12),
        ),
      ],
    ],
  );
}
