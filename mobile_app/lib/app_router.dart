import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import 'core/auth/auth_state.dart';
import 'features/auth/screens/login_screen.dart';
import 'features/auth/screens/register_screen.dart';
import 'features/dashboard/screens/dashboard_screen.dart';
import 'features/splash/splash_screen.dart';

GoRouter buildRouter(AuthState authState) => GoRouter(
  initialLocation: '/splash',
  refreshListenable: authState,
  redirect: (context, state) {
    final auth = context.read<AuthState>();
    final path = state.matchedLocation;

    if (auth.status == AuthStatus.unknown) {
      return path == '/splash' ? null : '/splash';
    }

    final isAuthRoute = path.startsWith('/login') || path.startsWith('/register');
    if (!auth.isAuthenticated && (path == '/splash' || !isAuthRoute)) return '/login';
    if (auth.isAuthenticated && (path == '/splash' || isAuthRoute)) return '/home';
    return null;
  },
  routes: [
    GoRoute(path: '/splash', builder: (_, _) => const SplashScreen()),
    GoRoute(path: '/login', builder: (_, _) => const LoginScreen()),
    GoRoute(path: '/register', builder: (_, _) => const RegisterScreen()),
    GoRoute(path: '/home', builder: (_, _) => const DashboardScreen()),
  ],
);
