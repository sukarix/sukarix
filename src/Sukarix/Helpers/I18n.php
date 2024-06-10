<?php

declare(strict_types=1);

namespace Sukarix\Helpers;

/**
 * Localisation Helper Class.
 */
class I18n extends Helper
{
    /**
     * Get a i18n label.
     *
     * @param mixed $key
     *
     * @return string
     */
    public function lbl($key)
    {
        return $this->f3->get('i18n.label.' . $key);
    }

    /**
     * Get a i18n message.
     *
     * @param mixed $key
     *
     * @return string
     */
    public function msg($key)
    {
        return $this->f3->get('i18n.message.' . $key);
    }

    /**
     * Get a i18n error.
     *
     * @param mixed $key
     *
     * @return string
     */
    public function err($key)
    {
        return $this->f3->get('i18n.error.' . $key);
    }

    /**
     * Get a i18n list.
     *
     * @param mixed $key
     *
     * @return array
     */
    public function lst($key)
    {
        return $this->f3->get('i18n.list.' . $key);
    }
}
