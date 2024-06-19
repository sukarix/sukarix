<?php

declare(strict_types=1);

namespace Sukarix\Notification;

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

            $this->zulip(['content' => "An error occurred on host [https://{$host}](https://{$host})\n* **Error ID:** {$hash}\n* **Subject:** {$subject}\n* **Message:** {$message}"]);
        }
    }

    /**
     * Sends a message to Zulip using F3 Web instance.
     *
     * @param array $params The parameters for the Zulip message.
     *                      Possible keys:
     *                      - 'type': The type of message to send. Possible values: 'stream', 'private', 'direct' and 'channel'.
     *                      - 'to': The destination of the message. For 'stream' type, this is the stream name.
     *                      - 'topic': The topic of the message. Required for 'stream' or 'channel") type.
     *                      - 'content': The content of the message.
     *                      - 'queue_id': The ID of the queue to add the message to.
     *                      - 'local_id': A unique local identifier for the message.
     *                      - 'read_by_sender': Whether the message should be initially marked read by its sender.
     *
     * @return array|false the response from the Zulip API
     */
    public function zulip(array $params = []): array|false
    {
        return \Web::instance()->request(rtrim($this->f3->get('NOTIFICATIONS.zulip.uri'), '/') . '/api/v1/messages', [
            'method' => 'POST',
            'header' => [
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic ' . base64_encode($this->f3->get('NOTIFICATIONS.zulip.mail') . ':' . $this->f3->get('NOTIFICATIONS.zulip.token')),
            ],
            'content' => http_build_query(array_merge([
                'type'  => 'stream',
                'to'    => $this->f3->get('NOTIFICATIONS.zulip.stream'),
                'topic' => $this->f3->get('NOTIFICATIONS.zulip.topic'),
            ], $params)),
        ]);
    }
}
