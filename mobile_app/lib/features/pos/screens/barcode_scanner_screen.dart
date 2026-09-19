import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../../core/theme/app_theme.dart';

/// What happened to a scanned code — shown as a toast over the camera preview.
class ScanOutcome {
  const ScanOutcome.added(this.message) : ok = true;
  const ScanOutcome.failed(this.message) : ok = false;

  final bool ok;
  final String message;
}

typedef ScanHandler = Future<ScanOutcome> Function(String code);

/// Full-screen barcode scanner for the POS. It keeps scanning after each item so
/// a cashier can scan several in a row; [onCode] decides what a code means
/// (look up the SKU, add to the cart) and the result is shown over the preview.
class BarcodeScannerScreen extends StatefulWidget {
  const BarcodeScannerScreen({super.key, required this.onCode});

  final ScanHandler onCode;

  @override
  State<BarcodeScannerScreen> createState() => _BarcodeScannerScreenState();
}

class _BarcodeScannerScreenState extends State<BarcodeScannerScreen> {
  static const _sameCodeCooldown = Duration(milliseconds: 1500);

  final _controller = MobileScannerController();
  Timer? _toastTimer;

  bool _busy = false;
  String? _lastCode;
  DateTime _lastAt = DateTime.fromMillisecondsSinceEpoch(0);
  ScanOutcome? _outcome;
  int _added = 0;

  @override
  void dispose() {
    _toastTimer?.cancel();
    _controller.dispose();
    super.dispose();
  }

  void _onDetect(BarcodeCapture capture) {
    for (final barcode in capture.barcodes) {
      final code = barcode.rawValue?.trim() ?? '';
      if (code.isNotEmpty) {
        _handle(code);
        return;
      }
    }
  }

  Future<void> _handle(String code) async {
    if (_busy) return;
    // The camera keeps seeing the same label; don't add it again until it has
    // been out of view or a moment has passed.
    if (code == _lastCode && DateTime.now().difference(_lastAt) < _sameCodeCooldown) return;

    _busy = true;
    _lastCode = code;
    final outcome = await widget.onCode(code);
    if (!mounted) return;

    outcome.ok ? HapticFeedback.mediumImpact() : HapticFeedback.heavyImpact();
    _toastTimer?.cancel();
    _toastTimer = Timer(const Duration(milliseconds: 2200), () {
      if (mounted) setState(() => _outcome = null);
    });
    setState(() {
      _busy = false;
      _lastAt = DateTime.now();
      _outcome = outcome;
      if (outcome.ok) _added++;
    });
  }

  Future<void> _typeCode() async {
    final code = await showDialog<String>(
      context: context,
      builder: (_) => const _TypeCodeDialog(),
    );
    if (code != null && code.trim().isNotEmpty) {
      _lastCode = null;
      await _handle(code.trim());
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: Colors.black,
    body: Stack(
      fit: StackFit.expand,
      children: [
        MobileScanner(
          controller: _controller,
          onDetect: _onDetect,
          errorBuilder: (context, error) => _CameraProblem(
            message: error.errorDetails?.message ?? 'The camera could not be started.',
            onTypeCode: _typeCode,
          ),
        ),
        const IgnorePointer(child: CustomPaint(painter: _FramePainter())),
        SafeArea(
          child: Column(
            children: [
              _buildTopBar(),
              const Spacer(),
              if (_outcome != null) _Toast(outcome: _outcome!),
              _buildBottomBar(),
            ],
          ),
        ),
      ],
    ),
  );

  Widget _buildTopBar() => Padding(
    padding: const EdgeInsets.fromLTRB(8, 8, 8, 0),
    child: Row(
      children: [
        IconButton(
          tooltip: 'Close',
          icon: const Icon(Icons.close_rounded, color: Colors.white),
          onPressed: () => Navigator.of(context).pop(),
        ),
        const Expanded(
          child: Text(
            'Scan barcode',
            textAlign: TextAlign.center,
            style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w700),
          ),
        ),
        ValueListenableBuilder<MobileScannerState>(
          valueListenable: _controller,
          builder: (context, state, _) {
            if (state.torchState == TorchState.unavailable) return const SizedBox(width: 48);
            final on = state.torchState == TorchState.on;
            return IconButton(
              tooltip: on ? 'Torch off' : 'Torch on',
              icon: Icon(on ? Icons.flash_on_rounded : Icons.flash_off_rounded, color: Colors.white),
              onPressed: _controller.toggleTorch,
            );
          },
        ),
        IconButton(
          tooltip: 'Switch camera',
          icon: const Icon(Icons.cameraswitch_rounded, color: Colors.white),
          onPressed: _controller.switchCamera,
        ),
      ],
    ),
  );

