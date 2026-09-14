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
final class EnvironmentOverrideTest extends Scenario
{
    protected $group = 'Core Environment overrides';

    public function testASetVariableReplacesTheConfiguredValue($f3)
    {
        $f3->set('mailer.smtp.host', 'localhost');
        $f3->set('environment', ['APP_TEST_SMTP_HOST' => 'mailer.smtp.host']);
        putenv('APP_TEST_SMTP_HOST=smtp.example.org');

        $this->applyOverrides();

        $test = $this->newTest();
        $test->expect('smtp.example.org' === $f3->get('mailer.smtp.host'), 'the environment wins over the file');

        putenv('APP_TEST_SMTP_HOST');

        return $test->results();
    }

    public function testAnUnsetVariableLeavesTheValueAlone($f3)
    {
        $f3->set('mailer.smtp.host', 'localhost');
        $f3->set('environment', ['APP_TEST_ABSENT' => 'mailer.smtp.host']);
        putenv('APP_TEST_ABSENT');

        $this->applyOverrides();

        $test = $this->newTest();
        $test->expect('localhost' === $f3->get('mailer.smtp.host'), 'an unset variable changes nothing');

        return $test->results();
    }

    public function testAnEmptyVariableLeavesTheValueAlone($f3)
    {
        $f3->set('mailer.from_mail', 'noreply@example.org');
        $f3->set('environment', ['APP_TEST_EMPTY' => 'mailer.from_mail']);
        putenv('APP_TEST_EMPTY=');

        $this->applyOverrides();

        $test = $this->newTest();
        $test->expect('noreply@example.org' === $f3->get('mailer.from_mail'), 'an empty variable changes nothing');

        putenv('APP_TEST_EMPTY');

        return $test->results();
    }

    public function testNothingHappensWithoutTheSection($f3)
    {
        $f3->clear('environment');
        $f3->set('mailer.smtp.host', 'localhost');

        $this->applyOverrides();

        $test = $this->newTest();
        $test->expect('localhost' === $f3->get('mailer.smtp.host'), 'no declaration means no overrides');

        return $test->results();
    }

    public function testAnEntryWithoutAKeyIsIgnored($f3)
    {
        $f3->set('environment', ['APP_TEST_NO_KEY' => '']);
        putenv('APP_TEST_NO_KEY=value');

        $this->applyOverrides();

        $test = $this->newTest();
        $test->expect(true, 'an entry naming no key is skipped rather than fatal');

        putenv('APP_TEST_NO_KEY');

        return $test->results();
    }

    /**
     * Reach the bootstrap hook without booting a whole application.
     */
    private function applyOverrides(): void
    {
        $bootstrap = (new \ReflectionClass(Bootstrap::class))->newInstanceWithoutConstructor();

        // The constructor boots a whole application; the hook only needs the hive.
        $f3 = new \ReflectionProperty($bootstrap, 'f3');
        $f3->setValue($bootstrap, \Base::instance());

        (new \ReflectionMethod(Bootstrap::class, 'applyEnvironmentOverrides'))->invoke($bootstrap);
    }
}
