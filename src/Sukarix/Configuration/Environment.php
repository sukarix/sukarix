<?php

declare(strict_types=1);

namespace Sukarix\Configuration;

class Environment
{
    public const CONFIG_KEY  = 'application.environment';
    public const TEST        = 'test';
    public const DEVELOPMENT = 'development';
    public const PRODUCTION  = 'production';

    public static function isProduction(): bool
    {
        return self::PRODUCTION === \Base::instance()->get(self::CONFIG_KEY);
    }

    public static function isNotProduction(): bool
    {
        return !self::isProduction();
    }

    public static function isTest(): bool
    {
        return self::TEST === \Base::instance()->get(self::CONFIG_KEY);
    }

    public static function getHostName(): string
    {
        return \Base::instance()->get('server.host') ?: \Base::instance()->get('HOST');
    }
}
