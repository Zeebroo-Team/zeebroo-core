import 'package:dio/dio.dart';

const _kDefaultTtl = Duration(minutes: 15);

class ApiCache {
  ApiCache._();
  static final ApiCache instance = ApiCache._();

  final Map<String, _CacheEntry> _store = {};

  dynamic get(String key) {
    final entry = _store[key];
    if (entry == null) return null;
    if (DateTime.now().isAfter(entry.expiresAt)) {
      _store.remove(key);
      return null;
    }
    return entry.data;
  }

  void set(String key, dynamic data, {Duration ttl = _kDefaultTtl}) {
    _store[key] = _CacheEntry(data: data, expiresAt: DateTime.now().add(ttl));
  }

  void invalidateAll() => _store.clear();
}

class _CacheEntry {
  const _CacheEntry({required this.data, required this.expiresAt});
  final dynamic data;
  final DateTime expiresAt;
}

/// Dio interceptor that caches GET responses for [_kDefaultTtl] and
/// invalidates the entire cache on any write (POST/PUT/PATCH/DELETE).
class CachingInterceptor extends Interceptor {
  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    if (options.method == 'GET') {
      final bypass = options.extra['bypass_cache'] == true;
      if (!bypass) {
        final cached = ApiCache.instance.get(_key(options));
        if (cached != null) {
          handler.resolve(
            Response(data: cached, statusCode: 200, requestOptions: options),
            true,
          );
          return;
        }
      }
    } else {
      ApiCache.instance.invalidateAll();
    }
    handler.next(options);
  }

  @override
  void onResponse(Response response, ResponseInterceptorHandler handler) {
    if (response.requestOptions.method == 'GET') {
      ApiCache.instance.set(_key(response.requestOptions), response.data);
    }
    handler.next(response);
  }

  static String _key(RequestOptions options) {
    final params = options.queryParameters;
    if (params.isEmpty) return options.path;
    final sorted = (params.entries.toList()
          ..sort((a, b) => a.key.compareTo(b.key)))
        .map((e) => '${e.key}=${e.value}')
        .join('&');
    return '${options.path}?$sorted';
  }
}
