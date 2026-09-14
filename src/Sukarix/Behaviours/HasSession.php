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
        // Boot skips prepareSession() on a stateless route. Resolving the alias
        // here would build a session anyway and open one behind that route's
        // back, so take the prepared session and otherwise stay without one.
        // Action already guards every call it makes on $this->session.
        $this->session = \Registry::exists('session') ? Injector::instance()->get('session') : null;
    }
}
