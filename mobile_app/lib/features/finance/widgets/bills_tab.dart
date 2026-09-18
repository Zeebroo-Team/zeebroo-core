import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/date.dart';
import '../../../core/utils/money.dart';
import '../../dashboard/widgets/stat_tile.dart';
import '../../inventory/widgets/list_states.dart';
import '../../inventory/widgets/picker_sheet.dart';
import 'finance_common.dart';

const _kBillCategories = [
  ('water', 'Water'),
  ('electricity', 'Electricity'),
  ('telephone', 'Telephone'),
  ('internet', 'Internet'),
  ('gas', 'Gas'),
  ('waste', 'Waste'),
  ('other', 'Other'),
];

const _kRecurringTypes = [
  ('per_day', 'Per day'),
  ('per_month', 'Per month'),
  ('per_year', 'Per year'),
];

const _kAssignmentTypes = [
  ('none', 'None'),
  ('branch', 'Branch'),
  ('department', 'Department'),
  ('property', 'Property'),
  ('employee', 'Employee'),
  ('modification', 'Modification'),
  ('rental', 'Rental'),
];

String _categoryLabel(String? key) =>
    _kBillCategories.firstWhere((c) => c.$1 == key, orElse: () => ('', key ?? '')).$2;

IconData _categoryIcon(String? key) => switch (key) {
  'water' => Icons.water_drop_outlined,
  'electricity' => Icons.bolt_outlined,
  'telephone' => Icons.call_outlined,
  'internet' => Icons.wifi_outlined,
  'gas' => Icons.local_fire_department_outlined,
  'waste' => Icons.delete_outline,
  _ => Icons.receipt_long_outlined,
};

String _recurringLabel(String? key) =>
    _kRecurringTypes.firstWhere((r) => r.$1 == key, orElse: () => ('', '')).$2;

String? _assignmentName(Map<String, dynamic> b) {
  for (final field in ['property_name', 'employee_name', 'modification_name', 'department_name', 'branch_name']) {
    final v = b[field] as String?;
    if (v != null && v.isNotEmpty) return v;
  }
  final rentalType = b['rental_type'] as String?;
  if (rentalType != null && rentalType.isNotEmpty) return rentalType;
  return null;
}

/// Financial > Bills — list, create, pay (full/partial/split) and delete.
class BillsTab extends StatefulWidget {
  const BillsTab({super.key});

  @override
  State<BillsTab> createState() => _BillsTabState();
}

class _BillsTabState extends State<BillsTab> with AutomaticKeepAliveClientMixin {
  @override
  bool get wantKeepAlive => true;

  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _bills = [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load({bool forceRefresh = false}) async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final res = await ApiClient.instance.get(ApiEndpoints.financeBills, bypassCache: forceRefresh);
      _bills = parseListData(res.data);
    } catch (e) {
      _error = apiErrorMessage(e);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _openAdd() async {
    final created = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => const AddBillSheet(),
    );
    if (created == true) _load();
  }

