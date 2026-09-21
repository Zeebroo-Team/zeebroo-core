import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/theme/app_theme.dart';

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen>
    with SingleTickerProviderStateMixin {
  late final TabController _tabs = TabController(length: 3, vsync: this);

  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _notifications = [];
  int _unreadCount = 0;
  bool _clearing = false;

  @override
  void initState() {
    super.initState();
    _load();
    _tabs.addListener(() {
      if (!_tabs.indexIsChanging) _load();
    });
  }

  @override
  void dispose() {
    _tabs.dispose();
    super.dispose();
  }

  String get _statusFilter => switch (_tabs.index) {
        1 => 'unread',
        2 => 'read',
        _ => 'all',
      };

  Future<void> _load({bool forceRefresh = false}) async {
    setState(() { _loading = true; _error = null; });
    try {
      final res = await ApiClient.instance.get(
        ApiEndpoints.notifications,
        params: {'status': _statusFilter, 'limit': 100},
        bypassCache: forceRefresh || _statusFilter != 'all',
      );
      final body = res.data as Map;
      _notifications = ((body['data'] as List?) ?? [])
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .toList();
      _unreadCount = (body['unread_count'] as num?)?.toInt() ?? 0;
    } catch (e) {
      _error = _msg(e);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _markRead(int id) async {
    try {
      await ApiClient.instance.post(ApiEndpoints.notificationRead(id));
      setState(() {
        final idx = _notifications.indexWhere((n) => n['id'] == id);
        if (idx >= 0) {
          _notifications[idx] = {..._notifications[idx], 'read': true};
          _unreadCount = (_unreadCount - 1).clamp(0, 9999);
        }
      });
    } catch (_) {}
  }

  Future<void> _markAllRead() async {
    try {
      await ApiClient.instance.post(ApiEndpoints.notificationsReadAll);
      setState(() {
        _notifications = _notifications.map((n) => {...n, 'read': true}).toList();
        _unreadCount = 0;
      });
    } catch (_) {}
  }

  Future<void> _delete(int id) async {
    try {
      await ApiClient.instance.delete(ApiEndpoints.notificationDelete(id));
      setState(() => _notifications.removeWhere((n) => n['id'] == id));
    } catch (_) {}
  }

  Future<void> _clearAll() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Clear all notifications'),
        content: const Text('All notifications will be permanently removed.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          TextButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Clear all', style: TextStyle(color: AppColors.error)),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    setState(() => _clearing = true);
    try {
      await ApiClient.instance.delete(ApiEndpoints.notificationsClearAll);
      setState(() { _notifications.clear(); _unreadCount = 0; });
    } catch (_) {}
    if (mounted) setState(() => _clearing = false);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        backgroundColor: AppColors.surface,
        foregroundColor: AppColors.textDark,
        elevation: 0,
        centerTitle: true,
        title: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Text('Notifications', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 17)),
            if (_unreadCount > 0) ...[
              const SizedBox(width: 8),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                decoration: BoxDecoration(
                  color: AppColors.error,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Text(
                  '$_unreadCount',
                  style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w800),
                ),
              ),
            ],
          ],
        ),
        actions: [
          if (_unreadCount > 0)
            TextButton(
              onPressed: _markAllRead,
              child: const Text('Read all', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
            ),
          if (_notifications.isNotEmpty)
            _clearing
                ? const Padding(
                    padding: EdgeInsets.only(right: 16),
                    child: SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2)),
                  )
                : IconButton(
                    icon: const Icon(Icons.delete_sweep_outlined, size: 22),
                    tooltip: 'Clear all',
                    onPressed: _clearAll,
                  ),
        ],
        bottom: TabBar(
          controller: _tabs,
          labelColor: AppColors.primary,
          unselectedLabelColor: AppColors.textMuted,
          indicatorColor: AppColors.primary,
          indicatorSize: TabBarIndicatorSize.tab,
          labelStyle: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700),
          unselectedLabelStyle: const TextStyle(fontSize: 13, fontWeight: FontWeight.w500),
          tabs: [
            const Tab(text: 'All'),
            Tab(
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Text('Unread'),
                  if (_unreadCount > 0) ...[
                    const SizedBox(width: 5),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
                      decoration: BoxDecoration(
                        color: AppColors.error,
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Text(
                        '$_unreadCount',
                        style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w800),
                      ),
                    ),
                  ],
                ],
              ),
            ),
            const Tab(text: 'Read'),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tabs,
        children: [
          _buildList(),
          _buildList(),
          _buildList(),
        ],
      ),
    );
  }

  Widget _buildList() {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.wifi_off_rounded, size: 40, color: AppColors.textHint),
            const SizedBox(height: 8),
            Text(_error!, style: const TextStyle(color: AppColors.textMuted)),
            const SizedBox(height: 12),
            TextButton(onPressed: () => _load(forceRefresh: true), child: const Text('Retry')),
          ],
        ),
      );
    }
    if (_notifications.isEmpty) {
      return _EmptyState(statusFilter: _statusFilter);
    }
    return RefreshIndicator(
      onRefresh: () => _load(forceRefresh: true),
      child: ListView.separated(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
        itemCount: _notifications.length,
        separatorBuilder: (_, __) => const SizedBox(height: 8),
        itemBuilder: (_, i) => _NotificationCard(
          notification: _notifications[i],
          onMarkRead: () {
            if (_notifications[i]['read'] != true) _markRead(_notifications[i]['id'] as int);
          },
          onDelete: () => _delete(_notifications[i]['id'] as int),
        ),
      ),
    );
  }
}

// ── Empty state ──────────────────────────────────────────────────────────────

