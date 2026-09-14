<?php

declare(strict_types=1);

namespace Sukarix\Core;

/**
 * What the framework requires of a session.
 *
 * Kept to the methods Sukarix itself calls, so an application can supply its own
 * implementation — a stateless JWT session, for instance — without inheriting the
 * database backed one.
 */
interface SessionInterface
{
    /**
     * Discard sessions that are no longer current.
     */
    public function cleanupOldSessions(): void;

    /**
     * @param mixed $key
     * @param mixed $value
     */
    public function set($key, $value): void;

    /**
     * @param mixed $key
     *
     * @return mixed
     */
    public function get($key);

    /**
     * Whether a user is authenticated on this session.
     */
    public function isLoggedIn(): bool;

    /**
     * Role name of the authenticated user, or the guest role.
     */
    public function getRole(): string;

    /**
     * Whether the request carries a valid anti-forgery token. A session that does
     * not use them answers true.
     */
    public function validateToken(): bool;
}
