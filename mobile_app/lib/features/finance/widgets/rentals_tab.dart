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

/// Financial > Rentals — list, create, pay and delete a rented property.
class RentalsTab extends StatefulWidget {
  const RentalsTab({super.key});

  @override
  State<RentalsTab> createState() => _RentalsTabState();
}

class _RentalsTabState extends State<RentalsTab> with AutomaticKeepAliveClientMixin {
  @override
  bool get wantKeepAlive => true;

  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _rentals = [];
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
      final res = await ApiClient.instance.get(ApiEndpoints.financeRentals, bypassCache: forceRefresh);
      final body = res.data;
      _rentals = parseListData(body);
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
      builder: (_) => const AddRentalSheet(),
    );
    if (created == true) _load();
  }

  Future<void> _handleAction(Map<String, dynamic> rental) async {
    final action = await showFinanceActionSheet(context, [
      (Icons.payments_outlined, 'Pay rent', AppColors.primary),
      (Icons.delete_outline, 'Delete', AppColors.error),
    ]);
    if (!mounted || action == null) return;
    if (action == 'Pay rent') {
      final paid = await showModalBottomSheet<bool>(
        context: context,
        isScrollControlled: true,
        backgroundColor: Colors.transparent,
        builder: (_) => _PayRentalSheet(rental: rental),
      );
      if (paid == true) _load();
    } else if (action == 'Delete') {
      final confirmed = await confirmDelete(
        context,
        title: 'Delete rental',
        message: 'Delete "${rental['name']}"? This cannot be undone.',
      );
      if (!confirmed) return;
      try {
        await ApiClient.instance.delete(ApiEndpoints.financeRental((rental['id'] as num).toInt()));
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
                child: Text('Rentals', style: TextStyle(fontSize: 15.5, fontWeight: FontWeight.w800, color: AppColors.textDark)),
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
    if (_error != null && _rentals.isEmpty) return ErrorState(error: _error!, onRetry: _load);
    if (_rentals.isEmpty) return const EmptyState(message: 'No rentals yet.');

    return RefreshIndicator(
      onRefresh: () => _load(forceRefresh: true),
      child: ListView(
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
        children: [
          StatGrid(
            tiles: [
              StatTile(label: 'Active rentals', value: '${_summary['active_count'] ?? _rentals.length}', icon: Icons.apartment_outlined),
              StatTile(
                label: 'Overdue',
                value: '${_summary['overdue_count'] ?? 0}',
                icon: Icons.warning_amber_rounded,
                color: AppColors.error,
              ),
              StatTile(
                label: 'Monthly total',
                value: (_summary['total_monthly_fmt'] as String?) ?? '0.00',
                icon: Icons.calendar_month_outlined,
                color: AppColors.primary,
              ),
            ],
          ),
          const SizedBox(height: 14),
          for (final r in _rentals) ...[
            _RentalCard(rental: r, onTap: () => _handleAction(r)),
            const SizedBox(height: 10),
          ],
        ],
      ),
    );
  }
}

class _RentalCard extends StatelessWidget {
  const _RentalCard({required this.rental, required this.onTap});
  final Map<String, dynamic> rental;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final overdue = rental['overdue'] as bool? ?? false;
    final purpose = rental['purpose'] as String?;
    final keyMoneyFmt = rental['key_money_fmt'] as String?;

    return FinanceCard(
      onTap: onTap,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  (rental['property_type'] as String?) ?? '',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontSize: 14.5, fontWeight: FontWeight.w700, color: AppColors.textDark),
                ),
              ),
              FinanceBadge(label: overdue ? 'OVERDUE' : 'ACTIVE', color: overdue ? AppColors.error : AppColors.success),
            ],
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 6,
            runSpacing: 6,
            children: [
              if (purpose != null && purpose.isNotEmpty) FinanceBadge(label: purpose, color: AppColors.textMuted),
              FinanceBadge(label: (rental['cadence_label'] as String?) ?? '', color: AppColors.textMuted),
              if (keyMoneyFmt != null && keyMoneyFmt.isNotEmpty)
                FinanceBadge(label: 'Key money $keyMoneyFmt', color: AppColors.primary),
              FinanceBadge(label: 'Until ${rental['agreement_valid_until_year'] ?? ''}', color: AppColors.textMuted),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'Due ${(rental['due_date_fmt'] as String?) ?? formatDate(rental['due_date'] as String?)}',
                style: const TextStyle(fontSize: 12, color: AppColors.textMuted),
              ),
              Text(
                (rental['recurring_cost_fmt'] as String?) ?? formatMoney(rental['recurring_cost']),
                style: const TextStyle(fontSize: 14.5, fontWeight: FontWeight.w800, color: AppColors.textDark),
              ),
            ],
          ),
          if ((rental['account_name'] as String?)?.isNotEmpty ?? false) ...[
            const SizedBox(height: 6),
            Text('Debited from ${rental['account_name']}', style: const TextStyle(fontSize: 11.5, color: AppColors.textMuted)),
          ],
        ],
      ),
    );
  }
}

