<div class="card">
    <h2>Ganti Password</h2>
    <p style="margin-bottom:16px;color:#666;font-size:14px;">Password harus minimal 8 karakter, mengandung huruf besar, huruf kecil, angka, dan karakter khusus.</p>

    <form method="POST" action="/password/change">
        <?= \App\Core\Security::csrfField() ?>

        <div class="form-group">
            <label for="current_password">Password Saat Ini</label>
            <input type="password" id="current_password" name="current_password" placeholder="Password saat ini" required>
        </div>

        <div class="form-group">
            <label for="new_password">Password Baru</label>
            <input type="password" id="new_password" name="new_password" placeholder="Password baru" required>
        </div>

        <div class="form-group">
            <label for="new_password_confirmation">Konfirmasi Password Baru</label>
            <input type="password" id="new_password_confirmation" name="new_password_confirmation" placeholder="Ulangi password baru" required>
        </div>

        <button type="submit" class="btn btn-primary">Simpan Password</button>
    </form>
</div>
