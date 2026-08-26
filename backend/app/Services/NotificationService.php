<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Env;
use App\Core\Logger;
use App\Models\Notification;
use App\Services\Push\NullPushNotifier;
use App\Services\Push\PushNotifierInterface;
use PDO;

class NotificationService
{
    private PDO $db;
    private PushNotifierInterface $pushNotifier;
    private bool $pushDisabled;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->pushDisabled = Env::get('FCM_ENABLED', 'false') !== 'true';
        if ($this->pushDisabled) {
            $this->pushNotifier = new NullPushNotifier();
        } else {
            $this->pushNotifier = $this->createFcmNotifier();
        }
    }

    public function notifyUser(int $userId, string $type, string $title, string $body, ?array $data = null): void
    {
        $id = Notification::insert([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data_json' => $data !== null ? json_encode($data) : null,
        ]);

        try {
            $this->pushNotifier->send($userId, $title, $body, $data);
        } catch (\Throwable $e) {
            Logger::warning('Push notification failed', [
                'user_id' => $userId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function notifyAdmins(string $type, string $title, string $body, ?array $data = null, ?int $exceptUserId = null): void
    {
        $sql = "SELECT id FROM `users` WHERE `role` = 'admin' AND `aktif` = 1";
        $params = [];

        if ($exceptUserId !== null) {
            $sql .= " AND id != ?";
            $params[] = $exceptUserId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $adminIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($adminIds as $adminId) {
            $this->notifyUser((int) $adminId, $type, $title, $body, $data);
        }
    }

    public function listForUser(int $userId, int $page = 1, int $limit = 20, ?bool $unreadOnly = null): array
    {
        $result = Notification::listForUser($userId, $page, $limit, $unreadOnly);

        $items = [];
        foreach ($result['data'] as $row) {
            $items[] = $this->formatRow($row);
        }

        return [
            'data' => $items,
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $result['total'],
                'unread' => Notification::unreadCount($userId),
            ],
        ];
    }

    public function unreadCount(int $userId): int
    {
        return Notification::unreadCount($userId);
    }

    public function markRead(int $userId, int $notificationId): bool
    {
        return Notification::markRead($userId, $notificationId);
    }

    public function markAllRead(int $userId): int
    {
        return Notification::markAllRead($userId);
    }

    public function deleteForUser(int $userId, int $notificationId): bool
    {
        return Notification::deleteForUser($userId, $notificationId);
    }

    public function getRecentForUser(int $userId, int $limit = 5): array
    {
        $rows = Notification::getRecentForUser($userId, $limit);
        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->formatRow($row);
        }
        return $items;
    }

    public function pruneOlderThan(int $days): int
    {
        return Notification::pruneOlderThan($days);
    }

    /**
     * Notifikasi tanda terima sukses kirim laporan kepada petugas pengirim,
     * memuat nomor laporan dan waktu pencatatan (persyaratan notifikasi ganda).
     */
    public function notifySubmitSuccessToOwner(
        int $ownerId,
        string $entity,
        int $laporanId,
        string $nomor
    ): void {
        $submittedAt = date('Y-m-d H:i:s');
        $this->notifyUser(
            $ownerId,
            'laporan_submit_success',
            'Laporan berhasil dikirim',
            "{$nomor} tercatat pada {$submittedAt} WIB.",
            [
                'entity' => $entity,
                'laporan_id' => $laporanId,
                'nomor_laporan' => $nomor,
                'status' => 'Submitted',
                'submitted_at' => $submittedAt,
                'web_path' => "/laporan-{$entity}/{$laporanId}",
                'api_path' => "/api/v1/laporan-{$entity}/{$laporanId}",
            ]
        );
    }

    /**
     * Notifikasi kegagalan pengiriman laporan kepada petugas beserta kode
     * penyebab (validation|conflict|not_found|server_error). Dide duplikasi:
     * kegagalan identik pada rentang 5 menit tidak mengirim notifikasi baru
     * agar retry otomatis tidak membanjiri pengguna.
     *
     * @param array<string, string> $errors Ringkasan per bidang (opsional)
     */
    public function notifySubmitFailureToOwner(
        int $userId,
        string $entity,
        ?int $laporanId,
        string $reasonCode,
        string $reasonMessage,
        array $errors = []
    ): bool {
        if (!$this->shouldSendFailure($userId, $entity, $laporanId, $reasonCode)) {
            return false;
        }

        $title = 'Laporan gagal dikirim';
        $body = $this->truncateBody('Pengiriman gagal (' . $reasonCode . '): ' . $reasonMessage);

        $this->notifyUser(
            $userId,
            'laporan_submit_failed',
            $title,
            $body,
            [
                'entity' => $entity,
                'laporan_id' => $laporanId,
                'reason_code' => $reasonCode,
                'reason_message' => mb_substr($reasonMessage, 0, 300),
                'errors' => array_slice($errors, 0, 10, true),
            ]
        );

        return true;
    }

    private function shouldSendFailure(
        int $userId,
        string $entity,
        ?int $laporanId,
        string $reasonCode
    ): bool {
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM `notifications`
                 WHERE `user_id` = ? AND `type` = 'laporan_submit_failed'
                   AND `created_at` >= (NOW() - INTERVAL 5 MINUTE)
                   AND `data_json` LIKE ?"
            );
            $needle = '%"reason_code":"' . $reasonCode . '"%';
            if ($laporanId !== null) {
                $needle = '%"entity":"' . $entity . '"%"laporan_id":' . (int) $laporanId . '%' . $needle;
            }
            $stmt->execute([$userId, $needle]);
            return (int) $stmt->fetchColumn() === 0;
        } catch (\Throwable) {
            // Bila pemeriksaan dedupe gagal, tetap kirim (gagal aman).
            return true;
        }
    }

    public function truncateBody(string $body, int $maxLength = 120): string
    {
        if (mb_strlen($body) <= $maxLength) {
            return $body;
        }
        return mb_substr($body, 0, $maxLength - 3) . '...';
    }

    private function formatRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'user_id' => (int) $row['user_id'],
            'type' => $row['type'],
            'title' => $row['title'],
            'body' => $row['body'],
            'data' => $row['data_json'] ? json_decode($row['data_json'], true) : null,
            'read_at' => $row['read_at'],
            'created_at' => $row['created_at'],
        ];
    }

    private function createFcmNotifier(): PushNotifierInterface
    {
        return new \App\Services\Push\FcmPushNotifier();
    }
}
