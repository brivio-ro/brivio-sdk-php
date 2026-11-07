<?php

declare(strict_types=1);

namespace Brivio\Tests;

use Brivio\Legacy\Facturis;
use Brivio\Legacy\Fgo;
use Brivio\Legacy\Oblio;
use Brivio\Legacy\SmartBill;
use Brivio\Tests\Support\FakeTransport;
use Brivio\Tests\Support\OpenApiSchema;
use Brivio\Tests\Support\RecordingTransport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * MRC-M4043-09: every legacy shim must put a body on the wire that the
 * native API accepts — validated against the request schema of the endpoint
 * it actually hits in docs/brivio-openapi.json, not against the shim's own
 * idea of the shape.
 */
final class LegacyShimTest extends TestCase
{
    private const BASE = 'https://api.example/v1';

    /**
     * Keys `POST /contacts` accepts (gateway createContactSchema,
     * apps/gateway/src/routes/contacts.ts). The published ContactCreate schema
     * lists only five of them and is open, so this list is the stricter check.
     */
    private const GATEWAY_CONTACT_KEYS = [
        'type', 'person_type', 'name', 'email', 'phone', 'website', 'vat_number',
        'registration_number', 'address', 'notes', 'tags', 'is_active', 'external_id',
    ];

    private static ?OpenApiSchema $spec = null;

    public static function setUpBeforeClass(): void
    {
        self::$spec = OpenApiSchema::load();
    }

    private function spec(): OpenApiSchema
    {
        if (self::$spec === null) {
            self::markTestSkipped('docs/brivio-openapi.json not found (SDK tested outside the monorepo)');
        }
        return self::$spec;
    }

    /**
     * The whole flow: lookup → create contact → create invoice, every body
     * valid against its endpoint's request schema.
     *
     * @return array{contact: array<string,mixed>, invoice: array<string,mixed>}
     */
    private function assertNativeFlow(RecordingTransport $t): array
    {
        self::assertSame(['GET /contacts', 'POST /contacts', 'POST /invoices'], $t->calls());

        $lookup = $t->requests[0]['query'];
        $contact = $t->bodyOf('POST', '/contacts');
        $invoice = $t->bodyOf('POST', '/invoices');

        $this->assertValidRequest('POST', '/contacts', $contact, closed: false);
        self::assertSame([], array_values(array_diff(array_keys($contact), self::GATEWAY_CONTACT_KEYS)), 'contact body has keys the gateway does not read');
        self::assertIsString($contact['external_id'] ?? null);

        // InvoiceCreate declares every key the shims may send: closed mode
        // rejects any leftover legacy key (client, lines, series_name, …).
        $this->assertValidRequest('POST', '/invoices', $invoice, closed: true);
        self::assertArrayNotHasKey('contact_id', $invoice, 'exactly one of contact_id / contact_external_id');
        self::assertSame($contact['external_id'], $invoice['contact_external_id'] ?? null);
        self::assertSame($contact['external_id'], $lookup['external_id'] ?? null);

        return ['contact' => $contact, 'invoice' => $invoice];
    }

    /** @param array<string,mixed> $body */
    private function assertValidRequest(string $method, string $path, array $body, bool $closed): void
    {
        $spec = $this->spec();
        $errors = $spec->validate($body, $spec->requestSchema($method, $path), $closed);
        self::assertSame([], $errors, "{$method} {$path} body violates the OpenAPI request schema:\n" . implode("\n", $errors) . "\nbody: " . json_encode($body));
    }

    // ── SmartBill ─────────────────────────────────────────────────────

    /** @return array<string,mixed> */
    private static function smartBillPayload(mixed $isTaxPayer, string $vatCode = 'RO999'): array
    {
        $client = ['name' => 'ACME SRL', 'vatCode' => $vatCode, 'city' => 'Cluj-Napoca', 'county' => 'Cluj'];
        if ($isTaxPayer !== null) {
            $client['isTaxPayer'] = $isTaxPayer;
        }
        return [
            'companyVatCode' => 'RO123',
            'seriesName' => 'FACT',
            'issueDate' => '2026-11-05',
            'dueDate' => '2026-11-20',
            'currency' => 'RON',
            'useStock' => true,
            'client' => $client,
            'products' => [
                ['name' => 'Widget', 'quantity' => 2, 'price' => 10.5, 'taxPercentage' => 21, 'measuringUnitName' => 'buc', 'isService' => false],
            ],
        ];
    }

