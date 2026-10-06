import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../core/api/api_client.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/money.dart';
import '../data/gift_card_repository.dart';

class GiftCardFormScreen extends StatefulWidget {
  const GiftCardFormScreen({
    super.key,
    this.existing,
    this.repository = const GiftCardRepository(),
  });
  final GiftCardRecord? existing;
  final GiftCardRepository repository;
  @override
  State<GiftCardFormScreen> createState() => _GiftCardFormScreenState();
}

class _GiftCardFormScreenState extends State<GiftCardFormScreen> {
  final _form = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _value = TextEditingController();
  final _quantity = TextEditingController(text: '1');
  final _code = TextEditingController();
  final _notes = TextEditingController();
  DateTime? _from;
  DateTime? _until;
  bool _noExpiry = false;
  bool _active = true;
  bool _saving = false;
  bool _generating = false;
  String? _error;
  bool get _groupEdit => widget.existing?.isGroup == true;
  bool get _cardEdit => widget.existing != null && !_groupEdit;
  bool get _useCode =>
      _cardEdit || (!_groupEdit && int.tryParse(_quantity.text) == 1);

  @override
  void initState() {
    super.initState();
    final record = widget.existing;
    _name.text = record?.name ?? '';
    _value.text = record?.initialValue.toString() ?? '';
    _code.text = record?.code ?? '';
    _notes.text = record?.notes ?? '';
    _from = record == null
        ? DateUtils.dateOnly(DateTime.now())
        : record.validFrom;
    _until = record?.expiresAt;
    _noExpiry = record != null && _until == null;
    _active = record?.isActive ?? true;
    if (record == null) _generate();
  }

