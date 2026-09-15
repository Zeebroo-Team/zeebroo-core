import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import 'app_router.dart';
import 'core/auth/auth_state.dart';
import 'core/theme/app_theme.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  SystemChrome.setPreferredOrientations([
    DeviceOrientation.portraitUp,
    DeviceOrientation.portraitDown,
  ]);
  runApp(const ZeebizApp());
}

class ZeebizApp extends StatefulWidget {
  const ZeebizApp({super.key});

  @override
  State<ZeebizApp> createState() => _ZeebizAppState();
}

class _ZeebizAppState extends State<ZeebizApp> {
  final _auth = AuthState();
  late final _router = buildRouter(_auth);

  @override
  void initState() {
    super.initState();
    _auth.bootstrap(); // Re-hydrate session from secure storage
  }

  @override
  void dispose() {
    _auth.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => ChangeNotifierProvider<AuthState>.value(
    value: _auth,
    child: MaterialApp.router(
      title: 'Zeebroo Business',
      theme: buildAppTheme(),
      routerConfig: _router,
      debugShowCheckedModeBanner: false,
    ),
  );
}
