<?php

declare(strict_types=1);

namespace Suite;

use Helpers\I18nTest;
use Helpers\ReceiverTest;
use Test\TestGroup;

/**
 * Aggregates the Helpers test scenarios for the Sukarix library.
 *
 * @internal
 *
 * @coversNothing
 */
final class HelpersTest extends TestGroup
{
    protected $classes = [
        I18nTest::class,
        ReceiverTest::class,
    ];
}
