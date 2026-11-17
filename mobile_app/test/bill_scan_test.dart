import 'dart:async';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:image_picker/image_picker.dart';
import 'package:zeebroo_mobile/core/theme/app_theme.dart';
import 'package:zeebroo_mobile/features/finance/data/bill_scan_repository.dart';
import 'package:zeebroo_mobile/features/finance/widgets/bills_tab.dart';
import 'package:zeebroo_mobile/features/finance/widgets/finance_common.dart';

class FakeBillScanner extends BillScanRepository {
  final fields = <String, dynamic>{
    'name': 'Electricity bill',
    'bill_category': 'electricity',
    'payment_mode': 'recurring',
    'recurring_type': 'per_month',
    'agreement_valid_until_year': 2027,
    'due_date': '2026-10-20',
    'recurring_cost': 1234.5,
    'amount_varies_by_usage': false,
    'allow_split_payment': true,
    'description': 'Account 123456',
    'notes': 'October statement',
  };
  ImageSource? source;
  bool cancel = false;
  String? error;
  int scans = 0;
  int saves = 0;
  Map<String, dynamic>? saved;
  Completer<BillScanDraft>? pending;
  @override
  Future<({Map<String, dynamic> targets, List<Map<String, dynamic>> accounts})>
  loadOptions() async =>
      (targets: <String, dynamic>{}, accounts: <Map<String, dynamic>>[]);
  @override
  Future<XFile?> pick(ImageSource pickedSource) async {
    source = pickedSource;
    return cancel
        ? null
        : XFile.fromData(Uint8List.fromList([1]), name: 'bill.jpg');
  }

  @override
  Future<BillScanDraft> scan(XFile image) async {
    ++scans;
    if (error != null) throw Exception(error);
    if (pending != null) return pending!.future;
    return BillScanDraft.clean(fields);
  }

  @override
  Future<void> save(Map<String, dynamic> fields) async {
    ++saves;
    saved = fields;
  }
}

Future<void> openBill(WidgetTester tester, FakeBillScanner repo) async {
  await tester.binding.setSurfaceSize(const Size(320, 800));
  addTearDown(() => tester.binding.setSurfaceSize(null));
  await tester.pumpWidget(
    MaterialApp(
      theme: buildAppTheme(),
      home: Builder(
        builder: (context) => Scaffold(
          body: TextButton(
            child: const Text('Start'),
            onPressed: () => showModalBottomSheet(
              context: context,
              isScrollControlled: true,
              builder: (_) => AddBillSheet(repository: repo),
            ),
          ),
        ),
      ),
    ),
  );
  await tester.tap(find.text('Start'));
  await tester.pumpAndSettle();
  expect(tester.takeException(), isNull);
}

Future<void> scan(WidgetTester tester, String source) async {
  final button = find.byKey(const ValueKey('scan-bill'));
  await tester.ensureVisible(button);
  await tester.pumpAndSettle();
  await tester.tap(button);
  await tester.pumpAndSettle();
  expect(find.textContaining('sent to Google Gemini'), findsOneWidget);
  await tester.tap(find.text(source));
  await tester.pumpAndSettle();
}

Future<void> submit(WidgetTester tester) async {
  final button = find.widgetWithText(ElevatedButton, 'Add bill');
  await tester.ensureVisible(button);
  await tester.pumpAndSettle();
  await tester.tap(button);
  await tester.pumpAndSettle();
}

