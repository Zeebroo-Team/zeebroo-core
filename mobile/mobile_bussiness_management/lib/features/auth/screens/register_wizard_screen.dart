import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/auth/auth_state.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/gradient_header.dart';
import '../widgets/step_indicator.dart';

const _steps = ['Account', 'Business', 'Done'];

class RegisterWizardScreen extends StatefulWidget {
  const RegisterWizardScreen({super.key});

  @override
  State<RegisterWizardScreen> createState() => _RegisterWizardScreenState();
}

class _RegisterWizardScreenState extends State<RegisterWizardScreen>
    with SingleTickerProviderStateMixin {
  int _step = 0;
  bool _loading = false;
  String? _error;

  // Step 0 — Account (local validation only, no API call yet)
  final _form0    = GlobalKey<FormState>();
  final _name     = TextEditingController();
  final _email    = TextEditingController();
  final _password = TextEditingController();
  final _confirm  = TextEditingController();
  bool _obscure   = true;
  bool _obscureC  = true;

  // Step 1 — Business (submitted together with step 0 data)
  final _form1      = GlobalKey<FormState>();
  final _bizName    = TextEditingController();
  String? _bizCategory; // slug value from API
  List<Map<String, dynamic>> _categories = [];
  bool _loadingCats = false;

  late final AnimationController _anim = AnimationController(
    vsync: this, duration: const Duration(milliseconds: 220),
  )..forward();
  late Animation<Offset> _slide = Tween<Offset>(
    begin: const Offset(0.06, 0), end: Offset.zero,
  ).animate(CurvedAnimation(parent: _anim, curve: Curves.easeOut));

  @override
  void initState() {
    super.initState();
    _loadCategories();
  }

  @override
  void dispose() {
    _anim.dispose();
    _name.dispose(); _email.dispose(); _password.dispose(); _confirm.dispose();
    _bizName.dispose();
    super.dispose();
  }

  // Fetch /v1/pos/auth/business-categories → { data: [{value, label}] }
  Future<void> _loadCategories() async {
    setState(() => _loadingCats = true);
    try {
      final res = await ApiClient.instance.get(ApiEndpoints.businessCategories);
      final data = res.data;
      List raw = [];
      if (data is Map) raw = (data['data'] ?? []) as List;
      if (data is List) raw = data;
      if (mounted) setState(() => _categories = raw.cast<Map<String, dynamic>>());
    } catch (_) { /* non-fatal */ }
    finally { if (mounted) setState(() => _loadingCats = false); }
  }

  void _animateTo(int next) {
    final dir = next > _step ? 0.06 : -0.06;
    _anim.reset();
    setState(() { _step = next; _error = null; });
    _slide = Tween<Offset>(begin: Offset(dir, 0), end: Offset.zero)
        .animate(CurvedAnimation(parent: _anim, curve: Curves.easeOut));
    _anim.forward();
  }

  // Step 0 — just validate locally and advance
  void _nextStep0() {
    if (_form0.currentState!.validate()) _animateTo(1);
  }

  // Step 1 — submit everything to /v1/pos/auth/register
  Future<void> _submit() async {
    if (!_form1.currentState!.validate()) return;
    if (_bizCategory == null) {
      setState(() => _error = 'Please select a business type');
      return;
    }
    setState(() { _loading = true; _error = null; });
    try {
      await context.read<AuthState>().registerAndFinish({
        'name':              _name.text.trim(),
        'email':             _email.text.trim().toLowerCase(),
        'password':          _password.text,
        'password_confirmation': _confirm.text,
        'business_name':     _bizName.text.trim(),
        'business_category': _bizCategory,   // slug e.g. "retail"
      });
      if (mounted) _animateTo(2);
      // AuthState notifies → GoRouter redirect takes over automatically
    } catch (e) {
      if (mounted) setState(() => _error = apiErrorMessage(e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: AppColors.surface,
    body: SafeArea(
      top: false,
      child: Column(
        children: [
          GradientHeader(
            leading: IconButton(
              icon: const Icon(Icons.arrow_back, color: Colors.white),
              onPressed: () => _step == 0 ? context.go('/login') : _animateTo(_step - 1),
            ),
            title: 'Create Account',
            child: StepIndicator(steps: _steps, current: _step),
          ),
          Expanded(
            child: SlideTransition(
              position: _slide,
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(24),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const SizedBox(height: 4),
                    if (_error != null) ...[
                      _ErrorBanner(message: _error!),
                      const SizedBox(height: 16),
                    ],
                    if (_step == 0) _buildStep0(),
                    if (_step == 1) _buildStep1(),
                    if (_step == 2) const _StepDone(),
                    const SizedBox(height: 32),
                    if (_step == 0) _loginHint(context),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    ),
  );

  // ─── Step 0 — Account info ─────────────────────────────────────────────────
  Widget _buildStep0() => Form(
    key: _form0,
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text('Your account', style: TextStyle(
            fontSize: 20, fontWeight: FontWeight.w700, color: AppColors.textDark)),
        const SizedBox(height: 4),
        const Text("You'll use these to sign in",
            style: TextStyle(fontSize: 13, color: AppColors.textMuted)),
        const SizedBox(height: 24),

        _label('Full Name'),
        TextFormField(
          controller: _name,
          decoration: const InputDecoration(
            hintText: 'Jane Doe',
            prefixIcon: Icon(Icons.person_outline, size: 18, color: AppColors.textHint),
          ),
          validator: (v) => (v == null || v.trim().isEmpty) ? 'Name is required' : null,
        ),
        const SizedBox(height: 16),

        _label('Email'),
        TextFormField(
          controller: _email,
          keyboardType: TextInputType.emailAddress,
          decoration: const InputDecoration(
            hintText: 'you@company.com',
            prefixIcon: Icon(Icons.mail_outline, size: 18, color: AppColors.textHint),
          ),
          validator: (v) {
            if (v == null || v.trim().isEmpty) return 'Email is required';
            if (!RegExp(r'\S+@\S+\.\S+').hasMatch(v)) return 'Enter a valid email';
            return null;
          },
        ),
        const SizedBox(height: 16),

        _label('Password'),
        TextFormField(
          controller: _password,
          obscureText: _obscure,
          decoration: InputDecoration(
            hintText: 'Min. 8 characters',
            prefixIcon: const Icon(Icons.lock_outline, size: 18, color: AppColors.textHint),
            suffixIcon: IconButton(
              icon: Icon(_obscure ? Icons.visibility_outlined : Icons.visibility_off_outlined,
                  size: 18, color: AppColors.textHint),
              onPressed: () => setState(() => _obscure = !_obscure),
            ),
          ),
          validator: (v) {
            if (v == null || v.isEmpty) return 'Password is required';
            if (v.length < 8) return 'Minimum 8 characters';
            return null;
          },
        ),
        const SizedBox(height: 16),

        _label('Confirm Password'),
        TextFormField(
          controller: _confirm,
          obscureText: _obscureC,
          textInputAction: TextInputAction.done,
          onFieldSubmitted: (_) => _nextStep0(),
          decoration: InputDecoration(
            hintText: 'Repeat password',
            prefixIcon: const Icon(Icons.lock_outline, size: 18, color: AppColors.textHint),
            suffixIcon: IconButton(
              icon: Icon(_obscureC ? Icons.visibility_outlined : Icons.visibility_off_outlined,
                  size: 18, color: AppColors.textHint),
              onPressed: () => setState(() => _obscureC = !_obscureC),
            ),
          ),
          validator: (v) {
            if (v == null || v.isEmpty) return 'Please confirm your password';
            if (v != _password.text) return 'Passwords do not match';
            return null;
          },
        ),
        const SizedBox(height: 28),

        AppButton(
          label: 'Continue',
          onPressed: _nextStep0,
          icon: Icons.arrow_forward_rounded,
        ),
      ],
    ),
  );

  // ─── Step 1 — Business info (submitted to API) ────────────────────────────
  static const _bizIcons = <String, IconData>{
    'retail':      Icons.shopping_bag_outlined,
    'restaurant':  Icons.restaurant_outlined,
    'services':    Icons.construction_outlined,
    'wholesale':   Icons.inventory_2_outlined,
    'healthcare':  Icons.medical_services_outlined,
    'education':   Icons.school_outlined,
    'technology':  Icons.computer_outlined,
    'other':       Icons.more_horiz,
  };

  Widget _buildStep1() => Form(
    key: _form1,
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text('Your business', style: TextStyle(
            fontSize: 20, fontWeight: FontWeight.w700, color: AppColors.textDark)),
        const SizedBox(height: 4),
        const Text("Almost there — tell us about your business",
            style: TextStyle(fontSize: 13, color: AppColors.textMuted)),
        const SizedBox(height: 24),

        // Business type chips from API
        _label('Business Type'),
        if (_loadingCats)
          const Center(child: Padding(
            padding: EdgeInsets.symmetric(vertical: 12),
            child: CircularProgressIndicator(strokeWidth: 2),
          ))
        else if (_categories.isEmpty)
          // Fallback manual entry if categories failed to load
          TextFormField(
            decoration: const InputDecoration(
              hintText: 'e.g. Retail, Restaurant, Services…',
              prefixIcon: Icon(Icons.business_outlined, size: 18, color: AppColors.textHint),
            ),
            onChanged: (v) => _bizCategory = v.trim().toLowerCase(),
            validator: (v) => (v == null || v.trim().isEmpty) ? 'Business type is required' : null,
          )
        else
          Wrap(
            spacing: 10, runSpacing: 10,
            children: _categories.map((cat) {
              // API returns { value: "retail", label: "Retail" }
              final slug  = cat['value']?.toString() ?? '';
              final label = cat['label']?.toString() ?? '';
              final sel   = _bizCategory == slug;
              return GestureDetector(
                onTap: () => setState(() { _bizCategory = slug; _error = null; }),
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 180),
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 9),
                  decoration: BoxDecoration(
                    color: sel ? AppColors.primary : Colors.white,
                    border: Border.all(
                        color: sel ? AppColors.primary : AppColors.border, width: 1.5),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(_bizIcons[slug] ?? Icons.business_outlined,
                          size: 18, color: sel ? Colors.white : AppColors.primary),
                      const SizedBox(width: 6),
                      Text(label, style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600,
                          color: sel ? Colors.white : AppColors.primary)),
                    ],
                  ),
                ),
              );
            }).toList(),
          ),
        const SizedBox(height: 20),

        _label('Business Name'),
        TextFormField(
          controller: _bizName,
          textInputAction: TextInputAction.done,
          onFieldSubmitted: (_) => _submit(),
          decoration: const InputDecoration(
            hintText: 'Acme Store',
            prefixIcon: Icon(Icons.storefront_outlined, size: 18, color: AppColors.textHint),
          ),
          validator: (v) => (v == null || v.trim().isEmpty) ? 'Business name is required' : null,
        ),
        const SizedBox(height: 28),

        Row(
          children: [
            Expanded(
              child: OutlinedButton.icon(
                onPressed: () => _animateTo(0),
                icon: const Icon(Icons.arrow_back, size: 16),
                label: const Text('Back'),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              flex: 2,
              child: AppButton(
                label: 'Create Account',
                onPressed: _submit,
                loading: _loading,
              ),
            ),
          ],
        ),
      ],
    ),
  );

  // ─── Helpers ───────────────────────────────────────────────────────────────
  Widget _label(String text) => Padding(
    padding: const EdgeInsets.only(bottom: 6),
    child: Text(text, style: const TextStyle(
        fontSize: 13, fontWeight: FontWeight.w600, color: AppColors.textMid)),
  );

  Widget _loginHint(BuildContext ctx) => Row(
    mainAxisAlignment: MainAxisAlignment.center,
    children: [
      const Text('Already have an account? ',
          style: TextStyle(color: AppColors.textMuted, fontSize: 14)),
      GestureDetector(
        onTap: () => ctx.go('/login'),
        child: const Text('Sign in', style: TextStyle(
            color: AppColors.primary, fontWeight: FontWeight.w600, fontSize: 14)),
      ),
    ],
  );
}

// ─── Step Done ────────────────────────────────────────────────────────────────
class _StepDone extends StatelessWidget {
  const _StepDone();

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.symmetric(vertical: 40),
      child: Column(
        children: [
          Container(
            width: 96, height: 96,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: AppColors.primary,
              boxShadow: [BoxShadow(
                color: AppColors.primary.withValues(alpha: 0.35),
                blurRadius: 24, offset: const Offset(0, 8),
              )],
            ),
            child: const Icon(Icons.check_rounded, size: 52, color: Colors.white),
          ),
          const SizedBox(height: 28),
          const Text("You're all set!", style: TextStyle(
              fontSize: 24, fontWeight: FontWeight.w800, color: AppColors.textDark)),
          const SizedBox(height: 8),
          const Text('Taking you to your dashboard…',
              style: TextStyle(fontSize: 15, color: AppColors.textMuted)),
          const SizedBox(height: 28),
          const CircularProgressIndicator(color: AppColors.primary, strokeWidth: 2.5),
        ],
      ),
    ),
  );
}

// ─── Error banner ─────────────────────────────────────────────────────────────
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
        Expanded(child: Text(message,
            style: const TextStyle(color: AppColors.error, fontSize: 13))),
      ],
    ),
  );
}
