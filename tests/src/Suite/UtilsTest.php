<?php

declare(strict_types=1);

namespace Suite;

use Test\TestGroup;
use Utils\DataUtilsTest;
use Utils\FileSystemUtilsTest;

/**
 * Aggregates all Utils test scenarios for the Sukarix library.
 *
 * @internal
 *
 * @coversNothing
 */
final class UtilsTest extends TestGroup
{
    protected $classes = [
        DataUtilsTest::class,
        FileSystemUtilsTest::class,
    ];
}
