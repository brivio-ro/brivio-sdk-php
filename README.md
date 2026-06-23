# Brivio PHP SDK (`brivio/sdk`)

Official PHP SDK for the [Brivio](https://brivio.ro) public API — invoicing,
contacts, articles, locations and module entitlements — plus **drop-in
compatibility shims** so you can migrate from **FGO**, **SmartBill**, **Oblio** or **Facturis**
with minimal code changes.

## Install

```bash
composer require brivio/sdk
```

Requires PHP 8.2+, `ext-json`, `ext-curl`.

## Quick start

```php
$brivio = new \Brivio\BrivioClient('brivio_sk_live_...');

$me = $brivio->me();
$contacts = $brivio->listContacts(['search' => 'srl']);
$contact = $brivio->createContact(['name' => 'ACME SRL', 'vat_number' => 'RO123']);

$invoice = $brivio->createInvoice([
    'client' => ['name' => 'ACME SRL', 'vat_number' => 'RO123'],
    'series_name' => 'FACT',
    'currency' => 'RON',
    'lines' => [
        ['name' => 'Consultanță', 'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 21],
    ],
], idempotencyKey: 'order-42'); // optional idempotency
```

Errors throw `\Brivio\BrivioException` (`->errorCode`, `->status`, `->details`).

## Migrating from a competitor (legacy shims)

The `\Brivio\Legacy\*` classes accept the **same payload shape** as the original
provider and translate it to Brivio. In most cases you only change the
constructor and the namespace.

### SmartBill

```php
// Before:  $client = new \SmartBill\Client($email, $token);
$client = new \Brivio\Legacy\SmartBill('brivio_sk_live_...');

$client->createInvoice([
    'companyVatCode' => 'RO123',
    'seriesName' => 'FACT',
    'client' => ['name' => 'ACME', 'vatCode' => 'RO999', 'isTaxPayer' => true],
    'products' => [
        ['name' => 'Widget', 'quantity' => 2, 'price' => 10.5, 'taxPercentage' => 21],
    ],
]);
```

Mapped methods: `createInvoice`, `createEstimate` (proforma).

### FGO

```php
$fgo = new \Brivio\Legacy\Fgo('brivio_sk_live_...');

$fgo->createInvoice([
    'Serie' => 'BV',
    'Client'  => ['Denumire' => 'SC Test SRL', 'CodUnic' => 'RO555', 'Tip' => 'PJ'],
    'Continut' => [
        ['Denumire' => 'Consultanță', 'NrProduse' => 1, 'PretUnitar' => 100, 'CotaTVA' => 21, 'UM' => 'oră'],
    ],
]);
```

Mapped methods: `createInvoice`, `createClient`.

### Oblio

```php
$api = new \Brivio\Legacy\Oblio('brivio_sk_live_...');
$api->setCif('RO777');

$api->createInvoice([
    'seriesName' => 'FACT',
    'client' => ['name' => 'Beta', 'cif' => 'RO888', 'vatPayer' => true],
    'products' => [
        ['name' => 'Serviciu X', 'quantity' => 3, 'price' => 50, 'vatPercentage' => 21, 'productType' => 'Serviciu'],
    ],
]);
```

Mapped methods: `createInvoice`, `createProforma`, `nomenclature('clients'|'products')`.

### Facturis Online

```php
$api = new \Brivio\Legacy\Facturis('brivio_sk_live_...');
$api->setCui('RO123');

$api->saveInvoice([
    'serie' => 'FAC',
    'client_name' => 'Gamma SRL',
    'client_cui' => 'RO321',
    'trimite_efactura' => true,
    'products' => [
        ['denumire' => 'Abonament', 'cantitate' => 2, 'pret' => 40, 'cota_tva' => 21, 'tip' => 'serviciu'],
    ],
]);
```

Mapped methods: `saveInvoice`, `saveProforma`, `listInvoices`, `saveClient`, `saveProduct`.

> The shims cover the **core invoice / contact / article** flows. Less common
> provider methods are intentionally not shimmed; use the native `BrivioClient`
> for everything else. Field names not present in Brivio are ignored.

## Configuration

```php
new \Brivio\BrivioClient($apiKey, baseUrl: 'https://api.brivio.ro/v1');
```

For testing, inject a transport callable:
`new BrivioClient($key, $baseUrl, fn($method,$url,$headers,$body) => ['status'=>200,'body'=>'{...}'])`.

## Development

```bash
composer install
composer test      # PHPUnit
composer analyse   # PHPStan (level 6)
```
