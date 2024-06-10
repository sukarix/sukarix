<?php

declare(strict_types=1);

namespace Sukarix\Behaviours;

use Sukarix\Core\Injector;
use Sukarix\Helpers\I18n;

trait HasI18n
{
    /**
     * f3 instance.
     *
     * @var I18n f3
     */
    protected $i18n;

    public function initHasI18n(): void
    {
        $this->i18n = Injector::instance()->get('i18n');
    }
}
