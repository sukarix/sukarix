<?php

declare(strict_types=1);

namespace Suite;

use Core\InjectorTest;
use Core\ResponseTest;
use Core\SessionCsrfTest;
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
        InjectorTest::class,
        ResponseTest::class,
        SessionCsrfTest::class,
    ];
}
