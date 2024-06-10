<?php

declare(strict_types=1);

namespace Sukarix\Mail;

use Nette\Utils\Strings;
use Sukarix\Behaviours\HasF3;
use Sukarix\Behaviours\LogWriter;
use Sukarix\Configuration\Environment;
use Sukarix\Core\Tailored;

/**
 * MailSender Class.
 */
class MailSender extends Tailored
{
    use HasF3;
    use LogWriter;

    /**
     * @var \Mailer
     */
    protected $mailer;

    public function __construct()
    {
        $this->mailer = new \Mailer('UTF-8');
        \Mailer::initTracking();
    }

    /**
     * @param \Exception $exception
     */
    public function sendExceptionEmail($exception): void
    {
        $hash         = mb_substr(md5(preg_replace('~(Resource id #)\d+~', '$1', $exception)), 0, 10);
        $mailSentPath = $this->f3->get('ROOT') . '/' . $this->f3->get('LOGS') . 'email-sent-' . $hash;
        $snooze       = strtotime('1 day') - time();
        $messageId    = $this->generateId();
        if (@filemtime($mailSentPath) + $snooze < time() && @file_put_contents($mailSentPath, 'sent')) {
            $this->f3->set('mailer.from_name', 'BBB LB Debugger');
            $subject = 'PHP: An error occurred on server ' . Environment::getHostName() . " ERROR ID '{$hash}'";
            $message = 'An error occurred on <b>' . Environment::getHostName() . '</b><br />' . nl2br($exception->getTraceAsString());
            $this->smtpSend(null, $this->f3->get('debug.email'), 'Sukarix Application Debugger', $subject, $message, $messageId);
        }
    }

    public function send($template, $vars, $to, $title, $subject): bool
    {
        $messageId         = $this->generateId();
        $vars['date']      = date('%A %d %B %A à %T');
        $vars['messageId'] = Strings::before(mb_substr($messageId, 1, -1), '@');
        $vars['SCHEME']    = $this->f3->get('SCHEME');
        $vars['HOST']      = Environment::getHostName();
        $vars['PORT']      = $this->f3->get('PORT');
        $vars['BASE']      = $this->f3->get('BASE');
        $message           = \Template::instance()->render('mail/' . $template . '.phtml', null, $vars);

        return $this->smtpSend(null, $to, $title, $subject, $message, $messageId);
    }

    private function smtpSend($from, $to, $title, $subject, $message, $messageId): bool
    {
        if (\is_array($to)) {
            foreach ($to as $email) {
                $this->mailer->addTo($email);
            }
        } else {
            $this->mailer->addTo($to, $title);
        }

        if (null !== $from) {
            $this->mailer->setFrom($from);
        }
        $this->mailer->setHTML($message);
        $this->mailer->set('Message-Id', $messageId);
        $sent = $this->mailer->send($subject, Environment::isNotProduction());

        if (false !== $sent && Environment::isNotProduction()) {
            @file_put_contents(
                $this->f3->get('MAIL_STORAGE') . mb_substr($messageId, 1, -1) . '.eml',
                explode("354 Go ahead\n", explode("250 OK\nQUIT", $this->mailer->log())[0])[1]
            );
        }

        $this->logger->info('Sending email | Status: ' . ($sent ? 'true' : 'false') . " | Log:\n" . $this->mailer->log());

        return (true === $sent) ? $messageId : $sent;
    }

    /**
     * Generate a unique message id.
     */
    private function generateId(): string
    {
        return sprintf(
            '<%s.%s@%s>',
            base_convert(microtime(), 10, 36),
            base_convert(bin2hex(openssl_random_pseudo_bytes(8)), 16, 36),
            Environment::getHostName()
        );
    }
}
