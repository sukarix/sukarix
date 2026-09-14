<?php

declare(strict_types=1);

namespace Sukarix\Behaviours;

use Sukarix\Core\Injector;
use Sukarix\Core\SessionInterface;

trait HasSession
{
    /**
     * @var SessionInterface
     */
    protected $session;

    public function initHasSession(): void
    {
        $this->session = Injector::instance()->get('session');
    }
}
