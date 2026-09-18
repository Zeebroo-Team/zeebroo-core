import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/money.dart';
import 'add_account_sheet.dart';

/// First home tab — a balance "card" for the business's bank accounts
/// (switchable from its corner avatar, with a quick add-account action)
/// followed by a recent-transactions feed sourced from bill/rental payments.
class AccountOverviewTab extends StatefulWidget {
  const AccountOverviewTab({super.key});

  @override
  State<AccountOverviewTab> createState() => _AccountOverviewTabState();
}

class _AccountOverviewTabState extends State<AccountOverviewTab> {
  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _accounts = [];
  List<Map<String, dynamic>> _recentTxns = [];
  int _selected = 0;
  int? _defaultAccountId;
  bool _settingDefault = false;

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
      final results = await Future.wait([
        ApiClient.instance.get(ApiEndpoints.accounts),
        ApiClient.instance.get(ApiEndpoints.expensesOverview),
        ApiClient.instance.get(ApiEndpoints.businessSettings),
      ]);

      final accRaw = results[0].data;
      final accounts = (accRaw is Map ? accRaw['data'] : accRaw) as List? ?? [];
      final expRaw = results[1].data;
      final expData = (expRaw is Map ? expRaw['data'] : expRaw) as Map<String, dynamic>?;
      final recent = (expData?['recent_payments'] as List?) ?? [];
      final settingsRaw = results[2].data;
      final settingsData = (settingsRaw is Map ? settingsRaw['data'] : settingsRaw) as Map<String, dynamic>?;

