<?php

declare(strict_types=1);

namespace Queue;

use Sukarix\Behaviours\HasQueue;
use Sukarix\Core\Injector;
use Sukarix\Queue\QueueProgressLogger;
use Sukarix\Queue\QueueService;
use Test\Scenario;

/**
 * QueueService core-operation tests.
 *
 * Exercises Redis-atomic enqueue/dequeue, deduplication and FIFO
 * ordering against a live Redis instance using test-prefixed queue
 * names that are cleaned up in each scenario.
 *
 * @internal
 *
 * @coversNothing
 */
final class QueueServiceCoreTest extends Scenario
{
    protected $group = 'Queue Service :: Core Operations';

    private const QUEUE = 'test:sukarix:queue:locking';

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
     * Pushing an item adds it to the queue and the membership set.
     */
    public function testPushAddsItem($f3): array
    {
        $test = $this->newTest();
        $q    = $this->queue();

        $q->pushToQueue(self::QUEUE, ['meeting_id' => 'm-1']);
        $test->expect(1 === $q->getQueueSize(self::QUEUE), 'Queue size is 1 after pushing one item');
        $test->expect($q->existsInQueue(self::QUEUE, ['meeting_id' => 'm-1']), 'Item exists in the membership set');

        $q->clearQueue(self::QUEUE);

        return $test->results();
    }

    /**
     * Pushing the same item twice does not duplicate it (deduplication via set).
     */
    public function testPushDeduplicates($f3): array
    {
        $test = $this->newTest();
        $q    = $this->queue();

        $q->pushToQueue(self::QUEUE, ['meeting_id' => 'm-dup']);
        $q->pushToQueue(self::QUEUE, ['meeting_id' => 'm-dup']);
        $test->expect(1 === $q->getQueueSize(self::QUEUE), 'Duplicate push does not increase queue size');

        $q->clearQueue(self::QUEUE);

        return $test->results();
    }

    /**
     * Popping returns the oldest item (FIFO) and removes it from the set.
     */
    public function testPopReturnsAndRemoves($f3): array
    {
        $test = $this->newTest();
        $q    = $this->queue();

        $q->pushToQueue(self::QUEUE, ['meeting_id' => 'm-a']);
        $q->pushToQueue(self::QUEUE, ['meeting_id' => 'm-b']);

        $first = $q->popFromQueue(self::QUEUE);
        $test->expect(['meeting_id' => 'm-a'] === $first, 'Pop returns the first pushed item (FIFO)');
        $test->expect(!$q->existsInQueue(self::QUEUE, ['meeting_id' => 'm-a']), 'Popped item is removed from the membership set');
        $test->expect(1 === $q->getQueueSize(self::QUEUE), 'Queue size is 1 after popping one item');

        $q->clearQueue(self::QUEUE);

        return $test->results();
    }

    /**
     * Popping an empty queue returns null.
     */
    public function testPopEmptyReturnsNull($f3): array
    {
        $test = $this->newTest();
        $q    = $this->queue();

        $result = $q->popFromQueue(self::QUEUE);
        $test->expect(null === $result, 'Pop on empty queue returns null');

        $q->clearQueue(self::QUEUE);

        return $test->results();
    }

    /**
     * isEmpty returns true for a fresh queue and false after a push.
     */
    public function testIsEmpty($f3): array
    {
        $test = $this->newTest();
        $q    = $this->queue();

        $test->expect(true === $q->isEmpty(self::QUEUE), 'Fresh queue is empty');
        $q->pushToQueue(self::QUEUE, ['meeting_id' => 'm-e']);
        $test->expect(false === $q->isEmpty(self::QUEUE), 'Queue is not empty after push');

        $q->clearQueue(self::QUEUE);

        return $test->results();
    }

    /**
     * peek returns the tail item without removing it.
     */
    public function testPeekDoesNotRemove($f3): array
    {
        $test = $this->newTest();
        $q    = $this->queue();

        $q->pushToQueue(self::QUEUE, ['meeting_id' => 'm-p1']);
        $q->pushToQueue(self::QUEUE, ['meeting_id' => 'm-p2']);

        $peeked = $q->peek(self::QUEUE);
        $test->expect(['meeting_id' => 'm-p1'] === $peeked, 'Peek returns the tail item');
        $test->expect(2 === $q->getQueueSize(self::QUEUE), 'Peek does not remove the item');

        $q->clearQueue(self::QUEUE);

        return $test->results();
    }

    /**
     * peek on an empty queue returns null.
     */
    public function testPeekEmptyReturnsNull($f3): array
    {
        $test = $this->newTest();
        $q    = $this->queue();

        $test->expect(null === $q->peek(self::QUEUE), 'Peek on empty queue returns null');

        $q->clearQueue(self::QUEUE);

        return $test->results();
    }

