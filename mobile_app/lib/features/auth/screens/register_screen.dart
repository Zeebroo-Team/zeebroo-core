import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/auth/auth_state.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/step_dots.dart';

/// Sign-up + onboarding, combined into one short wizard:
///
///  0. Account   — name, email, password (the "sign up" step)
///  1. Business  — business name + industry (the "onboarding" step)
///  2. Done      — the mobile-only package (if any) was auto-installed and
///                 payment was skipped entirely; AuthState.registerAndFinish
///                 does both in a single API call. There is no feature-picker
///                 and no package-picker screen on mobile by design.
class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> with SingleTickerProviderStateMixin {
  int _step = 0;
  bool _loading = false;
  String? _error;

  // Step 0
  final _form0 = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _password = TextEditingController();
  final _confirm = TextEditingController();
  bool _obscure = true;
  bool _obscureC = true;

  // Step 1
  final _form1 = GlobalKey<FormState>();
  final _businessName = TextEditingController();
  // The business-type picker is hidden on mobile (see _buildStep1) — every
  // sign-up defaults to the "other" category, which the desktop/admin side
  // can refine later from the business profile.
  final String _businessCategory = 'other';

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _password.dispose();
    _confirm.dispose();
    _businessName.dispose();
    super.dispose();
  }

  void _goToStep(int step) {
    setState(() {
      _step = step;
      _error = null;
    });
  }

  void _submitStep0() {
    if (_form0.currentState!.validate()) _goToStep(1);
  }

  Future<void> _submit() async {
    if (!_form1.currentState!.validate()) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      await context.read<AuthState>().registerAndFinish(
            name: _name.text.trim(),
            email: _email.text.trim(),
            password: _password.text,
            businessName: _businessName.text.trim(),
            businessCategory: _businessCategory,
          );
      if (mounted) _goToStep(2);
      // AuthState is now authenticated → GoRouter redirect takes it to /home.
    } catch (e) {
      if (mounted) setState(() => _error = apiErrorMessage(e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    body: SafeArea(
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(8, 8, 8, 0),
            child: Row(
              children: [
                IconButton(
                  icon: const Icon(Icons.arrow_back),
                  onPressed: () => _step == 0 ? context.go('/login') : _goToStep(_step - 1),
                ),
                const Spacer(),
                if (_step < 2) StepDots(total: 2, current: _step),
                const Spacer(),
                const SizedBox(width: 48),
              ],
            ),
          ),
          Expanded(
            child: SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(24, 8, 24, 24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (_error != null) ...[
                    _ErrorBanner(message: _error!),
                    const SizedBox(height: 16),
                  ],
                  if (_step == 0) _buildStep0(),
                  if (_step == 1) _buildStep1(),
                  if (_step == 2) const _DoneStep(),
                ],
              ),
            ),
          ),
        ],
      ),
    ),
  );

  Widget _buildStep0() => Form(
    key: _form0,
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text('Create your account',
            style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800, color: AppColors.textDark)),
        const SizedBox(height: 4),
        const Text("Let's get you set up", style: TextStyle(fontSize: 13, color: AppColors.textMuted)),
        const SizedBox(height: 24),
        const _FieldLabel('Full name'),
        TextFormField(
          controller: _name,
          decoration: const InputDecoration(
            hintText: 'Jane Doe',
            prefixIcon: Icon(Icons.person_outline, size: 18),
          ),
          validator: (v) => (v == null || v.trim().isEmpty) ? 'Name is required' : null,
        ),
        const SizedBox(height: 16),
        const _FieldLabel('Email'),
        TextFormField(
          controller: _email,
          keyboardType: TextInputType.emailAddress,
          decoration: const InputDecoration(
            hintText: 'you@company.com',
            prefixIcon: Icon(Icons.mail_outline, size: 18),
          ),
          validator: (v) {
            if (v == null || v.trim().isEmpty) return 'Email is required';
            if (!RegExp(r'\S+@\S+\.\S+').hasMatch(v)) return 'Enter a valid email';
            return null;
          },
        ),
        const SizedBox(height: 16),
        const _FieldLabel('Password'),
        TextFormField(
          controller: _password,
          obscureText: _obscure,
          decoration: InputDecoration(
            hintText: 'Min. 8 characters',
            prefixIcon: const Icon(Icons.lock_outline, size: 18),
            suffixIcon: IconButton(
              icon: Icon(_obscure ? Icons.visibility_outlined : Icons.visibility_off_outlined, size: 18),
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
        const _FieldLabel('Confirm password'),
        TextFormField(
          controller: _confirm,
          obscureText: _obscureC,
          textInputAction: TextInputAction.done,
          onFieldSubmitted: (_) => _submitStep0(),
          decoration: InputDecoration(
            hintText: 'Repeat password',
            prefixIcon: const Icon(Icons.lock_outline, size: 18),
            suffixIcon: IconButton(
              icon:
                  Icon(_obscureC ? Icons.visibility_outlined : Icons.visibility_off_outlined, size: 18),
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
        AppButton(label: 'Continue', onPressed: _submitStep0, icon: Icons.arrow_forward_rounded),
        const SizedBox(height: 20),
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Text('Already have an account? ',
                style: TextStyle(color: AppColors.textMuted, fontSize: 14)),
            GestureDetector(
              onTap: () => context.go('/login'),
              child: const Text('Sign in',
                  style: TextStyle(
                      color: AppColors.textDark, fontWeight: FontWeight.w700, fontSize: 14)),
            ),
          ],
        ),
      ],
    ),
  );

  Widget _buildStep1() => Form(
    key: _form1,
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text('Tell us about your business',
            style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800, color: AppColors.textDark)),
        const SizedBox(height: 4),
        const Text('This becomes your business profile',
            style: TextStyle(fontSize: 13, color: AppColors.textMuted)),
        const SizedBox(height: 24),
        const _FieldLabel('Business name'),
        TextFormField(
          controller: _businessName,
          textInputAction: TextInputAction.done,
          onFieldSubmitted: (_) => _submit(),
          decoration: const InputDecoration(
            hintText: 'Acme Store',
            prefixIcon: Icon(Icons.storefront_outlined, size: 18),
          ),
          validator: (v) => (v == null || v.trim().isEmpty) ? 'Business name is required' : null,
        ),
        const SizedBox(height: 28),
        AppButton(label: 'Create account', onPressed: _submit, loading: _loading),
      ],
    ),
  );
}

