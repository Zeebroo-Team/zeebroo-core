import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import 'core/auth/auth_state.dart';
import 'features/auth/screens/login_screen.dart';
import 'features/auth/screens/register_wizard_screen.dart';
import 'features/dashboard/screens/dashboard_screen.dart';
import 'features/inventory/screens/inventory_screen.dart';
import 'features/sales/screens/sales_screen.dart';
import 'features/settings/screens/settings_screen.dart';
import 'features/shell/main_shell.dart';

GoRouter buildRouter(AuthState authState) => GoRouter(
  initialLocation: '/home',
  refreshListenable: authState,
  redirect: (ctx, state) {
    final auth    = ctx.read<AuthState>();
    final isAuth  = auth.status == AuthStatus.authenticated;
    final unknown = auth.status == AuthStatus.unknown;
    final path    = state.matchedLocation;

    if (unknown) return null;                    // still booting
    if (!isAuth && !path.startsWith('/login') && !path.startsWith('/register')) {
      return '/login';
    }
    if (isAuth && (path.startsWith('/login') || path.startsWith('/register'))) {
      return '/home';
    }
    return null;
  },
  routes: [
    // ── Auth ──────────────────────────────────────────────────────────────
    GoRoute(path: '/login',    builder: (_, __) => const LoginScreen()),
    GoRoute(path: '/register', builder: (_, __) => const RegisterWizardScreen()),

    // ── Authenticated shell with bottom nav ───────────────────────────────
    StatefulShellRoute.indexedStack(
      builder: (_, __, shell) => MainShell(navigationShell: shell),
      branches: [
        StatefulShellBranch(routes: [
          GoRoute(path: '/home', builder: (_, __) => const DashboardScreen()),
        ]),
        StatefulShellBranch(routes: [
          GoRoute(path: '/sales', builder: (_, __) => const SalesScreen()),
        ]),
        StatefulShellBranch(routes: [
          GoRoute(path: '/products', builder: (_, __) => const InventoryScreen()),
        ]),
        StatefulShellBranch(routes: [
          GoRoute(path: '/settings', builder: (_, __) => const SettingsScreen()),
        ]),
      ],
    ),
  ],
  errorBuilder: (_, state) => Scaffold(
    body: Center(child: Text('404 — ${state.error}')),
  ),
);
