<?php

declare(strict_types=1);

namespace App\Core;

class Jwt
{
    private static function getSecret(): string
    {
        $secret = Env::get('JWT_SECRET', '');
        $placeholder = 'GANTI_DENGAN_SECRET_MINIMAL_64_KARAKTER_ACAK';
        if ($secret === '' || $secret === $placeholder || strlen($secret) < 32) {
            throw new \RuntimeException('JWT_SECRET tidak dikonfigurasi dengan benar.');
        }
        return $secret;
    }

    public static function encode(array $payload): string
    {
        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT',
        ];

        $payload['iat'] = $payload['iat'] ?? time();
        $payload['exp'] = $payload['exp'] ?? time() + (int) Env::get('JWT_EXPIRY', '3600');
        // iss/aud dari env bila tersedia (untuk kompatibilitas transisi, tidak wajib bila belum dikonfigurasi)
        $payload['iss'] = $payload['iss'] ?? Env::get('JWT_ISS', Env::get('APP_BASE_URL', 'jagapadi'));
        $payload['aud'] = $payload['aud'] ?? Env::get('JWT_AUD', 'jagapadi-mobile');
        if (!isset($payload['nbf']) && Env::get('JWT_NBF_ENABLED', 'false') === 'true') {
            $payload['nbf'] = time();
        }

        if (!isset($payload['jti']) || $payload['jti'] === '' || $payload['jti'] === null) {
            $payload['jti'] = bin2hex(random_bytes(16));
        }

        $segments = [];
        $segments[] = self::base64UrlEncode((string) json_encode($header));
        $segments[] = self::base64UrlEncode((string) json_encode($payload));
        $signature = hash_hmac('sha256', implode('.', $segments), self::getSecret(), true);
        $segments[] = self::base64UrlEncode($signature);

        return implode('.', $segments);
    }

    public static function decode(string $token): ?array
    {
        // Validasi konfigurasi secret lebih dulu (perilaku historis: jika
        // JWT_SECRET tidak valid, aplikasi harus langsung gagal, bukan lenyap
        // ditelan setiap token yang tidak terstruktur).
        $secret = self::getSecret();

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        // Header wajib HS256 — mencegah algoritma downgrade (mis. none/RS256).
        $header = json_decode(self::base64UrlDecode($headerB64), true);
        if (!is_array($header) || ($header['alg'] ?? '') !== 'HS256') {
            return null;
        }

        $signature = self::base64UrlDecode($signatureB64);
        $expectedSignature = hash_hmac('sha256', "$headerB64.$payloadB64", $secret, true);

        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $payload = json_decode(self::base64UrlDecode($payloadB64), true);
        if (!is_array($payload)) {
            return null;
        }

        // `sub` wajib ada & numerik positif (target user).
        $sub = $payload['sub'] ?? null;
        if (!is_numeric($sub) || (int) $sub <= 0) {
            return null;
        }

        // `exp` wajib ada dan belum lewat.
        if (!isset($payload['exp']) || !is_numeric($payload['exp']) || (int) $payload['exp'] < time()) {
            return null;
        }

        // `iat` tidak boleh dari masa depan (toleransi 60 detik untuk clock skew).
        $skew = (int) Env::get('JWT_CLOCK_SKEW', '60');
        if (isset($payload['iat']) && is_numeric($payload['iat']) && (int) $payload['iat'] > time() + $skew) {
            return null;
        }

        // `nbf` jika ada, tidak boleh di masa depan (skew tolerant)
        if (isset($payload['nbf']) && is_numeric($payload['nbf']) && (int) $payload['nbf'] > time() + $skew) {
            return null;
        }

        // `jti` wajib non-kosong (dasar blacklist/revokasi & deteksi replay).
        if (!isset($payload['jti']) || !is_string($payload['jti']) || trim($payload['jti']) === '') {
            return null;
        }

        // `iss` validasi bila env mengharuskan (transisi kompatibel: skip bila env kosong)
        $expectedIss = Env::get('JWT_ISS', '');
        if ($expectedIss !== '' && isset($payload['iss']) && $payload['iss'] !== $expectedIss) {
            return null;
        }
        $expectedAud = Env::get('JWT_AUD', '');
        if ($expectedAud !== '' && isset($payload['aud']) && $payload['aud'] !== $expectedAud) {
            // Aud bisa string atau array; support keduanya
            $aud = $payload['aud'];
            if (is_array($aud) && !in_array($expectedAud, $aud, true)) {
                return null;
            }
            if (is_string($aud) && $aud !== $expectedAud) {
                return null;
            }
        }

        // `ver` token_version sudah divalidasi di ApiAuthMiddleware terhadap DB, tapi juga
        // pastikan numerik bila ada.
        if (isset($payload['ver']) && !is_numeric($payload['ver'])) {
            return null;
        }

        return $payload;
    }

    public static function refresh(string $token): ?string
    {
        $payload = self::decode($token);
        if ($payload === null) {
            return null;
        }

        // Token hasil refresh WAJIB membawa `jti` BARU. Toko lama di-revoke di
        // controller saat refresh, sehingga jika `jti` dipertahankan, token baru
        // akan langsung ditolak oleh blacklist.
        unset($payload['iat'], $payload['exp'], $payload['jti']);
        return self::encode($payload);
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        return $decoded !== false ? $decoded : '';
    }
}
