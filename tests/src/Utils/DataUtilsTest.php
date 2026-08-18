<?php

declare(strict_types=1);

namespace Utils;

use Sukarix\Utils\DataUtils;
use Test\Scenario;

/**
 * Exercises the generic array/data helpers in Sukarix\Utils\DataUtils.
 *
 * @internal
 *
 * @coversNothing
 */
final class DataUtilsTest extends Scenario
{
    protected $group = 'Utils DataUtils';

    public function testKeepIntegerInArray($f3)
    {
        $test  = $this->newTest();
        $array = ['1', 'a', '2', '3.5', '10'];

        DataUtils::keepIntegerInArray($array);
        $test->expect($array === ['1', '2', '10'], 'keepIntegerInArray retains only digit strings');

        $nullArray = null;
        DataUtils::keepIntegerInArray($nullArray);
        $test->expect(null === $nullArray, 'keepIntegerInArray leaves null untouched');

        return $test->results();
    }

    public function testUnsetByValue($f3)
    {
        $test  = $this->newTest();
        $array = ['a', 'b', 'c', 'b'];

        DataUtils::unsetByValue($array, 'b');
        $test->expect($array === [0 => 'a', 2 => 'c', 3 => 'b'], 'unsetByValue removes the first matching value');

        return $test->results();
    }

    public function testGetArrayFromField($f3)
    {
        $test  = $this->newTest();
        $array = [['id' => 1, 'name' => 'a'], ['id' => 2, 'name' => 'b']];

        $test->expect(DataUtils::getArrayFromField($array, 'name') === ['a', 'b'], 'getArrayFromField extracts the requested column');
        $test->expect(DataUtils::getArrayFromField($array, 'missing') === [null, null], 'getArrayFromField returns null for missing keys');

        return $test->results();
    }

    public function testToJsonArray($f3)
    {
        $test = $this->newTest();

        $test->expect(DataUtils::toJsonArray([1, 2, 3]) === '1,2,3', 'toJsonArray without wrapping');
        $test->expect(DataUtils::toJsonArray(['a', 'b'], true) === "'a','b'", 'toJsonArray with wrapping');

        return $test->results();
    }
}
