import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/auth/auth_state.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/gradient_header.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _form     = GlobalKey<FormState>();
  final _email    = TextEditingController();
  final _password = TextEditingController();
  bool _obscure   = true;
  bool _loading   = false;
  String? _error;

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    setState(() { _loading = true; _error = null; });
    try {
      await context.read<AuthState>().login(
        _email.text.trim().toLowerCase(),
        _password.text,
      );
      // Router will redirect to /home via GoRouter redirect
    } catch (e) {
      setState(() { _error = apiErrorMessage(e); });
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
          // ── Header ──────────────────────────────────────────────────────
          GradientHeader(
            child: Column(
              children: [
                const Icon(Icons.business_center_rounded, size: 48, color: Colors.white),
                const SizedBox(height: 12),
                const Text(
                  'Zeebroo',
                  style: TextStyle(fontSize: 30, fontWeight: FontWeight.w800,
                      color: Colors.white, letterSpacing: 0.5),
                ),
                const SizedBox(height: 4),
                Text(
                  'Business Management Suite',
                  style: TextStyle(fontSize: 13, color: Colors.white.withValues(alpha: 0.75)),
                ),
              ],
            ),
          ),

          // ── Form ─────────────────────────────────────────────────────────
          Expanded(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(24),
              child: Form(
                key: _form,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const SizedBox(height: 8),
                    const Text('Welcome back',
                      style: TextStyle(fontSize: 22, fontWeight: FontWeight.w700,
                          color: AppColors.textDark)),
                    const SizedBox(height: 4),
                    const Text('Sign in to your account',
                      style: TextStyle(fontSize: 14, color: AppColors.textMuted)),
                    const SizedBox(height: 28),

                    // Error banner
                    if (_error != null) ...[
                      Container(
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
                            Expanded(child: Text(_error!,
                              style: const TextStyle(color: AppColors.error, fontSize: 13))),
                          ],
                        ),
                      ),
                      const SizedBox(height: 20),
                    ],

                    AppField(
                      label: 'Email',
                      controller: _email,
                      hint: 'you@company.com',
                      icon: Icons.mail_outline,
                      keyboard: TextInputType.emailAddress,
                      validator: (v) {
                        if (v == null || v.trim().isEmpty) return 'Email is required';
                        if (!RegExp(r'\S+@\S+\.\S+').hasMatch(v)) return 'Enter a valid email';
                        return null;
                      },
                    ),
                    const SizedBox(height: 16),

                    AppField(
                      label: 'Password',
                      controller: _password,
                      hint: '••••••••',
                      icon: Icons.lock_outline,
                      obscure: _obscure,
                      action: TextInputAction.done,
                      onSubmitted: (_) => _submit(),
                      suffix: IconButton(
                        icon: Icon(_obscure ? Icons.visibility_outlined
                                            : Icons.visibility_off_outlined,
                          size: 18, color: AppColors.textHint),
                        onPressed: () => setState(() => _obscure = !_obscure),
                      ),
                      validator: (v) =>
                          (v == null || v.isEmpty) ? 'Password is required' : null,
                    ),

                    Align(
                      alignment: Alignment.centerRight,
                      child: TextButton(
                        onPressed: () {},
                        child: const Text('Forgot password?',
                          style: TextStyle(color: AppColors.primary, fontSize: 13)),
                      ),
                    ),
                    const SizedBox(height: 8),

                    AppButton(label: 'Sign In', onPressed: _submit, loading: _loading,
                        icon: Icons.login_rounded),
                    const SizedBox(height: 24),

                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Text("Don't have an account? ",
                          style: TextStyle(color: AppColors.textMuted, fontSize: 14)),
                        GestureDetector(
                          onTap: () => context.go('/register'),
                          child: const Text('Create one',
                            style: TextStyle(color: AppColors.primary,
                                fontWeight: FontWeight.w600, fontSize: 14)),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    ),
  );
}