  Future<void> _handleAction(Map<String, dynamic> bill) async {
    final action = await showFinanceActionSheet(context, [
      (Icons.payments_outlined, 'Pay bill', AppColors.primary),
      (Icons.delete_outline, 'Delete', AppColors.error),
    ]);
    if (!mounted || action == null) return;
    if (action == 'Pay bill') {
      final paid = await showModalBottomSheet<bool>(
        context: context,
        isScrollControlled: true,
        backgroundColor: Colors.transparent,
        builder: (_) => _PayBillSheet(bill: bill),
      );
      if (paid == true) _load();
    } else if (action == 'Delete') {
      final confirmed = await confirmDelete(
        context,
        title: 'Delete bill',
        message: 'Delete "${bill['name']}"? This cannot be undone.',
      );
      if (!confirmed) return;
      try {
        await ApiClient.instance.delete(ApiEndpoints.financeBill((bill['id'] as num).toInt()));
        _load();
      } catch (e) {
        if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(apiErrorMessage(e))));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 14, 20, 0),
          child: Row(
            children: [
              const Expanded(
                child: Text('Bills', style: TextStyle(fontSize: 15.5, fontWeight: FontWeight.w800, color: AppColors.textDark)),
              ),
              AddButton(onTap: _openAdd),
            ],
          ),
        ),
        Expanded(child: _buildBody()),
      ],
    );
  }

  Widget _buildBody() {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null && _bills.isEmpty) return ErrorState(error: _error!, onRetry: _load);
    if (_bills.isEmpty) return const EmptyState(message: 'No bills yet.');

    final overdueCount = _bills.where((b) => b['overdue'] as bool? ?? false).length;
    final monthlyTotal = _bills
        .where((b) => b['payment_mode'] == 'recurring')
        .fold<double>(0, (sum, b) => sum + ((b['amount'] as num?)?.toDouble() ?? 0));

    return RefreshIndicator(
      onRefresh: () => _load(forceRefresh: true),
      child: ListView(
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
        children: [
          StatGrid(
            tiles: [
              StatTile(label: 'Bills', value: '${_bills.length}', icon: Icons.receipt_long_outlined),
              StatTile(label: 'Overdue', value: '$overdueCount', icon: Icons.warning_amber_rounded, color: AppColors.error),
              StatTile(label: 'Monthly total', value: formatMoney(monthlyTotal), icon: Icons.calendar_month_outlined, color: AppColors.primary),
            ],
          ),
          const SizedBox(height: 14),
          for (final b in _bills) ...[
            _BillCard(bill: b, onTap: () => _handleAction(b)),
            const SizedBox(height: 10),
          ],
        ],
      ),
    );
  }
}

class _BillCard extends StatelessWidget {
  const _BillCard({required this.bill, required this.onTap});
  final Map<String, dynamic> bill;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final overdue = bill['overdue'] as bool? ?? false;
    final paid = bill['is_fully_paid'] as bool? ?? false;
    final varies = bill['amount_varies_by_usage'] as bool? ?? false;
    final isRecurring = bill['payment_mode'] == 'recurring';
    final assignment = _assignmentName(bill);

    final Color statusColor;
    final String statusLabel;
    if (paid) {
      statusColor = AppColors.success;
      statusLabel = 'PAID';
    } else if (overdue) {
      statusColor = AppColors.error;
      statusLabel = 'OVERDUE';
    } else {
      statusColor = AppColors.warning;
      statusLabel = 'DUE';
    }

