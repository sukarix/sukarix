<?php

declare(strict_types=1);

namespace Sukarix\Observability;

use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

/**
 * One shared Monolog root logger. Callers take a named channel so every
 * object does not open its own file handle.
 */
final class LoggerFactory
{
    private static ?Logger $root = null;

    public static function channel(string $name): Logger
    {
        return self::root()->withName($name);
    }

    public static function reset(): void
    {
        self::$root = null;
    }

    private static function root(): Logger
    {
        if (null !== self::$root) {
            return self::$root;
        }

        $f3      = \Base::instance();
        $level   = mb_strtoupper((string) ($f3->get('log.level') ?: 'info'));
        $constant = (new \ReflectionClass(Logger::class))->getConstants()[$level] ?? Level::Info;
        $json    = self::wantsJson($f3);

        $logger = new Logger('sukarix');
        $logger->pushProcessor(new RequestIdProcessor());

        if ($json) {
            $stream = new StreamHandler('php://stdout', $constant);
            $stream->setFormatter(new JsonFormatter());
            $logger->pushHandler($stream);
        } else {
            $logFile = $f3->get('application.logfile') ?: ($f3->get('LOGS') . 'app.log');
            $stream  = new StreamHandler($logFile, $constant);
            $stream->setFormatter(new LineFormatter(
                '[' . (\Base::instance()->ip() ?: 'CLI:PID.' . getmypid()) . '] '
                . '[%datetime%] [' . \Base::instance()->get('VERB') . (true === \Base::instance()->get('AJAX') ? '.AJAX' : '') . '] %channel%.%level_name%: %message% %context% %extra%'
                . ' [' . \Base::instance()->get('AGENT') . ']'
                . "\n",
                'Y-m-d G:i:s.u'
            ));
            $logger->pushHandler($stream);
        }

        if ($f3->get('log.console') && !$json) {
            $console = new StreamHandler('php://stdout', $constant);
            if (class_exists(\Bramus\Monolog\Formatter\ColoredLineFormatter::class)) {
                $console->setFormatter(
                    new \Bramus\Monolog\Formatter\ColoredLineFormatter(
                        new \Bramus\Monolog\Formatter\ColorSchemes\DefaultScheme()
                    )
                );
            }
            $logger->pushHandler($console);
        }

        self::$root = $logger;

        return self::$root;
    }

    private static function wantsJson(\Base $f3): bool
    {
        $hive = $f3->get('log.json');
        if (true === $hive || 'true' === $hive || 1 === $hive || '1' === $hive) {
            return true;
        }

        return filter_var(getenv('LOG_JSON') ?: '', FILTER_VALIDATE_BOOL);
    }
}
