import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';

import '../auth/auth_storage.dart';
import '../business/business_storage.dart';
import 'api_endpoints.dart';

class ApiClient {
  ApiClient._();
  static final ApiClient instance = ApiClient._();

  late final Dio _dio = _build();

  Dio _build() {
    final dio = Dio(
      BaseOptions(
        baseUrl: ApiEndpoints.baseUrl,
        connectTimeout: const Duration(seconds: 15),
        receiveTimeout: const Duration(seconds: 20),
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
      ),
    );

    dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          final token = await AuthStorage.getToken();
          if (token != null) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          // Same header the desktop app sends — tells the server which
          // business/branch to resolve data for. A caller that already set
          // one explicitly (e.g. previewing branches for a business that
          // isn't the current selection yet) wins over the stored default.
          if (!options.headers.containsKey('X-Business-Id')) {
            final businessId = await BusinessStorage.getBusinessId();
            if (businessId != null) {
              options.headers['X-Business-Id'] = businessId.toString();
            }
          }
          if (!options.headers.containsKey('X-Branch-Id')) {
            final branchId = await BusinessStorage.getBranchId();
            if (branchId != null) {
              options.headers['X-Branch-Id'] = branchId.toString();
            }
          }
          handler.next(options);
        },
      ),
    );

    if (kDebugMode) {
      dio.interceptors.add(
        LogInterceptor(requestBody: true, responseBody: true),
      );
    }

    return dio;
  }

  Future<Response> get(
    String path, {
    Map<String, dynamic>? params,
    Map<String, dynamic>? headers,
  }) => _dio.get(
    path,
    queryParameters: params,
    options: headers != null ? Options(headers: headers) : null,
  );

  Future<Response> post(String path, {dynamic data}) =>
      _dio.post(path, data: data);

  Future<Response> put(String path, {dynamic data}) =>
      _dio.put(path, data: data);

  Future<Response> patch(String path, {dynamic data}) =>
      _dio.patch(path, data: data);

  Future<Response> delete(String path, {dynamic data}) =>
      _dio.delete(path, data: data);

  Future<Response> postMultipart(String path, FormData data) => _dio.post(
    path,
    data: data,
    options: Options(contentType: 'multipart/form-data'),
  );
}

/// Turns a [DioException] (or any error) into a user-facing message.
String apiErrorMessage(Object err) {
  if (err is DioException) {
    final data = err.response?.data;
    if (data is Map) {
      final errors = data['errors'];
      if (errors is Map && errors.isNotEmpty) {
        final first = errors.values.first;
        if (first is List && first.isNotEmpty) return first.first.toString();
      }
      final msg = data['message'] as String?;
      if (msg != null && msg.isNotEmpty) return msg;
    }
    switch (err.type) {
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.receiveTimeout:
      case DioExceptionType.sendTimeout:
        return 'Connection timed out. Check your internet connection.';
      case DioExceptionType.connectionError:
        return 'Could not reach the server.';
      default:
        return 'Something went wrong (${err.response?.statusCode ?? 'no response'}).';
    }
  }
  return err.toString();
}
