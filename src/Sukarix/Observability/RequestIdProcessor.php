<?php

declare(strict_types=1);

namespace Sukarix\Observability;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Stamps every record with the request id minted in Boot.
 */
final class RequestIdProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $record->extra['request_id'] = (string) (\Base::instance()->get('application.request_id') ?: '');

        return $record;
    }
}
