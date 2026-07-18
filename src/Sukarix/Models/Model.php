<?php

declare(strict_types=1);

namespace Sukarix\Models;

use DB\Cortex;
use Sukarix\Behaviours\HasCache;
use Sukarix\Behaviours\HasEvents;
use Sukarix\Behaviours\HasF3;
use Sukarix\Behaviours\HasSession;
use Sukarix\Behaviours\LogWriter;
use Sukarix\Core\Processor;
use Sukarix\Utils\Time;

/**
 * Base Model Class.
 *
 * @property \DateTime $created_on
 * @property \DateTime $updated_on
 */
abstract class Model extends Cortex
{
    use HasCache;
    use HasEvents;
    use HasF3;
    use HasSession;
    use LogWriter;

    /**
     * Page size for list.
     *
     * @var int
     */
    protected $pageSize;

    /**
     * DTO storage for models instantiated without a database connection.
     *
     * @var array<string, mixed>
     */
    protected $dto = [];

    /**
     * Base constructor. Initialises the model.
     *
     * @param null $db
     * @param null $table
     * @param null $fluid
     * @param int  $ttl
     */
    public function __construct($db = null, $table = null, $fluid = null, $ttl = 0)
    {
        if ($db) {
            $this->db = $db;
        } elseif (\Registry::exists('db')) {
            $this->db = \Registry::get('db');
        }

        if (!\is_object($this->db)) {
            // Allow DTO-like usage when no database is configured (e.g. unit tests).
            return;
        }

        parent::__construct($this->db, $table, $fluid, $ttl);

        Processor::instance()->initialize($this);

        $this->pageSize = \Base::instance()->get('pagination.limit');

        $this->beforeinsert(
            static function(self $self): void {
                $self->setCreatedOnDate();
            }
        );

        $this->afterinsert(
            static function(self $self): void {
                if ($self->primary) {
                    $self[$self->primary] = $self->mapper->get('_id');
                }
            }
        );

        $this->beforeupdate(
            static function(self $self): void {
                $self->setUpdatedOnDate();
            }
        );
    }

    /**
     * Magic setter that writes to a DTO buffer when no database is configured.
     *
     * @param mixed $key
     * @param mixed $value
     */
    public function __set($key, $value)
    {
        if (!\is_object($this->db) || null === $this->mapper) {
            $this->dto[$key] = $value;

            return;
        }

        parent::__set($key, $value);
    }

    /**
     * Magic isset that checks the DTO buffer when no database is configured.
     *
     * @param mixed $key
     */
    public function __isset($key)
    {
        if (!\is_object($this->db) || null === $this->mapper) {
            return \array_key_exists($key, $this->dto);
        }

        return parent::__isset($key);
    }

    /**
     * @param mixed $filter
     *
     * @return array
     */
    public function prepareFilter($filter)
    {
        return array_map(static fn ($value) => '' === $value ? '%' : '%' . $value . '%', $filter);
    }

    /**
     * Set page size value for pagination.
     *
     * @param int $pageSize
     */
    public function setPageSize($pageSize): void
    {
        $this->pageSize = $pageSize;
    }

    /**
     * Returns the last inserted id.
     */
    public function lastInsertId(): int
    {
        try {
            $result = $this->db->exec('SELECT MAX(id) as seq FROM ' . $this->table);
            $id     = $result[0]['seq'] ?? 0;
            $this->logger->debug('Retrieved last insert ID', ['table' => $this->table, 'id' => $id]);

            return (int) $id;
        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve last insert ID', ['table' => $this->table, 'error' => $e->getMessage()]);

            return 0;
        }
    }

    /**
     * Load a record by its primary key id.
     *
     * @param int $id
     *
     * @return self
     */
    public function loadById($id)
    {
        $this->load(['id = ?', $id]);

        return $this;
    }

    /**
     * Returns object converted to an array.
     */
    public function toArray(int $depth = 0): array
    {
        return $this->cast(null, $depth);
    }

