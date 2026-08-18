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

    /**
     * Header carrying the CSRF token, for requests that cannot send it as a parameter.
     * Fat-Free normalises header names, so this is the casing it exposes in the hive.
     */
    public const CSRF_HEADER = 'X-Csrf-Token';

    /**
     * Request parameter and form field name carrying the CSRF token.
     */
    public const CSRF_FIELD = 'csrf_token';

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
    public function __construct(?SQL $db = null, $table = null, $force = false, $key = null)
    {
        Processor::instance()->initialize($this);
        $this->csrfEnabled = $this->f3->get('SECURITY.csrf.enabled');
        $this->csrfExpiry  = $this->f3->get('SECURITY.csrf.expiry');

        if (null === $table) {
            $table = $this->f3->get('session.table') ?? 'sessions';
        }

        if ('CACHE' !== $table && null === $db) {
            $db = \Registry::exists('db') ? \Registry::get('db') : null;
        }

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
     *  Generates a CSRF Token and stores it in the Session, or reuses the current one
     *  while it is still valid.
     *
     *  Reusing the token keeps every form of a page submittable. Minting a new token on
     *  each call would leave only the last rendered form valid.
     */
    public function generateToken(): string
    {
        if ($this->isTokenLive()) {
            return (string) $this->get(self::CSRF_FIELD);
        }

        $token = $this->internalSession->csrf();
        $this->set(self::CSRF_FIELD, $token);
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
     *  Compares the token sent with the request against the one held in the Session.
     *
     *  The token is read from the X-Csrf-Token header first, then from the request
     *  parameters. Fat-Free only maps GET, POST and COOKIE into the hive, so PUT, DELETE
     *  and PATCH requests, along with any request sending a JSON body, must use the header.
     */
    public function validateToken(): bool
    {
        if (!$this->csrfEnabled) {
            return true;
        }

        // Fat-Free normalises header names, so the key is always X-Csrf-Token
        $sent    = (string) ($this->f3->get('HEADERS.' . self::CSRF_HEADER) ?: $this->f3->get('REQUEST.' . self::CSRF_FIELD));
        $isValid = $this->isTokenLive() && hash_equals((string) $this->get(self::CSRF_FIELD), $sent);

        if (!$isValid) {
            $this->logger->critical('Invalid CSRF token', [
                'alias' => $this->f3->get('ALIAS'),
                'verb'  => $this->f3->get('VERB'),
                'ip'    => $this->f3->get('IP'),
            ]);
        }

        $this->set('csrf_valid', $isValid);
        $this->set('form_errors', $isValid ? [] : [self::CSRF_FIELD => 'Invalid CSRF token']);

        return $isValid;
    }

    /**
     *  Tells whether the Session holds a token that has not expired yet.
     */
    protected function isTokenLive(): bool
    {
        return (bool) $this->get(self::CSRF_FIELD) && time() <= (int) $this->get('csrf_expiry');
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

        if (!$this->get(self::CSRF_FIELD)) {
            $this->generateToken();
        }
    }
}
