<?php

declare(strict_types=1);

namespace Queue;

use Sukarix\Queue\QueueProgressLogger;
use Test\Scenario;

/**
 * QueueProgressLogger tests.
 *
 * Verifies that every log method delegates to the Monolog logger with
 * the correct level, prefix and context structure — without requiring
 * a Redis connection.
 *
 * @internal
 *
 * @coversNothing
 */
final class QueueProgressLoggerTest extends Scenario
{
    protected $group = 'Queue Progress Logger';

    private ?QueueProgressLogger $logger = null;

    private function logger(): QueueProgressLogger
    {
        if (null === $this->logger) {
            $this->logger = new QueueProgressLogger();
        }

        return $this->logger;
    }

    /**
     * logQueueStart logs at INFO level with the START prefix.
     */
    public function testLogQueueStart($f3): array
    {
        $test = $this->newTest();
        $l    = $this->logger();

        $records = $this->captureLog(fn() => $l->logQueueStart('test-q', 'validate', ['items' => 10]));

        $test->expect(1 === \count($records), 'One log record produced');
        $test->expect('info' === $records[0]['level_name'], 'Log level is INFO');
        $test->expect(str_contains($records[0]['message'], '[START]'), 'Message contains [START] prefix');
        $test->expect(str_contains($records[0]['message'], 'test-q'), 'Message contains queue name');
        $test->expect(str_contains($records[0]['message'], 'validate'), 'Message contains operation');
        $test->expect('test-q' === $records[0]['context']['queue'], 'Context contains queue name');
        $test->expect('validate' === $records[0]['context']['operation'], 'Context contains operation');

        return $test->results();
    }

    /**
     * logQueueComplete logs at INFO level with the SUCCESS prefix when no errors.
     */
    public function testLogQueueCompleteSuccess($f3): array
    {
        $test = $this->newTest();
        $l    = $this->logger();

        $records = $this->captureLog(fn() => $l->logQueueComplete('test-q', 'harvest', 50, 0));

        $test->expect(1 === \count($records), 'One log record produced');
        $test->expect('info' === $records[0]['level_name'], 'Log level is INFO');
        $test->expect(str_contains($records[0]['message'], '[SUCCESS]'), 'Message contains [SUCCESS] prefix');
        $test->expect('100%' === $records[0]['context']['success_rate'], 'Success rate is 100% with no errors');

        return $test->results();
    }

    /**
     * logQueueComplete logs with the ERROR prefix when errors > 0.
     */
    public function testLogQueueCompleteWithErrors($f3): array
    {
        $test = $this->newTest();
        $l    = $this->logger();

        $records = $this->captureLog(fn() => $l->logQueueComplete('test-q', 'harvest', 40, 10));

        $test->expect(str_contains($records[0]['message'], '[ERROR]'), 'Message contains [ERROR] prefix when errors > 0');
        $test->expect('80%' === $records[0]['context']['success_rate'], 'Success rate is 80% with 10 errors out of 50');

        return $test->results();
    }

    /**
     * logQueueWarning logs at WARNING level.
     */
    public function testLogQueueWarning($f3): array
    {
        $test = $this->newTest();
        $l    = $this->logger();

        $records = $this->captureLog(fn() => $l->logQueueWarning('test-q', 'Item already exists', ['item_type' => 'array']));

        $test->expect('warning' === $records[0]['level_name'], 'Log level is WARNING');
        $test->expect(str_contains($records[0]['message'], '[SKIP]'), 'Message contains [SKIP] prefix');
        $test->expect('Item already exists' === $records[0]['context']['message'], 'Context contains the warning message');

        return $test->results();
    }

    /**
     * logQueueError logs at ERROR level.
     */
    public function testLogQueueError($f3): array
    {
        $test = $this->newTest();
        $l    = $this->logger();

        $records = $this->captureLog(fn() => $l->logQueueError('test-q', 'Lock acquisition failed'));

        $test->expect('error' === $records[0]['level_name'], 'Log level is ERROR');
        $test->expect(str_contains($records[0]['message'], '[ERROR]'), 'Message contains [ERROR] prefix');

        return $test->results();
    }

    /**
     * logItemAdded and logItemRemoved log at DEBUG level.
     */
    public function testItemAddRemoveAreDebug($f3): array
    {
        $test = $this->newTest();
        $l    = $this->logger();

        $added = $this->captureLog(fn() => $l->logItemAdded('test-q', 'array', 'm-1'));
        $test->expect('debug' === $added[0]['level_name'], 'logItemAdded is DEBUG level');
        $test->expect(str_contains($added[0]['message'], '[SUCCESS]'), 'logItemAdded contains [SUCCESS] prefix');

        $removed = $this->captureLog(fn() => $l->logItemRemoved('test-q', 'array', 'm-1'));
        $test->expect('debug' === $removed[0]['level_name'], 'logItemRemoved is DEBUG level');
        $test->expect(str_contains($removed[0]['message'], '[PROCESS]'), 'logItemRemoved contains [PROCESS] prefix');

        return $test->results();
    }

    /**
     * logQueueFill includes a progress bar and percentage in context.
     */
    public function testLogQueueFillHasProgressBar($f3): array
    {
        $test = $this->newTest();
        $l    = $this->logger();

        $records = $this->captureLog(fn() => $l->logQueueFill('test-q', 50, 100));

        $test->expect(str_contains($records[0]['message'], 'QUEUE_FILL'), 'Message contains QUEUE_FILL');
        $test->expect(str_contains($records[0]['context']['progress_bar'], '█'), 'Progress bar contains filled blocks');
        $test->expect('50%' === $records[0]['context']['percentage'], 'Percentage is 50%');

        return $test->results();
    }

    /**
     * logQueueThroughput includes items-per-second in context.
     */
    public function testLogQueueThroughput($f3): array
    {
        $test = $this->newTest();
        $l    = $this->logger();

        $records = $this->captureLog(fn() => $l->logQueueThroughput('test-q', 12.5, 100));

        $test->expect(str_contains($records[0]['message'], '[THROUGHPUT]'), 'Message contains [THROUGHPUT] prefix');
        $test->expect(12.5 === $records[0]['context']['items_per_second'], 'Context has items_per_second');

        return $test->results();
    }

    /**
     * logBatchProgress includes batch progress percentage.
     */
    public function testLogBatchProgress($f3): array
    {
        $test = $this->newTest();
        $l    = $this->logger();

        $records = $this->captureLog(fn() => $l->logBatchProgress('test-q', 10, 4, 2, 8));

        $test->expect(str_contains($records[0]['message'], '[BATCH]'), 'Message contains [BATCH] prefix');
        $test->expect('50%' === $records[0]['context']['batch_progress'], 'Batch progress is 50% (2 of 4)');

        return $test->results();
    }

    /**
     * Capture Monolog records by temporarily pushing a test handler.
     *
     * @param callable $callback the code to execute while capturing
     *
     * @return array<int,array{level_name:string,message:string,context:array}>
     */
    private function captureLog(callable $callback): array
    {
        $logger    = $this->logger();
        $reflection = new \ReflectionClass($logger);
        $prop      = $reflection->getProperty('logger');
        $monolog   = $prop->getValue($logger);

        $handler = new \Monolog\Handler\TestHandler();
        $monolog->pushHandler($handler);

        $callback();

        $monolog->popHandler();

        return array_map(
            static fn($record) => [
                'level_name' => strtolower($record['level_name']),
                'message'    => $record['message'],
                'context'    => $record['context'],
            ],
            $handler->getRecords()
        );
    }
}
