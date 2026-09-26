<?php

declare(strict_types=1);

namespace Brivio\Tests;

use Brivio\BrivioClient;
use Brivio\BrivioException;
use Brivio\Tests\Support\FakeTransport;
use PHPUnit\Framework\TestCase;

final class BrivioClientTest extends TestCase
{
    public function testSendsBearerAuth(): void
    {
        $t = new FakeTransport(['data' => ['organization' => ['id' => 'o1']], 'error' => null]);
        $client = new BrivioClient('brivio_sk_test_x', 'https://api.example/v1', $t);
        $me = $client->me();

        self::assertSame('Bearer brivio_sk_test_x', $t->headers['Authorization']);
        self::assertSame('https://api.example/v1/me', $t->url);
        self::assertSame('o1', $me['organization']['id']);
    }

    public function testCreateContactPostsBodyAndIdempotencyKey(): void
    {
        $t = new FakeTransport(['data' => ['id' => 'c1', 'name' => 'ACME'], 'error' => null], 201);
        $client = new BrivioClient('k', 'https://api.example/v1', $t);
        $contact = $client->createContact(['name' => 'ACME SRL'], 'idem-1');

        self::assertSame('POST', $t->method);
        self::assertSame('idem-1', $t->headers['Idempotency-Key']);
        self::assertSame('ACME SRL', $t->decodedBody()['name']);
        self::assertSame('c1', $contact['id']);
    }

    public function testListBuildsQueryString(): void
    {
        $t = new FakeTransport(['data' => [], 'error' => null, 'meta' => ['total' => 0]]);
        $client = new BrivioClient('k', 'https://api.example/v1', $t);
        $client->listContacts(['search' => 'srl', 'page' => 2]);

        self::assertStringContainsString('/contacts?', (string) $t->url);
        self::assertStringContainsString('search=srl', (string) $t->url);
        self::assertStringContainsString('page=2', (string) $t->url);
    }

