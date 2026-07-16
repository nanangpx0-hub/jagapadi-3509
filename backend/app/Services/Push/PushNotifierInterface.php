<?php

declare(strict_types=1);

namespace App\Services\Push;

interface PushNotifierInterface
{
    public function send(int $userId, string $title, string $body, ?array $data = null): bool;
}
