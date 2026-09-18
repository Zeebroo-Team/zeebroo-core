import 'package:dio/dio.dart' show FormData, MultipartFile;
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/app_button.dart';

/// Business settings — a focused subset of `/online/settings` (business
/// name, currency, currency position, timezone, logo). The full settings
/// object also covers receipts, tax, invoicing, branches and delivery, but
/// those stay desktop-only for now; this screen only edits the fields that
/// make sense on a phone.
class BusinessScreen extends StatefulWidget {
  const BusinessScreen({super.key});

  @override
  State<BusinessScreen> createState() => _BusinessScreenState();
}

class _BusinessScreenState extends State<BusinessScreen> {
  final _formKey = GlobalKey<FormState>();
  final _businessName = TextEditingController();
  final _currency = TextEditingController();
  final _timezone = TextEditingController();
  final _logoUrl = TextEditingController();
  String _currencyPosition = 'before';

  bool _loading = true;
  bool _saving = false;
  bool _uploadingLogo = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _businessName.dispose();
    _currency.dispose();
    _timezone.dispose();
    _logoUrl.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final res = await ApiClient.instance.get(ApiEndpoints.businessSettings);
      final data = res.data;
      final settings =
          (data is Map ? data['data'] : data) as Map<String, dynamic>?;
      _businessName.text = (settings?['business_name'] as String?) ?? '';
      _currency.text = (settings?['currency'] as String?) ?? '';
      _timezone.text = (settings?['timezone'] as String?) ?? '';
      _logoUrl.text = (settings?['business_logo_url'] as String?) ?? '';
      final position = settings?['currency_position'] as String?;
      _currencyPosition = position == 'after' ? 'after' : 'before';
    } catch (e) {
      _error = apiErrorMessage(e);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await ApiClient.instance.put(
        ApiEndpoints.businessSettings,
        data: {
          'business_name': _businessName.text.trim(),
          'currency': _currency.text.trim(),
          'currency_position': _currencyPosition,
          'timezone': _timezone.text.trim(),
        },
      );
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Business settings saved.')),
        );
      }
    } catch (e) {
      if (mounted) setState(() => _error = apiErrorMessage(e));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _pickAndUploadLogo() async {
    final picked = await ImagePicker().pickImage(
      source: ImageSource.gallery,
      maxWidth: 1024,
      maxHeight: 1024,
      imageQuality: 85,
    );
    if (picked == null) return;

    setState(() {
      _uploadingLogo = true;
      _error = null;
    });
    try {
      final bytes = await picked.readAsBytes();
      final form = FormData.fromMap({
        'logo': MultipartFile.fromBytes(bytes, filename: picked.name),
      });
      final res = await ApiClient.instance.postMultipart(
        ApiEndpoints.businessSettingsLogo,
        form,
      );
      final data = res.data;
      final settings =
          (data is Map ? data['data'] : data) as Map<String, dynamic>?;
      final url = settings?['business_logo_url'] as String?;
      if (mounted && url != null) setState(() => _logoUrl.text = url);
    } catch (e) {
      if (mounted) setState(() => _error = apiErrorMessage(e));
    } finally {
      if (mounted) setState(() => _uploadingLogo = false);
    }
  }

  Future<void> _removeLogo() async {
    setState(() {
      _uploadingLogo = true;
      _error = null;
    });
    try {
      await ApiClient.instance.put(
        ApiEndpoints.businessSettings,
        data: {'business_logo_url': ''},
      );
      if (mounted) setState(() => _logoUrl.clear());
    } catch (e) {
      if (mounted) setState(() => _error = apiErrorMessage(e));
    } finally {
      if (mounted) setState(() => _uploadingLogo = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: AppColors.surface,
    appBar: AppBar(
      backgroundColor: AppColors.surface,
      foregroundColor: AppColors.textDark,
      elevation: 0,
      centerTitle: true,
      title: const Text(
        'Business Settings',
        style: TextStyle(fontWeight: FontWeight.w700, fontSize: 17),
      ),
    ),
    body: _loading
        ? const Center(child: CircularProgressIndicator())
        : ListView(
            padding: const EdgeInsets.fromLTRB(20, 20, 20, 40),
            children: [
              if (_error != null && _businessName.text.isEmpty)
                _ErrorCard(message: _error!, onRetry: _load)
              else
                _buildForm(),
            ],
          ),
  );

  Widget _buildForm() => Form(
    key: _formKey,
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (_error != null) ...[
          _ErrorBanner(message: _error!),
          const SizedBox(height: 16),
        ],
        const _FieldLabel('Business name'),
        TextFormField(
          controller: _businessName,
          decoration: const InputDecoration(
            prefixIcon: Icon(Icons.storefront_outlined, size: 18),
          ),
          validator: (v) => (v == null || v.trim().isEmpty)
              ? 'Business name is required'
              : null,
        ),
        const SizedBox(height: 16),
        const _FieldLabel('Currency code'),
        TextFormField(
          controller: _currency,
          textCapitalization: TextCapitalization.characters,
          decoration: const InputDecoration(
            hintText: 'USD',
            prefixIcon: Icon(Icons.payments_outlined, size: 18),
          ),
        ),
        const SizedBox(height: 16),
        const _FieldLabel('Currency symbol position'),
        SegmentedButton<String>(
          segments: const [
            ButtonSegment(value: 'before', label: Text('Before (\$10)')),
            ButtonSegment(value: 'after', label: Text('After (10\$)')),
          ],
          selected: {_currencyPosition},
          onSelectionChanged: (s) =>
              setState(() => _currencyPosition = s.first),
        ),
        const SizedBox(height: 16),
        const _FieldLabel('Timezone'),
        TextFormField(
          controller: _timezone,
          decoration: const InputDecoration(
            hintText: 'Asia/Colombo',
            prefixIcon: Icon(Icons.public_outlined, size: 18),
          ),
        ),
        const SizedBox(height: 16),
        const _FieldLabel('Business logo'),
        _LogoPicker(
          logoUrl: _logoUrl.text,
          uploading: _uploadingLogo,
          onPick: _pickAndUploadLogo,
          onRemove: _logoUrl.text.isEmpty ? null : _removeLogo,
        ),
        const SizedBox(height: 24),
        AppButton(label: 'Save changes', onPressed: _save, loading: _saving),
      ],
    ),
  );
}