class AddRentalSheet extends StatefulWidget {
  const AddRentalSheet();

  @override
  State<AddRentalSheet> createState() => AddRentalSheetState();
}

class AddRentalSheetState extends State<AddRentalSheet> {
  final _formKey = GlobalKey<FormState>();
  final _propertyTypeCtrl = TextEditingController();
  final _purposeCtrl = TextEditingController();
  final _keyMoneyCtrl = TextEditingController();
  final _agreementYearCtrl = TextEditingController(text: '${DateTime.now().year + 1}');
  final _costCtrl = TextEditingController();
  final _remindCtrl = TextEditingController();
  final _notesCtrl = TextEditingController();
  final _ownerNameCtrl = TextEditingController();
  final _ownerEmailCtrl = TextEditingController();
  final _ownerPhoneCtrl = TextEditingController();
  final _ownerAddressCtrl = TextEditingController();
  final _ownerBankDetailsCtrl = TextEditingController();
  final _ownerNotesCtrl = TextEditingController();

  bool _loadingOptions = true;
  bool _saving = false;
  String? _error;
  String _recurringType = 'per_month';
  DateTime? _dueDate;
  DateTime? _firstInstallmentDate;
  int? _accountId;
  List<Map<String, dynamic>> _accounts = [];

  @override
  void initState() {
    super.initState();
    _loadAccounts();
  }

