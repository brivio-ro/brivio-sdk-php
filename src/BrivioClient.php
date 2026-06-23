<?php

declare(strict_types=1);

namespace Brivio;

/**
 * Brivio public API client.
 *
 * ```php
 * $brivio = new \Brivio\BrivioClient('brivio_sk_live_...');
 * $me = $brivio->me();
 * $contacts = $brivio->listContacts(['search' => 'srl']);
 * $contact = $brivio->createContact(['name' => 'ACME SRL', 'vat_number' => 'RO123']);
 * ```
 */
final class BrivioClient
{
    private HttpClient $http;

    public function __construct(string $apiKey, string $baseUrl = 'https://api.brivio.ro/v1', ?callable $transport = null)
    {
        $this->http = new HttpClient($apiKey, $baseUrl, $transport);
    }

    public function http(): HttpClient
    {
        return $this->http;
    }

    /** @return array<string,mixed> */
    public function me(): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/me')['data'];
        return $data;
    }

    // ── Contacts ──────────────────────────────────────────────────────
    /**
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listContacts(array $params = []): array
    {
        $res = $this->http->get('/contacts', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function createContact(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/contacts', $input, $idempotencyKey)['data'];
        return $data;
    }

    // ── Articles ──────────────────────────────────────────────────────
    /**
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listArticles(array $params = []): array
    {
        $res = $this->http->get('/articles', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function createArticle(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/articles', $input, $idempotencyKey)['data'];
        return $data;
    }

    // ── Locations ─────────────────────────────────────────────────────
    /** @return list<array<string,mixed>> */
    public function listLocations(): array
    {
        /** @var list<array<string,mixed>> $data */
        $data = $this->http->get('/locations')['data'] ?? [];
        return $data;
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function createLocation(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/locations', $input, $idempotencyKey)['data'];
        return $data;
    }

    // ── Invoices ──────────────────────────────────────────────────────
    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function createInvoice(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/invoices', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listInvoices(array $params = []): array
    {
        $res = $this->http->get('/invoices', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /** @return array<string,mixed> */
    public function getInvoice(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/invoices/' . rawurlencode($id))['data'];
        return $data;
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function updateInvoice(string $id, array $input): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->patch('/invoices/' . rawurlencode($id), $input)['data'];
        return $data;
    }

    public function deleteInvoice(string $id): void
    {
        $this->http->delete('/invoices/' . rawurlencode($id));
    }

    /**
     * Submit an issued invoice to RO e-Factura (ANAF).
     * @return array<string,mixed>
     */
    public function submitInvoiceToANAF(string $id, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/invoices/' . rawurlencode($id) . '/submit-efactura', [], $idempotencyKey)['data'];
        return $data;
    }

    // ── Payments ──────────────────────────────────────────────────────
    /**
     * Create a PaymentIntent (own Stripe or Brivio Connect).
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function charge(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/payments/charge', $input, $idempotencyKey)['data'];
        return $data;
    }

    // ── Modules ───────────────────────────────────────────────────────
    /** @return list<array<string,mixed>> */
    public function listModules(?string $locationId = null): array
    {
        /** @var list<array<string,mixed>> $data */
        $data = $this->http->get('/modules', $locationId !== null ? ['location_id' => $locationId] : [])['data'] ?? [];
        return $data;
    }
}
