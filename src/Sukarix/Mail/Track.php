<?php

declare(strict_types=1);

namespace Sukarix\Mail;

use Sukarix\Observability\LoggerFactory;

/**
 * Callbacks wired from `smtp.ini` (`mailer.on.failure` / `on.ping` /
 * `on.jump`) into ikkez/f3-mailer's delivery-failure and open/click
 * tracking hooks.
 */
final class Track
{
    public static function logError($mailer, $log): void
    {
        LoggerFactory::channel('mail')->error('SMTP delivery failed', ['log' => $log]);
    }

    public static function traceMail($hash): void
    {
        LoggerFactory::channel('mail')->info('Mail opened', ['hash' => $hash]);
    }

    public static function traceClick($target): void
    {
        LoggerFactory::channel('mail')->info('Mail link followed', ['target' => $target]);
    }
}
