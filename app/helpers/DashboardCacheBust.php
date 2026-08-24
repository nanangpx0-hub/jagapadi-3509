<?php

declare(strict_types=1);

/**
 * Standardisasi invalidasi cache dashboard runtime root.
 *
 * Cache key ringkasan dashboard memakai kontrak tunggal:
 *   dash_summary_{role}_{userId|all}
 * Helper ini dipanggil dari afterSave model laporan maupun controller
 * sehingga tidak ada jalur mutasi yang lupa membersihkan cache.
 */
final class DashboardCacheBust
{
    /** @var string[] Prefix cache yang wajib dibersihkan saat data berubah. */
    private const PREFIXES = ['dash_summary_', 'dashboard:', 'stats_'];

    public static function clear(): void
    {
        if (!class_exists('CacheManager')) {
            return;
        }
        try {
            $cache = CacheManager::getInstance();
            if (!$cache->isAvailable()) {
                return;
            }
            foreach (self::PREFIXES as $prefix) {
                try {
                    $cache->clearPrefix($prefix);
                } catch (Throwable $e) {
                    error_log('DashboardCacheBust prefix gagal');
                }
            }
        } catch (Throwable $e) {
            error_log('DashboardCacheBust gagal');
        }
    }
}