import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';

class QuickAction {
  const QuickAction({
    required this.label,
    required this.icon,
    required this.color,
    required this.onTap,
    this.primary = false,
  });

  final String label;
  final IconData icon;
  final Color color;
  final VoidCallback onTap;

  /// The one call-to-action — rendered as a solid gradient tile.
  final bool primary;
}

/// Row of icon shortcuts under the Today card. Tiles pop in one after the
/// other on first build and squish slightly while pressed.
class QuickActionsRow extends StatefulWidget {
  const QuickActionsRow({super.key, required this.actions});

  final List<QuickAction> actions;

  @override
  State<QuickActionsRow> createState() => _QuickActionsRowState();
}

class _QuickActionsRowState extends State<QuickActionsRow> with SingleTickerProviderStateMixin {
  late final AnimationController _ctrl = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 650),
  )..forward();

  @override
  void dispose() {
    _ctrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Row(
    children: [
      for (var i = 0; i < widget.actions.length; i++)
        Expanded(
          child: AnimatedBuilder(
            animation: _ctrl,
            builder: (_, child) {
              final delay = i * 0.12;
              final t = ((_ctrl.value - delay) / (1 - delay)).clamp(0.0, 1.0);
              return Opacity(
                opacity: t,
                child: Transform.scale(scale: 0.6 + 0.4 * Curves.easeOutBack.transform(t), child: child),
              );
            },
            child: _QuickActionTile(action: widget.actions[i]),
          ),
        ),
    ],
  );
}

class _QuickActionTile extends StatefulWidget {
  const _QuickActionTile({required this.action});
  final QuickAction action;

  @override
  State<_QuickActionTile> createState() => _QuickActionTileState();
}

class _QuickActionTileState extends State<_QuickActionTile> with SingleTickerProviderStateMixin {
  late final AnimationController _press = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 100),
    lowerBound: 0.92,
    upperBound: 1.0,
    value: 1.0,
  );

  @override
  void dispose() {
    _press.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final a = widget.action;
    return Semantics(
      button: true,
      label: a.label,
      excludeSemantics: true,
      child: GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTapDown: (_) => _press.reverse(),
        onTapUp: (_) {
          _press.forward();
          a.onTap();
        },
        onTapCancel: () => _press.forward(),
        child: ScaleTransition(
          scale: _press,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 56,
                height: 56,
                decoration: BoxDecoration(
                  gradient: a.primary ? const LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight, colors: [AppColors.primaryDk, AppColors.primary]) : null,
                  color: a.primary ? null : a.color.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(18),
                  boxShadow: a.primary
                      ? [BoxShadow(color: AppColors.primaryDk.withValues(alpha: 0.3), blurRadius: 12, offset: const Offset(0, 5))]
                      : null,
                ),
                alignment: Alignment.center,
                child: Icon(a.icon, size: 26, color: a.primary ? Colors.white : a.color),
              ),
              const SizedBox(height: 8),
              Text(
                a.label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontSize: 11.5, fontWeight: FontWeight.w600, color: AppColors.textDark),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
