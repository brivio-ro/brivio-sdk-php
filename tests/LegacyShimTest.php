<?php

declare(strict_types=1);

namespace Brivio\Tests;

use Brivio\Legacy\Fgo;
use Brivio\Legacy\Oblio;
use Brivio\Legacy\SmartBill;
use Brivio\Legacy\Facturis;
use Brivio\Tests\Support\FakeTransport;
use PHPUnit\Framework\TestCase;

final class LegacyShimTest extends TestCase
{
    public function testSmartBillCreateInvoiceMapsToCanonical(): void
    {
        $t = new FakeTransport(['data' => ['id' => 'inv1'], 'error' => null], 201);
        $sb = new SmartBill('k', 'https://api.example/v1', $t);

        $sb->createInvoice([
            'companyVatCode' => 'RO123',
            'seriesName' => 'FACT',
            'currency' => 'RON',
            'client' => ['name' => 'ACME', 'vatCode' => 'RO999', 'isTaxPayer' => true],
            'products' => [
                ['name' => 'Widget', 'quantity' => 2, 'price' => 10.5, 'taxPercentage' => 21, 'measuringUnitName' => 'buc'],
            ],
        ]);

        $body = $t->decodedBody();
        self::assertSame('https://api.example/v1/invoices', $t->url);
        self::assertSame('RO123', $body['seller_vat_code']);
        self::assertSame('ACME', $body['client']['name']);
        self::assertTrue($body['client']['vat_payer']);
        self::assertSame('Widget', $body['lines'][0]['name']);
        self::assertSame(2.0, $body['lines'][0]['quantity']);
        self::assertSame(21.0, $body['lines'][0]['vat_rate']);
    }

    public function testFgoCreateInvoiceMapsRoFields(): void
    {
        $t = new FakeTransport(['data' => ['id' => 'inv2'], 'error' => null], 201);
        $fgo = new Fgo('k', 'https://api.example/v1', $t);

        $fgo->createInvoice([
            'Serie' => 'BV',
            'Valuta' => 'RON',
            'Client' => ['Denumire' => 'SC Test SRL', 'CodUnic' => 'RO555', 'Tip' => 'PJ'],
            'Continut' => [
                ['Denumire' => 'Consultanta', 'NrProduse' => 1, 'PretUnitar' => 100, 'CotaTVA' => 21, 'UM' => 'ora'],
            ],
        ]);

        $body = $t->decodedBody();
        self::assertSame('BV', $body['series_name']);
        self::assertSame('SC Test SRL', $body['client']['name']);
        self::assertSame('COMPANY', $body['client']['person_type']);
        self::assertSame('Consultanta', $body['lines'][0]['name']);
        self::assertSame('ora', $body['lines'][0]['unit']);
    }

    public function testOblioCreateInvoiceUsesSetCif(): void
    {
        $t = new FakeTransport(['data' => ['id' => 'inv3'], 'error' => null], 201);
        $oblio = new Oblio('k', 'https://api.example/v1', $t);
        $oblio->setCif('RO777');

        $oblio->createInvoice([
            'seriesName' => 'PROF',
            'client' => ['name' => 'Beta', 'cif' => 'RO888', 'vatPayer' => true],
            'products' => [
                ['name' => 'Serviciu X', 'quantity' => 3, 'price' => 50, 'vatPercentage' => 21, 'productType' => 'Serviciu'],
            ],
        ]);

        $body = $t->decodedBody();
        self::assertSame('RO777', $body['seller_vat_code']);
        self::assertSame('Beta', $body['client']['name']);
        self::assertTrue($body['lines'][0]['is_service']);
    }

    public function testOblioNomenclatureClientsHitsContacts(): void
    {
        $t = new FakeTransport(['data' => [], 'error' => null, 'meta' => []]);
        $oblio = new Oblio('k', 'https://api.example/v1', $t);
        $oblio->nomenclature('clients');

        self::assertSame('https://api.example/v1/contacts', $t->url);
    }

    public function testFacturisSaveInvoiceMapsSnakeCaseFields(): void
    {
        $t = new FakeTransport(['data' => ['id' => 'inv4'], 'error' => null], 201);
        $f = new Facturis('k', 'https://api.example/v1', $t);
        $f->setCui('RO123');

        $f->saveInvoice([
            'serie' => 'FAC',
            'client_name' => 'Gamma SRL',
            'client_cui' => 'RO321',
            'moneda' => 'RON',
            'trimite_efactura' => true,
            'products' => [
                ['denumire' => 'Abonament', 'cantitate' => 2, 'pret' => 40, 'cota_tva' => 21, 'tip' => 'serviciu'],
            ],
        ]);

        $body = $t->decodedBody();
        self::assertSame('https://api.example/v1/invoices', $t->url);
        self::assertSame('RO123', $body['seller_vat_code']);
        self::assertSame('FAC', $body['series_name']);
        self::assertSame('Gamma SRL', $body['client']['name']);
        self::assertSame('RO321', $body['client']['vat_number']);
        self::assertSame('Abonament', $body['lines'][0]['name']);
        self::assertSame(2.0, $body['lines'][0]['quantity']);
        self::assertSame(21.0, $body['lines'][0]['vat_rate']);
        self::assertTrue($body['lines'][0]['is_service']);
        self::assertTrue($body['send_to_spv']);
    }

    public function testFacturisSaveProductHitsArticles(): void
    {
        $t = new FakeTransport(['data' => ['id' => 'art1'], 'error' => null], 201);
        $f = new Facturis('k', 'https://api.example/v1', $t);
        $f->saveProduct(['denumire' => 'Tuns', 'pret' => 50, 'cota_tva' => 21, 'tip' => 'serviciu']);

        self::assertSame('https://api.example/v1/articles', $t->url);
    }
}