    public function testSmartBillCreateInvoiceSendsNativeBody(): void
    {
        $t = new RecordingTransport();
        (new SmartBill('k', self::BASE, $t))->createInvoice(self::smartBillPayload(true));

        ['contact' => $contact, 'invoice' => $invoice] = $this->assertNativeFlow($t);
        self::assertSame(self::BASE . '/invoices', $t->requests[2]['url']);
        self::assertSame('RO999', $contact['vat_number']);
        self::assertSame('CLIENT', $contact['type']);
        self::assertSame(['city' => 'Cluj-Napoca', 'county' => 'Cluj', 'country' => 'RO'], $contact['address']);
        self::assertSame('INVOICE', $invoice['type']);
        self::assertSame('FACT', $invoice['series']);
        self::assertSame('2026-11-05', $invoice['issue_date']);
        self::assertSame('2026-11-20', $invoice['due_date']);
        self::assertSame([['description' => 'Widget', 'quantity' => 2.0, 'unit' => 'buc', 'unit_price' => 10.5, 'vat_rate' => 21.0]], $invoice['items']);
        self::assertArrayNotHasKey('exchange_rate', $invoice, 'RON never carries a rate');
    }

    public function testSmartBillEstimateIsProforma(): void
    {
        $t = new RecordingTransport();
        (new SmartBill('k', self::BASE, $t))->createEstimate(self::smartBillPayload(true));
        self::assertSame('PROFORMA', $this->assertNativeFlow($t)['invoice']['type']);
    }

    public function testSmartBillGrossPriceIsSentNet(): void
    {
        $payload = self::smartBillPayload(true);
        $payload['products'][0]['price'] = 121.0;
        $payload['products'][0]['isTaxIncluded'] = true;
        $t = new RecordingTransport();
        (new SmartBill('k', self::BASE, $t))->createInvoice($payload);

        self::assertEqualsWithDelta(100.0, $this->assertNativeFlow($t)['invoice']['items'][0]['unit_price'], 1e-9);
    }

    public function testSmartBillDiscountLineIsRefusedBeforeAnyRequest(): void
    {
        $payload = self::smartBillPayload(true);
        $payload['products'][] = ['name' => 'Discount', 'price' => 5, 'isDiscount' => true];
        $t = new RecordingTransport();
        try {
            (new SmartBill('k', self::BASE, $t))->createInvoice($payload);
            self::fail('a discount line must be refused');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('discount', $e->getMessage());
        }
        self::assertSame([], $t->calls());
    }

    public function testExistingContactIsReusedNotDuplicated(): void
    {
        $t = new RecordingTransport([['id' => 'c-1', 'external_id' => 'legacy:cui:999']]);
        (new SmartBill('k', self::BASE, $t))->createInvoice(self::smartBillPayload(true));

        self::assertSame(['GET /contacts', 'POST /invoices'], $t->calls());
        $invoice = $t->bodyOf('POST', '/invoices');
        $this->assertValidRequest('POST', '/invoices', $invoice, closed: true);
        self::assertSame('legacy:cui:999', $invoice['contact_external_id']);
    }

    // ── RO VAT prefix, per shim ───────────────────────────────────────

    /**
     * [shim call, expected vat_number]. Payer → RO<digits>; explicit
     * non-payer → bare digits, whatever prefix the caller typed; no flag →
     * the caller's own prefix is kept.
     *
     * @return iterable<string, array{\Closure(RecordingTransport): void, string}>
     */
    public static function vatCases(): iterable
    {
        yield 'smartbill isTaxPayer=true, bare cui' => [static fn (RecordingTransport $t) => (new SmartBill('k', self::BASE, $t))->createInvoice(self::smartBillPayload(true, '999')), 'RO999'];
        yield 'smartbill isTaxPayer=false, RO typed' => [static fn (RecordingTransport $t) => (new SmartBill('k', self::BASE, $t))->createInvoice(self::smartBillPayload(false, 'RO999')), '999'];
        yield 'smartbill isTaxPayer="0" string' => [static fn (RecordingTransport $t) => (new SmartBill('k', self::BASE, $t))->createInvoice(self::smartBillPayload('0', 'ro 999')), '999'];
        yield 'smartbill no flag keeps bare' => [static fn (RecordingTransport $t) => (new SmartBill('k', self::BASE, $t))->createInvoice(self::smartBillPayload(null, '999')), '999'];

        yield 'oblio vatPayer=true' => [static fn (RecordingTransport $t) => self::oblio($t, true, '888'), 'RO888'];
        yield 'oblio vatPayer=false' => [static fn (RecordingTransport $t) => self::oblio($t, false, 'RO888'), '888'];
        yield 'oblio vatPayer=1 int' => [static fn (RecordingTransport $t) => self::oblio($t, 1, '888'), 'RO888'];

        yield 'fgo PlatitorTVA=true' => [static fn (RecordingTransport $t) => self::fgo($t, true, '555'), 'RO555'];
        yield 'fgo PlatitorTVA=false' => [static fn (RecordingTransport $t) => self::fgo($t, false, 'RO555'), '555'];
        yield 'fgo PlatitorTVA="Da"' => [static fn (RecordingTransport $t) => self::fgo($t, 'Da', '555'), 'RO555'];

        yield 'facturis client_platitor_tva=1' => [static fn (RecordingTransport $t) => self::facturis($t, 1, '321'), 'RO321'];
        yield 'facturis client_platitor_tva=0' => [static fn (RecordingTransport $t) => self::facturis($t, 0, 'RO321'), '321'];
    }

