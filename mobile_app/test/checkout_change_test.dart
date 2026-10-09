import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zeebroo_mobile/core/theme/app_theme.dart';
import 'package:zeebroo_mobile/features/pos/models/pos_cart_item.dart';
import 'package:zeebroo_mobile/features/pos/screens/pos_screen.dart';

Future<void> openCheckout(
  WidgetTester tester, {
  double itemDiscountPct = 0,
}) async {
  await tester.binding.setSurfaceSize(const Size(430, 932));
  addTearDown(() => tester.binding.setSurfaceSize(null));
  await tester.pumpWidget(
    MaterialApp(
      theme: buildAppTheme(),
      home: PosScreen(
        initialCart: [
          PosCartItem(
            product: {
              'id': 1,
              'name': 'Test product',
              'unit_sell_price': 6125.0,
              'stock_quantity': 20,
            },
            itemDiscountPct: itemDiscountPct,
          ),
        ],
      ),
    ),
  );
  await tester.pumpAndSettle();
  await tester.tap(find.byIcon(Icons.shopping_cart_rounded));
  await tester.pumpAndSettle();
  await tester.ensureVisible(find.widgetWithText(TextField, 'Amount paid'));
  await tester.pumpAndSettle();
  expect(tester.takeException(), isNull);
}

Text changeText(WidgetTester tester) {
  final panel = find
      .ancestor(of: find.text('Change'), matching: find.byType(Column))
      .first;
  return tester.widget<Text>(
    find.descendant(of: panel, matching: find.byType(Text)).last,
  );
}

void main() {
  testWidgets(
    'Change updates while typing without submitting or losing focus',
    (tester) async {
      await openCheckout(tester);
      final amount = find.widgetWithText(TextField, 'Amount paid');
      expect(changeText(tester).data, '0.00');
      for (final entry in [
        ('7000', '875.00'),
        ('8000.50', '1,875.50'),
        ('6125', '0.00'),
      ]) {
        await tester.enterText(amount, entry.$1);
        await tester.pump();
        expect(changeText(tester).data, entry.$2);
        expect(changeText(tester).style!.color, AppColors.success);
        expect(tester.widget<TextField>(amount).controller!.text, entry.$1);
        expect(FocusManager.instance.primaryFocus?.hasFocus, isTrue);
      }
      await tester.enterText(amount, '6000');
      await tester.pump();
      expect(changeText(tester).data, '125.00');
      expect(changeText(tester).style!.color, AppColors.error);
      await tester.enterText(amount, '');
      await tester.pump();
      expect(changeText(tester).data, '6,125.00');
      expect(changeText(tester).style!.color, AppColors.error);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox());
      await tester.pumpAndSettle();
    },
  );

  testWidgets('Controller changes also refresh the displayed change', (
    tester,
  ) async {
    await openCheckout(tester);
    tester
            .widget<TextField>(find.widgetWithText(TextField, 'Amount paid'))
            .controller!
            .text =
        '7500';
    await tester.pump();
    expect(changeText(tester).data, '1,375.00');
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(const SizedBox());
    await tester.pumpAndSettle();
  });

  testWidgets('Live change uses the total after item discounts', (
    tester,
  ) async {
    await openCheckout(tester, itemDiscountPct: 10);
    await tester.enterText(
      find.widgetWithText(TextField, 'Amount paid'),
      '6000',
    );
    await tester.pump();
    expect(changeText(tester).data, '487.50');
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(const SizedBox());
    await tester.pumpAndSettle();
  });
}