    return FinanceCard(
      onTap: onTap,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 34,
                height: 34,
                decoration: BoxDecoration(color: AppColors.primaryLt, borderRadius: BorderRadius.circular(10)),
                alignment: Alignment.center,
                child: Icon(_categoryIcon(bill['category'] as String?), size: 16, color: AppColors.primary),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  (bill['name'] as String?) ?? '',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontSize: 14.5, fontWeight: FontWeight.w700, color: AppColors.textDark),
                ),
              ),
              FinanceBadge(label: statusLabel, color: statusColor),
            ],
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 6,
            runSpacing: 6,
            children: [
              FinanceBadge(label: _categoryLabel(bill['category'] as String?), color: AppColors.textMuted),
              FinanceBadge(
                label: isRecurring ? 'Recurring · ${_recurringLabel(bill['recurring_type'] as String?)}' : 'One-time',
                color: AppColors.textMuted,
              ),
              if (varies) const FinanceBadge(label: 'Varies by usage', color: AppColors.warning),
              if (assignment != null) FinanceBadge(label: 'via $assignment', color: AppColors.primary),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'Due ${(bill['due_date_fmt'] as String?) ?? formatDate(bill['due_date'] as String?)}',
                style: const TextStyle(fontSize: 12, color: AppColors.textMuted),
              ),
              Text(
                varies ? 'Varies' : formatMoney(bill['amount']),
                style: const TextStyle(fontSize: 14.5, fontWeight: FontWeight.w800, color: AppColors.textDark),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

/// Create-bill sheet — mirrors the desktop Bill form, including the
/// assignment picker (`GET expenses/bill-assignment-targets`).
class AddBillSheet extends StatefulWidget {
  const AddBillSheet();

  @override
  State<AddBillSheet> createState() => AddBillSheetState();
}

class AddBillSheetState extends State<AddBillSheet> {
  final _formKey = GlobalKey<FormState>();
  final _nameCtrl = TextEditingController();
  final _categoryOtherCtrl = TextEditingController();
  final _descriptionCtrl = TextEditingController();
  final _agreementYearCtrl = TextEditingController(text: '${DateTime.now().year + 1}');
  final _costCtrl = TextEditingController();
  final _remindCtrl = TextEditingController();
  final _notesCtrl = TextEditingController();

  bool _loadingOptions = true;
  bool _saving = false;
  String? _error;

  String _category = 'electricity';
  String _paymentMode = 'recurring';
  String _recurringType = 'per_month';
  String _assignmentType = 'none';
  bool _amountVaries = false;
  bool _allowSplit = true;
  DateTime? _dueDate;
  DateTime? _firstInstallmentDate;
  int? _assignmentId;
  int? _accountId;

  Map<String, dynamic> _targets = {};
  List<Map<String, dynamic>> _accounts = [];

  @override
  void initState() {
    super.initState();
    _loadOptions();
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _categoryOtherCtrl.dispose();
    _descriptionCtrl.dispose();
    _agreementYearCtrl.dispose();
    _costCtrl.dispose();
    _remindCtrl.dispose();
    _notesCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadOptions() async {
    try {
      final results = await Future.wait([
        ApiClient.instance.get(ApiEndpoints.financeBillAssignmentTargets),
        ApiClient.instance.get(ApiEndpoints.accounts),
      ]);
      final targetsRaw = results[0].data;
      _targets = ((targetsRaw is Map ? targetsRaw['data'] : null) as Map?)?.cast<String, dynamic>() ?? {};
      _accounts = parseListData(results[1].data);
    } catch (e) {
      _error = apiErrorMessage(e);
    } finally {
      if (mounted) setState(() => _loadingOptions = false);
    }
  }

  List<Map<String, dynamic>> get _assignmentOptions {
    const keys = {
      'branch': 'branches',
      'department': 'departments',
      'property': 'properties',
      'employee': 'employees',
      'modification': 'modifications',
      'rental': 'rentals',
    };
    final key = keys[_assignmentType];
    if (key == null) return const [];
    final list = _targets[key] as List? ?? [];
    return list.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();
  }

  Future<void> _submit() async {
    final formOk = _formKey.currentState?.validate() ?? false;
    if (!formOk) return;
    if (_category == 'other' && _categoryOtherCtrl.text.trim().isEmpty) {
      setState(() => _error = 'Enter the custom bill category.');
      return;
    }
    if (_paymentMode == 'one_time' && _dueDate == null) {
      setState(() => _error = 'Pick a due date for a one-time bill.');
      return;
    }
    if (_assignmentType != 'none' && _assignmentId == null) {
      setState(() => _error = 'Pick a $_assignmentType to assign this bill to.');
      return;
    }

    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await ApiClient.instance.post(
        ApiEndpoints.financeBills,
        data: {
          'name': _nameCtrl.text.trim(),
          'bill_category': _category,
          if (_category == 'other') 'bill_category_other': _categoryOtherCtrl.text.trim(),
          if (_descriptionCtrl.text.trim().isNotEmpty) 'description': _descriptionCtrl.text.trim(),
          'payment_mode': _paymentMode,
          if (_paymentMode == 'recurring') ...{
            'recurring_type': _recurringType,
            'agreement_valid_until_year': int.tryParse(_agreementYearCtrl.text.trim()),
          },
          if (_paymentMode == 'one_time' && _dueDate != null) 'due_date': toApiDate(_dueDate!),
          if (_firstInstallmentDate != null) 'first_installment_due_date': toApiDate(_firstInstallmentDate!),
          'amount_varies_by_usage': _amountVaries,
          if (!_amountVaries) 'recurring_cost': double.tryParse(_costCtrl.text.trim()) ?? 0,
          'allow_split_payment': _allowSplit,
          if (_remindCtrl.text.trim().isNotEmpty) 'remind_before_days': int.tryParse(_remindCtrl.text.trim()),
          'assignment_type': _assignmentType,
          if (_assignmentType != 'none' && _assignmentId != null) '${_assignmentType}_id': _assignmentId,
          if (_accountId != null) 'deduct_account_id': _accountId,
          if (_notesCtrl.text.trim().isNotEmpty) 'notes': _notesCtrl.text.trim(),
        },
      );
      if (mounted) Navigator.pop(context, true);
    } catch (e) {
      setState(() => _error = apiErrorMessage(e));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) => FormSheetShell(
    title: 'Add bill',
    loading: _loadingOptions,
    child: Form(
      key: _formKey,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          TextFormField(
            controller: _nameCtrl,
            decoration: const InputDecoration(labelText: 'Bill name'),
            validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
          ),
          const SizedBox(height: 14),
          DropdownButtonFormField<String>(
            initialValue: _category,
            decoration: const InputDecoration(labelText: 'Category'),
            items: [for (final c in _kBillCategories) DropdownMenuItem(value: c.$1, child: Text(c.$2))],
            onChanged: (v) => setState(() => _category = v ?? _category),
          ),
          if (_category == 'other') ...[
            const SizedBox(height: 14),
            TextFormField(
              controller: _categoryOtherCtrl,
              decoration: const InputDecoration(labelText: 'Custom category'),
            ),
          ],
          const SizedBox(height: 14),
          SegmentedButton<String>(
            segments: const [
              ButtonSegment(value: 'one_time', label: Text('One-time')),
              ButtonSegment(value: 'recurring', label: Text('Recurring')),
            ],
            selected: {_paymentMode},
            onSelectionChanged: (s) => setState(() => _paymentMode = s.first),
          ),
          if (_paymentMode == 'recurring') ...[
            const SizedBox(height: 14),
            DropdownButtonFormField<String>(
              initialValue: _recurringType,
              decoration: const InputDecoration(labelText: 'Billing cadence'),
              items: [for (final r in _kRecurringTypes) DropdownMenuItem(value: r.$1, child: Text(r.$2))],
              onChanged: (v) => setState(() => _recurringType = v ?? _recurringType),
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: _agreementYearCtrl,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(labelText: 'Agreement valid until (year)'),
              validator: (v) => (int.tryParse((v ?? '').trim()) == null) ? 'Enter a valid year' : null,
            ),
          ],
          const SizedBox(height: 14),
          FinanceDateField(
            label: _paymentMode == 'one_time' ? 'Due date' : 'Due date (optional)',
            value: _dueDate,
            onPick: (d) => setState(() => _dueDate = d),
          ),
          const SizedBox(height: 14),
          FinanceDateField(
            label: 'First installment date (optional)',
            value: _firstInstallmentDate,
            onPick: (d) => setState(() => _firstInstallmentDate = d),
          ),
          const SizedBox(height: 6),
          SwitchListTile.adaptive(
            contentPadding: EdgeInsets.zero,
            title: const Text('Amount varies by usage', style: TextStyle(fontSize: 14.5, fontWeight: FontWeight.w600)),
            value: _amountVaries,
            activeThumbColor: AppColors.primary,
            onChanged: (v) => setState(() => _amountVaries = v),
          ),
          if (!_amountVaries) ...[
            const SizedBox(height: 8),
            TextFormField(
              controller: _costCtrl,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              decoration: const InputDecoration(labelText: 'Amount'),
              validator: (v) {
                if (_amountVaries) return null;
                final n = double.tryParse((v ?? '').trim());
                return (n == null || n < 0) ? 'Enter a valid amount' : null;
              },
            ),
          ],
          SwitchListTile.adaptive(
            contentPadding: EdgeInsets.zero,
            title: const Text('Allow split payment', style: TextStyle(fontSize: 14.5, fontWeight: FontWeight.w600)),
            value: _allowSplit,
            activeThumbColor: AppColors.primary,
            onChanged: (v) => setState(() => _allowSplit = v),
          ),
          const SizedBox(height: 8),
          TextFormField(
            controller: _remindCtrl,
            keyboardType: TextInputType.number,
            decoration: const InputDecoration(labelText: 'Remind before (days, optional)'),
          ),
          const SizedBox(height: 14),
          DropdownButtonFormField<String>(
            initialValue: _assignmentType,
            decoration: const InputDecoration(labelText: 'Assign to'),
            items: [for (final a in _kAssignmentTypes) DropdownMenuItem(value: a.$1, child: Text(a.$2))],
            onChanged: (v) => setState(() {
              _assignmentType = v ?? 'none';
              _assignmentId = null;
            }),
          ),
          if (_assignmentType != 'none') ...[
            const SizedBox(height: 14),
            DropdownButtonFormField<int>(
              initialValue: _assignmentId,
              decoration: InputDecoration(labelText: _kAssignmentTypes.firstWhere((a) => a.$1 == _assignmentType).$2),
              items: [
                for (final t in _assignmentOptions)
                  DropdownMenuItem(value: (t['id'] as num).toInt(), child: Text(t['name'] as String? ?? '')),
              ],
              onChanged: (v) => setState(() => _assignmentId = v),
            ),
          ],
          const SizedBox(height: 14),
          DropdownButtonFormField<int?>(
            initialValue: _accountId,
            decoration: const InputDecoration(labelText: 'Debit account (optional)'),
            items: [
              const DropdownMenuItem(value: null, child: Text('—')),
              for (final a in _accounts)
                DropdownMenuItem(value: (a['id'] as num).toInt(), child: Text(a['account_name'] as String? ?? '')),
            ],
            onChanged: (v) => setState(() => _accountId = v),
          ),
          const SizedBox(height: 14),
          TextFormField(
            controller: _notesCtrl,
            maxLines: 3,
            decoration: const InputDecoration(labelText: 'Notes (optional)'),
          ),
          sheetError(_error),
          const SizedBox(height: 8),
          SheetSubmitButton(label: 'Add bill', saving: _saving, onPressed: _submit),
        ],
      ),
    ),
  );
}

