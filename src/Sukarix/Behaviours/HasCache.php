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
}
