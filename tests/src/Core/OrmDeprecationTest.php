<?php

declare(strict_types=1);

namespace Core;

use Sukarix\Application\Bootstrap;
use Test\Scenario;

/**
 * @internal
 *
 * @coversNothing
 */
final class OrmDeprecationTest extends Scenario
{
    protected $group = 'Core ORM deprecations';

    public function testDeprecationsAreMaskedByDefault($f3)
    {
        $before = error_reporting(E_ALL);
        $f3->clear('orm.mask_deprecations');

        $this->mask();
        $masked = error_reporting();

        error_reporting($before);

        $test = $this->newTest();
        $test->expect(0 === ($masked & E_DEPRECATED), 'a deprecation no longer reaches the error handler');
        $test->expect(0 !== ($masked & E_WARNING), 'everything else is still reported');

        return $test->results();
    }

    public function testMaskingCanBeTurnedOff($f3)
    {
        $before = error_reporting(E_ALL);
        $f3->set('orm.mask_deprecations', false);

        $this->mask();
        $kept = error_reporting();

        error_reporting($before);
        $f3->clear('orm.mask_deprecations');

        $test = $this->newTest();
        $test->expect(0 !== ($kept & E_DEPRECATED), 'the mask is skipped once the configuration says so');

        return $test->results();
    }

    private function mask(): void
    {
        $bootstrap = (new \ReflectionClass(Bootstrap::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty($bootstrap, 'f3'))->setValue($bootstrap, \Base::instance());
        (new \ReflectionMethod(Bootstrap::class, 'maskOrmDeprecations'))->invoke($bootstrap);
    }
}
