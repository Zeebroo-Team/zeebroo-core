import 'package:flutter/material.dart';

import '../../core/theme/app_theme.dart';

/// Shown briefly while [AuthState.bootstrap] checks for a stored token.
class SplashScreen extends StatelessWidget {
  const SplashScreen({super.key});

  @override
  Widget build(BuildContext context) => const Scaffold(
    backgroundColor: AppColors.card,
    body: Center(
      child: Image(image: AssetImage('assets/images/logo.png'), width: 220),
    ),
  );
}
