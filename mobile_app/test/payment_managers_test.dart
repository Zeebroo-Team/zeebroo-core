import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zeebroo_mobile/core/api/api_endpoints.dart';
import 'package:zeebroo_mobile/core/theme/app_theme.dart';
import 'package:zeebroo_mobile/features/payments/data/payment_manager_repository.dart';
import 'package:zeebroo_mobile/features/payments/screens/payment_manager_screen.dart';
import 'package:zeebroo_mobile/features/payments/screens/payments_overview_screen.dart';

class FakePayments extends PaymentManagerRepository {
  FakePayments(this.kind);
  final PaymentManagerKind kind;
  int? savedId;
  Map<String, dynamic>? saved;
  int deletes = 0;
  int lists = 0;
  String? saveError;
  Map<String, dynamic> get record => {
    'id': 42,
    'name': 'Existing record',
    'property_type': 'Shop',
    'bank_id': 7,
    'borrowed_amount': 10000,
    'interest_rate': 12,
    'interest_rate_type': 'percentage',
    'recurring_type': 'per_month',
    'deduct_account_id': 9,
    'remind_before_days': 3,
    'recurring_cost': 1500,
    'agreement_valid_until_year': 2027,
    'actual_due_date': null,
    'first_installment_due_date': '2026-10-10',
    'landlord': {'name': 'Landlord', 'phone': '0771234567', 'notes': 'Keep me'},
    'assignment_type': 'property',
    'assignment_reference': '6',
    'property_work_type': 'other',
    'property_work_type_other': 'Roof work',
    'estimated_cost': 3000,
    'description': 'Original description',
  };
  @override
  Future<dynamic> get(String path) async {
    if (path == ApiEndpoints.accounts) {
      return {
        'data': [
          {'id': 9, 'account_name': 'Account'},
        ],
      };
    }
    if (path == ApiEndpoints.banks) {
      return {
        'data': [
          {'id': 7, 'name': 'Bank'},
        ],
      };
    }
    if (path == ApiEndpoints.financeBillAssignmentTargets) {
      return {
        'data': {
          'properties': [
            {'id': 6, 'name': 'Property'},
          ],
        },
      };
    }
    if (path.endsWith('/42')) return {'data': record};
    lists++;
    return {
      'data': deletes == 0 ? [record] : [],
    };
  }

  @override
  Future<void> save(String path, Map<String, dynamic> fields, {int? id}) async {
    if (saveError != null) throw Exception(saveError);
    savedId = id;
    saved = fields;
  }

  @override
  Future<void> delete(String path, int id) async {
    deletes++;
  }
}

Future<void> openManager(WidgetTester tester, FakePayments repo) async {
  await tester.binding.setSurfaceSize(const Size(400, 850));
  addTearDown(() => tester.binding.setSurfaceSize(null));
  await tester.pumpWidget(
    MaterialApp(
      theme: buildAppTheme(),
      home: PaymentManagerScreen(kind: repo.kind, repository: repo),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('Payments has all four manager cards without overflow', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(320, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await tester.pumpWidget(
      MaterialApp(theme: buildAppTheme(), home: const PaymentsOverviewScreen()),
    );
    await tester.pumpAndSettle();
    for (final name in [
      'Bill Manager',
      'Loan Manager',
      'Rental Manager',
      'Modification Manager',
    ]) {
      expect(find.text(name), findsOneWidget);
    }
    expect(tester.takeException(), isNull);
  });

  for (final kind in PaymentManagerKind.values) {
    testWidgets('${kind.name} edit preserves full record and refreshes list', (
      tester,
    ) async {
      final repo = FakePayments(kind);
      await openManager(tester, repo);
      await tester.tap(find.byTooltip('Edit'));
      await tester.pumpAndSettle();
      expect(find.text('Edit ${kind.name}'), findsOneWidget);
      final submit = find.widgetWithText(ElevatedButton, 'Save changes');
      await tester.ensureVisible(submit);
      await tester.pumpAndSettle();
      await tester.tap(submit);
      await tester.pumpAndSettle();
      expect(repo.savedId, 42);
      expect(repo.lists, 2);
      if (kind == PaymentManagerKind.loan) {
        expect(repo.saved!['bank_id'], 7);
        expect(repo.saved!['description'], 'Original description');
      } else if (kind == PaymentManagerKind.rental) {
        expect(repo.saved!['owner_phone'], '0771234567');
        expect(repo.saved!['owner_notes'], 'Keep me');
        expect(repo.saved!['due_date'], isNull);
        expect(repo.saved!['first_installment_due_date'], '2026-10-10');
      } else {
        expect(repo.saved!['assignment_reference'], 6);
        expect(repo.saved!['property_work_type_other'], 'Roof work');
      }
      expect(tester.takeException(), isNull);
    });

    testWidgets('${kind.name} search and confirmed deletion', (tester) async {
      final repo = FakePayments(kind);
      await openManager(tester, repo);
      await tester.enterText(find.byType(TextField), 'no match');
      await tester.pumpAndSettle();
      expect(find.text('No matching results.'), findsOneWidget);
      await tester.enterText(find.byType(TextField), '');
      FocusManager.instance.primaryFocus?.unfocus();
      await tester.pumpAndSettle();
      await tester.tap(find.byTooltip('Delete'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Cancel'));
      await tester.pumpAndSettle();
      expect(repo.deletes, 0);
      await tester.tap(find.byTooltip('Delete'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Delete').last);
      await tester.pumpAndSettle();
      expect(repo.deletes, 1);
      expect(repo.lists, 2);
      expect(find.textContaining('Tap + to add one.'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });
  }
}
