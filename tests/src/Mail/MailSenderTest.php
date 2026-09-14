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

        };
    }
}
