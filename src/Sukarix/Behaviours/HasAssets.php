<?php

declare(strict_types=1);

namespace Sukarix\Behaviours;

use Sukarix\Core\Injector;
use Sukarix\Helpers\Assets;

trait HasAssets
{
    /**
     * Assets instance.
     *
     * @var Assets
     */
    protected $assets;

    public function initHasAssets(): void
    {
        $this->assets = Injector::instance()->get('assets');
    }
}
