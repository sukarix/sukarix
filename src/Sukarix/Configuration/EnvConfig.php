<?php

declare(strict_types=1);

namespace Sukarix\Configuration;

/**
 * Maps environment variables into Fat-Free globals.
 *
 * This is a lightweight, framework-level helper that applications can call
 * with their own key mapping.  It keeps the environment-reading logic in one
 * place and supports both simple scalar values and typed defaults.
 */
class EnvConfig
{
    /**
     * Apply environment variables to the hive using a flat mapping.
     *
     * Each entry maps a hive key to an environment variable name, optionally
     * with a default value and a cast:
     *
     *   'db.host' => 'DB_HOST',
     *   'db.port' => ['env' => 'DB_PORT', 'default' => 5432, 'cast' => 'int'],
     *
     * @param array<string, array<string, mixed>|string> $mappings
     */
    public static function apply(\Base $f3, array $mappings): void
    {
        foreach ($mappings as $hiveKey => $definition) {
            $envKey  = \is_array($definition) ? $definition['env'] : $definition;
            $default = \is_array($definition) ? ($definition['default'] ?? null) : null;
            $cast    = \is_array($definition) ? ($definition['cast'] ?? null) : null;

            if (self::hasEnv($envKey)) {
                $value = self::env($envKey);
                $f3->set($hiveKey, self::castValue($value, $cast));
            } elseif (null !== $default) {
                $f3->set($hiveKey, self::castValue($default, $cast));
            }
        }
    }

    /**
     * Read a scalar environment variable.
     */
    public static function env(string $key): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        return false !== $value && '' !== $value ? $value : null;
    }

    /**
     * Check whether an environment variable is set, including empty values.
     */
    public static function hasEnv(string $key): bool
    {
        return null !== self::env($key) || false !== ($_ENV[$key] ?? $_SERVER[$key] ?? getenv($key));
    }

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    private static function castValue($value, ?string $cast)
    {
        if (null === $cast || null === $value) {
            return $value;
        }

        return match ($cast) {
            'int'    => (int) $value,
            'float'  => (float) $value,
            'bool'   => \in_array(mb_strtolower((string) $value), ['1', 'true', 'yes', 'on'], true),
            'string' => (string) $value,
            default  => $value,
        };
    }
}
