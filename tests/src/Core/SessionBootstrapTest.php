<?php

declare(strict_types=1);

namespace Core;

use Sukarix\Application\Boot;
use Sukarix\Core\Injector;
use Sukarix\Core\Session;
use Test\Scenario;

/**
 * Covers the boot order the session depends on: it has to be built after the
 * database connection, and it has to be the one every Helper and action then
 * resolves.
 *
 * @internal
 *
 * @coversNothing
 */
final class SessionBootstrapTest extends Scenario
{
    protected $group = 'Core Session bootstrap';

    public function testPrepareSessionReplacesASessionTheInjectorAlreadyCached($f3)
    {
        $test = $this->newTest();

        $f3->set('classes.session', Session::class);
        $f3->set('session.table', 'CACHE');
        $this->resetInjector();

        // Constructing a Helper before prepareSession() makes the Injector
        // resolve a session of its own and cache it.
        $early = Injector::instance()->get('session');

        $boot = (new \ReflectionClass(BootDouble::class))->newInstanceWithoutConstructor();
        $property = new \ReflectionProperty(Boot::class, 'f3');
        $property->setValue($boot, $f3);
        $boot->prepareSession();

        $resolved = Injector::instance()->get('session');

        $test->expect($early !== $resolved, 'prepareSession() replaces the session the Injector cached earlier');
        $test->expect(\Registry::get('session') === $resolved, 'the Injector and the Registry hand out the same session');

        $this->resetInjector();

        return $test->results();
    }

    public function testDatabaseBackedSessionWithoutConnectionNamesTheBootOrder($f3)
    {
        $test  = $this->newTest();
        $threw = false;

        // The lookup inside the constructor falls back to the registered
        // connection, so the failure only shows with none registered.
        $hadDb = \Registry::exists('db');
        $db    = $hadDb ? \Registry::get('db') : null;
        if ($hadDb) {
            \Registry::clear('db');
        }

        try {
            new Session(null, 'users_sessions');
        } catch (\LogicException $exception) {
            $threw = true;
            $test->expect(
                str_contains($exception->getMessage(), 'needs a database connection'),
                'the failure says the session has no connection'
            );
            $test->expect(
                str_contains($exception->getMessage(), 'prepareSession'),
                'the failure points at the boot order rather than at Fat-Free internals'
            );
        } finally {
            if ($hadDb) {
                \Registry::set('db', $db);
            }
        }

        $test->expect($threw, 'a database backed session without a connection raises a LogicException');

        return $test->results();
    }

    private function resetInjector(): void
    {
        foreach (['session', Injector::class, Session::class] as $key) {
            if (\Registry::exists($key)) {
                \Registry::clear($key);
            }
        }
    }
}

/**
 * Boot only declares abstract hooks an application fills in; prepareSession()
 * itself is concrete, so the double just satisfies the signature.
 */
final class BootDouble extends Boot
{
    protected function loadConfiguration() {}

    protected function handleException() {}

    protected function loadRoutesAndAccess() {}
}
