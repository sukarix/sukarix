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
final class StatelessRouteTest extends Scenario
{
    protected $group = 'Core Stateless routes';

    public function testApiIsStatelessByDefault($f3)
    {
        $f3->clear('SECURITY.stateless.prefixes');

        $test = $this->newTest();
        $test->expect($this->isStateless('/api/rooms'), 'an /api path opens no session by default');
        $test->expect(!$this->isStateless('/rooms'), 'another path still opens one');

        return $test->results();
    }

    public function testAnEmptyListKeepsASessionEverywhere($f3)
    {
        $f3->set('SECURITY.stateless.prefixes', []);

        $test = $this->newTest();
        $test->expect(!$this->isStateless('/api/rooms'), 'an empty list turns the default off');

        $f3->clear('SECURITY.stateless.prefixes');

        return $test->results();
    }

    public function testABlankEntryMatchesNothing($f3)
    {
        $f3->set('SECURITY.stateless.prefixes', '');

        $test = $this->newTest();
        $test->expect(!$this->isStateless('/api/rooms'), 'a blank prefix does not make every path stateless');

        $f3->clear('SECURITY.stateless.prefixes');

        return $test->results();
    }

    public function testADeclaredButEmptyKeyTurnsItOff($f3)
    {
        // An ini entry with nothing after the equals sign.
        $f3->set('SECURITY.stateless.prefixes', null);

        $test = $this->newTest();
        $test->expect(!$this->isStateless('/api/rooms'), 'a declared empty key keeps a session everywhere');

        $f3->clear('SECURITY.stateless.prefixes');

        return $test->results();
    }

    public function testADeclaredListIsUsed($f3)
    {
        $f3->set('SECURITY.stateless.prefixes', ['/hooks']);

        $test = $this->newTest();
        $test->expect($this->isStateless('/hooks/incoming'), 'a declared prefix is stateless');
        $test->expect(!$this->isStateless('/api/rooms'), 'and replaces the default rather than adding to it');

        $f3->clear('SECURITY.stateless.prefixes');

        return $test->results();
    }

    private function isStateless(string $path): bool
    {
        \Base::instance()->set('PATH', $path);

        $bootstrap = (new \ReflectionClass(Bootstrap::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty($bootstrap, 'f3'))->setValue($bootstrap, \Base::instance());

        return (bool) (new \ReflectionMethod(Bootstrap::class, 'isStatelessRoute'))->invoke($bootstrap);
    }
}
