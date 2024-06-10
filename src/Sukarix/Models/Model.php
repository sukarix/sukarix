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
     * Base constructor. Initialises the model.
     *
     * @param null $db
     * @param null $table
     * @param null $fluid
     * @param int  $ttl
     */
    public function __construct($db = null, $table = null, $fluid = null, $ttl = 0)
    {
        $this->db = !$db ? \Registry::get('db') : $db;

        parent::__construct($this->db, $table, $fluid, $ttl);

        Processor::instance()->initialize($this);

        $this->pageSize = \Base::instance()->get('pagination.limit');

        $this->beforeinsert(
            static function(self $self): void {
                $self->setCreatedOnDate();
            }
        );

        $this->beforeupdate(
            static function(self $self): void {
                $self->setUpdatedOnDate();
            }
        );
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
        $id = $this->db->exec("SELECT MAX(id) as seq FROM `{$this->table}`");

        return $id[0]['seq'];
    }

    /**
     * Returns object converted to an array.
     *
     * @param int $depth
     */
    public function toArray($depth = 0): array
    {
        return $this->cast(null, $depth);
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

    protected function toPostgreSqlArray($set): string
    {
        $set    = (array) $set;
        $result = [];

        foreach ($set as $t) {
            if (\is_array($t)) {
                $result[] = $this->toPostgreSqlArray($t);
            } else {
                $t = str_replace('"', '\\"', $t); // escape double quote
                if (!is_numeric($t)) { // quote only non-numeric values
                    $t = '"' . $t . '"';
                }
                $result[] = $t;
            }
        }

        return '{' . implode(',', $result) . '}'; // format
    }
}
