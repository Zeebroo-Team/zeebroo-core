import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/utils/money.dart';
import '../data/pos_add_sound.dart';
import '../data/scanner_cart_controller.dart';
import '../data/scanner_product_repository.dart';
import '../models/pos_cart_item.dart';
import '../widgets/live_scanner_camera.dart';
import '../widgets/scanner_product_search_sheet.dart';

typedef ScannerCameraBuilder =
    Widget Function(
      BuildContext context,
      ValueChanged<String> onCode,
      bool paused,
    );

/// A continuous scanning session with its own cart, returned to the caller.
class BarcodeScannerScreen extends StatefulWidget {
  const BarcodeScannerScreen({
    super.key,
    this.initialCart = const [],
    this.repository,
    this.cameraBuilder,
    this.onProductAdded,
  });

  final List<PosCartItem> initialCart;
  final ScannerProductRepository? repository;
  final ScannerCameraBuilder? cameraBuilder;
  final VoidCallback? onProductAdded;

  @override
  State<BarcodeScannerScreen> createState() => _BarcodeScannerScreenState();
}

class _BarcodeScannerScreenState extends State<BarcodeScannerScreen> {
  static const _panelColor = Color(0xFF1C1D30);
  late final ScannerCartController _cart = ScannerCartController(
    repository: widget.repository ?? ScannerProductRepository(),
    initialCart: widget.initialCart,
  );
  Timer? _toastTimer;
  ScanOutcome? _outcome;
  bool _manualOpen = false;
  bool _finishing = false;
  final _cameraKey = GlobalKey<LiveScannerCameraState>();
  final _addSound = PosAddSound();

  @override
  void initState() {
    super.initState();
    _cart.addListener(_cartChanged);
  }

  void _cartChanged() {
    if (mounted) setState(() {});
  }

  @override
  void dispose() {
    _toastTimer?.cancel();
    _cart.removeListener(_cartChanged);
    _cart.dispose();
    unawaited(_addSound.dispose());
    super.dispose();
  }

  void _showOutcome(ScanOutcome outcome) {
    if (!mounted || _finishing) return;
    if (outcome.ok) {
      HapticFeedback.lightImpact();
      if (widget.onProductAdded != null) {
        widget.onProductAdded!();
      } else {
        unawaited(_addSound.play());
      }
    }
    _toastTimer?.cancel();
    setState(() => _outcome = outcome);
    _toastTimer = Timer(const Duration(seconds: 3), () {
      if (mounted) setState(() => _outcome = null);
    });
  }

  Future<void> _scan(String code, {bool manual = false}) async {
    if (_finishing || (_manualOpen && !manual)) return;
    final outcome = await _cart.scanCode(code, manual: manual);
    if (outcome != null) _showOutcome(outcome);
  }

