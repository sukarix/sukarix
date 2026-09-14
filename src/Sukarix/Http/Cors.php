<?php

declare(strict_types=1);

namespace Sukarix\Http;

/**
 * CORS handling for JSON APIs: allow-list origins, answer preflights, and
 * tag every response with Vary: Origin.
 */
final class Cors
{
    /**
     * @param \Base $f3
     * @param array<string> $allowedOrigins exact origins allowed to call the API
     * @param array<string> $allowedMethods HTTP methods the API answers
     * @param array<string> $allowedHeaders request headers callers may send
     *
     * @return bool true when the request was a preflight and is fully handled
     */
    public static function handle(\Base $f3, array $allowedOrigins, array $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], array $allowedHeaders = ['Content-Type', 'Authorization']): bool
    {
        $origin = (string) $f3->get('HEADERS.Origin');

        header('Vary: Origin', false);
        if ('' === $origin || !\in_array($origin, $allowedOrigins, true)) {
            return false;
        }

        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Methods: ' . implode(', ', $allowedMethods));
        header('Access-Control-Allow-Headers: ' . implode(', ', $allowedHeaders));

        if ('OPTIONS' === $f3->get('VERB')) {
            http_response_code(204);

            return true;
        }

        return false;
    }
}