    /** @param \Closure(RecordingTransport): void $call */
    #[DataProvider('vatCases')]
    public function testRoVatPrefixFollowsTheVendorPayerFlag(\Closure $call, string $expectedVat): void
    {
        $t = new RecordingTransport();
        $call($t);
        ['contact' => $contact, 'invoice' => $invoice] = $this->assertNativeFlow($t);

        self::assertSame($expectedVat, $contact['vat_number']);
        // Payer or not, it is the same company: one contact, one external id.
        self::assertSame('legacy:cui:' . preg_replace('/^RO/', '', $expectedVat), $invoice['contact_external_id']);
    }

    public function testCnpBuyerIsAnIndividualAndNotPrefixed(): void
    {
        $t = new RecordingTransport();
        (new SmartBill('k', self::BASE, $t))->createInvoice(self::smartBillPayload(true, '1900101123456'));
        $contact = $this->assertNativeFlow($t)['contact'];
        self::assertSame('1900101123456', $contact['vat_number']);
        self::assertSame('INDIVIDUAL', $contact['person_type']);
    }

    // ── Oblio ─────────────────────────────────────────────────────────

    private static function oblio(RecordingTransport $t, mixed $vatPayer, string $cif, bool $proforma = false): void
    {
        $api = new Oblio('k', self::BASE, $t);
        $api->setCif('RO777');
        $data = [
            'seriesName' => 'PROF',
            'issueDate' => '2026-11-05',
            'mentions' => 'Plata in 15 zile',
            'spvExtern' => 1,
            'client' => ['name' => 'Beta SRL', 'cif' => $cif, 'rc' => 'J12/1/2020', 'vatPayer' => $vatPayer, 'save' => 1],
            'products' => [
                ['name' => 'Serviciu X', 'quantity' => 3, 'price' => 50, 'vatPercentage' => 21, 'productType' => 'Serviciu', 'measuringUnit' => 'ora'],
            ],
        ];
        $proforma ? $api->createProforma($data) : $api->createInvoice($data);
    }

    public function testOblioCreateInvoiceSendsNativeBody(): void
    {
        $t = new RecordingTransport();
        self::oblio($t, true, '888');

        ['contact' => $contact, 'invoice' => $invoice] = $this->assertNativeFlow($t);
        self::assertSame('J12/1/2020', $contact['registration_number']);
        self::assertSame('PROF', $invoice['series']);
        self::assertSame('2026-11-05', $invoice['due_date'], 'due date defaults to the issue date');
        self::assertSame('Plata in 15 zile', $invoice['notes']);
        self::assertSame('ora', $invoice['items'][0]['unit']);
    }

    public function testOblioProforma(): void
    {
        $t = new RecordingTransport();
        self::oblio($t, true, '888', proforma: true);
        self::assertSame('PROFORMA', $this->assertNativeFlow($t)['invoice']['type']);
    }

    public function testOblioNomenclatureClientsHitsContacts(): void
    {
        $t = new FakeTransport(['data' => [], 'error' => null, 'meta' => []]);
        (new Oblio('k', self::BASE, $t))->nomenclature('clients');

        self::assertSame(self::BASE . '/contacts', $t->url);
    }

    // ── FGO ───────────────────────────────────────────────────────────

    private static function fgo(RecordingTransport $t, mixed $platitor, string $codUnic, string $valuta = 'RON'): void
    {
        (new Fgo('k', self::BASE, $t))->createInvoice([
            'Serie' => 'BV',
            'Valuta' => $valuta,
            'TipFactura' => 'Factura',
            'DataEmitere' => '05.11.2026',
            'Client' => ['Denumire' => 'SC Test SRL', 'CodUnic' => $codUnic, 'Tip' => 'PJ', 'PlatitorTVA' => $platitor, 'Judet' => 'Brasov', 'Tara' => 'ro'],
            'Continut' => [
                ['Denumire' => 'Consultanta', 'NrProduse' => 1, 'PretUnitar' => 100, 'CotaTVA' => 21, 'UM' => 'ora'],
            ],
        ]);
    }

