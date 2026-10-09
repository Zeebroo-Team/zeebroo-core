import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zeebroo_mobile/core/theme/app_theme.dart';
import 'package:zeebroo_mobile/features/finance/widgets/bills_tab.dart';
import 'bill_scan_test.dart' show FakeBillScanner;

class EditingRepository extends FakeBillScanner {
  int? updatedId;
  String? updateError;
  @override
  Future<void> update(int id, Map<String, dynamic> fields) async {
    if (updateError != null) throw Exception(updateError);
    updatedId = id;
    saved = fields;
  }
}

Future<void> openForm(
  WidgetTester tester,
  EditingRepository repo, {
  bool editing = true,
}) async {
  await tester.binding.setSurfaceSize(const Size(400, 900));
  addTearDown(() => tester.binding.setSurfaceSize(null));
  await tester.pumpWidget(
    MaterialApp(
      theme: buildAppTheme(),
      home: Builder(
        builder: (context) => Scaffold(
          body: TextButton(
            child: const Text('Open'),
            onPressed: () => showModalBottomSheet<bool>(
              context: context,
              isScrollControlled: true,
              builder: (_) => AddBillSheet(
                repository: repo,
                enableScan: false,
                bill: editing
                    ? {
                        ...repo.fields,
                        'id': 42,
                        'assignment_type': 'branch',
                        'branch_id': 7,
                        'deduct_account_id': 9,
                      }
                    : null,
              ),
            ),
          ),
        ),
      ),
    ),
  );
  await tester.tap(find.text('Open'));
  await tester.pumpAndSettle();
}

Future<void> saveEdit(WidgetTester tester) async {
  FocusManager.instance.primaryFocus?.unfocus();
  await tester.pumpAndSettle();
  final button = find.widgetWithText(ElevatedButton, 'Save changes');
  await tester.ensureVisible(button);
  await tester.pumpAndSettle();
  await tester.tap(button);
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('Manager add form does not offer scanning', (tester) async {
    await openForm(tester, EditingRepository(), editing: false);
    expect(find.text('Bill details'), findsOneWidget);
    expect(find.byKey(const ValueKey('scan-bill')), findsNothing);
    expect(find.text('Have the bill with you?'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('Edit prefills values, preserves IDs and clears optional text', (
    tester,
  ) async {
    final repo = EditingRepository();
    await openForm(tester, repo);
    expect(find.text('Edit bill'), findsOneWidget);
    expect(find.text('Electricity bill'), findsOneWidget);
    expect(find.byKey(const ValueKey('scan-bill')), findsNothing);
    await tester.enterText(
      find.byKey(const ValueKey('bill-name')),
      'Updated bill',
    );
    final description = find.byKey(const ValueKey('bill-description'));
    await tester.ensureVisible(description);
    await tester.enterText(description, '');
    await saveEdit(tester);
    expect(repo.updatedId, 42);
    expect(repo.saves, 0);
    expect(repo.saved!['name'], 'Updated bill');
    expect(repo.saved!['description'], isNull);
    expect(repo.saved!['branch_id'], 7);
    expect(repo.saved!['deduct_account_id'], 9);
    expect(repo.saved!['recurring_cost'], 1234.5);
    expect(repo.saved!['due_date'], '2026-10-20');
    expect(find.byType(AddBillSheet), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('Update errors keep the form open without creating a new bill', (
    tester,
  ) async {
    final repo = EditingRepository()..updateError = 'Unable to update bill';
    await openForm(tester, repo);
    await saveEdit(tester);
    expect(find.byType(AddBillSheet), findsOneWidget);
    expect(find.textContaining('Unable to update bill'), findsOneWidget);
    expect(repo.saves, 0);
    expect(repo.updatedId, isNull);
    expect(tester.takeException(), isNull);
  });
}
