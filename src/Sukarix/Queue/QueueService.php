<?php

declare(strict_types=1);

namespace Sukarix\Queue;

use Sukarix\Behaviours\HasF3;
use Sukarix\Behaviours\LogWriter;
use Sukarix\Core\Injector;
use Sukarix\Core\Processor;
use Sukarix\Core\Tailored;

/**
 * Redis-backed FIFO queue with membership-based deduplication and per-item
 * attempt tracking.
 *
 * Queue and membership updates are performed by Redis Lua scripts so they are
 * atomic even if a worker is terminated between list and set operations.
 *
 * Configuration (F3 hive):
 *   redis.host    — Redis host (default 127.0.0.1)
 *   redis.port    — Redis port (default 6379)
 *   redis.timeout — Connection timeout in seconds (default 2)
 */
class QueueService extends Tailored
{
    use HasF3;
    use LogWriter;

    private const PUSH_SCRIPT = <<<'LUA'
if redis.call('SISMEMBER', KEYS[2], ARGV[1]) == 1 then
    return 0
end
redis.call('LPUSH', KEYS[1], ARGV[1])
redis.call('SADD', KEYS[2], ARGV[1])
return 1
LUA;

    private const POP_SCRIPT = <<<'LUA'
local item = redis.call('RPOP', KEYS[1])
if not item then
    return false
end
redis.call('SREM', KEYS[2], item)
return item
LUA;

    protected \Redis $redis;

    protected QueueProgressLogger $progressLogger;

    /**
     * Connect to Redis and resolve the generic queue progress logger.
     *
     * @throws \RedisException when the Redis connection cannot be established
     */
    public function __construct()
    {
        Processor::instance()->initialize($this);

        $this->redis = new \Redis();
        $host        = (string) ($this->f3->get('redis.host') ?? '127.0.0.1');
        $port        = (int) ($this->f3->get('redis.port') ?? 6379);
        $timeout     = (float) ($this->f3->get('redis.timeout') ?? 2);

        if (!$this->redis->connect($host, $port, $timeout)) {
            throw new \RedisException("Failed to connect to Redis at {$host}:{$port}");
        }

        $this->progressLogger = $this->resolveProgressLogger();
        $this->logger->debug('Redis connected', ['connected' => $this->redis->isConnected()]);
    }

    /**
     * Push an item onto the queue head, skipping payloads already queued.
     *
     * @param mixed $data serialisable payload
     *
     * @throws \JsonException
     * @throws \RedisException
     */
    public function pushToQueue(string $queueName, mixed $data): void
    {
        $encodedData = json_encode($data, JSON_THROW_ON_ERROR);
        $inserted    = $this->redis->eval(
            self::PUSH_SCRIPT,
            [$queueName, $this->getMembersKey($queueName), $encodedData],
            2
        );

        if (1 === $inserted) {
            $this->progressLogger->logItemAdded(
                $queueName,
                get_debug_type($data),
                $this->getItemPreview($data)
            );

            return;
        }

        $this->progressLogger->logQueueWarning($queueName, 'Item already exists in queue', [
            'item_type' => get_debug_type($data),
        ]);
    }

    /**
     * Pop the oldest item from the queue tail.
     *
     * @return mixed|null the decoded payload, or null when the queue is empty
     *
     * @throws \JsonException
     * @throws \RedisException
     */
    public function popFromQueue(string $queueName): mixed
    {
        $data = $this->redis->eval(
            self::POP_SCRIPT,
            [$queueName, $this->getMembersKey($queueName)],
            2
        );

        if (false === $data || null === $data) {
            $this->progressLogger->logQueueStatus($queueName, 0, 'EMPTY');

            return null;
        }

        $decodedData = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        $this->progressLogger->logItemRemoved(
            $queueName,
            get_debug_type($decodedData),
            $this->getItemPreview($decodedData)
        );

        return $decodedData;
    }

    /**
     * Get the number of items currently in the queue.
     *
     * @throws \RedisException
     */
    public function getQueueSize(string $queueName): int
    {
        return $this->redis->lLen($queueName);
    }

    /**
     * Check whether the queue contains no items.
     *
     * @throws \RedisException
     */
    public function isEmpty(string $queueName): bool
    {
        return 0 === $this->redis->lLen($queueName);
    }

