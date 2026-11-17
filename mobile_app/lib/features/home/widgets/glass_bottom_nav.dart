import 'dart:ui';

import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';

class NavTabData {
  const NavTabData({
    required this.label,
    required this.icon,
    required this.activeIcon,
  });
  final String label;
  final IconData icon;
  final IconData activeIcon;
}

/// iOS-style frosted-glass tab bar with an optional raised center POS button.
/// Pair with `Scaffold(extendBody: true)`.
class GlassBottomNav extends StatelessWidget {
  const GlassBottomNav({
    super.key,
    required this.tabs,
    required this.currentIndex,
    required this.onTap,
    this.onPosTap,
    this.onScannerTap,
  });

  final List<NavTabData> tabs;
  final int currentIndex;
  final ValueChanged<int> onTap;

  /// When provided, a prominent POS button is inserted in the center of the bar.
  final VoidCallback? onPosTap;
  final VoidCallback? onScannerTap;

  @override
  Widget build(BuildContext context) {
    final bottomPad = MediaQuery.of(context).padding.bottom;
    final showPos = onPosTap != null;

    // Split tabs around the center POS button.
    final midPoint = showPos ? (tabs.length / 2).ceil() : tabs.length;
    final leftTabs = tabs.sublist(0, midPoint);
    final rightTabs = showPos ? tabs.sublist(midPoint) : <NavTabData>[];

    return ClipRect(
      child: BackdropFilter(
        filter: ImageFilter.blur(sigmaX: 24, sigmaY: 24),
        child: Container(
          padding: EdgeInsets.only(top: 0, bottom: bottomPad + 8),
          decoration: const BoxDecoration(
            color: AppColors.glassSurface,
            border: Border(top: BorderSide(color: AppColors.glassHairline)),
          ),
          child: showPos && tabs.length == 1
              ? Row(
                  crossAxisAlignment: CrossAxisAlignment.center,
                  children: [
                    SizedBox(
                      width: 80,
                      child: _NavButton(
                        data: tabs.first,
                        selected: currentIndex == 0,
                        onTap: () => onTap(0),
                      ),
                    ),
                    const Expanded(child: SizedBox()),
                    _PosCenterButton(onTap: onPosTap!),
                    const Expanded(child: SizedBox()),
                    SizedBox(
                      width: 80,
                      child: onScannerTap == null
                          ? null
                          : _NavButton(
                              data: const NavTabData(
                                label: 'Scanner',
                                icon: Icons.qr_code_scanner_rounded,
                                activeIcon: Icons.qr_code_scanner_rounded,
                              ),
                              selected: false,
                              foregroundColor: AppColors.primary,
                              onTap: onScannerTap!,
                            ),
                    ),
                  ],
                )
              : Row(
                  crossAxisAlignment: CrossAxisAlignment.center,
                  children: [
                    for (var i = 0; i < leftTabs.length; i++)
                      Expanded(
                        child: _NavButton(
                          data: leftTabs[i],
                          selected: i == currentIndex,
                          onTap: () => onTap(i),
                        ),
                      ),
                    if (showPos)
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 4),
                        child: _PosCenterButton(onTap: onPosTap!),
                      ),
                    for (var i = 0; i < rightTabs.length; i++)
                      Expanded(
                        child: _NavButton(
                          data: rightTabs[i],
                          selected: (midPoint + i) == currentIndex,
                          onTap: () => onTap(midPoint + i),
                        ),
                      ),
                  ],
                ),
        ),
      ),
    );
  }
}

// ── Regular tab button ───────────────────────────────────────────────────────

class _NavButton extends StatelessWidget {
  const _NavButton({
    required this.data,
    required this.selected,
    required this.onTap,
    this.foregroundColor,
  });
  final NavTabData data;
  final bool selected;
  final VoidCallback onTap;
  final Color? foregroundColor;

  @override
  Widget build(BuildContext context) {
    final color =
        foregroundColor ?? (selected ? AppColors.primary : AppColors.textHint);
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 10),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              selected ? data.activeIcon : data.icon,
              size: 24,
              color: color,
            ),
            const SizedBox(height: 3),
            Text(
              data.label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                fontSize: 11,
                color: color,
                fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ── Center POS button ────────────────────────────────────────────────────────

class _PosCenterButton extends StatefulWidget {
  const _PosCenterButton({required this.onTap});
  final VoidCallback onTap;

  @override
  State<_PosCenterButton> createState() => _PosCenterButtonState();
}

class _PosCenterButtonState extends State<_PosCenterButton>
    with SingleTickerProviderStateMixin {
  late final AnimationController _ctrl = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 120),
    lowerBound: 1.0,
    upperBound: 1.0,
  );

  bool _pressed = false;

  @override
  void dispose() {
    _ctrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTapDown: (_) => setState(() => _pressed = true),
      onTapUp: (_) {
        setState(() => _pressed = false);
        widget.onTap();
      },
      onTapCancel: () => setState(() => _pressed = false),
      child: AnimatedScale(
        scale: _pressed ? 0.9 : 1.0,
        duration: const Duration(milliseconds: 100),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 52,
              height: 52,
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: [AppColors.primaryDk, Color(0xFF3B82F6)],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                shape: BoxShape.circle,
                boxShadow: [
                  BoxShadow(
                    color: AppColors.primary.withValues(alpha: 0.45),
                    blurRadius: 14,
                    spreadRadius: 0,
                    offset: const Offset(0, 4),
                  ),
                ],
              ),
              child: const Icon(
                Icons.point_of_sale_rounded,
                color: Colors.white,
                size: 24,
              ),
            ),
            const SizedBox(height: 3),
            const Text(
              'POS',
              style: TextStyle(
                fontSize: 11,
                color: AppColors.primary,
                fontWeight: FontWeight.w800,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
