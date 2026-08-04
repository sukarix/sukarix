<?php

declare(strict_types=1);

namespace Sukarix\Configuration;

class ConfigurationFile
{
    public const ACCESS        = 'access';
    public const ACCESS_CLI    = 'access-cli';
    public const CONNECTORS    = 'connectors';
    public const DEFAULT       = 'default';
    public const NOTIFICATIONS = 'notifications';
    public const ROUTES        = 'routes';
    public const ROUTES_CLI    = 'routes-cli';
    public const SMTP          = 'smtp';
    public const UPLOAD        = 'upload';

    public static function getFile(string $name): string
    {
        return 'config/' . $name . '.ini';
    }

    public static function getEnvironmentRouteFile(string $environment): ?string
    {
        return file_exists('config/routes-' . $environment . '.ini') ? 'config/routes-' . $environment . '.ini' : null;
    }

    public static function getEnvironmentFile(string $environment): string
    {
        return 'config/config-' . $environment . '.ini';
    }
}
