<?php

declare(strict_types=1);

namespace Suite;

use Messaging\RecordingPublisherTest;
use Test\TestGroup;

/**
 * Aggregates the Messaging test scenarios for the Sukarix library.
 *
 * @internal
 *
 * @coversNothing
 */
final class MessagingTest extends TestGroup
{
    protected $classes = [
        RecordingPublisherTest::class,
    ];
}
