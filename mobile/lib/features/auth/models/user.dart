class User {
  final int id;
  final String username;
  final String namaLengkap;
  final String role;
  final bool isActive;
  final bool mustChangePassword;

  User({
    required this.id,
    required this.username,
    required this.namaLengkap,
    required this.role,
    required this.isActive,
    required this.mustChangePassword,
  });

  factory User.fromJson(Map<String, dynamic> json) {
    return User(
      id: json['id'] as int? ?? 0,
      username: json['username'] as String? ?? '',
      namaLengkap: json['nama_lengkap'] as String? ?? '',
      role: json['role'] as String? ?? 'petugas',
      isActive: json['aktif'] as bool? ?? true,
      mustChangePassword: json['must_change_password'] as bool? ?? false,
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'username': username,
        'nama_lengkap': namaLengkap,
        'role': role,
        'aktif': isActive,
        'must_change_password': mustChangePassword,
      };

  bool get isAdmin => role == 'admin';
}