class _LogoPicker extends StatelessWidget {
  const _LogoPicker({
    required this.logoUrl,
    required this.uploading,
    required this.onPick,
    required this.onRemove,
  });

  final String logoUrl;
  final bool uploading;
  final VoidCallback onPick;
  final VoidCallback? onRemove;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Container(
        width: 64,
        height: 64,
        clipBehavior: Clip.antiAlias,
        decoration: BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: AppColors.border),
        ),
        child: logoUrl.isEmpty
            ? const Icon(
                Icons.storefront_outlined,
                color: AppColors.textMuted,
              )
            : Image.network(
                logoUrl,
                fit: BoxFit.cover,
                loadingBuilder: (context, child, progress) => progress == null
                    ? child
                    : const Center(
                        child: SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        ),
                      ),
                errorBuilder: (context, error, stackTrace) => const Icon(
                  Icons.broken_image_outlined,
                  color: AppColors.textMuted,
                ),
              ),
      ),
      const SizedBox(width: 12),
      Expanded(
        child: Wrap(
          spacing: 8,
          runSpacing: 4,
          crossAxisAlignment: WrapCrossAlignment.center,
          children: [
            OutlinedButton.icon(
              onPressed: uploading ? null : onPick,
              icon: uploading
                  ? const SizedBox(
                      width: 14,
                      height: 14,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.upload_outlined, size: 16),
              label: Text(logoUrl.isEmpty ? 'Upload logo' : 'Change logo'),
            ),
            if (onRemove != null)
              TextButton(
                onPressed: uploading ? null : onRemove,
                child: const Text('Remove'),
              ),
          ],
        ),
      ),
    ],
  );
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
      boxShadow: const [
        BoxShadow(
          color: AppColors.shadow,
          blurRadius: 16,
          offset: Offset(0, 4),
        ),
      ],
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          message,
          style: const TextStyle(color: AppColors.textMuted, fontSize: 13),
        ),
        const SizedBox(height: 10),
        TextButton.icon(
          onPressed: onRetry,
          icon: const Icon(Icons.refresh, size: 16),
          label: const Text('Retry'),
        ),
      ],
    ),
  );
}
