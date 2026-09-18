import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/business/business_state.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/app_button.dart';

/// Shown right after sign-in (mirrors the desktop app's "Sign in to
/// continue" business/branch picker), and reachable again from the side
/// menu to switch business/branch mid-session. Same account, same backend —
/// this just tells the server (via `X-Business-Id`/`X-Branch-Id`) which one
/// to resolve data for.
class SelectBusinessScreen extends StatefulWidget {
  const SelectBusinessScreen({super.key});

  @override
  State<SelectBusinessScreen> createState() => _SelectBusinessScreenState();
}

class _Business {
  const _Business({required this.id, required this.name});
  final int id;
  final String name;
}

class _Branch {
  const _Branch({required this.id, required this.name});
  final int id;
  final String name;
}

class _SelectBusinessScreenState extends State<SelectBusinessScreen> {
  bool _loading = true;
  bool _loadingBranches = false;
  bool _submitting = false;
  String? _error;

  List<_Business> _businesses = [];
  List<_Branch> _branches = [];
  bool _branchSeparate = false;

  int? _selectedBusinessId;
  int? _selectedBranchId;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final res = await ApiClient.instance.get(ApiEndpoints.businesses);
      final data = res.data;
      final list = (data is Map ? data['data'] : data) as List? ?? [];
      _businesses = list
          .whereType<Map>()
          .map((m) => _Business(id: m['id'] as int, name: m['name'] as String))
          .toList();

      if (!mounted) return;
      final current = context.read<BusinessState>();
      final preselect = current.businessId;
      _selectedBusinessId =
          (preselect != null && _businesses.any((b) => b.id == preselect))
          ? preselect
          : (_businesses.isNotEmpty ? _businesses.first.id : null);

      if (_selectedBusinessId != null) {
        await _loadBranches(
          _selectedBusinessId!,
          preselectBranch: current.branchId,
        );
      }
    } catch (e) {
      _error = apiErrorMessage(e);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _loadBranches(int businessId, {int? preselectBranch}) async {
    setState(() => _loadingBranches = true);
    try {
      // Previewing branches for a candidate business before it's confirmed —
      // must win over whatever business is currently selected, so this is
      // pinned explicitly rather than left to the stored default.
      final res = await ApiClient.instance.get(
        ApiEndpoints.branches,
        params: {'business_id': businessId},
        headers: {'X-Business-Id': businessId.toString()},
      );
      final data = res.data;
      final settings =
          (data is Map ? data['data'] : data) as Map<String, dynamic>?;
      final list = (settings?['branches'] as List?) ?? [];
      _branchSeparate = (settings?['branch_pos_separate'] as bool?) ?? false;
      _branches = list
          .whereType<Map>()
          .map((m) => _Branch(id: m['id'] as int, name: m['name'] as String))
          .toList();
      _selectedBranchId =
          (preselectBranch != null &&
              _branches.any((b) => b.id == preselectBranch))
          ? preselectBranch
          : null;
    } catch (_) {
      // Branch selection is a nice-to-have — fall back to "no branch" rather
      // than blocking the business switch on it.
      _branchSeparate = false;
      _branches = [];
      _selectedBranchId = null;
    } finally {
      if (mounted) setState(() => _loadingBranches = false);
    }
  }

  Future<void> _continue() async {
    final businessId = _selectedBusinessId;
    if (businessId == null) return;
    setState(() => _submitting = true);

    final businessName = _businesses.firstWhere((b) => b.id == businessId).name;
    final branchId = _selectedBranchId;
    final branchName = branchId == null
        ? null
        : _branches.firstWhere((b) => b.id == branchId).name;

    await context.read<BusinessState>().select(
      businessId: businessId,
      businessName: businessName,
      branchId: branchId,
      branchName: branchName,
    );

    if (!mounted) return;
    setState(() => _submitting = false);
    if (context.canPop()) {
      context.pop();
    } else {
      context.go('/home');
    }
  }

  @override
  Widget build(BuildContext context) {
    final isSwitching = context.watch<BusinessState>().hasSelection;

    return Scaffold(
      appBar: isSwitching
          ? AppBar(
              backgroundColor: AppColors.surface,
              foregroundColor: AppColors.textDark,
              elevation: 0,
              title: const Text(
                'Switch Business',
                style: TextStyle(fontWeight: FontWeight.w700, fontSize: 17),
              ),
            )
          : null,
      body: SafeArea(
        child: _loading
            ? const Center(child: CircularProgressIndicator())
            : SingleChildScrollView(
                padding: const EdgeInsets.all(24),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (!isSwitching) ...[
                      const SizedBox(height: 24),
                      Center(
                        child: Image.asset(
                          'assets/images/logo.png',
                          height: 64,
                        ),
                      ),
                      const SizedBox(height: 20),
                      const Center(
                        child: Text(
                          'Sign in to continue',
                          style: TextStyle(
                            fontSize: 15,
                            color: AppColors.primaryDk,
                          ),
                        ),
                      ),
                      const SizedBox(height: 32),
                    ],
                    if (_error != null) ...[
                      _ErrorBanner(message: _error!),
                      const SizedBox(height: 16),
                    ],
                    const _FieldLabel('Business'),
                    DropdownButtonFormField<int>(
                      initialValue: _selectedBusinessId,
                      decoration: const InputDecoration(
                        prefixIcon: Icon(Icons.storefront_outlined, size: 18),
                      ),
                      items: [
                        for (final b in _businesses)
                          DropdownMenuItem(value: b.id, child: Text(b.name)),
                      ],
                      onChanged: (id) {
                        if (id == null) return;
                        setState(() => _selectedBusinessId = id);
                        _loadBranches(id);
                      },
                    ),
                    if (_branchSeparate && _branches.isNotEmpty) ...[
                      const SizedBox(height: 16),
                      const _FieldLabel('Branch (optional)'),
                      DropdownButtonFormField<int?>(
                        initialValue: _selectedBranchId,
                        decoration: const InputDecoration(
                          prefixIcon: Icon(Icons.alt_route_outlined, size: 18),
                        ),
                        items: [
                          const DropdownMenuItem(
                            value: null,
                            child: Text('All branches'),
                          ),
                          for (final b in _branches)
                            DropdownMenuItem(value: b.id, child: Text(b.name)),
                        ],
                        onChanged: (id) =>
                            setState(() => _selectedBranchId = id),
                      ),
                    ],
                    const SizedBox(height: 28),
                    AppButton(
                      label: 'Continue',
                      onPressed: _selectedBusinessId == null ? null : _continue,
                      loading: _submitting || _loadingBranches,
                    ),
                  ],
                ),
              ),
      ),
    );
  }
}

class _FieldLabel extends StatelessWidget {
  const _FieldLabel(this.text);
  final String text;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 6),
    child: Text(
      text,
      style: const TextStyle(
        fontSize: 13,
        fontWeight: FontWeight.w600,
        color: AppColors.textMid,
      ),
    ),
  );
}

class _ErrorBanner extends StatelessWidget {
  const _ErrorBanner({required this.message});
  final String message;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: AppColors.error.withValues(alpha: 0.08),
      borderRadius: BorderRadius.circular(10),
      border: Border.all(color: AppColors.error.withValues(alpha: 0.3)),
    ),
    child: Row(
      children: [
        const Icon(Icons.error_outline, color: AppColors.error, size: 18),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            message,
            style: const TextStyle(color: AppColors.error, fontSize: 13),
          ),
        ),
      ],
    ),
  );
}
