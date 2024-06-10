<?php

declare(strict_types=1);

namespace Sukarix\Actions\Core;

use Sukarix\Actions\WebAction;

/**
 * Class SessionsClean.
 */
class SessionsClean extends WebAction
{
    /**
     * Deletes expired sessions.
     *
     * @param \Base $f3
     * @param array $params
     */
    public function execute($f3, $params): void
    {
        $this->session->cleanupOldSessions();
    }
}
