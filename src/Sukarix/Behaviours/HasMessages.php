<?php

declare(strict_types=1);

namespace Sukarix\Behaviours;

trait HasMessages
{
    /**
     * @var array
     */
    protected $messages;

    public function initHasMessages(): void
    {
        $this->messages = &$this->f3->ref('SESSION.notifications');
    }
}
