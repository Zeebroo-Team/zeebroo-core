import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

import 'package:zeebroo_mobile/core/business/business_state.dart';
import 'package:zeebroo_mobile/features/home/widgets/app_side_drawer.dart';

void main() {
  testWidgets('Sidebar keeps management shortcuts without the Features group', (
    tester,
  ) async {
    await tester.pumpWidget(
      ChangeNotifierProvider(
        create: (_) => BusinessState(),
        child: const MaterialApp(
          home: Scaffold(
            body: AppSideDrawer(
              name: 'Test user',
              email: 'test@example.com',
              initials: 'TU',
            ),
          ),
        ),
      ),
    );

    expect(find.text('FEATURES'), findsNothing);
    for (final label in ['Inventory', 'Product', 'Contacts', 'Campaign']) {
      expect(find.text(label), findsOneWidget);
    }
    for (final label in [
      'Point of Sale',
      'Sales',
      'Products',
      'Stock',
      'CRM',
    ]) {
      expect(find.text(label), findsNothing);
    }
    expect(find.text('GENERAL'), findsOneWidget);
    expect(find.text('Notifications'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
