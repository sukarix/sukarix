<?php

declare(strict_types=1);

namespace Messaging;

use Sukarix\Messaging\RecordingPublisher;
use Test\Scenario;

/**
 * @internal
 *
 * @coversNothing
 */
final class RecordingPublisherTest extends Scenario
{
    protected $group = 'Messaging RecordingPublisher';

    public function testPublishedMessagesAreRecordedInOrder($f3)
    {
        $publisher = new RecordingPublisher();
        $publisher->publish('broadcast.start', ['id' => 1]);
        $publisher->publish('broadcast.stop', ['id' => 1]);

        $test = $this->newTest();
        $test->expect(2 === \count($publisher->messages), 'both messages are recorded');
        $test->expect('broadcast.start' === $publisher->messages[0]['routing_key'], 'the first routing key is preserved');
        $test->expect(['id' => 1] === $publisher->messages[1]['payload'], 'the payload is preserved');

        return $test->results();
    }
}
