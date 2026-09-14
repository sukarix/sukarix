<?php

declare(strict_types=1);

namespace Sukarix\Messaging;

/**
 * Publishes JSON commands on a message bus.
 */
interface CommandPublisher
{
    /**
     * @param array<string, mixed> $payload
     */
    public function publish(string $routingKey, array $payload): void;
}
