<?php

declare(strict_types=1);

namespace Sukarix\Helpers;

use Sukarix\Behaviours\HasF3;
use Sukarix\Behaviours\HasMessages;

/**
 * Class Flash.
 */
class Flash extends Helper
{
    use HasF3;
    use HasMessages;

    public const ALERT       = 'alert';
    public const ERROR       = 'error';
    public const WARNING     = 'warning';
    public const SUCCESS     = 'success';
    public const INFORMATION = 'information';

    /**
     * @param string $text the message text content
     * @param string $type the message type
     */
    public function addMessage($text, $type = self::INFORMATION): void
    {
        $this->messages[$type][] = $text;
    }

    /**
     * Clear all messages.
     */
    public function clearMessages(): void
    {
        $this->messages = [];
    }

    public function hasMessages(): bool
    {
        return !empty($this->messages);
    }

    /**
     * @param string $type
     * @param mixed  $text
     *
     * @return bool
     */
    public function hasMessage($text, $type = self::INFORMATION)
    {
        return \array_key_exists($type, $this->messages) && array_search($text, $this->messages[$type], true) > -1;
    }
}
