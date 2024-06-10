<?php

declare(strict_types=1);

namespace Sukarix\Core;

use DB\SQL;
use DB\SQL\Session as SQLSession;
use Models\User;
use Session as F3Session;
use Sukarix\Behaviours\HasF3;
use Sukarix\Behaviours\LogWriter;

class Session extends Tailored
{
    use HasF3;
    use LogWriter;

    /**
     * @var SQLSession
     */
    protected $internalSession;

    /**
     * Session constructor.
     *
     * @param string $table
     * @param bool   $force
     * @param null   $key
     */
    public function __construct(?SQL $db = null, $table = 'sessions', $force = false, $key = null)
    {
        Processor::instance()->initialize($this);
        if ('CACHE' === $table) {
            $this->internalSession = new F3Session(
                function(F3Session $session, $id) {
                    // Suspect session
                    if (($ip = $session->ip()) !== $this->f3->get('IP')) {
                        $this->logger->warning('user changed IP:' . $ip);
                    } else {
                        $this->logger->warning('user changed browser/device:' . $this->f3->get('AGENT'));
                    }

                    // The default behaviour destroys the suspicious session.
                    return true;
                },
                $key
            );
        } else {
            $this->internalSession = new SQLSession(
                $db,
                $table,
                $force,
                function($session) {
                    // Suspect session
                    if (($ip = $session->ip()) !== $this->f3->get('IP')) {
                        $this->logger->warning('user changed IP:' . $ip);
                    } else {
                        $this->logger->warning('user changed browser/device:' . $this->f3->get('AGENT'));
                    }

                    // The default behaviour destroys the suspicious session.
                    return true;
                },
                $key
            );
        }
    }

    public function cleanupOldSessions(): void
    {
        $this->logger->notice('Cleaning up old sessions');
        $this->cleanup(\ini_get('session.gc_maxlifetime'));
    }

    public function exists($key): bool
    {
        return $this->internalSession->exists($key);
    }

    public function set($key, $value): void
    {
        $this->f3->set('SESSION.' . $key, $value);
        $this->f3->sync('SESSION');
    }

    /**
     * @param mixed $key
     *
     * @return mixed
     */
    public function get($key)
    {
        return $this->f3->get('SESSION.' . $key);
    }

    /**
     *    Garbage collector.
     *
     * @param $max int
     *
     * @return true
     */
    public function cleanup($max): bool
    {
        return $this->internalSession->cleanup($max);
    }

    public function isLoggedIn(): bool
    {
        return true === $this->get('user.loggedIn');
    }

    /**
     * @param $user User
     */
    public function authorizeUser($user): void
    {
        $this->set('user.id', $user->id);
        $this->set('user.role', $user->role);
        $this->set('user.username', $user->username);
        $this->set('user.email', $user->email);
        $this->set('user.loggedIn', true);
        $this->logger->debug("User with id {$user->id} is now logged in");
    }

    /**
     * Clean all information in the session to mark the user as logged out.
     */
    public function revokeUser(): void
    {
        // Backup settings
        $theme        = $this->get('theme');
        $locale       = $this->get('locale');
        $organisation = $this->get('organisation');

        $this->logger->debug('Logging out user with id ' . $this->get('user.id'));
        $this->f3->clear('SESSION');

        // Revert back settings
        $this->set('theme', $theme);
        $this->set('locale', $locale);
        $this->set('organisation', $organisation);
    }

    public function getRole(): string
    {
        return $this->get('user.role') ?: '';
    }

    /**
     * Checks if the user has the specified role.
     *
     * @param mixed $role a string or an array of strings representing the role(s) to check against the user's role
     *
     * @return bool returns true if the user has the specified role, otherwise false
     *
     * @throws \InvalidArgumentException if the provided role is neither a string nor an array
     */
    public function isRole($role)
    {
        if (\is_string($role)) {
            return $role === $this->getRole();
        }
        if (\is_array($role)) {
            return \in_array($this->getRole(), $role, true);
        }

        // Log and handle incorrect type
        $this->logger->emergency('Cannot test user role on invalid object type', ['type' => \gettype($role)]);

        // Optionally, you could throw an exception instead of just logging
        throw new \InvalidArgumentException('Role must be a string or an array');
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->get('user.type');
    }

    /**
     *  Generates a CSRF Token and stores it in the Session.
     *
     * @return string
     */
    public function generateToken()
    {
        $token = $this->internalSession->csrf();
        $this->set('csrf_token', $token);
        $this->set('csrf_used', false);

        return $token;
    }

    /**
     * @return null|string
     */
    public function sid()
    {
        return $this->internalSession->sid();
    }

    /**
     *  Compares the given token with the value in the Session.
     *
     * @return bool
     */
    public function validateToken()
    {
        $errors = [];
        if (!$this->get('csrf_token') || $this->get('csrf_used')) {
            $tokenIsValid = $errors['csrf_token'] = 'CSRF token used or not set';
        } else {
            $this->set('csrf_used', true);
            $tokenIsValid = $this->f3->get($this->f3->get('VERB') . '.csrf_token') === $this->get('csrf_token');
            if (!$tokenIsValid) {
                $this->logger->critical(
                    'Invalid request token provided ' .
                    $this->f3->get($this->f3->get('VERB') . '.csrf_token') .
                    ' where it should be ' . $this->get('csrf_token')
                );
                $errors['csrf_token'] = 'Invalid CSRF token';
            }
        }

        // Validate fields
        $this->set('form_errors', $errors);

        return $tokenIsValid;
    }
}
