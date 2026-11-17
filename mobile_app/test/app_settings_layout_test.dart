import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:sqflite_common_ffi/sqflite_ffi.dart';
import 'package:zeebroo_mobile/features/settings/screens/app_settings_screen.dart';

void main() {
  setUpAll(() async {
    sqfliteFfiInit();
    databaseFactory = databaseFactoryFfi;
    await databaseFactory.setDatabasesPath(Directory.systemTemp.createTempSync('app_settings_layout_test').path);
    SharedPreferences.setMockInitialValues({'offline_products_enabled': true});
    FlutterSecureStorage.setMockInitialValues({'selected_business_id': '10'});
  });

  // A narrow phone with a large font size: the offline buttons must wrap
  // instead of overflowing (an overflow fails the test).
  testWidgets('offline buttons fit on a narrow phone with large text', (tester) async {
    tester.view.physicalSize = const Size(720, 1600);
    tester.view.devicePixelRatio = 2.0; // 360 x 800 logical
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      const MediaQuery(
        data: MediaQueryData(size: Size(360, 800), textScaler: TextScaler.linear(1.3)),
        child: MaterialApp(home: AppSettingsScreen()),
      ),
    );
    // Let the local databases open and the screen finish loading.
    for (var i = 0; i < 20 && find.text('Download latest').evaluate().isEmpty; i++) {
      await tester.runAsync(() => Future<void>.delayed(const Duration(milliseconds: 100)));
      await tester.pump();
    }

    expect(find.text('Download latest'), findsOneWidget);
    expect(find.text('Clear saved products'), findsOneWidget);
    await tester.scrollUntilVisible(find.text('View offline records'), 200);
    expect(find.text('View offline records'), findsOneWidget);
    expect(tester.takeException(), isNull);

    // Let the screen's background work (upload attempt, timeouts) finish.
    await tester.pumpWidget(const SizedBox());
    await tester.pump(const Duration(seconds: 30));
  });
}
