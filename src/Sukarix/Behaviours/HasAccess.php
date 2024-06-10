<?php

declare(strict_types=1);

namespace Sukarix\Behaviours;

use Sukarix\Core\Injector;

trait HasAccess
{
    /**
     * @var \Access
     */
    private $access;

    public function initHasAccess(): void
    {
        $this->access = Injector::instance()->get('access');
    }
}
