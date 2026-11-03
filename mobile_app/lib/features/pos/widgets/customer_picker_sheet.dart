import 'dart:async';

import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/offline/offline_common.dart';
import '../../../core/offline/offline_customers.dart';
import '../../../core/theme/app_theme.dart';
import '../../inventory/widgets/customer_form_sheet.dart';

class CustomerPickerSheet extends StatefulWidget {
  const CustomerPickerSheet({super.key});

  static Future<Map<String, dynamic>?> show(BuildContext context) {
    return showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Colors.transparent,
      builder: (_) => const CustomerPickerSheet(),
    );
  }

  @override
  State<CustomerPickerSheet> createState() => _CustomerPickerSheetState();
}

class _CustomerPickerSheetState extends State<CustomerPickerSheet> {
  final _searchController = TextEditingController();
  Timer? _searchDebounce;
  List<Map<String, dynamic>> _customers = [];
  bool _loading = true;
  String? _error;

  /// No connection: the list comes from the customers saved on the phone.
  bool _offline = false;

  @override
  void initState() {
    super.initState();
    _loadCustomers();
  }

  @override
  void dispose() {
    _searchDebounce?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _loadCustomers([String query = '']) async {
    if (mounted) setState(() => _loading = true);
    final offline = OfflineCustomers.instance;
    try {
      final response = await ApiClient.instance.get(
        ApiEndpoints.customers,
        params: {if (query.trim().isNotEmpty) 'q': query.trim()},
        bypassCache: true,
      );
      final body = response.data;
      final rows = (body is Map ? body['data'] : body) as List? ?? [];
      final customers = rows
          .whereType<Map>()
          .map((row) => Map<String, dynamic>.from(row))
          .toList();
      unawaited(offline.remember(customers));
      // Customers added offline that haven't uploaded yet are listed too.
      final notUploaded = await offline.search(query, notUploadedOnly: true);
      if (!mounted) return;
      setState(() {
        _customers = [...notUploaded, ...customers];
        _error = null;
        _offline = false;
      });
    } catch (error) {
      if (couldNotReachServer(error) && offline.canRecord) {
        final saved = await offline.search(query);
        if (!mounted) return;
        setState(() {
          _customers = saved;
          _error = null;
          _offline = true;
        });
      } else if (mounted) {
        setState(() => _error = apiErrorMessage(error));
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _search(String value) {
    _searchDebounce?.cancel();
    _searchDebounce = Timer(
      const Duration(milliseconds: 350),
      () => _loadCustomers(value),
    );
  }

  Future<void> _addCustomer() async {
    final customer = await showCustomerFormSheet(context);
    if (!mounted || customer == null) return;
    Navigator.of(context).pop(customer);
  }

  @override
  Widget build(BuildContext context) {
    final keyboard = MediaQuery.viewInsetsOf(context).bottom;
    return Padding(
      padding: EdgeInsets.only(bottom: keyboard),
      child: Container(
        constraints: BoxConstraints(
          maxHeight: MediaQuery.sizeOf(context).height * 0.82,
        ),
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
        child: Column(
          children: [
            const SizedBox(height: 10),
            Container(
              width: 42,
              height: 4,
              decoration: BoxDecoration(
                color: AppColors.border,
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 14, 8, 10),
              child: Row(
                children: [
                  const Expanded(
                    child: Text(
                      'Select customer',
                      style: TextStyle(
                        fontSize: 19,
                        fontWeight: FontWeight.w700,
                        color: AppColors.textDark,
                      ),
                    ),
                  ),
                  IconButton(
                    onPressed: () => Navigator.of(context).pop(),
                    icon: const Icon(Icons.close_rounded),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: TextField(
                controller: _searchController,
                autofocus: true,
                textInputAction: TextInputAction.search,
                decoration: const InputDecoration(
                  hintText: 'Search by name or phone number',
                  prefixIcon: Icon(Icons.search_rounded),
                ),
                onChanged: _search,
                onSubmitted: _loadCustomers,
              ),
            ),
            const SizedBox(height: 10),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: SizedBox(
                width: double.infinity,
                child: OutlinedButton.icon(
                  onPressed: _addCustomer,
                  icon: const Icon(Icons.person_add_alt_1_rounded),
                  label: const Text('Add new customer'),
                ),
              ),
            ),
            if (_offline)
              Padding(
                key: const ValueKey('customer-picker-offline'),
                padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
                child: Row(
                  children: const [
                    Icon(Icons.cloud_off_rounded, size: 16, color: AppColors.warning),
                    SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        'Offline — showing customers saved on this phone.',
                        style: TextStyle(fontSize: 12.5, color: AppColors.textMuted),
                      ),
                    ),
                  ],
                ),
              ),
            const Divider(height: 22),
            Expanded(child: _buildCustomerList()),
          ],
        ),
      ),
    );
  }

  Widget _buildCustomerList() {
    if (_loading && _customers.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null && _customers.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(_error!, textAlign: TextAlign.center),
              const SizedBox(height: 10),
              TextButton(
                onPressed: () => _loadCustomers(_searchController.text),
                child: const Text('Retry'),
              ),
            ],
          ),
        ),
      );
    }
    if (_customers.isEmpty) {
      return const Center(child: Text('No customers found'));
    }
    return ListView.separated(
      keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
      padding: const EdgeInsets.fromLTRB(8, 0, 8, 20),
      itemCount: _customers.length,
      separatorBuilder: (_, _) => const Divider(height: 1),
      itemBuilder: (_, index) {
        final customer = _customers[index];
        final name = customer['name']?.toString() ?? 'Customer';
        final phone = customer['phone']?.toString().trim() ?? '';
        final type = customer['customer_type']?.toString() ?? 'retail';
        return ListTile(
          leading: CircleAvatar(
            backgroundColor: AppColors.primary.withValues(alpha: 0.1),
            child: Text(
              name.isEmpty ? '?' : name[0].toUpperCase(),
              style: const TextStyle(
                color: AppColors.primary,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
          title: Text(
            name,
            style: const TextStyle(fontWeight: FontWeight.w600),
          ),
          subtitle: Text(
            [
              if (phone.isNotEmpty) phone,
              type == 'wholesale' ? 'Wholesale' : 'Retail',
              if (customer['offline'] == true) 'Added offline',
            ].join('  •  '),
          ),
          trailing: const Icon(Icons.chevron_right_rounded),
          onTap: () => Navigator.of(context).pop(customer),
        );
      },
    );
  }
}
