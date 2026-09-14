<?php

declare(strict_types=1);

namespace Sukarix\Behaviours;

use Sukarix\Core\Injector;
use Sukarix\Core\SessionInterface;

trait HasSession
{
    /**
     * @var null|SessionInterface
     */
    protected $session;

    public function initHasSession(): void
    {
        // No session on a stateless route: resolving the alias would build one anyway.
        $this->session = \Registry::exists('session') ? Injector::instance()->get('session') : null;
    }
}
