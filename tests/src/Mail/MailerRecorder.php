<?php

declare(strict_types=1);

namespace Mail;

/**
 * Stands in for the f3 mailer and records the calls made on it.
 *
 * @internal
 *
 * @coversNothing
 */
class MailerRecorder
{
    /** @var null|array{0: string, 1: null|string} */
    public $from;

    public bool $sent = false;

    public array $recipients = [];

    public function setFrom($email, $title = null): void
    {
        $this->from = [$email, $title];
    }

    public function addTo($email, $title = null): void
    {
        $this->recipients[$email] = $title;
    }

    public function setHTML($message): void {}

    public function set($key, $value): void {}

    public function send($subject, $log = false)
    {
        $this->sent = true;

        return true;
    }

    public function log(): string
    {
        return '';
    }
}
