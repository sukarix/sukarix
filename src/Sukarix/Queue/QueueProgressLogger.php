<?php

declare(strict_types=1);

namespace Sukarix\Queue;

use Sukarix\Behaviours\HasF3;
use Sukarix\Behaviours\LogWriter;
use Sukarix\Core\Tailored;

/**
 * Structured logging companion for queue operations.
 *
 * Every method logs with a distinctive prefix and a rich context array
 * so the output can be parsed by log aggregation tools (ELK, Loki,
 * CloudWatch, …) without grepping free-form text.
 */
class QueueProgressLogger extends Tailored
{
    use HasF3;
    use LogWriter;

    private const QUEUE_PREFIX      = '[QUEUE]';
    private const PROGRESS_PREFIX   = '[PROGRESS]';
    private const FILL_PREFIX       = '[FILL]';
    private const PROCESS_PREFIX    = '[PROCESS]';
    private const SUCCESS_PREFIX    = '[SUCCESS]';
    private const ERROR_PREFIX      = '[ERROR]';
    private const SKIP_PREFIX       = '[SKIP]';
    private const START_PREFIX      = '[START]';
    private const BATCH_PREFIX      = '[BATCH]';
    private const THROUGHPUT_PREFIX = '[THROUGHPUT]';

    public function __construct()
    {
        \Sukarix\Core\Processor::instance()->initialize($this);
    }

    /**
     * Log queue fill progress with a visual progress bar.
     */
    public function logQueueFill(
        string $queueName,
        int $currentSize,
        int $maxSize,
        int $processedCount = 0,
        int $skippedCount = 0,
        string $context = ''
    ): void {
        $percentage = $maxSize > 0 ? round(($currentSize / $maxSize) * 100, 1) : 0.0;
        $fillBar    = $this->createProgressBar($percentage, 20);

        $message = "QUEUE_FILL: {$queueName} [{$currentSize}/{$maxSize}] {$fillBar}";
        if ($skippedCount > 0) {
            $message .= " ({$skippedCount} skipped)";
        }

        $this->logger->info(self::QUEUE_PREFIX . ' ' . self::FILL_PREFIX . " {$message}", [
            'queue'        => $queueName,
            'current_size' => $currentSize,
            'max_size'     => $maxSize,
            'percentage'   => $percentage . '%',
            'progress_bar' => $fillBar,
            'processed'    => $processedCount,
            'skipped'      => $skippedCount,
            'context'      => $context,
        ]);
    }

    /**
     * Log queue processing progress with a visual progress bar.
     */
    public function logQueueProcess(
        string $queueName,
        int $processedCount,
        int $failedCount,
        int $remainingInQueue,
        int $maxItems,
        string $context = ''
    ): void {
        $percentage  = $maxItems > 0 ? round(($processedCount / $maxItems) * 100, 1) : 0.0;
        $progressBar = $this->createProgressBar($percentage, 20);

        $message = "QUEUE_PROCESS: {$queueName} [{$processedCount}/{$maxItems}] {$progressBar}";
        if ($failedCount > 0) {
            $message .= " ({$failedCount} failed)";
        }

        $this->logger->info(self::QUEUE_PREFIX . ' ' . self::PROCESS_PREFIX . " {$message}", [
            'queue'        => $queueName,
            'processed'    => $processedCount,
            'failed'       => $failedCount,
            'remaining'    => $remainingInQueue,
            'max_items'    => $maxItems,
            'percentage'   => $percentage . '%',
            'progress_bar' => $progressBar,
            'context'      => $context,
        ]);
    }

    /**
     * Log a queue status snapshot.
     */
    public function logQueueStatus(string $queueName, int $size, string $operation = 'STATUS'): void
    {
        $this->logger->info(self::QUEUE_PREFIX . ' ' . self::PROGRESS_PREFIX . " QUEUE_{$operation}: {$queueName}", [
            'queue'     => $queueName,
            'size'      => $size,
            'operation' => $operation,
        ]);
    }

    /**
     * Log that an item was added to a queue.
     */
    public function logItemAdded(string $queueName, string $itemType, string $itemPreview, int $queueSize = 0): void
    {
        $this->logger->debug(self::QUEUE_PREFIX . ' ' . self::SUCCESS_PREFIX . " QUEUE_ADD: {$queueName}", [
            'queue'        => $queueName,
            'item_type'    => $itemType,
            'item_preview' => $itemPreview,
            'queue_size'   => $queueSize,
        ]);
    }

