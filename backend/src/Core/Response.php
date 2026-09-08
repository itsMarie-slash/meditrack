<?php

declare(strict_types=1);

namespace MediTrack\Core;

/**
 * Every API response uses the same envelope:
 *   { "success": bool, "message": string, "data": mixed }
 */
final class Response
{
    /** @param array<string,mixed>|list<mixed> $data */
    public static function success(array $data = [], string $message = 'Operation completed successfully', int $status = 200): never
    {
        self::send(['success' => true, 'message' => $message, 'data' => $data], $status);
    }

    /** @param array<string,string>|null $errors */
    public static function error(string $message, int $status = 400, ?array $errors = null): never
    {
        $payload = ['success' => false, 'message' => $message, 'data' => []];
        if ($errors !== null) {
            $payload['data']['errors'] = $errors;
        }
        self::send($payload, $status);
    }

    /** @param array<string,mixed> $payload */
    private static function send(array $payload, int $status): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
        exit;
    }
}
