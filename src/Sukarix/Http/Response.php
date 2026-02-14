<?php

declare(strict_types=1);

namespace Sukarix\Http;

/**
 * Simple Response Handler for Sukarix Framework
 * Following F3 philosophy - lightweight and practical
 */
class Response
{
    private \Base $f3;

    public function __construct()
    {
        $this->f3 = \Base::instance();
    }

    /**
     * Send JSON response
     */
    public function json($data, int $status = 200): void
    {
        header('Content-Type: application/json; charset=utf-8', true, $status);
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Send success response
     */
    public function success($data = null, string $message = 'Success'): void
    {
        $this->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
    }

    /**
     * Send error response
     */
    public function error(string $message, int $status = 400, $errors = null): void
    {
        $response = [
            'success' => false,
            'message' => $message,
            'status' => $status
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        $this->json($response, $status);
    }

    /**
     * Send validation error response
     */
    public function validationError(array $errors, string $message = 'Validation failed'): void
    {
        $this->error($message, 422, $errors);
    }

    /**
     * Send not found response
     */
    public function notFound(string $message = 'Resource not found'): void
    {
        $this->error($message, 404);
    }

    /**
     * Send unauthorized response
     */
    public function unauthorized(string $message = 'Unauthorized'): void
    {
        $this->error($message, 401);
    }

    /**
     * Send paginated response
     */
    public function paginate(array $data, int $total, int $page, int $limit): void
    {
        $this->json([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }
}
