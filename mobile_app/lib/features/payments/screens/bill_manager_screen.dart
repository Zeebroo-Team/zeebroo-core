import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../finance/widgets/bills_tab.dart';

class BillManagerScreen extends StatelessWidget {
  const BillManagerScreen({super.key});

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: AppColors.surface,
    appBar: AppBar(
      backgroundColor: AppColors.surface,
      foregroundColor: AppColors.textDark,
      centerTitle: true,
      title: const Text(
        'Bill Manager',
        style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700),
      ),
    ),
    body: const SafeArea(top: false, child: BillsTab(managerMode: true)),
  );
}
