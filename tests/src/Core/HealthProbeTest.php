<?php

declare(strict_types=1);

namespace Core;

use Sukarix\Actions\Health\Probe;
use Test\Scenario;

/**
 * @internal
 *
 * @coversNothing
 */
final class HealthProbeTest extends Scenario
{
    protected $group = 'Core HealthProbe';

    public function testLivenessReportsOk($f3)
    {
        $f3->set('application.request_id', 'req-123');

        $body = $this->call(new Probe(), 'liveness');

        $test = $this->newTest();
        $test->expect('ok' === ($body['status'] ?? null), 'liveness reports ok');
        $test->expect('req-123' === ($body['request_id'] ?? null), 'the request id is echoed');

        return $test->results();
    }

    public function testReadinessWithoutRedisConfiguredIsOk($f3)
    {
        $f3->set('CACHE', 'folder=tmp/cache/');

        $body = $this->call(new Probe(), 'readiness');

        $test = $this->newTest();
        $test->expect('ok' === ($body['status'] ?? null), 'readiness is ok with no redis check configured');
        $test->expect(true === ($body['checks']['app'] ?? null), 'the app check is present');
        $test->expect(!\array_key_exists('redis', $body['checks'] ?? []), 'no redis check when CACHE is not redis-backed');

        return $test->results();
    }

    /**
     * @return array<string, mixed>
     */
    private function call(Probe $probe, string $method): array
    {
        ob_start();
        $probe->{$method}();

        return json_decode((string) ob_get_clean(), true);
    }
}
