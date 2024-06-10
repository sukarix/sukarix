<?php

declare(strict_types=1);

namespace Skuarix\Actions\Jobs;

use GO\Scheduler;
use Sukarix\Actions\Action;

class TaskScheduler extends Action
{
    /**
     * @var string
     */
    protected $documentRoot;

    /**
     * @var Scheduler
     */
    protected $scheduler;

    public function beforeroute(): void
    {
        parent::beforeroute();
        $this->documentRoot = $this->f3->get('SERVER.SCRIPT_NAME');
        $this->scheduler    = new Scheduler();
    }

    /**
     * Save the user locale.
     *
     * @param \Base $f3
     * @param array $params
     *
     * @throws
     */
    public function execute($f3, $params): void
    {
        // Clean logs older than 14 days (default.ini)
        $this->scheduler->php($this->documentRoot, null, ['/cli/logs/clean' => ''], 'logs-clean')->onlyOne()->daily(1);

        // Clean old sessions every 8 hours at 10 minutes after the hour
        $this->scheduler->php($this->documentRoot, null, ['/cli/sessions/clean' => ''], 'sessions-clean')->onlyOne()->at('20 */8 * * *');

        // Run all the jobs
        $this->scheduler->run();

        $failedJobs = $this->scheduler->getFailedJobs();
        if (!empty($failedJobs)) {
            $this->logger->warning('Failed to execute jobs.', ['jobs' => $failedJobs]);
        }
    }
}
