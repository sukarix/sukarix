<?php

declare(strict_types=1);

namespace Core;

use Sukarix\Http\Cors;
use Test\Scenario;

/**
 * @internal
 *
 * @coversNothing
 */
final class CorsTest extends Scenario
{
    protected $group = 'Core CORS';

    public function testUnknownOriginIsIgnored($f3)
    {
        $f3->set('HEADERS.Origin', 'https://evil.example');
        $f3->set('VERB', 'GET');

        $handled = Cors::handle($f3, ['https://console.example']);

        $test = $this->newTest();
        $test->expect(false === $handled, 'unknown origin is not a handled preflight');

        return $test->results();
    }

    public function testAllowedGetIsNotAPreflight($f3)
    {
        $f3->set('HEADERS.Origin', 'https://console.example');
        $f3->set('VERB', 'GET');

        $handled = Cors::handle($f3, ['https://console.example']);

        $test = $this->newTest();
        $test->expect(false === $handled, 'GET on an allowed origin is not consumed as a preflight');

        return $test->results();
    }

    public function testOptionsPreflightIsHandled($f3)
    {
        $f3->set('HEADERS.Origin', 'https://console.example');
        $f3->set('VERB', 'OPTIONS');

        $handled = Cors::handle($f3, ['https://console.example']);

        $test = $this->newTest();
        $test->expect(true === $handled, 'OPTIONS preflight from an allowed origin is handled');

        return $test->results();
    }
}
