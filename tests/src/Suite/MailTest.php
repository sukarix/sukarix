<?php

declare(strict_types=1);

namespace Suite;

use Mail\MailSenderTest;
use Test\TestGroup;

/**
 * Aggregates the Mail test scenarios for the Sukarix library.
 *
 * @internal
 *
 * @coversNothing
 */
final class MailTest extends TestGroup
{
    protected $classes = [
        MailSenderTest::class,
    ];
}
