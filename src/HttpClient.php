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
     * With `$raw = true` the body is returned undecoded as `['data' => bytes]`
     * (binary resources such as an invoice PDF); a non-2xx still throws with
     * the envelope's error when the server sent one.
     *
     * @param array<string, scalar|null> $query
     * @return array{data: mixed, meta?: array<string,mixed>}
     */
    public function get(string $path, array $query = [], bool $raw = false): array
    {
        $qs = '';
        $filtered = array_filter($query, static fn ($v) => $v !== null);
        if ($filtered !== []) {
            $qs = '?' . http_build_query($filtered);
        }
        if ($raw) {
            return $this->requestRaw('GET', $path . $qs);
        }
        return $this->request('GET', $path . $qs, null);
    }

    /**
     * @return array{data: string, meta: array<string,mixed>}
     */
    private function requestRaw(string $method, string $path): array
    {
        $headers = ['Authorization' => 'Bearer ' . $this->apiKey, 'Accept' => '*/*'];
        /** @var array{status:int, body:string} $res */
        $res = ($this->transport)($method, $this->baseUrl . $path, $headers, null);
        if ($res['status'] >= 400) {
            /** @var array{error?: array{code?:string,message?:string}}|null $json */
            $json = json_decode($res['body'], true);
            $err = is_array($json) ? ($json['error'] ?? []) : [];
            throw new BrivioException(
                $err['message'] ?? ('HTTP ' . $res['status']),
                $err['code'] ?? 'INTERNAL_ERROR',
                $res['status'],
            );
        }
        return ['data' => $res['body'], 'meta' => []];
    }

    /**
     * @param array<string, mixed> $body
     * @return array{data: mixed, meta?: array<string,mixed>}
     */
    public function post(string $path, array $body, ?string $idempotencyKey = null): array
    {
        return $this->request('POST', $path, self::encode($body), $idempotencyKey);
    }

    /**
     * @param array<string, mixed> $body
     * @return array{data: mixed, meta?: array<string,mixed>}
     */
    public function patch(string $path, array $body, ?string $idempotencyKey = null): array
    {
        return $this->request('PATCH', $path, self::encode($body), $idempotencyKey);
    }

    /**
     * @param array<string, mixed> $body
     * @return array{data: mixed, meta?: array<string,mixed>}
     */
    public function put(string $path, array $body, ?string $idempotencyKey = null): array
    {
        return $this->request('PUT', $path, self::encode($body), $idempotencyKey);
    }

    /**
     * Encode a request body, keeping whole floats as floats.
     *
     * Without JSON_PRESERVE_ZERO_FRACTION, PHP serialises (float) 2 as `2`,
     * so a quantity or VAT rate the caller deliberately typed as a float
     * arrives at the API as an integer. The legacy shims cast every numeric
     * line field to float precisely to normalise this, and the cast was being
     * undone on the way out.
     *
     * @param array<string, mixed> $body
     */
    private static function encode(array $body): string
    {
        return json_encode($body, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
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
