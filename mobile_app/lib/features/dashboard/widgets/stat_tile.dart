import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';

/// Lays out [StatTile]s two per row with fixed-height cells, using plain
/// `Column`/`Row` instead of a shrink-wrapped `GridView`. A `GridView`
/// with `shrinkWrap: true` + `mainAxisExtent` inside a non-scrolling
/// parent (e.g. our tab `ListView`s) reports a taller intrinsic size than
/// its rows actually need, leaving a large blank gap below it — this
/// sidesteps that by never going through the sliver shrink-wrap path.
class StatGrid extends StatelessWidget {
  const StatGrid({super.key, required this.tiles, this.tileHeight = 88, this.spacing = 10});

  final List<Widget> tiles;
  final double tileHeight;
  final double spacing;

  @override
  Widget build(BuildContext context) => Column(
    children: [
      for (var i = 0; i < tiles.length; i += 2) ...[
        if (i > 0) SizedBox(height: spacing),
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(child: SizedBox(height: tileHeight, child: tiles[i])),
            SizedBox(width: spacing),
            Expanded(
              child: i + 1 < tiles.length ? SizedBox(height: tileHeight, child: tiles[i + 1]) : const SizedBox.shrink(),
            ),
          ],
        ),
      ],
    ],
  );
}

class StatTile extends StatelessWidget {
  const StatTile({
    super.key,
    required this.label,
    required this.value,
    required this.icon,
    this.color = AppColors.primary,
  });

  final String label;
  final String value;
  final IconData icon;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(16),
      boxShadow: const [BoxShadow(color: AppColors.shadow, blurRadius: 16, offset: Offset(0, 4))],
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisAlignment: MainAxisAlignment.center,
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 24,
          height: 24,
          decoration: BoxDecoration(
            color: color.withValues(alpha: 0.12),
            borderRadius: BorderRadius.circular(8),
          ),
          alignment: Alignment.center,
          child: Icon(icon, size: 13, color: color),
        ),
        const SizedBox(height: 6),
        Text(value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w800, color: AppColors.textDark, height: 1.1)),
        const SizedBox(height: 1),
        Text(label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(fontSize: 10.5, color: AppColors.textMuted, height: 1.1)),
      ],
    ),
  );
}
