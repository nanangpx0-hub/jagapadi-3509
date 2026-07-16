<div class="card">
    <h2>Selamat Datang, <?= \App\Core\Security::e($nama_lengkap) ?></h2>
    <div class="info-grid">
        <div class="info-item">
            <div class="label">Username</div>
            <div class="value"><?= \App\Core\Security::e($username) ?></div>
        </div>
        <div class="info-item">
            <div class="label">Role</div>
            <div class="value"><?= \App\Core\Security::e($role) ?></div>
        </div>
        <div class="info-item">
            <div class="label">Nama Lengkap</div>
            <div class="value"><?= \App\Core\Security::e($nama_lengkap) ?></div>
        </div>
        <div class="info-item">
            <div class="label">Status</div>
            <div class="value">Online</div>
        </div>
    </div>
</div>