    /**
     * clearQueue removes the list, the membership set and the attempts hash.
     */
    public function testClearQueueRemovesEverything($f3): array
    {
        $test = $this->newTest();
        $q    = $this->queue();

        $q->pushToQueue(self::QUEUE, ['meeting_id' => 'm-c']);
        $q->incrementAttempts(self::QUEUE, ['meeting_id' => 'm-c']);
        $q->clearQueue(self::QUEUE);

        $test->expect(0 === $q->getQueueSize(self::QUEUE), 'Queue size is 0 after clear');
        $test->expect(!$q->existsInQueue(self::QUEUE, ['meeting_id' => 'm-c']), 'Membership set is cleared');
        $test->expect(0 === $q->getAttempts(self::QUEUE, ['meeting_id' => 'm-c']), 'Attempts hash is cleared');

        return $test->results();
    }

    /**
     * getQueueItems returns items in FIFO order without modifying the queue.
     */
    public function testGetQueueItems($f3): array
    {
        $test = $this->newTest();
        $q    = $this->queue();

        $q->pushToQueue(self::QUEUE, ['meeting_id' => 'm-1']);
        $q->pushToQueue(self::QUEUE, ['meeting_id' => 'm-2']);
        $q->pushToQueue(self::QUEUE, ['meeting_id' => 'm-3']);

        $items = $q->getQueueItems(self::QUEUE);
        $test->expect(3 === \count($items), 'getQueueItems returns all 3 items');
        $test->expect(['meeting_id' => 'm-1'] === $items[0], 'First item is the oldest (FIFO)');
        $test->expect(3 === $q->getQueueSize(self::QUEUE), 'Queue size unchanged after getQueueItems');

        $q->clearQueue(self::QUEUE);

        return $test->results();
    }

    /**
     * getQueueItems with a limit returns the oldest items in FIFO order.
     */
    public function testGetQueueItemsWithLimit($f3): array
    {
        $test = $this->newTest();
        $q    = $this->queue();

        $q->pushToQueue(self::QUEUE, ['meeting_id' => 'm-1']);
        $q->pushToQueue(self::QUEUE, ['meeting_id' => 'm-2']);
        $q->pushToQueue(self::QUEUE, ['meeting_id' => 'm-3']);

        $items = $q->getQueueItems(self::QUEUE, 2);
        $test->expect(2 === \count($items), 'getQueueItems with limit 2 returns 2 items');
        $test->expect(['meeting_id' => 'm-1'] === $items[0], 'First limited item is m-1 (oldest, FIFO)');

        $q->clearQueue(self::QUEUE);

        return $test->results();
    }

    /**
     * A missing queue registration fails at construction with useful context.
     */
    public function testQueueConsumerRequiresRegisteredQueue($f3): array
    {
        $test     = $this->newTest();
        $injector = Injector::instance();
        $injector->clear('queue');

        $threw = false;
        try {
            new QueueConsumer();
        } catch (\LogicException $exception) {
            $threw = true;
            $test->expect(str_contains($exception->getMessage(), 'queue service must be registered'), 'Missing queue registration produces an actionable error');
        }

        $test->expect($threw, 'Queue consumer cannot be constructed without a registered queue');

        return $test->results();
    }

    /**
     * Queue logging does not depend on an application-specific payload field.
     */
    public function testGenericPayloadPreview($f3): array
    {
        $test     = $this->newTest();
        $injector = Injector::instance();
        $injector->clear('queue.progress_logger');

        $logger = new RecordingQueueProgressLogger();
        $injector->set('queue.progress_logger', $logger);
        $q = new QueueService();

        try {
            $q->clearQueue(self::QUEUE);
            $q->pushToQueue(self::QUEUE, ['order_id' => 'order-1', 'total' => 42]);

            $test->expect('array[2]' === ($logger->addedPreviews[0] ?? null), 'Array payload preview is generic and does not depend on meeting_id');
            $test->expect(1 === $q->getQueueSize(self::QUEUE), 'Generic payload is queued successfully');
        } finally {
            $q->clearQueue(self::QUEUE);
            $injector->clear('queue.progress_logger');
        }

        return $test->results();
    }
}

final class QueueConsumer
{
    use HasQueue;

    public function __construct()
    {
        $this->initHasQueue();
    }
}

final class RecordingQueueProgressLogger extends QueueProgressLogger
{
    /** @var list<string> */
    public array $addedPreviews = [];

    public function logItemAdded(string $queueName, string $itemType, string $itemPreview, int $queueSize = 0): void
    {
        $this->addedPreviews[] = $itemPreview;
    }
}
