import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import 'inventory_screen.dart';

const _kContactSections = [
  _ContactSection(
    label: 'Customers',
    icon: Icons.people_alt_rounded,
    color: Color(0xFF3B82F6),
    inventoryTabIndex: 11,
  ),
  _ContactSection(
    label: 'Suppliers',
    icon: Icons.local_shipping_outlined,
    color: Color(0xFF14B8A6),
    inventoryTabIndex: 10,
  ),
];

class _ContactSection {
  const _ContactSection({
    required this.label,
    required this.icon,
    required this.color,
    required this.inventoryTabIndex,
  });

  final String label;
  final IconData icon;
  final Color color;
  final int inventoryTabIndex;
}

class ContactsOverviewScreen extends StatefulWidget {
  const ContactsOverviewScreen({super.key});

  @override
  State<ContactsOverviewScreen> createState() => _ContactsOverviewScreenState();
}

class _ContactsOverviewScreenState extends State<ContactsOverviewScreen>
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

  void _openSection(_ContactSection section) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => InventoryScreen(
          initialTabIndex: section.inventoryTabIndex,
          title: section.label,
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
        'Contacts',
        style: TextStyle(fontWeight: FontWeight.w700, fontSize: 17),
      ),
    ),
    body: SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 32),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _ContactsBanner(controller: _controller),
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
            itemCount: _kContactSections.length,
            itemBuilder: (context, index) {
              final section = _kContactSections[index];
              return _ContactSectionCard(
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

class _ContactsBanner extends StatelessWidget {
  const _ContactsBanner({required this.controller});

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
              colors: [Color(0xFF0F766E), Color(0xFF14B8A6)],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            borderRadius: BorderRadius.circular(20),
            boxShadow: [
              BoxShadow(
                color: const Color(0xFF14B8A6).withValues(alpha: 0.32),
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
                  Icons.contacts_rounded,
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
                      'Contacts',
                      style: TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.w800,
                        color: Colors.white,
                      ),
                    ),
                    SizedBox(height: 2),
                    Text(
                      'Manage customers and suppliers',
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

class _ContactSectionCard extends StatefulWidget {
  const _ContactSectionCard({
    required this.section,
    required this.controller,
    required this.delay,
    required this.onTap,
  });

  final _ContactSection section;
  final AnimationController controller;
  final double delay;
  final VoidCallback onTap;

  @override
  State<_ContactSectionCard> createState() => _ContactSectionCardState();
}

class _ContactSectionCardState extends State<_ContactSectionCard>
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
                Text(
                  widget.section.label,
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    fontSize: 12.5,
                    fontWeight: FontWeight.w700,
                    color: AppColors.textDark,
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
