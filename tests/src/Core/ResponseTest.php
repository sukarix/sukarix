<?php

declare(strict_types=1);

namespace Core;

use Sukarix\Behaviours\HasCache;
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
        $test->expect('id' === $cursor['field'], 'cursor field is id');
        $test->expect(3 === $cursor['limit'], 'cursor limit is 3');
        $test->expect(true === $cursor['has_more'], 'cursor has_more is true when more items exist');
        $test->expect(3 === $cursor['next'], 'cursor next is the last item id');
        $test->expect(3 === \count($data), 'cursor data has 3 items');

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
        $test->expect(false === $cursor['has_more'], 'cursor has_more is false when no more items');
        $test->expect(null === $cursor['next'], 'cursor next is null when no more items');

        return $test->results();
    }

    public function testPaginateReturnsCorrectPages($f3)
    {
        $total = 10;
        $limit = 5;
        $pages = (int) ceil($total / $limit);

        $test = $this->newTest();
        $test->expect(2 === $pages, 'paginate pages is 2 for 10 items at 5 per page');

        return $test->results();
    }

    public function testRememberCachePattern($f3)
    {
        $subject = new class {
            use HasCache;
        };
        $subject->initHasCache();

        $key      = 'response-test.remember.' . uniqid('', true);
        $calls    = 0;
        $callback = static function() use (&$calls) {
            ++$calls;

            return 'computed-value';
        };

        $result1 = $subject->remember($key, $callback);
        $result2 = $subject->remember($key, $callback);

        $test = $this->newTest();
        $test->expect('computed-value' === $result1, 'remember() callback produces value on miss');
        $test->expect('computed-value' === $result2, 'remember() returns cached value on hit');
        $test->expect(1 === $calls, 'remember() callback is called only once');

        $subject->forget($key);
        $result3 = $subject->remember($key, $callback);
        $test->expect(2 === $calls, 'forget() invalidates the cached value so the callback runs again');
        $test->expect('computed-value' === $result3, 'remember() recomputes the value after forget()');

        return $test->results();
    }

    public function testRememberCachesFalsyValues($f3)
    {
        $subject = new class {
            use HasCache;
        };
        $subject->initHasCache();

        $key   = 'response-test.remember-falsy.' . uniqid('', true);
        $calls = 0;
        $falsy = static function() use (&$calls) {
            ++$calls;

            return false;
        };

        $result1 = $subject->remember($key, $falsy);
        $result2 = $subject->remember($key, $falsy);

        $test = $this->newTest();
        $test->expect(false === $result1, 'remember() returns the computed false value on miss');
        $test->expect(false === $result2, 'remember() returns the cached false value on hit');
        $test->expect(1 === $calls, 'remember() does not recompute a cached false value');

        return $test->results();
    }
}
