import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/date.dart';
import '../../../core/utils/money.dart';
import '../widgets/customer_form_sheet.dart';

/// A customer's details, recent sales and credit (dues + receiving a
/// payment), like the web customer panel. Pops `true` if anything changed
/// (edited, deleted or paid), so the list knows to refresh.
class CustomerDetailScreen extends StatefulWidget {
  const CustomerDetailScreen({super.key, required this.customerId});

  final int customerId;

  @override
  State<CustomerDetailScreen> createState() => _CustomerDetailScreenState();
}

enum _Section { sales, credit, cheques }

class _CustomerDetailScreenState extends State<CustomerDetailScreen> {
  final _amountController = TextEditingController();

  bool _loading = true;
  String? _error;
  Map<String, dynamic>? _customer;
  List<Map<String, dynamic>> _creditSales = [];
  List<Map<String, dynamic>> _payments = [];
  List<Map<String, dynamic>> _cheques = [];
  Map<String, dynamic> _chequeSummary = {};
  String? _chequesError;
  _Section _section = _Section.sales;
  String _paymentMethod = 'cash';
  bool _paying = false;
  bool _changed = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _amountController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final results = await Future.wait([
        ApiClient.instance.get(
          ApiEndpoints.customer(widget.customerId),
          bypassCache: true,
        ),
        ApiClient.instance.get(
          ApiEndpoints.customerCreditSales(widget.customerId),
          bypassCache: true,
        ),
      ]);
      final body = results[0].data;
      _customer = (body is Map ? body['data'] as Map? : null)
          ?.cast<String, dynamic>();
      final credit = results[1].data;
      _creditSales = _rows(credit is Map ? credit['data'] : null);
      _payments = _rows(credit is Map ? credit['payments'] : null);
      await _loadCheques();
    } catch (e) {
      _error = apiErrorMessage(e);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  /// Cheques are a separate permission area: a failure here only affects
  /// the Cheques section, not the whole screen.
  Future<void> _loadCheques() async {
    try {
      final res = await ApiClient.instance.get(
        ApiEndpoints.saleCheques,
        params: {'customer_id': widget.customerId, 'per_page': 100},
        bypassCache: true,
      );
      final body = res.data;
      _cheques = _rows(body is Map ? body['data'] : null);
      _chequeSummary = body is Map && body['summary'] is Map
          ? Map<String, dynamic>.from(body['summary'] as Map)
          : {};
      _chequesError = null;
    } catch (e) {
      _chequesError = apiErrorMessage(e);
    }
  }

  static List<Map<String, dynamic>> _rows(dynamic list) => (list as List? ?? [])
      .whereType<Map>()
      .map((e) => Map<String, dynamic>.from(e))
      .toList();

  static double? _num(dynamic value) =>
      value is num ? value.toDouble() : double.tryParse('${value ?? ''}');

  double get _outstanding => _num(_customer?['credit_outstanding']) ?? 0;

  Future<void> _edit() async {
    final saved = await showCustomerFormSheet(context, existing: _customer);
    if (saved == null) return;
    _changed = true;
    await _load();
  }

  Future<void> _delete() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Delete customer'),
        content: Text('Delete "${_customer?['name']}" permanently?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text(
              'Delete',
              style: TextStyle(color: AppColors.error),
            ),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    try {
      await ApiClient.instance.delete(ApiEndpoints.customer(widget.customerId));
      if (mounted) Navigator.pop(context, true);
    } catch (e) {
      _snack(apiErrorMessage(e));
    }
  }

  Future<void> _receivePayment() async {
    final amount = double.tryParse(_amountController.text.trim()) ?? 0;
    if (amount <= 0) {
      _snack('Enter the amount received.');
      return;
    }
    if (amount > _outstanding + 0.001) {
      _snack('Amount is more than the ${formatMoney(_outstanding)} due.');
      return;
    }
    FocusScope.of(context).unfocus();
    setState(() => _paying = true);
    try {
      final res = await ApiClient.instance.post(
        ApiEndpoints.customerCreditPayments(widget.customerId),
        data: {'amount': amount, 'payment_method': _paymentMethod},
      );
      final body = res.data;
      _snack(
        body is Map && body['message'] is String
            ? body['message'] as String
            : 'Payment received.',
      );
      _amountController.clear();
      _changed = true;
      await _load();
    } catch (e) {
      _snack(apiErrorMessage(e));
    } finally {
      if (mounted) setState(() => _paying = false);
    }
  }

  void _snack(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) => PopScope(
    canPop: false,
    onPopInvokedWithResult: (didPop, _) {
      if (!didPop) Navigator.pop(context, _changed);
    },
    child: Scaffold(
      backgroundColor: AppColors.surface,
      appBar: AppBar(
        backgroundColor: AppColors.surface,
        foregroundColor: AppColors.textDark,
        elevation: 0,
        title: const Text(
          'Customer',
          style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16),
        ),
      ),
      body: _buildBody(),
      bottomNavigationBar: _customer == null ? null : _buildActions(),
    ),
  );

  Widget _buildBody() {
    if (_loading && _customer == null) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_customer == null) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              _error ?? 'Not found',
              style: const TextStyle(color: AppColors.textMuted),
            ),
            const SizedBox(height: 10),
            TextButton(onPressed: _load, child: const Text('Retry')),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 32),
        children: [
          _buildHeader(),
          const SizedBox(height: 16),
          _buildInfoCard(),
          const SizedBox(height: 18),
          SegmentedButton<_Section>(
            showSelectedIcon: false,
            segments: const [
              ButtonSegment(value: _Section.sales, label: Text('Sales')),
              ButtonSegment(value: _Section.credit, label: Text('Credit')),
              ButtonSegment(value: _Section.cheques, label: Text('Cheques')),
            ],
            selected: {_section},
            onSelectionChanged: (s) => setState(() => _section = s.first),
          ),
          const SizedBox(height: 16),
          ...switch (_section) {
            _Section.sales => _buildRecentSales(),
            _Section.credit => _buildCredit(),
            _Section.cheques => _buildCheques(),
          },
        ],
      ),
    );
  }

  Widget _buildHeader() {
    final c = _customer!;
    final name = c['name']?.toString() ?? '';
    final sales = (c['sales_count'] as num?)?.toInt() ?? 0;
    final wholesale = c['customer_type']?.toString() == 'wholesale';
    return Row(
      children: [
        CircleAvatar(
          radius: 26,
          backgroundColor: AppColors.primary,
          child: Text(
            name.isEmpty ? '?' : name[0].toUpperCase(),
            style: const TextStyle(
              color: Colors.white,
              fontSize: 20,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
        const SizedBox(width: 14),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                name,
                style: const TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w800,
                  color: AppColors.textDark,
                ),
              ),
              const SizedBox(height: 6),
              Wrap(
                spacing: 6,
                children: [
                  _pill(
                    '$sales ${sales == 1 ? 'sale' : 'sales'}',
                    AppColors.primary,
                    AppColors.primaryLt,
                  ),
                  wholesale
                      ? _pill(
                          'Wholesale',
                          const Color(0xFF7C3AED),
                          const Color(0xFFEDE9FE),
                        )
                      : _pill(
                          'Retail',
                          AppColors.primary,
                          AppColors.primaryLt,
                        ),
                ],
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildInfoCard() {
    final c = _customer!;
    String text(String key) {
      final v = c[key]?.toString().trim() ?? '';
      return v.isEmpty ? '—' : v;
    }

    final limit = _num(c['credit_limit']);
    final available = _num(c['credit_available']);
    return _card(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
      child: Column(
        children: [
          _kv('Phone', text('phone')),
          _kv('Email', text('email')),
          _kv('Address', text('address')),
          _kv('Category', text('category_name')),
          _kv(
            'Credit limit',
            limit == null
                ? 'No limit'
                : 'LKR ${formatMoney(limit)} · ${formatMoney(available)} available',
          ),
          _kv('Notes', text('notes'), last: true),
        ],
      ),
    );
  }

  List<Widget> _buildRecentSales() {
    final sales = _rows(_customer!['recent_sales']);
    if (sales.isEmpty) return [_empty('No sales yet.')];
    return [
      for (final s in sales)
        _row(
          title: s['sale_number']?.toString() ?? '',
          subtitle: [
            formatDate(s['sold_at']?.toString()),
            _methodLabel(s['payment_method']?.toString()),
          ].join(' · '),
          trailing: Text(
            formatMoney(_num(s['total'])),
            style: const TextStyle(
              fontWeight: FontWeight.w700,
              color: AppColors.textDark,
            ),
          ),
        ),
    ];
  }

  List<Widget> _buildCredit() {
    final c = _customer!;
    final limit = _num(c['credit_limit']);
    final available = _num(c['credit_available']);
    final outstanding = _outstanding;
    final used = limit == null || limit <= 0
        ? null
        : (outstanding / limit).clamp(0.0, 1.0);

    return [
      Row(
        children: [
          _stat(
            'Credit limit',
            limit == null ? 'No limit' : formatMoney(limit),
            AppColors.textDark,
            const Color(0xFFF3F4F6),
          ),
          const SizedBox(width: 8),
          _stat(
            'Outstanding',
            formatMoney(outstanding),
            const Color(0xFFC2410C),
            const Color(0xFFFFF7ED),
          ),
          const SizedBox(width: 8),
          _stat(
            'Available',
            available == null ? '—' : formatMoney(available),
            AppColors.success,
            const Color(0xFFF0FDF4),
          ),
        ],
      ),
      if (used != null) ...[
        const SizedBox(height: 12),
        ClipRRect(
          borderRadius: BorderRadius.circular(999),
          child: LinearProgressIndicator(
            value: used,
            minHeight: 6,
            backgroundColor: const Color(0xFFF3F4F6),
            color: used >= 1 ? AppColors.error : AppColors.warning,
          ),
        ),
        const SizedBox(height: 4),
        Align(
          alignment: Alignment.centerRight,
          child: Text(
            '${(used * 100).round()}% of credit limit used',
            style: const TextStyle(fontSize: 11.5, color: AppColors.textMuted),
          ),
        ),
      ],
      const SizedBox(height: 14),
      if (outstanding > 0) ...[_buildPaymentCard(), const SizedBox(height: 16)],
      const Text(
        'Credit sales',
        style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14),
      ),
      const SizedBox(height: 8),
      if (_creditSales.isEmpty) _empty('No credit sales.'),
      for (final s in _creditSales)
        _row(
          title: s['sale_number']?.toString() ?? '',
          subtitle: [
            formatDate(s['sold_at']?.toString()),
            'Total ${formatMoney(_num(s['total']))}',
            if (s['credit_due_date'] != null)
              'Due ${formatDate(s['credit_due_date']?.toString())}',
          ].join(' · '),
          trailing: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              s['is_paid'] == true
                  ? _pill('Paid', AppColors.success, const Color(0xFFF0FDF4))
                  : s['is_overdue'] == true
                  ? _pill('Overdue', AppColors.error, const Color(0xFFFEF2F2))
                  : _pill(
                      'Due',
                      const Color(0xFFC2410C),
                      const Color(0xFFFFF7ED),
                    ),
              const SizedBox(width: 8),
              Text(
                formatMoney(_num(s['due_amount'])),
                style: const TextStyle(
                  fontWeight: FontWeight.w700,
                  color: AppColors.primary,
                ),
              ),
            ],
          ),
        ),
      if (_payments.isNotEmpty) ...[
        const SizedBox(height: 16),
        const Text(
          'Payments received',
          style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14),
        ),
        const SizedBox(height: 8),
        for (final p in _payments)
          _row(
            title: p['reference']?.toString() ?? 'Payment',
            subtitle: [
              formatDate(p['paid_at']?.toString()),
              _methodLabel(p['payment_method']?.toString()),
              if (p['received_by'] != null) 'by ${p['received_by']}',
            ].join(' · '),
            trailing: Text(
              formatMoney(_num(p['amount'])),
              style: const TextStyle(
                fontWeight: FontWeight.w700,
                color: AppColors.success,
              ),
            ),
          ),
      ],
    ];
  }

  List<Widget> _buildCheques() {
    if (_chequesError != null && _cheques.isEmpty) {
      return [_empty(_chequesError!)];
    }
    final s = _chequeSummary;
    int count(String key) => (s[key] as num?)?.toInt() ?? 0;
    return [
      Row(
        children: [
          _stat(
            'Pending (${count('pending_count')})',
            formatMoney(_num(s['pending_total'])),
            const Color(0xFFC2410C),
            const Color(0xFFFFF7ED),
          ),
          const SizedBox(width: 8),
          _stat(
            'Claimed (${count('cleared_count')})',
            formatMoney(_num(s['cleared_total'])),
            AppColors.success,
            const Color(0xFFF0FDF4),
          ),
          const SizedBox(width: 8),
          _stat(
            'Bounced (${count('bounced_count')})',
            formatMoney(_num(s['bounced_total'])),
            AppColors.textDark,
            const Color(0xFFF3F4F6),
          ),
        ],
      ),
      const SizedBox(height: 14),
      if (_cheques.isEmpty) _empty('No cheques from this customer.'),
      for (final c in _cheques) _chequeRow(c),
    ];
  }

  Widget _chequePill(Map<String, dynamic> c) => switch (c['status']) {
    'cleared' => _pill('Claimed', AppColors.success, const Color(0xFFF0FDF4)),
    'bounced' => _pill('Bounced', AppColors.error, const Color(0xFFFEF2F2)),
    'cancelled' => _pill(
      'Cancelled',
      AppColors.textMuted,
      const Color(0xFFF3F4F6),
    ),
    _ when c['is_due'] == true => _pill(
      'Due to claim',
      AppColors.primaryDk,
      AppColors.primaryLt,
    ),
    _ => _pill('Pending', const Color(0xFFC2410C), const Color(0xFFFFF7ED)),
  };

  Widget _chequeRow(Map<String, dynamic> c) {
    final bank = c['bank_name']?.toString().trim() ?? '';
    final claimable = c['is_claimable'] == true;
    final pending = c['status'] == 'pending';
    final detail = c['status'] == 'cleared'
        ? [
            if (c['deposit_account'] is Map)
              'Into ${(c['deposit_account'] as Map)['label']}',
            if (c['claimed_at'] != null)
              'on ${formatDate(c['claimed_at']?.toString())}',
          ].join(' ')
        : c['status'] == 'bounced'
        ? (c['bounce_reason']?.toString() ?? '')
        : '';
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: _card(
        padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    '#${c['cheque_number'] ?? ''}',
                    style: const TextStyle(
                      fontWeight: FontWeight.w800,
                      color: AppColors.textDark,
                    ),
                  ),
                ),
                _chequePill(c),
                const SizedBox(width: 8),
                Text(
                  formatMoney(_num(c['amount'])),
                  style: const TextStyle(
                    fontWeight: FontWeight.w700,
                    color: AppColors.primary,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 3),
            Text(
              [
                c['sale_number']?.toString() ?? '',
                'Dated ${formatDate(c['cheque_date']?.toString())}',
                if (bank.isNotEmpty) bank,
              ].where((t) => t.isNotEmpty).join(' · '),
              style: const TextStyle(fontSize: 12, color: AppColors.textMuted),
            ),
            if (detail.isNotEmpty) ...[
              const SizedBox(height: 2),
              Text(
                detail,
                style: TextStyle(
                  fontSize: 12,
                  color: c['status'] == 'bounced'
                      ? AppColors.error
                      : AppColors.textMuted,
                ),
              ),
            ],
            if (claimable) ...[
              const SizedBox(height: 10),
              Row(
                children: [
                  Expanded(
                    child: ElevatedButton.icon(
                      onPressed: () => _claimCheque(c),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.success,
                        foregroundColor: Colors.white,
                        visualDensity: VisualDensity.compact,
                      ),
                      icon: const Icon(Icons.account_balance_rounded, size: 16),
                      label: const Text('Claim'),
                    ),
                  ),
                  if (pending) ...[
                    const SizedBox(width: 8),
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: () => _bounceCheque(c),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: AppColors.error,
                          side: const BorderSide(color: AppColors.error),
                          visualDensity: VisualDensity.compact,
                        ),
                        icon: const Icon(Icons.block_rounded, size: 16),
                        label: const Text('Bounced'),
                      ),
                    ),
                  ],
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }

  /// Customer, bill, bank, date and amount — the top of both cheque dialogs.
  Widget _chequeSummaryBox(Map<String, dynamic> c) {
    Widget line(String label, String value, {bool strong = false}) => Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        children: [
          Text(
            label,
            style: TextStyle(
              fontSize: 13,
              color: strong ? AppColors.textDark : AppColors.textMuted,
              fontWeight: strong ? FontWeight.w700 : FontWeight.w400,
            ),
          ),
          const Spacer(),
          Text(
            value,
            style: TextStyle(
              fontSize: strong ? 15 : 13,
              fontWeight: FontWeight.w700,
              color: strong ? AppColors.primary : AppColors.textDark,
            ),
          ),
        ],
      ),
    );
    final bank = c['bank_name']?.toString().trim() ?? '';
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        children: [
          line('Customer', _customer?['name']?.toString() ?? '—'),
          line('Bill', c['sale_number']?.toString() ?? '—'),
          line('Bank', bank.isEmpty ? '—' : bank),
          line('Cheque date', formatDate(c['cheque_date']?.toString())),
          const Divider(height: 14),
          line(
            'Amount',
            'LKR ${formatMoney(_num(c['amount']))}',
            strong: true,
          ),
        ],
      ),
    );
  }

  Future<void> _claimCheque(Map<String, dynamic> cheque) async {
    List<Map<String, dynamic>>? accounts;
    String? loadError;
    int? accountId;
    final noteCtrl = TextEditingController();
    var busy = false;
    var loadStarted = false;
    String? error;

    final claimed = await showDialog<bool>(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setDialog) {
          if (!loadStarted) {
            loadStarted = true;
            ApiClient.instance
                .get(ApiEndpoints.accounts, bypassCache: true)
                .then((res) {
                  final body = res.data;
                  accounts = _rows(body is Map ? body['data'] : body);
                  accountId = accounts!.isEmpty
                      ? null
                      : (accounts!.first['id'] as num).toInt();
                })
                .catchError((Object e) {
                  loadError = apiErrorMessage(e);
                })
                .whenComplete(() {
                  if (ctx.mounted) setDialog(() {});
                });
          }

          Future<void> submit() async {
            if (accountId == null) {
              setDialog(() => error = 'Choose the account to deposit into.');
              return;
            }
            setDialog(() {
              busy = true;
              error = null;
            });
            try {
              final res = await ApiClient.instance.post(
                ApiEndpoints.saleChequeClaim((cheque['id'] as num).toInt()),
                data: {
                  'deposit_account_id': accountId,
                  if (noteCtrl.text.trim().isNotEmpty)
                    'notes': noteCtrl.text.trim(),
                },
              );
              final body = res.data;
              _snack(
                body is Map && body['message'] is String
                    ? body['message'] as String
                    : 'Cheque claimed.',
              );
              if (ctx.mounted) Navigator.pop(ctx, true);
            } catch (e) {
              setDialog(() {
                busy = false;
                error = apiErrorMessage(e);
              });
            }
          }

          return AlertDialog(
            title: Text('Claim cheque #${cheque['cheque_number']}'),
            content: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _chequeSummaryBox(cheque),
                  const SizedBox(height: 14),
                  if (loadError != null)
                    Text(
                      loadError!,
                      style: const TextStyle(color: AppColors.error),
                    )
                  else if (accounts == null)
                    const Center(child: CircularProgressIndicator())
                  else if (accounts!.isEmpty)
                    const Text(
                      'Add an account first (Home → Accounts) to deposit into.',
                      style: TextStyle(color: AppColors.error),
                    )
                  else
                    DropdownButtonFormField<int>(
                      initialValue: accountId,
                      isExpanded: true,
                      decoration: const InputDecoration(
                        labelText: 'Deposit into account *',
                      ),
                      items: [
                        for (final a in accounts!)
                          DropdownMenuItem(
                            value: (a['id'] as num).toInt(),
                            child: Text(
                              [
                                a['account_name'],
                                if ((a['bank_name']?.toString() ?? '')
                                    .isNotEmpty)
                                  a['bank_name'],
                              ].join(' · '),
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                      ],
                      onChanged: busy
                          ? null
                          : (v) => setDialog(() => accountId = v),
                    ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: noteCtrl,
                    enabled: !busy,
                    maxLines: 2,
                    decoration: const InputDecoration(
                      labelText: 'Note (optional)',
                      hintText: 'e.g. Deposited at BOC Kandy branch',
                    ),
                  ),
                  if (error != null) ...[
                    const SizedBox(height: 10),
                    Text(
                      error!,
                      style: const TextStyle(
                        color: AppColors.error,
                        fontSize: 12.5,
                      ),
                    ),
                  ],
                ],
              ),
            ),
            actions: [
              TextButton(
                onPressed: busy ? null : () => Navigator.pop(ctx, false),
                child: const Text('Cancel'),
              ),
              ElevatedButton.icon(
                onPressed: busy || accounts == null || accounts!.isEmpty
                    ? null
                    : submit,
                icon: const Icon(Icons.account_balance_rounded, size: 16),
                label: const Text('Claim cheque'),
              ),
            ],
          );
        },
      ),
    );
    noteCtrl.dispose();
    if (claimed == true) {
      _changed = true;
      await _load();
    }
  }

  Future<void> _bounceCheque(Map<String, dynamic> cheque) async {
    final reasonCtrl = TextEditingController();
    var busy = false;
    String? error;

    final bounced = await showDialog<bool>(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setDialog) {
          Future<void> submit() async {
            if (reasonCtrl.text.trim().isEmpty) {
              setDialog(() => error = 'Say why the cheque bounced.');
              return;
            }
            setDialog(() {
              busy = true;
              error = null;
            });
            try {
              final res = await ApiClient.instance.post(
                ApiEndpoints.saleChequeBounce((cheque['id'] as num).toInt()),
                data: {'reason': reasonCtrl.text.trim()},
              );
              final body = res.data;
              _snack(
                body is Map && body['message'] is String
                    ? body['message'] as String
                    : 'Cheque marked as bounced.',
              );
              if (ctx.mounted) Navigator.pop(ctx, true);
            } catch (e) {
              setDialog(() {
                busy = false;
                error = apiErrorMessage(e);
              });
            }
          }

          return AlertDialog(
            title: Text('Bounced cheque #${cheque['cheque_number']}'),
            content: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _chequeSummaryBox(cheque),
                  const SizedBox(height: 12),
                  Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: const Color(0xFFFFF7ED),
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(color: const Color(0xFFFED7AA)),
                    ),
                    child: const Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Icon(
                          Icons.warning_amber_rounded,
                          size: 18,
                          color: Color(0xFFC2410C),
                        ),
                        SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            'Mark this cheque as returned unpaid by the bank. '
                            'You can still claim it later if the customer\'s '
                            'bank pays it on re-presentation.',
                            style: TextStyle(fontSize: 12.5),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: reasonCtrl,
                    enabled: !busy,
                    autofocus: true,
                    maxLength: 255,
                    decoration: const InputDecoration(
                      labelText: 'Why did it bounce? *',
                      hintText: 'e.g. Insufficient funds',
                      counterText: '',
                    ),
                  ),
                  if (error != null) ...[
                    const SizedBox(height: 10),
                    Text(
                      error!,
                      style: const TextStyle(
                        color: AppColors.error,
                        fontSize: 12.5,
                      ),
                    ),
                  ],
                ],
              ),
            ),
            actions: [
              TextButton(
                onPressed: busy ? null : () => Navigator.pop(ctx, false),
                child: const Text('Cancel'),
              ),
              ElevatedButton.icon(
                onPressed: busy ? null : submit,
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.error,
                  foregroundColor: Colors.white,
                ),
                icon: const Icon(Icons.block_rounded, size: 16),
                label: const Text('Mark bounced'),
              ),
            ],
          );
        },
      ),
    );
    reasonCtrl.dispose();
    if (bounced == true) {
      _changed = true;
      await _load();
    }
  }

  Widget _buildPaymentCard() => _card(
    padding: const EdgeInsets.all(14),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Row(
          children: [
            Icon(Icons.payments_outlined, size: 18, color: AppColors.primary),
            SizedBox(width: 8),
            Text(
              'Receive a payment',
              style: TextStyle(fontWeight: FontWeight.w700),
            ),
          ],
        ),
        const SizedBox(height: 12),
        TextField(
          controller: _amountController,
          enabled: !_paying,
          keyboardType: const TextInputType.numberWithOptions(decimal: true),
          inputFormatters: [
            FilteringTextInputFormatter.allow(RegExp(r'[0-9.]')),
          ],
          decoration: InputDecoration(
            prefixText: 'LKR ',
            hintText: '0.00',
            suffixIcon: TextButton(
              onPressed: _paying
                  ? null
                  : () => _amountController.text = _outstanding
                        .toStringAsFixed(2),
              child: const Text('Pay full'),
            ),
          ),
        ),
        const SizedBox(height: 10),
        SegmentedButton<String>(
          segments: const [
            ButtonSegment(
              value: 'cash',
              label: Text('Cash'),
              icon: Icon(Icons.payments_rounded),
            ),
            ButtonSegment(
              value: 'card',
              label: Text('Card'),
              icon: Icon(Icons.credit_card_rounded),
            ),
          ],
          selected: {_paymentMethod},
          onSelectionChanged: _paying
              ? null
              : (s) => setState(() => _paymentMethod = s.first),
        ),
        const SizedBox(height: 12),
        SizedBox(
          width: double.infinity,
          child: ElevatedButton.icon(
            onPressed: _paying ? null : _receivePayment,
            icon: _paying
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: Colors.white,
                    ),
                  )
                : const Icon(Icons.check_rounded),
            label: const Text('Receive payment'),
          ),
        ),
      ],
    ),
  );

  Widget _buildActions() => SafeArea(
    child: Padding(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 12),
      child: Row(
        children: [
          Expanded(
            child: OutlinedButton.icon(
              onPressed: _edit,
              icon: const Icon(Icons.edit_outlined, size: 18),
              label: const Text('Edit'),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: OutlinedButton.icon(
              onPressed: _delete,
              style: OutlinedButton.styleFrom(
                foregroundColor: AppColors.error,
                side: const BorderSide(color: AppColors.error),
              ),
              icon: const Icon(Icons.delete_outline, size: 18),
              label: const Text('Delete'),
            ),
          ),
        ],
      ),
    ),
  );

  static String _methodLabel(String? method) => switch (method) {
    null || '' => '—',
    'credit' => 'Credit',
    final m => m[0].toUpperCase() + m.substring(1),
  };

  Widget _card({required Widget child, required EdgeInsets padding}) =>
      Container(
        padding: padding,
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14),
          boxShadow: const [
            BoxShadow(
              color: AppColors.shadow,
              blurRadius: 12,
              offset: Offset(0, 3),
            ),
          ],
        ),
        child: child,
      );

  Widget _kv(String label, String value, {bool last = false}) => Container(
    padding: const EdgeInsets.symmetric(vertical: 11),
    decoration: BoxDecoration(
      border: last
          ? null
          : const Border(bottom: BorderSide(color: Color(0xFFF3F4F6))),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 100,
          child: Text(
            label,
            style: const TextStyle(fontSize: 12.5, color: AppColors.textMuted),
          ),
        ),
        Expanded(
          child: Text(
            value,
            style: const TextStyle(fontSize: 13.5, color: AppColors.textDark),
          ),
        ),
      ],
    ),
  );

  Widget _stat(String label, String value, Color color, Color background) =>
      Expanded(
        child: Container(
          padding: const EdgeInsets.all(10),
          decoration: BoxDecoration(
            color: background,
            borderRadius: BorderRadius.circular(12),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                label,
                style: const TextStyle(
                  fontSize: 11,
                  color: AppColors.textMuted,
                ),
              ),
              const SizedBox(height: 4),
              FittedBox(
                fit: BoxFit.scaleDown,
                alignment: Alignment.centerLeft,
                child: Text(
                  value,
                  style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w800,
                    color: color,
                  ),
                ),
              ),
            ],
          ),
        ),
      );

  Widget _row({
    required String title,
    required String subtitle,
    required Widget trailing,
  }) => Padding(
    padding: const EdgeInsets.only(bottom: 8),
    child: _card(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    fontWeight: FontWeight.w700,
                    color: AppColors.textDark,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  subtitle,
                  style: const TextStyle(
                    fontSize: 12,
                    color: AppColors.textMuted,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          trailing,
        ],
      ),
    ),
  );

  Widget _pill(String label, Color color, Color background) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
    decoration: BoxDecoration(
      color: background,
      borderRadius: BorderRadius.circular(999),
    ),
    child: Text(
      label,
      style: TextStyle(
        color: color,
        fontSize: 11,
        fontWeight: FontWeight.w600,
      ),
    ),
  );

  Widget _empty(String message) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 24),
    child: Center(
      child: Text(message, style: const TextStyle(color: AppColors.textMuted)),
    ),
  );
}
