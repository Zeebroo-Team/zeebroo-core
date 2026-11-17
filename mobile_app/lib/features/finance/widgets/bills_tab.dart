import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/date.dart';
import '../../../core/utils/money.dart';
import '../../dashboard/widgets/stat_tile.dart';
import '../../inventory/widgets/list_states.dart';
import '../../inventory/widgets/picker_sheet.dart';
import 'finance_common.dart';
import '../data/bill_scan_repository.dart';

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

String _categoryLabel(String? key) => _kBillCategories
    .firstWhere((c) => c.$1 == key, orElse: () => ('', key ?? ''))
    .$2;

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
  for (final field in [
    'property_name',
    'employee_name',
    'modification_name',
    'department_name',
    'branch_name',
  ]) {
    final v = b[field] as String?;
    if (v != null && v.isNotEmpty) return v;
  }
  final rentalType = b['rental_type'] as String?;
  if (rentalType != null && rentalType.isNotEmpty) return rentalType;
  return null;
}

/// Financial > Bills — list, create, pay (full/partial/split) and delete.
class BillsTab extends StatefulWidget {
  const BillsTab({super.key, this.managerMode = false});
  final bool managerMode;

  @override
  State<BillsTab> createState() => _BillsTabState();
}

class _BillsTabState extends State<BillsTab>
    with AutomaticKeepAliveClientMixin {
  @override
  bool get wantKeepAlive => true;

  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _bills = [];
  String _search = '';
  bool _openingEdit = false;

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
      final res = await ApiClient.instance.get(
        ApiEndpoints.financeBills,
        bypassCache: forceRefresh,
      );
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
      builder: (_) => AddBillSheet(enableScan: !widget.managerMode),
    );
    if (mounted && created == true) _load(forceRefresh: true);
  }

  Future<void> _openEdit(Map<String, dynamic> bill) async {
    if (_openingEdit) return;
    setState(() => _openingEdit = true);
    try {
      final response = await ApiClient.instance.get(
        ApiEndpoints.financeBill((bill['id'] as num).toInt()),
        bypassCache: true,
      );
      if (!mounted) return;
      final changed = await showModalBottomSheet<bool>(
        context: context,
        isScrollControlled: true,
        backgroundColor: Colors.transparent,
        builder: (_) => AddBillSheet(
          enableScan: false,
          bill: Map<String, dynamic>.from(response.data['data'] as Map),
        ),
      );
      if (mounted && changed == true) await _load(forceRefresh: true);
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(apiErrorMessage(e))));
      }
    } finally {
      if (mounted) setState(() => _openingEdit = false);
    }
  }

  Future<void> _handleAction(Map<String, dynamic> bill) async {
    final action = await showFinanceActionSheet(context, [
      if (widget.managerMode)
        (Icons.edit_outlined, 'Edit bill', AppColors.primary)
      else
        (Icons.payments_outlined, 'Pay bill', AppColors.primary),
      (Icons.delete_outline, 'Delete', AppColors.error),
    ]);
    if (!mounted || action == null) return;
    if (action == 'Edit bill') {
      await _openEdit(bill);
    } else if (action == 'Pay bill') {
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
        await ApiClient.instance.delete(
          ApiEndpoints.financeBill((bill['id'] as num).toInt()),
        );
        if (mounted) _load(forceRefresh: true);
      } catch (e) {
        if (mounted) {
          ScaffoldMessenger.of(
            context,
          ).showSnackBar(SnackBar(content: Text(apiErrorMessage(e))));
        }
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
              Expanded(
                child: widget.managerMode
                    ? TextField(
                        decoration: const InputDecoration(
                          hintText: 'Search bills',
                          prefixIcon: Icon(Icons.search),
                          isDense: true,
                        ),
                        onChanged: (value) => setState(
                          () => _search = value.trim().toLowerCase(),
                        ),
                      )
                    : const Text(
                        'Bills',
                        style: TextStyle(
                          fontSize: 15.5,
                          fontWeight: FontWeight.w800,
                          color: AppColors.textDark,
                        ),
                      ),
              ),
              const SizedBox(width: 10),
              AddButton(onTap: _openAdd),
            ],
          ),
        ),
        if (_openingEdit) const LinearProgressIndicator(),
        Expanded(child: _buildBody()),
      ],
    );
  }

  Widget _buildBody() {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null) {
      return ErrorState(error: _error!, onRetry: _load);
    }
    if (_bills.isEmpty) return const EmptyState(message: 'No bills yet.');
    final visibleBills = _bills
        .where(
          (bill) => '${bill['name']} ${bill['category_label']}'
              .toLowerCase()
              .contains(_search),
        )
        .toList();
    if (visibleBills.isEmpty) {
      return const EmptyState(message: 'No matching bills.');
    }

    final overdueCount = _bills
        .where((b) => b['overdue'] as bool? ?? false)
        .length;
    final monthlyTotal = _bills
        .where((b) => b['payment_mode'] == 'recurring')
        .fold<double>(
          0,
          (sum, b) => sum + ((b['amount'] as num?)?.toDouble() ?? 0),
        );

    return RefreshIndicator(
      onRefresh: () => _load(forceRefresh: true),
      child: ListView(
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
        children: [
          if (!widget.managerMode)
            StatGrid(
              tiles: [
                StatTile(
                  label: 'Bills',
                  value: '${_bills.length}',
                  icon: Icons.receipt_long_outlined,
                ),
                StatTile(
                  label: 'Overdue',
                  value: '$overdueCount',
                  icon: Icons.warning_amber_rounded,
                  color: AppColors.error,
                ),
                StatTile(
                  label: 'Monthly total',
                  value: formatMoney(monthlyTotal),
                  icon: Icons.calendar_month_outlined,
                  color: AppColors.primary,
                ),
              ],
            ),
          const SizedBox(height: 14),
          for (final b in visibleBills) ...[
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
                decoration: BoxDecoration(
                  color: AppColors.primaryLt,
                  borderRadius: BorderRadius.circular(10),
                ),
                alignment: Alignment.center,
                child: Icon(
                  _categoryIcon(bill['category'] as String?),
                  size: 16,
                  color: AppColors.primary,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  (bill['name'] as String?) ?? '',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 14.5,
                    fontWeight: FontWeight.w700,
                    color: AppColors.textDark,
                  ),
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
              FinanceBadge(
                label: _categoryLabel(bill['category'] as String?),
                color: AppColors.textMuted,
              ),
              FinanceBadge(
                label: isRecurring
                    ? 'Recurring · ${_recurringLabel(bill['recurring_type'] as String?)}'
                    : 'One-time',
                color: AppColors.textMuted,
              ),
              if (varies)
                const FinanceBadge(
                  label: 'Varies by usage',
                  color: AppColors.warning,
                ),
              if (assignment != null)
                FinanceBadge(
                  label: 'via $assignment',
                  color: AppColors.primary,
                ),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'Due ${(bill['due_date_fmt'] as String?) ?? formatDate(bill['due_date'] as String?)}',
                style: const TextStyle(
                  fontSize: 12,
                  color: AppColors.textMuted,
                ),
              ),
              Text(
                varies ? 'Varies' : formatMoney(bill['amount']),
                style: const TextStyle(
                  fontSize: 14.5,
                  fontWeight: FontWeight.w800,
                  color: AppColors.textDark,
                ),
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
  const AddBillSheet({
    super.key,
    this.repository = const BillScanRepository(),
    this.enableScan = true,
    this.bill,
  });
  final BillScanRepository repository;
  final bool enableScan;
  final Map<String, dynamic>? bill;

  @override
  State<AddBillSheet> createState() => AddBillSheetState();
}

class AddBillSheetState extends State<AddBillSheet> {
  final _formKey = GlobalKey<FormState>();
  final _nameCtrl = TextEditingController();
  final _categoryOtherCtrl = TextEditingController();
  final _descriptionCtrl = TextEditingController();
  final _agreementYearCtrl = TextEditingController(
    text: '${DateTime.now().year + 1}',
  );
  final _costCtrl = TextEditingController();
  final _remindCtrl = TextEditingController();
  final _notesCtrl = TextEditingController();

  bool _loadingOptions = true;
  bool _saving = false;
  bool _scanning = false;
  bool _scanned = false;
  bool _showScanNotice = true;
  final _scannedFields = <String>{};
  int _scanRevision = 0;
  String? _error;

  String? _category = 'electricity';
  String? _paymentMode = 'recurring';
  String? _recurringType = 'per_month';
  String _assignmentType = 'none';
  bool? _amountVaries = false;
  bool? _allowSplit = true;
  DateTime? _dueDate;
  DateTime? _firstInstallmentDate;
  int? _assignmentId;
  int? _accountId;

  Map<String, dynamic> _targets = {};
  List<Map<String, dynamic>> _accounts = [];

  @override
  void initState() {
    super.initState();
    final bill = widget.bill;
    if (bill != null) {
      _nameCtrl.text = bill['name'] ?? '';
      _category = bill['bill_category'];
      _categoryOtherCtrl.text = bill['bill_category_other'] ?? '';
      _descriptionCtrl.text = bill['description'] ?? '';
      _paymentMode = bill['payment_mode'];
      _recurringType = bill['recurring_type'];
      _agreementYearCtrl.text =
          bill['agreement_valid_until_year']?.toString() ?? '';
      _costCtrl.text = bill['recurring_cost']?.toString() ?? '';
      _remindCtrl.text = bill['remind_before_days']?.toString() ?? '';
      _notesCtrl.text = bill['notes'] ?? '';
      _dueDate = DateTime.tryParse(bill['due_date'] ?? '');
      _firstInstallmentDate = DateTime.tryParse(
        bill['first_installment_due_date'] ?? '',
      );
      _amountVaries = bill['amount_varies_by_usage'] ?? false;
      _allowSplit = bill['allow_split_payment'] ?? true;
      _assignmentType = bill['assignment_type'] ?? 'none';
      _assignmentId = (bill['${_assignmentType}_id'] as num?)?.toInt();
      _accountId = (bill['deduct_account_id'] as num?)?.toInt();
    }
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
      final options = await widget.repository.loadOptions();
      if (!mounted) return;
      _targets = options.targets;
      _accounts = options.accounts;
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
    return list
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e))
        .toList();
  }

  Future<void> _scanBill() async {
    if (_scanning || _saving) return;
    FocusScope.of(context).unfocus();
    final source = await showModalBottomSheet<ImageSource>(
      context: context,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Padding(
              padding: EdgeInsets.all(16),
              child: Text(
                'Choose a bill image',
                style: TextStyle(fontWeight: FontWeight.w700),
              ),
            ),
            const Padding(
              padding: EdgeInsets.symmetric(horizontal: 20),
              child: Text(
                'The image will be sent to Google Gemini to fill this form. Review all fields before saving.',
                style: TextStyle(fontSize: 12),
              ),
            ),
            ListTile(
              leading: const Icon(Icons.camera_alt_outlined),
              title: const Text('Camera'),
              onTap: () => Navigator.pop(ctx, ImageSource.camera),
            ),
            ListTile(
              leading: const Icon(Icons.photo_library_outlined),
              title: const Text('Gallery'),
              onTap: () => Navigator.pop(ctx, ImageSource.gallery),
            ),
          ],
        ),
      ),
    );
    if (!mounted || source == null) return;
    setState(() {
      _scanning = true;
      _error = null;
    });
    try {
      final image = await widget.repository.pick(source);
      if (!mounted || image == null) return;
      final draft = await widget.repository.scan(image);
      if (!mounted) return;
      final values = draft.values;
      setState(() {
        _nameCtrl.text = values['name'] ?? '';
        _category = values['bill_category'];
        _categoryOtherCtrl.text = values['bill_category_other'] ?? '';
        _descriptionCtrl.text = values['description'] ?? '';
        _paymentMode = values['payment_mode'];
        _recurringType = values['recurring_type'];
        _agreementYearCtrl.text =
            values['agreement_valid_until_year']?.toString() ?? '';
        _dueDate = DateTime.tryParse(values['due_date'] ?? '');
        _firstInstallmentDate = DateTime.tryParse(
          values['first_installment_due_date'] ?? '',
        );
        _amountVaries = values['amount_varies_by_usage'];
        _costCtrl.text =
            (values['recurring_cost'] as double?)?.toStringAsFixed(2) ?? '';
        _allowSplit = values['allow_split_payment'];
        _remindCtrl.text = values['remind_before_days']?.toString() ?? '';
        _notesCtrl.text = values['notes'] ?? '';
        _scannedFields
          ..clear()
          ..addAll(
            values.entries
                .where((field) => field.value != null)
                .map((field) => field.key),
          );
        _scanned = true;
        _showScanNotice = true;
        ++_scanRevision; // Refresh DropdownButtonFormField's initial selections.
      });
    } catch (error) {
      if (mounted) setState(() => _error = apiErrorMessage(error));
    } finally {
      if (mounted) setState(() => _scanning = false);
    }
  }

  Future<void> _submit() async {
    if (_saving || _scanning) return;
    final formOk = _formKey.currentState?.validate() ?? false;
    if (!formOk) return;
    if (_paymentMode == null || _amountVaries == null || _allowSplit == null) {
      setState(
        () => _error =
            'Choose a payment mode, Amount varies by usage, and Allow split payment before saving.',
      );
      return;
    }
    if (_category == 'other' && _categoryOtherCtrl.text.trim().isEmpty) {
      setState(() => _error = 'Enter the custom bill category.');
      return;
    }
    if (_paymentMode == 'one_time' &&
        _dueDate == null &&
        _firstInstallmentDate == null) {
      setState(
        () => _error =
            'Pick a due date or first installment date for a one-time bill.',
      );
      return;
    }
    if (_assignmentType != 'none' && _assignmentId == null) {
      setState(
        () => _error = 'Pick a $_assignmentType to assign this bill to.',
      );
      return;
    }

    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      final fields = <String, dynamic>{
        // Explicit nulls let edits clear optional values instead of retaining them.
        if (widget.bill != null) ...{
          'description': null,
          'notes': null,
          'due_date': null,
          'first_installment_due_date': null,
          'remind_before_days': null,
          'deduct_account_id': null,
        },
        'rental_property_related': _assignmentType == 'rental',
        'name': _nameCtrl.text.trim(),
        'bill_category': _category,
        if (_category == 'other')
          'bill_category_other': _categoryOtherCtrl.text.trim(),
        if (_descriptionCtrl.text.trim().isNotEmpty)
          'description': _descriptionCtrl.text.trim(),
        'payment_mode': _paymentMode,
        if (_paymentMode == 'recurring') ...{
          'recurring_type': _recurringType,
          'agreement_valid_until_year': int.tryParse(
            _agreementYearCtrl.text.trim(),
          ),
        },
        if (_dueDate != null) 'due_date': toApiDate(_dueDate!),
        if (_firstInstallmentDate != null)
          'first_installment_due_date': toApiDate(_firstInstallmentDate!),
        'amount_varies_by_usage': _amountVaries,
        if (_amountVaries == false)
          'recurring_cost': double.tryParse(_costCtrl.text.trim()),
        'allow_split_payment': _allowSplit,
        if (_remindCtrl.text.trim().isNotEmpty)
          'remind_before_days': int.tryParse(_remindCtrl.text.trim()),
        'assignment_type': _assignmentType,
        if (_assignmentType != 'none' && _assignmentId != null)
          '${_assignmentType}_id': _assignmentId,
        if (_accountId != null) 'deduct_account_id': _accountId,
        if (_notesCtrl.text.trim().isNotEmpty) 'notes': _notesCtrl.text.trim(),
      };
      if (widget.bill == null) {
        await widget.repository.save(fields);
      } else {
        await widget.repository.update(
          (widget.bill!['id'] as num).toInt(),
          fields,
        );
      }
      if (mounted) Navigator.pop(context, true);
    } catch (e) {
      if (mounted) setState(() => _error = apiErrorMessage(e));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  void _edited(String field) {
    if (_scannedFields.contains(field)) {
      setState(() => _scannedFields.remove(field));
    }
  }

  Widget? _scanMarker(String field) => _scannedFields.contains(field)
      ? Tooltip(
          message: 'Filled from your bill. Please review.',
          child: Icon(
            Icons.auto_awesome_rounded,
            key: ValueKey('bill-sparkle-$field'),
            size: 17,
            color: const Color(0xFF2563EB),
          ),
        )
      : null;

  Widget _dropdownIcon(String field) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      if (_scanMarker(field) case final marker?) ...[
        marker,
        const SizedBox(width: 8),
      ],
      const Icon(Icons.arrow_drop_down),
    ],
  );

  Widget _switchTitle(String text, String field) => Row(
    children: [
      Expanded(
        child: Text(
          text,
          style: const TextStyle(fontSize: 14.5, fontWeight: FontWeight.w600),
        ),
      ),
      if (_scanMarker(field) case final marker?) ...[
        const SizedBox(width: 8),
        marker,
      ],
    ],
  );

  Widget _scanCard() => Container(
    key: const ValueKey('bill-scan-card'),
    width: double.infinity,
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      color: const Color(0xFFEFF6FF),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Row(
          children: [
            Icon(
              Icons.document_scanner_outlined,
              size: 22,
              color: Color(0xFF1E3A8A),
            ),
            SizedBox(width: 10),
            Expanded(
              child: Text(
                'Have the bill with you?',
                style: TextStyle(
                  fontSize: 17,
                  fontWeight: FontWeight.w700,
                  color: Color(0xFF1E3A8A),
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 10),
        const Text(
          'Take a photo and we’ll fill in what we can read. You can change anything before saving.',
          style: TextStyle(fontSize: 13, height: 1.5, color: Color(0xFF1E3A8A)),
        ),
        const SizedBox(height: 14),
        SizedBox(
          width: double.infinity,
          child: FilledButton.icon(
            key: const ValueKey('scan-bill'),
            style: FilledButton.styleFrom(
              backgroundColor: const Color(0xFF2563EB),
              foregroundColor: Colors.white,
              disabledBackgroundColor: const Color(
                0xFF2563EB,
              ).withValues(alpha: 0.7),
              disabledForegroundColor: Colors.white,
              minimumSize: const Size(0, 48),
              shape: const StadiumBorder(),
              textStyle: const TextStyle(
                fontSize: 15,
                fontWeight: FontWeight.w700,
              ),
            ),
            onPressed: _scanning || _saving ? null : _scanBill,
            icon: _scanning
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: Colors.white,
                    ),
                  )
                : const Icon(Icons.camera_alt_outlined, size: 20),
            label: Text(_scanning ? 'Reading your bill...' : 'Scan bill'),
          ),
        ),
        if (_scanned && _showScanNotice) ...[
          const SizedBox(height: 12),
          Row(
            key: const ValueKey('bill-scan-notice'),
            children: [
              const Icon(
                Icons.auto_awesome_rounded,
                size: 17,
                color: Color(0xFF2563EB),
              ),
              const SizedBox(width: 8),
              const Expanded(
                child: Text(
                  'Fields marked with a sparkle came from your bill. Check them before saving.',
                  style: TextStyle(
                    fontSize: 12,
                    height: 1.5,
                    color: Color(0xFF1E3A8A),
                  ),
                ),
              ),
              IconButton(
                tooltip: 'Dismiss scan notice',
                onPressed: () => setState(() => _showScanNotice = false),
                icon: const Icon(
                  Icons.close_rounded,
                  size: 18,
                  color: Color(0xFF1E3A8A),
                ),
              ),
            ],
          ),
        ],
      ],
    ),
  );

  @override
  Widget build(BuildContext context) => FormSheetShell(
    title: widget.bill == null ? 'Add bill' : 'Edit bill',
    loading: _loadingOptions,
    showBackButton: true,
    child: Material(
      color: Colors.transparent,
      child: AbsorbPointer(
        absorbing: _scanning || _saving,
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              if (widget.enableScan) ...[
                _scanCard(),
                const SizedBox(height: 22),
              ],
              const Text(
                'Bill details',
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w700,
                  color: AppColors.textDark,
                ),
              ),
              const SizedBox(height: 14),
              TextFormField(
                key: const ValueKey('bill-name'),
                controller: _nameCtrl,
                maxLength: 255,
                decoration: InputDecoration(
                  labelText: 'Bill name',
                  suffixIcon: _scanMarker('name'),
                ),
                onChanged: (_) => _edited('name'),
                validator: (v) =>
                    (v == null || v.trim().isEmpty) ? 'Required' : null,
              ),
              const SizedBox(height: 14),
              DropdownButtonFormField<String>(
                key: ValueKey('bill-category-$_scanRevision'),
                initialValue: _category,
                icon: _dropdownIcon('bill_category'),
                decoration: const InputDecoration(labelText: 'Category'),
                items: [
                  for (final c in _kBillCategories)
                    DropdownMenuItem(value: c.$1, child: Text(c.$2)),
                ],
                validator: (v) => v == null ? 'Choose a category' : null,
                onChanged: (v) => setState(() {
                  _category = v;
                  _scannedFields.remove('bill_category');
                }),
              ),
              if (_category == 'other') ...[
                const SizedBox(height: 14),
                TextFormField(
                  controller: _categoryOtherCtrl,
                  maxLength: 255,
                  onChanged: (_) => _edited('bill_category_other'),
                  decoration: InputDecoration(
                    labelText: 'Custom category',
                    suffixIcon: _scanMarker('bill_category_other'),
                  ),
                ),
              ],
              const SizedBox(height: 14),
              Row(
                children: [
                  Expanded(
                    child: SegmentedButton<String>(
                      segments: const [
                        ButtonSegment(
                          value: 'one_time',
                          label: Text('One-time'),
                        ),
                        ButtonSegment(
                          value: 'recurring',
                          label: Text('Recurring'),
                        ),
                      ],
                      emptySelectionAllowed: true,
                      selected: {?_paymentMode},
                      onSelectionChanged: (s) => setState(() {
                        _paymentMode = s.isEmpty ? null : s.first;
                        _scannedFields.remove('payment_mode');
                      }),
                    ),
                  ),
                  if (_scanMarker('payment_mode') case final marker?) ...[
                    const SizedBox(width: 8),
                    marker,
                  ],
                ],
              ),
              if (_paymentMode == null)
                const Padding(
                  padding: EdgeInsets.only(top: 6),
                  child: Text(
                    'Choose One-time or Recurring.',
                    style: TextStyle(fontSize: 12, color: AppColors.textMuted),
                  ),
                ),
              if (_paymentMode == 'recurring') ...[
                const SizedBox(height: 14),
                DropdownButtonFormField<String>(
                  key: ValueKey('bill-cadence-$_scanRevision'),
                  initialValue: _recurringType,
                  icon: _dropdownIcon('recurring_type'),
                  decoration: const InputDecoration(
                    labelText: 'Billing cadence',
                  ),
                  items: [
                    for (final r in _kRecurringTypes)
                      DropdownMenuItem(value: r.$1, child: Text(r.$2)),
                  ],
                  validator: (v) =>
                      v == null ? 'Choose a billing cadence' : null,
                  onChanged: (v) => setState(() {
                    _recurringType = v;
                    _scannedFields.remove('recurring_type');
                  }),
                ),
                const SizedBox(height: 14),
                TextFormField(
                  key: const ValueKey('bill-agreement-year'),
                  controller: _agreementYearCtrl,
                  keyboardType: TextInputType.number,
                  onChanged: (_) => _edited('agreement_valid_until_year'),
                  decoration: InputDecoration(
                    labelText: 'Agreement valid until (year)',
                    suffixIcon: _scanMarker('agreement_valid_until_year'),
                  ),
                  validator: (v) {
                    final year = int.tryParse((v ?? '').trim());
                    return year == null || year < 2000 || year > 2100
                        ? 'Enter a year from 2000 to 2100'
                        : null;
                  },
                ),
              ],
              const SizedBox(height: 14),
              FinanceDateField(
                label: _paymentMode == 'one_time'
                    ? 'Due date'
                    : 'Due date (optional)',
                value: _dueDate,
                indicator: _scanMarker('due_date'),
                onPick: (d) => setState(() {
                  _dueDate = d;
                  _scannedFields.remove('due_date');
                }),
              ),
              const SizedBox(height: 14),
              FinanceDateField(
                label: 'First installment date (optional)',
                value: _firstInstallmentDate,
                indicator: _scanMarker('first_installment_due_date'),
                onPick: (d) => setState(() {
                  _firstInstallmentDate = d;
                  _scannedFields.remove('first_installment_due_date');
                }),
              ),
              const SizedBox(height: 6),
              if (_amountVaries == null)
                DropdownButtonFormField<bool>(
                  key: ValueKey('bill-varies-$_scanRevision'),
                  decoration: const InputDecoration(
                    labelText: 'Amount varies by usage',
                  ),
                  items: const [
                    DropdownMenuItem(value: true, child: Text('Yes')),
                    DropdownMenuItem(value: false, child: Text('No')),
                  ],
                  validator: (v) => v == null ? 'Choose Yes or No' : null,
                  onChanged: (v) => setState(() => _amountVaries = v),
                )
              else
                SwitchListTile.adaptive(
                  contentPadding: EdgeInsets.zero,
                  title: _switchTitle(
                    'Amount varies by usage',
                    'amount_varies_by_usage',
                  ),
                  value: _amountVaries!,
                  activeThumbColor: AppColors.primary,
                  onChanged: (v) => setState(() {
                    _amountVaries = v;
                    _scannedFields.remove('amount_varies_by_usage');
                  }),
                ),
              if (_amountVaries != true) ...[
                const SizedBox(height: 8),
                TextFormField(
                  key: const ValueKey('bill-amount'),
                  controller: _costCtrl,
                  keyboardType: const TextInputType.numberWithOptions(
                    decimal: true,
                  ),
                  decoration: InputDecoration(
                    labelText: 'Amount',
                    suffixIcon: _scanMarker('recurring_cost'),
                  ),
                  onChanged: (_) => _edited('recurring_cost'),
                  validator: (v) {
                    if (_amountVaries == true) return null;
                    final n = double.tryParse((v ?? '').trim());
                    return (n == null ||
                            !n.isFinite ||
                            n < 0 ||
                            n > 9999999999.99)
                        ? 'Enter a valid amount'
                        : null;
                  },
                ),
              ],
              if (_allowSplit == null)
                DropdownButtonFormField<bool>(
                  key: ValueKey('bill-split-$_scanRevision'),
                  decoration: const InputDecoration(
                    labelText: 'Allow split payment',
                  ),
                  items: const [
                    DropdownMenuItem(value: true, child: Text('Yes')),
                    DropdownMenuItem(value: false, child: Text('No')),
                  ],
                  validator: (v) => v == null ? 'Choose Yes or No' : null,
                  onChanged: (v) => setState(() => _allowSplit = v),
                )
              else
                SwitchListTile.adaptive(
                  contentPadding: EdgeInsets.zero,
                  title: _switchTitle(
                    'Allow split payment',
                    'allow_split_payment',
                  ),
                  value: _allowSplit!,
                  activeThumbColor: AppColors.primary,
                  onChanged: (v) => setState(() {
                    _allowSplit = v;
                    _scannedFields.remove('allow_split_payment');
                  }),
                ),
              const SizedBox(height: 8),
              TextFormField(
                key: const ValueKey('bill-remind'),
                controller: _remindCtrl,
                keyboardType: TextInputType.number,
                onChanged: (_) => _edited('remind_before_days'),
                decoration: InputDecoration(
                  labelText: 'Remind before (days, optional)',
                  suffixIcon: _scanMarker('remind_before_days'),
                ),
                validator: (v) {
                  if (v == null || v.trim().isEmpty) return null;
                  final days = int.tryParse(v.trim());
                  return days == null || days < 0 || days > 366
                      ? 'Enter 0–366 days'
                      : null;
                },
              ),
              const SizedBox(height: 14),
              DropdownButtonFormField<String>(
                initialValue: _assignmentType,
                decoration: const InputDecoration(labelText: 'Assign to'),
                items: [
                  for (final a in _kAssignmentTypes)
                    DropdownMenuItem(value: a.$1, child: Text(a.$2)),
                ],
                onChanged: (v) => setState(() {
                  _assignmentType = v ?? 'none';
                  _assignmentId = null;
                }),
              ),
              if (_assignmentType != 'none') ...[
                const SizedBox(height: 14),
                DropdownButtonFormField<int>(
                  key: ValueKey('bill-assignment-$_assignmentType'),
                  isExpanded: true,
                  initialValue: _assignmentId,
                  decoration: InputDecoration(
                    labelText: _kAssignmentTypes
                        .firstWhere((a) => a.$1 == _assignmentType)
                        .$2,
                  ),
                  items: [
                    if (_assignmentId != null &&
                        !_assignmentOptions.any(
                          (t) => t['id'] == _assignmentId,
                        ))
                      DropdownMenuItem(
                        value: _assignmentId,
                        child: const Text('Current assignment (unavailable)'),
                      ),
                    for (final t in _assignmentOptions)
                      DropdownMenuItem(
                        value: (t['id'] as num).toInt(),
                        child: Text(t['name'] as String? ?? ''),
                      ),
                  ],
                  onChanged: (v) => setState(() => _assignmentId = v),
                ),
              ],
              const SizedBox(height: 14),
              DropdownButtonFormField<int?>(
                isExpanded: true,
                initialValue: _accountId,
                decoration: const InputDecoration(
                  labelText: 'Debit account (optional)',
                ),
                items: [
                  if (_accountId != null &&
                      !_accounts.any((a) => a['id'] == _accountId))
                    DropdownMenuItem(
                      value: _accountId,
                      child: const Text('Current account (unavailable)'),
                    ),
                  const DropdownMenuItem(value: null, child: Text('—')),
                  for (final a in _accounts)
                    DropdownMenuItem(
                      value: (a['id'] as num).toInt(),
                      child: Text(a['account_name'] as String? ?? ''),
                    ),
                ],
                onChanged: (v) => setState(() => _accountId = v),
              ),
              const SizedBox(height: 14),
              TextFormField(
                key: const ValueKey('bill-description'),
                controller: _descriptionCtrl,
                maxLength: 2000,
                maxLines: 2,
                onChanged: (_) => _edited('description'),
                decoration: InputDecoration(
                  labelText: 'Description (optional)',
                  suffixIcon: _scanMarker('description'),
                ),
              ),
              const SizedBox(height: 14),
              TextFormField(
                key: const ValueKey('bill-notes'),
                controller: _notesCtrl,
                maxLength: 5000,
                maxLines: 3,
                onChanged: (_) => _edited('notes'),
                decoration: InputDecoration(
                  labelText: 'Notes (optional)',
                  suffixIcon: _scanMarker('notes'),
                ),
              ),
              sheetError(_error),
              const SizedBox(height: 8),
              SheetSubmitButton(
                label: widget.bill == null ? 'Add bill' : 'Save changes',
                saving: _saving,
                onPressed: _scanning ? null : _submit,
              ),
            ],
          ),
        ),
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

  bool get _amountVaries =>
      widget.bill['amount_varies_by_usage'] as bool? ?? false;

  Future<void> _submit() async {
    if (_occurrenceDate == null) {
      setState(() => _error = 'Pick the payment date.');
      return;
    }
    if (_option != 'split' && _accountId == null) {
      setState(() => _error = 'Pick a debit account.');
      return;
    }
    if (_option == 'partial' &&
        (double.tryParse(_partialAmountCtrl.text.trim()) ?? -1) <= 0) {
      setState(() => _error = 'Enter a valid partial amount.');
      return;
    }
    if (_option == 'split') {
      final valid = _splitRows.where(
        (r) =>
            r.accountId != null &&
            (double.tryParse(r.amount.text.trim()) ?? 0) > 0,
      );
      if (valid.length < 2) {
        setState(
          () => _error =
              'Add at least two split rows with an account and amount.',
        );
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
          if (_option == 'partial')
            'partial_amount': double.tryParse(_partialAmountCtrl.text.trim()),
          if (_option == 'split')
            'split_rows': [
              for (final r in _splitRows)
                if (r.accountId != null &&
                    (double.tryParse(r.amount.text.trim()) ?? 0) > 0)
                  {
                    'deduct_account_id': r.accountId,
                    'amount': double.tryParse(r.amount.text.trim()),
                  },
            ],
          if (_amountVaries)
            'period_charge_total': double.tryParse(
              _periodChargeCtrl.text.trim(),
            ),
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
        FinanceDateField(
          label: 'Payment date',
          value: _occurrenceDate,
          onPick: (d) => setState(() => _occurrenceDate = d),
        ),
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
                DropdownMenuItem(
                  value: (a['id'] as num).toInt(),
                  child: Text(a['account_name'] as String? ?? ''),
                ),
            ],
            onChanged: (v) => setState(() => _accountId = v),
          ),
          if (_option == 'partial') ...[
            const SizedBox(height: 14),
            TextFormField(
              controller: _partialAmountCtrl,
              keyboardType: const TextInputType.numberWithOptions(
                decimal: true,
              ),
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
                        DropdownMenuItem(
                          value: (a['id'] as num).toInt(),
                          child: Text(a['account_name'] as String? ?? ''),
                        ),
                    ],
                    onChanged: (v) => setState(
                      () => _splitRows[i] = (
                        accountId: v,
                        amount: _splitRows[i].amount,
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  flex: 2,
                  child: TextFormField(
                    controller: _splitRows[i].amount,
                    keyboardType: const TextInputType.numberWithOptions(
                      decimal: true,
                    ),
                    decoration: const InputDecoration(labelText: 'Amount'),
                  ),
                ),
                if (_splitRows.length > 2)
                  IconButton(
                    icon: const Icon(
                      Icons.remove_circle_outline,
                      size: 20,
                      color: AppColors.error,
                    ),
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
            onPressed: () => setState(
              () => _splitRows.add((
                accountId: null,
                amount: TextEditingController(),
              )),
            ),
            icon: const Icon(Icons.add, size: 18),
            label: const Text('Add row'),
          ),
        ],
        if (_amountVaries) ...[
          const SizedBox(height: 14),
          TextFormField(
            controller: _periodChargeCtrl,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: const InputDecoration(
              labelText: 'Total usage charge for this period',
            ),
          ),
        ],
        sheetError(_error),
        const SizedBox(height: 8),
        SheetSubmitButton(
          label: 'Record payment',
          saving: _saving,
          onPressed: _submit,
        ),
      ],
    ),
  );
}
