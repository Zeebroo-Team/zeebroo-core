import 'dart:async';
import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/money.dart';
import '../data/gift_card_repository.dart';
import '../widgets/gift_card_summary_card.dart';
import 'gift_card_detail_screen.dart';
import 'gift_card_form_screen.dart';

class GiftCardManagerScreen extends StatefulWidget {
  const GiftCardManagerScreen({
    super.key,
    this.repository = const GiftCardRepository(),
  });
  final GiftCardRepository repository;
  @override
  State<GiftCardManagerScreen> createState() => _GiftCardManagerScreenState();
}

class _GiftCardManagerScreenState extends State<GiftCardManagerScreen> {
  static const _filters = [
    (label: 'All', value: ''),
    (label: 'Active', value: 'active'),
    (label: 'Used', value: 'used'),
    (label: 'Expired', value: 'expired'),
    (label: 'Disabled', value: 'disabled'),
    (label: 'Scheduled', value: 'scheduled'),
  ];
  final _search = TextEditingController();
  Timer? _debounce;
  String _status = '';
  bool _loading = true;
  String? _error;
  int _request = 0;
  List<GiftCardRecord> _groups = [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    if (!mounted) return;
    final request = ++_request;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final groups = await widget.repository.list(
        query: _search.text.trim(),
        status: _status,
      );
      if (mounted && request == _request) setState(() => _groups = groups);
    } catch (error) {
      if (mounted && request == _request) {
        setState(() => _error = apiErrorMessage(error));
      }
    } finally {
      if (mounted && request == _request) setState(() => _loading = false);
    }
  }

  Future<void> _new() async {
    final saved = await Navigator.of(context).push<GiftCardRecord>(
      MaterialPageRoute(
        builder: (_) => GiftCardFormScreen(repository: widget.repository),
      ),
    );
    if (!mounted || saved == null) return;
    await _load();
    if (mounted) await _open(saved);
  }

  Future<void> _open(GiftCardRecord record) async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) =>
            GiftCardDetailScreen(record: record, repository: widget.repository),
      ),
    );
    if (mounted) await _load();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: AppColors.surface,
    appBar: AppBar(centerTitle: true, title: const Text('Gift Cards')),
    body: SafeArea(
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 12, 20, 0),
            child: TextField(
              controller: _search,
              textInputAction: TextInputAction.search,
              onChanged: (_) {
                _debounce?.cancel();
                ++_request;
                _debounce = Timer(const Duration(milliseconds: 300), _load);
              },
              onSubmitted: (_) {
                _debounce?.cancel();
                _load();
              },
              decoration: const InputDecoration(
                hintText: 'Search by name or code',
                prefixIcon: Icon(Icons.search_rounded),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 10, 20, 2),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    '${_groups.length} groups · ${_groups.fold<int>(0, (sum, g) => sum + g.cardCount)} cards',
                    style: const TextStyle(
                      color: AppColors.textMuted,
                      fontSize: 12,
                    ),
                  ),
                ),
                TextButton.icon(
                  onPressed: _new,
                  icon: const Icon(Icons.add_rounded),
                  label: const Text('New Gift Card'),
                ),
              ],
            ),
          ),
          SizedBox(
            height: 48,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 20),
              itemCount: _filters.length,
              separatorBuilder: (_, _) => const SizedBox(width: 8),
              itemBuilder: (_, index) {
                final filter = _filters[index];
                return ChoiceChip(
                  label: Text(filter.label),
                  selected: _status == filter.value,
                  onSelected: (_) {
                    _debounce?.cancel();
                    setState(() => _status = filter.value);
                    _load();
                  },
                );
              },
            ),
          ),
          Expanded(child: _body()),
        ],
      ),
    ),
  );

  Widget _body() {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(_error!, textAlign: TextAlign.center),
              TextButton(onPressed: _load, child: const Text('Retry')),
            ],
          ),
        ),
      );
    }
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.separated(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
        itemCount: _groups.isEmpty ? 1 : _groups.length,
        separatorBuilder: (_, _) => const SizedBox(height: 12),
        itemBuilder: (_, index) {
          if (_groups.isEmpty) {
            return const Padding(
              padding: EdgeInsets.symmetric(vertical: 60),
              child: Center(child: Text('No gift cards found.')),
            );
          }
          final group = _groups[index];
          return Card(
            margin: EdgeInsets.zero,
            child: Column(
              children: [
                ListTile(
                  onTap: () => _open(group),
                  leading: const CircleAvatar(
                    backgroundColor: Color(0xFFF3E8FF),
                    child: Icon(
                      Icons.card_giftcard_rounded,
                      color: Color(0xFF7C3AED),
                    ),
                  ),
                  title: Text(
                    group.name,
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                  subtitle: Text(
                    '${formatMoney(group.initialValue)} each · ${group.cardCount} cards',
                    style: const TextStyle(fontSize: 12),
                  ),
                  trailing: const Icon(Icons.chevron_right_rounded),
                ),
                ExpansionTile(
                  key: PageStorageKey('gift-group-${group.id}'),
                  title: const Text(
                    'View cards',
                    style: TextStyle(fontSize: 13),
                  ),
                  subtitle: _status != '' || _search.text.trim().isNotEmpty
                      ? const Text(
                          'Matching cards only',
                          style: TextStyle(fontSize: 11),
                        )
                      : null,
                  children: group.cards
                      .map(
                        (card) => ListTile(
                          onTap: () => _open(card),
                          title: Text(
                            card.code,
                            style: const TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                          subtitle: Text(
                            'Balance ${formatMoney(card.balance)}',
                            style: const TextStyle(fontSize: 12),
                          ),
                          trailing: GiftCardStatusBadge(status: card.status),
                        ),
                      )
                      .toList(),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}
