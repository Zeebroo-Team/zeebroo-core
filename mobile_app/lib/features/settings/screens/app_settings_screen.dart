import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../../core/offline/offline_customers.dart';
import '../../../core/offline/offline_products.dart';
import '../../../core/offline/offline_sales.dart';
import '../../../core/theme/app_theme.dart';
import '../../pos/screens/offline_sales_screen.dart';

/// Settings that belong to this phone (not the business): currently the
/// "Offline products" switch for an instant, on-device POS catalog.
class AppSettingsScreen extends StatefulWidget {
  const AppSettingsScreen({super.key});

  @override
  State<AppSettingsScreen> createState() => _AppSettingsScreenState();
}

class _AppSettingsScreenState extends State<AppSettingsScreen> {
  final _offline = OfflineProducts.instance;
  final _sales = OfflineSales.instance;
  bool _loaded = false;
  int _count = 0;
  DateTime? _syncedAt;

  @override
  void initState() {
    super.initState();
    _offline.addListener(_onOfflineChanged);
    _sales.addListener(_onOfflineChanged);
    _sales.start();
    _init();
  }

  @override
  void dispose() {
    _offline.removeListener(_onOfflineChanged);
    _sales.removeListener(_onOfflineChanged);
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
          title: const Text('Turn off offline mode?'),
          content: Text(
            'Saved products and customers will be deleted from this phone and POS will load them from the server again. '
            'New sales will need internet.'
            '${_sales.allWaitingCount > 0 ? ' The ${_sales.allWaitingCount} offline record${_sales.allWaitingCount == 1 ? '' : 's'} not yet uploaded will be kept and still upload.' : ''}',
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
                          // Own Material so the tap ripple shows on the white card.
                          Material(
                            type: MaterialType.transparency,
                            child: SwitchListTile(
                              key: const ValueKey('offline-products-switch'),
                              value: _offline.enabled,
                              onChanged: _offline.syncing && !_offline.enabled ? null : _toggle,
                              title: const Text(
                                'Offline mode',
                                style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14.5),
                              ),
                              subtitle: const Padding(
                                padding: EdgeInsets.only(top: 4),
                                child: Text(
                                  'Saves products and customers on this phone so POS opens instantly, and keeps working when the internet drops: '
                                  'sales (cash, card or credit), new customers and quick expenses are kept on the phone. '
                                  'They upload automatically when you’re back online.',
                                  style: TextStyle(fontSize: 12.5, height: 1.4),
                                ),
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
                              // Wraps onto two lines on narrow phones / large text.
                              child: Wrap(
                                alignment: WrapAlignment.spaceBetween,
                                spacing: 8,
                                children: [
                                  TextButton.icon(
                                    onPressed: _offline.syncing
                                        ? null
                                        : () {
                                            _offline.sync();
                                            OfflineCustomers.instance.download(force: true);
                                          },
                                    icon: const Icon(Icons.cloud_download_rounded, size: 18),
                                    label: const Text('Download latest'),
                                  ),
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
              if (OfflineProducts.supported) ...[
                const Padding(
                  padding: EdgeInsets.fromLTRB(4, 24, 4, 8),
                  child: Text(
                    'OFFLINE RECORDS',
                    style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, letterSpacing: 0.6, color: AppColors.textMuted),
                  ),
                ),
                Container(
                  padding: const EdgeInsets.fromLTRB(16, 14, 12, 8),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: const Color(0xFFE5E7EB)),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Text(
                        _sales.allWaitingCount == 0
                            ? 'Everything saved offline is uploaded.'
                            : '${_sales.allWaitingCount} offline record${_sales.allWaitingCount == 1 ? '' : 's'} not uploaded yet',
                        style: const TextStyle(fontSize: 13.5, fontWeight: FontWeight.w600, color: AppColors.textDark),
                      ),
                      if (_sales.lastError != null && !_sales.uploading) ...[
                        const SizedBox(height: 6),
                        Text(_sales.lastError!, style: const TextStyle(fontSize: 12, color: AppColors.error)),
                      ],
                      const SizedBox(height: 4),
                      Wrap(
                        alignment: WrapAlignment.spaceBetween,
                        spacing: 8,
                        children: [
                          TextButton.icon(
                            onPressed: _sales.uploading || !_sales.hasPendingUploads ? null : _sales.upload,
                            icon: _sales.uploading
                                ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                                : const Icon(Icons.cloud_upload_rounded, size: 18),
                            label: Text(_sales.uploading ? 'Uploading…' : 'Upload pending'),
                          ),
                          TextButton(
                            onPressed: () => Navigator.of(context).push(
                              MaterialPageRoute(builder: (_) => const OfflineSalesScreen()),
                            ),
                            child: const Text('View offline records'),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ],
            ],
          ),
  );
}