/// Pay-bill sheet — full, partial, or split across multiple accounts.
class _PayBillSheet extends StatefulWidget {
  const _PayBillSheet({required this.bill});
  final Map<String, dynamic> bill;

  @override
  State<_PayBillSheet> createState() => _PayBillSheetState();
}

class _PayBillSheetState extends State<_PayBillSheet> {
  bool _loadingOptions = true;
  bool _saving = false;
  String? _error;
  List<Map<String, dynamic>> _accounts = [];

  String _option = 'full';
  DateTime? _occurrenceDate = DateTime.now();
  int? _accountId;
  final _partialAmountCtrl = TextEditingController();
  final _periodChargeCtrl = TextEditingController();
  final List<({int? accountId, TextEditingController amount})> _splitRows = [
    (accountId: null, amount: TextEditingController()),
    (accountId: null, amount: TextEditingController()),
  ];

  @override
  void initState() {
    super.initState();
    _loadAccounts();
  }

  @override
  void dispose() {
    _partialAmountCtrl.dispose();
    _periodChargeCtrl.dispose();
    for (final row in _splitRows) {
      row.amount.dispose();
    }
    super.dispose();
  }

  Future<void> _loadAccounts() async {
    try {
      final res = await ApiClient.instance.get(ApiEndpoints.accounts);
      _accounts = parseListData(res.data);
    } catch (e) {
      _error = apiErrorMessage(e);
    } finally {
      if (mounted) setState(() => _loadingOptions = false);
    }
  }

