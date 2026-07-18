<?php

declare(strict_types=1);

namespace App\Helpers;

class LaporanStatus
{
    public const DRAF = 'Draf';
    public const SUBMITTED = 'Submitted';
    public const DIVERIFIKASI = 'Diverifikasi';
    public const DITOLAK = 'Ditolak';
    public const DIARSIPKAN = 'Diarsipkan';

    private const ALLOWED = [self::DRAF, self::SUBMITTED, self::DIVERIFIKASI, self::DITOLAK, self::DIARSIPKAN];

    private const TRANSITIONS = [
        self::DRAF => [
            self::SUBMITTED => 'petugas',
        ],
        self::SUBMITTED => [
            self::DIVERIFIKASI => 'admin',
            self::DITOLAK => 'admin',
        ],
        self::DIVERIFIKASI => [
            self::DIARSIPKAN => 'admin',
        ],
        self::DITOLAK => [
            self::SUBMITTED => 'petugas',
            self::DRAF => 'petugas',
        ],
    ];

    public static function isValid(string $status): bool
    {
        return in_array($status, self::ALLOWED, true);
    }

    public static function canTransition(string $from, string $to, string $role): bool
    {
        if (!self::isValid($from) || !self::isValid($to)) {
            return false;
        }

        $allowed = self::TRANSITIONS[$from] ?? null;
        if ($allowed === null) {
            return false;
        }

        $requiredRole = $allowed[$to] ?? null;
        if ($requiredRole === null) {
            return false;
        }

        if ($requiredRole === 'admin' && $role !== 'admin') {
            return false;
        }

        if ($requiredRole === 'petugas' && $role !== 'petugas') {
            return false;
        }

        return true;
    }

    public static function assertCanTransition(string $from, string $to, string $role): void
    {
        if (!self::canTransition($from, $to, $role)) {
            $fromMsg = "'$from' -> '$to'";
            if (!self::isValid($from) || !self::isValid($to)) {
                throw new \DomainException("Status tidak valid: $fromMsg");
            }

            $requiredRole = (self::TRANSITIONS[$from][$to] ?? null);
            if ($requiredRole === 'admin' && $role !== 'admin') {
                throw new \DomainException('Aksi ini hanya untuk admin.');
            }
            if ($requiredRole === 'petugas' && $role !== 'petugas') {
                throw new \DomainException('Aksi ini hanya untuk petugas pemilik laporan.');
            }

            throw new \DomainException("Transisi status tidak diizinkan: $fromMsg");
        }
    }

    public static function isEditableByPetugas(string $status): bool
    {
        return $status === self::DRAF || $status === self::DITOLAK;
    }

    public static function isVerifiable(string $status): bool
    {
        return $status === self::SUBMITTED;
    }

    public static function isRejectable(string $status): bool
    {
        return $status === self::SUBMITTED;
    }

    public static function isArchivable(string $status): bool
    {
        return $status === self::DIVERIFIKASI;
    }

    public static function isResubmittable(string $status): bool
    {
        return $status === self::DITOLAK;
    }
}
