<?php

declare(strict_types=1);

namespace App\Services\Push;

use App\Core\Env;
use App\Core\Logger;
use App\Models\DeviceToken;

class FcmPushNotifier implements PushNotifierInterface
{
    private ?string $serverKey;
    private bool $enabled;

    public function __construct()
    {
        $this->enabled = Env::get('FCM_ENABLED', 'false') === 'true';
        $this->serverKey = $this->enabled ? Env::get('FCM_SERVER_KEY', '') : null;
    }

    public function send(int $userId, string $title, string $body, ?array $data = null): bool
    {
        if (!$this->enabled || empty($this->serverKey)) {
            return false;
        }

        $tokens = DeviceToken::listByUserId($userId);
        if (empty($tokens)) {
            return true;
        }

        $payload = [
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
            'data' => $this->buildDataPayload($data),
            'android' => [
                'priority' => 'high',
                'notification' => [
                    'channel_id' => 'jagapadi_default',
                    'sound' => 'default',
                ],
            ],
        ];

        $successCount = 0;
        foreach ($tokens as $tokenRow) {
            $fcmToken = $tokenRow['token'];
            $payload['token'] = $fcmToken;

            try {
                $result = $this->sendToFcm($payload);

                if ($result['success'] === true) {
                    $successCount++;
                } elseif (isset($result['invalid'])) {
                    DeviceToken::deleteByToken($fcmToken);
                    Logger::info('FCM token removed (invalid/unregistered)', [
                        'user_id' => $userId,
                        'token_prefix' => substr($fcmToken, 0, 16) . '...',
                    ]);
                }
            } catch (\Throwable $e) {
                Logger::warning('FCM send failed', [
                    'user_id' => $userId,
                    'token_prefix' => substr($fcmToken, 0, 16) . '...',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $successCount > 0;
    }

    private function buildDataPayload(?array $data): array
    {
        $payload = [];
        if ($data === null) {
            return $payload;
        }
        foreach ($data as $key => $value) {
            $payload[$key] = is_string($value) ? $value : (string) json_encode($value);
        }
        return $payload;
    }

    private function sendToFcm(array $payload): array
    {
        $json = json_encode($payload);
        if ($json === false) {
            return ['success' => false, 'error' => 'json_encode_failed'];
        }

        $ch = curl_init('https://fcm.googleapis.com/fcm/send');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: key=' . $this->serverKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError !== '') {
            return ['success' => false, 'error' => $curlError];
        }

        $result = json_decode($response, true);
        if ($httpCode !== 200 || !isset($result['success'])) {
            return ['success' => false, 'error' => $result['results'][0]['error'] ?? 'unknown', 'http_code' => $httpCode];
        }

        $isInvalid = isset($result['results'][0]['error']) &&
            in_array($result['results'][0]['error'], ['NotRegistered', 'InvalidRegistration', 'Unregistered'], true);

        return [
            'success' => $result['success'] > 0 && !$isInvalid,
            'invalid' => $isInvalid,
            'fcm_result' => $result['results'][0] ?? [],
        ];
    }
}
