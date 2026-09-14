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

    /**
     * Match an Accept-Language header against a set of supported locales and
     * return the best one, falling back when nothing matches.
     *
     * @param array<int|string, string> $languages short language code => full
     *                                             locale (e.g. ['en' => 'en-GB', 'fr' => 'fr-FR'])
     */
    public static function negotiateLocale(array $languages, string $acceptLanguageHeader, string $fallback): string
    {
        $supported = [];
        foreach ($languages as $code => $locale) {
            $supported[$locale] = $locale;
            if (\is_string($code)) {
                $supported[$code] = $locale;
            }
        }
        if ([] === $supported) {
            return $fallback;
        }

        if ('' === $acceptLanguageHeader) {
            return $supported[$fallback] ?? $fallback;
        }

        // Parse "fr-FR,fr;q=0.9,en;q=0.8" into ordered candidates.
        $candidates = [];
        foreach (explode(',', $acceptLanguageHeader) as $part) {
            $segments = explode(';', mb_trim($part), 2);
            $tag      = mb_trim($segments[0]);
            $quality  = 1.0;
            if (isset($segments[1]) && preg_match('/q\s*=\s*([0-9.]+)/', $segments[1], $m)) {
                $quality = (float) $m[1];
            }
            $candidates[$tag] = $quality;
        }
        arsort($candidates);

        foreach ($candidates as $tag => $quality) {
            // A q=0 candidate is an explicit "not acceptable" (RFC 9110 12.5.1), not a low preference.
            if (0.0 === $quality) {
                continue;
            }
            $normalized = str_replace('_', '-', $tag);
            // Exact match (e.g. fr-FR).
            if (isset($supported[$normalized])) {
                return $supported[$normalized];
            }
            // Language-only match (e.g. fr -> fr-FR).
            $lang = mb_strtolower(explode('-', $normalized)[0]);
            foreach ($languages as $code => $locale) {
                if (mb_strtolower((string) $code) === $lang) {
                    return $locale;
                }
            }
        }

        return $supported[$fallback] ?? $fallback;
    }
}
