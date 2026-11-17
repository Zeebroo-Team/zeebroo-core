import 'package:audioplayers/audioplayers.dart';
import 'package:flutter/foundation.dart';

/// Lazily prepares the existing POS beep, including after a hot reload.
class PosAddSound {
  Future<AudioPool?>? _pool;
  bool _disposed = false;

  Future<AudioPool?> _prepare() async {
    try {
      return await AudioPool.createFromAsset(
        path: 'sounds/beep.wav',
        minPlayers: 1,
        maxPlayers: 4,
      );
    } catch (error) {
      debugPrint('Could not prepare POS beep: $error');
      return null;
    }
  }

  Future<void> play() async {
    if (_disposed) return;
    try {
      final pool = await (_pool ??= _prepare());
      if (!_disposed && pool != null) await pool.start();
    } catch (error) {
      debugPrint('Could not play POS beep: $error');
    }
  }

  Future<void> dispose() async {
    _disposed = true;
    try {
      final pool = await _pool;
      await pool?.dispose();
    } catch (error) {
      debugPrint('Could not dispose POS beep: $error');
    }
  }
}
