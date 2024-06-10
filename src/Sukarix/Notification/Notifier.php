<?php

declare(strict_types=1);

namespace Sukarix\Notification;

use Guanguans\Notify\Factory;
use Guanguans\Notify\Messages\Zulip\StreamMessage;
use Sukarix\Behaviours\HasF3;
use Sukarix\Behaviours\LogWriter;
use Sukarix\Configuration\Environment;
use Sukarix\Core\Tailored;

class Notifier extends Tailored
{
    use HasF3;
    use LogWriter;

    /**
     * @param \Exception $exception
     */
    public function notifyException($exception): void
    {
        $hash         = mb_substr(md5(preg_replace('~(Resource id #)\d+~', '$1', $exception->__toString())), 0, 10);
        $mailSentPath = $this->f3->get('ROOT') . '/' . $this->f3->get('LOGS') . 'zulip-sent-' . $hash;
        $snooze       = strtotime('1 day') - time();
        if (@filemtime($mailSentPath) + $snooze < time() && @file_put_contents($mailSentPath, 'sent')) {
            $subject = 'PHP: An error occurred on server ' . Environment::getHostName() . " ERROR ID '{$hash}'";
            $message = 'An error occurred on <b>' . Environment::getHostName() . '</b><br />' . nl2br($exception->getTraceAsString());
            $host    = Environment::getHostName();

            Factory::zulip()
                ->setToken($this->f3->get('NOTIFICATIONS.zulip.token'))
                ->setEmail($this->f3->get('NOTIFICATIONS.zulip.mail'))
                ->setBaseUri($this->f3->get('NOTIFICATIONS.zulip.uri'))
                ->setMessage(
                    new StreamMessage(
                        [
                            'to'      => $this->f3->get('NOTIFICATIONS.zulip.stream'),
                            'topic'   => $this->f3->get('NOTIFICATIONS.zulip.topic'),
                            'content' => "An error occurred on host [https://{$host}](https://{$host})\n* **Error ID:** {$hash}\n* **Subject:** {$subject}\n* **Message:** {$message}",
                        ]
                    )
                )
                ->send()
            ;
        }
    }
}
