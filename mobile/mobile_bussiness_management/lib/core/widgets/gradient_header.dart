import 'package:flutter/material.dart';
import '../theme/app_theme.dart';

/// Purple gradient app header — reused on Login, Register, and Dashboard.
class GradientHeader extends StatelessWidget {
  const GradientHeader({
    super.key,
    this.leading,
    this.title,
    this.child,
    this.bottomPad = 24,
    this.topPad,
  });

  final Widget? leading;
  final String? title;
  final Widget? child;
  final double  bottomPad;
  final double? topPad;

  @override
  Widget build(BuildContext context) {
    final top = topPad ?? MediaQuery.of(context).padding.top + 16;
    return Container(
      width: double.infinity,
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          colors: [AppColors.primary, AppColors.primaryDk],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
      ),
      padding: EdgeInsets.fromLTRB(20, top, 20, bottomPad),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          if (leading != null || title != null)
            Row(
              children: [
                if (leading != null) ...[leading!, const SizedBox(width: 8)],
                if (title != null)
                  Expanded(
                    child: Text(
                      title!,
                      style: const TextStyle(
                        fontSize: 18, fontWeight: FontWeight.w700, color: Colors.white,
                      ),
                      textAlign: leading == null ? TextAlign.center : TextAlign.start,
                    ),
                  ),
              ],
            ),
          if (child != null) ...[
            if (title != null) const SizedBox(height: 16),
            child!,
          ],
        ],
      ),
    );
  }
}
