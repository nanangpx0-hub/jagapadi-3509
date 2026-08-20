import 'package:flutter_test/flutter_test.dart';
import 'package:jagapadi_mobile/core/operation_id.dart';

void main() {
  test('menghasilkan id unik dengan format op-', () {
    final id = OperationId.generate();
    expect(id, startsWith('op-'));
  });

  test('panjang 32 karakter hex setelah awalan', () {
    final id = OperationId.generate();
    final body = id.substring(3);
    expect(body.length, 32);
    expect(RegExp(r'^[0-9a-f]{32}$').hasMatch(body), isTrue);
  });

  test('dua pemanggilan menghasilkan id berbeda', () {
    expect(OperationId.generate(), isNot(OperationId.generate()));
  });
}