    public function testThrowsOnErrorEnvelope(): void
    {
        $t = new FakeTransport(
            ['data' => null, 'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'bad', 'details' => ['name' => ['Required']]]],
            422,
        );
        $client = new BrivioClient('k', 'https://api.example/v1', $t);

        $this->expectException(BrivioException::class);
        $client->createContact(['name' => '']);
    }

    public function testGetInvoiceUsesPathId(): void
    {
        $t = new FakeTransport(['data' => ['id' => 'i9', 'number' => 9], 'error' => null]);
        $client = new BrivioClient('k', 'https://api.example/v1', $t);
        $inv = $client->getInvoice('i9');

        self::assertSame('GET', $t->method);
        self::assertStringContainsString('/invoices/i9', (string) $t->url);
        self::assertSame('i9', $inv['id']);
    }

    public function testUpdateInvoicePatches(): void
    {
        $t = new FakeTransport(['data' => ['id' => 'i3', 'status' => 'SENT'], 'error' => null]);
        $client = new BrivioClient('k', 'https://api.example/v1', $t);
        $client->updateInvoice('i3', ['status' => 'SENT']);

        self::assertSame('PATCH', $t->method);
        self::assertStringContainsString('/invoices/i3', (string) $t->url);
        self::assertSame('SENT', $t->decodedBody()['status']);
    }

    public function testDeleteInvoiceUsesDeleteVerb(): void
    {
        $t = new FakeTransport(['data' => null, 'error' => null]);
        $client = new BrivioClient('k', 'https://api.example/v1', $t);
        $client->deleteInvoice('i3');

        self::assertSame('DELETE', $t->method);
        self::assertStringContainsString('/invoices/i3', (string) $t->url);
    }

    public function testSubmitInvoiceToAnaf(): void
    {
        $t = new FakeTransport(['data' => ['status' => 'uploaded'], 'error' => null]);
        $client = new BrivioClient('k', 'https://api.example/v1', $t);
        $r = $client->submitInvoiceToANAF('i4', 'idem-anaf');

        self::assertSame('POST', $t->method);
        self::assertStringContainsString('/invoices/i4/submit-efactura', (string) $t->url);
        self::assertSame('idem-anaf', $t->headers['Idempotency-Key']);
        self::assertSame('uploaded', $r['status']);
    }

    public function testChargeCreatesPaymentIntent(): void
    {
        $t = new FakeTransport(['data' => ['payment_intent_id' => 'pi_1', 'status' => 'requires_payment_method'], 'error' => null]);
        $client = new BrivioClient('k', 'https://api.example/v1', $t);
        $r = $client->charge(['amount' => 1000, 'currency' => 'RON', 'order_id' => 'o1'], 'idem-pay');

        self::assertSame('POST', $t->method);
        self::assertStringContainsString('/payments/charge', (string) $t->url);
        self::assertSame('idem-pay', $t->headers['Idempotency-Key']);
        self::assertSame('pi_1', $r['payment_intent_id']);
    }

    // PAY-0047/0048 — the payments spine.
    public function testListPaymentsSendsSpineFilters(): void
    {
        $t = new FakeTransport(['data' => [['id' => 'p1', 'amount_minor' => 35000]], 'error' => null, 'meta' => ['total' => 1]]);
        $client = new BrivioClient('k', 'https://api.example/v1', $t);
        $r = $client->listPayments(['status' => 'paid', 'source_kind' => 'invoice', 'q' => 'pi_123']);

        self::assertSame('GET', $t->method);
        self::assertStringContainsString('/payments?', (string) $t->url);
        self::assertStringNotContainsString('/payments/charge', (string) $t->url);
        self::assertStringContainsString('status=paid', (string) $t->url);
        self::assertStringContainsString('source_kind=invoice', (string) $t->url);
        self::assertStringContainsString('q=pi_123', (string) $t->url);
        self::assertSame(35000, $r['data'][0]['amount_minor']);
        self::assertSame(1, $r['meta']['total']);
    }

    public function testGetPaymentUsesPathId(): void
    {
        $t = new FakeTransport(['data' => ['id' => 'p9', 'status' => 'paid'], 'error' => null]);
        $client = new BrivioClient('k', 'https://api.example/v1', $t);
        $p = $client->getPayment('p9');

        self::assertSame('https://api.example/v1/payments/p9', $t->url);
        self::assertSame('p9', $p['id']);
    }

    public function testRefundPaymentPostsAmountAndIdempotencyKey(): void
    {
        $t = new FakeTransport(['data' => ['refunded_minor' => 1000, 'pending' => false, 'payment' => ['id' => 'p1', 'status' => 'partially_refunded']], 'error' => null]);
        $client = new BrivioClient('k', 'https://api.example/v1', $t);
        $r = $client->refundPayment('p1', ['amount_minor' => 1000, 'reason' => 'goodwill'], 'idem-refund');

        self::assertSame('POST', $t->method);
        self::assertSame('https://api.example/v1/payments/p1/refund', $t->url);
        self::assertSame('idem-refund', $t->headers['Idempotency-Key']);
        self::assertSame(1000, $t->decodedBody()['amount_minor']);
        self::assertSame('goodwill', $t->decodedBody()['reason']);
        self::assertSame(1000, $r['refunded_minor']);
        self::assertFalse($r['pending']);
        self::assertSame('partially_refunded', $r['payment']['status']);
    }

    public function testRefundPaymentConflictThrows(): void
    {
        $t = new FakeTransport(
            ['data' => null, 'error' => ['code' => 'CONFLICT', 'message' => 'not-paid', 'details' => null]],
            409,
        );
        $client = new BrivioClient('k', 'https://api.example/v1', $t);

        $this->expectException(BrivioException::class);
        $client->refundPayment('p1');
    }

    public function testCreatePaymentLinkPostsMajorUnitAmount(): void
    {
        $t = new FakeTransport(
            ['data' => ['id' => 'l1', 'token' => 'tok', 'url' => 'https://pay.brivio.ro/pay/tok', 'qr_url' => null, 'expires_at' => '2026-10-23T00:00:00.000Z'], 'error' => null],
            201,
        );
        $client = new BrivioClient('k', 'https://api.example/v1', $t);
        $r = $client->createPaymentLink(['amount' => 350, 'currency' => 'RON', 'description' => 'Consultanță'], 'idem-link');

        self::assertSame('POST', $t->method);
        self::assertSame('https://api.example/v1/payment-links', $t->url);
        self::assertSame('idem-link', $t->headers['Idempotency-Key']);
        self::assertSame(350, $t->decodedBody()['amount']);
        self::assertSame('https://pay.brivio.ro/pay/tok', $r['url']);
    }

    public function testPaymentLinkListGetCancelPaths(): void
    {
        $t = new FakeTransport(['data' => ['id' => 'l1', 'provider_cancelled' => false, 'link' => null], 'error' => null, 'meta' => []]);
        $client = new BrivioClient('k', 'https://api.example/v1', $t);

        $client->listPaymentLinks(['status' => 'pending']);
        self::assertSame('GET', $t->method);
        self::assertStringContainsString('/payment-links?', (string) $t->url);
        self::assertStringContainsString('status=pending', (string) $t->url);

        $client->getPaymentLink('l1');
        self::assertSame('GET', $t->method);
        self::assertSame('https://api.example/v1/payment-links/l1', $t->url);

        $r = $client->cancelPaymentLink('l1');
        self::assertSame('POST', $t->method);
        self::assertSame('https://api.example/v1/payment-links/l1/cancel', $t->url);
        self::assertFalse($r['provider_cancelled']);
    }

    public function testListPayoutsSendsDateRange(): void
    {
        $t = new FakeTransport(['data' => [['id' => 'po1', 'gross' => '5000.00', 'fee' => '150.00', 'net' => '4850.00']], 'error' => null, 'meta' => ['total' => 1]]);
        $client = new BrivioClient('k', 'https://api.example/v1', $t);
        $r = $client->listPayouts(['provider' => 'stripe', 'from' => '2026-09-01', 'to' => '2026-09-30', 'include_items' => 'false']);

        self::assertStringContainsString('/payouts?', (string) $t->url);
        self::assertStringContainsString('provider=stripe', (string) $t->url);
        self::assertStringContainsString('from=2026-09-01', (string) $t->url);
        self::assertStringContainsString('include_items=false', (string) $t->url);
        self::assertSame('4850.00', $r['data'][0]['net']);
    }

    public function testListAndCreateProject(): void
    {
        $t = new FakeTransport(['data' => ['id' => 'p1', 'name' => 'P1'], 'error' => null], 201);
        $client = new BrivioClient('k', 'https://api.example/v1', $t);
        $p = $client->createProject(['name' => 'P1']);
        self::assertSame('POST', $t->method);
        self::assertStringContainsString('/projects', (string) $t->url);
        self::assertSame('p1', $p['id']);
    }

    public function testListProjectsSendsQuery(): void
    {
        $t = new FakeTransport(['data' => [], 'meta' => [], 'error' => null]);
        $client = new BrivioClient('k', 'https://api.example/v1', $t);
        $client->listProjects(['status' => 'ACTIVE']);
        self::assertSame('GET', $t->method);
        self::assertStringContainsString('/projects', (string) $t->url);
        self::assertStringContainsString('status=ACTIVE', (string) $t->url);
    }

    /**
     * CN-0108. The construction vertical reached the gateway, OpenAPI, the TS
     * SDK, the CLI and MCP — and not this SDK, while both coverage gates
     * stayed green because neither looked at PHP.
     */
    public function testListWorkCertificatesFiltersByProject(): void
    {
        $t = new FakeTransport(['data' => [], 'meta' => [], 'error' => null]);
        $client = new BrivioClient('k', 'https://api.example/v1', $t);
        $client->listWorkCertificates(['project_id' => 'p1']);
        self::assertSame('GET', $t->method);
        self::assertStringContainsString('/construction/work-certificates', (string) $t->url);
        self::assertStringContainsString('project_id=p1', (string) $t->url);
    }

    public function testListProjectScheduleEncodesPathId(): void
    {
        $t = new FakeTransport(['data' => [], 'error' => null]);
        $client = new BrivioClient('k', 'https://api.example/v1', $t);
        $client->listProjectSchedule('p 1/x');
        self::assertSame('GET', $t->method);
        // The id is interpolated into the PATH, so an unencoded slash would
        // silently address a different endpoint.
        self::assertStringContainsString('/construction/projects/p%201%2Fx/schedule', (string) $t->url);
    }

    public function testCreateExpensePostsBody(): void
    {
        $t = new FakeTransport(['data' => ['id' => 'e1'], 'error' => null], 201);
        $client = new BrivioClient('k', 'https://api.example/v1', $t);
        $e = $client->createExpense(['description' => 'E1', 'amount' => 50, 'expense_date' => '2026-01-15']);
        self::assertSame('POST', $t->method);
        self::assertStringContainsString('/expenses', (string) $t->url);
        self::assertSame('e1', $e['id']);
    }

    public function testCreateContractPostsBody(): void
    {
        $t = new FakeTransport(['data' => ['id' => 'k1', 'name' => 'C1'], 'error' => null], 201);
        $client = new BrivioClient('k', 'https://api.example/v1', $t);
        $k = $client->createContract(['name' => 'C1']);
        self::assertSame('POST', $t->method);
        self::assertStringContainsString('/contracts', (string) $t->url);
        self::assertSame('k1', $k['id']);
    }

    public function testListExpensesAndContracts(): void
    {
        $t = new FakeTransport(['data' => [], 'meta' => [], 'error' => null]);
        $client = new BrivioClient('k', 'https://api.example/v1', $t);
        $client->listExpenses(['category' => 'OFFICE']);
        self::assertStringContainsString('/expenses', (string) $t->url);
        self::assertStringContainsString('category=OFFICE', (string) $t->url);
        $client->listContracts(['status' => 'DRAFT']);
        self::assertStringContainsString('/contracts', (string) $t->url);
    }

    public function testCreateWebhookReturnsSecret(): void
    {
        $t = new FakeTransport(
            ['data' => ['id' => 'wh1', 'url' => 'https://app/wh', 'secret' => 'whsec_x', 'events' => ['invoice.paid']], 'error' => null],
            201,
        );
        $client = new BrivioClient('k', 'https://api.example/v1', $t);
        $wh = $client->createWebhook(['url' => 'https://app/wh', 'events' => ['invoice.paid']]);

        self::assertSame('POST', $t->method);
        self::assertStringContainsString('/webhooks', (string) $t->url);
        self::assertSame('whsec_x', $wh['secret']);
    }

    public function testRotateWebhookSecretPatchesFlag(): void
    {
        $t = new FakeTransport(['data' => ['id' => 'wh2', 'secret' => 'whsec_new'], 'error' => null]);
        $client = new BrivioClient('k', 'https://api.example/v1', $t);
        $wh = $client->rotateWebhookSecret('wh2');

        self::assertSame('PATCH', $t->method);
        self::assertTrue($t->decodedBody()['rotate_secret']);
        self::assertSame('whsec_new', $wh['secret']);
    }

    public function testListWebhookDeliveriesBuildsPath(): void
    {
        $t = new FakeTransport(['data' => [], 'meta' => [], 'error' => null]);
        $client = new BrivioClient('k', 'https://api.example/v1', $t);
        $client->listWebhookDeliveries('wh3', ['status' => 'failed']);

        self::assertStringContainsString('/webhooks/wh3/deliveries', (string) $t->url);
        self::assertStringContainsString('status=failed', (string) $t->url);
    }

    public function testVerifyWebhookSignature(): void
    {
        $secret = 'whsec_test';
        $payload = '{"event":"invoice.paid"}';
        $sig = 'sha256=' . hash_hmac('sha256', $payload, $secret);

        self::assertTrue(BrivioClient::verifyWebhookSignature($payload, $sig, $secret));
        self::assertFalse(BrivioClient::verifyWebhookSignature($payload . 'x', $sig, $secret));
        self::assertFalse(BrivioClient::verifyWebhookSignature($payload, $sig, 'wrong'));
        // Stale timestamp rejected
        self::assertFalse(BrivioClient::verifyWebhookSignature($payload, $sig, $secret, (string) (time() - 400)));
        // Fresh timestamp accepted
        self::assertTrue(BrivioClient::verifyWebhookSignature($payload, $sig, $secret, (string) time()));
    }

    public function testInventoryAndHrPaths(): void
    {
        $t = new FakeTransport(['data' => [], 'meta' => [], 'error' => null]);
        $client = new BrivioClient('k', 'https://api.example/v1', $t);

        $client->listStockLevels(['below_min' => 'true']);
        self::assertStringContainsString('/inventory/stock', (string) $t->url);
        self::assertStringContainsString('below_min=true', (string) $t->url);

        $client->listStockMovements(['type' => 'SALE']);
        self::assertStringContainsString('/inventory/movements', (string) $t->url);

        $client->listNirDocuments(['status' => 'CONFIRMED']);
        self::assertStringContainsString('/inventory/nir', (string) $t->url);
        self::assertStringContainsString('status=CONFIRMED', (string) $t->url);

        $client->listEmployees(['department' => 'Vanzari']);
        self::assertStringContainsString('/hr/employees', (string) $t->url);
    }

    public function testParityMethodsBuildPathsAndVerbs(): void
    {
        $t = new FakeTransport(['data' => ['id' => 'x'], 'meta' => [], 'error' => null]);
        $client = new BrivioClient('k', 'https://api.example/v1', $t);

        $client->acceptOffer('q 1');
        self::assertSame('POST', $t->method);
        self::assertStringContainsString('/network/quotes/q%201/accept', (string) $t->url);

        $client->sendEmail(['to' => [['email' => 'a@b.ro']], 'subject' => 'Hi', 'category' => 'transactional', 'text' => 'x'], 'idem-1');
        self::assertSame('POST', $t->method);
        self::assertStringContainsString('/emails/send', (string) $t->url);
        self::assertSame('transactional', $t->decodedBody()['category']);
        self::assertSame('idem-1', $t->headers['Idempotency-Key'] ?? null);

        $client->sendEmailMessage(['to' => ['Ana <a@b.ro>'], 'subject' => 'Hi', 'text' => 'x', 'tags' => ['kind' => 'alert']], 'idem-2');
        self::assertSame('POST', $t->method);
        self::assertStringContainsString('/email/send', (string) $t->url);
        self::assertSame(['Ana <a@b.ro>'], $t->decodedBody()['to']);
        self::assertSame('idem-2', $t->headers['Idempotency-Key'] ?? null);

        $client->getEmailMessage('m 1');
        self::assertSame('GET', $t->method);
        self::assertStringContainsString('/email/messages/m%201', (string) $t->url);

        $client->verifyEmailDomain('d1');
        self::assertSame('POST', $t->method);
        self::assertStringContainsString('/email-domains/d1/verify', (string) $t->url);

        $client->deleteEmailDomain('d1');
        self::assertSame('DELETE', $t->method);

        $client->listPaymentMethods('cust_9');
        self::assertSame('GET', $t->method);
        self::assertStringContainsString('/payment-methods', (string) $t->url);
        self::assertStringContainsString('external_customer_id=cust_9', (string) $t->url);

        $client->rotateApiKey('k1', ['grace_period_hours' => 1]);
        self::assertSame('POST', $t->method);
        self::assertStringContainsString('/api-keys/k1/rotate', (string) $t->url);
        self::assertSame(1, $t->decodedBody()['grace_period_hours']);

        $client->listSites();
        self::assertSame('GET', $t->method);
        self::assertStringContainsString('/sites', (string) $t->url);
    }

    public function testSaftGenerationPathsAndGeneratedIdempotencyKey(): void
    {
        $t = new FakeTransport(['data' => ['id' => 'g1', 'status' => 'queued'], 'error' => null], 202);
        $client = new BrivioClient('k', 'https://api.example/v1', $t);

        $client->createSaftGeneration(['fiscal_year_id' => 'fy1', 'variant' => 'monthly']);
        self::assertSame('POST', $t->method);
        self::assertSame('https://api.example/v1/saft/generations', $t->url);
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $t->headers['Idempotency-Key'] ?? '');
        $first = $t->headers['Idempotency-Key'];
        $client->createSaftGeneration(['fiscal_year_id' => 'fy1']);
        self::assertNotSame($first, $t->headers['Idempotency-Key'] ?? null);
        $client->createSaftGeneration([], 'mine');
        self::assertSame('mine', $t->headers['Idempotency-Key'] ?? null);

        $client->listSaftGenerations(['fiscal_year_id' => 'fy1']);
        self::assertSame('GET', $t->method);
        self::assertSame('https://api.example/v1/saft/generations?fiscal_year_id=fy1', $t->url);
        $client->getSaftGeneration('g 1');
        self::assertSame('https://api.example/v1/saft/generations/g%201', $t->url);
        $client->listSaftGenerationFindings('g1');
        self::assertSame('https://api.example/v1/saft/generations/g1/findings', $t->url);
        $client->getSaftGenerationDownload('g1');
        self::assertSame('GET', $t->method);
        self::assertSame('https://api.example/v1/saft/generations/g1/download', $t->url);
    }

