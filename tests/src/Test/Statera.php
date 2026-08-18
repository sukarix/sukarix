<?php

declare(strict_types=1);

namespace Test;

use Suite\CoreTest;
use Suite\QueueTest;
use Suite\UtilsTest;
use Sukarix\Statera as SukarixStatera;

/**
 * Library-level Statera registrar. Registers the test groups that make up
 * the Sukarix library test suite.
 *
 * @internal
 *
 * @coversNothing
 */
class Statera extends SukarixStatera
{
    public static function registerGroups(): void
    {
        self::setGroups([
            CoreTest::class,
            QueueTest::class,
            UtilsTest::class,
        ]);
    }
}