class _DoneStep extends StatelessWidget {
  const _DoneStep();

  @override
  Widget build(BuildContext context) {
    final plan = context.watch<AuthState>().lastInstalledPackageName;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 40),
      child: Column(
        children: [
          Container(
            width: 88,
            height: 88,
            decoration: const BoxDecoration(shape: BoxShape.circle, color: AppColors.success),
            child: const Icon(Icons.check_rounded, size: 48, color: Colors.white),
          ),
          const SizedBox(height: 28),
          const Text("You're all set!",
              style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800, color: AppColors.textDark)),
          const SizedBox(height: 8),
          Text(
            plan != null
                ? '$plan is active on your account — no payment needed.'
                : 'Taking you to your dashboard…',
            textAlign: TextAlign.center,
            style: const TextStyle(fontSize: 14, color: AppColors.textMuted),
          ),
          const SizedBox(height: 28),
          const CircularProgressIndicator(strokeWidth: 2.5),
        ],
      ),
    );
  }
}

class _FieldLabel extends StatelessWidget {
  const _FieldLabel(this.text);
  final String text;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 6),
    child: Text(text,
        style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: AppColors.textMid)),
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
        Expanded(child: Text(message, style: const TextStyle(color: AppColors.error, fontSize: 13))),
      ],
    ),
  );
}
