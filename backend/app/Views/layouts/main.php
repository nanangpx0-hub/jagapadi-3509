<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= \App\Core\Security::csrfToken() ?>">
    <title><?= $pageTitle ?? 'Dashboard' ?> — JAGAPADI</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f0f2f5; color: #333;
        }
        .navbar {
            background: #1a73e8; color: #fff; padding: 0 24px;
            display: flex; align-items: center; justify-content: space-between;
            height: 56px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .navbar-brand { font-size: 18px; font-weight: 700; color: #fff; text-decoration: none; }
        .navbar-menu { display: flex; align-items: center; gap: 16px; }
        .navbar-user { font-size: 14px; }
        .navbar-user .role { font-size: 12px; opacity: 0.8; }
        .btn-logout {
            background: rgba(255,255,255,0.15); color: #fff; border: none;
            padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 13px;
            text-decoration: none;
        }
        .btn-logout:hover { background: rgba(255,255,255,0.25); }
        .container { max-width: 1200px; margin: 0 auto; padding: 24px; }
        .flash-message {
            padding: 12px 16px; border-radius: 6px; margin-bottom: 16px;
            font-size: 14px; line-height: 1.4;
        }
        .flash-error { background: #fce4ec; color: #c62828; border: 1px solid #f8bbd0; }
        .flash-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .flash-warning { background: #fff3e0; color: #e65100; border: 1px solid #ffe0b2; }
        .card {
            background: #fff; border-radius: 8px; padding: 24px;
            box-shadow: 0 1px 6px rgba(0,0,0,0.06); margin-bottom: 16px;
        }
        .card h2 { font-size: 18px; margin-bottom: 12px; color: #1a73e8; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .info-item { padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
        .info-item .label { font-size: 12px; color: #888; text-transform: uppercase; }
        .info-item .value { font-size: 15px; font-weight: 500; margin-top: 2px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 14px; font-weight: 500; margin-bottom: 6px; color: #555; }
        .form-group input {
            width: 100%; padding: 10px 14px; border: 1px solid #d0d0d0;
            border-radius: 6px; font-size: 15px; transition: border-color .2s;
        }
        .form-group input:focus { outline: none; border-color: #1a73e8; box-shadow: 0 0 0 3px rgba(26,115,232,0.1); }
        .btn {
            width: 100%; padding: 12px; border: none; border-radius: 6px;
            font-size: 15px; font-weight: 600; cursor: pointer; transition: background .2s;
        }
        .btn-primary { background: #1a73e8; color: #fff; }
        .btn-primary:hover { background: #1557b0; }
        @media (max-width: 640px) {
            .info-grid { grid-template-columns: 1fr; }
            .container { padding: 16px; }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="/dashboard" class="navbar-brand">JAGAPADI</a>
        <div class="navbar-menu">
            <span class="navbar-user">
                <?= \App\Core\Security::e($_SESSION['nama_lengkap'] ?? '') ?>
                <span class="role">(<?= \App\Core\Security::e($_SESSION['role'] ?? '') ?>)</span>
            </span>
            <form action="/logout" method="POST" style="display:inline">
                <?= \App\Core\Security::csrfField() ?>
                <button type="submit" class="btn-logout">Logout</button>
            </form>
        </div>
    </nav>

    <div class="container">
        <?php if (!empty($_SESSION['flash_error'])): ?>
            <div class="flash-message flash-error"><?= \App\Core\Security::e($_SESSION['flash_error']) ?></div>
            <?php unset($_SESSION['flash_error']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div class="flash-message flash-success"><?= \App\Core\Security::e($_SESSION['flash_success']) ?></div>
            <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['flash_warning'])): ?>
            <div class="flash-message flash-warning"><?= \App\Core\Security::e($_SESSION['flash_warning']) ?></div>
            <?php unset($_SESSION['flash_warning']); ?>
        <?php endif; ?>

        <?= $content ?? '' ?>
    </div>
</body>
</html>
