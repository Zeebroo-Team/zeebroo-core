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
    this.unreadNotifications = 0,
    this.onNotificationTap,
  });

  final String greeting;
  final String name;
  final String initials;
  final VoidCallback onMenuTap;

  /// e.g. "Nimal's Store › Main Branch" — the currently selected business
  /// (and branch, when one is set). Null/empty hides that line.
  final String? businessLabel;
  final VoidCallback? onBusinessTap;

  final int unreadNotifications;
  final VoidCallback? onNotificationTap;

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
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 8),
                child: Row(
                  children: [
                    SizedBox(
                      width: 38,
                      height: 38,
                      child: IconButton(
                        padding: EdgeInsets.zero,
                        splashRadius: 19,
                        icon: const Icon(Icons.menu_rounded, size: 22, color: AppColors.textMuted),
                        tooltip: 'Menu',
                        onPressed: onMenuTap,
                      ),
                    ),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            '$greeting, $name',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontSize: 11.5,
                              fontWeight: FontWeight.w500,
                              color: AppColors.textMuted,
                              letterSpacing: 0.1,
                            ),
                          ),
                          if (_showBusinessLabel) ...[
                            const SizedBox(height: 2),
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
                                      style: const TextStyle(
                                        fontSize: 15.5,
                                        fontWeight: FontWeight.w700,
                                        color: AppColors.primaryDk,
                                        letterSpacing: -0.1,
                                      ),
                                    ),
                                  ),
                                  if (onBusinessTap != null) ...[
                                    const SizedBox(width: 3),
                                    Icon(Icons.expand_more_rounded, size: 17, color: AppColors.primaryDk.withValues(alpha: 0.55)),
                                  ],
                                ],
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                    const SizedBox(width: 8),
                    // Notification bell
                    GestureDetector(
                      onTap: onNotificationTap,
                      child: Stack(
                        clipBehavior: Clip.none,
                        children: [
                          Container(
                            width: 38,
                            height: 38,
                            decoration: BoxDecoration(
                              color: unreadNotifications > 0 ? AppColors.primaryLt : const Color(0xFFF1F5F9),
                              borderRadius: BorderRadius.circular(10),
                            ),
                            child: Icon(
                              unreadNotifications > 0 ? Icons.notifications_rounded : Icons.notifications_none_rounded,
                              size: 20,
                              color: unreadNotifications > 0 ? AppColors.primary : AppColors.textMuted,
                            ),
                          ),
                          if (unreadNotifications > 0)
                            Positioned(
                              top: -3,
                              right: -3,
                              child: Container(
                                padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 1),
                                decoration: BoxDecoration(
                                  color: AppColors.error,
                                  borderRadius: BorderRadius.circular(7),
                                  border: Border.all(color: Colors.white, width: 1),
                                ),
                                child: Text(
                                  unreadNotifications > 99 ? '99+' : '$unreadNotifications',
                                  style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w800),
                                ),
                              ),
                            ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 8),
                    InkWell(
                      onTap: onMenuTap,
                      customBorder: const CircleBorder(),
                      child: Container(
                        width: 38,
                        height: 38,
                        decoration: BoxDecoration(
                          color: AppColors.primaryLt,
                          shape: BoxShape.circle,
                          border: Border.all(color: Colors.white, width: 1.5),
                          boxShadow: const [BoxShadow(color: AppColors.shadow, blurRadius: 6, offset: Offset(0, 2))],
                        ),
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
    ),
  );
}
