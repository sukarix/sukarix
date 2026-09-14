<?php

declare(strict_types=1);

namespace Mail;

use Sukarix\Mail\MailSender;
use Test\Scenario;

/**
 * @internal
 *
 * @coversNothing
 */
final class MailSenderTest extends Scenario
{
    protected $group = 'Mail MailSender';

    public function testSenderNameIsPutOnTheMessage($f3)
    {
        $f3->set('mailer.from_name', 'Sukarix Notifications');

        $sender = $this->newSender();
        $sender->sendTo('noreply@example.org', 'someone@example.org');

        $test = $this->newTest();
        $test->expect(
            ['noreply@example.org', 'Sukarix Notifications'] === $sender->recorder()->from,
            'the configured sender name is passed with the address'
        );

        return $test->results();
    }

    public function testUnreachableServerIsReportedNotFatal($f3)
    {
        $f3->set('mailer.smtp.host', 'does-not-exist.invalid');
        $f3->set('mailer.smtp.port', 2525);

        $sender = $this->newSender();
        $answer = $sender->sendTo('noreply@example.org', 'someone@example.org');

        $test = $this->newTest();
        $test->expect(false === $answer, 'an unreachable server answers false instead of aborting the request');
        $test->expect(!$sender->recorder()->sent, 'the transport is never asked to send');

        return $test->results();
    }

    public function testAMissingHostIsNotReachable($f3)
    {
        $f3->set('mailer.smtp.host', '');

        $test = $this->newTest();
        $test->expect(!$this->newSender()->reachable(), 'an empty host is not reachable');

        return $test->results();
    }

    public function testTimeoutFallsBackWhenUnset($f3)
    {
        $f3->clear('mailer.smtp.timeout');
        $sender = $this->newSender();

        $test = $this->newTest();
        $test->expect(2 === $sender->timeout(), 'the wait falls back to two seconds');

        $f3->set('mailer.smtp.timeout', 5);
        $test->expect(5 === $sender->timeout(), 'a configured wait is used');

        return $test->results();
    }

    public function testTheSameExceptionIsOnlyReportedOnce($f3)
    {
        $f3->set('LOGS', 'tmp/');
        $f3->set('mailer.smtp.host', 'does-not-exist.invalid');
        $f3->set('debug.email', 'devops@example.org');

        $exception = new \RuntimeException('the same failure');
        foreach (glob($f3->get('ROOT') . '/' . $f3->get('LOGS') . 'email-sent-*') as $stale) {
            unlink($stale);
        }

        $sender = $this->newSender();
        $sender->sendExceptionEmail($exception);
        $marks = glob($f3->get('ROOT') . '/' . $f3->get('LOGS') . 'email-sent-*');

        $test = $this->newTest();
        $test->expect(1 === \count($marks), 'the first report leaves one snooze mark');

        $sender->sendExceptionEmail($exception);
        $test->expect(
            1 === \count(glob($f3->get('ROOT') . '/' . $f3->get('LOGS') . 'email-sent-*')),
            'a repeat of the same exception is snoozed rather than reported again'
        );

        foreach ($marks as $mark) {
            unlink($mark);
        }

        return $test->results();
    }

    public function testTheDebuggerNameCanBeSet($f3)
    {
        $f3->set('LOGS', 'tmp/');
        $f3->set('mailer.smtp.host', 'does-not-exist.invalid');
        $f3->set('debug.email', 'devops@example.org');
        $f3->set('mailer.debugger_name', 'BBBEasy Debugger');

        foreach (glob($f3->get('ROOT') . '/' . $f3->get('LOGS') . 'email-sent-*') as $stale) {
            unlink($stale);
        }

        $this->newSender()->sendExceptionEmail(new \RuntimeException('named failure'));

        $test = $this->newTest();
        $test->expect('BBBEasy Debugger' === $f3->get('mailer.from_name'), 'the report is sent under the configured name');

        $f3->clear('mailer.debugger_name');
        foreach (glob($f3->get('ROOT') . '/' . $f3->get('LOGS') . 'email-sent-*') as $mark) {
            unlink($mark);
        }

        return $test->results();
    }

    /**
     * A sender whose transport records what it was asked to do.
     */
    private function newSender()
    {
        // Keep the stored copy of a sent mail out of the library root.
        \Base::instance()->set('MAIL_STORAGE', \Base::instance()->get('TEMP'));

        return new class extends MailSender {
            public function __construct()
            {
                parent::__construct();
                $this->mailer = new MailerRecorder();
            }

            public function recorder(): MailerRecorder
            {
                return $this->mailer;
            }

            public function sendTo($from, $to): bool
            {
                return $this->smtpSend($from, $to, 'title', 'subject', 'message', '<id@example.org>');
            }

            public function reachable(): bool
            {
                return $this->smtpIsReachable();
            }

            public function timeout(): int
            {
                return $this->smtpTimeout();
            }
        };
    }
}
