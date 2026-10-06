import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import 'inventory_screen.dart';
import 'unit_manager_screen.dart';

const _kProductSections = [
  _ProductSection(
    label: 'Products Manager',
    icon: Icons.inventory_2_rounded,
    color: Color(0xFF6366F1),
    inventoryTabIndex: 1,
    pageTitle: 'Products',
  ),
  _ProductSection(
    label: 'Categories',
    icon: Icons.category_rounded,
    color: Color(0xFF06B6D4),
    inventoryTabIndex: 7,
    pageTitle: 'Categories',
  ),
  _ProductSection(
    label: 'Brands',
    icon: Icons.verified_rounded,
    color: Color(0xFF84CC16),
    inventoryTabIndex: 9,
    pageTitle: 'Brands',
  ),
  _ProductSection(
    label: 'Unit Manager',
    icon: Icons.straighten_rounded,
    color: Color(0xFF9333EA),
    pageTitle: 'Unit Manager',
  ),
];

class _ProductSection {
  const _ProductSection({
    required this.label,
    required this.icon,
    required this.color,
    this.inventoryTabIndex,
    required this.pageTitle,
  });

  final String label;
  final IconData icon;
  final Color color;
  final int? inventoryTabIndex;
  final String pageTitle;
}

class ProductOverviewScreen extends StatefulWidget {
  const ProductOverviewScreen({super.key});

  @override
  State<ProductOverviewScreen> createState() => _ProductOverviewScreenState();
}

class _ProductOverviewScreenState extends State<ProductOverviewScreen>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 800),
  )..forward();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _openSection(_ProductSection section) {
    if (section.inventoryTabIndex == null) {
      Navigator.of(
        context,
      ).push(MaterialPageRoute(builder: (_) => const UnitManagerScreen()));
      return;
    }
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => InventoryScreen(
          initialTabIndex: section.inventoryTabIndex!,
          title: section.pageTitle,
          returnToOverviewOnBack: false,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: AppColors.surface,
    appBar: AppBar(
      backgroundColor: AppColors.surface,
      foregroundColor: AppColors.textDark,
      elevation: 0,
      centerTitle: true,
      title: const Text(
        'Product',
        style: TextStyle(fontWeight: FontWeight.w700, fontSize: 17),
      ),
    ),
    body: SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 32),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _ProductBanner(controller: _controller),
          const SizedBox(height: 24),
          const Text(
            'Quick Access',
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w700,
              color: AppColors.textMuted,
              letterSpacing: 0.6,
            ),
          ),
          const SizedBox(height: 12),
          GridView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 2,
              mainAxisSpacing: 14,
              crossAxisSpacing: 14,
              childAspectRatio: 1.1,
            ),
            itemCount: _kProductSections.length,
            itemBuilder: (context, index) {
              final section = _kProductSections[index];
              return _ProductSectionCard(
                section: section,
                controller: _controller,
                delay: index * 0.1,
                onTap: () => _openSection(section),
              );
            },
          ),
        ],
      ),
    ),
  );
}

class _ProductBanner extends StatelessWidget {
  const _ProductBanner({required this.controller});

  final AnimationController controller;

  @override
  Widget build(BuildContext context) {
    final fade = Tween<double>(
      begin: 0,
      end: 1,
    ).animate(CurvedAnimation(parent: controller, curve: Curves.easeOut));
    final slide = Tween<Offset>(
      begin: const Offset(0, -0.15),
      end: Offset.zero,
    ).animate(CurvedAnimation(parent: controller, curve: Curves.easeOut));

    return FadeTransition(
      opacity: fade,
      child: SlideTransition(
        position: slide,
        child: Container(
          width: double.infinity,
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            gradient: const LinearGradient(
              colors: [Color(0xFF2563EB), Color(0xFF6366F1)],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            borderRadius: BorderRadius.circular(20),
            boxShadow: [
              BoxShadow(
                color: const Color(0xFF2563EB).withValues(alpha: 0.32),
                blurRadius: 20,
                offset: const Offset(0, 8),
              ),
            ],
          ),
          child: Row(
            children: [
              Container(
                width: 52,
                height: 52,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.2),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: const Icon(
                  Icons.inventory_2_rounded,
                  color: Colors.white,
                  size: 28,
                ),
              ),
              const SizedBox(width: 16),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Product',
                      style: TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.w800,
                        color: Colors.white,
                      ),
                    ),
                    SizedBox(height: 2),
                    Text(
                      'Manage products, categories & brands',
                      style: TextStyle(fontSize: 12.5, color: Colors.white70),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ProductSectionCard extends StatefulWidget {
  const _ProductSectionCard({
    required this.section,
    required this.controller,
    required this.delay,
    required this.onTap,
  });

  final _ProductSection section;
  final AnimationController controller;
  final double delay;
  final VoidCallback onTap;

  @override
  State<_ProductSectionCard> createState() => _ProductSectionCardState();
}

class _ProductSectionCardState extends State<_ProductSectionCard>
    with SingleTickerProviderStateMixin {
  late final AnimationController _pressController = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 120),
    lowerBound: 0,
    upperBound: 1,
  );

  late final Animation<double> _pressScale = Tween<double>(
    begin: 1,
    end: 0.93,
  ).animate(CurvedAnimation(parent: _pressController, curve: Curves.easeInOut));

  Animation<double> get _entrance {
    final start = widget.delay.clamp(0.0, 0.85);
    final end = (widget.delay + 0.35).clamp(0.0, 1.0);
    return Tween<double>(begin: 0, end: 1).animate(
      CurvedAnimation(
        parent: widget.controller,
        curve: Interval(start, end, curve: Curves.easeOutBack),
      ),
    );
  }

  @override
  void dispose() {
    _pressController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final color = widget.section.color;
    return ScaleTransition(
      scale: _entrance,
      child: ScaleTransition(
        scale: _pressScale,
        child: GestureDetector(
          onTapDown: (_) => _pressController.forward(),
          onTapUp: (_) {
            _pressController.reverse();
            widget.onTap();
          },
          onTapCancel: () => _pressController.reverse(),
          child: Container(
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(18),
              boxShadow: [
                BoxShadow(
                  color: color.withValues(alpha: 0.18),
                  blurRadius: 16,
                  offset: const Offset(0, 6),
                ),
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.05),
                  blurRadius: 4,
                  offset: const Offset(0, 2),
                ),
              ],
            ),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Container(
                  width: 56,
                  height: 56,
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      colors: [color, color.withValues(alpha: 0.75)],
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                    ),
                    borderRadius: BorderRadius.circular(16),
                    boxShadow: [
                      BoxShadow(
                        color: color.withValues(alpha: 0.4),
                        blurRadius: 12,
                        offset: const Offset(0, 4),
                      ),
                    ],
                  ),
                  child: Icon(
                    widget.section.icon,
                    color: Colors.white,
                    size: 26,
                  ),
                ),
                const SizedBox(height: 10),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 8),
                  child: Text(
                    widget.section.label,
                    textAlign: TextAlign.center,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 12.5,
                      fontWeight: FontWeight.w700,
                      color: AppColors.textDark,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
