import 'dart:math';

import 'package:dio/dio.dart';

import '../api/api_client.dart';

/// Shared by the offline outboxes (sales, customers, expenses).

const offlineNoConnectionMessage = 'No connection — offline records will upload when you’re back online.';

/// A random RFC 4122 v4 id; the server uses it to recognise a retried upload.
String newUuidV4() {
  final rnd = Random.secure();
  final b = List<int>.generate(16, (_) => rnd.nextInt(256));
  b[6] = (b[6] & 0x0f) | 0x40;
  b[8] = (b[8] & 0x3f) | 0x80;
  final h = b.map((x) => x.toRadixString(16).padLeft(2, '0')).join();
  return '${h.substring(0, 8)}-${h.substring(8, 12)}-${h.substring(12, 16)}-${h.substring(16, 20)}-${h.substring(20)}';
}

/// True only when the request never reached the server (no network, DNS,
/// refused). A timeout *after* sending is excluded: the server may already
/// have saved the record, and queuing it again would risk a duplicate.
bool couldNotReachServer(Object e) =>
    e is DioException &&
    e.response == null &&
    (e.type == DioExceptionType.connectionError || e.type == DioExceptionType.connectionTimeout);

/// What an outbox does after one upload attempt failed.
enum UploadFailure {
  /// No connection: keep this and everything after it waiting.
  offline,

  /// Nothing will go through right now (subscription locked, server error,
  /// server too old): stop this outbox, retry later.
  stop,

  /// The server refused this one record: mark it "needs attention", go on.
  rejected,
}

/// Sorts an upload error into [UploadFailure] with a message for the user.
/// [what] names the record type for the "server too old" message.
(UploadFailure, String) classifyUploadError(DioException e, String what) {
  final status = e.response?.statusCode;
  if (status == null) return (UploadFailure.offline, offlineNoConnectionMessage);
  if (status == 404 || status == 405) {
    return (UploadFailure.stop, 'The server doesn’t support offline $what yet. Update the server, then upload again.');
  }
  if (status == 422 || status == 403) return (UploadFailure.rejected, apiErrorMessage(e));
  return (UploadFailure.stop, apiErrorMessage(e)); // 402, 5xx, …
}
