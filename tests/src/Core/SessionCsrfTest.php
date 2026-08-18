<?php

declare(strict_types=1);

namespace Core;

use Sukarix\Core\Session;
use Test\Scenario;

/**
 * Tests the CSRF token lifecycle and validation of the Session.
 *
 * @internal
 *
 * @coversNothing
 */
final class SessionCsrfTest extends Scenario
{
    protected $group = 'Core Session CSRF';

    public function testTokenIsReusedWhileLive($f3)
    {
        $session = $this->newSession($f3);

        $first  = $session->generateToken();
        $second = $session->generateToken();

        $test = $this->newTest();
        $test->expect('' !== $first, 'a token is issued');
        $test->expect($first === $second, 'the same token is reused while it is still valid');

        return $test->results();
    }

    public function testTokenIsRenewedOnceExpired($f3)
    {
        $first = $this->newSession($f3)->generateToken();

        // Fat-Free computes one CSRF value per Session instance, so a new instance is
        // needed to observe the renewal, as would happen on the next request
        $f3->set('SESSION.csrf_expiry', time() - 1);
        $second = (new Session())->generateToken();

        $test = $this->newTest();
        $test->expect($first !== $second, 'an expired token is replaced on the next request');
        $test->expect($second === $f3->get('SESSION.csrf_token'), 'the new token is stored in the session');

        return $test->results();
    }

    public function testValidateTokenAcceptsRequestParameter($f3)
    {
        $session = $this->newSession($f3);
        $token   = $session->generateToken();
        $f3->set('REQUEST.' . Session::CSRF_FIELD, $token);

        $test = $this->newTest();
        $test->expect($session->validateToken(), 'a token sent as a request parameter is accepted');
        $test->expect($session->isCsrfValid(), 'the session is flagged as valid');

        return $test->results();
    }

    public function testValidateTokenAcceptsHeader($f3)
    {
        $session = $this->newSession($f3);
        $token   = $session->generateToken();
        $f3->set('HEADERS.' . Session::CSRF_HEADER, $token);

        $test = $this->newTest();
        $test->expect($session->validateToken(), 'a token sent as a header is accepted');

        return $test->results();
    }

    public function testValidateTokenRejectsForgedToken($f3)
    {
        $session = $this->newSession($f3);
        $session->generateToken();
        $f3->set('REQUEST.' . Session::CSRF_FIELD, 'forged');

        $test = $this->newTest();
        $test->expect(!$session->validateToken(), 'a forged token is rejected');
        $test->expect(!$session->isCsrfValid(), 'the session is flagged as invalid');

        return $test->results();
    }

    public function testValidateTokenRejectsMissingToken($f3)
    {
        $session = $this->newSession($f3);
        $session->generateToken();

        $test = $this->newTest();
        $test->expect(!$session->validateToken(), 'a request without a token is rejected');

        return $test->results();
    }

    public function testValidateTokenRejectsExpiredToken($f3)
    {
        $session = $this->newSession($f3);
        $token   = $session->generateToken();
        $f3->set('REQUEST.' . Session::CSRF_FIELD, $token);
        $f3->set('SESSION.csrf_expiry', time() - 1);

        $test = $this->newTest();
        $test->expect(!$session->validateToken(), 'an expired token is rejected');

        return $test->results();
    }

    public function testTokenStaysValidAcrossSuccessiveRequests($f3)
    {
        $session = $this->newSession($f3);
        $token   = $session->generateToken();
        $f3->set('REQUEST.' . Session::CSRF_FIELD, $token);

        $test = $this->newTest();
        $test->expect($session->validateToken(), 'the token validates once');
        $test->expect($session->validateToken(), 'the same token validates again, it is not single use');

        return $test->results();
    }

    public function testValidationIsSkippedWhenDisabled($f3)
    {
        $session = $this->newSession($f3, false);

        $test = $this->newTest();
        $test->expect($session->validateToken(), 'validation passes when CSRF protection is disabled');

        return $test->results();
    }

    /**
     * Builds a cache backed Session with a clean CSRF state.
     *
     * @param mixed $f3
     */
    private function newSession($f3, bool $enabled = true): Session
    {
        $f3->set('SECURITY.csrf.enabled', $enabled);
        $f3->set('SECURITY.csrf.expiry', 3600);
        $f3->set('session.table', 'CACHE');

        foreach (['csrf_token', 'csrf_expiry', 'csrf_valid', 'form_errors'] as $key) {
            $f3->clear('SESSION.' . $key);
        }
        $f3->clear('REQUEST.' . Session::CSRF_FIELD);
        $f3->clear('HEADERS.' . Session::CSRF_HEADER);

        return new Session();
    }
}
