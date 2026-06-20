<?php

declare(strict_types=1);

namespace Brivio\Legacy;

use Brivio\BrivioClient;

/**
 * Drop-in compatibility shim for code written against the SmartBill Cloud REST
 * API. Mirrors SmartBill's resource/method names and request shape, translating
 * to Brivio's canonical `/v1` API so existing integrations migrate with minimal
 * changes.
 *
 * SmartBill original:
 *   $client = new \SmartBill\Client($email, $token);
 *   $client->createInvoice($payload);   // payload uses companyVatCode, client{}, products[]
 *
 * Brivio drop-in:
 *   $client = new \Brivio\Legacy\SmartBill('brivio_sk_live_...');
 *   $client->createInvoice($payload);   // same payload shape
 *
 * @phpstan-type SmartBillProduct array{name:string, code?:string, isDiscount?:bool, measuringUnitName?:string, quantity?:float, price?:float, isTaxIncluded?:bool, taxName?:string, taxPercentage?:float, warehouseName?:string, isService?:bool}
 */
final class SmartBill
{
    private BrivioClient $brivio;

    public function __construct(string $apiKey, string $baseUrl = 'https://api.brivio.ro/v1', ?callable $transport = null)
    {
        $this->brivio = new BrivioClient($apiKey, $baseUrl, $transport);
    }

    public function client(): BrivioClient
    {
        return $this->brivio;
    }

    /**
     * SmartBill: POST /invoice. Accepts the SmartBill invoice payload and maps
     * it to Brivio's canonical invoice.
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function createInvoice(array $payload): array
    {
        return $this->brivio->createInvoice($this->mapInvoice($payload));
    }

    /**
     * SmartBill: POST /estimate (proforma).
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function createEstimate(array $payload): array
    {
        $invoice = $this->mapInvoice($payload);
        $invoice['kind'] = 'proforma';
        return $this->brivio->createInvoice($invoice);
    }

    /**
     * Map a SmartBill invoice/estimate payload to the Brivio canonical shape.
     *
     * @param array<string,mixed> $p
     * @return array<string,mixed>
     */
    private function mapInvoice(array $p): array
    {
        /** @var array<string,mixed> $client */
        $client = is_array($p['client'] ?? null) ? $p['client'] : [];
        /** @var list<SmartBillProduct> $products */
        $products = is_array($p['products'] ?? null) ? $p['products'] : [];

        $lines = array_map(static function (array $row): array {
            return [
                'name' => $row['name'] ?? '',
                'code' => $row['code'] ?? null,
                'quantity' => (float) ($row['quantity'] ?? 1),
                'unit_price' => (float) ($row['price'] ?? 0),
                'vat_rate' => (float) ($row['taxPercentage'] ?? 0),
                'vat_included' => (bool) ($row['isTaxIncluded'] ?? false),
                'unit' => $row['measuringUnitName'] ?? 'buc',
                'is_service' => (bool) ($row['isService'] ?? false),
                'is_discount' => (bool) ($row['isDiscount'] ?? false),
            ];
        }, $products);

        return [
            'seller_vat_code' => $p['companyVatCode'] ?? null,
            'client' => [
                'name' => $client['name'] ?? '',
                'vat_number' => $client['vatCode'] ?? null,
                'address' => $client['address'] ?? null,
                'city' => $client['city'] ?? null,
                'county' => $client['county'] ?? null,
                'country' => $client['country'] ?? 'RO',
                'vat_payer' => (bool) ($client['isTaxPayer'] ?? false),
                'save' => (bool) ($client['saveToDb'] ?? false),
            ],
            'series_name' => $p['seriesName'] ?? null,
            'issue_date' => $p['issueDate'] ?? null,
            'due_date' => $p['dueDate'] ?? null,
            'currency' => $p['currency'] ?? 'RON',
            'exchange_rate' => $p['exchangeRate'] ?? null,
            'language' => $p['language'] ?? 'RO',
            'use_stock' => (bool) ($p['useStock'] ?? false),
            'lines' => $lines,
        ];
    }
}
