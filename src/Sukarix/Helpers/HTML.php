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
        \Template::instance()->extend('form_error', '\Sukarix\Helpers\HTML::renderFormError');
        \Template::instance()->extend('tooltip', '\Sukarix\Helpers\HTML::renderToolTip');
        \Template::instance()->extend('css', '\Sukarix\Helpers\Assets::renderCss');
        \Template::instance()->extend('js', '\Sukarix\Helpers\Assets::renderJs');
        \Template::instance()->extend('pagebrowser', '\Sukarix\Helpers\Pagination::renderTag');

        // Template filters
        \Template::instance()->filter('logLevelColor', '\Sukarix\Helpers\HTML::logLevelColor');
        \Template::instance()->filter('logContent', '\Sukarix\Helpers\HTML::logContent');
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

    /**
     * Renders a form error message for the given field.
     *
     * @param mixed $node
     *
     * @return string
     */
    public static function renderFormError($node)
    {
        $name = $node['@attrib']['name'];
        unset($node);

        return '<?php if (isset($form_errors[\'' . $name . '\'])) echo "<div style=\"color:red\" class=\"help-block has-error\">  $form_errors[' . $name . ']</div>"; ?>';
    }

    /**
     * Renders a tooltip element.
     *
     * @param mixed $node
     *
     * @return string
     */
    public static function renderToolTip($node)
    {
        $text  = $node['@attrib']['text'] ?? '';
        $title = $node['@attrib']['title'] ?? '';
        unset($node);

        return '<?php echo "<span data-bs-toggle=\"tooltip\" data-bs-placement=\"top\" title=\"' . htmlspecialchars($text, ENT_QUOTES) . '\">' . htmlspecialchars($title, ENT_QUOTES) . '</span>"; ?>';
    }

    /**
     * Returns a color for a given log level.
     *
     * @param string $level
     *
     * @return string
     */
    public static function logLevelColor($level)
    {
        return match ($level) {
            'DEBUG'     => '#b0c4de',
            'INFO'      => '#5bc0de',
            'NOTICE'    => '#5cb85c',
            'WARNING'   => '#f0ad4e',
            'ERROR'     => '#d9534f',
            'CRITICAL'  => '#c9302c',
            'ALERT'     => '#d43f3a',
            'EMERGENCY' => '#a94442',
            default     => '#777',
        };
    }

    /**
     * Formats log entries with colored log levels.
     *
     * @param array $logEntries
     *
     * @return string
     */
    public static function logContent($logEntries)
    {
        return implode(PHP_EOL, array_map(static function($entry) {
            if (preg_match('/\w+\\\.*\.(\w+):/', $entry, $matches)) {
                $level = $matches[1];
                $color = self::logLevelColor($level);

                return "<span class=\"log-entry log-level-{$level}\" style=\"color: {$color}\">{$entry}</span>";
            }

            return $entry;
        }, $logEntries));
    }
}
