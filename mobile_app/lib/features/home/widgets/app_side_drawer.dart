import 'dart:ui';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/auth/auth_state.dart';
import '../../../core/business/business_state.dart';
import '../../../core/theme/app_theme.dart';
import '../../business/screens/business_screen.dart';
import '../../business/screens/select_business_screen.dart';
import '../../finance/screens/finance_screen.dart';
import '../../inventory/screens/inventory_screen.dart';
import '../../notifications/screens/notifications_screen.dart';
import '../../profile/screens/profile_screen.dart';
import '../models/feature_entry.dart';
import '../screens/feature_placeholder_screen.dart';

/// The app's side bar — an iOS Settings-style grouped list behind frosted
/// glass, reachable from the menu button in [GlassAppBar]. Lists every
/// feature enabled on the business's plan plus account-level actions.
class AppSideDrawer extends StatelessWidget {
  const AppSideDrawer({
    super.key,
    required this.name,
    required this.email,
    required this.initials,
    required this.features,
    this.unreadNotifications = 0,
  });

  final String name;
  final String email;
  final String initials;
  final List<FeatureEntry> features;
  final int unreadNotifications;

  @override
  Widget build(BuildContext context) {
    final business = context.watch<BusinessState>();
    final hasDistinctBranch =
        business.branchName != null &&
        business.branchName!.isNotEmpty &&
        business.branchName != business.businessName;
    final businessSubtitle = hasDistinctBranch
        ? '${business.businessName} › ${business.branchName}'
        : business.businessName;

    return Drawer(
      backgroundColor: Colors.transparent,
      width: MediaQuery.of(context).size.width * 0.82,
      child: ClipRRect(
        child: BackdropFilter(
          filter: ImageFilter.blur(sigmaX: 30, sigmaY: 30),
          child: Container(
            color: AppColors.glassSurface,
            child: SafeArea(
              child: ListView(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
                children: [
                  InkWell(
                    borderRadius: BorderRadius.circular(18),
                    onTap: () {
                      Navigator.of(context).pop();
                      Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => const ProfileScreen(),
                        ),
                      );
                    },
                    child: _ProfileCard(
                      name: name,
                      email: email,
                      initials: initials,
                    ),
                  ),
                  const SizedBox(height: 24),
                  _GroupedCard(
                    children: [
                      _Row(
                        icon: Icons.inventory_2_outlined,
                        label: 'Inventory',
                        showDivider: false,
                        onTap: () {
                          Navigator.of(context).pop();
                          Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) => const InventoryScreen(),
                            ),
                          );
                        },
                      ),
                    ],
                  ),
                  const SizedBox(height: 24),
                  if (features.isNotEmpty) ...[
                    const _SectionLabel('Features'),
                    const SizedBox(height: 8),
                    _GroupedCard(
                      children: [
                        for (final f in features.where((f) => f.key != 'event_management'))
                          _Row(
                            icon: f.icon,
                            label: f.label,
                            onTap: () {
                              Navigator.of(context).pop();
                              Navigator.of(context).push(
                                MaterialPageRoute(
                                  builder: (_) => f.key == 'bill_management'
                                      ? const FinanceScreen()
                                      : FeaturePlaceholderScreen(feature: f),
                                ),
                              );
                            },
                          ),
                      ],
                    ),
                    const SizedBox(height: 24),
                  ],
                  const _SectionLabel('General'),
                  const SizedBox(height: 8),
                  _GroupedCard(
                    children: [
                      _Row(
                        icon: Icons.notifications_outlined,
                        label: 'Notifications',
                        badge: unreadNotifications > 0 ? '$unreadNotifications' : null,
                        onTap: () {
                          Navigator.of(context).pop();
                          Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) => const NotificationsScreen(),
                            ),
                          );
                        },
                      ),
                      _Row(
                        icon: Icons.swap_horiz_rounded,
                        label: 'Switch Business',
                        subtitle: businessSubtitle,
                        onTap: () {
                          Navigator.of(context).pop();
                          Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) => const SelectBusinessScreen(),
                            ),
                          );
                        },
                      ),
                      _Row(
                        icon: Icons.storefront_outlined,
                        label: 'Business Settings',
                        onTap: () {
                          Navigator.of(context).pop();
                          Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) => const BusinessScreen(),
                            ),
                          );
                        },
                      ),
                      _Row(
                        icon: Icons.help_outline_rounded,
                        label: 'Help & Support',
                        onTap: () {
                          Navigator.of(context).pop();
                          Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) => const FeaturePlaceholderScreen(
                                feature: FeatureEntry(
                                  key: 'help',
                                  label: 'Help & Support',
                                  description: "We're here to help — reach out any time.",
                                  icon: Icons.help_outline_rounded,
                                  activeIcon: Icons.help_rounded,
                                ),
                              ),
                            ),
                          );
                        },
                        showDivider: false,
                      ),
                    ],
                  ),
                  const SizedBox(height: 24),
                  _GroupedCard(
                    children: [
                      _Row(
                        icon: Icons.logout_rounded,
                        label: 'Log Out',
                        color: AppColors.error,
                        showDivider: false,
                        onTap: () => _confirmLogout(context),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _confirmLogout(BuildContext context) async {
    final auth = context.read<AuthState>();
    final business = context.read<BusinessState>();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Log out'),
        content: const Text('Are you sure you want to log out?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(false),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(true),
            child: const Text(
              'Log Out',
              style: TextStyle(color: AppColors.error),
            ),
          ),
        ],
      ),
    );
    if (confirmed == true) {
      if (context.mounted) Navigator.of(context).pop();
      await auth.logout();
      await business.clear();
    }
  }
}

