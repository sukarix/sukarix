<?php

declare(strict_types=1);

namespace Core;

use Sukarix\Core\Session;
use Sukarix\Core\SessionInterface;
use Test\Scenario;

/**
 * @internal
 *
 * @coversNothing
 */
final class SessionContractTest extends Scenario
{
    protected $group = 'Core Session contract';

    public function testTheShippedSessionSatisfiesTheContract($f3)
    {
        $test = $this->newTest();
        $test->expect(
            \in_array(SessionInterface::class, class_implements(Session::class), true),
            'the database backed session implements the contract'
        );

        return $test->results();
    }

    public function testTheContractCoversEveryMethodTheFrameworkCalls($f3)
    {
        // Whatever Sukarix calls on $this->session has to be in the contract, or an
        // application supplying its own session breaks at run time.
        $called = [];
        foreach ($this->frameworkSources() as $file) {
            preg_match_all('~session->([a-zA-Z]+)\(~', (string) file_get_contents($file), $matches);
            foreach ($matches[1] as $method) {
                $called[$method] = true;
            }
        }

        // ip() is read from the internal session object, not from ours.
        unset($called['ip']);

        $declared = array_flip(get_class_methods(SessionInterface::class));

        $test    = $this->newTest();
        $missing = array_diff_key($called, $declared);
        $test->expect([] === $missing, 'the contract declares every method the framework calls: ' . implode(', ', array_keys($missing)));

        return $test->results();
    }

    public function testAnApplicationSessionIsAccepted($f3)
    {
        $own = new class implements SessionInterface {
            public array $values = [];

            public function cleanupOldSessions(): void {}

            public function set($key, $value): void
            {
                $this->values[$key] = $value;
            }

            public function get($key)
            {
                return $this->values[$key] ?? null;
            }

            public function isLoggedIn(): bool
            {
                return isset($this->values['user']);
            }

            public function getRole(): string
            {
                return 'guest';
            }

            public function validateToken(): bool
            {
                return true;
            }
        };

        $own->set('user', ['id' => 1]);

        $test = $this->newTest();
        $test->expect($own instanceof SessionInterface, 'a session written by an application satisfies the contract');
        $test->expect($own->isLoggedIn(), 'the framework can ask it whether someone is signed in');
        $test->expect('guest' === $own->getRole(), 'the framework can ask it for the role');

        return $test->results();
    }

    /**
     * @return list<string>
     */
    private function frameworkSources(): array
    {
        $root  = \Base::instance()->get('ROOT') . '/src';
        $files = [];
        $tree  = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        foreach ($tree as $file) {
            if ($file->isFile() && 'php' === $file->getExtension()) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
