import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zeebroo_mobile/core/theme/app_theme.dart';
import 'package:zeebroo_mobile/features/pos/widgets/checkout_code_entry.dart';

void main() {
  for (final width in [320.0, 375.0]) {
    testWidgets('Checkout codes can open, type and apply at width $width', (
      tester,
    ) async {
      await tester.binding.setSurfaceSize(Size(width, 800));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final coupon = TextEditingController();
      final gift = TextEditingController();
      addTearDown(coupon.dispose);
      addTearDown(gift.dispose);
      var opened = false;
      var loading = false;
      String? error;
      String? submitted;
      late StateSetter update;

      await tester.pumpWidget(
        MaterialApp(
          theme: buildAppTheme(),
          home: Scaffold(
            body: StatefulBuilder(
              builder: (context, setState) {
                update = setState;
                return ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    if (!opened)
                      TextButton(
                        onPressed: () => setState(() => opened = true),
                        child: const Text('Apply coupon'),
                      )
                    else ...[
                      CheckoutCodeEntry(
                        controller: coupon,
                        hint: 'Coupon code',
                        color: Colors.teal,
                        loading: loading,
                        error: error,
                        onApply: () async {
                          submitted = coupon.text;
                          setState(() => loading = true);
                        },
                        onCancel: () => setState(() => opened = false),
                      ),
                      const SizedBox(height: 8),
                      CheckoutCodeEntry(
                        controller: gift,
                        hint: 'Gift card code',
                        color: Colors.pink,
                        loading: false,
                        error: null,
                        onApply: () async => submitted = gift.text,
                        onCancel: gift.clear,
                      ),
                    ],
                  ],
                );
              },
            ),
          ),
        ),
      );

      await tester.tap(find.text('Apply coupon'));
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      expect(find.byType(TextField), findsNWidgets(2));
      await tester.enterText(find.byType(TextField).first, 'SAVE10');
      await tester.tap(find.widgetWithText(ElevatedButton, 'Apply').first);
      await tester.pump();
      expect(submitted, 'SAVE10');
      expect(find.byType(CircularProgressIndicator), findsOneWidget);
      expect(tester.takeException(), isNull);

      update(() {
        loading = false;
        error = 'Coupon not found.';
      });
      await tester.pumpAndSettle();
      expect(find.text('Coupon not found.'), findsOneWidget);
      await tester.enterText(find.byType(TextField).last, 'GIFT001');
      await tester.tap(find.widgetWithText(ElevatedButton, 'Apply').last);
      await tester.pumpAndSettle();
      expect(submitted, 'GIFT001');
      await tester.tap(find.byTooltip('Cancel').first);
      await tester.pumpAndSettle();
      expect(find.text('Apply coupon'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });
  }
}