  Widget _buildBottomBar() => Container(
    margin: const EdgeInsets.fromLTRB(16, 8, 16, 16),
    padding: const EdgeInsets.fromLTRB(16, 12, 12, 12),
    decoration: BoxDecoration(color: Colors.black.withValues(alpha: 0.65), borderRadius: BorderRadius.circular(16)),
    child: Row(
      children: [
        Expanded(
          child: Text(
            _added == 0 ? 'Point the camera at a barcode' : '$_added ${_added == 1 ? 'item' : 'items'} added',
            style: const TextStyle(color: Colors.white, fontSize: 13.5, fontWeight: FontWeight.w600),
          ),
        ),
        TextButton.icon(
          onPressed: _typeCode,
          icon: const Icon(Icons.keyboard_rounded, size: 18, color: Colors.white),
          label: const Text('Type code', style: TextStyle(color: Colors.white)),
        ),
        const SizedBox(width: 4),
        FilledButton(
          onPressed: () => Navigator.of(context).pop(),
          style: FilledButton.styleFrom(backgroundColor: AppColors.primary, minimumSize: const Size(72, 40)),
          child: const Text('Done'),
        ),
      ],
    ),
  );
}

class _Toast extends StatelessWidget {
  const _Toast({required this.outcome});
  final ScanOutcome outcome;

  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.symmetric(horizontal: 16),
    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
    decoration: BoxDecoration(
      color: outcome.ok ? AppColors.success : AppColors.error,
      borderRadius: BorderRadius.circular(12),
    ),
    child: Row(
      children: [
        Icon(outcome.ok ? Icons.check_circle_rounded : Icons.error_rounded, color: Colors.white, size: 20),
        const SizedBox(width: 10),
        Expanded(
          child: Text(
            outcome.message,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(color: Colors.white, fontSize: 13.5, fontWeight: FontWeight.w600),
          ),
        ),
      ],
    ),
  );
}

class _CameraProblem extends StatelessWidget {
  const _CameraProblem({required this.message, required this.onTypeCode});
  final String message;
  final VoidCallback onTypeCode;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.symmetric(horizontal: 32),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.videocam_off_rounded, color: Colors.white70, size: 44),
          const SizedBox(height: 14),
          const Text(
            'Camera unavailable',
            style: TextStyle(color: Colors.white, fontSize: 17, fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 8),
          Text(
            '$message\nCheck the camera permission, or type the code instead.',
            textAlign: TextAlign.center,
            style: const TextStyle(color: Colors.white70, fontSize: 13, height: 1.4),
          ),
          const SizedBox(height: 18),
          FilledButton.icon(
            onPressed: onTypeCode,
            icon: const Icon(Icons.keyboard_rounded, size: 18),
            label: const Text('Type code'),
          ),
        ],
      ),
    ),
  );
}

class _TypeCodeDialog extends StatefulWidget {
  const _TypeCodeDialog();

  @override
  State<_TypeCodeDialog> createState() => _TypeCodeDialogState();
}

class _TypeCodeDialogState extends State<_TypeCodeDialog> {
  final _ctrl = TextEditingController();

  @override
  void dispose() {
    _ctrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: const Text('Enter barcode / SKU'),
    content: TextField(
      controller: _ctrl,
      autofocus: true,
      textInputAction: TextInputAction.done,
      decoration: const InputDecoration(hintText: 'e.g. TEST-003252'),
      onSubmitted: (v) => Navigator.of(context).pop(v),
    ),
    actions: [
      TextButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Cancel')),
      FilledButton(onPressed: () => Navigator.of(context).pop(_ctrl.text), child: const Text('Add')),
    ],
  );
}

/// Dims the preview around a rounded "aim here" window.
class _FramePainter extends CustomPainter {
  const _FramePainter();

  @override
  void paint(Canvas canvas, Size size) {
    final width = size.width * 0.78;
    final window = RRect.fromRectAndRadius(
      Rect.fromCenter(center: Offset(size.width / 2, size.height * 0.42), width: width, height: width * 0.55),
      const Radius.circular(18),
    );

    final dim = Path.combine(
      PathOperation.difference,
      Path()..addRect(Offset.zero & size),
      Path()..addRRect(window),
    );
    canvas.drawPath(dim, Paint()..color = Colors.black.withValues(alpha: 0.45));
    canvas.drawRRect(
      window,
      Paint()
        ..style = PaintingStyle.stroke
        ..strokeWidth = 2.5
        ..color = Colors.white.withValues(alpha: 0.9),
    );
  }

  @override
  bool shouldRepaint(_FramePainter old) => false;
}
