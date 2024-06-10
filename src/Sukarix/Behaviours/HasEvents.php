<?php

declare(strict_types=1);

namespace Sukarix\Behaviours;

use Sugar\Event;

trait HasEvents
{
    /**
     * @var Event
     */
    private $events;

    public function initHasEvents(): void
    {
        $this->events = Event::instance();
    }
}