    /**
     * Peek at the next item that would be popped without removing it.
     *
     * @return mixed|null the decoded payload at the tail, or null when empty
     *
     * @throws \JsonException
     * @throws \RedisException
     */
    public function peek(string $queueName): mixed
    {
        $data = $this->redis->lIndex($queueName, -1);

        if (false === $data) {
            return null;
        }

        return json_decode($data, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Check whether an item is tracked in the queue's membership set.
     *
     * @param mixed $valueToCheck serialisable payload
     *
     * @throws \JsonException
     * @throws \RedisException
     */
    public function existsInQueue(string $queueName, mixed $valueToCheck): bool
    {
        return $this->redis->sIsMember(
            $this->getMembersKey($queueName),
            json_encode($valueToCheck, JSON_THROW_ON_ERROR)
        );
    }

    /**
     * Get the number of failed processing attempts recorded for an item.
     *
     * @param mixed $item serialisable payload
     *
     * @throws \JsonException
     * @throws \RedisException
     */
    public function getAttempts(string $queueName, mixed $item): int
    {
        $attempts = $this->redis->hGet($this->getAttemptsKey($queueName), $this->getItemKey($item));

        return false === $attempts ? 0 : (int) $attempts;
    }

    /**
     * Record one more failed attempt and return the new count.
     *
     * @param mixed $item serialisable payload
     *
     * @throws \JsonException
     * @throws \RedisException
     */
    public function incrementAttempts(string $queueName, mixed $item): int
    {
        return $this->redis->hIncrBy($this->getAttemptsKey($queueName), $this->getItemKey($item), 1);
    }

    /**
     * Reset the attempt counter for an item after successful processing.
     *
     * @param mixed $item serialisable payload
     *
     * @throws \JsonException
     * @throws \RedisException
     */
    public function clearAttempts(string $queueName, mixed $item): void
    {
        $this->redis->hDel($this->getAttemptsKey($queueName), $this->getItemKey($item));
    }

    /**
     * Return queued items in FIFO order (oldest first).
     *
     * This is an observational, eventually-consistent snapshot. Concurrent
     * producers and consumers may change the queue before the caller acts on it.
     *
     * @param int|null $limit maximum number of oldest items, null for all
     *
     * @return array<int,mixed>
     *
     * @throws \JsonException
     * @throws \RedisException
     */
    public function getQueueItems(string $queueName, ?int $limit = null): array
    {
        $size = $this->redis->lLen($queueName);

        if (0 === $size || 0 === $limit) {
            return [];
        }

        $offset = null === $limit ? 0 : max(0, $size - $limit);
        $items  = $this->redis->lRange($queueName, $offset, -1);

        return array_reverse(array_map(
            static fn (string $data): mixed => json_decode($data, true, 512, JSON_THROW_ON_ERROR),
            $items
        ));
    }

    /**
     * Remove the queue list, membership set and attempts hash.
     *
     * This is an administrative operation. Do not run it while producers or
     * consumers are active because Redis cannot make the wider workflow atomic.
     *
     * @throws \RedisException
     */
    public function clearQueue(string $queueName): void
    {
        $this->redis->del(
            $queueName,
            $this->getMembersKey($queueName),
            $this->getAttemptsKey($queueName)
        );
    }

    /**
     * Resolve an application-supplied QueueProgressLogger when registered,
     * otherwise use the framework default singleton.
     */
    private function resolveProgressLogger(): QueueProgressLogger
    {
        $injector = Injector::instance();
        if (!$injector->has('queue.progress_logger')) {
            return QueueProgressLogger::instance();
        }

        $logger = $injector->get('queue.progress_logger');
        if (!$logger instanceof QueueProgressLogger) {
            throw new \UnexpectedValueException(
                'The queue.progress_logger service must extend ' . QueueProgressLogger::class . '.'
            );
        }

        return $logger;
    }

    /**
     * A generic preview intentionally avoids application-specific fields and
     * payload content that may include sensitive data.
     */
    private function getItemPreview(mixed $item): string
    {
        return match (true) {
            is_array($item)  => 'array[' . count($item) . ']',
            is_object($item) => $item::class,
            null === $item   => 'null',
            default          => get_debug_type($item),
        };
    }

    private function getMembersKey(string $queueName): string
    {
        return $queueName . ':members';
    }

    private function getAttemptsKey(string $queueName): string
    {
        return $queueName . ':attempts';
    }

    /**
     * Build a stable key for attempt tracking.
     *
     * @param mixed $item serialisable payload
     *
     * @throws \JsonException
     */
    private function getItemKey(mixed $item): string
    {
        return md5(json_encode($item, JSON_THROW_ON_ERROR));
    }
}
