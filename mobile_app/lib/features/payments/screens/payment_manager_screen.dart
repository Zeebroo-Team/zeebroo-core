import 'package:flutter/material.dart';
import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/money.dart';
import '../../finance/widgets/finance_common.dart';
import '../../finance/widgets/loans_tab.dart';
import '../../finance/widgets/rentals_tab.dart';
import '../../finance/widgets/modifications_tab.dart';
import '../../inventory/widgets/list_states.dart';
import '../../inventory/widgets/picker_sheet.dart';
import '../data/payment_manager_repository.dart';

enum PaymentManagerKind {
  loan('Loan Manager', Icons.account_balance_rounded, Color(0xFF0EA5E9)),
  rental('Rental Manager', Icons.apartment_rounded, Color(0xFF10B981)),
  modification('Modification Manager', Icons.tune_rounded, Color(0xFFF59E0B));

  const PaymentManagerKind(this.title, this.icon, this.color);
  final String title;
  final IconData icon;
  final Color color;
  String get path => switch (this) {
    loan => ApiEndpoints.financeLoans,
    rental => ApiEndpoints.financeRentals,
    modification => ApiEndpoints.financeModifications,
  };
}

class PaymentManagerScreen extends StatefulWidget {
  const PaymentManagerScreen({
    super.key,
    required this.kind,
    this.repository = const PaymentManagerRepository(),
  });
  final PaymentManagerKind kind;
  final PaymentManagerRepository repository;
  @override
  State<PaymentManagerScreen> createState() => _PaymentManagerScreenState();
}

class _PaymentManagerScreenState extends State<PaymentManagerScreen> {
  List<Map<String, dynamic>> _items = [];
  bool _loading = true;
  bool _busy = false;
  bool _fetching = false;
  String? _error;
  String _query = '';
  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    if (!mounted) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await widget.repository.get(widget.kind.path);
      if (mounted) setState(() => _items = parseListData(data));
    } catch (e) {
      if (mounted) setState(() => _error = apiErrorMessage(e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _form([Map<String, dynamic>? item]) async {
    if (_busy) return;
    setState(() {
      _busy = true;
      _fetching = item != null;
    });
    try {
      Map<String, dynamic>? record;
      if (item != null) {
        final data = await widget.repository.get(
          '${widget.kind.path}/${item['id']}',
        );
        record = Map<String, dynamic>.from(data['data'] as Map);
      }
      if (!mounted) return;
      setState(() => _fetching = false);
      final changed = await showModalBottomSheet<bool>(
        context: context,
        isScrollControlled: true,
        backgroundColor: Colors.transparent,
        builder: (_) => switch (widget.kind) {
          PaymentManagerKind.loan => AddLoanSheet(
            record: record,
            repository: widget.repository,
          ),
          PaymentManagerKind.rental => AddRentalSheet(
            record: record,
            repository: widget.repository,
          ),
          PaymentManagerKind.modification => AddModificationSheet(
            record: record,
            repository: widget.repository,
          ),
        },
      );
      if (changed == true) await _load();
    } catch (e) {
      _showError(e);
    } finally {
      if (mounted) {
        setState(() {
          _busy = false;
          _fetching = false;
        });
      }
    }
  }

  Future<void> _delete(Map<String, dynamic> item) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      final confirmed = await confirmDelete(
        context,
        title: 'Delete ${widget.kind.name}',
        message:
            'Delete "${item['name'] ?? item['property_type']}"? This cannot be undone.',
      );
      if (!confirmed || !mounted) return;
      await widget.repository.delete(
        widget.kind.path,
        (item['id'] as num).toInt(),
      );
      await _load();
    } catch (e) {
      _showError(e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _showError(Object e) {
    if (mounted) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(apiErrorMessage(e))));
    }
  }

  @override
  Widget build(BuildContext context) {
    final visible = _items
        .where(
          (item) =>
              '${item['name'] ?? item['property_type']} ${item['description'] ?? ''} ${item['purpose'] ?? ''}'
                  .toLowerCase()
                  .contains(_query),
        )
        .toList();
    return Scaffold(
      backgroundColor: AppColors.surface,
      appBar: AppBar(
        title: Text(
          widget.kind.title,
          style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w700),
        ),
        centerTitle: true,
      ),
      body: SafeArea(
        top: false,
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 14, 20, 12),
              child: Row(
                children: [
                  Expanded(
                    child: TextField(
                      decoration: InputDecoration(
                        hintText: 'Search ${widget.kind.name}s',
                        prefixIcon: const Icon(Icons.search),
                      ),
                      onChanged: (value) =>
                          setState(() => _query = value.trim().toLowerCase()),
                    ),
                  ),
                  const SizedBox(width: 10),
                  AddButton(onTap: () => _form()),
                ],
              ),
            ),
            if (_fetching) const LinearProgressIndicator(),
            Expanded(
              child: _loading
                  ? const Center(child: CircularProgressIndicator())
                  : _error != null
                  ? ErrorState(error: _error!, onRetry: _load)
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: ListView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: const EdgeInsets.fromLTRB(20, 0, 20, 24),
                        children: [
                          if (visible.isEmpty)
                            Padding(
                              padding: const EdgeInsets.all(32),
                              child: Text(
                                _items.isEmpty
                                    ? 'No ${widget.kind.name}s yet. Tap + to add one.'
                                    : 'No matching results.',
                                textAlign: TextAlign.center,
                              ),
                            ),
                          for (final item in visible)
                            Padding(
                              padding: const EdgeInsets.only(bottom: 10),
                              child: FinanceCard(
                                onTap: _busy ? null : () => _form(item),
                                child: Row(
                                  children: [
                                    Icon(
                                      widget.kind.icon,
                                      color: widget.kind.color,
                                    ),
                                    const SizedBox(width: 12),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            '${item['name'] ?? item['property_type'] ?? ''}',
                                            style: const TextStyle(
                                              fontWeight: FontWeight.w700,
                                            ),
                                          ),
                                          const SizedBox(height: 5),
                                          Text(
                                            formatMoney(
                                              item['borrowed_amount'] ??
                                                  item['recurring_cost'] ??
                                                  item['estimated_cost'],
                                            ),
                                            style: const TextStyle(
                                              color: AppColors.textMuted,
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                    IconButton(
                                      tooltip: 'Edit',
                                      onPressed: _busy
                                          ? null
                                          : () => _form(item),
                                      icon: const Icon(Icons.edit_outlined),
                                    ),
                                    IconButton(
                                      tooltip: 'Delete',
                                      onPressed: _busy
                                          ? null
                                          : () => _delete(item),
                                      icon: const Icon(
                                        Icons.delete_outline,
                                        color: AppColors.error,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                        ],
                      ),
                    ),
            ),
          ],
        ),
      ),
    );
  }
}
