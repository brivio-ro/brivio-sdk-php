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

    /**
     * List payments on the spine (ADR-0201) — every attempt to collect money,
     * with provider reference and refund state. Filters: status, source_kind,
     * from, to, q (exact provider_ref or pay token), page, perPage.
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listPayments(array $params = []): array
    {
        $res = $this->http->get('/payments', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /** @return array<string,mixed> */
    public function getPayment(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/payments/' . rawurlencode($id))['data'];
        return $data;
    }

    /**
     * Refund through the account that collected the money. Omit amount_minor
     * for the full remaining amount; `pending` true = processor accepted but
     * has not settled. Scope payments:refund.
     * @param array{amount_minor?: int, reason?: string} $input
     * @return array{refunded_minor: int, pending: bool, payment: array<string,mixed>}
     */
    public function refundPayment(string $id, array $input = [], ?string $idempotencyKey = null): array
    {
        /** @var array{refunded_minor: int, pending: bool, payment: array<string,mixed>} $data */
        $data = $this->http->post('/payments/' . rawurlencode($id) . '/refund', $input, $idempotencyKey)['data'];
        return $data;
    }

    // ── Payment links ─────────────────────────────────────────────────
    /**
     * Mint a shareable pay URL. `amount` is in MAJOR units (350.00). The
     * provider is not contacted until the payer opens the link.
     * @param array<string,mixed> $input amount, currency, description, customer_email?, customer_name?, expires_in_days?
     * @return array{id: string, token: string, url: string, qr_url: ?string, expires_at: string}
     */
    public function createPaymentLink(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array{id: string, token: string, url: string, qr_url: ?string, expires_at: string} $data */
        $data = $this->http->post('/payment-links', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listPaymentLinks(array $params = []): array
    {
        $res = $this->http->get('/payment-links', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /** @return array<string,mixed> */
    public function getPaymentLink(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/payment-links/' . rawurlencode($id))['data'];
        return $data;
    }

    /**
     * Cancel an open link. Throws BrivioException (409 CONFLICT) when already paid.
     * @return array{provider_cancelled: bool, link: ?array<string,mixed>}
     */
    public function cancelPaymentLink(string $id, ?string $idempotencyKey = null): array
    {
        /** @var array{provider_cancelled: bool, link: ?array<string,mixed>} $data */
        $data = $this->http->post('/payment-links/' . rawurlencode($id) . '/cancel', [], $idempotencyKey)['data'];
        return $data;
    }

    // ── Payouts ───────────────────────────────────────────────────────
    /**
     * Processor settlements with the charges each one covers. Filters:
     * provider, status, from, to (arrival_date, YYYY-MM-DD), include_items.
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listPayouts(array $params = []): array
    {
        $res = $this->http->get('/payouts', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
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

    // ── Construction ──────────────────────────────────────────────────
    /**
     * Situații de lucrări — what has been certified as built, and for how
     * much (scope: projects:read).
     *
     * `project_id` filters through the deviz rather than a column on the
     * certificate: a situație reaches its project only via the quote it was
     * cut against, so the server does the join.
     *
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listWorkCertificates(array $params = []): array
    {
        $res = $this->http->get('/construction/work-certificates', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * The graficul de eșalonare of a project, in order (scope: projects:read).
     *
     * @return list<array<string,mixed>>
     */
    public function listProjectSchedule(string $projectId): array
    {
        $res = $this->http->get('/construction/projects/' . rawurlencode($projectId) . '/schedule');
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
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

    // BK-0044 — read-complete + import / match / rules.

    /**
     * One bank transaction with its invoice allocations.
     *
     * @return array<string,mixed>
     */
    public function getBankingTransaction(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/banking/transactions/' . rawurlencode($id))['data'];
        return $data;
    }

    /**
     * Top-5 open-invoice candidates for a bank line.
     *
     * @return list<array<string,mixed>>
     */
    public function suggestBankingMatches(string $id): array
    {
        /** @var list<array<string,mixed>> $data */
        $data = $this->http->get('/banking/transactions/' . rawurlencode($id) . '/suggestions')['data'] ?? [];
        return $data;
    }

    /**
     * Allocate a bank line to an invoice and post it to the ledger.
     *
     * @param array{invoice_id: string, amount?: float} $input
     * @return array<string,mixed>
     */
    public function matchBankingTransaction(string $id, array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/banking/transactions/' . rawurlencode($id) . '/match', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Unmatch a bank line. A line posted to the ledger needs `storno => true`.
     *
     * @param array{storno?: bool} $input
     * @return array<string,mixed>
     */
    public function unmatchBankingTransaction(string $id, array $input = []): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/banking/transactions/' . rawurlencode($id) . '/unmatch', $input)['data'];
        return $data;
    }

    /**
     * Imported bank statements (IBAN masked to last4).
     *
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listBankingStatements(array $params = []): array
    {
        $res = $this->http->get('/banking/statements', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * One statement with its (paginated) transactions.
     *
     * @param array<string, scalar|null> $params
     * @return array{data: array<string,mixed>, meta: array<string,mixed>}
     */
    public function getBankingStatement(string $id, array $params = []): array
    {
        $res = $this->http->get('/banking/statements/' . rawurlencode($id), $params);
        /** @var array<string,mixed> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * Import a statement file (csv, mt940, camt053, ...). Re-importing the
     * same file is a safe no-op.
     *
     * @param array{format: string, content: string, sourceFilename?: string} $input
     * @return array<string,mixed>
     */
    public function importBankingStatement(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/banking/statements/import', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listBankingRules(): array
    {
        /** @var list<array<string,mixed>> $data */
        $data = $this->http->get('/banking/rules')['data'] ?? [];
        return $data;
    }

    /**
     * @return array<string,mixed>
     */
    public function getBankingRule(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/banking/rules/' . rawurlencode($id))['data'];
        return $data;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string,mixed>
     */
    public function createBankingRule(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/banking/rules', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string,mixed>
     */
    public function updateBankingRule(string $id, array $input): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->patch('/banking/rules/' . rawurlencode($id), $input)['data'];
        return $data;
    }

    /**
     * @return array<string,mixed>
     */
    public function deleteBankingRule(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->delete('/banking/rules/' . rawurlencode($id))['data'];
        return $data;
    }

    /**
     * Payment batches (read-only; IBANs masked).
     *
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listBankingPaymentBatches(array $params = []): array
    {
        $res = $this->http->get('/banking/payment-batches', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * @return array<string,mixed>
     */
    public function getBankingPaymentBatch(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/banking/payment-batches/' . rawurlencode($id))['data'];
        return $data;
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

        /**
         * Report usage for a metered price (ADR-0212). `identifier` makes a retry a no-op.
         *
         * @param array{quantity:int, timestamp?:string, identifier?:string} $input
         * @return array<string,mixed>
         */
        public function reportSubscriptionUsage(string $id, array $input): array
        {
            /** @var array<string,mixed> $data */
            $data = $this->http->post('/subscriptions/' . rawurlencode($id) . '/usage', $input)['data'];
            return $data;
        }

        // ── Quotes ────────────────────────────────────────────────────────
        /**
         * Accept a quote/offer on behalf of the API caller.
         *
         * Maps to POST /network/quotes/{id}/accept (there is no bare /quotes/{id}/accept
         * in the API — that path never existed; BC-0194). Alias of networkAcceptQuote().
         *
         * @return array<string,mixed>
         */
        public function acceptOffer(string $quoteId, ?string $idempotencyKey = null): array
        {
            /** @var array<string,mixed> $data */
            $data = $this->http->post('/network/quotes/' . rawurlencode($quoteId) . '/accept', [], $idempotencyKey)['data'];
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

        // ── Business Network (BN-0082, ADR-0157) ──────────────────────────
        // Find verified providers, ask up to three for a quote, accept one.
        // Money never transits Brivio (ADR-0147): accepting a quote creates a
        // relationship, not a charge.

        /**
         * Ranked provider search; policy-gated per category (scope: network:read).
         *
         * @param array<string, scalar|null> $params category, county, city, q, limit (max 50)
         * @return array{ranking_config_version: int, partners: list<array<string,mixed>>}
         */
        public function networkSearchPartners(array $params = []): array
        {
            /** @var array{ranking_config_version: int, partners: list<array<string,mixed>>} $data */
            $data = $this->http->get('/network/partners', $params)['data'];
            return $data;
        }

        /**
         * Requests you raised (role=requester, default) or were asked to quote
         * (role=provider, requester redacted to a label) (scope: network:read).
         *
         * @param array<string, scalar|null> $params role, page, perPage
         * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
         */
        public function networkListRequests(array $params = []): array
        {
            $res = $this->http->get('/network/requests', $params);
            /** @var list<array<string,mixed>> $data */
            $data = $res['data'] ?? [];
            return ['data' => $data, 'meta' => $res['meta'] ?? []];
        }

        /**
         * Create a service request to at most 3 verified listings chosen from
         * networkSearchPartners() (scope: network:write).
         *
         * @param array<string, mixed> $body category, title, brief, listing_ids (1-3), county?, city?, accepts_remote?, budget_bani?, details?
         * @return array{id: string, targets: int}
         */
        public function networkCreateRequest(array $body, ?string $idempotencyKey = null): array
        {
            /** @var array{id: string, targets: int} $data */
            $data = $this->http->post('/network/requests', $body, $idempotencyKey)['data'];
            return $data;
        }

        /**
         * One request: full with every quote for the requester, redacted with
         * only your own quote for a targeted provider (scope: network:read).
         *
         * @return array<string, mixed>
         */
        public function networkGetRequest(string $requestId): array
        {
            /** @var array<string, mixed> $data */
            $data = $this->http->get('/network/requests/' . rawurlencode($requestId))['data'];
            return $data;
        }

        /**
         * Submit (or supersede) your quote on a request you were asked to quote
         * (scope: network:write).
         *
         * @param array<string, mixed> $body amount_bani, currency?, pricing?, includes?, excludes?, delivery_days?, valid_days?, non_assurance_attested?
         * @return array<string, mixed>
         */
        public function networkSubmitQuote(string $requestId, array $body, ?string $idempotencyKey = null): array
        {
            /** @var array<string, mixed> $data */
            $data = $this->http->post(
                '/network/requests/' . rawurlencode($requestId) . '/quotes',
                $body,
                $idempotencyKey,
            )['data'];
            return $data;
        }

        /**
         * Accept a quote on your request: closes the request, declines the
         * others, creates the relationship. No payment step (scope: network:write).
         *
         * @return array{relationship_id: string, request_id: string, quote_id: string, payment_required: bool}
         */
        public function networkAcceptQuote(string $quoteId, ?string $idempotencyKey = null): array
        {
            /** @var array{relationship_id: string, request_id: string, quote_id: string, payment_required: bool} $data */
            $data = $this->http->post('/network/quotes/' . rawurlencode($quoteId) . '/accept', [], $idempotencyKey)['data'];
            return $data;
        }

        /**
         * Decline one quote on your request (scope: network:write).
         *
         * @return array{id: string, status: string}
         */
        public function networkDeclineQuote(string $quoteId, ?string $idempotencyKey = null): array
        {
            /** @var array{id: string, status: string} $data */
            $data = $this->http->post('/network/quotes/' . rawurlencode($quoteId) . '/decline', [], $idempotencyKey)['data'];
            return $data;
        }

        /**
         * Withdraw an open request you raised; live quotes are declined
         * (scope: network:write).
         *
         * @return array{id: string, status: string}
         */
        public function networkWithdrawRequest(string $requestId, ?string $idempotencyKey = null): array
        {
            /** @var array{id: string, status: string} $data */
            $data = $this->http->post('/network/requests/' . rawurlencode($requestId) . '/withdraw', [], $idempotencyKey)['data'];
            return $data;
        }

        /**
         * Relationships where you are the provider or the client, with origin
         * and service kind; never the counterpart's contact details
         * (scope: network:read).
         *
         * @param array<string, scalar|null> $params page, perPage
         * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
         */
        public function networkListRelationships(array $params = []): array
        {
            $res = $this->http->get('/network/relationships', $params);
            /** @var list<array<string,mixed>> $data */
            $data = $res['data'] ?? [];
            return ['data' => $data, 'meta' => $res['meta'] ?? []];
        }

    // ── Contacts / articles / projects / contracts / expenses / locations — single-record CRUD (BC-0194) ───

    /**
     * Get a contact by ID.
     *
     * @return array<string,mixed>
     */
    public function getContact(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/contacts/' . rawurlencode($id))['data'];
        return $data;
    }

    /**
     * Update a contact (partial).
     *
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function updateContact(string $id, array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->patch('/contacts/' . rawurlencode($id), $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Delete a contact.
     */
    public function deleteContact(string $id): void
    {
        $this->http->delete('/contacts/' . rawurlencode($id));
    }

    /**
     * Get an article by ID.
     *
     * @return array<string,mixed>
     */
    public function getArticle(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/articles/' . rawurlencode($id))['data'];
        return $data;
    }

    /**
     * Update an article (partial).
     *
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function updateArticle(string $id, array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->patch('/articles/' . rawurlencode($id), $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Delete an article.
     */
    public function deleteArticle(string $id): void
    {
        $this->http->delete('/articles/' . rawurlencode($id));
    }

    /**
     * Upsert articles by external_id (ADR-0212). Input: { articles: list<array>, deactivate_missing_prices?: bool, sync_prices?: bool }.
     *
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function syncArticles(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/articles/sync', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * List an article's prices (ADR-0212).
     *
     * @return list<array<string,mixed>>
     */
    public function listArticlePrices(string $id): array
    {
        /** @var list<array<string,mixed>> $data */
        $data = $this->http->get('/articles/' . rawurlencode($id) . '/prices')['data'] ?? [];
        return $data;
    }

    /**
     * Add a price to an article; `unit_amount` in MINOR units, interval one_time|month|year|metered (ADR-0212).
     *
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function createArticlePrice(string $id, array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/articles/' . rawurlencode($id) . '/prices', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Push an article's active prices to the org's Stripe account (ADR-0212).
     *
     * @return array<string,mixed>
     */
    public function syncArticlePrices(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/articles/' . rawurlencode($id) . '/prices/sync', [])['data'];
        return $data;
    }

    /**
     * List a kit's components (BUNDLE articles only, ADR-0225).
     *
     * @return list<array<string,mixed>>
     */
    public function listArticleComponents(string $id): array
    {
        /** @var list<array<string,mixed>> $data */
        $data = $this->http->get('/articles/' . rawurlencode($id) . '/components')['data'] ?? [];
        return $data;
    }

    /**
     * Replace a kit's components; each item: component_article_id, quantity, is_optional?, option_group?, substitutes? (ADR-0225).
     *
     * @param list<array<string,mixed>> $components
     * @return list<array<string,mixed>>
     */
    public function setArticleComponents(string $id, array $components, ?string $idempotencyKey = null): array
    {
        /** @var list<array<string,mixed>> $data */
        $data = $this->http->put('/articles/' . rawurlencode($id) . '/components', ['components' => $components], $idempotencyKey)['data'] ?? [];
        return $data;
    }

    /**
     * A project's team with each person's org-wide weekly load vs capacity (L-R4-PROJ-07).
     *
     * @param array{from?:string,weeks?:int} $params
     * @return array<string,mixed>
     */
    public function listProjectAllocations(string $id, array $params = []): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/projects/' . rawurlencode($id) . '/allocations', $params)['data'] ?? [];
        return $data;
    }

    /**
     * Book a person on a project for one ISO week (0 hours clears); the result carries over_allocated (L-R4-PROJ-07).
     *
     * @param array{user_id:string,week_start:string,allocated_hours:float|int,role?:?string,note?:?string} $input
     * @return array<string,mixed>
     */
    public function setProjectAllocation(string $id, array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->put('/projects/' . rawurlencode($id) . '/allocations', $input, $idempotencyKey)['data'] ?? [];
        return $data;
    }

    /**
     * People x ISO weeks across the org: allocated vs available hours (L-R4-PROJ-07).
     *
     * @param array{from?:string,weeks?:int} $params
     * @return array<string,mixed>
     */
    public function getResourcing(array $params = []): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/resourcing', $params)['data'] ?? [];
        return $data;
    }

    /**
     * Get a project by ID.
     *
     * @return array<string,mixed>
     */
    public function getProject(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/projects/' . rawurlencode($id))['data'];
        return $data;
    }

    /**
     * Update a project (partial).
     *
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function updateProject(string $id, array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->patch('/projects/' . rawurlencode($id), $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Delete a project.
     */
    public function deleteProject(string $id): void
    {
        $this->http->delete('/projects/' . rawurlencode($id));
    }

    /**
     * Get a contract by ID.
     *
     * @return array<string,mixed>
     */
    public function getContract(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/contracts/' . rawurlencode($id))['data'];
        return $data;
    }

    /**
     * Update a contract (partial).
     *
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function updateContract(string $id, array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->patch('/contracts/' . rawurlencode($id), $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Delete a contract.
     */
    public function deleteContract(string $id): void
    {
        $this->http->delete('/contracts/' . rawurlencode($id));
    }

    // ── Contract obligations (L-R4-CONTR-05) ───────────────────────────
    /**
     * The obligations register across contracts, oldest due first.
     * Filters: contract_id, bucket (open|overdue|due_soon|upcoming|undated|done),
     * obligor (us|counterparty), assignee_user_id, q, page, perPage.
     *
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listContractObligations(array $params = []): array
    {
        $res = $this->http->get('/contract-obligations', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * 422 ASSIGNEE_NOT_MEMBER / DOCUMENT_NOT_FOUND for references outside the org.
     *
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function createContractObligation(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/contract-obligations', $input, $idempotencyKey)['data'];
        return $data;
    }

    /** @return array<string,mixed> */
    public function getContractObligation(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/contract-obligations/' . rawurlencode($id))['data'];
        return $data;
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function updateContractObligation(string $id, array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->patch('/contract-obligations/' . rawurlencode($id), $input, $idempotencyKey)['data'];
        return $data;
    }

    public function deleteContractObligation(string $id): void
    {
        $this->http->delete('/contract-obligations/' . rawurlencode($id));
    }

    /**
     * Complete an obligation. Our own payment/delivery/report obligations need
     * `evidence_document_id` or `evidence_note` (422 EVIDENCE_REQUIRED); 409 when done.
     *
     * @param array{evidence_document_id?: string, evidence_note?: string} $evidence
     * @return array{obligation: array<string,mixed>, next: array<string,mixed>|null}
     */
    public function completeContractObligation(string $id, array $evidence = [], ?string $idempotencyKey = null): array
    {
        /** @var array{obligation: array<string,mixed>, next: array<string,mixed>|null} $data */
        $data = $this->http->post('/contract-obligations/' . rawurlencode($id) . '/complete', $evidence, $idempotencyKey)['data'];
        return $data;
    }

    /** @return array<string,mixed> */
    public function reopenContractObligation(string $id, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/contract-obligations/' . rawurlencode($id) . '/reopen', [], $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Get an expense by ID.
     *
     * @return array<string,mixed>
     */
    public function getExpense(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/expenses/' . rawurlencode($id))['data'];
        return $data;
    }

    /**
     * Update an expense (partial).
     *
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function updateExpense(string $id, array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->patch('/expenses/' . rawurlencode($id), $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Delete an expense.
     */
    public function deleteExpense(string $id): void
    {
        $this->http->delete('/expenses/' . rawurlencode($id));
    }

    /**
     * Get a location by ID.
     *
     * @return array<string,mixed>
     */
    public function getLocation(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/locations/' . rawurlencode($id))['data'];
        return $data;
    }

    // ── Companies — lookup + GET search (BC-0194) ─────────────────────────

    /**
     * Look up one company by CUI (`cui`) or by name (`q`). Scope: contacts:read.
     *
     * @param array{cui?: string, q?: string} $params
     * @return array<string,mixed>|list<array<string,mixed>>
     */
    public function lookupCompany(array $params = []): array
    {
        /** @var array<string,mixed>|list<array<string,mixed>> $data */
        $data = $this->http->get('/companies/lookup', $params)['data'] ?? [];
        return $data;
    }

    /**
     * GET variant of the registry search for simple filters passed as query parameters (the POST variant takes structured groups). Scope: companies:read.
     *
     * @param array<string, scalar|null> $params
     * @return array<string,mixed>|list<array<string,mixed>>
     */
    public function searchCompaniesGet(array $params = []): array
    {
        /** @var array<string,mixed>|list<array<string,mixed>> $data */
        $data = $this->http->get('/companies/search', $params)['data'] ?? [];
        return $data;
    }

    // ── Invoices — line items, payments, send, e-Factura status (BC-0194) ───

    /**
     * Line items of an invoice.
     *
     * @return array<string,mixed>|list<array<string,mixed>>
     */
    public function listInvoiceItems(string $id): array
    {
        /** @var array<string,mixed>|list<array<string,mixed>> $data */
        $data = $this->http->get('/invoices/' . rawurlencode($id) . '/items')['data'] ?? [];
        return $data;
    }

    /**
     * Payments recorded against an invoice.
     *
     * @return array<string,mixed>|list<array<string,mixed>>
     */
    public function listInvoicePayments(string $id): array
    {
        /** @var array<string,mixed>|list<array<string,mixed>> $data */
        $data = $this->http->get('/invoices/' . rawurlencode($id) . '/payments')['data'] ?? [];
        return $data;
    }

    /**
     * Record a payment against an invoice.
     *
     * @param array{amount: string|float, payment_date: string, currency?: string, method?: string, reference?: string, notes?: string} $input
     * @return array<string,mixed>
     */
    public function recordInvoicePayment(string $id, array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/invoices/' . rawurlencode($id) . '/payments', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Issue an invoice (DRAFT → SENT; idempotent): VIES, storno association, stock and ledger posting like the app; 409 CONFLICT on a VIES or storno refusal.
     *
     * @return array<string,mixed>
     */
    public function sendInvoice(string $id, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/invoices/' . rawurlencode($id) . '/send', [], $idempotencyKey)['data'];
        return $data;
    }

    /**
     * e-Factura (SPV) transmission status of an invoice.
     *
     * @return array<string,mixed>|list<array<string,mixed>>
     */
    public function getInvoiceEfacturaStatus(string $id): array
    {
        /** @var array<string,mixed>|list<array<string,mixed>> $data */
        $data = $this->http->get('/invoices/' . rawurlencode($id) . '/efactura-status')['data'] ?? [];
        return $data;
    }

    /**
     * Issue a credit note (storno) for an issued invoice (ADR-0212).
     * Omit `lines` for a full storno; partial: list<array{invoice_item_id:string, quantity:float|int}>.
     * 422 CREDIT_EXCEEDS_SOURCE when the credit notes would exceed the source total.
     *
     * @param array{reason?:string, lines?:list<array{invoice_item_id:string, quantity:float|int}>} $input
     * @return array<string,mixed>
     */
    public function createInvoiceCreditNote(string $id, array $input = [], ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/invoices/' . rawurlencode($id) . '/credit-note', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * The invoice PDF as raw bytes (ADR-0212).
     */
    public function getInvoicePdf(string $id): string
    {
        /** @var string $bytes */
        $bytes = $this->http->get('/invoices/' . rawurlencode($id) . '/pdf', [], true)['data'];
        return $bytes;
    }

    /**
     * Get (minting on first call) the invoice's public pay URL (ADR-0212).
     *
     * @return array<string,mixed>
     */
    public function getInvoicePaymentLink(string $id, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/invoices/' . rawurlencode($id) . '/payment-link', [], $idempotencyKey)['data'];
        return $data;
    }

    // ── Quotes / time entries (BC-0194) ───────────────────────────────────

    /**
     * List quotes. Params: page, perPage, status, search.
     *
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listQuotes(array $params = []): array
    {
        $res = $this->http->get('/quotes', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * Get a quote by ID.
     *
     * @return array<string,mixed>
     */
    public function getQuote(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/quotes/' . rawurlencode($id))['data'];
        return $data;
    }

    // Quotes are read-only over the API (BC-0195): PATCH/DELETE /quotes/{id}
    // were advertised in the spec but never served, so the methods are gone.

    /**
     * List time entries. Params: page, perPage, project_id, from, to, search.
     *
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listTimeEntries(array $params = []): array
    {
        $res = $this->http->get('/time-entries', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * Get a time entry by ID.
     *
     * @return array<string,mixed>
     */
    public function getTimeEntry(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/time-entries/' . rawurlencode($id))['data'];
        return $data;
    }

    // Time entries are read-only over the API (BC-0195): PATCH/DELETE
    // /time-entries/{id} were advertised in the spec but never served.

    // ── Affiliates (BC-0194) ──────────────────────────────────────────────

    /**
     * List affiliates of the programme. Params: page, perPage, status.
     *
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listAffiliates(array $params = []): array
    {
        $res = $this->http->get('/affiliates', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * Enrol an affiliate.
     *
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function enrollAffiliate(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/affiliates', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Get an affiliate by ID.
     *
     * @return array<string,mixed>
     */
    public function getAffiliate(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/affiliates/' . rawurlencode($id))['data'];
        return $data;
    }

    /**
     * Referrals attributed to an affiliate.
     *
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listAffiliateReferrals(string $id, array $params = []): array
    {
        $res = $this->http->get('/affiliates/' . rawurlencode($id) . '/referrals', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * Commission entries of an affiliate.
     *
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listAffiliateCommissions(string $id, array $params = []): array
    {
        $res = $this->http->get('/affiliates/' . rawurlencode($id) . '/commissions', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * Create a referral code for an affiliate.
     *
     * @param array{code: string, kind?: string} $input
     * @return array<string,mixed>
     */
    public function createAffiliateCode(string $id, array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/affiliates/' . rawurlencode($id) . '/codes', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Record a referral click from an external storefront (scope: affiliates:write).
     *
     * @param array{code: string, ip?: string, user_agent?: string, referer?: string, landing_path?: string, utm?: array<string,string>} $input
     * @return array<string,mixed>
     */
    public function recordAffiliateClick(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/affiliate-events/click', $input, $idempotencyKey)['data'];
        return $data;
    }

    // ── Comms + Connect outreach (BC-0194) ────────────────────────────────

    /**
     * List Brivio Comms conversations. Params: page, perPage, status.
     *
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listConversations(array $params = []): array
    {
        $res = $this->http->get('/comms/conversations', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * Get a conversation by ID.
     *
     * @return array<string,mixed>
     */
    public function getConversation(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/comms/conversations/' . rawurlencode($id))['data'];
        return $data;
    }

    /**
     * Messages of a conversation.
     *
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listConversationMessages(string $id, array $params = []): array
    {
        $res = $this->http->get('/comms/conversations/' . rawurlencode($id) . '/messages', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * Send a message in a conversation.
     *
     * @param array{content: string, ai_generated?: bool} $input
     * @return array<string,mixed>
     */
    public function sendConversationMessage(string $id, array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/comms/conversations/' . rawurlencode($id) . '/messages', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * List Connect outreach sequences. Params: page, perPage.
     *
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listOutreach(array $params = []): array
    {
        $res = $this->http->get('/connect/outreach', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * Get an outreach sequence by ID.
     *
     * @return array<string,mixed>
     */
    public function getOutreach(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/connect/outreach/' . rawurlencode($id))['data'];
        return $data;
    }

    // ── Webhooks — events + redeliver; API keys — rotate (BC-0194) ────────

    /**
     * Event types a webhook can subscribe to.
     *
     * @return array<string,mixed>|list<array<string,mixed>>
     */
    public function listWebhookEvents(): array
    {
        /** @var array<string,mixed>|list<array<string,mixed>> $data */
        $data = $this->http->get('/webhooks/events')['data'] ?? [];
        return $data;
    }

    /**
     * Redeliver a failed/dead delivery now; pass ['force' => true] for an already-succeeded one.
     *
     * @param array{force?: bool} $input
     * @return array<string,mixed>
     */
    public function redeliverWebhookDelivery(string $id, string $deliveryId, array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/webhooks/' . rawurlencode($id) . '/deliveries/' . rawurlencode($deliveryId) . '/redeliver', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Issue a replacement key with the same name and scopes; the old key keeps working for the grace period.
     *
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function rotateApiKey(string $id, array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/api-keys/' . rawurlencode($id) . '/rotate', $input, $idempotencyKey)['data'];
        return $data;
    }

    // ── Reference data — series, VAT rates, stock by location, banking coverage, cabinet SPV (BC-0194) ───

    /**
     * Document numbering series. Params: page, perPage, document_type, active.
     *
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listSeries(array $params = []): array
    {
        $res = $this->http->get('/series', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * Romanian VAT rates valid on a date (default today, Bucharest time).
     *
     * @param array{date?: string} $params
     * @return array<string,mixed>|list<array<string,mixed>>
     */
    public function getVatRates(array $params = []): array
    {
        /** @var array<string,mixed>|list<array<string,mixed>> $data */
        $data = $this->http->get('/taxes/vat-rates', $params)['data'] ?? [];
        return $data;
    }

    /**
     * Per-location (gestiune) stock balances. Params: page, perPage, article_id, location_id.
     *
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listStockByLocation(array $params = []): array
    {
        $res = $this->http->get('/inventory/stock-by-location', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * Reconciliation coverage per own bank account (scope: banking:read).
     *
     * @return array<string,mixed>|list<array<string,mixed>>
     */
    public function getBankingCoverage(): array
    {
        /** @var array<string,mixed>|list<array<string,mixed>> $data */
        $data = $this->http->get('/banking/coverage')['data'] ?? [];
        return $data;
    }

    /**
     * One row per active managed client: last SPV sync, unread, rejected, SLA at risk (scope: efactura:read).
     *
     * @return array<string,mixed>|list<array<string,mixed>>
     */
    public function getCabinetSpvStatus(): array
    {
        /** @var array<string,mixed>|list<array<string,mixed>> $data */
        $data = $this->http->get('/cabinet/spv-status')['data'] ?? [];
        return $data;
    }

    /**
     * ANAF risk score (0–100) per active managed client, with the signals behind it (scope: accounting:read).
     *
     * @return array<string,mixed>|list<array<string,mixed>>
     */
    public function getCabinetRisk(): array
    {
        /** @var array<string,mixed>|list<array<string,mixed>> $data */
        $data = $this->http->get('/cabinet/risk')['data'] ?? [];
        return $data;
    }

    // ── Storefront verticals — orders, bookings, reservations, catalog, customers (BC-0194) ───

    /**
     * Storefront orders. Params: page, perPage, status.
     *
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listOrders(array $params = []): array
    {
        $res = $this->http->get('/orders', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * Get an order with its line items.
     *
     * @return array<string,mixed>
     */
    public function getOrder(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/orders/' . rawurlencode($id))['data'];
        return $data;
    }

    /**
     * Transition an order: pending|paid|fulfilled|cancelled|refunded.
     *
     * @param array{status: string} $input
     * @return array<string,mixed>
     */
    public function setOrderStatus(string $id, array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/orders/' . rawurlencode($id) . '/status', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Service appointments. Params: page, perPage, status.
     *
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listBookings(array $params = []): array
    {
        $res = $this->http->get('/bookings', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * Get an appointment.
     *
     * @return array<string,mixed>
     */
    public function getBooking(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/bookings/' . rawurlencode($id))['data'];
        return $data;
    }

    /**
     * Book an appointment.
     *
     * @param array{service_id: string, resource_id: string, customer_name: string, starts_at: string, customer_email?: string, customer_phone?: string, notes?: string} $input
     * @return array<string,mixed>
     */
    public function createBooking(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/bookings', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Hotel reservations. Params: page, perPage, status.
     *
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listReservations(array $params = []): array
    {
        $res = $this->http->get('/reservations', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * Get a reservation.
     *
     * @return array<string,mixed>
     */
    public function getReservation(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/reservations/' . rawurlencode($id))['data'];
        return $data;
    }

    /**
     * Sellable product catalog with synced prices (scope: articles:read).
     *
     * @return array<string,mixed>|list<array<string,mixed>>
     */
    public function getCatalog(): array
    {
        /** @var array<string,mixed>|list<array<string,mixed>> $data */
        $data = $this->http->get('/catalog')['data'] ?? [];
        return $data;
    }

    /**
     * Consolidated billing view of an external customer (scope: invoices:read).
     *
     * @return array<string,mixed>
     */
    public function getCustomer(string $externalId): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/customers/' . rawurlencode($externalId))['data'];
        return $data;
    }

    // ── Marketing, payment methods, subscriptions — merchant platform (BC-0194) ───

    /**
     * Marketing audiences.
     *
     * @return array<string,mixed>|list<array<string,mixed>>
     */
    public function listAudiences(): array
    {
        /** @var array<string,mixed>|list<array<string,mixed>> $data */
        $data = $this->http->get('/marketing/audiences')['data'] ?? [];
        return $data;
    }

    /**
     * Create an audience.
     *
     * @param array{name: string, description?: string, contact_ids?: list<string>} $input
     * @return array<string,mixed>
     */
    public function createAudience(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/marketing/audiences', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Add a newsletter subscriber with double opt-in (V3-27). Creates a
     * `pending` subscriber and emails a confirmation link; nothing is sent
     * before the person confirms. Idempotent per address (200 when known,
     * 201 when new). Scope marketing_subscribers:write.
     *
     * @param array{email: string, consent: array{text_version: string, text?: string, ip?: string, user_agent?: string}, name?: string, locale?: string, source?: string, redirect_url?: string} $input
     * @return array<string,mixed>
     */
    public function createMarketingSubscriber(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/marketing/subscribers', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Marketing campaigns.
     *
     * @param array{channel?: string, status?: string} $params
     * @return array<string,mixed>|list<array<string,mixed>>
     */
    public function listCampaigns(array $params = []): array
    {
        /** @var array<string,mixed>|list<array<string,mixed>> $data */
        $data = $this->http->get('/marketing/campaigns', $params)['data'] ?? [];
        return $data;
    }

    /**
     * Create a campaign (draft).
     *
     * @param array{name: string, channel: string, audience_id: string, template_id?: string, subject?: string, body?: string, from_address?: string} $input
     * @return array<string,mixed>
     */
    public function createCampaign(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/marketing/campaigns', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Saved payment methods of an external customer.
     *
     * @return array<string,mixed>|list<array<string,mixed>>
     */
    public function listPaymentMethods(string $externalCustomerId): array
    {
        /** @var array<string,mixed>|list<array<string,mixed>> $data */
        $data = $this->http->get('/payment-methods', ['external_customer_id' => $externalCustomerId])['data'] ?? [];
        return $data;
    }

    /**
     * Create a SetupIntent so the customer can save a card.
     *
     * @param array{external_customer_id: string, action: 'setup_intent', customer_email?: string} $input
     * @return array<string,mixed>
     */
    public function createSetupIntent(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/payment-methods', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Set a saved method as default or detach it.
     *
     * @param array{external_customer_id: string, payment_method_id: string, action: 'set_default'|'detach'} $input
     * @return array<string,mixed>
     */
    public function updatePaymentMethod(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->patch('/payment-methods', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Start a merchant subscription for an external customer.
     *
     * @param array{article_price_id: string, external_customer_id: string, customer: array{email: string, name?: string}, contact_id?: string, metadata?: array<string,string>} $input
     * @return array<string,mixed>
     */
    public function createSubscription(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/subscriptions', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * cancel | resume | change_price (with article_price_id).
     *
     * @param array{action: 'cancel'|'resume'|'change_price', article_price_id?: string} $input
     * @return array<string,mixed>
     */
    public function updateSubscription(string $id, array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->patch('/subscriptions/' . rawurlencode($id), $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Cancel immediately and delete the subscription record.
     */
    public function deleteSubscription(string $id): void
    {
        $this->http->delete('/subscriptions/' . rawurlencode($id));
    }

    // ── Trust — providers, signatures, validations, timestamps (BC-0194) ───

    /**
     * Available signature providers.
     *
     * @return array<string,mixed>|list<array<string,mixed>>
     */
    public function listTrustProviders(): array
    {
        /** @var array<string,mixed>|list<array<string,mixed>> $data */
        $data = $this->http->get('/trust/providers')['data'] ?? [];
        return $data;
    }

    /**
     * Request a signature on a base64-encoded document.
     *
     * @param array{document: string, file_name: string, signature_type?: string, level?: string, format?: string, provider?: string} $input
     * @return array<string,mixed>
     */
    public function requestSignature(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/trust/signatures', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Batch-sign up to 100 documents.
     *
     * @param array{documents: list<array{document: string, file_name: string}>, signature_type?: string, level?: string, format?: string, provider?: string} $input
     * @return array<string,mixed>
     */
    public function requestSignatureBatch(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/trust/signatures/batch', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Get a signature request by ID.
     *
     * @return array<string,mixed>
     */
    public function getSignature(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/trust/signatures/' . rawurlencode($id))['data'];
        return $data;
    }

    /**
     * Evidence bundles (audit trail, timestamps, certificates) of a signature.
     *
     * @return array<string,mixed>|list<array<string,mixed>>
     */
    public function getSignatureEvidence(string $id): array
    {
        /** @var array<string,mixed>|list<array<string,mixed>> $data */
        $data = $this->http->get('/trust/signatures/' . rawurlencode($id) . '/evidence')['data'] ?? [];
        return $data;
    }

    /**
     * Validate signatures on a base64-encoded signed document.
     *
     * @param array{document: string, file_name: string, original_document?: string} $input
     * @return array<string,mixed>
     */
    public function validateSignature(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/trust/validations', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Get a stored validation result.
     *
     * @return array<string,mixed>
     */
    public function getValidation(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/trust/validations/' . rawurlencode($id))['data'];
        return $data;
    }

    /**
     * Apply a qualified timestamp to a signed document.
     *
     * @param array{document: string, file_name: string, provider?: string} $input
     * @return array<string,mixed>
     */
    public function requestTimestamp(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/trust/timestamps', $input, $idempotencyKey)['data'];
        return $data;
    }

    // ── Sites + Email-as-a-service (ADR-0210, BC-0194) ────────────────────────
    /**
     * Hosted sites of the organization.
     *
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listSites(array $params = []): array
    {
        $res = $this->http->get('/sites', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * Send an e-mail as the organization (transactional | notification | marketing).
     * Attachments are base64 (2 MB total). Scope: emails:send.
     *
     * @param array{to: list<array{email: string, name?: string}>, subject: string, category: string, html?: string, text?: string, cc?: list<array{email: string, name?: string}>, bcc?: list<array{email: string, name?: string}>, from?: array{email: string, name?: string}, reply_to?: array{email: string, name?: string}, headers?: array<string,string>, tags?: list<string>, attachments?: list<array{filename: string, content_base64: string, content_type?: string}>} $input
     * @return array<string,mixed>
     */
    public function sendEmail(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/emails/send', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Sent messages. Params: since (ISO datetime), status, page, perPage.
     *
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listEmails(array $params = []): array
    {
        $res = $this->http->get('/emails', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * One sent message with its delivery lifecycle.
     *
     * @return array<string,mixed>
     */
    public function getEmail(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/emails/' . rawurlencode($id))['data'];
        return $data;
    }

    /**
     * Platform mail lane (POST /email/send): RFC 5322 address strings and key=>value tags.
    * 202 when recorded (status queued|sent|suppressed); 422 from_domain_not_verified;
    * 422 sender_identity_missing (no sending identity may use that from: neither the domain's
    * sending address nor a mailbox/alias with API sending enabled);
     * 502 send_failed. Scope: emails:send.
     *
     * @param array{to: list<string>, subject: string, from?: string, cc?: list<string>, bcc?: list<string>, reply_to?: string, html?: string, text?: string, headers?: array<string,string>, tags?: array<string,string>, category?: string} $input
     * @return array<string,mixed>
     */
    public function sendEmailMessage(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/email/send', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * A message sent through sendEmailMessage, with its delivery timeline.
     * Scope: emails:send or emails:read.
     *
     * @return array<string,mixed>
     */
    public function getEmailMessage(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/email/messages/' . rawurlencode($id))['data'];
        return $data;
    }

    /**
     * Sending domains of the organization (SPF/DKIM/DMARC state per domain).
     *
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listEmailDomains(array $params = []): array
    {
        $res = $this->http->get('/email-domains', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * Register a sending domain; the response carries the DNS records to publish.
     *
     * @param array{domain: string, from_local_part?: string} $input
     * @return array<string,mixed>
     */
    public function createEmailDomain(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/email-domains', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * @return array<string,mixed>
     */
    public function getEmailDomain(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/email-domains/' . rawurlencode($id))['data'];
        return $data;
    }

    /**
     * Re-check the DNS records of a sending domain and update its verification state.
     *
     * @return array<string,mixed>
     */
    public function verifyEmailDomain(string $id, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/email-domains/' . rawurlencode($id) . '/verify', [], $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Remove a sending domain.
     */
    public function deleteEmailDomain(string $id): void
    {
        $this->http->delete('/email-domains/' . rawurlencode($id));
    }

    // ── SAF-T D406 generations (BC-0196) ─────────────────────────────
    /**
     * SAF-T generations, newest first. Filters: page, perPage, fiscal_year_id,
     * variant. Scope saft:read.
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listSaftGenerations(array $params = []): array
    {
        $res = $this->http->get('/saft/generations', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * A SAF-T generation with its issue counts. Scope saft:read.
     * @return array<string,mixed>
     */
    public function getSaftGeneration(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/saft/generations/' . rawurlencode($id))['data'];
        return $data;
    }

    /**
     * Remediable findings of a SAF-T generation. Scope saft:read.
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listSaftGenerationFindings(string $id, array $params = []): array
    {
        $res = $this->http->get('/saft/generations/' . rawurlencode($id) . '/findings', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * Queue a SAF-T D406 generation on the worker (202). 409 when the
     * readiness pre-flight blocks or one is already running; 503 when no
     * worker is reachable. The API REQUIRES an Idempotency-Key — one is
     * generated when omitted; pass your own to make retries safe.
     * Scope saft:write.
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function createSaftGeneration(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/saft/generations', $input, $idempotencyKey ?? self::newIdempotencyKey())['data'];
        return $data;
    }

    /**
     * Signed download URL for a generated SAF-T file. 409 while not ready,
     * 410 past retention. Heavy rate tier. Scope saft:export.
     * @return array<string,mixed> url, expires_at, filename, …
     */
    public function getSaftGenerationDownload(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/saft/generations/' . rawurlencode($id) . '/download')['data'];
        return $data;
    }

    // ── Exports (BC-0196) ────────────────────────────────────────────
    /**
     * Export jobs, newest first. Scope exports:read.
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listExports(array $params = []): array
    {
        $res = $this->http->get('/exports', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * An export job. Scope exports:read.
     * @return array<string,mixed>
     */
    public function getExport(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/exports/' . rawurlencode($id))['data'];
        return $data;
    }

    /**
     * Start a csv/xlsx export (202, runs in the background; poll getExport or
     * subscribe to export.completed). Needs the exported entity's read scope
     * too. The API REQUIRES an Idempotency-Key — generated when omitted.
     * Scope exports:write.
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function createExport(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/exports', $input, $idempotencyKey ?? self::newIdempotencyKey())['data'];
        return $data;
    }

    /**
     * Download a finished export. Stored files answer JSON and this returns
     * `['url' => …, 'expires_at' => …, 'filename' => …, 'sha256' => …]` — fetch
     * the signed URL WITHOUT the bearer. Legacy rows (and deployments without
     * object storage) answer the raw file; this then returns
     * `['bytes' => <string>]`. 409 while running or failed, 410 once expired
     * (both throw BrivioException). Heavy rate tier. Scope exports:read.
     * @return array<string,mixed>
     */
    public function downloadExport(string $id): array
    {
        /** @var string $body */
        $body = $this->http->get('/exports/' . rawurlencode($id) . '/download', [], true)['data'];
        $json = json_decode($body, true);
        if (is_array($json) && isset($json['data']) && is_array($json['data']) && isset($json['data']['url'])) {
            /** @var array<string,mixed> $data */
            $data = $json['data'];
            return $data;
        }
        return ['bytes' => $body];
    }

    // ── Imports (BC-0196) ────────────────────────────────────────────
    /**
     * Import jobs, newest first. Scope imports:read.
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listImports(array $params = []): array
    {
        $res = $this->http->get('/imports', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * An import job. Scope imports:read.
     * @return array<string,mixed>
     */
    public function getImport(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/imports/' . rawurlencode($id))['data'];
        return $data;
    }

    /**
     * Row-level errors of an import job, paginated. Scope imports:read.
     * @param array<string, scalar|null> $params page, perPage
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listImportErrors(string $id, array $params = []): array
    {
        $res = $this->http->get('/imports/' . rawurlencode($id) . '/errors', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * Stage rows for import (202, status staged or failed): computes the
     * dedupe preview; nothing is written until commitImport. The API REQUIRES
     * an Idempotency-Key — generated when omitted. Scope imports:write.
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function createImport(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/imports', $input, $idempotencyKey ?? self::newIdempotencyKey())['data'];
        return $data;
    }

    /**
     * Cancel a staged import and drop its rows. 409 when not staged.
     * Scope imports:write.
     * @return array<string,mixed>
     */
    public function cancelImport(string $id, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/imports/' . rawurlencode($id) . '/cancel', [], $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Commit a staged import (202 with the job, completed or failed). 409 when
     * not staged; 422 for an entity with no committer. The API REQUIRES an
     * Idempotency-Key — generated when omitted. Scope imports:commit.
     * @return array<string,mixed>
     */
    public function commitImport(string $id, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/imports/' . rawurlencode($id) . '/commit', [], $idempotencyKey ?? self::newIdempotencyKey())['data'];
        return $data;
    }

    // ── Marketing A/B tests (BC-0196) ────────────────────────────────
    /**
     * A/B arms of a campaign with counters and rates. Winner state lives on
     * the campaign (ab_winner_variant_id, ab_decided_at). Scope marketing:read.
     * @return list<array<string,mixed>>
     */
    public function listCampaignVariants(string $campaignId): array
    {
        /** @var list<array<string,mixed>> $data */
        $data = $this->http->get('/marketing/campaigns/' . rawurlencode($campaignId) . '/variants')['data'] ?? [];
        return $data;
    }

    /**
     * Configure (replace) the A/B subject test of a DRAFT campaign. 409 when
     * the campaign is not a draft. Scope marketing_ab:write.
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function configureCampaignAbTest(string $campaignId, array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->put('/marketing/campaigns/' . rawurlencode($campaignId) . '/ab-test', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Remove the A/B test from a DRAFT campaign. 409 when not a draft.
     * Scope marketing_ab:write.
     * @return array<string,mixed>
     */
    public function clearCampaignAbTest(string $campaignId, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->delete('/marketing/campaigns/' . rawurlencode($campaignId) . '/ab-test', $idempotencyKey)['data'] ?? [];
        return $data;
    }

    // ── Prospecting saved searches (BC-0196) ─────────────────────────
    /**
     * Saved prospecting searches, newest first. Scope prospecting:read.
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listProspectingSavedSearches(array $params = []): array
    {
        $res = $this->http->get('/prospecting/saved-searches', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * A saved prospecting search. Scope prospecting:read.
     * @return array<string,mixed>
     */
    public function getProspectingSavedSearch(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/prospecting/saved-searches/' . rawurlencode($id))['data'];
        return $data;
    }

    /**
     * Save a prospecting search (201). Counts toward the plan limit (402
     * PLAN_LIMIT_EXCEEDED); attributed to the user who created the API key
     * (403 when there is none). Scope prospecting:write.
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function createProspectingSavedSearch(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/prospecting/saved-searches', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Update name, filters, sort, columns or alert flags; last_* fields are
     * read-only. Scope prospecting:write.
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function updateProspectingSavedSearch(string $id, array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->patch('/prospecting/saved-searches/' . rawurlencode($id), $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Delete a saved prospecting search. Scope prospecting:write.
     */
    public function deleteProspectingSavedSearch(string $id, ?string $idempotencyKey = null): void
    {
        $this->http->delete('/prospecting/saved-searches/' . rawurlencode($id), $idempotencyKey);
    }

    // ── Fleet (BC-0196 batch B) ──────────────────────────────────────
    /**
     * Fleet vehicles, by plate. Scope fleet:read.
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listFleetVehicles(array $params = []): array
    {
        $res = $this->http->get('/fleet/vehicles', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * Add a fleet vehicle (201); the registry vehicle is found (VIN, then
     * plate) or created. Scope fleet:write.
     * @param array<string,mixed> $input snake_case: plate (required), vin, make, model, year, category, fuel_type, ...
     * @return array<string,mixed>
     */
    public function createFleetVehicle(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/fleet/vehicles', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * A fleet vehicle. Scope fleet:read.
     * @return array<string,mixed>
     */
    public function getFleetVehicle(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/fleet/vehicles/' . rawurlencode($id))['data'];
        return $data;
    }

    /**
     * Update a fleet vehicle; absent fields are left unchanged. Scope fleet:write.
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function updateFleetVehicle(string $id, array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->patch('/fleet/vehicles/' . rawurlencode($id), $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Delete a fleet vehicle — its odometer readings and service logs are
     * deleted with it. Scope fleet:write.
     */
    public function deleteFleetVehicle(string $id, ?string $idempotencyKey = null): void
    {
        $this->http->delete('/fleet/vehicles/' . rawurlencode($id), $idempotencyKey);
    }

    /**
     * A vehicle's odometer readings, newest first. Scope fleet:read.
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listFleetOdometerReadings(string $vehicleId, array $params = []): array
    {
        $res = $this->http->get('/fleet/vehicles/' . rawurlencode($vehicleId) . '/odometer-readings', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * Record an odometer reading (201); the vehicle's current odometer moves
     * to it. Scope fleet:write.
     * @param array<string,mixed> $input { reading_km, reading_date (YYYY-MM-DD), notes? }
     * @return array<string,mixed>
     */
    public function createFleetOdometerReading(string $vehicleId, array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/fleet/vehicles/' . rawurlencode($vehicleId) . '/odometer-readings', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * A vehicle's service / repair / fuel log, newest first. Scope fleet:read.
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listFleetServiceLogs(string $vehicleId, array $params = []): array
    {
        $res = $this->http->get('/fleet/vehicles/' . rawurlencode($vehicleId) . '/service-logs', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * Add a service log entry (201). Scope fleet:write.
     * @param array<string,mixed> $input { service_date (YYYY-MM-DD), type?, odometer_km?, cost_amount?, ... }
     * @return array<string,mixed>
     */
    public function createFleetServiceLog(string $vehicleId, array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/fleet/vehicles/' . rawurlencode($vehicleId) . '/service-logs', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Drivers as id, name and status only, by name. Scope fleet_drivers:read.
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listFleetDrivers(array $params = []): array
    {
        $res = $this->http->get('/fleet/drivers', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    // ── Vehicle registry + trip sheets (BC-0196 batch B1) ────────────
    /**
     * Registry vehicles, by plate (identity only, no owner data). Scope fleet:read.
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listVehicles(array $params = []): array
    {
        $res = $this->http->get('/vehicles', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * A registry vehicle (a fleet vehicle's registry_vehicle_id). Scope fleet:read.
     * @return array<string,mixed>
     */
    public function getVehicle(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/vehicles/' . rawurlencode($id))['data'];
        return $data;
    }

    /**
     * A registry vehicle's trip sheets (foi de parcurs), newest first.
     * driver_name only with fleet_drivers:read. Scope fleet:read.
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listVehicleTripSheets(string $vehicleId, array $params = []): array
    {
        $res = $this->http->get('/vehicles/' . rawurlencode($vehicleId) . '/trip-sheets', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * Record a trip sheet (201). Scope fleet:write.
     * @param array<string,mixed> $input { performed_at (ISO 8601), driver_name?, route_from?, route_to?, purpose?, odometer_start_km?, odometer_end_km?, fuel_issued_litres?, notes? }
     * @return array<string,mixed>
     */
    public function createVehicleTripSheet(string $vehicleId, array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/vehicles/' . rawurlencode($vehicleId) . '/trip-sheets', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * A trip sheet. driver_name only with fleet_drivers:read. Scope fleet:read.
     * @return array<string,mixed>
     */
    public function getTripSheet(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/trip-sheets/' . rawurlencode($id))['data'];
        return $data;
    }

    // ── Spaces + space transfers (BC-0266, ADR-0214 D8) ──────────────
    /**
     * Spaces of the organization, by name. Scope spaces:read.
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listSpaces(array $params = []): array
    {
        $res = $this->http->get('/spaces', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * A space with its members (kind, id, label). Scope spaces:read.
     * @return array<string,mixed>
     */
    public function getSpace(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/spaces/' . rawurlencode($id))['data'];
        return $data;
    }

    /**
     * Create a space (201). Scope spaces:write.
     * @param array<string,mixed> $input { name, slug?, description? }
     * @return array<string,mixed>
     */
    public function createSpace(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/spaces', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Update a space's name, slug or description. Scope spaces:write.
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function updateSpace(string $id, array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->patch('/spaces/' . rawurlencode($id), $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Delete a space; its members are released, never deleted. Scope spaces:write.
     * @return array<string,mixed>
     */
    public function deleteSpace(string $id, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->delete('/spaces/' . rawurlencode($id), $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Add a resource of this organization to a space. Scope spaces:write.
     * @param string $kind website|custom_domain|dns_zone|hosted_site|registered_domain|email_domain|oauth_client|webhook
     * @return array<string,mixed>
     */
    public function attachSpaceMember(string $spaceId, string $kind, string $resourceId, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/spaces/' . rawurlencode($spaceId) . '/members', ['kind' => $kind, 'resource_id' => $resourceId], $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Remove a resource from a space (the resource is untouched). Scope spaces:write.
     * @return array<string,mixed>
     */
    public function detachSpaceMember(string $spaceId, string $kind, string $resourceId, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->delete('/spaces/' . rawurlencode($spaceId) . '/members', $idempotencyKey, ['kind' => $kind, 'resource_id' => $resourceId])['data'];
        return $data;
    }

    /**
     * Transfers this organization sent or received (read-only: they are created and
     * accepted in the app, with a second factor). Scope space_transfers:read.
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listSpaceTransfers(array $params = []): array
    {
        $res = $this->http->get('/space-transfers', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * A space transfer; 404 unless this organization is a party. Scope space_transfers:read.
     * @return array<string,mixed>
     */
    public function getSpaceTransfer(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/space-transfers/' . rawurlencode($id))['data'];
        return $data;
    }

    /**
     * ADR-0260: the paired display — device, org branding, config, available
     * screens. Scope display:read.
     * @return array<string,mixed>
     */
    public function getDisplayMe(): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/display/me')['data'];
        return $data;
    }

    /**
        * ADR-0260: read-only data for one screen (pulse, feed, present, kitchen,
        * orders, bookings, gate, promo, queue, menu, slides). Money is integer minor units. Scope display:read.
     * @return array<string,mixed>
     */
    public function getDisplaySnapshot(string $screen): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/display/snapshot', ['screen' => $screen])['data'];
        return $data;
    }

    /**
     * ADR-0260: ask the display assistant about the pulse data.
     * Scope display:assistant.
     * @param array<string,mixed> $input { text?, audio?, mime?, locale? }
     * @return array<string,mixed>
     */
    public function askDisplayAssistant(array $input): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/display/assistant', $input)['data'];
        return $data;
    }

    /**
     * ADR-0260: server re-check of a locked display's PIN (device tokens only,
     * 5 attempts/min, audited without the PIN). Scope display:read.
     * @return array{ok: bool, retryAfterSec: int|null}
     */
    public function unlockDisplay(string $pin): array
    {
        /** @var array{ok: bool, retryAfterSec: int|null} $data */
        $data = $this->http->post('/display/unlock', ['pin' => $pin])['data'];
        return $data;
    }

    /**
     * Delivery routes, newest first. Filters: status, date_from, date_to
     * (YYYY-MM-DD, inclusive), page, per_page. Scope delivery:read.
     * @param array<string,scalar> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listDeliveryRoutes(array $params = []): array
    {
        $res = $this->http->get('/delivery-routes', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * Create a delivery route (DRAFT). Scope delivery:write.
     * @param array<string,mixed> $input name?, route_date?, start_location_id?, start_address?, driver_id?, vehicle_id?, …
     * @return array<string,mixed>
     */
    public function createDeliveryRoute(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/delivery-routes', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * A delivery route. Scope delivery:read.
     * @return array<string,mixed>
     */
    public function getDeliveryRoute(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/delivery-routes/' . rawurlencode($id))['data'];
        return $data;
    }

    /**
     * Update a delivery route (name, route_date, notes, capacity, driver_id,
     * vehicle_id, status DRAFT|IN_PROGRESS|COMPLETED|CANCELLED). Scope delivery:write.
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function updateDeliveryRoute(string $id, array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->patch('/delivery-routes/' . rawurlencode($id), $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Cancel a delivery route (there is no deletion). Scope delivery:write.
     * @return array<string,mixed>
     */
    public function cancelDeliveryRoute(string $id, ?string $idempotencyKey = null): array
    {
        return $this->updateDeliveryRoute($id, ['status' => 'CANCELLED'], $idempotencyKey);
    }

    /**
     * Stops of a delivery route, in visit order. Scope delivery:read.
     * @return list<array<string,mixed>>
     */
    public function listDeliveryRouteStops(string $id): array
    {
        /** @var list<array<string,mixed>> $data */
        $data = $this->http->get('/delivery-routes/' . rawurlencode($id) . '/stops')['data'] ?? [];
        return $data;
    }

    /**
     * Add a stop (route must be DRAFT or PLANNED; 409 otherwise). `recipient_phone`
     * and `private_notes` are write-only. Scope delivery:write.
     * @param array<string,mixed> $input address (street or city required), recipient_name?, invoice_id?, …
     * @return array<string,mixed>
     */
    public function addDeliveryRouteStop(string $id, array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/delivery-routes/' . rawurlencode($id) . '/stops', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Remove a stop from a delivery route. Scope delivery:write.
     */
    public function removeDeliveryRouteStop(string $id, string $stopId, ?string $idempotencyKey = null): void
    {
        $this->http->delete('/delivery-routes/' . rawurlencode($id) . '/stops/' . rawurlencode($stopId), $idempotencyKey);
    }

    /**
     * Optimize the stop order (synchronous OSRM) and mark the route PLANNED.
     * REQUIRED Idempotency-Key (generated when null); heavy rate tier. Scope delivery:write.
     * @return array<string,mixed> route_id, status, stop_order, total_distance_km, warnings, …
     */
    public function optimizeDeliveryRoute(string $id, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post(
            '/delivery-routes/' . rawurlencode($id) . '/optimize',
            [],
            $idempotencyKey ?? self::newIdempotencyKey(),
        )['data'];
        return $data;
    }

    /**
     * E-mail the route sheet to the assigned driver and mark the route DISPATCHED.
     * REQUIRED Idempotency-Key (generated when null — pass your own so a retry cannot
     * mail twice). Scope delivery:dispatch (not implied by delivery:write).
     * @return array<string,mixed> route_id, status, dispatched_at, stop_count, sent_to_driver
     */
    public function dispatchDeliveryRoute(string $id, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post(
            '/delivery-routes/' . rawurlencode($id) . '/dispatch',
            [],
            $idempotencyKey ?? self::newIdempotencyKey(),
        )['data'];
        return $data;
    }

    /**
     * Own fleet vs courier for a route (F-VS): fleet cost (`marginal` running costs,
     * `full` adds depreciation and insurance), the courier price of the same parcels,
     * verdict FLEET|COURIER|TIE|UNKNOWN, saving, hybrid stops, break-even stop count
     * and CO₂. Amounts are integer minor units (bani); `breakdown` is always null over
     * the API. Heavy rate tier. Scope delivery:read.
     * @param 'marginal'|'full' $mode
     * @return array<string,mixed> currency, mode, fleetMinor, courierProjectedMinor, savingMinor, verdict, hybridStops, breakEvenStops, co2Grams, …
     * @throws \InvalidArgumentException when $mode is not marginal|full (no request is sent)
     */
    public function getDeliveryRouteCostComparison(string $id, string $mode = 'marginal'): array
    {
        if ($mode !== 'marginal' && $mode !== 'full') {
            throw new \InvalidArgumentException('mode must be marginal or full');
        }
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/delivery-routes/' . rawurlencode($id) . '/cost-comparison', ['mode' => $mode])['data'];
        return $data;
    }

    /**
     * Own fleet vs courier over the last 30 or 90 days (F-VS), from the cost
     * snapshots stored when routes were completed: totals, saving, verdict counts,
     * hybrid saving, CO₂, break-even stop count, estimated share and the routes that
     * cost more than a courier. Amounts are integer minor units. Scope delivery:read.
     * @param 30|90 $window
     * @param 'marginal'|'full' $mode
     * @return array<string,mixed> windowDays, mode, currency, routes, comparableRoutes, fleetMinor, courierMinor, savingMinor, verdicts, hybridSavingMinor, co2Grams, avgStops, breakEvenStops, estimatedPercent, losing
     * @throws \InvalidArgumentException when $window is not 30|90 or $mode is not marginal|full (no request is sent)
     */
    public function getDeliveryRouteCostAnalytics(int $window = 30, string $mode = 'marginal'): array
    {
        if ($window !== 30 && $window !== 90) {
            throw new \InvalidArgumentException('window must be 30 or 90');
        }
        if ($mode !== 'marginal' && $mode !== 'full') {
            throw new \InvalidArgumentException('mode must be marginal or full');
        }
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/delivery-routes/cost-analytics', ['window' => (string) $window, 'mode' => $mode])['data'];
        return $data;
    }

    // ── Steluțe rewards programme (ADR-0268, RW-0702) ─────────────────────
    // Mirrors the TS SDK `client.rewards`. Steluțe are loyalty units, not money.

    /**
     * Your organization's own programme, created on first use. Scope rewards:read.
     * @return array<string,mixed>
     */
    public function getRewardsProgram(): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/rewards/program')['data'];
        return $data;
    }

    /**
     * Active catalogue of the programme. Scope rewards:read.
     * @return list<array<string,mixed>>
     */
    public function listRewardsCatalog(): array
    {
        /** @var list<array<string,mixed>> $data */
        $data = $this->http->get('/rewards/catalog')['data'] ?? [];
        return $data;
    }

    /**
     * Create or fetch a member by externalRef or customerId. Scope rewards:write.
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function upsertRewardsMember(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/rewards/members', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Balance, level, streak and expiry of one member. Scope rewards:read.
     * @return array<string,mixed>
     */
    public function getRewardsMember(string $ref): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/rewards/members/' . rawurlencode($ref))['data'];
        return $data;
    }

    /**
     * Newest ledger movements of a member (limit ≤ 200). Scope rewards:read.
     * @param array<string,mixed> $params
     * @return list<array<string,mixed>>
     */
    public function listRewardsMemberHistory(string $ref, array $params = []): array
    {
        /** @var list<array<string,mixed>> $data */
        $data = $this->http->get('/rewards/members/' . rawurlencode($ref) . '/history', $params)['data'] ?? [];
        return $data;
    }

    /**
     * Redeem a catalogue item for a member. 409 insufficient_units / in_debt. Scope rewards:write.
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function createRewardsRedemption(string $ref, array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/rewards/members/' . rawurlencode($ref) . '/redemptions', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Record a business event, processed inline. Pass a stable idempotency key
     * (e.g. "order-123-paid") so a retry awards nothing twice. Scope rewards:write.
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function recordRewardsEvent(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/rewards/events', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Reverse what a refunded/voided business object earned. Scope rewards:write.
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function createRewardsClawback(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/rewards/clawbacks', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Create a widget signing key; the secret is returned ONCE. Scope rewards:write.
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function createRewardsWidgetKey(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/rewards/widget-keys', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Revoke a widget key; its live sessions stop at once. Scope rewards:write.
     * @return array<string,mixed>
     */
    public function revokeRewardsWidgetKey(string $kid): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->delete('/rewards/widget-keys/' . rawurlencode($kid))['data'] ?? [];
        return $data;
    }

    /**
     * PUBLIC: exchange a host-signed token for a 15-minute read-only widget session.
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function createRewardsWidgetSession(array $input): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/rewards/widget/session', $input)['data'];
        return $data;
    }

    /**
     * PUBLIC: what the widget renders. Construct a client whose api key is the
     * widget SESSION token to call this.
     * @return array<string,mixed>
     */
    public function getRewardsWidgetMe(): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/rewards/widget/me')['data'];
        return $data;
    }

    // ── Courier hub: shipping points + shipments (ADR-0273) ─────────────────
    /**
     * Search lockers, partner shops and courier offices. One anchor required:
     * near ("lat,lng"), city, postal_code or q. Filters: courier, type (comma
     * lists), cod, card, open_24_7, weight_kg, radius_km, limit. Scope shipping:read.
     * @param array<string,mixed> $params
     * @return list<array<string,mixed>>
     */
    public function searchShippingPoints(array $params): array
    {
        foreach ($params as $k => $v) {
            if (is_array($v)) {
                $params[$k] = implode(',', $v);
            } elseif (is_bool($v)) {
                $params[$k] = $v ? 'true' : 'false';
            }
        }
        /** @var list<array<string,mixed>> $data */
        $data = $this->http->get('/shipping-points', $params)['data'] ?? [];
        return $data;
    }

    /**
     * Shipments, newest first (status, page, per_page). Scope shipping:read.
     * @param array<string,mixed> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listShipments(array $params = []): array
    {
        $res = $this->http->get('/shipments', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * Price a parcel at every configured courier and compare (PARITY API-10).
     * Creates nothing. Scope shipping:read.
     * @param array<string,mixed> $input recipient, parcel, cod_amount?, pickup_point_id?, couriers?
     * @return array<string,mixed> quotes, couriers
     */
    public function quoteShipment(array $input): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/shipments/quote', $input)['data'];
        return $data;
    }

    /**
     * Buy an AWB (a real courier cost). REQUIRED Idempotency-Key (generated when
     * null — pass your own so a retry cannot buy two). Scope shipping:write.
     * @param array<string,mixed> $input courier, reference, recipient, parcel, …
     * @return array<string,mixed>
     */
    public function createShipment(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/shipments', $input, $idempotencyKey ?? self::newIdempotencyKey())['data'];
        return $data;
    }

    /**
     * A shipment. Scope shipping:read.
     * @return array<string,mixed>
     */
    public function getShipment(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/shipments/' . rawurlencode($id))['data'];
        return $data;
    }

    /**
     * The AWB label bytes (PDF; ZPL with format "zpl"). Scope shipping:read.
     */
    public function getShipmentLabel(string $id, ?string $format = null): string
    {
        /** @var string $bytes */
        $bytes = $this->http->get('/shipments/' . rawurlencode($id) . '/label', ['format' => $format], true)['data'];
        return $bytes;
    }

    /**
     * Refresh tracking from the courier. Scope shipping:read.
     * @return array<string,mixed>
     */
    public function trackShipment(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/shipments/' . rawurlencode($id) . '/tracking')['data'];
        return $data;
    }

    /**
     * Cancel the AWB at the courier. Scope shipping:write.
     * @return array<string,mixed>
     */
    public function cancelShipment(string $id, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/shipments/' . rawurlencode($id) . '/cancel', [], $idempotencyKey)['data'];
        return $data;
    }

    // ── Court cases (BC-0196 batch B) ─────────────────────────────────────────
    /**
     * The organization's own court cases, newest first. Optional `status`
     * (OPEN|SUSPENDED|WON|LOST|SETTLED|CLOSED), `page`, `perPage`. Scope legal:read.
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listCourtCases(array $params = []): array
    {
        $res = $this->http->get('/legal/court-cases', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * Record a court case (201). Linked contact/invoice/contract must belong to
     * the organization (422 otherwise). Scope legal:write.
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function createCourtCase(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/legal/court-cases', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * A court case. Scope legal:read.
     * @return array<string,mixed>
     */
    public function getCourtCase(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/legal/court-cases/' . rawurlencode($id))['data'];
        return $data;
    }

    /**
     * Partial update; a terminal status sets closed_at when absent, OPEN clears it.
     * Scope legal:write.
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function updateCourtCase(string $id, array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->patch('/legal/court-cases/' . rawurlencode($id), $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Delete a court case with its hearings and notes. Scope legal:write.
     */
    public function deleteCourtCase(string $id, ?string $idempotencyKey = null): void
    {
        $this->http->delete('/legal/court-cases/' . rawurlencode($id), $idempotencyKey);
    }

    /**
     * Hearings (termene) of a court case, by date. Scope legal:read.
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listCourtCaseHearings(string $id, array $params = []): array
    {
        $res = $this->http->get('/legal/court-cases/' . rawurlencode($id) . '/hearings', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * Add a hearing (201): `{ hearing_date, time?, room?, panel?, solution?,
     * solution_summary?, notes? }`. A future date becomes next_hearing_at.
     * Scope legal:write.
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function createCourtCaseHearing(string $id, array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/legal/court-cases/' . rawurlencode($id) . '/hearings', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Notes of a court case, newest first. Scope legal:read.
     * @param array<string, scalar|null> $params
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listCourtCaseNotes(string $id, array $params = []): array
    {
        $res = $this->http->get('/legal/court-cases/' . rawurlencode($id) . '/notes', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * Add a note (201): `{ content, is_internal? }`. Scope legal:write.
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function createCourtCaseNote(string $id, array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/legal/court-cases/' . rawurlencode($id) . '/notes', $input, $idempotencyKey)['data'];
        return $data;
    }

    // ── Customs import declarations — DVI (BC-0196 batch C) ────────────────

    /**
     * Customs import declarations (DVI), newest declaration date first. Scope purchases:read.
     * @param array<string,mixed> $params page, perPage, status (DRAFT|CONFIRMED|CANCELLED), from, to (YYYY-MM-DD)
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listImportDeclarations(array $params = []): array
    {
        $res = $this->http->get('/purchases/import-declarations', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * A customs import declaration with its lines and cost allocation. Scope purchases:read.
     * @return array<string,mixed>
     */
    public function getImportDeclaration(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/purchases/import-declarations/' . rawurlencode($id))['data'];
        return $data;
    }

    /**
     * Create a DRAFT DVI (201); customs value, VAT base and VAT computed server-side. Scope purchases:write.
     * @param array<string,mixed> $input snake_case: regime, declaration_date, customs_exchange_rate, vat_rate, lines (required), nir_document_id, customs_duty, ...
     * @return array<string,mixed>
     */
    public function createImportDeclaration(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/purchases/import-declarations', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Confirm a DRAFT DVI (202): posts the customs journal entry (Dr 371/607 = Cr 446;
     * VAT Dr 4426 = Cr 446 or 4427) and raises the stock cost. Irreversible except by
     * storno. 409 not_draft; 422 no_period / no_journal / missing_account. The
     * Idempotency-Key is mandatory — one is generated when omitted. Scope purchases:confirm.
     * @return array<string,mixed>
     */
    public function confirmImportDeclaration(string $id, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post(
            '/purchases/import-declarations/' . rawurlencode($id) . '/confirm',
            [],
            $idempotencyKey ?? self::newIdempotencyKey(),
        )['data'];
        return $data;
    }

    // ── SAF-T D406 import (BC-0196 batch C) ─────────────────────────────────
    // The file never passes through the API: create → PUT the bytes to
    // `upload.url` with every `upload.headers` entry → parse → review → apply.

    /**
     * SAF-T import jobs, newest first. Scope imports:read.
     * @param array<string, scalar|null> $params page, perPage
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listSaftImports(array $params = []): array
    {
        $res = $this->http->get('/saft/imports', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * A SAF-T import job with its preview and, once applied, its result. Scope imports:read.
     * @return array<string,mixed>
     */
    public function getSaftImport(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/saft/imports/' . rawurlencode($id))['data'];
        return $data;
    }

    /**
     * Create a SAF-T import job and get a signed PUT URL (201). 503 when storage
     * is not configured. Scope saft_import:write.
     * @param array<string,mixed> $input { filename, byte_size (≤ 52428800), sha256 (hex) }
     * @return array<string,mixed> job fields + upload { url, method, headers, expires_at }
     */
    public function createSaftImport(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/saft/imports', $input, $idempotencyKey ?? self::newIdempotencyKey())['data'];
        return $data;
    }

    /**
     * Verify the uploaded bytes (size + sha256) and store the preview (202).
     * 409 while the upload is missing. Scope saft_import:write.
     * @return array<string,mixed>
     */
    public function parseSaftImport(string $id, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/saft/imports/' . rawurlencode($id) . '/parse', [], $idempotencyKey ?? self::newIdempotencyKey())['data'];
        return $data;
    }

    /**
     * Apply a previewed import (202): contacts, products, account links, stock and
     * opening balances. 409 when already applied. Scope saft_import:write.
     * @param array<string,mixed> $input sections?, migration_date?, account_mapping?
     * @return array<string,mixed>
     */
    public function applySaftImport(string $id, array $input = [], ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/saft/imports/' . rawurlencode($id) . '/apply', $input, $idempotencyKey ?? self::newIdempotencyKey())['data'];
        return $data;
    }

    // ── Gate & yard (BC-0196 batch C) ───────────────────────────────────────
    // The driver's identity-document number and phone are deliberately not exposed.

    /**
     * Gate visits, newest first. Scope gate:read.
     * @param array<string,mixed> $params status, active (true|false), direction, plate, from, to (ISO), page, perPage
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listGateVisits(array $params = []): array
    {
        $res = $this->http->get('/gate/visits', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * A gate visit with its weighings and timeline. Scope gate:read.
     * @return array<string,mixed>
     */
    public function getGateVisit(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/gate/visits/' . rawurlencode($id))['data'];
        return $data;
    }

    /**
     * Register a vehicle at the gate (201). Scope gate:write.
     * @param array<string,mixed> $input snake_case: vehicle_plate (required), direction, trailer_plate, driver_name, carrier_id, carrier_name, partner_id, location_id, dock_name, purpose, notes, expected_at, appointment_id
     * @return array<string,mixed>
     */
    public function createGateVisit(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/gate/visits', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Move a visit along the state machine; 409 when not allowed. Scope gate:write.
     * @param array<string,mixed> $input { to, note?, dock_name? }
     * @return array<string,mixed>
     */
    public function transitionGateVisit(string $id, array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/gate/visits/' . rawurlencode($id) . '/transitions', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Record a weighbridge reading (201 first, 200 later). Scope gate:write.
     * @param array<string,mixed> $input { phase: IN|OUT, gross_kg?, tare_kg?, scale_name?, operator_name?, notes? }
     * @return array<string,mixed>
     */
    public function addGateVisitWeighing(string $id, array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/gate/visits/' . rawurlencode($id) . '/weighings', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Dock appointments by slot start. Scope gate:read.
     * @param array<string,mixed> $params status, dock_name, from, to, page, perPage
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listDockAppointments(array $params = []): array
    {
        $res = $this->http->get('/gate/dock-appointments', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * Book a dock slot (201); 409 on overlap. Scope gate:write.
     * @param array<string,mixed> $input slot_start, slot_end (required), direction, location_id, dock_name, vehicle_plate, driver_name, carrier_id, carrier_name, partner_id, reference, notes
     * @return array<string,mixed>
     */
    public function createDockAppointment(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/gate/dock-appointments', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * A dock appointment. Scope gate:read.
     * @return array<string,mixed>
     */
    public function getDockAppointment(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/gate/dock-appointments/' . rawurlencode($id))['data'];
        return $data;
    }

    /**
     * Update a dock appointment (partial); status CANCELLED cancels it. Scope gate:write.
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function updateDockAppointment(string $id, array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->patch('/gate/dock-appointments/' . rawurlencode($id), $input, $idempotencyKey)['data'];
        return $data;
    }

    // ── Costs Management (PF-0310, ADR-0296) ────────────────────────────────
    // Amounts are integer minor units of their currency. Lists are capped (500)
    // and not paginated. SaaS cost subscriptions live under /costs/subscriptions,
    // not /subscriptions (Stripe merchant billing).

    /**
     * Month overview: spend and forecast per currency, top services, SaaS subscriptions. Scope costs:read.
     * @param array<string,mixed> $params period (YYYY-MM)
     * @return array<string,mixed>
     */
    public function getCostsOverview(array $params = []): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/costs', $params)['data'];
        return $data;
    }

    /**
     * Usage of a month grouped by provider and service / sub-account / region. Scope costs:read.
     * @param array<string,mixed> $params period (YYYY-MM), group_by (service|sub_account|region)
     * @return list<array<string,mixed>>
     */
    public function listCostUsage(array $params = []): array
    {
        /** @var list<array<string,mixed>> $data */
        $data = $this->http->get('/costs/usage', $params)['data'] ?? [];
        return $data;
    }

    /**
     * Cost data sources; credentials are never returned. Scope costs:read.
     * @return list<array<string,mixed>>
     */
    public function listCostConnectors(): array
    {
        /** @var list<array<string,mixed>> $data */
        $data = $this->http->get('/costs/connectors')['data'] ?? [];
        return $data;
    }

    /**
     * Cost budgets with the spend of the month and thresholds crossed. Scope costs:read.
     * @param array<string,mixed> $params period (YYYY-MM)
     * @return list<array<string,mixed>>
     */
    public function listCostBudgets(array $params = []): array
    {
        /** @var list<array<string,mixed>> $data */
        $data = $this->http->get('/costs/budgets', $params)['data'] ?? [];
        return $data;
    }

    /**
     * Create a monthly cost budget (201, returns { id }). Scope costs:write.
     * @param array<string,mixed> $input name, currency, limit_minor (required), company_id, scope, scope_value, soft_threshold_pcts, enabled
     * @return array<string,mixed>
     */
    public function createCostBudget(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/costs/budgets', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Delete a cost budget (returns { id, deleted }). Scope costs:write.
     * @return array<string,mixed>
     */
    public function deleteCostBudget(string $id, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->delete('/costs/budgets/' . rawurlencode($id), $idempotencyKey)['data'] ?? [];
        return $data;
    }

    /**
     * SaaS subscriptions tracked by hand, active first. Scope costs:read.
     * @return list<array<string,mixed>>
     */
    public function listCostSubscriptions(): array
    {
        /** @var list<array<string,mixed>> $data */
        $data = $this->http->get('/costs/subscriptions')['data'] ?? [];
        return $data;
    }

    /**
     * Record a SaaS subscription (201, returns { id }). Scope costs:write.
     * @param array<string,mixed> $input company_id, vendor, amount_minor, currency (required), plan, cadence, next_renewal, notes
     * @return array<string,mixed>
     */
    public function createCostSubscription(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/costs/subscriptions', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Pause or resume tracking a SaaS subscription (returns { id, active }). Scope costs:write.
     * @return array<string,mixed>
     */
    public function updateCostSubscription(string $id, bool $active, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->patch('/costs/subscriptions/' . rawurlencode($id), ['active' => $active], $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Delete a SaaS subscription (returns { id, deleted }). Scope costs:write.
     * @return array<string,mixed>
     */
    public function deleteCostSubscription(string $id, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->delete('/costs/subscriptions/' . rawurlencode($id), $idempotencyKey)['data'] ?? [];
        return $data;
    }

    /**
     * Usage vs provider invoice per connector, month and currency. Scope costs:read.
     * @param array<string,mixed> $params period (YYYY-MM); omit for all months, latest first
     * @return list<array<string,mixed>>
     */
    public function listCostReconciliations(array $params = []): array
    {
        /** @var list<array<string,mixed>> $data */
        $data = $this->http->get('/costs/reconciliations', $params)['data'] ?? [];
        return $data;
    }

    // ── Custom hostnames (PF-0311, ADR-0198) ────────────────────────────────
    // Claim a hostname in the API key's environment, publish the records from
    // getCustomHostnameDnsInstructions(), then verify until it is active.
    // Deleting releases the Cloudflare hostname first (502 + delete_failed
    // when Cloudflare refuses; retry). Not paginated.

    /**
     * The organization's custom hostnames. Scope sites:read.
     * @return list<array<string,mixed>>
     */
    public function listCustomHostnames(): array
    {
        /** @var list<array<string,mixed>> $data */
        $data = $this->http->get('/custom-hostnames')['data'] ?? [];
        return $data;
    }

    /**
     * One custom hostname. Scope sites:read.
     * @return array<string,mixed>
     */
    public function getCustomHostname(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/custom-hostnames/' . rawurlencode($id))['data'];
        return $data;
    }

    /**
     * Claim a custom hostname (201 created / 200 already this org's; returns { id, domain, created }).
     * 409 when another organization or account holds it. Scope sites:write.
     * @param array<string,mixed> $input domain (required), target (store|site|tenant), site_id
     * @return array<string,mixed>
     */
    public function createCustomHostname(array $input, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/custom-hostnames', $input, $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Re-check TXT ownership, CNAME and the Cloudflare hostname (returns { status, verified, points_to_us, ... }). Scope sites:write.
     * @return array<string,mixed>
     */
    public function verifyCustomHostname(string $id, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/custom-hostnames/' . rawurlencode($id) . '/verify', [], $idempotencyKey)['data'];
        return $data;
    }

    /**
     * The DNS records to publish: txt, cname and Cloudflare dcv records. Scope sites:read.
     * @return array<string,mixed>
     */
    public function getCustomHostnameDnsInstructions(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/custom-hostnames/' . rawurlencode($id) . '/dns-instructions')['data'];
        return $data;
    }

    /**
     * Release and delete a custom hostname (returns { id, deleted }). Scope sites:write.
     * @return array<string,mixed>
     */
    public function deleteCustomHostname(string $id, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->delete('/custom-hostnames/' . rawurlencode($id), $idempotencyKey)['data'] ?? [];
        return $data;
    }

    // ── Accountant access grants (BC-0196 batch C) ──────────────────────────
    // List, request and revoke only. There is deliberately no approve method:
    // approval is the client admin's decision in the app.

    /**
     * Grants where the key's organization is either side. Scope grants:read.
     * @param array<string,mixed> $params role (cabinet|client), status (pending|active|revoked), page, perPage
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listAccessGrants(array $params = []): array
    {
        $res = $this->http->get('/cabinet/access-grants', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * The cabinet requests access to a linked client (201 new, 200 existing live grant). Scope grants:write.
     * @return array<string,mixed> { id, status, client_org_id, created }
     */
    public function requestAccessGrant(string $clientOrgId, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/cabinet/access-grants', ['client_org_id' => $clientOrgId], $idempotencyKey)['data'];
        return $data;
    }

    /**
     * Revoke a pending or active grant from either side. Scope grants:write.
     * @return array<string,mixed>
     */
    public function revokeAccessGrant(string $id, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/cabinet/access-grants/' . rawurlencode($id) . '/revoke', [], $idempotencyKey)['data'];
        return $data;
    }

    // ── Sales channels & channel orders (ADR-0272) ──────────────────────────
    // Read-only view of the channel sync engine (eMAG, WooCommerce, Trendyol),
    // plus retrying an oversold / unmapped order after restock or mapping.

    /**
     * Connected sales channels with their sync health. Scope orders:read.
     * @return list<array<string,mixed>>
     */
    public function listSalesChannels(): array
    {
        /** @var list<array<string,mixed>> $data */
        $data = $this->http->get('/sales-channels')['data'] ?? [];
        return $data;
    }

    /**
     * One sales channel. Scope orders:read.
     * @return array<string,mixed>
     */
    public function getSalesChannel(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/sales-channels/' . rawurlencode($id))['data'];
        return $data;
    }

    /**
     * Orders imported from channels. Scope orders:read.
     * @param array<string,mixed> $params channel_id, status (new|acknowledged|in_progress|shipped|delivered|cancelled|returned|needs_mapping|oversold), updated_since (ISO 8601), page, perPage
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listChannelOrders(array $params = []): array
    {
        $res = $this->http->get('/channel-orders', $params);
        /** @var list<array<string,mixed>> $data */
        $data = $res['data'] ?? [];
        return ['data' => $data, 'meta' => $res['meta'] ?? []];
    }

    /**
     * One channel order with its lines. Scope orders:read.
     * @return array<string,mixed>
     */
    public function getChannelOrder(string $id): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->get('/channel-orders/' . rawurlencode($id))['data'];
        return $data;
    }

    /**
     * Retry stock reservation for an oversold / unmapped order. Scope orders:write.
     * @return array<string,mixed> { ok, status }
     */
    public function retryChannelOrder(string $id, ?string $idempotencyKey = null): array
    {
        /** @var array<string,mixed> $data */
        $data = $this->http->post('/channel-orders/' . rawurlencode($id) . '/retry', [], $idempotencyKey)['data'];
        return $data;
    }

    /** RFC 4122 v4 UUID for endpoints whose Idempotency-Key is mandatory. */
    private static function newIdempotencyKey(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
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

    /**
     * Verify a Standard Webhooks delivery (headers `webhook-id`,
     * `webhook-timestamp`, `webhook-signature`). `email.received` and every
     * other Brivio event are signed this way: HMAC-SHA256 (base64) over
     * `{id}.{timestamp}.{payload}`, header = space-separated `v1,<b64>` tokens
     * (several during a secret rotation; any match is accepted). Pass the RAW
     * request body, not re-encoded JSON.
     */
    public static function verifyStandardWebhook(
        string $payload,
        string $webhookId,
        string $webhookTimestamp,
        string $webhookSignature,
        string $secret,
        int $toleranceSec = 300,
    ): bool {
        if ($webhookId === '' || $webhookTimestamp === '' || $webhookSignature === '') {
            return false;
        }
        if (preg_match('/^[0-9]+$/', $webhookTimestamp) !== 1) {
            return false;
        }
        $ts = (int) $webhookTimestamp;
        if ($ts <= 0 || abs(time() - $ts) > $toleranceSec) {
            return false;
        }

        // Key = base64 of the part after `whsec_`, only when it round-trips
        // exactly (legacy `whsec_<hex>` secrets are used as raw bytes).
        $body = str_starts_with($secret, 'whsec_') ? substr($secret, 6) : $secret;
        $decoded = base64_decode($body, true);
        $key = ($decoded !== false && $decoded !== '' && base64_encode($decoded) === $body)
            ? $decoded
            : $secret;

        $expected = 'v1,' . base64_encode(hash_hmac('sha256', $webhookId . '.' . $ts . '.' . $payload, $key, true));
        foreach (explode(' ', $webhookSignature) as $token) {
            if ($token !== '' && hash_equals($expected, $token)) {
                return true;
            }
        }
        return false;
    }
}
