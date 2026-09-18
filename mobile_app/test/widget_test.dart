import 'package:flutter_test/flutter_test.dart';

import 'package:zeebroo_mobile/main.dart';

void main() {
  testWidgets('App boots to the login screen when unauthenticated', (WidgetTester tester) async {
    await tester.pumpWidget(const ZeebrooApp());
    await tester.pumpAndSettle();

    expect(find.text('Welcome back'), findsOneWidget);
  });
}
