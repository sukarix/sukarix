<?php

declare(strict_types=1);

namespace Queue;

use Sukarix\Queue\QueueService;
use Test\Scenario;

/**
 * QueueService attempt tracking tests.
 *
 * @internal
 *
 * @coversNothing
 */
final class QueueServiceAttemptsTest extends Scenario
{
    protected $group = 'Queue Service :: Attempts Tracking';

    private const QUEUE = 'test:sukarix:queue:attempts';

    private ?QueueService $queue = null;

    private function queue(): QueueService
    {
        if (null === $this->queue) {
            $this->queue = new QueueService();
        }
        $this->queue->clearQueue(self::QUEUE);

        return $this->queue;
    }

    /**
     * getAttempts returns 0 for an item with no recorded attempts.
     */
    public function testInitialAttemptsIsZero($f3): array
    {
        $test = $this->newTest();
        $q    = $this->queue();

        $test->expect(0 === $q->getAttempts(self::QUEUE, ['meeting_id' => 'm-x']), 'Initial attempts is 0');

        $q->clearQueue(self::QUEUE);

        return $test->results();
    }

    /**
     * incrementAttempts increments and returns the new count.
     */
    public function testIncrementAttempts($f3): array
    {
        $test = $this->newTest();
        $q    = $this->queue();

        $item = ['meeting_id' => 'm-y'];
        $q->incrementAttempts(self::QUEUE, $item);
        $second = $q->incrementAttempts(self::QUEUE, $item);
        $test->expect(2 === $second, 'Second increment returns 2');
        $test->expect(2 === $q->getAttempts(self::QUEUE, $item), 'getAttempts returns 2 after two increments');

        $q->clearQueue(self::QUEUE);

        return $test->results();
    }

    /**
     * clearAttempts resets the counter to 0.
     */
    public function testClearAttempts($f3): array
    {
        $test = $this->newTest();
        $q    = $this->queue();

        $item = ['meeting_id' => 'm-z'];
        $q->incrementAttempts(self::QUEUE, $item);
        $q->incrementAttempts(self::QUEUE, $item);
        $q->clearAttempts(self::QUEUE, $item);
        $test->expect(0 === $q->getAttempts(self::QUEUE, $item), 'Attempts is 0 after clear');

        $q->clearQueue(self::QUEUE);

        return $test->results();
    }

    /**
     * Different items have independent attempt counters.
     */
    public function testAttemptsArePerItem($f3): array
    {
        $test = $this->newTest();
        $q    = $this->queue();

        $itemA = ['meeting_id' => 'm-a1'];
        $itemB = ['meeting_id' => 'm-b1'];
        $q->incrementAttempts(self::QUEUE, $itemA);
        $q->incrementAttempts(self::QUEUE, $itemA);
        $q->incrementAttempts(self::QUEUE, $itemB);

        $test->expect(2 === $q->getAttempts(self::QUEUE, $itemA), 'Item A has 2 attempts');
        $test->expect(1 === $q->getAttempts(self::QUEUE, $itemB), 'Item B has 1 attempt');

        $q->clearQueue(self::QUEUE);

        return $test->results();
    }

    /**
     * Scalar items (strings, integers) also work with attempt tracking.
     */
    public function testAttemptsWithScalarItems($f3): array
    {
        $test = $this->newTest();
        $q    = $this->queue();

        $q->incrementAttempts(self::QUEUE, 'scalar-item');
        $q->incrementAttempts(self::QUEUE, 'scalar-item');
        $test->expect(2 === $q->getAttempts(self::QUEUE, 'scalar-item'), 'Scalar item has 2 attempts');

        $q->clearQueue(self::QUEUE);

        return $test->results();
    }
}
