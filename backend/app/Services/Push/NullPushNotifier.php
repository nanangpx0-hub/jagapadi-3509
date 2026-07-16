<?php

declare(strict_types=1);

namespace App\Services\Push;

class NullPushNotifier implements PushNotifierInterface
{
    public function send(int $userId, string $title, string $body, ?array $data = null): bool
    {
        return true;
    }
}
