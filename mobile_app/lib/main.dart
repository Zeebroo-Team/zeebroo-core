import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

// DEV MOBILE PREVIEW — remove this import and the DevicePreview wrapping
// below (marked START/END) once development is done, then drop
// `device_preview` from pubspec.yaml.
import 'package:device_preview/device_preview.dart';

import 'app_router.dart';
import 'core/auth/auth_state.dart';
import 'core/business/business_state.dart';
import 'core/theme/app_theme.dart';

void main() {
  // DEV MOBILE PREVIEW — START
  runApp(
    DevicePreview(enabled: kIsWeb, builder: (context) => const ZeebrooApp()),
  );
  // DEV MOBILE PREVIEW — END
}

class ZeebrooApp extends StatefulWidget {
  const ZeebrooApp({super.key});

  @override
  State<ZeebrooApp> createState() => _ZeebrooAppState();
}

class _ZeebrooAppState extends State<ZeebrooApp> {
  final _authState = AuthState();
  final _businessState = BusinessState();
  late final _router = buildRouter(_authState, _businessState);

  @override
  void initState() {
    super.initState();
    _authState.bootstrap();
    _businessState.bootstrap();
  }

  @override
  Widget build(BuildContext context) => MultiProvider(
    providers: [
      ChangeNotifierProvider.value(value: _authState),
      ChangeNotifierProvider.value(value: _businessState),
    ],
    child: MaterialApp.router(
      title: 'Zeebroo',
      debugShowCheckedModeBanner: false,
      theme: buildAppTheme(),
      routerConfig: _router,
      // DEV MOBILE PREVIEW — remove these three lines together with the
      // DevicePreview wrapping in main() above.
      useInheritedMediaQuery: true,
      locale: DevicePreview.locale(context),
      builder: DevicePreview.appBuilder,
    ),
  );
}
