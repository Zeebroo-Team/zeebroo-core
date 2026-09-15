import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../core/theme/app_theme.dart';

/// Persistent bottom-navigation shell wrapping the authenticated tabs.
class MainShell extends StatelessWidget {
  const MainShell({super.key, required this.navigationShell});
  final StatefulNavigationShell navigationShell;

  static const _tabs = [
    _Tab(icon: Icons.home_outlined,      activeIcon: Icons.home_rounded,         label: 'Home'),
    _Tab(icon: Icons.receipt_long_outlined, activeIcon: Icons.receipt_long_rounded, label: 'Sales'),
    _Tab(icon: Icons.inventory_2_outlined,  activeIcon: Icons.inventory_2_rounded,  label: 'Inventory'),
    _Tab(icon: Icons.settings_outlined,     activeIcon: Icons.settings_rounded,     label: 'Settings'),
  ];

  @override
  Widget build(BuildContext context) => Scaffold(
    body: navigationShell,
    bottomNavigationBar: Container(
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border(top: BorderSide(color: AppColors.border.withValues(alpha: 0.6))),
        boxShadow: [
          BoxShadow(color: Colors.black.withValues(alpha: 0.05),
              blurRadius: 12, offset: const Offset(0, -4)),
        ],
      ),
      child: SafeArea(
        top: false,
        child: NavigationBar(
          selectedIndex: navigationShell.currentIndex,
          onDestinationSelected: navigationShell.goBranch,
          backgroundColor: Colors.white,
          elevation: 0,
          indicatorColor: AppColors.purpleLight,
          labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
          destinations: _tabs.map((t) => NavigationDestination(
            icon:           Icon(t.icon,       color: AppColors.textMuted),
            selectedIcon:   Icon(t.activeIcon, color: AppColors.primary),
            label:          t.label,
          )).toList(),
        ),
      ),
    ),
  );
}

class _Tab {
  const _Tab({required this.icon, required this.activeIcon, required this.label});
  final IconData icon, activeIcon;
  final String label;
}
