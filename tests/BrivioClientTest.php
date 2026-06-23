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
}
