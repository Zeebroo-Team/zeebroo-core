import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/auth/auth_state.dart';
import '../../../core/business/business_state.dart';
import '../../../core/theme/app_theme.dart';

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  static const _totalSteps = 5;

  final _forms = List.generate(_totalSteps, (_) => GlobalKey<FormState>());
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _password = TextEditingController();
  final _confirm = TextEditingController();
  final _businessName = TextEditingController();

  int _step = 0;
  double _slideDirection = 1;
  bool _loading = false;
  bool _obscurePassword = true;
  bool _obscureConfirm = true;
  String? _error;

  String get _displayName {
    final name = _name.text.trim();
    return name.isEmpty ? 'there' : name;
  }

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _password.dispose();
    _confirm.dispose();
    _businessName.dispose();
    super.dispose();
  }

  void _back() {
    FocusScope.of(context).unfocus();
    if (_step == 0) {
      context.go('/login');
      return;
    }
    setState(() {
      _slideDirection = -1;
      _step--;
      _error = null;
    });
  }

  void _next() {
    if (!(_forms[_step].currentState?.validate() ?? false)) return;
    FocusScope.of(context).unfocus();
    if (_step == _totalSteps - 1) {
      _submit();
      return;
    }
    setState(() {
      _slideDirection = 1;
      _step++;
      _error = null;
    });
  }

  Future<void> _submit() async {
    if (_loading) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final auth = context.read<AuthState>();
      final businessState = context.read<BusinessState>();
      final business = await auth.registerAndFinish(
        name: _name.text.trim(),
        email: _email.text.trim(),
        password: _password.text,
        businessName: _businessName.text.trim(),
        businessCategory: 'other',
        deferNavigation: true,
      );
      await businessState.select(
        businessId: business.id,
        businessName: business.name,
      );
      auth.finishDeferredNavigation();
    } catch (e) {
      if (mounted) setState(() => _error = apiErrorMessage(e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: const Color(0xFFF8FBFF),
    body: Stack(
      children: [
        const Positioned.fill(child: _SignupBackground()),
        SafeArea(
          child: GestureDetector(
            behavior: HitTestBehavior.translucent,
            onTap: () => FocusScope.of(context).unfocus(),
            child: Column(
              children: [
                _SignupHeader(
                  current: _step,
                  total: _totalSteps,
                  onBack: _back,
                ),
                Expanded(
                  child: AnimatedSwitcher(
                    duration: const Duration(milliseconds: 260),
                    switchInCurve: Curves.easeOut,
                    switchOutCurve: Curves.easeIn,
                    transitionBuilder: (child, animation) => FadeTransition(
                      opacity: animation,
                      child: SlideTransition(
                        position: Tween<Offset>(
                          begin: Offset(0.1 * _slideDirection, 0),
                          end: Offset.zero,
                        ).animate(animation),
                        child: child,
                      ),
                    ),
                    child: SingleChildScrollView(
                      key: ValueKey(_step),
                      padding: const EdgeInsets.fromLTRB(24, 10, 24, 18),
                      child: _buildStep(),
                    ),
                  ),
                ),
                if (_error != null)
                  Padding(
                    padding: const EdgeInsets.fromLTRB(20, 0, 20, 10),
                    child: _ErrorBanner(message: _error!),
                  ),
                _SignupFooter(
                  finalStep: _step == _totalSteps - 1,
                  loading: _loading,
                  onBack: _back,
                  onNext: _next,
                ),
              ],
            ),
          ),
        ),
      ],
    ),
  );

  Widget _buildStep() => switch (_step) {
    0 => Form(
      key: _forms[0],
      child: _SignupSlide(
        title: 'Hey there!',
        subtitle: 'Let’s get started.',
        question: 'First things first — what’s your name?',
        field: TextFormField(
          controller: _name,
          autofocus: true,
          textCapitalization: TextCapitalization.words,
          textInputAction: TextInputAction.next,
          autofillHints: const [AutofillHints.name],
          onFieldSubmitted: (_) => _next(),
          decoration: const InputDecoration(
            hintText: 'Enter your name',
            prefixIcon: Icon(Icons.person_outline_rounded),
          ),
          validator: (value) => value == null || value.trim().isEmpty
              ? 'Please enter your name'
              : null,
        ),
        illustration: const _SignupIllustration(
          assetPath: 'assets/images/signup/name.png',
        ),
      ),
    ),
    1 => Form(
      key: _forms[1],
      child: _SignupSlide(
        title: 'Nice to meet you, $_displayName!',
        question: 'What’s your email?',
        field: TextFormField(
          controller: _email,
          autofocus: true,
          keyboardType: TextInputType.emailAddress,
          textInputAction: TextInputAction.next,
          autofillHints: const [AutofillHints.email],
          onFieldSubmitted: (_) => _next(),
          decoration: const InputDecoration(
            hintText: 'you@company.com',
            prefixIcon: Icon(Icons.mail_outline_rounded),
          ),
          validator: (value) {
            final email = value?.trim() ?? '';
            if (email.isEmpty) return 'Please enter your email';
            if (!RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(email)) {
              return 'Please enter a valid email address';
            }
            return null;
          },
        ),
        illustration: const _SignupIllustration(
          assetPath: 'assets/images/signup/email.png',
        ),
      ),
    ),
    2 => Form(
      key: _forms[2],
      child: _SignupSlide(
        title: 'Great!',
        subtitle: 'Let’s create a password.',
        question: 'Create your password',
        field: TextFormField(
          controller: _password,
          autofocus: true,
          obscureText: _obscurePassword,
          textInputAction: TextInputAction.next,
          autofillHints: const [AutofillHints.newPassword],
          onFieldSubmitted: (_) => _next(),
          decoration: InputDecoration(
            hintText: 'Min. 8 characters',
            prefixIcon: const Icon(Icons.lock_outline_rounded),
            suffixIcon: IconButton(
              tooltip: _obscurePassword ? 'Show password' : 'Hide password',
              onPressed: () =>
                  setState(() => _obscurePassword = !_obscurePassword),
              icon: Icon(
                _obscurePassword
                    ? Icons.visibility_outlined
                    : Icons.visibility_off_outlined,
              ),
            ),
          ),
          validator: (value) {
            if (value == null || value.isEmpty) {
              return 'Please create a password';
            }
            if (value.length < 8) {
              return 'Password must be at least 8 characters';
            }
            return null;
          },
        ),
        illustration: const _SignupIllustration(
          assetPath: 'assets/images/signup/password.png',
        ),
      ),
    ),
    3 => Form(
      key: _forms[3],
      child: _SignupSlide(
        title: 'Almost there!',
        question: 'Can you enter your password again?',
        field: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            TextFormField(
              controller: _confirm,
              autofocus: true,
              obscureText: _obscureConfirm,
              autovalidateMode: AutovalidateMode.onUserInteraction,
              textInputAction: TextInputAction.next,
              autofillHints: const [AutofillHints.newPassword],
              onChanged: (_) => setState(() {}),
              onFieldSubmitted: (_) => _next(),
              decoration: InputDecoration(
                hintText: 'Repeat password',
                prefixIcon: const Icon(Icons.lock_outline_rounded),
                suffixIcon: IconButton(
                  tooltip: _obscureConfirm ? 'Show password' : 'Hide password',
                  onPressed: () =>
                      setState(() => _obscureConfirm = !_obscureConfirm),
                  icon: Icon(
                    _obscureConfirm
                        ? Icons.visibility_outlined
                        : Icons.visibility_off_outlined,
                  ),
                ),
              ),
              validator: (value) {
                if (value == null || value.isEmpty) {
                  return 'Please confirm your password';
                }
                if (value != _password.text) {
                  return "Passwords don't match. Please try again.";
                }
                return null;
              },
            ),
            AnimatedSwitcher(
              duration: const Duration(milliseconds: 240),
              transitionBuilder: (child, animation) => FadeTransition(
                opacity: animation,
                child: ScaleTransition(
                  scale: Tween<double>(begin: 0.82, end: 1).animate(
                    CurvedAnimation(
                      parent: animation,
                      curve: Curves.easeOutBack,
                    ),
                  ),
                  child: child,
                ),
              ),
              child: _confirm.text.isNotEmpty && _confirm.text == _password.text
                  ? const Padding(
                      key: ValueKey('password-match'),
                      padding: EdgeInsets.only(top: 10),
                      child: Row(
                        children: [
                          Icon(
                            Icons.check_circle_rounded,
                            size: 18,
                            color: AppColors.success,
                          ),
                          SizedBox(width: 6),
                          Text(
                            'Perfect! ✓',
                            style: TextStyle(
                              color: AppColors.success,
                              fontSize: 13,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ],
                      ),
                    )
                  : const SizedBox(key: ValueKey('password-no-match')),
            ),
          ],
        ),
        illustration: const _SignupIllustration(
          assetPath: 'assets/images/signup/confirm_password.png',
        ),
      ),
    ),
    _ => Form(
      key: _forms[4],
      child: _SignupSlide(
        title: 'Awesome, $_displayName!',
        question: 'What do you call your business?',
        field: TextFormField(
          controller: _businessName,
          autofocus: true,
          textCapitalization: TextCapitalization.words,
          textInputAction: TextInputAction.done,
          onFieldSubmitted: (_) => _next(),
          decoration: const InputDecoration(
            hintText: 'Acme Store',
            prefixIcon: Icon(Icons.storefront_outlined),
          ),
          validator: (value) => value == null || value.trim().isEmpty
              ? 'Please enter your business name'
              : null,
        ),
        illustration: const _SignupIllustration(
          assetPath: 'assets/images/signup/business.png',
        ),
      ),
    ),
  };
}

class _SignupBackground extends StatefulWidget {
  const _SignupBackground();

  @override
  State<_SignupBackground> createState() => _SignupBackgroundState();
}

class _SignupBackgroundState extends State<_SignupBackground>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(seconds: 10),
  )..repeat(reverse: true);

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => IgnorePointer(
    child: RepaintBoundary(
      child: AnimatedBuilder(
        animation: _controller,
        builder: (context, child) {
          final progress = Curves.easeInOut.transform(_controller.value);
          return Stack(
            fit: StackFit.expand,
            children: [
              const DecoratedBox(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                    colors: [
                      Color(0xFFF8FBFF),
                      Color(0xFFFFFFFF),
                      Color(0xFFF1F6FF),
                    ],
                    stops: [0, 0.52, 1],
                  ),
                ),
              ),
              const CustomPaint(painter: _ZebraStripePainter()),
              Positioned(
                top: -92,
                right: -72,
                child: Transform.translate(
                  offset: Offset(7 * progress, 10 * (progress - 0.5)),
                  child: const _BackgroundCircle(
                    size: 225,
                    colors: [Color(0x1A4F91F7), Color(0x084F91F7)],
                  ),
                ),
              ),
              Positioned(
                left: -58,
                bottom: 54,
                child: Transform.translate(
                  offset: Offset(-6 * progress, -8 * (progress - 0.5)),
                  child: const _BackgroundCircle(
                    size: 155,
                    colors: [Color(0x164F91F7), Color(0x064F91F7)],
                  ),
                ),
              ),
            ],
          );
        },
      ),
    ),
  );
}

