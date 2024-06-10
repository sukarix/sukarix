<?php

declare(strict_types=1);

namespace Sukarix\Actions;

/**
 * Base Web Controller Class.
 */
abstract class WebAction extends Action
{
    public const JSON            = 'Content-Type: application/json; charset=utf-8';
    public const APPLICATION_XML = 'Content-Type: application/xml; charset=UTF-8';
    public const CSV             = 'Content-Type: text/csv; charset=UTF-8';
    public const TEXT            = 'Content-Type: text/plain; charset=utf-8';
    public const XML             = 'Content-Type: text/xml; charset=UTF-8';

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
