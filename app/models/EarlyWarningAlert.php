<?php

declare(strict_types=1);

/**
 * EarlyWarningAlert Model
 * Mengelola data persistensi peringatan dini serangan OPT (EWS).
 */
class EarlyWarningAlert extends Model
{
    protected $table = 'early_warning_alerts';
    protected $fillable = [
        'alert_code',
        'master_opt_id',
        'kecamatan_id',
        'desa_id',
        'tingkat_risiko',
        'skor_risiko',
        'faktor_cuaca_skor',
        'faktor_citra_skor',
        'faktor_populasi_skor',
        'faktor_spasial_skor',
        'prediksi_outbreak_at',
        'lead_time_jam',
        'ringkasan_ancaman',
        'rekomendasi_penanganan',
        'data_lingkungan',
        'saluran_distribusi',
        'status',
    ];

    /**
     * Mengambil seluruh alert aktif lengkap dengan relasi nama kecamatan dan OPT.
     */
    public function getActiveAlerts(int $limit = 20): array
    {
        $stmt = $this->db->prepare(
            "SELECT ewa.*, mo.nama_opt, mo.nama_ilmiah, mk.nama_kecamatan
             FROM early_warning_alerts ewa
             LEFT JOIN master_opt mo ON ewa.master_opt_id = mo.id
             LEFT JOIN master_kecamatan mk ON ewa.kecamatan_id = mk.id
             WHERE ewa.status = 'Aktif'
             ORDER BY ewa.skor_risiko DESC, ewa.created_at DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mengubah status alert menjadi Selesai.
     */
    public function markAsResolved(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE early_warning_alerts SET status = 'Selesai', updated_at = NOW() WHERE id = ?"
        );
        return $stmt->execute([$id]);
    }
}
