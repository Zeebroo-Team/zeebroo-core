import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../core/api/api_client.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/money.dart';
import '../data/gift_card_repository.dart';
import '../widgets/gift_card_summary_card.dart';
import 'gift_card_form_screen.dart';

class GiftCardDetailScreen extends StatefulWidget {
  const GiftCardDetailScreen({
    super.key,
    required this.record,
    this.repository = const GiftCardRepository(),
  });
  final GiftCardRecord record;
  final GiftCardRepository repository;
  @override
  State<GiftCardDetailScreen> createState() => _GiftCardDetailScreenState();
}

class _GiftCardDetailScreenState extends State<GiftCardDetailScreen> {
  late GiftCardRecord _record;
  bool _loading = true;
  bool _busy = false;
  String? _error;
  int _request = 0;
  bool get _locked => _loading || _busy;
  bool get _used => _record.isGroup
      ? _record.cards.any((card) => card.usedAmount > 0)
      : _record.hasRedemptions || _record.usedAmount > 0;

  @override
  void initState() {
    super.initState();
    _record = widget.record;
    _load();
  }

  Future<void> _load({bool popOnNotFound = false}) async {
    if (!mounted || _busy) return;
    final request = ++_request;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final record = await widget.repository.detail(_record);
      if (mounted && request == _request) setState(() => _record = record);
    } catch (error) {
      if (!mounted || request != _request) return;
      // Deleting the last card also deletes its group on the server.
      if (popOnNotFound &&
          error is DioException &&
          error.response?.statusCode == 404) {
        Navigator.of(context).pop(true);
      } else {
        setState(() => _error = apiErrorMessage(error));
      }
    } finally {
      if (mounted && request == _request) setState(() => _loading = false);
    }
  }

  Future<void> _edit() async {
    final saved = await Navigator.of(context).push<GiftCardRecord>(
      MaterialPageRoute(
        builder: (_) => GiftCardFormScreen(
          existing: _record,
          repository: widget.repository,
        ),
      ),
    );
    if (!mounted || saved == null) return;
    setState(() => _record = saved);
    await _load();
  }

  Future<void> _openCard(GiftCardRecord card) async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) =>
            GiftCardDetailScreen(record: card, repository: widget.repository),
      ),
    );
    if (mounted) await _load(popOnNotFound: true);
  }

  Future<void> _copy([String? code]) async {
    final text =
        code ??
        (_record.isGroup
            ? _record.cards.map((card) => card.code).join('\n')
            : _record.code);
    if (text.isEmpty) return;
    await Clipboard.setData(ClipboardData(text: text));
    if (mounted) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Gift card code copied')));
    }
  }

  Future<void> _addCards() async {
    final quantity = await showDialog<int>(
      context: context,
      builder: (_) => _AddGiftCardsDialog(group: _record),
    );
    if (!mounted || quantity == null || _locked) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final updated = await widget.repository.addCards(_record.id, quantity);
      if (mounted) {
        setState(() => _record = updated);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('$quantity gift cards generated')),
        );
      }
    } catch (error) {
      if (mounted) setState(() => _error = apiErrorMessage(error));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _delete() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(
          _record.isGroup ? 'Delete gift card group' : 'Delete gift card',
        ),
        content: Text(
          _record.isGroup
              ? 'Permanently delete "${_record.name}" and all ${_record.cardCount} cards?'
              : 'Permanently delete ${_record.code}?',
        ),
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
    if (!mounted || confirmed != true || _locked) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await widget.repository.delete(_record);
      if (mounted) Navigator.of(context).pop(true);
    } catch (error) {
      if (mounted) setState(() => _error = apiErrorMessage(error));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: AppColors.surface,
    appBar: AppBar(
      centerTitle: true,
      title: Text(_record.isGroup ? 'Gift Card Group' : 'Gift Card Details'),
    ),
    body: RefreshIndicator(
      onRefresh: () => _load(),
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(20),
        children: [
          GiftCardSummaryCard(record: _record),
          if (_loading || _busy)
            const Padding(
              padding: EdgeInsets.only(top: 12),
              child: LinearProgressIndicator(),
            ),
          const SizedBox(height: 20),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  style: _buttonStyle,
                  onPressed: _locked ? null : () => _copy(),
                  icon: const Icon(Icons.copy_rounded, size: 18),
                  label: Text(_record.isGroup ? 'Copy All Codes' : 'Copy Code'),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: OutlinedButton.icon(
                  style: _buttonStyle,
                  onPressed: _locked || _error != null ? null : _edit,
                  icon: const Icon(Icons.edit_outlined, size: 18),
                  label: Text(_record.isGroup ? 'Edit Group' : 'Edit'),
                ),
              ),
            ],
          ),
          const SizedBox(height: 20),
          _field('Valid', _record.validityLabel),
          if (!_record.isGroup)
            _field(
              'Customer',
              _record.customerName.isEmpty
                  ? 'Not assigned'
                  : _record.customerName,
            ),
          _field('Notes', _record.notes.isEmpty ? '—' : _record.notes),
          if (_record.isGroup) ...[
            const Divider(height: 24),
            const Text(
              'Cards in this group',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 10),
            OutlinedButton.icon(
              key: const ValueKey('generate-more-gifts'),
              style: _buttonStyle,
              onPressed: _locked || _error != null ? null : _addCards,
              icon: const Icon(Icons.add_rounded),
              label: const Text('Generate more'),
            ),
            const SizedBox(height: 10),
            if (_record.cards.isEmpty) const Text('No cards in this group.'),
            ..._record.cards.map(
              (card) => Card(
                margin: const EdgeInsets.only(bottom: 8),
                child: ListTile(
                  contentPadding: const EdgeInsets.symmetric(horizontal: 12),
                  onTap: _locked ? null : () => _openCard(card),
                  title: Text(
                    card.code,
                    style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  subtitle: Wrap(
                    spacing: 8,
                    runSpacing: 4,
                    crossAxisAlignment: WrapCrossAlignment.center,
                    children: [
                      Text(
                        'Balance ${formatMoney(card.balance)}',
                        style: const TextStyle(fontSize: 12),
                      ),
                      GiftCardStatusBadge(status: card.status),
                    ],
                  ),
                  trailing: IconButton(
                    tooltip: 'Copy code',
                    onPressed: _locked ? null : () => _copy(card.code),
                    icon: const Icon(Icons.copy_outlined, size: 18),
                  ),
                ),
              ),
            ),
          ] else ...[
            const Divider(height: 24),
            const Text(
              'Usage history',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 10),
            if (!_loading && _error == null && _record.transactions.isEmpty)
              const Text('No transactions yet.'),
            ..._record.transactions.map(_transaction),
          ],
          if (_error != null) ...[
            const SizedBox(height: 12),
            Text(_error!, style: const TextStyle(color: AppColors.error)),
            TextButton(
              onPressed: _busy ? null : () => _load(),
              child: const Text('Refresh details'),
            ),
          ],
          const SizedBox(height: 20),
          if (_used)
            const Text(
              'Cards used in sales cannot be deleted. Edit and turn off Active to disable them.',
              style: TextStyle(fontSize: 12, color: AppColors.textMuted),
            ),
          TextButton.icon(
            onPressed: _locked || _used || _error != null ? null : _delete,
            style: TextButton.styleFrom(foregroundColor: AppColors.error),
            icon: const Icon(Icons.delete_outline),
            label: Text(_record.isGroup ? 'Delete Group' : 'Delete Gift Card'),
          ),
        ],
      ),
    ),
  );

  ButtonStyle get _buttonStyle => OutlinedButton.styleFrom(
    minimumSize: const Size(0, 46),
    padding: const EdgeInsets.symmetric(horizontal: 6),
    textStyle: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
  );

  Widget _field(String label, String value) => Padding(
    padding: const EdgeInsets.only(bottom: 14),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label.toUpperCase(),
          style: const TextStyle(
            fontSize: 11,
            fontWeight: FontWeight.w700,
            color: AppColors.textMuted,
          ),
        ),
        const SizedBox(height: 5),
        Text(value),
      ],
    ),
  );

  Widget _transaction(Map<String, dynamic> transaction) {
    final amount = double.tryParse(transaction['amount'].toString()) ?? 0;
    final after = double.tryParse(transaction['balance_after'].toString()) ?? 0;
    final type = transaction['type']?.toString() ?? '';
    final label = switch (type) {
      'issue' => 'Issued',
      'redeem' => 'Redeemed',
      'refund' => 'Refunded',
      'adjust' => 'Value adjusted',
      _ => type,
    };
    final when = DateTime.tryParse(
      transaction['created_at']?.toString() ?? '',
    )?.toLocal();
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            [
              label,
              if (transaction['sale_number'] != null)
                transaction['sale_number'],
            ].join(' · '),
            style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
          ),
          if (when != null)
            Text(
              '${giftCardDate(when)} ${TimeOfDay.fromDateTime(when).format(context)}',
              style: const TextStyle(fontSize: 11, color: AppColors.textMuted),
            ),
          if (transaction['user_name'] != null)
            Text(
              transaction['user_name'].toString(),
              style: const TextStyle(fontSize: 11, color: AppColors.textMuted),
            ),
          if (transaction['notes'] != null)
            Text(
              transaction['notes'].toString(),
              style: const TextStyle(fontSize: 11, color: AppColors.textMuted),
            ),
          const SizedBox(height: 4),
          Wrap(
            spacing: 12,
            runSpacing: 4,
            children: [
              Text(
                '${amount > 0 ? '+' : ''}${formatMoney(amount)}',
                style: TextStyle(
                  fontWeight: FontWeight.w700,
                  color: amount < 0 ? AppColors.error : AppColors.success,
                ),
              ),
              Text(
                'Balance ${formatMoney(after)}',
                style: const TextStyle(fontSize: 12, color: AppColors.primary),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _AddGiftCardsDialog extends StatefulWidget {
  const _AddGiftCardsDialog({required this.group});
  final GiftCardRecord group;
  @override
  State<_AddGiftCardsDialog> createState() => _AddGiftCardsDialogState();
}

class _AddGiftCardsDialogState extends State<_AddGiftCardsDialog> {
  final _form = GlobalKey<FormState>();
  int? _quantity = 1;
  @override
  Widget build(BuildContext context) => AlertDialog(
    title: const Text('Generate more cards'),
    content: SingleChildScrollView(
      child: Form(
        key: _form,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Add cards to "${widget.group.name}" worth ${formatMoney(widget.group.initialValue)} each.',
            ),
            const SizedBox(height: 16),
            TextFormField(
              key: const ValueKey('add-gifts-quantity'),
              initialValue: '1',
              keyboardType: TextInputType.number,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly],
              decoration: const InputDecoration(labelText: 'Number of cards'),
              onChanged: (text) =>
                  setState(() => _quantity = int.tryParse(text)),
              validator: (_) =>
                  _quantity == null || _quantity! < 1 || _quantity! > 500
                  ? 'Enter 1–500 cards'
                  : null,
            ),
            const SizedBox(height: 12),
            if (_quantity != null && _quantity! > 0 && _quantity! <= 500)
              Text(
                'Total issued value: ${formatMoney(widget.group.initialValue * _quantity!)}',
              ),
          ],
        ),
      ),
    ),
    actions: [
      TextButton(
        onPressed: () => Navigator.pop(context),
        child: const Text('Cancel'),
      ),
      TextButton(
        onPressed: () {
          if (_form.currentState!.validate()) Navigator.pop(context, _quantity);
        },
        child: const Text('Generate'),
      ),
    ],
  );
}
