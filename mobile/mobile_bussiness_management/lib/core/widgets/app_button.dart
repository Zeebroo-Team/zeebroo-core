import 'package:flutter/material.dart';
import '../theme/app_theme.dart';

/// Full-width primary button with loading state.
class AppButton extends StatelessWidget {
  const AppButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.loading = false,
    this.icon,
    this.outlined = false,
  });

  final String   label;
  final VoidCallback? onPressed;
  final bool     loading;
  final IconData? icon;
  final bool     outlined;

  @override
  Widget build(BuildContext context) {
    Widget child = loading
        ? const SizedBox(
            width: 22, height: 22,
            child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white),
          )
        : Row(
            mainAxisAlignment: MainAxisAlignment.center,
            mainAxisSize: MainAxisSize.min,
            children: [
              if (icon != null) ...[
                Icon(icon, size: 18),
                const SizedBox(width: 8),
              ],
              Text(label),
            ],
          );

    if (outlined) {
      return OutlinedButton(
        onPressed: loading ? null : onPressed,
        child: child,
      );
    }
    return ElevatedButton(
      onPressed: loading ? null : onPressed,
      child: child,
    );
  }
}

/// Labelled text field with icon prefix.
class AppField extends StatelessWidget {
  const AppField({
    super.key,
    required this.label,
    required this.controller,
    this.hint = '',
    this.icon,
    this.obscure = false,
    this.keyboard = TextInputType.text,
    this.action = TextInputAction.next,
    this.error,
    this.suffix,
    this.onSubmitted,
    this.validator,
    this.enabled = true,
  });

  final String             label;
  final TextEditingController controller;
  final String             hint;
  final IconData?          icon;
  final bool               obscure;
  final TextInputType      keyboard;
  final TextInputAction    action;
  final String?            error;
  final Widget?            suffix;
  final ValueChanged<String>? onSubmitted;
  final String? Function(String?)? validator;
  final bool               enabled;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(label, style: const TextStyle(
        fontSize: 13, fontWeight: FontWeight.w600, color: AppColors.textMid,
      )),
      const SizedBox(height: 6),
      TextFormField(
        controller: controller,
        obscureText: obscure,
        keyboardType: keyboard,
        textInputAction: action,
        enabled: enabled,
        onFieldSubmitted: onSubmitted,
        validator: validator,
        style: const TextStyle(fontSize: 15, color: AppColors.textDark),
        decoration: InputDecoration(
          hintText: hint,
          prefixIcon: icon != null ? Icon(icon, size: 18, color: AppColors.textHint) : null,
          suffixIcon: suffix,
          errorText: error?.isEmpty ?? true ? null : error,
        ),
      ),
    ],
  );
}
