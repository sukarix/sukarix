<?php

declare(strict_types=1);

namespace Sukarix\Behaviours;

use Sukarix\Observability\LoggerFactory;

trait LogWriter
{
    /**
     * Logger instance.
     *
     * @var \Monolog\Logger
     */
    protected $logger;

    public function initLogWriter(): void
    {
        $this->logger = LoggerFactory::channel(static::class);
    }

    /**
     * Backward-compatible alias for initLogWriter().
     */
    public function initLogger(): void
    {
        $this->initLogWriter();
    }
}
