<?php

declare(strict_types=1);

namespace Core;

use Sukarix\Security\CapabilityToken;
use Test\Scenario;

/**
 * @internal
 *
 * @coversNothing
 */
final class CapabilityTokenTest extends Scenario
{
    protected $group = 'Core Capability Token';

    public function testIssueAndVerify($f3)
    {
        $token  = CapabilityToken::issue('s3cret', ['iss' => 'spoutbreeze', 'aud' => 'jobs'], 60);
        $claims = CapabilityToken::verify('s3cret', $token, 'jobs');

        $test = $this->newTest();
        $test->expect(str_contains($token, '.'), 'token is payload.signature');
        $test->expect('spoutbreeze' === ($claims['iss'] ?? null), 'issuer is preserved');
        $test->expect('jobs' === ($claims['aud'] ?? null), 'audience is preserved');
        $test->expect(null === CapabilityToken::verify('other', $token), 'forged secret is rejected');
        $test->expect(null === CapabilityToken::verify('s3cret', $token, 'other'), 'foreign audience is rejected');
        $test->expect(null === CapabilityToken::verify('s3cret', 'not-a-token'), 'malformed token is rejected');

        return $test->results();
    }

    public function testExpiredTokenIsRejected($f3)
    {
        $token = CapabilityToken::issue('s3cret', ['iss' => 'spoutbreeze', 'aud' => 'jobs'], -10);

        $test = $this->newTest();
        $test->expect(null === CapabilityToken::verify('s3cret', $token, 'jobs'), 'expired token is rejected');

        return $test->results();
    }
}
