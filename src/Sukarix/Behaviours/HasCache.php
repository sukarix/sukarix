<?php

declare(strict_types=1);

namespace Sukarix\Behaviours;

trait HasCache
{
    /**
     * @var \Cache
     */
    protected $cache;

    public function initHasCache(): void
    {
        $this->cache = \Cache::instance();
    }

    /**
     * Get a cached value or compute and store it.
     *
     * Follows the classic "remember" pattern: if the key exists in cache,
     * return it; otherwise call the callback, store the result, and return it.
     *
     * @param string   $key      cache key
     * @param callable $callback function that produces the value when cache misses
     * @param int      $ttl      time-to-live in seconds (0 = no expiry)
     *
     * @return mixed the cached or freshly computed value
     */
    public function remember(string $key, callable $callback, int $ttl = 0): mixed
    {
        if ($this->cache->exists($key, $cached)) {
            return $cached;
        }

        $value = $callback();
        $this->cache->set($key, $value, $ttl);

        return $value;
    }

    /**
     * Forget a cached key.
     */
    public function forget(string $key): void
    {
        $this->cache->clear($key);
    }
}
