import 'package:flutter/foundation.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import 'core/auth/auth_state.dart';
import 'core/business/business_state.dart';
import 'features/auth/screens/login_screen.dart';
import 'features/auth/screens/register_screen.dart';
import 'features/business/screens/select_business_screen.dart';
import 'features/home/screens/home_shell.dart';
import 'features/splash/splash_screen.dart';

GoRouter buildRouter(AuthState authState, BusinessState businessState) =>
    GoRouter(
      initialLocation: '/splash',
      refreshListenable: Listenable.merge([authState, businessState]),
      redirect: (context, state) {
        final auth = context.read<AuthState>();
        final business = context.read<BusinessState>();
        final path = state.matchedLocation;

        final stillBootstrapping =
            auth.status == AuthStatus.unknown ||
            (auth.isAuthenticated &&
                business.status == BusinessSelectionStatus.unknown);
        if (stillBootstrapping) {
          return path == '/splash' ? null : '/splash';
        }

        final isAuthRoute =
            path.startsWith('/login') || path.startsWith('/register');
        if (!auth.isAuthenticated) {
          return (path == '/splash' || !isAuthRoute) ? '/login' : null;
        }

        // Authenticated from here on — every account has at least one business,
        // so this always resolves once the businesses list loads.
        if (!business.hasSelection) {
          return path == '/select-business' ? null : '/select-business';
        }
        if (path == '/splash' || isAuthRoute) return '/home';
        return null;
      },
      routes: [
        GoRoute(path: '/splash', builder: (_, _) => const SplashScreen()),
        GoRoute(path: '/login', builder: (_, _) => const LoginScreen()),
        GoRoute(path: '/register', builder: (_, _) => const RegisterScreen()),
        GoRoute(
          path: '/select-business',
          builder: (_, _) => const SelectBusinessScreen(),
        ),
        GoRoute(path: '/home', builder: (_, _) => const HomeShell()),
      ],
    );
