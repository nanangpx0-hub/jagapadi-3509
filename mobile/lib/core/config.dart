import 'dart:io';

class AppConfig {
  static String get baseUrl {
    const defined = String.fromEnvironment('API_BASE_URL');
    if (defined.isNotEmpty) return defined;
    if (Platform.isAndroid) return 'http://10.0.2.2:8080/api/v1';
    return 'http://localhost:8080/api/v1';
  }

  static const int connectTimeout = 15000;
  static const int receiveTimeout = 30000;
  static const int uploadTimeout = 120000;
  static const int notifPollIntervalSec = 60;
  static const int maxFotoSizeMB = 10;
}