  Future<void> _manualSearch() async {
    if (_cart.busy || _manualOpen || _finishing) return;
    setState(() => _manualOpen = true);
    final selected = await showModalBottomSheet<Object>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: AppColors.surface,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (_) => ScannerProductSearchSheet(repository: _cart.repository),
    );
    if (!mounted) return;
    if (selected is Map<String, dynamic>) {
      _showOutcome(_cart.addProduct(selected));
    } else if (selected is String) {
      await _scan(selected, manual: true);
    }
    if (mounted) setState(() => _manualOpen = false);
  }

  Future<void> _finish() async {
    if (_cart.busy || _finishing || _manualOpen) return;
    setState(() => _finishing = true);
    try {
      await _cameraKey.currentState?.stopForExit();
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'The camera could not stop. Close this browser tab to release it.',
          ),
        ),
      );
      return;
    }
    if (!mounted) return;
    // Back/close also returns the session so scanned items are not lost.
    Navigator.of(context).pop(_cart.snapshot());
  }

  @override
  Widget build(BuildContext context) => PopScope<List<PosCartItem>>(
    canPop: false,
    onPopInvokedWithResult: (didPop, result) {
      if (!didPop) _finish();
    },
    child: Scaffold(
      backgroundColor: _panelColor,
      body: SafeArea(
        child: LayoutBuilder(
          builder: (context, constraints) {
            if (constraints.maxWidth > constraints.maxHeight) {
              return Row(
                children: [
                  Expanded(child: _camera()),
                  SizedBox(
                    width: constraints.maxWidth * 0.46,
                    child: _cartPanel(),
                  ),
                ],
              );
            }
            return Column(
              children: [
                Expanded(child: _camera()),
                Expanded(child: _cartPanel()),
              ],
            );
          },
        ),
      ),
    ),
  );

  Widget _camera() => Stack(
    fit: StackFit.expand,
    children: [
      widget.cameraBuilder?.call(context, _scan, _manualOpen || _finishing) ??
          LiveScannerCamera(
            key: _cameraKey,
            onCode: _scan,
            paused: _manualOpen || _finishing,
          ),
      const IgnorePointer(child: CustomPaint(painter: _FramePainter())),
      Positioned(
        top: 8,
        left: 8,
        right: 8,
        child: Row(
          children: [
            IconButton.filledTonal(
              tooltip: 'Finish and close scanner',
              onPressed: _cart.busy || _finishing ? null : _finish,
              style: IconButton.styleFrom(
                backgroundColor: Colors.black54,
                foregroundColor: Colors.white,
              ),
              icon: const Icon(Icons.close_rounded),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 12,
                ),
                decoration: BoxDecoration(
                  color: Colors.black.withValues(alpha: 0.65),
                  borderRadius: BorderRadius.circular(32),
                ),
                child: Row(
                  children: [
                    Icon(
                      Icons.circle,
                      color: _cart.busy
                          ? AppColors.warning
                          : const Color(0xFF5FE198),
                      size: 9,
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        _cart.busy
                            ? 'Looking up product...'
                            : 'Ready · scan a barcode',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 13,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
      if (_outcome != null)
        Positioned(
          bottom: 58,
          left: 12,
          right: 12,
          child: Container(
            key: const ValueKey('scanner-feedback'),
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: _outcome!.ok ? AppColors.success : AppColors.error,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Text(
              _outcome!.message,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(color: Colors.white, fontSize: 13),
            ),
          ),
        ),
    ],
  );

  Widget _cartPanel() => Material(
    color: _panelColor,
    borderRadius: const BorderRadius.vertical(top: Radius.circular(28)),
    clipBehavior: Clip.antiAlias,
    child: Padding(
      padding: const EdgeInsets.fromLTRB(14, 10, 14, 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Center(
            child: Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: Colors.white30,
                borderRadius: BorderRadius.circular(4),
              ),
            ),
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              const Icon(
                Icons.shopping_cart_outlined,
                color: AppColors.primary,
                size: 24,
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Cart (${_cart.count})',
                      key: const ValueKey('scanner-cart-count'),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 17,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const Text(
                      'Live scan session',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(color: Colors.white60, fontSize: 11),
                    ),
                  ],
                ),
              ),
              SizedBox(
                width: 140,
                child: FilledButton.icon(
                  key: const ValueKey('finish-scanning'),
                  onPressed: _cart.busy || _finishing ? null : _finish,
                  style: FilledButton.styleFrom(
                    backgroundColor: AppColors.primaryDk,
                    foregroundColor: Colors.white,
                    disabledBackgroundColor: AppColors.primaryDk.withValues(
                      alpha: 0.5,
                    ),
                    disabledForegroundColor: Colors.white60,
                    padding: const EdgeInsets.symmetric(horizontal: 12),
                    minimumSize: const Size(0, 44),
                  ),
                  icon: const Icon(Icons.check_rounded, size: 16),
                  label: Text(
                    _finishing ? 'Closing camera...' : 'Finish scanning',
                    style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Expanded(
            child: _cart.items.isEmpty
                ? const Center(
                    child: Text(
                      'Scan a barcode to add your first item',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: Colors.white60, fontSize: 13),
                    ),
                  )
                : ListView.separated(
                    itemCount: _cart.items.length,
                    separatorBuilder: (_, index) => const SizedBox(height: 8),
                    itemBuilder: (context, index) =>
                        _cartLine(_cart.items[index]),
                  ),
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                child: Text(
                  'TOTAL · ${_cart.count} ${_cart.count == 1 ? 'item' : 'items'}',
                  style: const TextStyle(
                    color: Colors.white70,
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
              Text(
                formatMoney(_cart.total),
                key: const ValueKey('scanner-cart-total'),
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 18,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              key: const ValueKey('scanner-manual-search'),
              onPressed: _cart.busy || _finishing ? null : _manualSearch,
              style: OutlinedButton.styleFrom(
                foregroundColor: const Color(0xFF94BFFF),
                disabledForegroundColor: Colors.white30,
                side: const BorderSide(color: Colors.white30),
                minimumSize: const Size(0, 46),
                padding: const EdgeInsets.symmetric(horizontal: 8),
              ),
              icon: const Icon(Icons.search_rounded, size: 18),
              label: const Text(
                'Search or add product manually',
                style: TextStyle(fontSize: 12.5),
              ),
            ),
          ),
        ],
      ),
    ),
  );

  Widget _cartLine(PosCartItem item) => Container(
    key: ValueKey('scanner-cart-item-${item.id}'),
    padding: const EdgeInsets.fromLTRB(12, 10, 4, 6),
    decoration: BoxDecoration(
      color: const Color(0xFF303146),
      borderRadius: BorderRadius.circular(16),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          item.name,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(
            color: Colors.white,
            fontSize: 14,
            fontWeight: FontWeight.w600,
          ),
        ),
        Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    '${item.qty} × ${formatMoney(item.effectivePrice)}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: Colors.white60, fontSize: 11),
                  ),
                  Text(
                    formatMoney(item.lineTotal),
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ),
            ),
            _quantityButton(
              Icons.remove,
              'Decrease ${item.name}',
              'scanner-minus-${item.id}',
              () => _cart.setQuantity(item.id, item.qty - 1),
            ),
            Text(
              '${item.qty}',
              key: ValueKey('scanner-quantity-${item.id}'),
              style: const TextStyle(color: Colors.white, fontSize: 14),
            ),
            _quantityButton(
              Icons.add,
              'Increase ${item.name}',
              'scanner-plus-${item.id}',
              () => _cart.setQuantity(item.id, item.qty + 1),
            ),
            _quantityButton(
              Icons.close,
              'Remove ${item.name}',
              'scanner-remove-${item.id}',
              () => _cart.remove(item.id),
            ),
          ],
        ),
      ],
    ),
  );

  Widget _quantityButton(
    IconData icon,
    String tooltip,
    String key,
    VoidCallback onTap,
  ) => IconButton(
    key: ValueKey(key),
    tooltip: tooltip,
    onPressed: _cart.busy || _finishing ? null : onTap,
    constraints: const BoxConstraints(minWidth: 36, minHeight: 40),
    padding: const EdgeInsets.all(8),
    iconSize: 18,
    color: Colors.white70,
    disabledColor: Colors.white30,
    icon: Icon(icon),
  );
}

class _FramePainter extends CustomPainter {
  const _FramePainter();

  @override
  void paint(Canvas canvas, Size size) {
    final width = size.width * 0.76;
    final frame = RRect.fromRectAndRadius(
      Rect.fromCenter(
        center: Offset(size.width / 2, size.height * 0.5),
        width: width,
        height: (width * 0.5).clamp(50, size.height * 0.42),
      ),
      const Radius.circular(18),
    );
    canvas.drawRRect(
      frame,
      Paint()
        ..color = Colors.white70
        ..style = PaintingStyle.stroke
        ..strokeWidth = 2,
    );
    canvas.drawLine(
      Offset(frame.left + 8, frame.center.dy),
      Offset(frame.right - 8, frame.center.dy),
      Paint()
        ..color = const Color(0xFFFF6175)
        ..strokeWidth = 2,
    );
  }

  @override
  bool shouldRepaint(_FramePainter oldDelegate) => false;
}
