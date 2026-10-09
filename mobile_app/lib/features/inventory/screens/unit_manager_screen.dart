import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../widgets/units_tab.dart';

class UnitManagerScreen extends StatelessWidget {
  const UnitManagerScreen({super.key});

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: AppColors.surface,
    appBar: AppBar(
      backgroundColor: AppColors.surface,
      foregroundColor: AppColors.textDark,
      elevation: 0,
      centerTitle: true,
      title: const Text(
        'Unit Manager',
        style: TextStyle(fontWeight: FontWeight.w700, fontSize: 17),
      ),
    ),
    body: const UnitsTab(),
  );
}
