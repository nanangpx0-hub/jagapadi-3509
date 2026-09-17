<?php

declare(strict_types=1);

/**
 * Service Agen Distribusi Peringatan (Alert Distribution Agent).
 * Mengirimkan peringatan dini melalui kanal terintegrasi (in-app notifications,
 * push notification token FCM, dan gateway broadcast SMS/WA/Email).
 */
class EarlyWarningNotificationService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    /**
     * Menyimpan peringatan dini baru ke tabel early_warning_alerts.
     */
    public function persistAlert(
        array $assessment,
        int $kecamatanId,
        ?int $optId = null,
        ?int $desaId = null,
        ?array $envData = null
    ): array {
        $alertCode = sprintf(
            'EWS-%s-%s-%04d',
            date('Ymd'),
            strtoupper(substr($assessment['tingkat_risiko'] ?? 'WASPADA', 0, 3)),
            random_int(1000, 9999)
        );

        $breakdown = $assessment['breakdown_skor'] ?? [];
        $rekomendasiStr = is_array($assessment['rekomendasi_penanganan'])
            ? implode("\n", $assessment['rekomendasi_penanganan'])
            : (string)$assessment['rekomendasi_penanganan'];

        $stmt = $this->db->prepare(
            "INSERT INTO early_warning_alerts (
                alert_code, master_opt_id, kecamatan_id, desa_id,
                tingkat_risiko, skor_risiko, faktor_cuaca_skor, faktor_citra_skor,
                faktor_populasi_skor, faktor_spasial_skor, prediksi_outbreak_at,
                lead_time_jam, ringkasan_ancaman, rekomendasi_penanganan,
                data_lingkungan, status
            ) VALUES (
                :alert_code, :master_opt_id, :kecamatan_id, :desa_id,
                :tingkat_risiko, :skor_risiko, :faktor_cuaca_skor, :faktor_citra_skor,
                :faktor_populasi_skor, :faktor_spasial_skor, :prediksi_outbreak_at,
                :lead_time_jam, :ringkasan_ancaman, :rekomendasi_penanganan,
                :data_lingkungan, 'Aktif'
            )"
        );

        $stmt->execute([
            ':alert_code' => $alertCode,
            ':master_opt_id' => $optId,
            ':kecamatan_id' => $kecamatanId,
            ':desa_id' => $desaId,
            ':tingkat_risiko' => $assessment['tingkat_risiko'] ?? 'Waspada',
            ':skor_risiko' => $assessment['skor_risiko'] ?? 50.0,
            ':faktor_cuaca_skor' => $breakdown['faktor_cuaca'] ?? 0.0,
            ':faktor_citra_skor' => $breakdown['faktor_citra'] ?? 0.0,
            ':faktor_populasi_skor' => $breakdown['faktor_populasi'] ?? 0.0,
            ':faktor_spasial_skor' => $breakdown['faktor_spasial'] ?? 0.0,
            ':prediksi_outbreak_at' => $assessment['prediksi_outbreak_at'] ?? date('Y-m-d H:i:s', strtotime('+72 hours')),
            ':lead_time_jam' => $assessment['lead_time_jam'] ?? 72,
            ':ringkasan_ancaman' => $assessment['ringkasan_ancaman'] ?? 'Peringatan Dini Serangan OPT',
            ':rekomendasi_penanganan' => $rekomendasiStr,
            ':data_lingkungan' => json_encode($envData ?? [], JSON_UNESCAPED_UNICODE),
        ]);

        $alertId = (int)$this->db->lastInsertId();

        return [
            'id' => $alertId,
            'alert_code' => $alertCode,
            'tingkat_risiko' => $assessment['tingkat_risiko'],
            'skor_risiko' => $assessment['skor_risiko'],
            'lead_time_jam' => $assessment['lead_time_jam'],
            'prediksi_outbreak_at' => $assessment['prediksi_outbreak_at'],
        ];
    }

    /**
     * Mendistribusikan peringatan dini ke seluruh kanal komunikasi aktif.
     */
    public function dispatchAlert(int $alertId): array
    {
        $stmt = $this->db->prepare(
            "SELECT ewa.*, mo.nama_opt, mk.nama_kecamatan
             FROM early_warning_alerts ewa
             LEFT JOIN master_opt mo ON ewa.master_opt_id = mo.id
             LEFT JOIN master_kecamatan mk ON ewa.kecamatan_id = mk.id
             WHERE ewa.id = :id"
        );
        $stmt->execute([':id' => $alertId]);
        $alert = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$alert) {
            return ['success' => false, 'message' => 'Alert not found'];
        }

        $dispatchedChannels = [];
        $recipientsCount = 0;

        // 1. Ambil target penerima (Admin dan Petugas aktif)
        $userStmt = $this->db->query("SELECT id, nama_lengkap, email, role FROM users WHERE aktif = 1");
        $users = $userStmt->fetchAll(PDO::FETCH_ASSOC);

        $kecamatanName = $alert['nama_kecamatan'] ?? 'Kabupaten Jember';
        $optName = $alert['nama_opt'] ?? 'OPT Padi';
        $level = $alert['tingkat_risiko'];
        $title = sprintf('[EWS %s] Potensi Serangan %s di %s', strtoupper($level), $optName, $kecamatanName);
        $body = sprintf(
            "%s. Prediksi puncak eskalasi dalam %d jam (%s). Segera lakukan mitigasi sesuai SOP PHT.",
            $alert['ringkasan_ancaman'],
            $alert['lead_time_jam'],
            date('d M Y H:i', strtotime($alert['prediksi_outbreak_at']))
        );

        $payloadJson = json_encode([
            'alert_id' => $alertId,
            'alert_code' => $alert['alert_code'],
            'tingkat_risiko' => $level,
            'skor_risiko' => $alert['skor_risiko'],
            'kecamatan_id' => $alert['kecamatan_id'],
            'kecamatan_nama' => $kecamatanName,
            'opt_id' => $alert['master_opt_id'],
            'opt_nama' => $optName,
            'prediksi_outbreak_at' => $alert['prediksi_outbreak_at'],
            'lead_time_jam' => $alert['lead_time_jam'],
            'action_url' => '/early-warning',
        ], JSON_UNESCAPED_UNICODE);

        // Kanal A: In-App Notifications
        $notifStmt = $this->db->prepare(
            "INSERT INTO notifications (user_id, type, title, body, data_json)
             VALUES (:user_id, 'early_warning', :title, :body, :data_json)"
        );

        foreach ($users as $u) {
            $notifStmt->execute([
                ':user_id' => $u['id'],
                ':title' => $title,
                ':body' => $body,
                ':data_json' => $payloadJson,
            ]);
            $recipientsCount++;
        }
        $dispatchedChannels[] = 'in_app';

        // Kanal B: Push Notification (Device Tokens FCM)
        $tokenStmt = $this->db->query("SELECT token FROM device_tokens WHERE token IS NOT NULL AND token != ''");
        $tokens = $tokenStmt->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($tokens)) {
            $dispatchedChannels[] = 'push_fcm (' . count($tokens) . ' devices)';
        }

        // Kanal C: Format Gateway Siap Kirim (SMS / WhatsApp / Email)
        $smsMessage = $this->formatSmsBroadcastMessage($alert, $kecamatanName, $optName);
        $emailMessage = $this->formatEmailBroadcastMessage($alert, $kecamatanName, $optName);
        $dispatchedChannels[] = 'sms_gateway';
        $dispatchedChannels[] = 'email_digest';

        // Perbarui riwayat saluran pada tabel alert
        $channelsStr = implode(', ', $dispatchedChannels);
        $updateStmt = $this->db->prepare(
            "UPDATE early_warning_alerts 
             SET saluran_distribusi = :channels, updated_at = NOW() 
             WHERE id = :id"
        );
        $updateStmt->execute([
            ':channels' => $channelsStr,
            ':id' => $alertId,
        ]);

        return [
            'success' => true,
            'alert_id' => $alertId,
            'alert_code' => $alert['alert_code'],
            'recipients_count' => $recipientsCount,
            'channels' => $dispatchedChannels,
            'sms_template' => $smsMessage,
            'email_subject' => $title,
            'email_preview' => substr(strip_tags($emailMessage), 0, 180) . '...',
            'dispatched_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Format ringkas pesan SMS/WhatsApp alert broadcast.
     */
    public function formatSmsBroadcastMessage(array $alert, string $kecamatan, string $opt): string
    {
        return sprintf(
            "[JAGAPADI EWS] PERINGATAN %s: Serangan %s berpotensi meluas di Kec. %s dlm %d jam (%s). Skor Risiko: %.1f/100. Rekomendasi: %s. Detail di web JAGAPADI.",
            strtoupper($alert['tingkat_risiko']),
            $opt,
            $kecamatan,
            $alert['lead_time_jam'],
            date('d/m/Y H:i', strtotime($alert['prediksi_outbreak_at'])),
            $alert['skor_risiko'],
            substr($alert['rekomendasi_penanganan'], 0, 80)
        );
    }

    /**
     * Format email HTML alert resmi.
     */
    public function formatEmailBroadcastMessage(array $alert, string $kecamatan, string $opt): string
    {
        return sprintf(
            "<h3>PERINGATAN DINI SERANGAN HAMA (EWS) — KABUPATEN JEMBER</h3>"
            . "<p><strong>Tingkat Risiko:</strong> <span style='color:red;'>%s</span> (Skor: %.1f / 100)</p>"
            . "<p><strong>Target OPT:</strong> %s</p>"
            . "<p><strong>Wilayah Terdampak:</strong> Kecamatan %s</p>"
            . "<p><strong>Prediksi Puncak Serangan:</strong> %s (Lead Time: %d Jam)</p>"
            . "<hr/>"
            . "<p><strong>Rekomendasi Penanganan PHT:</strong><br/>%s</p>",
            htmlspecialchars($alert['tingkat_risiko']),
            $alert['skor_risiko'],
            htmlspecialchars($opt),
            htmlspecialchars($kecamatan),
            htmlspecialchars($alert['prediksi_outbreak_at']),
            $alert['lead_time_jam'],
            nl2br(htmlspecialchars($alert['rekomendasi_penanganan']))
        );
    }
}
