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
        $f3       = \Base::instance();
        $logFile  = $f3->get('application.logfile') ?: ($f3->get('LOGS') . 'app.log');
        $level    = mb_strtoupper($f3->get('log.level') ?: 'info');
        $constant = (new \ReflectionClass(Logger::class))->getConstants()[$level] ?? Logger::INFO;

        $this->logger = new Logger(static::class);
        $stream       = new StreamHandler($logFile, $constant);
        $stream->setFormatter(
            new LineFormatter('[' . (\Base::instance()->ip() ?: 'CLI:PID.' . getmypid()) . '] '
                . '[%datetime%] [' . \Base::instance()->get('VERB') . (true === \Base::instance()->get('AJAX') ? '.AJAX' : '') . '] %channel%.%level_name%: %message% %context% %extra%'
                . ' [' . \Base::instance()->get('AGENT') . ']'
                // Keep line return between double quotes
                . "\n", 'Y-m-d G:i:s.u')
        );
        $this->logger->pushHandler($stream);
    }

    /**
     * Backward-compatible alias for initLogWriter().
     */
    public function initLogger(): void
    {
        $this->initLogWriter();
    }
}
