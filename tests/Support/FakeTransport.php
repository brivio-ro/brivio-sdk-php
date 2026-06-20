<?php

declare(strict_types=1);

namespace Brivio\Tests\Support;

/**
 * Records the last request and returns a canned response. Used to test the SDK
 * and the Legacy shims without real HTTP.
 */
final class FakeTransport
{
    public ?string $method = null;
    public ?string $url = null;
    /** @var array<string,string> */
    public array $headers = [];
    public ?string $body = null;

    /** @var array{status:int, body:string} */
    private array $response;

    /**
     * @param array<string,mixed> $responseData
     */
    public function __construct(array $responseData = ['data' => ['ok' => true], 'error' => null], int $status = 200)
    {
        $this->response = ['status' => $status, 'body' => json_encode($responseData, JSON_THROW_ON_ERROR)];
    }

    /**
     * @param array<string,string> $headers
     * @return array{status:int, body:string}
     */
    public function __invoke(string $method, string $url, array $headers, ?string $body): array
    {
        $this->method = $method;
        $this->url = $url;
        $this->headers = $headers;
        $this->body = $body;
        return $this->response;
    }

    /** @return array<string,mixed> */
    public function decodedBody(): array
    {
        if ($this->body === null) {
            return [];
        }
        /** @var array<string,mixed> $decoded */
        $decoded = json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
        return $decoded;
    }
}
