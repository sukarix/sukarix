<?php

declare(strict_types=1);

namespace Core;

use DB\SQL;
use Sukarix\Models\Model;
use Test\Scenario;

/**
 * @internal
 *
 * @coversNothing
 */
final class ModelPersistenceTest extends Scenario
{
    protected $group = 'Core Model persistence';

    public function testInsertLeavesTheRecordLoaded($f3)
    {
        $model = $this->newModel();

        $model->name = 'first';
        $model->save();

        $test = $this->newTest();
        $test->expect($model->valid(), 'the record is still loaded after an insert');
        $test->expect($model->id > 0, 'the record carries the identifier the database assigned');
        $test->expect('first' === $model->name, 'the record keeps the values it was saved with');

        return $test->results();
    }

    public function testReloadReadsBackARecordLeftBlank($f3)
    {
        $model = $this->newModel();

        $model->name = 'written';
        $model->save();
        $id = $model->id;

        // The state PostgreSQL identity columns leave behind: a row in the table and
        // nothing in memory.
        $model->reset();

        $test = $this->newTest();
        $test->expect(!$model->valid(), 'the record starts blank');

        $model->pretendTheInsertLeftOnlyTheId($id);
        $model->reloadAfterInsert();

        $test->expect($model->valid(), 'the reload finds the row the insert wrote');
        $test->expect('written' === $model->name, 'the reload brings the stored values back');

        return $test->results();
    }

    public function testReloadLeavesALoadedRecordAlone($f3)
    {
        $model = $this->newModel();

        $model->name = 'kept';
        $model->save();

        $model->name = 'edited but not saved';
        $model->reloadAfterInsert();

        $test = $this->newTest();
        $test->expect('edited but not saved' === $model->name, 'a loaded record is not read back over');

        return $test->results();
    }

    public function testExcludeIdDropsTheClauseWithoutAnIdentifier($f3)
    {
        $model = $this->newModel();

        $test = $this->newTest();
        $test->expect(
            ['lower(name) = ?', 'taken'] === $model->excludeId(['lower(name) = ?', 'taken']),
            'no identifier leaves the filter untouched'
        );
        $test->expect(
            ['(lower(name) = ?) and id != ?', 'taken', 7] === $model->excludeId(['lower(name) = ?', 'taken'], 7),
            'an identifier is excluded without breaking the original condition'
        );

        return $test->results();
    }

    public function testUniquenessCheckStillMatchesWhileCreating($f3)
    {
        $model = $this->newModel();
        $model->name = 'engineering';
        $model->save();

        $other = $this->newModel();

        $test = $this->newTest();
        $test->expect(
            $other->load($other->excludeId(['lower(name) = ?', 'engineering'])),
            'the name is reported as taken while creating a record'
        );

        return $test->results();
    }

    public function testFindReturnsACollection($f3)
    {
        $model = $this->newModel();
        $model->name = 'findable';
        $model->save();

        $found = $this->newModel()->find(['name = ?', 'findable']);

        $test = $this->newTest();
        $test->expect($found instanceof \DB\CortexCollection, 'find answers with a collection, not an array');
        $test->expect(1 === \count($found->castAll()), 'the collection carries the row that was stored');

        return $test->results();
    }

    /**
     * A model over a scratch database, created fresh for each scenario.
     */
    private function newModel(): Model
    {
        static $db = null;

        if (null === $db) {
            $db = new SQL('sqlite::memory:');
            $db->exec('CREATE TABLE things (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
            \Registry::set('db', $db);
        }

        return new class($db) extends Model {
            protected $table = 'things';

            public function __construct($db)
            {
                $this->db = $db;
                parent::__construct($db, 'things');
            }

            /**
             * The state a PostgreSQL identity column leaves: the row is written and
             * the mapper knows its id, but nothing is loaded.
             */
            public function pretendTheInsertLeftOnlyTheId(int $id): void
            {
                $this->mapper->set('_id', $id);
            }
        };
    }
}