  @override
  void dispose() {
    _propertyTypeCtrl.dispose();
    _purposeCtrl.dispose();
    _keyMoneyCtrl.dispose();
    _agreementYearCtrl.dispose();
    _costCtrl.dispose();
    _remindCtrl.dispose();
    _notesCtrl.dispose();
    _ownerNameCtrl.dispose();
    _ownerEmailCtrl.dispose();
    _ownerPhoneCtrl.dispose();
    _ownerAddressCtrl.dispose();
    _ownerBankDetailsCtrl.dispose();
    _ownerNotesCtrl.dispose();
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

  Future<void> _submit() async {
    final formOk = _formKey.currentState?.validate() ?? false;
    if (!formOk) return;
    if (_ownerEmailCtrl.text.trim().isEmpty && _ownerPhoneCtrl.text.trim().isEmpty) {
      setState(() => _error = 'Provide the landlord\'s email or phone.');
      return;
    }

    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await ApiClient.instance.post(
        ApiEndpoints.financeRentals,
        data: {
          'property_type': _propertyTypeCtrl.text.trim(),
          if (_purposeCtrl.text.trim().isNotEmpty) 'purpose': _purposeCtrl.text.trim(),
          if (_keyMoneyCtrl.text.trim().isNotEmpty) 'key_money': double.tryParse(_keyMoneyCtrl.text.trim()),
          'agreement_valid_until_year': int.tryParse(_agreementYearCtrl.text.trim()),
          if (_accountId != null) 'deduct_account_id': _accountId,
          'recurring_cost': double.tryParse(_costCtrl.text.trim()) ?? 0,
          'recurring_type': _recurringType,
          if (_remindCtrl.text.trim().isNotEmpty) 'remind_before_days': int.tryParse(_remindCtrl.text.trim()),
          if (_dueDate != null) 'due_date': toApiDate(_dueDate!),
          if (_firstInstallmentDate != null) 'first_installment_due_date': toApiDate(_firstInstallmentDate!),
          if (_notesCtrl.text.trim().isNotEmpty) 'notes': _notesCtrl.text.trim(),
          'owner_name': _ownerNameCtrl.text.trim(),
          if (_ownerEmailCtrl.text.trim().isNotEmpty) 'owner_email': _ownerEmailCtrl.text.trim(),
          if (_ownerPhoneCtrl.text.trim().isNotEmpty) 'owner_phone': _ownerPhoneCtrl.text.trim(),
          if (_ownerAddressCtrl.text.trim().isNotEmpty) 'owner_address': _ownerAddressCtrl.text.trim(),
          if (_ownerBankDetailsCtrl.text.trim().isNotEmpty) 'owner_bank_details': _ownerBankDetailsCtrl.text.trim(),
          if (_ownerNotesCtrl.text.trim().isNotEmpty) 'owner_notes': _ownerNotesCtrl.text.trim(),
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
    title: 'Add rental',
    loading: _loadingOptions,
    child: Form(
      key: _formKey,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          TextFormField(
            controller: _propertyTypeCtrl,
            decoration: const InputDecoration(labelText: 'Property type'),
            validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
          ),
          const SizedBox(height: 14),
          TextFormField(
            controller: _purposeCtrl,
            decoration: const InputDecoration(labelText: 'Purpose (optional)'),
          ),
          const SizedBox(height: 14),
          TextFormField(
            controller: _keyMoneyCtrl,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: const InputDecoration(labelText: 'Key money (optional)'),
          ),
          const SizedBox(height: 14),
          TextFormField(
            controller: _agreementYearCtrl,
            keyboardType: TextInputType.number,
            decoration: const InputDecoration(labelText: 'Agreement valid until (year)'),
            validator: (v) => (int.tryParse((v ?? '').trim()) == null) ? 'Enter a valid year' : null,
          ),
          const SizedBox(height: 14),
          TextFormField(
            controller: _costCtrl,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: const InputDecoration(labelText: 'Rent amount'),
            validator: (v) {
              final n = double.tryParse((v ?? '').trim());
              return (n == null || n < 0) ? 'Enter a valid amount' : null;
            },
          ),
          const SizedBox(height: 14),
          DropdownButtonFormField<String>(
            initialValue: _recurringType,
            decoration: const InputDecoration(labelText: 'Billing cadence'),
            items: [for (final r in _kRecurringTypes) DropdownMenuItem(value: r.$1, child: Text(r.$2))],
            onChanged: (v) => setState(() => _recurringType = v ?? _recurringType),
          ),
          const SizedBox(height: 14),
          FinanceDateField(label: 'Due date (optional)', value: _dueDate, onPick: (d) => setState(() => _dueDate = d)),
          const SizedBox(height: 14),
          FinanceDateField(
            label: 'First installment date (optional)',
            value: _firstInstallmentDate,
            onPick: (d) => setState(() => _firstInstallmentDate = d),
          ),
          const SizedBox(height: 14),
          TextFormField(
            controller: _remindCtrl,
            keyboardType: TextInputType.number,
            decoration: const InputDecoration(labelText: 'Remind before (days, optional)'),
          ),
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
          TextFormField(controller: _notesCtrl, maxLines: 3, decoration: const InputDecoration(labelText: 'Notes (optional)')),
          const SizedBox(height: 18),
          const Text('Landlord', style: TextStyle(fontSize: 13.5, fontWeight: FontWeight.w800, color: AppColors.textDark)),
          const SizedBox(height: 10),
          TextFormField(
            controller: _ownerNameCtrl,
            decoration: const InputDecoration(labelText: 'Landlord name'),
            validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
          ),
          const SizedBox(height: 14),
          TextFormField(
            controller: _ownerEmailCtrl,
            keyboardType: TextInputType.emailAddress,
            decoration: const InputDecoration(labelText: 'Landlord email'),
          ),
          const SizedBox(height: 14),
          TextFormField(
            controller: _ownerPhoneCtrl,
            keyboardType: TextInputType.phone,
            decoration: const InputDecoration(labelText: 'Landlord phone'),
          ),
          const Padding(
            padding: EdgeInsets.only(top: 4),
            child: Text('Provide at least one of email or phone.', style: TextStyle(fontSize: 11.5, color: AppColors.textMuted)),
          ),
          const SizedBox(height: 14),
          TextFormField(
            controller: _ownerAddressCtrl,
            decoration: const InputDecoration(labelText: 'Landlord address (optional)'),
          ),
          const SizedBox(height: 14),
          TextFormField(
            controller: _ownerBankDetailsCtrl,
            maxLines: 2,
            decoration: const InputDecoration(labelText: 'Landlord bank details (optional)'),
          ),
          const SizedBox(height: 14),
          TextFormField(
            controller: _ownerNotesCtrl,
            maxLines: 2,
            decoration: const InputDecoration(labelText: 'Landlord notes (optional)'),
          ),
          sheetError(_error),
          const SizedBox(height: 8),
          SheetSubmitButton(label: 'Add rental', saving: _saving, onPressed: _submit),
        ],
      ),
    ),
  );
}

class _PayRentalSheet extends StatefulWidget {
  const _PayRentalSheet({required this.rental});
  final Map<String, dynamic> rental;

  @override
  State<_PayRentalSheet> createState() => _PayRentalSheetState();
}

class _PayRentalSheetState extends State<_PayRentalSheet> {
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
      setState(() => _error = 'Pick the payment date.');
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
        ApiEndpoints.financeRentalPay((widget.rental['id'] as num).toInt()),
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
    title: 'Pay "${widget.rental['property_type'] ?? ''}"',
    loading: _loadingOptions,
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        FinanceDateField(label: 'Payment date', value: _dueDate, onPick: (d) => setState(() => _dueDate = d)),
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
