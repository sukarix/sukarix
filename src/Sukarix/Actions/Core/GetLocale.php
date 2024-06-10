<?php

declare(strict_types=1);

namespace Sukarix\Actions\Core;

use Sukarix\Actions\WebAction;

/**
 * Class LocalesController.
 */
class GetLocale extends WebAction
{
    /**
     * Loads a json translation files from cache or generates if it does not exist.
     *
     * @param \Base $f3
     * @param array $params
     */
    public function execute($f3, $params): void
    {
        $cache        = \Cache::instance();
        $localePrefix = 'locale.' . $params['locale'];

        // checking if the file is already cached, the cache locale file is generated from the file last modification time
        $cached = $cache->exists($hash = $localePrefix . '.' . $f3->hash(filemtime($f3['LOCALES'] . $params['locale'] . '.php') . $params['locale']));

        if (false === $cached) {
            // we create a new json file from locales data
            $cache->reset($localePrefix);
            $cache->set($hash, json_encode($f3['i18n']));
        }

        // @todo: move to CDN and make the call lighter
        $this->logger->info('Loading locale: ' . $params['locale'], ['cached' => false !== $cached]);

        $this->renderJson($cache->get($hash));
    }
}
