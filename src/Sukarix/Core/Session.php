<?php

declare(strict_types=1);

namespace Sukarix\Core;

use DB\SQL;
use DB\SQL\Session as SQLSession;
use Session as F3Session;
use Sukarix\Behaviours\HasF3;
use Sukarix\Behaviours\LogWriter;
use Sukarix\Models\User;

class Session extends Tailored
{
    use HasF3;
    use LogWriter;

    protected $internalSession;
    protected $csrfEnabled;
    protected $csrfExpiry;

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
        $this->csrfEnabled = $this->f3->get('SECURITY.csrf.enabled');
        $this->csrfExpiry  = $this->f3->get('SECURITY.csrf.expiry');
        $this->initializeSession($db, $table, $force, $key);
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

    public function authorizeUser(User $user): void
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
    public function isRole($role): bool
    {
        if (\is_string($role)) {
            return $role === $this->getRole();
        }
        if (\is_array($role)) {
            return \in_array($this->getRole(), $role, true);
        }

        $this->logger->emergency('Cannot test user role on invalid object type', ['type' => \gettype($role)]);

        throw new \InvalidArgumentException('Role must be a string or an array');
    }

    public function getType(): string
    {
        return $this->get('user.type') ?: '';
    }

    /**
     *  Generates a CSRF Token and stores it in the Session.
     */
    public function generateToken(): string
    {
        $token = $this->internalSession->csrf();
        $this->set('csrf_token', $token);
        $this->set('csrf_used', false);
        $this->set('csrf_valid', true);
        $this->set('csrf_expiry', time() + $this->csrfExpiry);

        return $token;
    }

    public function sid(): ?string
    {
        return $this->internalSession->sid();
    }

    public function isCsrfValid(): bool
    {
        return !$this->csrfEnabled || $this->get('csrf_valid');
    }

    /**
     *  Compares the given token with the value in the Session.
     */
    public function validateToken(): bool
    {
        if (!$this->csrfEnabled) {
            return true;
        }

        $errors       = [];
        $sessionToken = $this->get('csrf_token');
        $csrfExpiry   = $this->get('csrf_expiry');

        // Log the current csrf_used value for debugging
        $this->logger->debug('CSRF used status at start: ' . var_export($this->get('csrf_used'), true));

        if (!$sessionToken || $this->get('csrf_used') || time() > $csrfExpiry) {
            $this->set('csrf_valid', false);
            $errors['csrf_token'] = 'CSRF token used, not set, or expired';
        } else {
            $this->set('csrf_used', true);
            $this->f3->sync('SESSION'); // Ensure session synchronization
            $this->logger->debug('CSRF used status after setting to true: ' . var_export($this->get('csrf_used'), true));

            $tokenIsValid = $this->f3->get($this->f3->get('VERB') . '.csrf_token') === $sessionToken;
            if (!$tokenIsValid) {
                $this->logger->critical(
                    'Invalid request token provided ' .
                    $this->f3->get($this->f3->get('VERB') . '.csrf_token') .
                    ' where it should be ' . $sessionToken .
                    ' IP: ' . $this->f3->get('SERVER.REMOTE_ADDR') . ' User-Agent: ' . $this->f3->get('SERVER.HTTP_USER_AGENT')
                );
                $errors['csrf_token'] = 'Invalid CSRF token';
                $this->set('csrf_valid', false);
            } else {
                $this->set('csrf_valid', true);
            }
        }

        // Debugging the final csrf_valid state
        $this->logger->debug('CSRF valid status at end: ' . var_export($this->get('csrf_valid'), true));

        $this->set('form_errors', $errors);

        return $this->get('csrf_valid');
    }

    protected function initializeSession(?SQL $db, $table, $force, $key)
    {
        $sessionCallback = function($session) {
            if (($ip = $session->ip()) !== $this->f3->get('IP')) {
                $this->logger->warning('User changed IP: ' . $ip);
            } else {
                $this->logger->warning('User changed browser/device: ' . $this->f3->get('AGENT'));
            }

            return true;
        };

        if ('CACHE' === $table) {
            $this->internalSession = new F3Session($sessionCallback, $key);
        } else {
            $this->internalSession = new SQLSession($db, $table, $force, $sessionCallback, $key);
        }

        if (!$this->get('csrf_token')) {
            $this->generateToken();
        }
    }
}
