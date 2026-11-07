<?php

declare(strict_types=1);

namespace Brivio\Tests\Support;

/**
 * Records EVERY request (not just the last one) and answers per route, so a
 * multi-call flow (contact lookup → contact create → invoice create) can be
 * asserted step by step.
 */
final class RecordingTransport
{
    /** @var list<array{method:string, url:string, path:string, query:array<string,string>, body:array<string,mixed>|null}> */
    public array $requests = [];

    /**
     * @param list<array<string,mixed>> $existingContacts what `GET /contacts` returns
     */
    public function __construct(private array $existingContacts = [])
    {
    }

    /**
     * @param array<string,string> $headers
     * @return array{status:int, body:string}
     */
    public function __invoke(string $method, string $url, array $headers, ?string $body): array
    {
        $parts = parse_url($url);
        $path = (string) ($parts['path'] ?? '');
        $path = (string) preg_replace('#^/v1#', '', $path);
        $query = [];
        parse_str((string) ($parts['query'] ?? ''), $query);
        /** @var array<string,string> $query */
        /** @var array<string,mixed>|null $decoded */
        $decoded = $body === null ? null : json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        $this->requests[] = ['method' => $method, 'url' => $url, 'path' => $path, 'query' => $query, 'body' => $decoded];

        return match (true) {
            $method === 'GET' && $path === '/contacts' => self::json(['data' => $this->existingContacts, 'error' => null, 'meta' => []]),
            $method === 'POST' && $path === '/contacts' => self::json(['data' => ['id' => 'c-new'] + ($decoded ?? []), 'error' => null], 201),
            $method === 'POST' && $path === '/invoices' => self::json(['data' => ['id' => 'inv-new', 'status' => 'DRAFT'], 'error' => null], 201),
            default => self::json(['data' => null, 'error' => ['code' => 'NOT_FOUND', 'message' => "unexpected {$method} {$path}"]], 404),
        };
    }

    /** @return list<string> e.g. ['GET /contacts', 'POST /contacts', 'POST /invoices'] */
    public function calls(): array
    {
        return array_map(static fn (array $r): string => $r['method'] . ' ' . $r['path'], $this->requests);
    }

    /** @return array<string,mixed> body of the last request to $method $path */
    public function bodyOf(string $method, string $path): array
    {
        foreach (array_reverse($this->requests) as $r) {
            if ($r['method'] === $method && $r['path'] === $path) {
                return $r['body'] ?? [];
            }
        }
        throw new \LogicException("no {$method} {$path} was sent; calls: " . implode(', ', $this->calls()));
    }

    /**
     * @param array<string,mixed> $data
     * @return array{status:int, body:string}
     */
    private static function json(array $data, int $status = 200): array
    {
        return ['status' => $status, 'body' => json_encode($data, JSON_THROW_ON_ERROR)];
    }
}
