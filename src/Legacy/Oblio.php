<?php

declare(strict_types=1);

namespace Brivio\Legacy;

use Brivio\BrivioClient;

/**
 * Drop-in compatibility shim for code written against the Oblio API
 * (oblio.eu/api). Mirrors Oblio's method names (createInvoice, nomenclature)
 * and payload (cif, client{}, products[]) and maps them to Brivio's canonical
 * `/v1` API.
 *
 * Oblio original (obliosoftware/oblio-api):
 *   $api = new \OblioSoftware\Api($email, $secret);
 *   $api->setCif($cif);
 *   $api->createInvoice($data);   // data: client{}, products[]
 *
 * Brivio drop-in:
 *   $api = new \Brivio\Legacy\Oblio('brivio_sk_live_...');
 *   $api->setCif($cif);
 *   $api->createInvoice($data);   // same shape
 *
 * @phpstan-type OblioProduct array{name:string, price?:float, measuringUnit?:string, vatPercentage?:float, vatIncluded?:bool, quantity?:float, productType?:string}
 */
final class Oblio
{
    private BrivioClient $brivio;
    private ?string $cif = null;

    public function __construct(string $apiKey, string $baseUrl = 'https://api.brivio.ro/v1', ?callable $transport = null)
    {
        $this->brivio = new BrivioClient($apiKey, $baseUrl, $transport);
    }

    public function client(): BrivioClient
    {
        return $this->brivio;
    }

    public function setCif(string $cif): void
    {
        $this->cif = $cif;
    }

    /**
     * Oblio: createInvoice($data).
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function createInvoice(array $data): array
    {
        return $this->brivio->createInvoice($this->mapInvoice($data));
    }

    /**
     * Oblio: createProforma($data).
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function createProforma(array $data): array
    {
        $invoice = $this->mapInvoice($data);
        $invoice['kind'] = 'proforma';
        return $this->brivio->createInvoice($invoice);
    }

    /**
     * Oblio: nomenclature('clients'|'products'|...). Maps the common cases to
     * Brivio list endpoints; unsupported types throw.
     *
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function nomenclature(string $type): array
    {
        return match ($type) {
            'clients' => $this->brivio->listContacts(),
            'products' => $this->brivio->listArticles(),
            default => throw new \InvalidArgumentException("Unsupported nomenclature type: {$type}"),
        };
    }

    /**
     * @param array<string,mixed> $p
     * @return array<string,mixed>
     */
    private function mapInvoice(array $p): array
    {
        /** @var array<string,mixed> $client */
        $client = is_array($p['client'] ?? null) ? $p['client'] : [];
        /** @var list<OblioProduct> $products */
        $products = is_array($p['products'] ?? null) ? $p['products'] : [];

        $lines = array_map(static function (array $row): array {
            return [
                'name' => $row['name'] ?? '',
                'quantity' => (float) ($row['quantity'] ?? 1),
                'unit_price' => (float) ($row['price'] ?? 0),
                'vat_rate' => (float) ($row['vatPercentage'] ?? 0),
                'vat_included' => (bool) ($row['vatIncluded'] ?? false),
                'unit' => $row['measuringUnit'] ?? 'buc',
                'is_service' => ($row['productType'] ?? null) === 'Serviciu',
            ];
        }, $products);

        return [
            'seller_vat_code' => $p['cif'] ?? $this->cif,
            'client' => [
                'name' => $client['name'] ?? '',
                'vat_number' => $client['cif'] ?? null,
                'registration_number' => $client['rc'] ?? null,
                'vat_payer' => (bool) ($client['vatPayer'] ?? false),
                'save' => (bool) ($client['save'] ?? false),
            ],
            'series_name' => $p['seriesName'] ?? null,
            'currency' => $p['currency'] ?? 'RON',
            'lines' => $lines,
            'send_to_spv' => (bool) ($p['spvExtern'] ?? false),
        ];
    }
}
