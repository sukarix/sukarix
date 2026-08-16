<?php

declare(strict_types=1);

namespace Sukarix\Behaviours;

use Sukarix\Core\Injector;
use Sukarix\Queue\QueueService;

trait HasQueue
{
    protected QueueService $queueService;

    /**
     * Resolve the queue service required by the consuming class.
     */
    public function initHasQueue(): void
    {
        $injector = Injector::instance();
        if (!$injector->has('queue')) {
            throw new \LogicException(
                'A queue service must be registered before constructing ' . static::class . '.'
            );
        }

        $queueService = $injector->get('queue');
        if (!$queueService instanceof QueueService) {
            throw new \UnexpectedValueException(
                'The queue service must extend ' . QueueService::class . '.'
            );
        }

        $this->queueService = $queueService;
    }
}