class _BackgroundCircle extends StatelessWidget {
  const _BackgroundCircle({required this.size, required this.colors});

  final double size;
  final List<Color> colors;

  @override
  Widget build(BuildContext context) => Container(
    width: size,
    height: size,
    decoration: BoxDecoration(
      shape: BoxShape.circle,
      gradient: RadialGradient(colors: colors),
    ),
  );
}

class _ZebraStripePainter extends CustomPainter {
  const _ZebraStripePainter();

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = const Color(0xFF2563EB).withValues(alpha: 0.025)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 22
      ..strokeCap = StrokeCap.round;

    for (var index = 0; index < 7; index++) {
      final y = size.height * 0.18 + (index * 58);
      final path = Path()
        ..moveTo(size.width * 0.68, y)
        ..cubicTo(
          size.width * 0.82,
          y + 20,
          size.width * 0.78,
          y + 56,
          size.width * 1.05,
          y + 72,
        );
      canvas.drawPath(path, paint);
    }
  }

  @override
  bool shouldRepaint(covariant _ZebraStripePainter oldDelegate) => false;
}

class _SignupHeader extends StatelessWidget {
  const _SignupHeader({
    required this.current,
    required this.total,
    required this.onBack,
  });

  final int current;
  final int total;
  final VoidCallback onBack;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(8, 8, 8, 4),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        IconButton(
          tooltip: 'Back',
          onPressed: onBack,
          icon: const Icon(Icons.arrow_back_rounded),
        ),
        Expanded(
          child: Column(
            children: [
              const SizedBox(height: 7),
              _ProgressDots(total: total, current: current),
              const SizedBox(height: 6),
              Text(
                '${current + 1} / $total',
                style: const TextStyle(
                  color: AppColors.textMuted,
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(width: 48),
      ],
    ),
  );
}

class _ProgressDots extends StatelessWidget {
  const _ProgressDots({required this.total, required this.current});

  final int total;
  final int current;

  @override
  Widget build(BuildContext context) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      for (var index = 0; index < total; index++) ...[
        AnimatedContainer(
          duration: const Duration(milliseconds: 220),
          width: index == current ? 11 : 9,
          height: index == current ? 11 : 9,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: index == current ? AppColors.primary : Colors.white,
            border: Border.all(
              color: index <= current ? AppColors.primary : AppColors.border,
              width: 2,
            ),
          ),
        ),
        if (index < total - 1)
          AnimatedContainer(
            duration: const Duration(milliseconds: 260),
            curve: Curves.easeOut,
            width: 16,
            height: 2,
            color: index < current ? AppColors.primary : AppColors.border,
          ),
      ],
    ],
  );
}

