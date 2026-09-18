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

const _kRecurringTypes = [
  ('per_day', 'Per day'),
  ('per_month', 'Per month'),
  ('per_year', 'Per year'),
];

/// Financial > Loans — list, create, pay an installment and delete.
class LoansTab extends StatefulWidget {
  const LoansTab({super.key});

  @override
  State<LoansTab> createState() => _LoansTabState();
}

class _LoansTabState extends State<LoansTab> with AutomaticKeepAliveClientMixin {
  @override
  bool get wantKeepAlive => true;

  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _loans = [];
  Map<String, dynamic> _summary = {};

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
      final res = await ApiClient.instance.get(ApiEndpoints.financeLoans, bypassCache: forceRefresh);
      final body = res.data;
      _loans = parseListData(body);
      _summary = body is Map ? Map<String, dynamic>.from(body) : {};
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
      builder: (_) => const AddLoanSheet(),
    );
    if (created == true) _load();
  }

  Future<void> _handleAction(Map<String, dynamic> loan) async {
    final action = await showFinanceActionSheet(context, [
      (Icons.payments_outlined, 'Pay installment', AppColors.primary),
      (Icons.delete_outline, 'Delete', AppColors.error),
    ]);
    if (!mounted || action == null) return;
    if (action == 'Pay installment') {
      final paid = await showModalBottomSheet<bool>(
        context: context,
        isScrollControlled: true,
        backgroundColor: Colors.transparent,
        builder: (_) => _PayLoanSheet(loan: loan),
      );
      if (paid == true) _load();
    } else if (action == 'Delete') {
      final confirmed = await confirmDelete(
        context,
        title: 'Delete loan',
        message: 'Delete "${loan['name']}"? This cannot be undone.',
      );
      if (!confirmed) return;
      try {
        await ApiClient.instance.delete(ApiEndpoints.financeLoan((loan['id'] as num).toInt()));
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
                child: Text('Loans', style: TextStyle(fontSize: 15.5, fontWeight: FontWeight.w800, color: AppColors.textDark)),
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
    if (_error != null && _loans.isEmpty) return ErrorState(error: _error!, onRetry: _load);
    if (_loans.isEmpty) return const EmptyState(message: 'No loans yet.');

    return RefreshIndicator(
      onRefresh: () => _load(forceRefresh: true),
      child: ListView(
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
        children: [
          StatGrid(
            tiles: [
              StatTile(label: 'Active loans', value: '${_summary['active_count'] ?? _loans.length}', icon: Icons.account_balance_outlined),
              StatTile(
                label: 'Total principal',
                value: (_summary['total_principal_fmt'] as String?) ?? formatMoney(_summary['total_principal']),
                icon: Icons.savings_outlined,
                color: AppColors.primary,
              ),
              StatTile(
                label: 'Monthly outflow',
                value: (_summary['total_monthly_fmt'] as String?) ?? formatMoney(_summary['total_monthly_outflow']),
                icon: Icons.calendar_month_outlined,
                color: AppColors.warning,
              ),
            ],
          ),
          const SizedBox(height: 14),
          for (final l in _loans) ...[
            _LoanCard(loan: l, onTap: () => _handleAction(l)),
            const SizedBox(height: 10),
          ],
        ],
      ),
    );
  }
}

class _LoanCard extends StatelessWidget {
  const _LoanCard({required this.loan, required this.onTap});
  final Map<String, dynamic> loan;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final endDate = DateTime.tryParse((loan['loan_ending_date'] as String?) ?? '');
    final completed = endDate != null && endDate.isBefore(DateTime.now());
    final interestLabel = '${loan['interest_rate_type_label'] ?? ''} · ${loan['interest_rate'] ?? ''}%';

    return FinanceCard(
      onTap: onTap,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  (loan['name'] as String?) ?? '',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontSize: 14.5, fontWeight: FontWeight.w700, color: AppColors.textDark),
                ),
              ),
              FinanceBadge(label: completed ? 'COMPLETED' : 'ACTIVE', color: completed ? AppColors.textMuted : AppColors.success),
            ],
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 6,
            runSpacing: 6,
            children: [
              if ((loan['bank_name'] as String?)?.isNotEmpty ?? false)
                FinanceBadge(label: loan['bank_name'] as String, color: AppColors.primary),
              FinanceBadge(label: (loan['cadence_label'] as String?) ?? '', color: AppColors.textMuted),
              FinanceBadge(label: interestLabel, color: AppColors.textMuted),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'Principal ${(loan['borrowed_amount_fmt'] as String?) ?? formatMoney(loan['borrowed_amount'])}',
                style: const TextStyle(fontSize: 12, color: AppColors.textMuted),
              ),
              Text(
                '${(loan['payment_formatted'] as String?) ?? ''} / period',
                style: const TextStyle(fontSize: 13.5, fontWeight: FontWeight.w800, color: AppColors.textDark),
              ),
            ],
          ),
          if ((loan['account_name'] as String?)?.isNotEmpty ?? false) ...[
            const SizedBox(height: 6),
            Text(
              'Debited from ${loan['account_name']}',
              style: const TextStyle(fontSize: 11.5, color: AppColors.textMuted),
            ),
          ],
        ],
      ),
    );
  }
}

