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
    /**
     * A bare AUTH LOGIN/PLAIN payload line in the SMTP transcript: base64,
     * no spaces. Every other line in the exchange has one (a command with
     * arguments, or a "NNN " reply code), so this alone tells credentials
     * apart from the rest of the log.
     */
    private const AUTH_PAYLOAD = '/^[A-Za-z0-9+\/]{4,}=*$/m';

    public static function logError($mailer, $log): void
    {
        LoggerFactory::channel('mail')->error('SMTP delivery failed', ['log' => self::redact((string) $log)]);
    }

    public static function traceMail($hash): void
    {
        LoggerFactory::channel('mail')->info('Mail opened', ['hash' => $hash]);
    }

    public static function traceClick($target): void
    {
        LoggerFactory::channel('mail')->info('Mail link followed', ['target' => $target]);
    }

    /**
     * fatfree-core's SMTP class echoes the AUTH LOGIN/PLAIN username and
     * password into the transcript as-is (base64, not encrypted), so a
     * failed send would otherwise put live credentials in the log.
     */
    private static function redact(string $log): string
    {
        return preg_replace(self::AUTH_PAYLOAD, '[redacted]', $log) ?? $log;
    }
}