  @override
  void dispose() {
    for (final controller in [_name, _value, _quantity, _code, _notes]) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _generate() async {
    if (_generating || _saving) return;
    setState(() {
      _generating = true;
      _error = null;
    });
    try {
      final code = await widget.repository.generateCode();
      if (mounted) _code.text = code;
    } catch (error) {
      if (mounted) setState(() => _error = apiErrorMessage(error));
    } finally {
      if (mounted) setState(() => _generating = false);
    }
  }

  Future<void> _pickDate(bool until) async {
    final date = await showDatePicker(
      context: context,
      initialDate: (until ? _until ?? _from : _from) ?? DateTime.now(),
      firstDate: DateTime(1900),
      lastDate: DateTime(2100, 12, 31),
    );
    if (!mounted || date == null) return;
    setState(() {
      if (until) {
        _until = date;
      } else {
        _from = date;
      }
    });
  }

  Future<void> _save() async {
    if (_saving || _generating || !_form.currentState!.validate()) return;
    FocusScope.of(context).unfocus();
    setState(() {
      _saving = true;
      _error = null;
    });
    final data = <String, dynamic>{
      if (!_cardEdit) 'name': _name.text.trim(),
      if (!_groupEdit) 'initial_value': double.parse(_value.text.trim()),
      if (widget.existing == null) 'quantity': int.parse(_quantity.text),
      if (_useCode) 'code': _code.text.trim().toUpperCase(),
      'valid_from': _from == null ? null : giftCardDate(_from!),
      'expires_at': _noExpiry || _until == null ? null : giftCardDate(_until!),
      'notes': _notes.text.trim().isEmpty ? null : _notes.text.trim(),
      'is_active': _active,
    };
    try {
      final saved = await widget.repository.save(
        data,
        existing: widget.existing,
      );
      if (mounted) Navigator.of(context).pop(saved);
    } catch (error) {
      if (mounted) setState(() => _error = apiErrorMessage(error));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  String? _validateValue(String? text) {
    final value = double.tryParse(text ?? '');
    if (value == null || !value.isFinite || value < 0.01 || value > 99999999) {
      return 'Enter a value from 0.01 to 99,999,999';
    }
    if (_cardEdit && value + 0.005 < widget.existing!.usedAmount) {
      return 'Cannot be below ${formatMoney(widget.existing!.usedAmount)} already spent';
    }
    return null;
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: AppColors.surface,
    appBar: AppBar(
      centerTitle: true,
      title: Text(
        _groupEdit
            ? 'Edit Gift Card Group'
            : _cardEdit
            ? 'Edit Gift Card'
            : 'New Gift Card',
      ),
    ),
    body: SafeArea(
      child: Form(
        key: _form,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
          children: [
            AbsorbPointer(
              absorbing: _saving,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  if (!_cardEdit) ...[
                    TextFormField(
                      key: const ValueKey('gift-name'),
                      controller: _name,
                      maxLength: 191,
                      textCapitalization: TextCapitalization.sentences,
                      decoration: const InputDecoration(
                        labelText: 'Gift card name *',
                        hintText: 'e.g. Birthday Gift Card',
                      ),
                      validator: (text) => text?.trim().isNotEmpty == true
                          ? null
                          : 'Gift card name is required',
                    ),
                    const SizedBox(height: 14),
                  ],
                  if (_groupEdit) ...[
                    Text(
                      'New cards in this group are worth ${formatMoney(widget.existing!.initialValue)} each.',
                      style: const TextStyle(
                        color: AppColors.textMuted,
                        fontSize: 13,
                      ),
                    ),
                    const SizedBox(height: 10),
                    const Text(
                      'Name, dates and Active changes apply to every card in this group.',
                      style: TextStyle(
                        color: AppColors.textMuted,
                        fontSize: 12,
                      ),
                    ),
                    const SizedBox(height: 18),
                  ],
                  if (!_groupEdit) ...[
                    TextFormField(
                      key: const ValueKey('gift-value'),
                      controller: _value,
                      keyboardType: const TextInputType.numberWithOptions(
                        decimal: true,
                      ),
                      inputFormatters: [
                        TextInputFormatter.withFunction(
                          (oldValue, newValue) =>
                              RegExp(r'^\d*\.?\d{0,2}$').hasMatch(newValue.text)
                              ? newValue
                              : oldValue,
                        ),
                      ],
                      decoration: InputDecoration(
                        labelText: _cardEdit
                            ? 'Card value *'
                            : 'Value per card *',
                      ),
                      validator: _validateValue,
                    ),
                    if (_cardEdit) ...[
                      const SizedBox(height: 8),
                      Text(
                        '${formatMoney(widget.existing!.usedAmount)} already spent. Changing the value adjusts the remaining balance by the same amount.',
                        style: const TextStyle(
                          color: AppColors.textMuted,
                          fontSize: 12,
                        ),
                      ),
                    ],
                    const SizedBox(height: 18),
                  ],
                  if (widget.existing == null) ...[
                    TextFormField(
                      key: const ValueKey('gift-quantity'),
                      controller: _quantity,
                      keyboardType: TextInputType.number,
                      inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                      onChanged: (_) => setState(() {}),
                      decoration: const InputDecoration(
                        labelText: 'How many cards? *',
                        helperText:
                            'Generate 1–500 cards, each with a unique code.',
                      ),
                      validator: (text) {
                        final quantity = int.tryParse(text ?? '');
                        return quantity == null ||
                                quantity < 1 ||
                                quantity > 500
                            ? 'Enter a whole number from 1 to 500'
                            : null;
                      },
                    ),
                    const SizedBox(height: 18),
                  ],
                  if (_useCode) ...[
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: TextFormField(
                            key: const ValueKey('gift-code'),
                            controller: _code,
                            maxLength: 40,
                            enabled: !_generating,
                            textCapitalization: TextCapitalization.characters,
                            decoration: const InputDecoration(
                              labelText: 'Gift card code *',
                            ),
                            validator: (text) =>
                                RegExp(
                                  r'^[A-Za-z0-9\- ]{4,40}$',
                                ).hasMatch(text?.trim() ?? '')
                                ? null
                                : 'Use 4–40 letters, numbers or dashes',
                          ),
                        ),
                        const SizedBox(width: 8),
                        SizedBox(
                          width: 102,
                          height: 52,
                          child: OutlinedButton(
                            style: OutlinedButton.styleFrom(
                              minimumSize: const Size(0, 52),
                              padding: const EdgeInsets.symmetric(
                                horizontal: 6,
                              ),
                            ),
                            onPressed: _generating ? null : _generate,
                            child: _generating
                                ? const SizedBox(
                                    width: 18,
                                    height: 18,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                    ),
                                  )
                                : const Text(
                                    'Generate',
                                    style: TextStyle(fontSize: 13),
                                  ),
                          ),
                        ),
                      ],
                    ),
                    const Text(
                      'Every gift card has its own unique code.',
                      style: TextStyle(
                        fontSize: 12,
                        color: AppColors.textMuted,
                      ),
                    ),
                    const SizedBox(height: 12),
                  ] else if (widget.existing == null) ...[
                    const Text(
                      'Unique codes will be generated automatically for every card in this batch.',
                      style: TextStyle(
                        fontSize: 12,
                        color: AppColors.textMuted,
                      ),
                    ),
                    const SizedBox(height: 12),
                  ],
                  CheckboxListTile(
                    contentPadding: EdgeInsets.zero,
                    controlAffinity: ListTileControlAffinity.leading,
                    title: const Text('No expiry date'),
                    value: _noExpiry,
                    onChanged: (value) =>
                        setState(() => _noExpiry = value ?? false),
                  ),
                  _dateField(false),
                  if (!_noExpiry) ...[
                    const SizedBox(height: 14),
                    _dateField(true),
                  ],
                  const SizedBox(height: 18),
                  TextFormField(
                    key: const ValueKey('gift-notes'),
                    controller: _notes,
                    maxLength: 2000,
                    maxLines: 3,
                    decoration: const InputDecoration(
                      labelText: 'Notes (optional)',
                      hintText: 'Who is this gift card for?',
                    ),
                  ),
                  SwitchListTile.adaptive(
                    contentPadding: EdgeInsets.zero,
                    title: const Text('Active'),
                    value: _active,
                    onChanged: (value) => setState(() => _active = value),
                  ),
                ],
              ),
            ),
            if (_error != null) ...[
              const SizedBox(height: 12),
              Text(_error!, style: const TextStyle(color: AppColors.error)),
            ],
            const SizedBox(height: 16),
            ElevatedButton.icon(
              key: const ValueKey('save-gift'),
              onPressed: _saving || _generating ? null : _save,
              icon: _saving
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(
                        color: Colors.white,
                        strokeWidth: 2,
                      ),
                    )
                  : const Icon(Icons.check_rounded),
              label: Text(
                _saving
                    ? 'Saving...'
                    : _groupEdit
                    ? 'Save Group'
                    : 'Save Gift Card',
              ),
            ),
            const SizedBox(height: 8),
            TextButton(
              onPressed: _saving ? null : () => Navigator.of(context).pop(),
              child: const Text('Cancel'),
            ),
          ],
        ),
      ),
    ),
  );

  Widget _dateField(bool until) {
    final date = until ? _until : _from;
    return FormField<DateTime>(
      key: ValueKey('${until ? 'until' : 'from'}-$date'),
      initialValue: date,
      validator: (_) {
        if (!until || _noExpiry) return null;
        if (_until == null) {
          return 'Choose an end date or select No expiry date';
        }
        if (_from != null && _until!.isBefore(_from!)) {
          return 'End date cannot be before start date';
        }
        return null;
      },
      builder: (field) => InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: () => _pickDate(until),
        child: InputDecorator(
          decoration: InputDecoration(
            labelText: until ? 'Valid until *' : 'Valid from (optional)',
            errorText: field.errorText,
            suffixIcon: !until && date != null
                ? IconButton(
                    tooltip: 'Clear start date',
                    onPressed: () => setState(() => _from = null),
                    icon: const Icon(Icons.close_rounded),
                  )
                : const Icon(Icons.calendar_today_outlined, size: 20),
          ),
          child: Text(date == null ? 'Select date' : giftCardDate(date)),
        ),
      ),
    );
  }
}
