# Flutter Wrapper
-keep class io.flutter.plugin.** { *; }
-keep class io.flutter.util.** { *; }
-keep class io.flutter.view.** { *; }
-keep class io.flutter.embedding.** { *; }
-keep class io.flutter.provider.** { *; }

# Flutter Play Store Split Compat (Deferred Components R8 Rules)
-dontwarn com.google.android.play.core.**
-dontwarn io.flutter.embedding.engine.deferredcomponents.**

# Flutter Secure Storage
-keep class com.it_ne.flutter_secure_storage.** { *; }

# Firebase Messaging
-keep class com.google.firebase.** { *; }

# Dio HTTP Client
-keep class com.dart.dio.** { *; }

# Model Classes
-keep class com.jagapadi.app.models.** { *; }
