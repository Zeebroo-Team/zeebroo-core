import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zeebroo_mobile/core/theme/app_theme.dart';
import 'package:zeebroo_mobile/features/inventory/data/gift_card_repository.dart';
import 'package:zeebroo_mobile/features/inventory/screens/campaign_overview_screen.dart';
import 'package:zeebroo_mobile/features/inventory/screens/gift_card_detail_screen.dart';
import 'package:zeebroo_mobile/features/inventory/screens/gift_card_form_screen.dart';
import 'package:zeebroo_mobile/features/inventory/screens/gift_card_manager_screen.dart';

GiftCardRecord card({Map<String, dynamic> overrides = const {}}) =>
    GiftCardRecord({
      'id': 11,
      'group_id': 1,
      'name': 'Birthday',
      'code': 'GC-AAAA-BBBB-CCCC',
      'initial_value': 2000,
      'balance': 2000,
      'used_amount': 0,
      'valid_from': '2026-10-05',
      'expires_at': null,
      'is_active': true,
      'status': 'active',
      'notes': '',
      'customer_name': 'Akila',
      'transactions': [
        {
          'id': 1,
          'type': 'issue',
          'amount': 2000,
          'balance_after': 2000,
          'created_at': '2026-10-05T10:00:00Z',
          'notes': 'Gift card issued',
        },
      ],
      ...overrides,
    });

GiftCardRecord group({Map<String, dynamic> overrides = const {}}) =>
    GiftCardRecord({
      'id': 1,
      'name': 'Birthday',
      'initial_value': 2000,
      'card_count': 1,
      'total_value': 2000,
      'total_balance': 2000,
      'valid_from': '2026-10-05',
      'expires_at': null,
      'is_active': true,
      'notes': '',
      'status_counts': {'active': 1},
      'cards': [card().values],
      ...overrides,
    }, isGroup: true);

class FakeGiftCards extends GiftCardRepository {
  Map<String, dynamic>? saved;
  GiftCardRecord? savedExisting;
  String? saveError;
  GiftCardRecord currentGroup = group();
  GiftCardRecord currentCard = card();
  final requests = <({String query, String status})>[];
  int? addedGroupId;
  int? addedQuantity;
  GiftCardRecord? deleted;

  @override
  Future<String> generateCode() async => 'GC-GENX-GENY-GENZ';
  @override
  Future<List<GiftCardRecord>> list({
    String query = '',
    String status = '',
  }) async {
    requests.add((query: query, status: status));
    return [currentGroup];
  }

  @override
  Future<GiftCardRecord> detail(GiftCardRecord record) async =>
      record.isGroup ? currentGroup : currentCard;
  @override
  Future<GiftCardRecord> save(
    Map<String, dynamic> data, {
    GiftCardRecord? existing,
  }) async {
    if (saveError != null) throw Exception(saveError);
    saved = data;
    savedExisting = existing;
    return existing == null || existing.isGroup
        ? group(overrides: data)
        : card(overrides: data);
  }

  @override
  Future<GiftCardRecord> addCards(int groupId, int quantity) async {
    addedGroupId = groupId;
    addedQuantity = quantity;
    currentGroup = group(
      overrides: {
        'card_count': 1 + quantity,
        'total_balance': 2000 * (1 + quantity),
        'total_value': 2000 * (1 + quantity),
        'status_counts': {'active': 1 + quantity},
      },
    );
    return currentGroup;
  }

  @override
  Future<void> delete(GiftCardRecord record) async {
    deleted = record;
  }
}

Future<void> phone(WidgetTester tester, Widget screen) async {
  await tester.binding.setSurfaceSize(const Size(320, 800));
  addTearDown(() => tester.binding.setSurfaceSize(null));
  await tester.pumpWidget(MaterialApp(theme: buildAppTheme(), home: screen));
  await tester.pumpAndSettle();
  expect(tester.takeException(), isNull);
}

