<?php

declare(strict_types=1);

namespace Sukarix\Behaviours;

use Sukarix\Core\Injector;
use Sukarix\Core\Session;

trait HasSession
{
    /**
     * f3 instance.
     *
     * @var Session f3
     */
    protected $session;

    public function initHasSession(): void
    {
        $this->session = Injector::instance()->get('session');
    }
}
