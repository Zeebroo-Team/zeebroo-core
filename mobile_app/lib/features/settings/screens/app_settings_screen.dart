import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../../core/offline/offline_products.dart';
import '../../../core/theme/app_theme.dart';

/// Settings that belong to this phone (not the business): currently the
/// "Offline products" switch for an instant, on-device POS catalog.
class AppSettingsScreen extends StatefulWidget {
  const AppSettingsScreen({super.key});

  @override
  State<AppSettingsScreen> createState() => _AppSettingsScreenState();
}

class _AppSettingsScreenState extends State<AppSettingsScreen> {
  final _offline = OfflineProducts.instance;
  bool _loaded = false;
  int _count = 0;
  DateTime? _syncedAt;

  @override
  void initState() {
    super.initState();
    _offline.addListener(_onOfflineChanged);
    _init();
  }

  @override
  void dispose() {
    _offline.removeListener(_onOfflineChanged);
    super.dispose();
  }

  Future<void> _init() async {
    await _offline.load();
    await _refreshStatus();
    if (mounted) setState(() => _loaded = true);
  }

  void _onOfflineChanged() {
    if (!mounted) return;
    setState(() {});
    if (!_offline.syncing) _refreshStatus();
  }

  Future<void> _refreshStatus() async {
    final status = await _offline.status();
    if (!mounted) return;
    setState(() {
      _count = status.count;
      _syncedAt = status.syncedAt;
    });
  }

  Future<void> _toggle(bool value) async {
    if (!value) {
      final ok = await showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: const Text('Turn off offline products?'),
          content: const Text(
            'Saved products will be deleted from this phone. POS will load products from the server again.',
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
            FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Turn off')),
          ],
        ),
      );
      if (ok != true) return;
    }
    await _offline.setEnabled(value);
    await _refreshStatus();
  }

  Future<void> _clear() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Clear saved products?'),
        content: const Text('They will be downloaded again on the next sync.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Clear')),
        ],
      ),
    );
    if (ok != true) return;
    await _offline.clearSaved();
    await _refreshStatus();
  }

  String _statusLine() {
    if (_offline.syncing) {
      return _offline.progressTotal > 0
          ? 'Syncing… ${_offline.progressDone} / ${_offline.progressTotal} products'
          : 'Checking for changes…';
    }
    if (_syncedAt == null) return 'Not synced yet';
    final when = DateFormat('MMM d, h:mm a').format(_syncedAt!);
    return 'Last synced: $when · $_count product${_count == 1 ? '' : 's'}';
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: AppColors.surface,
    appBar: AppBar(
      backgroundColor: AppColors.surface,
      foregroundColor: AppColors.textDark,
      elevation: 0,
      centerTitle: true,
      leading: IconButton(
        onPressed: () => Navigator.of(context).pop(),
        icon: const Icon(Icons.arrow_back_rounded),
      ),
      title: const Text('App Settings', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 17)),
    ),
    body: !_loaded
        ? const Center(child: CircularProgressIndicator())
        : ListView(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
            children: [
              const Padding(
                padding: EdgeInsets.fromLTRB(4, 8, 4, 8),
                child: Text(
                  'OFFLINE PRODUCTS',
                  style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, letterSpacing: 0.6, color: AppColors.textMuted),
                ),
              ),
              Container(
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: const Color(0xFFE5E7EB)),
                ),
                child: !OfflineProducts.supported
                    ? const Padding(
                        padding: EdgeInsets.all(16),
                        child: Text(
                          'Offline products are available in the Android and iPhone app only.',
                          style: TextStyle(fontSize: 13, color: AppColors.textMuted),
                        ),
                      )
                    : Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          SwitchListTile(
                            key: const ValueKey('offline-products-switch'),
                            value: _offline.enabled,
                            onChanged: _offline.syncing && !_offline.enabled ? null : _toggle,
                            title: const Text(
                              'Save products on this phone',
                              style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14.5),
                            ),
                            subtitle: const Padding(
                              padding: EdgeInsets.only(top: 4),
                              child: Text(
                                'POS opens instantly and search & barcode scan work without waiting. '
                                'Products update in the background; sales still go to the server.',
                                style: TextStyle(fontSize: 12.5, height: 1.4),
                              ),
                            ),
                          ),
                          if (_offline.enabled) ...[
                            const Divider(height: 1),
                            Padding(
                              padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
                              child: Row(
                                children: [
                                  if (_offline.syncing)
                                    const Padding(
                                      padding: EdgeInsets.only(right: 8),
                                      child: SizedBox(width: 14, height: 14, child: CircularProgressIndicator(strokeWidth: 2)),
                                    ),
                                  Expanded(
                                    child: Text(_statusLine(), style: const TextStyle(fontSize: 13, color: AppColors.textDark)),
                                  ),
                                ],
                              ),
                            ),
                            if (_offline.syncing && _offline.progressTotal > 0)
                              Padding(
                                padding: const EdgeInsets.fromLTRB(16, 6, 16, 0),
                                child: LinearProgressIndicator(
                                  value: _offline.progressDone / _offline.progressTotal,
                                  minHeight: 4,
                                  borderRadius: BorderRadius.circular(2),
                                ),
                              ),
                            if (_offline.lastError != null && !_offline.syncing)
                              Padding(
                                padding: const EdgeInsets.fromLTRB(16, 6, 16, 0),
                                child: Text(
                                  _offline.lastErrorWasConnection
                                      ? 'Last sync failed: no connection. Saved products are still used.'
                                      : 'Last sync failed: ${_offline.lastError}',
                                  style: const TextStyle(fontSize: 12, color: AppColors.error),
                                ),
                              ),
                            Padding(
                              padding: const EdgeInsets.fromLTRB(12, 8, 12, 12),
                              child: Row(
                                children: [
                                  TextButton.icon(
                                    onPressed: _offline.syncing ? null : () => _offline.sync(),
                                    icon: const Icon(Icons.sync_rounded, size: 18),
                                    label: const Text('Sync now'),
                                  ),
                                  const Spacer(),
                                  TextButton.icon(
                                    style: TextButton.styleFrom(foregroundColor: AppColors.error),
                                    onPressed: _offline.syncing ? null : _clear,
                                    icon: const Icon(Icons.delete_outline_rounded, size: 18),
                                    label: const Text('Clear saved products'),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ],
                      ),
              ),
            ],
          ),
  );
}
