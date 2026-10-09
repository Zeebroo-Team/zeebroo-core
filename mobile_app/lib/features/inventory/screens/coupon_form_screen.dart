import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../core/api/api_client.dart';
import '../../../core/theme/app_theme.dart';
import '../data/coupon_repository.dart';

class CouponFormScreen extends StatefulWidget {
  const CouponFormScreen({
    super.key,
    this.existing,
    this.repository = const CouponRepository(),
  });

  final CouponData? existing;
  final CouponRepository repository;

  @override
  State<CouponFormScreen> createState() => _CouponFormScreenState();
}

class _CouponFormScreenState extends State<CouponFormScreen> {
  final _form = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _value = TextEditingController();
  final _code = TextEditingController();
  final _quantity = TextEditingController();
  final _notes = TextEditingController();
  String _type = 'percent';
  DateTime? _from;
  DateTime? _until;
  bool _noExpiry = false;
  bool _active = true;
  bool _saving = false;
  bool _generating = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    final existing = widget.existing;
    _name.text = existing?.name ?? '';
    _value.text = existing == null ? '10' : existing.discountValue.toString();
    _code.text = existing?.code ?? '';
    _quantity.text = existing?.quantity.toString() ?? '100';
    _notes.text = existing?.notes ?? '';
    _type = existing?.discountType ?? 'percent';
    _from = existing == null
        ? DateUtils.dateOnly(DateTime.now())
        : existing.validFrom;
    _until = existing?.expiresAt;
    _noExpiry = existing != null && existing.expiresAt == null;
    _active = existing?.isActive ?? true;
  }

  @override
  void dispose() {
    for (final controller in [_name, _value, _code, _quantity, _notes]) {
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

  Future<void> _pickDate(bool isUntil) async {
    final selected = await showDatePicker(
      context: context,
      initialDate: (isUntil ? _until ?? _from : _from) ?? DateTime.now(),
      firstDate: DateTime(1900),
      lastDate: DateTime(2100, 12, 31),
    );
    if (!mounted || selected == null) return;
    setState(() {
      if (isUntil) {
        _until = selected;
      } else {
        _from = selected;
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
      'name': _name.text.trim(),
      'code': _code.text.trim().toUpperCase(),
      'discount_type': _type,
      'discount_value': double.parse(_value.text.trim()),
      'quantity': int.parse(_quantity.text.trim()),
      'valid_from': _from == null ? null : couponDate(_from!),
      'expires_at': _noExpiry || _until == null ? null : couponDate(_until!),
      'notes': _notes.text.trim().isEmpty ? null : _notes.text.trim(),
      'is_active': _active,
    };
    try {
      final saved = await widget.repository.save(data, id: widget.existing?.id);
      if (mounted) Navigator.of(context).pop(saved);
    } catch (error) {
      if (mounted) setState(() => _error = apiErrorMessage(error));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  String? _validateValue(String? text) {
    final value = double.tryParse(text?.trim() ?? '');
    if (value == null || !value.isFinite || value < 0.01) {
      return 'Discount must be at least 0.01';
    }
    if (_type == 'percent' && value > 100) {
      return 'Percentage cannot exceed 100%';
    }
    if (value > 99999999) return 'Discount value is too large';
    return null;
  }

  String? _validateQuantity(String? text) {
    final value = int.tryParse(text?.trim() ?? '');
    if (value == null || value < 1 || value > 100000) {
      return 'Enter a whole number from 1 to 100,000';
    }
    final used = widget.existing?.usedCount ?? 0;
    if (value < used) return 'Cannot be below the $used uses already redeemed';
    return null;
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: AppColors.surface,
    appBar: AppBar(
      backgroundColor: AppColors.surface,
      foregroundColor: AppColors.textDark,
      centerTitle: true,
      title: Text(widget.existing == null ? 'New Coupon' : 'Edit Coupon'),
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
                  TextFormField(
                    key: const ValueKey('coupon-name'),
                    controller: _name,
                    textCapitalization: TextCapitalization.sentences,
                    maxLength: 191,
                    decoration: const InputDecoration(
                      labelText: 'Coupon name *',
                      hintText: 'e.g. Avurudu 10% OFF',
                    ),
                    validator: (text) => text?.trim().isNotEmpty == true
                        ? null
                        : 'Coupon name is required',
                  ),
                  const SizedBox(height: 12),
                  const Text(
                    'Discount *',
                    style: TextStyle(fontWeight: FontWeight.w600),
                  ),
                  const SizedBox(height: 8),
                  SegmentedButton<String>(
                    segments: const [
                      ButtonSegment(value: 'percent', label: Text('%')),
                      ButtonSegment(value: 'flat', label: Text('Flat')),
                    ],
                    selected: {_type},
                    onSelectionChanged: (selection) =>
                        setState(() => _type = selection.first),
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    key: const ValueKey('coupon-value'),
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
                      labelText: _type == 'percent'
                          ? 'Percentage *'
                          : 'Discount amount *',
                      suffixText: _type == 'percent' ? '%' : null,
                    ),
                    validator: _validateValue,
                  ),
                  const SizedBox(height: 6),
                  const Text(
                    'Discount off the bill after any order discount.',
                    style: TextStyle(fontSize: 12, color: AppColors.textMuted),
                  ),
                  const SizedBox(height: 18),
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: TextFormField(
                          key: const ValueKey('coupon-code'),
                          controller: _code,
                          enabled: !_generating,
                          maxLength: 40,
                          textCapitalization: TextCapitalization.characters,
                          decoration: const InputDecoration(
                            labelText: 'Coupon code *',
                          ),
                          validator: (text) =>
                              RegExp(
                                r'^[A-Za-z0-9\- ]{3,40}$',
                              ).hasMatch(text?.trim() ?? '')
                              ? null
                              : 'Use 3–40 letters, numbers or dashes',
                        ),
                      ),
                      const SizedBox(width: 8),
                      SizedBox(
                        width: 102,
                        height: 52,
                        child: OutlinedButton(
                          style: OutlinedButton.styleFrom(
                            minimumSize: const Size(0, 52),
                            padding: const EdgeInsets.symmetric(horizontal: 6),
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
                    'Every customer uses the same code.',
                    style: TextStyle(fontSize: 12, color: AppColors.textMuted),
                  ),
                  const SizedBox(height: 18),
                  TextFormField(
                    key: const ValueKey('coupon-quantity'),
                    controller: _quantity,
                    keyboardType: TextInputType.number,
                    inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                    decoration: const InputDecoration(
                      labelText: 'Number of coupons *',
                      helperText: 'Total number of times the code can be used.',
                    ),
                    validator: _validateQuantity,
                  ),
                  const SizedBox(height: 12),
                  CheckboxListTile(
                    contentPadding: EdgeInsets.zero,
                    controlAffinity: ListTileControlAffinity.leading,
                    title: const Text('No expiry date'),
                    value: _noExpiry,
                    onChanged: (value) =>
                        setState(() => _noExpiry = value ?? false),
                  ),
                  _dateField(isUntil: false),
                  if (!_noExpiry) ...[
                    const SizedBox(height: 14),
                    _dateField(isUntil: true),
                  ],
                  const SizedBox(height: 18),
                  TextFormField(
                    controller: _notes,
                    maxLength: 2000,
                    maxLines: 3,
                    decoration: const InputDecoration(
                      labelText: 'Notes (optional)',
                      hintText: 'Where is this coupon promoted?',
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
              key: const ValueKey('save-coupon'),
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
              label: Text(_saving ? 'Saving...' : 'Save Coupon'),
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

  Widget _dateField({required bool isUntil}) {
    final date = isUntil ? _until : _from;
    return FormField<DateTime>(
      key: ValueKey('${isUntil ? 'until' : 'from'}-$date'),
      initialValue: date,
      validator: (_) {
        if (!isUntil || _noExpiry) return null;
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
        onTap: () => _pickDate(isUntil),
        child: InputDecorator(
          decoration: InputDecoration(
            labelText: isUntil ? 'Valid until *' : 'Valid from (optional)',
            errorText: field.errorText,
            suffixIcon: !isUntil && date != null
                ? IconButton(
                    tooltip: 'Clear start date',
                    onPressed: () => setState(() => _from = null),
                    icon: const Icon(Icons.close_rounded),
                  )
                : const Icon(Icons.calendar_today_outlined, size: 20),
          ),
          child: Text(date == null ? 'Select date' : couponDate(date)),
        ),
      ),
    );
  }
}
