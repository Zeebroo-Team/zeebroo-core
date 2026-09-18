import 'package:flutter/material.dart';

/// A single navigable business feature (POS, CRM, HR, ...). Mirrors the
/// feature keys in `config/features.php` — a business only sees the ones
/// enabled on its plan (`GET /v1/pos/online/features`).
class FeatureEntry {
  const FeatureEntry({
    required this.key,
    required this.label,
    required this.description,
    required this.icon,
    required this.activeIcon,
  });

  final String key;
  final String label;
  final String description;
  final IconData icon;
  final IconData activeIcon;
}

/// Full catalog, in the priority order features should appear in the bottom
/// bar / side menu. `account_management` is deliberately excluded — it's
/// always on and represents the account itself rather than a feature tile.
class FeatureCatalog {
  FeatureCatalog._();

  static const List<FeatureEntry> all = [
    FeatureEntry(
      key: 'point_of_sale',
      label: 'Point of Sale',
      description: 'Ring up sales, take payments and manage the register.',
      icon: Icons.point_of_sale_outlined,
      activeIcon: Icons.point_of_sale_rounded,
    ),
    FeatureEntry(
      key: 'sales_management',
      label: 'Sales',
      description: 'Track orders, quotations and sales performance.',
      icon: Icons.storefront_outlined,
      activeIcon: Icons.storefront_rounded,
    ),
    FeatureEntry(
      key: 'product_management',
      label: 'Products',
      description: 'Manage your product catalog, pricing and categories.',
      icon: Icons.inventory_2_outlined,
      activeIcon: Icons.inventory_2_rounded,
    ),
    FeatureEntry(
      key: 'stock_management',
      label: 'Stock',
      description: 'Monitor stock levels, transfers and audits.',
      icon: Icons.warehouse_outlined,
      activeIcon: Icons.warehouse_rounded,
    ),
    FeatureEntry(
      key: 'crm',
      label: 'CRM',
      description: 'Keep track of customers and their activity.',
      icon: Icons.groups_outlined,
      activeIcon: Icons.groups_rounded,
    ),
    FeatureEntry(
      key: 'service_management',
      label: 'Services',
      description: 'Manage service requests from start to finish.',
      icon: Icons.build_outlined,
      activeIcon: Icons.build_rounded,
    ),
    FeatureEntry(
      key: 'bill_management',
      label: 'Bills',
      description: 'Track bills, expenses and payments.',
      icon: Icons.receipt_long_outlined,
      activeIcon: Icons.receipt_long_rounded,
    ),
    FeatureEntry(
      key: 'human_resources',
      label: 'HR',
      description: 'Manage employees, attendance and payroll.',
      icon: Icons.badge_outlined,
      activeIcon: Icons.badge_rounded,
    ),
    FeatureEntry(
      key: 'event_management',
      label: 'Events',
      description: 'Plan and manage bookings and events.',
      icon: Icons.event_outlined,
      activeIcon: Icons.event_rounded,
    ),
    FeatureEntry(
      key: 'restaurant',
      label: 'Restaurant',
      description: 'Manage tables, menus and orders.',
      icon: Icons.restaurant_outlined,
      activeIcon: Icons.restaurant_rounded,
    ),
    FeatureEntry(
      key: 'project_management',
      label: 'Projects',
      description: 'Plan and track projects and tasks.',
      icon: Icons.assignment_outlined,
      activeIcon: Icons.assignment_rounded,
    ),
    FeatureEntry(
      key: 'social_media_campaign',
      label: 'Campaigns',
      description: 'Plan and run social media campaigns.',
      icon: Icons.campaign_outlined,
      activeIcon: Icons.campaign_rounded,
    ),
    FeatureEntry(
      key: 'mail',
      label: 'Mail',
      description: 'Send and manage business email.',
      icon: Icons.mail_outline_rounded,
      activeIcon: Icons.mail_rounded,
    ),
    FeatureEntry(
      key: 'automation_editor',
      label: 'Automation',
      description: 'Build automated workflows.',
      icon: Icons.auto_awesome_outlined,
      activeIcon: Icons.auto_awesome_rounded,
    ),
    FeatureEntry(
      key: 'developers',
      label: 'Developers',
      description: 'API keys, webhooks and developer tools.',
      icon: Icons.code_outlined,
      activeIcon: Icons.code_rounded,
    ),
  ];

  /// Returns catalog entries for the given enabled keys, in catalog order.
  static List<FeatureEntry> enabledFrom(Iterable<String> enabledKeys) {
    final enabled = enabledKeys.toSet();
    return all.where((f) => enabled.contains(f.key)).toList(growable: false);
  }
}
