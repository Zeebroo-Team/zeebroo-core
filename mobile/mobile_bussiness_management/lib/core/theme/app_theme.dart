import 'package:flutter/material.dart';

class AppColors {
  static const primary    = Color(0xFF6C3AED);
  static const primaryDk  = Color(0xFF4F46E5);
  static const surface    = Color(0xFFF8F7FF);
  static const card       = Colors.white;
  static const border     = Color(0xFFE5E7EB);
  static const textDark   = Color(0xFF111827);
  static const textMid    = Color(0xFF374151);
  static const textMuted  = Color(0xFF6B7280);
  static const textHint   = Color(0xFF9CA3AF);
  static const error      = Color(0xFFEF4444);
  static const success    = Color(0xFF10B981);
  static const warning    = Color(0xFFF59E0B);
  static const info       = Color(0xFF0EA5E9);

  // Semantic chip backgrounds
  static const purpleLight = Color(0xFFEDE9FE);
  static const greenLight  = Color(0xFFD1FAE5);
  static const blueLight   = Color(0xFFE0F2FE);
  static const amberLight  = Color(0xFFFEF3C7);
}

ThemeData buildAppTheme() => ThemeData(
  useMaterial3: true,
  colorScheme: ColorScheme.fromSeed(
    seedColor: AppColors.primary,
    primary:   AppColors.primary,
    surface:   AppColors.surface,
    error:     AppColors.error,
  ),
  scaffoldBackgroundColor: AppColors.surface,
  fontFamily: 'Roboto',
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
      textStyle: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700, letterSpacing: 0.3),
      elevation: 4,
      shadowColor: AppColors.primary.withValues(alpha: 0.4),
    ),
  ),
  outlinedButtonTheme: OutlinedButtonThemeData(
    style: OutlinedButton.styleFrom(
      foregroundColor: AppColors.primary,
      minimumSize: const Size.fromHeight(50),
      side: const BorderSide(color: AppColors.primary, width: 1.5),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      textStyle: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
    ),
  ),
  cardTheme: CardThemeData(
    color: AppColors.card,
    elevation: 2,
    shadowColor: Colors.black.withValues(alpha: 0.06),
    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
    margin: EdgeInsets.zero,
  ),
);
