<?php

declare(strict_types=1);

namespace Sukarix\Behaviours;

trait HasF3
{
    /**
     * f3 instance.
     *
     * @var \Base f3
     */
    protected $f3;

    public function initHasF3(): void
    {
        $this->f3 = \Base::instance();
    }
}
