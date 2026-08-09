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

    // ── Companies (public registry / Prospectare) ─────────────────────
    /**
     * Advanced registry search: filter ~2M Romanian companies by CAEN,
     * county, size, financials and multi-year trends. AND between filter
     * groups, OR within a group. Scope: companies:read.
     *
     * @param array{groups: list<array<string,mixed>>, sort?: string, page?: int, pageSize?: int, stats?: bool} $request
     * @return array{results: list<array<string,mixed>>, pagination: array<string,mixed>, stats?: array<string,mixed>}
     */
    public function searchCompanies(array $request): array
    {
        /** @var array{results: list<array<string,mixed>>, pagination: array<string,mixed>, stats?: array<string,mixed>} $data */
        $data = $this->http->post('/companies/search', $request)['data'];
        return $data;
    }

    /**
     * Peer companies for a seed CUI (same CAEN / county / size band).
     *
     * @return array{results: list<array<string,mixed>>}
     */
    public function similarCompanies(string $cui, int $limit = 10): array
    {
        /** @var array{results: list<array<string,mixed>>} $data */
        $data = $this->http->get('/companies/' . rawurlencode($cui) . '/similar', ['limit' => $limit])['data'];
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

    // ── Projects ──────────────────────────────────────────────────────
    /**
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listProjects(array $params = []): array
    {
        $res = $this->http->get('/projects', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function createProject(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/projects', $input, $idempotencyKey)['data'];
        return $data;
    }

    // ── Expenses ──────────────────────────────────────────────────────
    /**
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listExpenses(array $params = []): array
    {
        $res = $this->http->get('/expenses', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function createExpense(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/expenses', $input, $idempotencyKey)['data'];
        return $data;
    }

    // ── Contracts ─────────────────────────────────────────────────────
    /**
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listContracts(array $params = []): array
    {
        $res = $this->http->get('/contracts', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function createContract(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/contracts', $input, $idempotencyKey)['data'];
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

    // ── Webhooks ──────────────────────────────────────────────────────
    /**
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listWebhooks(array $params = []): array
    {
        $res = $this->http->get('/webhooks', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * Register a webhook endpoint. The returned array contains `secret`
     * exactly once — persist it for signature verification.
     *
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function createWebhook(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/webhooks', $input, $idempotencyKey)['data'];
        return $data;
    }

    /** @return array<string,mixed> */
    public function getWebhook(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/webhooks/' . $id)['data'];
        return $data;
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function updateWebhook(string $id, array $input): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->patch('/webhooks/' . $id, $input)['data'];
        return $data;
    }

    /** Rotate the signing secret; response contains the new `secret`.
     * @return array<string,mixed>
     */
    public function rotateWebhookSecret(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->patch('/webhooks/' . $id, ['rotate_secret' => true])['data'];
        return $data;
    }

    public function deleteWebhook(string $id): void
    {
        $this->http->delete('/webhooks/' . $id);
    }

    /**
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listWebhookDeliveries(string $id, array $params = []): array
    {
        $res = $this->http->get('/webhooks/' . $id . '/deliveries', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    // ── Inventory ─────────────────────────────────────────────────────
    /**
     * Current stock levels for stock-tracked articles.
     *
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listStockLevels(array $params = []): array
    {
        $res = $this->http->get('/inventory/stock', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * Stock movement history.
     *
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listStockMovements(array $params = []): array
    {
        $res = $this->http->get('/inventory/movements', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * List goods receipt notes (NIR).
     *
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listNirDocuments(array $params = []): array
    {
        $res = $this->http->get('/inventory/nir', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /** @return array<string,mixed> */
    public function getNirDocument(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/inventory/nir/' . $id)['data'];
        return $data;
    }

    // ── HR ────────────────────────────────────────────────────────────
    /**
     * Employee directory. PII (CNP, IBAN, address) is never exposed.
     *
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listEmployees(array $params = []): array
    {
        $res = $this->http->get('/hr/employees', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    // ── Banking ───────────────────────────────────────────────────────
    /**
     * Bank transactions synced from connected banks (read-only).
     *
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listBankingTransactions(array $params = []): array
    {
        $res = $this->http->get('/banking/transactions', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * Bank connections for the organization (read-only).
     *
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listBankingConnections(array $params = []): array
    {
        $res = $this->http->get('/banking/connections', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    // ── Fixed assets ──────────────────────────────────────────────────
    /**
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listFixedAssets(array $params = []): array
    {
        $res = $this->http->get('/fixed-assets', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function createFixedAsset(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/fixed-assets', $input, $idempotencyKey)['data'];
        return $data;
    }

    // ── Documents ─────────────────────────────────────────────────────
    /**
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listDocuments(array $params = []): array
    {
        $res = $this->http->get('/documents', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /** @return array<string,mixed> */
    public function getDocument(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/documents/' . rawurlencode($id))['data'];
        return $data;
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function createDocument(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/documents', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function updateDocument(string $id, array $input): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->patch('/documents/' . rawurlencode($id), $input)['data'];
        return $data;
    }

    public function deleteDocument(string $id): void
    {
        $this->http->delete('/documents/' . rawurlencode($id));
    }

    // ── Web & Domains: DNS ────────────────────────────────────────
    /**
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listDnsZones(array $params = []): array
    {
        $res = $this->http->get('/dns/zones', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /** @return array<string,mixed> */
    public function getDnsZone(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/dns/zones/' . rawurlencode($id))['data'];
        return $data;
    }

    /** @return array<string,mixed> */
    public function createDnsZone(string $name, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/dns/zones', ['name' => $name], $idempotencyKey)['data'];
        return $data;
    }

    public function deleteDnsZone(string $id): void
    {
        $this->http->delete('/dns/zones/' . rawurlencode($id));
    }

    /** @return list<array<string,mixed>> */
    public function listDnsRecords(string $zoneId): array
    {
        /** @var list<array<string,mixed>> $data */
        $data = $this->http->get('/dns/zones/' . rawurlencode($zoneId) . '/records')['data'];
        return $data;
    }

    /**
     * @param array<string,mixed> $input {name?, type, content, ttl?, priority?}
     * @return array<string,mixed>
     */
    public function createDnsRecord(string $zoneId, array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/dns/zones/' . rawurlencode($zoneId) . '/records', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function updateDnsRecord(string $zoneId, string $recordId, array $input): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->put(
            '/dns/zones/' . rawurlencode($zoneId) . '/records/' . rawurlencode($recordId),
            $input,
        )['data'];
        return $data;
    }

    public function deleteDnsRecord(string $zoneId, string $recordId): void
    {
        $this->http->delete('/dns/zones/' . rawurlencode($zoneId) . '/records/' . rawurlencode($recordId));
    }

    /** @return array<string,mixed> */
    public function importDnsZone(string $zoneId, string $zoneFile, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post(
            '/dns/zones/' . rawurlencode($zoneId) . '/import',
            ['zone_file' => $zoneFile],
            $idempotencyKey,
        )['data'];
        return $data;
    }

    /** @return array{zone: string, zone_file: string} */
    public function exportDnsZone(string $zoneId): array
    {
        /** @var array{zone: string, zone_file: string} $data */
        $data = $this->http->get('/dns/zones/' . rawurlencode($zoneId) . '/export')['data'];
        return $data;
    }

    /** @return list<array<string,mixed>> */
    public function listDnsTemplates(): array
    {
        /** @var list<array<string,mixed>> $data */
        $data = $this->http->get('/dns/templates')['data'];
        return $data;
    }

    /**
     * @param array<string,string> $params
     * @return array<string,mixed>
     */
    public function applyDnsTemplate(string $zoneId, string $templateId, array $params = [], ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post(
            '/dns/zones/' . rawurlencode($zoneId) . '/template',
            ['template_id' => $templateId, 'params' => (object) $params],
            $idempotencyKey,
        )['data'];
        return $data;
    }

    /** @return list<array<string,mixed>> */
    public function listDnsZoneVersions(string $zoneId): array
    {
        /** @var list<array<string,mixed>> $data */
        $data = $this->http->get('/dns/zones/' . rawurlencode($zoneId) . '/versions')['data'];
        return $data;
    }

    /** @return array<string,mixed> */
    public function rollbackDnsZone(string $zoneId, int $version, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post(
            '/dns/zones/' . rawurlencode($zoneId) . '/rollback',
            ['version' => $version],
            $idempotencyKey,
        )['data'];
        return $data;
    }

    /** @return list<array<string,mixed>> */
    public function listDnsConnections(): array
    {
        /** @var list<array<string,mixed>> $data */
        $data = $this->http->get('/dns/connections')['data'];
        return $data;
    }

    /** @return list<array<string,mixed>> */
    public function listDnsConnectionZones(string $connectionId): array
    {
        /** @var list<array<string,mixed>> $data */
        $data = $this->http->get('/dns/connections/' . rawurlencode($connectionId) . '/zones')['data'];
        return $data;
    }

    // ── Web & Domains: registered domains ──────────────────────────
    /**
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listDomains(array $params = []): array
    {
        $res = $this->http->get('/domains', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /** @return array<string,mixed> */
    public function lockDomain(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/domains/' . rawurlencode($id) . '/lock', [])['data'];
        return $data;
    }

    /** @return array<string,mixed> */
    public function unlockDomain(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/domains/' . rawurlencode($id) . '/unlock', [])['data'];
        return $data;
    }

    /** @return array{domain: string, auth_code: string} */
    public function getDomainAuthCode(string $id): array
    {
        /** @var array{domain: string, auth_code: string} $data */
        $data = $this->http->get('/domains/' . rawurlencode($id) . '/auth-code')['data'];
        return $data;
    }

    /** @return array<string,mixed> */
    public function renewDomain(string $id, int $period = 1, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/domains/' . rawurlencode($id) . '/renew', ['period' => $period], $idempotencyKey)['data'];
        return $data;
    }

    /** @return array<string,mixed> */
    public function getDomainHealth(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/domains/' . rawurlencode($id) . '/health')['data'];
        return $data;
    }

        // ── API keys ──────────────────────────────────────────────────────
        /**
         * List API keys for the organization (mirrors TS ApiKeysClient::list).
         *
         * @return list<array<string,mixed>>
         */
        public function getApiKeys(): array
        {
            /** @var list<array<string,mixed>> $data */
            $data = $this->http->get('/api-keys')['data'] ?? [];
            return $data;
        }

        /**
         * Create an API key (mirrors TS ApiKeysClient::create). The full secret
         * `key` is present only in this response — store it immediately.
         *
         * @param array{name: string, scopes: list<string>, expires_at?: string} $input
         * @return array<string,mixed>
         */
        public function createApiKey(array $input, ?string $idempotencyKey = null): array
        {
            /** @var array<string,mixed> $data */
            $data = $this->http->post('/api-keys', $input, $idempotencyKey)['data'];
            return $data;
        }

        /**
         * Revoke an API key (mirrors TS ApiKeysClient::revoke).
         *
         * @return array{id: string, revoked: bool}
         */
        public function revokeApiKey(string $id): array
        {
            /** @var array{id: string, revoked: bool} $data */
            $data = $this->http->delete('/api-keys/' . rawurlencode($id))['data'];
            return $data;
        }

        // ── Subscriptions (merchant recurring billing) ────────────────────
        /**
         * List subscriptions for an external customer id (mirrors TS
         * SubscriptionsClient::listForCustomer).
         *
         * NOTE: unlike the TS SDK, this client has no automatic pagination /
         * retry helper yet — callers iterate pages manually where applicable.
         *
         * @return array{subscriptions: list<array<string,mixed>>}
         */
        public function getSubscriptions(string $externalCustomerId): array
        {
            /** @var array{subscriptions: list<array<string,mixed>>} $data */
            $data = $this->http->get('/subscriptions', ['external_customer_id' => $externalCustomerId])['data'];
            return $data;
        }

        /**
         * Get a subscription by id (mirrors TS SubscriptionsClient::get).
         *
         * @return array{subscription: array<string,mixed>}
         */
        public function getSubscription(string $id): array
        {
            /** @var array{subscription: array<string,mixed>} $data */
            $data = $this->http->get('/subscriptions/' . rawurlencode($id))['data'];
            return $data;
        }

        // ── Quotes ────────────────────────────────────────────────────────
        /**
         * Accept a quote/offer on behalf of the API caller.
         *
         * Maps to POST /quotes/{id}/accept; the public-token acceptance flow
         * stays in the app UI — this is the API-key variant.
         *
         * @return array<string,mixed>
         */
        public function acceptOffer(string $quoteId, ?string $idempotencyKey = null): array
        {
            /** @var array<string,mixed> $data */
            $data = $this->http->post('/quotes/' . rawurlencode($quoteId) . '/accept', [], $idempotencyKey)['data'];
            return $data;
        }

        // ── Trust (EU digital signatures) ─────────────────────────────────
        /**
         * List trust documents/signatures (scope: trust:sign).
         *
         * NOTE: no pagination/retry helper here yet (gap vs the TS SDK's
         * page-aware clients) — pass `page`/`perPage` in $params manually.
         *
         * @param array<string, scalar|null> $params
         * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
         */
        public function listTrustDocuments(array $params = []): array
        {
            $res = $this->http->get('/trust/signatures', $params);
            /** @var list<array<string,mixed>> $data */
            $data = $res['data'] ?? [];
            return ['data' => $data, 'meta' => $res['meta'] ?? []];
        }

    /**
     * Verify an incoming webhook signature (X-Brivio-Signature: sha256=<hex>).
     * Optionally pass the X-Brivio-Timestamp header value to enforce a replay
     * window (default ± 300 seconds).
     */
    public static function verifyWebhookSignature(
        string $payload,
        string $signatureHeader,
        string $secret,
        ?string $timestampHeader = null,
        int $toleranceSec = 300,
    ): bool {
        if ($timestampHeader !== null) {
            $ts = (int) $timestampHeader;
            if ($ts === 0 || abs(time() - $ts) > $toleranceSec) {
                return false;
            }
        }
        $actual = preg_replace('/^sha256=/', '', $signatureHeader) ?? '';
        $expected = hash_hmac('sha256', $payload, $secret);
        return hash_equals($expected, $actual);
    }
}
