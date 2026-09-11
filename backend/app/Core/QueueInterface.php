<?php
declare(strict_types=1);
namespace App\Core;

/**
 * Durable queue dengan outbox pattern untuk mutasi DB → event.
 * Implementasi: Redis (production) dengan fallback file-lock (dev).
 * Fitur: retry exponential backoff+jitter, timeout, circuit breaker, idempotency, DLQ, last-good, stale marker.
 */
interface QueueInterface
{
    public function push(string $queue, array $payload, ?string $idempotencyKey = null, int $delaySeconds = 0): string;
    public function pop(string $queue): ?array; // ['id'=>..., 'payload'=>..., 'attempts'=>...]
    public function ack(string $queue, string $jobId): void;
    public function retry(string $queue, string $jobId, string $reason): void;
    public function deadLetter(string $queue, string $jobId, string $reason): void;
    public function size(string $queue): int;
}