class _SignupSlide extends StatelessWidget {
  const _SignupSlide({
    required this.title,
    required this.question,
    required this.field,
    required this.illustration,
    this.subtitle,
  });

  final String title;
  final String? subtitle;
  final String question;
  final Widget field;
  final Widget illustration;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        title,
        style: const TextStyle(
          fontSize: 25,
          height: 1.15,
          fontWeight: FontWeight.w800,
          color: AppColors.textDark,
        ),
      ),
      if (subtitle != null) ...[
        const SizedBox(height: 8),
        Text(
          subtitle!,
          style: const TextStyle(fontSize: 15, color: AppColors.textMuted),
        ),
      ],
      const SizedBox(height: 14),
      Text(
        question,
        style: const TextStyle(
          fontSize: 15,
          fontWeight: FontWeight.w500,
          color: AppColors.textMid,
        ),
      ),
      const SizedBox(height: 12),
      field,
      const SizedBox(height: 22),
      illustration,
    ],
  );
}

class _SignupIllustration extends StatefulWidget {
  const _SignupIllustration({required this.assetPath});

  final String assetPath;

  @override
  State<_SignupIllustration> createState() => _SignupIllustrationState();
}

class _SignupIllustrationState extends State<_SignupIllustration>
    with TickerProviderStateMixin {
  late final AnimationController _entryController = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 480),
  );
  late final AnimationController _floatController = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 2400),
  );
  late final Listenable _animations = Listenable.merge([
    _entryController,
    _floatController,
  ]);

  @override
  void initState() {
    super.initState();
    _entryController.addStatusListener((status) {
      if (status == AnimationStatus.completed && mounted) {
        _floatController.repeat(reverse: true);
      }
    });
    _entryController.forward();
  }

  @override
  void dispose() {
    _entryController.dispose();
    _floatController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedBuilder(
    animation: _animations,
    builder: (context, child) {
      final entry = Curves.easeOutCubic.transform(_entryController.value);
      final entryOffset = 18 * (1 - entry);
      final floatingOffset = -6 * _floatController.value;
      return Opacity(
        opacity: entry,
        child: Transform.translate(
          offset: Offset(0, entryOffset + floatingOffset),
          child: Transform.scale(scale: 0.95 + (0.05 * entry), child: child),
        ),
      );
    },
    child: SizedBox(
      width: double.infinity,
      height: 205,
      child: Stack(
        fit: StackFit.expand,
        children: [
          const DecoratedBox(
            decoration: BoxDecoration(
              gradient: RadialGradient(
                radius: 0.78,
                colors: [Color(0x164F91F7), Color(0x004F91F7)],
              ),
            ),
          ),
          ShaderMask(
            blendMode: BlendMode.dstIn,
            shaderCallback: (bounds) => const LinearGradient(
              begin: Alignment.centerLeft,
              end: Alignment.centerRight,
              colors: [
                Colors.transparent,
                Colors.white,
                Colors.white,
                Colors.transparent,
              ],
              stops: [0, 0.06, 0.94, 1],
            ).createShader(bounds),
            child: ShaderMask(
              blendMode: BlendMode.dstIn,
              shaderCallback: (bounds) => const LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [
                  Colors.transparent,
                  Colors.white,
                  Colors.white,
                  Colors.transparent,
                ],
                stops: [0, 0.05, 0.9, 1],
              ).createShader(bounds),
              child: Image.asset(
                widget.assetPath,
                fit: BoxFit.cover,
                alignment: Alignment.center,
                filterQuality: FilterQuality.high,
              ),
            ),
          ),
        ],
      ),
    ),
  );
}

