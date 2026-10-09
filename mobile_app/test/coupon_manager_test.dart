import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zeebroo_mobile/core/theme/app_theme.dart';
import 'package:zeebroo_mobile/features/inventory/data/coupon_repository.dart';
import 'package:zeebroo_mobile/features/inventory/screens/campaign_overview_screen.dart';
import 'package:zeebroo_mobile/features/inventory/screens/coupon_detail_screen.dart';
import 'package:zeebroo_mobile/features/inventory/screens/coupon_form_screen.dart';
import 'package:zeebroo_mobile/features/inventory/screens/coupon_manager_screen.dart';

CouponData sample({Map<String, dynamic> overrides = const {}}) => CouponData({
  'id': 1,
  'name': 'Summer sale',
  'code': 'CP-TEST01',
  'discount_type': 'percent',
  'discount_value': 10,
  'quantity': 100,
  'used_count': 0,
  'remaining': 100,
  'status': 'active',
  'is_active': true,
  'valid_from': '2026-10-05',
  'expires_at': null,
  'notes': '',
  'redemptions': <Map<String, dynamic>>[],
  ...overrides,
});

class FakeCoupons extends CouponRepository {
  Map<String, dynamic>? saved;
  int? savedId;
  String? saveError;
  final requests = <({String query, String status})>[];
  CouponData current = sample();

  @override
  Future<String> generateCode() async => 'CP-GEN123';

  @override
  Future<List<CouponData>> list({String query = '', String status = ''}) async {
    requests.add((query: query, status: status));
    return [current];
  }

  @override
  Future<CouponData> detail(int id) async => current;

  @override
  Future<CouponData> save(Map<String, dynamic> data, {int? id}) async {
    if (saveError != null) throw Exception(saveError);
    saved = data;
    savedId = id;
    return sample(overrides: data);
  }
}

Future<void> phone(WidgetTester tester, Widget screen) async {
  await tester.binding.setSurfaceSize(const Size(320, 800));
  addTearDown(() => tester.binding.setSurfaceSize(null));
  await tester.pumpWidget(MaterialApp(theme: buildAppTheme(), home: screen));
  await tester.pumpAndSettle();
  expect(tester.takeException(), isNull);
}

Future<void> enter(WidgetTester tester, String key, String value) async {
  final field = find.byKey(ValueKey(key));
  await tester.ensureVisible(field);
  await tester.pumpAndSettle();
  await tester.enterText(field, value);
  await tester.pumpAndSettle();
}

