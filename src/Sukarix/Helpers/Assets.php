<?php

declare(strict_types=1);

namespace Sukarix\Helpers;

use MatthiasMullie\Minify\CSS;
use MatthiasMullie\Minify\JS;
use Respect\Validation\Validator;
use Sukarix\Validation\DataValidator;

/**
 * Class Assets Helper.
 */
class Assets extends Helper
{
    /**
     * @var array
     */
    private $assets;

    private $validator;

    public function __construct()
    {
        $this->validator = new DataValidator();
        $this->assets    = ['head' => [], 'footer' => []];
    }

    public function currentJsLocale()
    {
        return "Locale.setLocale('{$this->session->get('locale')}');\n";
    }

    public function setUserRole()
    {
        return "Common.setUserRole('{$this->session->getRole()}');\n";
    }

    /**
     * @param mixed $node
     *
     * @return string
     */
    public static function renderCss($node)
    {
        $params = [];

        if (isset($node['@attrib'])) {
            $params = $node['@attrib'];
            unset($node['@attrib']);
        }

        return self::instance()->renderCssTag($params['src'], $params['id'] ?? null);
    }

    /**
     * @return string
     */
    public function renderCssTag(string $filePath, ?string $id = null)
    {
        $filePath = '/css/' . $filePath;
        if (false === mb_stripos($filePath, 'http')) {
            $this->validator->verify($this->f3->get('ROOT') . $filePath, Validator::exists()->setName('css_exist'));
        }
        $idTag = $id ? 'id="' . $id . '"' : '';
        if (true === $this->f3->get('MINIFY_CSS') && false === mb_stripos($filePath, 'http') && !str_contains($filePath, '.min.')) {
            $cssTag = '<link href="/minified/' . $this->minifyCSS($filePath, mb_stripos($filePath, '.min.')) . '" rel="stylesheet" type="text/css" ' . $idTag . '/>' . "\n";
        } else {
            $cssTag = '<link href="' . $filePath . '" rel="stylesheet" type="text/css" ' . $idTag . '/>' . "\n";
        }

        return $cssTag;
    }

    public function initJsClasses()
    {
        $init = '';

        $classes = $this->f3->get('init.js');

        foreach ($classes as $value) {
            $init .= "{$value}.init();\n";
        }

        return $init;
    }

    public function initJs($js): void
    {
        $this->f3->push('init.js', $js);
    }

    /**
     * @param mixed $node
     *
     * @return string
     */
    public static function renderJs($node)
    {
        $params = [];

        if (isset($node['@attrib'])) {
            $params = $node['@attrib'];
            unset($node['@attrib']);
        }

        return self::instance()->renderJsTag($params['src']);
    }

    /**
     * @param $filePath string
     *
     * @return string
     */
    public function renderJsTag($filePath)
    {
        $filePath = '/js/' . $filePath;
        if (true === $this->f3->get('MINIFY_JS') && !str_contains($filePath, '.min.')) {
            $jsTag = '<script src="/minified/' . $this->minifyJavaScript($filePath, mb_stripos($filePath, '.min.')) . '" type="text/javascript"></script>' . "\n";
        } else {
            $this->validator->verify($this->f3->get('ROOT') . $filePath, Validator::exists()->setName('js_exist'));
            $jsTag = '<script src="' . $filePath . '" type="text/javascript"></script>' . "\n";
        }

        return $jsTag;
    }

    /**
     * Minifies a JavaScript files if not minified yet and returns its new path.
     *
     * @param $file string JavaScript file path
     * @param $copy bool Just copy the file to the 'minified' folder instead of minifying it
     *
     * @return string new minified JavaScript path
     */
    public function minifyJavaScript($file, $copy = false)
    {
        $hash     = $this->f3->hash(filemtime($this->f3['ROOT'] . $file) . $file);
        $fileName = $hash . '.' . $this->f3->hash($file) . '.js';
        if (!file_exists($filePath = $this->getMinifyPath() . $fileName)) {
            if (!$copy) {
                $css = new JS($this->f3['ROOT'] . $file);
                $css->minify($filePath);
                $this->logger->debug('Minified JS file "' . $file . '" to "' . $filePath . '"');
            } else {
                copy($this->f3->f3['ROOT'] . $file, $filePath);
                $this->logger->debug('Copied JS file "' . $file . '" to "' . $filePath . '"');
            }
        }

        return $fileName;
    }

    public function addJs($path): void
    {
        $this->assets['footer'][] = $path;
    }

    public function addCss($path): void
    {
        $this->assets['head'][] = $path;
    }

    /**
     * get all defined groups.
     *
     * @return array
     */
    public function getGroups()
    {
        return array_keys($this->assets);
    }

    /**
     * @param mixed $group
     *
     * @return string
     */
    public function renderGroup($group)
    {
        $tags = '';
        if ('head' === $group) {
            foreach ($this->assets['head'] as $tag) {
                $tags .= $this->renderCssTag($tag) . "\n";
            }
        } elseif ('footer' === $group) {
            foreach ($this->assets['footer'] as $tag) {
                $tags .= $this->renderJsTag($tag) . "\n";
            }
        }

        return $tags;
    }

    /**
     * Minifies a CSS file if not minified yet and returns its new minified path.
     *
     * @param string $file CSS file path
     * @param        $copy bool Just copy the file to the minifcation folder instead of minifying it
     *
     * @return string new minified CSS path
     */
    private function minifyCSS($file, $copy = false)
    {
        $hash     = $this->f3->hash(filemtime($this->f3['ROOT'] . $file) . $file);
        $fileName = $hash . '.' . $this->f3->hash($file) . '.css';
        if (!file_exists($filePath = $this->getMinifyPath() . $fileName)) {
            if (!$copy) {
                $css = new CSS($this->f3['ROOT'] . $file);
                $css->minify($filePath);
                $this->logger->debug('Minified CSS file "' . $file . '" to "' . $filePath . '"');
            } else {
                $this->logger->debug('Copied CSS file "' . $file . '" to "' . $filePath . '"');
                copy($this->f3['ROOT'] . $file, $filePath);
            }
        }

        return $fileName;
    }

    /**
     * Returns the minification path.
     *
     * @return string
     */
    private function getMinifyPath()
    {
        return $this->f3['ROOT'] . '/minified/';
    }
}
