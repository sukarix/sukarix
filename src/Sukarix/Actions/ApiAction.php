<?php

declare(strict_types=1);

namespace Sukarix\Actions;

// Base for stateless JSON API endpoints: CSRF-exempt, JSON envelopes throughout.
abstract class ApiAction extends Action
{
    protected bool $csrfExempt = true;

    public function onAccessAuthorizeDeny($route, $subject): void
    {
        $this->logger->warning('Access denied to route ' . $route . ' for subject ' . ($subject ?: 'unknown'));
        $this->error('Access denied', 403);
    }

    // CORS preflight when origins are configured, a plain 204 otherwise.
    public function options(): void
    {
        $origins = (array) ($this->f3->get('api.cors.origins') ?: []);
        if ([] === $origins || !\Sukarix\Http\Cors::handle($this->f3, $origins)) {
            http_response_code(204);
        }
    }

    // ONERROR handler for API routes; register it from the bootstrap for the API prefix.
    public static function onError(\Base $f3): void
    {
        $status = (int) $f3->get('ERROR.code');
        header('Content-Type: application/json; charset=utf-8', true, $status);
        echo json_encode([
            'success' => false,
            'message' => $f3->get('ERROR.text'),
            'status'  => $status,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Constant-time check of the Authorization header against a static
     * bearer token, for machine callers authenticating with one long-lived
     * key. An empty $expected always fails - hash_equals('', '') is true,
     * so a caller sending a bare "Bearer " would otherwise authenticate
     * against an unset key.
     */
    protected function bearerTokenMatches(string $expected): bool
    {
        if ('' === $expected) {
            return false;
        }

        $provided = (string) ($this->f3->get('HEADERS.Authorization') ?? '');

        return str_starts_with($provided, 'Bearer ') && hash_equals($expected, substr($provided, 7));
    }
}
