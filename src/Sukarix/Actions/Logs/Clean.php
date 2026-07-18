<?php

declare(strict_types=1);

namespace Sukarix\Actions\Logs;

use Carbon\Carbon;
use Sukarix\Actions\Action;

/**
 * Class Clean.
 */
class Clean extends Action
{
    /**
     * @param \Base $f3
     * @param array $params
     */
    public function execute($f3, $params): void
    {
        $path = mb_rtrim($f3->get('LOGS'), \DIRECTORY_SEPARATOR) . \DIRECTORY_SEPARATOR;
        $date = Carbon::yesterday()->toDateString();

        // Rotate
        foreach (['error', 'exception'] as $type) {
            $src = "{$path}{$type}.log";
            $dst = "{$path}{$type}-{$date}.log";

            if (is_file($src)) {
                rename($src, $dst);
            }
        }

        // Cleanup
        $keepDays = (int) ($f3->get('log.keep') ?: 7);
        $maxAge   = 60 * 60 * 24 * $keepDays;

        foreach (glob($path . '*.log') as $file) {
            if (is_file($file) && (time() - filemtime($file)) >= $maxAge) {
                $this->logger->info('Deleting old log file', ['log_file' => $file]);
                unlink($file);
            }
        }
    }
}