class _EmptyState extends StatelessWidget {
  const _EmptyState({required this.statusFilter});
  final String statusFilter;

  @override
  Widget build(BuildContext context) {
    final msg = switch (statusFilter) {
      'unread' => 'No unread notifications',
      'read' => 'No read notifications',
      _ => 'No notifications yet',
    };
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 72,
            height: 72,
            decoration: BoxDecoration(
              color: AppColors.primaryLt,
              shape: BoxShape.circle,
            ),
            child: const Icon(Icons.notifications_none_rounded, size: 36, color: AppColors.primary),
          ),
          const SizedBox(height: 14),
          Text(msg, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w600, color: AppColors.textMuted)),
          const SizedBox(height: 4),
          const Text("You're all caught up!", style: TextStyle(fontSize: 12.5, color: AppColors.textHint)),
        ],
      ),
    );
  }
}

// ── Notification card ────────────────────────────────────────────────────────

class _NotificationCard extends StatelessWidget {
  const _NotificationCard({
    required this.notification,
    required this.onMarkRead,
    required this.onDelete,
  });

  final Map<String, dynamic> notification;
  final VoidCallback onMarkRead;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final bool read = notification['read'] == true;
    final type = (notification['type'] as String?) ?? '';
    final title = (notification['title'] as String?) ?? '';
    final message = (notification['message'] as String?) ?? '';
    final createdAt = notification['created_at'] as String?;

    final (iconData, iconColor) = _iconForType(type);

    return Dismissible(
      key: ValueKey(notification['id']),
      direction: DismissDirection.endToStart,
      background: Container(
        alignment: Alignment.centerRight,
        padding: const EdgeInsets.only(right: 20),
        decoration: BoxDecoration(
          color: AppColors.error,
          borderRadius: BorderRadius.circular(16),
        ),
        child: const Icon(Icons.delete_outline_rounded, color: Colors.white, size: 24),
      ),
      onDismissed: (_) => onDelete(),
      child: GestureDetector(
        onTap: read ? null : onMarkRead,
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: read ? Colors.white : AppColors.primaryLt,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(
              color: read ? AppColors.border : AppColors.primary.withValues(alpha: 0.25),
            ),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: read ? 0.03 : 0.06),
                blurRadius: 8,
                offset: const Offset(0, 3),
              ),
            ],
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Icon container
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: iconColor.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(iconData, color: iconColor, size: 22),
              ),
              const SizedBox(width: 12),
              // Content
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        if (!read)
                          Container(
                            width: 7,
                            height: 7,
                            margin: const EdgeInsets.only(right: 6, top: 2),
                            decoration: const BoxDecoration(
                              color: AppColors.primary,
                              shape: BoxShape.circle,
                            ),
                          ),
                        Expanded(
                          child: Text(
                            title,
                            style: TextStyle(
                              fontSize: 13.5,
                              fontWeight: read ? FontWeight.w600 : FontWeight.w700,
                              color: AppColors.textDark,
                            ),
                          ),
                        ),
                      ],
                    ),
                    if (message.isNotEmpty) ...[
                      const SizedBox(height: 3),
                      Text(
                        message,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(fontSize: 12.5, color: AppColors.textMuted),
                      ),
                    ],
                    if (createdAt != null) ...[
                      const SizedBox(height: 5),
                      Text(
                        _timeAgo(createdAt),
                        style: const TextStyle(fontSize: 11, color: AppColors.textHint),
                      ),
                    ],
                  ],
                ),
              ),
              // Delete button
              GestureDetector(
                onTap: onDelete,
                child: const Padding(
                  padding: EdgeInsets.only(left: 6),
                  child: Icon(Icons.close_rounded, size: 16, color: AppColors.textHint),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  (IconData, Color) _iconForType(String type) => switch (type) {
        'stock_out' => (Icons.inventory_2_outlined, AppColors.error),
        'stock_low' => (Icons.warning_amber_rounded, AppColors.warning),
        'bill_overdue' => (Icons.receipt_outlined, AppColors.error),
        'loan_overdue' => (Icons.account_balance_outlined, AppColors.error),
        'rental_overdue' => (Icons.home_outlined, AppColors.error),
        'investment_overdue' => (Icons.show_chart_rounded, AppColors.error),
        'property_expired' => (Icons.apartment_outlined, const Color(0xFF8B5CF6)),
        'purchase_order_overdue' => (Icons.shopping_cart_outlined, AppColors.warning),
        'purchase_order_received' => (Icons.local_shipping_outlined, AppColors.success),
        'cheque_overdue' => (Icons.money_outlined, AppColors.error),
        'sale_large' => (Icons.trending_up_rounded, AppColors.success),
        'automation' => (Icons.auto_awesome_outlined, const Color(0xFF06B6D4)),
        'payment_succeeded' => (Icons.check_circle_outline_rounded, AppColors.success),
        'payment_failed' => (Icons.error_outline_rounded, AppColors.error),
        'subscription_renewal_upcoming' => (Icons.refresh_rounded, AppColors.primary),
        _ => (Icons.notifications_outlined, AppColors.primary),
      };

  String _timeAgo(String dateStr) {
    try {
      final dt = DateTime.parse(dateStr);
      final diff = DateTime.now().difference(dt);
      if (diff.inSeconds < 60) return 'Just now';
      if (diff.inMinutes < 60) return '${diff.inMinutes}m ago';
      if (diff.inHours < 24) return '${diff.inHours}h ago';
      if (diff.inDays < 7) return '${diff.inDays}d ago';
      return '${dt.day}/${dt.month}/${dt.year}';
    } catch (_) {
      return dateStr;
    }
  }
}

String _msg(Object e) => e.toString().replaceFirst('Exception: ', '');