    public function testExportPathsIdempotencyAndSignedUrlDownload(): void
    {
        $t = new FakeTransport(['data' => ['url' => 'https://gcs/x', 'filename' => 'e.csv', 'sha256' => 'ab'], 'error' => null]);
        $client = new BrivioClient('k', 'https://api.example/v1', $t);

        $client->createExport(['entity' => 'contacts', 'format' => 'csv']);
        self::assertSame('POST', $t->method);
        self::assertSame('https://api.example/v1/exports', $t->url);
        self::assertNotEmpty($t->headers['Idempotency-Key'] ?? '');
        self::assertSame('contacts', $t->decodedBody()['entity']);

        $client->listExports(['page' => 2]);
        self::assertSame('GET', $t->method);
        self::assertSame('https://api.example/v1/exports?page=2', $t->url);
        $client->getExport('e1');
        self::assertSame('https://api.example/v1/exports/e1', $t->url);

        $dl = $client->downloadExport('e1');
        self::assertSame('GET', $t->method);
        self::assertSame('https://api.example/v1/exports/e1/download', $t->url);
        self::assertSame('https://gcs/x', $dl['url']);
        self::assertArrayNotHasKey('Idempotency-Key', $t->headers);
    }

    public function testDownloadExportReturnsRawBytesForLegacyRows(): void
    {
        $transport = static fn (string $m, string $u, array $h, ?string $b): array => ['status' => 200, 'body' => "id,name\n1,ACME\n"];
        $client = new BrivioClient('k', 'https://api.example/v1', $transport);
        self::assertSame(['bytes' => "id,name\n1,ACME\n"], $client->downloadExport('e1'));
    }

