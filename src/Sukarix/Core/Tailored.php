<?php

namespace Sukarix\Core;

abstract class Tailored extends \Prefab
{
    /**
     *	Return class instance.
     */
    public static function instance(): static
    {
        $instance = parent::instance();

        // Handle initialisation of the defined traits
        Processor::instance()->initialize($instance);

        return $instance;
    }
}