Future<void> save(WidgetTester tester) async {
  FocusManager.instance.primaryFocus?.unfocus();
  await tester.pumpAndSettle();
  final button = find.byKey(const ValueKey('save-coupon'));
  await tester.scrollUntilVisible(
    button,
    300,
    scrollable: find.byType(Scrollable).first,
  );
  await tester.pumpAndSettle();
  await tester.tap(button);
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('Editing sends the coupon ID, flat discount and disabled state', (
    tester,
  ) async {
    final repo = FakeCoupons();
    await phone(
      tester,
      Builder(
        builder: (context) => Scaffold(
          body: TextButton(
            onPressed: () => Navigator.of(context).push<CouponData>(
              MaterialPageRoute(
                builder: (_) =>
                    CouponFormScreen(repository: repo, existing: sample()),
              ),
            ),
            child: const Text('Start'),
          ),
        ),
      ),
    );
    await tester.tap(find.text('Start'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Flat'));
    await tester.pumpAndSettle();
    await enter(tester, 'coupon-value', '250.50');
    final active = find.byType(SwitchListTile);
    await tester.ensureVisible(active);
    await tester.pumpAndSettle();
    await tester.tap(active);
    await save(tester);
    expect(repo.savedId, 1);
    expect(repo.saved, containsPair('discount_type', 'flat'));
    expect(repo.saved, containsPair('discount_value', 250.50));
    expect(repo.saved, containsPair('is_active', false));
    expect(find.text('Start'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('Campaign shows Discounts and Coupons without layout errors', (
    tester,
  ) async {
    await phone(tester, const CampaignOverviewScreen());
    expect(find.text('Discounts'), findsOneWidget);
    expect(find.text('Coupons'), findsOneWidget);
  });

  testWidgets('New coupon generates code and saves the complete payload', (
    tester,
  ) async {
    final repo = FakeCoupons();
    CouponData? result;
    await phone(
      tester,
      Builder(
        builder: (context) => Scaffold(
          body: TextButton(
            onPressed: () async =>
                result = await Navigator.of(context).push<CouponData>(
                  MaterialPageRoute(
                    builder: (_) => CouponFormScreen(repository: repo),
                  ),
                ),
            child: const Text('Start'),
          ),
        ),
      ),
    );
    await tester.tap(find.text('Start'));
    await tester.pumpAndSettle();
    await enter(tester, 'coupon-name', 'New promotion');
    await tester.ensureVisible(find.text('Generate'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Generate'));
    await tester.pumpAndSettle();
    expect(find.text('CP-GEN123'), findsOneWidget);
    await tester.ensureVisible(find.byType(CheckboxListTile));
    await tester.pumpAndSettle();
    await tester.tap(find.byType(CheckboxListTile));
    await save(tester);
    expect(repo.saved, containsPair('name', 'New promotion'));
    expect(repo.saved, containsPair('code', 'CP-GEN123'));
    expect(repo.saved, containsPair('discount_type', 'percent'));
    expect(repo.saved, containsPair('discount_value', 10.0));
    expect(repo.saved, containsPair('quantity', 100));
    expect(repo.saved, containsPair('expires_at', null));
    expect(repo.saved, containsPair('is_active', true));
    expect(repo.saved, containsPair('notes', null));
    expect(repo.savedId, isNull);
    expect(result?.id, 1);
    expect(find.text('Start'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'Edit rejects percentages above 100 and quantity below redeemed uses',
    (tester) async {
      final repo = FakeCoupons();
      await phone(
        tester,
        CouponFormScreen(
          repository: repo,
          existing: sample(overrides: {'used_count': 8}),
        ),
      );
      await enter(tester, 'coupon-value', '101');
      await enter(tester, 'coupon-quantity', '5');
      await save(tester);
      expect(repo.saved, isNull);
      expect(find.text('Percentage cannot exceed 100%'), findsOneWidget);
      expect(
        find.text('Cannot be below the 8 uses already redeemed'),
        findsOneWidget,
      );
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'New coupon requires an expiry date unless no expiry is selected',
    (tester) async {
      final repo = FakeCoupons();
      await phone(tester, CouponFormScreen(repository: repo));
      await enter(tester, 'coupon-name', 'Limited promotion');
      await enter(tester, 'coupon-code', 'SAVE10');
      await save(tester);
      expect(repo.saved, isNull);
      expect(
        find.text('Choose an end date or select No expiry date'),
        findsOneWidget,
      );
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('End date cannot precede the start date', (tester) async {
    final repo = FakeCoupons();
    await phone(
      tester,
      CouponFormScreen(
        repository: repo,
        existing: sample(
          overrides: {'valid_from': '2026-10-10', 'expires_at': '2026-10-09'},
        ),
      ),
    );
    await save(tester);
    expect(repo.saved, isNull);
    expect(find.text('End date cannot be before start date'), findsOneWidget);
  });

  testWidgets('List searches by name/code and applies server status filters', (
    tester,
  ) async {
    final repo = FakeCoupons();
    await phone(tester, CouponManagerScreen(repository: repo));
    expect(repo.requests.last.status, '');
    await tester.enterText(find.byType(TextField), 'SAVE');
    await tester.pump(const Duration(milliseconds: 350));
    await tester.pumpAndSettle();
    expect(repo.requests.last.query, 'SAVE');
    final used = find.widgetWithText(ChoiceChip, 'Used up');
    await tester.ensureVisible(used);
    await tester.pumpAndSettle();
    await tester.tap(used);
    await tester.pumpAndSettle();
    expect(repo.requests.last, (query: 'SAVE', status: 'used'));
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'Details show usage history and protect redeemed coupons from deletion',
    (tester) async {
      final repo = FakeCoupons();
      repo.current = sample(
        overrides: {
          'used_count': 1,
          'remaining': 99,
          'total_discount': 100,
          'redemptions': [
            {
              'id': 1,
              'sale_number': 'SALE-001',
              'user_name': 'Cashier',
              'discount_amount': 100,
              'reversed': false,
              'created_at': '2026-10-05T10:00:00Z',
            },
          ],
        },
      );
      await phone(
        tester,
        CouponDetailScreen(coupon: sample(), repository: repo),
      );
      await tester.drag(find.byType(ListView), const Offset(0, -500));
      await tester.pumpAndSettle();
      expect(find.text('SALE-001'), findsOneWidget);
      final delete = tester.widget<TextButton>(
        find.widgetWithText(TextButton, 'Delete coupon'),
      );
      expect(delete.onPressed, isNull);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('Server save errors keep the form open and preserve input', (
    tester,
  ) async {
    final repo = FakeCoupons()..saveError = 'Coupon code already exists';
    await phone(tester, CouponFormScreen(repository: repo, existing: sample()));
    await enter(tester, 'coupon-name', 'Edited promotion');
    await save(tester);
    expect(find.textContaining('Coupon code already exists'), findsOneWidget);
    expect(find.text('Edited promotion'), findsOneWidget);
    expect(find.text('Edit Coupon'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
