<?php

declare(strict_types=1);

namespace Sukarix\Actions;

use Carbon\Carbon;

abstract class CyclicAction extends Action
{
    protected $maxExecutionTime; // Max task time in seconds
    protected $relaxPeriod; // Relax period in seconds
    protected $startTime;

    public function beforeroute(): void
    {
        parent::beforeroute();
        $this->startTime = Carbon::now();
        $this->logger->info('Task started', ['start_time' => $this->startTime]);
    }

    public function execute($f3, $params): void
    {
        $this->maxExecutionTime = (int) @$this->argv['time'] ?: 0;
        $this->relaxPeriod      = (int) @$this->argv['relax'] ?: 0;

        $this->startTime = Carbon::now();
        $this->logger->info('Execution started', ['max_execution_time' => $this->maxExecutionTime]);

        while ($this->getCurrentRuntime() < $this->maxExecutionTime - $this->relaxPeriod) {
            $this->executeAction($f3, $params);
            $currentRuntime = $this->getCurrentRuntime();

            if ($currentRuntime >= $this->maxExecutionTime - $this->relaxPeriod) {
                $this->logger->info('Active period ended, entering relax period', ['current_runtime' => $currentRuntime]);

                break;
            }
        }

        $this->logger->info('Relax period started', ['duration' => $this->relaxPeriod]);
        sleep($this->relaxPeriod);
    }

    protected function getCurrentRuntime(): float
    {
        return $this->startTime->diffInSeconds(Carbon::now());
    }

    abstract protected function executeAction($f3, $params): void;
}