    public function testDownloadExportThrowsOnExpired(): void
    {
        $t = new FakeTransport(['data' => null, 'error' => ['code' => 'NOT_FOUND', 'message' => 'expired']], 410);
        $client = new BrivioClient('k', 'https://api.example/v1', $t);
        $this->expectException(BrivioException::class);
        $client->downloadExport('e1');
    }

    public function testImportPathsAndIdempotency(): void
    {
        $t = new FakeTransport(['data' => ['id' => 'j1', 'status' => 'staged'], 'error' => null], 202);
        $client = new BrivioClient('k', 'https://api.example/v1', $t);

        $client->createImport(['entity' => 'contacts', 'rows' => [['name' => 'ACME']]]);
        self::assertSame('POST', $t->method);
        self::assertSame('https://api.example/v1/imports', $t->url);
        self::assertNotEmpty($t->headers['Idempotency-Key'] ?? '');

        $client->commitImport('j1');
        self::assertSame('POST', $t->method);
        self::assertSame('https://api.example/v1/imports/j1/commit', $t->url);
        self::assertNotEmpty($t->headers['Idempotency-Key'] ?? '');

        $client->cancelImport('j1');
        self::assertSame('POST', $t->method);
        self::assertSame('https://api.example/v1/imports/j1/cancel', $t->url);
        self::assertArrayNotHasKey('Idempotency-Key', $t->headers);
        $client->cancelImport('j1', 'c-1');
        self::assertSame('c-1', $t->headers['Idempotency-Key'] ?? null);

        $client->listImports();
        self::assertSame('GET', $t->method);
        self::assertSame('https://api.example/v1/imports', $t->url);
        $client->getImport('j1');
        self::assertSame('https://api.example/v1/imports/j1', $t->url);
        $client->listImportErrors('j1', ['page' => 3]);
        self::assertSame('https://api.example/v1/imports/j1/errors?page=3', $t->url);
    }

