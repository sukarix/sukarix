<?php

declare(strict_types=1);

namespace Sukarix\Models;

use DB\Cortex;

/**
 * Class User.
 *
 * @property int       $id
 * @property string    $email
 * @property string    $role
 * @property string    $username
 * @property string    $first_name
 * @property string    $last_name
 * @property string    $password
 * @property string    $status
 * @property \DateTime $created_on
 * @property \DateTime $updated_on
 * @property \DateTime $last_login
 */
class User extends Model
{
    protected $table = 'users';

    public function __construct($db = null, $table = null, $fluid = null, $ttl = 0)
    {
        parent::__construct($db, $table, $fluid, $ttl);
        $this->onset('password', static fn ($self, $value) => password_hash($value, PASSWORD_BCRYPT));
    }

    /**
     * Get user record by email value.
     *
     * @param string $email
     *
     * @return Cortex
     */
    public function getByEmail($email)
    {
        $this->load(['lower(email) = ?', mb_strtolower($email)]);

        return $this;
    }

    /**
     * Check if email already in use.
     *
     * @param string $email
     *
     * @return bool
     */
    public function emailExists($email)
    {
        return \count($this->db->exec('SELECT 1 FROM users WHERE email= ?', $email)) > 0;
    }

    /**
     * Check if username already in use.
     *
     * @param string $username
     *
     * @return bool
     */
    public function usernameExists($username)
    {
        return \count($this->db->exec('SELECT 1 FROM users WHERE username= ?', $username)) > 0;
    }

    public function verifyPassword($password): bool
    {
        return password_verify(trim($password), $this->password);
    }

    /**
     * Check if email or username already in use.
     *
     * @param null|mixed $id
     */
    public function getUsersByUsernameOrEmail(string $username, string $email, $id = null): array
    {
        $data = [];

        $users = $this->find(['(username = lower(?) and id != ?) or (email = lower(?) and id != ?)', $username, $id, $email, $id]);
        if ($users) {
            $data = $users->castAll(['username', 'email']);
        }

        return $data;
    }
}
