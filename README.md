# Brivio PHP SDK (`brivio-ro/sdk`)

Official PHP SDK for the [Brivio](https://brivio.ro) public API — invoicing,
contacts, articles, locations and module entitlements — plus **drop-in
compatibility shims** so you can migrate from **FGO**, **SmartBill**, **Oblio** or **Facturis**
with minimal code changes.

## Install

```bash
composer require brivio-ro/sdk
```

## Why this directory has no `package.json`

Deliberate (Grand Sweep P3-06, finding F15). This is a **Composer** package,
not a Node one. `pnpm-workspace.yaml` globs `packages/*`, but pnpm ignores a
directory without a `package.json`, so it costs nothing and adding a stub
`package.json` would put a fake Node package into `pnpm -r` and the Turbo task
graph for no build, test or lint task that could run there.

R-F flagged it as "invisible to pnpm/Turbo/CI". Invisible to pnpm and Turbo —
yes, correctly. **Invisible to CI — no.** It has first-class CI of its own:

| Concern              | Where                                                                      |
| -------------------- | -------------------------------------------------------------------------- |
| PHPUnit + PHPStan    | `.github/workflows/php-sdk.yml` (paths-filtered on `packages/sdk-php/**`)  |
| Release to Packagist | `.github/workflows/release-php-sdk.yml`                                    |
| Pre-commit gate      | `scripts/pre-commit.mjs` runs PHPStan/PHPUnit when `.php` files are staged |
| Affected detection   | `scripts/lib/affected.mjs` (`phpChanged`)                                  |
| OpenAPI drift        | `scripts/openapi-sync.mjs`                                                 |

Decision: **leave as-is.** Revisit only if it ever needs to participate in a
Turbo pipeline.

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

Invoices, payments and e-Factura:

```php
$invoice = $brivio->getInvoice($id);
$brivio->updateInvoice($id, ['status' => 'SENT']);
$brivio->submitInvoiceToANAF($id);              // RO e-Factura
$brivio->charge(['amount' => 1000, 'currency' => 'RON', 'order_id' => 'o1']);
```

Errors throw `\Brivio\BrivioException` (`->errorCode`, `->status`, `->details`).

Webhooks:

```php
$hook = $brivio->createWebhook([
    'url' => 'https://app.example/hooks/brivio',
    'events' => ['invoice.paid', 'contact.created'],
]);
// $hook['secret'] is returned ONCE — store it for signature verification.

// Incoming webhook verification (raw body + headers):
$ok = \Brivio\BrivioClient::verifyWebhookSignature(
    $rawBody,
    $_SERVER['HTTP_X_BRIVIO_SIGNATURE'],   // "sha256=<hex>"
    $secret,
    $_SERVER['HTTP_X_BRIVIO_TIMESTAMP'],   // optional replay protection
);

$deliveries = $brivio->listWebhookDeliveries($hook['id'], ['status' => 'failed']);
$brivio->rotateWebhookSecret($hook['id']);  // returns new secret once
```

Inventory & HR:

```php
$stock = $brivio->listStockLevels(['below_min' => 'true']);   // low-stock report
$moves = $brivio->listStockMovements(['type' => 'SALE']);
$nirs  = $brivio->listNirDocuments(['status' => 'CONFIRMED']);
$nir   = $brivio->getNirDocument($nirs['data'][0]['id']);      // with line items
$staff = $brivio->listEmployees(['department' => 'Vanzari']);  // PII never exposed
```

## API surfaces

Two backends serve the API behind the same key. Core data resources
(`contacts`, `articles`, `invoices`, `documents`, `projects`, `expenses`,
`contracts`, …) use the gateway (`https://api.brivio.ro/v1`, the default).
Stripe/KMS-bound resources (`charge`, catalog, subscriptions) must use the app
surface:

```php
$billing = new \Brivio\BrivioClient('brivio_sk_live_...', 'https://app.brivio.ro/api/v1');
$billing->charge(['amount' => 1000, 'currency' => 'RON', 'order_id' => 'o1']);
```

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
