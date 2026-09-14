<?php

declare(strict_types=1);

namespace Sukarix\Mail;

use Sukarix\Behaviours\HasF3;
use Sukarix\Behaviours\LogWriter;
use Sukarix\Configuration\Environment;
use Sukarix\Core\Processor;
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
        Processor::instance()->initialize($this);
        $this->mailer = new \Mailer('UTF-8');
        \Mailer::initTracking();
    }

    /**
     * @param \Exception $exception
     */
    public function sendExceptionEmail($exception): void
    {
        // The hash only groups repeats of the same exception, it is not a signature.
        $hash         = mb_substr(hash('xxh128', (string) preg_replace('~(Resource id #)\d+~', '$1', (string) $exception)), 0, 10);
        $mailSentPath = $this->f3->get('ROOT') . '/' . $this->f3->get('LOGS') . 'email-sent-' . $hash;
        $snooze       = strtotime('1 day') - time();
        $messageId    = $this->generateId();
        $lastSent     = is_file($mailSentPath) ? (int) filemtime($mailSentPath) : 0;

        if ($lastSent + $snooze < time() && false !== file_put_contents($mailSentPath, 'sent')) {
            $this->f3->set('mailer.from_name', 'Application Debugger');
            $subject = 'PHP: An error occurred on server ' . Environment::getHostName() . " ERROR ID '{$hash}'";
            $message = 'An error occurred on <b>' . Environment::getHostName() . '</b><br />' . nl2br($exception->getTraceAsString());
            $this->smtpSend(null, $this->f3->get('debug.email'), 'Application Debugger', $subject, $message, $messageId);
        }
    }

    public function send($template, $vars, $to, $title, $subject): bool
    {
        $messageId         = $this->generateId();
        $vars['date']      = date('l j F Y H:i:s');
        $vars['messageId'] = mb_strstr(mb_substr($messageId, 1, -1), '@', true);
        $vars['SCHEME']    = $this->f3->get('SCHEME');
        $vars['HOST']      = Environment::getHostName();
        $vars['PORT']      = $this->f3->get('PORT');
        $vars['BASE']      = $this->f3->get('BASE');
        $message           = \Template::instance()->render('mail/' . $template . '.phtml', null, $vars);

        return $this->smtpSend(null, $to, $title, $subject, $message, $messageId);
    }

    /**
     * Check that the configured SMTP server accepts connections.
     */
    protected function smtpIsReachable(): bool
    {
        $host = (string) $this->f3->get('mailer.smtp.host');
        $port = (int) ($this->f3->get('mailer.smtp.port') ?: 25);

        if ('' === $host) {
            return false;
        }

        // A refused connection is the answer this asks for, not a warning in the log.
        set_error_handler(static fn (): bool => true);

        try {
            $socket = fsockopen(mb_strtolower($host), $port, $errno, $error, (float) $this->smtpTimeout());
        } finally {
            restore_error_handler();
        }

        if (!$socket) {
            return false;
        }

        fclose($socket);

        return true;
    }

    /**
     * Seconds to wait for the server to answer.
     */
    protected function smtpTimeout(): int
    {
        return (int) ($this->f3->get('mailer.smtp.timeout') ?: 2);
    }

    protected function smtpSend($from, $to, $title, $subject, $message, $messageId): bool
    {
        if (\is_array($to)) {
            foreach ($to as $email) {
                $this->mailer->addTo($email);
            }
        } else {
            $this->mailer->addTo($to, $title);
        }

        if (null !== $from) {
            // A relay authenticates as one identity and sends as another, so the
            // sender name belongs on the message. Without it the mail arrives
            // showing a bare address.
            $this->mailer->setFrom($from, $this->f3->get('mailer.from_name'));
        }
        $this->mailer->setHTML($message);
        $this->mailer->set('Message-Id', $messageId);

        // The SMTP transport aborts the whole request when the server cannot be
        // reached, so ask first and report the failure to the caller.
        if (!$this->smtpIsReachable()) {
            $this->logger->error('Sending email failed, the SMTP server is unreachable', [
                'host' => $this->f3->get('mailer.smtp.host'),
                'port' => $this->f3->get('mailer.smtp.port'),
            ]);

            return false;
        }

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
    protected function generateId(): string
    {
        return \sprintf(
            '<%s.%s@%s>',
            base_convert(microtime(), 10, 36),
            base_convert(bin2hex(openssl_random_pseudo_bytes(8)), 16, 36),
            Environment::getHostName()
        );
    }
}
