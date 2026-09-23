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
     * Mark an invoice as sent (DRAFT → SENT; idempotent).
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
