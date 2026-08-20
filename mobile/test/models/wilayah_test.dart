import 'package:flutter_test/flutter_test.dart';
import 'package:jagapadi_mobile/features/wilayah/models/wilayah.dart';

void main() {
  test('model wilayah menerima ID integer dari API', () {
    expect(Kabupaten.fromJson({'id': 1, 'nama_kabupaten': 'Jember'}).id, 1);
  });

  test('model wilayah menerima ID string dari PDO/API', () {
    expect(Kabupaten.fromJson({'id': '1', 'nama_kabupaten': 'Jember'}).id, 1);
    expect(Kecamatan.fromJson({'id': '11', 'nama_kecamatan': 'Ajung'}).id, 11);
    expect(Desa.fromJson({'id': '111', 'nama_desa': 'Ajung'}).id, 111);
  });
}
