<?php

declare(strict_types=1);

namespace Sukarix\Actions\Health;

use Sukarix\Actions\ApiAction;

/**
 * Liveness and readiness probes. CSRF-exempt and session-free when listed
 * in SECURITY.stateless.prefixes.
 */
class Probe extends ApiAction
{
    public function liveness(): void
    {
        $this->json([
            'status'     => 'ok',
            'request_id' => $this->f3->get('application.request_id'),
        ]);
    }

    public function readiness(): void
    {
        $checks = [
            'app' => true,
        ];
        $cache = (string) $this->f3->get('CACHE');
        if (str_starts_with($cache, 'redis=')) {
            $checks['redis'] = $this->redisPing($cache);
        }
        $ready = !\in_array(false, $checks, true);
        $this->json([
            'status'     => $ready ? 'ok' : 'degraded',
            'checks'     => $checks,
            'request_id' => $this->f3->get('application.request_id'),
        ], $ready ? 200 : 503);
    }

    private function redisPing(string $cache): bool
    {
        if (!class_exists(\Redis::class)) {
            return false;
        }
        try {
            $host  = preg_match('/redis=([\w.-]+)/', $cache, $m) ? $m[1] : '127.0.0.1';
            $redis = new \Redis();
            $redis->connect($host, 6379, 0.5);

            // phpredis returns true for an argument-less PING; only the
            // older/raw form answers the "+PONG" status string. Comparing
            // against 'PONG' alone made every readiness check fail.
            $pong = $redis->ping();

            return true === $pong || \in_array($pong, ['PONG', '+PONG'], true);
        } catch (\Throwable) {
            return false;
        }
    }
}
