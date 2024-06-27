<?php

declare(strict_types=1);

namespace Sukarix\Actions;

/**
 * Base Web Controller Class.
 */
abstract class WebAction extends Action
{
    /**
     * f3 instance.
     *
     * @var \Base f3
     */
    protected $f3;

    /**
     * initialize controller.
     */
    public function __construct()
    {
        parent::__construct();

        $this->f3->set('init.js', ['Locale', 'Common']);
    }
}