class AddLoanSheet extends StatefulWidget {
  const AddLoanSheet();

  @override
  State<AddLoanSheet> createState() => AddLoanSheetState();
}

class AddLoanSheetState extends State<AddLoanSheet> {
  final _formKey = GlobalKey<FormState>();
  final _nameCtrl = TextEditingController();
  final _descriptionCtrl = TextEditingController();
  final _borrowedCtrl = TextEditingController();
  final _interestRateCtrl = TextEditingController();
  final _remindCtrl = TextEditingController();

  bool _loadingOptions = true;
  bool _saving = false;
  String? _error;

  String _interestRateType = 'percentage';
  String _recurringType = 'per_month';
  DateTime? _firstInstallmentDate;
  DateTime? _loanEndingDate;
  int? _bankId;
  int? _accountId;

  List<Map<String, dynamic>> _banks = [];
  List<Map<String, dynamic>> _accounts = [];

  @override
  void initState() {
    super.initState();
    _loadOptions();
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _descriptionCtrl.dispose();
    _borrowedCtrl.dispose();
    _interestRateCtrl.dispose();
    _remindCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadOptions() async {
    try {
      final results = await Future.wait([
        ApiClient.instance.get(ApiEndpoints.banks),
        ApiClient.instance.get(ApiEndpoints.accounts),
      ]);
      _banks = parseListData(results[0].data);
      _accounts = parseListData(results[1].data);
    } catch (e) {
      _error = apiErrorMessage(e);
    } finally {
      if (mounted) setState(() => _loadingOptions = false);
    }
  }

  Future<void> _submit() async {
    final formOk = _formKey.currentState?.validate() ?? false;
    if (!formOk) return;
    if (_bankId == null) {
      setState(() => _error = 'Pick a bank.');
      return;
    }

    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await ApiClient.instance.post(
        ApiEndpoints.financeLoans,
        data: {
          'name': _nameCtrl.text.trim(),
          if (_descriptionCtrl.text.trim().isNotEmpty) 'description': _descriptionCtrl.text.trim(),
          'bank_id': _bankId,
          'borrowed_amount': double.tryParse(_borrowedCtrl.text.trim()) ?? 0,
          'interest_rate_type': _interestRateType,
          'interest_rate': double.tryParse(_interestRateCtrl.text.trim()) ?? 0,
          'recurring_type': _recurringType,
          if (_firstInstallmentDate != null) 'first_installment_due_date': toApiDate(_firstInstallmentDate!),
          if (_loanEndingDate != null) 'loan_ending_date': toApiDate(_loanEndingDate!),
          if (_accountId != null) 'deduct_account_id': _accountId,
          if (_remindCtrl.text.trim().isNotEmpty) 'remind_before_days': int.tryParse(_remindCtrl.text.trim()),
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
    title: 'Add loan',
    loading: _loadingOptions,
    child: Form(
      key: _formKey,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          TextFormField(
            controller: _nameCtrl,
            decoration: const InputDecoration(labelText: 'Loan name'),
            validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
          ),
          const SizedBox(height: 14),
          DropdownButtonFormField<int>(
            initialValue: _bankId,
            decoration: const InputDecoration(labelText: 'Bank'),
            items: [
              for (final b in _banks) DropdownMenuItem(value: (b['id'] as num).toInt(), child: Text(b['name'] as String? ?? '')),
            ],
            onChanged: (v) => setState(() => _bankId = v),
          ),
          const SizedBox(height: 14),
          TextFormField(
            controller: _borrowedCtrl,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: const InputDecoration(labelText: 'Borrowed amount'),
            validator: (v) {
              final n = double.tryParse((v ?? '').trim());
              return (n == null || n < 0) ? 'Enter a valid amount' : null;
            },
          ),
          const SizedBox(height: 14),
          SegmentedButton<String>(
            segments: const [
              ButtonSegment(value: 'percentage', label: Text('Percentage')),
              ButtonSegment(value: 'flat', label: Text('Flat')),
            ],
            selected: {_interestRateType},
            onSelectionChanged: (s) => setState(() => _interestRateType = s.first),
          ),
          const SizedBox(height: 14),
          TextFormField(
            controller: _interestRateCtrl,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: const InputDecoration(labelText: 'Interest rate'),
            validator: (v) {
              final n = double.tryParse((v ?? '').trim());
              return (n == null || n < 0) ? 'Enter a valid rate' : null;
            },
          ),
          const SizedBox(height: 14),
          DropdownButtonFormField<String>(
            initialValue: _recurringType,
            decoration: const InputDecoration(labelText: 'Repayment cadence'),
            items: [for (final r in _kRecurringTypes) DropdownMenuItem(value: r.$1, child: Text(r.$2))],
            onChanged: (v) => setState(() => _recurringType = v ?? _recurringType),
          ),
          const SizedBox(height: 14),
          FinanceDateField(
            label: 'First installment date (optional)',
            value: _firstInstallmentDate,
            onPick: (d) => setState(() => _firstInstallmentDate = d),
          ),
          const SizedBox(height: 14),
          FinanceDateField(label: 'Loan ending date (optional)', value: _loanEndingDate, onPick: (d) => setState(() => _loanEndingDate = d)),
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
            controller: _remindCtrl,
            keyboardType: TextInputType.number,
            decoration: const InputDecoration(labelText: 'Remind before (days, optional)'),
          ),
          sheetError(_error),
          const SizedBox(height: 8),
          SheetSubmitButton(label: 'Add loan', saving: _saving, onPressed: _submit),
        ],
      ),
    ),
  );
}

