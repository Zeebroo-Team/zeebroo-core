import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/auth/auth_state.dart';
import '../../../core/business/business_state.dart';
import '../../../core/theme/app_theme.dart';
import '../../business/screens/select_business_screen.dart';
import '../../notifications/screens/notifications_screen.dart';
import '../../pos/models/pos_cart_item.dart';
import '../../pos/screens/barcode_scanner_screen.dart';
import '../../pos/screens/pos_screen.dart';
import '../widgets/app_side_drawer.dart';
import '../widgets/glass_app_bar.dart';
import '../widgets/glass_bottom_nav.dart';
import 'home_content.dart';

/// The authenticated app shell: frosted top bar, Home content, Home/POS/Scanner
/// navigation, and a side menu with management shortcuts and account actions.
/// This is what `/home` renders.
class HomeShell extends StatefulWidget {
  const HomeShell({super.key});

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  final _scaffoldKey = GlobalKey<ScaffoldState>();
  int _homeTapSignal = 0;
  int _unreadNotifications = 0;

  @override
  void initState() {
    super.initState();
    _loadUnreadCount();
  }

  Future<void> _loadUnreadCount() async {
    try {
      final res = await ApiClient.instance.get(
        ApiEndpoints.notifications,
        params: {'status': 'unread', 'limit': 1},
      );
      final count = (res.data is Map ? res.data['unread_count'] : null) as num?;
      if (mounted) setState(() => _unreadNotifications = count?.toInt() ?? 0);
    } catch (_) {}
  }

  void _openNotifications() async {
    await Navigator.of(
      context,
    ).push(MaterialPageRoute(builder: (_) => const NotificationsScreen()));
    // Refresh count after returning from notifications
    _loadUnreadCount();
  }

  Future<void> _openScanner() async {
    final cart = await Navigator.of(context).push<List<PosCartItem>>(
      MaterialPageRoute(
        fullscreenDialog: true,
        builder: (_) => const BarcodeScannerScreen(),
      ),
    );
    if (!mounted || cart == null || cart.isEmpty) return;
    await Navigator.of(
      context,
    ).push(MaterialPageRoute(builder: (_) => PosScreen(initialCart: cart)));
  }

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

    final business = context.watch<BusinessState>();
    final hasDistinctBranch =
        business.branchName != null &&
        business.branchName!.isNotEmpty &&
        business.branchName != business.businessName;
    final businessLabel = hasDistinctBranch
        ? '${business.businessName} › ${business.branchName}'
        : business.businessName;

    const tabs = <NavTabData>[
      NavTabData(
        label: 'Home',
        icon: Icons.home_outlined,
        activeIcon: Icons.home_rounded,
      ),
    ];

    return Scaffold(
      key: _scaffoldKey,
      backgroundColor: AppColors.surface,
      drawer: AppSideDrawer(
        name: name,
        email: email,
        initials: _initials(name),
        unreadNotifications: _unreadNotifications,
      ),
      appBar: GlassAppBar(
        greeting: _greeting(),
        name: firstName,
        initials: _initials(name),
        onMenuTap: () => _scaffoldKey.currentState?.openDrawer(),
        businessLabel: businessLabel,
        onBusinessTap: () => Navigator.of(
          context,
        ).push(MaterialPageRoute(builder: (_) => const SelectBusinessScreen())),
        unreadNotifications: _unreadNotifications,
        onNotificationTap: _openNotifications,
      ),
      extendBody: true,
      body: HomeContent(homeTapSignal: _homeTapSignal),
      bottomNavigationBar: GlassBottomNav(
        tabs: tabs,
        currentIndex: 0,
        onTap: (_) => setState(() => _homeTapSignal++),
        onPosTap: () => Navigator.of(
          context,
        ).push(MaterialPageRoute(builder: (_) => const PosScreen())),
        onScannerTap: _openScanner,
      ),
    );
  }
}
