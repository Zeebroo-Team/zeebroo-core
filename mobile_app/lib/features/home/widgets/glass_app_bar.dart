import 'dart:ui';

import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';

/// iOS-style frosted-glass top bar: blurred, near-white, sits above the
/// scroll content instead of a solid Material AppBar.
///
/// Minimal two-line layout so greeting, name, business and branch are all
/// visible at once without crowding the row: a small muted "Good afternoon,
/// Name" line on top, and the bold, tappable "Business › Branch" line below
/// it — plus the menu button and account avatar.
class GlassAppBar extends StatelessWidget implements PreferredSizeWidget {
  const GlassAppBar({
    super.key,
    required this.greeting,
    required this.name,
    required this.initials,
    required this.onMenuTap,
    this.businessLabel,
    this.onBusinessTap,
  });

  final String greeting;
  final String name;
  final String initials;
  final VoidCallback onMenuTap;

  /// e.g. "Nimal's Store › Main Branch" — the currently selected business
  /// (and branch, when one is set). Null/empty hides that line.
  final String? businessLabel;
  final VoidCallback? onBusinessTap;

  /// Toolbar content height, excluding the status-bar inset. Scaffold grants
  /// the appBar slot `MediaQuery.padding.top` of *extra* height on top of
  /// this automatically — that extra space is where [SafeArea] below lands,
  /// so this must stay a plain content height, never padding-inclusive.
  static const _toolbarHeight = 62.0;

  bool get _showBusinessLabel => businessLabel != null && businessLabel!.isNotEmpty;

  @override
  Size get preferredSize => const Size.fromHeight(_toolbarHeight);

  @override
  Widget build(BuildContext context) => ClipRect(
    child: BackdropFilter(
      filter: ImageFilter.blur(sigmaX: 24, sigmaY: 24),
      child: Container(
        decoration: const BoxDecoration(
          color: AppColors.glassSurface,
          border: Border(bottom: BorderSide(color: AppColors.glassHairline)),
        ),
        child: SafeArea(
          bottom: false,
          // The toolbar is a fixed-height strip of chrome, not scrollable
          // body content — clamp text scaling here so a large system/
          // accessibility font size can't blow the text column past the
          // SizedBox below and trigger a bottom-overflow render error.
          child: MediaQuery(
            data: MediaQuery.of(context).copyWith(
              textScaler: MediaQuery.textScalerOf(context).clamp(maxScaleFactor: 1.15),
            ),
            child: SizedBox(
              height: _toolbarHeight,
              child: Row(
                children: [
                  const SizedBox(width: 2),
                  IconButton(
                    icon: const Icon(Icons.menu_rounded, color: AppColors.textDark),
                    tooltip: 'Menu',
                    onPressed: onMenuTap,
                  ),
                  Expanded(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          '$greeting, $name',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(fontSize: 11.5, color: AppColors.textMuted),
                        ),
                        if (_showBusinessLabel) ...[
                          const SizedBox(height: 1),
                          InkWell(
                            onTap: onBusinessTap,
                            borderRadius: BorderRadius.circular(6),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Flexible(
                                  child: Text(
                                    businessLabel!,
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(fontSize: 15.5, fontWeight: FontWeight.w700, color: AppColors.textDark),
                                  ),
                                ),
                                if (onBusinessTap != null) ...[
                                  const SizedBox(width: 2),
                                  const Icon(Icons.expand_more_rounded, size: 16, color: AppColors.textMuted),
                                ],
                              ],
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                  InkWell(
                    onTap: onMenuTap,
                    customBorder: const CircleBorder(),
                    child: Container(
                      width: 36,
                      height: 36,
                      margin: const EdgeInsets.only(right: 14),
                      decoration: const BoxDecoration(color: AppColors.primaryLt, shape: BoxShape.circle),
                      alignment: Alignment.center,
                      child: Text(
                        initials,
                        style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: AppColors.primaryDk),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    ),
  );
}