    public function testCampaignAbTestPaths(): void
    {
        $t = new FakeTransport(['data' => [['id' => 'v1']], 'error' => null]);
        $client = new BrivioClient('k', 'https://api.example/v1', $t);

        self::assertSame([['id' => 'v1']], $client->listCampaignVariants('c1'));
        self::assertSame('GET', $t->method);
        self::assertSame('https://api.example/v1/marketing/campaigns/c1/variants', $t->url);

        $client->configureCampaignAbTest('c1', ['variants' => [['subject' => 'A'], ['subject' => 'B']]], 'ab-1');
        self::assertSame('PUT', $t->method);
        self::assertSame('https://api.example/v1/marketing/campaigns/c1/ab-test', $t->url);
        self::assertSame('ab-1', $t->headers['Idempotency-Key'] ?? null);

        $client->clearCampaignAbTest('c1', 'ab-2');
        self::assertSame('DELETE', $t->method);
        self::assertSame('https://api.example/v1/marketing/campaigns/c1/ab-test', $t->url);
        self::assertSame('ab-2', $t->headers['Idempotency-Key'] ?? null);
    }

    public function testProspectingSavedSearchPaths(): void
    {
        $t = new FakeTransport(['data' => ['id' => 's1'], 'error' => null]);
        $client = new BrivioClient('k', 'https://api.example/v1', $t);

        $client->listProspectingSavedSearches();
        self::assertSame('GET', $t->method);
        self::assertSame('https://api.example/v1/prospecting/saved-searches', $t->url);
        $client->createProspectingSavedSearch(['name' => 'IT Cluj', 'filters' => ['county' => 'CJ']]);
        self::assertSame('POST', $t->method);
        self::assertSame('IT Cluj', $t->decodedBody()['name']);
        $client->getProspectingSavedSearch('s1');
        self::assertSame('GET', $t->method);
        self::assertSame('https://api.example/v1/prospecting/saved-searches/s1', $t->url);
        $client->updateProspectingSavedSearch('s1', ['name' => 'X']);
        self::assertSame('PATCH', $t->method);
        self::assertSame('https://api.example/v1/prospecting/saved-searches/s1', $t->url);
        $client->deleteProspectingSavedSearch('s1');
        self::assertSame('DELETE', $t->method);
        self::assertSame('https://api.example/v1/prospecting/saved-searches/s1', $t->url);
    }

