<div class="card">
    <h2>Profil Saya</h2>
    <div class="info-grid">
        <div class="info-item">
            <div class="label">Username</div>
            <div class="value"><?= \App\Core\Security::e($user['username'] ?? '') ?></div>
        </div>
        <div class="info-item">
            <div class="label">Nama Lengkap</div>
            <div class="value"><?= \App\Core\Security::e($user['nama_lengkap'] ?? '') ?></div>
        </div>
        <div class="info-item">
            <div class="label">Email</div>
            <div class="value"><?= \App\Core\Security::e($user['email'] ?? '-') ?></div>
        </div>
        <div class="info-item">
            <div class="label">Role</div>
            <div class="value"><?= \App\Core\Security::e($user['role'] ?? '') ?></div>
        </div>
        <div class="info-item">
            <div class="label">Status</div>
            <div class="value"><?= ($user['aktif'] ?? false) ? 'Aktif' : 'Tidak Aktif' ?></div>
        </div>
        <div class="info-item">
            <div class="label">Terdaftar Sejak</div>
            <div class="value"><?= \App\Core\Security::e($user['created_at'] ?? '') ?></div>
        </div>
    </div>
</div>

<div class="card">
    <h2>Akun</h2>
    <a href="/password/change" class="btn btn-primary" style="width:auto;display:inline-block;text-decoration:none;">Ganti Password</a>
</div>
