<?php

declare(strict_types=1);

namespace Sukarix\Actions\Logs;

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
        $files = glob($f3->get('LOGS') . '*.log');
        $now   = time();

        foreach ($files as $file) {
            if (is_file($file) && $now - filemtime($file) >= 60 * 60 * 24 * $f3->get('log.keep')) { // 14 days by default
                $this->logger->info('Deleting old log file', ['log_file' => $file]);
                unlink($file);
            }
        }
    }
}
