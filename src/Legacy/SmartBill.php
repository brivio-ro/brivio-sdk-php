<?php

declare(strict_types=1);

namespace Brivio\Legacy;

use Brivio\BrivioClient;

/**
 * Drop-in compatibility shim for code written against the SmartBill Cloud REST
 * API. Mirrors SmartBill's resource/method names and request shape, translating
 * to Brivio's native `/v1` API (buyer upserted via `/contacts` with an
 * `external_id`, then `POST /invoices` with `contact_external_id` — see
 * NativeInvoiceBody) so existing integrations migrate with minimal changes.
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
    use NativeInvoiceBody;

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
     * SmartBill: POST /invoice. Accepts the SmartBill invoice payload and
     * creates a DRAFT native invoice.
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function createInvoice(array $payload): array
    {
        return $this->createNativeInvoice($this->mapClient($payload), $this->mapDocument($payload, 'INVOICE'));
    }

    /**
     * SmartBill: POST /estimate (proforma).
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function createEstimate(array $payload): array
    {
        return $this->createNativeInvoice($this->mapClient($payload), $this->mapDocument($payload, 'PROFORMA'));
    }

    /**
     * @param array<string,mixed> $p
     * @return array<string,mixed>
     */
    private function mapClient(array $p): array
    {
        /** @var array<string,mixed> $client */
        $client = is_array($p['client'] ?? null) ? $p['client'] : [];
        return [
            'name' => (string) ($client['name'] ?? ''),
            'vat_code' => $client['vatCode'] ?? null,
            'vat_payer' => self::flag($client['isTaxPayer'] ?? null),
            'registration_number' => $client['regCom'] ?? null,
            'email' => $client['email'] ?? null,
            'phone' => $client['phone'] ?? null,
            'street' => $client['address'] ?? null,
            'city' => $client['city'] ?? null,
            'county' => $client['county'] ?? null,
            'country' => $client['country'] ?? null,
        ];
    }

    /**
     * @param array<string,mixed> $p
     * @return array<string,mixed>
     */
    private function mapDocument(array $p, string $type): array
    {
        /** @var list<SmartBillProduct> $products */
        $products = is_array($p['products'] ?? null) ? $p['products'] : [];

        $lines = [];
        foreach ($products as $i => $row) {
            if (self::flag($row['isDiscount'] ?? null) === true) {
                throw new \InvalidArgumentException(
                    "Brivio SmartBill shim: product {$i} is a discount line (isDiscount); "
                    . 'discount lines are not supported, put the discounted price on the product instead',
                );
            }
            $lines[] = [
                'description' => (string) ($row['name'] ?? ''),
                'quantity' => $row['quantity'] ?? 1,
                'unit_price' => $row['price'] ?? 0,
                'vat_rate' => $row['taxPercentage'] ?? null,
                'vat_included' => self::flag($row['isTaxIncluded'] ?? null) ?? false,
                'unit' => $row['measuringUnitName'] ?? null,
            ];
        }

        return [
            'type' => $type,
            'series' => $p['seriesName'] ?? null,
            'issue_date' => $p['issueDate'] ?? null,
            'due_date' => $p['dueDate'] ?? null,
            'currency' => $p['currency'] ?? null,
            'exchange_rate' => $p['exchangeRate'] ?? null,
            'notes' => $p['mentions'] ?? null,
            'lines' => $lines,
        ];
    }
}
