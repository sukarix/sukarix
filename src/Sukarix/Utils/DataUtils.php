<?php

declare(strict_types=1);

namespace Sukarix\Utils;

/**
 * Generic array/data helpers shared across Sukarix applications.
 */
class DataUtils
{
    /**
     * Filters the input array to retain only integer string values.
     */
    public static function keepIntegerInArray(?array &$array): void
    {
        if (\is_array($array)) {
            $array = array_values(array_filter($array, 'ctype_digit'));
        }
    }

    /**
     * Removes the first occurrence of a value from an array.
     */
    public static function unsetByValue(array &$array, mixed $value): void
    {
        if (($key = array_search($value, $array, true)) !== false) {
            unset($array[$key]);
        }
    }

    /**
     * Extracts a column from a multidimensional array.
     */
    public static function getArrayFromField(array $array, int|string $key): array
    {
        return array_map(
            static fn ($item) => $item[$key] ?? null,
            $array
        );
    }

    /**
     * Converts an array to a comma-separated string.
     *
     * @param bool $wrapStrings Whether to wrap each value with single quotes
     */
    public static function toJsonArray(array $array, bool $wrapStrings = false): string
    {
        if (!$wrapStrings) {
            return implode(',', $array);
        }

        return "'" . implode("','", $array) . "'";
    }
}
