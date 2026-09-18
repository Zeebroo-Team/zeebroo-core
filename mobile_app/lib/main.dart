import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'app_router.dart';
import 'core/auth/auth_state.dart';
import 'core/theme/app_theme.dart';

void main() {
  runApp(const ZeebrooApp());
}

class ZeebrooApp extends StatefulWidget {
  const ZeebrooApp({super.key});

  @override
  State<ZeebrooApp> createState() => _ZeebrooAppState();
}

class _ZeebrooAppState extends State<ZeebrooApp> {
  final _authState = AuthState();
  late final _router = buildRouter(_authState);

  @override
  void initState() {
    super.initState();
    _authState.bootstrap();
  }

  @override
  Widget build(BuildContext context) => ChangeNotifierProvider.value(
    value: _authState,
    child: MaterialApp.router(
      title: 'Zeebroo',
      debugShowCheckedModeBanner: false,
      theme: buildAppTheme(),
      routerConfig: _router,
    ),
  );
}