      _accounts = accounts.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();
      _recentTxns = recent.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();
      _defaultAccountId = (settingsData?['default_deposit_account_id'] as num?)?.toInt();
      if (_selected >= _accounts.length) _selected = 0;
      if (_defaultAccountId != null) {
        final idx = _accounts.indexWhere((a) => (a['id'] as num?)?.toInt() == _defaultAccountId);
        if (idx != -1) _selected = idx;
      }
    } catch (e) {
      _error = apiErrorMessage(e);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _setDefaultAccount(int accountId) async {
    if (accountId == _defaultAccountId || _settingDefault) return;
    setState(() => _settingDefault = true);
    try {
      await ApiClient.instance.put(
        ApiEndpoints.businessSettings,
        data: {'default_deposit_account_id': accountId},
      );
      if (mounted) {
        setState(() => _defaultAccountId = accountId);
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Default deposit account updated')),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(apiErrorMessage(e))),
        );
      }
    } finally {
      if (mounted) setState(() => _settingDefault = false);
    }
  }

  Future<void> _openAddAccount() async {
    final created = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => const AddAccountSheet(),
    );
    if (created == true) _load();
  }

  void _pickAccount() {
    if (_accounts.isEmpty) return;
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(22))),
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setSheetState) => SafeArea(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Padding(
                padding: EdgeInsets.fromLTRB(20, 18, 20, 4),
                child: Align(
                  alignment: Alignment.centerLeft,
                  child: Text('Switch account', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: AppColors.textDark)),
                ),
              ),
              for (final entry in _accounts.asMap().entries)
                ListTile(
                  leading: CircleAvatar(
                    backgroundColor: AppColors.primaryLt,
                    child: Text(
                      _initialsFor(entry.value['account_name'] as String? ?? '?'),
                      style: const TextStyle(color: AppColors.primaryDk, fontWeight: FontWeight.w700, fontSize: 12),
                    ),
                  ),
                  title: Text(entry.value['account_name'] as String? ?? '', style: const TextStyle(fontWeight: FontWeight.w600)),
                  subtitle: Text((entry.value['bank_name'] as String?)?.isNotEmpty == true ? entry.value['bank_name'] as String : '—'),
                  trailing: _buildAccountTrailing(entry.key, entry.value, setSheetState),
                  onTap: () {
                    setState(() => _selected = entry.key);
                    Navigator.pop(ctx);
                  },
                ),
              ListTile(
                leading: const Icon(Icons.add_circle_outline_rounded, color: AppColors.primary),
                title: const Text('Add bank account', style: TextStyle(fontWeight: FontWeight.w600, color: AppColors.primary)),
                onTap: () {
                  Navigator.pop(ctx);
                  _openAddAccount();
                },
              ),
              const SizedBox(height: 8),
            ],
          ),
        ),
      ),
    );
  }

  /// Star toggles which account sales payments deposit to by default (same
  /// "Default deposit account" setting as the desktop app's payment
  /// settings), plus the usual check for which account is switched to.
  Widget _buildAccountTrailing(int index, Map<String, dynamic> account, StateSetter setSheetState) {
    final id = (account['id'] as num?)?.toInt();
    final isDefault = id != null && id == _defaultAccountId;

    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Tooltip(
          message: isDefault ? 'Default deposit account' : 'Set as default deposit account',
          child: InkWell(
            customBorder: const CircleBorder(),
            onTap: id == null
                ? null
                : () async {
                    await _setDefaultAccount(id);
                    setSheetState(() {});
                  },
            child: Padding(
              padding: const EdgeInsets.all(8),
              child: Icon(
                isDefault ? Icons.star_rounded : Icons.star_border_rounded,
                size: 20,
                color: isDefault ? AppColors.warning : AppColors.textMuted,
              ),
            ),
          ),
        ),
        if (index == _selected) const Icon(Icons.check_circle_rounded, color: AppColors.primary),
      ],
    );
  }

  static String _initialsFor(String name) {
    final parts = name.trim().split(RegExp(r'\s+')).where((p) => p.isNotEmpty).toList();
    if (parts.isEmpty) return '?';
    final first = parts.first[0];
    final last = parts.length > 1 ? parts.last[0] : '';
    return (first + last).toUpperCase();
  }

  @override
  Widget build(BuildContext context) => RefreshIndicator(
    onRefresh: _load,
    child: ListView(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 120),
      children: [
        if (_loading)
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 60),
            child: Center(child: CircularProgressIndicator()),
          )
        else if (_error != null)
          _ErrorCard(message: _error!, onRetry: _load)
        else ...[
          _buildBalanceCard(),
          const SizedBox(height: 26),
          _buildRecentTransactions(),
        ],
      ],
    ),
  );

  Widget _buildBalanceCard() {
    final hasAccounts = _accounts.isNotEmpty;
    final account = hasAccounts ? _accounts[_selected] : null;
    final balance = formatMoney(account?['current_balance']);
    final name = account?['account_name'] as String? ?? 'No account yet';
    final bank = account?['bank_name'] as String?;

    return Container(
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(24),
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [AppColors.primaryDk, AppColors.primary],
        ),
        boxShadow: [
          BoxShadow(color: AppColors.primaryDk.withValues(alpha: 0.28), blurRadius: 24, offset: const Offset(0, 10)),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 34,
                height: 34,
                decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.18), borderRadius: BorderRadius.circular(10)),
                alignment: Alignment.center,
                child: const Icon(Icons.account_balance_wallet_rounded, size: 18, color: Colors.white),
              ),
              const SizedBox(width: 10),
              const Expanded(
                child: Text('Account overview',
                    style: TextStyle(color: Colors.white, fontWeight: FontWeight.w600, fontSize: 13.5)),
              ),
              InkWell(
                onTap: hasAccounts ? _pickAccount : _openAddAccount,
                customBorder: const CircleBorder(),
                child: Container(
                  width: 34,
                  height: 34,
                  decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.18), shape: BoxShape.circle),
                  alignment: Alignment.center,
                  child: Text(
                    hasAccounts ? _initialsFor(name) : '+',
                    style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 13),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              InkWell(
                onTap: _openAddAccount,
                customBorder: const CircleBorder(),
                child: Container(
                  width: 34,
                  height: 34,
                  decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.18), shape: BoxShape.circle),
                  alignment: Alignment.center,
                  child: const Icon(Icons.add_rounded, size: 20, color: Colors.white),
                ),
              ),
            ],
          ),
          const SizedBox(height: 26),
          const Text('Current balance', style: TextStyle(color: Colors.white70, fontSize: 12.5)),
          const SizedBox(height: 6),
          Text(balance,
              style: const TextStyle(color: Colors.white, fontSize: 32, fontWeight: FontWeight.w800, letterSpacing: -0.5)),
          const SizedBox(height: 18),
          Row(
            children: [
              Expanded(
                child: Row(
                  children: [
                    Flexible(
                      child: Text(name,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 14.5)),
                    ),
                    if (account != null && (account['id'] as num?)?.toInt() == _defaultAccountId) ...[
                      const SizedBox(width: 6),
                      const Icon(Icons.star_rounded, size: 14, color: AppColors.warning),
                    ],
                  ],
                ),
              ),
              if (bank != null && bank.isNotEmpty)
                Text(bank, style: const TextStyle(color: Colors.white70, fontSize: 12.5)),
            ],
          ),
          if (_accounts.length > 1) ...[
            const SizedBox(height: 10),
            Text('${_accounts.length} accounts · tap the avatar to switch',
                style: const TextStyle(color: Colors.white60, fontSize: 11)),
          ] else if (!hasAccounts) ...[
            const SizedBox(height: 10),
            GestureDetector(
              onTap: _openAddAccount,
              child: const Text('+ Add a bank account',
                  style: TextStyle(color: Colors.white, fontSize: 12.5, fontWeight: FontWeight.w700)),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildRecentTransactions() => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      const Text('Recent transactions', style: TextStyle(fontSize: 16.5, fontWeight: FontWeight.w800, color: AppColors.textDark)),
      const SizedBox(height: 12),
      if (_recentTxns.isEmpty)
        Container(
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(16),
            boxShadow: const [BoxShadow(color: AppColors.shadow, blurRadius: 16, offset: Offset(0, 4))],
          ),
          child: const Text('No transactions yet.', style: TextStyle(color: AppColors.textMuted, fontSize: 13)),
        )
      else
        Container(
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(16),
            boxShadow: const [BoxShadow(color: AppColors.shadow, blurRadius: 16, offset: Offset(0, 4))],
          ),
          child: Column(
            children: [
              for (var i = 0; i < _recentTxns.length; i++) ...[
                if (i > 0) const Divider(height: 1, color: AppColors.border),
                _TransactionTile(txn: _recentTxns[i]),
              ],
            ],
          ),
        ),
    ],
  );
}

