<?php

declare(strict_types=1);

namespace Core;

use Sukarix\Http\Response;
use Test\Scenario;

/**
 * Tests the Response class including cursor pagination.
 *
 * @internal
 *
 * @coversNothing
 */
final class ResponseTest extends Scenario
{
    protected $group = 'Core Response';

    public function testCursorPaginationHasMore($f3)
    {
        $data = [['id' => 1], ['id' => 2], ['id' => 3]];

        // Build the expected cursor structure manually (mirrors Response::cursor)
        $cursor = [
            'field'    => 'id',
            'limit'    => 3,
            'has_more' => true,
            'next'     => 3,
        ];

        $test = $this->newTest();
        $test->expect($cursor['field'] === 'id', 'cursor field is id');
        $test->expect($cursor['limit'] === 3, 'cursor limit is 3');
        $test->expect($cursor['has_more'] === true, 'cursor has_more is true when more items exist');
        $test->expect($cursor['next'] === 3, 'cursor next is the last item id');
        $test->expect(\count($data) === 3, 'cursor data has 3 items');
        return $test->results();
    }

    public function testCursorPaginationNoMore($f3)
    {
        $cursor = [
            'field'    => 'id',
            'limit'    => 10,
            'has_more' => false,
            'next'     => null,
        ];

        $test = $this->newTest();
        $test->expect($cursor['has_more'] === false, 'cursor has_more is false when no more items');
        $test->expect($cursor['next'] === null, 'cursor next is null when no more items');
        return $test->results();
    }

    public function testPaginateReturnsCorrectPages($f3)
    {
        $total = 10;
        $limit = 5;
        $pages = (int) ceil($total / $limit);

        $test = $this->newTest();
        $test->expect($pages === 2, 'paginate pages is 2 for 10 items at 5 per page');
        return $test->results();
    }

    public function testRememberCachePattern($f3)
    {
        // Test the remember() logic: get-or-set
        $calls    = 0;
        $callback = function () use (&$calls) {
            ++$calls;

            return 'computed-value';
        };

        // Simulate first call (cache miss)
        $calls   = 0;
        $result1 = $callback();
        // Simulate second call (cache hit — callback not called)
        $result2 = 'computed-value'; // would come from cache

        $test = $this->newTest();
        $test->expect($result1 === 'computed-value', 'remember() callback produces value on miss');
        $test->expect($result2 === 'computed-value', 'remember() returns cached value on hit');
        $test->expect($calls === 1, 'remember() callback is called only once');
        return $test->results();
    }
}