    public function testFgoCreateInvoiceSendsNativeBody(): void
    {
        $t = new RecordingTransport();
        self::fgo($t, true, '555');

        ['contact' => $contact, 'invoice' => $invoice] = $this->assertNativeFlow($t);
        self::assertSame('COMPANY', $contact['person_type']);
        self::assertSame(['county' => 'Brasov', 'country' => 'RO'], $contact['address']);
        self::assertSame('BV', $invoice['series']);
        self::assertSame('2026-11-05', $invoice['issue_date'], 'DD.MM.YYYY normalised to ISO');
        self::assertSame('Consultanta', $invoice['items'][0]['description']);
    }

    public function testForeignCurrencyLeavesTheRateToTheServer(): void
    {
        $t = new RecordingTransport();
        self::fgo($t, true, '555', 'eur');
        $invoice = $this->assertNativeFlow($t)['invoice'];
        self::assertSame('EUR', $invoice['currency']);
        self::assertArrayNotHasKey('exchange_rate', $invoice, 'omitted → BNR T-1 server-side, never a silent 1');
    }

    public function testFgoCreateClientUpsertsWithTheSameExternalId(): void
    {
        $t = new RecordingTransport();
        (new Fgo('k', self::BASE, $t))->createClient(['Denumire' => 'SC Test SRL', 'CodUnic' => '555', 'PlatitorTVA' => true]);

        self::assertSame(['GET /contacts', 'POST /contacts'], $t->calls());
        $contact = $t->bodyOf('POST', '/contacts');
        $this->assertValidRequest('POST', '/contacts', $contact, closed: false);
        self::assertSame('legacy:cui:555', $contact['external_id']);
    }

    // ── Facturis ──────────────────────────────────────────────────────

    private static function facturis(RecordingTransport $t, mixed $platitor, string $cui): void
    {
        $f = new Facturis('k', self::BASE, $t);
        $f->setCui('RO123');
        $f->saveInvoice([
            'serie' => 'FAC',
            'client_name' => 'Gamma SRL',
            'client_cui' => $cui,
            'client_platitor_tva' => $platitor,
            'client_email' => 'ana@gamma.ro',
            'moneda' => 'RON',
            'data_factura' => '2026-11-05',
            'data_scadenta' => '2026-12-05',
            'trimite_efactura' => true,
            'products' => [
                ['denumire' => 'Abonament', 'cantitate' => 2, 'pret' => 40, 'cota_tva' => 21, 'tip' => 'serviciu'],
            ],
        ]);
    }

    public function testFacturisSaveInvoiceSendsNativeBody(): void
    {
        $t = new RecordingTransport();
        self::facturis($t, 1, '321');

        ['contact' => $contact, 'invoice' => $invoice] = $this->assertNativeFlow($t);
        self::assertSame('ana@gamma.ro', $contact['email']);
        self::assertSame('FAC', $invoice['series']);
        self::assertSame('2026-12-05', $invoice['due_date']);
        self::assertSame([['description' => 'Abonament', 'quantity' => 2.0, 'unit' => 'buc', 'unit_price' => 40.0, 'vat_rate' => 21.0]], $invoice['items']);
    }

    public function testFacturisSaveProductHitsArticles(): void
    {
        $t = new FakeTransport(['data' => ['id' => 'art1'], 'error' => null], 201);
        (new Facturis('k', self::BASE, $t))->saveProduct(['denumire' => 'Tuns', 'pret' => 50, 'cota_tva' => 21, 'tip' => 'serviciu']);

        self::assertSame(self::BASE . '/articles', $t->url);
    }

    // ── The validator itself must be able to say no ───────────────────

    public function testValidatorRejectsTheOldLegacyBody(): void
    {
        $spec = $this->spec();
        $legacy = [
            'seller_vat_code' => 'RO123',
            'client' => ['name' => 'ACME', 'vat_number' => 'RO999'],
            'series_name' => 'FACT',
            'lines' => [['name' => 'Widget', 'quantity' => 2.0, 'unit_price' => 10.5, 'vat_rate' => 21.0, 'vat_included' => false]],
        ];
        $errors = $spec->validate($legacy, $spec->requestSchema('POST', '/invoices'), true);

        foreach (["missing required property 'issue_date'", "missing required property 'items'", "unknown property 'client'", "unknown property 'series_name'"] as $expected) {
            self::assertNotEmpty(array_filter($errors, static fn (string $e) => str_contains($e, $expected)), "validator did not report: {$expected}");
        }
    }
}