    public function testFleetPaths(): void
    {
        $t = new FakeTransport(['data' => ['id' => 'v1'], 'error' => null]);
        $client = new BrivioClient('k', 'https://api.example/v1', $t);

        $client->listFleetVehicles();
        self::assertSame('GET', $t->method);
        self::assertSame('https://api.example/v1/fleet/vehicles', $t->url);

        $client->createFleetVehicle(['plate' => 'B 01 ABC'], 'fv-1');
        self::assertSame('POST', $t->method);
        self::assertSame('https://api.example/v1/fleet/vehicles', $t->url);
        self::assertSame('B 01 ABC', $t->decodedBody()['plate']);
        self::assertSame('fv-1', $t->headers['Idempotency-Key'] ?? null);

        $client->getFleetVehicle('v1');
        self::assertSame('GET', $t->method);
        self::assertSame('https://api.example/v1/fleet/vehicles/v1', $t->url);

        $client->updateFleetVehicle('v1', ['status' => 'SOLD']);
        self::assertSame('PATCH', $t->method);
        self::assertSame('https://api.example/v1/fleet/vehicles/v1', $t->url);
        self::assertSame('SOLD', $t->decodedBody()['status']);

        $client->deleteFleetVehicle('v1');
        self::assertSame('DELETE', $t->method);
        self::assertSame('https://api.example/v1/fleet/vehicles/v1', $t->url);
    }