    /**
     * Log that an item was removed from a queue.
     */
    public function logItemRemoved(string $queueName, string $itemType, string $itemPreview, int $sizeBefore = 0, int $sizeAfter = 0): void
    {
        $this->logger->debug(self::QUEUE_PREFIX . ' ' . self::PROCESS_PREFIX . " QUEUE_REMOVE: {$queueName}", [
            'queue'        => $queueName,
            'item_type'    => $itemType,
            'item_preview' => $itemPreview,
            'size_before'  => $sizeBefore,
            'size_after'   => $sizeAfter,
        ]);
    }

    /**
     * Log that a queue operation has completed.
     *
     * @param array<string,mixed> $additionalMetrics
     */
    public function logQueueComplete(
        string $queueName,
        string $operation,
        int $processedCount,
        int $errorCount = 0,
        array $additionalMetrics = []
    ): void {
        $icon           = $errorCount > 0 ? self::ERROR_PREFIX : self::SUCCESS_PREFIX;
        $totalProcessed = $processedCount + $errorCount;

        $this->logger->info(self::QUEUE_PREFIX . ' ' . $icon . " QUEUE_COMPLETE: {$queueName} - {$operation}", [
            'queue'              => $queueName,
            'operation'          => $operation,
            'processed'          => $processedCount,
            'errors'             => $errorCount,
            'total'              => $totalProcessed,
            'success_rate'       => $totalProcessed > 0 ? round(($processedCount / $totalProcessed) * 100, 1) . '%' : '0%',
            'additional_metrics' => $additionalMetrics,
        ]);
    }

    /**
     * Log that a queue operation has started.
     *
     * @param array<string,mixed> $initialMetrics
     */
    public function logQueueStart(string $queueName, string $operation, array $initialMetrics = []): void
    {
        $this->logger->info(self::QUEUE_PREFIX . ' ' . self::START_PREFIX . " QUEUE_START: {$queueName} - {$operation}", [
            'queue'           => $queueName,
            'operation'       => $operation,
            'initial_metrics' => $initialMetrics,
        ]);
    }

    /**
     * Log a queue warning.
     *
     * @param array<string,mixed> $context
     */
    public function logQueueWarning(string $queueName, string $message, array $context = []): void
    {
        $this->logger->warning(self::QUEUE_PREFIX . ' ' . self::SKIP_PREFIX . " QUEUE_WARNING: {$queueName}", [
            'queue'   => $queueName,
            'message' => $message,
            'context' => $context,
        ]);
    }

    /**
     * Log a queue error.
     *
     * @param array<string,mixed> $context
     */
    public function logQueueError(string $queueName, string $message, array $context = []): void
    {
        $this->logger->error(self::QUEUE_PREFIX . ' ' . self::ERROR_PREFIX . " QUEUE_ERROR: {$queueName}", [
            'queue'   => $queueName,
            'message' => $message,
            'context' => $context,
        ]);
    }

    /**
     * Log batch processing progress.
     */
    public function logBatchProgress(
        string $queueName,
        int $batchSize,
        int $totalBatches,
        int $currentBatch,
        int $processedInBatch,
        string $operation = 'BATCH'
    ): void {
        $batchProgress = $totalBatches > 0 ? round(($currentBatch / $totalBatches) * 100, 1) : 0.0;
        $batchBar      = $this->createProgressBar($batchProgress, 10);

        $this->logger->info(self::QUEUE_PREFIX . ' ' . self::BATCH_PREFIX . " QUEUE_{$operation}: {$queueName}", [
            'queue'              => $queueName,
            'current_batch'      => $currentBatch,
            'total_batches'      => $totalBatches,
            'batch_size'         => $batchSize,
            'processed_in_batch' => $processedInBatch,
            'batch_progress'     => $batchProgress . '%',
            'batch_bar'          => $batchBar,
        ]);
    }

    /**
     * Log queue throughput metrics.
     */
    public function logQueueThroughput(string $queueName, float $itemsPerSecond, int $totalItems, string $operation = 'THROUGHPUT'): void
    {
        $this->logger->info(self::QUEUE_PREFIX . ' ' . self::THROUGHPUT_PREFIX . " QUEUE_{$operation}: {$queueName}", [
            'queue'            => $queueName,
            'items_per_second' => round($itemsPerSecond, 2),
            'total_items'      => $totalItems,
            'operation'        => $operation,
        ]);
    }

    /**
     * Create a visual progress bar using block characters.
     */
    private function createProgressBar(float $percentage, int $width = 20): string
    {
        $filled = (int) (($percentage / 100) * $width);
        $empty  = $width - $filled;

        $filledBar = str_repeat('█', $filled);
        $emptyBar  = str_repeat('░', $empty);

        return "[{$filledBar}{$emptyBar}] {$percentage}%";
    }
}
