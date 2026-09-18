import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/auth/auth_state.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/app_button.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _email = TextEditingController();
  final _password = TextEditingController();
  bool _obscure = true;
  bool _loading = false;
  String? _error;

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      await context.read<AuthState>().login(_email.text.trim(), _password.text);
      // AuthState notifies → GoRouter redirect takes over from here.
    } catch (e) {
      if (mounted) setState(() => _error = apiErrorMessage(e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    body: SafeArea(
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const SizedBox(height: 24),
              Center(
                child: Image.asset('assets/images/logo.png', height: 64),
              ),
              const SizedBox(height: 20),
              const Center(
                child: Text('Sign in to continue',
                    style: TextStyle(fontSize: 15, color: AppColors.primaryDk)),
              ),
              const SizedBox(height: 32),
              if (_error != null) ...[
                _ErrorBanner(message: _error!),
                const SizedBox(height: 16),
              ],
              const _FieldLabel('Email'),
              TextFormField(
                controller: _email,
                keyboardType: TextInputType.emailAddress,
                autofillHints: const [AutofillHints.email],
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
                textInputAction: TextInputAction.done,
                onFieldSubmitted: (_) => _submit(),
                decoration: InputDecoration(
                  hintText: 'Your password',
                  prefixIcon: const Icon(Icons.lock_outline, size: 18),
                  suffixIcon: IconButton(
                    icon: Icon(_obscure ? Icons.visibility_outlined : Icons.visibility_off_outlined,
                        size: 18),
                    onPressed: () => setState(() => _obscure = !_obscure),
                  ),
                ),
                validator: (v) => (v == null || v.isEmpty) ? 'Password is required' : null,
              ),
              const SizedBox(height: 28),
              AppButton(label: 'Sign in', onPressed: _submit, loading: _loading),
              const SizedBox(height: 24),
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Text("Don't have an account? ",
                      style: TextStyle(color: AppColors.textMuted, fontSize: 14)),
                  GestureDetector(
                    onTap: () => context.go('/register'),
                    child: const Text('Sign up',
                        style: TextStyle(
                            color: AppColors.primaryDk, fontWeight: FontWeight.w700, fontSize: 14)),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    ),
  );
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
