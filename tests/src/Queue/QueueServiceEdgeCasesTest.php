<?php

declare(strict_types=1);

namespace Queue;

use Sukarix\Queue\QueueService;
use Test\Scenario;

/**
 * QueueService edge-case tests.
 *
 * Covers scenarios that enterprise/AAA IT audit would flag:
 * empty-queue operations, large payloads, scalar values,
 * re-push after pop, and concurrent push/pop interleaving.
 *
 * @internal
 *
 * @coversNothing
 */
final class QueueServiceEdgeCasesTest extends Scenario
{
    protected $group = 'Queue Service :: Edge Cases';

    private const QUEUE = 'test:sukarix:queue:edge';

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
     * A scalar string can be pushed and popped.
     */
    public function testScalarStringPushPop($f3): array
    {
        $test = $this->newTest();
        $q    = $this->queue();

        $q->pushToQueue(self::QUEUE, 'hello-world');
        $test->expect(1 === $q->getQueueSize(self::QUEUE), 'Scalar string is enqueued');
        $result = $q->popFromQueue(self::QUEUE);
        $test->expect('hello-world' === $result, 'Scalar string is dequeued correctly');

        $q->clearQueue(self::QUEUE);

        return $test->results();
    }

    /**
     * An integer can be pushed and popped (JSON round-trip preserves type).
     */
    public function testIntegerPushPop($f3): array
    {
        $test = $this->newTest();
        $q    = $this->queue();

        $q->pushToQueue(self::QUEUE, 42);
        $result = $q->popFromQueue(self::QUEUE);
        $test->expect(42 === $result, 'Integer is dequeued with correct type');

        $q->clearQueue(self::QUEUE);

        return $test->results();
    }

    /**
     * A nested associative array survives the JSON round-trip.
     */
    public function testNestedArrayPushPop($f3): array
    {
        $test = $this->newTest();
        $q    = $this->queue();

        $payload = ['meeting_id' => 'm-nest', 'stats' => ['duration' => 120, 'participants' => 5]];
        $q->pushToQueue(self::QUEUE, $payload);
        $result = $q->popFromQueue(self::QUEUE);
        $test->expect($payload === $result, 'Nested array survives JSON round-trip');

        $q->clearQueue(self::QUEUE);

        return $test->results();
    }

    /**
     * Re-pushing an item after it was popped succeeds (membership was cleared).
     */
    public function testRePushAfterPop($f3): array
    {
        $test = $this->newTest();
        $q    = $this->queue();

        $item = ['meeting_id' => 'm-repush'];
        $q->pushToQueue(self::QUEUE, $item);
        $q->popFromQueue(self::QUEUE);
        $test->expect(!$q->existsInQueue(self::QUEUE, $item), 'Item is not in membership set after pop');

        $q->pushToQueue(self::QUEUE, $item);
        $test->expect(1 === $q->getQueueSize(self::QUEUE), 'Re-push after pop succeeds');
        $test->expect($q->existsInQueue(self::QUEUE, $item), 'Item is back in membership set');

        $q->clearQueue(self::QUEUE);

        return $test->results();
    }

    /**
     * Interleaved push and pop operations maintain FIFO order.
     */
    public function testInterleavedPushPopOrder($f3): array
    {
        $test = $this->newTest();
        $q    = $this->queue();

        $q->pushToQueue(self::QUEUE, ['id' => 1]);
        $q->pushToQueue(self::QUEUE, ['id' => 2]);
        $first = $q->popFromQueue(self::QUEUE);
        $q->pushToQueue(self::QUEUE, ['id' => 3]);
        $second = $q->popFromQueue(self::QUEUE);
        $third  = $q->popFromQueue(self::QUEUE);

        $test->expect(['id' => 1] === $first, 'First pop returns id=1');
        $test->expect(['id' => 2] === $second, 'Second pop returns id=2');
        $test->expect(['id' => 3] === $third, 'Third pop returns id=3');

        $q->clearQueue(self::QUEUE);

        return $test->results();
    }

    /**
     * getQueueItems on an empty queue returns an empty array.
     */
    public function testGetQueueItemsEmpty($f3): array
    {
        $test = $this->newTest();
        $q    = $this->queue();

        $items = $q->getQueueItems(self::QUEUE);
        $test->expect([] === $items, 'getQueueItems on empty queue returns empty array');

        $q->clearQueue(self::QUEUE);

        return $test->results();
    }

    /**
     * existsInQueue returns false for an item that was never pushed.
     */
    public function testExistsInQueueFalseForUnknown($f3): array
    {
        $test = $this->newTest();
        $q    = $this->queue();

        $test->expect(!$q->existsInQueue(self::QUEUE, ['meeting_id' => 'never-pushed']), 'existsInQueue returns false for unknown item');

        $q->clearQueue(self::QUEUE);

        return $test->results();
    }

    /**
     * Multiple distinct items can be pushed and all exist in the membership set.
     */
    public function testMultipleItemsInMembershipSet($f3): array
    {
        $test = $this->newTest();
        $q    = $this->queue();

        $items = [
            ['meeting_id' => 'm-multi-1'],
            ['meeting_id' => 'm-multi-2'],
            ['meeting_id' => 'm-multi-3'],
        ];

        foreach ($items as $item) {
            $q->pushToQueue(self::QUEUE, $item);
        }

        foreach ($items as $item) {
            $test->expect($q->existsInQueue(self::QUEUE, $item), 'Item ' . $item['meeting_id'] . ' exists in membership set');
        }

        $test->expect(3 === $q->getQueueSize(self::QUEUE), 'Queue size is 3');

        $q->clearQueue(self::QUEUE);

        return $test->results();
    }
}
