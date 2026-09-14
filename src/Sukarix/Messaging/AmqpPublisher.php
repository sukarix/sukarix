<?php

declare(strict_types=1);

namespace Sukarix\Messaging;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Publishes JSON commands on a direct-exchange RabbitMQ bus. Connections
 * are per-call, for callers that publish rarely enough that holding one
 * open is not worth the complexity.
 */
class AmqpPublisher implements CommandPublisher
{
    public function __construct(private string $dsn, private string $exchange) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function publish(string $routingKey, array $payload): void
    {
        $parts = parse_url($this->dsn);

        $connection = new AMQPStreamConnection(
            $parts['host'] ?? 'localhost',
            $parts['port'] ?? 5672,
            $parts['user'] ?? 'guest',
            $parts['pass'] ?? 'guest'
        );

        try {
            $channel = $connection->channel();
            $channel->exchange_declare($this->exchange, 'direct', false, true, false);
            $channel->basic_publish(
                new AMQPMessage(json_encode($payload, JSON_THROW_ON_ERROR), ['content_type' => 'application/json']),
                $this->exchange,
                $routingKey
            );
        } finally {
            $connection->close();
        }
    }
}
