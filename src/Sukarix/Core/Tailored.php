<?php

namespace Sukarix\Core;

abstract class Tailored extends \Prefab
{
    /**
     *	Return class instance.
     */
    public static function instance(): static
    {
        return parent::instance();
    }
}
