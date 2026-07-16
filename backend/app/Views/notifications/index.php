<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px">
        <h2>Notifikasi</h2>
        <div style="display:flex;gap:8px">
            <a href="/notifications" class="btn-logout" style="
                background:#1a73e8;color:#fff;display:inline-block;padding:8px 16px;
                border-radius:6px;font-size:13px;text-decoration:none;
                <?= $unreadOnly === null || $unreadOnly === false ? 'opacity:1' : 'opacity:0.6' ?>
            ">Semua</a>
            <a href="/notifications?unread=1" class="btn-logout" style="
                background:#1a73e8;color:#fff;display:inline-block;padding:8px 16px;
                border-radius:6px;font-size:13px;text-decoration:none;
                <?= $unreadOnly === true ? 'opacity:1' : 'opacity:0.6' ?>
            ">Belum Dibaca</a>
            <form action="/notifications/read-all" method="POST" style="display:inline">
                <?= \App\Core\Security::csrfField() ?>
                <button type="submit" class="btn-logout" style="background:#34a853;color:#fff;border:none;padding:8px 16px;border-radius:6px;font-size:13px;cursor:pointer">Tandai Semua Dibaca</button>
            </form>
        </div>
    </div>

    <?php if (count($notifications) === 0): ?>
        <p style="text-align:center;color:#888;padding:40px 0;font-size:14px">Belum ada notifikasi.</p>
    <?php else: ?>
        <?php foreach ($notifications as $n): ?>
            <div style="
                padding:12px 16px;
                margin-bottom:6px;
                background: <?= $n['read_at'] === null ? '#f0f7ff' : '#fff' ?>;
                border-radius:6px;
                border: 1px solid <?= $n['read_at'] === null ? '#cce5ff' : '#e0e0e0' ?>;
                display:flex;align-items:flex-start;gap:12px;
            ">
                <div style="flex:1;min-width:0">
                    <div style="font-size:14px;font-weight:600;color:#333;margin-bottom:2px">
                        <?= \App\Core\Security::e($n['title']) ?>
                    </div>
                    <div style="font-size:13px;color:#666;margin-bottom:4px">
                        <?= \App\Core\Security::e($n['body']) ?>
                    </div>
                    <div style="font-size:11px;color:#999">
                        <?= \App\Core\Security::e($n['created_at']) ?>
                        <?php if ($n['data']['web_path'] ?? null): ?>
                            &middot;
                            <a href="<?= \App\Core\Security::e($n['data']['web_path']) ?>" style="color:#1a73e8;text-decoration:none"
                               onclick="markReadAndNavigate(event, this.href, <?= (int) $n['id'] ?>)">
                                Lihat Detail
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="display:flex;flex-direction:column;gap:4px;flex-shrink:0">
                    <?php if ($n['read_at'] === null): ?>
                        <form action="/notifications/<?= (int) $n['id'] ?>/read" method="POST" style="display:inline">
                            <?= \App\Core\Security::csrfField() ?>
                            <input type="hidden" name="redirect" value="/notifications">
                            <button type="submit" style="
                                background:none;border:1px solid #1a73e8;color:#1a73e8;
                                padding:4px 10px;border-radius:4px;font-size:11px;cursor:pointer
                            ">Baca</button>
                        </form>
                    <?php endif; ?>
                    <form action="/notifications/<?= (int) $n['id'] ?>/delete" method="POST" style="display:inline">
                        <?= \App\Core\Security::csrfField() ?>
                        <button type="submit" style="
                            background:none;border:1px solid #ccc;color:#999;
                            padding:4px 10px;border-radius:4px;font-size:11px;cursor:pointer
                        " onclick="return confirm('Hapus notifikasi ini?')">Hapus</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if ($meta['total'] > $meta['limit']): ?>
            <div style="display:flex;justify-content:center;gap:8px;margin-top:16px">
                <?php
                $lastPage = max(1, (int) ceil($meta['total'] / $meta['limit']));
                $currentPage = $meta['page'];
                $urlPrefix = '/notifications' . ($unreadOnly ? '?unread=1' : '');
                ?>
                <?php if ($currentPage > 1): ?>
                    <a href="<?= $urlPrefix ?>&page=<?= $currentPage - 1 ?>" class="btn-logout" style="background:#eee;color:#333;text-decoration:none;padding:8px 16px;border-radius:6px;font-size:13px">← Sebelumnya</a>
                <?php endif; ?>
                <span style="padding:8px 0;font-size:13px;color:#666">
                    Halaman <?= $currentPage ?> dari <?= $lastPage ?> (<?= $meta['total'] ?> notifikasi)
                </span>
                <?php if ($currentPage < $lastPage): ?>
                    <a href="<?= $urlPrefix ?>&page=<?= $currentPage + 1 ?>" class="btn-logout" style="background:#eee;color:#333;text-decoration:none;padding:8px 16px;border-radius:6px;font-size:13px">Selanjutnya →</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
function markReadAndNavigate(event, url, id) {
    event.preventDefault();
    fetch('/notifications/' + id + '/read', {
        method: 'POST',
        headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')},
        body: new URLSearchParams({redirect: url})
    }).then(function() {
        window.location.href = url;
    }).catch(function() {
        window.location.href = url;
    });
}
</script>
