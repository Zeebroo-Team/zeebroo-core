import 'package:flutter/material.dart';
import '../../../core/theme/app_theme.dart';

class StatCard extends StatelessWidget {
  const StatCard({
    super.key,
    required this.label,
    required this.value,
    required this.icon,
    required this.iconColor,
    required this.iconBg,
    this.change,
    this.changePositive,
  });

  final String  label;
  final String  value;
  final IconData icon;
  final Color   iconColor;
  final Color   iconBg;
  final String? change;
  final bool?   changePositive;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 38, height: 38,
            decoration: BoxDecoration(color: iconBg, borderRadius: BorderRadius.circular(10)),
            child: Icon(icon, size: 20, color: iconColor),
          ),
          const SizedBox(height: 10),
          Text(value,
            style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800,
                color: AppColors.textDark)),
          const SizedBox(height: 2),
          Text(label, style: const TextStyle(fontSize: 12,
              color: AppColors.textHint, fontWeight: FontWeight.w500)),
          if (change != null) ...[
            const SizedBox(height: 4),
            Text(
              change!,
              style: TextStyle(
                fontSize: 11, fontWeight: FontWeight.w600,
                color: (changePositive ?? true) ? AppColors.success : AppColors.error,
              ),
            ),
          ],
        ],
      ),
    ),
  );
}