class _SignupFooter extends StatelessWidget {
  const _SignupFooter({
    required this.finalStep,
    required this.loading,
    required this.onBack,
    required this.onNext,
  });

  final bool finalStep;
  final bool loading;
  final VoidCallback onBack;
  final VoidCallback onNext;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.fromLTRB(20, 12, 20, 16),
    decoration: BoxDecoration(
      color: Colors.white.withValues(alpha: 0.94),
      border: const Border(top: BorderSide(color: AppColors.border)),
    ),
    child: Row(
      children: [
        TextButton.icon(
          onPressed: loading ? null : onBack,
          icon: const Icon(Icons.arrow_back_rounded, size: 18),
          label: const Text('Back'),
          style: TextButton.styleFrom(
            foregroundColor: AppColors.textMid,
            backgroundColor: const Color(0xFFF3F7FD),
            padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
          ),
        ),
        const Spacer(),
        ElevatedButton(
          onPressed: loading ? null : onNext,
          style: ElevatedButton.styleFrom(
            minimumSize: Size(finalStep ? 190 : 128, 50),
            padding: const EdgeInsets.symmetric(horizontal: 20),
          ),
          child: loading
              ? const SizedBox(
                  width: 20,
                  height: 20,
                  child: CircularProgressIndicator(
                    color: Colors.white,
                    strokeWidth: 2.4,
                  ),
                )
              : Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(finalStep ? 'Create my account' : 'Next'),
                    const SizedBox(width: 8),
                    const Icon(Icons.arrow_forward_rounded, size: 18),
                  ],
                ),
        ),
      ],
    ),
  );
}

class _ErrorBanner extends StatelessWidget {
  const _ErrorBanner({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: AppColors.error.withValues(alpha: 0.08),
      borderRadius: BorderRadius.circular(12),
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
