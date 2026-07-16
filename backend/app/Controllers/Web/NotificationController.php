<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Controller;
use App\Services\NotificationService;

class NotificationController extends Controller
{
    public function index(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $service = new NotificationService();

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(50, max(1, (int) ($_GET['limit'] ?? 20)));
        $unreadOnly = isset($_GET['unread']) ? ($_GET['unread'] === '1' || $_GET['unread'] === 'true') : null;

        $result = $service->listForUser($userId, $page, $limit, $unreadOnly);

        $this->view('notifications/index', [
            'pageTitle' => 'Notifikasi — JAGAPADI',
            'notifications' => $result['data'],
            'meta' => $result['meta'],
            'unreadOnly' => $unreadOnly,
        ]);
    }

    public function unreadCountJson(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $service = new NotificationService();

        $this->jsonResponse([
            'success' => true,
            'count' => $service->unreadCount($userId),
        ]);
    }

    public function recentJson(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $service = new NotificationService();

        $items = $service->getRecentForUser($userId, 5);
        $unread = $service->unreadCount($userId);

        $this->jsonResponse([
            'success' => true,
            'data' => $items,
            'unread' => $unread,
        ]);
    }

    public function markRead(array $params): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $id = (int) ($params['id'] ?? 0);
        $service = new NotificationService();

        $success = $service->markRead($userId, $id);

        if (!$success) {
            $_SESSION['flash_error'] = 'Notifikasi tidak ditemukan.';
        }

        $redirect = $_POST['redirect'] ?? ($_GET['redirect'] ?? '/notifications');
        $this->redirect($redirect);
    }

    public function markAllRead(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $service = new NotificationService();
        $service->markAllRead($userId);

        $_SESSION['flash_success'] = 'Semua notifikasi telah dibaca.';
        $this->redirect('/notifications');
    }

    public function delete(array $params): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $id = (int) ($params['id'] ?? 0);
        $service = new NotificationService();

        $success = $service->deleteForUser($userId, $id);

        if (!$success) {
            $_SESSION['flash_error'] = 'Notifikasi tidak ditemukan.';
        } else {
            $_SESSION['flash_success'] = 'Notifikasi berhasil dihapus.';
        }

        $this->redirect('/notifications');
    }

    private function jsonResponse(mixed $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
