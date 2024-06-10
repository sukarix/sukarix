<?php

declare(strict_types=1);

namespace Sukarix\Behaviours;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

trait LogWriter
{
    /**
     * Logger instance.
     *
     * @var Logger
     */
    protected $logger;

    public function initLogWriter(): void
    {
        $this->logger = new Logger(static::class);
        $level        = mb_strtoupper(\Base::instance()->get('log.level'));
        $class        = new \ReflectionClass(Logger::class);
        $stream       = new StreamHandler(\Base::instance()->get('application.logfile'), $class->getConstants()[$level]);
        $stream->setFormatter(
            new LineFormatter('[' . (\Base::instance()->ip() ?: 'CLI:PID.' . getmypid()) . '] '
                . '[%datetime%] [' . \Base::instance()->get('VERB') . (true === \Base::instance()->get('AJAX') ? '.AJAX' : '') . '] %channel%.%level_name%: %message% %context% %extra%' .
                ' [' . \Base::instance()->get('AGENT') . ']' .
                // Keep line return between double quotes
                "\n", 'Y-m-d G:i:s.u')
        );
        $this->logger->pushHandler($stream);
    }
}
