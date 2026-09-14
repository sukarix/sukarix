<?php

declare(strict_types=1);

namespace Helpers;

use Sukarix\Helpers\I18n;
use Test\Scenario;

/**
 * @internal
 *
 * @coversNothing
 */
final class I18nTest extends Scenario
{
    private const LANGUAGES = ['en' => 'en-GB', 'fr' => 'fr-FR'];
    protected $group        = 'Helpers I18n negotiateLocale';

    public function testExactMatch($f3)
    {
        $test = $this->newTest();

        $test->expect('fr-FR' === I18n::negotiateLocale(self::LANGUAGES, 'fr-FR,fr;q=0.9,en;q=0.8', 'en'), 'the exact locale tag wins');

        return $test->results();
    }

    public function testLanguageOnlyMatch($f3)
    {
        $test = $this->newTest();

        $test->expect('fr-FR' === I18n::negotiateLocale(self::LANGUAGES, 'fr', 'en'), 'a bare language code resolves to its full locale');

        return $test->results();
    }

    public function testHighestQualityWins($f3)
    {
        $test = $this->newTest();

        // Neither candidate is q=1: the previous implementation only considered
        // q=1 candidates and fell through to the fallback here.
        $test->expect('fr-FR' === I18n::negotiateLocale(self::LANGUAGES, 'en;q=0.8,fr-FR;q=0.9', 'en'), 'the higher-quality candidate wins even when neither is q=1');

        return $test->results();
    }

    public function testZeroQualityIsRejected($f3)
    {
        $test = $this->newTest();

        $test->expect('en-GB' === I18n::negotiateLocale(self::LANGUAGES, 'fr;q=0', 'en'), 'a q=0 candidate is treated as explicitly unacceptable');

        return $test->results();
    }

    public function testNoMatchFallsBackToTheFallbackLocale($f3)
    {
        $test = $this->newTest();

        $test->expect('en-GB' === I18n::negotiateLocale(self::LANGUAGES, 'de-DE,de;q=0.9', 'en'), 'an unsupported language falls back to the resolved fallback locale');

        return $test->results();
    }

    public function testEmptyHeaderResolvesTheFallbackShortCode($f3)
    {
        $test = $this->newTest();

        // The fallback is passed as a short code ("fr"), matching the $languages
        // convention documented on the method; it must resolve to "fr-FR", not
        // be returned verbatim.
        $test->expect('fr-FR' === I18n::negotiateLocale(self::LANGUAGES, '', 'fr'), 'an empty header resolves the fallback short code to its full locale');

        return $test->results();
    }

    public function testNoSupportedLanguages($f3)
    {
        $test = $this->newTest();

        $test->expect('en' === I18n::negotiateLocale([], 'fr-FR', 'en'), 'with no supported languages the fallback is returned as-is');

        return $test->results();
    }
}
