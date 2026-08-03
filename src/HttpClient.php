<?php

declare(strict_types=1);

namespace Brivio;

/**
 * Minimal cURL transport for the Brivio public API. Unwraps the
 * `{ data, error, meta }` envelope; throws BrivioException on error.
 *
 * Injectable transport (the $transport callable) makes it unit-testable
 * without real HTTP.
 *
 * @phpstan-type Transport callable(string, string, array<string,string>, ?string): array{status:int, body:string}
 */
final class HttpClient
{
    private string $apiKey;
    private string $baseUrl;
    /** @var callable */
    private $transport;

    /**
     * @param Transport|null $transport custom transport for testing
     */
    public function __construct(string $apiKey, string $baseUrl = 'https://api.brivio.ro/v1', ?callable $transport = null)
    {
        if ($apiKey === '') {
            throw new \InvalidArgumentException('Brivio SDK: apiKey is required');
        }
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->transport = $transport ?? [$this, 'curlTransport'];
    }

    /**
     * @param array<string, scalar|null> $query
     * @return array{data: mixed, meta?: array<string,mixed>}
     */
    public function get(string $path, array $query = []): array
    {
        $qs = '';
        $filtered = array_filter($query, static fn ($v) => $v !== null);
        if ($filtered !== []) {
            $qs = '?' . http_build_query($filtered);
        }
        return $this->request('GET', $path . $qs, null);
    }

    /**
     * @param array<string, mixed> $body
     * @return array{data: mixed, meta?: array<string,mixed>}
     */
    public function post(string $path, array $body, ?string $idempotencyKey = null): array
    {
        return $this->request('POST', $path, json_encode($body, JSON_THROW_ON_ERROR), $idempotencyKey);
    }

    /**
     * @param array<string, mixed> $body
     * @return array{data: mixed, meta?: array<string,mixed>}
     */
    public function patch(string $path, array $body, ?string $idempotencyKey = null): array
    {
        return $this->request('PATCH', $path, json_encode($body, JSON_THROW_ON_ERROR), $idempotencyKey);
    }

    /**
     * @param array<string, mixed> $body
     * @return array{data: mixed, meta?: array<string,mixed>}
     */
    public function put(string $path, array $body, ?string $idempotencyKey = null): array
    {
        return $this->request('PUT', $path, json_encode($body, JSON_THROW_ON_ERROR), $idempotencyKey);
    }

    /**
     * @return array{data: mixed, meta?: array<string,mixed>}
     */
    public function delete(string $path): array
    {
        return $this->request('DELETE', $path, null);
    }

    /**
     * @return array{data: mixed, meta?: array<string,mixed>}
     */
    private function request(string $method, string $path, ?string $body, ?string $idempotencyKey = null): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
        if ($idempotencyKey !== null) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        /** @var array{status:int, body:string} $res */
        $res = ($this->transport)($method, $this->baseUrl . $path, $headers, $body);

        /** @var array{data?: mixed, error?: array{code?:string,message?:string,details?:array<string,list<string>>|null}|null, meta?: array<string,mixed>} $json */
        $json = json_decode($res['body'], true, 512, JSON_THROW_ON_ERROR) ?? [];

        if (isset($json['error'])) {
            $err = $json['error'];
            throw new BrivioException(
                $err['message'] ?? 'Request failed',
                $err['code'] ?? 'INTERNAL_ERROR',
                $res['status'],
                $err['details'] ?? null,
            );
        }

        return ['data' => $json['data'] ?? null, 'meta' => $json['meta'] ?? []];
    }

    /**
     * @param array<string,string> $headers
     * @return array{status:int, body:string}
     */
    private function curlTransport(string $method, string $url, array $headers, ?string $body): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new BrivioException('Failed to initialise cURL', 'INTERNAL_ERROR');
        }
        $headerLines = [];
        foreach ($headers as $k => $v) {
            $headerLines[] = $k . ': ' . $v;
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_TIMEOUT => 30,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $responseBody = curl_exec($ch);
        if ($responseBody === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new BrivioException('HTTP transport error: ' . $error, 'INTERNAL_ERROR');
        }
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['status' => $status, 'body' => (string) $responseBody];
    }
}
