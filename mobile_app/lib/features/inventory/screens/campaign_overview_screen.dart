import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import 'coupon_manager_screen.dart';
import 'gift_card_manager_screen.dart';
import 'inventory_screen.dart';

class CampaignOverviewScreen extends StatefulWidget {
  const CampaignOverviewScreen({super.key});

  @override
  State<CampaignOverviewScreen> createState() => _CampaignOverviewScreenState();
}

class _CampaignOverviewScreenState extends State<CampaignOverviewScreen>
    with SingleTickerProviderStateMixin {
  late final AnimationController _entranceController = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 800),
  )..forward();

  @override
  void dispose() {
    _entranceController.dispose();
    super.dispose();
  }

  void _openDiscounts() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => const InventoryScreen(
          initialTabIndex: 8,
          title: 'Discounts',
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
        'Campaign',
        style: TextStyle(fontWeight: FontWeight.w700, fontSize: 17),
      ),
    ),
    body: SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 32),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _CampaignBanner(controller: _entranceController),
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
          GridView(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 2,
              mainAxisSpacing: 14,
              crossAxisSpacing: 14,
              childAspectRatio: 1.1,
            ),
            children: [
              _CampaignAccessCard(
                label: 'Discounts',
                icon: Icons.local_offer_rounded,
                color: const Color(0xFFEC4899),
                controller: _entranceController,
                delay: 0.1,
                onTap: _openDiscounts,
              ),
              _CampaignAccessCard(
                label: 'Coupons',
                icon: Icons.confirmation_number_rounded,
                color: const Color(0xFF0D9488),
                controller: _entranceController,
                delay: 0.2,
                onTap: () => Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) => const CouponManagerScreen(),
                  ),
                ),
              ),
              _CampaignAccessCard(
                label: 'Gift Card',
                icon: Icons.card_giftcard_rounded,
                color: const Color(0xFF7C3AED),
                controller: _entranceController,
                delay: 0.3,
                onTap: () => Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) => const GiftCardManagerScreen(),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    ),
  );
}

class _CampaignAccessCard extends StatefulWidget {
  const _CampaignAccessCard({
    required this.label,
    required this.icon,
    required this.color,
    required this.controller,
    required this.delay,
    required this.onTap,
  });
  final String label;
  final IconData icon;
  final Color color;
  final AnimationController controller;
  final double delay;
  final VoidCallback onTap;

  @override
  State<_CampaignAccessCard> createState() => _CampaignAccessCardState();
}

class _CampaignAccessCardState extends State<_CampaignAccessCard>
    with SingleTickerProviderStateMixin {
  late final AnimationController _press = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 120),
  );
  late final Animation<double> _scale = Tween<double>(
    begin: 1,
    end: 0.93,
  ).animate(CurvedAnimation(parent: _press, curve: Curves.easeInOut));

  @override
  void dispose() {
    _press.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final entrance = Tween<double>(begin: 0, end: 1).animate(
      CurvedAnimation(
        parent: widget.controller,
        curve: Interval(
          widget.delay,
          widget.delay + 0.35,
          curve: Curves.easeOutBack,
        ),
      ),
    );
    final color = widget.color;
    return ScaleTransition(
      scale: entrance,
      child: ScaleTransition(
        scale: _scale,
        child: GestureDetector(
          onTapDown: (_) => _press.forward(),
          onTapUp: (_) => _press.reverse(),
          onTapCancel: () => _press.reverse(),
          onTap: widget.onTap,
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
                  child: Icon(widget.icon, color: Colors.white, size: 26),
                ),
                const SizedBox(height: 10),
                Text(
                  widget.label,
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

class _CampaignBanner extends StatelessWidget {
  const _CampaignBanner({required this.controller});

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
              colors: [Color(0xFFBE185D), Color(0xFFEC4899)],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            borderRadius: BorderRadius.circular(20),
            boxShadow: [
              BoxShadow(
                color: const Color(0xFFEC4899).withValues(alpha: 0.32),
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
                  Icons.campaign_rounded,
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
                      'Campaign',
                      style: TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.w800,
                        color: Colors.white,
                      ),
                    ),
                    SizedBox(height: 2),
                    Text(
                      'Manage discounts, coupons & gift cards',
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