    public function testFleetNestedLogPathsAndDrivers(): void
    {
        $t = new FakeTransport(['data' => ['id' => 'l1'], 'error' => null]);
        $client = new BrivioClient('k', 'https://api.example/v1', $t);

        $client->listFleetOdometerReadings('v 1');
        self::assertSame('GET', $t->method);
        self::assertSame('https://api.example/v1/fleet/vehicles/v%201/odometer-readings', $t->url);

        $client->createFleetOdometerReading('v1', ['reading_km' => 12000, 'reading_date' => '2026-09-01'], 'odo-1');
        self::assertSame('POST', $t->method);
        self::assertSame('https://api.example/v1/fleet/vehicles/v1/odometer-readings', $t->url);
        self::assertSame(12000, $t->decodedBody()['reading_km']);
        self::assertSame('odo-1', $t->headers['Idempotency-Key'] ?? null);

        $client->listFleetServiceLogs('v1');
        self::assertSame('GET', $t->method);
        self::assertSame('https://api.example/v1/fleet/vehicles/v1/service-logs', $t->url);

        $client->createFleetServiceLog('v1', ['service_date' => '2026-09-01', 'type' => 'ITP']);
        self::assertSame('POST', $t->method);
        self::assertSame('https://api.example/v1/fleet/vehicles/v1/service-logs', $t->url);
        self::assertSame('ITP', $t->decodedBody()['type']);

        $client->listFleetDrivers();
        self::assertSame('GET', $t->method);
        self::assertSame('https://api.example/v1/fleet/drivers', $t->url);
    }

    public function testDeliveryRoutePathsAndBodies(): void
    {
        $t = new FakeTransport(['data' => ['id' => 'r1'], 'error' => null]);
        $client = new BrivioClient('k', 'https://api.example/v1', $t);

        $client->listDeliveryRoutes(['status' => 'PLANNED', 'date_from' => '2026-09-01']);
        self::assertSame('GET', $t->method);
        self::assertSame('https://api.example/v1/delivery-routes?status=PLANNED&date_from=2026-09-01', $t->url);

        $client->createDeliveryRoute(['name' => 'Luni'], 'c-1');
        self::assertSame('POST', $t->method);
        self::assertSame('https://api.example/v1/delivery-routes', $t->url);
        self::assertSame('Luni', $t->decodedBody()['name']);
        self::assertSame('c-1', $t->headers['Idempotency-Key'] ?? null);

        $client->getDeliveryRoute('r 1');
        self::assertSame('GET', $t->method);
        self::assertSame('https://api.example/v1/delivery-routes/r%201', $t->url);

        $client->updateDeliveryRoute('r1', ['name' => 'Marti']);
        self::assertSame('PATCH', $t->method);
        self::assertSame('https://api.example/v1/delivery-routes/r1', $t->url);
        self::assertSame('Marti', $t->decodedBody()['name']);

        $client->cancelDeliveryRoute('r1');
        self::assertSame('PATCH', $t->method);
        self::assertSame('CANCELLED', $t->decodedBody()['status']);

        $client->listDeliveryRouteStops('r1');
        self::assertSame('GET', $t->method);
        self::assertSame('https://api.example/v1/delivery-routes/r1/stops', $t->url);

        $client->addDeliveryRouteStop('r1', ['address' => ['city' => 'Cluj']]);
        self::assertSame('POST', $t->method);
        self::assertSame('https://api.example/v1/delivery-routes/r1/stops', $t->url);
        self::assertSame('Cluj', $t->decodedBody()['address']['city']);

        $client->removeDeliveryRouteStop('r1', 's 1');
        self::assertSame('DELETE', $t->method);
        self::assertSame('https://api.example/v1/delivery-routes/r1/stops/s%201', $t->url);
    }

