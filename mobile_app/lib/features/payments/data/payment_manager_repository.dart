import '../../../core/api/api_client.dart';

class PaymentManagerRepository {
  const PaymentManagerRepository();

  Future<dynamic> get(String path) async =>
      (await ApiClient.instance.get(path, bypassCache: true)).data;

  Future<void> save(String path, Map<String, dynamic> fields, {int? id}) async {
    if (id == null) {
      await ApiClient.instance.post(path, data: fields);
    } else {
      await ApiClient.instance.put('$path/$id', data: fields);
    }
  }

  Future<void> delete(String path, int id) async {
    await ApiClient.instance.delete('$path/$id');
  }
}