  bool get _amountVaries => widget.bill['amount_varies_by_usage'] as bool? ?? false;

  Future<void> _submit() async {
    if (_occurrenceDate == null) {
      setState(() => _error = 'Pick the payment date.');
      return;
    }
    if (_option != 'split' && _accountId == null) {
      setState(() => _error = 'Pick a debit account.');
      return;
    }
    if (_option == 'partial' && (double.tryParse(_partialAmountCtrl.text.trim()) ?? -1) <= 0) {
      setState(() => _error = 'Enter a valid partial amount.');
      return;
    }
    if (_option == 'split') {
      final valid = _splitRows.where((r) => r.accountId != null && (double.tryParse(r.amount.text.trim()) ?? 0) > 0);
      if (valid.length < 2) {
        setState(() => _error = 'Add at least two split rows with an account and amount.');
        return;
      }
    }

    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await ApiClient.instance.post(
        ApiEndpoints.financeBillPay((widget.bill['id'] as num).toInt()),
        data: {
          'occurrence_date': toApiDate(_occurrenceDate!),
          'payment_option': _option,
          if (_option != 'split') 'deduct_account_id': _accountId,
          if (_option == 'partial') 'partial_amount': double.tryParse(_partialAmountCtrl.text.trim()),
          if (_option == 'split')
            'split_rows': [
              for (final r in _splitRows)
                if (r.accountId != null && (double.tryParse(r.amount.text.trim()) ?? 0) > 0)
                  {'deduct_account_id': r.accountId, 'amount': double.tryParse(r.amount.text.trim())},
            ],
          if (_amountVaries) 'period_charge_total': double.tryParse(_periodChargeCtrl.text.trim()),
        },
      );
      if (mounted) Navigator.pop(context, true);
    } catch (e) {
      setState(() => _error = apiErrorMessage(e));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) => FormSheetShell(
    title: 'Pay "${widget.bill['name'] ?? ''}"',
    loading: _loadingOptions,
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        FinanceDateField(label: 'Payment date', value: _occurrenceDate, onPick: (d) => setState(() => _occurrenceDate = d)),
        const SizedBox(height: 14),
        SegmentedButton<String>(
          segments: const [
            ButtonSegment(value: 'full', label: Text('Full')),
            ButtonSegment(value: 'partial', label: Text('Partial')),
            ButtonSegment(value: 'split', label: Text('Split')),
          ],
          selected: {_option},
          onSelectionChanged: (s) => setState(() => _option = s.first),
        ),
        const SizedBox(height: 14),
        if (_option != 'split') ...[
          DropdownButtonFormField<int>(
            initialValue: _accountId,
            decoration: const InputDecoration(labelText: 'Debit account'),
            items: [
              for (final a in _accounts)
                DropdownMenuItem(value: (a['id'] as num).toInt(), child: Text(a['account_name'] as String? ?? '')),
            ],
            onChanged: (v) => setState(() => _accountId = v),
          ),
          if (_option == 'partial') ...[
            const SizedBox(height: 14),
            TextFormField(
              controller: _partialAmountCtrl,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              decoration: const InputDecoration(labelText: 'Partial amount'),
            ),
          ],
        ] else ...[
          for (var i = 0; i < _splitRows.length; i++) ...[
            Row(
              children: [
                Expanded(
                  flex: 3,
                  child: DropdownButtonFormField<int>(
                    initialValue: _splitRows[i].accountId,
                    decoration: InputDecoration(labelText: 'Account ${i + 1}'),
                    items: [
                      for (final a in _accounts)
                        DropdownMenuItem(value: (a['id'] as num).toInt(), child: Text(a['account_name'] as String? ?? '')),
                    ],
                    onChanged: (v) => setState(() => _splitRows[i] = (accountId: v, amount: _splitRows[i].amount)),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  flex: 2,
                  child: TextFormField(
                    controller: _splitRows[i].amount,
                    keyboardType: const TextInputType.numberWithOptions(decimal: true),
                    decoration: const InputDecoration(labelText: 'Amount'),
                  ),
                ),
                if (_splitRows.length > 2)
                  IconButton(
                    icon: const Icon(Icons.remove_circle_outline, size: 20, color: AppColors.error),
                    onPressed: () => setState(() {
                      _splitRows[i].amount.dispose();
                      _splitRows.removeAt(i);
                    }),
                  ),
              ],
            ),
            const SizedBox(height: 10),
          ],
          TextButton.icon(
            onPressed: () => setState(() => _splitRows.add((accountId: null, amount: TextEditingController()))),
            icon: const Icon(Icons.add, size: 18),
            label: const Text('Add row'),
          ),
        ],
        if (_amountVaries) ...[
          const SizedBox(height: 14),
          TextFormField(
            controller: _periodChargeCtrl,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: const InputDecoration(labelText: 'Total usage charge for this period'),
          ),
        ],
        sheetError(_error),
        const SizedBox(height: 8),
        SheetSubmitButton(label: 'Record payment', saving: _saving, onPressed: _submit),
      ],
    ),
  );
}
