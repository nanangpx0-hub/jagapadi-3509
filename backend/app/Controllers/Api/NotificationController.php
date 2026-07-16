<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\BaseApiController;
use App\Services\NotificationService;

class NotificationController extends BaseApiController
{
    public function index(): void
    {
        $currentUser = $GLOBALS['auth_user'] ?? null;
        if ($currentUser === null) {
            $this->error('Unauthenticated', 'Autentikasi diperlukan.', [], 401);
            return;
        }

        $userId = (int) $currentUser['id'];
        $service = new NotificationService();

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(50, max(1, (int) ($_GET['limit'] ?? 20)));
        $unreadOnly = isset($_GET['unread']) ? ($_GET['unread'] === '1' || $_GET['unread'] === 'true') : null;

        $result = $service->listForUser($userId, $page, $limit, $unreadOnly);
        $this->success($result['data'], 'Daftar notifikasi', [
            'page' => $result['meta']['page'],
            'limit' => $result['meta']['limit'],
            'total' => $result['meta']['total'],
            'unread' => $result['meta']['unread'],
        ]);
    }

    public function unreadCount(): void
    {
        $currentUser = $GLOBALS['auth_user'] ?? null;
        if ($currentUser === null) {
            $this->error('Unauthenticated', 'Autentikasi diperlukan.', [], 401);
            return;
        }

        $service = new NotificationService();
        $count = $service->unreadCount((int) $currentUser['id']);

        $this->success(['count' => $count], 'Unread count');
    }

    public function markRead(array $params): void
    {
        $currentUser = $GLOBALS['auth_user'] ?? null;
        if ($currentUser === null) {
            $this->error('Unauthenticated', 'Autentikasi diperlukan.', [], 401);
            return;
        }

        $id = (int) ($params['id'] ?? 0);
        $service = new NotificationService();

        $success = $service->markRead((int) $currentUser['id'], $id);

        if (!$success) {
            $this->error('NotFound', 'Notifikasi tidak ditemukan.', [], 404);
            return;
        }

        $this->success(['id' => $id], 'Notifikasi ditandai telah dibaca.');
    }

    public function markAllRead(): void
    {
        $currentUser = $GLOBALS['auth_user'] ?? null;
        if ($currentUser === null) {
            $this->error('Unauthenticated', 'Autentikasi diperlukan.', [], 401);
            return;
        }

        $service = new NotificationService();
        $count = $service->markAllRead((int) $currentUser['id']);

        $this->success(['count' => $count], 'Semua notifikasi ditandai telah dibaca.');
    }

    public function delete(array $params): void
    {
        $currentUser = $GLOBALS['auth_user'] ?? null;
        if ($currentUser === null) {
            $this->error('Unauthenticated', 'Autentikasi diperlukan.', [], 401);
            return;
        }

        $id = (int) ($params['id'] ?? 0);
        $service = new NotificationService();

        $success = $service->deleteForUser((int) $currentUser['id'], $id);

        if (!$success) {
            $this->error('NotFound', 'Notifikasi tidak ditemukan.', [], 404);
            return;
        }

        $this->success(['id' => $id], 'Notifikasi berhasil dihapus.');
    }
}