    public function testDeliveryRouteOptimizeAndDispatchAlwaysSendAnIdempotencyKey(): void
    {
        $t = new FakeTransport(['data' => ['route_id' => 'r1', 'sent_to_driver' => true], 'error' => null]);
        $client = new BrivioClient('k', 'https://api.example/v1', $t);
        $uuid = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

        $client->optimizeDeliveryRoute('r1');
        self::assertSame('POST', $t->method);
        self::assertSame('https://api.example/v1/delivery-routes/r1/optimize', $t->url);
        self::assertMatchesRegularExpression($uuid, $t->headers['Idempotency-Key'] ?? '');

        $client->dispatchDeliveryRoute('r1');
        self::assertSame('POST', $t->method);
        self::assertSame('https://api.example/v1/delivery-routes/r1/dispatch', $t->url);
        self::assertMatchesRegularExpression($uuid, $t->headers['Idempotency-Key'] ?? '');
        $first = $t->headers['Idempotency-Key'];

        $client->dispatchDeliveryRoute('r1');
        self::assertNotSame($first, $t->headers['Idempotency-Key'] ?? null);

        $client->dispatchDeliveryRoute('r1', 'mine');
        self::assertSame('mine', $t->headers['Idempotency-Key'] ?? null);
        $client->optimizeDeliveryRoute('r1', 'opt-1');
        self::assertSame('opt-1', $t->headers['Idempotency-Key'] ?? null);
    }

    public function testCourtCasePaths(): void
    {
        $t = new FakeTransport(['data' => ['id' => 'c1'], 'error' => null]);
        $client = new BrivioClient('k', 'https://api.example/v1', $t);

        $client->listCourtCases(['status' => 'OPEN']);
        self::assertSame('GET', $t->method);
        self::assertStringStartsWith('https://api.example/v1/legal/court-cases', $t->url);
        self::assertStringContainsString('status=OPEN', $t->url);

        $client->createCourtCase(['case_number' => '123/3/2026'], 'idem-1');
        self::assertSame('POST', $t->method);
        self::assertSame('https://api.example/v1/legal/court-cases', $t->url);
        self::assertSame('123/3/2026', $t->decodedBody()['case_number']);
        self::assertSame('idem-1', $t->headers['Idempotency-Key'] ?? null);

        $client->getCourtCase('c1');
        self::assertSame('GET', $t->method);
        self::assertSame('https://api.example/v1/legal/court-cases/c1', $t->url);

        $client->updateCourtCase('c1', ['status' => 'WON']);
        self::assertSame('PATCH', $t->method);
        self::assertSame('https://api.example/v1/legal/court-cases/c1', $t->url);
        self::assertSame('WON', $t->decodedBody()['status']);

        $client->deleteCourtCase('c1');
        self::assertSame('DELETE', $t->method);
        self::assertSame('https://api.example/v1/legal/court-cases/c1', $t->url);
    }

    public function testCourtCaseChildPaths(): void
    {
        $t = new FakeTransport(['data' => ['id' => 'h1'], 'error' => null]);
        $client = new BrivioClient('k', 'https://api.example/v1', $t);

        $client->listCourtCaseHearings('c 1');
        self::assertSame('GET', $t->method);
        self::assertSame('https://api.example/v1/legal/court-cases/c%201/hearings', $t->url);

        $client->createCourtCaseHearing('c1', ['hearing_date' => '2026-10-01']);
        self::assertSame('POST', $t->method);
        self::assertSame('https://api.example/v1/legal/court-cases/c1/hearings', $t->url);
        self::assertSame('2026-10-01', $t->decodedBody()['hearing_date']);

        $client->listCourtCaseNotes('c1');
        self::assertSame('GET', $t->method);
        self::assertSame('https://api.example/v1/legal/court-cases/c1/notes', $t->url);

        $client->createCourtCaseNote('c1', ['content' => 'Depus intampinare', 'is_internal' => true]);
        self::assertSame('POST', $t->method);
        self::assertSame('https://api.example/v1/legal/court-cases/c1/notes', $t->url);
        self::assertSame('Depus intampinare', $t->decodedBody()['content']);
    }

}