void main() {
  testWidgets(
    'Highlighted scan card uses a filled action and explains the scan',
    (tester) async {
      await openBill(tester, FakeBillScanner());
      expect(find.byKey(const ValueKey('bill-scan-card')), findsOneWidget);
      expect(find.text('Have the bill with you?'), findsOneWidget);
      expect(
        find.textContaining('You can change anything before saving.'),
        findsOneWidget,
      );
      expect(find.widgetWithText(FilledButton, 'Scan bill'), findsOneWidget);
      expect(find.text('Bill details'), findsOneWidget);
      expect(find.byKey(const ValueKey('bill-scan-notice')), findsNothing);
      expect(find.byIcon(Icons.auto_awesome_rounded), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'Only populated scanned fields sparkle and text edits clear individual markers',
    (tester) async {
      final repo = FakeBillScanner();
      repo.fields['remind_before_days'] = 0;
      await openBill(tester, repo);
      await scan(tester, 'Gallery');
      for (final field in repo.fields.keys) {
        expect(
          find.byKey(ValueKey('bill-sparkle-$field')),
          findsOneWidget,
          reason: field,
        );
      }
      expect(
        find.byKey(const ValueKey('bill-sparkle-first_installment_due_date')),
        findsNothing,
      );
      expect(
        find.byKey(const ValueKey('bill-sparkle-assignment_type')),
        findsNothing,
      );
      expect(find.byKey(const ValueKey('bill-scan-notice')), findsOneWidget);
      expect(
        find.textContaining(
          'Fields marked with a sparkle came from your bill.',
        ),
        findsOneWidget,
      );
      for (final entry in {
        'bill-name': 'name',
        'bill-amount': 'recurring_cost',
        'bill-agreement-year': 'agreement_valid_until_year',
        'bill-description': 'description',
        'bill-remind': 'remind_before_days',
        'bill-notes': 'notes',
      }.entries) {
        final field = find.byKey(ValueKey(entry.key));
        await tester.ensureVisible(field);
        await tester.pumpAndSettle();
        await tester.enterText(
          field,
          entry.key == 'bill-name' ? 'Edited bill' : '123',
        );
        await tester.pumpAndSettle();
        expect(
          find.byKey(ValueKey('bill-sparkle-${entry.value}')),
          findsNothing,
        );
      }
      expect(
        find.byKey(const ValueKey('bill-sparkle-bill_category')),
        findsOneWidget,
      );
      expect(
        find.byKey(const ValueKey('bill-sparkle-due_date')),
        findsOneWidget,
      );
      expect(repo.saves, 0);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'Dropdown, mode, switch and date edits clear only their own scan markers',
    (tester) async {
      await openBill(tester, FakeBillScanner());
      await scan(tester, 'Camera');
      final category = find.byKey(const ValueKey('bill-category-1'));
      await tester.ensureVisible(category);
      await tester.pumpAndSettle();
      await tester.tap(category);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Water').last);
      await tester.pumpAndSettle();
      expect(
        find.byKey(const ValueKey('bill-sparkle-bill_category')),
        findsNothing,
      );
      final cadence = find.byKey(const ValueKey('bill-cadence-1'));
      await tester.ensureVisible(cadence);
      await tester.pumpAndSettle();
      await tester.tap(cadence);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Per year').last);
      await tester.pumpAndSettle();
      expect(
        find.byKey(const ValueKey('bill-sparkle-recurring_type')),
        findsNothing,
      );
      final due = find.byType(FinanceDateField).first;
      await tester.ensureVisible(due);
      await tester.pumpAndSettle();
      await tester.tap(due);
      await tester.pumpAndSettle();
      await tester.tap(find.text('21').last);
      await tester.tap(find.text('OK'));
      await tester.pumpAndSettle();
      expect(find.byKey(const ValueKey('bill-sparkle-due_date')), findsNothing);
      for (final entry in {
        'Amount varies by usage': 'amount_varies_by_usage',
        'Allow split payment': 'allow_split_payment',
      }.entries) {
        final toggle = find.widgetWithText(SwitchListTile, entry.key);
        await tester.ensureVisible(toggle);
        await tester.pumpAndSettle();
        await tester.tap(toggle);
        await tester.pumpAndSettle();
        expect(
          find.byKey(ValueKey('bill-sparkle-${entry.value}')),
          findsNothing,
        );
      }
      final mode = find.byType(SegmentedButton<String>);
      await tester.ensureVisible(mode);
      await tester.pumpAndSettle();
      await tester.tap(find.text('One-time'));
      await tester.pumpAndSettle();
      expect(
        find.byKey(const ValueKey('bill-sparkle-payment_mode')),
        findsNothing,
      );
      expect(find.byKey(const ValueKey('bill-sparkle-name')), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'Notice dismissal preserves markers and a new scan restores the notice and markers',
    (tester) async {
      final repo = FakeBillScanner();
      await openBill(tester, repo);
      await scan(tester, 'Gallery');
      await tester.ensureVisible(find.byTooltip('Dismiss scan notice'));
      await tester.pumpAndSettle();
      await tester.tap(find.byTooltip('Dismiss scan notice'));
      await tester.pumpAndSettle();
      expect(find.byKey(const ValueKey('bill-scan-notice')), findsNothing);
      expect(find.byKey(const ValueKey('bill-sparkle-name')), findsOneWidget);
      final name = find.byKey(const ValueKey('bill-name'));
      await tester.ensureVisible(name);
      await tester.pumpAndSettle();
      await tester.enterText(name, 'Manual edit');
      await tester.pumpAndSettle();
      expect(find.byKey(const ValueKey('bill-sparkle-name')), findsNothing);
      await scan(tester, 'Camera');
      expect(find.byKey(const ValueKey('bill-scan-notice')), findsOneWidget);
      expect(find.byKey(const ValueKey('bill-sparkle-name')), findsOneWidget);
      expect(repo.saves, 0);
      expect(tester.takeException(), isNull);
    },
  );

  test(
    'Draft cleaning drops invalid values and ignores account/assignment IDs',
    () {
      final draft = BillScanDraft.clean({
        'name': ' CEB\nBill ',
        'bill_category': 'ELECTRICITY',
        'payment_mode': 'recurring',
        'recurring_type': 'per_week',
        'agreement_valid_until_year': 1900,
        'recurring_cost': 'LKR 1,234.50',
        'due_date': '2026-02-30',
        'first_installment_due_date': '10/11/2026',
        'remind_before_days': 367,
        'amount_varies_by_usage': 'maybe',
        'allow_split_payment': false,
        'account_id': 1,
        'employee_id': 2,
      }).values;
      expect(draft['name'], 'CEB Bill');
      expect(draft['bill_category'], 'electricity');
      expect(draft['recurring_cost'], 1234.5);
      for (final field in [
        'recurring_type',
        'agreement_valid_until_year',
        'due_date',
        'first_installment_due_date',
        'remind_before_days',
        'amount_varies_by_usage',
      ]) {
        expect(draft[field], isNull);
      }
      expect(draft['allow_split_payment'], false);
      expect(draft.containsKey('account_id'), false);
      expect(draft.containsKey('employee_id'), false);
      expect(
        BillScanDraft.clean({}).values.values.every((value) => value == null),
        true,
      );
    },
  );

  test(
    'Invalid amounts and text stay empty while zero and valid dates are preserved',
    () {
      for (final amount in [
        -1,
        double.nan,
        double.infinity,
        '1,2',
        '1.000,00',
        'unknown',
        10000000000,
      ]) {
        expect(
          BillScanDraft.clean({
            'recurring_cost': amount,
          }).values['recurring_cost'],
          isNull,
        );
      }
      final draft = BillScanDraft.clean({
        'recurring_cost': 0,
        'due_date': '2028-02-29',
        'name': ['bad'],
        'notes': 'x' * 5001,
      });
      expect(draft.values['recurring_cost'], 0.0);
      expect(draft.values['due_date'], '2028-02-29');
      expect(draft.values['name'], isNull);
      expect(draft.values['notes'], isNull);
    },
  );

  for (final source in ['Camera', 'Gallery']) {
    testWidgets(
      '$source scan fills the form but only user submission saves it',
      (tester) async {
        final repo = FakeBillScanner();
        await openBill(tester, repo);
        await scan(tester, source);
        expect(
          repo.source,
          source == 'Camera' ? ImageSource.camera : ImageSource.gallery,
        );
        expect(repo.scans, 1);
        expect(repo.saves, 0);
        expect(find.text('Electricity bill'), findsOneWidget);
        expect(find.text('1234.50'), findsOneWidget);
        expect(find.text('2027'), findsOneWidget);
        expect(find.text('Account 123456'), findsOneWidget);
        final due = tester
            .widgetList<FinanceDateField>(find.byType(FinanceDateField))
            .first;
        expect(due.value, DateTime(2026, 10, 20));
        final amount = find.byKey(const ValueKey('bill-amount'));
        await tester.ensureVisible(amount);
        await tester.pumpAndSettle();
        await tester.enterText(amount, '1400.00');
        await submit(tester);
        expect(repo.saves, 1);
        expect(repo.saved?['recurring_cost'], 1400.0);
        expect(repo.saved?['description'], 'Account 123456');
        expect(repo.saved?['due_date'], '2026-10-20');
        expect(repo.saved?['assignment_type'], 'none');
        expect(repo.saved?.containsKey('deduct_account_id'), false);
        expect(tester.takeException(), isNull);
      },
    );
  }

  testWidgets(
    'Missing scan settings remove manual defaults and cannot be saved silently',
    (tester) async {
      final repo = FakeBillScanner();
      repo.fields.clear();
      repo.fields.addAll({
        'name': 'Unknown bill',
        'recurring_cost': -5,
        'due_date': '2026-02-30',
      });
      await openBill(tester, repo);
      await scan(tester, 'Gallery');
      final segmented = tester.widget<SegmentedButton<String>>(
        find.byType(SegmentedButton<String>),
      );
      expect(segmented.selected, isEmpty);
      final category = tester.widget<DropdownButtonFormField<String>>(
        find.byKey(const ValueKey('bill-category-1')),
      );
      expect(category.initialValue, isNull);
      final amount = tester.widget<TextFormField>(
        find.byKey(const ValueKey('bill-amount')),
      );
      expect(amount.controller!.text, isEmpty);
      expect(find.byKey(const ValueKey('bill-varies-1')), findsOneWidget);
      expect(find.byKey(const ValueKey('bill-split-1')), findsOneWidget);
      await submit(tester);
      expect(repo.saves, 0);
      expect(find.text('Choose a category'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'Cancelling image selection preserves entered fields and makes no scan request',
    (tester) async {
      final repo = FakeBillScanner()..cancel = true;
      await openBill(tester, repo);
      await tester.enterText(
        find.byKey(const ValueKey('bill-name')),
        'Manual bill',
      );
      await scan(tester, 'Camera');
      expect(repo.scans, 0);
      expect(repo.saves, 0);
      expect(find.text('Manual bill'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('Scan failure preserves manual input and remains retryable', (
    tester,
  ) async {
    final repo = FakeBillScanner()..error = 'Bill scanning timed out';
    await openBill(tester, repo);
    await tester.enterText(
      find.byKey(const ValueKey('bill-name')),
      'Manual bill',
    );
    await scan(tester, 'Gallery');
    expect(find.text('Manual bill'), findsOneWidget);
    expect(find.textContaining('Bill scanning timed out'), findsOneWidget);
    expect(repo.saves, 0);
    final button = tester.widget<FilledButton>(
      find.byKey(const ValueKey('scan-bill')),
    );
    expect(button.onPressed, isNotNull);
  });

  testWidgets(
    'Saving and form edits are disabled while Gemini is reading the image',
    (tester) async {
      final repo = FakeBillScanner()..pending = Completer<BillScanDraft>();
      await openBill(tester, repo);
      await tester.tap(find.byKey(const ValueKey('scan-bill')));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Gallery'));
      await tester.pump(const Duration(milliseconds: 400));
      expect(find.text('Reading your bill...'), findsOneWidget);
      final save = tester.widget<ElevatedButton>(
        find.widgetWithText(ElevatedButton, 'Add bill'),
      );
      expect(save.onPressed, isNull);
      expect(
        tester
            .widget<AbsorbPointer>(
              find
                  .descendant(
                    of: find.byType(AddBillSheet),
                    matching: find.byType(AbsorbPointer),
                  )
                  .first,
            )
            .absorbing,
        true,
      );
      repo.pending!.complete(BillScanDraft.clean(repo.fields));
      await tester.pumpAndSettle();
      expect(find.text('Electricity bill'), findsOneWidget);
      expect(repo.saves, 0);
      expect(tester.takeException(), isNull);
    },
  );
}
