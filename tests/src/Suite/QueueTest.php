<?php

declare(strict_types=1);

namespace Suite;

use Test\TestGroup;

/**
 * Aggregates all Queue test scenarios for the Sukarix library.
 *
 * @internal
 *
 * @coversNothing
 */
final class QueueTest extends TestGroup
{
    protected $classes = [
        \Queue\QueueServiceLockingTest::class,
        \Queue\QueueServiceAttemptsTest::class,
        \Queue\QueueServiceEdgeCasesTest::class,
        \Queue\QueueProgressLoggerTest::class,
    ];
}