Future<void> openForm(
  WidgetTester tester,
  FakeGiftCards repo, {
  GiftCardRecord? existing,
}) async {
  await phone(
    tester,
    Builder(
      builder: (context) => Scaffold(
        body: TextButton(
          onPressed: () => Navigator.of(context).push<GiftCardRecord>(
            MaterialPageRoute(
              builder: (_) =>
                  GiftCardFormScreen(repository: repo, existing: existing),
            ),
          ),
          child: const Text('Start'),
        ),
      ),
    ),
  );
  await tester.tap(find.text('Start'));
  await tester.pumpAndSettle();
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
  final button = find.byKey(const ValueKey('save-gift'));
  await tester.scrollUntilVisible(
    button,
    300,
    scrollable: find.byType(Scrollable).first,
  );
  await tester.pumpAndSettle();
  await tester.tap(button);
  await tester.pumpAndSettle();
}

Future<void> noExpiry(WidgetTester tester) async {
  await tester.ensureVisible(find.byType(CheckboxListTile));
  await tester.pumpAndSettle();
  await tester.tap(find.byType(CheckboxListTile));
  await tester.pumpAndSettle();
}

void main() {
  for (final isGroup in [true, false]) {
    testWidgets(
      'Deleting ${isGroup ? 'group' : 'card'} requires confirmation and targets the correct record',
      (tester) async {
        final repo = FakeGiftCards();
        final record = isGroup ? group() : card();
        await phone(
          tester,
          Builder(
            builder: (context) => Scaffold(
              body: TextButton(
                onPressed: () => Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) =>
                        GiftCardDetailScreen(record: record, repository: repo),
                  ),
                ),
                child: const Text('Start'),
              ),
            ),
          ),
        );
        await tester.tap(find.text('Start'));
        await tester.pumpAndSettle();
        final delete = find.widgetWithText(
          TextButton,
          isGroup ? 'Delete Group' : 'Delete Gift Card',
        );
        await tester.scrollUntilVisible(
          delete,
          300,
          scrollable: find.byType(Scrollable).first,
        );
        await tester.pumpAndSettle();
        await tester.tap(delete);
        await tester.pumpAndSettle();
        expect(repo.deleted, isNull);
        await tester.tap(find.text('Cancel'));
        await tester.pumpAndSettle();
        expect(repo.deleted, isNull);
        await tester.tap(delete);
        await tester.pumpAndSettle();
        await tester.tap(find.widgetWithText(TextButton, 'Delete'));
        await tester.pumpAndSettle();
        expect(repo.deleted?.id, record.id);
        expect(repo.deleted?.isGroup, isGroup);
        expect(find.text('Start'), findsOneWidget);
        expect(tester.takeException(), isNull);
      },
    );
  }

  testWidgets(
    'Campaign shows animated gift-card access alongside existing cards',
    (tester) async {
      await phone(tester, const CampaignOverviewScreen());
      expect(find.text('Gift Card'), findsOneWidget);
      expect(find.text('Coupons'), findsOneWidget);
      expect(find.text('Discounts'), findsOneWidget);
    },
  );

  testWidgets(
    'Single card uses a generated unique code and saves all settings',
    (tester) async {
      final repo = FakeGiftCards();
      await openForm(tester, repo);
      expect(find.text('GC-GENX-GENY-GENZ'), findsOneWidget);
      await enter(tester, 'gift-name', 'Birthday promotion');
      await enter(tester, 'gift-value', '2000');
      await noExpiry(tester);
      await save(tester);
      expect(repo.saved, containsPair('name', 'Birthday promotion'));
      expect(repo.saved, containsPair('code', 'GC-GENX-GENY-GENZ'));
      expect(repo.saved, containsPair('initial_value', 2000.0));
      expect(repo.saved, containsPair('quantity', 1));
      expect(repo.saved, containsPair('expires_at', null));
      expect(repo.saved, containsPair('notes', null));
      expect(repo.saved, containsPair('is_active', true));
      expect(repo.savedExisting, isNull);
      expect(find.text('Start'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'Batch creation hides custom code and lets the server issue unique codes',
    (tester) async {
      final repo = FakeGiftCards();
      await openForm(tester, repo);
      await enter(tester, 'gift-name', 'Batch');
      await enter(tester, 'gift-value', '500');
      await enter(tester, 'gift-quantity', '3');
      expect(find.byKey(const ValueKey('gift-code')), findsNothing);
      await noExpiry(tester);
      await save(tester);
      expect(repo.saved, containsPair('quantity', 3));
      expect(repo.saved, isNot(contains('code')));
      expect(repo.saved, containsPair('initial_value', 500.0));
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('Batch creation enforces the 500-card limit', (tester) async {
    final repo = FakeGiftCards();
    await openForm(tester, repo);
    await enter(tester, 'gift-name', 'Batch');
    await enter(tester, 'gift-value', '500');
    await enter(tester, 'gift-quantity', '501');
    await noExpiry(tester);
    await save(tester);
    expect(repo.saved, isNull);
    expect(find.text('Enter a whole number from 1 to 500'), findsOneWidget);
  });

  testWidgets('Card edit rejects values below the amount already spent', (
    tester,
  ) async {
    final repo = FakeGiftCards();
    await openForm(
      tester,
      repo,
      existing: card(overrides: {'used_amount': 500, 'balance': 1500}),
    );
    await enter(tester, 'gift-value', '499');
    await save(tester);
    expect(repo.saved, isNull);
    expect(find.text('Cannot be below 500.00 already spent'), findsOneWidget);
  });

  testWidgets(
    'Individual edit preserves group/customer association and updates only card fields',
    (tester) async {
      final repo = FakeGiftCards();
      await openForm(tester, repo, existing: card());
      expect(find.byKey(const ValueKey('gift-name')), findsNothing);
      expect(find.byKey(const ValueKey('gift-quantity')), findsNothing);
      await enter(tester, 'gift-value', '2500.50');
      await enter(tester, 'gift-code', 'gc-edited');
      await save(tester);
      expect(repo.savedExisting?.id, 11);
      expect(repo.savedExisting?.isGroup, false);
      expect(repo.saved, containsPair('initial_value', 2500.50));
      expect(repo.saved, containsPair('code', 'GC-EDITED'));
      for (final key in [
        'name',
        'quantity',
        'pos_customer_id',
        'group_id',
        'balance',
      ]) {
        expect(repo.saved, isNot(contains(key)));
      }
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'Group edit excludes monetary fields and supports disabling the group',
    (tester) async {
      final repo = FakeGiftCards();
      await openForm(tester, repo, existing: group());
      expect(find.byKey(const ValueKey('gift-value')), findsNothing);
      expect(find.byKey(const ValueKey('gift-code')), findsNothing);
      await enter(tester, 'gift-name', 'Changed group');
      await tester.ensureVisible(find.byType(SwitchListTile));
      await tester.pumpAndSettle();
      await tester.tap(find.byType(SwitchListTile));
      await save(tester);
      expect(repo.savedExisting?.isGroup, true);
      expect(repo.saved, containsPair('name', 'Changed group'));
      expect(repo.saved, containsPair('is_active', false));
      for (final key in ['initial_value', 'quantity', 'code', 'balance']) {
        expect(repo.saved, isNot(contains(key)));
      }
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('Expiry must be present or explicitly disabled', (tester) async {
    final repo = FakeGiftCards();
    await openForm(tester, repo);
    await enter(tester, 'gift-name', 'Date test');
    await enter(tester, 'gift-value', '1000');
    await save(tester);
    expect(repo.saved, isNull);
    expect(
      find.text('Choose an end date or select No expiry date'),
      findsOneWidget,
    );
  });

  testWidgets('Expiry cannot precede start date', (tester) async {
    final repo = FakeGiftCards();
    await openForm(
      tester,
      repo,
      existing: group(
        overrides: {'valid_from': '2026-10-10', 'expires_at': '2026-10-09'},
      ),
    );
    await save(tester);
    expect(repo.saved, isNull);
    expect(find.text('End date cannot be before start date'), findsOneWidget);
  });

  testWidgets(
    'Search and status filters use the group API and cards can expand',
    (tester) async {
      final repo = FakeGiftCards();
      await phone(tester, GiftCardManagerScreen(repository: repo));
      await tester.tap(find.text('View cards'));
      await tester.pumpAndSettle();
      expect(find.text('GC-AAAA-BBBB-CCCC'), findsOneWidget);
      await tester.enterText(find.byType(TextField), 'GC-AAAA');
      await tester.pump(const Duration(milliseconds: 350));
      await tester.pumpAndSettle();
      expect(repo.requests.last.query, 'GC-AAAA');
      final used = find.widgetWithText(ChoiceChip, 'Used');
      await tester.ensureVisible(used);
      await tester.pumpAndSettle();
      await tester.tap(used);
      await tester.pumpAndSettle();
      expect(repo.requests.last, (query: 'GC-AAAA', status: 'used'));
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('Generate more validates quantity and refreshes group balance', (
    tester,
  ) async {
    final repo = FakeGiftCards();
    await phone(
      tester,
      GiftCardDetailScreen(record: group(), repository: repo),
    );
    final button = find.byKey(const ValueKey('generate-more-gifts'));
    await tester.ensureVisible(button);
    await tester.pumpAndSettle();
    await tester.tap(button);
    await tester.pumpAndSettle();
    await enter(tester, 'add-gifts-quantity', '0');
    await tester.tap(find.text('Generate'));
    await tester.pumpAndSettle();
    expect(find.text('Enter 1–500 cards'), findsOneWidget);
    expect(repo.addedQuantity, isNull);
    await enter(tester, 'add-gifts-quantity', '2');
    expect(find.text('Total issued value: 4,000.00'), findsOneWidget);
    await tester.tap(find.text('Generate'));
    await tester.pumpAndSettle();
    expect(repo.addedGroupId, 1);
    expect(repo.addedQuantity, 2);
    expect(find.text('6,000.00'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'History shows issue and refunds and prevents deletion even after full refund',
    (tester) async {
      final repo = FakeGiftCards();
      repo.currentCard = card(
        overrides: {
          'transactions': [
            {'type': 'issue', 'amount': 2000, 'balance_after': 2000},
            {
              'type': 'redeem',
              'amount': -500,
              'balance_after': 1500,
              'sale_number': 'SALE-001',
            },
            {
              'type': 'refund',
              'amount': 500,
              'balance_after': 2000,
              'sale_number': 'SALE-001',
            },
          ],
        },
      );
      await phone(
        tester,
        GiftCardDetailScreen(record: card(), repository: repo),
      );
      await tester.drag(find.byType(ListView), const Offset(0, -500));
      await tester.pumpAndSettle();
      expect(find.text('Issued'), findsOneWidget);
      expect(find.text('Redeemed · SALE-001'), findsOneWidget);
      expect(find.text('Refunded · SALE-001'), findsOneWidget);
      final button = tester.widget<TextButton>(
        find.widgetWithText(TextButton, 'Delete Gift Card'),
      );
      expect(button.onPressed, isNull);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('Duplicate-code error keeps form open and input preserved', (
    tester,
  ) async {
    final repo = FakeGiftCards()
      ..saveError = 'This gift card code is already in use.';
    await openForm(tester, repo, existing: card());
    await enter(tester, 'gift-code', 'GC-DUPLICATE');
    await save(tester);
    expect(find.textContaining('already in use'), findsOneWidget);
    expect(find.text('GC-DUPLICATE'), findsOneWidget);
    expect(find.text('Edit Gift Card'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
