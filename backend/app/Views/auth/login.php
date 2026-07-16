<form method="POST" action="/login">
    <?= \App\Core\Security::csrfField() ?>

    <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" placeholder="Masukkan username" required autofocus>
    </div>

    <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" placeholder="Masukkan password" required>
    </div>

    <button type="submit" class="btn btn-primary">Login</button>
</form>
