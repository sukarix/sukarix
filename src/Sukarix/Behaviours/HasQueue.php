<?php

declare(strict_types=1);

namespace Sukarix\Behaviours;

use Sukarix\Core\Injector;

trait HasQueue
{
    /**
     * Queue service instance resolved from the injector.
     *
     * @var mixed
     */
    protected $queueService;

    public function initHasQueue(): void
    {
        if (Injector::instance()->has('queue')) {
            $this->queueService = Injector::instance()->get('queue');
        }
    }
}
