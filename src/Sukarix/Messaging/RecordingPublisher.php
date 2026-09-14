<?php

declare(strict_types=1);

namespace Sukarix\Messaging;

/**
 * Records published commands for tests, in place of a real broker.
 */
final class RecordingPublisher implements CommandPublisher
{
    /** @var list<array{routing_key: string, payload: array<string, mixed>}> */
    public array $messages = [];

    public function publish(string $routingKey, array $payload): void
    {
        $this->messages[] = ['routing_key' => $routingKey, 'payload' => $payload];
    }
}