    /**
     * Load model data from an array.
     */
    public function fromArray(array $data): self
    {
        $reflection = new \ReflectionClass($this);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED);

        $phpDocProperties = $this->getPhpDocProperties($reflection);

        foreach ($properties as $property) {
            $name = $property->getName();
            $type = $this->getPropertyType($property);

            if (isset($data[$name])) {
                $value         = $this->castValue($data[$name], $type);
                $this->{$name} = $value;
            }
        }

        foreach ($phpDocProperties as $name => $type) {
            if (isset($data[$name])) {
                $value         = $this->castValue($data[$name], $type);
                $this->{$name} = $value;
            }
        }

        return $this;
    }

    public function hasChanges()
    {
        $fields         = array_keys($this->mapper->cast());
        $excludedFields = ['id', '_id', 'updated_on'];

        $fields = array_diff($fields, $excludedFields);

        foreach ($fields as $field) {
            if ($this->mapper->changed($field)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Magic getter that reads from the DTO buffer when no database is configured.
     *
     * @param mixed $key
     */
    public function &__get($key)
    {
        if (!\is_object($this->db) || null === $this->mapper) {
            if (\array_key_exists($key, $this->dto)) {
                return $this->dto[$key];
            }

            $null = null;

            return $null;
        }

        return parent::__get($key);
    }

    protected function setCreatedOnDate(): void
    {
        // is_null($this->created_on) check is required for recreating old record from server data
        if (false !== array_search('created_on', $this->fields(), true) && null === $this->created_on) {
            $this->created_on = Time::db();
        }
        if (method_exists($this, 'onCreateCleanUp')
            && \is_callable([$this, 'onCreateCleanUp'])
        ) {
            \call_user_func(
                [$this, 'onCreateCleanUp']
            );
        }
    }

    protected function setUpdatedOnDate(): void
    {
        if (false !== array_search('updated_on', $this->fields(), true)) {
            $this->updated_on = Time::db();
        }
        if (method_exists($this, 'onUpdateCleanUp')
            && \is_callable([$this, 'onCreateCleanUp'])
        ) {
            \call_user_func(
                [$this, 'onCreateCleanUp']
            );
        }
    }

    /**
     * Execute SQL query with error handling.
     */
    protected function execQuery(string $query, array $params = [], string $op = 'query'): array
    {
        try {
            $result = $this->db->exec($query, $params);
            $this->logger->debug("DB {$op}", ['params' => $this->sanitizeParams($params), 'count' => \count($result)]);

            return $result ?: [];
        } catch (\Exception $e) {
            $this->logger->error("DB {$op} failed", ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Execute scalar query returning single value.
     *
     * @param mixed $default
     *
     * @return mixed
     */
    protected function execScalar(string $query, array $params = [], string $op = 'scalar', $default = null)
    {
        try {
            $result = $this->db->exec($query, $params);
            $value  = $result[0][array_key_first($result[0])] ?? $default;
            $this->logger->debug("DB {$op}", ['value' => $value]);

            return $value;
        } catch (\Exception $e) {
            $this->logger->error("DB {$op} failed", ['error' => $e->getMessage()]);

            return $default;
        }
    }

    /**
     * Execute write query (INSERT/UPDATE/DELETE).
     */
    protected function execWrite(string $query, array $params = [], string $op = 'write'): bool
    {
        try {
            $this->db->exec($query, $params);
            $this->logger->info("DB {$op} completed");

            return true;
        } catch (\Exception $e) {
            $this->logger->error("DB {$op} failed", ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Check if record exists.
     */
    protected function recordExists(string $table, string $cond, array $params = []): bool
    {
        return \count($this->execQuery("SELECT 1 FROM {$table} WHERE {$cond}", $params, 'exists')) > 0;
    }

    /**
     * Count records.
     */
    protected function countRecords(string $table, string $cond = '1=1', array $params = []): int
    {
        return (int) $this->execScalar("SELECT COUNT(*) as count FROM {$table} WHERE {$cond}", $params, 'count');
    }

    /**
     * Converts a PHP array to a PostgreSQL array format.
     *
     * Handles multidimensional arrays, escaping each element with `pg_escape_string`.
     * - Strings are enclosed in single quotes, numeric values are not.
     * - Special values like PHP `NULL` and booleans are converted to PostgreSQL `NULL`, `TRUE`, and `FALSE`.
     * - Supports scalar and multidimensional arrays.
     *
     * Examples:
     * - String array: `['a', 'b', 'c']` => `{'a','b','c'}`
     * - Numeric array: `[1, 2, 3]` => `{1,2,3}`
     * - Boolean array: `[true, false]` => `{TRUE,FALSE}`
     * - Multidimensional array: `[['a', 'b'], ['c', 'd']]` => `{{'a','b'},{'c','d'}}`
     * - Empty array: `[]` => `{}`
     * - Null value: `null` => `NULL`
     *
     * Use the result directly in queries; do not quote or escape it further.
     * Do not use as a parameter for prepared statements.
     * Specify array type in queries to avoid errors with empty or null arrays.
     *
     * Example usage:
     * ```php
     * $query = 'INSERT INTO foo (field1, field_array) VALUES ($1, ' . toPostgreSqlArray($phpArray) . '::varchar[])';
     * $params = ['scalar_parameter'];
     * ```
     *
     * Note: The function ensures syntax correctness but does not perform type or logical checks.
     *
     * Inspired by: https://stackoverflow.com/a/24311189
     *
     * @param null|array $set Input PHP array
     *
     * @return string PostgreSQL array syntax
     */
    protected function toPostgreSqlArray($set): string
    {
        if (null === $set || !\is_array($set)) {
            return 'NULL';
        }

        $set    = (array) $set; // Ensure $set is an array
        $result = [];

        foreach ($set as $t) {
            if (\is_array($t)) {
                $result[] = $this->toPostgreSqlArray($t); // Recursion for nested arrays
            } elseif (null === $t) {
                $result[] = 'NULL';
            } elseif (\is_bool($t)) {
                $result[] = $t ? 'TRUE' : 'FALSE';
            } else {
                // Escape and quote non-numeric values
                $t        = pg_escape_string($t);
                $result[] = is_numeric($t) ? $t : "'" . $t . "'";
            }
        }

        return \sprintf('{%s}', implode(',', $result));
    }

    /**
     * Get PHPDoc properties and their types.
     */
    protected function getPhpDocProperties(\ReflectionClass $reflection): array
    {
        $docComment = $reflection->getDocComment();
        $properties = [];

        if ($docComment) {
            preg_match_all('/@property\s+([^\s]+)\s+\$([^\s]+)/', $docComment, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $properties[$match[2]] = $match[1];
            }
        }

        return $properties;
    }

    /**
     * Get the type of a property from its PHPDoc.
     */
    protected function getPropertyType(\ReflectionProperty $property): ?string
    {
        $docComment = $property->getDocComment();
        if ($docComment) {
            if (preg_match('/@property\s+([^\s]+)\s+\$' . $property->getName() . '/', $docComment, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Cast a value to the specified type.
     *
     * @return mixed
     */
    protected static function castValue(string $value, ?string $type)
    {
        switch ($type) {
            case 'int':
                return (int) $value;

            case 'float':
                return (float) $value;

            case 'bool':
                return 'y' === mb_strtolower($value) || 'true' === mb_strtolower($value) || '1' === $value;

            case '\DateTime':
                return Time::db($value);

            default:
                return $value;
        }
    }

    /**
     * Sanitize params for logging by redacting sensitive values.
     */
    private function sanitizeParams(array $params): array
    {
        $sensitive = ['password', 'secret', 'token', 'key'];
        foreach ($params as $k => $v) {
            if (\is_string($k)) {
                $keyLower = mb_strtolower($k);
                foreach ($sensitive as $word) {
                    if (str_contains($keyLower, $word)) {
                        $params[$k] = '***REDACTED***';

                        break;
                    }
                }
            }
        }

        return $params;
    }
}
