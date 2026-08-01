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

        if (!isset($payload['jti']) || $payload['jti'] === '' || $payload['jti'] === null) {
            $payload['jti'] = bin2hex(random_bytes(16));
        }

        $segments = [];
        $segments[] = self::base64UrlEncode(json_encode($header));
        $segments[] = self::base64UrlEncode(json_encode($payload));
        $signature = hash_hmac('sha256', implode('.', $segments), self::getSecret(), true);
        $segments[] = self::base64UrlEncode($signature);

        return implode('.', $segments);
    }

    public static function decode(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        $signature = self::base64UrlDecode($signatureB64);
        $expectedSignature = hash_hmac('sha256', "$headerB64.$payloadB64", self::getSecret(), true);

        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $payload = json_decode(self::base64UrlDecode($payloadB64), true);
        if (!is_array($payload)) {
            return null;
        }

        if (isset($payload['exp']) && $payload['exp'] < time()) {
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

        unset($payload['iat'], $payload['exp']);
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
