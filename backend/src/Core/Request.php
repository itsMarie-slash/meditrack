<?php

declare(strict_types=1);

namespace MediTrack\Core;

final class Request
{
    /** @param array<string,string> $query
     *  @param array<string,mixed> $body
     *  @param array<string,string> $headers
     *  @param array<string,string> $params
     *  @param array{id:int,username:string,role:string,full_name:string}|null $user
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $body,
        public readonly array $headers,
        public readonly string $ip,
        public array $params = [],
        public ?array $user = null,
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = rtrim((string) parse_url($uri, PHP_URL_PATH), '/');
        $path = $path === '' ? '/' : $path;

        parse_str((string) ($_SERVER['QUERY_STRING'] ?? ''), $query);

        $rawBody = file_get_contents('php://input') ?: '';
        $body = [];
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if ($rawBody !== '' && str_contains($contentType, 'application/json')) {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        } elseif (!empty($_POST)) {
            $body = $_POST;
        }

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
                $headers[$name] = (string) $value;
            }
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        return new self($method, $path, $query, $body, $headers, $ip);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }
        return $default;
    }

    public function paginationLimit(int $default = 20, int $max = 100): int
    {
        $limit = (int) ($this->query['per_page'] ?? $default);
        return max(1, min($limit, $max));
    }

    public function paginationOffset(): int
    {
        $page = max(1, (int) ($this->query['page'] ?? 1));
        return ($page - 1) * $this->paginationLimit();
    }

    public function paginationPage(): int
    {
        return max(1, (int) ($this->query['page'] ?? 1));
    }
}
