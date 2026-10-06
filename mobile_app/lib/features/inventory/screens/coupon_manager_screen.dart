import 'dart:async';

import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/theme/app_theme.dart';
import '../data/coupon_repository.dart';
import '../widgets/coupon_summary_card.dart';
import 'coupon_detail_screen.dart';
import 'coupon_form_screen.dart';

const _filters = [
  (label: 'All', value: ''),
  (label: 'Active', value: 'active'),
  (label: 'Used up', value: 'used'),
  (label: 'Expired', value: 'expired'),
  (label: 'Disabled', value: 'disabled'),
  (label: 'Scheduled', value: 'scheduled'),
];

class CouponManagerScreen extends StatefulWidget {
  const CouponManagerScreen({
    super.key,
    this.repository = const CouponRepository(),
  });
  final CouponRepository repository;

  @override
  State<CouponManagerScreen> createState() => _CouponManagerScreenState();
}

class _CouponManagerScreenState extends State<CouponManagerScreen> {
  final _search = TextEditingController();
  Timer? _debounce;
  String _status = '';
  int _request = 0;
  bool _loading = true;
  String? _error;
  List<CouponData> _coupons = [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    if (!mounted) return;
    final request = ++_request;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final coupons = await widget.repository.list(
        query: _search.text.trim(),
        status: _status,
      );
      if (mounted && request == _request) setState(() => _coupons = coupons);
    } catch (error) {
      if (mounted && request == _request) {
        setState(() => _error = apiErrorMessage(error));
      }
    } finally {
      if (mounted && request == _request) setState(() => _loading = false);
    }
  }

  void _onSearch(String text) {
    _debounce?.cancel();
    ++_request;
    _debounce = Timer(const Duration(milliseconds: 300), _load);
  }

  Future<void> _newCoupon() async {
    final saved = await Navigator.of(context).push<CouponData>(
      MaterialPageRoute(
        builder: (_) => CouponFormScreen(repository: widget.repository),
      ),
    );
    if (!mounted || saved == null) return;
    await _load();
    if (mounted) await _open(saved);
  }

  Future<void> _open(CouponData coupon) async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) =>
            CouponDetailScreen(coupon: coupon, repository: widget.repository),
      ),
    );
    if (mounted) await _load();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: AppColors.surface,
    appBar: AppBar(
      backgroundColor: AppColors.surface,
      foregroundColor: AppColors.textDark,
      centerTitle: true,
      title: const Text('Coupons'),
    ),
    body: SafeArea(
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 12, 20, 0),
            child: TextField(
              controller: _search,
              onChanged: _onSearch,
              textInputAction: TextInputAction.search,
              onSubmitted: (_) {
                _debounce?.cancel();
                _load();
              },
              decoration: const InputDecoration(
                hintText: 'Search by name or code',
                prefixIcon: Icon(Icons.search_rounded),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 10, 20, 2),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    '${_coupons.length} coupons',
                    style: const TextStyle(
                      color: AppColors.textMuted,
                      fontSize: 12,
                    ),
                  ),
                ),
                TextButton.icon(
                  onPressed: _newCoupon,
                  icon: const Icon(Icons.add_rounded),
                  label: const Text('New Coupon'),
                ),
              ],
            ),
          ),
          SizedBox(
            height: 48,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 20),
              itemCount: _filters.length,
              separatorBuilder: (_, _) => const SizedBox(width: 8),
              itemBuilder: (_, index) {
                final filter = _filters[index];
                return ChoiceChip(
                  label: Text(filter.label),
                  selected: _status == filter.value,
                  onSelected: (_) {
                    _debounce?.cancel();
                    setState(() => _status = filter.value);
                    _load();
                  },
                );
              },
            ),
          ),
          Expanded(child: _body()),
        ],
      ),
    ),
  );

  Widget _body() {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(_error!, textAlign: TextAlign.center),
              TextButton(onPressed: _load, child: const Text('Retry')),
            ],
          ),
        ),
      );
    }
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.separated(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
        itemCount: _coupons.isEmpty ? 1 : _coupons.length,
        separatorBuilder: (_, _) => const SizedBox(height: 12),
        itemBuilder: (_, index) {
          if (_coupons.isEmpty) {
            return const Padding(
              padding: EdgeInsets.symmetric(vertical: 60),
              child: Center(
                child: Text(
                  'No coupons found.',
                  style: TextStyle(color: AppColors.textMuted),
                ),
              ),
            );
          }
          final coupon = _coupons[index];
          return Material(
            color: Colors.white,
            borderRadius: BorderRadius.circular(14),
            child: InkWell(
              onTap: () => _open(coupon),
              borderRadius: BorderRadius.circular(14),
              child: Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(
                    color: AppColors.border.withValues(alpha: 0.55),
                  ),
                ),
                child: Row(
                  children: [
                    Container(
                      width: 40,
                      height: 40,
                      decoration: BoxDecoration(
                        color: const Color(0xFFE0F2F1),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: const Icon(
                        Icons.confirmation_number_outlined,
                        color: Color(0xFF0D9488),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            coupon.name,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontWeight: FontWeight.w700,
                              fontSize: 14,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            coupon.code,
                            style: const TextStyle(
                              fontSize: 12,
                              color: AppColors.textMuted,
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            coupon.discountLabel,
                            style: const TextStyle(
                              color: Color(0xFF0D9488),
                              fontSize: 12,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 8),
                    Column(
                      children: [
                        CouponStatusBadge(coupon: coupon),
                        const SizedBox(height: 6),
                        Text(
                          '${coupon.remaining}/${coupon.quantity} left',
                          style: const TextStyle(
                            fontSize: 11,
                            color: AppColors.textMuted,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}
