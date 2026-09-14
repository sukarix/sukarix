<?php

declare(strict_types=1);

namespace Core;

use Sukarix\Actions\ApiAction;
use Test\Scenario;

/**
 * @internal
 *
 * @coversNothing
 */
final class ApiActionTest extends Scenario
{
    protected $group = 'Core ApiAction';

    public function testErrorEnvelope($f3)
    {
        $f3->set('ERROR.code', 404);
        $f3->set('ERROR.text', 'Not Found');

        ob_start();
        ApiAction::onError($f3);
        $body = json_decode((string) ob_get_clean(), true);

        $test = $this->newTest();
        $test->expect(false === ($body['success'] ?? true), 'API errors use the JSON envelope');
        $test->expect(404 === ($body['status'] ?? 0), 'status is copied from ERROR.code');
        $test->expect('Not Found' === ($body['message'] ?? ''), 'message is copied from ERROR.text');

        return $test->results();
    }

    public function testOptionsWithoutOriginsIsNoContent($f3)
    {
        $f3->set('api.cors.origins', []);
        $action = new class extends ApiAction {
            public function ping(): void
            {
            }
        };

        ob_start();
        $action->options();
        ob_end_clean();

        $test = $this->newTest();
        $test->expect(true, 'OPTIONS without a CORS allow-list still returns');

        return $test->results();
    }
}