class _ProfileCard extends StatelessWidget {
  const _ProfileCard({
    required this.name,
    required this.email,
    required this.initials,
  });
  final String name;
  final String email;
  final String initials;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(18),
      boxShadow: const [
        BoxShadow(
          color: AppColors.shadow,
          blurRadius: 16,
          offset: Offset(0, 4),
        ),
      ],
    ),
    child: Row(
      children: [
        Container(
          width: 48,
          height: 48,
          decoration: const BoxDecoration(
            color: AppColors.primaryLt,
            shape: BoxShape.circle,
          ),
          alignment: Alignment.center,
          child: Text(
            initials,
            style: const TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w700,
              color: AppColors.primaryDk,
            ),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                name,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w700,
                  color: AppColors.textDark,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                email,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontSize: 12,
                  color: AppColors.textMuted,
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _SectionLabel extends StatelessWidget {
  const _SectionLabel(this.text);
  final String text;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(left: 4),
    child: Text(
      text.toUpperCase(),
      style: const TextStyle(
        fontSize: 12,
        fontWeight: FontWeight.w700,
        color: AppColors.textHint,
        letterSpacing: 0.4,
      ),
    ),
  );
}

class _GroupedCard extends StatelessWidget {
  const _GroupedCard({required this.children});
  final List<Widget> children;

  @override
  Widget build(BuildContext context) => Container(
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(16),
      boxShadow: const [
        BoxShadow(
          color: AppColors.shadow,
          blurRadius: 16,
          offset: Offset(0, 4),
        ),
      ],
    ),
    clipBehavior: Clip.antiAlias,
    child: Column(children: children),
  );
}

class _Row extends StatelessWidget {
  const _Row({
    required this.icon,
    required this.label,
    required this.onTap,
    this.color = AppColors.textDark,
    this.showDivider = true,
    this.subtitle,
    this.badge,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;
  final Color color;
  final bool showDivider;
  final String? subtitle;
  final String? badge;

  @override
  Widget build(BuildContext context) => Column(
    children: [
      InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
          child: Row(
            children: [
              Icon(icon, size: 20, color: color),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      label,
                      style: TextStyle(
                        fontSize: 14.5,
                        fontWeight: FontWeight.w600,
                        color: color,
                      ),
                    ),
                    if (subtitle != null && subtitle!.isNotEmpty)
                      Text(
                        subtitle!,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontSize: 12,
                          color: AppColors.textMuted,
                        ),
                      ),
                  ],
                ),
              ),
              if (badge != null)
                Container(
                  margin: const EdgeInsets.only(right: 6),
                  padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                  decoration: BoxDecoration(
                    color: AppColors.error,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Text(
                    badge!,
                    style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w800),
                  ),
                ),
              if (color != AppColors.error)
                const Icon(
                  Icons.chevron_right_rounded,
                  size: 18,
                  color: AppColors.textHint,
                ),
            ],
          ),
        ),
      ),
      if (showDivider)
        const Divider(height: 1, indent: 48, color: AppColors.glassHairline),
    ],
  );
}