class _TransactionTile extends StatelessWidget {
  const _TransactionTile({required this.txn});
  final Map<String, dynamic> txn;

  @override
  Widget build(BuildContext context) {
    final title = (txn['source_title'] as String?) ?? (txn['source_label'] as String?) ?? 'Payment';
    final subtitle = [txn['source_label'], txn['date_fmt']]
        .whereType<String>()
        .where((s) => s.isNotEmpty)
        .join(' · ');
    final amount = formatMoney(txn['amount']);

    return ListTile(
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 2),
      leading: Container(
        width: 38,
        height: 38,
        decoration: BoxDecoration(color: AppColors.error.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(11)),
        alignment: Alignment.center,
        child: const Icon(Icons.arrow_upward_rounded, size: 17, color: AppColors.error),
      ),
      title: Text(title, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13.5, color: AppColors.textDark)),
      subtitle: subtitle.isEmpty
          ? null
          : Text(subtitle, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 11.5, color: AppColors.textMuted)),
      trailing: Text('-$amount', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5, color: AppColors.error)),
    );
  }
}

class _ErrorCard extends StatelessWidget {
  const _ErrorCard({required this.message, required this.onRetry});
  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(16),
      boxShadow: const [BoxShadow(color: AppColors.shadow, blurRadius: 16, offset: Offset(0, 4))],
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(message, style: const TextStyle(color: AppColors.textMuted, fontSize: 13)),
        const SizedBox(height: 10),
        TextButton.icon(onPressed: onRetry, icon: const Icon(Icons.refresh, size: 16), label: const Text('Retry')),
      ],
    ),
  );
}
