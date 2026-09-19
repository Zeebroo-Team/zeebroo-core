import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/money.dart';
import 'add_account_sheet.dart';

/// First home tab — a compact balance row for the business's bank accounts
/// (tap to switch or add an account), the caller's [belowBalance] content
/// (today's sales + quick actions), then a recent-transactions feed sourced
/// from bill/rental payments.
class AccountOverviewTab extends StatefulWidget {
  const AccountOverviewTab({super.key, this.belowBalance, this.onPullRefresh});

  /// Rendered directly under the balance row, scrolling with the list.
  final Widget? belowBalance;

  /// Called when the user pulls to refresh, alongside the tab's own reload.
  final VoidCallback? onPullRefresh;

  @override
  State<AccountOverviewTab> createState() => _AccountOverviewTabState();
}

class _AccountOverviewTabState extends State<AccountOverviewTab>
    with AutomaticKeepAliveClientMixin {
  @override
  bool get wantKeepAlive => true;

  bool _loading = true;
  bool _loaded = false;
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

  Future<void> _load({bool forceRefresh = false}) async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final results = await Future.wait([
        ApiClient.instance.get(ApiEndpoints.accounts, bypassCache: forceRefresh),
        ApiClient.instance.get(ApiEndpoints.expensesOverview, bypassCache: forceRefresh),
        ApiClient.instance.get(ApiEndpoints.businessSettings, bypassCache: forceRefresh),
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
      _loaded = true;
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
  Widget build(BuildContext context) {
    super.build(context);
    final firstLoad = _loading && !_loaded;
    return RefreshIndicator(
      onRefresh: () {
        widget.onPullRefresh?.call();
        return _load(forceRefresh: true);
      },
      child: ListView(
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 120),
        children: [
          if (firstLoad)
            const SizedBox(
              height: 52,
              child: Center(child: SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2.4))),
            )
          else if (_error != null)
            _ErrorCard(message: _error!, onRetry: _load)
          else
            _buildBalanceRow(),
          if (widget.belowBalance != null) ...[
            const SizedBox(height: 8),
            const Divider(height: 1, color: Color(0xFFE5E7EB)),
            const SizedBox(height: 14),
            widget.belowBalance!,
          ],
          if (!firstLoad && _error == null) ...[
            const SizedBox(height: 26),
            _buildRecentTransactions(),
          ],
        ],
      ),
    );
  }

  Widget _buildBalanceRow() {
    final hasAccounts = _accounts.isNotEmpty;
    final account = hasAccounts ? _accounts[_selected] : null;
    final balance = formatMoney(account?['current_balance']);
    final name = account?['account_name'] as String? ?? 'No account yet';
    final bank = account?['bank_name'] as String?;
    final isDefault = account != null && (account['id'] as num?)?.toInt() == _defaultAccountId;
    final subtitle = hasAccounts
        ? [
            if (bank != null && bank.isNotEmpty) bank,
            if (_accounts.length > 1) '${_accounts.length} accounts',
          ].join(' · ')
        : 'Tap to add a bank account';

    return InkWell(
      onTap: hasAccounts ? _pickAccount : _openAddAccount,
      borderRadius: BorderRadius.circular(12),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 8),
        child: Row(
          children: [
            Container(
              width: 36,
              height: 36,
              decoration: const BoxDecoration(color: AppColors.primaryLt, shape: BoxShape.circle),
              alignment: Alignment.center,
              child: hasAccounts
                  ? Text(_initialsFor(name), style: const TextStyle(color: AppColors.primaryDk, fontWeight: FontWeight.w800, fontSize: 12.5))
                  : const Icon(Icons.add_rounded, size: 20, color: AppColors.primaryDk),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Row(
                    children: [
                      Flexible(
                        child: Text(name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5, color: AppColors.textDark)),
                      ),
                      if (isDefault) ...[
                        const SizedBox(width: 5),
                        const Icon(Icons.star_rounded, size: 14, color: AppColors.warning),
                      ],
                    ],
                  ),
                  if (subtitle.isNotEmpty)
                    Text(subtitle, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 11.5, color: AppColors.textMuted)),
                ],
              ),
            ),
            if (hasAccounts) ...[
              const SizedBox(width: 10),
              ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 150),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    FittedBox(
                      fit: BoxFit.scaleDown,
                      alignment: Alignment.centerRight,
                      child: Text(balance, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: AppColors.textDark, letterSpacing: -0.2)),
                    ),
                    const Text('Current balance', style: TextStyle(fontSize: 10.5, color: AppColors.textMuted)),
                  ],
                ),
              ),
            ],
            const SizedBox(width: 2),
            Icon(hasAccounts ? Icons.unfold_more_rounded : Icons.chevron_right_rounded, size: 18, color: AppColors.textHint),
          ],
        ),
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
