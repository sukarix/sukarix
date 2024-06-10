<?php

declare(strict_types=1);

namespace Sukarix\Helpers;

/**
 * Template extensions Helper Class.
 */
class HTML extends Helper
{
    public function __construct()
    {
        // Template extensions
        \Template::instance()->extend('csrf', '\Sukarix\Helpers\HTML::renderCsrf');
        \Template::instance()->extend('css', '\Sukarix\Helpers\Assets::renderCss');
        \Template::instance()->extend('js', '\Sukarix\Helpers\Assets::renderJs');
    }

    /**
     * Renders the CSRF hidden input for the form.
     *
     * @param mixed $node
     *
     * @return string HTML-Output of the rendering process
     */
    public static function renderCsrf($node)
    {
        return '<input type="hidden" name="csrf_token" value="<?php echo \Registry::get(\'session\')->generateToken(); ?>" />';
    }
}
