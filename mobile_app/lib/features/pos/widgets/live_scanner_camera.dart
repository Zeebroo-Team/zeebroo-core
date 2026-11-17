import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

/// Keeps the preview alive across scans, but releases it when the app is hidden
/// or manual search covers the scanner. Unsupported platforms still allow search.
class LiveScannerCamera extends StatefulWidget {
  const LiveScannerCamera({
    super.key,
    required this.onCode,
    this.paused = false,
  });

  final ValueChanged<String> onCode;
  final bool paused;

  @override
  State<LiveScannerCamera> createState() => LiveScannerCameraState();
}

class LiveScannerCameraState extends State<LiveScannerCamera>
    with WidgetsBindingObserver {
  late final MobileScannerController _controller = MobileScannerController(
    autoStart: false,
    detectionSpeed: DetectionSpeed.normal,
    detectionTimeoutMs: 250,
  );
  bool _foreground = true;
  bool _disposed = false;
  bool _closing = false;
  Future<void> _operation = Future.value();
  Future<void>? _exitStop;
  late final bool _supported =
      kIsWeb ||
      defaultTargetPlatform == TargetPlatform.android ||
      defaultTargetPlatform == TargetPlatform.iOS ||
      defaultTargetPlatform == TargetPlatform.macOS;

  @override
  void initState() {
    super.initState();
    _foreground =
        WidgetsBinding.instance.lifecycleState == null ||
        WidgetsBinding.instance.lifecycleState == AppLifecycleState.resumed;
    WidgetsBinding.instance.addObserver(this);
    if (_supported) {
      if (kIsWeb) {
        // mobile_scanner 7.4.2's polling readers stop decoding but leave their
        // MediaStream tracks live. ZXing JS resets/stops those tracks as well.
        // Remove this workaround once the polling-reader cleanup is fixed.
        MobileScannerPlatform.instance.setWebBarcodeReader(
          WebBarcodeReader.zxingJs,
        );
      }
      _controller.addListener(_cameraChanged);
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!_disposed && !_closing) _syncCamera();
      });
    }
  }

  bool get _shouldRun =>
      !_disposed && !_closing && _foreground && !widget.paused;

  void _cameraChanged() {
    // A permission prompt can finish after a sheet opens or the app is hidden.
    if (!_disposed &&
        _controller.value.isRunning &&
        !_closing &&
        (!_foreground || widget.paused)) {
      _syncCamera();
    }
  }

  @override
  void didUpdateWidget(LiveScannerCamera oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.paused != widget.paused) _syncCamera();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    _foreground = state == AppLifecycleState.resumed;
    // Permission prompts can fire before the controller is ready.
    if (_supported && _controller.value.hasCameraPermission) _syncCamera();
  }

  void _syncCamera() {
    if (!_supported) return;
    // Serialize start/stop operations so rapid resume/search/close cannot race.
    _operation = _operation.then((_) async {
      if (_disposed || _closing) return;
      try {
        if (_shouldRun) {
          if (!_controller.value.isRunning) await _controller.start();
          // Finish/search/background can happen while permission or startup
          // is pending. Release that late stream instead of leaving it live.
          if (!_shouldRun) await _controller.stop();
        } else {
          await _controller.stop();
        }
      } catch (_) {
        // The preview's errorBuilder explains permission/camera failures.
      }
    });
  }

  /// Permanently stop this session before its route is popped. In-flight
  /// starts/actions finish first, and cannot restart the camera afterwards.
  Future<void> stopForExit() {
    _closing = true;
    if (!_supported) return Future.value();
    return _exitStop ??= _operation = _operation.then((_) async {
      await _controller.stop();
    });
  }

  Future<void> _disposeCamera() async {
    try {
      await _operation;
      await _controller.stop();
    } catch (error) {
      debugPrint('Could not stop scanner camera: $error');
    } finally {
      try {
        await _controller.dispose();
      } catch (error) {
        debugPrint('Could not dispose scanner camera: $error');
      }
    }
  }

  @override
  void dispose() {
    _disposed = true;
    WidgetsBinding.instance.removeObserver(this);
    if (_supported) {
      _controller.removeListener(_cameraChanged);
      unawaited(_disposeCamera());
    }
    super.dispose();
  }

  Future<void> _cameraAction(Future<void> Function() action) async {
    if (_disposed || _closing) return;
    _operation = _operation.then((_) async {
      if (!_shouldRun) return;
      try {
        await action();
        if (!_shouldRun) await _controller.stop();
      } catch (_) {
        // No camera/torch on this device; keep manual entry accessible.
      }
    });
    await _operation;
  }

  @override
  Widget build(BuildContext context) {
    if (!_supported) {
      return const _CameraProblem(
        message:
            'Live camera scanning is unavailable on this platform. Use manual product search below.',
      );
    }
    return Stack(
      fit: StackFit.expand,
      children: [
        MobileScanner(
          controller: _controller,
          onDetect: (capture) {
            if (!_shouldRun) return;
            for (final barcode in capture.barcodes) {
              final code = barcode.rawValue?.trim() ?? '';
              if (code.isNotEmpty) {
                widget.onCode(code);
                break;
              }
            }
          },
          errorBuilder: (context, error) => _CameraProblem(
            message: error.errorCode == MobileScannerErrorCode.permissionDenied
                ? 'Allow camera access to scan barcodes, or use manual product search below.'
                : 'The camera could not start. Check its permission or use manual product search below.',
          ),
        ),
        Positioned(
          bottom: 8,
          right: 8,
          child: ValueListenableBuilder<MobileScannerState>(
            valueListenable: _controller,
            builder: (context, state, child) => Row(
              children: [
                if (state.torchState != TorchState.unavailable)
                  IconButton.filledTonal(
                    tooltip: state.torchState == TorchState.on
                        ? 'Torch off'
                        : 'Torch on',
                    onPressed: !state.isRunning || !_shouldRun
                        ? null
                        : () => _cameraAction(_controller.toggleTorch),
                    style: IconButton.styleFrom(
                      backgroundColor: Colors.black54,
                      foregroundColor: Colors.white,
                    ),
                    icon: Icon(
                      state.torchState == TorchState.on
                          ? Icons.flash_on
                          : Icons.flash_off,
                    ),
                  ),
                IconButton.filledTonal(
                  tooltip: 'Switch camera',
                  onPressed: !state.isRunning || !_shouldRun
                      ? null
                      : () => _cameraAction(_controller.switchCamera),
                  style: IconButton.styleFrom(
                    backgroundColor: Colors.black54,
                    foregroundColor: Colors.white,
                  ),
                  icon: const Icon(Icons.cameraswitch_outlined),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class _CameraProblem extends StatelessWidget {
  const _CameraProblem({required this.message});
  final String message;

  @override
  Widget build(BuildContext context) => ColoredBox(
    color: const Color(0xFF252636),
    child: Center(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 28),
        child: Text(
          message,
          textAlign: TextAlign.center,
          style: const TextStyle(
            color: Colors.white,
            fontSize: 13,
            height: 1.5,
          ),
        ),
      ),
    ),
  );
}
