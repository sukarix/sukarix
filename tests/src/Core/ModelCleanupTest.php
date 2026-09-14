<?php

declare(strict_types=1);

namespace Core;

use Sukarix\Models\Model;
use Test\Scenario;

/**
 * @internal
 *
 * @coversNothing
 */
final class ModelCleanupTest extends Scenario
{
    protected $group = 'Core Model cleanup hooks';

    public function testUpdateCallsOnUpdateCleanUp($f3)
    {
        $model = new class extends Model {
            public bool $updated = false;

            public function fields(): array
            {
                return ['updated_on'];
            }

            public function onUpdateCleanUp(): void
            {
                $this->updated = true;
            }

            public function onCreateCleanUp(): void
            {
                $this->updated = false;
            }

            public function touchUpdated(): void
            {
                $this->setUpdatedOnDate();
            }
        };

        $model->touchUpdated();

        $test = $this->newTest();
        $test->expect(true === $model->updated, 'setUpdatedOnDate calls onUpdateCleanUp rather than onCreateCleanUp');

        return $test->results();
    }
}
