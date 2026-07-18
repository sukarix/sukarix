<?php

declare(strict_types=1);

namespace Suite;

use Test\TestGroup;

/**
 * Aggregates all Core test scenarios for the Sukarix library.
 *
 * @internal
 *
 * @coversNothing
 */
final class CoreTest extends TestGroup
{
    protected $classes = [
        \Core\InjectorTest::class,
    ];
}