class _PayLoanSheet extends StatefulWidget {
  const _PayLoanSheet({required this.loan});
  final Map<String, dynamic> loan;

  @override
  State<_PayLoanSheet> createState() => _PayLoanSheetState();
}

class _PayLoanSheetState extends State<_PayLoanSheet> {
  bool _loadingOptions = true;
  bool _saving = false;
  String? _error;
  List<Map<String, dynamic>> _accounts = [];

  DateTime? _dueDate = DateTime.now();
  String _recordingOption = 'ledger';
  int? _accountId;

  @override
  void initState() {
    super.initState();
    _loadAccounts();
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

  Future<void> _submit() async {
    if (_dueDate == null) {
      setState(() => _error = 'Pick the installment date.');
      return;
    }
    if (_recordingOption == 'ledger' && _accountId == null) {
      setState(() => _error = 'Pick a debit account.');
      return;
    }

    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await ApiClient.instance.post(
        ApiEndpoints.financeLoanPay((widget.loan['id'] as num).toInt()),
        data: {
          'due_date': toApiDate(_dueDate!),
          'recording_option': _recordingOption,
          if (_recordingOption == 'ledger') 'account_id': _accountId,
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
    title: 'Pay "${widget.loan['name'] ?? ''}"',
    loading: _loadingOptions,
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        FinanceDateField(label: 'Installment date', value: _dueDate, onPick: (d) => setState(() => _dueDate = d)),
        const SizedBox(height: 14),
        SegmentedButton<String>(
          segments: const [
            ButtonSegment(value: 'ledger', label: Text('Ledger')),
            ButtonSegment(value: 'external', label: Text('External')),
          ],
          selected: {_recordingOption},
          onSelectionChanged: (s) => setState(() => _recordingOption = s.first),
        ),
        if (_recordingOption == 'ledger') ...[
          const SizedBox(height: 14),
          DropdownButtonFormField<int>(
            initialValue: _accountId,
            decoration: const InputDecoration(labelText: 'Debit account'),
            items: [
              for (final a in _accounts)
                DropdownMenuItem(value: (a['id'] as num).toInt(), child: Text(a['account_name'] as String? ?? '')),
            ],
            onChanged: (v) => setState(() => _accountId = v),
          ),
        ],
        sheetError(_error),
        const SizedBox(height: 8),
        SheetSubmitButton(label: 'Record payment', saving: _saving, onPressed: _submit),
      ],
    ),
  );
}
