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
}
