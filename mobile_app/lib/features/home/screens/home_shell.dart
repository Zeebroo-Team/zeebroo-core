import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/auth/auth_state.dart';
import '../../../core/business/business_state.dart';
import '../../../core/theme/app_theme.dart';
import '../../business/screens/select_business_screen.dart';
import '../models/feature_entry.dart';
import '../widgets/app_side_drawer.dart';
import '../widgets/glass_app_bar.dart';
import '../widgets/glass_bottom_nav.dart';
import 'feature_placeholder_screen.dart';
import 'home_content.dart';

/// The authenticated app shell: frosted top bar, Home content, up to three
/// plan-enabled features as quick bottom tabs, and a side menu listing every
/// enabled feature plus account actions. This is what `/home` renders.
class HomeShell extends StatefulWidget {
  const HomeShell({super.key});

  @override
  State<HomeShell> createState() => _HomeShellState();
}

/// How many plan features get a permanent slot in the bottom bar, in
/// addition to Home. The rest are still reachable from the side menu.
const _kMaxBottomFeatures = 3;

class _HomeShellState extends State<HomeShell> {
  final _scaffoldKey = GlobalKey<ScaffoldState>();
  int _tabIndex = 0;
  List<FeatureEntry> _enabledFeatures = [];

  @override
  void initState() {
    super.initState();
    _loadFeatures();
  }

  Future<void> _loadFeatures() async {
    try {
      final res = await ApiClient.instance.get(ApiEndpoints.features);
      final data = res.data;
      final keys = (data is Map ? data['data'] : data) as List? ?? [];
      final enabled = FeatureCatalog.enabledFrom(keys.whereType<String>());
      if (mounted) setState(() => _enabledFeatures = enabled);
    } catch (_) {
      // Bottom bar simply falls back to Home-only; the side menu (and its
      // own retry-by-reopen) isn't essential to get to today's overview.
    }
  }

  List<FeatureEntry> get _bottomFeatures =>
      _enabledFeatures.take(_kMaxBottomFeatures).toList();

  static String _greeting() {
    final hour = DateTime.now().hour;
    if (hour < 12) return 'Good morning';
    if (hour < 17) return 'Good afternoon';
    return 'Good evening';
  }

  static String _initials(String name) {
    final parts = name
        .trim()
        .split(RegExp(r'\s+'))
        .where((p) => p.isNotEmpty)
        .toList();
    if (parts.isEmpty) return '?';
    final first = parts.first[0];
    final last = parts.length > 1 ? parts.last[0] : '';
    return (first + last).toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthState>().user;
    final name = (user?['name'] as String?) ?? 'there';
    final email = (user?['email'] as String?) ?? '';
    final firstName = name.split(' ').first;
    final bottomFeatures = _bottomFeatures;

    final business = context.watch<BusinessState>();
    final businessLabel =
        business.branchName != null && business.branchName!.isNotEmpty
        ? '${business.businessName} · ${business.branchName}'
        : business.businessName;

    final tabs = <NavTabData>[
      const NavTabData(
        label: 'Home',
        icon: Icons.home_outlined,
        activeIcon: Icons.home_rounded,
      ),
      for (final f in bottomFeatures)
        NavTabData(label: f.label, icon: f.icon, activeIcon: f.activeIcon),
    ];
    final safeIndex = _tabIndex.clamp(0, tabs.length - 1);

    return Scaffold(
      key: _scaffoldKey,
      backgroundColor: AppColors.surface,
      drawer: AppSideDrawer(
        name: name,
        email: email,
        initials: _initials(name),
        features: _enabledFeatures,
      ),
      appBar: GlassAppBar(
        greeting: _greeting(),
        name: firstName,
        initials: _initials(name),
        onMenuTap: () => _scaffoldKey.currentState?.openDrawer(),
        businessLabel: businessLabel,
        onBusinessTap: () => Navigator.of(context).push(
          MaterialPageRoute(builder: (_) => const SelectBusinessScreen()),
        ),
      ),
      extendBody: true,
      body: IndexedStack(
        index: safeIndex,
        children: [
          const HomeContent(),
          for (final f in bottomFeatures) FeaturePlaceholderBody(feature: f),
        ],
      ),
      bottomNavigationBar: GlassBottomNav(
        tabs: tabs,
        currentIndex: safeIndex,
        onTap: (i) => setState(() => _tabIndex = i),
      ),
    );
  }
}
