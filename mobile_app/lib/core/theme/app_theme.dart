import 'package:flutter/material.dart';

/// Zeebroo brand palette — matches the Electron POS app's ribbon theme
/// (electron_app/renderer/css/ribbon.css :root variables: --accent,
/// --accent-dark, --bg, --border, --text, --text-muted).
class AppColors {
  static const primary    = Color(0xFF4E8EF7);
  static const primaryDk  = Color(0xFF2563EB);
  static const primaryLt  = Color(0xFFEFF6FF);
  static const surface    = Color(0xFFF0F2F5);
  static const card       = Colors.white;
  static const border     = Color(0xFFD1D5DB);
  static const textDark   = Color(0xFF1F2937);
  static const textMid    = Color(0xFF1F2937);
  static const textMuted  = Color(0xFF6B7280);
  static const textHint   = Color(0xFF9CA3AF);
  static const error      = Color(0xFFEF4444);
  static const success    = Color(0xFF16A34A);
  static const warning    = Color(0xFFF59E0B);
}

ThemeData buildAppTheme() => ThemeData(
  useMaterial3: true,
  colorScheme: ColorScheme.fromSeed(
    seedColor: AppColors.primary,
    primary: AppColors.primary,
    secondary: AppColors.primaryDk,
    surface: AppColors.surface,
    error: AppColors.error,
  ),
  scaffoldBackgroundColor: AppColors.surface,
  appBarTheme: const AppBarTheme(
    backgroundColor: AppColors.primary,
    foregroundColor: Colors.white,
    elevation: 0,
    centerTitle: true,
  ),
  inputDecorationTheme: InputDecorationTheme(
    filled: true,
    fillColor: AppColors.card,
    contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
    border: OutlineInputBorder(
      borderRadius: BorderRadius.circular(12),
      borderSide: const BorderSide(color: AppColors.border),
    ),
    enabledBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(12),
      borderSide: const BorderSide(color: AppColors.border),
    ),
    focusedBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(12),
      borderSide: const BorderSide(color: AppColors.primary, width: 1.5),
    ),
    errorBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(12),
      borderSide: const BorderSide(color: AppColors.error),
    ),
    focusedErrorBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(12),
      borderSide: const BorderSide(color: AppColors.error, width: 1.5),
    ),
    hintStyle: const TextStyle(color: AppColors.textHint),
    errorStyle: const TextStyle(color: AppColors.error, fontSize: 12),
  ),
  elevatedButtonTheme: ElevatedButtonThemeData(
    style: ElevatedButton.styleFrom(
      backgroundColor: AppColors.primary,
      foregroundColor: Colors.white,
      minimumSize: const Size.fromHeight(50),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      textStyle: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
      elevation: 0,
    ),
  ),
  outlinedButtonTheme: OutlinedButtonThemeData(
    style: OutlinedButton.styleFrom(
      foregroundColor: AppColors.textDark,
      minimumSize: const Size.fromHeight(50),
      side: const BorderSide(color: AppColors.border, width: 1.5),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      textStyle: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
    ),
  ),
  cardTheme: CardThemeData(
    color: AppColors.card,
    elevation: 0,
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(14),
      side: const BorderSide(color: AppColors.border),
    ),
    margin: EdgeInsets.zero,
  ),
);
